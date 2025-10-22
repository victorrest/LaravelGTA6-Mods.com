<?php
/**
 * REST API endpoints for Mod Ratings.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the REST API routes for mod ratings.
 */
function gta6mods_register_ratings_rest_routes() {
    register_rest_route(
        'gta6-mods/v1',
        '/mod/(?P<id>\d+)/rate',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_handle_rating',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                    'required' => true,
                ],
                'rating' => [
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param) && (int) $param >= 1 && (int) $param <= 5;
                    },
                    'required' => true,
                    'type' => 'integer',
                ],
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_ratings_rest_routes');

/**
 * Handles a rating submission via the REST API.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
 */
function gta6mods_rest_handle_rating(WP_REST_Request $request) {
    $post_id = (int) $request['id'];
    $rating  = (int) $request['rating'];
    $user_id = get_current_user_id();

    if ($post_id <= 0 || get_post_type($post_id) !== 'post') {
        return new WP_Error('rest_invalid_post', __('Invalid post.', 'gta6-mods'), ['status' => 400]);
    }

    if ($rating < 1 || $rating > 5) {
        return new WP_Error('rest_invalid_rating', __('Rating must be between 1 and 5.', 'gta6-mods'), ['status' => 400]);
    }

    $ratings_key    = '_gta6mods_ratings';
    $ratings_meta   = get_post_meta($post_id, $ratings_key, true);
    $sanitized_meta = [];

    if (is_array($ratings_meta)) {
        foreach ($ratings_meta as $meta_user_id => $meta_rating) {
            $meta_user_id = (int) $meta_user_id;
            $meta_rating  = (int) $meta_rating;

            if ($meta_user_id > 0 && $meta_rating >= 1 && $meta_rating <= 5) {
                $sanitized_meta[$meta_user_id] = $meta_rating;
            }
        }
    }

    $sanitized_meta[$user_id] = $rating;

    $total_rating = array_sum($sanitized_meta);
    $count        = count($sanitized_meta);
    $average      = $count > 0 ? round($total_rating / $count, 1) : 0;

    update_post_meta($post_id, $ratings_key, $sanitized_meta);
    gta6mods_set_mod_rating_stats($post_id, $average, $count);

    // Clear caches
    wp_cache_delete($post_id, 'post_meta');
    delete_transient('gta6_front_page_data_v1');

    $response_data = [
        'average'     => $average,
        'count'       => $count,
        'user_rating' => $rating,
    ];

    return new WP_REST_Response($response_data, 200);
}
