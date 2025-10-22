<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function gta6_forum_sanitize_filter_token(string $value): string
{
    $value = strtolower(trim($value));

    return preg_replace('/[^a-z0-9\-]/', '', $value);
}

function gta6_forum_normalize_sort(string $sort): string
{
    $sort = gta6_forum_sanitize_filter_token($sort);
    $allowed = ['hot', 'new', 'top'];

    return in_array($sort, $allowed, true) ? $sort : 'hot';
}

function gta6_forum_normalize_time_range(string $time_range): string
{
    $time_range = gta6_forum_sanitize_filter_token($time_range);
    $allowed    = ['all-time', 'today', 'last-week', 'last-month', 'last-year'];

    return in_array($time_range, $allowed, true) ? $time_range : 'all-time';
}

function gta6_forum_get_top_time_range_options(): array
{
    return [
        'all-time'  => __('All time', 'gta6mods'),
        'today'     => __('Today', 'gta6mods'),
        'last-week' => __('Last week', 'gta6mods'),
        'last-month'=> __('Last month', 'gta6mods'),
        'last-year' => __('Last year', 'gta6mods'),
    ];
}

function gta6_forum_calculate_time_range_cutoff(string $time_range): ?string
{
    $normalized = gta6_forum_normalize_time_range($time_range);

    if ('all-time' === $normalized) {
        return null;
    }

    try {
        $timezone = wp_timezone();
        $now      = new DateTimeImmutable('now', $timezone);

        switch ($normalized) {
            case 'today':
                $start = $now->setTime(0, 0, 0);
                break;
            case 'last-week':
                $start = $now->modify('-7 days');
                break;
            case 'last-month':
                $start = $now->modify('-30 days');
                break;
            case 'last-year':
                $start = $now->modify('-1 year');
                break;
            default:
                $start = null;
                break;
        }

        return $start instanceof DateTimeImmutable ? $start->format('Y-m-d H:i:s') : null;
    } catch (Exception $exception) {
        return null;
    }
}

function gta6_forum_get_main_page(): ?WP_Post
{
    static $resolved = false;
    static $page = null;

    if ($resolved) {
        return $page instanceof WP_Post ? $page : null;
    }

    $pages = get_pages([
        'meta_key'   => '_wp_page_template',
        'meta_value' => 'template-forum-main.php',
        'number'     => 1,
    ]);

    $page = !empty($pages) && $pages[0] instanceof WP_Post ? $pages[0] : null;
    $resolved = true;

    return $page;
}

function gta6_forum_get_flair_description_markup(\WP_Term $term): string
{
    $raw_value = get_term_field('description', $term->term_id, 'forum_flair', 'raw');
    if (is_wp_error($raw_value)) {
        $raw_value = '';
    }

    $raw_value = is_string($raw_value) ? $raw_value : '';

    if ('' === trim($raw_value)) {
        $fallback = sprintf(
            /* translators: %s: flair name */
            esc_html__('Discover the latest “%s” discussions from the GTA VI modding community.', 'gta6mods'),
            esc_html($term->name)
        );

        return wpautop($fallback);
    }

    return wpautop(wp_kses_post($raw_value));
}

function gta6_forum_get_create_thread_url(): string {
    $pages = get_pages([
        'meta_key'   => '_wp_page_template',
        'meta_value' => 'template-forum-create.php',
        'number'     => 1,
    ]);

    if (!empty($pages)) {
        return get_permalink($pages[0]);
    }

    return home_url('/forum/create/');
}

function gta6_forum_get_main_url(): string {
    $page = gta6_forum_get_main_page();

    if ($page instanceof WP_Post) {
        return get_permalink($page);
    }

    return home_url('/forum/');
}

function gta6_forum_get_listing_cache_prefix(bool $force_regenerate = false): string
{
    static $prefix = null;

    if ($force_regenerate) {
        $prefix = null;
    }

    if (null !== $prefix) {
        return $prefix;
    }

    $cached_prefix = $force_regenerate
        ? ''
        : (string) gta6mods_cache_get('forum_listing_prefix', 'gta6_forum_meta', '');

    if ($force_regenerate || '' === $cached_prefix) {
        $cached_prefix = wp_generate_uuid4();
        gta6mods_cache_set('forum_listing_prefix', $cached_prefix, 'gta6_forum_meta', 0);
    }

    $prefix = $cached_prefix;

    return $prefix;
}

function gta6_forum_get_cached_thread_listing(array $args = []): array
{
    $cache_namespace = gta6_forum_get_listing_cache_prefix();
    $cache_key       = 'forum_listing:' . $cache_namespace . ':' . md5(wp_json_encode($args));

    $cached_results = gta6mods_cache_get($cache_key, 'gta6_forum_data');
    if (is_array($cached_results)) {
        return $cached_results;
    }

    $live_results = gta6_forum_get_thread_listing($args);
    gta6mods_cache_set($cache_key, $live_results, 'gta6_forum_data', 600);

    return $live_results;
}

function gta6_forum_flush_redis_listings_cache(int $post_id): void
{
    if ('forum_thread' !== get_post_type($post_id)) {
        return;
    }

    gta6_forum_get_listing_cache_prefix(true);
}
add_action('save_post_forum_thread', 'gta6_forum_flush_redis_listings_cache');
add_action('delete_post', 'gta6_forum_flush_redis_listings_cache');

if (!function_exists('gta6_forum_register_create_page_rewrite')) {
    function gta6_forum_register_create_page_rewrite(): void {
        // Always add an explicit rule for the expected /forum/create/ slug so the child page
        // is never captured by the forum_thread CPT rewrite.
        add_rewrite_rule('^forum/create/?$', 'index.php?pagename=forum/create', 'top');

        $pages = get_pages([
            'meta_key'   => '_wp_page_template',
            'meta_value' => 'template-forum-create.php',
            'number'     => 5,
        ]);

        foreach ($pages as $page) {
            $uri = trim((string) get_page_uri($page), '/');
            if ($uri === '') {
                continue;
            }

            $pattern = '^' . preg_quote($uri, '/') . '/?$';
            add_rewrite_rule($pattern, 'index.php?pagename=' . $uri, 'top');
        }
    }
}
add_action('init', 'gta6_forum_register_create_page_rewrite', 5);

function gta6_forum_register_sort_rewrite_rules(): void
{
    $page    = gta6_forum_get_main_page();
    $baseUri = $page instanceof WP_Post ? trim((string) get_page_uri($page), '/') : 'forum';

    if ('' === $baseUri) {
        $baseUri = 'forum';
    }

    $rewriteTarget = $page instanceof WP_Post
        ? 'index.php?page_id=' . $page->ID
        : 'index.php?pagename=' . $baseUri;

    $escapedBase = preg_quote($baseUri, '/');
    $timePattern = '(today|last-week|last-month|last-year|all-time)';

    add_rewrite_rule('^' . $escapedBase . '\/(hot|new)\/?$', $rewriteTarget . '&forum_sort=$matches[1]', 'top');
    add_rewrite_rule('^' . $escapedBase . '\/top\/?$', $rewriteTarget . '&forum_sort=top&forum_time_range=all-time', 'top');
    add_rewrite_rule('^' . $escapedBase . '\/top\/' . $timePattern . '\/?$', $rewriteTarget . '&forum_sort=top&forum_time_range=$matches[1]', 'top');
}
add_action('init', 'gta6_forum_register_sort_rewrite_rules', 6);

function gta6_forum_render_share_modal(): void
{
    static $rendered = false;

    if ($rendered) {
        return;
    }

    $rendered = true;
    ?>
    <div class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50" data-forum-share-modal aria-hidden="true" role="dialog" aria-modal="true">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm text-center relative dark:bg-gray-800">
            <button type="button" class="absolute top-3 right-3 text-gray-400 hover:text-gray-800 transition dark:text-gray-500 dark:hover:text-gray-200" data-share-close>
                <i class="fa-solid fa-xmark fa-lg" aria-hidden="true"></i>
                <span class="sr-only"><?php esc_html_e('Close', 'gta6mods'); ?></span>
            </button>

            <h3 class="text-xl font-bold text-gray-800 mb-2 dark:text-gray-100" data-share-modal-title><?php esc_html_e('Share this thread', 'gta6mods'); ?></h3>
            <p class="text-gray-500 mb-6 dark:text-gray-300" data-share-modal-description><?php esc_html_e('Pick a platform to spread the word.', 'gta6mods'); ?></p>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <a href="#" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-facebook flex items-center justify-center p-3 rounded-lg font-semibold" data-share-network="facebook">
                    <i class="fa-brands fa-facebook-f mr-2" aria-hidden="true"></i> Facebook
                </a>
                <a href="#" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-x flex items-center justify-center p-3 rounded-lg font-semibold" data-share-network="twitter">
                    <i class="fa-brands fa-x-twitter mr-2" aria-hidden="true"></i> X / Twitter
                </a>
                <a href="#" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-vk flex items-center justify-center p-3 rounded-lg font-semibold" data-share-network="vk">
                    <i class="fa-brands fa-vk mr-2" aria-hidden="true"></i> VK
                </a>
                <a href="#" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-reddit flex items-center justify-center p-3 rounded-lg font-semibold" data-share-network="reddit">
                    <i class="fa-brands fa-reddit-alien mr-2" aria-hidden="true"></i> Reddit
                </a>
                <a href="#" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-whatsapp flex items-center justify-center p-3 rounded-lg font-semibold" data-share-network="whatsapp">
                    <i class="fa-brands fa-whatsapp mr-2" aria-hidden="true"></i> WhatsApp
                </a>
                <a href="#" target="_blank" rel="noopener noreferrer" class="social-share-btn btn-bluesky flex items-center justify-center p-3 rounded-lg font-semibold" data-share-network="bluesky">
                    <i class="fa-solid fa-square-poll-vertical mr-2" aria-hidden="true"></i> Bluesky
                </a>
            </div>

            <button type="button" class="w-full mt-2 p-3 rounded-lg font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 transition flex items-center justify-center gap-2 dark:text-gray-900 dark:bg-gray-100 dark:hover:bg-gray-200" data-share-copy>
                <i class="fa-solid fa-copy" aria-hidden="true"></i> <?php esc_html_e('Copy link', 'gta6mods'); ?>
            </button>
            <p class="text-sm mt-3" data-share-feedback></p>
        </div>
    </div>
    <?php
}

function gta6_forum_get_thread_listing(array $args = []): array
{
    $defaults = [
        'page'       => 1,
        'per_page'   => 10,
        'sort'       => 'hot',
        'time_range' => 'all-time',
        'flair'      => '',
        'search'     => '',
    ];

    $params = wp_parse_args($args, $defaults);

    $params['sort']       = gta6_forum_normalize_sort((string) $params['sort']);
    $params['time_range'] = gta6_forum_normalize_time_range((string) $params['time_range']);

    if ('top' !== $params['sort']) {
        $params['time_range'] = 'all-time';
    }

    if (!function_exists('gta6_forum_rest_list_threads')) {
        return [
            'threads'    => [],
            'pagination' => [
                'total'       => 0,
                'total_pages' => 1,
                'current'     => (int) $params['page'],
                'per_page'    => (int) $params['per_page'],
            ],
        ];
    }

    $request = new WP_REST_Request('GET', '/gta6-forum/v1/threads');
    foreach ($defaults as $key => $_default) {
        $request->set_param($key, $params[$key] ?? $_default);
    }

    $response = rest_ensure_response(gta6_forum_rest_list_threads($request));
    $data     = $response->get_data();

    if (!is_array($data)) {
        return [
            'threads'    => [],
            'pagination' => [
                'total'       => 0,
                'total_pages' => 1,
                'current'     => (int) $params['page'],
                'per_page'    => (int) $params['per_page'],
            ],
        ];
    }

    $threads    = isset($data['threads']) && is_array($data['threads']) ? $data['threads'] : [];
    $pagination = isset($data['pagination']) && is_array($data['pagination']) ? $data['pagination'] : [];

    $total       = isset($pagination['total']) ? (int) $pagination['total'] : count($threads);
    $total_pages = max(1, isset($pagination['total_pages']) ? (int) $pagination['total_pages'] : 1);
    $current     = max(1, isset($pagination['current']) ? (int) $pagination['current'] : (int) $params['page']);
    $per_page    = max(1, isset($pagination['per_page']) ? (int) $pagination['per_page'] : (int) $params['per_page']);

    return [
        'threads'    => $threads,
        'pagination' => [
            'total'       => $total,
            'total_pages' => $total_pages,
            'current'     => $current,
            'per_page'    => $per_page,
        ],
    ];
}

function gta6_forum_format_comment_count(int $count): string
{
    $count = max(0, $count);
    $label = _n('%s comment', '%s comments', $count, 'gta6mods');

    return sprintf($label, number_format_i18n($count));
}

function gta6_forum_render_thread_card(array $thread): void
{
    $thread_id   = isset($thread['id']) ? (int) $thread['id'] : 0;
    $permalink   = isset($thread['permalink']) ? esc_url($thread['permalink']) : '#';
    $raw_title   = isset($thread['title']) ? (string) $thread['title'] : '';
    if ($raw_title !== '') {
        $raw_title = wp_specialchars_decode($raw_title, ENT_QUOTES);
    }
    $title       = $raw_title !== '' ? esc_html($raw_title) : esc_html__('Untitled thread', 'gta6mods');
    $thread_title_text   = $raw_title !== '' ? $raw_title : __('Untitled thread', 'gta6mods');
    $thread_read_label   = sprintf(__('Read thread: %s', 'gta6mods'), $thread_title_text);
    $thread_comments_label = sprintf(__('View comments for %s', 'gta6mods'), $thread_title_text);
    $thread_share_label  = sprintf(__('Share the thread "%s"', 'gta6mods'), $thread_title_text);
    $score       = isset($thread['score']) ? (int) $thread['score'] : 0;
    $score_label = esc_html(number_format_i18n($score));
    $comment_count = isset($thread['comment_count']) ? (int) $thread['comment_count'] : 0;
    $comment_label = esc_html(gta6_forum_format_comment_count($comment_count));
    $share_label   = esc_html__('Share', 'gta6mods');
    $view_count    = isset($thread['views']) ? (int) $thread['views'] : ($thread_id > 0 && function_exists('gta6_forum_get_thread_views') ? gta6_forum_get_thread_views($thread_id) : 0);
    $view_label_text = '';
    if (isset($thread['formatted_views']) && is_string($thread['formatted_views']) && $thread['formatted_views'] !== '') {
        $view_label_text = $thread['formatted_views'];
    } else {
        $view_label_text = function_exists('gta6_forum_format_view_count')
            ? gta6_forum_format_view_count($view_count)
            : sprintf(_n('%s view', '%s views', $view_count, 'gta6mods'), number_format_i18n($view_count));
    }
    $view_label    = esc_html($view_label_text);

    $bookmark_endpoint = isset($thread['bookmark_endpoint']) ? esc_url($thread['bookmark_endpoint']) : '';
    $is_bookmarked     = !empty($thread['is_bookmarked']);
    $bookmark_label    = $is_bookmarked ? esc_html__('Saved', 'gta6mods') : esc_html__('Bookmark', 'gta6mods');
    $bookmark_classes  = 'thread-action-button flex items-center gap-2 hover:text-pink-600';
    if ($is_bookmarked) {
        $bookmark_classes .= ' is-active';
    }

    $current_vote  = isset($thread['current_user_vote']) ? (int) $thread['current_user_vote'] : 0;
    $upvote_class  = 'vote-button';
    $downvote_class = 'vote-button';
    if (1 === $current_vote) {
        $upvote_class .= ' upvoted';
    } elseif (-1 === $current_vote) {
        $downvote_class .= ' downvoted';
    }

    $thread_type = isset($thread['type']) ? sanitize_key((string) $thread['type']) : 'text';
    $excerpt     = isset($thread['excerpt']) ? trim((string) $thread['excerpt']) : '';
    if ($excerpt !== '') {
        $excerpt = wp_specialchars_decode($excerpt, ENT_QUOTES);
    }

    $author_name_raw = isset($thread['author']['name']) ? (string) $thread['author']['name'] : '';
    if ('' === $author_name_raw) {
        $author_name_raw = __('Anonymous', 'gta6mods');
    }
    $author_name       = esc_html($author_name_raw);
    $author_url        = isset($thread['author']['url']) ? esc_url($thread['author']['url']) : '#';
    $author_profile_title = sprintf(
        /* translators: %s: author display name */
        __('View profile of %s', 'gta6mods'),
        $author_name_raw
    );
    $author_profile_aria = sprintf(
        /* translators: %s: author display name */
        __('Author profile for %s', 'gta6mods'),
        $author_name_raw
    );
    $author_link = sprintf(
        '<a href="%1$s" class="font-semibold text-amber-600 hover:underline" rel="author" title="%2$s" aria-label="%3$s">%4$s</a>',
        $author_url,
        esc_attr($author_profile_title),
        esc_attr($author_profile_aria),
        $author_name
    );

    $created_at = isset($thread['created_at']) ? strtotime((string) $thread['created_at']) : 0;
    if ($created_at <= 0 && $thread_id > 0) {
        $created_at = get_post_time('U', true, $thread_id);
    }

    $time_ago = '';
    if ($created_at > 0) {
        $time_ago = human_time_diff($created_at, current_time('timestamp', true));
    }

    if ($time_ago !== '') {
        $meta_text = sprintf(
            /* translators: 1: author link, 2: human readable time diff */
            __('Posted by %1$s · %2$s ago', 'gta6mods'),
            $author_link,
            esc_html($time_ago)
        );
    } else {
        $meta_text = sprintf(
            /* translators: %s: author link */
            __('Posted by %s', 'gta6mods'),
            $author_link
        );
    }

    $meta_markup = wp_kses(
        $meta_text,
        [
            'a' => [
                'href'       => [],
                'class'      => [],
                'rel'        => [],
                'title'      => [],
                'aria-label' => [],
            ],
        ]
    );

    $flair_items = [];
    if (!empty($thread['flairs']) && is_array($thread['flairs'])) {
        foreach ($thread['flairs'] as $flair) {
            $flair_name_raw = isset($flair['name']) ? (string) $flair['name'] : '';
            $flair_name  = $flair_name_raw !== '' ? esc_html($flair_name_raw) : '';
            if ('' === $flair_name) {
                continue;
            }
            $flair_link  = isset($flair['link']) ? esc_url($flair['link']) : '';
            $flair_slug  = isset($flair['slug']) ? esc_attr($flair['slug']) : '';
            $background  = isset($flair['colors']['background']) ? esc_attr($flair['colors']['background']) : '';
            $text_color  = isset($flair['colors']['text']) ? esc_attr($flair['colors']['text']) : '';
            $style       = '';
            if ('' !== $background) {
                $style .= 'background-color: ' . $background . ';';
            }
            if ('' !== $text_color) {
                $style .= ' color: ' . $text_color . ';';
            }

            $flair_label_text = sprintf(
                /* translators: %s: flair name */
                __('Browse threads tagged with %s', 'gta6mods'),
                $flair_name_raw !== '' ? $flair_name_raw : __('this flair', 'gta6mods')
            );

            $flair_items[] = sprintf(
                '<a href="%1$s" class="post-flair thread-flair" data-flair="%2$s" rel="tag"%4$s title="%5$s" aria-label="%5$s">%3$s</a>',
                $flair_link,
                $flair_slug,
                $flair_name,
                $style ? ' style="' . esc_attr($style) . '"' : '',
                esc_attr($flair_label_text)
            );
        }
    }

    $flair_wrapper_classes = 'flex flex-wrap items-center gap-2';
    if (empty($flair_items)) {
        $flair_wrapper_classes .= ' hidden';
    }

    $media_html = '';
    if ('image' === $thread_type && !empty($thread['image']) && is_array($thread['image'])) {
        $image_url = '';
        if (!empty($thread['image']['preview_url'])) {
            $image_url = esc_url($thread['image']['preview_url']);
        } elseif (!empty($thread['image']['full_url'])) {
            $image_url = esc_url($thread['image']['full_url']);
        }
        if ('' !== $image_url) {
            $image_alt = !empty($thread['image']['alt']) ? $thread['image']['alt'] : $thread_title_text;
            $dimensions = gta6m_get_image_dimensions_from_metadata($image_url);
            $dimension_attr = '';
            if (is_array($dimensions)) {
                $dimension_attr = sprintf(' width="%d" height="%d"', (int) $dimensions['width'], (int) $dimensions['height']);
            }

            $media_html = sprintf(
                '<a href="%1$s" class="forum-thread-image-wrapper" title="%4$s" aria-label="%4$s"><img src="%2$s" alt="%3$s" class="forum-thread-image" loading="lazy" decoding="async"%5$s></a>',
                $permalink,
                $image_url,
                esc_attr($image_alt),
                esc_attr($thread_read_label),
                $dimension_attr
            );
        }
    } elseif ('link' === $thread_type && !empty($thread['link']['url'])) {
        $link_url   = esc_url($thread['link']['url']);
        $link_label = !empty($thread['link']['display']) ? esc_html($thread['link']['display']) : esc_html($thread['link']['url']);
        $external_link_label = sprintf(
            /* translators: %s: external link label */
            __('Open external resource: %s (opens in new tab)', 'gta6mods'),
            $link_label
        );
        $media_html = sprintf(
            '<a href="%1$s" target="_blank" rel="nofollow noopener ugc" class="forum-thread-link" title="%3$s" aria-label="%3$s"><span class="forum-thread-link__label">%2$s<i class="fas fa-external-link-alt" aria-hidden="true"></i></span></a>',
            $link_url,
            $link_label,
            esc_attr($external_link_label)
        );
        $excerpt = '';
    }

    $excerpt_html = '';
    if ($excerpt !== '') {
        $excerpt_html = '<p class="text-gray-700 text-sm leading-relaxed forum-thread-excerpt">' . esc_html($excerpt) . '</p>';
    }

    $comments_link = '#';
    if ($permalink && '#' !== $permalink) {
        $comments_link = $permalink . '#comments';
    }

    $share_title = isset($thread['title']) ? esc_attr($thread['title']) : '';

    ?>
    <article class="card thread-card"<?php echo $thread_id > 0 ? ' data-thread-id="' . esc_attr((string) $thread_id) . '"' : ''; ?><?php echo $thread_type ? ' data-thread-type="' . esc_attr($thread_type) . '"' : ''; ?>>
        <div class="flex">
            <div class="flex flex-col items-center bg-gray-50 dark:bg-gray-800 p-2 space-y-1" data-vote-wrapper<?php echo $thread_id > 0 ? ' data-thread-id="' . esc_attr((string) $thread_id) . '"' : ''; ?>>
                <button class="<?php echo esc_attr($upvote_class); ?>" data-vote="up" aria-label="<?php echo esc_attr__('Upvote thread', 'gta6mods'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-up-icon lucide-arrow-big-up"><path d="M9 13a1 1 0 0 0-1-1H5.061a1 1 0 0 1-.75-1.811l6.836-6.835a1.207 1.207 0 0 1 1.707 0l6.835 6.835a1 1 0 0 1-.75 1.811H16a1 1 0 0 0-1 1v6a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z"/></svg>
                </button>
                <span class="font-bold text-sm text-gray-800 dark:text-gray-100" data-score><?php echo $score_label; ?></span>
                <button class="<?php echo esc_attr($downvote_class); ?>" data-vote="down" aria-label="<?php echo esc_attr__('Downvote thread', 'gta6mods'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-down-icon lucide-arrow-big-down"><path d="M15 11a1 1 0 0 0 1 1h2.939a1 1 0 0 1 .75 1.811l-6.835 6.836a1.207 1.207 0 0 1-1.707 0L4.31 13.81a1 1 0 0 1 .75-1.811H8a1 1 0 0 0 1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1z"/></svg>
                </button>
            </div>
            <div class="p-4 w-full">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-gray-500 mb-2">
                    <div class="<?php echo esc_attr($flair_wrapper_classes); ?>" data-flair-wrapper>
                        <?php echo implode('', $flair_items); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <span class="text-xs text-gray-500" data-thread-meta><?php echo $meta_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </div>
                <a href="<?php echo $permalink; ?>" class="block" title="<?php echo esc_attr($thread_read_label); ?>" aria-label="<?php echo esc_attr($thread_read_label); ?>">
                    <h3 class="text-lg font-bold text-gray-900 mb-1 hover:text-pink-600 transition"><?php echo $title; ?></h3>
                </a>
                <?php if ('' !== $media_html) : ?>
                    <?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
                <?php if ('' !== $excerpt_html) : ?>
                    <?php echo $excerpt_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 font-semibold mt-4">
                    <span class="flex items-center gap-2" aria-label="<?php echo esc_attr(sprintf(__('Thread views: %s', 'gta6mods'), $view_label_text)); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M12.0003 3C17.3924 3 21.8784 6.87976 22.8189 12C21.8784 17.1202 17.3924 21 12.0003 21C6.60812 21 2.12215 17.1202 1.18164 12C2.12215 6.87976 6.60812 3 12.0003 3ZM12.0003 19C16.2359 19 19.8603 16.052 20.7777 12C19.8603 7.94803 16.2359 5 12.0003 5C7.7646 5 4.14022 7.94803 3.22278 12C4.14022 16.052 7.7646 19 12.0003 19ZM12.0003 16.5C9.51498 16.5 7.50026 14.4853 7.50026 12C7.50026 9.51472 9.51498 7.5 12.0003 7.5C14.4855 7.5 16.5003 9.51472 16.5003 12C16.5003 14.4853 14.4855 16.5 12.0003 16.5ZM12.0003 14.5C13.381 14.5 14.5003 13.3807 14.5003 12C14.5003 10.6193 13.381 9.5 12.0003 9.5C10.6196 9.5 9.50026 10.6193 9.50026 12C9.50026 13.3807 10.6196 14.5 12.0003 14.5Z"></path></svg>
                        <span><?php echo $view_label; ?></span>
                    </span>
                    <a href="<?php echo esc_url($comments_link); ?>" class="flex items-center gap-2 hover:text-pink-600" title="<?php echo esc_attr($thread_comments_label); ?>" aria-label="<?php echo esc_attr($thread_comments_label); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M10 3H14C18.4183 3 22 6.58172 22 11C22 15.4183 18.4183 19 14 19V22.5C9 20.5 2 17.5 2 11C2 6.58172 5.58172 3 10 3ZM12 17H14C17.3137 17 20 14.3137 20 11C20 7.68629 17.3137 5 14 5H10C6.68629 5 4 7.68629 4 11C4 14.61 6.46208 16.9656 12 19.4798V17Z"></path></svg>
                        <span><?php echo $comment_label; ?></span>
                    </a>
                    <button type="button" class="thread-action-button flex items-center gap-2 hover:text-pink-600" data-share-trigger data-share-title="<?php echo $share_title; ?>" data-share-url="<?php echo $permalink; ?>" aria-label="<?php echo esc_attr($thread_share_label); ?>" title="<?php echo esc_attr($thread_share_label); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M13.1202 17.0228L8.92129 14.7324C8.19135 15.5125 7.15261 16 6 16C3.79086 16 2 14.2091 2 12C2 9.79086 3.79086 8 6 8C7.15255 8 8.19125 8.48746 8.92118 9.26746L13.1202 6.97713C13.0417 6.66441 13 6.33707 13 6C13 3.79086 14.7909 2 17 2C19.2091 2 21 3.79086 21 6C21 8.20914 19.2091 10 17 10C15.8474 10 14.8087 9.51251 14.0787 8.73246L9.87977 11.0228C9.9583 11.3355 10 11.6629 10 12C10 12.3371 9.95831 12.6644 9.87981 12.9771L14.0788 15.2675C14.8087 14.4875 15.8474 14 17 14C19.2091 14 21 15.7909 21 18C21 20.2091 19.2091 22 17 22C14.7909 22 13 20.2091 13 18C13 17.6629 13.0417 17.3355 13.1202 17.0228ZM6 14C7.10457 14 8 13.1046 8 12C8 10.8954 7.10457 10 6 10C4.89543 10 4 10.8954 4 12C4 13.1046 4.89543 14 6 14ZM17 8C18.1046 8 19 7.10457 19 6C19 4.89543 18.1046 4 17 4C15.8954 4 15 4.89543 15 6C15 7.10457 15.8954 8 17 8ZM17 20C18.1046 20 19 19.1046 19 18C19 16.8954 18.1046 16 17 16C15.8954 16 15 16.8954 15 18C15 19.1046 15.8954 20 17 20Z"></path></svg>
                        <span><?php echo $share_label; ?></span>
                    </button>
                    <?php if ($bookmark_endpoint !== '') : ?>
                        <?php
                        $bookmark_action_label = $is_bookmarked
                            ? __('Remove bookmark for this thread', 'gta6mods')
                            : __('Bookmark this thread for later', 'gta6mods');
                        ?>
                        <button type="button" class="<?php echo esc_attr($bookmark_classes); ?>" data-bookmark-button data-bookmark-endpoint="<?php echo $bookmark_endpoint; ?>" data-bookmarked="<?php echo $is_bookmarked ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr($bookmark_action_label); ?>" aria-pressed="<?php echo $is_bookmarked ? 'true' : 'false'; ?>" title="<?php echo esc_attr($bookmark_action_label); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true" data-bookmark-icon><path d="M5 2H19C19.5523 2 20 2.44772 20 3V22.1433C20 22.4194 19.7761 22.6434 19.5 22.6434C19.4061 22.6434 19.314 22.6168 19.2344 22.5669L12 18.0313L4.76559 22.5669C4.53163 22.7136 4.22306 22.6429 4.07637 22.4089C4.02647 22.3293 4 22.2373 4 22.1433V3C4 2.44772 4.44772 2 5 2ZM18 4H6V19.4324L12 15.6707L18 19.4324V4Z"></path></svg>
                            <span data-bookmark-label><?php echo $bookmark_label; ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </article>
    <?php
}
