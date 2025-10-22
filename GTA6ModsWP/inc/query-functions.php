<?php
/**
 * Functions that modify or handle WP_Query.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GTA6_MODS_AUTHOR_DISPLAY_NAME_META_KEY')) {
    define('GTA6_MODS_AUTHOR_DISPLAY_NAME_META_KEY', '_gta6mods_author_display_name');
}

if (!defined('GTA6_MODS_PRIMARY_CATEGORY_NAME_META_KEY')) {
    define('GTA6_MODS_PRIMARY_CATEGORY_NAME_META_KEY', '_gta6mods_primary_category_name');
}

if (!defined('GTA6_MODS_PRIMARY_CATEGORY_SLUG_META_KEY')) {
    define('GTA6_MODS_PRIMARY_CATEGORY_SLUG_META_KEY', '_gta6mods_primary_category_slug');
}

if (!defined('GTA6_MODS_PRIMARY_CATEGORY_ID_META_KEY')) {
    define('GTA6_MODS_PRIMARY_CATEGORY_ID_META_KEY', '_gta6mods_primary_category_id');
}

/**
 * Collects data for the front page sections.
 */
function gta6_mods_collect_front_page_data() {
    global $wpdb;

    $data = [
        'featuredMods' => [],
        'popularMods' => [],
        'latestMods' => [],
        'latestNews' => [],
    ];

    $featured_limit = (int) apply_filters('gta6mods_front_page_featured_limit', 4);
    if ($featured_limit <= 0) {
        $featured_limit = 4;
    }

    $index_table         = gta6mods_get_filter_index_table_name();
    $featured_rows       = [];
    $index_query_error   = false;

    if ($featured_limit > 0) {
        $prepared = $wpdb->prepare(
            "SELECT post_id, featured_timestamp FROM {$index_table} WHERE post_status = %s AND is_featured = 1 ORDER BY featured_timestamp DESC LIMIT %d",
            'publish',
            $featured_limit
        );

        if ($prepared) {
            $featured_rows = $wpdb->get_results($prepared, ARRAY_A);
        }

        if (!empty($wpdb->last_error)) {
            $index_query_error = true;
            $featured_rows = [];
        }
    }

    if (!empty($featured_rows)) {
        $featured_ids = [];
        $timestamp_map = [];

        foreach ($featured_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $post_id = isset($row['post_id']) ? (int) $row['post_id'] : 0;

            if ($post_id <= 0) {
                continue;
            }

            $featured_ids[]                = $post_id;
            $timestamp_map[$post_id] = isset($row['featured_timestamp']) ? (string) $row['featured_timestamp'] : '';
        }

        if (!empty($featured_ids)) {
            $featured_posts = get_posts([
                'post_type'              => gta6mods_get_mod_post_types(),
                'post_status'            => 'publish',
                'posts_per_page'         => count($featured_ids),
                'post__in'               => $featured_ids,
                'orderby'                => 'post__in',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'lazy_load_term_meta'    => false,
                'cache_results'          => true,
            ]);

            foreach ($featured_posts as $featured_post) {
                $post_id = $featured_post instanceof WP_Post ? $featured_post->ID : (int) $featured_post;

                if ($post_id <= 0) {
                    continue;
                }

                $author_name = gta6_mods_get_denormalized_author_name($post_id);
                $metrics     = gta6_mods_get_mod_metrics($post_id);
                $rating      = number_format_i18n(isset($metrics['rating']) ? (float) $metrics['rating'] : 0.0, 1);
                $likes       = number_format_i18n(isset($metrics['likes']) ? (int) $metrics['likes'] : 0);
                $downloads   = number_format_i18n(isset($metrics['downloads']) ? (int) $metrics['downloads'] : 0);
                $version     = function_exists('gta6_mods_get_mod_version') ? gta6_mods_get_mod_version($post_id) : '';

                if ('' === $version) {
                    $version = '1.0';
                }

                $data['featuredMods'][] = [
                    'title'      => wp_strip_all_tags(get_the_title($post_id)),
                    'author'     => $author_name,
                    'downloads'  => $downloads,
                    'likes'      => $likes,
                    'rating'     => $rating,
                    'version'    => sanitize_text_field($version),
                    'image'      => gta6_mods_get_image($post_id, 'large', 'featured'),
                    'link'       => get_permalink($post_id),
                    'featuredAt' => isset($timestamp_map[$post_id]) ? $timestamp_map[$post_id] : gta6mods_get_featured_timestamp($post_id),
                ];
            }
        }
    } elseif ($featured_limit > 0 && $index_query_error) {
        $fallback_query = new WP_Query([
            'posts_per_page'           => $featured_limit,
            'post_status'              => 'publish',
            'post_type'                => gta6mods_get_mod_post_types(),
            'ignore_sticky_posts'      => true,
            'no_found_rows'            => true,
            'update_post_meta_cache'   => false,
            'update_post_term_cache'   => false,
            'lazy_load_term_meta'      => false,
            'cache_results'            => true,
            'meta_key'                 => GTA6_MODS_FEATURED_TIMESTAMP_META_KEY,
            'meta_type'                => 'NUMERIC',
            'orderby'                  => 'meta_value_num',
            'order'                    => 'DESC',
        ]);

        if ($fallback_query->have_posts()) {
            while ($fallback_query->have_posts()) {
                $fallback_query->the_post();
                $post_id     = get_the_ID();
                $author_name = gta6_mods_get_denormalized_author_name($post_id);
                $metrics     = gta6_mods_get_mod_metrics($post_id);
                $rating      = number_format_i18n(isset($metrics['rating']) ? (float) $metrics['rating'] : 0.0, 1);
                $likes       = number_format_i18n(isset($metrics['likes']) ? (int) $metrics['likes'] : 0);
                $downloads   = number_format_i18n(isset($metrics['downloads']) ? (int) $metrics['downloads'] : 0);
                $version     = function_exists('gta6_mods_get_mod_version') ? gta6_mods_get_mod_version($post_id) : '';

                if ('' === $version) {
                    $version = '1.0';
                }

                $data['featuredMods'][] = [
                    'title'      => wp_strip_all_tags(get_the_title()),
                    'author'     => $author_name,
                    'downloads'  => $downloads,
                    'likes'      => $likes,
                    'rating'     => $rating,
                    'version'    => sanitize_text_field($version),
                    'image'      => gta6_mods_get_image($post_id, 'large', 'featured'),
                    'link'       => get_permalink(),
                    'featuredAt' => gta6mods_get_featured_timestamp($post_id),
                ];
            }
            wp_reset_postdata();
        }
    }

    $popular_ids = gta6mods_get_top_mod_ids('downloads', 8);

    if (!empty($popular_ids)) {
        $popular_posts = get_posts([
            'post_type'              => gta6mods_get_mod_post_types(),
            'post_status'            => 'publish',
            'posts_per_page'         => count($popular_ids),
            'post__in'               => $popular_ids,
            'orderby'                => 'post__in',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
            'cache_results'          => true,
        ]);

        foreach ($popular_posts as $popular_post) {
            $post_id     = $popular_post instanceof WP_Post ? $popular_post->ID : (int) $popular_post;
            $author_name = gta6_mods_get_denormalized_author_name($post_id);
            $metrics     = gta6_mods_get_mod_metrics($post_id);
            $rating      = number_format_i18n(isset($metrics['rating']) ? (float) $metrics['rating'] : 0.0, 1);
            $likes       = number_format_i18n(isset($metrics['likes']) ? (int) $metrics['likes'] : 0);
            $downloads   = number_format_i18n(isset($metrics['downloads']) ? (int) $metrics['downloads'] : 0);

            $data['popularMods'][] = [
                'title'     => wp_strip_all_tags(get_the_title($post_id)),
                'author'    => $author_name,
                'downloads' => $downloads,
                'likes'     => $likes,
                'rating'    => $rating,
                'image'     => gta6_mods_get_image($post_id, 'large', 'card'),
                'link'      => get_permalink($post_id),
            ];
        }
    } else {
        $popular_query = new WP_Query([
            'posts_per_page'           => 8,
            'orderby'                  => 'comment_count',
            'order'                    => 'DESC',
            'post_status'              => 'publish',
            'ignore_sticky_posts'      => true,
            'no_found_rows'            => true,
            'update_post_meta_cache'   => false,
            'update_post_term_cache'   => false,
            'lazy_load_term_meta'      => false,
            'cache_results'            => true,
        ]);

        if ($popular_query->have_posts()) {
            while ($popular_query->have_posts()) {
                $popular_query->the_post();
                $post_id     = get_the_ID();
                $author_name = gta6_mods_get_denormalized_author_name($post_id);
                $metrics     = gta6_mods_get_mod_metrics($post_id);
                $rating      = number_format_i18n(isset($metrics['rating']) ? (float) $metrics['rating'] : 0.0, 1);
                $likes       = number_format_i18n(isset($metrics['likes']) ? (int) $metrics['likes'] : 0);
                $downloads   = number_format_i18n(isset($metrics['downloads']) ? (int) $metrics['downloads'] : 0);

                $data['popularMods'][] = [
                    'title'     => wp_strip_all_tags(get_the_title()),
                    'author'    => $author_name,
                    'downloads' => $downloads,
                    'likes'     => $likes,
                    'rating'    => $rating,
                    'image'     => gta6_mods_get_image($post_id, 'large', 'card'),
                    'link'      => get_permalink(),
                ];
            }
            wp_reset_postdata();
        }
    }

    $latest_query = new WP_Query([
        'posts_per_page'           => 8,
        'post_status'              => 'publish',
        'ignore_sticky_posts'      => true,
        'no_found_rows'            => true,
        'update_post_meta_cache'   => false,
        'update_post_term_cache'   => false,
        'lazy_load_term_meta'      => false,
        'cache_results'            => true,
    ]);

    if ($latest_query->have_posts()) {
        while ($latest_query->have_posts()) {
            $latest_query->the_post();
            $post_id     = get_the_ID();
            $author_name = gta6_mods_get_denormalized_author_name($post_id);
            $metrics     = gta6_mods_get_mod_metrics($post_id);
            $rating      = number_format_i18n(isset($metrics['rating']) ? (float) $metrics['rating'] : 0.0, 1);
            $likes       = number_format_i18n(isset($metrics['likes']) ? (int) $metrics['likes'] : 0);
            $downloads   = number_format_i18n(isset($metrics['downloads']) ? (int) $metrics['downloads'] : 0);
            $data['latestMods'][] = [
                'title'     => wp_strip_all_tags(get_the_title()),
                'author'    => $author_name,
                'downloads' => $downloads,
                'likes'     => $likes,
                'rating'    => $rating,
                'image'     => gta6_mods_get_image($post_id, 'large', 'card'),
                'link'      => get_permalink(),
            ];
        }
        wp_reset_postdata();
    }

    $news_query = new WP_Query([
        'posts_per_page'           => 4,
        'post_status'              => 'publish',
        'ignore_sticky_posts'      => true,
        'no_found_rows'            => true,
        'update_post_meta_cache'   => false,
        'update_post_term_cache'   => false,
        'lazy_load_term_meta'      => false,
        'cache_results'            => true,
    ]);

    if ($news_query->have_posts()) {
        while ($news_query->have_posts()) {
            $news_query->the_post();
            $post_id = get_the_ID();
            $raw_excerpt = get_the_excerpt() ? get_the_excerpt() : wp_strip_all_tags(get_the_content());
            $summary     = wp_strip_all_tags(wp_trim_words($raw_excerpt, 30));
            $data['latestNews'][] = [
                'title'    => wp_strip_all_tags(get_the_title()),
                'summary'  => wp_strip_all_tags($summary),
                'date'     => get_the_date(get_option('date_format')),
                'category' => wp_strip_all_tags(gta6_mods_get_category_name($post_id)),
                'image'    => gta6_mods_get_image($post_id, 'large', 'news'),
                'link'     => get_permalink(),
            ];
        }
        wp_reset_postdata();
    }

    return $data;
}

/**
 * Builds a lightweight associative array for a mod card.
 *
 * @param int $post_id Post ID.
 *
 * @return array
 */
function gta6_mods_prepare_mod_card($post_id) {
    $post_id = absint($post_id);

    if ($post_id <= 0) {
        return [];
    }

    $metrics     = gta6_mods_get_mod_metrics($post_id);
    $author_name = gta6_mods_get_denormalized_author_name($post_id);
    $rating      = number_format_i18n(isset($metrics['rating']) ? (float) $metrics['rating'] : 0.0, 1);
    $likes       = number_format_i18n(isset($metrics['likes']) ? (int) $metrics['likes'] : 0);
    $downloads   = number_format_i18n(isset($metrics['downloads']) ? (int) $metrics['downloads'] : 0);

    return [
        'id'         => $post_id,
        'title'      => get_the_title($post_id),
        'permalink'  => get_permalink($post_id),
        'image'      => gta6_mods_get_image($post_id, 'large', 'card'),
        'author'     => $author_name,
        'rating'     => $rating,
        'likes'      => $likes,
        'downloads'  => $downloads,
        'version'    => function_exists('gta6_mods_get_mod_version') ? gta6_mods_get_mod_version($post_id) : '',
    ];
}

/**
 * Retrieves more mods from the same author for the single view sidebar.
 *
 * @param int $author_id Author ID.
 * @param int $exclude_post_id Current post ID to exclude.
 * @param int $limit Number of results to retrieve.
 *
 * @return array[]
 */
function gta6_mods_get_more_by_author_cards($author_id, $exclude_post_id, $limit = 2) {
    $author_id       = absint($author_id);
    $exclude_post_id = absint($exclude_post_id);
    $limit           = max(1, absint($limit));

    if ($author_id <= 0) {
        return [];
    }

    $cache_key = sprintf('gta6mods_more_by_author_%d_%d_%d', $author_id, $exclude_post_id, $limit);
    $cached    = get_transient($cache_key);

    if (false !== $cached) {
        return is_array($cached) ? $cached : [];
    }

    $query = new WP_Query([
        'post_type'              => gta6mods_get_mod_post_types(),
        'post_status'            => 'publish',
        'author'                 => $author_id,
        'post__not_in'           => $exclude_post_id > 0 ? [$exclude_post_id] : [],
        'posts_per_page'         => $limit,
        'ignore_sticky_posts'    => true,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'lazy_load_term_meta'    => false,
        'cache_results'          => true,
    ]);

    $cards = [];

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $cards[] = gta6_mods_prepare_mod_card($post instanceof WP_Post ? $post->ID : (int) $post);
        }
    }

    wp_reset_postdata();

    $cards = array_values(array_filter($cards));

    set_transient($cache_key, $cards, HOUR_IN_SECONDS);

    return $cards;
}

/**
 * Retrieves similar mods for the single view sidebar.
 *
 * @param int $post_id Current post ID.
 * @param int $limit   Number of results.
 *
 * @return array[]
 */
function gta6_mods_get_similar_mod_cards($post_id, $limit = 3) {
    $post_id = absint($post_id);
    $limit   = max(1, absint($limit));

    if ($post_id <= 0) {
        return [];
    }

    $primary_term_ids = [];
    $terms            = get_the_terms($post_id, 'category');

    if (!is_wp_error($terms) && !empty($terms)) {
        $primary_term_ids = wp_list_pluck($terms, 'term_id');
    }

    $cache_key = sprintf('gta6mods_similar_mods_%d_%d_%s', $post_id, $limit, empty($primary_term_ids) ? 'any' : implode('-', $primary_term_ids));
    $cached    = get_transient($cache_key);

    if (false !== $cached) {
        return is_array($cached) ? $cached : [];
    }

    $query_args = [
        'post_type'              => gta6mods_get_mod_post_types(),
        'post_status'            => 'publish',
        'post__not_in'           => [$post_id],
        'posts_per_page'         => $limit,
        'ignore_sticky_posts'    => true,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'lazy_load_term_meta'    => false,
        'cache_results'          => true,
    ];

    if (!empty($primary_term_ids)) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $primary_term_ids,
            ],
        ];
    }

    $query = new WP_Query($query_args);

    $cards = [];

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $cards[] = gta6_mods_prepare_mod_card($post instanceof WP_Post ? $post->ID : (int) $post);
        }
    }

    wp_reset_postdata();

    $cards = array_values(array_filter($cards));

set_transient($cache_key, $cards, HOUR_IN_SECONDS);

return $cards;
}

/**
 * Ensures archive mod cards include consistent image metadata.
 *
 * @param array<int, array<string, mixed>> $mods Mod cards.
 * @return array<int, array<string, mixed>>
 */
function gta6_mods_ensure_archive_mod_image_metadata(array $mods) {
    $normalized = array_map(
        static function ($mod) {
            if (!is_array($mod) && !is_object($mod)) {
                return null;
            }

            $mod = (array) $mod;

            $title    = isset($mod['title']) ? wp_strip_all_tags($mod['title']) : '';
            $category = isset($mod['category']) ? wp_strip_all_tags($mod['category']) : '';
            if ('' === $category) {
                $category = __('mod', 'gta6-mods');
            }

            $category_lower = function_exists('mb_strtolower')
                ? mb_strtolower($category, 'UTF-8')
                : strtolower($category);

            if (empty($mod['imageAlt'])) {
                $mod['imageAlt'] = sprintf(
                    __('%1$s - GTA 6 %2$s mod thumbnail', 'gta6-mods'),
                    $title,
                    $category_lower
                );
            }

            if (empty($mod['imageWidth'])) {
                $mod['imageWidth'] = 1280;
            }

            if (empty($mod['imageHeight'])) {
                $mod['imageHeight'] = 720;
            }

            return $mod;
        },
        $mods
    );

    return array_values(
        array_filter(
            $normalized,
            static function ($mod) {
                return is_array($mod);
            }
        )
    );
}

/**
 * Collects post data for archive pages.
 */
function gta6_mods_collect_archive_posts($query = null) {
    if (!$query instanceof WP_Query) {
        global $wp_query;
        $query = $wp_query;
    }

    $mods = [];

    if (!$query instanceof WP_Query || !$query->have_posts()) {
        return $mods;
    }

    $query_signature = [
        'hash'  => md5(wp_json_encode([
            'vars'  => $query->query_vars,
            'paged' => max(1, (int) $query->get('paged')),
            'lang'  => get_locale(),
        ])),
        'group' => 'gta6mods_archive_lists',
    ];

    $cached = get_transient($query_signature['group'] . '_' . $query_signature['hash']);

    if (false !== $cached && is_array($cached)) {
        return $cached;
    }

    $fallback_tags = ['Add-On', 'Vice City', 'Tuning', 'FiveM', 'Lore Friendly', 'Replace'];

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        $categories = get_the_category($post_id);
        $primary_category = !empty($categories) ? $categories[0] : null;
        $author_id = (int) get_post_field('post_author', $post_id);
        $author_name = gta6_mods_get_denormalized_author_name($post_id);
        $author_url  = $author_id > 0 ? get_author_posts_url($author_id) : '';
        $category_data = gta6_mods_get_primary_category_data($post_id, $categories);
        $category_name = '' !== $category_data['name']
            ? $category_data['name']
            : ($primary_category instanceof WP_Term ? wp_strip_all_tags($primary_category->name) : __('Modok', 'gta6-mods'));
        $category_url = '';

        if ($category_data['term_id'] > 0) {
            $term_link = get_term_link($category_data['term_id'], 'category');
            if (!is_wp_error($term_link)) {
                $category_url = $term_link;
            }
        } elseif ('' !== $category_data['slug']) {
            $term_link = get_term_link($category_data['slug'], 'category');
            if (!is_wp_error($term_link)) {
                $category_url = $term_link;
            }
        }

        if ('' === $category_url && $primary_category instanceof WP_Term) {
            $term_link = get_category_link($primary_category);
            if (!is_wp_error($term_link)) {
                $category_url = $term_link;
            }
        }

        $tag_names = [];

        if (!empty($categories)) {
            foreach ($categories as $category) {
                $tag_names[] = $category->name;
            }
        }

        $post_tags = get_the_tags($post_id);
        if (!empty($post_tags)) {
            foreach ($post_tags as $tag) {
                $tag_names[] = $tag->name;
            }
        }

        if (empty($tag_names)) {
            shuffle($fallback_tags);
            $tag_names = array_slice($fallback_tags, 0, 2);
        }

        $metrics = gta6_mods_get_mod_metrics($post_id);

        $category_label_for_alt = '' !== $category_name ? $category_name : __('mod', 'gta6-mods');
        $category_label_for_alt = function_exists('mb_strtolower')
            ? mb_strtolower($category_label_for_alt, 'UTF-8')
            : strtolower($category_label_for_alt);

        $mods[] = [
            'id'           => $post_id,
            'title'        => get_the_title(),
            'link'         => get_permalink(),
            'author'       => $author_name,
            'authorUrl'    => $author_url,
            'imageUrl'     => gta6_mods_get_image($post_id, 'large', 'card'),
            'imageAlt'     => sprintf(
                __('%1$s - GTA 6 %2$s mod thumbnail', 'gta6-mods'),
                wp_strip_all_tags(get_the_title()),
                $category_label_for_alt
            ),
            'imageWidth'   => 1280,
            'imageHeight'  => 720,
            'rating'       => isset($metrics['rating']) ? (float) $metrics['rating'] : 0.0,
            'ratingCount'  => isset($metrics['rating_count']) ? (int) $metrics['rating_count'] : 0,
            'likes'        => isset($metrics['likes']) ? (int) $metrics['likes'] : 0,
            'downloads'    => isset($metrics['downloads']) ? (int) $metrics['downloads'] : 0,
            'tags'         => array_values(array_unique($tag_names)),
            'isFeatured'   => gta6_mods_is_post_featured($post_id),
            'date'         => get_the_date(get_option('date_format')),
            'category'     => $category_name,
            'categoryUrl'  => $category_url,
        ];
    }

    $query->rewind_posts();
    wp_reset_postdata();

    $mods = gta6_mods_ensure_archive_mod_image_metadata($mods);

    set_transient(
        $query_signature['group'] . '_' . $query_signature['hash'],
        $mods,
        15 * MINUTE_IN_SECONDS
    );

    return $mods;
}

/**
 * Adjusts posts per page for archive queries.
 */
function gta6_mods_adjust_archive_posts_per_page($query) {
    if (!($query instanceof WP_Query) || is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->is_category() || $query->is_tag() || $query->is_author() || $query->is_search()) {
        $query->set('posts_per_page', 24);
    }
}
add_action('pre_get_posts', 'gta6_mods_adjust_archive_posts_per_page');

/**
 * Builds a model for pagination controls.
 */
function gta6_mods_build_pagination_model($query = null) {
    if (!$query instanceof WP_Query) {
        global $wp_query;
        $query = $wp_query;
    }

    if (!$query instanceof WP_Query || $query->max_num_pages <= 1) {
        return [];
    }

    $current = max(1, get_query_var('paged') ? (int) get_query_var('paged') : 1);
    $max = (int) $query->max_num_pages;

    $filter_context = null;
    if (
        function_exists('gta6mods_is_filterable_archive_query') &&
        function_exists('gta6mods_build_archive_filter_url') &&
        function_exists('gta6mods_get_archive_filter_state') &&
        function_exists('gta6mods_get_default_archive_sort') &&
        function_exists('gta6mods_get_default_archive_since') &&
        gta6mods_is_filterable_archive_query($query)
    ) {
        $state = gta6mods_get_archive_filter_state($query);
        if (is_array($state) && !empty($state)) {
            $filter_context = [
                'query'    => $query,
                'sort'     => isset($state['sort']) ? $state['sort'] : gta6mods_get_default_archive_sort(),
                'since'    => isset($state['since']) ? $state['since'] : gta6mods_get_default_archive_since(),
                'preserve' => isset($state['preserve']) && is_array($state['preserve']) ? $state['preserve'] : [],
            ];
        }
    }

    $page_url = function ($page) use ($filter_context) {
        if (!$filter_context) {
            return get_pagenum_link($page);
        }

        return gta6mods_build_archive_filter_url(
            $filter_context['query'],
            $filter_context['sort'],
            $filter_context['since'],
            $filter_context['preserve'],
            $page
        );
    };

    $model = [
        'first' => [
            'label'    => '«',
            'url'      => $current > 1 ? $page_url(1) : '',
            'disabled' => $current <= 1,
        ],
        'previous' => [
            'label'    => '‹',
            'url'      => $current > 1 ? $page_url($current - 1) : '',
            'disabled' => $current <= 1,
        ],
        'pages' => [],
        'next' => [
            'label'    => '›',
            'url'      => $current < $max ? $page_url($current + 1) : '',
            'disabled' => $current >= $max,
        ],
        'last' => [
            'label'    => '»',
            'url'      => $current < $max ? $page_url($max) : '',
            'disabled' => $current >= $max,
        ],
    ];

    $pages = [];

    $add_page = function ($value) use (&$pages) {
        $pages[] = $value;
    };

    if ($max <= 7) {
        for ($i = 1; $i <= $max; $i++) {
            $add_page($i);
        }
    } else {
        $add_page(1);
        $add_page(2);

        $start = max(3, $current - 1);
        $end = min($max - 2, $current + 1);

        if ($start > 3) {
            $add_page('ellipsis');
        }

        for ($i = $start; $i <= $end; $i++) {
            $add_page($i);
        }

        if ($end < $max - 2) {
            $add_page('ellipsis');
        }

        $add_page($max - 1);
        $add_page($max);
    }

    $normalized_pages = [];
    $seen = [];

    foreach ($pages as $page) {
        $key = is_numeric($page) ? 'page-' . $page : 'ellipsis-' . count($seen);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $normalized_pages[] = $page;
    }

    foreach ($normalized_pages as $page) {
        if ($page === 'ellipsis') {
            $model['pages'][] = ['type' => 'ellipsis'];
            continue;
        }

        $page_number = (int) $page;
        $model['pages'][] = [
            'type'    => 'page',
            'number'  => $page_number,
            'url'     => $page_url($page_number),
            'current' => $page_number === $current,
        ];
    }

    return $model;
}

final class GTA6_Mods_Post_Meta_Prefetcher {
    /**
     * Initializes the filter hook registrations.
     */
    public static function init() {
        add_filter('the_posts', [__CLASS__, 'prime_meta_cache'], 10, 2);
    }

    /**
     * Ensures that required post meta values are loaded in a single query.
     *
     * @param WP_Post[]|array $posts  The posts for the query.
     * @param WP_Query        $query  The query object.
     *
     * @return array
     */
    public static function prime_meta_cache($posts, $query) {
        if (empty($posts) || !is_array($posts)) {
            return $posts;
        }

        if ($query instanceof WP_Query && false === $query->get('cache_results')) {
            return $posts;
        }

        $post_ids = [];

        foreach ($posts as $post) {
            if ($post instanceof WP_Post) {
                $post_ids[$post->ID] = (int) $post->ID;
            }
        }

        if (empty($post_ids)) {
            return $posts;
        }

        $post_ids = array_values($post_ids);

        update_postmeta_cache($post_ids);

        if (function_exists('gta6mods_prime_mod_stats')) {
            gta6mods_prime_mod_stats($post_ids);
        }

        return $posts;
    }
}
GTA6_Mods_Post_Meta_Prefetcher::init();

final class GTA6_Mods_Post_Denormalizer {
    /**
     * Registers the save_post hook.
     */
    public static function init() {
        add_action('save_post', [__CLASS__, 'store_denormalized_fields'], 20, 3);
    }

    /**
     * Stores frequently accessed post data as denormalized meta values.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public static function store_denormalized_fields($post_id, $post, $update) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!$post instanceof WP_Post) {
            $post = get_post($post_id);
        }

        if (!$post instanceof WP_Post) {
            return;
        }

        if (!in_array($post->post_type, gta6mods_get_mod_post_types(), true)) {
            return;
        }

        $author_id = (int) $post->post_author;
        $author_display_name = '';

        if ($author_id > 0) {
            $raw_name = get_the_author_meta('display_name', $author_id);
            if (is_string($raw_name)) {
                $author_display_name = wp_strip_all_tags($raw_name);
            }
        }

        if ('' === $author_display_name) {
            delete_post_meta($post_id, GTA6_MODS_AUTHOR_DISPLAY_NAME_META_KEY);
        } else {
            update_post_meta($post_id, GTA6_MODS_AUTHOR_DISPLAY_NAME_META_KEY, $author_display_name);
        }

        $categories = get_the_category($post_id);
        $primary_category = null;

        if (is_array($categories) && !empty($categories)) {
            $primary_category = $categories[0];
        }

        if (!$primary_category instanceof WP_Term) {
            $default_category_id = (int) get_option('default_category');
            if ($default_category_id > 0) {
                $default_term = get_term($default_category_id, 'category');
                if ($default_term instanceof WP_Term && !is_wp_error($default_term)) {
                    $primary_category = $default_term;
                }
            }
        }

        if ($primary_category instanceof WP_Term) {
            update_post_meta(
                $post_id,
                GTA6_MODS_PRIMARY_CATEGORY_NAME_META_KEY,
                wp_strip_all_tags($primary_category->name)
            );
            update_post_meta(
                $post_id,
                GTA6_MODS_PRIMARY_CATEGORY_SLUG_META_KEY,
                sanitize_title($primary_category->slug)
            );
            update_post_meta(
                $post_id,
                GTA6_MODS_PRIMARY_CATEGORY_ID_META_KEY,
                (int) $primary_category->term_id
            );
        } else {
            delete_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_NAME_META_KEY);
            delete_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_SLUG_META_KEY);
            delete_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_ID_META_KEY);
        }
    }
}
GTA6_Mods_Post_Denormalizer::init();
