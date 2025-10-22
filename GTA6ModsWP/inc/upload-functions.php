<?php
/**
 * Mod Upload related functions for the GTA6 Mods theme.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allow mod authors to satisfy edit_post capability checks for their own submissions.
 *
 * @param array  $caps    Required primitive caps.
 * @param string $cap     Capability being checked.
 * @param int    $user_id User ID.
 * @param array  $args    Additional arguments, first is usually the post ID.
 *
 * @return array
 */
function gta6mods_allow_authors_to_edit_mods($caps, $cap, $user_id, $args) {
    if ('edit_post' !== $cap || empty($args[0])) {
        return $caps;
    }

    $post = get_post((int) $args[0]);

    if (!$post instanceof WP_Post) {
        return $caps;
    }

    if ('post' === $post->post_type && (int) $post->post_author === (int) $user_id) {
        return ['exist'];
    }

    return $caps;
}
add_filter('map_meta_cap', 'gta6mods_allow_authors_to_edit_mods', 20, 4);

/**
 * Allow logged in users to upload files through the dedicated frontend flows
 * even if their role (e.g. subscriber) lacks the upload_files capability.
 *
 * @param array   $allcaps All the capabilities of the user.
 * @param array   $caps    Actual capabilities being checked.
 * @param array   $args    Arguments passed to capability check.
 * @param WP_User $user    The user object.
 *
 * @return array
 */
function gta6mods_allow_frontend_mod_upload_capabilities($allcaps, $caps, $args, $user) {
    if (!($user instanceof WP_User) || !$user->exists()) {
        return $allcaps;
    }

    if (!is_user_logged_in()) {
        return $allcaps;
    }

    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        return $allcaps;
    }

    $action = isset($_REQUEST['action']) ? wp_unslash($_REQUEST['action']) : '';
    $action = is_string($action) ? sanitize_key($action) : '';

    $allowed_actions = ['gta6mods_submit_mod_upload', 'gta6mods_submit_mod_update'];

    if ($action && in_array($action, $allowed_actions, true)) {
        $allcaps['upload_files'] = true;
    }

    return $allcaps;
}
add_filter('user_has_cap', 'gta6mods_allow_frontend_mod_upload_capabilities', 10, 4);

/**
 * Allow archive mime types for uploads.
 */
function gta6mods_allow_archive_mime_types($mimes) {
    $mimes['zip'] = 'application/zip';
    $mimes['rar'] = 'application/vnd.rar|application/x-rar-compressed|application/octet-stream';
    $mimes['7z']  = 'application/x-7z-compressed';
    $mimes['oiv'] = 'application/x-oiv';

    return $mimes;
}
add_filter('upload_mimes', 'gta6mods_allow_archive_mime_types');

/**
 * Fix validation for archive uploads.
 */
function gta6mods_fix_archive_upload_validation($data, $file, $filename, $mimes) {
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $allowed_archives = [
        'rar' => 'application/x-rar-compressed',
        '7z'  => 'application/x-7z-compressed',
        'oiv' => 'application/octet-stream'
    ];
    
    // If the extension is on our list but WordPress couldn't identify its type,
    // we manually set the correct values.
    if (array_key_exists($file_extension, $allowed_archives) && (empty($data['type']) || empty($data['ext']))) {
        $data['ext']  = $file_extension;
        $data['type'] = $allowed_archives[$file_extension];
    }

    return $data;
}
add_filter('wp_check_filetype_and_ext', 'gta6mods_fix_archive_upload_validation', 10, 4);

/**
 * Handles the heavy lifting for creating a pending mod submission.
 *
 * @param array   $request      Request payload similar to $_POST.
 * @param array   $files        Uploaded file payload similar to $_FILES.
 * @param WP_User $current_user Current user context.
 *
 * @return array|WP_Error
 */
function gta6mods_handle_mod_submission_request(array $request, array $files, WP_User $current_user)
{
    $title                = isset($request['file_name']) ? sanitize_text_field(wp_unslash($request['file_name'])) : '';
    $category_id          = isset($request['category_id']) ? absint($request['category_id']) : 0;
    $version              = isset($request['version']) ? sanitize_text_field(wp_unslash($request['version'])) : '';
    $description_json_raw = isset($request['description']) ? wp_unslash($request['description']) : '';
    $tags_raw             = isset($request['tags']) ? sanitize_text_field(wp_unslash($request['tags'])) : '';
    $video_permission     = isset($request['video_permissions']) ? sanitize_key(wp_unslash($request['video_permissions'])) : 'moderate';
    $featured_index       = isset($request['featured_index']) ? absint($request['featured_index']) : 0;
    $external_url_raw     = isset($request['mod_external_url']) ? wp_unslash($request['mod_external_url']) : '';
    $external_url_raw     = is_string($external_url_raw) ? trim($external_url_raw) : '';
    $external_url         = $external_url_raw !== '' ? esc_url_raw($external_url_raw) : '';
    $external_size_value  = isset($request['mod_external_size_value']) ? wp_unslash($request['mod_external_size_value']) : '';
    $external_size_unit   = isset($request['mod_external_size_unit']) ? wp_unslash($request['mod_external_size_unit']) : '';
    $external_size_unit   = strtoupper(sanitize_text_field($external_size_unit));

    if ('' === $title) {
        return new WP_Error('gta6mods_mod_upload_missing_title', __('The "File Name" field is required.', 'gta6-mods'), [ 'status' => 400 ]);
    }

    $allowed_category_slugs = ['tools', 'vehicles', 'paint-jobs', 'weapons', 'scripts', 'player', 'maps', 'misc'];
    $category_term          = $category_id ? get_term($category_id, 'category') : null;
    if (!$category_term || is_wp_error($category_term) || !in_array($category_term->slug, $allowed_category_slugs, true)) {
        return new WP_Error('gta6mods_mod_upload_invalid_category', __('Please select a valid category.', 'gta6-mods'), [ 'status' => 400 ]);
    }

    if ('' === $version) {
        return new WP_Error('gta6mods_mod_upload_missing_version', __('The "Version" field is required.', 'gta6-mods'), [ 'status' => 400 ]);
    }

    $description_data = json_decode($description_json_raw, true);
    if (empty($description_json_raw) || !is_array($description_data) || !isset($description_data['blocks']) || empty($description_data['blocks'])) {
        return new WP_Error('gta6mods_mod_upload_invalid_description', __('The description is empty or invalid.', 'gta6-mods'), [ 'status' => 400 ]);
    }

    $gutenberg_content = gta6_mods_editorjs_to_gutenberg_blocks($description_json_raw);

    $mod_file            = isset($files['mod_file']) ? $files['mod_file'] : null;
    $has_mod_file        = $mod_file && !empty($mod_file['name']);
    $has_external_source = $external_url !== '';
    $external_source_meta = null;

    if ($has_mod_file && $has_external_source) {
        return new WP_Error('gta6mods_mod_upload_conflict_source', __('Please choose either a file upload or an external download link, not both.', 'gta6-mods'), [ 'status' => 400 ]);
    }

    if ($has_external_source) {
        $validated_url   = wp_http_validate_url($external_url);
        $parsed_url      = wp_parse_url($external_url);
        $allowed_schemes = ['http', 'https'];

        if (!$validated_url || empty($parsed_url['scheme']) || !in_array(strtolower($parsed_url['scheme']), $allowed_schemes, true)) {
            return new WP_Error('gta6mods_mod_upload_invalid_url', __('Please provide a valid download URL.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        $size_value_string = is_string($external_size_value) ? trim(str_replace(',', '.', $external_size_value)) : '';
        $size_value        = (float) $size_value_string;
        if ($size_value <= 0) {
            return new WP_Error('gta6mods_mod_upload_invalid_size', __('Please enter a valid file size.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        $size_unit  = in_array($external_size_unit, ['MB', 'GB'], true) ? $external_size_unit : 'MB';
        $multiplier = 'GB' === $size_unit ? 1024 * 1024 * 1024 : 1024 * 1024;
        $size_bytes = (int) round($size_value * $multiplier);
        $size_label = $size_value === (float) (int) $size_value
            ? (string) (int) $size_value
            : rtrim(rtrim(number_format($size_value, 2, '.', ''), '0'), '.');

        $external_source_meta = [
            'url'        => $external_url,
            'size_bytes' => $size_bytes,
            'size_value' => $size_value,
            'size_unit'  => $size_unit,
            'size_human' => sprintf('%s %s', $size_label, $size_unit),
        ];
    }

    if (!$has_mod_file && !$has_external_source) {
        return new WP_Error('gta6mods_mod_upload_missing_source', __('Please attach a mod file or provide a download URL.', 'gta6-mods'), [ 'status' => 400 ]);
    }

    if (!isset($files['screenshots']) || empty($files['screenshots']['name']) || !is_array($files['screenshots']['name'])) {
        return new WP_Error('gta6mods_mod_upload_missing_screenshots', __('Please upload at least one screenshot.', 'gta6-mods'), [ 'status' => 400 ]);
    }

    $tags = array_filter(array_map('trim', explode(',', $tags_raw)));

    $additional_authors = [];
    if (!empty($request['additional_authors']) && is_array($request['additional_authors'])) {
        foreach ($request['additional_authors'] as $author_name) {
            $author_name = sanitize_text_field(wp_unslash($author_name));
            if ($author_name !== '') {
                $additional_authors[] = $author_name;
            }
        }
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $uploaded_screenshots  = [];
    $queued_screenshot_ids = [];
    $screenshot_files      = [];

    foreach ($files['screenshots']['name'] as $index => $name) {
        $screenshot_files[] = [
            'name'     => $name,
            'type'     => $files['screenshots']['type'][$index],
            'tmp_name' => $files['screenshots']['tmp_name'][$index],
            'error'    => $files['screenshots']['error'][$index],
            'size'     => $files['screenshots']['size'][$index],
        ];
    }

    if (empty($screenshot_files)) {
        return new WP_Error('gta6mods_mod_upload_no_screenshots', __('Please upload at least one screenshot.', 'gta6-mods'), [ 'status' => 400 ]);
    }

    $allowed_screenshot_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    $allowed_screenshot_exts  = ['jpg', 'jpeg', 'png', 'webp'];

    foreach ($screenshot_files as $file) {
        if (!empty($file['error'])) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_screenshot_error', __('One of the screenshots could not be processed.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_screenshot_too_large', __('Screenshots must be smaller than 10MB.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        $file_type      = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $is_allowed_type = !empty($file_type['type']) && in_array($file_type['type'], $allowed_screenshot_mimes, true);
        $is_allowed_ext  = !empty($file_type['ext']) && in_array(strtolower($file_type['ext']), $allowed_screenshot_exts, true);

        if (!$is_allowed_type || !$is_allowed_ext) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_invalid_screenshot', __('Only JPG, PNG, and WEBP screenshots are allowed.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);
        if (isset($upload['error'])) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_screenshot_upload_failed', sprintf(__('Screenshot upload failed: %s', 'gta6-mods'), $upload['error']), [ 'status' => 500 ]);
        }

        $attachment_id = wp_insert_attachment(
            [
                'post_mime_type' => $upload['type'],
                'post_title'     => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_author'    => $current_user->ID,
            ],
            $upload['file']
        );

        if (is_wp_error($attachment_id)) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_screenshot_save_failed', __('Unable to save screenshot.', 'gta6-mods'), [ 'status' => 500 ]);
        }

        $uploaded_screenshots[]  = [
            'id'  => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
        ];
        $queued_screenshot_ids[] = (int) $attachment_id;
    }

    $mod_upload        = [];
    $mod_attachment_id = 0;

    if ($has_mod_file) {
        if (!empty($mod_file['error'])) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_file_error', __('The mod file could not be uploaded.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        if ($mod_file['size'] > 400 * 1024 * 1024) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_file_too_large', __('The mod file must be smaller than 400MB.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        $mod_extension         = strtolower(pathinfo($mod_file['name'], PATHINFO_EXTENSION));
        $allowed_mod_extensions = ['zip', 'rar', '7z', 'oiv'];
        if (!in_array($mod_extension, $allowed_mod_extensions, true)) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_invalid_extension', __('Invalid mod file type.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        $file_info        = wp_check_filetype_and_ext($mod_file['tmp_name'], $mod_file['name']);
        $allowed_mod_mimes = [
            'application/zip',
            'application/x-zip-compressed',
            'application/x-rar-compressed',
            'application/vnd.rar',
            'application/x-7z-compressed',
            'application/octet-stream',
        ];

        if (empty($file_info['type']) || !in_array($file_info['type'], $allowed_mod_mimes, true)) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_invalid_mime', __('Invalid mod file MIME type detected.', 'gta6-mods'), [ 'status' => 400 ]);
        }

        $mod_upload = wp_handle_upload($mod_file, ['test_form' => false]);
        if (isset($mod_upload['error'])) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            return new WP_Error('gta6mods_mod_upload_file_failed', sprintf(__('Mod file upload failed: %s', 'gta6-mods'), $mod_upload['error']), [ 'status' => 500 ]);
        }

        $mod_attachment_id = wp_insert_attachment(
            [
                'post_mime_type' => $mod_upload['type'],
                'post_title'     => sanitize_file_name(pathinfo($mod_file['name'], PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_author'    => $current_user->ID,
            ],
            $mod_upload['file']
        );

        if (is_wp_error($mod_attachment_id)) {
            gta6mods_cleanup_attachments($uploaded_screenshots);
            if (!empty($mod_upload['file'])) {
                wp_delete_file($mod_upload['file']);
            }

            return new WP_Error('gta6mods_mod_upload_file_save_failed', __('Unable to save the mod file.', 'gta6-mods'), [ 'status' => 500 ]);
        }
    }

    $post_id = wp_insert_post(
        [
            'post_type'    => 'post',
            'post_title'   => $title,
            'post_content' => $gutenberg_content,
            'post_status'  => 'pending',
            'post_author'  => get_current_user_id(),
        ],
        true
    );

    if (is_wp_error($post_id)) {
        gta6mods_cleanup_attachments($uploaded_screenshots);
        if ($mod_attachment_id) {
            wp_delete_attachment($mod_attachment_id, true);
        }

        return new WP_Error('gta6mods_mod_upload_post_failed', __('Unable to create the pending post.', 'gta6-mods'), [ 'status' => 500 ]);
    }

    gta6_mods_store_editorjs_payload($post_id, $description_json_raw);

    wp_set_post_categories($post_id, [$category_id]);

    if (!empty($tags)) {
        wp_set_post_terms($post_id, $tags, 'post_tag', false);
    }

    if (!empty($additional_authors)) {
        update_post_meta($post_id, '_gta6mods_additional_authors', $additional_authors);
    } else {
        delete_post_meta($post_id, '_gta6mods_additional_authors');
    }

    update_post_meta($post_id, '_gta6mods_video_permissions', $video_permission);
    update_post_meta($post_id, '_gta6mods_mod_version', $version);

    if ($has_mod_file && $mod_attachment_id) {
        $mod_file_size = isset($mod_file['size']) ? (int) $mod_file['size'] : 0;
        if ($mod_file_size <= 0 && !empty($mod_upload['file']) && file_exists($mod_upload['file'])) {
            $mod_file_size = (int) filesize($mod_upload['file']);
        }

        update_post_meta($post_id, '_gta6mods_mod_file', [
            'id'            => $mod_attachment_id,
            'attachment_id' => $mod_attachment_id,
            'url'           => wp_get_attachment_url($mod_attachment_id),
            'size_bytes'    => $mod_file_size,
            'size_human'    => size_format((float) $mod_file_size),
        ]);
        delete_post_meta($post_id, '_gta6mods_mod_external');
        gta6mods_invalidate_external_waiting_room_cache($post_id, $post_id, 'mod');
    } elseif ($external_source_meta) {
        delete_post_meta($post_id, '_gta6mods_mod_file');
        update_post_meta($post_id, '_gta6mods_mod_external', $external_source_meta);
        gta6mods_invalidate_external_waiting_room_cache($post_id, $post_id, 'mod');
    }

    $gallery_meta = [];
    foreach ($uploaded_screenshots as $index => $data) {
        $attachment_id = isset($data['id']) ? (int) $data['id'] : 0;
        if ($attachment_id <= 0) {
            continue;
        }

        wp_update_post([
            'ID'          => $attachment_id,
            'post_parent' => $post_id,
        ]);

        $gallery_meta[] = [
            'attachment_id' => $attachment_id,
            'order'         => (int) $index,
        ];
    }

    $valid_index = ($featured_index >= 0 && isset($uploaded_screenshots[$featured_index])) ? $featured_index : 0;
    if (!empty($uploaded_screenshots[$valid_index]['id'])) {
        set_post_thumbnail($post_id, $uploaded_screenshots[$valid_index]['id']);
    }

    if (!empty($gallery_meta)) {
        update_post_meta($post_id, '_gta6mods_gallery_images', wp_json_encode($gallery_meta));
    } else {
        delete_post_meta($post_id, '_gta6mods_gallery_images');
    }

    if ($mod_attachment_id) {
        wp_update_post([
            'ID'          => $mod_attachment_id,
            'post_parent' => $post_id,
        ]);
    }

    if ($has_mod_file && $mod_attachment_id) {
        $existing_version = GTA6Mods_Mod_Versions::get_latest_version($post_id);
        if (!$existing_version) {
            GTA6Mods_Mod_Versions::insert_version($post_id, $version, $mod_attachment_id, '', null, true);
        }
    }

    if (function_exists('gta6mods_ensure_initial_version_exists')) {
        gta6mods_ensure_initial_version_exists($post_id);
    }

    $redirect_url = get_preview_post_link($post_id);
    if (!$redirect_url) {
        $redirect_url = get_permalink($post_id);
    }

    if (!empty($queued_screenshot_ids)) {
        gta6mods_queue_attachment_metadata_generation($queued_screenshot_ids);
    }

    return [
        'redirect_url' => $redirect_url,
        'post_id'      => $post_id,
        'status'       => 'pending',
    ];
}

/**
 * AJAX handler for submitting a new mod.
 */
function gta6mods_submit_mod_upload()
{
    if (!is_user_logged_in()) {
        wp_send_json_error([
            'message' => __('You must be logged in to submit a mod.', 'gta6-mods'),
        ], 403);
    }

    check_ajax_referer('gta6mods_mod_upload', 'nonce');

    if (!current_user_can('upload_files')) {
        wp_send_json_error([
            'message' => __('You do not have permission to upload files.', 'gta6-mods'),
        ], 403);
    }

    $current_user = wp_get_current_user();

    $result = gta6mods_handle_mod_submission_request($_POST, $_FILES, $current_user);

    if (is_wp_error($result)) {
        $error_data = $result->get_error_data();
        $status     = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 400;

        wp_send_json_error([
            'message' => $result->get_error_message(),
        ], $status);
    }

    wp_send_json_success($result);
}
add_action('wp_ajax_gta6mods_submit_mod_upload', 'gta6mods_submit_mod_upload');

/**
 * Queue attachment metadata generation so large image processing happens asynchronously.
 *
 * @param array $attachment_ids List of attachment IDs to process.
 */
function gta6mods_queue_attachment_metadata_generation(array $attachment_ids) {
    if (empty($attachment_ids)) {
        return;
    }

    foreach ($attachment_ids as $attachment_id) {
        $attachment_id = (int) $attachment_id;

        if ($attachment_id <= 0) {
            continue;
        }

        if (!wp_next_scheduled('gta6mods_process_attachment_metadata', [$attachment_id])) {
            wp_schedule_single_event(time() + 5, 'gta6mods_process_attachment_metadata', [$attachment_id]);
        }
    }
}

/**
 * Background worker that generates attachment metadata for queued uploads.
 *
 * @param int $attachment_id Attachment ID.
 */
function gta6mods_process_attachment_metadata($attachment_id) {
    $attachment_id = (int) $attachment_id;

    if ($attachment_id <= 0) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file_path = get_attached_file($attachment_id);

    if (!$file_path || !file_exists($file_path)) {
        return;
    }

    $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);

    if (!empty($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }
}
add_action('gta6mods_process_attachment_metadata', 'gta6mods_process_attachment_metadata');

/**
 * Clean up attachments if the upload process fails.
 */
function gta6mods_cleanup_attachments($attachments) {
    if (empty($attachments)) {
        return;
    }

    foreach ($attachments as $attachment) {
        if (empty($attachment['id'])) {
            continue;
        }

        $attachment_id = (int) $attachment['id'];

        if ($attachment_id <= 0) {
            continue;
        }

        wp_clear_scheduled_hook('gta6mods_process_attachment_metadata', [$attachment_id]);
        wp_delete_attachment($attachment_id, true);
    }
}

