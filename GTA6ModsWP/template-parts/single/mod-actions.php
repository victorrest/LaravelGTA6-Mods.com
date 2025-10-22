<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = get_query_var('gta6mods_mod_actions');
if (!is_array($context)) {
    return;
}

$post_id           = isset($context['post_id']) ? (int) $context['post_id'] : get_the_ID();
$is_user_logged_in = !empty($context['is_user_logged_in']);

?>
<div class="grid grid-cols-3 gap-2">
    <button type="button" class="mod-secondary-action flex flex-col items-center justify-center py-2 rounded-md transition text-sm" data-share-modal-target="">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-1 h-5 w-5"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
        <span><?php esc_html_e('Share', 'gta6-mods'); ?></span>
    </button>

    <button
        type="button"
        class="mod-secondary-action flex flex-col items-center justify-center py-2 rounded-md transition text-sm"
        data-video-submit-modal="true"
        data-mod-id="<?php echo esc_attr($post_id); ?>"
        data-requires-login="<?php echo $is_user_logged_in ? 'false' : 'true'; ?>"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-1 h-5 w-5"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect></svg>
        <span><?php esc_html_e('Add Video', 'gta6-mods'); ?></span>
    </button>

    <button type="button" class="mod-secondary-action flex flex-col items-center justify-center py-2 rounded-md transition text-sm" data-scroll-to-comments="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-1 h-5 w-5"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"></path><path d="M8 12h.01"></path><path d="M12 12h.01"></path><path d="M16 12h.01"></path></svg>
        <span><?php esc_html_e('Add Comment', 'gta6-mods'); ?></span>
    </button>
</div>
