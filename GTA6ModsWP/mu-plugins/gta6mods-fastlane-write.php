<?php
/**
 * Plugin Name: GTA6Mods Fastlane Write Runtime
 * Description: Short-init írási műveleteket biztosít a bejelentkezett felhasználók számára, minimalizálva a WordPress
 *              bootstrap költségét.
 * Author: GTA6Mods
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/gta6mods-fastlane-write-actions.php';

if ( ! function_exists( 'gta6mods_fastlane_write_endpoint_url' ) ) {
    /**
     * Visszaadja a write endpoint abszolút URL-jét.
     */
    function gta6mods_fastlane_write_endpoint_url(): string {
        static $endpoint = null;

        if ( null === $endpoint ) {
            $relative = 'mu-plugins/gta6mods-fastlane-write-endpoint.php';
            $endpoint = trailingslashit( content_url() ) . $relative;
        }

        return $endpoint;
    }
}

add_action( 'rest_api_init', function () {
    register_rest_route(
        'gta6mods/v1',
        '/fastlane/write-actions',
        [
            'methods'             => 'GET',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'callback'            => function () {
                $actions = gta6mods_fastlane_write_actions();
                $output  = [];

                foreach ( $actions as $name => $config ) {
                    $output[ $name ] = [
                        'description' => $config['description'] ?? '',
                        'capability'  => $config['capability'] ?? 'read',
                        'nonce'       => wp_create_nonce( $config['nonce_action'] ?? ( 'gta6mods_fastlane_write_' . $name ) ),
                    ];
                }

                return [
                    'endpoint' => gta6mods_fastlane_write_endpoint_url(),
                    'actions'  => $output,
                ];
            },
        ]
    );
} );
