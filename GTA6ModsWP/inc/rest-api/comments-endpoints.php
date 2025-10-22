<?php
/**
 * REST API endpoints for comment interactions.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers REST API routes for the comment system.
 */
function gta6mods_rest_sanitize_comment_content($param) {
    $value = wp_unslash((string) $param);
    $value = wp_kses($value, gta6mods_get_allowed_comment_html());

    return trim($value);
}

function gta6mods_register_comment_rest_routes() {
    register_rest_route(
        'gta6mods/v1',
        '/comments/(?P<post_id>\d+)',
        [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'gta6mods_rest_get_post_comments',
                'permission_callback' => '__return_true',
                'args'                => [
                    'orderby' => [
                        'description'       => __('Comment sort order.', 'gta6-mods'),
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => static function ($param) {
                            return in_array($param, ['best', 'newest', 'oldest'], true);
                        },
                        'default'           => 'best',
                    ],
                    'page' => [
                        'description'       => __('The comments page to load.', 'gta6-mods'),
                        'sanitize_callback' => 'absint',
                        'default'           => 1,
                    ],
                    'per_page' => [
                        'description'       => __('Number of top-level comments per page.', 'gta6-mods'),
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'gta6mods_rest_submit_post_comment',
                'permission_callback' => 'gta6mods_rest_can_submit_comment',
                'args'                => [
                    'comment' => [
                        'required'          => true,
                        'sanitize_callback' => 'gta6mods_rest_sanitize_comment_content',
                    ],
                    'comment_parent' => [
                        'sanitize_callback' => 'absint',
                        'default'           => 0,
                    ],
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/comments/mentions',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_search_comment_mentions',
            'permission_callback' => '__return_true',
            'args'                => [
                'search' => [
                    'description'       => __('Partial username to search for.', 'gta6-mods'),
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/comments/(?P<id>\d+)/like',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_toggle_comment_like',
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/comments/(?P<id>\d+)/report',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_report_comment',
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
            'args'                => [
                'reason' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'details' => [
                    'sanitize_callback' => static function ($value) {
                        return wp_strip_all_tags((string) $value);
                    },
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/comments/(?P<id>\d+)/retract',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_retract_comment',
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/comments/(?P<id>\d+)/pin',
        [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'gta6mods_rest_pin_comment',
                'permission_callback' => 'gta6mods_rest_can_manage_comment_pin',
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => 'gta6mods_rest_unpin_comment',
                'permission_callback' => 'gta6mods_rest_can_manage_comment_pin',
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_comment_rest_routes');

/**
 * Ensures comment submissions include a valid nonce before processing.
 *
 * @param WP_REST_Request $request Request instance.
 * @return true|WP_Error
 */
function gta6mods_rest_can_submit_comment(WP_REST_Request $request) {
    if (gta6mods_rest_request_has_valid_comment_nonce($request)) {
        return true;
    }

    return new WP_Error(
        'rest_forbidden',
        __('Security verification failed. Please refresh the page and try again.', 'gta6-mods'),
        ['status' => 403]
    );
}

/**
 * Builds an array of WP_Comment objects representing a flattened thread order.
 *
 * @param int   $parent_id    Parent comment ID.
 * @param array $children_map Map of parent IDs to child comment arrays.
 *
 * @return WP_Comment[]
 */
function gta6mods_flatten_comment_children($parent_id, $children_map) {
    if (!isset($children_map[$parent_id]) || !is_array($children_map[$parent_id])) {
        return [];
    }

    $ordered = [];

    foreach ($children_map[$parent_id] as $child_comment) {
        if (!$child_comment instanceof WP_Comment) {
            continue;
        }

        $ordered[] = $child_comment;
        $ordered   = array_merge($ordered, gta6mods_flatten_comment_children($child_comment->comment_ID, $children_map));
    }

    return $ordered;
}

/**
 * Returns the like count for a comment using a local static cache.
 *
 * @param WP_Comment|int $comment Comment object or ID.
 *
 * @return int
 */
function gta6mods_get_cached_comment_like_count($comment) {
    static $cache = [];

    $comment_id = $comment instanceof WP_Comment ? (int) $comment->comment_ID : (int) $comment;

    if ($comment_id <= 0) {
        return 0;
    }

    if (!array_key_exists($comment_id, $cache)) {
        $count = 0;

        if (function_exists('gta6_mods_get_comment_like_data')) {
            $like_data = gta6_mods_get_comment_like_data($comment_id);
            if (is_array($like_data) && isset($like_data['count'])) {
                $count = (int) $like_data['count'];
            }
        } else {
            $likes = get_comment_meta($comment_id, 'gta6_comment_likes', true);
            $likes = is_array($likes) ? array_values(array_unique(array_filter(array_map('absint', $likes)))) : [];
            $count = count($likes);
        }

        $cache[$comment_id] = $count;
    }

    return $cache[$comment_id];
}

/**
 * Builds the comments payload for a given post and configuration.
 *
 * @param int   $post_id Post ID.
 * @param array $args    Optional arguments (orderby, page, per_page).
 *
 * @return array<string, mixed>|WP_Error
 */
function gta6mods_build_comments_payload($post_id, array $args = []) {
    $post_id = (int) $post_id;
    $post    = get_post($post_id);

    $allowed_types = gta6mods_get_commentable_post_types();

    if (!$post instanceof WP_Post || !in_array($post->post_type, $allowed_types, true)) {
        return new WP_Error('invalid_post', __('Invalid post.', 'gta6-mods'), ['status' => 400]);
    }

    if ('post' === $post->post_type && function_exists('gta6mods_prime_mod_stats')) {
        gta6mods_prime_mod_stats([$post_id]);
    }

    $orderby = isset($args['orderby']) ? (string) $args['orderby'] : 'best';
    if (!in_array($orderby, ['best', 'newest', 'oldest'], true)) {
        $orderby = 'best';
    }

    $page = isset($args['page']) ? (int) $args['page'] : 1;
    $page = max(1, $page);

    $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 0;
    if ($per_page <= 0) {
        $per_page = 15;
    }

    $all_comments = get_comments([
        'post_id' => $post_id,
        'status'  => 'approve',
        'orderby' => 'comment_date_gmt',
        'order'   => 'ASC',
        'number'  => 0,
    ]);

    $comments_map = [];
    $children_map = [];
    $top_level    = [];
    $last_modified = 0;

    foreach ($all_comments as $comment) {
        if (!$comment instanceof WP_Comment) {
            continue;
        }

        $comments_map[$comment->comment_ID] = $comment;
        $parent_id                           = (int) $comment->comment_parent;

        $comment_timestamp = strtotime($comment->comment_date_gmt . ' GMT');
        if ($comment_timestamp > $last_modified) {
            $last_modified = $comment_timestamp;
        }

        if (!isset($children_map[$parent_id])) {
            $children_map[$parent_id] = [];
        }

        $children_map[$parent_id][] = $comment;

        if (0 === $parent_id) {
            $top_level[] = $comment;
        }
    }

    foreach ($children_map as $parent_key => &$children) {
        if (!is_array($children) || empty($children)) {
            continue;
        }

        usort(
            $children,
            static function ($a, $b) {
                $a_count = gta6mods_get_cached_comment_like_count($a);
                $b_count = gta6mods_get_cached_comment_like_count($b);

                if ($a_count === $b_count) {
                    return strcmp($b->comment_date_gmt, $a->comment_date_gmt);
                }

                return $b_count <=> $a_count;
            }
        );
    }
    unset($children);

    switch ($orderby) {
        case 'newest':
            usort(
                $top_level,
                static function ($a, $b) {
                    return strcmp($b->comment_date_gmt, $a->comment_date_gmt);
                }
            );
            break;
        case 'oldest':
            usort(
                $top_level,
                static function ($a, $b) {
                    return strcmp($a->comment_date_gmt, $b->comment_date_gmt);
                }
            );
            break;
        case 'best':
        default:
            usort(
                $top_level,
                static function ($a, $b) {
                    $a_likes = get_comment_meta($a->comment_ID, '_gta6_comment_thread_like_count', true);
                    if ($a_likes === '') {
                        gta6_mods_update_comment_thread_likes($a->comment_ID);
                        $a_likes = get_comment_meta($a->comment_ID, '_gta6_comment_thread_like_count', true);
                    }

                    $b_likes = get_comment_meta($b->comment_ID, '_gta6_comment_thread_like_count', true);
                    if ($b_likes === '') {
                        gta6_mods_update_comment_thread_likes($b->comment_ID);
                        $b_likes = get_comment_meta($b->comment_ID, '_gta6_comment_thread_like_count', true);
                    }

                    $a_total = $a_likes ? (int) $a_likes : 0;
                    $b_total = $b_likes ? (int) $b_likes : 0;

                    if ($a_total === $b_total) {
                        return strcmp($b->comment_date_gmt, $a->comment_date_gmt);
                    }

                    return $b_total <=> $a_total;
                }
            );
            break;
    }

    $pinned_comment_id = gta6mods_get_pinned_comment_id($post_id);
    if ($pinned_comment_id > 0 && function_exists('gta6mods_prioritize_pinned_comment')) {
        $top_level = gta6mods_prioritize_pinned_comment($top_level, $pinned_comment_id);
    } else {
        $pinned_comment_id = 0;
    }

    $total_top_level = count($top_level);
    $total_pages     = $total_top_level > 0 ? (int) ceil($total_top_level / $per_page) : 1;
    $page            = min($page, $total_pages);
    $offset          = ($page - 1) * $per_page;
    $page_slice      = array_slice($top_level, $offset, $per_page);

    $ordered_comments = [];
    foreach ($page_slice as $top_level_comment) {
        $ordered_comments[] = $top_level_comment;
        $ordered_comments   = array_merge($ordered_comments, gta6mods_flatten_comment_children($top_level_comment->comment_ID, $children_map));
    }

    $html = gta6mods_render_comments_markup($post_id, $orderby, $page, $total_pages, $per_page, $ordered_comments, $pinned_comment_id);

    return [
        'html'        => $html,
        'count'       => get_comments_number($post_id),
        'page'        => $page,
        'per_page'    => $per_page,
        'total_pages' => $total_pages,
        'orderby'     => $orderby,
        'last_modified' => $last_modified,
        'pinned_comment_id' => $pinned_comment_id,
        'has_more'    => $page < $total_pages,
    ];
}

/**
 * Renders the complete comment template for the REST response.
 *
 * @param int          $post_id     Post ID.
 * @param string       $orderby     Sort order.
 * @param int          $page        Current page.
 * @param int          $total_pages Total pages available.
 * @param int          $per_page    Number of top-level comments per page.
 * @param WP_Comment[] $comments    Comments to render.
 *
 * @return string
 */
function gta6mods_render_comments_markup($post_id, $orderby, $page, $total_pages, $per_page, $comments, $pinned_comment_id = 0) {
    $post = get_post($post_id);

    if (!$post instanceof WP_Post) {
        return '';
    }

    if (function_exists('gta6mods_prime_mod_stats')) {
        gta6mods_prime_mod_stats([$post_id]);
    }

    $previous_post = $GLOBALS['post'] ?? null;
    setup_postdata($post);

    $comment_count         = get_comments_number($post_id);
    $comments_available    = !empty($comments);
    $current_order         = in_array($orderby, ['best', 'newest', 'oldest'], true) ? $orderby : 'best';
    $comment_label         = sprintf(_n('%s Comment', '%s Comments', $comment_count, 'gta6-mods'), number_format_i18n($comment_count));
    $pagination_aria_label = esc_attr__('Comments pagination', 'gta6-mods');
    $form_markup           = gta6mods_get_comment_form_markup($post_id);
    $has_more_pages        = $page < $total_pages;

    ob_start();
    ?>
    <div id="gta6-comments">
        <div id="kommentek" class="space-y-6">
            <div class="flex flex-row items-center justify-between gap-4">
                <h3 class="font-bold text-lg text-gray-900"
                    data-comment-count-label
                    data-template-singular="<?php echo esc_attr__('%s Comment', 'gta6-mods'); ?>"
                    data-template-plural="<?php echo esc_attr__('%s Comments', 'gta6-mods'); ?>"
                ><?php echo esc_html($comment_label); ?></h3>
                <div class="flex items-center gap-x-2">
                    <label for="gta6-comment-sort" class="sr-only"><?php esc_html_e('Sort comments', 'gta6-mods'); ?></label>
                    <select id="gta6-comment-sort"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-pink-300 focus:ring-2 focus:ring-pink-500 cursor-pointer"
                        aria-label="<?php esc_attr_e('Sort comments', 'gta6-mods'); ?>">
                        <option value="best" <?php selected('best', $current_order); ?>><?php esc_html_e('Best', 'gta6-mods'); ?></option>
                        <option value="newest" <?php selected('newest', $current_order); ?>><?php esc_html_e('Newest', 'gta6-mods'); ?></option>
                        <option value="oldest" <?php selected('oldest', $current_order); ?>><?php esc_html_e('Oldest', 'gta6-mods'); ?></option>
                    </select>
                </div>
            </div>

            <div
                id="gta6-comment-feedback"
                class="fixed top-4 right-4 z-50 flex flex-col items-end gap-3 pointer-events-none"
                aria-live="polite"
                aria-atomic="true"
            ></div>

            <?php if ('' !== $form_markup) : ?>
                <?php echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>

            <div id="gta6-comment-list" class="space-y-4">
                <?php
                if ($comments_available) {
                    $max_depth = gta6mods_get_comment_max_depth($post_id);
                    $walker = class_exists('GTA6_Mods_Comment_Walker')
                        ? new GTA6_Mods_Comment_Walker([
                            'pinned_comment_id' => (int) $pinned_comment_id,
                            'max_depth'         => $max_depth,
                        ])
                        : null;

                    wp_list_comments([
                        'style'       => 'div',
                        'short_ping'  => false,
                        'avatar_size' => 40,
                        'walker'      => $walker ?: new GTA6_Mods_Comment_Walker(),
                        'max_depth'   => $max_depth,
                        'echo'        => true,
                    ], $comments);
                }
                ?>
            </div>

            <div
                id="gta6-comment-pagination"
                class="mt-6 flex items-center justify-center"
                data-current-page="<?php echo esc_attr((string) $page); ?>"
                data-total-pages="<?php echo esc_attr((string) $total_pages); ?>"
                data-per-page="<?php echo esc_attr((string) $per_page); ?>"
                data-orderby="<?php echo esc_attr($current_order); ?>"
                aria-label="<?php echo esc_attr($pagination_aria_label); ?>"
            >
                <?php if ($has_more_pages) : ?>
                    <button
                        type="button"
                        class="gta6-load-more-comments inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-pink-500 rounded-lg shadow-sm transition hover:bg-pink-600 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2"
                        data-action="load-more-comments"
                        data-loading="0"
                    >
                        <span data-default-label><?php esc_html_e('Load more comments', 'gta6-mods'); ?></span>
                        <span data-loading-label class="hidden"><?php esc_html_e('Loading…', 'gta6-mods'); ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <p id="gta6-no-comments" class="text-sm text-gray-500<?php echo $comments_available ? ' hidden' : ''; ?>"><?php esc_html_e('No comments yet. Be the first to share your thoughts!', 'gta6-mods'); ?></p>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    if ($previous_post instanceof WP_Post) {
        setup_postdata($previous_post);
        $GLOBALS['post'] = $previous_post;
    } else {
        wp_reset_postdata();
    }

    return $html;
}

/**
 * Retrieves comments for a post.
 *
 * @param WP_REST_Request $request The REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_post_comments(WP_REST_Request $request) {
    $post_id = (int) $request['post_id'];

    $payload = gta6mods_build_comments_payload($post_id, [
        'orderby'  => $request->get_param('orderby') ?: 'best',
        'page'     => $request->get_param('page'),
        'per_page' => $request->get_param('per_page'),
    ]);

    if (is_wp_error($payload)) {
        return $payload;
    }

    $last_modified = isset($payload['last_modified']) ? (int) $payload['last_modified'] : 0;

    if ($last_modified <= 0) {
        $post_modified = get_post_modified_time('U', true, $post_id);
        if ($post_modified) {
            $last_modified = (int) $post_modified;
        }
    }

    $orderby    = isset($payload['orderby']) ? (string) $payload['orderby'] : 'best';
    $page       = isset($payload['page']) ? (int) $payload['page'] : 1;
    $per_page   = isset($payload['per_page']) ? (int) $payload['per_page'] : 0;
    $etag_value = sprintf('comments-%d-%s-%d-%d-%d', $post_id, $orderby, $page, $per_page, $last_modified);

    return gta6mods_rest_prepare_response(
        $payload,
        $request,
        [
            'public'        => !is_user_logged_in(),
            'last_modified' => $last_modified,
            'etag'          => $etag_value,
        ]
    );
}

/**
 * Handles comment submissions via REST.
 *
 * @param WP_REST_Request $request The REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_submit_post_comment(WP_REST_Request $request) {
    if (!gta6mods_rest_request_has_valid_comment_nonce($request)) {
        return new WP_Error(
            'rest_forbidden',
            __('Security verification failed. Please refresh the page and try again.', 'gta6-mods'),
            ['status' => 403]
        );
    }

    $post_id = (int) $request['post_id'];
    $post    = get_post($post_id);

    $allowed_types = gta6mods_get_commentable_post_types();

    if (!$post instanceof WP_Post || !in_array($post->post_type, $allowed_types, true)) {
        return new WP_Error('invalid_post', __('Comments are not available for this content.', 'gta6-mods'), ['status' => 400]);
    }

    if (!comments_open($post_id)) {
        return new WP_Error('comments_closed', __('Comments are closed for this post.', 'gta6-mods'), ['status' => 403]);
    }

    $raw_comment_content = $request->get_param('comment');
    $comment_content     = gta6mods_rest_sanitize_comment_content($raw_comment_content);

    if ('' === $comment_content || '' === trim(wp_strip_all_tags($comment_content))) {
        return new WP_Error('empty_comment', __('Please enter a comment before submitting.', 'gta6-mods'), ['status' => 400]);
    }

    $comment_parent = max(0, (int) $request->get_param('comment_parent'));
    $max_depth      = gta6mods_get_comment_max_depth($post_id);

    if ($comment_parent > 0) {
        $parent_comment = get_comment($comment_parent);

        if (!$parent_comment instanceof WP_Comment || (int) $parent_comment->comment_post_ID !== $post_id) {
            return new WP_Error('invalid_parent', __('The comment you are replying to could not be found.', 'gta6-mods'), ['status' => 400]);
        }

        $parent_depth = gta6mods_calculate_comment_depth($parent_comment);
        if ($parent_depth >= $max_depth) {
            return new WP_Error('comment_depth_exceeded', __('Replies are limited to two levels in this thread.', 'gta6-mods'), ['status' => 400]);
        }
    }

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $author       = $current_user->display_name ?: $current_user->user_login;
        $email        = $current_user->user_email;
        $url          = $current_user->user_url;
    } else {
        $author = sanitize_text_field((string) $request->get_param('author'));
        $email  = sanitize_email((string) $request->get_param('email'));
        $url    = esc_url_raw((string) $request->get_param('url'));

        if ('' === $author || '' === $email) {
            return new WP_Error('missing_fields', __('Name and email are required to post a comment.', 'gta6-mods'), ['status' => 400]);
        }

        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Please provide a valid email address.', 'gta6-mods'), ['status' => 400]);
        }
    }

    $cookies_consent = $request->get_param('wp-comment-cookies-consent') ? 'yes' : '';

    $files            = $request->get_file_params();
    $image_file       = isset($files['comment_image_file']) ? $files['comment_image_file'] : null;
    $attachment_id    = 0;
    $handled_upload   = false;
    if (is_array($image_file) && !empty($image_file['name'])) {
        $handled_upload = true;
        $attachment_id  = gta6_mods_handle_comment_image_upload($post_id, $image_file);
        if (is_wp_error($attachment_id)) {
            return new WP_Error('comment_image_upload', $attachment_id->get_error_message(), ['status' => 400]);
        }
    }

    $gif_url_raw = (string) $request->get_param('comment_gif_url');
    $gif_url     = $gif_url_raw ? esc_url_raw($gif_url_raw) : '';

    $mentions_raw = (string) $request->get_param('comment_mentioned_users');
    $mention_ids  = [];
    if ('' !== $mentions_raw) {
        $pieces = array_map('trim', explode(',', $mentions_raw));
        foreach ($pieces as $piece) {
            $user_id = absint($piece);
            if ($user_id > 0) {
                $mention_ids[] = $user_id;
            }
        }
    }
    $mention_ids = array_values(array_unique($mention_ids));

    $submission = [
        'comment_post_ID'            => $post_id,
        'comment_parent'             => $comment_parent,
        'comment'                    => $comment_content,
        'comment_type'               => '',
        'author'                     => isset($author) ? $author : '',
        'email'                      => isset($email) ? $email : '',
        'url'                        => isset($url) ? $url : '',
        'wp-comment-cookies-consent' => $cookies_consent,
    ];

    if (is_user_logged_in()) {
        $submission['user_ID'] = get_current_user_id();
    }

    $original_post  = $_POST;
    $original_files = $_FILES;
    $_POST          = [];
    $_POST['comment_image_id']       = $attachment_id > 0 ? (string) $attachment_id : '';
    $_POST['comment_gif_url']        = $gif_url;
    $_POST['comment_mentioned_users'] = implode(',', $mention_ids);
    $_POST['gta6_comment_meta_nonce'] = $request->get_param('gta6_comment_meta_nonce');

    if ($handled_upload) {
        $_FILES['comment_image_file'] = $image_file;
    }

    $comment = wp_handle_comment_submission($submission);

    $_POST = $original_post;
    $_FILES = $original_files;

    if (is_wp_error($comment)) {
        if ($attachment_id > 0) {
            wp_delete_attachment($attachment_id, true);
        }

        $error_codes = $comment->get_error_codes();
        if (in_array('comment_duplicate', $error_codes, true)) {
            $message = __('We detected a duplicate comment, it looks like you\'ve already said this once!', 'gta6-mods');

            return new WP_Error('comment_duplicate', $message, ['status' => 409]);
        }

        return new WP_Error('comment_error', $comment->get_error_message(), ['status' => 400]);
    }

    if (!$comment instanceof WP_Comment) {
        if ($attachment_id > 0) {
            wp_delete_attachment($attachment_id, true);
        }

        return new WP_Error('comment_error', __('Unable to save your comment. Please try again.', 'gta6-mods'), ['status' => 500]);
    }

    $comment_id     = (int) $comment->comment_ID;
    $status         = (string) $comment->comment_approved;
    $approved_count = get_comments_number($post_id);
    $display_count  = ('1' === $status) ? $approved_count : ($approved_count + 1);

    $html = wp_list_comments([
        'style'       => 'div',
        'short_ping'  => false,
        'avatar_size' => 40,
        'walker'      => new GTA6_Mods_Comment_Walker(['max_depth' => $max_depth]),
        'max_depth'   => $max_depth,
        'echo'        => false,
    ], [$comment]);

    $message = ('0' === $status)
        ? esc_html__('Your comment is awaiting moderation.', 'gta6-mods')
        : esc_html__('Your comment has been posted.', 'gta6-mods');

    return rest_ensure_response([
        'comment_id' => $comment_id,
        'parent_id'  => $comment_parent,
        'status'     => $status,
        'html'       => $html,
        'counts'     => [
            'approved' => (int) $approved_count,
            'display'  => (int) $display_count,
        ],
        'message'    => $message,
    ]);
}

/**
 * Searches for users to mention in comments.
 *
 * @param WP_REST_Request $request The REST request.
 *
 * @return WP_REST_Response
 */
function gta6mods_rest_search_comment_mentions(WP_REST_Request $request) {
    $search = $request->get_param('search');

    $query_args = [
        'number'  => 5,
        'orderby' => 'display_name',
        'order'   => 'ASC',
    ];

    if (!empty($search)) {
        $query_args['search']         = '*' . esc_attr($search) . '*';
        $query_args['search_columns'] = ['user_login', 'user_nicename', 'display_name'];
    }

    $user_query = new WP_User_Query($query_args);

    $users = [];
    foreach ($user_query->get_results() as $user) {
        if (!$user instanceof WP_User) {
            continue;
        }

        $deletion_data = function_exists('gta6_mods_get_account_deletion_data') ? gta6_mods_get_account_deletion_data($user->ID) : null;
        if (is_array($deletion_data) && isset($deletion_data['status']) && 'deleted' === $deletion_data['status']) {
            continue;
        }

        $users[] = [
            'id'       => (int) $user->ID,
            'username' => $user->user_login,
            'name'     => $user->display_name,
            'avatar'   => get_avatar_url($user->ID, ['size' => 64]),
        ];
    }

    return rest_ensure_response($users);
}

/**
 * Toggles comment likes via REST.
 *
 * @param WP_REST_Request $request The REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_toggle_comment_like(WP_REST_Request $request) {
    $comment_id = (int) $request['id'];
    $comment    = get_comment($comment_id);

    if (!$comment instanceof WP_Comment) {
        return new WP_Error('invalid_comment', __('Invalid comment.', 'gta6-mods'), ['status' => 400]);
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return new WP_Error('not_logged_in', __('Authentication required.', 'gta6-mods'), ['status' => 401]);
    }

    $likes = get_comment_meta($comment_id, 'gta6_comment_likes', true);
    $likes = is_array($likes) ? array_values(array_unique(array_filter(array_map('absint', $likes)))) : [];

    $liked = in_array($user_id, $likes, true);

    if ($liked) {
        $likes = array_values(array_diff($likes, [$user_id]));
        $liked = false;
    } else {
        $likes[] = $user_id;
        $liked   = true;
    }

    update_comment_meta($comment_id, 'gta6_comment_likes', $likes);
    gta6_mods_update_comment_thread_likes($comment_id);

    return rest_ensure_response([
        'count' => count($likes),
        'liked' => $liked,
    ]);
}

/**
 * Records a comment report via the REST API.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_report_comment(WP_REST_Request $request) {
    $comment_id = (int) $request['id'];
    $reason     = sanitize_key($request->get_param('reason'));
    $details    = $request->get_param('details');

    $result = gta6mods_submit_comment_report(
        $comment_id,
        get_current_user_id(),
        $reason,
        is_string($details) ? $details : ''
    );

    if (is_wp_error($result)) {
        return $result;
    }

    $response = [
        'commentId' => isset($result['commentId']) ? (int) $result['commentId'] : $comment_id,
        'count'     => isset($result['count']) ? (int) $result['count'] : 0,
        'reason'    => isset($result['reason']) ? $result['reason'] : '',
        'message'   => __('Thank you for helping us keep the community safe.', 'gta6-mods'),
    ];

    return rest_ensure_response($response);
}

/**
 * Determines whether the current user may manage the pinned comment for a request.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return bool
 */
function gta6mods_rest_can_manage_comment_pin(WP_REST_Request $request) {
    if (!is_user_logged_in()) {
        return false;
    }

    $comment_id = (int) $request['id'];
    $comment    = get_comment($comment_id);

    if (!$comment instanceof WP_Comment) {
        return false;
    }

    return gta6mods_user_can_pin_comment($comment, get_current_user_id());
}

/**
 * Handles comment retraction via the REST API.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_retract_comment(WP_REST_Request $request) {
    $comment_id = (int) $request['id'];
    $comment    = get_comment($comment_id);

    if (!$comment instanceof WP_Comment) {
        return new WP_Error('invalid_comment', __('Invalid comment.', 'gta6-mods'), ['status' => 404]);
    }

    $user_id = get_current_user_id();

    if (!gta6mods_user_can_retract_comment($comment, $user_id)) {
        return new WP_Error('forbidden', __('You are not allowed to delete this comment.', 'gta6-mods'), ['status' => 403]);
    }

    $result = gta6mods_mark_comment_retracted($comment_id, $user_id);

    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response([
        'commentId'      => $comment_id,
        'retracted'      => true,
        'pinned_removed' => !empty($result['pinned_removed']),
    ]);
}

/**
 * Pins a comment for the associated post.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_pin_comment(WP_REST_Request $request) {
    $comment_id = (int) $request['id'];
    $comment    = get_comment($comment_id);

    if (!$comment instanceof WP_Comment) {
        return new WP_Error('invalid_comment', __('Invalid comment.', 'gta6-mods'), ['status' => 404]);
    }

    if (!gta6mods_comment_can_be_pinned($comment)) {
        return new WP_Error('cannot_pin', __('Only top-level comments that are visible can be pinned.', 'gta6-mods'), ['status' => 400]);
    }

    gta6mods_set_post_pinned_comment($comment->comment_post_ID, $comment_id);

    return rest_ensure_response([
        'commentId' => $comment_id,
        'pinned'    => true,
    ]);
}

/**
 * Removes the pinned comment from a post.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_unpin_comment(WP_REST_Request $request) {
    $comment_id = (int) $request['id'];
    $comment    = get_comment($comment_id);

    if (!$comment instanceof WP_Comment) {
        return new WP_Error('invalid_comment', __('Invalid comment.', 'gta6-mods'), ['status' => 404]);
    }

    gta6mods_set_post_pinned_comment($comment->comment_post_ID, 0);

    return rest_ensure_response([
        'commentId' => $comment_id,
        'pinned'    => false,
    ]);
}
