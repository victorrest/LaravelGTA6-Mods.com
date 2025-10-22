<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main class="container mx-auto p-4 lg:p-6 space-y-10">
    <section class="card p-10 text-center">
        <h1 class="text-5xl font-extrabold text-pink-500 mb-4">404</h1>
        <p class="text-gray-600 mb-6"><?php esc_html_e('A keresett oldal nem található.', 'gta6-mods'); ?></p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center bg-pink-500 hover:bg-pink-600 text-white font-semibold px-6 py-3 rounded-full transition">
            <i class="fa-solid fa-arrow-left mr-2"></i><?php esc_html_e('Vissza a főoldalra', 'gta6-mods'); ?>
        </a>
    </section>
</main>
<?php get_footer(); ?>
