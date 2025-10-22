<?php
/**
 * Forum Sitemap Providers
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_Sitemaps_Provider') && file_exists(ABSPATH . 'wp-includes/sitemaps/class-wp-sitemaps-provider.php')) {
    require_once ABSPATH . 'wp-includes/sitemaps/class-wp-sitemaps-provider.php';
}

if (!class_exists('WP_Sitemaps_Provider')) {
    return;
}

/**
 * Registers forum sitemap providers.
 */
function gta6m_register_forum_sitemaps(): void
{
    if (!function_exists('wp_register_sitemap_provider')) {
        return;
    }

    wp_register_sitemap_provider('forum-threads', new GTA6M_Forum_Threads_Sitemap_Provider());
    wp_register_sitemap_provider('forum-flairs', new GTA6M_Forum_Flairs_Sitemap_Provider());
}
add_action('init', 'gta6m_register_forum_sitemaps', 20);

/**
 * Forum Threads Sitemap Provider.
 */
class GTA6M_Forum_Threads_Sitemap_Provider extends WP_Sitemaps_Provider
{
    public function get_name()
    {
        return 'forum-threads';
    }

    public function get_object_subtypes()
    {
        return [];
    }

    public function get_url_list($page_num, $object_subtype = '')
    {
        if ($page_num < 1) {
            return [];
        }

        $cache_key   = 'gta6m_sitemap_threads_' . $page_num;
        $cache_group = 'gta6m_sitemaps';
        $cached      = wp_cache_get($cache_key, $cache_group);

        if (false !== $cached) {
            return is_array($cached) ? $cached : [];
        }

        $per_page = 2000;
        $offset   = ($page_num - 1) * $per_page;

        $threads = get_posts([
            'post_type'              => 'forum_thread',
            'post_status'            => 'publish',
            'posts_per_page'         => $per_page,
            'offset'                 => $offset,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $entries = [];
        foreach ($threads as $thread_id) {
            $lastmod      = $this->get_thread_lastmod($thread_id);
            $comment_count = (int) get_comments_number($thread_id);
            $changefreq   = $comment_count > 10 ? 'daily' : 'weekly';

            $entries[] = [
                'loc'        => get_permalink($thread_id),
                'lastmod'    => $lastmod,
                'changefreq' => $changefreq,
                'priority'   => 0.8,
            ];
        }

        wp_cache_set($cache_key, $entries, $cache_group, HOUR_IN_SECONDS * 6);
        gta6m_track_cache_key($cache_key, $cache_group);

        return $entries;
    }

    public function get_max_num_pages($object_subtype = '')
    {
        $cache_key   = 'gta6m_sitemap_threads_max';
        $cache_group = 'gta6m_sitemaps';
        $cached      = wp_cache_get($cache_key, $cache_group);

        if (false !== $cached) {
            return (int) $cached;
        }

        $counts    = wp_count_posts('forum_thread');
        $total     = $counts->publish ?? 0;
        $per_page  = 2000;
        $max_pages = (int) ceil($total / $per_page);

        wp_cache_set($cache_key, $max_pages, $cache_group, HOUR_IN_SECONDS * 6);
        gta6m_track_cache_key($cache_key, $cache_group);

        return $max_pages;
    }

    private function get_thread_lastmod(int $thread_id): string
    {
        $cache_key   = 'gta6m_thread_lastmod_' . $thread_id;
        $cache_group = 'gta6m_dates';
        $cached      = wp_cache_get($cache_key, $cache_group);

        if (false !== $cached) {
            return (string) $cached;
        }

        $post_modified = get_post_modified_time('Y-m-d\TH:i:s\Z', true, $thread_id);

        $last_comment_ids = get_comments([
            'post_id' => $thread_id,
            'number'  => 1,
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
            'status'  => 'approve',
            'fields'  => 'ids',
            'no_found_rows' => true,
        ]);

        $lastmod = $post_modified;
        if (!empty($last_comment_ids)) {
            $comment = get_comment($last_comment_ids[0]);
            if ($comment) {
                $comment_date = get_comment_date('Y-m-d\TH:i:s\Z', $comment);
                if ($comment_date && strtotime($comment_date) > strtotime($post_modified)) {
                    $lastmod = $comment_date;
                }
            }
        }

        wp_cache_set($cache_key, $lastmod, $cache_group, HOUR_IN_SECONDS * 6);
        gta6m_track_cache_key($cache_key, $cache_group);

        return $lastmod;
    }
}

/**
 * Forum Flairs Sitemap Provider.
 */
class GTA6M_Forum_Flairs_Sitemap_Provider extends WP_Sitemaps_Provider
{
    public function get_name()
    {
        return 'forum-flairs';
    }

    public function get_object_subtypes()
    {
        return [];
    }

    public function get_url_list($page_num, $object_subtype = '')
    {
        if ($page_num > 1) {
            return [];
        }

        $cache_key   = 'gta6m_sitemap_flairs';
        $cache_group = 'gta6m_sitemaps';
        $cached      = wp_cache_get($cache_key, $cache_group);

        if (false !== $cached) {
            return is_array($cached) ? $cached : [];
        }

        $terms = get_terms([
            'taxonomy'   => 'forum_flair',
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $entries = [];
        foreach ($terms as $term) {
            $term_link = get_term_link($term);
            if (is_wp_error($term_link)) {
                continue;
            }

            $entries[] = [
                'loc'        => $term_link,
                'lastmod'    => $this->get_flair_lastmod($term->term_id),
                'changefreq' => 'weekly',
                'priority'   => 0.7,
            ];
        }

        wp_cache_set($cache_key, $entries, $cache_group, HOUR_IN_SECONDS * 6);
        gta6m_track_cache_key($cache_key, $cache_group);

        return $entries;
    }

    public function get_max_num_pages($object_subtype = '')
    {
        return 1;
    }

    private function get_flair_lastmod(int $term_id): string
    {
        $cache_key   = 'gta6m_flair_lastmod_' . $term_id;
        $cache_group = 'gta6m_dates';
        $cached      = wp_cache_get($cache_key, $cache_group);

        if (false !== $cached) {
            return (string) $cached;
        }

        global $wpdb;

        $sql = "SELECT MAX(p.post_modified_gmt)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.term_id = %d
                AND tt.taxonomy = 'forum_flair'
                AND p.post_status = 'publish'
                AND p.post_type = 'forum_thread'";

        $prepared    = $wpdb->prepare($sql, $term_id);
        $lastmod_raw = $wpdb->get_var($prepared);

        $lastmod = $lastmod_raw ? gmdate('Y-m-d\TH:i:s\Z', strtotime($lastmod_raw)) : gmdate('Y-m-d\TH:i:s\Z');

        wp_cache_set($cache_key, $lastmod, $cache_group, HOUR_IN_SECONDS * 6);
        gta6m_track_cache_key($cache_key, $cache_group);

        return $lastmod;
    }
}
