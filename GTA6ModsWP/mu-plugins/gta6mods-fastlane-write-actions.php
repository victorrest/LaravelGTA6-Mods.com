<?php
/**
 * Fastlane write action definitions.
 */

if ( ! function_exists( 'gta6mods_fastlane_write_actions' ) ) {
    /**
     * Returns the list of Fastlane write actions.
     *
     * @return array<string, array<string, mixed>>
     */
    function gta6mods_fastlane_write_actions(): array {
        $actions = [
            'update-user-settings' => [
                'callback'    => 'gta6mods_fastlane_write_update_user_settings',
                'capability'  => 'read',
                'nonce_action'=> 'gta6mods_fastlane_write_update_user_settings',
                'description' => 'Felhasználói beállítások gyors frissítése optimalizált meta írással.',
                'purge'       => [ 'user-settings' ],
            ],
            'acknowledge-notifications' => [
                'callback'    => 'gta6mods_fastlane_write_acknowledge_notifications',
                'capability'  => 'read',
                'nonce_action'=> 'gta6mods_fastlane_write_acknowledge_notifications',
                'description' => 'Értesítések megtekintésének naplózása és cache érvénytelenítése.',
                'purge'       => [ 'user-context' ],
            ],
            'toggle-favorite-mod' => [
                'callback'    => 'gta6mods_fastlane_write_toggle_favorite_mod',
                'capability'  => 'read',
                'nonce_action'=> 'gta6mods_fastlane_write_toggle_favorite_mod',
                'description' => 'Mod kedvencek közé helyezése vagy eltávolítása minimális WordPress bootstrap mellett.',
                'purge'       => [ 'user-metrics' ],
            ],
            'submit-mod-upload' => [
                'callback'    => 'gta6mods_fastlane_write_submit_mod_upload',
                'capability'  => 'upload_files',
                'nonce_action'=> 'gta6mods_fastlane_write_submit_mod_upload',
                'description' => 'Új mod feltöltésének gyorsított végpontja nagy fájlok és képgalériák kezeléséhez.',
                'purge'       => [ 'user-metrics' ],
                'bootstrap'   => 'gta6mods_fastlane_write_bootstrap_mod_upload',
            ],
        ];

        if ( function_exists( 'apply_filters' ) ) {
            /**
             * Lehetővé teszi a Fastlane write műveletek bővítését.
             */
            $actions = apply_filters( 'gta6mods_fastlane_write_actions', $actions );
        }

        return $actions;
    }
}

if ( ! function_exists( 'gta6mods_fastlane_purge_cache' ) ) {
    /**
     * Segédfüggvény a Fastlane cache érvénytelenítéséhez.
     */
    function gta6mods_fastlane_purge_cache( int $user_id, array $action_keys ): void {
        if ( empty( $action_keys ) ) {
            return;
        }

        foreach ( $action_keys as $action ) {
            $cache_key = sprintf( 'fastlane:%d:%s', $user_id, $action );
            wp_cache_delete( $cache_key, 'gta6mods_fastlane' );
        }
    }
}

if ( ! function_exists( 'gta6mods_fastlane_write_bootstrap_mod_upload' ) ) {
    /**
     * Ensures every dependency required for accelerated mod uploads is loaded.
     */
    function gta6mods_fastlane_write_bootstrap_mod_upload(): void {
        if ( ! defined( 'DOING_AJAX' ) ) {
            define( 'DOING_AJAX', true );
        }

        if ( ! function_exists( 'get_stylesheet_directory' ) ) {
            require_once ABSPATH . WPINC . '/link-template.php';
            require_once ABSPATH . WPINC . '/theme.php';
        }

        $stylesheet_dir = function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : '';

        if ( ! $stylesheet_dir ) {
            $stylesheet = get_option( 'stylesheet' );
            if ( $stylesheet ) {
                $stylesheet_dir = trailingslashit( WP_CONTENT_DIR ) . 'themes/' . $stylesheet;
            }
        }

        if ( ! $stylesheet_dir || ! is_dir( $stylesheet_dir ) ) {
            return;
        }

        $include_files = [
            '/inc/cache-helpers.php',
            '/inc/editorjs-functions.php',
            '/inc/class-mod-versions.php',
            '/inc/mod-update-functions.php',
            '/inc/upload-functions.php',
        ];

        foreach ( $include_files as $relative ) {
            $path = $stylesheet_dir . $relative;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    }
}

if ( ! function_exists( 'gta6mods_fastlane_write_submit_mod_upload' ) ) {
    /**
     * Handles accelerated mod uploads through the Fastlane endpoint.
     */
    function gta6mods_fastlane_write_submit_mod_upload( array $payload ) {
        if ( ! function_exists( 'gta6mods_handle_mod_submission_request' ) ) {
            return new WP_Error( 'fastlane_mod_upload_unavailable', __( 'The mod upload handler is unavailable.', 'gta6-mods' ), [ 'status' => 500 ] );
        }

        $current_user = wp_get_current_user();
        if ( ! $current_user || ! $current_user->exists() ) {
            return new WP_Error( 'fastlane_mod_upload_no_user', __( 'You must be logged in to submit a mod.', 'gta6-mods' ), [ 'status' => 403 ] );
        }

        if ( ! current_user_can( 'upload_files' ) ) {
            return new WP_Error( 'fastlane_mod_upload_forbidden', __( 'You do not have permission to upload files.', 'gta6-mods' ), [ 'status' => 403 ] );
        }

        $request = $payload;
        foreach ( $request as $key => $value ) {
            if ( is_string( $value ) ) {
                $request[ $key ] = wp_unslash( $value );
            }
        }

        $files  = isset( $_FILES ) && is_array( $_FILES ) ? $_FILES : [];
        $result = gta6mods_handle_mod_submission_request( $request, $files, $current_user );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        /**
         * A mod upload changes aggregate metrics such as pending counts, so invalidate metrics caches.
         */
        gta6mods_fastlane_purge_cache( (int) $current_user->ID, [ 'user-metrics' ] );

        return $result;
    }
}

if ( ! function_exists( 'gta6mods_fastlane_write_update_user_settings' ) ) {
    /**
     * Beállítások frissítése kontrollált mezőkön.
     */
    function gta6mods_fastlane_write_update_user_settings( array $payload ): array {
        $user_id = get_current_user_id();

        $allowed_fields = [
            'language_preference' => 'sanitize_text_field',
            'timezone'            => 'sanitize_text_field',
            'notification_digest' => 'sanitize_text_field',
            'beta_tester'         => 'gta6mods_fastlane_sanitize_bool',
        ];

        $updated = [];
        $skipped = [];

        $settings = $payload['settings'] ?? [];

        if ( ! is_array( $settings ) ) {
            return [
                'updated' => $updated,
                'skipped' => array_keys( $allowed_fields ),
            ];
        }

        foreach ( $allowed_fields as $field => $sanitizer ) {
            if ( ! array_key_exists( $field, $settings ) ) {
                $skipped[] = $field;
                continue;
            }

            $value = $settings[ $field ];

            if ( is_callable( $sanitizer ) ) {
                $value = call_user_func( $sanitizer, $value );
            }

            update_user_meta( $user_id, $field, $value );
            $updated[] = $field;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}

if ( ! function_exists( 'gta6mods_fastlane_write_acknowledge_notifications' ) ) {
    /**
     * Értesítések olvasottságának beállítása.
     */
    function gta6mods_fastlane_write_acknowledge_notifications( array $payload ): array {
        $user_id = get_current_user_id();

        $timestamp = $payload['timestamp'] ?? time();
        $timestamp = (int) $timestamp;

        if ( $timestamp <= 0 ) {
            $timestamp = time();
        }

        update_user_meta( $user_id, 'gta6mods_notifications_last_seen', $timestamp );

        return [
            'acknowledgedAt' => $timestamp,
        ];
    }
}

if ( ! function_exists( 'gta6mods_fastlane_write_toggle_favorite_mod' ) ) {
    /**
     * Mod kedvencek közé helyezése vagy eltávolítása.
     */
    function gta6mods_fastlane_write_toggle_favorite_mod( array $payload ): array {
        $user_id = get_current_user_id();
        $mod_id  = isset( $payload['mod_id'] ) ? (int) $payload['mod_id'] : 0;

        if ( $mod_id <= 0 ) {
            return [
                'modId'    => $mod_id,
                'favorited'=> false,
                'count'    => (int) get_user_meta( $user_id, 'gta6mods_favorites_count', true ),
            ];
        }

        global $wpdb;
        $post_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s AND post_status IN ('publish','pending','draft')",
                $mod_id,
                'gta6_mod'
            )
        );

        if ( ! $post_exists ) {
            return [
                'modId'    => $mod_id,
                'favorited'=> false,
                'count'    => (int) get_user_meta( $user_id, 'gta6mods_favorites_count', true ),
                'error'    => 'Mod nem található.',
            ];
        }

        $favorites = get_user_meta( $user_id, 'gta6mods_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        $favorites = array_map( 'intval', $favorites );
        $favorites = array_unique( $favorites );

        $should_favorite = true;

        if ( isset( $payload['state'] ) ) {
            $should_favorite = (bool) gta6mods_fastlane_sanitize_bool( $payload['state'] );
        } elseif ( in_array( $mod_id, $favorites, true ) ) {
            $should_favorite = false;
        }

        if ( $should_favorite ) {
            $favorites[] = $mod_id;
        } else {
            $favorites = array_values( array_diff( $favorites, [ $mod_id ] ) );
        }

        update_user_meta( $user_id, 'gta6mods_favorites', $favorites );
        update_user_meta( $user_id, 'gta6mods_favorites_count', count( $favorites ) );

        return [
            'modId'     => $mod_id,
            'favorited' => $should_favorite,
            'count'     => count( $favorites ),
        ];
    }
}

if ( ! function_exists( 'gta6mods_fastlane_sanitize_bool' ) ) {
    /**
     * Boole érték sztringből vagy számokból.
     *
     * @param mixed $value Bemeneti érték.
     */
    function gta6mods_fastlane_sanitize_bool( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_string( $value ) ) {
            $value = strtolower( trim( $value ) );
            return in_array( $value, [ '1', 'true', 'yes', 'on' ], true );
        }

        return (bool) $value;
    }
}
