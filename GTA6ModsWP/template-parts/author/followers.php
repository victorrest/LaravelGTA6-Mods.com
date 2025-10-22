<?php
/**
 * Author followers tab template.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

$followers = $args['followers'] ?? [];

if (!empty($followers)) :
    ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <?php foreach ($followers as $follower) :
            if (!($follower instanceof WP_User)) {
                continue;
            }
            $avatar      = get_avatar_url($follower->ID, ['size' => 160]);
            $upload_count = (int) get_user_meta($follower->ID, '_mod_upload_count', true);
            ?>
            <a href="<?php echo esc_url(get_author_posts_url($follower->ID)); ?>" class="bg-gray-50 p-4 text-center rounded-lg hover:shadow-md hover:-translate-y-0.5 transition-all block">
                <img src="<?php echo esc_url($avatar); ?>" class="w-16 h-16 rounded-full mx-auto object-cover" alt="<?php echo esc_attr($follower->display_name); ?>" />
                <p class="font-semibold text-sm mt-2 text-gray-800"><?php echo esc_html($follower->display_name); ?></p>
                <p class="text-xs text-gray-500"><?php printf(esc_html__('%d uploads', 'gta6-mods'), $upload_count); ?></p>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
else :
    ?>
    <div class="text-center py-12">
        <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
        <h3 class="font-bold text-lg text-gray-700"><?php esc_html_e('No followers yet', 'gta6-mods'); ?></h3>
        <p class="text-gray-500 mt-1"><?php esc_html_e('Share your profile to grow your audience.', 'gta6-mods'); ?></p>
    </div>
    <?php
endif;
