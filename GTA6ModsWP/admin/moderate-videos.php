<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface for moderating submitted mod videos.
 */

add_action('admin_menu', 'gta6mods_add_video_moderation_page');

if (!function_exists('gta6mods_add_video_moderation_page')) {
    /**
     * Registers the admin menu page for video moderation.
     */
    function gta6mods_add_video_moderation_page() {
        add_menu_page(
            __('Moderate Videos', 'gta6-mods'),
            __('Mod Videos', 'gta6-mods'),
            'moderate_comments',
            'gta6mods-videos',
            'gta6mods_render_video_moderation_page',
            'dashicons-video-alt3',
            26
        );
    }
}

if (!function_exists('gta6mods_render_video_moderation_page')) {
    /**
     * Renders the moderation screen.
     */
    function gta6mods_render_video_moderation_page() {
        if (!current_user_can('moderate_comments')) {
            wp_die(__('You do not have permission to access this page.', 'gta6-mods'));
        }

        global $wpdb;

        $table_name   = gta6mods_get_video_table_name();
        $allowed_tabs = gta6mods_get_video_statuses();

        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'pending';
        if (!in_array($current_tab, $allowed_tabs, true)) {
            $current_tab = 'pending';
        }

        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $video_id = isset($_POST['video_id']) ? absint($_POST['video_id']) : 0;
            $action   = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
            $nonce    = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

            if ($video_id > 0 && $action && $nonce && wp_verify_nonce($nonce, 'moderate_video_' . $video_id)) {
                if (in_array($action, ['approve', 'restore'], true)) {
                    $is_restore     = ('restore' === $action);
                    $user_id        = get_current_user_id();
                    $can_process    = true;
                    $transient_key  = '';
                    $recent_approvals = 0;

                    if (!$is_restore) {
                        $transient_key    = 'gta6mods_admin_approvals_' . $user_id;
                        $recent_approvals = (int) get_transient($transient_key);

                        if ($recent_approvals >= 50) {
                            add_settings_error(
                                'gta6mods-video-moderation',
                                'video-approval-rate-limit',
                                __('Túl sok jóváhagyás rövid időn belül. Várj 1 percet.', 'gta6-mods'),
                                'error'
                            );
                            $can_process = false;
                        }
                    }

                    if ($can_process) {
                        $video = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $video_id)
                        );

                        if ($video) {
                            $video_status = isset($video->status) ? (string) $video->status : '';

                            if ($is_restore && 'rejected' !== $video_status) {
                                add_settings_error(
                                    'gta6mods-video-moderation',
                                    'video-restore-invalid-state',
                                    __('Only rejected videos can be restored.', 'gta6-mods'),
                                    'error'
                                );
                            } else {
                                if (!$is_restore) {
                                    set_transient($transient_key, $recent_approvals + 1, 60);
                                }

                                $thumbnail_data = gta6mods_download_youtube_thumbnail($video->youtube_id, (int) $video->mod_id);

                                $thumbnail_path = (is_array($thumbnail_data) && !empty($thumbnail_data['relative_url']))
                                    ? $thumbnail_data['relative_url']
                                    : null;
                                $attachment_id = (is_array($thumbnail_data) && !empty($thumbnail_data['attachment_id']))
                                    ? (int) $thumbnail_data['attachment_id']
                                    : null;

                                $video_details = gta6mods_fetch_youtube_video_details($video->youtube_id);

                                $position = (int) $video->position;
                                if ($position <= 0) {
                                    $position = gta6mods_get_next_video_position((int) $video->mod_id);
                                }

                                $update_data = [
                                    'status'                  => 'approved',
                                    'thumbnail_path'          => $thumbnail_path,
                                    'thumbnail_attachment_id' => $attachment_id,
                                    'moderated_by'            => $user_id,
                                    'moderated_at'            => current_time('mysql'),
                                    'position'                => $position,
                                ];

                                $update_format = ['%s', '%s', '%d', '%d', '%s', '%d'];

                                if (is_array($video_details)) {
                                    $update_data['video_title'] = isset($video_details['title']) && $video_details['title'] !== ''
                                        ? $video_details['title']
                                        : null;
                                    $update_data['video_description'] = isset($video_details['description']) && $video_details['description'] !== ''
                                        ? $video_details['description']
                                        : null;
                                    $update_data['duration'] = isset($video_details['duration']) && $video_details['duration'] !== ''
                                        ? $video_details['duration']
                                        : null;

                                    $update_format[] = '%s';
                                    $update_format[] = '%s';
                                    $update_format[] = '%s';
                                }

                                $wpdb->update(
                                    $table_name,
                                    $update_data,
                                    ['id' => $video_id],
                                    $update_format,
                                    ['%d']
                                );

                                gta6mods_clear_video_cache((int) $video->mod_id);
                                if (function_exists('gta6mods_clear_sitemap_cache')) {
                                    gta6mods_clear_sitemap_cache('videos');
                                }

                                $notice_code = $is_restore ? 'video-restored' : 'video-approved';
                                $notice_text = $is_restore
                                    ? __('Video restored to approved status.', 'gta6-mods')
                                    : __('Video approved!', 'gta6-mods');

                                add_settings_error('gta6mods-video-moderation', $notice_code, $notice_text, 'updated');
                            }
                        }
                    }
                } elseif ('reject' === $action) {
                    $wpdb->update(
                        $table_name,
                        [
                            'status'       => 'rejected',
                            'moderated_by' => get_current_user_id(),
                            'moderated_at' => current_time('mysql'),
                        ],
                        ['id' => $video_id],
                        ['%s', '%d', '%s'],
                        ['%d']
                    );

                    add_settings_error('gta6mods-video-moderation', 'video-rejected', __('Video rejected.', 'gta6-mods'), 'notice-warning');
                    if (function_exists('gta6mods_clear_sitemap_cache')) {
                        gta6mods_clear_sitemap_cache('videos');
                    }
                } elseif ('delete' === $action) {
                    $video = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT mod_id, thumbnail_path, thumbnail_attachment_id FROM {$table_name} WHERE id = %d",
                            $video_id
                        )
                    );

                    if ($video) {
                        $attachment_id = isset($video->thumbnail_attachment_id) ? (int) $video->thumbnail_attachment_id : 0;

                        if ($attachment_id > 0) {
                            wp_delete_attachment($attachment_id, true);
                        } elseif (!empty($video->thumbnail_path)) {
                            $thumbnail_path = ABSPATH . ltrim($video->thumbnail_path, '/');
                            if (file_exists($thumbnail_path)) {
                                @unlink($thumbnail_path);
                            }
                        }
                    }

                    $wpdb->delete($table_name, ['id' => $video_id], ['%d']);

                    if ($video && isset($video->mod_id)) {
                        gta6mods_clear_video_cache((int) $video->mod_id);
                        if (function_exists('gta6mods_clear_sitemap_cache')) {
                            gta6mods_clear_sitemap_cache('videos');
                        }
                    }

                    add_settings_error('gta6mods-video-moderation', 'video-deleted', __('Video deleted.', 'gta6-mods'), 'updated');
                }
            }
        }

        settings_errors('gta6mods-video-moderation');

        $statuses_for_counts = ['pending', 'reported'];
        $counts              = [];
        foreach ($statuses_for_counts as $status) {
            $counts[$status] = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE status = %s", $status)
            );
        }

        $videos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.*, p.post_title, u.display_name
                 FROM {$table_name} v
                 INNER JOIN {$wpdb->posts} p ON v.mod_id = p.ID
                 LEFT JOIN {$wpdb->users} u ON v.submitted_by = u.ID
                 WHERE v.status = %s
                 ORDER BY v.submitted_at DESC
                 LIMIT 50",
                $current_tab
            )
        );

        $reporters_map = [];
        if ('reported' === $current_tab && !empty($videos) && function_exists('gta6mods_get_video_reporters_map')) {
            $video_ids = array_map(static function ($video) {
                return isset($video->id) ? (int) $video->id : 0;
            }, (array) $videos);

            $video_ids = array_filter($video_ids);

            if (!empty($video_ids)) {
                $reporters_map = gta6mods_get_video_reporters_map($video_ids);
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Moderate Videos', 'gta6-mods'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $tab) :
                    $label = '';
                    switch ($tab) {
                        case 'pending':
                            $label = __('Pending', 'gta6-mods');
                            break;
                        case 'approved':
                            $label = __('Approved', 'gta6-mods');
                            break;
                        case 'rejected':
                            $label = __('Rejected', 'gta6-mods');
                            break;
                        case 'reported':
                            $label = __('Reported', 'gta6-mods');
                            break;
                    }

                    $tab_url   = esc_url(add_query_arg(['page' => 'gta6mods-videos', 'tab' => $tab], admin_url('admin.php')));
                    $is_active = $current_tab === $tab;
                    $count     = isset($counts[$tab]) ? (int) $counts[$tab] : 0;
                    ?>
                    <a href="<?php echo $tab_url; ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                        <?php if ($count > 0 && in_array($tab, ['pending', 'reported'], true)) : ?>
                            <span class="awaiting-mod count-<?php echo (int) $count; ?>">
                                <span class="pending-count"><?php echo (int) $count; ?></span>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php if (empty($videos)) : ?>
                <p><?php esc_html_e('No videos found.', 'gta6-mods'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;">&nbsp;</th>
                            <th><?php esc_html_e('Mod', 'gta6-mods'); ?></th>
                            <th><?php esc_html_e('Submitted by', 'gta6-mods'); ?></th>
                            <th><?php esc_html_e('Submitted at', 'gta6-mods'); ?></th>
                            <?php if ('reported' === $current_tab) : ?>
                                <th><?php esc_html_e('Reports', 'gta6-mods'); ?></th>
                            <?php endif; ?>
                            <th><?php esc_html_e('Actions', 'gta6-mods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $video) :
                            $thumbnail_url = sprintf('https://i.ytimg.com/vi/%s/hqdefault.jpg', esc_attr($video->youtube_id));
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($video->youtube_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?php echo esc_url($thumbnail_url); ?>" alt="" width="120" height="90">
                                    </a>
                                </td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(get_permalink((int) $video->mod_id)); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($video->post_title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($video->submitted_by) : ?>
                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . (int) $video->submitted_by)); ?>">
                                            <?php echo esc_html($video->display_name); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="description"><?php esc_html_e('Unknown user', 'gta6-mods'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $submitted_time = strtotime($video->submitted_at);
                                    echo esc_html(
                                        $submitted_time ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $submitted_time) : '—'
                                    );
                                    ?>
                                </td>
                                <?php if ('reported' === $current_tab) : ?>
                                    <td>
                                        <strong><?php echo (int) $video->report_count; ?></strong>
                                        <?php if (!empty($video->last_reported_at)) :
                                            $reported_time = strtotime($video->last_reported_at);
                                            if ($reported_time) : ?>
                                                <br><small><?php echo esc_html(human_time_diff($reported_time, current_time('timestamp'))); ?></small>
                                            <?php endif;
                                        endif; ?>
                                        <?php
                                        if ('reported' === $current_tab && !empty($reporters_map)) {
                                            $video_id = isset($video->id) ? (int) $video->id : 0;
                                            if ($video_id > 0 && isset($reporters_map[$video_id]) && is_array($reporters_map[$video_id]) && !empty($reporters_map[$video_id])) {
                                                echo '<div class="mt-2 text-xs text-gray-600">';
                                                printf('<div class="font-semibold text-gray-700">%s</div>', esc_html__('Reported by:', 'gta6-mods'));
                                                foreach ($reporters_map[$video_id] as $reporter) {
                                                    $reporter_id = isset($reporter['user_id']) ? (int) $reporter['user_id'] : 0;
                                                    $reporter_name = isset($reporter['display_name']) && $reporter['display_name'] !== ''
                                                        ? $reporter['display_name']
                                                        : ($reporter_id > 0 ? sprintf(__('User #%d', 'gta6-mods'), $reporter_id) : __('Anonymous', 'gta6-mods'));

                                                    if ($reporter_id > 0) {
                                                        $reporter_url = esc_url(admin_url('user-edit.php?user_id=' . $reporter_id));
                                                        printf('<div><a href="%1$s">%2$s</a></div>', $reporter_url, esc_html($reporter_name));
                                                    } else {
                                                        printf('<div>%s</div>', esc_html($reporter_name));
                                                    }
                                                }
                                                echo '</div>';
                                            }
                                        }
                                        ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('moderate_video_' . (int) $video->id); ?>
                                        <input type="hidden" name="video_id" value="<?php echo (int) $video->id; ?>">

                                        <?php if (in_array($current_tab, ['pending', 'reported'], true)) : ?>
                                            <button type="submit" name="action" value="approve" class="button button-primary button-small">
                                                <?php esc_html_e('Approve', 'gta6-mods'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ('pending' === $current_tab) : ?>
                                            <button type="submit" name="action" value="reject" class="button button-small">
                                                <?php esc_html_e('Reject', 'gta6-mods'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ('rejected' === $current_tab) : ?>
                                            <button type="submit" name="action" value="restore" class="button button-small">
                                                <?php esc_html_e('Restore to Approved', 'gta6-mods'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <button type="submit" name="action" value="delete" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this video?', 'gta6-mods')); ?>');">
                                            <?php esc_html_e('Delete', 'gta6-mods'); ?>
                                        </button>
                                    </form>
                                    <a href="<?php echo esc_url($video->youtube_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-small">
                                        <?php esc_html_e('Watch', 'gta6-mods'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <style>
            .awaiting-mod {
                background-color: #d63638;
                color: #fff;
                border-radius: 10px;
                padding: 0 8px;
                font-size: 11px;
                font-weight: 600;
                margin-left: 6px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 20px;
            }
        </style>
        <?php
    }
}
