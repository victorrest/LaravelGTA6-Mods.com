<?php
/**
 * High-performance XML sitemap and search analytics system.
 *
 * Provides cached, multi-part sitemaps alongside intelligent search logging
 * and indexing controls tailored for extremely high traffic environments.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns the database table name for logged searches.
 *
 * @return string
 */
function gta6mods_get_search_log_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'gta_search_log';
}

/**
 * Registers rewrite rules for sitemap endpoints.
 *
 * @return void
 */
function gta6mods_register_sitemap_rewrite_rules() {
    add_rewrite_rule('^sitemap\\.xml$', 'index.php?gta_sitemap=index', 'top');
    add_rewrite_rule('^sitemap-([^/]+)\\.xml$', 'index.php?gta_sitemap=$matches[1]', 'top');
}
add_action('init', 'gta6mods_register_sitemap_rewrite_rules');

/**
 * Flushes rewrite rules on theme activation to register sitemap routes.
 *
 * @return void
 */
function gta6mods_flush_sitemap_rewrite() {
    gta6mods_register_sitemap_rewrite_rules();
    flush_rewrite_rules(false);
}
add_action('after_switch_theme', 'gta6mods_flush_sitemap_rewrite');

/**
 * Adds custom query var for sitemap routing.
 *
 * @param array<int, string> $vars Public query vars.
 *
 * @return array<int, string>
 */
function gta6mods_add_sitemap_query_var($vars) {
    $vars[] = 'gta_sitemap';

    return $vars;
}
add_filter('query_vars', 'gta6mods_add_sitemap_query_var');

/**
 * Prevents canonical redirects from forcing trailing slashes on sitemap URLs.
 *
 * @param string|false $redirect_url  The redirect destination URL.
 * @param string       $requested_url The requested URL.
 *
 * @return string|false
 */
function gta6mods_disable_sitemap_canonical($redirect_url, $requested_url) {
    unset($requested_url);

    if (get_query_var('gta_sitemap')) {
        return false;
    }

    return $redirect_url;
}
add_filter('redirect_canonical', 'gta6mods_disable_sitemap_canonical', 10, 2);

/**
 * Handles sitemap output on matched requests.
 *
 * @return void
 */
function gta6mods_handle_sitemap_request() {
    if (is_admin()) {
        return;
    }

    $sitemap = get_query_var('gta_sitemap');
    if (empty($sitemap)) {
        return;
    }

    $sitemap = is_string($sitemap) ? trim($sitemap) : '';
    if ('' === $sitemap) {
        return;
    }

    $xml = gta6mods_get_sitemap_xml($sitemap);

    if ('' === $xml) {
        status_header(404);
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<!-- Sitemap not found -->';
        exit;
    }

    status_header(200);
    header('Content-Type: application/xml; charset=utf-8');
    echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
add_action('template_redirect', 'gta6mods_handle_sitemap_request');

/**
 * Builds the cache key for a sitemap payload.
 *
 * @param string $type Sitemap identifier.
 *
 * @return string
 */
function gta6mods_get_sitemap_cache_key($type) {
    $sanitized = sanitize_key(str_replace(['/', '.'], '-', strtolower($type)));

    return 'gta6mods_sitemap_' . $sanitized;
}

/**
 * Returns the sitemap cache group name.
 *
 * @return string
 */
function gta6mods_get_sitemap_cache_group() {
    return 'gta6mods_sitemaps';
}

/**
 * Stores generated sitemap XML in the object cache and tracks the key.
 *
 * @param string $type Sitemap identifier.
 * @param string $xml  Sitemap payload.
 *
 * @return void
 */
function gta6mods_set_cached_sitemap($type, $xml) {
    $cache_group = gta6mods_get_sitemap_cache_group();
    $cache_key   = gta6mods_get_sitemap_cache_key($type);

    wp_cache_set($cache_key, $xml, $cache_group, HOUR_IN_SECONDS);

    $tracked_keys = wp_cache_get('gta6mods_sitemap_keys', $cache_group);
    if (!is_array($tracked_keys)) {
        $tracked_keys = [];
    }

    if (!in_array($cache_key, $tracked_keys, true)) {
        $tracked_keys[] = $cache_key;
        wp_cache_set('gta6mods_sitemap_keys', $tracked_keys, $cache_group, 0);
    }
}

/**
 * Retrieves cached sitemap XML.
 *
 * @param string $type Sitemap identifier.
 *
 * @return string|false
 */
function gta6mods_get_cached_sitemap_xml($type) {
    $cache_key = gta6mods_get_sitemap_cache_key($type);
    $cache_group = gta6mods_get_sitemap_cache_group();

    return wp_cache_get($cache_key, $cache_group);
}

/**
 * Clears cached sitemap payloads.
 *
 * @return void
 */
function gta6mods_clear_sitemap_cache($unused = null) {
    $cache_group  = gta6mods_get_sitemap_cache_group();
    $tracked_keys = wp_cache_get('gta6mods_sitemap_keys', $cache_group);

    if (is_array($tracked_keys)) {
        foreach ($tracked_keys as $cache_key) {
            if (!is_string($cache_key) || '' === $cache_key) {
                continue;
            }

            wp_cache_delete($cache_key, $cache_group);
        }
    }

    wp_cache_delete('gta6mods_sitemap_keys', $cache_group);
}
add_action('save_post', 'gta6mods_clear_sitemap_cache');
add_action('deleted_post', 'gta6mods_clear_sitemap_cache');
add_action('created_term', 'gta6mods_clear_sitemap_cache');
add_action('edited_term', 'gta6mods_clear_sitemap_cache');

/**
 * Generates sitemap XML, using cache when possible.
 *
 * @param string $type Sitemap identifier.
 *
 * @return string
 */
function gta6mods_get_sitemap_xml($type) {
    $cached = gta6mods_get_cached_sitemap_xml($type);
    if (false !== $cached && is_string($cached)) {
        return $cached;
    }

    $xml = '';
    switch ($type) {
        case 'index':
            $xml = gta6mods_generate_sitemap_index();
            break;
        case 'static':
            $xml = gta6mods_generate_static_sitemap();
            break;
        case 'categories':
            $xml = gta6mods_generate_categories_sitemap();
            break;
        case 'tags':
            $xml = gta6mods_generate_tags_sitemap();
            break;
        case 'filters':
            $xml = gta6mods_generate_filters_sitemap();
            break;
        case 'searches':
            $xml = gta6mods_generate_searches_sitemap();
            break;
        case 'images':
            $xml = gta6mods_generate_images_sitemap();
            break;
        case 'authors':
            $xml = gta6mods_generate_authors_sitemap();
            break;
        case 'videos':
            $xml = gta6mods_generate_video_sitemap();
            break;
        case 'forum-flairs':
            $xml = gta6mods_generate_forum_flairs_sitemap();
            break;
        default:
            if (0 === strpos($type, 'posts-')) {
                $xml = gta6mods_generate_posts_sitemap($type);
            } elseif (0 === strpos($type, 'forum-threads-')) {
                $page = (int) substr($type, strlen('forum-threads-'));
                $xml  = gta6mods_generate_forum_threads_sitemap($page);
            }
            break;
    }

    if (!is_string($xml) || '' === $xml) {
        return '';
    }

    gta6mods_set_cached_sitemap($type, $xml);

    return $xml;
}

/**
 * Returns allowed post types for mods.
 *
 * @return array<int, string>
 */
function gta6mods_get_sitemap_post_types() {
    $types = function_exists('gta6mods_get_mod_post_types') ? gta6mods_get_mod_post_types() : ['post'];
    if (empty($types) || !is_array($types)) {
        $types = ['post'];
    }

    return array_values(array_unique(array_map('sanitize_key', $types)));
}

/**
 * Formats a MySQL datetime into W3C format.
 *
 * @param string $datetime Datetime string.
 *
 * @return string
 */
function gta6mods_format_sitemap_date($datetime) {
    if (empty($datetime)) {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    $timestamp = strtotime($datetime);
    if (false === $timestamp) {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
}

/**
 * Provides the most recent modification date across all mod post types.
 *
 * @return string
 */
function gta6mods_get_latest_post_modified_gmt() {
    global $wpdb;

    $post_types = gta6mods_get_sitemap_post_types();
    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
    $sql = "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders})";
    $prepared = $wpdb->prepare($sql, $post_types);
    $latest = $wpdb->get_var($prepared);

    if (empty($latest)) {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    return gta6mods_format_sitemap_date($latest);
}

/**
 * Builds the sitemap index XML.
 *
 * @return string
 */
function gta6mods_generate_sitemap_index() {
    $entries = [];
    $entries[] = [
        'loc'     => esc_url_raw(home_url('/sitemap-static.xml')),
        'lastmod' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    $latest_post_modified = gta6mods_get_latest_post_modified_gmt();

    $entries[] = [
        'loc'     => esc_url_raw(home_url('/sitemap-categories.xml')),
        'lastmod' => $latest_post_modified,
    ];
    $entries[] = [
        'loc'     => esc_url_raw(home_url('/sitemap-tags.xml')),
        'lastmod' => $latest_post_modified,
    ];
    $entries[] = [
        'loc'     => esc_url_raw(home_url('/sitemap-filters.xml')),
        'lastmod' => $latest_post_modified,
    ];
    $entries[] = [
        'loc'     => esc_url_raw(home_url('/sitemap-searches.xml')),
        'lastmod' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
    $entries[] = [
        'loc'     => esc_url_raw(home_url('/sitemap-images.xml')),
        'lastmod' => $latest_post_modified,
    ];
    $entries[] = [
        'loc'     => esc_url_raw(home_url('/sitemap-authors.xml')),
        'lastmod' => $latest_post_modified,
    ];
    $entries[] = [
        'loc'     => esc_url_raw(home_url('/sitemap-videos.xml')),
        'lastmod' => $latest_post_modified,
    ];

    $post_sitemaps = gta6mods_get_post_sitemap_slugs();
    foreach ($post_sitemaps as $slug) {
        $entries[] = [
            'loc'     => esc_url_raw(home_url('/sitemap-' . $slug . '.xml')),
            'lastmod' => $latest_post_modified,
        ];
    }

    if (class_exists('GTA6M_Forum_Threads_Sitemap_Provider')) {
        $threads_provider = new GTA6M_Forum_Threads_Sitemap_Provider();
        $thread_pages     = max(1, (int) $threads_provider->get_max_num_pages());

        for ($page = 1; $page <= $thread_pages; $page++) {
            $thread_entries = $threads_provider->get_url_list($page);
            $thread_lastmod = !empty($thread_entries) && isset($thread_entries[0]['lastmod']) && '' !== $thread_entries[0]['lastmod']
                ? $thread_entries[0]['lastmod']
                : gmdate('Y-m-d\TH:i:s\Z');

            $entries[] = [
                'loc'     => esc_url_raw(home_url('/sitemap-forum-threads-' . $page . '.xml')),
                'lastmod' => $thread_lastmod,
            ];
        }
    }

    if (class_exists('GTA6M_Forum_Flairs_Sitemap_Provider')) {
        $flairs_provider = new GTA6M_Forum_Flairs_Sitemap_Provider();
        $flair_entries   = $flairs_provider->get_url_list(1);
        $flairs_lastmod  = !empty($flair_entries) && isset($flair_entries[0]['lastmod']) && '' !== $flair_entries[0]['lastmod']
            ? $flair_entries[0]['lastmod']
            : gmdate('Y-m-d\TH:i:s\Z');

        $entries[] = [
            'loc'     => esc_url_raw(home_url('/sitemap-forum-flairs.xml')),
            'lastmod' => $flairs_lastmod,
        ];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $entry) {
        $xml .= '  <sitemap>' . "\n";
        $xml .= '    <loc>' . esc_xml($entry['loc']) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . esc_xml($entry['lastmod']) . '</lastmod>' . "\n";
        $xml .= '  </sitemap>' . "\n";
    }
    $xml .= '</sitemapindex>';

    return $xml;
}

/**
 * Returns the slugs for paginated post sitemaps.
 *
 * @return array<int, string>
 */
function gta6mods_get_post_sitemap_slugs() {
    static $slugs = null;

    if (null !== $slugs) {
        return $slugs;
    }

    $post_types = gta6mods_get_sitemap_post_types();
    $total = 0;
    foreach ($post_types as $type) {
        $counts = wp_count_posts($type);
        if ($counts && isset($counts->publish)) {
            $total += (int) $counts->publish;
        }
    }

    $pages = (int) ceil($total / 10000);
    $slugs = [];

    if ($pages < 1) {
        return $slugs;
    }

    for ($i = 1; $i <= $pages; $i++) {
        $slugs[] = 'posts-' . $i;
    }

    return $slugs;
}

/**
 * Generates the static pages sitemap.
 *
 * @return string
 */
function gta6mods_generate_static_sitemap() {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $home_url = home_url('/');
    $xml .= '  <url>' . "\n";
    $xml .= '    <loc>' . esc_xml(esc_url_raw($home_url)) . '</loc>' . "\n";
    $xml .= '    <changefreq>hourly</changefreq>' . "\n";
    $xml .= '    <priority>1.0</priority>' . "\n";
    $xml .= '    <lastmod>' . esc_xml(gta6mods_get_latest_post_modified_gmt()) . '</lastmod>' . "\n";
    $xml .= '  </url>' . "\n";

    $pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ]);

    foreach ($pages as $page_id) {
        $permalink = get_permalink($page_id);
        if (empty($permalink)) {
            continue;
        }

        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml(esc_url_raw($permalink)) . '</loc>' . "\n";
        $xml .= '    <changefreq>monthly</changefreq>' . "\n";
        $xml .= '    <priority>0.5</priority>' . "\n";
        $xml .= '    <lastmod>' . esc_xml(get_post_modified_time('Y-m-d\TH:i:s\Z', true, $page_id)) . '</lastmod>' . "\n";
        $xml .= '  </url>' . "\n";
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Generates the categories sitemap.
 *
 * @return string
 */
function gta6mods_generate_categories_sitemap() {
    $terms = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => true,
    ]);

    if (is_wp_error($terms)) {
        return '';
    }

    $post_types = gta6mods_get_sitemap_post_types();
    $term_lastmod = gta6mods_get_term_last_modified_map('category', $post_types);

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $link = get_term_link($term);
        if (is_wp_error($link) || empty($link)) {
            continue;
        }

        $priority = 0.7;
        if ($term->count >= 1000) {
            $priority = 0.9;
        } elseif ($term->count >= 100) {
            $priority = 0.8;
        }

        $lastmod = isset($term_lastmod[$term->term_id]) ? $term_lastmod[$term->term_id] : gta6mods_get_latest_post_modified_gmt();

        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml(esc_url_raw($link)) . '</loc>' . "\n";
        $xml .= '    <changefreq>daily</changefreq>' . "\n";
        $xml .= '    <priority>' . esc_xml(number_format($priority, 1, '.', '')) . '</priority>' . "\n";
        $xml .= '    <lastmod>' . esc_xml($lastmod) . '</lastmod>' . "\n";
        $xml .= '  </url>' . "\n";
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Retrieves last modified timestamps for terms.
 *
 * @param string               $taxonomy   Taxonomy name.
 * @param array<int, string>   $post_types Post types.
 *
 * @return array<int, string>
 */
function gta6mods_get_term_last_modified_map($taxonomy, $post_types) {
    global $wpdb;

    if (empty($post_types)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
    $sql = "SELECT tt.term_id, MAX(p.post_modified_gmt) AS lastmod" .
        " FROM {$wpdb->term_taxonomy} tt" .
        " INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id" .
        " INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id" .
        " WHERE tt.taxonomy = %s AND p.post_status = 'publish' AND p.post_type IN ({$placeholders})" .
        ' GROUP BY tt.term_id';

    $params = array_merge([$taxonomy], $post_types);
    $prepared = $wpdb->prepare($sql, $params);
    $rows = $wpdb->get_results($prepared, ARRAY_A);

    $map = [];
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
            if ($term_id <= 0) {
                continue;
            }
            $map[$term_id] = gta6mods_format_sitemap_date($row['lastmod']);
        }
    }

    return $map;
}

/**
 * Generates the tags sitemap (top 100 tags by usage).
 *
 * @return string
 */
function gta6mods_generate_tags_sitemap() {
    $terms = get_terms([
        'taxonomy'   => 'post_tag',
        'hide_empty' => true,
        'number'     => 100,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ]);

    if (is_wp_error($terms)) {
        return '';
    }

    $post_types = gta6mods_get_sitemap_post_types();
    $term_lastmod = gta6mods_get_term_last_modified_map('post_tag', $post_types);

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $index = 0;
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $link = get_term_link($term);
        if (is_wp_error($link) || empty($link)) {
            continue;
        }

        $priority = ($index < 20) ? 0.7 : 0.6;
        $lastmod = isset($term_lastmod[$term->term_id]) ? $term_lastmod[$term->term_id] : gta6mods_get_latest_post_modified_gmt();

        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml(esc_url_raw($link)) . '</loc>' . "\n";
        $xml .= '    <changefreq>daily</changefreq>' . "\n";
        $xml .= '    <priority>' . esc_xml(number_format($priority, 1, '.', '')) . '</priority>' . "\n";
        $xml .= '    <lastmod>' . esc_xml($lastmod) . '</lastmod>' . "\n";
        $xml .= '  </url>' . "\n";

        $index++;
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Generates paginated post sitemaps.
 *
 * @param string $type Sitemap slug (posts-1, posts-2, ...).
 *
 * @return string
 */
function gta6mods_generate_posts_sitemap($type) {
    if (0 !== strpos($type, 'posts-')) {
        return '';
    }

    $page = (int) substr($type, strlen('posts-'));
    if ($page < 1) {
        return '';
    }

    $offset = ($page - 1) * 10000;

    $query = new WP_Query([
        'post_type'           => gta6mods_get_sitemap_post_types(),
        'post_status'         => 'publish',
        'posts_per_page'      => 10000,
        'offset'              => $offset,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'fields'              => 'ids',
        'ignore_sticky_posts' => true,
    ]);

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    if (empty($query->posts)) {
        $xml .= '</urlset>';

        return $xml;
    }

    foreach ($query->posts as $post_id) {
        $permalink = get_permalink($post_id);
        if (empty($permalink)) {
            continue;
        }

        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml(esc_url_raw($permalink)) . '</loc>' . "\n";
        $xml .= '    <changefreq>weekly</changefreq>' . "\n";
        $xml .= '    <priority>0.7</priority>' . "\n";
        $xml .= '    <lastmod>' . esc_xml(get_post_modified_time('Y-m-d\TH:i:s\Z', true, $post_id)) . '</lastmod>' . "\n";
        $xml .= '  </url>' . "\n";
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Generates the forum threads sitemap for the given page.
 */
function gta6mods_generate_forum_threads_sitemap(int $page = 1): string {
    if ($page < 1 || !class_exists('GTA6M_Forum_Threads_Sitemap_Provider')) {
        return '';
    }

    $provider = new GTA6M_Forum_Threads_Sitemap_Provider();
    $entries  = $provider->get_url_list($page);

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    if (empty($entries)) {
        $xml .= '</urlset>';

        return $xml;
    }

    $allowed_changefreq = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    foreach ($entries as $entry) {
        $loc = isset($entry['loc']) ? $entry['loc'] : '';
        if ('' === $loc) {
            continue;
        }

        $lastmod    = isset($entry['lastmod']) && '' !== $entry['lastmod'] ? $entry['lastmod'] : gmdate('Y-m-d\TH:i:s\Z');
        $changefreq = isset($entry['changefreq']) ? strtolower((string) $entry['changefreq']) : 'weekly';
        if (!in_array($changefreq, $allowed_changefreq, true)) {
            $changefreq = 'weekly';
        }
        $priority = isset($entry['priority']) ? (float) $entry['priority'] : 0.6;
        $priority = max(0.0, min(1.0, $priority));
        $priority_string = number_format($priority, 1, '.', '');

        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml(esc_url_raw($loc)) . '</loc>' . "\n";
        $xml .= '    <changefreq>' . esc_xml($changefreq) . '</changefreq>' . "\n";
        $xml .= '    <priority>' . esc_xml($priority_string) . '</priority>' . "\n";
        $xml .= '    <lastmod>' . esc_xml($lastmod) . '</lastmod>' . "\n";
        $xml .= '  </url>' . "\n";
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Generates the forum flairs sitemap.
 */
function gta6mods_generate_forum_flairs_sitemap(): string {
    if (!class_exists('GTA6M_Forum_Flairs_Sitemap_Provider')) {
        return '';
    }

    $provider = new GTA6M_Forum_Flairs_Sitemap_Provider();
    $entries  = $provider->get_url_list(1);

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    if (empty($entries)) {
        $xml .= '</urlset>';

        return $xml;
    }

    foreach ($entries as $entry) {
        $loc = isset($entry['loc']) ? $entry['loc'] : '';
        if ('' === $loc) {
            continue;
        }

        $lastmod    = isset($entry['lastmod']) && '' !== $entry['lastmod'] ? $entry['lastmod'] : gmdate('Y-m-d\TH:i:s\Z');
        $changefreq = isset($entry['changefreq']) ? strtolower((string) $entry['changefreq']) : 'weekly';
        $priority   = isset($entry['priority']) ? (float) $entry['priority'] : 0.6;
        $priority   = max(0.0, min(1.0, $priority));
        $priority_string = number_format($priority, 1, '.', '');

        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml(esc_url_raw($loc)) . '</loc>' . "\n";
        $xml .= '    <changefreq>' . esc_xml($changefreq) . '</changefreq>' . "\n";
        $xml .= '    <priority>' . esc_xml($priority_string) . '</priority>' . "\n";
        $xml .= '    <lastmod>' . esc_xml($lastmod) . '</lastmod>' . "\n";
        $xml .= '  </url>' . "\n";
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Determines whether a category/tag combination has any published posts.
 *
 * @param WP_Term $category Category term.
 * @param WP_Term $tag      Tag term.
 *
 * @return bool
 */
function gta6mods_filter_has_results(WP_Term $category, WP_Term $tag) {
    static $cache = [];

    $cache_key = $category->term_id . '|' . $tag->term_id;
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $query = new WP_Query([
        'post_type'      => gta6mods_get_sitemap_post_types(),
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'fields'         => 'ids',
        'tax_query'      => [
            'relation' => 'AND',
            [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => [$category->term_id],
            ],
            [
                'taxonomy' => 'post_tag',
                'field'    => 'term_id',
                'terms'    => [$tag->term_id],
            ],
        ],
    ]);

    $has_results = $query->have_posts();
    $cache[$cache_key] = $has_results;

    return $has_results;
}

/**
 * Generates the filters sitemap focusing on top category/tag combinations.
 *
 * @return string
 */
function gta6mods_generate_filters_sitemap() {
    $top_categories = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => true,
        'number'     => 10,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ]);

    $top_tags = get_terms([
        'taxonomy'   => 'post_tag',
        'hide_empty' => true,
        'number'     => 20,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ]);

    if (is_wp_error($top_categories) || is_wp_error($top_tags)) {
        return '';
    }

    $sorts = ['most-downloaded', 'latest-uploads', 'highest-rated', 'most-liked'];
    $times = ['week', 'month', 'all'];
    $default_sort = function_exists('gta6mods_get_default_archive_sort') ? gta6mods_get_default_archive_sort() : 'latest-uploads';
    $default_since = function_exists('gta6mods_get_default_archive_since') ? gta6mods_get_default_archive_since() : 'all';

    $latest = gta6mods_get_latest_post_modified_gmt();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($top_categories as $category) {
        if (!$category instanceof WP_Term) {
            continue;
        }

        $category_query = gta6mods_build_virtual_category_query($category);

        foreach ($top_tags as $tag) {
            if (!$tag instanceof WP_Term) {
                continue;
            }

            if (!gta6mods_filter_has_results($category, $tag)) {
                continue;
            }

            $base_priority = 0.8;
            $base_url = gta6mods_build_category_tag_url($category_query, $tag);
            if ($base_url) {
                $xml .= gta6mods_render_filter_url_entry($base_url, $base_priority, 'daily', $latest);
            }

            foreach ($sorts as $sort) {
                if ($sort === $default_sort) {
                    continue;
                }

                $sort_priority = 0.7;
                $sort_url = gta6mods_build_category_tag_url($category_query, $tag, $sort);
                if ($sort_url) {
                    $xml .= gta6mods_render_filter_url_entry($sort_url, $sort_priority, 'weekly', $latest);
                }

                foreach ($times as $since) {
                    if ($since === $default_since) {
                        continue;
                    }

                    $time_priority = 0.6;
                    $time_url = gta6mods_build_category_tag_url($category_query, $tag, $sort, $since);
                    if ($time_url) {
                        $xml .= gta6mods_render_filter_url_entry($time_url, $time_priority, 'weekly', $latest);
                    }
                }
            }

            $tag_query = gta6mods_build_virtual_tag_query($tag, $category);
            if ($tag_query) {
                $tag_base_url = gta6mods_build_tag_category_url($tag_query, $category);
                if ($tag_base_url) {
                    $xml .= gta6mods_render_filter_url_entry($tag_base_url, $base_priority, 'daily', $latest);
                }
            }
        }
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Creates a virtual WP_Query for a category context.
 *
 * @param WP_Term $category Category term.
 *
 * @return WP_Query
 */
function gta6mods_build_virtual_category_query(WP_Term $category) {
    $query = new WP_Query();
    $query->is_category = true;
    $query->queried_object = $category;
    $query->queried_object_id = (int) $category->term_id;
    $query->set('cat', (int) $category->term_id);

    return $query;
}

/**
 * Creates a virtual WP_Query for a tag context.
 *
 * @param WP_Term      $tag      Tag term.
 * @param WP_Term|null $category Optional paired category.
 *
 * @return WP_Query|null
 */
function gta6mods_build_virtual_tag_query(WP_Term $tag, ?WP_Term $category = null) {
    $query = new WP_Query();
    $query->is_tag = true;
    $query->queried_object = $tag;
    $query->queried_object_id = (int) $tag->term_id;
    $query->set('tag_id', (int) $tag->term_id);

    if ($category instanceof WP_Term) {
        $query->set('gta_category_filter', [
            'path' => $category->slug,
            'term' => $category,
        ]);
    }

    return $query;
}

/**
 * Builds a category + tag filter URL.
 *
 * @param WP_Query $category_query Virtual category query.
 * @param WP_Term  $tag            Tag term.
 * @param string   $sort           Sort key.
 * @param string   $since          Time filter.
 *
 * @return string
 */
function gta6mods_build_category_tag_url(WP_Query $category_query, WP_Term $tag, $sort = null, $since = null) {
    $category_query->set('gta_tag_filter', [
        'raw'    => $tag->slug,
        'labels' => [$tag->name],
    ]);

    return gta6mods_build_archive_filter_url($category_query, $sort, $since, []);
}

/**
 * Builds a tag + category filter URL.
 *
 * @param WP_Query $tag_query Tag context query.
 * @param WP_Term  $category  Category term.
 * @param string   $sort      Sort key.
 * @param string   $since     Time filter.
 *
 * @return string
 */
function gta6mods_build_tag_category_url(WP_Query $tag_query, WP_Term $category, $sort = null, $since = null) {
    $tag_query->set('gta_category_filter', [
        'path' => $category->slug,
        'term' => $category,
    ]);

    return gta6mods_build_archive_filter_url($tag_query, $sort, $since, []);
}

/**
 * Renders a single URL entry for the filter sitemap.
 *
 * @param string $url        URL.
 * @param float  $priority   Priority value.
 * @param string $changefreq Change frequency.
 * @param string $lastmod    Last modified timestamp.
 *
 * @return string
 */
function gta6mods_render_filter_url_entry($url, $priority, $changefreq, $lastmod) {
    if (empty($url)) {
        return '';
    }

    $output  = '  <url>' . "\n";
    $output .= '    <loc>' . esc_xml(esc_url_raw($url)) . '</loc>' . "\n";
    $output .= '    <changefreq>' . esc_xml($changefreq) . '</changefreq>' . "\n";
    $output .= '    <priority>' . esc_xml(number_format((float) $priority, 1, '.', '')) . '</priority>' . "\n";
    $output .= '    <lastmod>' . esc_xml($lastmod) . '</lastmod>' . "\n";
    $output .= '  </url>' . "\n";

    return $output;
}

/**
 * Returns a curated list of default high-value searches.
 *
 * @return array<int, string>
 */
function gta6mods_get_default_search_terms() {
    return [
        'BMW',
        'Tesla',
        'Lamborghini',
        'Ferrari',
        'Porsche',
        'Bugatti',
        'Motorcycle',
        'Weapons',
        'Graphics',
        'Realistic',
        'Performance',
        'Sound mod',
        'Supercar',
        'Police car',
        'Multiplayer',
    ];
}

/**
 * Generates the searches sitemap.
 *
 * @return string
 */
function gta6mods_generate_searches_sitemap() {
    $defaults = gta6mods_get_default_search_terms();
    $logged_terms = gta6mods_get_tracked_search_terms();

    $combined = [];
    foreach ($defaults as $term) {
        $term = trim($term);
        if ('' === $term) {
            continue;
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
        $combined[$key] = [
            'term'         => $term,
            'count'        => 0,
            'last_searched'=> '',
        ];
    }

    foreach ($logged_terms as $record) {
        if (!is_array($record) || empty($record['term'])) {
            continue;
        }

        $term = trim($record['term']);
        if ('' === $term) {
            continue;
        }

        $normalized = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
        $count = isset($record['count']) ? max(0, (int) $record['count']) : 0;
        $last = isset($record['last_searched']) ? $record['last_searched'] : '';

        if (!isset($combined[$normalized]) || $combined[$normalized]['count'] < $count) {
            $combined[$normalized] = [
                'term'         => $term,
                'count'        => $count,
                'last_searched'=> $last,
            ];
        }
    }

    $term_data = array_values($combined);

    usort(
        $term_data,
        static function ($a, $b) {
            $count_a = isset($a['count']) ? (int) $a['count'] : 0;
            $count_b = isset($b['count']) ? (int) $b['count'] : 0;

            if ($count_a === $count_b) {
                return 0;
            }

            return ($count_a < $count_b) ? 1 : -1;
        }
    );

    $entries = [];
    $term_data = array_slice($term_data, 0, 200);

    foreach ($term_data as $record) {
        $term = isset($record['term']) ? trim($record['term']) : '';
        if ('' === $term) {
            continue;
        }

        $result_count = gta6mods_count_search_results($term);
        if ($result_count < 5) {
            continue;
        }

        $popularity = isset($record['count']) ? max(0, (int) $record['count']) : 0;
        $priority = min(0.8, 0.5 + ($popularity / 1000));
        $lastmod_source = isset($record['last_searched']) ? $record['last_searched'] : '';
        $lastmod = '' !== $lastmod_source ? gta6mods_format_sitemap_date($lastmod_source) : gmdate('Y-m-d\TH:i:s\Z');

        $link = get_search_link($term);
        if (empty($link) || is_wp_error($link)) {
            continue;
        }

        $entries[] = [
            'loc'        => esc_url_raw($link),
            'priority'   => $priority,
            'lastmod'    => $lastmod,
            'changefreq' => 'weekly',
        ];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $entry) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml($entry['loc']) . '</loc>' . "\n";
        $xml .= '    <changefreq>' . esc_xml($entry['changefreq']) . '</changefreq>' . "\n";
        $xml .= '    <priority>' . esc_xml(number_format($entry['priority'], 1, '.', '')) . '</priority>' . "\n";
        $xml .= '    <lastmod>' . esc_xml($entry['lastmod']) . '</lastmod>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    $xml .= '</urlset>';

    return $xml;
}

/**
 * Fetches top tracked searches from analytics table.
 *
 * @return array<int, array<string, mixed>>
 */
function gta6mods_get_tracked_search_terms() {
    global $wpdb;

    $table = gta6mods_get_search_log_table_name();
    $like  = $wpdb->esc_like($table);
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like)) !== $table) {
        return [];
    }

    $sql = "SELECT search_term, search_count, last_searched FROM {$table} WHERE search_count >= %d ORDER BY search_count DESC, last_searched DESC LIMIT %d";
    $prepared = $wpdb->prepare($sql, 3, 100);
    $results = $wpdb->get_results($prepared, ARRAY_A);

    if (empty($results)) {
        return [];
    }

    $formatted = [];
    foreach ($results as $row) {
        $term = isset($row['search_term']) ? sanitize_text_field($row['search_term']) : '';
        if ('' === $term) {
            continue;
        }

        $formatted[] = [
            'term'         => $term,
            'count'        => isset($row['search_count']) ? max(0, (int) $row['search_count']) : 0,
            'last_searched'=> isset($row['last_searched']) ? $row['last_searched'] : '',
        ];
    }

    return $formatted;
}

/**
 * Counts published search results for a query.
 *
 * @param string $term Search term.
 *
 * @return int
 */
function gta6mods_count_search_results($term) {
    $term = trim($term);
    if ('' === $term) {
        return 0;
    }

    $query = new WP_Query([
        's'                 => $term,
        'post_type'         => gta6mods_get_sitemap_post_types(),
        'post_status'       => 'publish',
        'posts_per_page'    => 1,
        'ignore_sticky_posts' => true,
    ]);

    return (int) $query->found_posts;
}

/**
 * Generates the image sitemap for recent posts.
 *
 * @return string
 */
function gta6mods_generate_images_sitemap() {
    $query = new WP_Query([
        'post_type'           => gta6mods_get_sitemap_post_types(),
        'post_status'         => 'publish',
        'posts_per_page'      => 5000,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'meta_key'            => '_thumbnail_id',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ]);

    if (empty($query->posts)) {
        return '';
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    foreach ($query->posts as $post_id) {
        $permalink = get_permalink($post_id);
        $thumb_id = get_post_thumbnail_id($post_id);
        if (empty($permalink) || !$thumb_id) {
            continue;
        }

        $image_url = wp_get_attachment_url($thumb_id);
        if (empty($image_url)) {
            continue;
        }

        $title = get_the_title($post_id);
        $caption = wp_get_attachment_caption($thumb_id);

        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml(esc_url_raw($permalink)) . '</loc>' . "\n";
        $xml .= '    <image:image>' . "\n";
        $xml .= '      <image:loc>' . esc_xml(esc_url_raw($image_url)) . '</image:loc>' . "\n";
        if (!empty($title)) {
            $xml .= '      <image:title>' . esc_xml($title) . '</image:title>' . "\n";
        }
        if (!empty($caption)) {
            $xml .= '      <image:caption>' . esc_xml($caption) . '</image:caption>' . "\n";
        }
        $xml .= '    </image:image>' . "\n";
        $xml .= '  </url>' . "\n";
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Generates the authors sitemap with top creators.
 *
 * @return string
 */
function gta6mods_generate_authors_sitemap() {
    global $wpdb;

    $post_types = gta6mods_get_sitemap_post_types();
    if (empty($post_types)) {
        return '';
    }

    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
    $sql = "SELECT p.post_author, COUNT(*) AS post_count, MAX(p.post_modified_gmt) AS lastmod" .
        " FROM {$wpdb->posts} p" .
        " WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})" .
        ' GROUP BY p.post_author HAVING post_count > 0' .
        ' ORDER BY post_count DESC, lastmod DESC LIMIT 500';

    $prepared = $wpdb->prepare($sql, $post_types);
    $rows = $wpdb->get_results($prepared, ARRAY_A);
    if (empty($rows)) {
        return '';
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($rows as $row) {
        $author_id = isset($row['post_author']) ? (int) $row['post_author'] : 0;
        if ($author_id <= 0) {
            continue;
        }

        $author_url = get_author_posts_url($author_id);
        if (empty($author_url)) {
            continue;
        }

        $priority = 0.5;
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_xml(esc_url_raw($author_url)) . '</loc>' . "\n";
        $xml .= '    <changefreq>weekly</changefreq>' . "\n";
        $xml .= '    <priority>' . esc_xml(number_format($priority, 1, '.', '')) . '</priority>' . "\n";
        $xml .= '    <lastmod>' . esc_xml(gta6mods_format_sitemap_date($row['lastmod'])) . '</lastmod>' . "\n";
        $xml .= '  </url>' . "\n";
    }

    $xml .= '</urlset>';

    return $xml;
}

/**
 * Installs or updates the search analytics table.
 *
 * @return void
 */
function gta6mods_install_search_log_table() {
    global $wpdb;

    $table = gta6mods_get_search_log_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table} (" .
        ' id bigint(20) unsigned NOT NULL AUTO_INCREMENT,' .
        ' search_term varchar(200) NOT NULL,' .
        ' search_count int unsigned NOT NULL DEFAULT 1,' .
        ' last_searched datetime NOT NULL,' .
        ' PRIMARY KEY (id),' .
        ' UNIQUE KEY search_term (search_term),' .
        ' KEY search_count (search_count),' .
        ' KEY last_searched (last_searched)' .
        ") {$charset_collate};";

    dbDelta($sql);

    update_option('gta6mods_search_log_version', '1.0.0', false);
}

/**
 * Ensures the search analytics table exists.
 *
 * @return void
 */
function gta6mods_maybe_install_search_log_table() {
    $installed = get_option('gta6mods_search_log_version');
    if (false === $installed) {
        gta6mods_install_search_log_table();
    }
}
add_action('after_switch_theme', 'gta6mods_install_search_log_table');
add_action('init', 'gta6mods_maybe_install_search_log_table', 5);

/**
 * Logs search queries for analytics.
 *
 * @param WP_Query $query Query instance.
 *
 * @return void
 */
function gta6mods_track_search_query(WP_Query $query) {
    if (!($query instanceof WP_Query)) {
        return;
    }

    if (! $query->is_main_query() || !$query->is_search() || is_admin()) {
        return;
    }

    $search = $query->get('s');
    if (!is_string($search) || '' === trim($search)) {
        return;
    }

    $trimmed_search = trim($search);
    $normalized = function_exists('mb_strtolower')
        ? mb_strtolower($trimmed_search, 'UTF-8')
        : strtolower($trimmed_search);
    if ('' === $normalized) {
        return;
    }

    static $logged = false;
    if ($logged) {
        return;
    }

    $logged = true;

    global $wpdb;
    $table = gta6mods_get_search_log_table_name();
    $like  = $wpdb->esc_like($table);

    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if ($exists !== $table) {
        return;
    }

    $now = current_time('mysql', true);
    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$table} (search_term, search_count, last_searched) VALUES (%s, %d, %s) " .
            'ON DUPLICATE KEY UPDATE search_count = search_count + 1, last_searched = VALUES(last_searched)',
            $normalized,
            1,
            $now
        )
    );
}
add_action('pre_get_posts', 'gta6mods_track_search_query', 20);

/**
 * Pings Google with the sitemap URL when a post is published.
 *
 * @param int $post_id Post ID.
 *
 * @return void
 */
function gta6mods_ping_google($post_id) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    if (get_transient('gta6mods_pinged_' . $post_id)) {
        return;
    }

    $sitemap_url = urlencode(home_url('/sitemap.xml'));
    $ping_url = "https://www.google.com/ping?sitemap={$sitemap_url}";

    wp_remote_get($ping_url, [
        'timeout' => 5,
    ]);

    set_transient('gta6mods_pinged_' . $post_id, 1, DAY_IN_SECONDS);
}
add_action('publish_post', 'gta6mods_ping_google');

