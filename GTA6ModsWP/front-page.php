<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$front_page_cache_key   = 'gta6_front_page_data_v1';
$front_page_cached_data = get_transient($front_page_cache_key);

if (false === $front_page_cached_data) {
    $front_page_cached_data = gta6_mods_collect_front_page_data();
    set_transient($front_page_cache_key, $front_page_cached_data, 30 * MINUTE_IN_SECONDS);
}

$featured_mods = isset($front_page_cached_data['featuredMods']) && is_array($front_page_cached_data['featuredMods'])
    ? $front_page_cached_data['featuredMods']
    : [];
$popular_mods = isset($front_page_cached_data['popularMods']) && is_array($front_page_cached_data['popularMods'])
    ? $front_page_cached_data['popularMods']
    : [];
$latest_mods = isset($front_page_cached_data['latestMods']) && is_array($front_page_cached_data['latestMods'])
    ? $front_page_cached_data['latestMods']
    : [];
$latest_news = isset($front_page_cached_data['latestNews']) && is_array($front_page_cached_data['latestNews'])
    ? $front_page_cached_data['latestNews']
    : [];

$featured_count        = is_array($featured_mods) ? count($featured_mods) : 0;
$has_featured_content  = $featured_count > 0;
$primary_featured_mod  = $has_featured_content ? $featured_mods[0] : null;
$has_popular_mods      = !empty($popular_mods);
$has_latest_mods       = !empty($latest_mods);
$has_latest_news       = !empty($latest_news);

?>
<main class="container mx-auto p-4 lg:p-6 space-y-10">

    <section id="featured-section">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900"><?php esc_html_e('Featured Mods', 'gta6-mods'); ?></h2>
            <a href="#" class="text-sm font-medium text-pink-600 hover:text-pink-700"><?php esc_html_e('View All', 'gta6-mods'); ?> <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div class="lg:grid lg:grid-cols-12 lg:gap-6">
            <div class="lg:col-span-8">
                <div class="relative">
                    <div
                        id="featured-slider-container"
                        class="card relative overflow-hidden"
                        data-hydrated="<?php echo $has_featured_content ? 'true' : 'false'; ?>"
                        data-by-label="<?php esc_attr_e('by', 'gta6-mods'); ?>"
                        data-featured-label="<?php esc_attr_e('Featured', 'gta6-mods'); ?>"
                        data-loading-text="<?php esc_attr_e('Kiemelt modok betöltése…', 'gta6-mods'); ?>"
                        data-empty-label="<?php esc_attr_e('Jelenleg nincs kiemelt mod.', 'gta6-mods'); ?>"
                        data-prev-label="<?php esc_attr_e('Előző kiemelt mod', 'gta6-mods'); ?>"
                        data-next-label="<?php esc_attr_e('Következő kiemelt mod', 'gta6-mods'); ?>"
                    >
                        <?php if ($has_featured_content && $primary_featured_mod) :
                            $featured_image = isset($primary_featured_mod['image']) && $primary_featured_mod['image'] ? $primary_featured_mod['image'] : 'https://placehold.co/900x500/ec4899/ffffff?text=GTA6+Mod';
                            $featured_link  = isset($primary_featured_mod['link']) && $primary_featured_mod['link'] ? $primary_featured_mod['link'] : '#';
                        ?>
                            <a href="<?php echo esc_url($featured_link); ?>" id="featured-main-display" class="block relative group rounded-lg overflow-hidden">
                                <div id="featured-image-container" class="relative w-full aspect-video bg-gray-800">
                                    <img id="featured-image-1" src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr($primary_featured_mod['title']); ?>" class="absolute inset-0 w-full h-full object-cover" style="opacity: 1;">
                                    <img id="featured-image-2" src="" alt="" class="absolute inset-0 w-full h-full object-cover" style="opacity: 0;">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                                </div>
                                <div class="featured-badge">
                                    <i class="fas fa-star fa-xs mr-1.5" aria-hidden="true"></i><?php esc_html_e('Featured', 'gta6-mods'); ?>
                                </div>
                                <?php if (!empty($featured_mods)) : ?>
                                    <div id="featured-nav-container">
                                        <div class="flex items-center gap-2">
                                            <?php foreach ($featured_mods as $index => $mod) :
                                                $is_active_segment = (0 === $index);
                                            ?>
                                                <div class="featured-nav-segment<?php echo $is_active_segment ? ' active' : ''; ?>" data-index="<?php echo esc_attr((string) $index); ?>">
                                                    <div class="progress-bar-inner" style="width: <?php echo $is_active_segment ? '100' : '0'; ?>%;"></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div id="featured-text-content" class="absolute bottom-0 left-0 p-4 md:p-6 text-white w-full">
                                    <h3 id="featured-title" class="text-lg sm:text-xl md:text-2xl font-bold leading-tight mb-1"><?php echo esc_html($primary_featured_mod['title']); ?></h3>
                                    <p id="featured-author" class="text-sm text-gray-200">
                                        <?php esc_html_e('by', 'gta6-mods'); ?> <span class="font-semibold"><?php echo esc_html($primary_featured_mod['author']); ?></span>
                                    </p>
                                </div>
                                <button type="button" id="featured-prev" class="absolute left-3 top-1/2 -translate-y-1/2 bg-black/40 text-white text-[12px] sm:text-base rounded-full w-6 h-6 sm:w-10 sm:h-10 flex items-center justify-center opacity-100 group-hover:opacity-100 transform-gpu transition-all duration-300 hover:bg-black/60 hover:scale-110 focus:outline-none z-30" aria-label="<?php esc_attr_e('Előző kiemelt mod', 'gta6-mods'); ?>">
                                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                                </button>
                                <button type="button" id="featured-next" class="absolute right-3 top-1/2 -translate-y-1/2 bg-black/40 text-white text-[12px] sm:text-base rounded-full w-6 h-6 sm:w-10 sm:h-10 flex items-center justify-center opacity-100 group-hover:opacity-100 transform-gpu transition-all duration-300 hover:bg-black/60 hover:scale-110 focus:outline-none z-30" aria-label="<?php esc_attr_e('Következő kiemelt mod', 'gta6-mods'); ?>">
                                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                </button>
                            </a>
                        <?php else : ?>
                            <div class="p-8 text-center text-gray-400 flex items-center justify-center min-h-[300px]">
                                <?php esc_html_e('Jelenleg nincs kiemelt mod.', 'gta6-mods'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="hidden lg:flex lg:col-span-4 items-center justify-center mt-6 lg:mt-0">
                <div class="w-full h-full min-h-[300px] bg-gray-200 border-2 border-dashed border-gray-400 rounded-lg flex items-center justify-center text-gray-500">
                    <?php esc_html_e('Hirdetés (336x280)', 'gta6-mods'); ?>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900"><?php esc_html_e('Popular Mods', 'gta6-mods'); ?></h2>
            <a href="#" class="text-sm font-medium text-pink-600 hover:text-pink-700"><?php esc_html_e('View All', 'gta6-mods'); ?> <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div
            id="popular-mods-grid"
            class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6"
            data-hydrated="<?php echo $has_popular_mods ? 'true' : 'false'; ?>"
        >
            <?php if ($has_popular_mods) : ?>
                <?php foreach ($popular_mods as $mod) : ?>
                    <div class="card hover:shadow-xl transition duration-300">
                        <a href="<?php echo esc_url($mod['link']); ?>" class="block">
                            <div class="relative">
                                <img src="<?php echo esc_url($mod['image']); ?>" alt="<?php echo esc_attr($mod['title']); ?>" class="w-full h-auto object-cover rounded-t-xl">
                                <div class="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                                    <div class="flex justify-between items-center">
                                        <span class="flex items-center font-semibold text-yellow-400"><i class="fa-solid fa-star mr-1"></i><?php echo esc_html($mod['rating']); ?></span>
                                        <div class="flex items-center space-x-3">
                                            <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1"></i><?php echo esc_html($mod['likes']); ?></span>
                                            <span class="flex items-center"><i class="fa-solid fa-download mr-1"></i><?php echo esc_html($mod['downloads']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3">
                                <h3 class="font-semibold text-gray-900 text-sm truncate" title="<?php echo esc_attr($mod['title']); ?>"><?php echo esc_html($mod['title']); ?></h3>
                                <div class="flex justify-between items-center text-xs text-gray-500 mt-1">
                                    <span class="flex items-center"><i class="fa-solid fa-user mr-1"></i> <?php echo esc_html($mod['author']); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900"><?php esc_html_e('Latest Mods', 'gta6-mods'); ?> <span class="text-sm font-normal text-pink-500"><?php esc_html_e('(24h)', 'gta6-mods'); ?></span></h2>
            <a href="#" class="text-sm font-medium text-pink-600 hover:text-pink-700"><?php esc_html_e('View All', 'gta6-mods'); ?> <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div
            id="latest-mods-grid"
            class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6"
            data-hydrated="<?php echo $has_latest_mods ? 'true' : 'false'; ?>"
        >
            <?php if ($has_latest_mods) : ?>
                <?php foreach ($latest_mods as $mod) : ?>
                    <div class="card hover:shadow-xl transition duration-300">
                        <a href="<?php echo esc_url($mod['link']); ?>" class="block">
                            <div class="relative">
                                <img src="<?php echo esc_url($mod['image']); ?>" alt="<?php echo esc_attr($mod['title']); ?>" class="w-full h-auto object-cover rounded-t-xl">
                                <div class="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                                    <div class="flex justify-between items-center">
                                        <span class="flex items-center font-semibold text-yellow-400"><i class="fa-solid fa-star mr-1"></i><?php echo esc_html($mod['rating']); ?></span>
                                        <div class="flex items-center space-x-3">
                                            <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1"></i><?php echo esc_html($mod['likes']); ?></span>
                                            <span class="flex items-center"><i class="fa-solid fa-download mr-1"></i><?php echo esc_html($mod['downloads']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3">
                                <h3 class="font-semibold text-gray-900 text-sm truncate" title="<?php echo esc_attr($mod['title']); ?>"><?php echo esc_html($mod['title']); ?></h3>
                                <div class="flex justify-between items-center text-xs text-gray-500 mt-1">
                                    <span class="flex items-center"><i class="fa-solid fa-user mr-1"></i> <?php echo esc_html($mod['author']); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900"><?php esc_html_e('Latest News', 'gta6-mods'); ?></h2>
            <a href="#" class="text-sm font-medium text-pink-600 hover:text-pink-700"><?php esc_html_e('All News', 'gta6-mods'); ?> <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div id="latest-news-list" class="lg:col-span-9 space-y-4" data-hydrated="<?php echo $has_latest_news ? 'true' : 'false'; ?>">
                <?php if ($has_latest_news) : ?>
                    <?php foreach ($latest_news as $news) : ?>
                        <div class="card p-4 md:p-5 hover:shadow-xl transition duration-300">
                            <a href="<?php echo esc_url($news['link']); ?>" class="flex flex-col md:flex-row gap-4 md:gap-5 items-start">
                                <div class="w-full h-32 md:w-48 md:h-28 flex-shrink-0 rounded-lg overflow-hidden">
                                    <img src="<?php echo esc_url($news['image']); ?>" alt="<?php echo esc_attr($news['title']); ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-grow md:border-l md:border-gray-200 md:pl-5">
                                    <div class="flex flex-wrap items-center space-x-3 mb-2 text-xs">
                                        <span class="bg-pink-100 text-pink-800 font-semibold px-2 py-0.5 rounded-full shadow-sm"><?php echo esc_html($news['category']); ?></span>
                                        <span class="text-gray-500 mt-1 md:mt-0"><i class="fa-solid fa-calendar-days mr-1"></i><?php echo esc_html($news['date']); ?></span>
                                    </div>
                                    <h3 class="font-bold text-lg text-gray-900 hover:text-pink-600 transition"><?php echo esc_html($news['title']); ?></h3>
                                    <p class="text-gray-600 mt-1 text-sm"><?php echo esc_html($news['summary']); ?></p>
                                    <span class="mt-3 inline-flex items-center text-xs font-semibold text-pink-600 hover:underline"><?php esc_html_e('Read More', 'gta6-mods'); ?> <i class="fa-solid fa-chevron-right ml-1 text-sm"></i></span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <aside class="lg:col-span-3">
                <div class="card p-4 text-center border-2 border-dashed border-gray-300 bg-gray-100 sticky top-6">
                    <span class="text-xs font-semibold text-gray-400"><?php esc_html_e('LEBEGŐ HIRDETÉS (300x250)', 'gta6-mods'); ?></span>
                    <div class="mt-3 w-full h-64 bg-white border border-gray-300 flex items-center justify-center rounded-lg shadow-inner">
                        <p class="text-gray-500 text-sm font-bold"><?php esc_html_e('STICKY AD BLOCK', 'gta6-mods'); ?></p>
                    </div>
                    <span class="text-xl font-extrabold text-pink-600 logo-font mt-4"><?php esc_html_e('TÁMOGATÁS', 'gta6-mods'); ?></span>
                    <p class="text-sm text-gray-500 mt-1"><?php esc_html_e('Támogasd az oldalt!', 'gta6-mods'); ?></p>
                </div>
            </aside>
        </div>
    </section>

    <noscript>
        <div class="card p-4 text-center">
            <p class="text-sm text-gray-500"><?php esc_html_e('A tartalom teljes megjelenítéséhez engedélyezd a JavaScriptet.', 'gta6-mods'); ?></p>
        </div>
    </noscript>
</main>
<?php get_footer(); ?>
