<?php
/**
 * Shared action definitions for the Fastlane runtime.
 */

if ( ! function_exists( 'gta6mods_fastlane_actions' ) ) {
    /**
     * Returns the list of Fastlane actions.
     *
     * @return array<string, array<string, mixed>>
     */
    function gta6mods_fastlane_actions(): array {
        $actions = [
            'user-context'  => [
                'callback'    => 'gta6mods_fastlane_action_user_context',
                'ttl'         => 15,
                'description' => 'Alapvető felhasználói profiladatok és jogosultsági kivonat.',
            ],
            'user-metrics'  => [
                'callback'    => 'gta6mods_fastlane_action_user_metrics',
                'ttl'         => 30,
                'description' => 'Felhasználóhoz tartozó legfontosabb számlálók (modok, hozzászólások, kedvencek).',
            ],
            'user-settings' => [
                'callback'    => 'gta6mods_fastlane_action_user_settings',
                'ttl'         => 60,
                'description' => 'Gyakran használt felhasználói meta adatok gyorsítótárazott kivonata.',
            ],
        ];

        if ( function_exists( 'apply_filters' ) ) {
            /**
             * Szűrő a Fastlane műveletek bővítésére vagy módosítására.
             */
            $actions = apply_filters( 'gta6mods_fastlane_actions', $actions );
        }

        return $actions;
    }
}

if ( ! function_exists( 'gta6mods_fastlane_action_user_context' ) ) {
    /**
     * Visszaadja a bejelentkezett felhasználó alapinformációit.
     */
    function gta6mods_fastlane_action_user_context(): array {
        $user = wp_get_current_user();

        return [
            'id'          => (int) $user->ID,
            'displayName' => $user->display_name,
            'slug'        => $user->user_nicename,
            'roles'       => array_values( (array) $user->roles ),
            'emailHash'   => $user->user_email ? md5( strtolower( trim( $user->user_email ) ) ) : null,
            'lastLogin'   => get_user_meta( $user->ID, 'last_login', true ) ?: null,
        ];
    }
}

if ( ! function_exists( 'gta6mods_fastlane_action_user_metrics' ) ) {
    /**
     * Gyors számlálók lekérése közvetlen adatbázis lekérdezéssel.
     */
    function gta6mods_fastlane_action_user_metrics(): array {
        global $wpdb;

        $user_id = get_current_user_id();

        $mod_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s AND post_status IN ('publish','pending','draft')",
                $user_id,
                'gta6_mod'
            )
        );

        $comment_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE user_id = %d",
                $user_id
            )
        );

        $favorite_count = (int) get_user_meta( $user_id, 'gta6mods_favorites_count', true );

        return [
            'mods'       => $mod_count,
            'comments'   => $comment_count,
            'favorites'  => $favorite_count,
        ];
    }
}

if ( ! function_exists( 'gta6mods_fastlane_action_user_settings' ) ) {
    /**
     * Gyakran használt felhasználói meta adatok gyűjtése.
     */
    function gta6mods_fastlane_action_user_settings(): array {
        $user_id = get_current_user_id();

        $fields = [
            'language_preference',
            'timezone',
            'notification_digest',
            'beta_tester',
        ];

        $settings = [];

        foreach ( $fields as $field ) {
            $settings[ $field ] = get_user_meta( $user_id, $field, true );
        }

        return $settings;
    }
}

