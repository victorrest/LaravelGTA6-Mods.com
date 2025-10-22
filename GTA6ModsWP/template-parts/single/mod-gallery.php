<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = get_query_var('gta6mods_mod_gallery');
if (!is_array($context)) {
    return;
}

$main_image_url         = isset($context['main_image_url']) ? $context['main_image_url'] : '';
$main_image_alt         = isset($context['main_image_alt']) ? $context['main_image_alt'] : '';
$main_image_data        = isset($context['main_image_data']) ? $context['main_image_data'] : [];
$gallery_items          = isset($context['gallery_items']) ? (array) $context['gallery_items'] : [];
$has_gallery_thumbnails = !empty($context['has_gallery_thumbnails']);
$remaining_thumbnails   = isset($context['remaining_thumbnails']) ? (int) $context['remaining_thumbnails'] : 0;
$show_gallery_load_more = !empty($context['show_gallery_load_more']);
$post_id                = isset($context['post_id']) ? (int) $context['post_id'] : get_the_ID();
$raw_mod_title = isset($context['mod_title']) ? $context['mod_title'] : get_the_title($post_id);
$default_image_payload  = isset($context['default_image_payload']) && is_array($context['default_image_payload'])
    ? $context['default_image_payload']
    : null;
$mod_title     = '';

if (is_string($raw_mod_title)) {
    $mod_title = $raw_mod_title;
} elseif (is_scalar($raw_mod_title)) {
    $mod_title = (string) $raw_mod_title;
}

if ('' !== $mod_title) {
    $mod_title = trim($mod_title);
}

$main_media_type   = isset($main_image_data['type']) ? $main_image_data['type'] : 'image';
$main_image_title  = isset($main_image_data['link_title']) ? $main_image_data['link_title'] : $mod_title;
$main_image_aria   = isset($main_image_data['aria_label']) ? $main_image_data['aria_label'] : '';
$main_image_width  = isset($main_image_data['width']) ? (int) $main_image_data['width'] : 1920;
$main_image_height = isset($main_image_data['height']) ? (int) $main_image_data['height'] : 1080;
$main_image_sizes  = '(min-width: 1024px) 64vw, 100vw';
$main_image_thumb_small = isset($main_image_data['thumbnail_small']) && $main_image_data['thumbnail_small']
    ? $main_image_data['thumbnail_small']
    : (isset($main_image_data['thumbnail']) ? $main_image_data['thumbnail'] : $main_image_url);
$main_image_thumb_small_width = isset($main_image_data['thumbnail_small_width']) ? (int) $main_image_data['thumbnail_small_width'] : 0;
$main_image_thumb_small_height = isset($main_image_data['thumbnail_small_height']) ? (int) $main_image_data['thumbnail_small_height'] : 0;
$main_image_thumb_large = isset($main_image_data['thumbnail_large']) && $main_image_data['thumbnail_large']
    ? $main_image_data['thumbnail_large']
    : $main_image_thumb_small;
$main_image_thumb_large_width = isset($main_image_data['thumbnail_large_width']) ? (int) $main_image_data['thumbnail_large_width'] : 0;
$main_image_thumb_large_height = isset($main_image_data['thumbnail_large_height']) ? (int) $main_image_data['thumbnail_large_height'] : 0;

if ($main_image_thumb_small_width <= 0) {
    $main_image_thumb_small_width = $main_image_width;
}

if ($main_image_thumb_small_height <= 0) {
    $main_image_thumb_small_height = $main_image_height;
}

if ($main_image_thumb_large_width <= 0) {
    $main_image_thumb_large_width = $main_image_width;
}

if ($main_image_thumb_large_height <= 0) {
    $main_image_thumb_large_height = $main_image_height;
}

$default_image_json = $default_image_payload ? wp_json_encode($default_image_payload) : '';
$thumbnail_sizes    = '(max-width: 639px) 33vw, 20vw';
$main_video_data    = ('video' === $main_media_type && isset($main_image_data['video']) && is_array($main_image_data['video']))
    ? $main_image_data['video']
    : null;
$main_video_id       = $main_video_data && isset($main_video_data['video_id']) ? (int) $main_video_data['video_id'] : 0;
$main_video_youtube  = $main_video_data && isset($main_video_data['youtube_id']) ? $main_video_data['youtube_id'] : '';
$main_video_title    = $main_video_data && isset($main_video_data['video_title']) ? $main_video_data['video_title'] : $main_image_title;
$main_video_added_by = $main_video_data && isset($main_video_data['display_name']) ? $main_video_data['display_name'] : '';
$main_video_profile  = $main_video_data && isset($main_video_data['profile_url']) ? $main_video_data['profile_url'] : home_url('/');
$main_video_status   = $main_video_data && isset($main_video_data['status']) ? $main_video_data['status'] : 'approved';
$main_video_reports  = $main_video_data && isset($main_video_data['report_count']) ? (int) $main_video_data['report_count'] : 0;
$main_video_reported = $main_video_data && !empty($main_video_data['is_reported']);
$main_video_featured = $main_video_data && !empty($main_video_data['is_featured']);
$main_video_can_manage = $main_video_data && !empty($main_video_data['can_manage']);
$main_video_can_feature = $main_video_data && !empty($main_video_data['can_feature']);
$main_media_identifier = isset($main_image_data['identifier']) ? $main_image_data['identifier'] : '';
$main_media_sequence   = isset($main_image_data['sequence']) ? (int) $main_image_data['sequence'] : 1;

?>
<div
    id="single-gallery-container"
    class="pswp-gallery space-y-2 sm:space-y-3"
    data-gallery-mod-title="<?php echo esc_attr($mod_title); ?>"
>
    <div
        class="aspect-video bg-gray-200 rounded-md sm:rounded-lg overflow-hidden shadow-inner"
        data-gallery-featured-wrapper
        data-gallery-featured-type="<?php echo esc_attr($main_media_type); ?>"
        <?php if ('video' === $main_media_type && $main_video_id) : ?>data-gallery-featured-video-id="<?php echo esc_attr((string) $main_video_id); ?>"<?php endif; ?>
        <?php if ($default_image_json) : ?>data-gallery-featured-default-image="<?php echo esc_attr($default_image_json); ?>"<?php endif; ?>
        data-gallery-thumbnail-sizes="<?php echo esc_attr($thumbnail_sizes); ?>"
        data-gallery-featured-sizes="<?php echo esc_attr($main_image_sizes); ?>"
    >
        <?php if ('video' === $main_media_type && $main_video_data) : ?>
            <?php
            $featured_video_label = $main_video_title
                ? sprintf(/* translators: %s: video title */ __('Play featured video: %s', 'gta6-mods'), $main_video_title)
                : __('Play featured video', 'gta6-mods');
            $featured_video_thumb = $main_image_thumb_large ? $main_image_thumb_large : $main_image_url;
            $featured_video_thumb_width = $main_image_thumb_large_width > 0 ? $main_image_thumb_large_width : $main_image_width;
            $featured_video_thumb_height = $main_image_thumb_large_height > 0 ? $main_image_thumb_large_height : $main_image_height;
            if (!$featured_video_thumb) {
                $featured_video_thumb = $main_image_url;
            }

            if ($featured_video_thumb_width <= 0) {
                $featured_video_thumb_width = $main_image_width;
            }

            if ($featured_video_thumb_height <= 0) {
                $featured_video_thumb_height = $main_image_height;
            }

            $featured_video_srcset_values = [];

            if ($featured_video_thumb) {
                $featured_video_srcset_values[] = sprintf('%s %dw', $featured_video_thumb, max(1, (int) $featured_video_thumb_width));
            }

            if ($main_image_url && $main_image_url !== $featured_video_thumb) {
                $featured_video_srcset_values[] = sprintf('%s %dw', $main_image_url, max(1, (int) $main_image_width));
            }

            $featured_video_srcset_attr = !empty($featured_video_srcset_values) ? implode(', ', array_unique($featured_video_srcset_values)) : '';
            ?>
            <div class="relative h-full w-full" data-featured-video-preview>
                <div class="relative h-full w-full" data-featured-video-stage>
                    <button
                        type="button"
                        class="group relative block h-full w-full overflow-hidden rounded-md sm:rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-pink-500"
                        data-featured-video-trigger
                        data-youtube-id="<?php echo esc_attr($main_video_youtube); ?>"
                        data-video-id="<?php echo esc_attr((string) $main_video_id); ?>"
                        data-video-title="<?php echo esc_attr($main_video_title); ?>"
                        title="<?php echo esc_attr($main_video_title); ?>"
                        aria-label="<?php echo esc_attr($featured_video_label); ?>"
                    >
                        <span class="sr-only"><?php echo esc_html($featured_video_label); ?></span>
                        <img
                            src="<?php echo esc_url($featured_video_thumb); ?>"
                            alt="<?php echo esc_attr($main_image_alt); ?>"
                            title="<?php echo esc_attr($main_video_title); ?>"
                            class="h-full w-full object-cover"
                            width="<?php echo esc_attr($featured_video_thumb_width); ?>"
                            height="<?php echo esc_attr($featured_video_thumb_height); ?>"
                            loading="eager"
                            decoding="async"
                            fetchpriority="high"
                            sizes="<?php echo esc_attr($main_image_sizes); ?>"
                            <?php if ($featured_video_srcset_attr) : ?>srcset="<?php echo esc_attr($featured_video_srcset_attr); ?>"<?php endif; ?>
                        >
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/40 transition group-hover:bg-black/60" data-featured-video-overlay>
                            <div class="play-button" aria-hidden="true">
                                <div class="play-button-icon"></div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
            <a
                href="#"
                class="gallery-item video-gallery-item hidden"
                data-gallery-featured-link
                data-gallery-index="0"
                data-gallery-role="featured"
                data-gallery-type="video"
                data-pswp-width="<?php echo esc_attr($main_image_width); ?>"
                data-pswp-height="<?php echo esc_attr($main_image_height); ?>"
                <?php if ($main_media_sequence > 0) : ?>data-gallery-sequence="<?php echo esc_attr((string) $main_media_sequence); ?>"<?php endif; ?>
                <?php if ('' !== $main_media_identifier) : ?>data-gallery-identifier="<?php echo esc_attr($main_media_identifier); ?>"<?php endif; ?>
                <?php if ($main_image_thumb_small) : ?>data-thumbnail-small="<?php echo esc_attr($main_image_thumb_small); ?>"<?php endif; ?>
                <?php if ($main_image_thumb_small_width > 0) : ?>data-thumbnail-small-width="<?php echo esc_attr($main_image_thumb_small_width); ?>"<?php endif; ?>
                <?php if ($main_image_thumb_small_height > 0) : ?>data-thumbnail-small-height="<?php echo esc_attr($main_image_thumb_small_height); ?>"<?php endif; ?>
                <?php if ($main_image_thumb_large) : ?>data-thumbnail-large="<?php echo esc_attr($main_image_thumb_large); ?>"<?php endif; ?>
                <?php if ($main_image_thumb_large_width > 0) : ?>data-thumbnail-large-width="<?php echo esc_attr($main_image_thumb_large_width); ?>"<?php endif; ?>
                <?php if ($main_image_thumb_large_height > 0) : ?>data-thumbnail-large-height="<?php echo esc_attr($main_image_thumb_large_height); ?>"<?php endif; ?>
                data-youtube-id="<?php echo esc_attr($main_video_youtube); ?>"
                data-added-by="<?php echo esc_attr($main_video_added_by); ?>"
                data-profile-url="<?php echo esc_url($main_video_profile); ?>"
                data-video-id="<?php echo esc_attr((string) $main_video_id); ?>"
                data-video-title="<?php echo esc_attr($main_video_title); ?>"
                data-video-status="<?php echo esc_attr($main_video_status); ?>"
                data-is-reported="<?php echo $main_video_reported ? '1' : '0'; ?>"
                data-is-featured="<?php echo $main_video_featured ? '1' : '0'; ?>"
                data-can-manage="<?php echo $main_video_can_manage ? '1' : '0'; ?>"
                data-can-feature="<?php echo $main_video_can_feature ? '1' : '0'; ?>"
                data-report-count="<?php echo esc_attr((string) $main_video_reports); ?>"
                title="<?php echo esc_attr($main_video_title); ?>"
                aria-label="<?php echo esc_attr($main_image_aria ? $main_image_aria : $main_video_title); ?>"
                aria-haspopup="dialog"
                aria-expanded="false"
                tabindex="-1"
                hidden
            >
                <img
                    src="<?php echo esc_url($main_image_url); ?>"
                    alt="<?php echo esc_attr($main_image_alt); ?>"
                    title="<?php echo esc_attr($main_video_title); ?>"
                    class="hidden"
                    width="<?php echo esc_attr($main_image_width); ?>"
                    height="<?php echo esc_attr($main_image_height); ?>"
                    loading="lazy"
                    decoding="async"
                >
            </a>
        <?php else : ?>
            <?php
            $featured_image_src = $main_image_thumb_large ? $main_image_thumb_large : $main_image_url;
            $featured_image_width = $main_image_thumb_large_width > 0 ? $main_image_thumb_large_width : $main_image_width;
            $featured_image_height = $main_image_thumb_large_height > 0 ? $main_image_thumb_large_height : $main_image_height;

            if (!$featured_image_src) {
                $featured_image_src = $main_image_url;
            }

            if ($featured_image_width <= 0) {
                $featured_image_width = $main_image_width;
            }

            if ($featured_image_height <= 0) {
                $featured_image_height = $main_image_height;
            }

            $featured_image_srcset_values = [];

            if ($featured_image_src) {
                $featured_image_srcset_values[] = sprintf('%s %dw', $featured_image_src, max(1, (int) $featured_image_width));
            }

            if ($main_image_url && $main_image_url !== $featured_image_src) {
                $featured_image_srcset_values[] = sprintf('%s %dw', $main_image_url, max(1, (int) $main_image_width));
            }

            $featured_image_srcset_attr = !empty($featured_image_srcset_values) ? implode(', ', array_unique($featured_image_srcset_values)) : '';
            ?>
            <a
                href="<?php echo esc_url($main_image_url); ?>"
                class="gallery-item block w-full h-full"
                data-gallery-featured-link
                data-gallery-index="0"
                data-gallery-role="featured"
                data-gallery-type="image"
                data-pswp-width="<?php echo esc_attr($main_image_width); ?>"
                data-pswp-height="<?php echo esc_attr($main_image_height); ?>"
                <?php if ($main_media_sequence > 0) : ?>data-gallery-sequence="<?php echo esc_attr((string) $main_media_sequence); ?>"<?php endif; ?>
                <?php if ('' !== $main_media_identifier) : ?>data-gallery-identifier="<?php echo esc_attr($main_media_identifier); ?>"<?php endif; ?>
                title="<?php echo esc_attr($main_image_title); ?>"
                aria-label="<?php echo esc_attr($main_image_aria ? $main_image_aria : $main_image_title); ?>"
                aria-haspopup="dialog"
                aria-expanded="false"
            >
                <img
                    src="<?php echo esc_url($featured_image_src); ?>"
                    alt="<?php echo esc_attr($main_image_alt); ?>"
                    title="<?php echo esc_attr($main_image_title); ?>"
                    class="w-full h-full object-cover"
                    width="<?php echo esc_attr($featured_image_width); ?>"
                    height="<?php echo esc_attr($featured_image_height); ?>"
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                    sizes="<?php echo esc_attr($main_image_sizes); ?>"
                    <?php if ($featured_image_srcset_attr) : ?>srcset="<?php echo esc_attr($featured_image_srcset_attr); ?>"<?php endif; ?>
                    data-gallery-featured-image
                >
            </a>
        <?php endif; ?>
    </div>

    <?php if ($has_gallery_thumbnails) : ?>
        <div id="single-gallery-thumbnails" class="grid grid-cols-3 sm:grid-cols-5 gap-2 sm:gap-3">
            <?php foreach ($gallery_items as $index => $item) :
                if (0 === $index) {
                    continue;
                }

                $is_hidden   = $index > 5;
                $item_width  = isset($item['width']) ? (int) $item['width'] : 1920;
                $item_height = isset($item['height']) ? (int) $item['height'] : 1080;
                $item_type   = isset($item['type']) ? $item['type'] : 'image';
                $thumbnail_small = isset($item['thumbnail_small']) && $item['thumbnail_small']
                    ? $item['thumbnail_small']
                    : (isset($item['thumbnail']) ? $item['thumbnail'] : (isset($item['src']) ? $item['src'] : ''));
                $thumbnail_large = isset($item['thumbnail_large']) && $item['thumbnail_large']
                    ? $item['thumbnail_large']
                    : $thumbnail_small;
                $thumb_small_w = isset($item['thumbnail_small_width']) ? (int) $item['thumbnail_small_width'] : 0;
                $thumb_small_h = isset($item['thumbnail_small_height']) ? (int) $item['thumbnail_small_height'] : 0;
                $thumb_large_w = isset($item['thumbnail_large_width']) ? (int) $item['thumbnail_large_width'] : 0;
                $thumb_large_h = isset($item['thumbnail_large_height']) ? (int) $item['thumbnail_large_height'] : 0;

                if ('' === $thumbnail_large && '' !== $thumbnail_small) {
                    $thumbnail_large = $thumbnail_small;
                }

                if ('' === $thumbnail_small && '' !== $thumbnail_large) {
                    $thumbnail_small = $thumbnail_large;
                }
                $thumbnail     = $thumbnail_small ? $thumbnail_small : $thumbnail_large;
                $alt_text    = isset($item['alt']) ? $item['alt'] : '';
                $item_title  = isset($item['link_title']) ? $item['link_title'] : $mod_title;
                $item_aria   = isset($item['aria_label']) ? $item['aria_label'] : $item_title;
                $item_sequence = isset($item['sequence']) ? (int) $item['sequence'] : ($index + 1);
                $item_identifier = isset($item['identifier']) ? $item['identifier'] : '';

                if ($thumb_small_w <= 0) {
                    $thumb_small_w = $item_width > 0 ? $item_width : 1920;
                }

                if ($thumb_small_h <= 0) {
                    $thumb_small_h = $item_height > 0 ? $item_height : 1080;
                }

                if ($thumb_large_w <= 0) {
                    $thumb_large_w = $item_width > 0 ? $item_width : 1920;
                }

                if ($thumb_large_h <= 0) {
                    $thumb_large_h = $item_height > 0 ? $item_height : 1080;
                }

                $attribute_parts   = [];
                $attribute_parts[] = 'data-gallery-role="thumbnail"';
                $attribute_parts[] = sprintf('data-gallery-index="%s"', esc_attr((string) $index));
                $attribute_parts[] = sprintf('data-gallery-type="%s"', esc_attr($item_type));
                $attribute_parts[] = sprintf('data-pswp-width="%d"', $item_width);
                $attribute_parts[] = sprintf('data-pswp-height="%d"', $item_height);

                if ($item_sequence > 0) {
                    $attribute_parts[] = sprintf('data-gallery-sequence="%s"', esc_attr((string) $item_sequence));
                }

                if ($item_identifier !== '') {
                    $attribute_parts[] = sprintf('data-gallery-identifier="%s"', esc_attr($item_identifier));
                }

                if ($is_hidden) {
                    $attribute_parts[] = 'data-gallery-hidden="true"';
                }

                if ('' !== $thumbnail_small) {
                    $attribute_parts[] = sprintf('data-thumbnail-small="%s"', esc_attr($thumbnail_small));
                }

                if ($thumb_small_w > 0) {
                    $attribute_parts[] = sprintf('data-thumbnail-small-width="%d"', $thumb_small_w);
                }

                if ($thumb_small_h > 0) {
                    $attribute_parts[] = sprintf('data-thumbnail-small-height="%d"', $thumb_small_h);
                }

                if ('' !== $thumbnail_large) {
                    $attribute_parts[] = sprintf('data-thumbnail-large="%s"', esc_attr($thumbnail_large));
                }

                if ($thumb_large_w > 0) {
                    $attribute_parts[] = sprintf('data-thumbnail-large-width="%d"', $thumb_large_w);
                }

                if ($thumb_large_h > 0) {
                    $attribute_parts[] = sprintf('data-thumbnail-large-height="%d"', $thumb_large_h);
                }

                $common_attrs = implode(' ', $attribute_parts);

                if ('video' === $item_type && isset($item['video']) && is_array($item['video'])) {
                    $video_data   = $item['video'];
                    $video_id     = isset($video_data['video_id']) ? (int) $video_data['video_id'] : 0;
                    $youtube_id   = isset($video_data['youtube_id']) ? $video_data['youtube_id'] : '';
                    $video_title  = isset($video_data['video_title']) ? $video_data['video_title'] : '';
                    $display_name = isset($video_data['display_name']) ? $video_data['display_name'] : '';
                    $profile_url  = isset($video_data['profile_url']) ? $video_data['profile_url'] : home_url('/');
                    $status       = isset($video_data['status']) ? $video_data['status'] : 'approved';
                    $report_count = isset($video_data['report_count']) ? (int) $video_data['report_count'] : 0;
                    $is_reported  = !empty($video_data['is_reported']);
                    $is_featured  = !empty($video_data['is_featured']);
                    $can_manage   = !empty($video_data['can_manage']);
                    $can_feature  = !empty($video_data['can_feature']);
                    $link_classes = ['gallery-item', 'video-gallery-item', 'relative', 'aspect-video', 'block', 'rounded-md', 'sm:rounded-lg', 'overflow-hidden', 'group'];
                    if ($is_hidden) {
                        $link_classes[] = 'hidden';
                        $link_classes[] = 'extra-thumbnail';
                    }
                    ?>
                    <a
                        href="#"
                        class="<?php echo esc_attr(implode(' ', $link_classes)); ?>"
                        <?php echo $common_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        data-youtube-id="<?php echo esc_attr($youtube_id); ?>"
                        data-added-by="<?php echo esc_attr($display_name); ?>"
                        data-profile-url="<?php echo esc_url($profile_url); ?>"
                        data-video-id="<?php echo esc_attr($video_id); ?>"
                        data-video-title="<?php echo esc_attr($video_title); ?>"
                        data-video-status="<?php echo esc_attr($status); ?>"
                        data-is-reported="<?php echo $is_reported ? '1' : '0'; ?>"
                        data-is-featured="<?php echo $is_featured ? '1' : '0'; ?>"
                        data-can-manage="<?php echo $can_manage ? '1' : '0'; ?>"
                        data-can-feature="<?php echo $can_feature ? '1' : '0'; ?>"
                        data-report-count="<?php echo esc_attr($report_count); ?>"
                        title="<?php echo esc_attr($item_title); ?>"
                        aria-label="<?php echo esc_attr($item_aria); ?>"
                        aria-haspopup="dialog"
                        aria-expanded="false"
                    >
                        <img
                            src="<?php echo esc_url($thumbnail_small); ?>"
                            alt="<?php echo esc_attr($alt_text); ?>"
                            title="<?php echo esc_attr($item_title); ?>"
                            class="w-full h-full object-cover"
                            width="<?php echo esc_attr($thumb_small_w); ?>"
                            height="<?php echo esc_attr($thumb_small_h); ?>"
                            loading="lazy"
                            decoding="async"
                            fetchpriority="low"
                            sizes="<?php echo esc_attr($thumbnail_sizes); ?>"
                        >
                        <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-60 transition flex items-center justify-center">
                            <i class="fa-brands fa-youtube text-white text-xl opacity-90"></i>
                        </div>
                    </a>
                    <?php
                } else {
                    $link_classes = ['gallery-item', 'relative', 'aspect-video', 'block'];
                    if ($is_hidden) {
                        $link_classes[] = 'hidden';
                        $link_classes[] = 'extra-thumbnail';
                    }
                    ?>
                    <a
                        href="<?php echo esc_url($item['src']); ?>"
                        class="<?php echo esc_attr(implode(' ', $link_classes)); ?>"
                        <?php echo $common_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        title="<?php echo esc_attr($item_title); ?>"
                        aria-label="<?php echo esc_attr($item_aria); ?>"
                        aria-haspopup="dialog"
                        aria-expanded="false"
                    >
                        <img
                            src="<?php echo esc_url($thumbnail_small); ?>"
                            alt="<?php echo esc_attr($alt_text); ?>"
                            title="<?php echo esc_attr($item_title); ?>"
                            class="w-full h-full object-cover rounded-md sm:rounded-lg"
                            width="<?php echo esc_attr($thumb_small_w); ?>"
                            height="<?php echo esc_attr($thumb_small_h); ?>"
                            loading="lazy"
                            decoding="async"
                            fetchpriority="low"
                            sizes="<?php echo esc_attr($thumbnail_sizes); ?>"
                        >
                    </a>
                    <?php
                }
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($show_gallery_load_more) : ?>
    <div id="single-gallery-load-more" class="mt-4">
        <button
            type="button"
            class="w-full py-2 px-4 rounded-md sm:rounded-lg border-2 border-pink-500 text-pink-600 font-semibold hover:bg-pink-50 transition duration-300 ease-in-out"
            data-gallery-load-more
            data-load-more-text-template="<?php echo esc_attr__('Load more images and videos (%d)', 'gta6-mods'); ?>"
            title="<?php esc_attr_e('Load the remaining gallery items', 'gta6-mods'); ?>"
            aria-label="<?php esc_attr_e('Load the remaining gallery items', 'gta6-mods'); ?>"
        >
            <i class="fa-solid fa-images mr-2" aria-hidden="true"></i><?php printf(esc_html__('Load more images and videos (%d)', 'gta6-mods'), (int) $remaining_thumbnails); ?>
        </button>
    </div>
<?php endif; ?>
