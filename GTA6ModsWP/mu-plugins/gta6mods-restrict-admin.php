<?php
/**
 * Plugin Name: GTA6 Mods – Admin Restrictions
 * Description: Hides the admin bar and blocks wp-admin access for non-administrator users.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hide the WordPress admin bar for non-administrator users.
 *
 * @param bool $show Whether the admin bar would be shown.
 *
 * @return bool
 */
function gta6mods_hide_admin_bar_for_non_admins($show) {
    if (!current_user_can('administrator')) {
        return false;
    }

    return $show;
}
add_filter('show_admin_bar', 'gta6mods_hide_admin_bar_for_non_admins', 100);

/**
 * Prevent non-administrator users from accessing the wp-admin area.
 */
function gta6mods_restrict_admin_area() {
    if (!is_admin()) {
        return;
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    if (current_user_can('administrator')) {
        return;
    }

    wp_safe_redirect(home_url('/'));
    exit;
}
add_action('init', 'gta6mods_restrict_admin_area', 0);
