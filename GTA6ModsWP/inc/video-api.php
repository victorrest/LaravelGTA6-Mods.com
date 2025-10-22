<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Video Gallery REST API Endpoints
 */

add_action('rest_api_init', 'gta6mods_register_video_endpoints');

if (!function_exists('gta6mods_register_video_endpoints')) {
    /**
     * Registers REST API routes for mod videos.
     */
    function gta6mods_register_video_endpoints() {
        register_rest_route('gta6mods/v1', '/videos/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_api_submit_video',
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
            'args'                => [
                'mod_id' => [
                    'required'          => true,
                    'validate_callback' => static function ($param) {
                        $mod_id = (int) $param;
                        if ($mod_id <= 0) {
                            return false;
                        }

                        return get_post_status($mod_id) !== false;
                    },
                ],
                'youtube_url' => [
                    'required'          => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'validate_callback' => static function ($param) {
                        return is_string($param) && trim($param) !== '';
                    },
                ],
            ],
        ]);

        register_rest_route('gta6mods/v1', '/videos/(?P<mod_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_api_get_videos',
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('gta6mods/v1', '/videos/(?P<video_id>\d+)/report', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_api_report_video',
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('gta6mods/v1', '/videos/(?P<video_id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'gta6mods_api_delete_video',
            'permission_callback' => static function (WP_REST_Request $request) {
                return gta6mods_user_can_manage_video((int) $request->get_param('video_id'));
            },
        ]);

        register_rest_route('gta6mods/v1', '/videos/(?P<video_id>\d+)/feature', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_api_feature_video',
            'permission_callback' => static function (WP_REST_Request $request) {
                return gta6mods_user_can_feature_video((int) $request->get_param('video_id'));
            },
        ]);
        register_rest_route('gta6mods/v1', '/videos/(?P<video_id>\d+)/feature', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'gta6mods_api_unfeature_video',
            'permission_callback' => static function (WP_REST_Request $request) {
                return gta6mods_user_can_feature_video((int) $request->get_param('video_id'));
            },
        ]);
    }
}

if (!function_exists('gta6mods_api_submit_video')) {
    /**
     * Handles the submission of a new video.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_Error|array
     */
    function gta6mods_api_submit_video(WP_REST_Request $request) {
        global $wpdb;

        $mod_id      = (int) $request->get_param('mod_id');
        $youtube_url = (string) $request->get_param('youtube_url');
        $youtube_url = trim(wp_unslash($youtube_url));

        $current_user_id = get_current_user_id();
        if ($mod_id <= 0 || $current_user_id <= 0) {
            return new WP_Error('invalid_request', __('Invalid request.', 'gta6-mods'), ['status' => 400]);
        }

        $youtube_id = gta6mods_extract_youtube_id($youtube_url);
        if (!$youtube_id) {
            return new WP_Error('invalid_url', __('Invalid YouTube URL.', 'gta6-mods'), ['status' => 400]);
        }

        $table_name = gta6mods_get_video_table_name();

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE mod_id = %d AND youtube_id = %s",
                $mod_id,
                $youtube_id
            )
        );

        if ($existing_id) {
            return new WP_Error('duplicate', __('This video has already been submitted.', 'gta6-mods'), ['status' => 409]);
        }

        $today_start = wp_date('Y-m-d 00:00:00');

        $recent_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE submitted_by = %d AND submitted_at >= %s",
                $current_user_id,
                $today_start
            )
        );

        if ($recent_count >= 3) {
            return new WP_Error(
                'rate_limit',
                __('You can submit only 3 videos per day. Please try again tomorrow.', 'gta6-mods'),
                ['status' => 429]
            );
        }

        $inserted = $wpdb->insert(
            $table_name,
            [
                'mod_id'       => $mod_id,
                'youtube_url'  => $youtube_url,
                'youtube_id'   => $youtube_id,
                'submitted_by' => $current_user_id,
                'status'       => 'pending',
            ],
            ['%d', '%s', '%s', '%d', '%s']
        );

        if (false === $inserted) {
            return new WP_Error('db_error', __('Failed to submit video.', 'gta6-mods'), ['status' => 500]);
        }

        return [
            'success'  => true,
            'message'  => __('Video submitted successfully! It will appear after moderation.', 'gta6-mods'),
            'video_id' => (int) $wpdb->insert_id,
        ];
    }
}

if (!function_exists('gta6mods_api_get_videos')) {
    /**
     * Returns the list of approved videos for a mod.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return array
     */
    function gta6mods_api_get_videos(WP_REST_Request $request) {
        $mod_id = (int) $request->get_param('mod_id');
        $videos = gta6mods_get_mod_videos($mod_id, 'approved');

        return [
            'success' => true,
            'videos'  => $videos,
            'count'   => count($videos),
        ];
    }
}

if (!function_exists('gta6mods_api_report_video')) {
    /**
     * Marks a video as reported by a user.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_Error|array
     */
    function gta6mods_api_report_video(WP_REST_Request $request) {
        global $wpdb;

        $video_id = (int) $request->get_param('video_id');
        $user_id  = get_current_user_id();

        if ($video_id <= 0 || $user_id <= 0) {
            return new WP_Error('invalid_request', __('Invalid request.', 'gta6-mods'), ['status' => 400]);
        }

        $video = gta6mods_get_video_row($video_id);
        if (!$video) {
            return new WP_Error('not_found', __('Video not found.', 'gta6-mods'), ['status' => 404]);
        }

        $current_status = isset($video->status) ? sanitize_key($video->status) : '';

        if (!in_array($current_status, ['approved', 'reported'], true)) {
            return new WP_Error('invalid_status', __('This video can no longer be reported.', 'gta6-mods'), ['status' => 409]);
        }

        if (gta6mods_has_user_reported_video($video_id, $user_id)) {
            return new WP_Error('already_reported', __('You have already reported this video.', 'gta6-mods'), ['status' => 409]);
        }

        $reports_table = gta6mods_get_video_reports_table_name();
        $inserted      = $wpdb->insert(
            $reports_table,
            [
                'video_id' => $video_id,
                'user_id'  => $user_id,
            ],
            ['%d', '%d']
        );

        if (false === $inserted) {
            if ($wpdb->last_error && false !== stripos($wpdb->last_error, 'duplicate')) {
                return new WP_Error('already_reported', __('You have already reported this video.', 'gta6-mods'), ['status' => 409]);
            }

            return new WP_Error('db_error', __('Failed to report video.', 'gta6-mods'), ['status' => 500]);
        }

        $report_total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$reports_table} WHERE video_id = %d",
                $video_id
            )
        );

        $table_name = gta6mods_get_video_table_name();

        $wpdb->update(
            $table_name,
            [
                'status'            => 'reported',
                'report_count'      => $report_total,
                'last_reported_at'  => current_time('mysql'),
                'last_reported_by'  => $user_id,
            ],
            ['id' => $video_id],
            ['%s', '%d', '%s', '%d'],
            ['%d']
        );

        gta6mods_clear_video_report_cache($video_id, $user_id);
        gta6mods_clear_single_video_cache($video_id);

        if (isset($video->mod_id)) {
            gta6mods_clear_video_cache((int) $video->mod_id);
        }

        return [
            'success'      => true,
            'message'      => __('Thanks! Your report was received and will be reviewed shortly. Thank you for helping us maintain the site’s quality!', 'gta6-mods'),
            'report_count' => $report_total,
        ];
    }
}

if (!function_exists('gta6mods_api_delete_video')) {
    /**
     * Deletes a video entry (admin only).
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_Error|array
     */
    function gta6mods_api_delete_video(WP_REST_Request $request) {
        global $wpdb;

        $video_id = (int) $request->get_param('video_id');
        if ($video_id <= 0) {
            return new WP_Error('invalid_request', __('Invalid request.', 'gta6-mods'), ['status' => 400]);
        }

        $table_name = gta6mods_get_video_table_name();
        $video      = gta6mods_get_video_row($video_id);

        if (!$video) {
            return new WP_Error('not_found', __('Video not found.', 'gta6-mods'), ['status' => 404]);
        }

        $updated = $wpdb->update(
            $table_name,
            [
                'status'       => 'rejected',
                'moderated_by' => get_current_user_id(),
                'moderated_at' => current_time('mysql'),
                'is_featured'  => 0,
                'featured_at'  => null,
            ],
            ['id' => $video_id],
            ['%s', '%d', '%s', '%d', '%s'],
            ['%d']
        );

        if (false === $updated) {
            return new WP_Error('db_error', __('Failed to update video.', 'gta6-mods'), ['status' => 500]);
        }

        gta6mods_clear_single_video_cache($video_id);

        if (isset($video->mod_id)) {
            gta6mods_clear_video_cache((int) $video->mod_id);
        }

        if (function_exists('gta6mods_clear_sitemap_cache')) {
            gta6mods_clear_sitemap_cache('videos');
        }

        $current_user_id = get_current_user_id();
        $is_moderator    = user_can($current_user_id, 'moderate_comments');
        $is_mod_author   = false;

        if (!$is_moderator && isset($video->mod_id)) {
            $mod_id       = (int) $video->mod_id;
            $mod_author_id = $mod_id > 0 ? (int) get_post_field('post_author', $mod_id) : 0;
            $is_mod_author = $mod_author_id > 0 && $mod_author_id === $current_user_id;
        }

        $message = $is_mod_author
            ? __('Video deleted.', 'gta6-mods')
            : __('Video hidden from gallery.', 'gta6-mods');

        return [
            'success' => true,
            'message' => $message,
        ];
    }
}

if (!function_exists('gta6mods_api_feature_video')) {
    /**
     * Marks a video as the featured video for its mod.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_Error|array
     */
    function gta6mods_api_feature_video(WP_REST_Request $request) {
        global $wpdb;

        $video_id = (int) $request->get_param('video_id');
        $user_id  = get_current_user_id();

        if ($video_id <= 0 || $user_id <= 0) {
            return new WP_Error('invalid_request', __('Invalid request.', 'gta6-mods'), ['status' => 400]);
        }

        if (!gta6mods_user_can_feature_video($video_id, $user_id)) {
            return new WP_Error('forbidden', __('You do not have permission to perform this action.', 'gta6-mods'), ['status' => 403]);
        }

        $video = gta6mods_get_video_row($video_id);
        if (!$video) {
            return new WP_Error('not_found', __('Video not found.', 'gta6-mods'), ['status' => 404]);
        }

        if (isset($video->status) && 'approved' !== $video->status) {
            return new WP_Error('invalid_status', __('Only approved videos can be featured.', 'gta6-mods'), ['status' => 400]);
        }

        $table_name = gta6mods_get_video_table_name();
        $mod_id     = isset($video->mod_id) ? (int) $video->mod_id : 0;

        if ($mod_id <= 0) {
            return new WP_Error('invalid_mod', __('Unable to determine the parent mod.', 'gta6-mods'), ['status' => 400]);
        }

        $existing_featured = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE mod_id = %d AND is_featured = 1",
                $mod_id
            )
        );

        $wpdb->query('START TRANSACTION');

        $demoted = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} SET is_featured = 0, featured_at = NULL WHERE mod_id = %d AND id <> %d",
                $mod_id,
                $video_id
            )
        );

        if (false === $demoted) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', __('Failed to feature video.', 'gta6-mods'), ['status' => 500]);
        }

        $featured = $wpdb->update(
            $table_name,
            [
                'is_featured' => 1,
                'featured_at' => current_time('mysql'),
            ],
            ['id' => $video_id],
            ['%d', '%s'],
            ['%d']
        );

        if (false === $featured) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', __('Failed to feature video.', 'gta6-mods'), ['status' => 500]);
        }

        $wpdb->query('COMMIT');

        gta6mods_clear_single_video_cache($video_id);
        if (is_array($existing_featured)) {
            foreach ($existing_featured as $existing_id) {
                $existing_id = (int) $existing_id;
                if ($existing_id > 0 && $existing_id !== $video_id) {
                    gta6mods_clear_single_video_cache($existing_id);
                }
            }
        }

        gta6mods_clear_video_cache($mod_id);

        if (function_exists('gta6mods_clear_sitemap_cache')) {
            gta6mods_clear_sitemap_cache('videos');
        }

        return [
            'success'        => true,
            'message'        => __('Featured video updated.', 'gta6-mods'),
            'featured_video' => $video_id,
        ];
    }
}


if (!function_exists('gta6mods_api_unfeature_video')) {
    /**
     * Removes the featured flag from a video.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_Error|array
     */
    function gta6mods_api_unfeature_video(WP_REST_Request $request) {
        global $wpdb;

        $video_id = (int) $request->get_param('video_id');
        $user_id  = get_current_user_id();

        if ($video_id <= 0 || $user_id <= 0) {
            return new WP_Error('invalid_request', __('Invalid request.', 'gta6-mods'), ['status' => 400]);
        }

        if (!gta6mods_user_can_feature_video($video_id, $user_id)) {
            return new WP_Error('forbidden', __('You do not have permission to perform this action.', 'gta6-mods'), ['status' => 403]);
        }

        $video = gta6mods_get_video_row($video_id);
        if (!$video) {
            return new WP_Error('not_found', __('Video not found.', 'gta6-mods'), ['status' => 404]);
        }

        $table_name = gta6mods_get_video_table_name();
        $mod_id     = isset($video->mod_id) ? (int) $video->mod_id : 0;

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} SET is_featured = 0, featured_at = NULL WHERE id = %d",
                $video_id
            )
        );

        if (false === $updated) {
            return new WP_Error('db_error', __('Failed to remove featured video.', 'gta6-mods'), ['status' => 500]);
        }

        gta6mods_clear_single_video_cache($video_id);
        if ($mod_id > 0) {
            gta6mods_clear_video_cache($mod_id);
        }

        if (function_exists('gta6mods_clear_sitemap_cache')) {
            gta6mods_clear_sitemap_cache('videos');
        }

        return [
            'success' => true,
            'message' => __('Featured video removed.', 'gta6-mods'),
        ];
    }
}
