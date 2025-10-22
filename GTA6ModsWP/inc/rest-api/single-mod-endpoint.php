<?php
/**
 * REST API endpoint for aggregated single mod page data.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the aggregated single mod REST route.
 */
function gta6mods_register_single_mod_page_rest_route() {
    register_rest_route(
        'gta6-mods/v1',
        '/mod/(?P<id>\d+)/single-page-data',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_single_mod_page_data',
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => static function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
                'include_comments' => [
                    'description'       => __('Include the first page of comments.', 'gta6-mods'),
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
                'orderby' => [
                    'description'       => __('Comment sort order.', 'gta6-mods'),
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => static function ($param) {
                        if (null === $param || '' === $param) {
                            return true;
                        }
                        return in_array($param, ['best', 'newest', 'oldest'], true);
                    },
                ],
                'per_page' => [
                    'description'       => __('Number of top-level comments per page.', 'gta6-mods'),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_single_mod_page_rest_route');

/**
 * Handles the aggregated single mod REST request.
 *
 * @param WP_REST_Request $request REST request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_single_mod_page_data(WP_REST_Request $request) {
    $post_id = (int) $request['id'];
    $post    = get_post($post_id);

    if (!$post instanceof WP_Post || 'post' !== $post->post_type) {
        return new WP_Error('invalid_post', __('Invalid mod.', 'gta6-mods'), ['status' => 404]);
    }

    $include_param    = $request->get_param('include_comments');
    $include_comments = (null !== $include_param) ? rest_sanitize_boolean($include_param) : false;

    $orderby_param = $request->get_param('orderby');
    $orderby       = in_array($orderby_param, ['best', 'newest', 'oldest'], true) ? $orderby_param : 'best';

    $per_page = (int) $request->get_param('per_page');
    if ($per_page <= 0) {
        $default_per_page = (int) get_option('comments_per_page');
        $per_page         = $default_per_page > 0 ? $default_per_page : 10;
    }

    $rating_data = [
        'average' => 0.0,
        'count'   => 0,
    ];

    if (function_exists('gta6_mods_get_rating_data')) {
        $rating_data = array_merge($rating_data, (array) gta6_mods_get_rating_data($post_id));
    }

    $metrics = [
        'downloads' => function_exists('gta6_mods_get_download_count') ? (int) gta6_mods_get_download_count($post_id) : 0,
        'likes'     => function_exists('gta6_mods_get_like_count') ? (int) gta6_mods_get_like_count($post_id) : 0,
        'views'     => function_exists('gta6_mods_get_view_count') ? (int) gta6_mods_get_view_count($post_id) : 0,
    ];

    $comment_count = get_comments_number($post_id);

    $comments_data = [
        'count'       => (int) $comment_count,
        'html'        => null,
        'page'        => 1,
        'per_page'    => $per_page,
        'total_pages' => null,
        'orderby'     => $orderby,
    ];

    if ($include_comments) {
        $comments_payload = gta6mods_build_comments_payload($post_id, [
            'orderby'  => $orderby,
            'page'     => 1,
            'per_page' => $per_page,
        ]);

        if (is_wp_error($comments_payload)) {
            return $comments_payload;
        }

        $comments_data = array_merge($comments_data, $comments_payload);
    }

    $response_data = [
        'mod'          => [
            'id'      => $post_id,
            'rating'  => [
                'average' => isset($rating_data['average']) ? (float) $rating_data['average'] : 0.0,
                'count'   => isset($rating_data['count']) ? (int) $rating_data['count'] : 0,
            ],
            'metrics' => $metrics,
        ],
        'related_mods' => function_exists('gta6mods_get_related_mods_data') ? gta6mods_get_related_mods_data($post_id) : [],
        'comments'     => $comments_data,
    ];

    $last_modified = (int) get_post_modified_time('U', true, $post_id);
    if ($include_comments && isset($comments_data['last_modified'])) {
        $comments_last_modified = (int) $comments_data['last_modified'];
        if ($comments_last_modified > $last_modified) {
            $last_modified = $comments_last_modified;
        }
    }

    if ($last_modified <= 0) {
        $last_modified = time();
    }

    $etag_context = $include_comments ? sprintf('with-comments-%s-%d', $orderby, $per_page) : 'public';
    $etag_value   = sprintf('mod-%d-%d-%s', $post_id, $last_modified, $etag_context);

    return gta6mods_rest_prepare_response(
        $response_data,
        $request,
        [
            'public'      => !$include_comments,
            'max_age'     => 300,
            'stale_while_revalidate' => 600,
            'etag'        => $etag_value,
            'last_modified' => $last_modified,
        ]
    );
}
