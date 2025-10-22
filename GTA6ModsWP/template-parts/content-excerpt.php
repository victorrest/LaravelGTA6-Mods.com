<?php
if (!defined('ABSPATH')) {
    exit;
}

$author_display_name = gta6_mods_get_denormalized_author_name(get_the_ID());

if ($author_display_name === '') {
    $author_display_name = get_the_author();
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('card hover:shadow-xl transition duration-300'); ?>>
    <a href="<?php the_permalink(); ?>" class="block">
        <div class="relative">
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large', ['class' => 'w-full h-48 object-cover rounded-t-xl']); ?>
            <?php else : ?>
                <img src="<?php echo esc_url(gta6_mods_get_placeholder('card')); ?>" alt="<?php the_title_attribute(); ?>" class="w-full h-48 object-cover rounded-t-xl">
            <?php endif; ?>
            <div class="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                <div class="flex justify-between items-center">
                    <span class="flex items-center font-semibold text-yellow-400"><i class="fa-solid fa-star mr-1"></i><?php echo esc_html(number_format_i18n(wp_rand(35, 50) / 10, 1)); ?></span>
                    <div class="flex items-center space-x-3">
                        <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1"></i><?php echo esc_html(number_format_i18n(wp_rand(50, 1800))); ?></span>
                        <span class="flex items-center"><i class="fa-solid fa-download mr-1"></i><?php echo esc_html(number_format_i18n(wp_rand(500, 45000))); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4">
            <h3 class="font-semibold text-gray-900 text-lg leading-tight mb-1"><?php the_title(); ?></h3>
            <div class="text-xs text-gray-500 flex justify-between items-center mb-2">
                <span class="flex items-center"><i class="fa-solid fa-user mr-1"></i><?php echo esc_html($author_display_name); ?></span>
                <span class="flex items-center"><i class="fa-solid fa-calendar-days mr-1"></i><?php echo esc_html(get_the_date()); ?></span>
            </div>
            <p class="text-sm text-gray-600"><?php echo esc_html(wp_trim_words(get_the_excerpt() ? get_the_excerpt() : wp_strip_all_tags(get_the_content()), 18)); ?></p>
        </div>
    </a>
</article>
