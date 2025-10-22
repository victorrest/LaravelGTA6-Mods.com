<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        $format = get_post_format();

        if ($format === 'link') {
            get_template_part('template-parts/single', 'link');
        } else {
            get_template_part('template-parts/single', 'default');
        }
    endwhile;
else :
    echo '<main class="container mx-auto p-4 lg:p-6">';
    get_template_part('template-parts/content', 'none');
    echo '</main>';
endif;

get_footer();
