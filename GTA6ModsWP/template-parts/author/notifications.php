<?php
/**
 * Author notifications tab template.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

$notifications = $args['notifications'] ?? [];

if (!empty($notifications)) :
    ?>
    <ul class="space-y-4">
        <?php foreach ($notifications as $notification) :
            $is_read = (int) ($notification['is_read'] ?? 0) === 1;
            $actor   = !empty($notification['actor_user_id']) ? get_user_by('id', (int) $notification['actor_user_id']) : null;
            $actor_name = $actor ? $actor->display_name : __('System', 'gta6-mods');
            $timestamp  = !empty($notification['created_at']) ? strtotime($notification['created_at'] . ' UTC') : false;
            $time_diff  = $timestamp ? human_time_diff($timestamp, current_time('timestamp', true)) : '';
            $meta       = $notification['meta'] ?? [];

            $notification_link = function_exists('gta6mods_get_notification_link')
                ? gta6mods_get_notification_link($notification)
                : '';

            $icon_styles = function_exists('gta6mods_get_notification_icon_styles')
                ? gta6mods_get_notification_icon_styles($notification['action_type'] ?? '')
                : ['icon' => 'fa-bell', 'wrapper' => 'bg-pink-100 text-pink-600'];

            $icon_class          = isset($icon_styles['icon']) ? (string) $icon_styles['icon'] : 'fa-bell';
            $icon_wrapper_class  = isset($icon_styles['wrapper']) ? (string) $icon_styles['wrapper'] : 'bg-pink-100 text-pink-600';

            $message         = __('You have a new notification.', 'gta6-mods');
            $message_is_html = false;

            $actor_url   = $actor ? get_author_posts_url((int) $actor->ID) : '';
            $actor_label = $actor ? $actor->display_name : __('System', 'gta6-mods');
            $actor_html  = $actor_url
                ? sprintf(
                    '<a href="%s" class="font-semibold text-pink-600 hover:underline">%s</a>',
                    esc_url($actor_url),
                    esc_html($actor_label)
                )
                : sprintf(
                    '<span class="font-semibold text-gray-900">%s</span>',
                    esc_html($actor_label)
                );

            switch ($notification['action_type']) {
                case 'followed':
                    $message = sprintf(
                        /* translators: %s: user display name with link */
                        __('%s started following you.', 'gta6-mods'),
                        $actor_html
                    );
                    $message_is_html = true;
                    break;
                case 'commented':
                    $mod_title = !empty($meta['post_title']) ? $meta['post_title'] : __('your mod', 'gta6-mods');
                    $post_url  = $notification_link;

                    if (empty($post_url) && !empty($meta['post_id'])) {
                        $post_url = get_permalink((int) $meta['post_id']);
                    }

                    $post_html = $post_url
                        ? sprintf(
                            '<a href="%s" class="font-semibold text-pink-600 hover:underline">%s</a>',
                            esc_url($post_url),
                            esc_html($mod_title)
                        )
                        : sprintf(
                            '<span class="font-semibold text-gray-900">%s</span>',
                            esc_html($mod_title)
                        );

                    $message = sprintf(
                        /* translators: 1: user display name with link, 2: post title with link */
                        __('%1$s commented on %2$s.', 'gta6-mods'),
                        $actor_html,
                        $post_html
                    );
                    $message_is_html = true;
                    break;
                case 'liked':
                    $mod_title = !empty($meta['post_title']) ? $meta['post_title'] : __('your mod', 'gta6-mods');
                    $post_url  = $notification_link;

                    if (empty($post_url) && !empty($meta['post_id'])) {
                        $post_url = get_permalink((int) $meta['post_id']);
                    }

                    $post_html = $post_url
                        ? sprintf(
                            '<a href="%s" class="font-semibold text-pink-600 hover:underline">%s</a>',
                            esc_url($post_url),
                            esc_html($mod_title)
                        )
                        : sprintf(
                            '<span class="font-semibold text-gray-900">%s</span>',
                            esc_html($mod_title)
                        );

                    $message = sprintf(
                        /* translators: 1: user display name with link, 2: post title with link */
                        __('%1$s liked %2$s.', 'gta6-mods'),
                        $actor_html,
                        $post_html
                    );
                    $message_is_html = true;
                    break;
                default:
                    if (!empty($meta['message'])) {
                        $message = wp_strip_all_tags($meta['message']);
                    }
                    break;
            }
            ?>
            <li class="p-4 rounded-lg border-l-4 <?php echo $is_read ? 'bg-white border-transparent' : 'bg-pink-50 border-pink-500'; ?>">
                <div class="flex items-start gap-4">
                    <div class="<?php echo esc_attr(trim($icon_wrapper_class)); ?> rounded-full h-9 w-9 flex-shrink-0 flex items-center justify-center">
                        <i class="fas <?php echo esc_attr($icon_class); ?>"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-700">
                            <?php
                            if ($message_is_html) {
                                echo wp_kses_post($message);
                            } elseif (!empty($notification_link)) {
                                ?>
                                <a href="<?php echo esc_url($notification_link); ?>" class="hover:text-pink-600 transition-colors duration-150 ease-in-out"><?php echo esc_html($message); ?></a>
                                <?php
                            } else {
                                echo esc_html($message);
                            }
                            ?>
                        </p>
                        <?php if ($time_diff) : ?>
                            <p class="text-xs text-gray-500 mt-1.5"><?php printf(esc_html__('%s ago', 'gta6-mods'), esc_html($time_diff)); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
else :
    ?>
    <div class="text-center py-12">
        <i class="fas fa-bell text-4xl text-gray-300 mb-4"></i>
        <h3 class="font-bold text-lg text-gray-700"><?php esc_html_e('All caught up!', 'gta6-mods'); ?></h3>
        <p class="text-gray-500 mt-1"><?php esc_html_e('You do not have any notifications right now.', 'gta6-mods'); ?></p>
    </div>
    <?php
endif;
