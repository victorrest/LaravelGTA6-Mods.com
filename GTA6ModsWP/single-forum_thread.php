<?php
declare(strict_types=1);

global $post;

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

the_post();

$author_id      = (int) get_the_author_meta('ID');
$author_name    = get_the_author_meta('display_name');
$author_bio     = get_the_author_meta('description');
$author_url     = get_author_posts_url($author_id);
$author_avatar  = get_avatar_url($author_id, ['size' => 96]);
$author_profile_title = sprintf(
    /* translators: %s: author display name */
    __('View profile of %s', 'gta6mods'),
    $author_name
);
$author_profile_aria = sprintf(
    /* translators: %s: author display name */
    __('Author profile for %s', 'gta6mods'),
    $author_name
);
$flairs         = get_the_terms(get_the_ID(), 'forum_flair');
$comment_count  = get_comments_number(get_the_ID());
$thread_views   = function_exists('gta6_forum_get_thread_views') ? gta6_forum_get_thread_views(get_the_ID()) : (int) get_post_meta(get_the_ID(), '_thread_views', true);
$forum_home_url = function_exists('gta6_forum_get_main_url') ? gta6_forum_get_main_url() : home_url('/forum/');
$thread_score   = (int) get_post_meta(get_the_ID(), '_thread_score', true);
$user_vote      = function_exists('gta6_forum_get_user_vote') ? gta6_forum_get_user_vote('thread', get_the_ID()) : 0;
$is_bookmarked  = is_user_logged_in() && function_exists('gta6_forum_is_thread_bookmarked_by_user')
    ? gta6_forum_is_thread_bookmarked_by_user(get_the_ID())
    : false;
$bookmark_endpoint = rest_url('gta6-forum/v1/threads/' . get_the_ID() . '/bookmark');
$related_mod_url_raw = (string) get_post_meta(get_the_ID(), '_thread_related_mod_url', true);
$related_mod_url     = $related_mod_url_raw !== '' ? esc_url($related_mod_url_raw) : '';
$related_mod_label   = '';
$forum_search_term   = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
$thread_type         = (string) get_post_meta(get_the_ID(), '_thread_post_type', true);
if ($thread_type === '') {
    $thread_type = 'text';
}
$external_url_raw = (string) get_post_meta(get_the_ID(), '_thread_external_url', true);
$external_url     = $external_url_raw !== '' ? esc_url($external_url_raw) : '';
$external_display = '';

if ($external_url) {
    $parsed_external = wp_parse_url($external_url);
    if (is_array($parsed_external)) {
        $host    = isset($parsed_external['host']) ? (string) $parsed_external['host'] : '';
        $display = $host;
        if (!empty($parsed_external['path'])) {
            $display .= '/' . ltrim((string) $parsed_external['path'], '/');
        }
        if (!empty($parsed_external['query'])) {
            $display .= '?' . $parsed_external['query'];
        }
        $display = trim($display);
        if ('' === $display) {
            $display = $external_url;
        }
        $external_display = wp_html_excerpt($display, 160, '…');
    }

    if ('' === $external_display) {
        $external_display = $external_url;
    }
}

if ($related_mod_url) {
    $parsed_related = wp_parse_url($related_mod_url);
    if (is_array($parsed_related)) {
        $related_mod_label = $parsed_related['host'] ?? '';
        $path_segment      = isset($parsed_related['path']) ? trim((string) $parsed_related['path'], '/') : '';
        if ($path_segment !== '') {
            $related_mod_label .= '/' . $path_segment;
        }
    }
}

$comment_count_label = sprintf(
    _n('%s comment', '%s comments', $comment_count, 'gta6mods'),
    number_format_i18n($comment_count)
);
$thread_view_label = function_exists('gta6_forum_format_view_count')
    ? gta6_forum_format_view_count($thread_views)
    : sprintf(_n('%s view', '%s views', $thread_views, 'gta6mods'), number_format_i18n($thread_views));

$comments_html = '';
if (function_exists('gta6mods_build_comments_payload')) {
    $payload = gta6mods_build_comments_payload(
        get_the_ID(),
        [
            'orderby'  => 'best',
            'page'     => 1,
            'per_page' => 15,
        ]
    );

    if (!is_wp_error($payload) && is_array($payload) && isset($payload['html'])) {
        $comments_html = (string) $payload['html'];
    }
}

$author_avatar_markup = gta6m_get_optimized_thread_avatar($author_id, get_the_ID(), 56);
if ('' === $author_avatar_markup) {
    $fallback_avatar = $author_avatar ?: 'https://placehold.co/64x64/e5e7eb/374151?text=AV';
    $avatar_label    = sprintf(
        /* translators: %s: author display name */
        __('Avatar of %s', 'gta6mods'),
        $author_name
    );

    $author_avatar_markup = sprintf(
        '<img src="%1$s" alt="%2$s" class="w-14 h-14 rounded-full object-cover" width="56" height="56" loading="lazy" decoding="async" title="%3$s" aria-label="%3$s">',
        esc_url($fallback_avatar),
        esc_attr($author_name),
        esc_attr($avatar_label)
    );
}
?>

<main class="container mx-auto px-4 lg:px-6 mt-8" data-thread-view data-thread-id="<?php echo esc_attr(get_the_ID()); ?>">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a
            href="<?php echo esc_url($forum_home_url); ?>"
            class="hover:text-pink-600 transition flex items-center gap-2"
            title="<?php echo esc_attr__('Return to the forum homepage', 'gta6mods'); ?>"
            aria-label="<?php echo esc_attr__('Go back to the forum homepage', 'gta6mods'); ?>"
            rel="home"
        >
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span><?php echo esc_html__('Back to forum', 'gta6mods'); ?></span>
        </a>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-8 space-y-8">
            <article class="card">
                <div class="flex flex-col md:flex-row">
                    <div class="w-full md:w-16 bg-gray-50 dark:bg-gray-800/40 flex md:flex-col items-center justify-between md:justify-start md:py-4 md:px-0 px-4 py-3 gap-6 md:gap-3" data-thread-vote>
                        <button class="vote-button<?php echo $user_vote === 1 ? ' upvoted' : ''; ?>" data-vote="up" aria-label="<?php echo esc_attr__('Upvote thread', 'gta6mods'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-up-icon lucide-arrow-big-up"><path d="M9 13a1 1 0 0 0-1-1H5.061a1 1 0 0 1-.75-1.811l6.836-6.835a1.207 1.207 0 0 1 1.707 0l6.835 6.835a1 1 0 0 1-.75 1.811H16a1 1 0 0 0-1 1v6a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z"/></svg>
                        </button>
                        <span class="font-bold text-sm text-gray-800" data-thread-score><?php echo esc_html($thread_score); ?></span>
                        <button class="vote-button<?php echo $user_vote === -1 ? ' downvoted' : ''; ?>" data-vote="down" aria-label="<?php echo esc_attr__('Downvote thread', 'gta6mods'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-down-icon lucide-arrow-big-down"><path d="M15 11a1 1 0 0 0 1 1h2.939a1 1 0 0 1 .75 1.811l-6.835 6.836a1.207 1.207 0 0 1-1.707 0L4.31 13.81a1 1 0 0 1 .75-1.811H8a1 1 0 0 0 1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1z"/></svg>
                        </button>
                    </div>
                    <div class="flex-1 p-6 md:p-8">
                        <div class="flex flex-wrap items-center gap-3 mb-4 text-xs text-gray-500">
                            <?php if (!empty($flairs) && !is_wp_error($flairs)) : ?>
                                <?php foreach ($flairs as $flair) : ?>
                                    <?php
                                    $colors = gta6_forum_get_flair_colors($flair->term_id);
                                    $flair_link = get_term_link($flair, 'forum_flair');
                                    $flair_href = !is_wp_error($flair_link) ? $flair_link : '';
                                    ?>
                                    <?php
                                    $flair_title = sprintf(
                                        /* translators: %s: flair name */
                                        __('Browse threads tagged with %s', 'gta6mods'),
                                        $flair->name
                                    );
                                    ?>
                                    <a
                                        href="<?php echo esc_url($flair_href); ?>"
                                        class="post-flair thread-flair"
                                        style="background-color: <?php echo esc_attr($colors['background']); ?>; color: <?php echo esc_attr($colors['text']); ?>;"
                                        rel="tag"
                                        title="<?php echo esc_attr($flair_title); ?>"
                                        aria-label="<?php echo esc_attr($flair_title); ?>"
                                    >
                                        <?php echo esc_html($flair->name); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <span>
                                <?php
                                $author_link_markup = sprintf(
                                    '<a href="%1$s" class="font-semibold text-amber-600 hover:underline" rel="author" title="%2$s" aria-label="%3$s">%4$s</a>',
                                    esc_url($author_url),
                                    esc_attr($author_profile_title),
                                    esc_attr($author_profile_aria),
                                    esc_html($author_name)
                                );
                                $author_allowed_tags = [
                                    'a' => [
                                        'href'        => [],
                                        'class'       => [],
                                        'rel'         => [],
                                        'title'       => [],
                                        'aria-label'  => [],
                                    ],
                                ];
                                $time_diff = esc_html(human_time_diff(get_the_time('U'), current_time('timestamp')));

                                printf(
                                    /* translators: 1: author, 2: time */
                                    esc_html__('Posted by %1$s %2$s ago', 'gta6mods'),
                                    wp_kses($author_link_markup, $author_allowed_tags),
                                    $time_diff
                                );
                                ?>
                            </span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-snug"><?php the_title(); ?></h1>
                        <div class="text-gray-700 dark:text-gray-200 leading-relaxed mt-4 space-y-4">
                            <?php if ('link' === $thread_type && $external_url) : ?>
                                <?php
                                $external_link_title = sprintf(
                                    /* translators: %s: external link host */
                                    __('Open external resource: %s (opens in new tab)', 'gta6mods'),
                                    $external_display
                                );
                                ?>
                                <a
                                    href="<?php echo esc_url($external_url); ?>"
                                    target="_blank"
                                    rel="nofollow noopener ugc"
                                    class="forum-thread-link"
                                    title="<?php echo esc_attr($external_link_title); ?>"
                                    aria-label="<?php echo esc_attr($external_link_title); ?>"
                                >
                                    <span class="forum-thread-link__label"><?php echo esc_html($external_display); ?><i class="fas fa-external-link-alt" aria-hidden="true"></i></span>
                                </a>
                            <?php else : ?>
                                <?php the_content(); ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($related_mod_url) : ?>
                            <div class="mt-6">
                                <div class="card">
                                    <div class="p-4 border-b border-gray-100">
                                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                            <i class="fas fa-link text-pink-600"></i>
                                            <?php echo esc_html__('Related mod', 'gta6mods'); ?>
                                        </h3>
                                    </div>
                                    <div class="p-4">
                                        <?php
                                        $related_link_title = sprintf(
                                            /* translators: %s: related mod link label */
                                            __('Open related mod link: %s', 'gta6mods'),
                                            $related_mod_label ?: wp_parse_url($related_mod_url, PHP_URL_HOST)
                                        );
                                        ?>
                                        <a
                                            href="<?php echo esc_url($related_mod_url); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="flex items-center gap-2 text-sm font-semibold text-pink-600 hover:text-pink-700 break-all"
                                            title="<?php echo esc_attr($related_link_title); ?>"
                                            aria-label="<?php echo esc_attr($related_link_title); ?>"
                                        >
                                            <i class="fas fa-external-link-alt"></i>
                                            <span><?php echo esc_html($related_mod_label ?: wp_parse_url($related_mod_url, PHP_URL_HOST)); ?></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 border-t border-gray-200 dark:border-gray-700 pt-4 mt-6 font-semibold">
                            <span
                                class="flex items-center gap-2 text-gray-700"
                                data-thread-view-count
                                aria-label="<?php echo esc_attr(sprintf(__('Thread views: %s', 'gta6mods'), $thread_view_label)); ?>"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M12.0003 3C17.3924 3 21.8784 6.87976 22.8189 12C21.8784 17.1202 17.3924 21 12.0003 21C6.60812 21 2.12215 17.1202 1.18164 12C2.12215 6.87976 6.60812 3 12.0003 3ZM12.0003 19C16.2359 19 19.8603 16.052 20.7777 12C19.8603 7.94803 16.2359 5 12.0003 5C7.7646 5 4.14022 7.94803 3.22278 12C4.14022 16.052 7.7646 19 12.0003 19ZM12.0003 16.5C9.51498 16.5 7.50026 14.4853 7.50026 12C7.50026 9.51472 9.51498 7.5 12.0003 7.5C14.4855 7.5 16.5003 9.51472 16.5003 12C16.5003 14.4853 14.4855 16.5 12.0003 16.5ZM12.0003 14.5C13.381 14.5 14.5003 13.3807 14.5003 12C14.5003 10.6193 13.381 9.5 12.0003 9.5C10.6196 9.5 9.50026 10.6193 9.50026 12C9.50026 13.3807 10.6196 14.5 12.0003 14.5Z"></path></svg>
                                <span data-thread-view-count-label><?php echo esc_html($thread_view_label); ?></span>
                            </span>
                            <span
                                class="flex items-center gap-2 text-gray-700"
                                data-thread-comment-count
                                aria-label="<?php echo esc_attr(sprintf(__('Thread comments: %s', 'gta6mods'), $comment_count_label)); ?>"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M10 3H14C18.4183 3 22 6.58172 22 11C22 15.4183 18.4183 19 14 19V22.5C9 20.5 2 17.5 2 11C2 6.58172 5.58172 3 10 3ZM12 17H14C17.3137 17 20 14.3137 20 11C20 7.68629 17.3137 5 14 5H10C6.68629 5 4 7.68629 4 11C4 14.61 6.46208 16.9656 12 19.4798V17Z"></path></svg>
                                <span data-thread-comment-count-label><?php echo esc_html($comment_count_label); ?></span>
                            </span>
                            <?php
                            $share_label = sprintf(
                                /* translators: %s: thread title */
                                __('Share the thread "%s"', 'gta6mods'),
                                get_the_title()
                            );
                            ?>
                            <button
                                type="button"
                                class="thread-action-button flex items-center gap-2 text-gray-700 hover:text-pink-600 transition"
                                data-share-trigger
                                data-share-title="<?php echo esc_attr(get_the_title()); ?>"
                                data-share-url="<?php echo esc_url(get_permalink()); ?>"
                                aria-label="<?php echo esc_attr($share_label); ?>"
                                title="<?php echo esc_attr($share_label); ?>"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M13.1202 17.0228L8.92129 14.7324C8.19135 15.5125 7.15261 16 6 16C3.79086 16 2 14.2091 2 12C2 9.79086 3.79086 8 6 8C7.15255 8 8.19125 8.48746 8.92118 9.26746L13.1202 6.97713C13.0417 6.66441 13 6.33707 13 6C13 3.79086 14.7909 2 17 2C19.2091 2 21 3.79086 21 6C21 8.20914 19.2091 10 17 10C15.8474 10 14.8087 9.51251 14.0787 8.73246L9.87977 11.0228C9.9583 11.3355 10 11.6629 10 12C10 12.3371 9.95831 12.6644 9.87981 12.9771L14.0788 15.2675C14.8087 14.4875 15.8474 14 17 14C19.2091 14 21 15.7909 21 18C21 20.2091 19.2091 22 17 22C14.7909 22 13 20.2091 13 18C13 17.6629 13.0417 17.3355 13.1202 17.0228ZM6 14C7.10457 14 8 13.1046 8 12C8 10.8954 7.10457 10 6 10C4.89543 10 4 10.8954 4 12C4 13.1046 4.89543 14 6 14ZM17 8C18.1046 8 19 7.10457 19 6C19 4.89543 18.1046 4 17 4C15.8954 4 15 4.89543 15 6C15 7.10457 15.8954 8 17 8ZM17 20C18.1046 20 19 19.1046 19 18C19 16.8954 18.1046 16 17 16C15.8954 16 15 16.8954 15 18C15 19.1046 15.8954 20 17 20Z"></path></svg>
                                <span><?php echo esc_html__('Share', 'gta6mods'); ?></span>
                            </button>
                            <?php
                            $bookmark_aria = $is_bookmarked
                                ? __('Remove bookmark for this thread', 'gta6mods')
                                : __('Bookmark this thread for later', 'gta6mods');
                            ?>
                            <button
                                type="button"
                                class="thread-action-button flex items-center gap-2 text-gray-700 hover:text-pink-600 transition<?php echo $is_bookmarked ? ' is-active' : ''; ?>"
                                data-bookmark-button
                                data-bookmark-endpoint="<?php echo esc_url($bookmark_endpoint); ?>"
                                data-bookmarked="<?php echo $is_bookmarked ? 'true' : 'false'; ?>"
                                aria-label="<?php echo esc_attr($bookmark_aria); ?>"
                                aria-pressed="<?php echo $is_bookmarked ? 'true' : 'false'; ?>"
                                title="<?php echo esc_attr($bookmark_aria); ?>"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true" data-bookmark-icon><path d="M5 2H19C19.5523 2 20 2.44772 20 3V22.1433C20 22.4194 19.7761 22.6434 19.5 22.6434C19.4061 22.6434 19.314 22.6168 19.2344 22.5669L12 18.0313L4.76559 22.5669C4.53163 22.7136 4.22306 22.6429 4.07637 22.4089C4.02647 22.3293 4 22.2373 4 22.1433V3C4 2.44772 4.44772 2 5 2ZM18 4H6V19.4324L12 15.6707L18 19.4324V4Z"></path></svg>
                                <span data-bookmark-label><?php echo $is_bookmarked ? esc_html__('Saved', 'gta6mods') : esc_html__('Bookmark', 'gta6mods'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <section class="space-y-6" data-thread-comments-section>
                <div data-thread-comments-root>
                    <?php if ($comments_html !== '') : ?>
                        <?php echo $comments_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php else : ?>
                        <div class="py-12 text-center text-sm text-gray-500"><?php echo esc_html__('Loading comments…', 'gta6mods'); ?></div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <aside class="col-span-12 lg:col-span-4">
            <div class="sticky top-6 space-y-6">
                <div class="card">
                    <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-pink-500/10 to-purple-500/10">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-user-circle text-pink-600"></i>
                            <?php echo esc_html__('About the author', 'gta6mods'); ?>
                        </h2>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex items-center gap-4">
                            <?php echo $author_avatar_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo esc_html($author_name); ?></p>
                                <a
                                    href="<?php echo esc_url($author_url); ?>"
                                    class="text-sm text-pink-600 hover:underline"
                                    title="<?php echo esc_attr($author_profile_title); ?>"
                                    aria-label="<?php echo esc_attr($author_profile_aria); ?>"
                                    rel="author"
                                >
                                    <?php echo esc_html__('View profile', 'gta6mods'); ?>
                                </a>
                            </div>
                        </div>
                        <?php if ($author_bio) : ?>
                            <p class="text-sm text-gray-600 leading-relaxed"><?php echo esc_html(wp_trim_words($author_bio, 30)); ?></p>
                        <?php else : ?>
                            <p class="text-sm text-gray-600 leading-relaxed"><?php echo esc_html__('This creator has not added a bio yet.', 'gta6mods'); ?></p>
                        <?php endif; ?>
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
                        <form action="<?php echo esc_url($forum_home_url); ?>" method="get" class="forum-search-form flex flex-col gap-3">
                            <label for="forum-thread-search" class="screen-reader-text"><?php echo esc_html__('Search the forum', 'gta6mods'); ?></label>
                            <div class="forum-search-field w-full">
                                <span class="forum-search-icon" aria-hidden="true"><i class="fas fa-search"></i></span>
                                <input type="search" id="forum-thread-search" name="q" value="<?php echo esc_attr($forum_search_term); ?>" class="forum-search-input" placeholder="<?php echo esc_attr__('Search forum threads…', 'gta6mods'); ?>">
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
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-scroll text-pink-600"></i>
                            <?php echo esc_html__('Forum rules', 'gta6mods'); ?>
                        </h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <details class="group" open>
                            <summary class="flex items-center justify-between cursor-pointer text-sm font-semibold text-gray-800">
                                <span><?php echo esc_html__('Be respectful', 'gta6mods'); ?></span>
                                <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
                            </summary>
                            <div class="mt-2 text-sm text-gray-600 leading-relaxed">
                                <?php echo esc_html__('Treat other members with respect. Personal attacks, hate speech or harassment have no place here.', 'gta6mods'); ?>
                            </div>
                        </details>
                        <details class="group">
                            <summary class="flex items-center justify-between cursor-pointer text-sm font-semibold text-gray-800">
                                <span><?php echo esc_html__('Stay on topic', 'gta6mods'); ?></span>
                                <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
                            </summary>
                            <div class="mt-2 text-sm text-gray-600 leading-relaxed">
                                <?php echo esc_html__('Keep discussions relevant to the flair and the thread. Create a new thread if you want to start a different conversation.', 'gta6mods'); ?>
                            </div>
                        </details>
                        <details class="group">
                            <summary class="flex items-center justify-between cursor-pointer text-sm font-semibold text-gray-800">
                                <span><?php echo esc_html__('No spam or self-promotion', 'gta6mods'); ?></span>
                                <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
                            </summary>
                            <div class="mt-2 text-sm text-gray-600 leading-relaxed">
                                <?php echo esc_html__('Avoid low-effort posts, repetitive advertising, and misleading links. Share your work when it adds value to the discussion.', 'gta6mods'); ?>
                            </div>
                        </details>
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
