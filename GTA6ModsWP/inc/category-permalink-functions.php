<?php
/**
 * Category permalink helpers for base-less URLs.
 *
 * Provides rewrite rules and link normalisation so category archives can live
 * directly under the site root without the default `/category/` prefix. The
 * heavy lifting happens only when rewrite rules are regenerated, keeping
 * runtime requests fast for anonymous visitors under high load.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns the configured category base segment.
 *
 * @return string
 */
function gta6mods_get_category_base_segment() {
    static $segment = null;

    if (null !== $segment) {
        return $segment;
    }

    $base = (string) get_option('category_base');
    if ('' === trim($base)) {
        $base = 'category';
    }

    $segment = trim($base, '/');

    return $segment;
}

/**
 * Builds the hierarchical slug path for a category.
 *
 * @param WP_Term $category Category term.
 * @return string
 */
function gta6mods_get_category_path(WP_Term $category) {
    $ancestors = get_ancestors($category->term_id, 'category');
    $segments  = [];

    if (!empty($ancestors)) {
        $ancestors = array_reverse(array_map('absint', $ancestors));

        foreach ($ancestors as $ancestor_id) {
            $ancestor = get_term($ancestor_id, 'category');

            if ($ancestor instanceof WP_Term && !is_wp_error($ancestor)) {
                $segments[] = $ancestor->slug;
            }
        }
    }

    $segments[] = $category->slug;

    return implode('/', array_filter($segments));
}

/**
 * Generates base-less rewrite rules for every category.
 *
 * @param array<string, string> $existing_rules Existing rewrite rules.
 * @return array<string, string>
 */
function gta6mods_category_rewrite_rules($existing_rules) {
    $categories = get_categories(
        [
            'hide_empty' => false,
            'taxonomy'   => 'category',
        ]
    );

    if (empty($categories)) {
        return $existing_rules;
    }

    $rules = [];

    foreach ($categories as $category) {
        if (!$category instanceof WP_Term) {
            continue;
        }

        $category_path = gta6mods_get_category_path($category);
        if ('' === $category_path) {
            continue;
        }

        $escaped_path = preg_quote($category_path, '/');

        $rules[$escaped_path . '/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=' . $category_path . '&feed=$matches[1]';
        $rules[$escaped_path . '/page/?([0-9]{1,})/?$']                = 'index.php?category_name=' . $category_path . '&paged=$matches[1]';
        $rules[$escaped_path . '/?$']                                  = 'index.php?category_name=' . $category_path;

        if (function_exists('gta6mods_get_category_filter_rewrite_rules_for_path')) {
            $filter_rules = gta6mods_get_category_filter_rewrite_rules_for_path($category_path);

            foreach ($filter_rules as $pattern => $query) {
                $rules[$pattern] = $query;
            }
        }
    }

    // Ensure the new rules have priority over the default /category/ ones.
    return $rules + $existing_rules;
}
add_filter('category_rewrite_rules', 'gta6mods_category_rewrite_rules');

/**
 * Normalises generated category permalinks to strip the legacy base segment.
 *
 * @param string  $termlink Generated permalink.
 * @param WP_Term $term     Term object.
 * @param string  $taxonomy Taxonomy slug.
 * @return string
 */
function gta6mods_remove_category_base_from_term_link($termlink, $term, $taxonomy) {
    if ('category' !== $taxonomy || !$term instanceof WP_Term) {
        return $termlink;
    }

    $segment = gta6mods_get_category_base_segment();
    if ('' === $segment) {
        return $termlink;
    }

    $path = wp_parse_url($termlink, PHP_URL_PATH);
    if (!is_string($path)) {
        return $termlink;
    }

    $pattern = '#^/' . preg_quote($segment, '#') . '(/|$)#';
    $adjusted_path = preg_replace($pattern, '/', $path, 1);

    if (!is_string($adjusted_path) || '' === $adjusted_path) {
        return $termlink;
    }

    $adjusted_path = '/' . ltrim($adjusted_path, '/');

    $rebuilt = home_url($adjusted_path);

    $query = wp_parse_url($termlink, PHP_URL_QUERY);
    if (is_string($query) && '' !== $query) {
        $rebuilt = $rebuilt . '?' . $query;
    }

    $fragment = wp_parse_url($termlink, PHP_URL_FRAGMENT);
    if (is_string($fragment) && '' !== $fragment) {
        $rebuilt = $rebuilt . '#' . $fragment;
    }

    return $rebuilt;
}
add_filter('term_link', 'gta6mods_remove_category_base_from_term_link', 10, 3);

/**
 * Redirects legacy `/category/slug/` requests to the canonical URL.
 */
function gta6mods_redirect_category_base_requests() {
    if (!is_category()) {
        return;
    }

    $segment = gta6mods_get_category_base_segment();
    if ('' === $segment) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
    if ('' === $request_uri) {
        return;
    }

    $path = wp_parse_url(home_url($request_uri), PHP_URL_PATH);
    if (!is_string($path)) {
        return;
    }

    $normalised = trim($path, '/');
    if ('' === $normalised) {
        return;
    }

    if (0 !== strpos($normalised, $segment . '/')) {
        return;
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return;
    }

    $target = get_term_link($term);
    if (is_wp_error($target)) {
        return;
    }

    if (trailingslashit($target) === trailingslashit(home_url($path))) {
        return;
    }

    wp_safe_redirect($target, 301);
    exit;
}
add_action('template_redirect', 'gta6mods_redirect_category_base_requests', 1);

/**
 * Queues a rewrite flush whenever category slugs change.
 */
function gta6mods_queue_category_rewrite_flush() {
    update_option('gta6mods_category_rewrite_flush_needed', 1, false);
}
add_action('created_category', 'gta6mods_queue_category_rewrite_flush');
add_action('edited_category', 'gta6mods_queue_category_rewrite_flush');
add_action('delete_category', 'gta6mods_queue_category_rewrite_flush');
add_action('after_switch_theme', 'gta6mods_queue_category_rewrite_flush');

/**
 * Ensures a flush is queued on first bootstrap after deployment.
 */
function gta6mods_bootstrap_category_rewrite_flush_flag() {
    $bootstrapped = get_option('gta6mods_category_rewrite_bootstrapped');

    if ($bootstrapped) {
        return;
    }

    if (!get_option('gta6mods_category_rewrite_flush_needed')) {
        update_option('gta6mods_category_rewrite_flush_needed', 1, false);
    }
}
add_action('after_setup_theme', 'gta6mods_bootstrap_category_rewrite_flush_flag', 20);

/**
 * Flushes rewrite rules in the admin once queued.
 */
function gta6mods_maybe_flush_category_rewrites() {
    if (!is_admin()) {
        return;
    }

    if (!get_option('gta6mods_category_rewrite_flush_needed')) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('gta6mods_category_rewrite_bootstrapped', 1, false);
    delete_option('gta6mods_category_rewrite_flush_needed');
}
add_action('admin_init', 'gta6mods_maybe_flush_category_rewrites');
