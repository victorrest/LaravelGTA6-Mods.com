<?php
/**
 * REST API endpoints for likes and bookmarks using the lightweight namespace.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers REST API routes for mod interactions (likes, bookmarks).
 */
function gta6mods_register_interaction_rest_routes() {
    register_rest_route(
        'gta6mods/v1',
        '/mod/(?P<id>\d+)/like',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_toggle_mod_like',
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/mod/(?P<id>\d+)/bookmark',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_toggle_mod_bookmark',
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_interaction_rest_routes');
