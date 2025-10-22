<?php
/**
 * REST API endpoint for user-specific state on single mod pages.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the REST route for retrieving user state for a mod.
 */
function gta6mods_register_single_mod_user_state_route() {
    register_rest_route(
        'gta6-mods/v1',
        '/mod/(?P<id>\d+)/user-state',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_single_mod_user_state',
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => static function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ],
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_single_mod_user_state_route');

/**
 * Returns the logged-in user's interaction state for a mod.
 *
 * @param WP_REST_Request $request REST request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_single_mod_user_state(WP_REST_Request $request) {
    $post_id = (int) $request['id'];
    $post    = get_post($post_id);

    if (!$post instanceof WP_Post || 'post' !== $post->post_type) {
        return new WP_Error('invalid_post', __('Invalid mod.', 'gta6-mods'), ['status' => 404]);
    }

    $state = [
        'liked'       => false,
        'bookmarked'  => false,
        'rating'      => 0,
        'is_logged_in'=> is_user_logged_in(),
    ];

    if ($state['is_logged_in']) {
        if (function_exists('gta6_mods_get_user_like_status')) {
            $state['liked'] = (bool) gta6_mods_get_user_like_status($post_id);
        }

        if (function_exists('gta6_mods_is_mod_bookmarked_by_user')) {
            $state['bookmarked'] = (bool) gta6_mods_is_mod_bookmarked_by_user($post_id);
        }

        if (function_exists('gta6_mods_get_user_rating')) {
            $state['rating'] = (int) gta6_mods_get_user_rating($post_id);
        }
    }

    return gta6mods_rest_prepare_response(
        $state,
        $request,
        [
            'public' => false,
            'vary'   => 'Cookie',
        ]
    );
}
