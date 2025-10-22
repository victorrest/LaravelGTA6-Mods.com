<?php
/**
 * Short-init endpoint for Fastlane requests.
 */

if ( defined( 'ABSPATH' ) && realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
    return;
}

if ( ! defined( 'SHORTINIT' ) ) {
    define( 'SHORTINIT', true );
}

require dirname( __FILE__, 3 ) . '/wp-load.php';

require_once ABSPATH . WPINC . '/plugin.php';
require_once ABSPATH . WPINC . '/formatting.php';
require_once ABSPATH . WPINC . '/class-wp-error.php';
require_once ABSPATH . WPINC . '/user.php';
require_once ABSPATH . WPINC . '/meta.php';
require_once ABSPATH . WPINC . '/pluggable.php';
require_once ABSPATH . WPINC . '/capabilities.php';
if ( ! function_exists( 'wp_cache_get' ) ) {
    require_once ABSPATH . WPINC . '/cache.php';
}
require_once ABSPATH . WPINC . '/option.php';
require_once ABSPATH . WPINC . '/general-template.php';
require_once ABSPATH . WPINC . '/load.php';
require_once ABSPATH . WPINC . '/wp-db.php';

global $wpdb, $table_prefix;

if ( ! isset( $wpdb ) || ! ( $wpdb instanceof wpdb ) ) {
    $wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
    if ( isset( $table_prefix ) ) {
        $wpdb->set_prefix( $table_prefix );
    }
}

require_once __DIR__ . '/gta6mods-fastlane-actions.php';

header( 'Content-Type: application/json; charset=utf-8' );
header( 'X-Fastlane-Endpoint: 1' );

$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

if ( empty( $action ) ) {
    http_response_code( 400 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'missing_action',
        'message' => 'Hiányzó action paraméter.',
    ] );
    exit;
}

$user_id = wp_validate_auth_cookie( '', 'logged_in' );
if ( ! $user_id ) {
    http_response_code( 401 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'unauthorized',
        'message' => 'A művelet csak bejelentkezett felhasználóknak érhető el.',
    ] );
    exit;
}

wp_set_current_user( $user_id );

$actions = gta6mods_fastlane_actions();

if ( ! isset( $actions[ $action ] ) ) {
    http_response_code( 404 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'unknown_action',
        'message' => 'Ismeretlen Fastlane action.',
    ] );
    exit;
}

$action_config = $actions[ $action ];
$ttl           = isset( $action_config['ttl'] ) ? (int) $action_config['ttl'] : 0;
$cache_key     = sprintf( 'fastlane:%d:%s', $user_id, $action );
$cache_group   = 'gta6mods_fastlane';

if ( $ttl > 0 ) {
    $cached = wp_cache_get( $cache_key, $cache_group );
    if ( false !== $cached ) {
        header( 'Cache-Control: private, max-age=' . $ttl );
        header( 'X-Fastlane-Cache: hit' );
        echo wp_json_encode( [
            'success' => true,
            'data'    => $cached,
            'cached'  => true,
        ] );
        exit;
    }
}

header( 'X-Fastlane-Cache: miss' );

$callback = $action_config['callback'];

if ( ! is_callable( $callback ) ) {
    http_response_code( 500 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'invalid_callback',
        'message' => 'A Fastlane action hibásan van konfigurálva.',
    ] );
    exit;
}

$data = call_user_func( $callback );

if ( $ttl > 0 ) {
    wp_cache_set( $cache_key, $data, $cache_group, $ttl );
}

if ( $ttl > 0 ) {
    header( 'Cache-Control: private, max-age=' . $ttl );
} else {
    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
}

echo wp_json_encode( [
    'success' => true,
    'data'    => $data,
    'cached'  => false,
] );

exit;

