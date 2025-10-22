<?php
/**
 * Author uploads tab template.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

$query      = $args['query'] ?? null;
$author_id  = isset($args['author_id']) ? (int) $args['author_id'] : 0;

if (!($query instanceof WP_Query)) {
    echo '<div class="text-center py-12 text-sm text-gray-500">' . esc_html__('Nothing to display.', 'gta6-mods') . '</div>';
    return;
}

if ($query->have_posts()) :
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php
        while ($query->have_posts()) :
            $query->the_post();
            $post_id     = get_the_ID();
            $thumbnail   = get_the_post_thumbnail_url($post_id, 'medium');
            if (!$thumbnail) {
                $thumbnail = apply_filters('gta6mods_mod_placeholder_image', 'https://placehold.co/640x360?text=Mod');
            }
            $rating_data = gta6_mods_get_rating_data($post_id);
            $rating      = isset($rating_data['average']) ? (float) $rating_data['average'] : 0.0;
            $downloads   = gta6_mods_get_download_count($post_id);
            $likes       = gta6_mods_get_like_count($post_id);
            $time_diff   = human_time_diff(get_post_time('U', true), current_time('timestamp', true));
            ?>
            <article class="card group overflow-hidden border border-transparent hover:border-pink-200 transition">
                <a href="<?php echo esc_url(get_permalink()); ?>" class="block">
                    <div class="relative">
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="w-full h-32 object-cover group-hover:scale-105 transition-transform duration-300" />
                        <div class="absolute bottom-0 left-0 right-0 p-1.5 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                            <div class="flex justify-between items-center">
                                <span class="flex items-center font-semibold">
                                    <i class="fas fa-star mr-1 text-yellow-400"></i>
                                    <?php echo esc_html(number_format_i18n($rating, 2)); ?>
                                </span>
                                <div class="flex items-center space-x-2">
                                    <span class="flex items-center">
                                        <i class="fas fa-download mr-1"></i>
                                        <?php echo esc_html(number_format_i18n($downloads)); ?>
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-thumbs-up mr-1"></i>
                                        <?php echo esc_html(number_format_i18n($likes)); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-3">
                        <h4 class="font-semibold text-sm text-gray-800 group-hover:text-pink-600 transition truncate">
                            <?php the_title(); ?>
                        </h4>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php printf(esc_html__('%s ago', 'gta6-mods'), esc_html($time_diff)); ?>
                        </p>
                    </div>
                </a>
            </article>
        <?php endwhile; ?>
    </div>
    <?php
    $pagination = $args['pagination'] ?? [];
    $base_url   = $args['base_url'] ?? '';
    $total_pages = (int) ($pagination['found'] ?? 0);
    if ($total_pages > 1) :
        $current = max(1, (int) ($pagination['current'] ?? 1));
        $pages   = [];
        $pages[] = 1;
        $range   = 1;
        $start   = max(2, $current - $range);
        $end     = min($total_pages - 1, $current + $range);

        if ($start > 2) {
            $pages[] = 'gap';
        }

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        if ($end < $total_pages - 1) {
            $pages[] = 'gap';
        }

        if ($total_pages > 1) {
            $pages[] = $total_pages;
        }
        ?>
        <div class="mt-8 mb-6 flex justify-center">
            <nav class="inline-flex items-center gap-1" aria-label="<?php esc_attr_e('Pagination', 'gta6-mods'); ?>" data-upload-pagination="1">
                <?php
                $prev_disabled = $current <= 1;
                $prev_target   = max(1, $current - 1);
                $prev_url      = $author_id > 0 ? gta6mods_get_author_profile_tab_page_url($author_id, 'uploads', $prev_target) : ($base_url ? add_query_arg('tab_page', $prev_target, $base_url) : '');
                ?>
                <?php if ($prev_disabled) : ?>
                    <span class="px-3 py-1 border text-sm rounded bg-white text-gray-400 border-gray-200 opacity-60 cursor-not-allowed" aria-disabled="true">&lsaquo;</span>
                <?php else : ?>
                    <a class="px-3 py-1 border text-sm rounded bg-white text-gray-600 border-gray-200 hover:bg-gray-50" href="<?php echo esc_url($prev_url); ?>" aria-label="<?php esc_attr_e('Previous page', 'gta6-mods'); ?>" data-upload-page="<?php echo esc_attr($prev_target); ?>">&lsaquo;</a>
                <?php endif; ?>
                <?php foreach ($pages as $page) : ?>
                    <?php if ('gap' === $page) : ?>
                        <span class="px-3 py-1 border text-sm bg-white text-gray-400 border-transparent select-none">…</span>
                    <?php else :
                        $is_current = ((int) $page === $current);
                        $page_number = (int) $page;
                        $page_url   = $author_id > 0 ? gta6mods_get_author_profile_tab_page_url($author_id, 'uploads', $page_number) : ($base_url ? add_query_arg('tab_page', $page_number, $base_url) : '');
                        ?>
                        <?php if ($is_current) : ?>
                            <span class="px-3 py-1 border text-sm rounded bg-pink-600 text-white border-pink-600" aria-current="page"><?php echo esc_html($page); ?></span>
                        <?php else : ?>
                            <a class="px-3 py-1 border text-sm rounded bg-white text-gray-600 border-gray-200 hover:bg-gray-50" href="<?php echo esc_url($page_url); ?>" data-upload-page="<?php echo esc_attr($page_number); ?>"><?php echo esc_html($page); ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php
                $next_disabled = $current >= $total_pages;
                $next_target   = min($total_pages, $current + 1);
                $next_url      = $author_id > 0 ? gta6mods_get_author_profile_tab_page_url($author_id, 'uploads', $next_target) : ($base_url ? add_query_arg('tab_page', $next_target, $base_url) : '');
                ?>
                <?php if ($next_disabled) : ?>
                    <span class="px-3 py-1 border text-sm rounded bg-white text-gray-400 border-gray-200 opacity-60 cursor-not-allowed" aria-disabled="true">&rsaquo;</span>
                <?php else : ?>
                    <a class="px-3 py-1 border text-sm rounded bg-white text-gray-600 border-gray-200 hover:bg-gray-50" href="<?php echo esc_url($next_url); ?>" aria-label="<?php esc_attr_e('Next page', 'gta6-mods'); ?>" data-upload-page="<?php echo esc_attr($next_target); ?>">&rsaquo;</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php
    endif;
else :
    ?>
    <div class="text-center py-12">
        <i class="fas fa-upload text-4xl text-gray-300 mb-4"></i>
        <h3 class="font-bold text-lg text-gray-700"><?php esc_html_e('No uploads yet', 'gta6-mods'); ?></h3>
        <p class="text-gray-500 mt-1"><?php esc_html_e('Once mods are published they will appear here.', 'gta6-mods'); ?></p>
    </div>
    <?php
endif;
