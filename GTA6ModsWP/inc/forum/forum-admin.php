<?php
/**
 * Admin integrations for the forum subsystem.
 *
 * @package gta6modswp
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function gta6_forum_add_thread_meta_box(): void
{
    add_meta_box(
        'gta6_forum_thread_settings',
        __('Thread settings', 'gta6mods'),
        'gta6_forum_render_thread_meta_box',
        'forum_thread',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes_forum_thread', 'gta6_forum_add_thread_meta_box');

function gta6_forum_render_thread_meta_box(WP_Post $post): void
{
    $type            = (string) get_post_meta($post->ID, '_thread_post_type', true);
    $externalUrl     = (string) get_post_meta($post->ID, '_thread_external_url', true);
    $relatedModUrl   = (string) get_post_meta($post->ID, '_thread_related_mod_url', true);
    $allowedTypes    = ['text', 'image', 'link'];
    $currentType     = in_array($type, $allowedTypes, true) ? $type : 'text';

    wp_nonce_field('gta6_forum_thread_meta', 'gta6_forum_thread_meta_nonce');
    ?>
    <p>
        <label for="gta6-forum-thread-type"><strong><?php esc_html_e('Thread type', 'gta6mods'); ?></strong></label>
        <select id="gta6-forum-thread-type" name="gta6_forum_thread_type" class="widefat">
            <option value="text"<?php selected('text', $currentType); ?>><?php esc_html_e('Text', 'gta6mods'); ?></option>
            <option value="image"<?php selected('image', $currentType); ?>><?php esc_html_e('Image', 'gta6mods'); ?></option>
            <option value="link"<?php selected('link', $currentType); ?>><?php esc_html_e('Link', 'gta6mods'); ?></option>
        </select>
    </p>
    <p>
        <label for="gta6-forum-thread-external-url"><strong><?php esc_html_e('External URL', 'gta6mods'); ?></strong></label>
        <input type="url" id="gta6-forum-thread-external-url" name="gta6_forum_thread_external_url" class="widefat"
               value="<?php echo esc_attr($externalUrl); ?>"
               placeholder="https://example.com/resource" autocomplete="off">
        <em class="description"><?php esc_html_e('Used when the thread type is set to “Link”.', 'gta6mods'); ?></em>
    </p>
    <p>
        <label for="gta6-forum-related-mod-url"><strong><?php esc_html_e('Related mod URL', 'gta6mods'); ?></strong></label>
        <input type="url" id="gta6-forum-related-mod-url" name="gta6_forum_related_mod_url" class="widefat"
               value="<?php echo esc_attr($relatedModUrl); ?>"
               placeholder="https://gta6-mods.com/..." autocomplete="off">
        <em class="description"><?php esc_html_e('Optional link to a related GTA6-Mods.com mod page.', 'gta6mods'); ?></em>
    </p>
    <?php
}

function gta6_forum_save_thread_meta_box(int $postId, WP_Post $post): void
{
    if (!isset($_POST['gta6_forum_thread_meta_nonce'])) {
        return;
    }

    $nonce = wp_unslash((string) $_POST['gta6_forum_thread_meta_nonce']);
    if (!wp_verify_nonce($nonce, 'gta6_forum_thread_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $postId)) {
        return;
    }

    $type       = isset($_POST['gta6_forum_thread_type']) ? sanitize_key(wp_unslash((string) $_POST['gta6_forum_thread_type'])) : 'text';
    $allowed    = ['text', 'image', 'link'];
    $finalType  = in_array($type, $allowed, true) ? $type : 'text';
    update_post_meta($postId, '_thread_post_type', $finalType);

    $externalUrl = '';
    if ('link' === $finalType && isset($_POST['gta6_forum_thread_external_url'])) {
        $externalUrl = esc_url_raw((string) wp_unslash($_POST['gta6_forum_thread_external_url']));
    }

    if ('link' === $finalType && '' !== $externalUrl) {
        update_post_meta($postId, '_thread_external_url', $externalUrl);
    } else {
        delete_post_meta($postId, '_thread_external_url');
    }

    $relatedUrl = isset($_POST['gta6_forum_related_mod_url']) ? (string) wp_unslash($_POST['gta6_forum_related_mod_url']) : '';
    $validated  = '';
    if ('' !== $relatedUrl && function_exists('gta6_forum_validate_related_mod_url')) {
        $validation = gta6_forum_validate_related_mod_url($relatedUrl);
        if (!is_wp_error($validation)) {
            $validated = $validation;
        }
    }

    if ('' !== $validated) {
        update_post_meta($postId, '_thread_related_mod_url', $validated);
    } else {
        delete_post_meta($postId, '_thread_related_mod_url');
    }
}
add_action('save_post_forum_thread', 'gta6_forum_save_thread_meta_box', 10, 2);
