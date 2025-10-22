<?php
if (!defined('ABSPATH')) {
    exit;
}

$post_id     = get_the_ID();
$post_format = get_post_format($post_id);

if ($post_format && 'standard' !== $post_format) {
?>
    <main class="container mx-auto p-4 lg:p-6 space-y-10">
        <article id="post-<?php the_ID(); ?>" <?php post_class('card overflow-hidden'); ?>>
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('full', ['class' => 'w-full h-auto object-cover']); ?>
            <?php else : ?>
                <img src="<?php echo esc_url(gta6_mods_get_placeholder('featured')); ?>" alt="<?php the_title_attribute(); ?>" class="w-full h-auto object-cover">
            <?php endif; ?>
            <div class="p-6 md:p-8">
                <div class="flex flex-wrap items-center text-xs text-gray-500 space-x-4 mb-4">
                    <span class="flex items-center"><i class="fa-solid fa-user mr-2"></i><?php the_author_posts_link(); ?></span>
                    <span class="flex items-center"><i class="fa-solid fa-calendar-days mr-2"></i><?php echo esc_html(get_the_date()); ?></span>
                    <span class="flex items-center"><i class="fa-solid fa-folder-open mr-2"></i><?php the_category(', '); ?></span>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4"><?php the_title(); ?></h1>
                <div class="prose max-w-none text-gray-700">
                    <?php the_content(); ?>
                </div>
                <?php
                $is_user_logged_in = is_user_logged_in();
                $is_liked          = gta6_mods_get_user_like_status($post_id);
                $rating_data       = gta6_mods_get_rating_data($post_id);
                $user_rating       = gta6_mods_get_user_rating($post_id);
                $like_count        = gta6_mods_get_like_count($post_id);
                $is_bookmarked_main = gta6_mods_is_mod_bookmarked_by_user($post_id);
                $rating_tooltips   = [
                    1 => __('Poor', 'gta6-mods'),
                    2 => __('Fair', 'gta6-mods'),
                    3 => __('Good', 'gta6-mods'),
                    4 => __('Very Good', 'gta6-mods'),
                    5 => __('Excellent', 'gta6-mods'),
                ];
                $like_active_classes   = 'bg-pink-600 text-white';
                $like_inactive_classes = 'bg-gray-200 text-gray-700 hover:bg-gray-300';
                ?>
                <?php
                $rating_average_value     = isset($rating_data['average']) ? (float) $rating_data['average'] : 0.0;
                $rating_average_attribute = number_format($rating_average_value, 3, '.', '');
                $rating_average_display   = round($rating_average_value * 2) / 2;
                $rating_full_stars        = (int) floor($rating_average_display);
                $rating_has_half_star     = ($rating_average_display - $rating_full_stars) >= 0.5;
                ?>
                <div class="flex flex-wrap items-center gap-4 mt-6">
                    <button
                        type="button"
                        class="mod-like-button flex items-center gap-2 px-4 py-2 rounded-lg transition-colors<?php echo $is_liked ? ' liked ' . $like_active_classes : ' ' . $like_inactive_classes; ?>"
                        data-post-id="<?php echo esc_attr($post_id); ?>"
                        data-like-active-class="<?php echo esc_attr($like_active_classes); ?>"
                        data-like-inactive-class="<?php echo esc_attr($like_inactive_classes); ?>"
                        aria-pressed="<?php echo $is_liked ? 'true' : 'false'; ?>"
                        <?php if (!$is_user_logged_in) : ?>disabled title="<?php echo esc_attr__('Jelentkezz be a kedveléshez', 'gta6-mods'); ?>"<?php endif; ?>
                    >
                        <i class="fa-solid fa-thumbs-up"></i>
                        <span class="like-count"><?php echo esc_html(number_format_i18n($like_count)); ?></span>
                    </button>

                    <div
                        class="mod-rating-container flex items-center gap-2"
                        data-post-id="<?php echo esc_attr($post_id); ?>"
                        data-user-rating="<?php echo esc_attr($user_rating); ?>"
                        data-average-rating="<?php echo esc_attr($rating_average_attribute); ?>"
                        data-is-logged-in="false"
                    >
                        <div class="flex gap-1">
                            <?php for ($i = 1; $i <= 5; $i++) : ?>
                                <?php
                                $button_title = $is_user_logged_in ? ($rating_tooltips[$i] ?? '') : __('Jelentkezz be az értékeléshez', 'gta6-mods');
                                $star_classes = ['rating-star', 'text-2xl', 'transition-colors', 'hover:text-yellow-500', 'focus:text-yellow-500'];
                                $icon_classes = ['fa-solid'];

                                if ($user_rating > 0) {
                                    $is_active      = $i <= $user_rating;
                                    $star_classes[] = $is_active ? 'active text-yellow-400' : 'text-gray-300';
                                    $icon_classes[] = 'fa-star';
                                    $average_state  = 'none';
                                } else {
                                    $is_full        = $i <= $rating_full_stars;
                                    $is_half        = !$is_full && $rating_has_half_star && $i === ($rating_full_stars + 1);
                                    $average_state  = $is_half ? 'half' : ($is_full ? 'full' : 'none');

                                    if ($is_full || $is_half) {
                                        $star_classes[] = 'text-yellow-400';
                                        $icon_classes[] = $is_half ? 'fa-star-half-stroke' : 'fa-star';
                                    } else {
                                        $star_classes[] = 'text-gray-300';
                                        $icon_classes[] = 'fa-star';
                                    }
                                }
                                ?>
                                <button
                                    type="button"
                                    class="<?php echo esc_attr(implode(' ', $star_classes)); ?>"
                                    data-rating="<?php echo esc_attr($i); ?>"
                                    data-average-state="<?php echo esc_attr($average_state); ?>"
                                    title="<?php echo esc_attr($button_title); ?>"
                                >
                                    <i class="<?php echo esc_attr(implode(' ', $icon_classes)); ?>"></i>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <span class="text-sm text-gray-600">
                            <span class="rating-average font-bold"><?php echo esc_html(number_format_i18n($rating_data['average'], 1)); ?></span>/5
                            <span class="rating-count">(<?php echo esc_html(number_format_i18n($rating_data['count'])); ?>)</span>
                        </span>
                    </div>

                    <span class="flex items-center text-gray-600">
                        <i class="fa-solid fa-download mr-2"></i>
                        <?php echo esc_html(number_format_i18n(wp_rand(800, 90000))); ?> <?php esc_html_e('letöltés', 'gta6-mods'); ?>
                    </span>
                </div>
                <?php the_tags('<div class="mt-6 text-sm text-gray-600"><strong>' . esc_html__('Címkék:', 'gta6-mods') . '</strong> ', ', ', '</div>'); ?>
            </div>
        </article>
        <nav class="flex flex-col md:flex-row justify-between gap-4">
            <div class="card p-4 md:w-1/2">
                <span class="text-xs uppercase tracking-wide text-gray-400"><?php esc_html_e('Előző mod', 'gta6-mods'); ?></span>
                <div class="mt-2 font-semibold text-pink-600"><?php previous_post_link('%link'); ?></div>
            </div>
            <div class="card p-4 md:w-1/2 text-right md:text-left">
                <span class="text-xs uppercase tracking-wide text-gray-400"><?php esc_html_e('Következő mod', 'gta6-mods'); ?></span>
                <div class="mt-2 font-semibold text-pink-600"><?php next_post_link('%link'); ?></div>
            </div>
        </nav>
        <section class="card p-6">
            <div id="comments-container" data-mod-comments-root></div>
        </section>
        <div id="related-mods-container" class="card mt-12" data-related-mods-root></div>
    </main>
<?php
    return;
}

$categories       = get_the_category($post_id);
$breadcrumbs      = [];
$breadcrumbs[]    = [
    'label'   => esc_html__('Home', 'gta6-mods'),
    'url'     => home_url('/'),
    'is_home' => true,
];
$primary_category = $categories ? $categories[0] : null;

if ($primary_category instanceof WP_Term) {
    $ancestors = array_reverse(get_ancestors($primary_category->term_id, 'category'));
    foreach ($ancestors as $ancestor_id) {
        $ancestor = get_term($ancestor_id, 'category');
        if ($ancestor && !is_wp_error($ancestor)) {
            $breadcrumbs[] = [
                'label' => $ancestor->name,
                'url'   => get_term_link($ancestor),
            ];
        }
    }

    $breadcrumbs[] = [
        'label' => $primary_category->name,
        'url'   => get_term_link($primary_category),
    ];
}

$author_id        = (int) get_the_author_meta('ID');
$author_name      = get_the_author();
$author_url       = get_author_posts_url($author_id);
$author_avatar    = get_avatar_url($author_id, ['size' => 96]);
$featured         = gta6_mods_is_post_featured($post_id);
$download_count   = gta6_mods_get_download_count($post_id);
$current_version_data = gta6_mods_get_current_version_for_display($post_id);
$download_url     = !empty($current_version_data['download_url']) ? $current_version_data['download_url'] : gta6_mods_get_mod_download_url($post_id);
$last_download_text = gta6_mods_format_time_ago(gta6_mods_get_last_download_timestamp($post_id));
$view_count       = gta6_mods_get_view_count($post_id);
$version_id       = !empty($current_version_data['id']) ? (int) $current_version_data['id'] : 0;
$version_number   = !empty($current_version_data['number']) ? $current_version_data['number'] : gta6_mods_get_mod_version($post_id);
$file_size        = !empty($current_version_data['size_human']) ? $current_version_data['size_human'] : gta6_mods_get_mod_file_size_display($post_id);
$current_version_scan_url = !empty($current_version_data['virus_scan_url']) ? $current_version_data['virus_scan_url'] : '';
$version_history  = gta6_mods_get_mod_versions_for_display($post_id);
$upload_timestamp   = get_post_time('U', true, $post_id);
$modified_timestamp = get_post_modified_time('U', true, $post_id);
$upload_date      = $upload_timestamp ? date_i18n('F j, Y', $upload_timestamp) : '';
$modified_date    = $modified_timestamp ? date_i18n('F j, Y', $modified_timestamp) : '';
$upload_ago       = $upload_timestamp ? sprintf(esc_html__('%s ago', 'gta6-mods'), human_time_diff($upload_timestamp, current_time('timestamp'))) : '';
$last_download_text = $last_download_text !== '' ? $last_download_text : esc_html__('—', 'gta6-mods');
$file_size        = $file_size !== '' ? $file_size : esc_html__('—', 'gta6-mods');
$version_number   = $version_number !== '' ? $version_number : esc_html__('1.0', 'gta6-mods');
$upload_ago       = $upload_ago !== '' ? $upload_ago : esc_html__('—', 'gta6-mods');
$view_count       = number_format_i18n($view_count);
$download_formatted = number_format_i18n($download_count);
$gallery_images     = function_exists('gta6_mods_get_gallery_images') ? gta6_mods_get_gallery_images($post_id) : [];
$download_button_disabled_attr = $download_url ? '' : ' disabled aria-disabled="true"';

$main_image_alt = get_the_title($post_id);
$gallery_items  = [];
$image_sequence = 1;
$video_sequence = 1;

$normalize_gallery_text = static function ($value) {
    if (!is_string($value)) {
        $value = is_scalar($value) ? (string) $value : '';
    }

    if ('' === $value) {
        return '';
    }

    $stripped = wp_strip_all_tags($value);
    $decoded  = html_entity_decode($stripped, ENT_QUOTES, get_bloginfo('charset'));
    $replaced = preg_replace('/[\-_]+/u', ' ', $decoded);
    $compressed = preg_replace('/\s+/u', ' ', $replaced);

    return trim($compressed);
};

$to_gallery_lower = static function ($value) use ($normalize_gallery_text) {
    $normalized = $normalize_gallery_text($value);
    if ('' === $normalized) {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($normalized, 'UTF-8');
    }

    return strtolower($normalized);
};

$resolve_media_identifier = static function ($source, $fallback = '') use ($normalize_gallery_text, $to_gallery_lower) {
    $path = '';

    if (is_string($source) && '' !== $source) {
        $parsed_path = wp_parse_url($source, PHP_URL_PATH);
        if (is_string($parsed_path) && '' !== $parsed_path) {
            $path = $parsed_path;
        }
    }

    if ('' === $path && is_string($fallback) && '' !== $fallback) {
        $path = $fallback;
    }

    if ('' === $path) {
        return 'media';
    }

    $basename = wp_basename($path);
    $basename = preg_replace('/\.[^.]+$/u', '', $basename);
    $basename = $normalize_gallery_text($basename);

    if ('' === $basename) {
        $basename = $normalize_gallery_text($fallback);
    }

    if ('' === $basename) {
        return 'media';
    }

    return $to_gallery_lower($basename);
};

$build_gallery_alt = static function ($identifier, $label, $sequence) use ($normalize_gallery_text, $to_gallery_lower) {
    $id_part    = $normalize_gallery_text($identifier);
    $label_part = $to_gallery_lower($label);
    $index      = max(1, (int) $sequence);

    if ('' === $id_part) {
        $id_part = 'media';
    }

    if ('' === $label_part) {
        $label_part = 'gta6 mods';
    }

    return trim(sprintf('%s %s %d', $id_part, $label_part, $index));
};

$gallery_mod_title_full   = is_string($main_image_alt) ? $main_image_alt : '';
$gallery_mod_title_simple = $normalize_gallery_text($gallery_mod_title_full);
if ('' === $gallery_mod_title_simple) {
    $gallery_mod_title_simple = __('GTA6 mod', 'gta6-mods');
}

$update_page    = get_page_by_path('update');
$update_base_url = $update_page instanceof WP_Post ? get_permalink($update_page) : home_url('/update/');
$update_mod_url = add_query_arg('mod_id', $post_id, $update_base_url);
$can_edit_mod   = current_user_can('edit_post', $post_id);
$current_user_id = get_current_user_id();
$post_status     = get_post_status($post_id);
$is_pending_post = ('pending' === $post_status);
$is_current_user_author = ($current_user_id === $author_id);
$has_pending_update = gta6mods_mod_has_pending_update($post_id);
$can_bypass_pending = gta6mods_user_can_bypass_pending_lock($current_user_id);
$show_update_button = $can_edit_mod && ((!$is_pending_post) || $can_bypass_pending) && (!$has_pending_update || $can_bypass_pending);
$show_pending_notice = $has_pending_update && ($can_bypass_pending || $is_current_user_author);
$show_author_pending_notice = $is_pending_post && $is_current_user_author;
$update_query_param = isset($_GET['update']) ? strtolower(sanitize_text_field(wp_unslash($_GET['update']))) : '';
$show_update_success_notice = ('sucess' === $update_query_param);

$requested_tab = function_exists('gta6_mods_get_single_mod_requested_tab_slug')
    ? gta6_mods_get_single_mod_requested_tab_slug()
    : 'description';
$available_tabs = [
    'description' => 'tab-description',
    'comments'    => 'tab-comments',
    'changelogs'  => 'tab-changelog',
];
$active_tab = array_key_exists($requested_tab, $available_tabs) ? $requested_tab : 'description';

$tab_urls = function_exists('gta6_mods_get_single_mod_tab_urls')
    ? gta6_mods_get_single_mod_tab_urls($post_id)
    : [];

if (empty($tab_urls)) {
    $permalink = get_permalink($post_id);
    if ($permalink) {
        $base_permalink = trailingslashit($permalink);
        $tab_urls       = [
            'description' => $permalink,
            'comments'    => trailingslashit($base_permalink . 'comments'),
            'changelogs'  => trailingslashit($base_permalink . 'changelogs'),
        ];
    } else {
        $tab_urls = [
            'description' => '',
            'comments'    => '',
            'changelogs'  => '',
        ];
    }
}

if (!empty($gallery_images)) {
    foreach ($gallery_images as $image) {
        $attachment_id = isset($image['attachment_id']) ? (int) $image['attachment_id'] : 0;
        $full_image   = $attachment_id ? wp_get_attachment_image_src($attachment_id, 'full') : false;
        $thumb_small  = $attachment_id ? wp_get_attachment_image_src($attachment_id, 'thumbnail') : false;
        $thumb_large  = $attachment_id ? wp_get_attachment_image_src($attachment_id, 'medium_large') : false;
        $source_url   = $full_image ? $full_image[0] : (isset($image['url']) ? $image['url'] : '');

        if (!$source_url) {
            continue;
        }

        $width  = $full_image ? (int) $full_image[1] : 0;
        $height = $full_image ? (int) $full_image[2] : 0;

        if ($width <= 0) {
            $width = 1920;
        }

        if ($height <= 0) {
            $height = 1080;
        }

        $media_identifier = $resolve_media_identifier($source_url, (string) $attachment_id);
        $image_alt        = $build_gallery_alt($media_identifier, $gallery_mod_title_full, $image_sequence);
        $thumbnail_small_src    = $thumb_small && isset($thumb_small[0]) ? $thumb_small[0] : '';
        $thumbnail_small_width  = $thumb_small && isset($thumb_small[1]) ? (int) $thumb_small[1] : 0;
        $thumbnail_small_height = $thumb_small && isset($thumb_small[2]) ? (int) $thumb_small[2] : 0;
        $thumbnail_large_src    = $thumb_large && isset($thumb_large[0]) ? $thumb_large[0] : '';
        $thumbnail_large_width  = $thumb_large && isset($thumb_large[1]) ? (int) $thumb_large[1] : 0;
        $thumbnail_large_height = $thumb_large && isset($thumb_large[2]) ? (int) $thumb_large[2] : 0;

        if ('' === $thumbnail_large_src) {
            $thumbnail_large_src    = $thumbnail_small_src ? $thumbnail_small_src : $source_url;
            $thumbnail_large_width  = $thumbnail_large_width ? $thumbnail_large_width : ($thumbnail_small_width ? $thumbnail_small_width : $width);
            $thumbnail_large_height = $thumbnail_large_height ? $thumbnail_large_height : ($thumbnail_small_height ? $thumbnail_small_height : $height);
        }

        if ('' === $thumbnail_small_src) {
            $thumbnail_small_src    = $thumbnail_large_src ? $thumbnail_large_src : $source_url;
            $thumbnail_small_width  = $thumbnail_small_width ? $thumbnail_small_width : ($thumbnail_large_width ? $thumbnail_large_width : $width);
            $thumbnail_small_height = $thumbnail_small_height ? $thumbnail_small_height : ($thumbnail_large_height ? $thumbnail_large_height : $height);
        }
        $display_title    = '' !== $gallery_mod_title_full ? $gallery_mod_title_full : $gallery_mod_title_simple;
        $aria_label       = sprintf(
            /* translators: 1: mod title, 2: gallery image sequence number */
            __('Open gallery: %1$s – image #%2$d', 'gta6-mods'),
            $display_title,
            $image_sequence
        );

        $gallery_items[] = [
            'type'        => 'image',
            'src'         => $source_url,
            'width'       => $width,
            'height'      => $height,
            'alt'         => $image_alt,
            'thumbnail'   => $thumbnail_small_src,
            'thumbnail_small'  => $thumbnail_small_src,
            'thumbnail_small_width'  => $thumbnail_small_width,
            'thumbnail_small_height' => $thumbnail_small_height,
            'thumbnail_large'  => $thumbnail_large_src,
            'thumbnail_large_width'  => $thumbnail_large_width,
            'thumbnail_large_height' => $thumbnail_large_height,
            'title'       => $display_title,
            'link_title'  => $display_title,
            'aria_label'  => $aria_label,
            'sequence'    => $image_sequence,
            'identifier'  => $media_identifier,
        ];

        $image_sequence++;
    }
}

if (empty($gallery_items)) {
    $featured_id = get_post_thumbnail_id($post_id);
    if ($featured_id) {
        $full_image   = wp_get_attachment_image_src($featured_id, 'full');
        $thumb_small  = wp_get_attachment_image_src($featured_id, 'thumbnail');
        $thumb_large  = wp_get_attachment_image_src($featured_id, 'medium_large');

        if ($full_image) {
            $media_identifier = $resolve_media_identifier($full_image[0], (string) $featured_id);
            $image_alt        = $build_gallery_alt($media_identifier, $gallery_mod_title_full, $image_sequence);
            $thumbnail_small_src    = $thumb_small && isset($thumb_small[0]) ? $thumb_small[0] : '';
            $thumbnail_small_width  = $thumb_small && isset($thumb_small[1]) ? (int) $thumb_small[1] : 0;
            $thumbnail_small_height = $thumb_small && isset($thumb_small[2]) ? (int) $thumb_small[2] : 0;
            $thumbnail_large_src    = $thumb_large && isset($thumb_large[0]) ? $thumb_large[0] : '';
            $thumbnail_large_width  = $thumb_large && isset($thumb_large[1]) ? (int) $thumb_large[1] : 0;
            $thumbnail_large_height = $thumb_large && isset($thumb_large[2]) ? (int) $thumb_large[2] : 0;

            if ('' === $thumbnail_large_src) {
                $thumbnail_large_src    = $thumbnail_small_src ? $thumbnail_small_src : $full_image[0];
                $thumbnail_large_width  = $thumbnail_large_width ? $thumbnail_large_width : ($thumbnail_small_width ? $thumbnail_small_width : (int) $full_image[1]);
                $thumbnail_large_height = $thumbnail_large_height ? $thumbnail_large_height : ($thumbnail_small_height ? $thumbnail_small_height : (int) $full_image[2]);
            }

            if ('' === $thumbnail_small_src) {
                $thumbnail_small_src    = $thumbnail_large_src;
                $thumbnail_small_width  = $thumbnail_small_width ? $thumbnail_small_width : $thumbnail_large_width;
                $thumbnail_small_height = $thumbnail_small_height ? $thumbnail_small_height : $thumbnail_large_height;
            }
            $display_title    = '' !== $gallery_mod_title_full ? $gallery_mod_title_full : $gallery_mod_title_simple;
            $aria_label       = sprintf(
                /* translators: 1: mod title, 2: gallery image sequence number */
                __('Open gallery: %1$s – image #%2$d', 'gta6-mods'),
                $display_title,
                $image_sequence
            );

            $gallery_items[] = [
                'type'        => 'image',
                'src'         => $full_image[0],
                'width'       => (int) $full_image[1],
                'height'      => (int) $full_image[2],
                'alt'         => $image_alt,
                'thumbnail'   => $thumbnail_small_src,
                'thumbnail_small'  => $thumbnail_small_src,
                'thumbnail_small_width'  => $thumbnail_small_width,
                'thumbnail_small_height' => $thumbnail_small_height,
                'thumbnail_large'  => $thumbnail_large_src,
                'thumbnail_large_width'  => $thumbnail_large_width,
                'thumbnail_large_height' => $thumbnail_large_height,
                'title'       => $display_title,
                'link_title'  => $display_title,
                'aria_label'  => $aria_label,
                'sequence'    => $image_sequence,
                'identifier'  => $media_identifier,
            ];

            $image_sequence++;
        }
    }
}

if (empty($gallery_items)) {
    $placeholder = gta6_mods_get_placeholder('featured');
    $media_identifier = $resolve_media_identifier($placeholder, 'placeholder');
    $image_alt        = $build_gallery_alt($media_identifier, $gallery_mod_title_full, $image_sequence);
    $display_title    = '' !== $gallery_mod_title_full ? $gallery_mod_title_full : $gallery_mod_title_simple;
    $aria_label       = sprintf(
        /* translators: 1: mod title, 2: gallery image sequence number */
        __('Open gallery: %1$s – image #%2$d', 'gta6-mods'),
        $display_title,
        $image_sequence
    );

    $gallery_items[] = [
        'type'        => 'image',
        'src'         => $placeholder,
        'width'       => 1920,
        'height'      => 1080,
        'alt'         => $image_alt,
        'thumbnail'   => $placeholder,
        'thumbnail_small'  => $placeholder,
        'thumbnail_small_width'  => 1920,
        'thumbnail_small_height' => 1080,
        'thumbnail_large'  => $placeholder,
        'thumbnail_large_width'  => 1920,
        'thumbnail_large_height' => 1080,
        'title'       => $display_title,
        'link_title'  => $display_title,
        'aria_label'  => $aria_label,
        'sequence'    => $image_sequence,
        'identifier'  => $media_identifier,
    ];

    $image_sequence++;
}

$video_gallery_items = [];
if (function_exists('gta6mods_get_mod_videos')) {
    $videos            = gta6mods_get_mod_videos($post_id, 'approved');
    $video_count       = is_array($videos) ? count($videos) : 0;
    $mod_title_text    = get_the_title($post_id);
    $mod_author_id     = (int) get_post_field('post_author', $post_id);
    $can_moderate      = current_user_can('moderate_comments');
    $is_mod_author     = $current_user_id > 0 && $current_user_id === $mod_author_id;
    $can_manage_videos = $can_moderate || $is_mod_author;

    if (!is_string($mod_title_text) || '' === $mod_title_text) {
        $mod_title_text = __('Mod video', 'gta6-mods');
    }

    if ($video_count > 0) {
        foreach ($videos as $video) {
            $user_id       = isset($video['submitted_by']) ? (int) $video['submitted_by'] : 0;
            $user_nicename = isset($video['user_nicename']) ? sanitize_title($video['user_nicename']) : '';
            $author_url    = '';

            if ($user_id > 0) {
                if ('' !== $user_nicename) {
                    $author_url = get_author_posts_url($user_id, $user_nicename);
                }

                if (!$author_url) {
                    $author_url = get_author_posts_url($user_id);
                }
            }

            if (!$author_url) {
                $author_url = home_url('/');
            }

            $video_title = isset($video['video_title']) && '' !== $video['video_title']
                ? $video['video_title']
                : $mod_title_text;

            $thumbnail        = '';
            $thumbnail_small  = '';
            $thumbnail_large  = '';
            $thumb_small_w    = 0;
            $thumb_small_h    = 0;
            $thumb_large_w    = 0;
            $thumb_large_h    = 0;

            if (!empty($video['thumbnail_small_url'])) {
                $thumbnail_small = $video['thumbnail_small_url'];
            }

            if (!empty($video['thumbnail_large_url'])) {
                $thumbnail_large = $video['thumbnail_large_url'];
            }

            if (!empty($video['thumbnail_small_width'])) {
                $thumb_small_w = (int) $video['thumbnail_small_width'];
            }

            if (!empty($video['thumbnail_small_height'])) {
                $thumb_small_h = (int) $video['thumbnail_small_height'];
            }

            if (!empty($video['thumbnail_large_width'])) {
                $thumb_large_w = (int) $video['thumbnail_large_width'];
            }

            if (!empty($video['thumbnail_large_height'])) {
                $thumb_large_h = (int) $video['thumbnail_large_height'];
            }

            if (!empty($video['thumbnail_url'])) {
                $thumbnail = $video['thumbnail_url'];
            } elseif (!empty($video['thumbnail_path'])) {
                $thumbnail = home_url($video['thumbnail_path']);
            } else {
                $thumbnail = sprintf('https://i.ytimg.com/vi/%s/hqdefault.jpg', rawurlencode($video['youtube_id']));
            }

            if ('' === $thumbnail_large) {
                $thumbnail_large = $thumbnail;
                if (0 === $thumb_large_w) {
                    $thumb_large_w = 1920;
                }
                if (0 === $thumb_large_h) {
                    $thumb_large_h = 1080;
                }
            }

            if ('' === $thumbnail_small) {
                $thumbnail_small = $thumbnail;
                if (0 === $thumb_small_w) {
                    $thumb_small_w = $thumb_large_w ? $thumb_large_w : 1920;
                }
                if (0 === $thumb_small_h) {
                    $thumb_small_h = $thumb_large_h ? $thumb_large_h : 1080;
                }
            }

            $video_db_id  = isset($video['id']) ? (int) $video['id'] : 0;
            $is_featured  = !empty($video['is_featured']);
            $report_count = isset($video['report_count']) ? (int) $video['report_count'] : 0;
            $has_reported = false;

            if ($current_user_id > 0 && function_exists('gta6mods_has_user_reported_video') && $video_db_id > 0) {
                $has_reported = gta6mods_has_user_reported_video($video_db_id, $current_user_id);
            }

            $video_link_title = $video_title;
            if ('' === $video_link_title) {
                $video_link_title = $gallery_mod_title_full;
            }

            $video_identifier = $resolve_media_identifier($thumbnail, isset($video['youtube_id']) ? $video['youtube_id'] : '');
            $video_alt        = $build_gallery_alt($video_identifier, $video_link_title, $video_sequence);

            $video_aria_label = sprintf(
                /* translators: 1: video title, 2: video sequence number */
                __('Watch YouTube video: %1$s – video #%2$d', 'gta6-mods'),
                $video_link_title,
                $video_sequence
            );

            $video_gallery_items[] = [
                'type'        => 'video',
                'src'         => $thumbnail_large,
                'width'       => 1920,
                'height'      => 1080,
                'alt'         => $video_alt,
                'thumbnail'   => $thumbnail_small,
                'thumbnail_small'  => $thumbnail_small,
                'thumbnail_small_width'  => $thumb_small_w,
                'thumbnail_small_height' => $thumb_small_h,
                'thumbnail_large'  => $thumbnail_large,
                'thumbnail_large_width'  => $thumb_large_w,
                'thumbnail_large_height' => $thumb_large_h,
                'title'       => $video_link_title,
                'link_title'  => $video_link_title,
                'aria_label'  => $video_aria_label,
                'sequence'    => $video_sequence,
                'identifier'  => $video_identifier,
                'video'       => [
                    'youtube_id'   => isset($video['youtube_id']) ? $video['youtube_id'] : '',
                    'display_name' => isset($video['display_name']) ? $video['display_name'] : '',
                    'profile_url'  => $author_url,
                    'video_id'     => $video_db_id,
                    'video_title'  => $video_title,
                    'status'       => isset($video['status']) ? $video['status'] : 'approved',
                    'is_reported'  => $has_reported,
                    'is_featured'  => $is_featured,
                    'can_manage'   => $can_manage_videos,
                    'can_feature'  => $can_manage_videos,
                    'report_count' => $report_count,
                ],
            ];
            $video_sequence++;
        }
    }
}

if (!empty($video_gallery_items)) {
    $gallery_items = array_merge($gallery_items, $video_gallery_items);
}

if (!empty($gallery_items)) {
    $featured_video_index = null;

    foreach ($gallery_items as $candidate_index => $candidate_item) {
        if (isset($candidate_item['type']) && 'video' === $candidate_item['type']) {
            $video_details = isset($candidate_item['video']) ? $candidate_item['video'] : [];
            if (!empty($video_details['is_featured'])) {
                $featured_video_index = $candidate_index;
                break;
            }
        }
    }

    if (null !== $featured_video_index && $featured_video_index > 0 && isset($gallery_items[$featured_video_index])) {
        $featured_item = $gallery_items[$featured_video_index];
        unset($gallery_items[$featured_video_index]);
        array_unshift($gallery_items, $featured_item);
        $gallery_items = array_values($gallery_items);
    }
}

$is_user_logged_in        = is_user_logged_in();
$is_liked_main            = gta6_mods_get_user_like_status($post_id);
$like_count_main          = gta6_mods_get_like_count($post_id);
$like_total_display       = number_format_i18n($like_count_main);
$is_bookmarked_main       = gta6_mods_is_mod_bookmarked_by_user($post_id);
$bookmark_active_classes   = 'is-active';
$bookmark_inactive_classes = 'is-inactive';
$like_action_active_classes   = 'is-active';
$like_action_inactive_classes = 'is-inactive';


$default_gallery_image = null;
if (!empty($gallery_items)) {
    foreach ($gallery_items as $candidate_item) {
        if (!isset($candidate_item['type']) || 'video' !== $candidate_item['type']) {
            $default_gallery_image = $candidate_item;
            break;
        }
    }
}

$main_media_data   = $gallery_items[0];
$main_media_type   = isset($main_media_data['type']) ? $main_media_data['type'] : 'image';
$main_media_url    = isset($main_media_data['src']) ? $main_media_data['src'] : '';
$main_media_alt    = isset($main_media_data['alt']) ? $main_media_data['alt'] : '';
$main_media_title  = isset($main_media_data['link_title']) ? $main_media_data['link_title'] : $mod_title_text;
$main_media_aria   = isset($main_media_data['aria_label']) ? $main_media_data['aria_label'] : $main_media_title;
$main_media_width  = isset($main_media_data['width']) ? (int) $main_media_data['width'] : 1920;
$main_media_height = isset($main_media_data['height']) ? (int) $main_media_data['height'] : 1080;
$main_media_thumb  = isset($main_media_data['thumbnail']) ? $main_media_data['thumbnail'] : $main_media_url;

$default_image_payload = null;
if (is_array($default_gallery_image)) {
    $default_image_payload = [
        'src'        => isset($default_gallery_image['src']) ? $default_gallery_image['src'] : '',
        'thumbnail'  => isset($default_gallery_image['thumbnail']) ? $default_gallery_image['thumbnail'] : (isset($default_gallery_image['src']) ? $default_gallery_image['src'] : ''),
        'thumbnail_small'  => isset($default_gallery_image['thumbnail_small']) ? $default_gallery_image['thumbnail_small'] : (isset($default_gallery_image['thumbnail']) ? $default_gallery_image['thumbnail'] : (isset($default_gallery_image['src']) ? $default_gallery_image['src'] : '')),
        'thumbnail_small_width'  => isset($default_gallery_image['thumbnail_small_width']) ? (int) $default_gallery_image['thumbnail_small_width'] : (isset($default_gallery_image['width']) ? (int) $default_gallery_image['width'] : 1920),
        'thumbnail_small_height' => isset($default_gallery_image['thumbnail_small_height']) ? (int) $default_gallery_image['thumbnail_small_height'] : (isset($default_gallery_image['height']) ? (int) $default_gallery_image['height'] : 1080),
        'thumbnail_large'  => isset($default_gallery_image['thumbnail_large']) ? $default_gallery_image['thumbnail_large'] : (isset($default_gallery_image['src']) ? $default_gallery_image['src'] : ''),
        'thumbnail_large_width'  => isset($default_gallery_image['thumbnail_large_width']) ? (int) $default_gallery_image['thumbnail_large_width'] : (isset($default_gallery_image['width']) ? (int) $default_gallery_image['width'] : 1920),
        'thumbnail_large_height' => isset($default_gallery_image['thumbnail_large_height']) ? (int) $default_gallery_image['thumbnail_large_height'] : (isset($default_gallery_image['height']) ? (int) $default_gallery_image['height'] : 1080),
        'width'      => isset($default_gallery_image['width']) ? (int) $default_gallery_image['width'] : 1920,
        'height'     => isset($default_gallery_image['height']) ? (int) $default_gallery_image['height'] : 1080,
        'alt'        => isset($default_gallery_image['alt']) ? $default_gallery_image['alt'] : '',
        'title'      => isset($default_gallery_image['link_title']) ? $default_gallery_image['link_title'] : $mod_title_text,
        'aria'       => isset($default_gallery_image['aria_label']) ? $default_gallery_image['aria_label'] : $mod_title_text,
        'sequence'   => isset($default_gallery_image['sequence']) ? (int) $default_gallery_image['sequence'] : 1,
        'identifier' => isset($default_gallery_image['identifier']) ? $default_gallery_image['identifier'] : '',
    ];
}

$featured_default_image_json = $default_image_payload ? wp_json_encode($default_image_payload) : '';

$gallery_count            = count($gallery_items);
$thumbnail_count          = max($gallery_count - 1, 0);
$has_gallery_thumbnails = $thumbnail_count > 0;
$remaining_gallery_thumbnails = max($thumbnail_count - 5, 0);
$show_gallery_load_more = $remaining_gallery_thumbnails > 0;

$permalink = get_permalink($post_id);
$share_title = wp_strip_all_tags(get_the_title($post_id));

$more_by_author_mods = gta6_mods_get_more_by_author_cards($author_id, $post_id, 2);
if (!empty($more_by_author_mods)) {
    $more_by_author_ids = array_filter(array_map('absint', wp_list_pluck($more_by_author_mods, 'id')));
    if (!empty($more_by_author_ids)) {
        update_meta_cache('post', $more_by_author_ids);
    }
}
?>
<main class="container mx-auto p-4 lg:p-6">
    <?php if ($show_update_success_notice) : ?>
        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sky-900 flex items-start gap-3">
            <i class="fa-solid fa-circle-info mt-1 text-sky-500" aria-hidden="true"></i>
            <div>
                <p class="font-semibold"><?php esc_html_e('Update submitted successfully', 'gta6-mods'); ?></p>
                <p class="text-sm"><?php esc_html_e('Your update request was received. Once it passes moderation it will appear publicly on this mod page.', 'gta6-mods'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($show_pending_notice) : ?>
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900 flex items-start gap-3">
            <i class="fa-solid fa-clock mt-1 text-amber-500" aria-hidden="true"></i>
            <div>
                <p class="font-semibold"><?php esc_html_e('An update is awaiting review', 'gta6-mods'); ?></p>
                <p class="text-sm"><?php esc_html_e('A submitted update for this mod is currently pending moderation. Please wait until it is approved or rejected before submitting another update.', 'gta6-mods'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($show_author_pending_notice) : ?>
        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sky-900 flex items-start gap-3">
            <i class="fa-solid fa-circle-info mt-1 text-sky-500" aria-hidden="true"></i>
            <div>
                <p class="font-semibold"><?php esc_html_e('Your mod is awaiting moderation', 'gta6-mods'); ?></p>
                <p class="text-sm"><?php esc_html_e('Thanks for uploading your mod! The files were received and the page has been created, but it is still pending review. Our moderation team will double-check everything within a few minutes and publish it for everyone once it is approved.', 'gta6-mods'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <nav class="text-sm text-gray-500 mb-2" aria-label="<?php esc_attr_e('Breadcrumb', 'gta6-mods'); ?>">
        <ol class="breadcrumb-trail flex flex-wrap items-center gap-1" itemscope itemtype="https://schema.org/BreadcrumbList">
            <?php foreach ($breadcrumbs as $index => $crumb) :
                $position        = $index + 1;
                $is_last         = ($index === count($breadcrumbs) - 1);
                $label           = isset($crumb['label']) ? $crumb['label'] : '';
                $label_text      = wp_strip_all_tags($label);
                $crumb_url       = isset($crumb['url']) ? $crumb['url'] : '';
                $title_attribute = $label_text ? sprintf(
                    /* translators: %s: breadcrumb label */
                    esc_html__('View %s', 'gta6-mods'),
                    $label_text
                ) : '';
                $aria_attribute = $label_text ? sprintf(
                    /* translators: %s: breadcrumb label */
                    esc_html__('Navigate to %s', 'gta6-mods'),
                    $label_text
                ) : '';
                $is_home_crumb  = !empty($crumb['is_home']);
                $should_link    = !empty($crumb_url);
            ?>
                <li class="flex items-center gap-1" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <?php if ($should_link) : ?>
                        <a
                            href="<?php echo esc_url($crumb_url); ?>"
                            class="breadcrumb-link hover:text-pink-600"
                            itemprop="item"
                            title="<?php echo esc_attr($title_attribute); ?>"
                            aria-label="<?php echo esc_attr($aria_attribute); ?>"
                            <?php if ($is_home_crumb) : ?>rel="home"<?php endif; ?>
                            <?php if ($is_last) : ?>aria-current="page"<?php endif; ?>
                        >
                            <span itemprop="name"><?php echo esc_html($label_text); ?></span>
                        </a>
                    <?php else : ?>
                        <span class="breadcrumb-current" itemprop="name"><?php echo esc_html($label_text); ?></span>
                    <?php endif; ?>
                    <meta itemprop="position" content="<?php echo esc_attr((string) $position); ?>">
                    <?php if (!$is_last) : ?>
                        <span class="breadcrumb-separator" aria-hidden="true">&raquo;</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-3 lg:gap-4 mb-4">
        <div class="flex-grow min-w-0 max-w-[760px]">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight">
                <?php if ($featured) : ?>
                    <i class="fa-solid fa-star text-yellow-400" aria-hidden="true"></i>
                <?php endif; ?>
                <span class="break-words"><?php the_title(); ?></span><?php if ($version_number) : ?>&nbsp;<span class="text-xl md:text-2xl font-semibold text-gray-400"><?php echo esc_html($version_number); ?></span>
                <?php endif; ?>
            </h1>
            <div class="flex items-center flex-wrap gap-x-5 gap-y-2 text-gray-500 text-sm mt-2">
                <span class="flex items-center">
                    <?php esc_html_e('by', 'gta6-mods'); ?>
                    <a href="<?php echo esc_url($author_url); ?>" class="font-semibold text-amber-600 hover:underline ml-1"><?php echo esc_html($author_name); ?></a>
                </span>
                <span class="flex items-center" aria-label="<?php esc_attr_e('Total downloads', 'gta6-mods'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-1.5 text-gray-500"><path d="M12 15V3"></path><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path></svg>
                    <span class="text-gray-500" data-download-count=""><?php echo esc_html($download_formatted); ?></span>
                </span>
                <span class="flex items-center" aria-label="<?php esc_attr_e('Total likes', 'gta6-mods'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-1.5 text-gray-500"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path></svg>
                    <span class="text-gray-500 mod-like-total" data-post-id="<?php echo esc_attr($post_id); ?>"><?php echo esc_html($like_total_display); ?></span>
                </span>
            </div>
        </div>
        <div class="flex flex-col items-center md:items-end">
            <div class="flex items-center space-x-2 w-full lg:w-auto">
                <button id="gta6mods-download-button" type="button" class="btn-download font-bold py-3 px-5 rounded-[12px] transition flex items-center justify-center w-full md:w-auto download-button" data-download-url="<?php echo esc_url($download_url); ?>" <?php echo $version_id > 0 ? ' data-version-id="' . esc_attr((string) $version_id) . '"' : ''; ?><?php echo $download_button_disabled_attr ? ' ' . $download_button_disabled_attr : ''; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 max-[350px]:mr-0 mr-2"><path d="M12 15V3"></path><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path></svg>
                    <span class="download-text"><?php esc_html_e('Download', 'gta6-mods'); ?></span>
                </button>
                <?php
                $like_button_state_class     = $is_liked_main ? $like_action_active_classes : $like_action_inactive_classes;
                $bookmark_button_state_class = $is_bookmarked_main ? $bookmark_active_classes : $bookmark_inactive_classes;
                ?>
                <button
                    type="button"
                    class="mod-hero-icon-button mod-like-button <?php echo esc_attr($like_button_state_class); ?> w-11 h-11 hover:bg-gray-200 hover:text-pink-600 transition flex-shrink-0"
                    data-post-id="<?php echo esc_attr($post_id); ?>"
                    data-like-active-class="<?php echo esc_attr($like_action_active_classes); ?>"
                    data-like-inactive-class="<?php echo esc_attr($like_action_inactive_classes); ?>"
                    data-like-button="true"
                    aria-pressed="<?php echo $is_liked_main ? 'true' : 'false'; ?>"
                    <?php if (!$is_user_logged_in) : ?>disabled title="<?php echo esc_attr__('Jelentkezz be a kedveléshez', 'gta6-mods'); ?>"<?php endif; ?>
                >
                    <span class="sr-only"><?php esc_html_e('Kedvelés', 'gta6-mods'); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path></svg>
                </button>
                <button
                    type="button"
                    class="mod-hero-icon-button mod-bookmark-button <?php echo esc_attr($bookmark_button_state_class); ?> w-11 h-11 hover:bg-gray-200 hover:text-pink-600 transition flex-shrink-0"
                    data-bookmark-active-class="<?php echo esc_attr($bookmark_active_classes); ?>"
                    data-bookmark-inactive-class="<?php echo esc_attr($bookmark_inactive_classes); ?>"
                    data-bookmark-button="true"
                    aria-pressed="<?php echo $is_bookmarked_main ? 'true' : 'false'; ?>"
                    <?php if (!$is_user_logged_in) : ?>disabled title="<?php echo esc_attr__('Jelentkezz be a mentéshez', 'gta6-mods'); ?>"<?php endif; ?>
                >
                    <span class="sr-only" data-bookmark-label=""><?php echo $is_bookmarked_main ? esc_html__('Bookmarked', 'gta6-mods') : esc_html__('Bookmark', 'gta6-mods'); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                </button>
                <div class="relative">
                    <button type="button" class="mod-hero-icon-button w-11 h-11 hover:bg-gray-200 hover:text-pink-600 transition flex-shrink-0" data-more-options-toggle aria-haspopup="true" aria-expanded="false">
                        <span class="sr-only"><?php esc_html_e('További műveletek', 'gta6-mods'); ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                    </button>
                    <div class="more-options-dropdown hidden" data-more-options-menu role="menu" aria-hidden="true">
                        <?php if ($show_update_button) : ?>
                            <a href="<?php echo esc_url($update_mod_url); ?>" class="more-options-item" role="menuitem">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                <span><?php esc_html_e('Edit / Update', 'gta6-mods'); ?></span>
                            </a>
                        <?php else : ?>
                            <span class="more-options-item is-placeholder" role="menuitem" aria-disabled="true">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                <span><?php esc_html_e('Edit / Update', 'gta6-mods'); ?></span>
                            </span>
                        <?php endif; ?>
                        <span class="more-options-item is-placeholder" role="menuitem" aria-disabled="true">
                            <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                            <span><?php esc_html_e('Pin (coming soon)', 'gta6-mods'); ?></span>
                        </span>
                        <span class="more-options-item is-placeholder" role="menuitem" aria-disabled="true">
                            <i class="fa-solid fa-flag" aria-hidden="true"></i>
                            <span><?php esc_html_e('Report (coming soon)', 'gta6-mods'); ?></span>
                        </span>
                        <span class="more-options-item is-placeholder" role="menuitem" aria-disabled="true">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            <span><?php esc_html_e('Delete (coming soon)', 'gta6-mods'); ?></span>
                        </span>
                    </div>
                </div>
            </div>
            <?php if ($current_version_scan_url) : ?>
                <a
                    href="<?php echo esc_url($current_version_scan_url); ?>"
                    class="mt-3 lg:mt-2 inline-flex items-center text-xs font-medium text-green-700 hover:text-green-800"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="<?php echo esc_attr__('View the virus scan report', 'gta6-mods'); ?>"
                >
                    <i class="fas fa-shield-halved mr-1.5" aria-hidden="true"></i>
                    <span class="text-gray-500 hover:text-gray-600"><?php esc_html_e('This file was virus-scanned and is safe to download.', 'gta6-mods'); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
                <?php
                set_query_var(
                    'gta6mods_mod_gallery',
                    [
                        'main_image_url'            => $main_media_url,
                        'main_image_alt'            => $main_media_alt,
                        'main_image_data'           => $main_media_data,
                        'default_image_payload'     => $default_image_payload,
                        'gallery_items'             => $gallery_items,
                        'has_gallery_thumbnails'    => $has_gallery_thumbnails,
                        'remaining_thumbnails'      => $remaining_gallery_thumbnails,
                        'show_gallery_load_more'    => $show_gallery_load_more,
                        'post_id'                   => $post_id,
                        'mod_title'                 => $mod_title_text,
                    ]
                );
                get_template_part('template-parts/single/mod', 'gallery');
                set_query_var('gta6mods_mod_gallery', null);
                ?>

            <div class="card -ml-4 -mr-4 sm:ml-0 sm:mr-0 rounded-none sm:rounded-xl overflow-hidden">
                <?php
                $rating_data_main = gta6_mods_get_rating_data($post_id);
                $user_rating_main = gta6_mods_get_user_rating($post_id);
                $rating_tooltips    = [
                    1 => __('Poor', 'gta6-mods'),
                    2 => __('Fair', 'gta6-mods'),
                    3 => __('Good', 'gta6-mods'),
                    4 => __('Very Good', 'gta6-mods'),
                    5 => __('Excellent', 'gta6-mods'),
                ];
                $rating_average_main_value     = isset($rating_data_main['average']) ? (float) $rating_data_main['average'] : 0.0;
                $rating_average_main_attribute = number_format($rating_average_main_value, 3, '.', '');
                $rating_average_main_display   = round($rating_average_main_value * 2) / 2;
                $rating_full_stars_main        = (int) floor($rating_average_main_display);
                $rating_has_half_star_main     = ($rating_average_main_display - $rating_full_stars_main) >= 0.5;
                ?>
                <div class="border-t border-b border-gray-200 py-3 px-4 md:px-6">
                    <div class="flex flex-row justify-between items-center text-gray-600 gap-4">
                        <div class="flex items-center flex-wrap gap-4 md:gap-6">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-download text-3xl text-pink-500" aria-hidden="true"></i>
                                <div>
                                    <p class="font-bold text-base text-gray-800" data-download-count><?php echo esc_html($download_formatted); ?></p>
                                    <p class="text-xs uppercase text-gray-400"><?php esc_html_e('Letöltés', 'gta6-mods'); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <i class="fa-regular fa-thumbs-up text-3xl text-pink-500" aria-hidden="true"></i>
                                <div>
                                    <p class="font-bold text-base text-gray-800 mod-like-total" data-post-id="<?php echo esc_attr($post_id); ?>"><?php echo esc_html($like_total_display); ?></p>
                                    <p class="text-xs uppercase text-gray-400"><?php esc_html_e('Kedvelés', 'gta6-mods'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="text-left md:text-right">
                            <div class="text-left md:text-right">
                                  <div
                                      class="mod-rating-container flex flex-col items-start md:items-end"
                                      data-post-id="<?php echo esc_attr($post_id); ?>"
                                      data-user-rating="<?php echo esc_attr($user_rating_main); ?>"
                                      data-average-rating="<?php echo esc_attr($rating_average_main_attribute); ?>"
                                      data-is-logged-in="<?php echo $is_user_logged_in ? 'true' : 'false'; ?>"
                                  >

                                      <div class="flex gap-1">
                                          <?php for ($i = 1; $i <= 5; $i++) : ?>
                                              <?php
                                              $button_title = $is_user_logged_in ? ($rating_tooltips[$i] ?? '') : __('Jelentkezz be az értékeléshez', 'gta6-mods');
                                              $star_classes = ['rating-star', 'transition-colors', 'hover:text-yellow-500', 'focus:text-yellow-500'];
                                              $icon_classes = ['fa-solid'];

                                              if ($user_rating_main > 0) {
                                                  $is_active      = $i <= $user_rating_main;
                                                  $star_classes[] = $is_active ? 'active text-yellow-400' : 'text-gray-300';
                                                  $icon_classes[] = 'fa-star';
                                                  $average_state  = 'none';
                                              } else {
                                                  $is_full        = $i <= $rating_full_stars_main;
                                                  $is_half        = !$is_full && $rating_has_half_star_main && $i === ($rating_full_stars_main + 1);
                                                  $average_state  = $is_half ? 'half' : ($is_full ? 'full' : 'none');

                                                  if ($is_full || $is_half) {
                                                      $star_classes[] = 'text-yellow-400';
                                                      $icon_classes[] = $is_half ? 'fa-star-half-stroke' : 'fa-star';
                                                  } else {
                                                      $star_classes[] = 'text-gray-300';
                                                      $icon_classes[] = 'fa-star';
                                                  }
                                              }
                                              ?>
                                              <button
                                                  type="button"
                                                  class="<?php echo esc_attr(implode(' ', $star_classes)); ?>"
                                                  data-rating="<?php echo esc_attr($i); ?>"
                                                  data-average-state="<?php echo esc_attr($average_state); ?>"
                                                  title="<?php echo esc_attr($button_title); ?>"
                                              >
                                                  <i class="<?php echo esc_attr(implode(' ', $icon_classes)); ?>"></i>
                                              </button>
                                          <?php endfor; ?>
                                      </div>

                                    <p class="text-xs mt-1 text-gray-400">
                                        <span class="rating-average font-bold"><?php echo esc_html(number_format_i18n($rating_data_main['average'], 1)); ?></span> / 5
                                        <span class="rating-count">(<?php echo esc_html(number_format_i18n($rating_data_main['count'])); ?> szavazat)</span>
                                    </p>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="flex border-b border-gray-200">
                        <a
                            href="<?php echo esc_url($tab_urls['description']); ?>"
                            class="tab-btn px-4 sm:px-6 py-3 content-center font-semibold<?php echo ('description' === $active_tab) ? ' active text-pink-600' : ' text-gray-600 hover:text-pink-600'; ?>"
                            data-tab-key="description"
                            data-tab-target="tab-description"
                            <?php if ('description' === $active_tab) : ?>aria-current="page"<?php endif; ?>
                        ><?php esc_html_e('Leírás', 'gta6-mods'); ?></a>
                        <?php
                        $comment_label_template = esc_html__('Kommentek (%s)', 'gta6-mods');
                        $comment_count_display  = number_format_i18n(get_comments_number($post_id));
                        ?>
                        <a
                            href="<?php echo esc_url($tab_urls['comments']); ?>"
                            class="tab-btn px-4 sm:px-6 py-3 content-center font-semibold<?php echo ('comments' === $active_tab) ? ' active text-pink-600' : ' text-gray-600 hover:text-pink-600'; ?>"
                            data-tab-key="comments"
                            data-tab-target="tab-comments"
                            data-comment-label-template="<?php echo esc_attr($comment_label_template); ?>"
                            <?php if ('comments' === $active_tab) : ?>aria-current="page"<?php endif; ?>
                        >
                            <span data-comment-tab-label><?php echo esc_html(sprintf($comment_label_template, $comment_count_display)); ?></span>
                        </a>
                        <a
                            href="<?php echo esc_url($tab_urls['changelogs']); ?>"
                            class="tab-btn px-4 sm:px-6 py-3 content-center font-semibold<?php echo ('changelogs' === $active_tab) ? ' active text-pink-600' : ' text-gray-600 hover:text-pink-600'; ?>"
                            data-tab-key="changelogs"
                            data-tab-target="tab-changelog"
                            <?php if ('changelogs' === $active_tab) : ?>aria-current="page"<?php endif; ?>
                        ><?php esc_html_e('Changelog', 'gta6-mods'); ?></a>
                    </div>
                    <div class="p-4 md:p-6 text-gray-700 leading-relaxed">
                        <div id="tab-description" class="tab-section<?php echo ('description' === $active_tab) ? '' : ' hidden'; ?>">
                            <h4 class="font-bold text-lg mb-2 text-gray-900"><?php esc_html_e('Leírás', 'gta6-mods'); ?></h4>
                            <div class="prose max-w-none text-gray-700">
                                <?php the_content(); ?>
                            </div>
                            <div class="mt-8 pt-6 border-t border-gray-200">
                                <?php
                                $tags = get_the_tags($post_id);
                                if ($tags) :
                                ?>
                                    <div class="flex flex-wrap items-center gap-2 mb-4">
                                        <?php foreach ($tags as $tag) : ?>
                                            <a href="<?php echo esc_url(get_tag_link($tag)); ?>" class="bg-gray-200 text-gray-700 text-xs font-semibold px-3 py-1 rounded-full hover:bg-gray-300 transition"><?php echo esc_html($tag->name); ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-sm text-gray-500 space-y-1">
                                    <?php if ($upload_date) : ?>
                                        <p><strong class="font-medium text-gray-700"><?php esc_html_e('First Uploaded:', 'gta6-mods'); ?></strong> <?php echo esc_html($upload_date); ?></p>
                                    <?php endif; ?>
                                    <?php if ($modified_date) : ?>
                                        <p><strong class="font-medium text-gray-700"><?php esc_html_e('Last Updated:', 'gta6-mods'); ?></strong> <?php echo esc_html($modified_date); ?></p>
                                    <?php endif; ?>
                                    <p><strong class="font-medium text-gray-700"><?php esc_html_e('Last Downloaded:', 'gta6-mods'); ?></strong> <span data-last-downloaded><?php echo esc_html($last_download_text); ?></span></p>
                                </div>
                            </div>
                        </div>
                        <div id="tab-comments" class="tab-section<?php echo ('comments' === $active_tab) ? '' : ' hidden'; ?>">
                            <div id="comments-container" data-mod-comments-root></div>
                        </div>
                        <div id="tab-changelog" class="tab-section<?php echo ('changelogs' === $active_tab) ? '' : ' hidden'; ?>">
                            <h4 class="font-bold text-lg mb-4 text-gray-900"><?php esc_html_e('Verziótörténet (Changelog)', 'gta6-mods'); ?></h4>
                            <?php if (!empty($version_history)) : ?>
                                <div class="space-y-6">
                                    <?php foreach ($version_history as $history_index => $history_item) :
                                        $is_current_version = $version_id > 0 && isset($history_item['id']) && (int) $history_item['id'] === $version_id;
                                        $history_number = isset($history_item['number']) ? $history_item['number'] : '';
                                        $history_date = isset($history_item['date']) ? $history_item['date'] : '';
                                        $history_raw_date = isset($history_item['raw_date']) ? $history_item['raw_date'] : '';
                                        $history_downloads = isset($history_item['downloads_display']) ? $history_item['downloads_display'] : '';
                                        $history_download_url = isset($history_item['download_url']) ? $history_item['download_url'] : '';
                                        $history_id = isset($history_item['id']) ? (int) $history_item['id'] : 0;
                                        $history_changelog = isset($history_item['changelog']) && is_array($history_item['changelog']) ? $history_item['changelog'] : [];
                                        ?>
                                        <div class="border-b border-gray-200 pb-4">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                <div>
                                                    <h5 class="font-bold text-md text-gray-800 flex items-center gap-2">
                                                        <?php echo esc_html(sprintf(__('Verzió %s', 'gta6-mods'), $history_number)); ?>
                                                        <?php if (!empty($history_item['is_initial'])) : ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-200 text-gray-700"><?php esc_html_e('Initial Release', 'gta6-mods'); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($is_current_version) : ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700"><?php esc_html_e('Aktuális', 'gta6-mods'); ?></span>
                                                        <?php elseif (!empty($history_item['is_pending'])) : ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"><?php esc_html_e('Pending', 'gta6-mods'); ?></span>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <?php
                                                    if (!$history_date && $history_raw_date) {
                                                        $fallback_timestamp = strtotime($history_raw_date);
                                                        if ($fallback_timestamp) {
                                                            $history_date = date_i18n(get_option('date_format'), $fallback_timestamp);
                                                        }
                                                    }

                                                    if ($history_date) :
                                                        $history_date = wp_strip_all_tags($history_date);
                                                        $date_label_template = !empty($history_item['is_pending'])
                                                            ? __('Beküldés dátuma: %s', 'gta6-mods')
                                                            : __('Kiadás dátuma: %s', 'gta6-mods');
                                                    ?>
                                                        <p class="text-sm <?php echo !empty($history_item['is_pending']) ? 'text-amber-700' : 'text-gray-500'; ?>"><?php echo esc_html(sprintf($date_label_template, $history_date)); ?></p>
                                                    <?php
                                                    endif;
                                                    ?>
                                                    <?php if ($history_downloads) : ?>
                                                        <p class="text-xs text-gray-400"><?php esc_html_e('Letöltések:', 'gta6-mods'); ?> <span data-version-downloads="<?php echo esc_attr((string) $history_id); ?>"><?php echo esc_html($history_downloads); ?></span></p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($history_download_url && empty($history_item['is_pending'])) : ?>
                                                    <button type="button" class="border-2 border-pink-500 text-pink-600 font-semibold text-sm py-1 px-3 rounded-lg hover:bg-pink-50 transition" data-download-url="<?php echo esc_url($history_download_url); ?>"<?php echo $history_id > 0 ? ' data-version-id="' . esc_attr((string) $history_id) . '"' : ''; ?>><?php esc_html_e('Letöltés', 'gta6-mods'); ?></button>
                                                    <?php if (!empty($history_item['virus_scan_url'])) : ?>
                                                        <a href="<?php echo esc_url($history_item['virus_scan_url']); ?>" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-green-600 hover:text-green-800 underline transition"><?php esc_html_e('Virus Scan', 'gta6-mods'); ?></a>
                                                    <?php endif; ?>
                                                <?php elseif (!empty($history_item['is_pending'])) : ?>
                                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-amber-700 bg-amber-50 border border-amber-200 py-1 px-3 rounded-lg">
                                                        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                                                        <?php esc_html_e('Awaiting moderation', 'gta6-mods'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($history_changelog)) : ?>
                                                <ul class="list-disc list-inside mt-2 text-sm space-y-1">
                                                    <?php foreach ($history_changelog as $history_change) : ?>
                                                        <li><?php echo esc_html($history_change); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php elseif (!empty($history_item['is_pending'])) : ?>
                                                <p class="mt-2 text-sm text-amber-700">
                                                    <?php esc_html_e('The new version details are currently under moderator review.', 'gta6-mods'); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <p class="text-sm text-gray-500"><?php esc_html_e('Még nincs elérhető verziótörténet.', 'gta6-mods'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <aside class="lg:col-span-1">
            <div class="sticky top-6 space-y-6">
                <div class="card">
                    <div class="p-4">
                        <div class="flex">
                            <img src="<?php echo esc_url($author_avatar ?: 'https://placehold.co/50x50/ec4899/white?text=AV'); ?>" class="rounded-full w-12 h-12 mr-4" alt="<?php echo esc_attr($author_name); ?>" loading="lazy" decoding="async">
                            <div class="flex-grow">
                                <div class="flex items-center space-x-2">
                                    <p class="font-semibold text-gray-900"><?php echo esc_html($author_name); ?></p>
                                    <a href="<?php echo esc_url($author_url); ?>" class="text-gray-400 hover:text-pink-600">
                                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                                        <span class="sr-only"><?php esc_html_e('Author profile', 'gta6-mods'); ?></span>
                                    </a>
                                    <a href="#" class="text-gray-400 hover:text-pink-600">
                                        <i class="fa-brands fa-discord" aria-hidden="true"></i>
                                        <span class="sr-only"><?php esc_html_e('Discord', 'gta6-mods'); ?></span>
                                    </a>
                                </div>
                                <p class="text-xs text-gray-500 mb-2"><?php esc_html_e('Pro Modder', 'gta6-mods'); ?></p>
                                <div class="flex flex-col space-y-2 text-sm">
                                    <a href="#" class="flex items-center justify-center bg-red-600 text-white px-3 py-1.5 rounded-md hover:bg-red-700 transition">
                                        <i class="fa-brands fa-youtube mr-2" aria-hidden="true"></i>
                                        <span>YouTube</span>
                                    </a>
                                    <a href="#" class="flex items-center justify-center bg-blue-800 text-white px-3 py-1.5 rounded-md hover:bg-blue-900 transition">
                                        <i class="fa-brands fa-paypal mr-2" aria-hidden="true"></i>
                                        <span>Donate with PayPal</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-gray-200"></div>
                    <div class="p-4">
                        <h3 class="text-lg font-bold text-gray-900 mb-3"><?php esc_html_e('Mod Infó', 'gta6-mods'); ?></h3>
                        <div class="space-y-2 text-sm text-gray-600">
                            <div class="flex justify-between items-center">
                                <span class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 text-gray-400"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M6 21V9a9 9 0 0 0 9 9"></path></svg><?php esc_html_e('Verzió:', 'gta6-mods'); ?></span>
                                <span class="font-semibold text-gray-800"><?php echo esc_html($version_number); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 text-gray-400"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M10 11h4"></path><path d="M10 17h4"></path></svg><?php esc_html_e('Méret:', 'gta6-mods'); ?></span>
                                <span class="font-semibold text-gray-800"><?php echo esc_html($file_size); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 text-gray-400"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path><path d="M8 18h.01"></path><path d="M12 18h.01"></path><path d="M16 18h.01"></path></svg><?php esc_html_e('Feltöltve:', 'gta6-mods'); ?></span>
                                <span class="font-semibold text-gray-800"><?php echo esc_html($upload_ago); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 text-gray-400"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg><?php esc_html_e('Megtekintés:', 'gta6-mods'); ?></span>
                                <span class="font-semibold text-gray-800" data-mod-view-count><?php echo esc_html($view_count); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 text-gray-400"><path d="M12 15V3"></path><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path></svg><?php esc_html_e('Letöltés:', 'gta6-mods'); ?></span>
                                <span class="font-semibold text-gray-800" data-download-count><?php echo esc_html($download_formatted); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-gray-200"></div>
                    <div class="p-4">
                        <?php
                        set_query_var(
                            'gta6mods_mod_actions',
                            [
                                'post_id'           => $post_id,
                                'is_user_logged_in' => $is_user_logged_in,
                            ]
                        );
                        get_template_part('template-parts/single/mod', 'actions');
                        set_query_var('gta6mods_mod_actions', null);
                        ?>
                    </div>
                </div>

                <?php if (!empty($more_by_author_mods)) : ?>
                    <div class="card">
                        <h3 class="text-lg font-bold text-gray-900 p-4"><?php printf(esc_html__('More by %s', 'gta6-mods'), esc_html($author_name)); ?></h3>
                        <div class="p-2 space-y-3">
                            <?php foreach ($more_by_author_mods as $mod_card) :
                                $more_image = !empty($mod_card['image']) ? $mod_card['image'] : gta6_mods_get_image($mod_card['id'], 'large', 'card');
                                $mod_version = !empty($mod_card['version']) ? $mod_card['version'] : esc_html__('1.0', 'gta6-mods');
                            ?>
                                <a href="<?php echo esc_url($mod_card['permalink']); ?>" class="group block p-2 rounded-lg hover:bg-gray-50">
                                    <div class="relative overflow-hidden rounded-md">
                                        <img src="<?php echo esc_url($more_image); ?>" alt="<?php echo esc_attr($mod_card['title']); ?>" class="w-full h-32 object-cover" loading="lazy" decoding="async">
                                        <div class="absolute bottom-0 left-0 right-0 p-1.5 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                                            <div class="flex justify-between items-center">
                                                <span class="flex items-center font-semibold"><i class="fa-solid fa-star mr-1 text-yellow-400" aria-hidden="true"></i><?php echo esc_html($mod_card['rating']); ?></span>
                                                <div class="flex items-center space-x-2">
                                                    <span class="flex items-center"><i class="fa-solid fa-download mr-1" aria-hidden="true"></i><?php echo esc_html($mod_card['downloads']); ?></span>
                                                    <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1" aria-hidden="true"></i><?php echo esc_html($mod_card['likes']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-2">
                                        <div class="flex justify-between items-start">
                                            <h4 class="font-semibold text-sm text-gray-800 group-hover:text-pink-600 transition pr-2"><?php echo esc_html($mod_card['title']); ?></h4>
                                            <span class="text-xs font-bold bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full flex-shrink-0"><?php echo esc_html($mod_version); ?></span>
                                        </div>
                                        <p class="text-xs text-gray-500"><?php printf(esc_html__('by %s', 'gta6-mods'), esc_html($mod_card['author'])); ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card p-4 text-center">
                    <span class="text-sm text-gray-400"><?php esc_html_e('Hirdetés', 'gta6-mods'); ?></span>
                    <div class="bg-gray-200 w-full h-64 mt-2 rounded-lg flex items-center justify-center">
                        <p class="text-gray-500">300x250 Ad</p>
                    </div>
                </div>

                <div id="related-mods-container" class="card" data-related-mods-root></div>
            </div>
        </aside>
    </div>
</main>

<div id="gta6mods-share-modal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50 transition-opacity duration-300" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm text-center relative">
        <button type="button" class="absolute top-3 right-3 text-gray-400 hover:text-gray-800 transition" data-share-modal-close>
            <i class="fa-solid fa-xmark fa-lg" aria-hidden="true"></i>
            <span class="sr-only"><?php esc_html_e('Bezárás', 'gta6-mods'); ?></span>
        </button>

        <h3 class="text-xl font-bold text-gray-800 mb-2"><?php esc_html_e('Oszd meg ezt a modot!', 'gta6-mods'); ?></h3>
        <p class="text-gray-500 mb-6"><?php esc_html_e('Válaszd ki, hol szeretnéd megosztani.', 'gta6-mods'); ?></p>

        <div class="grid grid-cols-2 gap-3 mb-3">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($permalink); ?>" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-facebook flex items-center justify-center p-3 rounded-lg font-semibold">
                <i class="fa-brands fa-facebook-f mr-2" aria-hidden="true"></i> Facebook
            </a>
            <a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode($permalink); ?>&text=<?php echo rawurlencode($share_title); ?>" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-x flex items-center justify-center p-3 rounded-lg font-semibold">
                <i class="fa-brands fa-x-twitter mr-2" aria-hidden="true"></i> X / Twitter
            </a>
            <a href="https://vk.com/share.php?url=<?php echo rawurlencode($permalink); ?>" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-vk flex items-center justify-center p-3 rounded-lg font-semibold">
                <i class="fa-brands fa-vk mr-2" aria-hidden="true"></i> VK
            </a>
            <a href="https://www.reddit.com/submit?url=<?php echo rawurlencode($permalink); ?>&title=<?php echo rawurlencode($share_title); ?>" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-reddit flex items-center justify-center p-3 rounded-lg font-semibold">
                <i class="fa-brands fa-reddit-alien mr-2" aria-hidden="true"></i> Reddit
            </a>
            <a href="https://api.whatsapp.com/send?text=<?php echo rawurlencode(sprintf(__('Szuper GTA 6 mod! %s', 'gta6-mods'), $permalink)); ?>" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-whatsapp flex items-center justify-center p-3 rounded-lg font-semibold">
                <i class="fa-brands fa-whatsapp mr-2" aria-hidden="true"></i> WhatsApp
            </a>
            <a href="https://bsky.app/intent/compose?text=<?php echo rawurlencode(sprintf(__('Check out this awesome GTA 6 mod! %s', 'gta6-mods'), $permalink)); ?>" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-bluesky flex items-center justify-center p-3 rounded-lg font-semibold">
                <i class="fa-solid fa-square-poll-vertical mr-2" aria-hidden="true"></i> Bluesky
            </a>
        </div>

        <button type="button" class="w-full mt-2 p-3 rounded-lg font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 transition flex items-center justify-center" data-copy-link>
            <i class="fa-solid fa-copy mr-2" aria-hidden="true"></i> <?php esc_html_e('Link másolása', 'gta6-mods'); ?>
        </button>
    </div>
</div>
