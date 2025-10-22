<?php
if (!defined('ABSPATH')) { exit; }

function gta6_mods_report_reasons() {
    return [
        'spam'       => __('Spam', 'gta6-mods'),
        'harassment' => __('Harassment', 'gta6-mods'),
        'law'        => __('Violation of law', 'gta6-mods'),
        'content'    => __('Pornographic content', 'gta6-mods'),
        'violence'   => __('Violence', 'gta6-mods'),
        'copyright'  => __('Copyright', 'gta6-mods'),
        'misleading' => __('Misleading', 'gta6-mods'),
        'other'      => __('Other', 'gta6-mods'),
    ];
}

function gta6_mods_enqueue_comment_report_assets() {
    if (!is_singular() || !comments_open()) { return; }
    wp_enqueue_script('gta6-mods-tailwind', 'https://cdn.tailwindcss.com', [], null, false);
    wp_add_inline_script('gta6-mods-tailwind', 'tailwind.config = { corePlugins: { preflight: false } };', 'before');
    $script_path = get_template_directory() . '/assets/js/comment-report.js';
    if (!file_exists($script_path)) { return; }
    wp_enqueue_script('gta6-mods-comment-report', get_template_directory_uri() . '/assets/js/comment-report.js', [], filemtime($script_path), true);

    $rest_base = trailingslashit(rest_url('gta6mods/v1/comments'));

    wp_localize_script('gta6-mods-comment-report', 'GTA6CommentReport', [
        'restBase'   => esc_url_raw($rest_base),
        'restNonce'  => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
        'isLoggedIn' => is_user_logged_in(),
        'messages'   => [
            'loginRequired' => esc_html__('You must be logged in to report comments.', 'gta6-mods'),
            'genericError'  => esc_html__('Something went wrong. Please try again.', 'gta6-mods'),
        ],
    ]);
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_comment_report_assets', 30);

function gta6_mods_comment_report_inline_styles() {
    if (!is_singular() || !comments_open()) { return; }
    echo '<style id="gta6-comment-report-inline-css">.gta6-report-modal .gta6-form-radio{-webkit-appearance:none;-moz-appearance:none;appearance:none;position:relative;background-color:#fff;border:2px solid #d1d5db;border-radius:9999px;width:1.25rem;height:1.25rem;cursor:pointer;transition:background-color .2s ease,border-color .2s ease}.gta6-report-modal .gta6-form-radio:checked{border-color:#2563eb}.gta6-report-modal .gta6-form-radio:checked::after{content:"";position:absolute;top:50%;left:50%;width:.75rem;height:.75rem;border-radius:9999px;background-color:#2563eb;transform:translate(-50%,-50%)}.gta6-report-modal [data-progress-track]{position:absolute;left:0;right:0;bottom:0;height:.35rem;background-color:#d1fae5;border-bottom-left-radius:.75rem;border-bottom-right-radius:.75rem;overflow:hidden;pointer-events:none}.gta6-report-modal [data-progress-bar]{position:absolute;left:0;top:0;right:0;bottom:0;background-color:#16a34a;transform-origin:left center;transform:scaleX(1);pointer-events:none}</style>';
}
add_action('wp_head', 'gta6_mods_comment_report_inline_styles', 30);

function gta6_mods_render_comment_report_modal() {
    if (!is_singular() || !comments_open()) { return; }
    $reasons = gta6_mods_report_reasons();
    $options = '';
    foreach ($reasons as $key => $label) {
        $options .= '<div class="flex items-center"><input id="gta6-reason-' . esc_attr($key) . '" class="gta6-form-radio" type="radio" name="report_reason" value="' . esc_attr($key) . '"' . checked('spam', $key, false) . ' /><label for="gta6-reason-' . esc_attr($key) . '" class="ml-3 text-gray-800 cursor-pointer">' . esc_html($label) . '</label></div>';
    }
    echo '<div id="gta6-report-overlay" class="gta6-report-modal fixed inset-0 hidden bg-black/60 z-50 flex items-center justify-center p-4"><div id="gta6-report-modal" class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto transform transition duration-200 scale-95 opacity-0 relative overflow-hidden"><div class="flex items-center justify-between p-5 border-b border-gray-200"><h2 class="text-xl font-semibold text-gray-900">' . esc_html__('Report', 'gta6-mods') . '</h2><button type="button" class="text-gray-400 hover:text-gray-600" data-gta6-report-close><span class="sr-only">' . esc_html__('Close', 'gta6-mods') . '</span><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button></div><div class="p-6"><div id="gta6-report-feedback" class="hidden mb-4 text-sm font-medium text-red-600"></div><div id="gta6-report-form-wrapper"><p class="text-gray-600 mb-5">' . esc_html__('Please select a reason', 'gta6-mods') . '</p><form id="gta6-report-form" class="space-y-4">' . $options . '<div id="gta6-report-other" class="hidden pt-2"><label for="gta6-report-details" class="sr-only">' . esc_html__('Other reason', 'gta6-mods') . '</label><textarea id="gta6-report-details" rows="3" class="w-full p-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" placeholder="' . esc_attr__('Please specify...', 'gta6-mods') . '"></textarea></div></form></div><div id="gta6-report-success" class="hidden text-center py-10 px-6"><h3 class="text-2xl font-semibold text-green-600">' . esc_html__('Thank you!', 'gta6-mods') . '</h3><p class="mt-3 text-sm text-gray-600">' . esc_html__('Your report has been submitted. Thank you for helping us maintain the site’s quality!', 'gta6-mods') . '</p></div></div><div id="gta6-report-footer" class="flex justify-end items-center p-5 bg-gray-50 rounded-b-xl"><button type="button" id="gta6-report-submit" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-8 rounded-lg shadow-md transition duration-200">' . esc_html__('Submit', 'gta6-mods') . '</button></div><div data-progress-track class="hidden absolute left-0 right-0 bottom-0 h-1 bg-emerald-100"><span data-progress-bar class="absolute inset-0 bg-green-500"></span></div></div></div>';
}
add_action('wp_footer', 'gta6_mods_render_comment_report_modal');

function gta6_mods_add_report_button_to_comment_menu($items, $comment) {
    if (!($comment instanceof WP_Comment)) { return $items; }
    $current_user_id = get_current_user_id();

    if ($current_user_id > 0 && (int) $comment->user_id === $current_user_id) {
        return str_replace('<!--gta6-report-placeholder-->', '', $items);
    }

    if (function_exists('gta6mods_is_comment_retracted') && gta6mods_is_comment_retracted($comment)) {
        return str_replace('<!--gta6-report-placeholder-->', '', $items);
    }

    $button = sprintf('<button type="button" class="gta6-comment-report-btn flex items-center w-full px-4 py-2 text-red-600 hover:bg-red-50" data-comment-id="%1$d"><i class="fas fa-flag fa-fw mr-2"></i>%2$s</button>', (int) $comment->comment_ID, esc_html__('Report', 'gta6-mods'));
    return str_replace('<!--gta6-report-placeholder-->', $button, $items);
}
add_filter('gta6_mods_comment_menu_items', 'gta6_mods_add_report_button_to_comment_menu', 10, 2);

/**
 * Handles the persistence logic for a comment report.
 *
 * @param int    $comment_id Comment identifier.
 * @param int    $user_id    Reporting user identifier.
 * @param string $reason     Reason key.
 * @param string $details    Optional detail text.
 *
 * @return array|WP_Error
 */
function gta6mods_submit_comment_report($comment_id, $user_id, $reason, $details = '') {
    $comment_id = absint($comment_id);
    $user_id    = absint($user_id);

    if ($user_id <= 0) {
        return new WP_Error('not_logged_in', __('You must be logged in to report comments.', 'gta6-mods'), ['status' => 401]);
    }

    if ($comment_id <= 0) {
        return new WP_Error('invalid_comment', __('Invalid comment.', 'gta6-mods'), ['status' => 400]);
    }

    $comment = get_comment($comment_id);
    if (!$comment instanceof WP_Comment) {
        return new WP_Error('invalid_comment', __('Invalid comment.', 'gta6-mods'), ['status' => 404]);
    }

    $reason  = sanitize_key((string) $reason);
    $details = trim(wp_strip_all_tags((string) $details));
    $reasons = gta6_mods_report_reasons();

    if (!isset($reasons[$reason])) {
        return new WP_Error('invalid_reason', __('Invalid reason.', 'gta6-mods'), ['status' => 400]);
    }

    if ('other' === $reason && '' === $details) {
        return new WP_Error('missing_details', __('Please provide additional details.', 'gta6-mods'), ['status' => 400]);
    }

    $reporters = get_comment_meta($comment_id, '_report_users', true);
    $reporters = is_array($reporters) ? array_map('absint', $reporters) : [];

    if (in_array($user_id, $reporters, true)) {
        return new WP_Error('duplicate_report', __('You have already reported this comment.', 'gta6-mods'), ['status' => 429]);
    }

    $reporters[] = $user_id;
    $reporters   = array_values(array_unique(array_filter($reporters)));

    $entries = get_comment_meta($comment_id, '_report_entries', true);
    $entries = is_array($entries) ? array_values(array_filter($entries, 'is_array')) : [];
    $entries = array_filter($entries, static function ($entry) use ($user_id) {
        if (!is_array($entry)) {
            return false;
        }

        $entry_user = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
        return $entry_user > 0 && $entry_user !== $user_id;
    });

    $entries[] = [
        'user_id' => $user_id,
        'reason'  => $reason,
        'details' => 'other' === $reason ? $details : '',
        'date'    => current_time('mysql'),
    ];

    update_comment_meta($comment_id, '_report_users', $reporters);
    update_comment_meta($comment_id, '_report_entries', array_values($entries));
    update_comment_meta($comment_id, '_report_count', count($reporters));
    update_comment_meta($comment_id, '_last_report_user', $user_id);
    update_comment_meta($comment_id, '_last_report_reason', $reason);
    update_comment_meta($comment_id, '_last_report_details', 'other' === $reason ? $details : '');
    update_comment_meta($comment_id, '_last_report_date', current_time('mysql'));

    return [
        'commentId' => $comment_id,
        'count'     => count($reporters),
        'reason'    => $reasons[$reason],
    ];
}

function gta6_mods_handle_comment_report() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => esc_html__('You must be logged in to report comments.', 'gta6-mods')], 403);
    }

    check_ajax_referer('gta6_mods_comment_report', 'nonce');

    $comment_id = isset($_POST['comment_id']) ? absint($_POST['comment_id']) : 0;
    $reason     = isset($_POST['reason']) ? sanitize_key(wp_unslash($_POST['reason'])) : '';
    $details    = isset($_POST['details']) ? wp_strip_all_tags(wp_unslash($_POST['details'])) : '';

    $result = gta6mods_submit_comment_report($comment_id, get_current_user_id(), $reason, $details);

    if (is_wp_error($result)) {
        $status = $result->get_error_data();
        if (is_array($status) && isset($status['status'])) {
            $status = (int) $status['status'];
        } elseif (!is_int($status)) {
            $status = 400;
        }

        wp_send_json_error(['message' => $result->get_error_message()], $status);
    }

    wp_send_json_success([
        'count'  => isset($result['count']) ? (int) $result['count'] : 0,
        'reason' => isset($result['reason']) ? $result['reason'] : '',
    ]);
}
add_action('wp_ajax_submit_comment_report', 'gta6_mods_handle_comment_report');
add_action('wp_ajax_nopriv_submit_comment_report', 'gta6_mods_handle_comment_report');

function gta6_mods_clear_comment_reports($comment_id) {
    $comment_id = absint($comment_id);

    if ($comment_id <= 0) {
        return false;
    }

    delete_comment_meta($comment_id, '_report_users');
    delete_comment_meta($comment_id, '_report_entries');
    delete_comment_meta($comment_id, '_last_report_user');
    delete_comment_meta($comment_id, '_last_report_reason');
    delete_comment_meta($comment_id, '_last_report_details');
    delete_comment_meta($comment_id, '_last_report_date');
    delete_comment_meta($comment_id, '_report_count');

    return true;
}

function gta6_mods_comment_row_actions($actions, $comment) {
    if (!current_user_can('moderate_comments')) { return $actions; }
    if (!($comment instanceof WP_Comment)) { return $actions; }

    $count = (int) get_comment_meta($comment->comment_ID, '_report_count', true);
    if ($count <= 0) {
        return $actions;
    }

    $redirect = wp_get_referer();
    $context  = isset($_GET['comment_status']) && 'reported' === $_GET['comment_status'] ? 'reported' : '';

    if (!$redirect) {
        $redirect = admin_url('edit-comments.php');
    }

    if ($context && false === strpos($redirect, 'comment_status=')) {
        $redirect = add_query_arg('comment_status', $context, $redirect);
    }

    $args = [
        'action'      => 'gta6mods_clear_comment_report',
        'comment_id'  => (int) $comment->comment_ID,
        'redirect_to' => rawurlencode($redirect),
    ];

    if ($context) {
        $args['context'] = $context;
    }

    $url = add_query_arg($args, admin_url('admin-post.php'));

    $url = wp_nonce_url($url, 'gta6mods_clear_comment_report_' . (int) $comment->comment_ID);

    $actions['gta6mods-clear-report'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($url),
        esc_html__('Reject report', 'gta6-mods')
    );

    return $actions;
}
add_filter('comment_row_actions', 'gta6_mods_comment_row_actions', 10, 2);

function gta6_mods_handle_clear_comment_report_action() {
    if (!current_user_can('moderate_comments')) {
        wp_die(
            esc_html__('You are not allowed to perform this action.', 'gta6-mods'),
            esc_html__('Error', 'gta6-mods'),
            ['response' => 403]
        );
    }

    $comment_id = isset($_GET['comment_id']) ? absint($_GET['comment_id']) : 0;
    check_admin_referer('gta6mods_clear_comment_report_' . $comment_id);

    $result = false;
    if ($comment_id > 0) {
        $result = gta6_mods_clear_comment_reports($comment_id);
    }

    $redirect = isset($_GET['redirect_to']) ? rawurldecode((string) wp_unslash($_GET['redirect_to'])) : '';
    $redirect = $redirect ? wp_validate_redirect($redirect, admin_url('edit-comments.php')) : admin_url('edit-comments.php');

    $context = isset($_GET['context']) ? sanitize_key(wp_unslash($_GET['context'])) : '';
    if ('reported' === $context && false === strpos($redirect, 'comment_status=')) {
        $redirect = add_query_arg('comment_status', 'reported', $redirect);
    }

    $redirect = add_query_arg('gta6mods-cleared-report', $result ? '1' : '0', $redirect);

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_gta6mods_clear_comment_report', 'gta6_mods_handle_clear_comment_report_action');

function gta6_mods_comment_report_admin_notices() {
    if (!is_admin() || !current_user_can('moderate_comments')) { return; }
    if (!isset($_GET['gta6mods-cleared-report'])) { return; }

    $status = '1' === (string) $_GET['gta6mods-cleared-report'];
    $class  = $status ? 'notice-success' : 'notice-error';
    $message = $status
        ? esc_html__('Comment report dismissed.', 'gta6-mods')
        : esc_html__('Unable to dismiss the comment report.', 'gta6-mods');

    printf('<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}
add_action('admin_notices', 'gta6_mods_comment_report_admin_notices');

function gta6_mods_comments_admin_columns($columns) {
    $columns['gta6_reports'] = '🚩 ' . esc_html__('Reports', 'gta6-mods');
    return $columns;
}
add_filter('manage_edit-comments_columns', 'gta6_mods_comments_admin_columns');

function gta6_mods_comments_admin_column_content($column, $comment_id) {
    if ('gta6_reports' !== $column) { return; }
    $count   = (int) get_comment_meta($comment_id, '_report_count', true);
    if ($count <= 0) {
        echo '<span class="text-gray-500">0</span>';
        return;
    }
    $reasons = gta6_mods_report_reasons();
    $entries = get_comment_meta($comment_id, '_report_entries', true);
    $entries = is_array($entries) ? $entries : [];

    $output = sprintf('<span class="text-red-600 font-semibold">%d</span>', $count);

    if (!empty($entries)) {
        $output .= '<ul class="mt-2 space-y-1 text-xs text-gray-700">';

        foreach ($entries as $entry) {
            $user_id = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
            $user    = $user_id ? get_userdata($user_id) : false;
            $name    = $user ? $user->display_name : ($user_id ? sprintf(esc_html__('User #%d', 'gta6-mods'), $user_id) : esc_html__('Unknown user', 'gta6-mods'));

            $reason_key   = isset($entry['reason']) ? (string) $entry['reason'] : '';
            $reason_label = isset($reasons[$reason_key]) ? $reasons[$reason_key] : $reason_key;

            $output .= '<li class="leading-snug">';
            $output .= sprintf('<span class="font-semibold text-gray-900">%s</span><span class="text-gray-600"> — %s</span>', esc_html($name), esc_html($reason_label));

            if ('other' === $reason_key) {
                $details = isset($entry['details']) ? trim((string) $entry['details']) : '';
                if ('' !== $details) {
                    $output .= sprintf('<span class="block text-gray-500">%s</span>', esc_html($details));
                }
            }

            $output .= '</li>';
        }

        $output .= '</ul>';
    } else {
        $reason  = get_comment_meta($comment_id, '_last_report_reason', true);
        $details = trim((string) get_comment_meta($comment_id, '_last_report_details', true));
        $label   = isset($reasons[$reason]) ? $reasons[$reason] : $reason;

        $output .= sprintf('<span class="block text-xs text-gray-600 mt-1">%s</span>', esc_html($label));

        if ($details) {
            $output .= sprintf('<span class="block text-xs text-gray-500 mt-1">%s</span>', esc_html($details));
        }
    }
    echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('manage_comments_custom_column', 'gta6_mods_comments_admin_column_content', 10, 2);

function gta6_mods_comments_reported_view($views) {
    $count = get_comments([
        'status'     => 'approve',
        'count'      => true,
        'meta_query' => [[
            'key'     => '_report_count',
            'value'   => 0,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ]],
    ]);
    $current = isset($_GET['comment_status']) && 'reported' === $_GET['comment_status'];
    $views['reported'] = sprintf('<a href="%s"%s>%s</a>', esc_url(add_query_arg('comment_status', 'reported', admin_url('edit-comments.php'))), $current ? ' class="current"' : '', sprintf(esc_html__('Reported (%s)', 'gta6-mods'), number_format_i18n($count)));
    return $views;
}
add_filter('views_edit-comments', 'gta6_mods_comments_reported_view');

function gta6_mods_comments_filter_reported($query) {
    if (!is_admin() || !($query instanceof WP_Comment_Query)) { return; }
    if (isset($_GET['comment_status']) && 'reported' === $_GET['comment_status']) {
        $meta_query   = (array) $query->query_vars['meta_query'];
        $meta_query[] = [
            'key'     => '_report_count',
            'value'   => 0,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ];
        $query->query_vars['meta_query'] = $meta_query;
    }
}
add_action('pre_get_comments', 'gta6_mods_comments_filter_reported');
