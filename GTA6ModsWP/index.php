<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main class="container mx-auto p-4 lg:p-6 space-y-10">
    <section>
        <header class="mb-6 text-center">
            <h1 class="text-3xl font-bold text-gray-900"><?php esc_html_e('Legfrissebb Bejegyzések', 'gta6-mods'); ?></h1>
            <p class="text-gray-500 mt-2"><?php esc_html_e('Fedezd fel a legújabb Vice City modokat és híreket.', 'gta6-mods'); ?></p>
        </header>
        <?php if (have_posts()) : ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                while (have_posts()) :
                    the_post();
                    get_template_part('template-parts/content', 'excerpt');
                endwhile;
                ?>
            </div>
            <div class="mt-10 flex justify-center">
                <?php
                the_posts_pagination([
                    'mid_size'  => 1,
                    'prev_text' => '<span class="px-4 py-2 bg-gray-200 rounded-full text-sm">' . esc_html__('Előző', 'gta6-mods') . '</span>',
                    'next_text' => '<span class="px-4 py-2 bg-gray-200 rounded-full text-sm">' . esc_html__('Következő', 'gta6-mods') . '</span>',
                ]);
                ?>
            </div>
        <?php else : ?>
            <?php get_template_part('template-parts/content', 'none'); ?>
        <?php endif; ?>
    </section>
</main>
<?php get_footer(); ?>
