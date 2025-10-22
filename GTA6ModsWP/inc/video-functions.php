<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Video Gallery Functions
 */

if (!function_exists('gta6mods_get_video_table_name')) {
    /**
     * Returns the database table name that stores mod videos.
     */
    function gta6mods_get_video_table_name() {
        return GTA6MODS_VIDEOS_TABLE;
    }
}

if (!function_exists('gta6mods_get_video_reports_table_name')) {
    /**
     * Returns the database table name that stores mod video reports.
     */
    function gta6mods_get_video_reports_table_name() {
        return GTA6MODS_VIDEO_REPORTS_TABLE;
    }
}

if (!function_exists('gta6mods_get_video_row')) {
    /**
     * Retrieves a single video row from the database.
     *
     * @param int $video_id Video ID.
     * @return object|null
     */
    function gta6mods_get_video_row($video_id) {
        global $wpdb;

        $video_id = absint($video_id);
        if ($video_id <= 0) {
            return null;
        }

        $cache_key = 'video_row:' . $video_id;
        $cached    = wp_cache_get($cache_key, 'gta6mods_videos');
        if (false !== $cached) {
            return $cached;
        }

        $table_name = gta6mods_get_video_table_name();
        $row        = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $video_id)
        );

        if ($row) {
            wp_cache_set($cache_key, $row, 'gta6mods_videos', 600);
        }

        return $row;
    }
}

if (!function_exists('gta6mods_clear_single_video_cache')) {
    /**
     * Clears cached data for a single video record.
     *
     * @param int $video_id Video ID.
     */
    function gta6mods_clear_single_video_cache($video_id) {
        $video_id = absint($video_id);
        if ($video_id <= 0) {
            return;
        }

        wp_cache_delete('video_row:' . $video_id, 'gta6mods_videos');
    }
}

if (!function_exists('gta6mods_has_user_reported_video')) {
    /**
     * Checks whether a user has already reported a video.
     *
     * @param int $video_id Video ID.
     * @param int $user_id  User ID.
     * @return bool
     */
    function gta6mods_has_user_reported_video($video_id, $user_id) {
        global $wpdb;

        $video_id = absint($video_id);
        $user_id  = absint($user_id);

        if ($video_id <= 0 || $user_id <= 0) {
            return false;
        }

        $cache_key = sprintf('video_reported:%d:%d', $video_id, $user_id);
        $cached    = wp_cache_get($cache_key, 'gta6mods_video_reports');
        if (false !== $cached) {
            return (bool) $cached;
        }

        $reports_table = gta6mods_get_video_reports_table_name();

        $exists = (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$reports_table} WHERE video_id = %d AND user_id = %d LIMIT 1",
                $video_id,
                $user_id
            )
        );

        wp_cache_set($cache_key, $exists ? 1 : 0, 'gta6mods_video_reports', 600);

        return $exists;
    }
}

if (!function_exists('gta6mods_clear_video_report_cache')) {
    /**
     * Clears cached report lookups for a video.
     *
     * @param int      $video_id Video ID.
     * @param int|null $user_id  Optional user ID.
     */
    function gta6mods_clear_video_report_cache($video_id, $user_id = null) {
        $video_id = absint($video_id);
        if ($video_id <= 0) {
            return;
        }

        if (null !== $user_id) {
            $user_id = absint($user_id);
            if ($user_id > 0) {
                wp_cache_delete(sprintf('video_reported:%d:%d', $video_id, $user_id), 'gta6mods_video_reports');
            }
        }
    }
}

if (!function_exists('gta6mods_get_video_reporters_map')) {
    /**
     * Retrieves the reporters for the provided video IDs.
     *
     * @param int[] $video_ids Video IDs.
     * @return array<int, array<int, array<string, mixed>>>
     */
    function gta6mods_get_video_reporters_map($video_ids) {
        global $wpdb;

        if (!is_array($video_ids) || empty($video_ids)) {
            return [];
        }

        $video_ids = array_values(array_unique(array_map('absint', $video_ids)));
        $video_ids = array_filter($video_ids);

        if (empty($video_ids)) {
            return [];
        }

        $ids_sql = implode(',', $video_ids);
        if ('' === $ids_sql) {
            return [];
        }

        $reports_table = gta6mods_get_video_reports_table_name();

        $results = $wpdb->get_results(
            "SELECT r.video_id, r.user_id, r.reported_at, u.display_name, u.user_nicename
             FROM {$reports_table} r
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.video_id IN ({$ids_sql})
             ORDER BY r.reported_at DESC",
            ARRAY_A
        );

        if (!is_array($results) || empty($results)) {
            return [];
        }

        $grouped = [];
        foreach ($results as $row) {
            $video_id = isset($row['video_id']) ? (int) $row['video_id'] : 0;
            if ($video_id <= 0) {
                continue;
            }

            if (!isset($grouped[$video_id])) {
                $grouped[$video_id] = [];
            }

            $user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            $grouped[$video_id][] = [
                'user_id'       => $user_id,
                'display_name'  => isset($row['display_name']) ? sanitize_text_field($row['display_name']) : '',
                'user_nicename' => isset($row['user_nicename']) ? sanitize_title($row['user_nicename']) : '',
                'reported_at'   => isset($row['reported_at']) ? sanitize_text_field($row['reported_at']) : '',
            ];
        }

        return $grouped;
    }
}

if (!function_exists('gta6mods_user_can_manage_video')) {
    /**
     * Checks whether a user can manage (moderate or reject) a given video.
     *
     * @param int      $video_id Video ID.
     * @param int|null $user_id  Optional user ID.
     * @return bool
     */
    function gta6mods_user_can_manage_video($video_id, $user_id = null) {
        $video_id = absint($video_id);
        if ($video_id <= 0) {
            return false;
        }

        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        if (user_can($user_id, 'moderate_comments')) {
            return true;
        }

        $video = gta6mods_get_video_row($video_id);
        if (!$video || !isset($video->mod_id)) {
            return false;
        }

        $mod_id     = (int) $video->mod_id;
        $author_id  = $mod_id > 0 ? (int) get_post_field('post_author', $mod_id) : 0;

        return $author_id > 0 && $author_id === $user_id;
    }
}

if (!function_exists('gta6mods_user_can_feature_video')) {
    /**
     * Checks whether a user can feature a video for the given mod.
     *
     * @param int      $video_id Video ID.
     * @param int|null $user_id  Optional user ID.
     * @return bool
     */
    function gta6mods_user_can_feature_video($video_id, $user_id = null) {
        return gta6mods_user_can_manage_video($video_id, $user_id);
    }
}

if (!function_exists('gta6mods_get_video_statuses')) {
    /**
     * Returns the available moderation statuses for videos.
     *
     * @return string[]
     */
    function gta6mods_get_video_statuses() {
        return ['pending', 'approved', 'rejected', 'reported'];
    }
}

if (!function_exists('gta6mods_get_redis_client')) {
    /**
     * Returns a Redis client instance when available.
     *
     * @return \Redis|null
     */
    function gta6mods_get_redis_client() {
        static $client = null;
        static $unavailable = false;

        if ($unavailable) {
            return null;
        }

        if ($client instanceof Redis) {
            return $client;
        }

        if (!class_exists('Redis')) {
            $unavailable = true;
            return null;
        }

        try {
            $client = new Redis();
            $connected = $client->connect('127.0.0.1', 6379, 1.0);

            if (!$connected) {
                $client = null;
                $unavailable = true;
                return null;
            }

            return $client;
        } catch (Throwable $exception) {
            $client = null;
            $unavailable = true;
            return null;
        }
    }
}

if (!function_exists('gta6mods_extract_youtube_id')) {
    /**
     * Extracts the YouTube video ID from a supplied URL.
     *
     * @param string $url YouTube URL.
     * @return string|null
     */
    function gta6mods_extract_youtube_id($url) {
        if (!is_string($url) || $url === '') {
            return null;
        }

        $pattern = '/(?:youtube\.com\/(?:[^\/]+'
            . '\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/u';
        if (preg_match($pattern, $url, $matches) === 1 && !empty($matches[1])) {
            return $matches[1];
        }

        return null;
    }
}

if (!function_exists('gta6mods_get_mod_videos')) {
    /**
     * Retrieves videos for a given mod.
     *
     * @param int         $mod_id Mod post ID.
     * @param string      $status Moderation status.
     * @param int|null    $limit  Optional limit for the number of videos to fetch.
     * @return array[]
     */
    function gta6mods_get_mod_videos($mod_id, $status = 'approved', $limit = null) {
        $mod_id = absint($mod_id);

        if ($mod_id <= 0) {
            return [];
        }

        $available_statuses = gta6mods_get_video_statuses();
        $status             = is_string($status) ? sanitize_key($status) : '';

        if ($status !== '' && !in_array($status, $available_statuses, true)) {
            $status = '';
        }

        $limit = null !== $limit ? (int) $limit : null;
        if (null !== $limit && $limit <= 0) {
            $limit = null;
        }

        $status_key = $status !== '' ? $status : 'all';
        $limit_key  = null !== $limit ? (string) $limit : 'all';
        $cache_key  = sprintf('mod_videos:%d:%s:%s', $mod_id, $status_key, $limit_key);

        $redis = gta6mods_get_redis_client();
        if ($redis instanceof Redis) {
            try {
                $cached = $redis->get($cache_key);
                if (false !== $cached && null !== $cached) {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            } catch (Throwable $exception) {
                // Ignore Redis failures and continue.
            }
        } else {
            $cached = wp_cache_get($cache_key, 'gta6mods_videos');
            if (false !== $cached && is_array($cached)) {
                return $cached;
            }
        }

        global $wpdb;

        $table_name = gta6mods_get_video_table_name();
        $sql        = "SELECT v.*, u.display_name, u.user_nicename
             FROM {$table_name} v
             LEFT JOIN {$wpdb->users} u ON v.submitted_by = u.ID
             WHERE v.mod_id = %d";
        $placeholders = [$mod_id];

        if ($status !== '') {
            if ('approved' === $status) {
                $sql           .= ' AND v.status IN (%s, %s)';
                $placeholders[] = 'approved';
                $placeholders[] = 'reported';
            } else {
                $sql           .= ' AND v.status = %s';
                $placeholders[] = $status;
            }
        }

        $sql .= ' ORDER BY v.is_featured DESC, v.featured_at DESC, v.position ASC, v.submitted_at ASC, v.id ASC';

        if (null !== $limit) {
            $sql           .= ' LIMIT %d';
            $placeholders[] = $limit;
        }

        $prepared = $wpdb->prepare($sql, $placeholders);
        $videos   = $wpdb->get_results($prepared, ARRAY_A);

        if (!is_array($videos)) {
            $videos = [];
        }

        if (!empty($videos)) {
            foreach ($videos as &$video) {
                if (!is_array($video)) {
                    continue;
                }

                $attachment_id = isset($video['thumbnail_attachment_id']) ? (int) $video['thumbnail_attachment_id'] : 0;
                $video['thumbnail_attachment_id'] = $attachment_id;
                $video['thumbnail_url']          = '';
                $video['thumbnail_large_url']    = '';
                $video['thumbnail_small_url']    = '';
                $video['thumbnail_width']        = 0;
                $video['thumbnail_height']       = 0;
                $video['thumbnail_large_width']  = 0;
                $video['thumbnail_large_height'] = 0;
                $video['thumbnail_small_width']  = 0;
                $video['thumbnail_small_height'] = 0;
                $video['display_name']           = isset($video['display_name']) ? sanitize_text_field($video['display_name']) : '';
                $video['user_nicename']          = isset($video['user_nicename']) ? sanitize_title($video['user_nicename']) : '';

                $video['id']            = isset($video['id']) ? (int) $video['id'] : 0;
                $video['mod_id']        = isset($video['mod_id']) ? (int) $video['mod_id'] : $mod_id;
                $video['submitted_by']  = isset($video['submitted_by']) ? (int) $video['submitted_by'] : 0;
                $video['report_count']  = isset($video['report_count']) ? (int) $video['report_count'] : 0;
                $video['is_featured']   = !empty($video['is_featured']);
                $video['position']      = isset($video['position']) ? (int) $video['position'] : 0;
                $video['status']        = isset($video['status']) ? sanitize_key($video['status']) : '';
                $video['featured_at']   = isset($video['featured_at']) && $video['featured_at'] !== ''
                    ? sanitize_text_field($video['featured_at'])
                    : null;

                $video['video_title'] = isset($video['video_title']) && $video['video_title'] !== ''
                    ? sanitize_text_field($video['video_title'])
                    : '';

                $video['video_description'] = isset($video['video_description']) && $video['video_description'] !== ''
                    ? wp_strip_all_tags($video['video_description'])
                    : '';

                $video['duration'] = isset($video['duration']) && $video['duration'] !== ''
                    ? sanitize_text_field($video['duration'])
                    : '';

                if ($attachment_id > 0) {
                    $small_image = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                    $large_image = wp_get_attachment_image_src($attachment_id, 'medium_large');

                    if (!$large_image) {
                        $large_image = wp_get_attachment_image_src($attachment_id, 'large');
                    }

                    if ($small_image && isset($small_image[0])) {
                        $video['thumbnail_small_url']    = $small_image[0];
                        $video['thumbnail_small_width']  = isset($small_image[1]) ? (int) $small_image[1] : 0;
                        $video['thumbnail_small_height'] = isset($small_image[2]) ? (int) $small_image[2] : 0;
                    }

                    if ($large_image && isset($large_image[0])) {
                        $video['thumbnail_large_url']    = $large_image[0];
                        $video['thumbnail_large_width']  = isset($large_image[1]) ? (int) $large_image[1] : 0;
                        $video['thumbnail_large_height'] = isset($large_image[2]) ? (int) $large_image[2] : 0;
                    }

                    $image_url = '';

                    if (!empty($video['thumbnail_large_url'])) {
                        $image_url = $video['thumbnail_large_url'];
                        $video['thumbnail_width']  = $video['thumbnail_large_width'];
                        $video['thumbnail_height'] = $video['thumbnail_large_height'];
                    } elseif (!empty($video['thumbnail_small_url'])) {
                        $image_url = $video['thumbnail_small_url'];
                        $video['thumbnail_width']  = $video['thumbnail_small_width'];
                        $video['thumbnail_height'] = $video['thumbnail_small_height'];
                    }

                    if ($image_url) {
                        $video['thumbnail_url'] = $image_url;

                        if (empty($video['thumbnail_path'])) {
                            $relative = wp_make_link_relative($image_url);
                            if (!empty($relative)) {
                                $video['thumbnail_path'] = $relative;
                            }
                        }
                    }
                }

                if (empty($video['thumbnail_small_url']) && !empty($video['thumbnail_url'])) {
                    $video['thumbnail_small_url']   = $video['thumbnail_url'];
                    $video['thumbnail_small_width'] = $video['thumbnail_width'];
                    $video['thumbnail_small_height'] = $video['thumbnail_height'];
                }

                if (empty($video['thumbnail_large_url']) && !empty($video['thumbnail_url'])) {
                    $video['thumbnail_large_url']   = $video['thumbnail_url'];
                    $video['thumbnail_large_width'] = $video['thumbnail_width'];
                    $video['thumbnail_large_height'] = $video['thumbnail_height'];
                }
            }
            unset($video);
        }

        if ($redis instanceof Redis) {
            try {
                $redis->setex($cache_key, 600, wp_json_encode($videos));
            } catch (Throwable $exception) {
                wp_cache_set($cache_key, $videos, 'gta6mods_videos', 600);
            }
        } else {
            wp_cache_set($cache_key, $videos, 'gta6mods_videos', 600);
        }

        return $videos;
    }
}

if (!function_exists('gta6mods_get_next_video_position')) {
    /**
     * Retrieves the next available gallery position for a mod's videos.
     *
     * @param int $mod_id Mod post ID.
     * @return int
     */
    function gta6mods_get_next_video_position($mod_id) {
        global $wpdb;

        $mod_id = absint($mod_id);
        if ($mod_id <= 0) {
            return 1;
        }

        $table_name = gta6mods_get_video_table_name();

        $current_max = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(position) FROM {$table_name} WHERE mod_id = %d AND status IN (%s, %s)",
                $mod_id,
                'approved',
                'reported'
            )
        );

        return $current_max + 1;
    }
}

if (!function_exists('gta6mods_download_youtube_thumbnail')) {
    /**
     * Downloads a YouTube thumbnail, stores it as a WordPress attachment and returns metadata.
     *
     * @param string $youtube_id YouTube video identifier.
     * @param int    $mod_id     Parent mod post ID.
     * @return array|null        Array containing attachment details or null on failure.
     */
    function gta6mods_download_youtube_thumbnail($youtube_id, $mod_id = 0) {
        $youtube_id = is_string($youtube_id) ? trim($youtube_id) : '';
        if ($youtube_id === '') {
            return null;
        }

        $mod_id = absint($mod_id);

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $sizes      = ['maxresdefault', 'sddefault', 'hqdefault'];
        $source_url = null;

        foreach ($sizes as $size) {
            $url      = sprintf('https://i.ytimg.com/vi/%s/%s.jpg', rawurlencode($youtube_id), $size);
            $response = wp_remote_head($url, [
                'timeout'     => 5,
                'redirection' => 2,
            ]);

            if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
                $source_url = $url;
                break;
            }
        }

        if (!$source_url) {
            return null;
        }

        $tmp_file = download_url($source_url, 15);
        if (is_wp_error($tmp_file)) {
            return null;
        }

        $file_array = [
            'name'     => sanitize_file_name(sprintf('%s-%s.jpg', $youtube_id, wp_unique_id('thumb-'))),
            'tmp_name' => $tmp_file,
        ];

        $attachment_id = media_handle_sideload(
            $file_array,
            $mod_id,
            '',
            [
                'test_form' => false,
                'test_type' => false,
            ]
        );

        if (is_wp_error($attachment_id)) {
            @unlink($tmp_file);
            return null;
        }

        if ($mod_id > 0) {
            wp_update_post([
                'ID'          => (int) $attachment_id,
                'post_parent' => $mod_id,
            ]);
        }

        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            wp_delete_attachment($attachment_id, true);
            if (file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
            return null;
        }

        $relative_url = wp_make_link_relative($image_url);
        if (!$relative_url) {
            $relative_url = $image_url;
        }

        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        return [
            'attachment_id' => (int) $attachment_id,
            'relative_url'  => $relative_url,
            'url'           => $image_url,
        ];
    }
}

if (!function_exists('gta6mods_fetch_youtube_video_details')) {
    /**
     * Fetches video metadata from the YouTube Data API v3.
     *
     * @param string $youtube_id YouTube video identifier.
     *
     * @return array<string, string>|null
     */
    function gta6mods_fetch_youtube_video_details($youtube_id) {
        $youtube_id = is_string($youtube_id) ? trim($youtube_id) : '';
        if ('' === $youtube_id) {
            return null;
        }

        $api_key = (string) get_option('gta6mods_youtube_api_key', '');
        $api_key = trim($api_key);

        if ('' === $api_key) {
            return null;
        }

        $request_url = add_query_arg(
            [
                'part' => 'snippet,contentDetails',
                'id'   => $youtube_id,
                'key'  => $api_key,
            ],
            'https://www.googleapis.com/youtube/v3/videos'
        );

        $response = wp_remote_get($request_url, [
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if ('' === $body) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || empty($decoded['items']) || !isset($decoded['items'][0])) {
            return null;
        }

        $item = $decoded['items'][0];
        if (!is_array($item)) {
            return null;
        }

        $snippet        = isset($item['snippet']) && is_array($item['snippet']) ? $item['snippet'] : [];
        $contentDetails = isset($item['contentDetails']) && is_array($item['contentDetails']) ? $item['contentDetails'] : [];

        $title       = isset($snippet['title']) ? sanitize_text_field($snippet['title']) : '';
        $description = isset($snippet['description']) ? sanitize_textarea_field($snippet['description']) : '';
        $duration    = isset($contentDetails['duration']) ? sanitize_text_field($contentDetails['duration']) : '';

        if ('' === $title && '' === $description && '' === $duration) {
            return null;
        }

        return [
            'title'       => $title,
            'description' => $description,
            'duration'    => $duration,
        ];
    }
}

if (!function_exists('gta6mods_clear_video_cache')) {
    /**
     * Clears cached video data for a mod.
     *
     * @param int $mod_id Mod post ID.
     */
    function gta6mods_clear_video_cache($mod_id) {
        $mod_id = absint($mod_id);

        if ($mod_id <= 0) {
            return;
        }

        $redis = gta6mods_get_redis_client();

        if ($redis instanceof Redis) {
            try {
                $pattern  = sprintf('mod_videos:%d:*', $mod_id);
                $iterator = null;

                do {
                    $keys = $redis->scan($iterator, $pattern, 50);
                    if (is_array($keys) && !empty($keys)) {
                        foreach ($keys as $key) {
                            $redis->del($key);
                        }
                    }
                } while ($iterator > 0);
            } catch (Throwable $exception) {
                // Ignore Redis errors silently.
            }
        } else {
            $statuses = gta6mods_get_video_statuses();
            $statuses[] = 'all';
            $statuses   = array_unique($statuses);

            $limits = [null, 4, 10, 20, 50, 'all'];

            foreach ($statuses as $status) {
                $status_key = $status ? $status : 'all';

                foreach ($limits as $limit) {
                    $limit_key = (null !== $limit && 'all' !== $limit) ? (string) $limit : 'all';
                    $cache_key = sprintf('mod_videos:%d:%s:%s', $mod_id, $status_key, $limit_key);
                    wp_cache_delete($cache_key, 'gta6mods_videos');
                }
            }
        }
    }
}

if (!function_exists('gta6mods_get_video_count')) {
    /**
     * Returns the number of videos a mod has in a given status.
     *
     * @param int    $mod_id Mod post ID.
     * @param string $status Moderation status.
     * @return int
     */
    function gta6mods_get_video_count($mod_id, $status = 'approved') {
        global $wpdb;

        $mod_id = (int) $mod_id;
        if ($mod_id <= 0) {
            return 0;
        }

        $status     = is_string($status) ? $status : 'approved';
        $table_name = gta6mods_get_video_table_name();

        if ('approved' === $status) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE mod_id = %d AND status IN (%s, %s)",
                    $mod_id,
                    'approved',
                    'reported'
                )
            );
        } else {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE mod_id = %d AND status = %s",
                    $mod_id,
                    $status
                )
            );
        }

        return (int) $count;
    }
}

if (!function_exists('gta6mods_get_video_schema_fallbacks')) {
    /**
     * Provides fallback data for schema.org markup when API metadata is unavailable.
     *
     * @param int $mod_id Mod post ID.
     *
     * @return array<string, string>
     */
    function gta6mods_get_video_schema_fallbacks($mod_id) {
        $fallback_title = get_the_title($mod_id);
        if (!is_string($fallback_title) || '' === $fallback_title) {
            $fallback_title = __('Mod video', 'gta6-mods');
        }

        $fallback_description = '';
        $post = get_post($mod_id);

        if ($post instanceof WP_Post) {
            if (has_excerpt($post)) {
                $fallback_description = wp_strip_all_tags(get_the_excerpt($post));
            }

            if ('' === $fallback_description) {
                $fallback_description = wp_trim_words(wp_strip_all_tags($post->post_content), 55, '');
            }
        }

        if ('' === $fallback_description) {
            $fallback_description = __('Gameplay video for this GTA mod.', 'gta6-mods');
        }

        return [
            'title'       => $fallback_title,
            'description' => $fallback_description,
        ];
    }
}

if (!function_exists('gta6mods_output_video_schema_markup')) {
    /**
     * Outputs schema.org markup for approved mod videos.
     *
     * @param int $mod_id Optional mod ID.
     */
    function gta6mods_output_video_schema_markup($mod_id = 0) {
        $mod_id = absint($mod_id ? $mod_id : get_the_ID());
        if ($mod_id <= 0) {
            return;
        }

        $videos = gta6mods_get_mod_videos($mod_id, 'approved');
        if (empty($videos)) {
            return;
        }

        $fallbacks = gta6mods_get_video_schema_fallbacks($mod_id);

        $primary_video = null;
        foreach ($videos as $video) {
            if (is_array($video) && !empty($video['youtube_id'])) {
                $primary_video = $video;
                break;
            }
        }

        if (empty($primary_video)) {
            return;
        }

        $video_title = isset($primary_video['video_title']) && $primary_video['video_title'] !== ''
            ? $primary_video['video_title']
            : $fallbacks['title'];

        $video_description = isset($primary_video['video_description']) && $primary_video['video_description'] !== ''
            ? $primary_video['video_description']
            : $fallbacks['description'];

        $video_title       = sanitize_text_field($video_title);
        $video_description = wp_strip_all_tags($video_description);
        $video_description = wp_trim_words($video_description, 55, '');

        if (!empty($primary_video['thumbnail_path'])) {
            $thumbnail = home_url($primary_video['thumbnail_path']);
        } else {
            $thumbnail = sprintf('https://i.ytimg.com/vi/%s/hqdefault.jpg', rawurlencode($primary_video['youtube_id']));
        }

        $duration = isset($primary_video['duration']) && $primary_video['duration'] !== ''
            ? $primary_video['duration']
            : 'PT0M0S';

        $upload_date_source = !empty($primary_video['moderated_at']) ? $primary_video['moderated_at'] : $primary_video['submitted_at'];
        $upload_timestamp   = $upload_date_source ? strtotime($upload_date_source) : false;
        $upload_date        = $upload_timestamp ? gmdate('c', $upload_timestamp) : gmdate('c');

        $content_url = !empty($primary_video['youtube_url'])
            ? $primary_video['youtube_url']
            : sprintf('https://www.youtube.com/watch?v=%s', rawurlencode($primary_video['youtube_id']));

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'VideoObject',
            'name'            => $video_title,
            'description'     => $video_description,
            'thumbnailUrl'    => $thumbnail,
            'uploadDate'      => $upload_date,
            'contentUrl'      => esc_url_raw($content_url),
            'embedUrl'        => sprintf('https://www.youtube.com/embed/%s', rawurlencode($primary_video['youtube_id'])),
            'duration'        => $duration,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

if (!function_exists('gta6mods_render_video_schema_in_head')) {
    /**
     * Renders schema markup on single mod pages.
     */
    function gta6mods_render_video_schema_in_head() {
        if (!is_singular('post')) {
            return;
        }

        gta6mods_output_video_schema_markup(get_queried_object_id());
    }
}
add_action('wp_head', 'gta6mods_render_video_schema_in_head', 45);
