<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main class="container mx-auto p-4 lg:p-6 space-y-10">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('card p-6 md:p-10'); ?>>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4"><?php the_title(); ?></h1>
                <div class="prose max-w-none text-gray-700">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php if (comments_open() || get_comments_number()) : ?>
                <section class="card p-6">
                    <?php comments_template(); ?>
                </section>
            <?php endif; ?>
        <?php endwhile; ?>
    <?php else : ?>
        <?php get_template_part('template-parts/content', 'none'); ?>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
