<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GTA6_FORUM_REWRITE_VERSION')) {
    define('GTA6_FORUM_REWRITE_VERSION', '10');
}

if (!defined('GTA6_FORUM_BOOKMARK_META_KEY')) {
    define('GTA6_FORUM_BOOKMARK_META_KEY', '_gta6_forum_bookmarks');
}

function gta6_forum_get_user_bookmarked_thread_ids(int $userId = 0): array
{
    $userId = $userId > 0 ? $userId : get_current_user_id();

    if ($userId <= 0) {
        return [];
    }

    $bookmarks = get_user_meta($userId, GTA6_FORUM_BOOKMARK_META_KEY, true);

    if (!is_array($bookmarks)) {
        return [];
    }

    $bookmarks = array_map('absint', $bookmarks);
    $bookmarks = array_filter($bookmarks);

    return array_values(array_unique($bookmarks));
}

function gta6_forum_is_thread_bookmarked_by_user(int $threadId, int $userId = 0): bool
{
    if ($threadId <= 0) {
        return false;
    }

    $bookmarks = gta6_forum_get_user_bookmarked_thread_ids($userId);

    return in_array($threadId, $bookmarks, true);
}

function gta6_forum_update_user_bookmarked_thread_ids(int $userId, array $threadIds): void
{
    if ($userId <= 0) {
        return;
    }

    $threadIds = array_map('absint', $threadIds);
    $threadIds = array_filter($threadIds);

    update_user_meta($userId, GTA6_FORUM_BOOKMARK_META_KEY, array_values(array_unique($threadIds)));
}

/**
 * Registers the forum_thread custom post type.
 */
function gta6_forum_register_post_type(): void {
    $labels = [
        'name'                  => _x('Forum Threads', 'Post type general name', 'gta6mods'),
        'singular_name'         => _x('Forum Thread', 'Post type singular name', 'gta6mods'),
        'menu_name'             => _x('Forum Threads', 'Admin Menu text', 'gta6mods'),
        'name_admin_bar'        => _x('Forum Thread', 'Add New on Toolbar', 'gta6mods'),
        'add_new'               => __('Add New', 'gta6mods'),
        'add_new_item'          => __('Add New Forum Thread', 'gta6mods'),
        'new_item'              => __('New Forum Thread', 'gta6mods'),
        'edit_item'             => __('Edit Forum Thread', 'gta6mods'),
        'view_item'             => __('View Forum Thread', 'gta6mods'),
        'all_items'             => __('All Forum Threads', 'gta6mods'),
        'search_items'          => __('Search Forum Threads', 'gta6mods'),
        'parent_item_colon'     => __('Parent Forum Threads:', 'gta6mods'),
        'not_found'             => __('No forum threads found.', 'gta6mods'),
        'not_found_in_trash'    => __('No forum threads found in Trash.', 'gta6mods'),
        'featured_image'        => __('Thread Thumbnail', 'gta6mods'),
        'set_featured_image'    => __('Set thread thumbnail', 'gta6mods'),
        'remove_featured_image' => __('Remove thread thumbnail', 'gta6mods'),
        'use_featured_image'    => __('Use as thread thumbnail', 'gta6mods'),
        'archives'              => __('Forum thread archives', 'gta6mods'),
        'insert_into_item'      => __('Insert into forum thread', 'gta6mods'),
        'uploaded_to_this_item' => __('Uploaded to this forum thread', 'gta6mods'),
        'filter_items_list'     => __('Filter forum threads list', 'gta6mods'),
        'items_list_navigation' => __('Forum threads list navigation', 'gta6mods'),
        'items_list'            => __('Forum threads list', 'gta6mods'),
    ];

    $supports = ['title', 'editor', 'author', 'comments', 'custom-fields'];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'show_in_rest'       => true,
        'rest_base'          => 'forum-threads',
        'rewrite'            => [
            'slug'       => 'forum',
            'with_front' => false,
        ],
        'supports'           => $supports,
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'menu_icon'          => 'dashicons-format-chat',
        'capability_type'    => 'post',
        'show_in_nav_menus'  => false,
        'show_in_menu'       => true,
        'exclude_from_search'=> false,
    ];

    register_post_type('forum_thread', $args);
}
add_action('init', 'gta6_forum_register_post_type');

/**
 * Registers the forum_flair taxonomy used by forum threads.
 */
function gta6_forum_register_taxonomy(): void {
    $labels = [
        'name'              => _x('Flairs', 'taxonomy general name', 'gta6mods'),
        'singular_name'     => _x('Flair', 'taxonomy singular name', 'gta6mods'),
        'search_items'      => __('Search flairs', 'gta6mods'),
        'all_items'         => __('All flairs', 'gta6mods'),
        'parent_item'       => __('Parent flair', 'gta6mods'),
        'parent_item_colon' => __('Parent flair:', 'gta6mods'),
        'edit_item'         => __('Edit flair', 'gta6mods'),
        'update_item'       => __('Update flair', 'gta6mods'),
        'add_new_item'      => __('Add new flair', 'gta6mods'),
        'new_item_name'     => __('New flair name', 'gta6mods'),
        'menu_name'         => __('Flairs', 'gta6mods'),
    ];

    $args = [
        'labels'            => $labels,
        'public'            => true,
        'show_in_nav_menus' => false,
        'show_ui'           => true,
        'show_tagcloud'     => false,
        'show_in_rest'      => true,
        'rest_base'         => 'flairs',
        'hierarchical'      => false,
        'query_var'         => 'forum_flair',
        'rewrite'           => [
            'slug'       => 'forum/flair',
            'with_front' => false,
        ],
    ];

    register_taxonomy('forum_flair', ['forum_thread'], $args);
}
add_action('init', 'gta6_forum_register_taxonomy', 11);

/**
 * Ensures pretty permalinks for flair archives work even when the slug contains a nested path.
 */
function gta6_forum_register_flair_rewrite_rule(): void {
    $taxonomy = get_taxonomy('forum_flair');

    if (!$taxonomy || empty($taxonomy->rewrite['slug'])) {
        return;
    }

    $slug = trim((string) $taxonomy->rewrite['slug'], '/');
    if ('' === $slug) {
        return;
    }

    $escapedSlug = preg_quote($slug, '/');

    $timePattern = '(today|last-week|last-month|last-year|all-time)';

    add_rewrite_rule(
        '^' . $escapedSlug . '\/([^\/]+)\/top\/' . $timePattern . '\/?$',
        'index.php?forum_flair=$matches[1]&forum_sort=top&forum_time_range=$matches[2]',
        'top'
    );

    add_rewrite_rule(
        '^' . $escapedSlug . '\/([^\/]+)\/top\/?$',
        'index.php?forum_flair=$matches[1]&forum_sort=top&forum_time_range=all-time',
        'top'
    );

    add_rewrite_rule(
        '^' . $escapedSlug . '\/([^\/]+)\/(hot|new)\/?$',
        'index.php?forum_flair=$matches[1]&forum_sort=$matches[2]',
        'top'
    );

    add_rewrite_rule(
        '^' . $escapedSlug . '\/([^\/]+)\/?$',
        'index.php?forum_flair=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^' . $escapedSlug . '\/([^\/]+)\/page\/(\d+)\/?$',
        'index.php?forum_flair=$matches[1]&paged=$matches[2]',
        'top'
    );

    add_rewrite_rule(
        '^' . $escapedSlug . '\/([^\/]+)\/(feed|rdf|rss|rss2|atom)\/?$',
        'index.php?forum_flair=$matches[1]&feed=$matches[2]',
        'top'
    );

    add_rewrite_rule(
        '^' . $escapedSlug . '\/([^\/]+)\/feed\/(feed|rdf|rss|rss2|atom)\/?$',
        'index.php?forum_flair=$matches[1]&feed=$matches[2]',
        'top'
    );
}
add_action('init', 'gta6_forum_register_flair_rewrite_rule', 20);

function gta6_forum_get_flair_rewrite_base(): string
{
    $taxonomy = get_taxonomy('forum_flair');
    if (!$taxonomy) {
        return 'forum_flair';
    }

    $slug = '';
    if (isset($taxonomy->rewrite['slug'])) {
        $slug = (string) $taxonomy->rewrite['slug'];
    }

    $slug = trim($slug, '/');

    return '' !== $slug ? $slug : 'forum_flair';
}

function gta6_forum_filter_flair_term_link(string $termlink, $term, string $taxonomy)
{
    if ('forum_flair' !== $taxonomy || !($term instanceof WP_Term)) {
        return $termlink;
    }

    $base = gta6_forum_get_flair_rewrite_base();
    if ('' === $base) {
        return $termlink;
    }

    $path     = wp_parse_url($termlink, PHP_URL_PATH);
    $suffix   = '';
    $segments = [];

    if (is_string($path) && '' !== $path) {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
    }

    if (!empty($segments)) {
        $termIndex = array_search($term->slug, $segments, true);
        if (false !== $termIndex) {
            $suffixSegments = array_slice($segments, $termIndex + 1);
            if (!empty($suffixSegments)) {
                $suffix = implode('/', $suffixSegments);
            }
        }
    }

    $rebuiltPath = sprintf('%s/%s/', trim($base, '/'), $term->slug);
    if ('' !== $suffix) {
        $rebuiltPath .= trim($suffix, '/') . '/';
    }

    $url  = home_url('/' . ltrim($rebuiltPath, '/'));

    $query = wp_parse_url($termlink, PHP_URL_QUERY);
    if (is_string($query) && '' !== $query) {
        $url .= '?' . $query;
    }

    $fragment = wp_parse_url($termlink, PHP_URL_FRAGMENT);
    if (is_string($fragment) && '' !== $fragment) {
        $url .= '#' . $fragment;
    }

    return $url;
}
add_filter('term_link', 'gta6_forum_filter_flair_term_link', 20, 3);

function gta6_forum_register_query_vars(array $vars): array
{
    if (!in_array('forum_flair', $vars, true)) {
        $vars[] = 'forum_flair';
    }

    if (!in_array('forum_sort', $vars, true)) {
        $vars[] = 'forum_sort';
    }

    if (!in_array('forum_time_range', $vars, true)) {
        $vars[] = 'forum_time_range';
    }

    return $vars;
}
add_filter('query_vars', 'gta6_forum_register_query_vars');

/**
 * Registers flair taxonomy meta that is exposed through the REST API.
 */
function gta6_forum_register_term_meta(): void {
    register_term_meta(
        'forum_flair',
        'flair_palette',
        [
            'type'              => 'string',
            'description'       => __('Colour palette key used to render flair badges.', 'gta6mods'),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'gta6_forum_sanitize_flair_palette',
            'auth_callback'     => static fn() => current_user_can('manage_categories'),
        ]
    );

    // Legacy meta used as a fallback for earlier installs.
    register_term_meta(
        'forum_flair',
        'flair_color',
        [
            'type'              => 'string',
            'description'       => __('Legacy flair background colour.', 'gta6mods'),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => static fn($value) => is_string($value) ? sanitize_hex_color($value) : '',
            'auth_callback'     => static fn() => current_user_can('manage_categories'),
        ]
    );
}
add_action('init', 'gta6_forum_register_term_meta', 12);

function gta6_forum_get_flair_palette_catalog(): array
{
    return [
        'sky'        => ['label' => __('Sky Blue', 'gta6mods'),      'background' => '#dbeafe', 'text' => '#1d4ed8'],
        'amber'      => ['label' => __('Amber Glow', 'gta6mods'),    'background' => '#fef3c7', 'text' => '#b45309'],
        'emerald'    => ['label' => __('Emerald', 'gta6mods'),       'background' => '#d1fae5', 'text' => '#047857'],
        'rose'       => ['label' => __('Rose Quartz', 'gta6mods'),   'background' => '#ffe4e6', 'text' => '#be123c'],
        'violet'     => ['label' => __('Violet Pulse', 'gta6mods'),  'background' => '#ede9fe', 'text' => '#6d28d9'],
        'slate'      => ['label' => __('Slate', 'gta6mods'),         'background' => '#f3f4f6', 'text' => '#1f2937'],
        'cyan'       => ['label' => __('Cyan Breeze', 'gta6mods'),   'background' => '#cffafe', 'text' => '#0f766e'],
        'indigo'     => ['label' => __('Indigo Rush', 'gta6mods'),   'background' => '#e0e7ff', 'text' => '#3730a3'],
        'lime'       => ['label' => __('Lime Zest', 'gta6mods'),     'background' => '#ecfccb', 'text' => '#3f6212'],
        'orange'     => ['label' => __('Orange Burst', 'gta6mods'),  'background' => '#ffedd5', 'text' => '#c2410c'],
        'pink'       => ['label' => __('Blush Pink', 'gta6mods'),    'background' => '#fce7f3', 'text' => '#be185d'],
        'teal'       => ['label' => __('Teal Drift', 'gta6mods'),    'background' => '#ccfbf1', 'text' => '#0f766e'],
        'red'        => ['label' => __('Crimson', 'gta6mods'),       'background' => '#fee2e2', 'text' => '#b91c1c'],
        'blue'       => ['label' => __('Blue Steel', 'gta6mods'),    'background' => '#bfdbfe', 'text' => '#1d4ed8'],
        'charcoal'   => ['label' => __('Charcoal', 'gta6mods'),      'background' => '#e5e7eb', 'text' => '#111827'],
    ];
}

function gta6_forum_default_flair_palette(): string
{
    $catalog = gta6_forum_get_flair_palette_catalog();
    $keys = array_keys($catalog);

    return $keys ? (string) $keys[0] : 'sky';
}

function gta6_forum_sanitize_flair_palette($value): string
{
    $value = is_string($value) ? sanitize_key($value) : '';
    $catalog = gta6_forum_get_flair_palette_catalog();

    return array_key_exists($value, $catalog) ? $value : gta6_forum_default_flair_palette();
}

function gta6_forum_calculate_text_contrast(string $background): string
{
    $background = ltrim($background, '#');
    if (strlen($background) !== 6) {
        return '#111827';
    }

    $r = hexdec(substr($background, 0, 2));
    $g = hexdec(substr($background, 2, 2));
    $b = hexdec(substr($background, 4, 2));

    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    return $luminance > 0.6 ? '#111827' : '#ffffff';
}

function gta6_forum_get_flair_colors(int $termId): array
{
    $paletteKey = (string) get_term_meta($termId, 'flair_palette', true);
    $catalog = gta6_forum_get_flair_palette_catalog();

    if ($paletteKey && isset($catalog[$paletteKey])) {
        return [
            'key'        => $paletteKey,
            'background' => $catalog[$paletteKey]['background'],
            'text'       => $catalog[$paletteKey]['text'],
        ];
    }

    $legacy = (string) get_term_meta($termId, 'flair_color', true);
    if ($legacy !== '') {
        return [
            'key'        => '',
            'background' => $legacy,
            'text'       => gta6_forum_calculate_text_contrast($legacy),
        ];
    }

    $defaultKey = gta6_forum_default_flair_palette();

    return [
        'key'        => $defaultKey,
        'background' => $catalog[$defaultKey]['background'],
        'text'       => $catalog[$defaultKey]['text'],
    ];
}

function gta6_forum_render_flair_palette_controls(?WP_Term $term = null): void
{
    $catalog = gta6_forum_get_flair_palette_catalog();
    $selected = $term ? (string) get_term_meta($term->term_id, 'flair_palette', true) : '';

    if ($selected === '') {
        $selected = gta6_forum_default_flair_palette();
    }
    ?>
    <div class="gta6-flair-palette-options">
        <?php foreach ($catalog as $slug => $option) : ?>
            <label class="gta6-flair-palette-option">
                <input type="radio" name="flair_palette" value="<?php echo esc_attr($slug); ?>" <?php checked($slug, $selected); ?>>
                <span class="gta6-flair-palette-swatch" style="background-color: <?php echo esc_attr($option['background']); ?>; color: <?php echo esc_attr($option['text']); ?>;">
                    <?php echo esc_html($option['label']); ?>
                </span>
            </label>
        <?php endforeach; ?>
    </div>
    <?php
}

function gta6_forum_flair_add_palette_field(): void
{
    ?>
    <div class="form-field term-flair-palette-wrap">
        <label for="flair-palette"><?php esc_html_e('Flair colour', 'gta6mods'); ?></label>
        <?php gta6_forum_render_flair_palette_controls(); ?>
        <p class="description"><?php esc_html_e('Choose how this flair badge should appear across the forum.', 'gta6mods'); ?></p>
    </div>
    <?php
}
add_action('forum_flair_add_form_fields', 'gta6_forum_flair_add_palette_field');

function gta6_forum_flair_edit_palette_field(WP_Term $term): void
{
    ?>
    <tr class="form-field term-flair-palette-wrap">
        <th scope="row"><label for="flair-palette"><?php esc_html_e('Flair colour', 'gta6mods'); ?></label></th>
        <td>
            <?php gta6_forum_render_flair_palette_controls($term); ?>
            <p class="description"><?php esc_html_e('Choose how this flair badge should appear across the forum.', 'gta6mods'); ?></p>
        </td>
    </tr>
    <?php
}
add_action('forum_flair_edit_form_fields', 'gta6_forum_flair_edit_palette_field');

function gta6_forum_save_flair_palette(int $termId): void
{
    if (!isset($_POST['flair_palette'])) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }

    $value = sanitize_key(wp_unslash($_POST['flair_palette'])); // phpcs:ignore WordPress.Security.NonceVerification
    $catalog = gta6_forum_get_flair_palette_catalog();

    if (!array_key_exists($value, $catalog)) {
        $value = gta6_forum_default_flair_palette();
    }

    update_term_meta($termId, 'flair_palette', $value);
    update_term_meta($termId, 'flair_color', $catalog[$value]['background']);
}
add_action('created_forum_flair', 'gta6_forum_save_flair_palette');
add_action('edited_forum_flair', 'gta6_forum_save_flair_palette');

function gta6_forum_print_flair_palette_styles(): void
{
    $screen = get_current_screen();
    if (!$screen || 'forum_flair' !== $screen->taxonomy) {
        return;
    }
    ?>
    <style>
        .gta6-flair-palette-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }

        .gta6-flair-palette-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .gta6-flair-palette-option input[type="radio"] {
            margin: 0;
        }

        .gta6-flair-palette-swatch {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            padding: 0.35rem 0.9rem;
            text-transform: uppercase;
        }
    </style>
    <?php
}
add_action('admin_print_styles-edit-tags.php', 'gta6_forum_print_flair_palette_styles');

/**
 * Returns the fully qualified name of the forum vote tables.
 */
function gta6_forum_thread_votes_table(): string {
    global $wpdb;

    return $wpdb->prefix . 'gta6_forum_votes';
}

function gta6_forum_comment_votes_table(): string {
    global $wpdb;

    return $wpdb->prefix . 'gta6_comment_votes';
}

function gta6_forum_notifications_table(): string {
    global $wpdb;

    return $wpdb->prefix . 'gta6_notifications';
}

/**
 * Creates or updates the database tables required for the forum subsystem.
 */
function gta6_forum_maybe_create_tables(): void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    $threadVotesTable = gta6_forum_thread_votes_table();
    $commentVotesTable = gta6_forum_comment_votes_table();
    $notificationsTable = gta6_forum_notifications_table();

    $threadVotesSql = "CREATE TABLE {$threadVotesTable} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        thread_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        voter_fingerprint VARCHAR(128) NULL,
        vote TINYINT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY thread_user_unique (thread_id, user_id),
        KEY thread_vote_lookup (thread_id, voter_fingerprint)
    ) {$charset};";

    $commentVotesSql = "CREATE TABLE {$commentVotesTable} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        comment_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        voter_fingerprint VARCHAR(128) NULL,
        vote TINYINT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY comment_user_unique (comment_id, user_id),
        KEY comment_vote_lookup (comment_id, voter_fingerprint)
    ) {$charset};";

    $notificationsSql = "CREATE TABLE {$notificationsTable} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        thread_id BIGINT UNSIGNED NOT NULL,
        comment_id BIGINT UNSIGNED NOT NULL,
        notification_type VARCHAR(32) NOT NULL DEFAULT 'comment',
        status VARCHAR(16) NOT NULL DEFAULT 'unread',
        payload LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_status_lookup (user_id, status),
        KEY thread_lookup (thread_id)
    ) {$charset};";

    dbDelta($threadVotesSql);
    dbDelta($commentVotesSql);
    dbDelta($notificationsSql);
}

/**
 * Theme activation hook for ensuring database tables exist and cron events are registered.
 */
function gta6_forum_on_theme_activation(): void {
    gta6_forum_maybe_create_tables();
    gta6_forum_schedule_events();
    flush_rewrite_rules();
    update_option('gta6_forum_rewrite_version', GTA6_FORUM_REWRITE_VERSION);
}
add_action('after_switch_theme', 'gta6_forum_on_theme_activation');

/**
 * Ensures recurring cron jobs are scheduled.
 */
function gta6_forum_schedule_events(): void {
    if (!wp_next_scheduled('gta6_sync_scores_from_redis')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'five_minutes', 'gta6_sync_scores_from_redis');
    }

    if (!wp_next_scheduled('gta6_recalculate_hot_scores')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'quarter_hour', 'gta6_recalculate_hot_scores');
    }
}
add_action('init', 'gta6_forum_schedule_events', 20);

/**
 * Adds custom schedules for the forum cron jobs.
 */
function gta6_forum_register_custom_schedules(array $schedules): array {
    if (!isset($schedules['five_minutes'])) {
        $schedules['five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Every five minutes', 'gta6mods'),
        ];
    }

    if (!isset($schedules['quarter_hour'])) {
        $schedules['quarter_hour'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __('Every fifteen minutes', 'gta6mods'),
        ];
    }

    return $schedules;
}
add_filter('cron_schedules', 'gta6_forum_register_custom_schedules');

function gta6_forum_maybe_refresh_rewrite_rules(): void {
    $storedVersion = get_option('gta6_forum_rewrite_version');

    if ($storedVersion !== GTA6_FORUM_REWRITE_VERSION) {
        flush_rewrite_rules(false);
        update_option('gta6_forum_rewrite_version', GTA6_FORUM_REWRITE_VERSION);
    }
}
add_action('init', 'gta6_forum_maybe_refresh_rewrite_rules', 25);
