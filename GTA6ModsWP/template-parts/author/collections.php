<?php
/**
 * Author collections tab template.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

$collections = $args['collections'] ?? [];

if (!empty($collections)) :
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($collections as $collection) :
            $collection_id = sanitize_key($collection['id'] ?? uniqid('collection_', true));
            $title         = !empty($collection['title']) ? $collection['title'] : __('Untitled Collection', 'gta6-mods');
            $description   = !empty($collection['description']) ? $collection['description'] : '';
            $all_mod_ids   = array_map('absint', $collection['mods'] ?? []);
            $mod_ids       = array_slice($all_mod_ids, 0, 9);
            $mod_posts     = [];
            if (!empty($mod_ids)) {
                $mod_posts = get_posts([
                    'post_type'      => gta6mods_get_mod_post_types(),
                    'post__in'       => $mod_ids,
                    'orderby'        => 'post__in',
                    'posts_per_page' => 9,
                ]);
            }
            ?>
            <article class="card overflow-hidden group hover:shadow-lg transition-shadow">
                <div class="p-4">
                    <h4 class="font-bold text-lg text-pink-600 group-hover:underline">
                        <?php echo esc_html($title); ?>
                    </h4>
                    <?php if ($description) : ?>
                        <p class="text-sm text-gray-600 mt-1"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($mod_posts)) : ?>
                    <div class="grid grid-cols-3 gap-0.5 px-2 pb-2">
                        <?php foreach ($mod_posts as $mod_post) :
                            $thumb = get_the_post_thumbnail_url($mod_post, 'thumbnail');
                            if (!$thumb) {
                                $thumb = apply_filters('gta6mods_mod_placeholder_image', 'https://placehold.co/300x160?text=Mod');
                            }
                            ?>
                            <img src="<?php echo esc_url($thumb); ?>" class="aspect-video object-cover rounded-sm" alt="<?php echo esc_attr(get_the_title($mod_post)); ?>" />
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="px-4 pb-4 text-sm text-gray-500">
                        <?php esc_html_e('No mods added yet.', 'gta6-mods'); ?>
                    </div>
                <?php endif; ?>
                <div class="p-3 bg-gray-50 border-t text-xs text-gray-500 flex justify-between items-center">
                    <span><?php printf(esc_html__('%d mods', 'gta6-mods'), count($all_mod_ids)); ?></span>
                    <span class="font-semibold">
                        <?php esc_html_e('View collection', 'gta6-mods'); ?>
                        <i class="fas fa-arrow-right ml-1"></i>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
else :
    ?>
    <div class="text-center py-12">
        <i class="fas fa-layer-group text-4xl text-gray-300 mb-4"></i>
        <h3 class="font-bold text-lg text-gray-700"><?php esc_html_e('No collections yet', 'gta6-mods'); ?></h3>
        <p class="text-gray-500 mt-1"><?php esc_html_e('Create a collection to organise your favourite mods.', 'gta6-mods'); ?></p>
    </div>
    <?php
endif;
