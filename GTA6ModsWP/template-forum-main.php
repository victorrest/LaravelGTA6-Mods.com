<?php
/**
 * Template Name: Forum Main
 *
 * @package gta6modswp
 */

declare(strict_types=1);

// =============================================================================
// CACHE HEADERS - CRITICAL FOR CLOUDFLARE & BROWSER PERFORMANCE
// =============================================================================
if (!is_user_logged_in()) {
    $cache_ttl_seconds = 600; // 10 minutes

    header('Cache-Control: public, max-age=' . $cache_ttl_seconds . ', s-maxage=' . $cache_ttl_seconds);
    header('CDN-Cache-Control: max-age=' . $cache_ttl_seconds);
    header('Surrogate-Control: max-age=' . $cache_ttl_seconds);
    header('Vary: Accept-Encoding');
}
// =============================================================================

get_header();

$create_thread_url = function_exists('gta6_forum_get_create_thread_url') ? gta6_forum_get_create_thread_url() : '#';
$forum_home_url    = function_exists('gta6_forum_get_main_url') ? gta6_forum_get_main_url() : home_url('/forum/');
$flair_taxonomy    = get_taxonomy('forum_flair');
$flair_slug        = ($flair_taxonomy && !empty($flair_taxonomy->rewrite['slug'])) ? $flair_taxonomy->rewrite['slug'] : 'forum_flair';
$flair_path        = '/' . trim($flair_slug, '/') . '/';
$flair_base_url    = home_url($flair_path);
$flairs = get_terms([
    'taxonomy'   => 'forum_flair',
    'hide_empty' => false,
]);
$search_term = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';

$sort_query_var      = get_query_var('forum_sort');
$requested_sort      = is_string($sort_query_var) ? $sort_query_var : '';
if ('' === $requested_sort && isset($_GET['sort'])) {
    $requested_sort = sanitize_text_field(wp_unslash((string) $_GET['sort']));
}
$initial_sort = gta6_forum_normalize_sort($requested_sort);

$time_query_var         = get_query_var('forum_time_range');
$requested_time_range   = is_string($time_query_var) ? $time_query_var : '';
if ('' === $requested_time_range && isset($_GET['time_range'])) {
    $requested_time_range = sanitize_text_field(wp_unslash((string) $_GET['time_range']));
}
$initial_time_range = gta6_forum_normalize_time_range($requested_time_range);
if ('top' !== $initial_sort) {
    $initial_time_range = 'all-time';
}

$top_time_options   = gta6_forum_get_top_time_range_options();
$top_range_select_id = 'forum-top-range-main';
$show_top_time_range = ('top' === $initial_sort);

$threads_per_page = 10;
$initial_data      = gta6_forum_get_cached_thread_listing([
    'page'       => 1,
    'per_page'   => $threads_per_page,
    'sort'       => $initial_sort,
    'time_range' => $initial_time_range,
    'search'     => $search_term,
]);

$initial_threads      = isset($initial_data['threads']) && is_array($initial_data['threads']) ? $initial_data['threads'] : [];
$initial_pagination   = isset($initial_data['pagination']) && is_array($initial_data['pagination']) ? $initial_data['pagination'] : [];
$initial_total_pages  = max(1, isset($initial_pagination['total_pages']) ? (int) $initial_pagination['total_pages'] : 1);
$initial_current_page = max(1, isset($initial_pagination['current']) ? (int) $initial_pagination['current'] : 1);
$has_more_threads     = $initial_current_page < $initial_total_pages;

if (!empty($initial_pagination['per_page'])) {
    $threads_per_page = (int) $initial_pagination['per_page'];
}

$default_heading            = esc_html__('Community Forum', 'gta6mods');
$default_description_markup  = wpautop(esc_html__('Chat with fellow modders about the latest and greatest in GTA VI modding.', 'gta6mods'));
$flair_metadata              = [
    '' => [
        'heading'     => $default_heading,
        'description' => $default_description_markup,
    ],
];

if (!empty($flairs) && !is_wp_error($flairs)) {
    foreach ($flairs as $flair_term) {
        $flair_name = wp_strip_all_tags($flair_term->name);
        $flair_metadata[$flair_term->slug] = [
            'heading'     => sprintf(__('Threads tagged with "%s"', 'gta6mods'), $flair_name),
            'description' => gta6_forum_get_flair_description_markup($flair_term),
        ];
    }
}

$flair_metadata_json = wp_json_encode($flair_metadata);
if (false === $flair_metadata_json) {
    $flair_metadata_json = '{}';
}

$image_thread_url = ('#' !== $create_thread_url) ? add_query_arg('type', 'image', $create_thread_url) : '#';
$link_thread_url  = ('#' !== $create_thread_url) ? add_query_arg('type', 'link', $create_thread_url) : '#';

$status_message = '';
if (empty($initial_threads)) {
    if ('' !== $search_term) {
        /* translators: %s: search query */
        $status_message = sprintf(esc_html__('No threads matched “%s”.', 'gta6mods'), esc_html($search_term));
    } else {
        $status_message = esc_html__('No threads available yet.', 'gta6mods');
    }
}

$current_user_id          = get_current_user_id();
$composer_avatar_markup   = get_avatar($current_user_id ?: 0, 40, '', __('Your avatar', 'gta6mods'));
if ('' === trim((string) $composer_avatar_markup)) {
    $composer_avatar_markup = sprintf(
        '<img src="%1$s" alt="%2$s" class="rounded-full w-10 h-10" width="40" height="40" loading="lazy" decoding="async">',
        esc_url('https://placehold.co/80x80/e5e7eb/374151?text=AV'),
        esc_attr__('Your avatar', 'gta6mods')
    );
}

$create_thread_label      = __('Start a new thread…', 'gta6mods');
$create_thread_title_attr = __('Create a new discussion thread', 'gta6mods');
$create_media_title_attr  = __('Create a media-focused thread', 'gta6mods');
$create_link_title_attr   = __('Create a link-focused thread', 'gta6mods');
?>

<main class="container mx-auto px-4 lg:px-6 mt-8" data-forum-main data-base-url="<?php echo esc_url($forum_home_url); ?>" data-flair-path="<?php echo esc_attr($flair_path); ?>" data-flair-base-url="<?php echo esc_url($flair_base_url); ?>" data-initial-flair="" data-initial-search="<?php echo esc_attr($search_term); ?>" data-initial-sort="<?php echo esc_attr($initial_sort); ?>" data-initial-time-range="<?php echo esc_attr($initial_time_range); ?>" data-initial-page="<?php echo esc_attr((string) $initial_current_page); ?>" data-initial-total-pages="<?php echo esc_attr((string) $initial_total_pages); ?>" data-per-page="<?php echo esc_attr((string) $threads_per_page); ?>" data-has-initial="1" data-flair-metadata="<?php echo esc_attr($flair_metadata_json); ?>">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2" data-forum-heading><?php echo esc_html($default_heading); ?></h1>
    <div class="text-sm text-gray-500 mb-4 flair-description" data-forum-description><?php echo $default_description_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-8">
            <div class="card p-3 mb-4 flex items-center gap-3">
                <?php echo $composer_avatar_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <a
                    href="<?php echo esc_url($create_thread_url); ?>"
                    class="flex-grow bg-gray-100 hover:bg-gray-200 border border-gray-300 hover:border-gray-400 text-left px-4 py-2 rounded-md text-gray-600 transition"
                    title="<?php echo esc_attr($create_thread_title_attr); ?>"
                    aria-label="<?php echo esc_attr($create_thread_title_attr); ?>"
                >
                    <?php echo esc_html($create_thread_label); ?>
                </a>
                <a
                    href="<?php echo esc_url($image_thread_url); ?>"
                    class="p-2 text-gray-500 hover:text-pink-600 hover:bg-pink-100 rounded-md transition"
                    aria-label="<?php echo esc_attr($create_media_title_attr); ?>"
                    title="<?php echo esc_attr($create_media_title_attr); ?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image w-6 h-6"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg>
                </a>
                <a
                    href="<?php echo esc_url($link_thread_url); ?>"
                    class="p-2 text-gray-500 hover:text-pink-600 hover:bg-pink-100 rounded-md transition"
                    aria-label="<?php echo esc_attr($create_link_title_attr); ?>"
                    title="<?php echo esc_attr($create_link_title_attr); ?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link-2 w-6 h-6"><path d="M9 17H7A5 5 0 0 1 7 7h2"></path><path d="M15 7h2a5 5 0 1 1 0 10h-2"></path><line x1="8" x2="16" y1="12" y2="12"></line></svg>
                </a>
            </div>

            <div class="card p-2 mb-4 flex flex-wrap items-center gap-2">
                <button class="sort-button flex items-center gap-2 font-semibold px-3 py-1.5 rounded-md hover:bg-gray-200 dark:hover:bg-gray-700<?php echo 'hot' === $initial_sort ? ' active' : ' text-gray-600'; ?>" data-sort="hot">
                    <i class="fas fa-fire text-red-500"></i><?php echo esc_html__('Hot', 'gta6mods'); ?>
                </button>
                <button class="sort-button flex items-center gap-2 font-semibold px-3 py-1.5 rounded-md hover:bg-gray-200 dark:hover:bg-gray-700<?php echo 'new' === $initial_sort ? ' active' : ' text-gray-600'; ?>" data-sort="new">
                    <i class="fas fa-certificate text-blue-500"></i><?php echo esc_html__('New', 'gta6mods'); ?>
                </button>
                <button class="sort-button flex items-center gap-2 font-semibold px-3 py-1.5 rounded-md hover:bg-gray-200 dark:hover:bg-gray-700<?php echo 'top' === $initial_sort ? ' active' : ' text-gray-600'; ?>" data-sort="top">
                    <i class="fas fa-chart-line text-green-500"></i><?php echo esc_html__('Top', 'gta6mods'); ?>
                </button>
                <div class="<?php echo $show_top_time_range ? '' : 'hidden '; ?>relative" data-top-range-wrapper>
                    <label for="<?php echo esc_attr($top_range_select_id); ?>" class="sr-only"><?php echo esc_html__('Top range', 'gta6mods'); ?></label>
                    <select id="<?php echo esc_attr($top_range_select_id); ?>" class="sort-top-range-select" data-top-range>
                        <?php foreach ($top_time_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"<?php selected($initial_time_range, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="space-y-4" data-thread-list>
                <?php foreach ($initial_threads as $thread) : ?>
                    <?php gta6_forum_render_thread_card($thread); ?>
                <?php endforeach; ?>
            </div>
            <p class="text-sm text-gray-500 mt-4" data-status><?php echo $status_message; ?></p>
            <div class="flex justify-center mt-6">
                <button class="btn-primary font-semibold py-2 px-6 rounded-lg text-sm transition<?php echo $has_more_threads ? '' : ' hidden'; ?>" data-load-more>
                    <?php echo esc_html__('Load more threads', 'gta6mods'); ?>
                </button>
            </div>
        </div>

        <aside class="col-span-12 lg:col-span-4">
            <div class="sticky top-6 space-y-6">
                <div class="card">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-bold text-gray-900"><?php echo esc_html__('Browse by flair', 'gta6mods'); ?></h3>
                    </div>
                    <div class="p-4">
                        <div class="flex flex-wrap gap-2">
                            <?php if (!empty($flairs) && !is_wp_error($flairs)) : ?>
                                <?php foreach ($flairs as $flair) : ?>
                                    <?php
                                    $colors     = gta6_forum_get_flair_colors($flair->term_id);
                                    $flair_link = get_term_link($flair, 'forum_flair');
                                    if (is_wp_error($flair_link)) {
                                        $flair_link = '';
                                    }
                                    ?>
                                    <?php
                                    $flair_filter_title = sprintf(
                                        /* translators: %s: flair name */
                                        __('Filter threads by %s flair', 'gta6mods'),
                                        $flair->name
                                    );
                                    ?>
                                    <a
                                        href="<?php echo esc_url($flair_link); ?>"
                                        class="flair-filter-tag post-flair"
                                        data-flair-filter
                                        data-flair="<?php echo esc_attr($flair->slug); ?>"
                                        style="background-color: <?php echo esc_attr($colors['background']); ?>; color: <?php echo esc_attr($colors['text']); ?>;"
                                        rel="tag"
                                        title="<?php echo esc_attr($flair_filter_title); ?>"
                                        aria-label="<?php echo esc_attr($flair_filter_title); ?>"
                                    >
                                        <?php echo esc_html($flair->name); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="text-sm text-gray-500"><?php echo esc_html__('No flairs have been created yet.', 'gta6mods'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card forum-search-card">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-search text-pink-600" aria-hidden="true"></i>
                            <?php echo esc_html__('Search threads', 'gta6mods'); ?>
                        </h3>
                    </div>
                    <div class="p-4">
                        <form action="<?php echo esc_url($forum_home_url); ?>" method="get" class="forum-search-form flex flex-col gap-3" data-forum-search>
                            <label for="forum-search-input" class="screen-reader-text"><?php echo esc_html__('Search the forum', 'gta6mods'); ?></label>
                            <div class="forum-search-field w-full">
                                <span class="forum-search-icon" aria-hidden="true"><i class="fas fa-search"></i></span>
                                <input type="search" id="forum-search-input" name="q" value="<?php echo esc_attr($search_term); ?>" class="forum-search-input" placeholder="<?php echo esc_attr__('Search forum threads…', 'gta6mods'); ?>">
                                <button type="button" class="forum-search-clear" data-forum-search-clear aria-label="<?php echo esc_attr__('Clear search query', 'gta6mods'); ?>">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                            <button type="submit" class="forum-search-button w-full">
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                <span><?php echo esc_html__('Search', 'gta6mods'); ?></span>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-bold text-gray-900"><?php echo esc_html__('Forum guidelines', 'gta6mods'); ?></h3>
                    </div>
                    <div class="p-4 text-sm text-gray-600 space-y-3">
                        <p><?php echo esc_html__('Be respectful to other members and keep discussions constructive.', 'gta6mods'); ?></p>
                        <p><?php echo esc_html__('Only share legal and safe-to-use content.', 'gta6mods'); ?></p>
                        <p><?php echo esc_html__('Pick a flair that matches your topic to help others find it.', 'gta6mods'); ?></p>
                        <p><?php echo esc_html__('Mark spoilers clearly and avoid misleading titles.', 'gta6mods'); ?></p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<?php
if (function_exists('gta6_forum_render_share_modal')) {
    gta6_forum_render_share_modal();
}

get_footer();
