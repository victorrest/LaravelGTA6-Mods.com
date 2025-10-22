<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function gta6_forum_register_thread_routes(): void {
    $namespace = 'gta6-forum/v1';

    register_rest_route(
        $namespace,
        '/threads',
        [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'gta6_forum_rest_list_threads',
                'permission_callback' => '__return_true',
                'args'                => [
                    'page' => [
                        'sanitize_callback' => 'absint',
                        'default'           => 1,
                    ],
                    'per_page' => [
                        'sanitize_callback' => 'absint',
                        'default'           => 20,
                    ],
                    'sort' => [
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'hot',
                    ],
                    'time_range' => [
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'all-time',
                    ],
                    'flair' => [
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'search' => [
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'gta6_forum_rest_create_thread',
                'permission_callback' => static fn(): bool => current_user_can('publish_posts'),
                'args'                => [
                    'title' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'content' => [
                        'required'          => false,
                        'sanitize_callback' => 'wp_kses_post',
                    ],
                    'flair' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'type' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'external_url' => [
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'related_mod_url' => [
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'media_id' => [
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]
    );

    register_rest_route(
        $namespace,
        '/threads/(?P<id>\d+)/bookmark',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6_forum_rest_toggle_thread_bookmark',
            'permission_callback' => static fn(): bool => is_user_logged_in(),
        ]
    );
}
add_action('rest_api_init', 'gta6_forum_register_thread_routes');

/**
 * Validates and normalises the related mod URL submitted with a thread.
 *
 * @return string|WP_Error
 */
function gta6_forum_validate_related_mod_url(string $url)
{
    $url = trim($url);

    if ('' === $url) {
        return '';
    }

    $parsed = wp_parse_url($url);
    if (!is_array($parsed) || empty($parsed['host'])) {
        return new WP_Error(
            'invalid_related_mod_url',
            __('Please provide a valid GTA6-Mods.com URL (https://gta6-mods.com/...)', 'gta6mods'),
            ['status' => 400]
        );
    }

    $host          = strtolower($parsed['host']);
    $allowed_hosts = apply_filters('gta6_forum_allowed_related_mod_hosts', ['gta6-mods.com', 'www.gta6-mods.com']);

    if (!in_array($host, $allowed_hosts, true)) {
        return new WP_Error(
            'invalid_related_mod_url',
            __('Please provide a valid GTA6-Mods.com URL (https://gta6-mods.com/...)', 'gta6mods'),
            ['status' => 400]
        );
    }

    $normalized = esc_url_raw(set_url_scheme($url, 'https'));

    if ('' === $normalized) {
        return new WP_Error(
            'invalid_related_mod_url',
            __('Please provide a valid GTA6-Mods.com URL (https://gta6-mods.com/...)', 'gta6mods'),
            ['status' => 400]
        );
    }

    return $normalized;
}

function gta6_forum_rest_list_threads(WP_REST_Request $request): WP_REST_Response {
    $page      = max(1, (int) $request->get_param('page'));
    $perPage   = min(50, max(1, (int) $request->get_param('per_page')));
    $sort      = gta6_forum_normalize_sort((string) $request->get_param('sort'));
    $timeRange = gta6_forum_normalize_time_range((string) $request->get_param('time_range'));
    $flair     = (string) $request->get_param('flair');
    $search    = trim((string) $request->get_param('search'));

    if ('top' !== $sort) {
        $timeRange = 'all-time';
    }

    $metaKey = '';
    $orderby = 'date';
    $order   = 'DESC';

    if ('' === $search) {
        switch ($sort) {
            case 'new':
                $orderby = 'date';
                $metaKey = '';
                break;
            case 'top':
                $metaKey = '_thread_score';
                $orderby = 'meta_value_num';
                break;
            case 'hot':
            default:
                $metaKey = '_hot_score';
                $orderby = 'meta_value_num';
                break;
        }
    }

    $queryArgs = [
        'post_type'      => 'forum_thread',
        'post_status'    => 'publish',
        'posts_per_page' => $perPage,
        'paged'          => $page,
        'no_found_rows'  => false,
        'orderby'        => $orderby,
        'order'          => $order,
    ];

    if ('' === $search && !empty($metaKey)) {
        $queryArgs['meta_key'] = $metaKey;
    }

    if ('top' === $sort && 'all-time' !== $timeRange) {
        $cutoff = gta6_forum_calculate_time_range_cutoff($timeRange);
        if ($cutoff) {
            $queryArgs['date_query'] = [
                [
                    'after'     => $cutoff,
                    'inclusive' => true,
                    'column'    => 'post_date',
                ],
            ];
        }
    }

    if (!empty($flair)) {
        $queryArgs['tax_query'] = [
            [
                'taxonomy' => 'forum_flair',
                'field'    => 'slug',
                'terms'    => $flair,
            ],
        ];
    }

    $searchFilterAdded = false;
    if ('' !== $search) {
        $queryArgs['s'] = $search;
        $queryArgs['search_columns'] = ['post_title', 'post_content'];
        $queryArgs['gta6_forum_search_term'] = $search;
        add_filter('posts_clauses', 'gta6_forum_search_posts_clauses', 10, 2);
        $searchFilterAdded = true;
    }

    $bust = wp_cache_get('thread-list-bust', 'gta6_forum_rest');
    if (false === $bust) {
        $bust = (string) time();
        wp_cache_set('thread-list-bust', $bust, 'gta6_forum_rest', HOUR_IN_SECONDS);
    }

    $cacheKey = 'thread-list:' . $bust . ':' . md5(wp_json_encode($queryArgs));
    $cached = wp_cache_get($cacheKey, 'gta6_forum_rest');
    if (false !== $cached) {
        if ($searchFilterAdded) {
            remove_filter('posts_clauses', 'gta6_forum_search_posts_clauses', 10);
        }
        return new WP_REST_Response($cached, 200);
    }

    $query = new WP_Query($queryArgs);

    if ($searchFilterAdded) {
        remove_filter('posts_clauses', 'gta6_forum_search_posts_clauses', 10);
    }

    $threads = array_map('gta6_forum_rest_prepare_thread', $query->posts);

    $response = [
        'threads' => $threads,
        'pagination' => [
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'current'     => $page,
            'per_page'    => $perPage,
        ],
    ];

    wp_cache_set($cacheKey, $response, 'gta6_forum_rest', MINUTE_IN_SECONDS * 5);

    return new WP_REST_Response($response, 200);
}

function gta6_forum_rest_prepare_thread(WP_Post $post): array {
    $flairs = get_the_terms($post, 'forum_flair');
    $type   = (string) get_post_meta($post->ID, '_thread_post_type', true);
    $type   = $type !== '' ? $type : 'text';

    $externalUrl = (string) get_post_meta($post->ID, '_thread_external_url', true);
    $mediaId     = (int) get_post_meta($post->ID, '_thread_media_id', true);

    $imageData = null;
    if ('image' === $type) {
        if ($mediaId > 0) {
            $preview = wp_get_attachment_image_src($mediaId, 'large');
            $full    = wp_get_attachment_image_src($mediaId, 'full');
            $alt     = trim((string) get_post_meta($mediaId, '_wp_attachment_image_alt', true));
            if ('' === $alt) {
                $alt = get_the_title($mediaId) ?: get_the_title($post);
            }

            $imageData = [
                'id'          => $mediaId,
                'preview_url' => $preview ? esc_url_raw($preview[0]) : ($full ? esc_url_raw($full[0]) : ''),
                'full_url'    => $full ? esc_url_raw($full[0]) : ($preview ? esc_url_raw($preview[0]) : ''),
                'width'       => $preview ? (int) $preview[1] : ($full ? (int) $full[1] : 0),
                'height'      => $preview ? (int) $preview[2] : ($full ? (int) $full[2] : 0),
                'alt'         => sanitize_text_field($alt),
            ];
        } elseif ('' !== $externalUrl) {
            $imageData = [
                'id'          => 0,
                'preview_url' => esc_url_raw($externalUrl),
                'full_url'    => esc_url_raw($externalUrl),
                'width'       => 0,
                'height'      => 0,
                'alt'         => sanitize_text_field(get_the_title($post)),
            ];
        }
    }

    $linkData = null;
    if ('link' === $type && '' !== $externalUrl) {
        $parsed = wp_parse_url($externalUrl);
        $host   = isset($parsed['host']) ? sanitize_text_field($parsed['host']) : '';
        $display = $host;
        if (!empty($parsed['path'])) {
            $display .= '/' . ltrim((string) $parsed['path'], '/');
        }
        if (!empty($parsed['query'])) {
            $display .= '?' . $parsed['query'];
        }
        $display = trim($display);
        if ('' === $display) {
            $display = $externalUrl;
        }
        $display = wp_html_excerpt($display, 120, '…');

        $linkData = [
            'url'     => esc_url_raw($externalUrl),
            'host'    => $host,
            'display' => sanitize_text_field($display),
        ];
    }

    $excerpt = '';
    if ('link' !== $type) {
        $excerpt = wp_strip_all_tags(wp_trim_words($post->post_content, 55));
        $excerpt = $excerpt !== '' ? wp_specialchars_decode($excerpt, ENT_QUOTES) : '';
    }

    $title = get_the_title($post);
    if (is_string($title)) {
        $title = wp_specialchars_decode($title, ENT_QUOTES);
    }

    $views = function_exists('gta6_forum_get_thread_views') ? gta6_forum_get_thread_views($post->ID) : (int) get_post_meta($post->ID, '_thread_views', true);

    return [
        'id'          => $post->ID,
        'title'       => $title,
        'excerpt'     => $excerpt,
        'author'      => [
            'id'       => $post->post_author,
            'name'     => get_the_author_meta('display_name', $post->post_author),
            'avatar'   => get_avatar_url($post->post_author, ['size' => 64]),
            'url'      => get_author_posts_url($post->post_author),
        ],
        'permalink'   => get_permalink($post),
        'comment_count' => (int) get_comments_number($post),
        'score'       => (int) get_post_meta($post->ID, '_thread_score', true),
        'hot_score'   => (float) get_post_meta($post->ID, '_hot_score', true),
        'views'       => (int) $views,
        'formatted_views' => function_exists('gta6_forum_format_view_count') ? gta6_forum_format_view_count((int) $views) : sprintf(_n('%s view', '%s views', (int) $views, 'gta6mods'), number_format_i18n((int) $views)),
        'current_user_vote' => gta6_forum_get_user_vote('thread', $post->ID),
        'is_bookmarked' => is_user_logged_in() ? gta6_forum_is_thread_bookmarked_by_user($post->ID) : false,
        'bookmark_endpoint' => rest_url('gta6-forum/v1/threads/' . $post->ID . '/bookmark'),
        'flairs'      => is_array($flairs) ? array_map(
            static function ($flair): array {
                $colors = gta6_forum_get_flair_colors($flair->term_id);
                $link   = get_term_link($flair);
                if (is_wp_error($link)) {
                    $link = '';
                }

                return [
                    'id'     => $flair->term_id,
                    'name'   => $flair->name,
                    'slug'   => $flair->slug,
                    'link'   => $link ? esc_url_raw($link) : '',
                    'colors' => [
                        'background' => $colors['background'],
                        'text'       => $colors['text'],
                    ],
                ];
            },
            $flairs
        ) : [],
        'related_mod_url' => (string) get_post_meta($post->ID, '_thread_related_mod_url', true),
        'external_url'    => '' !== $externalUrl ? esc_url_raw($externalUrl) : '',
        'type'            => $type,
        'image'           => $imageData,
        'link'            => $linkData,
        'created_at'  => get_post_time(DATE_RFC3339, true, $post),
    ];
}

function gta6_forum_rest_toggle_thread_bookmark(WP_REST_Request $request)
{
    $threadId = absint($request['id']);

    if ($threadId <= 0 || 'forum_thread' !== get_post_type($threadId)) {
        return new WP_Error('invalid_thread', __('The requested thread could not be found.', 'gta6mods'), ['status' => 404]);
    }

    $userId = get_current_user_id();
    if ($userId <= 0) {
        return new WP_Error('not_logged_in', __('You must be signed in to manage bookmarks.', 'gta6mods'), ['status' => 401]);
    }

    $bookmarks = gta6_forum_get_user_bookmarked_thread_ids($userId);
    $isBookmarked = in_array($threadId, $bookmarks, true);

    if ($isBookmarked) {
        $bookmarks = array_values(array_diff($bookmarks, [$threadId]));
    } else {
        $bookmarks[] = $threadId;
    }

    gta6_forum_update_user_bookmarked_thread_ids($userId, $bookmarks);

    return new WP_REST_Response([
        'is_bookmarked' => !$isBookmarked,
    ]);
}

function gta6_forum_prepare_thread_content(string $content, string $type): string
{
    $content = trim($content);

    if ($content === '') {
        return '';
    }

    $content = wp_kses_post($content);

    if ('text' === $type) {
        $content = wpautop($content);
    }

    return $content;
}

function gta6_forum_rest_create_thread(WP_REST_Request $request): WP_REST_Response {
    $title        = sanitize_text_field((string) $request->get_param('title'));
    $flair        = sanitize_key((string) $request->get_param('flair'));
    $type         = sanitize_key((string) $request->get_param('type'));
    $content      = gta6_forum_prepare_thread_content((string) $request->get_param('content'), $type);
    $externalUrl  = (string) $request->get_param('external_url');
    $relatedModUrl = (string) $request->get_param('related_mod_url');
    $validated_related_mod_url = gta6_forum_validate_related_mod_url($relatedModUrl);
    if (is_wp_error($validated_related_mod_url)) {
        return $validated_related_mod_url;
    }
    $mediaId      = (int) $request->get_param('media_id');

    $postId = wp_insert_post([
        'post_title'   => $title,
        'post_content' => $content,
        'post_type'    => 'forum_thread',
        'post_status'  => 'publish',
        'meta_input'   => [
            '_thread_score' => 0,
            '_hot_score'    => 0,
        ],
    ]);

    if (is_wp_error($postId)) {
        return new WP_REST_Response([
            'message' => __('Failed to create thread.', 'gta6mods'),
            'errors'  => $postId->get_error_messages(),
        ], 400);
    }

    if (!empty($flair)) {
        wp_set_object_terms($postId, [$flair], 'forum_flair', false);
    }

    if (!empty($type)) {
        update_post_meta($postId, '_thread_post_type', $type);
    }

    if (!empty($externalUrl)) {
        update_post_meta($postId, '_thread_external_url', esc_url_raw($externalUrl));
    }

    if ($validated_related_mod_url !== '') {
        update_post_meta($postId, '_thread_related_mod_url', $validated_related_mod_url);
    } else {
        delete_post_meta($postId, '_thread_related_mod_url');
    }

    if ($mediaId > 0) {
        update_post_meta($postId, '_thread_media_id', $mediaId);
        set_post_thumbnail($postId, $mediaId);
    }

    clean_post_cache($postId);
    wp_cache_set('thread-list-bust', (string) microtime(true), 'gta6_forum_rest', HOUR_IN_SECONDS);

    do_action('gta6_forum_thread_created', $postId);

    $post = get_post($postId);

    return new WP_REST_Response([
        'thread' => gta6_forum_rest_prepare_thread($post),
    ], 201);
}

function gta6_forum_search_posts_clauses(array $clauses, WP_Query $query): array
{
    $searchTerm = (string) $query->get('gta6_forum_search_term');
    if ('' === $searchTerm) {
        return $clauses;
    }

    global $wpdb;

    $normalized = function_exists('mb_strtolower') ? mb_strtolower($searchTerm, 'UTF-8') : strtolower($searchTerm);
    $prefix     = $normalized . '%';
    $anywhere   = '%' . $normalized . '%';

    $ordering = $wpdb->prepare(
        "CASE
            WHEN LOWER({$wpdb->posts}.post_title) = %s THEN 0
            WHEN LOWER({$wpdb->posts}.post_title) LIKE %s THEN 1
            WHEN LOWER({$wpdb->posts}.post_title) LIKE %s THEN 2
            WHEN LOWER({$wpdb->posts}.post_content) LIKE %s THEN 3
            ELSE 4
        END",
        $normalized,
        $prefix,
        $anywhere,
        $anywhere
    );

    $clauses['orderby'] = $ordering . ', ' . $wpdb->posts . '.post_date DESC';

    return $clauses;
}
