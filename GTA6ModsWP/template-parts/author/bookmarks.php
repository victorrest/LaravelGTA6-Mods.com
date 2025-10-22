<?php
/**
 * Author bookmarks tab template.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div data-author-loading class="py-12 text-center text-sm text-gray-500">
    <span class="inline-flex items-center gap-2"><span class="loader h-4 w-4 border-2 border-gray-300 border-t-pink-500 rounded-full animate-spin"></span><?php esc_html_e('Loading bookmarks…', 'gta6-mods'); ?></span>
</div>
<div data-author-async-content></div>
