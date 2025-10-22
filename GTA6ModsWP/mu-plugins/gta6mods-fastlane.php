<?php
/**
 * Plugin Name: GTA6Mods Fastlane Runtime
 * Description: Provides a short-init powered data layer for logged-in users so that expensive WordPress bootstrap paths can be bypassed for critical interactions.
 * Author: GTA6Mods
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/gta6mods-fastlane-actions.php';

if ( ! function_exists( 'gta6mods_fastlane_endpoint_url' ) ) {
    /**
     * Returns the absolute URL to the Fastlane endpoint script.
     */
    function gta6mods_fastlane_endpoint_url(): string {
        static $endpoint = null;

        if ( null === $endpoint ) {
            $relative = 'mu-plugins/gta6mods-fastlane-endpoint.php';
            $endpoint = trailingslashit( content_url() ) . $relative;
        }

        return $endpoint;
    }
}

add_action( 'init', function () {
    if ( function_exists( 'wp_cache_add_global_groups' ) ) {
        wp_cache_add_global_groups( 'gta6mods_fastlane' );
    }
}, 5 );

add_action( 'rest_api_init', function () {
    register_rest_route(
        'gta6mods/v1',
        '/fastlane/actions',
        [
            'methods'             => 'GET',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'callback'            => function () {
                $actions = gta6mods_fastlane_actions();
                $output  = [];

                foreach ( $actions as $name => $config ) {
                    $output[ $name ] = [
                        'description' => $config['description'] ?? '',
                        'ttl'         => (int) ( $config['ttl'] ?? 0 ),
                    ];
                }

                return [
                    'endpoint' => gta6mods_fastlane_endpoint_url(),
                    'actions'  => $output,
                ];
            },
        ]
    );
} );

