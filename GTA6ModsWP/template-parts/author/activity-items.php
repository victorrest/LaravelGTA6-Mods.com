<?php
/**
 * Author activity placeholder container.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="author-activity-list" class="space-y-6" data-activity-list>
    <!-- Activity items will be appended here by JS -->
</div>

<div class="text-center pt-8" data-activity-load-more-container>
    <button type="button" class="btn-secondary font-semibold px-6 py-2 rounded-lg transition" data-load-more="activity" data-offset="0">
        <?php esc_html_e('Load More Activity', 'gta6-mods'); ?>
    </button>
</div>
