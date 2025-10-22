<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function gta6_forum_enqueue_assets(): void {
    if (!gta6_forum_is_forum_context()) {
        return;
    }

    wp_enqueue_script('gta6-forum-tailwind', 'https://cdn.tailwindcss.com', [], null, false);
    wp_enqueue_style('gta6-forum-google-fonts', 'https://fonts.googleapis.com/css2?family=Audiowide&family=Inter:wght@400;500;600;700;800&family=Oswald:wght@600&display=swap', [], null);
    wp_enqueue_style('gta6-forum-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');

    wp_enqueue_style(
        'gta6-forum-styles',
        get_template_directory_uri() . '/assets/css/forum.css',
        [],
        (string) filemtime(get_template_directory() . '/assets/css/forum.css')
    );

    wp_enqueue_script('wp-api-fetch');

    wp_enqueue_script(
        'gta6-forum-shared',
        get_template_directory_uri() . '/js/forum-shared.js',
        ['wp-api-fetch'],
        (string) filemtime(get_template_directory() . '/js/forum-shared.js'),
        true
    );

    wp_localize_script('gta6-forum-shared', 'GTA6ForumSharedData', gta6_forum_base_localized_data([
        'share' => [
            'modalTitle'       => __('Share this thread', 'gta6mods'),
            'modalDescription' => __('Pick a platform to spread the word.', 'gta6mods'),
            'copySuccess'      => __('Link copied to clipboard!', 'gta6mods'),
            'copyError'        => __('We could not copy the link. Please copy it manually.', 'gta6mods'),
        ],
        'bookmarks' => [
            'add'           => __('Bookmark', 'gta6mods'),
            'added'         => __('Saved', 'gta6mods'),
            'loginRequired' => __('Please sign in to save threads.', 'gta6mods'),
            'error'         => __('We could not update your bookmark. Please try again.', 'gta6mods'),
        ],
    ]));

    if (is_page_template('template-forum-main.php') || is_tax('forum_flair')) {
        wp_enqueue_script(
            'gta6-forum-main',
            get_template_directory_uri() . '/js/forum-main.js',
            ['wp-api-fetch', 'gta6-forum-shared'],
            (string) filemtime(get_template_directory() . '/js/forum-main.js'),
            true
        );

        wp_localize_script('gta6-forum-main', 'GTA6ForumMain', gta6_forum_base_localized_data([
            'sortOptions' => [
                'hot'  => __('Hot', 'gta6mods'),
                'top'  => __('Top', 'gta6mods'),
                'new'  => __('New', 'gta6mods'),
            ],
            'commentLabels' => [
                'singular' => __('%s comment', 'gta6mods'),
                'plural'   => __('%s comments', 'gta6mods'),
            ],
            'viewLabels' => [
                'singular' => __('%s view', 'gta6mods'),
                'plural'   => __('%s views', 'gta6mods'),
            ],
        ]));
    }

    if (is_singular('forum_thread')) {
        $threadViews = function_exists('gta6_forum_get_thread_views') ? gta6_forum_get_thread_views(get_the_ID()) : (int) get_post_meta(get_the_ID(), '_thread_views', true);
        wp_enqueue_script(
            'gta6-forum-thread',
            get_template_directory_uri() . '/js/forum-thread.js',
            ['wp-api-fetch', 'gta6-forum-shared'],
            (string) filemtime(get_template_directory() . '/js/forum-thread.js'),
            true
        );

        wp_localize_script('gta6-forum-thread', 'GTA6ForumThread', gta6_forum_base_localized_data([
            'threadId' => get_the_ID(),
            'threadTexts' => [
                'loadingComments' => __('Loading comments…', 'gta6mods'),
                'noComments'      => __('No comments yet. Start the conversation!', 'gta6mods'),
                'commentError'    => __('Unable to submit your comment. Please try again.', 'gta6mods'),
                'commentSingular' => __('%s comment', 'gta6mods'),
                'commentPlural'   => __('%s comments', 'gta6mods'),
                'linkPrompt'      => __('Enter the URL to link to:', 'gta6mods'),
                'invalidLink'     => __('Please enter a valid URL starting with http:// or https://.', 'gta6mods'),
            ],
            'share' => [
                'title' => get_the_title(),
                'url'   => get_permalink(),
            ],
            'bookmark' => [
                'endpoint'     => rest_url('gta6-forum/v1/threads/' . get_the_ID() . '/bookmark'),
                'isBookmarked' => is_user_logged_in() ? gta6_forum_is_thread_bookmarked_by_user(get_the_ID()) : false,
            ],
            'comments' => [
                'endpoint' => rest_url('gta6mods/v1/comments/' . get_the_ID()),
                'count'    => get_comments_number(get_the_ID()),
                'orderby'  => 'best',
            ],
            'views' => [
                'count'     => $threadViews,
                'formatted' => gta6_forum_format_view_count($threadViews),
                'endpoint'  => rest_url('gta6-forum/v1/threads/' . get_the_ID() . '/views'),
                'singular'  => __('%s view', 'gta6mods'),
                'plural'    => __('%s views', 'gta6mods'),
            ],
        ]));
    }

    if (is_page_template('template-forum-create.php')) {
        wp_enqueue_script(
            'gta6-forum-create',
            get_template_directory_uri() . '/js/forum-create.js',
            ['wp-api-fetch'],
            (string) filemtime(get_template_directory() . '/js/forum-create.js'),
            true
        );

        wp_localize_script('gta6-forum-create', 'GTA6ForumCreate', gta6_forum_base_localized_data([
            'flairs' => gta6_forum_get_flairs_for_frontend(),
            'createTexts' => [
                'submitting' => __('Submitting…', 'gta6mods'),
                'success'    => __('Thread published! Redirecting…', 'gta6mods'),
                'error'      => __('Unable to publish the thread. Please check your fields and try again.', 'gta6mods'),
                'loginRequired' => __('Please sign in to create a thread.', 'gta6mods'),
                'uploading'  => __('Uploading image…', 'gta6mods'),
                'uploadError'=> __('We could not upload the image. Please try again or use a direct URL.', 'gta6mods'),
                'fileTooLarge' => __('The selected file is too large. Please choose an image under 5MB.', 'gta6mods'),
                'imageRequired' => __('Please select an image file or switch to the URL option.', 'gta6mods'),
                'invalidModUrl' => __('Please enter a valid GTA6-Mods.com link (https://gta6-mods.com/...).', 'gta6mods'),
            ],
            'relatedModHosts' => apply_filters('gta6_forum_allowed_related_mod_hosts', ['gta6-mods.com', 'www.gta6-mods.com']),
        ]));
    }
}
add_action('wp_enqueue_scripts', 'gta6_forum_enqueue_assets');

function gta6_forum_base_localized_data(array $extra = []): array {
    return array_merge([
        'root'      => esc_url_raw(rest_url('gta6-forum/v1/')),
        'nonce'     => wp_create_nonce('wp_rest'),
        'isLoggedIn'=> is_user_logged_in(),
        'texts'     => [
            'loadMore'        => __('Load more', 'gta6mods'),
            'noThreads'       => __('No threads found for the selected filters.', 'gta6mods'),
            'voteError'       => __('Unable to register your vote. Please try again.', 'gta6mods'),
            'loginToVote'     => __('Please sign in to vote.', 'gta6mods'),
            'loading'         => __('Loading threads…', 'gta6mods'),
            'share'           => __('Share', 'gta6mods'),
            'searching'       => __('Searching threads…', 'gta6mods'),
            'searchNoResults' => __('No threads matched “%s”.', 'gta6mods'),
        ],
    ], $extra);
}

function gta6_forum_get_flairs_for_frontend(): array {
    $terms = get_terms([
        'taxonomy'   => 'forum_flair',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        return [];
    }

    return array_map(
        static function ($term): array {
            $colors = gta6_forum_get_flair_colors($term->term_id);

            return [
                'id'      => $term->term_id,
                'name'    => $term->name,
                'slug'    => $term->slug,
                'colors'  => [
                    'background' => $colors['background'],
                    'text'       => $colors['text'],
                ],
            ];
        },
        $terms
    );
}

function gta6_forum_is_forum_context(): bool {
    return is_singular('forum_thread')
        || is_tax('forum_flair')
        || is_page_template('template-forum-main.php')
        || is_page_template('template-forum-create.php');
}

function gta6_forum_body_class(array $classes): array {
    if (gta6_forum_is_forum_context()) {
        $classes[] = 'forum-tailwind-active';
    }

    return $classes;
}
add_filter('body_class', 'gta6_forum_body_class');
