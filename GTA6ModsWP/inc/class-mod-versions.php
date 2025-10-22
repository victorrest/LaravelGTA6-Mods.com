<?php
/**
 * High performance mod version manager.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralised service for interacting with the mod versions table.
 */
class GTA6Mods_Mod_Versions {
    private const OPTION_MIGRATED = 'gta6mods_mod_versions_migrated';
    private const CACHE_GROUP     = 'mod_versions';
    private const TRANSIENT_LATEST_PREFIX = 'mod_latest_version_';
    private const TRANSIENT_VERSION_PREFIX = 'mod_version_';
    private const TRANSIENT_LOOKUP_PREFIX = 'mod_version_lookup_';
    private const CACHE_TOTAL_PREFIX = 'mod_total_downloads_';
    private static bool $table_checked = false;

    /**
     * Bootstraps hooks.
     */
    public static function init(): void {
        add_action('after_switch_theme', [self::class, 'install']);
        add_action('init', [self::class, 'ensure_table_exists'], 1);
        add_action('admin_init', [self::class, 'register_admin_migration_hooks']);
        add_action('admin_post_gta6mods_run_version_migration', [self::class, 'handle_admin_migration_request']);
        add_action('save_post', [self::class, 'flush_post_cache'], 10, 1);

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('gta6mods migrate-versions', [self::class, 'cli_migrate_versions']);
        }
    }

    /**
     * Returns table name with prefix.
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'mod_versions';
    }

    /**
     * Installs the versions table using dbDelta.
     */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = self::table_name();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mod_id BIGINT UNSIGNED NOT NULL,
            version VARCHAR(50) NOT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            changelog LONGTEXT NULL,
            upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            download_count INT UNSIGNED NOT NULL DEFAULT 0,
            is_latest TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            is_deprecated TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_mod (mod_id),
            KEY idx_version (version),
            KEY idx_latest (mod_id, is_latest),
            KEY idx_upload_date (mod_id, upload_date DESC),
            KEY idx_deprecated (mod_id, is_deprecated)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Checks table existence at runtime.
     */
    public static function ensure_table_exists(): void {
        global $wpdb;

        if (self::$table_checked) {
            return;
        }

        self::$table_checked = true;

        $table_name   = self::table_name();
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        if ($table_name !== $table_exists) {
            self::install();
        }
    }

    /**
     * Migrates legacy post meta data into the versions table.
     *
     * @return array{processed:int,remaining:int}
     */
    public static function run_initial_migration_batch(int $limit = 250): array {
        $result = [
            'processed' => 0,
            'remaining' => 0,
        ];

        if (self::is_migrated()) {
            return $result;
        }

        $limit = max(1, $limit);

        global $wpdb;

        $table = self::table_name();

        $mods_sql = $wpdb->prepare(
            "SELECT p.ID AS id, pm.meta_value AS file_meta FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s)
            LEFT JOIN {$table} v ON v.mod_id = p.ID
            WHERE p.post_type = %s AND v.id IS NULL
            ORDER BY p.ID ASC
            LIMIT %d",
            '_gta6mods_mod_file',
            'post',
            $limit
        );

        $mods = $wpdb->get_results($mods_sql, ARRAY_A);

        if (empty($mods)) {
            update_option(self::OPTION_MIGRATED, 1, false);

            return $result;
        }

        foreach ($mods as $mod_row) {
            $mod_id    = (int) $mod_row['id'];
            $file_meta = maybe_unserialize($mod_row['file_meta']);

            if ($mod_id <= 0 || !is_array($file_meta)) {
                continue;
            }

            $version_number = get_post_meta($mod_id, '_gta6mods_mod_version', true);
            if (!is_string($version_number) || '' === $version_number) {
                $version_number = '1.0.0';
            }

            $changelog     = get_post_meta($mod_id, '_gta6mods_mod_changelog', true);
            $attachment_id = isset($file_meta['attachment_id']) ? (int) $file_meta['attachment_id'] : 0;

            if ($attachment_id <= 0 && isset($file_meta['id'])) {
                $attachment_id = (int) $file_meta['id'];
            }

            $existing_version = self::get_latest_version($mod_id);
            if (!empty($existing_version)) {
                continue;
            }

            $inserted = self::insert_version(
                $mod_id,
                $version_number,
                $attachment_id > 0 ? $attachment_id : null,
                is_string($changelog) ? $changelog : ''
            );

            if ($inserted > 0) {
                self::mark_latest($mod_id, $inserted);
                $result['processed']++;
            }
        }

        $remaining = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s)
                LEFT JOIN {$table} v ON v.mod_id = p.ID
                WHERE p.post_type = %s AND v.id IS NULL",
                '_gta6mods_mod_file',
                'post'
            )
        );

        $result['remaining'] = $remaining;

        if ($remaining <= 0) {
            update_option(self::OPTION_MIGRATED, 1, false);
        }

        return $result;
    }

    /**
     * Returns whether the legacy migration finished.
     */
    public static function is_migrated(): bool {
        return (bool) get_option(self::OPTION_MIGRATED, false);
    }

    /**
     * Registers admin-only helpers for running the migration manually.
     */
    public static function register_admin_migration_hooks(): void {
        self::ensure_table_exists();

        if (!current_user_can('manage_options')) {
            return;
        }

        $result_flag = filter_input(INPUT_GET, 'gta6mods_migration_result', FILTER_SANITIZE_NUMBER_INT);
        if ($result_flag) {
            add_action('admin_notices', [self::class, 'render_migration_result_notice']);
        }

        if (self::is_migrated()) {
            return;
        }

        add_action('admin_notices', [self::class, 'render_migration_notice']);
    }

    /**
     * Outputs the migration reminder notice.
     */
    public static function render_migration_notice(): void {
        if (!current_user_can('manage_options') || self::is_migrated()) {
            return;
        }

        $action_url = wp_nonce_url(
            admin_url('admin-post.php?action=gta6mods_run_version_migration'),
            'gta6mods_run_version_migration'
        );

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'The new waiting room download system requires migrating existing mod files into the versions table.',
            'gta6-mods'
        );
        echo '</p><p><a class="button button-primary" href="' . esc_url($action_url) . '">';
        esc_html_e('Run migration now', 'gta6-mods');
        echo '</a></p></div>';
    }

    /**
     * Outputs the result notice after a manual migration run.
     */
    public static function render_migration_result_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $processed = filter_input(INPUT_GET, 'gta6mods_migration_processed', FILTER_SANITIZE_NUMBER_INT);
        $remaining = filter_input(INPUT_GET, 'gta6mods_migration_remaining', FILTER_SANITIZE_NUMBER_INT);

        $processed = $processed ? absint($processed) : 0;
        $remaining = $remaining ? absint($remaining) : 0;

        $class = $remaining > 0 ? 'notice-info' : 'notice-success';

        echo '<div class="notice ' . esc_attr($class) . '"><p>';
        printf(
            /* translators: 1: processed count, 2: remaining count */
            esc_html__('Version migration processed %1$d records. Remaining: %2$d.', 'gta6-mods'),
            $processed,
            $remaining
        );
        echo '</p></div>';
    }

    /**
     * Handles the admin-post migration trigger.
     */
    public static function handle_admin_migration_request(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'gta6-mods'));
        }

        check_admin_referer('gta6mods_run_version_migration');

        $batch = filter_input(INPUT_GET, 'batch', FILTER_SANITIZE_NUMBER_INT);
        if (!$batch) {
            $batch = filter_input(INPUT_POST, 'batch', FILTER_SANITIZE_NUMBER_INT);
        }
        $batch_size = $batch ? max(1, absint($batch)) : 500;

        $result = self::run_initial_migration_batch($batch_size);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url();
        }

        $redirect = remove_query_arg(
            ['gta6mods_migration_result', 'gta6mods_migration_processed', 'gta6mods_migration_remaining'],
            $redirect
        );

        $redirect = add_query_arg(
            [
                'gta6mods_migration_result'     => 1,
                'gta6mods_migration_processed'  => $result['processed'],
                'gta6mods_migration_remaining' => $result['remaining'],
            ],
            $redirect
        );

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * WP-CLI command for running the migration.
     *
     * @param array $args Positional args.
     * @param array $assoc_args Associative args.
     */
    public static function cli_migrate_versions(array $args, array $assoc_args): void {
        if (self::is_migrated()) {
            WP_CLI::success(__('Mod version data already migrated.', 'gta6-mods'));

            return;
        }

        $batch_size = isset($assoc_args['batch-size']) ? max(1, (int) $assoc_args['batch-size']) : 500;
        $total_processed = 0;

        do {
            $result = self::run_initial_migration_batch($batch_size);
            $total_processed += $result['processed'];
        } while ($result['processed'] > 0 && $result['remaining'] > 0);

        WP_CLI::success(sprintf(__('Migration completed. Processed %d records.', 'gta6-mods'), $total_processed));
    }

    /**
     * Retrieves a single version by ID with caching.
     */
    public static function get_version(int $version_id): ?array {
        if ($version_id <= 0) {
            return null;
        }

        $cache_key = self::TRANSIENT_VERSION_PREFIX . $version_id;

        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached) {
            return $cached;
        }

        $cached = get_transient($cache_key);
        if (false !== $cached && is_array($cached)) {
            wp_cache_set($cache_key, $cached, self::CACHE_GROUP, 12 * HOUR_IN_SECONDS);
            return $cached;
        }

        global $wpdb;

        $table = self::table_name();
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, mod_id, version, attachment_id, changelog, upload_date, download_count, is_latest, is_deprecated
                FROM {$table}
                WHERE id = %d",
                $version_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['id']            = (int) $row['id'];
        $row['mod_id']        = (int) $row['mod_id'];
        $row['attachment_id'] = (int) $row['attachment_id'];
        $row['download_count'] = (int) $row['download_count'];
        $row['is_latest']     = (int) $row['is_latest'];
        $row['is_deprecated'] = (int) $row['is_deprecated'];

        set_transient($cache_key, $row, 12 * HOUR_IN_SECONDS);
        wp_cache_set($cache_key, $row, self::CACHE_GROUP, 12 * HOUR_IN_SECONDS);

        return $row;
    }

    /**
     * Retrieves latest version for a mod.
     */
    public static function get_latest_version(int $mod_id): ?array {
        if ($mod_id <= 0) {
            return null;
        }

        $cache_key = self::TRANSIENT_LATEST_PREFIX . $mod_id;

        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached) {
            return $cached;
        }

        $cached = get_transient($cache_key);
        if (false !== $cached && is_array($cached)) {
            wp_cache_set($cache_key, $cached, self::CACHE_GROUP, 12 * HOUR_IN_SECONDS);
            return $cached;
        }

        global $wpdb;

        $table  = self::table_name();
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, mod_id, version, attachment_id, changelog, upload_date, download_count, is_latest, is_deprecated
                FROM {$table}
                WHERE mod_id = %d AND is_latest = 1
                ORDER BY upload_date DESC
                LIMIT 1",
                $mod_id
            ),
            ARRAY_A
        );

        if (!$result) {
            return null;
        }

        $result['id']            = (int) $result['id'];
        $result['mod_id']        = (int) $result['mod_id'];
        $result['attachment_id'] = (int) $result['attachment_id'];
        $result['download_count'] = (int) $result['download_count'];
        $result['is_latest']     = (int) $result['is_latest'];
        $result['is_deprecated'] = (int) $result['is_deprecated'];

        set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, 12 * HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Retrieves version history.
     */
    public static function get_versions_for_mod(int $mod_id, int $limit = 0): array {
        if ($mod_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = self::table_name();

        $sql = "SELECT id, mod_id, version, attachment_id, changelog, upload_date, download_count, is_latest, is_deprecated
            FROM {$table}
            WHERE mod_id = %d
            ORDER BY upload_date DESC";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        $rows = $wpdb->get_results($wpdb->prepare($sql, $mod_id), ARRAY_A);
        if (!$rows) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['id']            = (int) $row['id'];
            $row['mod_id']        = (int) $row['mod_id'];
            $row['attachment_id'] = (int) $row['attachment_id'];
            $row['download_count'] = (int) $row['download_count'];
            $row['is_latest']     = (int) $row['is_latest'];
            $row['is_deprecated'] = (int) $row['is_deprecated'];
        }

        return $rows;
    }

    /**
     * Returns the summed download count for a mod across all versions.
     */
    public static function get_total_downloads_for_mod(int $mod_id): int {
        if ($mod_id <= 0) {
            return 0;
        }

        $cache_key = self::CACHE_TOTAL_PREFIX . $mod_id;
        $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached) {
            return (int) $cached;
        }

        global $wpdb;

        $table = self::table_name();
        $sum   = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(download_count) FROM {$table} WHERE mod_id = %d",
                $mod_id
            )
        );

        $total = $sum ? (int) $sum : 0;

        wp_cache_set($cache_key, $total, self::CACHE_GROUP, HOUR_IN_SECONDS);

        return $total;
    }

    /**
     * Inserts a new version row.
     */
    public static function insert_version(int $mod_id, string $version, ?int $attachment_id, string $changelog = '', ?string $upload_date = null, bool $is_latest = false): int {
        if ($mod_id <= 0 || '' === trim($version)) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        $data = [
            'mod_id'        => $mod_id,
            'version'       => substr($version, 0, 50),
            'attachment_id' => $attachment_id ? $attachment_id : null,
            'changelog'     => $changelog,
            'upload_date'   => $upload_date ? $upload_date : current_time('mysql', true),
            'download_count' => 0,
            'is_latest'     => $is_latest ? 1 : 0,
        ];

        $formats = ['%d', '%s', '%d', '%s', '%s', '%d', '%d'];

        $inserted = $wpdb->insert($table, $data, $formats);

        if (false === $inserted) {
            return 0;
        }

        $version_id = (int) $wpdb->insert_id;

        self::flush_cache($mod_id);
        self::flush_lookup_cache($mod_id, $version);

        if ($attachment_id) {
            self::flush_lookup_cache($mod_id, 'attachment:' . (int) $attachment_id);
        }

        if ($is_latest) {
            self::mark_latest($mod_id, $version_id);
        }

        if (function_exists('gta6mods_bump_version_cache_nonce')) {
            gta6mods_bump_version_cache_nonce($version_id);
        }

        do_action('gta6mods_version_updated', $mod_id, $version_id);

        return $version_id;
    }

    /**
     * Retrieves a version row by mod and version string.
     */
    public static function get_version_by_mod_and_number(int $mod_id, string $version): ?array {
        if ($mod_id <= 0) {
            return null;
        }

        $normalized = self::normalize_version_string($version);
        if ('' === $normalized) {
            return null;
        }

        $cache_key     = self::build_lookup_cache_key($mod_id, $normalized);
        $transient_key = self::TRANSIENT_LOOKUP_PREFIX . $cache_key;

        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached) {
            return isset($cached['__miss']) ? null : $cached;
        }

        $transient = get_transient($transient_key);
        if (false !== $transient) {
            wp_cache_set($cache_key, $transient, self::CACHE_GROUP, 6 * HOUR_IN_SECONDS);
            return isset($transient['__miss']) ? null : $transient;
        }

        global $wpdb;
        $table = self::table_name();
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, mod_id, version, attachment_id, changelog, upload_date, download_count, is_latest, is_deprecated FROM {$table} WHERE mod_id = %d AND LOWER(version) = %s ORDER BY upload_date DESC LIMIT 1",
                $mod_id,
                $normalized
            ),
            ARRAY_A
        );

        if (!$row) {
            $miss = ['__miss' => true];
            set_transient($transient_key, $miss, HOUR_IN_SECONDS);
            wp_cache_set($cache_key, $miss, self::CACHE_GROUP, HOUR_IN_SECONDS);
            return null;
        }

        $row['id']            = (int) $row['id'];
        $row['mod_id']        = (int) $row['mod_id'];
        $row['attachment_id'] = (int) $row['attachment_id'];
        $row['download_count'] = (int) $row['download_count'];
        $row['is_latest']     = (int) $row['is_latest'];
        $row['is_deprecated'] = (int) $row['is_deprecated'];

        set_transient($transient_key, $row, 6 * HOUR_IN_SECONDS);
        wp_cache_set($cache_key, $row, self::CACHE_GROUP, 6 * HOUR_IN_SECONDS);

        return $row;
    }

    /**
     * Retrieves a version row by mod and attachment ID.
     */
    public static function get_version_by_mod_and_attachment(int $mod_id, int $attachment_id): ?array {
        if ($mod_id <= 0 || $attachment_id <= 0) {
            return null;
        }

        $normalized   = 'attachment:' . $attachment_id;
        $cache_key     = self::build_lookup_cache_key($mod_id, $normalized);
        $transient_key = self::TRANSIENT_LOOKUP_PREFIX . $cache_key;

        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached) {
            return isset($cached['__miss']) ? null : $cached;
        }

        $transient = get_transient($transient_key);
        if (false !== $transient) {
            wp_cache_set($cache_key, $transient, self::CACHE_GROUP, 6 * HOUR_IN_SECONDS);
            return isset($transient['__miss']) ? null : $transient;
        }

        global $wpdb;
        $table = self::table_name();
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, mod_id, version, attachment_id, changelog, upload_date, download_count, is_latest, is_deprecated FROM {$table} WHERE mod_id = %d AND attachment_id = %d ORDER BY upload_date DESC LIMIT 1",
                $mod_id,
                $attachment_id
            ),
            ARRAY_A
        );

        if (!$row) {
            $miss = ['__miss' => true];
            set_transient($transient_key, $miss, HOUR_IN_SECONDS);
            wp_cache_set($cache_key, $miss, self::CACHE_GROUP, HOUR_IN_SECONDS);
            return null;
        }

        $row['id']             = (int) $row['id'];
        $row['mod_id']         = (int) $row['mod_id'];
        $row['attachment_id']  = (int) $row['attachment_id'];
        $row['download_count'] = (int) $row['download_count'];
        $row['is_latest']      = (int) $row['is_latest'];
        $row['is_deprecated']  = (int) $row['is_deprecated'];

        set_transient($transient_key, $row, 6 * HOUR_IN_SECONDS);
        wp_cache_set($cache_key, $row, self::CACHE_GROUP, 6 * HOUR_IN_SECONDS);

        return $row;
    }

    private static function normalize_version_string(string $version): string {
        $normalized = trim($version);
        if ('' === $normalized) {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($normalized, 'UTF-8');
        }

        return strtolower($normalized);
    }

    private static function build_lookup_cache_key(int $mod_id, string $normalized): string {
        return 'lookup_' . $mod_id . '_' . md5($normalized);
    }

    /**
     * Marks a specific version as latest inside a transaction.
     */
    public static function mark_latest(int $mod_id, int $version_id): bool {
        if ($mod_id <= 0 || $version_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $wpdb->query('START TRANSACTION');

        $unset = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET is_latest = 0 WHERE mod_id = %d",
                $mod_id
            )
        );

        $set = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET is_latest = 1 WHERE id = %d",
                $version_id
            )
        );

        if (false === $unset || false === $set) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');

        self::flush_cache($mod_id);
        self::flush_version_cache($version_id);

        if (function_exists('gta6mods_bump_version_cache_nonce')) {
            gta6mods_bump_version_cache_nonce($version_id);
        }

        do_action('gta6mods_version_updated', $mod_id, $version_id);

        return true;
    }

    /**
     * Increments download count atomically.
     */
    public static function increment_download_count(int $version_id) {
        if ($version_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET download_count = download_count + 1 WHERE id = %d",
                $version_id
            )
        );

        if (false === $result) {
            return false;
        }

        self::flush_version_cache($version_id);

        $version = self::get_version($version_id);
        if ($version) {
            self::flush_cache((int) $version['mod_id']);
            if (isset($version['version'])) {
                self::flush_lookup_cache((int) $version['mod_id'], (string) $version['version']);
            }
            if (!empty($version['attachment_id'])) {
                self::flush_lookup_cache((int) $version['mod_id'], 'attachment:' . (int) $version['attachment_id']);
            }
            return (int) $version['download_count'];
        }

        return false;
    }

    /**
     * Flags a version as deprecated.
     */
    public static function set_deprecated(int $version_id, bool $deprecated = true): bool {
        if ($version_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        $result = $wpdb->update(
            $table,
            ['is_deprecated' => $deprecated ? 1 : 0],
            ['id' => $version_id],
            ['%d'],
            ['%d']
        );

        if (false === $result) {
            return false;
        }

        $version = self::get_version($version_id);
        if ($version) {
            self::flush_cache((int) $version['mod_id']);
            self::flush_version_cache($version_id);
            if (isset($version['version'])) {
                self::flush_lookup_cache((int) $version['mod_id'], (string) $version['version']);
            }
            if (!empty($version['attachment_id'])) {
                self::flush_lookup_cache((int) $version['mod_id'], 'attachment:' . (int) $version['attachment_id']);
            }
            if (function_exists('gta6mods_bump_version_cache_nonce')) {
                gta6mods_bump_version_cache_nonce($version_id);
            }
            do_action('gta6mods_version_updated', (int) $version['mod_id'], $version_id);
        }

        return true;
    }

    /**
     * Flushes caches for a mod.
     */
    public static function flush_cache(int $mod_id): void {
        if ($mod_id <= 0) {
            return;
        }

        $key = self::TRANSIENT_LATEST_PREFIX . $mod_id;
        delete_transient($key);
        wp_cache_delete($key, self::CACHE_GROUP);
        wp_cache_delete(self::CACHE_TOTAL_PREFIX . $mod_id, self::CACHE_GROUP);
    }

    /**
     * Flushes lookup caches for a mod/version pair.
     */
    public static function flush_lookup_cache(int $mod_id, string $version): void {
        if ($mod_id <= 0) {
            return;
        }

        $normalized = self::normalize_version_string($version);
        if ('' === $normalized) {
            return;
        }

        $cache_key     = self::build_lookup_cache_key($mod_id, $normalized);
        $transient_key = self::TRANSIENT_LOOKUP_PREFIX . $cache_key;

        delete_transient($transient_key);
        wp_cache_delete($cache_key, self::CACHE_GROUP);
    }

    /**
     * Flushes caches for a version.
     */
    public static function flush_version_cache(int $version_id): void {
        if ($version_id <= 0) {
            return;
        }

        $key = self::TRANSIENT_VERSION_PREFIX . $version_id;
        delete_transient($key);
        wp_cache_delete($key, self::CACHE_GROUP);
    }

    /**
     * Flush caches when mod post updated.
     */
    public static function flush_post_cache(int $post_id): void {
        if ('post' !== get_post_type($post_id)) {
            return;
        }

        self::flush_cache((int) $post_id);
    }

    /**
     * Returns download stats for analytics.
     */
    public static function get_download_stats(): array {
        global $wpdb;
        $table = self::table_name();

        $totals = $wpdb->get_row("SELECT SUM(download_count) AS total, MAX(download_count) AS max_count FROM {$table}", ARRAY_A);
        if (!$totals) {
            return [
                'total' => 0,
                'max'   => 0,
            ];
        }

        return [
            'total' => (int) $totals['total'],
            'max'   => (int) $totals['max_count'],
        ];
    }

    /**
     * Returns the most downloaded version across all mods.
     */
    public static function get_most_popular_version(): ?array {
        global $wpdb;
        $table = self::table_name();
        $posts = $wpdb->posts;

        $row = $wpdb->get_row(
            "SELECT v.id, v.mod_id, v.version, v.download_count, v.upload_date, p.post_title
            FROM {$table} v
            LEFT JOIN {$posts} p ON p.ID = v.mod_id
            ORDER BY v.download_count DESC
            LIMIT 1",
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['id']             = (int) $row['id'];
        $row['mod_id']         = (int) $row['mod_id'];
        $row['download_count'] = (int) $row['download_count'];

        return $row;
    }

    /**
     * Returns the top versions ordered by downloads.
     */
    public static function get_top_versions(int $limit = 7): array {
        global $wpdb;
        $table = self::table_name();
        $posts = $wpdb->posts;

        $limit = max(1, $limit);

        $sql = $wpdb->prepare(
            "SELECT v.id, v.mod_id, v.version, v.download_count, v.upload_date, p.post_title
            FROM {$table} v
            LEFT JOIN {$posts} p ON p.ID = v.mod_id
            ORDER BY v.download_count DESC
            LIMIT %d",
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!$rows) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['id']             = (int) $row['id'];
            $row['mod_id']         = (int) $row['mod_id'];
            $row['download_count'] = (int) $row['download_count'];
        }

        return $rows;
    }
}

GTA6Mods_Mod_Versions::init();
