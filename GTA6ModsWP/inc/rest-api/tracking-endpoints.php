<?php
/**
 * REST API endpoints for download and view tracking.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers REST API tracking routes.
 */
function gta6mods_register_tracking_rest_routes() {
    register_rest_route(
        'gta6mods/v1',
        '/mod/(?P<id>\d+)/download',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_increment_downloads',
            'permission_callback' => 'gta6mods_rest_request_has_valid_tracking_nonce',
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/mod/(?P<id>\d+)/view',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_increment_views',
            'permission_callback' => 'gta6mods_rest_request_has_valid_tracking_nonce',
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/profile/(?P<id>\d+)/view',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_increment_profile_views',
            'permission_callback' => 'gta6mods_rest_request_has_valid_tracking_nonce',
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/user/activity',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_update_last_activity',
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/generate-download-token',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_generate_download_token',
            'permission_callback' => '__return_true',
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_tracking_rest_routes');

/**
 * Handles download counter increments.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_increment_downloads(WP_REST_Request $request) {
    $post_id = (int) $request['id'];

    if ($post_id <= 0 || !in_array(get_post_type($post_id), gta6mods_get_mod_post_types(), true)) {
        return new WP_Error('invalid_post', __('Invalid mod.', 'gta6-mods'), ['status' => 400]);
    }

    $version_id = (int) $request->get_param('versionId');
    $counts     = gta6_mods_increment_download_count($post_id, $version_id);

    $count            = isset($counts['post']) ? (int) $counts['post'] : 0;
    $version_count    = isset($counts['version']) ? (int) $counts['version'] : 0;
    $resolved_version = isset($counts['version_id']) ? (int) $counts['version_id'] : $version_id;
    $last_downloaded  = gta6_mods_format_time_ago(gta6_mods_get_last_download_timestamp($post_id));

    if ($resolved_version > 0 && function_exists('gta6mods_mark_download_click')) {
        gta6mods_mark_download_click($resolved_version);
    }

    return rest_ensure_response([
        'downloads'                 => $count,
        'formattedDownloads'        => number_format_i18n($count),
        'lastDownloadedHuman'       => $last_downloaded,
        'versionId'                 => $resolved_version,
        'versionDownloads'          => $version_count,
        'formattedVersionDownloads' => number_format_i18n($version_count),
    ]);
}

/**
 * Handles mod view count increments.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_increment_views(WP_REST_Request $request) {
    $post_id = (int) $request['id'];

    if ($post_id <= 0 || !in_array(get_post_type($post_id), gta6mods_get_mod_post_types(), true)) {
        return new WP_Error('invalid_post', __('Invalid mod.', 'gta6-mods'), ['status' => 400]);
    }

    $count = gta6_mods_increment_view_count($post_id);

    return rest_ensure_response([
        'views'          => $count,
        'formattedViews' => number_format_i18n($count),
    ]);
}

/**
 * Tracks profile view counters.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_increment_profile_views(WP_REST_Request $request) {
    $author_id = (int) $request['id'];

    if ($author_id <= 0) {
        return new WP_Error('invalid_profile', __('Invalid profile.', 'gta6-mods'), ['status' => 400]);
    }

    $current_user_id = get_current_user_id();
    if ($current_user_id !== $author_id) {
        gta6mods_increment_user_meta_counter($author_id, '_profile_view_count', 1);
    }

    $views = (int) get_user_meta($author_id, '_profile_view_count', true);

    return rest_ensure_response([
        'views'          => $views,
        'formattedViews' => number_format_i18n($views),
    ]);
}

/**
 * Updates the last activity timestamp for the authenticated user.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_update_last_activity(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return new WP_Error('not_logged_in', __('Authentication required.', 'gta6-mods'), ['status' => 401]);
    }

    $timestamp_sql = current_time('mysql', true);
    $timestamp     = current_time('timestamp', true);

    update_user_meta($user_id, '_last_activity', $timestamp_sql);

    return rest_ensure_response([
        'timestamp'      => $timestamp,
        'timestampHuman' => gta6_mods_format_time_ago($timestamp),
    ]);
}

/**
 * Issues short lived download tokens after the waiting room countdown.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_generate_download_token(WP_REST_Request $request) {
    $nonce_header = $request->get_header('X-WP-Nonce');
    if (!$nonce_header || !wp_verify_nonce($nonce_header, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('A kérés nem hitelesíthető.', 'gta6-mods'), ['status' => 403]);
    }

    $version_id = (int) $request->get_param('version_id');
    if ($version_id <= 0) {
        $version_id = (int) $request->get_param('versionId');
    }

    if ($version_id <= 0) {
        return new WP_Error('invalid_version', __('Érvénytelen verzióazonosító.', 'gta6-mods'), ['status' => 400]);
    }

    if (!gta6mods_download_rate_limiter_allow('rest')) {
        return new WP_Error('rate_limited', __('Túl sok próbálkozás, kérjük, várj egy percet.', 'gta6-mods'), ['status' => 429]);
    }

    $visitor_identifier = function_exists('gta6mods_get_waiting_room_identifier')
        ? gta6mods_get_waiting_room_identifier()
        : gta6mods_get_request_ip();
    $ip_hash   = md5($visitor_identifier);
    $nonce     = function_exists('gta6mods_get_version_cache_nonce') ? gta6mods_get_version_cache_nonce($version_id) : '0';
    $cache_key = sprintf('prep_%d_%s_%s', $version_id, $nonce, $ip_hash);
    $cached    = gta6mods_cache_get($cache_key, 'gta6mods_download_tokens');

    if (is_array($cached) && isset($cached['download_url'], $cached['expires_at'], $cached['token'])) {
        $response = rest_ensure_response($cached);
        $response->header('Cache-Control', 'public, max-age=' . GTA6MODS_DOWNLOAD_TOKEN_TTL);
        $response->header('X-Cache', 'HIT');

        return $response;
    }

    $result = gta6mods_generate_download_token($version_id);
    if (is_wp_error($result)) {
        return $result;
    }

    $payload = [
        'download_url' => esc_url_raw($result['download_url']),
        'expires_at'   => (int) $result['expires_at'],
        'token'        => $result['token'],
    ];

    gta6mods_cache_set($cache_key, $payload, 'gta6mods_download_tokens', GTA6MODS_DOWNLOAD_TOKEN_TTL);

    $response = rest_ensure_response($payload);
    $response->header('Cache-Control', 'public, max-age=' . GTA6MODS_DOWNLOAD_TOKEN_TTL);
    $response->header('X-Cache', 'MISS');

    return $response;
}
