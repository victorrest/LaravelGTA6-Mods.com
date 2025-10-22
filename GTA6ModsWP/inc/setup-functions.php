<?php
/**
 * Theme setup and asset enqueueing.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function gta6_mods_setup() {
    load_theme_textdomain('gta6-mods', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support(
        'html5',
        [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ]
    );

    register_nav_menus(
        [
            'primary' => __('Fő menü', 'gta6-mods'),
        ]
    );
}
add_action('after_setup_theme', 'gta6_mods_setup');

/**
 * Enqueues scripts and styles.
 */
function gta6_mods_enqueue_assets() {
    wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', [], null, false);
    wp_enqueue_style(
        'gta6-mods-google-fonts',
        'https://fonts.googleapis.com/css2?family=Audiowide&family=Inter:wght@400;500;600;700;800&family=Oswald:wght@600&display=swap',
        [],
        null
    );
    wp_enqueue_style('font-awesome-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');

    $theme_css = get_template_directory() . '/assets/css/theme.css';
    if (file_exists($theme_css)) {
        wp_enqueue_style('gta6-mods-theme', get_template_directory_uri() . '/assets/css/theme.css', [], filemtime($theme_css));
    }

    $utils_js = get_template_directory() . '/assets/js/utils.js';
    if (file_exists($utils_js)) {
        wp_enqueue_script('gta6-mods-utils', get_template_directory_uri() . '/assets/js/utils.js', [], filemtime($utils_js), true);

        wp_add_inline_script(
            'gta6-mods-utils',
            'window.GTAModsSecurity = window.GTAModsSecurity || {}; window.GTAModsSecurity.trackingNonce = "' . esc_js(wp_create_nonce('gta6mods_tracking')) . '";',
            'before'
        );
    }

    $theme_js = get_template_directory() . '/assets/js/theme.js';
    $script_enqueued = false;
    if (file_exists($theme_js)) {
        $dependencies = [];
        if (wp_script_is('gta6-mods-utils', 'registered') || wp_script_is('gta6-mods-utils', 'enqueued')) {
            $dependencies[] = 'gta6-mods-utils';
        }

        wp_enqueue_script('gta6-mods-theme', get_template_directory_uri() . '/assets/js/theme.js', $dependencies, filemtime($theme_js), true);
        $script_enqueued = true;
    }

    if ($script_enqueued) {
        $localize_data = [
            'featuredMods' => [],
            'popularMods'  => [],
            'latestMods'   => [],
            'latestNews'   => [],
        ];

        if (is_front_page()) {
            $cache_key   = 'gta6_front_page_data_v1';
            $cached_data = get_transient($cache_key);

            if (false === $cached_data) {
                $cached_data = gta6_mods_collect_front_page_data();
                set_transient($cache_key, $cached_data, 30 * MINUTE_IN_SECONDS);
            }

            $localize_data = $cached_data;
        }

        wp_localize_script('gta6-mods-theme', 'GTAModsData', $localize_data);

        $activity_data = [
            'shouldTrack'     => is_user_logged_in(),
            'restEndpoint'    => is_user_logged_in() ? rest_url('gta6mods/v1/user/activity') : '',
            'restNonce'       => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'cookieName'      => 'gta6_activity_throttle',
            'throttleSeconds' => 20 * MINUTE_IN_SECONDS,
            'delayMs'         => 1500,
            'isSecure'        => is_ssl(),
        ];

        wp_localize_script('gta6-mods-theme', 'GTAModsActivity', $activity_data);
    }
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_assets');

/**
 * Enqueues the favicon badge helper for logged-in users.
 */
function gta6_mods_enqueue_favicon_badge_script() {
    if (!is_user_logged_in()) {
        return;
    }

    $script_path = get_template_directory() . '/js/favicon-badge.js';

    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_script(
        'gta6mods-favicon-badge',
        get_template_directory_uri() . '/js/favicon-badge.js',
        [],
        filemtime($script_path),
        true
    );
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_favicon_badge_script', 40);

/**
 * Enqueues header interaction scripts for logged-in users.
 */
function gta6_mods_enqueue_header_menus_script() {
    $script_path = get_template_directory() . '/js/header-menus.js';

    if (!file_exists($script_path)) {
        return;
    }

    $dependencies = [];
    if (wp_script_is('gta6mods-favicon-badge', 'registered') || wp_script_is('gta6mods-favicon-badge', 'enqueued')) {
        $dependencies[] = 'gta6mods-favicon-badge';
    }

    wp_enqueue_script(
        'gta6mods-header-menus',
        get_template_directory_uri() . '/js/header-menus.js',
        $dependencies,
        filemtime($script_path),
        true
    );

    $current_user_id   = get_current_user_id();
    $is_user_logged_in = $current_user_id > 0 && is_user_logged_in();

    $localized_strings = [
        'loading'         => __('Loading…', 'gta6-mods'),
        'empty'           => __('You have no notifications yet.', 'gta6-mods'),
        'loadError'       => __('We could not load your notifications. Please try again.', 'gta6-mods'),
        'markError'       => __('We could not mark your notifications as read. Please try again.', 'gta6-mods'),
        'markAllComplete' => __('All notifications marked as read.', 'gta6-mods'),
    ];

    $payload = [
        'userId'   => $is_user_logged_in ? $current_user_id : 0,
        'restBase' => $is_user_logged_in ? untrailingslashit(rest_url('gta6-mods/v1')) : '',
        'nonce'    => $is_user_logged_in ? wp_create_nonce('wp_rest') : '',
        'limit'    => 5,
        'strings'  => $localized_strings,
    ];

    wp_localize_script('gta6mods-header-menus', 'gta6modsHeaderData', $payload);
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_header_menus_script', 45);

/**
 * Enqueues the welcome font and styles for the front page heading.
 */
function gta6_mods_enqueue_front_page_welcome_assets() {
    if (!is_front_page()) {
        return;
    }

    $handle = 'gta6mods-front-page-welcome';

    wp_enqueue_style(
        $handle,
        'https://fonts.googleapis.com/css2?family=Birthstone&display=swap',
        [],
        null
    );

    $custom_css = <<<'CSS'
:root {
    --clr-neon-glow: 236, 72, 153;
}

.welcome-text {
    font-family: 'Birthstone', cursive;
    text-shadow: 1px 2px 3px rgb(255 255 255 / 23%), 0 0 10px rgb(225 5 122 / 22%);
    letter-spacing: 0.05em;
    font-weight: 400 !important;
}
CSS;

    wp_add_inline_style($handle, $custom_css);
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_front_page_welcome_assets', 50);

/**
 * Enqueues assets for single mod posts.
 */
function gta6_mods_enqueue_single_assets() {
    if (!is_singular('post')) {
        return;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return;
    }

    $format = get_post_format($post_id);
    if ($format && 'standard' !== $format) {
        return;
    }

    $script_path = get_template_directory() . '/assets/js/single-mod.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_style(
        'photoswipe',
        'https://unpkg.com/photoswipe@5/dist/photoswipe.css',
        [],
        '5.4.4'
    );

    wp_enqueue_script(
        'photoswipe',
        'https://unpkg.com/photoswipe@5/dist/umd/photoswipe.umd.min.js',
        [],
        '5.4.4',
        true
    );

    wp_enqueue_script(
        'photoswipe-lightbox',
        'https://unpkg.com/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js',
        ['photoswipe'],
        '5.4.4',
        true
    );

    $single_dependencies = ['photoswipe-lightbox'];
    if (wp_script_is('gta6-mods-utils', 'registered') || wp_script_is('gta6-mods-utils', 'enqueued')) {
        $single_dependencies[] = 'gta6-mods-utils';
    }

    wp_enqueue_script(
        'gta6-mods-single',
        get_template_directory_uri() . '/assets/js/single-mod.js',
        $single_dependencies,
        filemtime($script_path),
        true
    );

    $ratings_js = get_template_directory() . '/assets/js/ratings.js';
    if (file_exists($ratings_js)) {
        wp_enqueue_script('gta6-mods-ratings', get_template_directory_uri() . '/assets/js/ratings.js', [], filemtime($ratings_js), true);

        $ratings_data = [
            'restUrl'   => esc_url_raw(rest_url('gta6-mods/v1/mod/' . $post_id . '/rate')),
            'restNonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
        ];

        wp_localize_script('gta6-mods-ratings', 'GTAModsRatings', $ratings_data);
    }

    $is_user_logged_in = is_user_logged_in();
    $like_count        = gta6_mods_get_like_count($post_id);
    $is_liked          = $is_user_logged_in ? gta6_mods_get_user_like_status($post_id) : false;
    $is_bookmarked     = $is_user_logged_in ? gta6_mods_is_mod_bookmarked_by_user($post_id) : false;

    $single_data = [
        'postId'       => $post_id,
        'shareUrl'     => get_permalink($post_id),
        'copiedLabel'  => esc_html__('Link a vágólapon!', 'gta6-mods'),
        'viewCookiePrefix' => 'gta6mods_viewed_',
        'viewThrottle' => HOUR_IN_SECONDS,
        'viewDelay'    => 1500,
        'isSecure'     => is_ssl(),
        'restNonce'    => $is_user_logged_in ? wp_create_nonce('wp_rest') : '',
        'restEndpoints' => [
            'single'   => rest_url('gta6-mods/v1/mod/' . $post_id . '/single-page-data'),
            'comments' => rest_url('gta6mods/v1/comments/' . $post_id),
            'like'     => rest_url('gta6mods/v1/mod/' . $post_id . '/like'),
            'bookmark' => rest_url('gta6mods/v1/mod/' . $post_id . '/bookmark'),
            'download' => rest_url('gta6mods/v1/mod/' . $post_id . '/download'),
            'view'     => rest_url('gta6mods/v1/mod/' . $post_id . '/view'),
            'related'  => rest_url('gta6-mods/v1/mod/' . $post_id . '/related'),
            'userState'=> $is_user_logged_in ? rest_url('gta6-mods/v1/mod/' . $post_id . '/user-state') : '',
        ],
        'likes' => [
            'count' => (int) $like_count,
            'liked' => (bool) $is_liked,
        ],
        'bookmarks' => [
            'isBookmarked' => (bool) $is_bookmarked,
            'labels'       => [
                'add'    => esc_html__('Bookmark', 'gta6-mods'),
                'added'  => esc_html__('Bookmarked', 'gta6-mods'),
                'error'  => esc_html__('We could not update your bookmark. Please try again.', 'gta6-mods'),
            ],
        ],
        'user' => [
            'isLoggedIn' => (bool) $is_user_logged_in,
        ],
        'comments' => [
            'count'   => get_comments_number($post_id),
            'strings' => [
                'loading'       => esc_html__('Loading…', 'gta6-mods'),
                'error'         => esc_html__('We could not load the comments. Please try again.', 'gta6-mods'),
                'empty'         => esc_html__('No comments yet. Be the first to share your thoughts!', 'gta6-mods'),
                'paginationAria'=> esc_html__('Comments pagination', 'gta6-mods'),
            ],
        ],
    ];

    wp_localize_script('gta6-mods-single', 'GTAModsSingle', $single_data);
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_single_assets');

function gta6_mods_enqueue_update_assets() {
    if (!is_page_template('page-update-mod.php')) {
        return;
    }

    $script_path = get_template_directory() . '/assets/js/update-mod.js';
    if (file_exists($script_path)) {
        wp_enqueue_script(
            'gta6-mods-update',
            get_template_directory_uri() . '/assets/js/update-mod.js',
            [],
            filemtime($script_path),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_update_assets');

/**
 * Registers pretty permalink endpoints for single mod tabs.
 */
function gta6_mods_register_single_mod_tab_endpoints() {
    add_rewrite_endpoint('comments', EP_PERMALINK);
    add_rewrite_endpoint('changelogs', EP_PERMALINK);
}
add_action('init', 'gta6_mods_register_single_mod_tab_endpoints');

/**
 * Flushes rewrite rules once to ensure the single mod endpoints are registered.
 */
function gta6_mods_maybe_flush_single_mod_tab_endpoints() {
    $target_version = '20240605';
    $stored_version = get_option('gta6mods_single_mod_tab_endpoint_version');

    if ($stored_version === $target_version) {
        return;
    }

    gta6_mods_register_single_mod_tab_endpoints();
    flush_rewrite_rules(false);
    update_option('gta6mods_single_mod_tab_endpoint_version', $target_version);
}
add_action('init', 'gta6_mods_maybe_flush_single_mod_tab_endpoints', 30);

/**
 * Determines whether the current request contains comment related query vars.
 *
 * @return bool
 */
function gta6_mods_request_targets_comments() {
    global $wp_query;

    $query_vars = ['comment', 'replytocom', 'cpage', 'withcomments', 'unapproved', 'moderation-hash'];

    if ($wp_query instanceof WP_Query) {
        foreach ($query_vars as $var) {
            $value = $wp_query->get($var, null);
            if (null !== $value && '' !== $value) {
                return true;
            }
        }
    }

    foreach ($query_vars as $var) {
        if (isset($_GET[$var]) && '' !== $_GET[$var]) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return true;
        }
    }

    return false;
}

/**
 * Returns sanitized comment related query args present in the current request.
 *
 * @return array<string, string>
 */
function gta6_mods_get_comment_tab_query_args() {
    $args       = [];
    $whitelist  = ['cpage', 'replytocom', 'unapproved', 'moderation-hash'];

    global $wp_query;

    if ($wp_query instanceof WP_Query) {
        $cpage = $wp_query->get('cpage', null);
        if (null !== $cpage && '' !== $cpage) {
            $cpage = absint($cpage);
            if ($cpage > 0) {
                $args['cpage'] = (string) $cpage;
            }
        }

        $reply = $wp_query->get('replytocom', null);
        if (null !== $reply && '' !== $reply) {
            $args['replytocom'] = (string) absint($reply);
        }

        $unapproved = $wp_query->get('unapproved', null);
        if (null !== $unapproved && '' !== $unapproved) {
            $args['unapproved'] = sanitize_text_field((string) $unapproved);
        }

        $hash = $wp_query->get('moderation-hash', null);
        if (null !== $hash && '' !== $hash) {
            $args['moderation-hash'] = sanitize_text_field((string) $hash);
        }
    }

    foreach ($whitelist as $key) {
        if (isset($args[$key])) {
            continue;
        }

        if (!isset($_GET[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            continue;
        }

        $value = wp_unslash($_GET[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ('' === $value) {
            continue;
        }

        if ('cpage' === $key) {
            $value = absint($value);
            if ($value <= 0) {
                continue;
            }
            $args[$key] = (string) $value;
        } else {
            $args[$key] = sanitize_text_field($value);
        }
    }

    return $args;
}

/**
 * Checks whether the pretty endpoint for a given tab is currently available.
 *
 * @param string $tab_slug Tab slug.
 *
 * @return bool
 */
function gta6_mods_single_mod_endpoint_supported($tab_slug) {
    static $cache = [];

    $tab_slug = sanitize_key($tab_slug);

    if (isset($cache[$tab_slug])) {
        return $cache[$tab_slug];
    }

    $structure = get_option('permalink_structure');

    if (!is_string($structure) || '' === $structure) {
        $cache[$tab_slug] = false;

        return false;
    }

    $rules = get_option('rewrite_rules');

    if (!is_array($rules)) {
        $cache[$tab_slug] = false;

        return false;
    }

    foreach ($rules as $regex => $query) {
        if (false !== strpos($regex, '/' . $tab_slug . '/?')) {
            $cache[$tab_slug] = true;

            return true;
        }
    }

    $cache[$tab_slug] = false;

    return false;
}

/**
 * Returns the requested tab slug for the current single mod view.
 *
 * Falls back to the description tab when no specific tab is requested.
 *
 * @return string
 */
function gta6_mods_get_single_mod_requested_tab_slug() {
    static $cache = null;

    if (null !== $cache) {
        return $cache;
    }

    $tab_labels = gta6_mods_get_single_mod_tab_labels();
    $valid_tabs = array_keys($tab_labels);
    $default    = 'description';
    $requested  = '';

    global $wp_query;
    if ($wp_query instanceof WP_Query) {
        foreach (['comments', 'changelogs'] as $endpoint) {
            $endpoint_value = $wp_query->get($endpoint, null);
            if (null !== $endpoint_value) {
                $requested = $endpoint;
                break;
            }
        }
    }

    if ('' === $requested && isset($_GET['tab'])) {
        $requested = strtolower(sanitize_key(wp_unslash($_GET['tab'])));
    }

    if ('' === $requested && gta6_mods_request_targets_comments()) {
        $requested = 'comments';
    }

    if ('' === $requested) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ('' !== $request_uri) {
            $path = wp_parse_url($request_uri, PHP_URL_PATH);
            if (is_string($path) && '' !== $path) {
                $segments = array_values(array_filter(explode('/', strtolower(trim($path, '/')))));
                if (!empty($segments)) {
                    $candidate = sanitize_key(end($segments));
                    if (is_string($candidate)) {
                        $requested = $candidate;
                    }
                }
            }
        }
    }

    if (!in_array($requested, $valid_tabs, true)) {
        $requested = $default;
    }

    $cache = $requested;

    return $cache;
}

/**
 * Builds the permalink for a given single mod tab.
 *
 * @param int          $post_id    Post ID.
 * @param string       $tab_slug   Tab slug.
 * @param array<mixed> $query_args Optional query arguments to append to the generated URL.
 *
 * @return string
 */
function gta6_mods_get_single_mod_tab_url($post_id, $tab_slug, $query_args = []) {
    $post_id  = absint($post_id);
    $tab_slug = strtolower(sanitize_key($tab_slug));

    if ($post_id <= 0 || '' === $tab_slug) {
        return '';
    }

    $permalink = get_permalink($post_id);
    if (!$permalink) {
        return '';
    }

    if (!is_array($query_args)) {
        $query_args = [];
    }

    if ('description' === $tab_slug) {
        return !empty($query_args) ? add_query_arg($query_args, $permalink) : $permalink;
    }

    $structure               = get_option('permalink_structure');
    $has_pretty_permalinks   = is_string($structure) && '' !== $structure;
    $should_use_pretty_links = $has_pretty_permalinks;

    if ($should_use_pretty_links) {
        $url = trailingslashit(trailingslashit($permalink) . $tab_slug);

        return !empty($query_args) ? add_query_arg($query_args, $url) : $url;
    }

    $args = array_merge(['tab' => $tab_slug], $query_args);

    return add_query_arg($args, $permalink);
}

/**
 * Returns the URLs for each available single mod tab.
 *
 * @param int $post_id Post ID.
 *
 * @return array<string, string>
 */
function gta6_mods_get_single_mod_tab_urls($post_id) {
    $urls       = [];
    $tab_labels = gta6_mods_get_single_mod_tab_labels();
    $comment_args = gta6_mods_get_comment_tab_query_args();

    foreach (array_keys($tab_labels) as $tab_slug) {
        $extra_args = [];

        if ('comments' === $tab_slug && !empty($comment_args)) {
            $extra_args = $comment_args;
        }

        $urls[$tab_slug] = gta6_mods_get_single_mod_tab_url($post_id, $tab_slug, $extra_args);
    }

    return $urls;
}

/**
 * Wraps oEmbeds (like YouTube videos) in a responsive container.
 */
function gta6_mods_wrap_oembeds($html, $url, $attr, $post_id) {
    // Only wrap video providers
    $video_providers = [
        'youtube.com',
        'youtu.be',
        'vimeo.com',
    ];

    $is_video = false;
    foreach ($video_providers as $provider) {
        if (strpos($url, $provider) !== false) {
            $is_video = true;
            break;
        }
    }

    if ($is_video) {
        return '<div class="responsive-video-wrapper">' . $html . '</div>';
    }

    return $html;
}
add_filter('embed_oembed_html', 'gta6_mods_wrap_oembeds', 10, 4);

/**
 * Adjust the document title for single mod tabs.
 *
 * When the comments or changelog tabs are active, append the tab label to the
 * page title so that the browser title reflects the visible content.
 *
 * @param array<string, string> $title_parts Title parts generated by WordPress.
 *
 * @return array<string, string>
 */
function gta6_mods_get_single_mod_tab_labels() {
    return [
        'description' => __('Description', 'gta6-mods'),
        'comments'    => __('Comments', 'gta6-mods'),
        'changelogs'  => __('Changelog', 'gta6-mods'),
    ];
}

function gta6_mods_adjust_single_tab_document_title($title_parts) {
    if (!is_singular('post')) {
        return $title_parts;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return $title_parts;
    }

    $format = get_post_format($post_id);
    if ($format && 'standard' !== $format) {
        return $title_parts;
    }

    $requested_tab = gta6_mods_get_single_mod_requested_tab_slug();
    if ('description' === $requested_tab) {
        return $title_parts;
    }

    $tab_titles = gta6_mods_get_single_mod_tab_labels();
    if (!isset($tab_titles[$requested_tab])) {
        return $title_parts;
    }

    $mod_title = get_the_title($post_id);
    if ('' === $mod_title) {
        return $title_parts;
    }

    $title_parts['title'] = sprintf('%s – %s', $mod_title, $tab_titles[$requested_tab]);

    return $title_parts;
}
add_filter('document_title_parts', 'gta6_mods_adjust_single_tab_document_title');

function gta6_mods_output_single_tab_meta_tags() {
    if (!is_singular('post')) {
        return;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return;
    }

    $format = get_post_format($post_id);
    if ($format && 'standard' !== $format) {
        return;
    }

    $requested_tab = gta6_mods_get_single_mod_requested_tab_slug();
    if ('description' === $requested_tab) {
        return;
    }

    $mod_title = get_the_title($post_id);
    if ('' === $mod_title) {
        return;
    }

    $tab_labels = gta6_mods_get_single_mod_tab_labels();
    if (!isset($tab_labels[$requested_tab])) {
        return;
    }

    $meta_payload = [];

    if ('comments' === $requested_tab) {
        $meta_payload = gta6_mods_build_comments_tab_meta_payload($post_id, $mod_title, $tab_labels[$requested_tab]);
    } elseif ('changelogs' === $requested_tab) {
        $meta_payload = gta6_mods_build_changelog_tab_meta_payload($post_id, $mod_title, $tab_labels[$requested_tab]);
    }

    /**
     * Filter the metadata payload rendered for single mod tabs.
     *
     * @param array<string, mixed> $meta_payload Generated metadata.
     * @param string               $requested_tab Current tab slug.
     * @param int                  $post_id       Post ID.
     */
    $meta_payload = apply_filters('gta6_mods_single_tab_meta_payload', $meta_payload, $requested_tab, $post_id);

    if (empty($meta_payload) || !is_array($meta_payload)) {
        return;
    }

    if (!empty($meta_payload['canonical'])) {
        printf('<link rel="canonical" href="%s" />' . "\n", esc_url($meta_payload['canonical']));
    }

    if (!empty($meta_payload['description'])) {
        printf('<meta name="description" content="%s" />' . "\n", esc_attr($meta_payload['description']));
    }

    if (!empty($meta_payload['og_title'])) {
        printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($meta_payload['og_title']));
    }

    if (!empty($meta_payload['og_description'])) {
        printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($meta_payload['og_description']));
    }

    if (!empty($meta_payload['twitter_title'])) {
        printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($meta_payload['twitter_title']));
    }

    if (!empty($meta_payload['twitter_description'])) {
        printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($meta_payload['twitter_description']));
    }

    if (!empty($meta_payload['structured_data']) && is_array($meta_payload['structured_data'])) {
        $json = wp_json_encode($meta_payload['structured_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json) {
            printf('<script type="application/ld+json">%s</script>' . "\n", $json);
        }
    }
}
add_action('wp_head', 'gta6_mods_output_single_tab_meta_tags', 1);

function gta6_mods_build_comments_tab_meta_payload($post_id, $mod_title, $tab_label) {
    $comment_count   = get_comments_number($post_id);
    $count_formatted = number_format_i18n($comment_count);

    if ($comment_count > 0) {
        $count_text = sprintf(_n('%s comment', '%s comments', $comment_count, 'gta6-mods'), $count_formatted);
        $description_intro = sprintf(__('Read what the community thinks about the %1$s mod – %2$s.', 'gta6-mods'), $mod_title, $count_text);
    } else {
        $description_intro = sprintf(__('Be the first to review the %s mod.', 'gta6-mods'), $mod_title);
    }

    $latest_comment_excerpt = '';
    $latest_comment         = get_comments(
        [
            'post_id' => $post_id,
            'number'  => 1,
            'status'  => 'approve',
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
        ]
    );

    if (!empty($latest_comment) && isset($latest_comment[0])) {
        $latest_comment_content = wp_strip_all_tags($latest_comment[0]->comment_content);
        if ('' !== $latest_comment_content) {
            $latest_comment_excerpt = wp_trim_words($latest_comment_content, 24, '…');
        }
    }

    $description = $description_intro;
    if ('' !== $latest_comment_excerpt) {
        $description .= ' ' . sprintf(__('Latest comment: %s', 'gta6-mods'), $latest_comment_excerpt);
    }

    $tab_url = gta6_mods_get_single_mod_tab_url($post_id, 'comments');

    $structured_data = [
        '@context'      => 'https://schema.org',
        '@type'         => 'DiscussionForumPosting',
        'name'          => sprintf('%s – %s', $mod_title, $tab_label),
        'headline'      => sprintf('%s – %s', $mod_title, $tab_label),
        'url'           => $tab_url,
        'commentCount'  => (int) $comment_count,
        'discussionUrl' => $tab_url,
    ];

    $author_name = get_bloginfo('name');
    if ($author_name) {
        $structured_data['publisher'] = [
            '@type' => 'Organization',
            'name'  => $author_name,
        ];
    }

    $comment_items = [];
    if ($comment_count > 0) {
        $recent_comments = get_comments(
            [
                'post_id' => $post_id,
                'number'  => 5,
                'status'  => 'approve',
                'orderby' => 'comment_date_gmt',
                'order'   => 'DESC',
            ]
        );

        foreach ($recent_comments as $index => $comment) {
            $comment_text = wp_strip_all_tags($comment->comment_content);
            if ('' === $comment_text) {
                continue;
            }

            $comment_items[] = [
                '@type'         => 'Comment',
                'position'      => $index + 1,
                'text'          => $comment_text,
                'datePublished' => get_comment_date('c', $comment),
                'url'           => get_comment_link($comment),
                'author'        => [
                    '@type' => 'Person',
                    'name'  => get_comment_author($comment),
                ],
            ];
        }
    }

    if (!empty($comment_items)) {
        $structured_data['comment'] = $comment_items;
    }

    return [
        'canonical'            => $tab_url,
        'description'          => $description,
        'og_title'             => sprintf('%s – %s', $mod_title, $tab_label),
        'og_description'       => $description,
        'twitter_title'        => sprintf('%s – %s', $mod_title, $tab_label),
        'twitter_description'  => $description,
        'structured_data'      => $structured_data,
    ];
}

function gta6_mods_build_changelog_tab_meta_payload($post_id, $mod_title, $tab_label) {
    if (!function_exists('gta6_mods_get_mod_versions_for_display')) {
        return [];
    }

    $versions = gta6_mods_get_mod_versions_for_display($post_id);
    $tab_url  = gta6_mods_get_single_mod_tab_url($post_id, 'changelogs');

    if (empty($versions)) {
        $description = sprintf(__('Explore the changelog and previous versions of the %s mod.', 'gta6-mods'), $mod_title);

        return [
            'canonical'          => $tab_url,
            'description'         => $description,
            'og_title'            => sprintf('%s – %s', $mod_title, $tab_label),
            'og_description'      => $description,
            'twitter_title'       => sprintf('%s – %s', $mod_title, $tab_label),
            'twitter_description' => $description,
        ];
    }

    $version_count = count($versions);
    $latest_version = null;

    foreach ($versions as $version) {
        if (isset($version['is_pending']) && $version['is_pending']) {
            continue;
        }

        $latest_version = $version;
        break;
    }

    if (null === $latest_version) {
        $latest_version = $versions[0];
    }

    $description_parts = [
        sprintf(__('Browse the detailed changelog for the %1$s mod (%2$s versions).', 'gta6-mods'), $mod_title, number_format_i18n($version_count)),
    ];

    if (!empty($latest_version['number'])) {
        $description_parts[] = sprintf(__('Latest version: %s.', 'gta6-mods'), $latest_version['number']);
    }

    if (!empty($latest_version['date'])) {
        $description_parts[] = sprintf(__('Released: %s.', 'gta6-mods'), wp_strip_all_tags($latest_version['date']));
    }

    if (!empty($latest_version['changelog'])) {
        $changes_summary = wp_trim_words(implode(' ', $latest_version['changelog']), 28, '…');
        if ('' !== $changes_summary) {
            $description_parts[] = sprintf(__('Key changes: %s', 'gta6-mods'), $changes_summary);
        }
    }

    $description = implode(' ', $description_parts);

    $structured_data = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => sprintf('%s – %s', $mod_title, $tab_label),
        'itemListOrder'   => 'Descending',
        'numberOfItems'   => (int) $version_count,
        'url'             => $tab_url,
        'itemListElement' => [],
    ];

    $max_versions = 5;
    $position     = 1;

    foreach ($versions as $version) {
        if ($position > $max_versions) {
            break;
        }

        $version_name = !empty($version['number'])
            ? sprintf(__('Version %s', 'gta6-mods'), $version['number'])
            : __('Unknown version', 'gta6-mods');

        $version_changes = '';
        if (!empty($version['changelog'])) {
            $version_changes = wp_trim_words(implode(' ', $version['changelog']), 24, '…');
        }

        $version_date_iso = '';
        if (!empty($version['raw_date'])) {
            $timestamp = strtotime($version['raw_date']);
            if ($timestamp) {
                $version_date_iso = gmdate('c', $timestamp);
            }
        }

        if ('' === $version_date_iso && !empty($version['date'])) {
            $timestamp = strtotime($version['date']);
            if ($timestamp) {
                $version_date_iso = gmdate('c', $timestamp);
            }
        }

        $structured_data['itemListElement'][] = [
            '@type'        => 'ListItem',
            'position'     => $position,
            'name'         => $version_name,
            'description'  => $version_changes,
            'url'          => !empty($version['download_url']) ? $version['download_url'] : $tab_url,
            'datePublished'=> $version_date_iso,
        ];

        $position++;
    }

    if (empty($structured_data['itemListElement'])) {
        unset($structured_data['itemListElement']);
    }

    return [
        'canonical'            => $tab_url,
        'description'          => $description,
        'og_title'             => sprintf('%s – %s', $mod_title, $tab_label),
        'og_description'       => $description,
        'twitter_title'        => sprintf('%s – %s', $mod_title, $tab_label),
        'twitter_description'  => $description,
        'structured_data'      => $structured_data,
    ];
}

/**
 * Ensures indexes exist for frequently queried post meta keys.
 */
function gta6mods_ensure_meta_indexes() {
    global $wpdb;

    if (!($wpdb instanceof wpdb)) {
        return;
    }

    $table = $wpdb->postmeta;

    if (empty($table)) {
        return;
    }

    $indexes = [
        'gta6mods_meta_idx_downloads' => [
            'meta_key'     => '_gta6mods_download_count',
            'value_length' => 20,
        ],
        'gta6mods_meta_idx_rating' => [
            'meta_key'     => '_gta6mods_rating_average',
            'value_length' => 20,
        ],
        'gta6mods_meta_idx_likes' => [
            'meta_key'     => '_gta6mods_likes',
            'value_length' => 20,
        ],
    ];

    foreach ($indexes as $index_name => $definition) {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index_name));

        if ($exists) {
            continue;
        }

        $value_length = max(1, (int) $definition['value_length']);
        $sql          = sprintf(
            'CREATE INDEX %1$s ON %2$s (meta_key(191), meta_value(%3$d))',
            $index_name,
            $table,
            $value_length
        );

        $wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}

/**
 * Schedules meta index checks for environments where the theme is already active.
 */
function gta6mods_schedule_meta_index_check() {
    if (!is_admin()) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (false !== get_transient('gta6mods_meta_index_last_check')) {
        return;
    }

    set_transient('gta6mods_meta_index_last_check', 1, DAY_IN_SECONDS);

    gta6mods_ensure_meta_indexes();
}

add_action('after_switch_theme', 'gta6mods_ensure_meta_indexes');
add_action('admin_init', 'gta6mods_schedule_meta_index_check');
