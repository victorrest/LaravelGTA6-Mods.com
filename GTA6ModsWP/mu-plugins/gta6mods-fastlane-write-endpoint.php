<?php
/**
 * Short-init endpoint for Fastlane write requests.
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
require_once ABSPATH . WPINC . '/post.php';
require_once ABSPATH . WPINC . '/post-template.php';
require_once ABSPATH . WPINC . '/taxonomy.php';
require_once ABSPATH . WPINC . '/post-thumbnail-template.php';
require_once ABSPATH . WPINC . '/cron.php';
require_once ABSPATH . WPINC . '/http.php';

if ( ! function_exists( 'wp_unslash' ) ) {
    require_once ABSPATH . WPINC . '/kses.php';
}

global $wpdb, $table_prefix;

if ( ! isset( $wpdb ) || ! ( $wpdb instanceof wpdb ) ) {
    $wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
    if ( isset( $table_prefix ) ) {
        $wpdb->set_prefix( $table_prefix );
    }
}

require_once __DIR__ . '/gta6mods-fastlane-write-actions.php';

header( 'Content-Type: application/json; charset=utf-8' );
header( 'X-Fastlane-Write-Endpoint: 1' );

if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
    http_response_code( 405 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'invalid_method',
        'message' => 'Csak POST kérés engedélyezett.',
    ] );
    exit;
}

$raw_body   = file_get_contents( 'php://input' );
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$payload    = [];

if ( $raw_body && false !== stripos( $content_type, 'application/json' ) ) {
    $decoded = json_decode( $raw_body, true );
    if ( is_array( $decoded ) ) {
        $payload = $decoded;
    }
} elseif ( ! empty( $_POST ) ) {
    $payload = wp_unslash( $_POST );
}

$action = $payload['action'] ?? ($_GET['action'] ?? '');
$action = sanitize_key( $action );

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

$actions = gta6mods_fastlane_write_actions();

if ( ! isset( $actions[ $action ] ) ) {
    http_response_code( 404 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'unknown_action',
        'message' => 'Ismeretlen Fastlane write action.',
    ] );
    exit;
}

$action_config = $actions[ $action ];
$bootstrap    = $action_config['bootstrap'] ?? null;

if ( $bootstrap && is_callable( $bootstrap ) ) {
    call_user_func( $bootstrap );
}
$capability    = $action_config['capability'] ?? 'read';
$nonce_action  = $action_config['nonce_action'] ?? ( 'gta6mods_fastlane_write_' . $action );

$nonce = $payload['nonce'] ?? '';
if ( empty( $nonce ) && isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
    $nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
}

if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
    http_response_code( 403 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'invalid_nonce',
        'message' => 'Érvénytelen vagy hiányzó biztonsági token.',
    ] );
    exit;
}

if ( ! user_can( $user_id, $capability ) ) {
    http_response_code( 403 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'insufficient_permissions',
        'message' => 'A felhasználó nem rendelkezik a szükséges jogosultsággal.',
    ] );
    exit;
}

$callback = $action_config['callback'] ?? null;

if ( ! is_callable( $callback ) ) {
    http_response_code( 500 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'invalid_callback',
        'message' => 'A Fastlane write action hibásan van konfigurálva.',
    ] );
    exit;
}

try {
    $result = call_user_func( $callback, $payload );
} catch ( Throwable $throwable ) {
    http_response_code( 500 );
    echo wp_json_encode( [
        'success' => false,
        'error'   => 'exception',
        'message' => 'A művelet végrehajtása közben hiba történt.',
        'details' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? $throwable->getMessage() : null,
    ] );
    exit;
}

if ( is_wp_error( $result ) ) {
    $error_data = $result->get_error_data();
    $status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;

    http_response_code( $status );
    echo wp_json_encode( [
        'success' => false,
        'error'   => $result->get_error_code(),
        'message' => $result->get_error_message(),
    ] );
    exit;
}

$purge_keys = $action_config['purge'] ?? [];
if ( ! empty( $purge_keys ) ) {
    gta6mods_fastlane_purge_cache( $user_id, (array) $purge_keys );
}

http_response_code( 200 );
echo wp_json_encode( [
    'success' => true,
    'action'  => $action,
    'data'    => $result,
] );

exit;
