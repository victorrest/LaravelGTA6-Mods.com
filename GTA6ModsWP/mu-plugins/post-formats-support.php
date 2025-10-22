<?php
/**
 * Plugin Name: Post Formats Support
 * Description: Engedélyezi a bejegyzésformátumokat minden témához
 * Version: 1.0
 * Author: Custom
 */

// Biztonsági ellenőrzés
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Formats támogatás hozzáadása
 */
function enable_post_formats_support() {
    // Post formats támogatás hozzáadása
    add_theme_support('post-formats', array(
        'aside',     // Rövid jegyzet
        'gallery',   // Galéria
        'link',      // Link
        'image',     // Kép
        'quote',     // Idézet
        'status',    // Státusz
        'video',     // Videó
        'audio',     // Audió
        'chat'       // Beszélgetés
    ));
}

// Hook hozzáadása az 'after_setup_theme' akcióhoz
add_action('after_setup_theme', 'enable_post_formats_support');

/**
 * Opcionális: Custom post format nevek magyar nyelven
 */
function custom_post_format_strings($strings) {
    $strings['aside'] = 'Rövid jegyzet';
    $strings['gallery'] = 'Galéria';
    $strings['link'] = 'Link';
    $strings['image'] = 'Kép';
    $strings['quote'] = 'Idézet';
    $strings['status'] = 'Státusz';
    $strings['video'] = 'Videó';
    $strings['audio'] = 'Audió';
    $strings['chat'] = 'Beszélgetés';
    
    return $strings;
}

// Magyar fordítások (opcionális)
add_filter('post_format_strings', 'custom_post_format_strings');

/**
 * Biztosítjuk, hogy a metabox megjelenjen
 */
function ensure_post_format_metabox() {
    add_meta_box(
        'formatdiv',
        __('Format'),
        'post_format_meta_box',
        'post',
        'side',
        'core'
    );
}

add_action('add_meta_boxes', 'ensure_post_format_metabox');


?>