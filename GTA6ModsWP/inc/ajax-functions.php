<?php
/**
 * AJAX handlers.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GTA6_MODS_ACCOUNT_DELETION_META_KEY')) {
    define('GTA6_MODS_ACCOUNT_DELETION_META_KEY', '_gta6mods_account_deletion');
}

if (!defined('GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY')) {
    define('GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY', '_gta6mods_account_deletion_content');
}

if (!defined('GTA6_MODS_DELETED_COMMENT_META_KEY')) {
    define('GTA6_MODS_DELETED_COMMENT_META_KEY', '_gta6mods_deleted_comment');
}

function gta6_mods_increment_download_count($post_id, $version_id = 0) {
    $post_id    = (int) $post_id;
    $version_id = (int) $version_id;

    if ($post_id <= 0) {
        return [
            'post'    => 0,
            'version' => 0,
        ];
    }

    $post_count = (int) gta6mods_increment_mod_stat($post_id, 'downloads', 1);
    update_post_meta($post_id, '_gta6mods_last_downloaded', current_time('timestamp'));
    gta6mods_adjust_author_download_total($post_id, 1);

    $resolved_version_id = $version_id;
    $version             = null;

    if ($resolved_version_id > 0) {
        $version = GTA6Mods_Mod_Versions::get_version($resolved_version_id);
        if (!$version || (int) $version['mod_id'] !== $post_id) {
            $version             = null;
            $resolved_version_id = 0;
        }
    }

    if (!$version) {
        $version = GTA6Mods_Mod_Versions::get_latest_version($post_id);
        if ($version) {
            $resolved_version_id = (int) $version['id'];
        }
    }

    $version_count = 0;

    if ($version) {
        $incremented = GTA6Mods_Mod_Versions::increment_download_count((int) $version['id']);
        if (false !== $incremented) {
            $version_count = (int) $incremented;
        }
        update_post_meta($post_id, '_gta6mods_last_downloaded_version', $resolved_version_id);
    }

    return [
        'post'       => $post_count,
        'version'    => $version_count,
        'version_id' => $resolved_version_id,
    ];
}

function gta6_mods_ajax_increment_download() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'gta6mods_download')) {
        wp_send_json_error(['message' => __('Invalid request.', 'gta6-mods')], 400);
    }

    $post_id = isset($_POST['postId']) ? absint($_POST['postId']) : 0;
    if ($post_id <= 0 || get_post_type($post_id) !== 'post') {
        wp_send_json_error(['message' => __('Invalid post.', 'gta6-mods')], 400);
    }

    $version_id = isset($_POST['versionId']) ? absint($_POST['versionId']) : 0;
    $counts     = gta6_mods_increment_download_count($post_id, $version_id);
    $count      = isset($counts['post']) ? (int) $counts['post'] : 0;
    $version_count = isset($counts['version']) ? (int) $counts['version'] : 0;
    $resolved_version_id = isset($counts['version_id']) ? (int) $counts['version_id'] : $version_id;
    $last_downloaded_human = gta6_mods_format_time_ago(gta6_mods_get_last_download_timestamp($post_id));

    wp_send_json_success([
        'downloads'            => $count,
        'formattedDownloads'   => number_format_i18n($count),
        'lastDownloadedHuman'  => $last_downloaded_human,
        'versionId'            => $resolved_version_id,
        'versionDownloads'     => $version_count,
        'formattedVersionDownloads' => number_format_i18n($version_count),
    ]);
}
add_action('wp_ajax_gta6mods_increment_download', 'gta6_mods_ajax_increment_download');
add_action('wp_ajax_nopriv_gta6mods_increment_download', 'gta6_mods_ajax_increment_download');

function gta6_mods_ajax_track_post_view() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'gta6mods_track_view')) {
        wp_send_json_error(['message' => __('Invalid request.', 'gta6-mods')], 400);
    }

    $post_id = isset($_POST['postId']) ? absint($_POST['postId']) : 0;
    if ($post_id <= 0 || get_post_type($post_id) !== 'post') {
        wp_send_json_error(['message' => __('Invalid post.', 'gta6-mods')], 400);
    }

    $count = gta6_mods_increment_view_count($post_id);

    wp_send_json_success([
        'views'           => $count,
        'formattedViews'  => number_format_i18n($count),
    ]);
}
add_action('wp_ajax_gta6mods_track_post_view', 'gta6_mods_ajax_track_post_view');
add_action('wp_ajax_nopriv_gta6mods_track_post_view', 'gta6_mods_ajax_track_post_view');

function gta6_mods_ajax_increment_profile_view() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'gta6mods_profile_view')) {
        wp_send_json_error(['message' => __('Invalid request.', 'gta6-mods')], 400);
    }

    $author_id = isset($_POST['authorId']) ? absint($_POST['authorId']) : 0;
    if ($author_id <= 0) {
        wp_send_json_error(['message' => __('Invalid profile.', 'gta6-mods')], 400);
    }

    $current_user_id = get_current_user_id();
    if ($current_user_id !== $author_id) {
        gta6mods_increment_user_meta_counter($author_id, '_profile_view_count', 1);
    }

    $views = (int) get_user_meta($author_id, '_profile_view_count', true);

    wp_send_json_success([
        'views'          => $views,
        'formattedViews' => number_format_i18n($views),
    ]);
}
add_action('wp_ajax_gta6mods_increment_profile_view', 'gta6_mods_ajax_increment_profile_view');
add_action('wp_ajax_nopriv_gta6mods_increment_profile_view', 'gta6_mods_ajax_increment_profile_view');

function gta6_mods_ajax_update_last_activity() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Authentication required.', 'gta6-mods')], 403);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'gta6mods_last_activity')) {
        wp_send_json_error(['message' => __('Invalid request.', 'gta6-mods')], 400);
    }

    $user_id       = get_current_user_id();
    $timestamp_sql = current_time('mysql', true);
    $timestamp     = current_time('timestamp', true);

    update_user_meta($user_id, '_last_activity', $timestamp_sql);

    wp_send_json_success([
        'timestamp'      => $timestamp,
        'timestampHuman' => gta6_mods_format_time_ago($timestamp),
    ]);
}
add_action('wp_ajax_gta6mods_update_last_activity', 'gta6_mods_ajax_update_last_activity');

function gta6_mods_handle_like() {
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => __('Be kell jelentkezned a kedveléshez.', 'gta6-mods'),
        ], 403);
    }

    check_ajax_referer('gta6mods_like_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
    if ($post_id <= 0 || get_post_type($post_id) !== 'post') {
        wp_send_json_error([
            'message' => __('Érvénytelen bejegyzés.', 'gta6-mods'),
        ], 400);
    }

    $user_id          = get_current_user_id();
    $liked_users_key  = '_gta6mods_liked_users';
    $liked_users_meta = get_post_meta($post_id, $liked_users_key, true);
    $liked_users      = [];

    if (is_array($liked_users_meta)) {
        foreach ($liked_users_meta as $liked_user_id) {
            $liked_user_id = (int) $liked_user_id;
            if ($liked_user_id > 0) {
                $liked_users[$liked_user_id] = $liked_user_id;
            }
        }
    }

    $already_liked = isset($liked_users[$user_id]);

    if ($already_liked) {
        unset($liked_users[$user_id]);
    } else {
        $liked_users[$user_id] = $user_id;
    }

    $liked_users_list = array_values($liked_users);
    $total_likes      = count($liked_users_list);

    update_post_meta($post_id, $liked_users_key, $liked_users_list);

    $total_likes = (int) gta6mods_set_mod_stat($post_id, 'likes', $total_likes);

    wp_cache_delete($post_id, 'post_meta');
    delete_transient('gta6_front_page_data_v1');

    $delta = $already_liked ? -1 : 1;
    gta6mods_adjust_author_like_total($post_id, $delta);

    wp_send_json_success([
        'liked' => !$already_liked,
        'total' => $total_likes,
    ]);
}
add_action('wp_ajax_gta6mods_like', 'gta6_mods_handle_like');
add_action('wp_ajax_nopriv_gta6mods_like', 'gta6_mods_handle_like');

// DEPRECATED: Rating submissions now use the REST endpoint in inc/rest-api/ratings-endpoints.php.
// add_action('wp_ajax_gta6mods_rating', 'gta6_mods_handle_rating');
// add_action('wp_ajax_nopriv_gta6mods_rating', 'gta6_mods_handle_rating');

function gta6mods_ajax_submit_mod_update() {
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => __('Be kell jelentkezned a frissítés beküldéséhez.', 'gta6-mods'),
        ], 403);
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'gta6mods_update_mod')) {
        wp_send_json_error([
            'message' => __('Érvénytelen kérés.', 'gta6-mods'),
        ], 400);
    }

    $mod_id = isset($_POST['mod_id']) ? absint($_POST['mod_id']) : 0;
    $allowed_types = function_exists('gta6mods_get_mod_post_types') ? gta6mods_get_mod_post_types() : ['post'];

    if ($mod_id <= 0 || !in_array(get_post_type($mod_id), $allowed_types, true)) {
        wp_send_json_error([
            'message' => __('Érvénytelen mod.', 'gta6-mods'),
        ], 400);
    }

    if (!current_user_can('edit_post', $mod_id)) {
        wp_send_json_error([
            'message' => __('Nincs jogosultságod a mod frissítéséhez.', 'gta6-mods'),
        ], 403);
    }

    $mod_post = get_post($mod_id);
    if (!$mod_post instanceof WP_Post) {
        wp_send_json_error([
            'message' => __('A mod nem található.', 'gta6-mods'),
        ], 400);
    }

    $mod_title_raw = isset($_POST['mod_title']) ? sanitize_text_field(wp_unslash($_POST['mod_title'])) : '';
    if ('' === $mod_title_raw) {
        wp_send_json_error([
            'message' => __('Add meg a mod címét.', 'gta6-mods'),
        ], 400);
    }

    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    $category_term = $category_id > 0 ? get_term($category_id, 'category') : null;
    $is_allowed_category = $category_term instanceof WP_Term && (!function_exists('gta6mods_is_allowed_category_id') || gta6mods_is_allowed_category_id($category_id));

    if (!$is_allowed_category) {
        wp_send_json_error([
            'message' => __('Válassz érvényes kategóriát.', 'gta6-mods'),
        ], 400);
    }

    $current_mod_title = get_the_title($mod_id);

    $current_category_terms = get_the_terms($mod_id, 'category');
    $current_primary_category_id = 0;
    if (!empty($current_category_terms) && !is_wp_error($current_category_terms)) {
        $current_primary_category = $current_category_terms[0];
        if ($current_primary_category instanceof WP_Term) {
            $current_primary_category_id = (int) $current_primary_category->term_id;
        }
    }

    $tags_raw = isset($_POST['tags']) ? sanitize_text_field(wp_unslash($_POST['tags'])) : '';
    $tags = array_filter(array_map('trim', explode(',', $tags_raw)));

    $description_raw = isset($_POST['description']) ? wp_unslash($_POST['description']) : '';
    $description_json = gta6_mods_normalize_editorjs_json($description_raw);
    if ('' === $description_json) {
        wp_send_json_error([
            'message' => __('A leírás üres vagy hibás formátumú.', 'gta6-mods'),
        ], 400);
    }

    $description = gta6_mods_editorjs_to_gutenberg_blocks($description_json);

    $video_permission = isset($_POST['video_permissions']) ? sanitize_key(wp_unslash($_POST['video_permissions'])) : 'moderate';
    if (!in_array($video_permission, ['deny', 'moderate', 'allow'], true)) {
        $video_permission = 'moderate';
    }

    $authors = [];
    if (!empty($_POST['authors']) && is_array($_POST['authors'])) {
        foreach ($_POST['authors'] as $author_name) {
            $author_name = sanitize_text_field(wp_unslash($author_name));
            if ('' !== $author_name) {
                $authors[] = $author_name;
            }
        }
    }

    $deleted_existing = [];
    if (!empty($_POST['deleted_existing_screenshots'])) {
        $deleted_existing = json_decode(wp_unslash($_POST['deleted_existing_screenshots']), true);
        if (!is_array($deleted_existing)) {
            $deleted_existing = [];
        }
    }
    $deleted_existing = array_values(array_filter(array_map('absint', $deleted_existing)));

    $screenshot_order = [];
    if (!empty($_POST['screenshot_order'])) {
        $decoded_order = json_decode(wp_unslash($_POST['screenshot_order']), true);
        if (is_array($decoded_order)) {
            $screenshot_order = array_values(array_filter(array_map('sanitize_text_field', $decoded_order)));
        }
    }

    $featured_identifier = isset($_POST['featured_identifier']) ? sanitize_text_field(wp_unslash($_POST['featured_identifier'])) : '';

    $changelog = [];
    if (!empty($_POST['changelog'])) {
        $decoded_changelog = json_decode(wp_unslash($_POST['changelog']), true);
        if (is_array($decoded_changelog)) {
            foreach ($decoded_changelog as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $item = trim(wp_strip_all_tags($item));
                if ('' !== $item) {
                    $changelog[] = $item;
                }
            }
        }
    }

    $new_version_number = isset($_POST['new_version']) ? sanitize_text_field(wp_unslash($_POST['new_version'])) : '';
    $current_version = function_exists('gta6mods_get_current_version') ? gta6mods_get_current_version($mod_id) : [];

    $external_url_raw = isset($_POST['mod_url']) ? wp_unslash($_POST['mod_url']) : '';
    $external_url     = $external_url_raw ? esc_url_raw($external_url_raw) : '';
    $external_size_value = isset($_POST['file_size_value']) ? wp_unslash($_POST['file_size_value']) : '';
    $external_size_unit  = isset($_POST['file_size_unit']) ? sanitize_text_field(wp_unslash($_POST['file_size_unit'])) : 'MB';
    $version_scan_url_raw = isset($_POST['version_scan_url']) ? wp_unslash($_POST['version_scan_url']) : '';
    $version_scan_url     = '';

    if ('' !== $version_scan_url_raw) {
        $maybe_scan_url = esc_url_raw($version_scan_url_raw);
        if ($maybe_scan_url && filter_var($maybe_scan_url, FILTER_VALIDATE_URL)) {
            $version_scan_url = $maybe_scan_url;
        }
    }

    $is_version_attempt = '' !== $new_version_number || !empty($_FILES['mod_file']['name']) || '' !== $external_url || !empty($changelog);

    if ($is_version_attempt) {
        if ('' === $new_version_number) {
            wp_send_json_error([
                'message' => __('Add meg az új verziószámot.', 'gta6-mods'),
            ], 400);
        }

        if (!empty($current_version['number']) && version_compare($new_version_number, $current_version['number'], '<=')) {
            wp_send_json_error([
                'message' => sprintf(__('Az új verziószámnak magasabbnak kell lennie, mint a jelenlegi (%s).', 'gta6-mods'), $current_version['number']),
            ], 400);
        }

        if (empty($changelog)) {
            wp_send_json_error([
                'message' => __('Adj hozzá legalább egy változást a changeloghoz.', 'gta6-mods'),
            ], 400);
        }
    }

    if (empty($screenshot_order)) {
        wp_send_json_error([
            'message' => __('Legalább egy képet meg kell tartanod vagy feltöltened.', 'gta6-mods'),
        ], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $uploaded_screenshots = [];
    if (isset($_FILES['new_screenshots']) && !empty($_FILES['new_screenshots']['name']) && is_array($_FILES['new_screenshots']['name'])) {
        foreach ($_FILES['new_screenshots']['name'] as $key => $name) {
            if (empty($name)) {
                continue;
            }

            $error_code = isset($_FILES['new_screenshots']['error'][$key]) ? (int) $_FILES['new_screenshots']['error'][$key] : UPLOAD_ERR_OK;
            if (UPLOAD_ERR_OK !== $error_code) {
                continue;
            }

            $file_array = [
                'name'     => $name,
                'type'     => $_FILES['new_screenshots']['type'][$key],
                'tmp_name' => $_FILES['new_screenshots']['tmp_name'][$key],
                'error'    => $_FILES['new_screenshots']['error'][$key],
                'size'     => $_FILES['new_screenshots']['size'][$key],
            ];

            $attachment_id = media_handle_sideload($file_array, 0);
            if (is_wp_error($attachment_id)) {
                foreach ($uploaded_screenshots as $uploaded) {
                    if (!empty($uploaded['attachment_id'])) {
                        wp_delete_attachment($uploaded['attachment_id'], true);
                    }
                }
                wp_send_json_error([
                    'message' => __('Nem sikerült feltölteni az egyik új képet.', 'gta6-mods'),
                ], 500);
            }

            $uploaded_screenshots[$key] = [
                'attachment_id' => $attachment_id,
            ];
        }
    }

    $version_source = [];
    $mod_file_attachment = null;

    if (isset($_FILES['mod_file']) && !empty($_FILES['mod_file']['name'])) {
        $mod_file_error = isset($_FILES['mod_file']['error']) ? (int) $_FILES['mod_file']['error'] : UPLOAD_ERR_OK;
        if (UPLOAD_ERR_OK !== $mod_file_error) {
            foreach ($uploaded_screenshots as $uploaded) {
                if (!empty($uploaded['attachment_id'])) {
                    wp_delete_attachment($uploaded['attachment_id'], true);
                }
            }
            wp_send_json_error([
                'message' => __('Nem sikerült feltölteni a mod fájlt.', 'gta6-mods'),
            ], 400);
        }

        if ('' !== $external_url) {
            foreach ($uploaded_screenshots as $uploaded) {
                if (!empty($uploaded['attachment_id'])) {
                    wp_delete_attachment($uploaded['attachment_id'], true);
                }
            }
            wp_send_json_error([
                'message' => __('Válassz fájl feltöltést vagy külső linket, de ne mindkettőt.', 'gta6-mods'),
            ], 400);
        }

        $allowed_mimes = [
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z'  => 'application/x-7z-compressed',
            'oiv' => 'application/octet-stream',
        ];

        $mod_file = $_FILES['mod_file'];
        $ext = strtolower(pathinfo($mod_file['name'], PATHINFO_EXTENSION));

        if (!array_key_exists($ext, $allowed_mimes)) {
            foreach ($uploaded_screenshots as $uploaded) {
                if (!empty($uploaded['attachment_id'])) {
                    wp_delete_attachment($uploaded['attachment_id'], true);
                }
            }
            wp_send_json_error([
                'message' => __('A mod fájl típusa nem engedélyezett.', 'gta6-mods'),
            ], 400);
        }

        $file_array = [
            'name'     => $mod_file['name'],
            'type'     => $mod_file['type'],
            'tmp_name' => $mod_file['tmp_name'],
            'error'    => $mod_file['error'],
            'size'     => $mod_file['size'],
        ];

        $mod_file_attachment = media_handle_sideload($file_array, 0);
        if (is_wp_error($mod_file_attachment)) {
            foreach ($uploaded_screenshots as $uploaded) {
                if (!empty($uploaded['attachment_id'])) {
                    wp_delete_attachment($uploaded['attachment_id'], true);
                }
            }
            wp_send_json_error([
                'message' => __('Nem sikerült feltölteni a mod fájlt.', 'gta6-mods'),
            ], 500);
        }

        $size_bytes = isset($mod_file['size']) ? (int) $mod_file['size'] : 0;
        if ($size_bytes <= 0) {
            $path = get_attached_file($mod_file_attachment);
            if ($path && file_exists($path)) {
                $size_bytes = (int) filesize($path);
            }
        }

        $version_source = [
            'type'          => 'file',
            'attachment_id' => $mod_file_attachment,
            'size_bytes'    => $size_bytes,
            'size_human'    => $size_bytes > 0 ? size_format((float) $size_bytes) : '',
        ];
    } elseif ('' !== $external_url) {
        $size_value = is_string($external_size_value) ? str_replace(',', '.', trim($external_size_value)) : '';
        $size_float = (float) $size_value;
        if ($size_float <= 0) {
            foreach ($uploaded_screenshots as $uploaded) {
                if (!empty($uploaded['attachment_id'])) {
                    wp_delete_attachment($uploaded['attachment_id'], true);
                }
            }
            wp_send_json_error([
                'message' => __('Adj meg érvényes fájlméretet.', 'gta6-mods'),
            ], 400);
        }

        $unit = in_array(strtoupper($external_size_unit), ['MB', 'GB'], true) ? strtoupper($external_size_unit) : 'MB';
        $bytes = 'GB' === $unit ? (int) round($size_float * 1024 * 1024 * 1024) : (int) round($size_float * 1024 * 1024);
        $version_source = [
            'type'       => 'external',
            'url'        => $external_url,
            'size_bytes' => $bytes,
            'size_human' => sprintf('%s %s', trim(rtrim(rtrim(number_format($size_float, 2, '.', ''), '0'), '.')), $unit),
        ];
    }

    if ($is_version_attempt && empty($version_source)) {
        foreach ($uploaded_screenshots as $uploaded) {
            if (!empty($uploaded['attachment_id'])) {
                wp_delete_attachment($uploaded['attachment_id'], true);
            }
        }
        if ($mod_file_attachment) {
            wp_delete_attachment($mod_file_attachment, true);
        }
        wp_send_json_error([
            'message' => __('Adj meg letöltési forrást az új verzióhoz.', 'gta6-mods'),
        ], 400);
    }

    $update_post_id = wp_insert_post([
        'post_type'   => 'mod_update',
        'post_status' => 'pending',
        'post_title'  => sprintf('%s – %s', get_the_title($mod_id), current_time(get_option('date_format') . ' ' . get_option('time_format'))),
        'post_author' => get_current_user_id(),
    ], true);

    if (is_wp_error($update_post_id)) {
        foreach ($uploaded_screenshots as $uploaded) {
            if (!empty($uploaded['attachment_id'])) {
                wp_delete_attachment($uploaded['attachment_id'], true);
            }
        }
        if ($mod_file_attachment) {
            wp_delete_attachment($mod_file_attachment, true);
        }

        wp_send_json_error([
            'message' => __('Nem sikerült létrehozni a frissítési kérelmet.', 'gta6-mods'),
        ], 500);
    }

    update_post_meta($update_post_id, '_gta6mods_update_mod_id', $mod_id);
    update_post_meta($update_post_id, '_gta6mods_update_description', $description);
    update_post_meta($update_post_id, '_gta6mods_update_description_json', $description_json);
    update_post_meta($update_post_id, '_gta6mods_update_tags', $tags);
    update_post_meta($update_post_id, '_gta6mods_update_video_permission', $video_permission);
    update_post_meta($update_post_id, '_gta6mods_update_authors', $authors);
    update_post_meta($update_post_id, '_gta6mods_update_deleted_screenshots', $deleted_existing);
    update_post_meta($update_post_id, '_gta6mods_update_screenshot_order', $screenshot_order);
    update_post_meta($update_post_id, '_gta6mods_update_featured_identifier', $featured_identifier);
    update_post_meta($update_post_id, '_gta6mods_update_changelog', $changelog);
    update_post_meta($update_post_id, '_gta6mods_update_version_number', $new_version_number);
    update_post_meta($update_post_id, '_gta6mods_update_submitted_by', get_current_user_id());

    if ($mod_title_raw !== $current_mod_title) {
        update_post_meta($update_post_id, '_gta6mods_update_mod_title', $mod_title_raw);
    } else {
        delete_post_meta($update_post_id, '_gta6mods_update_mod_title');
    }

    if ($category_id !== $current_primary_category_id) {
        update_post_meta($update_post_id, '_gta6mods_update_category_id', $category_id);
    } else {
        delete_post_meta($update_post_id, '_gta6mods_update_category_id');
    }

    if (!empty($uploaded_screenshots)) {
        gta6mods_set_update_new_screenshots($update_post_id, $uploaded_screenshots);
    } else {
        delete_post_meta($update_post_id, '_gta6mods_update_new_screenshots');
    }

    if (!empty($version_source)) {
        gta6mods_set_update_version_source($update_post_id, $version_source);
    } else {
        delete_post_meta($update_post_id, '_gta6mods_update_version_source');
    }

    if ('' !== $version_scan_url) {
        update_post_meta($update_post_id, '_gta6mods_update_version_scan_url', $version_scan_url);
    } else {
        delete_post_meta($update_post_id, '_gta6mods_update_version_scan_url');
    }

    $redirect_url = add_query_arg('update', 'sucess', get_permalink($mod_id));

    wp_send_json_success([
        'message'       => __('A frissítési kérelmed beérkezett, hamarosan átnézzük.', 'gta6-mods'),
        'redirect_url'  => $redirect_url,
    ]);
}
add_action('wp_ajax_gta6mods_submit_mod_update', 'gta6mods_ajax_submit_mod_update');

/**
 * AJAX handler for sorting comments.
 */
function gta6_mods_ajax_sort_comments() {
    check_ajax_referer('gta6_sort_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $sort_order = isset($_POST['sort_order']) ? sanitize_key($_POST['sort_order']) : 'best';

    if ($post_id <= 0) {
        wp_send_json_error(['message' => __('Invalid post.', 'gta6-mods')], 400);
    }

    $comments = [];
    $pinned_comment_id = gta6mods_get_pinned_comment_id($post_id);

    $base_args = [
        'post_id' => $post_id,
        'status' => 'approve',
    ];

    switch ($sort_order) {
        case 'newest':
            $base_args['orderby'] = 'comment_date_gmt';
            $base_args['order'] = 'DESC';
            $comments = get_comments($base_args);
            break;
        case 'oldest':
            $base_args['orderby'] = 'comment_date_gmt';
            $base_args['order'] = 'ASC';
            $comments = get_comments($base_args);
            break;
        case 'best':
        default:
            $top_level_args = array_merge($base_args, ['parent' => 0, 'fields' => 'ids']);
            $all_top_level_ids = get_comments($top_level_args);

            if (empty($all_top_level_ids)) {
                $comments = [];
                break;
            }

            $likes_map = [];
            foreach ($all_top_level_ids as $comment_id) {
                $thread_likes = get_comment_meta($comment_id, '_gta6_comment_thread_like_count', true);
                if ($thread_likes === '') {
                    gta6_mods_update_comment_thread_likes($comment_id);
                    $thread_likes = get_comment_meta($comment_id, '_gta6_comment_thread_like_count', true);
                }
                $likes_map[$comment_id] = $thread_likes ? (int) $thread_likes : 0;
            }

            arsort($likes_map, SORT_NUMERIC);
            $sorted_ids = array_keys($likes_map);

            if (!empty($sorted_ids)) {
                if ($pinned_comment_id > 0) {
                    $sorted_ids = array_values(array_diff($sorted_ids, [$pinned_comment_id]));
                    array_unshift($sorted_ids, $pinned_comment_id);
                }

                $all_comment_ids_for_post = get_comments(array_merge($base_args, ['fields' => 'ids']));
                $child_comment_ids = array_diff($all_comment_ids_for_post, $sorted_ids);
                $final_ids_in_order = array_merge($sorted_ids, $child_comment_ids);

                $comments = get_comments([
                    'comment__in' => $final_ids_in_order,
                    'orderby'     => 'comment__in',
                ]);
            } else {
                $comments = [];
            }
            break;
    }

    if (!empty($comments) && $pinned_comment_id > 0 && function_exists('gta6mods_prioritize_pinned_comment')) {
        $comments = gta6mods_prioritize_pinned_comment($comments, $pinned_comment_id);
    }

    if (empty($comments)) {
        wp_send_json_success(['html' => '<p id="gta6-no-comments" class="text-sm text-gray-500">' . esc_html__('No comments yet. Be the first to share your thoughts!', 'gta6-mods') . '</p>']);
    }

    ob_start();
    $max_depth = gta6mods_get_comment_max_depth($post_id);

    wp_list_comments([
        'style'       => 'div',
        'short_ping'  => false,
        'avatar_size' => 40,
        'walker'      => new GTA6_Mods_Comment_Walker([
            'pinned_comment_id' => $pinned_comment_id,
            'max_depth'         => $max_depth,
        ]),
        'max_depth'   => $max_depth,
        'echo'        => true,
    ], $comments);
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_gta6_sort_comments', 'gta6_mods_ajax_sort_comments');
add_action('wp_ajax_nopriv_gta6_sort_comments', 'gta6_mods_ajax_sort_comments');

function gta6_mods_validate_password_strength($password) {
    if (!is_string($password)) {
        return false;
    }

    $length = function_exists('mb_strlen') ? mb_strlen($password, '8bit') : strlen($password);
    if ($length < 12) {
        return false;
    }

    $has_lower   = (bool) preg_match('/[a-z]/', $password);
    $has_upper   = (bool) preg_match('/[A-Z]/', $password);
    $has_number  = (bool) preg_match('/\d/', $password);
    $has_special = (bool) preg_match('/[^\da-zA-Z]/', $password);

    return $has_lower && $has_upper && $has_number && $has_special;
}

function gta6_mods_ajax_change_password() {
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => __('Authentication required.', 'gta6-mods'),
        ], 403);
    }

    check_ajax_referer('gta6mods_change_password', 'nonce');

    $current_password = isset($_POST['current_password']) ? (string) wp_unslash($_POST['current_password']) : '';
    $new_password     = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';

    if ('' === $current_password || '' === $new_password || '' === $confirm_password) {
        wp_send_json_error([
            'message' => __('Please fill in all password fields.', 'gta6-mods'),
        ], 400);
    }

    if ($new_password !== $confirm_password) {
        wp_send_json_error([
            'message' => __('The new passwords do not match.', 'gta6-mods'),
        ], 400);
    }

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();

    if (!$user instanceof WP_User || (int) $user->ID !== $user_id) {
        wp_send_json_error([
            'message' => __('Unable to load your account.', 'gta6-mods'),
        ], 400);
    }

    $attempt_key      = 'gta6mods_password_attempts_' . $user_id;
    $attempt_limit    = 5;
    $lockout_duration = 15 * MINUTE_IN_SECONDS;
    $attempt_data     = get_transient($attempt_key);
    $attempt_count    = 0;

    if (is_array($attempt_data) && isset($attempt_data['count'])) {
        $attempt_count = (int) $attempt_data['count'];
    }

    if ($attempt_count >= $attempt_limit) {
        wp_send_json_error([
            'message' => __('Too many password change attempts. Please wait before trying again.', 'gta6-mods'),
        ], 429);
    }

    if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
        $attempt_count += 1;
        set_transient($attempt_key, ['count' => $attempt_count], $lockout_duration);
        wp_send_json_error([
            'message' => __('Your current password was incorrect.', 'gta6-mods'),
        ], 400);
    }

    if (wp_check_password($new_password, $user->user_pass, $user_id)) {
        wp_send_json_error([
            'message' => __('The new password must be different from your current password.', 'gta6-mods'),
        ], 400);
    }

    if (!gta6_mods_validate_password_strength($new_password)) {
        wp_send_json_error([
            'message' => __('Your new password does not meet the strength requirements.', 'gta6-mods'),
        ], 400);
    }

    $update_result = wp_update_user([
        'ID'        => $user_id,
        'user_pass' => $new_password,
    ]);

    if (is_wp_error($update_result)) {
        wp_send_json_error([
            'message' => __('We could not update your password. Please try again.', 'gta6-mods'),
        ], 500);
    }

    delete_transient($attempt_key);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    wp_send_json_success([
        'message' => __('Password updated successfully.', 'gta6-mods'),
        'nonce'   => wp_create_nonce('gta6mods_change_password'),
    ]);
}
add_action('wp_ajax_gta6mods_change_password', 'gta6_mods_ajax_change_password');

function gta6_mods_get_account_deletion_data($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }

    $data = get_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_META_KEY, true);
    if (!is_array($data)) {
        return null;
    }

    $status        = isset($data['status']) ? sanitize_key($data['status']) : '';
    $requested_at  = isset($data['requested_at']) ? (int) $data['requested_at'] : 0;
    $scheduled_for = isset($data['scheduled_for']) ? (int) $data['scheduled_for'] : 0;
    $finalized_at  = isset($data['finalized_at']) ? (int) $data['finalized_at'] : 0;
    $method        = isset($data['method']) ? sanitize_key($data['method']) : '';

    if ('pending' === $status) {
        if ($requested_at <= 0) {
            return null;
        }

        if ($scheduled_for < 0) {
            $scheduled_for = 0;
        }
    } elseif ('deleted' === $status) {
        if ($requested_at <= 0) {
            $requested_at = 0;
        }

        if ($finalized_at <= 0) {
            $finalized_at = $scheduled_for > 0 ? $scheduled_for : current_time('timestamp');
        }
    } else {
        return null;
    }

    return [
        'status'        => $status,
        'requested_at'  => $requested_at,
        'scheduled_for' => $scheduled_for,
        'finalized_at'  => $finalized_at,
        'method'        => $method,
    ];
}

function gta6_mods_prepare_account_deletion_payload($user_id, $data = null) {
    if (null === $data) {
        $data = gta6_mods_get_account_deletion_data($user_id);
    }

    if (!is_array($data)) {
        return [
            'requested'       => false,
            'requested_at'    => 0,
            'scheduled_for'   => 0,
            'scheduled_label' => '',
            'finalized'       => false,
            'finalized_at'    => 0,
            'status'          => '',
        ];
    }

    $status = isset($data['status']) ? sanitize_key($data['status']) : '';

    if ('deleted' === $status) {
        $finalized_at = isset($data['finalized_at']) ? (int) $data['finalized_at'] : 0;

        return [
            'requested'       => false,
            'requested_at'    => isset($data['requested_at']) ? (int) $data['requested_at'] : 0,
            'scheduled_for'   => isset($data['scheduled_for']) ? (int) $data['scheduled_for'] : 0,
            'scheduled_label' => '',
            'finalized'       => true,
            'finalized_at'    => $finalized_at,
            'status'          => 'deleted',
        ];
    }

    return [
        'requested'       => true,
        'requested_at'    => isset($data['requested_at']) ? (int) $data['requested_at'] : 0,
        'scheduled_for'   => isset($data['scheduled_for']) ? (int) $data['scheduled_for'] : 0,
        'scheduled_label' => '',
        'finalized'       => false,
        'finalized_at'    => 0,
        'status'          => 'pending',
    ];
}

function gta6_mods_mark_account_as_deleted($user_id, $method = 'scheduled', $existing = null) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    if (null === $existing) {
        $existing = gta6_mods_get_account_deletion_data($user_id);
    }

    $now = current_time('timestamp');

    $requested_at  = is_array($existing) && isset($existing['requested_at']) ? (int) $existing['requested_at'] : $now;
    $scheduled_for = is_array($existing) && isset($existing['scheduled_for']) ? (int) $existing['scheduled_for'] : $now;

    if ($scheduled_for <= 0) {
        $scheduled_for = $now;
    }

    $method_key = 'scheduled';
    if ('immediate' === $method) {
        $method_key = 'immediate';
    } elseif ('moderated' === $method || 'manual' === $method) {
        $method_key = 'moderated';
    }

    $payload = [
        'status'        => 'deleted',
        'requested_at'  => $requested_at,
        'scheduled_for' => $scheduled_for,
        'finalized_at'  => $now,
        'method'        => $method_key,
    ];

    update_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_META_KEY, $payload);

    if (function_exists('gta6mods_make_user_content_inaccessible')) {
        gta6mods_make_user_content_inaccessible($user_id);
    }

    return $payload;
}

function gta6_mods_ajax_request_account_deletion() {
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => __('Authentication required.', 'gta6-mods'),
        ], 403);
    }

    check_ajax_referer('gta6mods_request_account_deletion', 'nonce');

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();

    if (!$user instanceof WP_User || (int) $user->ID !== $user_id) {
        wp_send_json_error([
            'message' => __('Unable to load your account.', 'gta6-mods'),
        ], 400);
    }

    if (!gta6mods_user_can_self_schedule_account_deletion($user)) {
        wp_send_json_error([
            'message' => __('This action is not available for your account.', 'gta6-mods'),
        ], 403);
    }

    $existing = gta6_mods_get_account_deletion_data($user_id);
    if (is_array($existing)) {
        wp_send_json_error([
            'message' => __('You already have a pending account deletion request.', 'gta6-mods'),
        ], 400);
    }

    $confirmation = isset($_POST['confirmation']) ? (string) wp_unslash($_POST['confirmation']) : '';
    $required     = 'Delete my account';

    if ($confirmation !== $required) {
        wp_send_json_error([
            'message' => __('The confirmation phrase does not match.', 'gta6-mods'),
        ], 400);
    }

    $payload = [
        'status'        => 'pending',
        'requested_at'  => current_time('timestamp'),
        'scheduled_for' => 0,
        'finalized_at'  => 0,
        'method'        => 'requested',
    ];

    update_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_META_KEY, $payload);

    $response = gta6_mods_prepare_account_deletion_payload($user_id, $payload);

    wp_send_json_success([
        'message' => __('Account deletion request received. A moderator will review it soon.', 'gta6-mods'),
        'deletion' => $response,
        'nonces'  => [
            'request'   => wp_create_nonce('gta6mods_request_account_deletion'),
            'cancel'    => wp_create_nonce('gta6mods_cancel_account_deletion'),
            'deleteNow' => wp_create_nonce('gta6mods_delete_account_now'),
        ],
    ]);
}
add_action('wp_ajax_gta6mods_request_account_deletion', 'gta6_mods_ajax_request_account_deletion');

function gta6_mods_ajax_cancel_account_deletion() {
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => __('Authentication required.', 'gta6-mods'),
        ], 403);
    }

    check_ajax_referer('gta6mods_cancel_account_deletion', 'nonce');

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();

    if (!$user instanceof WP_User || (int) $user->ID !== $user_id) {
        wp_send_json_error([
            'message' => __('Unable to load your account.', 'gta6-mods'),
        ], 400);
    }

    if (!gta6mods_user_can_self_schedule_account_deletion($user)) {
        wp_send_json_error([
            'message' => __('This action is not available for your account.', 'gta6-mods'),
        ], 403);
    }

    $existing = gta6_mods_get_account_deletion_data($user_id);
    if (!is_array($existing) || !isset($existing['status']) || 'pending' !== $existing['status']) {
        wp_send_json_error([
            'message' => __('There is no pending account deletion request to cancel.', 'gta6-mods'),
        ], 400);
    }

    delete_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_META_KEY);

    wp_send_json_success([
        'message' => __('Account deletion request cancelled.', 'gta6-mods'),
        'deletion' => gta6_mods_prepare_account_deletion_payload($user_id, null),
        'nonces'  => [
            'request'   => wp_create_nonce('gta6mods_request_account_deletion'),
            'cancel'    => wp_create_nonce('gta6mods_cancel_account_deletion'),
            'deleteNow' => wp_create_nonce('gta6mods_delete_account_now'),
        ],
    ]);
}
add_action('wp_ajax_gta6mods_cancel_account_deletion', 'gta6_mods_ajax_cancel_account_deletion');

function gta6_mods_ajax_delete_account_now() {
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => __('Authentication required.', 'gta6-mods'),
        ], 403);
    }

    check_ajax_referer('gta6mods_delete_account_now', 'nonce');

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();

    if (!$user instanceof WP_User || (int) $user->ID !== $user_id) {
        wp_send_json_error([
            'message' => __('Unable to load your account.', 'gta6-mods'),
        ], 400);
    }

    if (!gta6mods_user_can_self_schedule_account_deletion($user)) {
        wp_send_json_error([
            'message' => __('This action is not available for your account.', 'gta6-mods'),
        ], 403);
    }

    $existing = gta6_mods_get_account_deletion_data($user_id);
    if (!is_array($existing) || !isset($existing['status']) || 'pending' !== $existing['status']) {
        wp_send_json_error([
            'message' => __('No pending account deletion request was found.', 'gta6-mods'),
        ], 400);
    }

    $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
    if ('' === $password) {
        wp_send_json_error([
            'message' => __('Please enter your password to continue.', 'gta6-mods'),
        ], 400);
    }

    if (!wp_check_password($password, $user->user_pass, $user_id)) {
        wp_send_json_error([
            'message' => __('Your password was incorrect.', 'gta6-mods'),
        ], 400);
    }

    $payload = gta6_mods_mark_account_as_deleted($user_id, 'immediate', $existing);

    if (function_exists('wp_destroy_user_sessions')) {
        wp_destroy_user_sessions($user_id);
    }

    wp_logout();

    $redirect_delay = 5;
    $countdown_label = sprintf(
        _n('%s second', '%s seconds', $redirect_delay, 'gta6-mods'),
        number_format_i18n($redirect_delay)
    );

    wp_send_json_success([
        'message' => sprintf(
            __('Your account has been deleted. You will be signed out in %s.', 'gta6-mods'),
            $countdown_label
        ),
        'redirect' => home_url('/'),
        'redirect_delay' => $redirect_delay,
        'nonces'  => [
            'request'   => wp_create_nonce('gta6mods_request_account_deletion'),
            'cancel'    => wp_create_nonce('gta6mods_cancel_account_deletion'),
            'deleteNow' => wp_create_nonce('gta6mods_delete_account_now'),
        ],
        'deletion' => gta6_mods_prepare_account_deletion_payload($user_id, $payload),
    ]);
}
add_action('wp_ajax_gta6mods_delete_account_now', 'gta6_mods_ajax_delete_account_now');

