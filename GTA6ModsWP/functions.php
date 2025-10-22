<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Video Gallery Table Name
 */
if (!defined('GTA6MODS_VIDEOS_TABLE')) {
    define('GTA6MODS_VIDEOS_TABLE', 'gta_mod_videos');
}

if (!defined('GTA6MODS_VIDEO_REPORTS_TABLE')) {
    define('GTA6MODS_VIDEO_REPORTS_TABLE', 'gta_mod_video_reports');
}

if (!defined('GTA6_MODS_FEATURED_META_KEY')) {
    define('GTA6_MODS_FEATURED_META_KEY', '_gta6mods_is_featured');
}

if (!defined('GTA6_MODS_FEATURED_TIMESTAMP_META_KEY')) {
    define('GTA6_MODS_FEATURED_TIMESTAMP_META_KEY', '_gta6mods_featured_timestamp');
}

// Cache utilities used across high-throughput components
require get_template_directory() . '/inc/cache-helpers.php';

// Core theme setup and asset enqueueing
require get_template_directory() . '/inc/setup-functions.php';

// Functions related to the comment system
require get_template_directory() . '/inc/comment-functions.php';

// Comment report functionality
require get_template_directory() . '/inc/comment-report.php';

// Functions for the mod upload functionality
require get_template_directory() . '/inc/upload-functions.php';

// Mod update workflow and version management
require get_template_directory() . '/inc/mod-update-functions.php';

// High performance mod version storage
require get_template_directory() . '/inc/class-mod-versions.php';

// Secure download handler
require get_template_directory() . '/inc/download-handler.php';

// Archive filtering, rewrite rules and denormalised index
require get_template_directory() . '/inc/filter-functions.php';

// Category permalinks without the default /category/ base
require get_template_directory() . '/inc/category-permalink-functions.php';

// Dynamic SEO for archive and search listings
require get_template_directory() . '/inc/seo-functions.php';

// Enterprise XML sitemaps and search analytics
require get_template_directory() . '/inc/sitemap-functions.php';

// High-throughput statistics helpers
require get_template_directory() . '/inc/stats-functions.php';

// Author profile functionality
require get_template_directory() . '/inc/author-functions.php';

// Authentication endpoints and helpers
require get_template_directory() . '/inc/auth-functions.php';

// Helper functions for use in templates
require get_template_directory() . '/inc/template-helpers.php';

// Functions that modify or handle WP_Query
require get_template_directory() . '/inc/query-functions.php';

// Functions for the admin area (meta boxes, taxonomy fields)
require get_template_directory() . '/inc/admin-functions.php';

// General AJAX handlers (likes, ratings, etc.)
require get_template_directory() . '/inc/ajax-functions.php';

// Editor.js integration
require get_template_directory() . '/inc/editorjs-functions.php';

// Forum subsystem
require get_template_directory() . '/inc/forum/forum-setup.php';
require get_template_directory() . '/inc/forum/forum-votes.php';
require get_template_directory() . '/inc/forum/forum-cron.php';
require get_template_directory() . '/inc/forum/forum-threads-rest.php';
require get_template_directory() . '/inc/forum/forum-views.php';
require get_template_directory() . '/inc/forum/forum-comment-rest.php';
require get_template_directory() . '/inc/forum/forum-notifications.php';
require get_template_directory() . '/inc/forum/forum-assets.php';
require get_template_directory() . '/inc/forum/forum-template.php';
require get_template_directory() . '/inc/forum/forum-admin.php';
require get_template_directory() . '/inc/forum/forum-seo-helpers.php';
require get_template_directory() . '/inc/forum/forum-seo.php';
require get_template_directory() . '/inc/forum/forum-sitemap.php';


// REST API Endpoints
require get_template_directory() . '/inc/rest-api/rest-utils.php';
require get_template_directory() . '/inc/rest-api/ratings-endpoints.php';
require get_template_directory() . '/inc/rest-api/related-mods-endpoint.php';
require get_template_directory() . '/inc/rest-api/comments-endpoints.php';
require get_template_directory() . '/inc/rest-api/single-mod-user-state-endpoint.php';
require get_template_directory() . '/inc/rest-api/single-mod-endpoint.php';
require get_template_directory() . '/inc/rest-api/interactions-endpoints.php';
require get_template_directory() . '/inc/rest-api/tracking-endpoints.php';

// Video gallery functionality
require get_template_directory() . '/inc/video-functions.php';
require get_template_directory() . '/inc/video-api.php';
require get_template_directory() . '/admin/moderate-videos.php';
require get_template_directory() . '/inc/video-sitemap-functions.php';

/**
 * Registers custom rewrite rules for the download waiting room and secure handler.
 */
function gta6mods_register_mod_download_rewrite_rules(): void {
    add_rewrite_rule(
        '^([^/]+)/download/([0-9]+)/?$',
        'index.php?mod_slug=$matches[1]&version_id=$matches[2]&waiting_room=1',
        'top'
    );

    add_rewrite_rule(
        '^([^/]+)/download/latest/?$',
        'index.php?mod_slug=$matches[1]&download_latest=1&waiting_room=1',
        'top'
    );

    add_rewrite_rule(
        '^([^/]+)/download/external/([0-9]+)/?$',
        'index.php?mod_slug=$matches[1]&waiting_room=1&external_type=version&external_target=$matches[2]',
        'top'
    );

    add_rewrite_rule(
        '^([^/]+)/download/external/?$',
        'index.php?mod_slug=$matches[1]&waiting_room=1&external_type=mod',
        'top'
    );

    add_rewrite_rule(
        '^([^/]+)/download/external/(version|mod)/([0-9]+)/?$',
        'index.php?mod_slug=$matches[1]&waiting_room=1&external_type=$matches[2]&external_target=$matches[3]',
        'top'
    );

    add_rewrite_rule(
        '^download-file/?$',
        'index.php?gta6mods_download=1',
        'top'
    );
}
add_action('init', 'gta6mods_register_mod_download_rewrite_rules');

/**
 * Makes the waiting room query vars available for WP_Query.
 *
 * @param array $vars Query vars.
 *
 * @return array
 */
function gta6mods_register_mod_download_query_vars(array $vars): array {
    $vars[] = 'waiting_room';
    $vars[] = 'mod_slug';
    $vars[] = 'version_id';
    $vars[] = 'download_latest';
    $vars[] = 'gta6mods_download';
    $vars[] = 'token';
    $vars[] = 'vid';
    $vars[] = 'nojs';
    $vars[] = 'external_type';
    $vars[] = 'external_target';

    return $vars;
}
add_filter('query_vars', 'gta6mods_register_mod_download_query_vars');

/**
 * Flushes the rewrite rules on theme activation.
 */
function gta6mods_flush_mod_download_rewrite_rules(): void {
    flush_rewrite_rules(false);
}
add_action('after_switch_theme', 'gta6mods_flush_mod_download_rewrite_rules');

/**
 * Resolves the waiting room context based on query vars.
 *
 * @return array{
 *     mod: WP_Post|null,
 *     version: array|null,
 *     valid: bool
 * }
 */
function gta6mods_waiting_room_extract_domain(string $url): string {
    if ('' === $url) {
        return '';
    }

    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || '' === $host) {
        return '';
    }

    $host = strtolower($host);
    if (function_exists('str_starts_with')) {
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
    } elseif (0 === strncmp($host, 'www.', 4)) {
        $host = substr($host, 4);
    }

    return $host;
}

function gta6mods_get_waiting_room_context_data(): array {
    static $resolved = null;

    if (null !== $resolved) {
        return $resolved;
    }

    $resolved = [
        'mod'              => null,
        'version'          => null,
        'valid'            => false,
        'is_external'      => false,
        'external_url'     => '',
        'external_domain'  => '',
        'cache_key'        => '',
        'session_key'      => '',
        'waiting_room_url' => '',
    ];

    $slug_raw = get_query_var('mod_slug');
    $slug     = is_string($slug_raw) ? sanitize_title_for_query($slug_raw) : '';

    if ('' === $slug) {
        return $resolved;
    }

    $mod_post = get_page_by_path($slug, OBJECT, 'post');
    if (!$mod_post instanceof WP_Post) {
        return $resolved;
    }

    $mod_id = (int) $mod_post->ID;
    $status = $mod_post->post_status;

    if ('publish' !== $status && !current_user_can('read_post', $mod_id)) {
        return $resolved;
    }

    if ('trash' === $status) {
        return $resolved;
    }

    if (post_password_required($mod_post)) {
        return $resolved;
    }

    $version_id        = absint(get_query_var('version_id'));
    $external_type_raw = get_query_var('external_type');
    $external_target   = absint(get_query_var('external_target'));
    $external_type     = is_string($external_type_raw) ? strtolower($external_type_raw) : '';

    if ($version_id > 0) {
        $version = GTA6Mods_Mod_Versions::get_version($version_id);
        if (!$version || (int) $version['mod_id'] !== $mod_id) {
            return $resolved;
        }

        $resolved = [
            'mod'              => $mod_post,
            'version'          => $version,
            'valid'            => true,
            'is_external'      => false,
            'external_url'     => '',
            'external_domain'  => '',
            'cache_key'        => 'waiting_room_html_' . $version_id,
            'session_key'      => 'gta6mods_wait_' . $version_id,
            'waiting_room_url' => gta6_mods_get_waiting_room_url($mod_id, $version_id),
        ];

        return $resolved;
    }

    if (in_array($external_type, ['version', 'mod'], true)) {
        if ('version' === $external_type && $external_target > 0 && function_exists('gta6mods_prepare_version_array')) {
            $version_post = get_post($external_target);
            if ($version_post instanceof WP_Post && 'mod_version' === $version_post->post_type) {
                $parent_id = (int) get_post_meta($external_target, '_gta6mods_version_parent', true);
                if ($parent_id === $mod_id) {
                    $prepared = gta6mods_prepare_version_array($version_post);
                    $source   = isset($prepared['source']) && is_array($prepared['source']) ? $prepared['source'] : [];
                    $type     = isset($source['type']) ? strtolower((string) $source['type']) : '';
                    $url      = isset($source['url']) ? esc_url_raw($source['url']) : '';

                    if ('external' === $type && '' !== $url) {
                        $version_number = isset($prepared['number']) ? sanitize_text_field((string) $prepared['number']) : '';
                        $downloads      = isset($prepared['downloads']) ? (int) $prepared['downloads'] : 0;
                        $size_human     = isset($prepared['size_human']) ? sanitize_text_field((string) $prepared['size_human']) : '';
                        $upload_date    = isset($prepared['date']) ? $prepared['date'] : get_the_date('c', $version_post);

                        $version_payload = [
                            'id'             => $external_target,
                            'mod_id'         => $mod_id,
                            'version'        => $version_number,
                            'download_count' => $downloads,
                            'size_human'     => $size_human,
                            'upload_date'    => $upload_date,
                            'attachment_id'  => 0,
                            'source_type'    => 'external',
                            'external_url'   => $url,
                        ];

                        $waiting_room_url = gta6_mods_get_waiting_room_url(
                            $mod_id,
                            $external_target,
                            [
                                'external_type'   => 'version',
                                'external_target' => $external_target,
                            ]
                        );

                        $resolved = [
                            'mod'              => $mod_post,
                            'version'          => $version_payload,
                            'valid'            => true,
                            'is_external'      => true,
                            'external_url'     => $url,
                            'external_domain'  => gta6mods_waiting_room_extract_domain($url),
                            'cache_key'        => 'waiting_room_html_external_version_' . $external_target,
                            'session_key'      => sprintf('gta6mods_wait_ext_%d_%d', $mod_id, $external_target),
                            'waiting_room_url' => $waiting_room_url,
                        ];

                        return $resolved;
                    }
                }
            }
        }

        if ('mod' === $external_type) {
            $external_meta = get_post_meta($mod_id, '_gta6mods_mod_external', true);
            if (is_array($external_meta) && !empty($external_meta['url'])) {
                $url         = esc_url_raw($external_meta['url']);
                $size_human  = isset($external_meta['size_human']) ? sanitize_text_field((string) $external_meta['size_human']) : '';
                $version_num = function_exists('gta6_mods_get_mod_version') ? (string) gta6_mods_get_mod_version($mod_id) : '';
                $downloads   = function_exists('gta6_mods_get_download_count') ? (int) gta6_mods_get_download_count($mod_id) : 0;
                $upload_date = get_post_modified_time('c', true, $mod_post) ?: get_post_time('c', true, $mod_post);

                if ('' !== $url) {
                    $version_payload = [
                        'id'             => $mod_id,
                        'mod_id'         => $mod_id,
                        'version'        => $version_num,
                        'download_count' => $downloads,
                        'size_human'     => $size_human,
                        'upload_date'    => $upload_date,
                        'attachment_id'  => 0,
                        'source_type'    => 'external',
                        'external_url'   => $url,
                    ];

                    $waiting_room_url = gta6_mods_get_waiting_room_url(
                        $mod_id,
                        $mod_id,
                        [
                            'external_type'   => 'mod',
                            'external_target' => $mod_id,
                        ]
                    );

                    $resolved = [
                        'mod'              => $mod_post,
                        'version'          => $version_payload,
                        'valid'            => true,
                        'is_external'      => true,
                        'external_url'     => $url,
                        'external_domain'  => gta6mods_waiting_room_extract_domain($url),
                        'cache_key'        => sprintf('waiting_room_html_external_mod_%d', $mod_id),
                        'session_key'      => sprintf('gta6mods_wait_ext_mod_%d', $mod_id),
                        'waiting_room_url' => $waiting_room_url,
                    ];

                    return $resolved;
                }
            }
        }

        return $resolved;
    }

    $latest_version = GTA6Mods_Mod_Versions::get_latest_version($mod_id);
    if ($latest_version) {
        $version_id = isset($latest_version['id']) ? (int) $latest_version['id'] : 0;
        if ($version_id > 0) {
            $resolved = [
                'mod'              => $mod_post,
                'version'          => $latest_version,
                'valid'            => true,
                'is_external'      => false,
                'external_url'     => '',
                'external_domain'  => '',
                'cache_key'        => 'waiting_room_html_' . $version_id,
                'session_key'      => 'gta6mods_wait_' . $version_id,
                'waiting_room_url' => gta6_mods_get_waiting_room_url($mod_id, $version_id),
            ];

            return $resolved;
        }
    }

    $external_meta = get_post_meta($mod_id, '_gta6mods_mod_external', true);
    if (is_array($external_meta) && !empty($external_meta['url'])) {
        $url         = esc_url_raw($external_meta['url']);
        $size_human  = isset($external_meta['size_human']) ? sanitize_text_field((string) $external_meta['size_human']) : '';
        $version_num = function_exists('gta6_mods_get_mod_version') ? (string) gta6_mods_get_mod_version($mod_id) : '';
        $downloads   = function_exists('gta6_mods_get_download_count') ? (int) gta6_mods_get_download_count($mod_id) : 0;
        $upload_date = get_post_modified_time('c', true, $mod_post) ?: get_post_time('c', true, $mod_post);

        if ('' !== $url) {
            $version_payload = [
                'id'             => $mod_id,
                'mod_id'         => $mod_id,
                'version'        => $version_num,
                'download_count' => $downloads,
                'size_human'     => $size_human,
                'upload_date'    => $upload_date,
                'attachment_id'  => 0,
                'source_type'    => 'external',
                'external_url'   => $url,
            ];

            $waiting_room_url = gta6_mods_get_waiting_room_url(
                $mod_id,
                $mod_id,
                [
                    'external_type'   => 'mod',
                    'external_target' => $mod_id,
                ]
            );

            $resolved = [
                'mod'              => $mod_post,
                'version'          => $version_payload,
                'valid'            => true,
                'is_external'      => true,
                'external_url'     => $url,
                'external_domain'  => gta6mods_waiting_room_extract_domain($url),
                'cache_key'        => sprintf('waiting_room_html_external_mod_%d', $mod_id),
                'session_key'      => sprintf('gta6mods_wait_ext_mod_%d', $mod_id),
                'waiting_room_url' => $waiting_room_url,
            ];

            return $resolved;
        }
    }

    return $resolved;
}

/**
 * Serves cached waiting room HTML when available.
 */
function gta6mods_serve_cached_waiting_room(): void {
    if (!get_query_var('waiting_room')) {
        return;
    }

    if ((int) get_query_var('download_latest') === 1) {
        $context = gta6mods_get_waiting_room_context_data();
        if ($context['valid'] && !empty($context['waiting_room_url'])) {
            wp_safe_redirect($context['waiting_room_url'], 302);
            exit;
        }

        status_header(404);
        return;
    }

    $context = gta6mods_get_waiting_room_context_data();

    if (!$context['valid'] || !isset($context['mod'], $context['version'])) {
        status_header(404);
        return;
    }

    $mod_post = $context['mod'];
    $version  = $context['version'];

    if (!($mod_post instanceof WP_Post) || !is_array($version)) {
        status_header(404);
        return;
    }

    $waiting_room_url = isset($context['waiting_room_url']) ? $context['waiting_room_url'] : '';
    if (!is_string($waiting_room_url)) {
        $waiting_room_url = '';
    }

    if (!empty($context['is_external']) && '' !== $waiting_room_url) {
        $canonical_path = wp_parse_url($waiting_room_url, PHP_URL_PATH);
        $request_uri    = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
        $current_path   = $request_uri !== '' ? wp_parse_url(home_url($request_uri), PHP_URL_PATH) : '';

        if ($canonical_path && $current_path && trailingslashit($current_path) !== trailingslashit($canonical_path)) {
            wp_safe_redirect($waiting_room_url, 301);
            exit;
        }
    }

    $version_id  = isset($version['id']) ? (int) $version['id'] : 0;
    $cache_key   = isset($context['cache_key']) && '' !== $context['cache_key']
        ? $context['cache_key']
        : ($version_id > 0 ? 'waiting_room_html_' . $version_id : 'waiting_room_html');
    $is_external = !empty($context['is_external']);

    if (!$is_external && $version_id > 0) {
        gta6mods_store_nojs_permission((int) $mod_post->ID, $version_id);
    }

    set_query_var('gta6mods_waiting_room_context', $context);

    $admin_bar_visible = function_exists('is_admin_bar_showing') ? is_admin_bar_showing() : false;
    $should_cache      = !is_user_logged_in() && !is_admin() && !$admin_bar_visible;
    $cached       = false;

    if ($should_cache) {
        $cached = gta6mods_cache_get($cache_key, 'gta6mods_waiting_room');

        if (is_string($cached) && false !== strpos($cached, 'wp-admin-bar')) {
            gta6mods_cache_delete($cache_key, 'gta6mods_waiting_room');
            $cached = false;
        }
    }

    if (is_string($cached) && '' !== $cached) {
        header('Cache-Control: public, max-age=3600, s-maxage=86400');
        header('X-WP-CF-Super-Cache: HIT');
        echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    $template = locate_template('page-template-waiting-room.php');
    if (!$template) {
        status_header(500);
        return;
    }

    ob_start();
    load_template($template, false);
    $html = ob_get_clean();

    $html_is_string = is_string($html) && '' !== trim($html);

    if ($should_cache && $html_is_string) {
        gta6mods_cache_set($cache_key, $html, 'gta6mods_waiting_room', HOUR_IN_SECONDS);
        header('Cache-Control: public, max-age=3600, s-maxage=86400');
        header('X-WP-CF-Super-Cache: MISS');
    } else {
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('X-WP-CF-Super-Cache: BYPASS');
    }

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}


add_action('template_redirect', 'gta6mods_serve_cached_waiting_room', 5);

/**
 * Routes waiting room requests to the dedicated template.
 *
 * @param string $template Template path.
 *
 * @return string
 */
function gta6mods_filter_waiting_room_template(string $template): string {
    $waiting_room = get_query_var('waiting_room');

    if (empty($waiting_room)) {
        return $template;
    }

    $context = gta6mods_get_waiting_room_context_data();

    if (!$context['valid']) {
        status_header(404);
        return get_404_template();
    }

    $mod_post     = $context['mod'];
    $version_data = $context['version'];

    if (!$mod_post instanceof WP_Post || !is_array($version_data)) {
        status_header(404);
        return get_404_template();
    }

    set_query_var('gta6mods_waiting_room_context', $context);

    $custom_template = locate_template('page-template-waiting-room.php');
    if ($custom_template) {
        return $custom_template;
    }

    return $template;
}
add_filter('template_include', 'gta6mods_filter_waiting_room_template');

/**
 * Loads lightweight assets for the waiting room page.
 */
function gta6mods_enqueue_waiting_room_assets(): void {
    if (!get_query_var('waiting_room')) {
        return;
    }

    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('classic-theme-styles');

    $context = gta6mods_get_waiting_room_context_data();
    if (!$context['valid'] || !$context['mod'] instanceof WP_Post || !is_array($context['version'])) {
        return;
    }

    $mod_id      = (int) $context['mod']->ID;
    $version     = $context['version'];
    $version_id  = isset($version['id']) ? $version['id'] : 0;
    $countdown   = (int) apply_filters('gta6mods_waiting_room_countdown', 5);
    if ($countdown <= 0) {
        $countdown = 5;
    }
    $is_external = !empty($context['is_external']);
    $session_key = !empty($context['session_key']) ? sanitize_key($context['session_key']) : 'gta6mods_wait_' . $version_id;
    $external_url    = $is_external && !empty($context['external_url']) ? esc_url_raw($context['external_url']) : '';
    $external_domain = $is_external && !empty($context['external_domain']) ? sanitize_text_field($context['external_domain']) : '';

    $download_js = get_template_directory() . '/assets/js/waiting-room.js';
    $download_css = get_template_directory() . '/assets/css/waiting-room.css';

    if (file_exists($download_css)) {
        wp_enqueue_style(
            'gta6mods-waiting-room',
            get_template_directory_uri() . '/assets/css/waiting-room.css',
            [],
            (string) filemtime($download_css)
        );
    }

    if (file_exists($download_js)) {
        wp_enqueue_script(
            'gta6mods-waiting-room',
            get_template_directory_uri() . '/assets/js/waiting-room.js',
            [],
            (string) filemtime($download_js),
            true
        );

        $strings = [
            'preparing'     => __('Preparing download…', 'gta6-mods'),
            'ready'         => __('Download Now', 'gta6-mods'),
            'countdown'     => __('Download starts in %d seconds.', 'gta6-mods'),
            'requestFailed' => __('We could not prepare your download. Please try again.', 'gta6-mods'),
            'rateLimited'   => __('Too many download attempts detected. Please wait a moment and try again.', 'gta6-mods'),
        ];

        if ($is_external) {
            $strings['preparing'] = __('Preparing external link…', 'gta6-mods');
            $strings['ready'] = __('Continue to external site', 'gta6-mods');
            if ('' !== $external_domain) {
                $strings['countdown'] = sprintf(
                    __('You will be redirected to %1$s in %2$s seconds.', 'gta6-mods'),
                    $external_domain,
                    '%d'
                );
            } else {
                $strings['countdown'] = __('You will be redirected in %d seconds.', 'gta6-mods');
            }
            $strings['requestFailed'] = __('We could not open the external link. Please try again.', 'gta6-mods');
        }

        wp_localize_script(
            'gta6mods-waiting-room',
            'gta6modsWaitingRoom',
            [
                'restUrl'          => esc_url_raw(rest_url('gta6mods/v1/generate-download-token')),
                'nonce'            => wp_create_nonce('wp_rest'),
                'versionId'        => $version_id,
                'modId'            => $mod_id,
                'countdownSeconds' => $countdown,
                'tokenTtl'         => 60,
                'downloadBase'     => esc_url_raw(home_url('/download-file/')),
                'mode'             => $is_external ? 'external' : 'internal',
                'externalUrl'      => $external_url,
                'externalDomain'   => $external_domain,
                'sessionKey'       => $session_key,
                'downloadEndpoint' => esc_url_raw(rest_url('gta6mods/v1/mod/' . $mod_id . '/download')),
                'strings'          => $strings,
            ]
        );
    }
}


add_action('wp_enqueue_scripts', 'gta6mods_enqueue_waiting_room_assets', 100);

/**
 * Adds a dedicated body class for the waiting room template.
 */
function gta6mods_waiting_room_body_class(array $classes): array {
    if (get_query_var('waiting_room')) {
        $classes[] = 'waiting-room-page';
    }

    return $classes;
}
add_filter('body_class', 'gta6mods_waiting_room_body_class');

/**
 * Builds metadata payload for waiting room pages.
 *
 * @return array
 */
function gta6mods_get_waiting_room_meta_payload(): array {
    static $payload = null;

    if (null !== $payload) {
        return $payload;
    }

    $payload = [];

    if (!get_query_var('waiting_room')) {
        return $payload;
    }

    $context = gta6mods_get_waiting_room_context_data();

    if (!$context['valid'] || !$context['mod'] instanceof WP_Post || !is_array($context['version'])) {
        return $payload;
    }

    $mod        = $context['mod'];
    $version    = $context['version'];
    $waiting_url = isset($context['waiting_room_url']) && '' !== $context['waiting_room_url'] ? $context['waiting_room_url'] : '';
    $is_external = !empty($context['is_external']);
    $external_domain = $is_external && !empty($context['external_domain']) ? sanitize_text_field($context['external_domain']) : '';
    $external_url    = $is_external && !empty($context['external_url']) ? esc_url_raw($context['external_url']) : '';

    $mod_id          = (int) $mod->ID;
    $mod_title       = get_the_title($mod);
    $version_number  = isset($version['version']) ? sanitize_text_field((string) $version['version']) : '';
    $version_id      = isset($version['id']) ? (int) $version['id'] : 0;
    $version_date    = isset($version['upload_date']) ? $version['upload_date'] : '';
    $attachment_id   = isset($version['attachment_id']) ? (int) $version['attachment_id'] : 0;
    $file_size_display = '';

    if ($is_external) {
        $file_size_display = isset($version['size_human']) ? sanitize_text_field((string) $version['size_human']) : '';
    } elseif ($attachment_id > 0) {
        $file_size_bytes = (int) get_post_meta($attachment_id, '_filesize', true);
        if ($file_size_bytes <= 0) {
            $attachment_path = get_attached_file($attachment_id);
            if ($attachment_path && file_exists($attachment_path)) {
                $file_size_bytes = (int) filesize($attachment_path);
            }
        }

        if ($file_size_bytes > 0) {
            $file_size_display = size_format((float) $file_size_bytes);
        }
    }

    $mod_permalink = $waiting_url ? $waiting_url : gta6_mods_get_waiting_room_url($mod_id, $version_id);
    $site_name     = get_bloginfo('name');
    $site_desc     = get_bloginfo('description');
    $author_id     = (int) $mod->post_author;
    $author_name   = get_the_author_meta('display_name', $author_id);
    if (!$author_name) {
        $author_name = get_the_author_meta('user_nicename', $author_id);
    }
    $author_name = $author_name ? $author_name : __('Unknown author', 'gta6-mods');
    $author_url  = get_author_posts_url($author_id);

    $raw_excerpt = $mod->post_excerpt;
    if (!$raw_excerpt) {
        $raw_excerpt = get_post_field('post_excerpt', $mod_id);
    }
    if (!$raw_excerpt) {
        $raw_excerpt = get_post_field('post_content', $mod_id);
    }
    $raw_excerpt = wp_strip_all_tags((string) $raw_excerpt);
    $raw_excerpt = preg_replace('/\s+/u', ' ', (string) $raw_excerpt);
    $excerpt     = wp_trim_words($raw_excerpt, 28, '…');

    $taxonomies = ['mod_category', 'category'];
    $terms      = [];
    foreach ($taxonomies as $taxonomy) {
        $term_names = wp_get_post_terms($mod_id, $taxonomy, ['fields' => 'names']);
        if (is_wp_error($term_names) || empty($term_names)) {
            continue;
        }
        $terms = array_merge($terms, array_filter($term_names, 'is_string'));
    }
    $terms = array_values(array_unique($terms));
    if (count($terms) > 3) {
        $terms = array_slice($terms, 0, 3);
    }

    $meta_description_parts = [];
    $meta_description_parts[] = sprintf(
        /* translators: %s: mod title */
        __('Download %s instantly from GTA6-Mods.com.', 'gta6-mods'),
        $mod_title
    );

    if ('' !== $version_number) {
        $meta_description_parts[] = sprintf(
            /* translators: %s: version number */
            __('Current version: %s.', 'gta6-mods'),
            $version_number
        );
    }

    if ('' !== $file_size_display) {
        $meta_description_parts[] = sprintf(
            /* translators: %s: file size */
            __('File size: %s.', 'gta6-mods'),
            $file_size_display
        );
    }

    if (!empty($terms)) {
        $meta_description_parts[] = sprintf(
            /* translators: %s: comma separated categories */
            __('Categories: %s.', 'gta6-mods'),
            implode(', ', $terms)
        );
    }

    if ($is_external && '' !== $external_domain) {
        $meta_description_parts[] = sprintf(
            /* translators: %s: external domain */
            __('Hosted externally on %s. You are leaving GTA6-Mods.com for this download.', 'gta6-mods'),
            $external_domain
        );
    }

    if ('' !== $excerpt) {
        $meta_description_parts[] = $excerpt;
    }

    $meta_description = trim(implode(' ', array_filter($meta_description_parts)));

    $seo_title = $version_number
        ? sprintf(
            /* translators: 1: mod title, 2: version */
            __('Downloading %1$s (v%2$s)', 'gta6-mods'),
            $mod_title,
            $version_number
        )
        : sprintf(
            /* translators: %s: mod title */
            __('Downloading %s', 'gta6-mods'),
            $mod_title
        );

    if ($is_external && '' !== $external_domain) {
        $seo_title = sprintf(
            /* translators: 1: external domain, 2: mod title */
            __('Continue to %1$s to download %2$s', 'gta6-mods'),
            $external_domain,
            $mod_title
        );
    }

    $thumbnail_id   = get_post_thumbnail_id($mod);
    $image_src      = '';
    $image_width    = 0;
    $image_height   = 0;
    if ($thumbnail_id) {
        $image_data = wp_get_attachment_image_src($thumbnail_id, 'large');
        if (is_array($image_data) && isset($image_data[0])) {
            $image_src    = $image_data[0];
            $image_width  = isset($image_data[1]) ? (int) $image_data[1] : 0;
            $image_height = isset($image_data[2]) ? (int) $image_data[2] : 0;
        }
    }
    if (!$image_src) {
        $image_src = gta6_mods_get_placeholder('featured');
    }

    $payload = [
        'seo_title'   => $seo_title,
        'description' => $meta_description,
        'canonical'   => $mod_permalink,
        'site_name'   => $site_name,
        'site_desc'   => $site_desc,
        'image'       => [
            'url'    => $image_src,
            'width'  => $image_width,
            'height' => $image_height,
        ],
        'author'      => [
            'name' => $author_name,
            'url'  => $author_url,
        ],
        'version'     => [
            'number' => $version_number,
            'date'   => $version_date,
            'size'   => $file_size_display,
        ],
        'mod'         => [
            'id'       => $mod_id,
            'title'    => $mod_title,
            'url'      => get_permalink($mod),
            'excerpt'  => $excerpt,
            'terms'    => $terms,
        ],
        'external'    => [
            'enabled' => $is_external,
            'domain'  => $external_domain,
            'url'     => $external_url,
        ],
    ];

    return $payload;
}


function gta6mods_waiting_room_document_title(array $parts): array {
    $meta = gta6mods_get_waiting_room_meta_payload();

    if (empty($meta)) {
        return $parts;
    }

    if (!empty($meta['seo_title'])) {
        $parts['title'] = $meta['seo_title'];
    }

    if (!empty($meta['site_name'])) {
        $parts['site'] = $meta['site_name'];
    }

    return $parts;
}
add_filter('document_title_parts', 'gta6mods_waiting_room_document_title', 20);

/**
 * Adjusts the canonical URL for waiting room pages.
 *
 * @param string|null $canonical Existing canonical.
 *
 * @return string|null
 */
function gta6mods_waiting_room_canonical(?string $canonical): ?string {
    $meta = gta6mods_get_waiting_room_meta_payload();

    if (empty($meta) || empty($meta['canonical'])) {
        return $canonical;
    }

    return $meta['canonical'];
}
add_filter('pre_get_canonical_url', 'gta6mods_waiting_room_canonical');

/**
 * Outputs SEO meta tags for the waiting room.
 */
function gta6mods_waiting_room_meta_tags(): void {
    $meta = gta6mods_get_waiting_room_meta_payload();

    if (empty($meta)) {
        return;
    }

    $description = isset($meta['description']) ? $meta['description'] : '';
    $canonical   = isset($meta['canonical']) ? $meta['canonical'] : '';
    $site_name   = isset($meta['site_name']) ? $meta['site_name'] : '';
    $image       = isset($meta['image']) && is_array($meta['image']) ? $meta['image'] : [];
    $author      = isset($meta['author']) && is_array($meta['author']) ? $meta['author'] : [];
    $mod         = isset($meta['mod']) && is_array($meta['mod']) ? $meta['mod'] : [];
    $version     = isset($meta['version']) && is_array($meta['version']) ? $meta['version'] : [];

    if ('' !== $description) {
        printf("\n<meta name=\"description\" content=\"%s\" />\n", esc_attr($description));
    }

    if ('' !== $canonical) {
        printf('<meta property="og:url" content="%s" />' . "\n", esc_url($canonical));
    }

    if (!empty($meta['seo_title'])) {
        printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($meta['seo_title']));
        printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($meta['seo_title']));
    }

    if ('' !== $description) {
        printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($description));
        printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($description));
    }

    if ('' !== $site_name) {
        printf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr($site_name));
    }

    if (!empty($author['name'])) {
        printf('<meta name="author" content="%s" />' . "\n", esc_attr($author['name']));
        printf('<meta property="article:author" content="%s" />' . "\n", esc_attr($author['name']));
    }

    if (!empty($image['url'])) {
        printf('<meta property="og:image" content="%s" />' . "\n", esc_url($image['url']));
        printf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($image['url']));
        if (!empty($image['width'])) {
            printf('<meta property="og:image:width" content="%d" />' . "\n", (int) $image['width']);
        }
        if (!empty($image['height'])) {
            printf('<meta property="og:image:height" content="%d" />' . "\n", (int) $image['height']);
        }
    }

    printf('<meta property="og:type" content="article" />' . "\n");
    printf('<meta name="twitter:card" content="summary_large_image" />' . "\n");

    if (!empty($version['date'])) {
        printf('<meta property="article:published_time" content="%s" />' . "\n", esc_attr(mysql2date(DATE_W3C, $version['date'], false)));
    }

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'SoftwareApplication',
        '@id'             => $canonical ? $canonical . '#waiting-room' : '',
        'name'            => isset($mod['title']) ? $mod['title'] : '',
        'operatingSystem' => 'PC',
        'applicationCategory' => 'GameModification',
        'url'             => $canonical,
        'image'           => isset($image['url']) ? $image['url'] : '',
        'author'          => [
            '@type' => 'Person',
            'name'  => isset($author['name']) ? $author['name'] : '',
            'url'   => isset($author['url']) ? $author['url'] : '',
        ],
        'publisher'       => [
            '@type' => 'Organization',
            'name'  => $site_name,
            'url'   => home_url('/'),
        ],
        'description'     => $description,
    ];

    if (!empty($version['number'])) {
        $schema['softwareVersion'] = $version['number'];
    }

    if (!empty($version['date'])) {
        $schema['datePublished'] = mysql2date(DATE_W3C, $version['date'], false);
    }

    if (!empty($version['size'])) {
        $schema['fileSize'] = $version['size'];
    }

    if (!empty($mod['terms'])) {
        $schema['keywords'] = implode(', ', $mod['terms']);
    }

    echo '<script type="application/ld+json">' . wp_json_encode(array_filter($schema)) . '</script>' . "\n";
}
add_action('wp_head', 'gta6mods_waiting_room_meta_tags', 5);

/**
 * Purges Cloudflare cache entries when a version changes.
 */
function gta6mods_purge_cloudflare_on_version_update(int $mod_id, int $version_id): void {
    $credentials = function_exists('gta6_get_cloudflare_credentials') ? gta6_get_cloudflare_credentials() : ['zone_id' => null, 'api_token' => null];
    $zone_id     = $credentials['zone_id'];
    $api_token   = $credentials['api_token'];

    if (empty($zone_id) || empty($api_token)) {
        return;
    }

    $urls = [];

    $permalink = get_permalink($mod_id);
    if ($permalink) {
        $urls[] = esc_url_raw($permalink);
    }

    if (function_exists('gta6_mods_get_waiting_room_url')) {
        $urls[] = esc_url_raw(gta6_mods_get_waiting_room_url($mod_id, $version_id));
        $urls[] = esc_url_raw(gta6_mods_get_waiting_room_url($mod_id, 0));
    }

    $urls = array_filter(array_unique($urls));

    if (empty($urls)) {
        return;
    }

    $response = wp_remote_post(
        'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . '/purge_cache',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode(['files' => $urls]),
            'timeout' => 10,
        ]
    );

    if (is_wp_error($response)) {
        error_log('GTA6_MODS_PURGE_ERROR: Failed to purge Cloudflare cache for mod update. ' . $response->get_error_message());
        return;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code && (int) $status_code >= 400) {
        error_log('GTA6_MODS_PURGE_ERROR: Cloudflare API returned status ' . $status_code . ' when purging mod cache.');
    }
}
add_action('gta6mods_version_updated', 'gta6mods_purge_cloudflare_on_version_update', 10, 2);

/**
 * Intelligently purge Cloudflare cache when forum content changes.
 */
function gta6_forum_purge_cloudflare_cache(int $post_id): void {
    if ('forum_thread' !== get_post_type($post_id)) {
        return;
    }

    $credentials = function_exists('gta6_get_cloudflare_credentials') ? gta6_get_cloudflare_credentials() : ['zone_id' => null, 'api_token' => null];
    $zone_id     = $credentials['zone_id'];
    $api_token   = $credentials['api_token'];

    if (empty($zone_id) || empty($api_token)) {
        return;
    }

    $urls_to_purge = [
        esc_url_raw(home_url('/forum/')),
    ];

    $thread_permalink = get_permalink($post_id);
    if (!empty($thread_permalink)) {
        $urls_to_purge[] = esc_url_raw($thread_permalink);
    }

    $flairs = get_the_terms($post_id, 'forum_flair');
    if (!empty($flairs) && !is_wp_error($flairs)) {
        foreach ($flairs as $flair) {
            $flair_link = get_term_link($flair);
            if (!is_wp_error($flair_link) && !empty($flair_link)) {
                $urls_to_purge[] = esc_url_raw($flair_link);
            }
        }
    }

    $urls_to_purge = array_filter(array_unique(array_map('esc_url_raw', $urls_to_purge)));

    if (empty($urls_to_purge)) {
        return;
    }

    $response = wp_remote_post(
        'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . '/purge_cache',
        [
            'method'  => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode(['files' => array_values($urls_to_purge)]),
            'timeout' => 15,
        ]
    );

    if (is_wp_error($response)) {
        error_log('GTA6_FORUM_PURGE_ERROR: Failed to connect to Cloudflare API. ' . $response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ((int) $response_code !== 200) {
        $body = wp_remote_retrieve_body($response);
        error_log('GTA6_FORUM_PURGE_ERROR: Cloudflare API returned non-200 response. Code: ' . $response_code . ' Body: ' . $body);
    }
}
add_action('save_post_forum_thread', 'gta6_forum_purge_cloudflare_cache');
add_action('delete_post', 'gta6_forum_purge_cloudflare_cache');

/**
 * Creates the database table used for storing mod videos on theme activation.
 */
function gta6mods_activate_video_table() {
    global $wpdb;

    $table_name      = gta6mods_get_video_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mod_id BIGINT UNSIGNED NOT NULL,
        youtube_url VARCHAR(255) NOT NULL,
        youtube_id VARCHAR(20) NOT NULL,
        thumbnail_path VARCHAR(255) NULL,
        video_title VARCHAR(255) NULL,
        video_description TEXT NULL,
        duration VARCHAR(20) NULL,
        thumbnail_attachment_id BIGINT UNSIGNED NULL,
        status ENUM('pending','approved','rejected','reported') DEFAULT 'pending',
        submitted_by BIGINT UNSIGNED NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        moderated_by BIGINT UNSIGNED NULL,
        moderated_at DATETIME NULL,
        report_count INT UNSIGNED DEFAULT 0,
        last_reported_at DATETIME NULL,
        last_reported_by BIGINT UNSIGNED NULL,
        view_count INT UNSIGNED DEFAULT 0,
        position INT UNSIGNED DEFAULT 0,
        is_featured TINYINT(1) UNSIGNED DEFAULT 0,
        featured_at DATETIME NULL,
        INDEX idx_mod_approved (mod_id, status, position),
        INDEX idx_status (status),
        INDEX idx_youtube_id (youtube_id),
        INDEX idx_submitted_by (submitted_by),
        UNIQUE KEY unique_mod_youtube (mod_id, youtube_id)
    ) {$charset_collate};";

    $reports_table = GTA6MODS_VIDEO_REPORTS_TABLE;

    $reports_sql = "CREATE TABLE {$reports_table} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        video_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_report (video_id, user_id),
        INDEX idx_video (video_id)
    ) {$charset_collate};";

    dbDelta($sql);
    dbDelta($reports_sql);
}
add_action('after_switch_theme', 'gta6mods_activate_video_table');

/**
 * Ensures video table exists on every theme load (safety check).
 */
function gta6mods_ensure_video_table_exists() {
    global $wpdb;

    $table_name   = gta6mods_get_video_table_name();
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

    if ($table_name !== $table_exists) {
        gta6mods_activate_video_table();
        return;
    }

    $required_columns = [
        'thumbnail_attachment_id',
        'video_title',
        'video_description',
        'duration',
        'position',
        'report_count',
        'last_reported_at',
        'last_reported_by',
        'view_count',
        'is_featured',
        'featured_at',
    ];

    foreach ($required_columns as $column) {
        $column_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $column)
        );

        if (empty($column_exists)) {
            gta6mods_activate_video_table();
            return;
        }
    }

    $reports_table = GTA6MODS_VIDEO_REPORTS_TABLE;
    $reports_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $reports_table));

    if ($reports_table !== $reports_exists) {
        gta6mods_activate_video_table();
    }
}
add_action('after_setup_theme', 'gta6mods_ensure_video_table_exists');

/**
 * Registers the YouTube API key setting.
 */
function gta6mods_register_video_settings() {
    register_setting(
        'gta6mods_video_settings',
        'gta6mods_youtube_api_key',
        [
            'type'              => 'string',
            'sanitize_callback' => static function ($value) {
                $value = is_string($value) ? trim($value) : '';

                return $value !== '' ? sanitize_text_field($value) : '';
            },
            'default'           => '',
        ]
    );

    register_setting(
        'gta6mods_video_settings',
        'gta6mods_waiting_room_security_mode',
        [
            'type'              => 'string',
            'sanitize_callback' => static function ($value) {
                $value = is_string($value) ? strtolower(trim($value)) : 'strict';

                return in_array($value, ['reduced', 'strict'], true) ? $value : 'strict';
            },
            'default'           => 'strict',
        ]
    );
}
add_action('admin_init', 'gta6mods_register_video_settings');

/**
 * Adds the GTA6 Mods settings page for storing API credentials.
 */
function gta6mods_add_video_settings_page() {
    add_options_page(
        __('GTA6 Mods Settings', 'gta6-mods'),
        __('GTA6 Mods Settings', 'gta6-mods'),
        'manage_options',
        'gta6mods-settings',
        'gta6mods_render_video_settings_page'
    );
}
add_action('admin_menu', 'gta6mods_add_video_settings_page');

/**
 * Renders the GTA6 Mods settings page.
 */
function gta6mods_render_video_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.', 'gta6-mods'));
    }

    $api_key        = get_option('gta6mods_youtube_api_key', '');
    $security_mode  = get_option('gta6mods_waiting_room_security_mode', 'strict');
    $security_value = in_array($security_mode, ['reduced', 'strict'], true) ? $security_mode : 'strict';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('GTA6 Mods Settings', 'gta6-mods'); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('gta6mods_video_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="gta6mods_youtube_api_key"><?php esc_html_e('YouTube Data API Key', 'gta6-mods'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            class="regular-text ltr"
                            id="gta6mods_youtube_api_key"
                            name="gta6mods_youtube_api_key"
                            value="<?php echo esc_attr($api_key); ?>"
                            autocomplete="off"
                            spellcheck="false"
                        />
                        <p class="description">
                            <?php esc_html_e('Enter your YouTube Data API v3 key. This is used to enrich approved videos with metadata for SEO.', 'gta6-mods'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Waiting Room Protection', 'gta6-mods'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Waiting Room Protection', 'gta6-mods'); ?></legend>
                            <label>
                                <input type="radio" name="gta6mods_waiting_room_security_mode" value="strict" <?php checked('strict', $security_value); ?> />
                                <strong><?php esc_html_e('Maximum Security', 'gta6-mods'); ?></strong>
                            </label>
                            <p class="description"><?php esc_html_e('Use IP and device fingerprinting to strictly limit token re-use and rapid re-download attempts.', 'gta6-mods'); ?></p>
                            <label>
                                <input type="radio" name="gta6mods_waiting_room_security_mode" value="reduced" <?php checked('reduced', $security_value); ?> />
                                <strong><?php esc_html_e('Reduced Security', 'gta6-mods'); ?></strong>
                            </label>
                            <p class="description"><?php esc_html_e('Relax the fingerprinting so users on shared networks (schools, offices) can download without running into protection limits.', 'gta6-mods'); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Changes', 'gta6-mods')); ?>
        </form>
    </div>
    <?php
}

/**
 * Enqueues assets for the mod video gallery.
 */
function gta6mods_enqueue_video_gallery_assets() {
    if (!is_singular('post')) {
        return;
    }

    $script_path = get_template_directory() . '/assets/js/video-gallery.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_script(
        'gta6mods-video-gallery',
        get_template_directory_uri() . '/assets/js/video-gallery.js',
        ['jquery'],
        filemtime($script_path),
        true
    );

    $style_path = get_template_directory() . '/assets/css/video-gallery.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'gta6mods-video-gallery',
            get_template_directory_uri() . '/assets/css/video-gallery.css',
            [],
            filemtime($style_path)
        );
    }

    $mod_id        = get_queried_object_id();
    $current_user  = get_current_user_id();
    $mod_author_id = (int) get_post_field('post_author', $mod_id);
    $can_moderate  = current_user_can('moderate_comments');
    $is_mod_author = $current_user > 0 && $current_user === $mod_author_id;

    wp_localize_script(
        'gta6mods-video-gallery',
        'gta6modsVideoData',
        [
            'restUrl'     => esc_url_raw(rest_url()),
            'nonce'       => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'canModerate' => $can_moderate,
            'profileUrl'  => home_url('/user/'),
            'modId'       => $mod_id,
            'isLoggedIn'  => is_user_logged_in(),
            'loginUrl'    => home_url('/login'),
            'currentUserId' => $current_user,
            'modAuthorId'   => $mod_author_id,
            'canManage'     => ($can_moderate || $is_mod_author),
            'canFeature'    => ($can_moderate || $is_mod_author),
            'i18n'        => [
                'submitVideo'         => __('Submit Video', 'gta6-mods'),
                'youtubeUrl'          => __('YouTube URL', 'gta6-mods'),
                'urlHelp'             => __('Paste the full YouTube video link.', 'gta6-mods'),
                'rateLimit'           => __('You can submit up to 3 videos per day.', 'gta6-mods'),
                'submitForModeration' => __('Submit for Moderation', 'gta6-mods'),
                'submitting'          => __('Submitting…', 'gta6-mods'),
                'submitError'         => __('Failed to submit video. Please try again.', 'gta6-mods'),
                'submitSuccess'       => __('Video submitted successfully! It will appear after moderation.', 'gta6-mods'),
                'rateLimitError'      => __('You have reached your daily limit of 3 videos. Please try again tomorrow.', 'gta6-mods'),
                'duplicateError'      => __('This video has already been submitted for this mod.', 'gta6-mods'),
                'loading'             => __('Loading…', 'gta6-mods'),
                'addedBy'             => __('Added by', 'gta6-mods'),
                'reportVideo'         => __('Report', 'gta6-mods'),
                'reportAlready'       => __('Reported', 'gta6-mods'),
                'deleteVideo'         => __('Delete', 'gta6-mods'),
                'confirmReport'       => __('Report this video?', 'gta6-mods'),
                'reportModalTitle'    => __('Report this video', 'gta6-mods'),
                'reportModalDescription' => __('If the video has been removed or is inappropriate, report it so a moderator can review and take action.', 'gta6-mods'),
                'reportModalConfirm'  => __('Submit report', 'gta6-mods'),
                'reportModalCancel'   => __('Cancel', 'gta6-mods'),
                'reportError'         => __('Failed to report video.', 'gta6-mods'),
                'reportSuccess'       => __('Thanks! Your report was received and will be reviewed shortly. Thank you for helping us maintain the site’s quality!', 'gta6-mods'),
                'reportSuccessTitle'       => __('Thank you!', 'gta6-mods'),
                'reportSuccessBodyPrimary' => __('Your report was received and will be reviewed shortly.', 'gta6-mods'),
                'reportSuccessBodySecondary' => __('Thank you for helping us maintain the site’s quality!', 'gta6-mods'),
                'reportAlreadySubmitted' => __('You have already reported this video.', 'gta6-mods'),
                'deleteError'         => __('Failed to update video.', 'gta6-mods'),
                'deleteSuccess'       => __('Video hidden from gallery.', 'gta6-mods'),
                'deleteSuccessAuthor' => __('Video deleted.', 'gta6-mods'),
                'deleteConfirm'       => __('Hide this video from the gallery?', 'gta6-mods'),
                'deleteModalTitle'    => __('Hide this video?', 'gta6-mods'),
                'deleteModalDescription' => __('This will hide the video from the gallery but keep it available to moderators.', 'gta6-mods'),
                'deleteModalConfirm'  => __('Hide video', 'gta6-mods'),
                'deleteModalCancel'   => __('Cancel', 'gta6-mods'),
                'deleteModalTitleAuthor' => __('Delete this video?', 'gta6-mods'),
                'deleteModalDescriptionAuthor' => __('You created this mod, so you can delete videos submitted to it. Deleted videos cannot be republished and this action cannot be undone.', 'gta6-mods'),
                'deleteModalConfirmAuthor' => __('Delete video', 'gta6-mods'),
                'featureVideo'        => __('Feature this video', 'gta6-mods'),
                'featureActive'       => __('Featured', 'gta6-mods'),
                'featureSuccess'      => __('Featured video updated.', 'gta6-mods'),
                'featureError'        => __('Failed to feature video.', 'gta6-mods'),
                'featureRemoved'      => __('Featured video removed.', 'gta6-mods'),
                'featureRemoveError'  => __('Failed to remove featured video.', 'gta6-mods'),
                'featureModalTitle'   => __('Feature this video?', 'gta6-mods'),
                'featureModalDescription' => __('Feature this video to move it to the front of the gallery so it is seen first by visitors.', 'gta6-mods'),
                'featureModalTitleAuthor' => __('Feature this video for your mod?', 'gta6-mods'),
                'featureModalDescriptionAuthor' => __('You created this mod, so you can feature one video. The featured video appears first in the gallery and is likely what visitors will watch most.', 'gta6-mods'),
                'featureModalConfirm' => __('Feature video', 'gta6-mods'),
                'featureModalCancel'  => __('Cancel', 'gta6-mods'),
                'close'               => __('Close', 'gta6-mods'),
                'previous'            => __('Previous video', 'gta6-mods'),
                'next'                => __('Next video', 'gta6-mods'),
                'loginRequired'       => __('Please log in to report videos.', 'gta6-mods'),
                'loginReportRequired' => __('You must be logged in to report videos.', 'gta6-mods'),
                'noPermission'        => __('You do not have permission to perform this action.', 'gta6-mods'),
                'loginModalTitle'     => __('Login Required', 'gta6-mods'),
                'loginModalMessage'   => __('You must be logged in to continue.', 'gta6-mods'),
                'loginModalReportMessage' => __('You must be logged in to report a video.', 'gta6-mods'),
                'loginButton'         => __('Log in', 'gta6-mods'),
                'youtubePlayerTitle'  => __('YouTube video player', 'gta6-mods'),
                'playFeaturedVideo'   => __('Play featured video: %s', 'gta6-mods'),
                'videoPlaceholder'    => __('this video', 'gta6-mods'),
                'toastClose'          => __('Dismiss', 'gta6-mods'),
                'loadMoreCount'       => __('Load %d more', 'gta6-mods'),
            ],
        ]
    );
}
add_action('wp_enqueue_scripts', 'gta6mods_enqueue_video_gallery_assets', 60);

/**
 * Registers a minutely cron schedule for the download queue processor.
 */
function gta6mods_register_download_queue_schedule(array $schedules): array {
    if (!isset($schedules['gta6mods_download_minutely'])) {
        $schedules['gta6mods_download_minutely'] = [
            'interval' => MINUTE_IN_SECONDS,
            'display'  => __('GTA6 Mods download queue (every minute)', 'gta6-mods'),
        ];
    }

    return $schedules;
}
add_filter('cron_schedules', 'gta6mods_register_download_queue_schedule');

/**
 * Ensures the download queue processor cron event is scheduled.
 */
function gta6mods_schedule_download_queue_event(): void {
    if (!wp_using_ext_object_cache()) {
        return;
    }

    if (!wp_next_scheduled('gta6mods_process_download_queue_event')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'gta6mods_download_minutely', 'gta6mods_process_download_queue_event');
    }
}
add_action('init', 'gta6mods_schedule_download_queue_event');
