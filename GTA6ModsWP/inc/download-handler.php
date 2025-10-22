<?php
/**
 * Secure download handler and helper utilities.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GTA6MODS_DOWNLOAD_TOKEN_TTL')) {
    define('GTA6MODS_DOWNLOAD_TOKEN_TTL', 600);
}

if (!defined('GTA6MODS_DOWNLOAD_RATE_LIMIT')) {
    define('GTA6MODS_DOWNLOAD_RATE_LIMIT', 3);
}

if (!defined('GTA6MODS_USE_XACCEL')) {
    define('GTA6MODS_USE_XACCEL', false);
}

if (!defined('GTA6MODS_XACCEL_PREFIX')) {
    define('GTA6MODS_XACCEL_PREFIX', '/protected-files/');
}

if (!defined('GTA6MODS_DOWNLOAD_QUEUE_TTL')) {
    define('GTA6MODS_DOWNLOAD_QUEUE_TTL', 600);
}

/**
 * Builds the cache key for a token payload.
 */
function gta6mods_download_token_cache_key(string $token): string {
    return 'gta6mods_download_' . md5($token);
}

/**
 * Stores a short lived permission for no-JavaScript downloads.
 */
function gta6mods_store_nojs_permission(int $mod_id, int $version_id): void {
    $mod_id     = absint($mod_id);
    $version_id = absint($version_id);

    if ($mod_id <= 0 || $version_id <= 0) {
        return;
    }

    $fingerprint = gta6mods_get_rate_limit_fingerprint('waiting_room_nojs');
    if ('' === $fingerprint) {
        return;
    }

    $key = sprintf('%s_%d', $fingerprint, $version_id);

    gta6mods_cache_set(
        $key,
        [
            'mod_id'     => $mod_id,
            'version_id' => $version_id,
            'timestamp'  => time(),
        ],
        'gta6mods_waiting_room_nojs',
        2 * MINUTE_IN_SECONDS
    );
}

/**
 * Consumes the stored permission for a no-JavaScript download attempt.
 *
 * @return array{mod_id:int,version_id:int}|null
 */
function gta6mods_consume_nojs_permission(int $version_id): ?array {
    $version_id = absint($version_id);

    if ($version_id <= 0) {
        return null;
    }

    $fingerprint = gta6mods_get_rate_limit_fingerprint('waiting_room_nojs');
    if ('' === $fingerprint) {
        return null;
    }

    $key  = sprintf('%s_%d', $fingerprint, $version_id);
    $data = gta6mods_cache_get($key, 'gta6mods_waiting_room_nojs');

    if (!is_array($data) || (int) ($data['version_id'] ?? 0) !== $version_id) {
        return null;
    }

    gta6mods_cache_delete($key, 'gta6mods_waiting_room_nojs');

    return [
        'mod_id'     => (int) ($data['mod_id'] ?? 0),
        'version_id' => $version_id,
    ];
}

/**
 * Generates a secure download token for the given version.
 *
 * @param int $version_id Version identifier.
 *
 * @return array{token: string, expires_at: int, download_url: string}|WP_Error
 */
function gta6mods_generate_download_token(int $version_id) {
    $version = GTA6Mods_Mod_Versions::get_version($version_id);
    if (!$version) {
        return new WP_Error('invalid_version', __('Érvénytelen verzió.', 'gta6-mods'), ['status' => 404]);
    }

    $namespace   = 'token_' . $version_id;
    $now         = time();
    $fingerprint = gta6mods_get_rate_limit_fingerprint($namespace, $now, GTA6MODS_DOWNLOAD_TOKEN_TTL);

    $expires = $now + GTA6MODS_DOWNLOAD_TOKEN_TTL;
    $random  = wp_generate_uuid4();
    $payload = $version_id . '|' . $expires . '|' . $random;
    $signature = hash_hmac('sha256', $payload, wp_salt('auth'));
    $token_raw = $payload . '|' . $signature;
    $token     = rtrim(strtr(base64_encode($token_raw), '+/', '-_'), '=');

    $cache_key = gta6mods_download_token_cache_key($token);
    $data      = [
        'version_id'  => $version_id,
        'expires'     => $expires,
        'namespace'   => $namespace,
        'fingerprint' => $fingerprint,
        'fingerprint_timestamp' => $now,
        'fingerprint_window'    => GTA6MODS_DOWNLOAD_TOKEN_TTL,
    ];

    gta6mods_cache_set($cache_key, $data, 'gta6mods_download_tokens', GTA6MODS_DOWNLOAD_TOKEN_TTL);

    $download_url = add_query_arg(
        [
            'token' => rawurlencode($token),
            'vid'   => $version_id,
        ],
        home_url('/download-file/')
    );

    return [
        'token'        => $token,
        'expires_at'   => $expires,
        'download_url' => $download_url,
    ];
}

/**
 * Validates a download token and returns the version data when successful.
 *
 * @param string $token      Token string.
 * @param int    $version_id Version identifier from the query string.
 *
 * @return array|WP_Error
 */
function gta6mods_validate_download_token(string $token, int $version_id) {
    if ('' === $token || $version_id <= 0) {
        return new WP_Error('invalid_token', __('Érvénytelen letöltési token.', 'gta6-mods'), ['status' => 403]);
    }

    $cache_key = gta6mods_download_token_cache_key($token);
    $cached    = gta6mods_cache_get($cache_key, 'gta6mods_download_tokens');

    if (!is_array($cached) || empty($cached['expires']) || (int) $cached['version_id'] !== $version_id) {
        return new WP_Error('invalid_token', __('A token érvénytelen vagy lejárt.', 'gta6-mods'), ['status' => 403]);
    }

    if ((int) $cached['expires'] < time()) {
        gta6mods_cache_delete($cache_key, 'gta6mods_download_tokens');
        return new WP_Error('token_expired', __('A token lejárt.', 'gta6-mods'), ['status' => 403]);
    }

    $decoded = base64_decode(strtr($token, '-_', '+/'));
    if (!$decoded) {
        return new WP_Error('invalid_token', __('A token formátuma hibás.', 'gta6-mods'), ['status' => 403]);
    }

    $parts = explode('|', $decoded);
    if (count($parts) !== 4) {
        return new WP_Error('invalid_token', __('A token formátuma hibás.', 'gta6-mods'), ['status' => 403]);
    }

    [$version_part, $expires_part, $random_part, $signature_part] = $parts;
    $expected_payload   = $version_part . '|' . $expires_part . '|' . $random_part;
    $expected_signature = hash_hmac('sha256', $expected_payload, wp_salt('auth'));

    if (!hash_equals($expected_signature, $signature_part)) {
        return new WP_Error('invalid_token', __('A token aláírása hibás.', 'gta6-mods'), ['status' => 403]);
    }

    $expected_namespace = 'token_' . $version_id;

    if (($cached['namespace'] ?? $expected_namespace) !== $expected_namespace) {
        return new WP_Error('invalid_token', __('A token nem egyezik a kért verzióval.', 'gta6-mods'), ['status' => 403]);
    }

    $stored_fingerprint  = isset($cached['fingerprint']) ? (string) $cached['fingerprint'] : '';
    $fingerprint_time    = isset($cached['fingerprint_timestamp']) ? (int) $cached['fingerprint_timestamp'] : 0;
    $fingerprint_window  = isset($cached['fingerprint_window']) ? (int) $cached['fingerprint_window'] : GTA6MODS_DOWNLOAD_TOKEN_TTL;
    if ($fingerprint_window <= 0) {
        $fingerprint_window = GTA6MODS_DOWNLOAD_TOKEN_TTL;
    }

    $seed_time           = $fingerprint_time > 0 ? $fingerprint_time : null;
    $current_fingerprint = gta6mods_get_rate_limit_fingerprint($expected_namespace, $seed_time, $fingerprint_window);

    if ('' !== $stored_fingerprint && '' !== $current_fingerprint && !hash_equals($stored_fingerprint, $current_fingerprint)) {
        if ($fingerprint_time <= 0) {
            $fallback_fingerprint = gta6mods_get_rate_limit_fingerprint($expected_namespace);

            if ('' === $fallback_fingerprint || !hash_equals($stored_fingerprint, $fallback_fingerprint)) {
                return new WP_Error('invalid_token', __('A token környezeti változás miatt érvényét veszítette.', 'gta6-mods'), ['status' => 403]);
            }

            $current_fingerprint = $fallback_fingerprint;
        } else {
            return new WP_Error('invalid_token', __('A token környezeti változás miatt érvényét veszítette.', 'gta6-mods'), ['status' => 403]);
        }
    }

    if ((int) $version_part !== $version_id || (int) $expires_part !== (int) $cached['expires']) {
        return new WP_Error('invalid_token', __('A token nem egyezik a kért verzióval.', 'gta6-mods'), ['status' => 403]);
    }

    $version = GTA6Mods_Mod_Versions::get_version($version_id);
    if (!$version) {
        return new WP_Error('invalid_version', __('A kért verzió már nem elérhető.', 'gta6-mods'), ['status' => 410]);
    }

    $allowance_fingerprint = '' !== $stored_fingerprint ? $stored_fingerprint : $current_fingerprint;
    gta6mods_grant_download_allowance($expected_namespace, $allowance_fingerprint);

    return $version;
}

/**
 * Grants a temporary rate-limit allowance for the given namespace.
 */
function gta6mods_grant_download_allowance(string $namespace, string $fingerprint = '', ?int $ttl = null): void {
    $namespace = trim($namespace);
    if ('' === $namespace) {
        return;
    }

    if (null === $ttl || $ttl <= 0) {
        $ttl = GTA6MODS_DOWNLOAD_TOKEN_TTL;
    }

    if ('' === $fingerprint) {
        $fingerprint = gta6mods_get_rate_limit_fingerprint($namespace);
    }

    if ('' === $fingerprint) {
        return;
    }

    gta6mods_cache_set('allow_' . $fingerprint, 1, 'gta6mods_rate_limit_allow', $ttl);
}

/**
 * Simple IP-based rate limiter with Redis/Transient fallback.
 *
 * @param string $namespace Identifier for limiter group.
 *
 * @return bool True when the request is allowed.
 */
function gta6mods_download_rate_limiter_allow(string $namespace = 'download'): bool {
    $fingerprint = gta6mods_get_rate_limit_fingerprint($namespace);
    if ('' === $fingerprint) {
        return true;
    }

    if (false !== gta6mods_cache_get('allow_' . $fingerprint, 'gta6mods_rate_limit_allow', false)) {
        return true;
    }

    $hash  = $fingerprint;
    $group = 'gta6mods_rate_limit';
    $limit = defined('GTA6MODS_DOWNLOAD_RATE_LIMIT') ? (int) GTA6MODS_DOWNLOAD_RATE_LIMIT : 3;

    if (function_exists('gta6mods_waiting_room_is_reduced_security') && gta6mods_waiting_room_is_reduced_security()) {
        $limit = (int) apply_filters('gta6mods_waiting_room_reduced_rate_limit', max($limit * 3, $limit + 5));
    }

    if ($limit <= 0) {
        return true;
    }

    if (wp_using_ext_object_cache()) {
        $count = (int) gta6mods_cache_incr($hash, 1, $group, MINUTE_IN_SECONDS);

        return $count <= $limit;
    }

    $bucket = gta6mods_cache_get($hash, $group, []);

    if (!is_array($bucket) || !isset($bucket['tokens'], $bucket['timestamp'])) {
        $bucket = [
            'tokens'    => $limit - 1,
            'timestamp' => time(),
        ];
        gta6mods_cache_set($hash, $bucket, $group, MINUTE_IN_SECONDS);

        return true;
    }

    $elapsed = time() - (int) $bucket['timestamp'];
    if ($elapsed >= MINUTE_IN_SECONDS) {
        $bucket['tokens']    = $limit - 1;
        $bucket['timestamp'] = time();
        gta6mods_cache_set($hash, $bucket, $group, MINUTE_IN_SECONDS);

        return true;
    }

    if ((int) $bucket['tokens'] <= 0) {
        return false;
    }

    $bucket['tokens'] = (int) $bucket['tokens'] - 1;
    gta6mods_cache_set($hash, $bucket, $group, MINUTE_IN_SECONDS - $elapsed);

    return true;
}

/**
 * Handles secure download streaming.
 */
function gta6mods_handle_secure_download(): void {
    if (!get_query_var('gta6mods_download')) {
        return;
    }

    $token      = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    $version_id = isset($_GET['vid']) ? absint($_GET['vid']) : 0;
    $nojs_mode  = isset($_GET['nojs']) && absint($_GET['nojs']) === 1;

    if ($version_id <= 0) {
        wp_die(__('Érvénytelen verzióazonosító.', 'gta6-mods'), __('Letöltés hiba', 'gta6-mods'), ['response' => 400]);
    }

    $rate_namespace = $nojs_mode ? 'nojs_' . $version_id : 'token_' . $version_id;

    if (!gta6mods_download_rate_limiter_allow($rate_namespace)) {
        wp_die(__('Túl sok letöltési kísérlet. Próbáld újra később.', 'gta6-mods'), __('Túl sok kérés', 'gta6-mods'), ['response' => 429]);
    }

    if ($nojs_mode) {
        $permission = gta6mods_consume_nojs_permission($version_id);
        if (!$permission) {
            wp_die(__('A JavaScript nélküli letöltéshez először töltsd be a várótermet.', 'gta6-mods'), __('Letöltés hiba', 'gta6-mods'), ['response' => 403]);
        }

        $version = GTA6Mods_Mod_Versions::get_version($version_id);
        if (!$version || (int) ($version['mod_id'] ?? 0) !== (int) $permission['mod_id']) {
            wp_die(__('A letöltéshez tartozó verzió nem elérhető.', 'gta6-mods'), __('Letöltés hiba', 'gta6-mods'), ['response' => 404]);
        }

        gta6mods_grant_download_allowance($rate_namespace);
    } else {
        $validation = gta6mods_validate_download_token($token, $version_id);
        if (is_wp_error($validation)) {
            wp_die($validation);
        }

        $version = $validation;
    }
    $mod_id  = (int) $version['mod_id'];

    $consumed_click = function_exists('gta6mods_consume_download_click')
        ? gta6mods_consume_download_click($version_id)
        : false;

    if (!$consumed_click) {
        gta6mods_queue_download_increment($mod_id, $version_id);
    }

    $attachment_id = isset($version['attachment_id']) ? (int) $version['attachment_id'] : 0;
    if ($attachment_id <= 0) {
        wp_die(__('A letöltéshez tartozó fájl nem található.', 'gta6-mods'), __('Letöltés hiba', 'gta6-mods'), ['response' => 404]);
    }

    $file_path = get_attached_file($attachment_id);
    $file_name = basename($file_path ? $file_path : get_post_meta($attachment_id, '_wp_attached_file', true));
    $file_url  = wp_get_attachment_url($attachment_id);

    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow', true);
    header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\';', true);

    if ($file_path && file_exists($file_path)) {
        $mime = get_post_mime_type($attachment_id) ?: 'application/octet-stream';
        $size = filesize($file_path);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($file_name) . '"');
        if (false !== $size) {
            header('Content-Length: ' . $size);
        }

        if (GTA6MODS_USE_XACCEL) {
            $uploads = wp_get_upload_dir();
            if (!empty($uploads['basedir'])) {
                $base_dir = trailingslashit($uploads['basedir']);
                if (str_starts_with($file_path, $base_dir)) {
                    $relative      = ltrim(substr($file_path, strlen($base_dir)), '/');
                    $internal_path = trailingslashit(GTA6MODS_XACCEL_PREFIX) . $relative;

                    header('X-Accel-Redirect: ' . $internal_path);
                    header('X-Accel-Buffering: yes');
                    header('X-Accel-Charset: utf-8');
                    exit;
                }
            }
        }

        $chunk_size = 8192;
        $handle     = fopen($file_path, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, $chunk_size);
                @ob_flush();
                flush();
            }
            fclose($handle);
            exit;
        }
    }

    if ($file_url) {
        wp_safe_redirect($file_url, 302);
        exit;
    }

    wp_die(__('A letöltés nem érhető el.', 'gta6-mods'), __('Letöltés hiba', 'gta6-mods'), ['response' => 500]);
}
add_action('template_redirect', 'gta6mods_handle_secure_download');

/**
 * Queues a download increment for asynchronous processing.
 */
function gta6mods_queue_download_increment(int $mod_id, int $version_id): void {
    $mod_id     = absint($mod_id);
    $version_id = absint($version_id);

    if ($mod_id <= 0 || $version_id <= 0) {
        return;
    }

    if (!wp_using_ext_object_cache()) {
        gta6_mods_increment_download_count($mod_id, $version_id);
        return;
    }

    gta6mods_cache_incr('version_' . $version_id, 1, 'gta6mods_download_queue_versions', GTA6MODS_DOWNLOAD_QUEUE_TTL);

    $version_index = (array) gta6mods_cache_get('versions', 'gta6mods_download_queue_index', []);
    if (!in_array($version_id, $version_index, true)) {
        $version_index[] = $version_id;
        gta6mods_cache_set('versions', $version_index, 'gta6mods_download_queue_index', GTA6MODS_DOWNLOAD_QUEUE_TTL);
    }
}

if (!function_exists('gta6mods_get_download_click_cache_key')) {
    /**
     * Builds a cache key for download clicks recorded before the secure download.
     */
    function gta6mods_get_download_click_cache_key(int $version_id, ?string $ip = null): string {
        $version_id = absint($version_id);
        $identifier = is_string($ip) ? trim($ip) : '';

        if ('' === $identifier && function_exists('gta6mods_get_waiting_room_identifier')) {
            $identifier = gta6mods_get_waiting_room_identifier();
        }

        if ('' === $identifier) {
            $identifier = gta6mods_get_request_ip();
        }

        $hash = $identifier !== '' ? md5($identifier) : 'unknown';

        return sprintf('click_%d_%s', $version_id, $hash);
    }
}

if (!function_exists('gta6mods_mark_download_click')) {
    /**
     * Marks a download as already counted when the visitor clicks the button on the mod page.
     */
    function gta6mods_mark_download_click(int $version_id, ?string $ip = null): void {
        $version_id = absint($version_id);

        if ($version_id <= 0) {
            return;
        }

        $cache_key = gta6mods_get_download_click_cache_key($version_id, $ip);

        gta6mods_cache_set(
            $cache_key,
            time(),
            'gta6mods_download_clicks',
            10 * MINUTE_IN_SECONDS
        );
    }
}

if (!function_exists('gta6mods_consume_download_click')) {
    /**
     * Consumes a previously recorded download click marker to avoid double counting.
     */
    function gta6mods_consume_download_click(int $version_id, ?string $ip = null): bool {
        $version_id = absint($version_id);

        if ($version_id <= 0) {
            return false;
        }

        $cache_key = gta6mods_get_download_click_cache_key($version_id, $ip);
        $cached    = gta6mods_cache_get($cache_key, 'gta6mods_download_clicks');

        if (false === $cached) {
            return false;
        }

        gta6mods_cache_delete($cache_key, 'gta6mods_download_clicks');

        return true;
    }
}

/**
 * Processes queued download increments in batches.
 *
 * @return array{versions:int, mods:int}
 */
function gta6mods_process_download_queue(): array {
    $result = [
        'versions' => 0,
        'mods'     => 0,
    ];

    if (!wp_using_ext_object_cache()) {
        return $result;
    }

    $version_ids = (array) gta6mods_cache_get('versions', 'gta6mods_download_queue_index', []);
    if (empty($version_ids)) {
        return $result;
    }

    $version_updates   = [];
    $version_mod_map   = [];
    $mod_last_version  = [];

    foreach ($version_ids as $version_id) {
        $version_id = absint($version_id);
        if ($version_id <= 0) {
            continue;
        }

        $count = (int) gta6mods_cache_get('version_' . $version_id, 'gta6mods_download_queue_versions', 0);
        if ($count <= 0) {
            continue;
        }

        $remaining = gta6mods_cache_decr('version_' . $version_id, $count, 'gta6mods_download_queue_versions', GTA6MODS_DOWNLOAD_QUEUE_TTL);
        $processed_count = $count;
        if (false !== $remaining) {
            $processed_count = max(0, $count - (int) $remaining);
        }

        if ($processed_count <= 0) {
            continue;
        }

        $version_updates[$version_id] = $processed_count;

        $version = GTA6Mods_Mod_Versions::get_version($version_id);
        if ($version && isset($version['mod_id'])) {
            $mod_id = (int) $version['mod_id'];
            if ($mod_id > 0) {
                $version_mod_map[$version_id] = $mod_id;

                $upload_timestamp = 0;
                if (!empty($version['upload_date'])) {
                    $upload_timestamp = strtotime((string) $version['upload_date']) ?: 0;
                }

                if (!isset($mod_last_version[$mod_id]) || $upload_timestamp >= $mod_last_version[$mod_id]['timestamp']) {
                    $mod_last_version[$mod_id] = [
                        'version_id' => $version_id,
                        'timestamp'  => $upload_timestamp,
                    ];
                }
            }
        }
    }

    $active_versions = [];
    foreach ($version_ids as $version_id) {
        $pending = (int) gta6mods_cache_get('version_' . $version_id, 'gta6mods_download_queue_versions', 0);
        if ($pending > 0) {
            $active_versions[] = (int) $version_id;
        }
    }
    gta6mods_cache_set('versions', $active_versions, 'gta6mods_download_queue_index', GTA6MODS_DOWNLOAD_QUEUE_TTL);

    if (empty($version_updates)) {
        return $result;
    }

    global $wpdb;

    $versions_table = GTA6Mods_Mod_Versions::table_name();
    $cases          = [];
    foreach ($version_updates as $version_id => $increment) {
        $cases[] = $wpdb->prepare('WHEN %d THEN download_count + %d', $version_id, $increment);
    }
    $ids_sql = implode(',', array_map('absint', array_keys($version_updates)));
    $wpdb->query("UPDATE {$versions_table} SET download_count = CASE id " . implode(' ', $cases) . " END WHERE id IN ({$ids_sql})");

    $mod_counts = [];
    foreach ($version_updates as $version_id => $increment) {
        $mod_id = $version_mod_map[$version_id] ?? 0;
        if ($mod_id <= 0) {
            continue;
        }

        if (!isset($mod_counts[$mod_id])) {
            $mod_counts[$mod_id] = 0;
        }
        $mod_counts[$mod_id] += $increment;

        GTA6Mods_Mod_Versions::flush_version_cache($version_id);
        GTA6Mods_Mod_Versions::flush_cache($mod_id);
    }

    $result['versions'] = count($version_updates);

    if (!empty($mod_counts)) {
        $cases = [];
        foreach ($mod_counts as $mod_id => $increment) {
            $cases[] = $wpdb->prepare('WHEN %d THEN downloads + %d', $mod_id, $increment);
        }

        $mod_ids_sql  = implode(',', array_map('absint', array_keys($mod_counts)));
        $stats_table  = gta6mods_get_mod_stats_table_name();
        $wpdb->query("UPDATE {$stats_table} SET downloads = CASE post_id " . implode(' ', $cases) . " END, last_updated = CURRENT_TIMESTAMP WHERE post_id IN ({$mod_ids_sql})");

        $current_timestamp = current_time('timestamp');
        foreach ($mod_counts as $mod_id => $increment) {
            $current_total = (int) get_post_meta($mod_id, '_gta6mods_download_count', true);
            $new_total     = max(0, $current_total + $increment);

            update_post_meta($mod_id, '_gta6mods_download_count', $new_total);
            update_post_meta($mod_id, '_gta6mods_last_downloaded', $current_timestamp);

            if (isset($mod_last_version[$mod_id]['version_id'])) {
                update_post_meta($mod_id, '_gta6mods_last_downloaded_version', $mod_last_version[$mod_id]['version_id']);
            }

            gta6mods_update_stat_meta_cache($mod_id, 'downloads', $new_total);
            gta6mods_adjust_author_download_total($mod_id, $increment);
        }

        $result['mods'] = count($mod_counts);
    }

    return $result;
}
add_action('gta6mods_process_download_queue_event', 'gta6mods_process_download_queue');

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command(
        'gta6mods process-downloads',
        static function () {
            $processed = gta6mods_process_download_queue();
            WP_CLI::success(
                sprintf(
                    'Processed %d version(s) and %d mod(s).',
                    (int) $processed['versions'],
                    (int) $processed['mods']
                )
            );
        }
    );
}
