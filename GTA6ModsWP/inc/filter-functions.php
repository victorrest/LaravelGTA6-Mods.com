<?php
/**
 * High-performance archive filtering utilities.
 *
 * Provides URL routing, query manipulation and a denormalised index table so
 * archive pages (categories, tags and search) can be filtered and sorted with
 * minimal database overhead even under heavy traffic.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

function gta6mods_get_filter_index_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'gta_mod_filter_index';
}

function gta6mods_get_filter_terms_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'gta_mod_filter_terms';
}

function gta6mods_get_filter_index_schema_version() {
    return '1.3.0';
}

function gta6mods_install_filter_index_table() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table           = gta6mods_get_filter_index_table_name();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $terms_table = gta6mods_get_filter_terms_table_name();

    $sql = "CREATE TABLE {$table} (
        post_id bigint(20) unsigned NOT NULL,
        post_type varchar(20) NOT NULL,
        post_status varchar(20) NOT NULL,
        post_format varchar(20) NOT NULL DEFAULT 'standard',
        is_featured tinyint(1) unsigned NOT NULL DEFAULT 0,
        featured_timestamp bigint(20) unsigned NOT NULL DEFAULT 0,
        published_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        downloads int unsigned NOT NULL DEFAULT 0,
        likes int unsigned NOT NULL DEFAULT 0,
        rating_average decimal(3,2) unsigned NOT NULL DEFAULT 0.00,
        rating_count int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (post_id),
        KEY status_type (post_status, post_type),
        KEY published_at (published_at),
        KEY updated_at (updated_at),
        KEY downloads (downloads),
        KEY likes (likes),
        KEY rating_average (rating_average),
        KEY idx_featured_sort (is_featured, featured_timestamp),
        KEY post_format (post_format)
    ) {$charset_collate};";

    dbDelta($sql);

    $sql_terms = "CREATE TABLE {$terms_table} (
        post_id bigint(20) unsigned NOT NULL,
        taxonomy varchar(32) NOT NULL,
        term_id bigint(20) unsigned NOT NULL,
        term_slug varchar(200) NOT NULL,
        PRIMARY KEY  (post_id, taxonomy, term_id),
        KEY taxonomy_slug (taxonomy, term_slug(32)),
        KEY term_id (term_id)
    ) {$charset_collate};";

    dbDelta($sql_terms);

    // Ensure legacy rows have sensible defaults in the new columns.
    $wpdb->query($wpdb->prepare("UPDATE {$table} SET post_format = %s WHERE post_format IS NULL OR post_format = ''", 'standard'));

    $non_standard_ids = $wpdb->get_col(
        "SELECT DISTINCT tr.object_id
        FROM {$wpdb->term_relationships} tr
        INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
        WHERE tt.taxonomy = 'post_format'"
    );

    if (!empty($non_standard_ids)) {
        $non_standard_ids = array_values(array_map('absint', $non_standard_ids));

        $placeholders = implode(',', array_fill(0, count($non_standard_ids), '%d'));

        $delete_index_sql = "DELETE FROM {$table} WHERE post_id IN ({$placeholders})";
        $index_params     = array_merge([$delete_index_sql], $non_standard_ids);
        $prepared_index   = call_user_func_array([$wpdb, 'prepare'], $index_params);

        if ($prepared_index) {
            $wpdb->query($prepared_index);
        }

        $delete_terms_sql = "DELETE FROM {$terms_table} WHERE post_id IN ({$placeholders})";
        $terms_params     = array_merge([$delete_terms_sql], $non_standard_ids);
        $prepared_terms   = call_user_func_array([$wpdb, 'prepare'], $terms_params);

        if ($prepared_terms) {
            $wpdb->query($prepared_terms);
        }
    }

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table} gfi
        LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = gfi.post_id AND pm.meta_key = %s
        LEFT JOIN {$wpdb->postmeta} ts ON ts.post_id = gfi.post_id AND ts.meta_key = %s
        SET gfi.is_featured = CASE WHEN CAST(pm.meta_value AS UNSIGNED) > 0 THEN 1 ELSE 0 END,
            gfi.featured_timestamp = CASE WHEN CAST(pm.meta_value AS UNSIGNED) > 0 THEN COALESCE(CAST(ts.meta_value AS UNSIGNED), 0) ELSE 0 END",
            GTA6_MODS_FEATURED_META_KEY,
            GTA6_MODS_FEATURED_TIMESTAMP_META_KEY
        )
    );

    update_option('gta6mods_filter_index_version', gta6mods_get_filter_index_schema_version());

    gta6mods_schedule_filter_index_prime();
}

add_action('after_switch_theme', 'gta6mods_install_filter_index_table');
add_action('init', 'gta6mods_maybe_install_filter_index_table', 5);

function gta6mods_maybe_install_filter_index_table() {
    $installed = get_option('gta6mods_filter_index_version');

    if (false === $installed || version_compare($installed, gta6mods_get_filter_index_schema_version(), '<')) {
        gta6mods_install_filter_index_table();
        flush_rewrite_rules(false);
    }
}

function gta6mods_schedule_filter_index_prime() {
    if (!get_option('gta6mods_filter_index_prime_pending')) {
        update_option('gta6mods_filter_index_prime_pending', 1);
        delete_option('gta6mods_filter_index_prime_last_id');
    }
}

function gta6mods_maybe_prime_filter_index() {
    if (!get_option('gta6mods_filter_index_prime_pending')) {
        return;
    }

    if (!is_admin() && !wp_doing_cron()) {
        return;
    }

    $batch_size = (int) apply_filters('gta6mods_filter_index_prime_batch_size', 50);
    $batch_size = max(1, $batch_size);

    global $wpdb;

    $post_types = gta6mods_get_mod_post_types();
    if (empty($post_types)) {
        delete_option('gta6mods_filter_index_prime_pending');
        delete_option('gta6mods_filter_index_prime_last_id');
        return;
    }

    $last_id      = (int) get_option('gta6mods_filter_index_prime_last_id', 0);
    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
    $sql          = "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ({$placeholders}) AND post_status != 'auto-draft' ORDER BY ID ASC LIMIT %d";
    $params       = array_merge([$last_id], $post_types, [$batch_size]);

    $prepared = call_user_func_array([
        $wpdb,
        'prepare',
    ], array_merge([$sql], $params));

    if (false === $prepared) {
        return;
    }

    $ids = $wpdb->get_col($prepared);

    if (empty($ids)) {
        delete_option('gta6mods_filter_index_prime_pending');
        delete_option('gta6mods_filter_index_prime_last_id');
        return;
    }

    foreach ($ids as $post_id) {
        gta6mods_sync_filter_index_for_post((int) $post_id);
        $last_id = max($last_id, (int) $post_id);
    }

    update_option('gta6mods_filter_index_prime_last_id', $last_id);
}
add_action('init', 'gta6mods_maybe_prime_filter_index', 20);

function gta6mods_remove_filter_index_for_post($post_id) {
    global $wpdb;

    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    $table = gta6mods_get_filter_index_table_name();
    $wpdb->delete($table, ['post_id' => $post_id], ['%d']);

    $terms_table = gta6mods_get_filter_terms_table_name();
    $wpdb->delete($terms_table, ['post_id' => $post_id], ['%d']);
}

function gta6mods_normalize_gmt_datetime($primary, $secondary) {
    $primary   = is_string($primary) ? trim($primary) : '';
    $secondary = is_string($secondary) ? trim($secondary) : '';

    if ('' !== $primary && '0000-00-00 00:00:00' !== $primary) {
        return get_gmt_from_date($primary, 'Y-m-d H:i:s');
    }

    if ('' !== $secondary && '0000-00-00 00:00:00' !== $secondary) {
        return get_gmt_from_date($secondary, 'Y-m-d H:i:s');
    }

    return gmdate('Y-m-d H:i:s');
}

function gta6mods_sync_filter_index_for_post($post_id, array $stat_overrides = []) {
    global $wpdb;

    $post_id = absint($post_id);

    if ($post_id <= 0) {
        return false;
    }

    $post = get_post($post_id);

    if (!$post instanceof WP_Post) {
        gta6mods_remove_filter_index_for_post($post_id);
        return false;
    }

    if (!in_array($post->post_type, gta6mods_get_mod_post_types(), true) || 'publish' !== $post->post_status) {
        gta6mods_remove_filter_index_for_post($post_id);
        return false;
    }

    $post_format = get_post_format($post_id);
    if (!is_string($post_format) || '' === $post_format) {
        $post_format = 'standard';
    }

    $post_format = sanitize_key($post_format);

    if ('' === $post_format) {
        $post_format = 'standard';
    }

    if ('standard' !== $post_format) {
        gta6mods_remove_filter_index_for_post($post_id);
        return false;
    }

    $published_at = gta6mods_normalize_gmt_datetime($post->post_date_gmt, $post->post_date);
    $updated_at   = gta6mods_normalize_gmt_datetime($post->post_modified_gmt, $post->post_modified);

    $stats = [];

    if (!empty($stat_overrides)) {
        $stats = $stat_overrides;
    } elseif (function_exists('gta6mods_get_mod_stats')) {
        $stats = gta6mods_get_mod_stats($post_id);
    }

    $downloads          = isset($stats['downloads']) ? max(0, (int) $stats['downloads']) : 0;
    $likes              = isset($stats['likes']) ? max(0, (int) $stats['likes']) : 0;
    $rating_average     = isset($stats['rating_average']) ? max(0.0, (float) $stats['rating_average']) : 0.0;
    $rating_count       = isset($stats['rating_count']) ? max(0, (int) $stats['rating_count']) : 0;
    $is_featured        = get_post_meta($post_id, GTA6_MODS_FEATURED_META_KEY, true) ? 1 : 0;
    $featured_timestamp = $is_featured ? (int) gta6mods_get_featured_timestamp($post_id) : 0;

    $table = gta6mods_get_filter_index_table_name();

    $result = $wpdb->replace(
        $table,
        [
            'post_id'        => $post_id,
            'post_type'      => sanitize_key($post->post_type),
            'post_status'    => sanitize_key($post->post_status),
            'post_format'        => $post_format,
            'is_featured'        => $is_featured,
            'featured_timestamp' => $featured_timestamp,
            'published_at'       => $published_at,
            'updated_at'         => $updated_at,
            'downloads'          => $downloads,
            'likes'              => $likes,
            'rating_average'     => (float) number_format($rating_average, 2, '.', ''),
            'rating_count'       => $rating_count,
        ],
        ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%f', '%d']
    );

    $success = false !== $result;

    $terms_table = gta6mods_get_filter_terms_table_name();
    $wpdb->delete($terms_table, ['post_id' => $post_id], ['%d']);

    $taxonomies_to_sync = apply_filters('gta6mods_filter_term_taxonomies', ['category', 'post_tag']);

    foreach ($taxonomies_to_sync as $taxonomy) {
        $taxonomy = sanitize_key($taxonomy);

        if ('' === $taxonomy) {
            continue;
        }

        $terms = get_the_terms($post_id, $taxonomy);

        if (empty($terms) || is_wp_error($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $wpdb->insert(
                $terms_table,
                [
                    'post_id'  => $post_id,
                    'taxonomy' => $taxonomy,
                    'term_id'  => (int) $term->term_id,
                    'term_slug' => sanitize_title($term->slug),
                ],
                ['%d', '%s', '%d', '%s']
            );
        }
    }

    return $success;
}

function gta6mods_update_filter_index_stats($post_id, array $stat_values) {
    global $wpdb;

    $post_id = absint($post_id);
    if ($post_id <= 0 || empty($stat_values)) {
        return;
    }

    $allowed_columns = [
        'downloads'      => '%d',
        'likes'          => '%d',
        'rating_average' => '%f',
        'rating_count'   => '%d',
    ];

    $data   = [];
    $format = [];

    foreach ($stat_values as $column => $value) {
        if (!isset($allowed_columns[$column])) {
            continue;
        }

        if ('rating_average' === $column) {
            $data[$column] = (float) number_format((float) $value, 2, '.', '');
        } else {
            $data[$column] = max(0, (int) $value);
        }

        $format[] = $allowed_columns[$column];
    }

    if (empty($data)) {
        return;
    }

    $table   = gta6mods_get_filter_index_table_name();
    $updated = $wpdb->update($table, $data, ['post_id' => $post_id], $format, ['%d']);

    if (false === $updated) {
        return;
    }

    if (0 === $updated) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$table} WHERE post_id = %d", $post_id));

        if (null === $exists) {
            gta6mods_sync_filter_index_for_post($post_id, $stat_values);
        }
    }
}

function gta6mods_handle_filter_index_post_save($post_id, $post) {
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

    if ('publish' !== $post->post_status) {
        gta6mods_remove_filter_index_for_post($post_id);
        return;
    }

    gta6mods_sync_filter_index_for_post($post_id);
}
add_action('save_post', 'gta6mods_handle_filter_index_post_save', 50, 2);

function gta6mods_handle_filter_index_status_transition($new_status, $old_status, $post) {
    if (!$post instanceof WP_Post) {
        return;
    }

    if (!in_array($post->post_type, gta6mods_get_mod_post_types(), true)) {
        return;
    }

    if ('publish' === $new_status) {
        gta6mods_sync_filter_index_for_post($post->ID);
    } elseif ('publish' === $old_status) {
        gta6mods_remove_filter_index_for_post($post->ID);
    }
}
add_action('transition_post_status', 'gta6mods_handle_filter_index_status_transition', 10, 3);
add_action('before_delete_post', 'gta6mods_remove_filter_index_for_post');

function gta6mods_sync_featured_timestamp_meta($meta_id, $post_id, $meta_key, $meta_value) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
    unset($meta_id);

    if (GTA6_MODS_FEATURED_META_KEY !== $meta_key) {
        return;
    }

    $post_id = absint($post_id);

    if ($post_id <= 0) {
        return;
    }

    if ((int) $meta_value > 0) {
        gta6mods_set_featured_timestamp($post_id);
    } else {
        gta6mods_clear_featured_timestamp($post_id);
    }

    delete_transient('gta6_front_page_data_v1');
}
add_action('added_post_meta', 'gta6mods_sync_featured_timestamp_meta', 5, 4);
add_action('updated_post_meta', 'gta6mods_sync_featured_timestamp_meta', 5, 4);

function gta6mods_handle_deleted_featured_flag($meta_ids, $post_id, $meta_key, $meta_value) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
    unset($meta_ids, $meta_value);

    if (GTA6_MODS_FEATURED_META_KEY !== $meta_key) {
        return;
    }

    $post_id = absint($post_id);

    if ($post_id <= 0) {
        return;
    }

    gta6mods_clear_featured_timestamp($post_id);
    delete_transient('gta6_front_page_data_v1');
}
add_action('deleted_post_meta', 'gta6mods_handle_deleted_featured_flag', 5, 4);

function gta6mods_handle_filter_index_featured_meta($meta_id, $post_id, $meta_key, $meta_value) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
    unset($meta_id, $meta_value);

    if (GTA6_MODS_FEATURED_META_KEY !== $meta_key) {
        return;
    }

    $post_id = absint($post_id);

    if ($post_id <= 0) {
        return;
    }

    $post = get_post($post_id);

    if (!$post instanceof WP_Post) {
        return;
    }

    if (!in_array($post->post_type, gta6mods_get_mod_post_types(), true)) {
        return;
    }

    if ('publish' !== $post->post_status) {
        gta6mods_remove_filter_index_for_post($post_id);
        return;
    }

    gta6mods_sync_filter_index_for_post($post_id);
}
add_action('added_post_meta', 'gta6mods_handle_filter_index_featured_meta', 10, 4);
add_action('updated_post_meta', 'gta6mods_handle_filter_index_featured_meta', 10, 4);
add_action('deleted_post_meta', 'gta6mods_handle_filter_index_featured_meta', 10, 4);

function gta6mods_handle_filter_index_object_terms($object_id, $terms, $tt_ids, $taxonomy) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
    $object_id = absint($object_id);

    if ($object_id <= 0) {
        return;
    }

    if (!in_array($taxonomy, ['category', 'post_tag', 'post_format'], true)) {
        return;
    }

    $post = get_post($object_id);

    if (!$post instanceof WP_Post || !in_array($post->post_type, gta6mods_get_mod_post_types(), true)) {
        return;
    }

    if ('publish' !== $post->post_status) {
        return;
    }

    gta6mods_sync_filter_index_for_post($object_id);
}
add_action('set_object_terms', 'gta6mods_handle_filter_index_object_terms', 20, 4);
function gta6mods_get_archive_sort_options() {
    global $wpdb;

    $posts_table = $wpdb->posts;

    return [
        'latest-uploads' => [
            'label'       => __('Latest uploads', 'gta6-mods'),
            'orderby'     => "COALESCE(gfi.published_at, {$posts_table}.post_date_gmt) DESC, {$posts_table}.ID DESC",
            'date_column' => 'post_date_gmt',
        ],
        'featured' => [
            'label'       => __('Featured', 'gta6-mods'),
            'orderby'     => "COALESCE(gfi.published_at, {$posts_table}.post_date_gmt) DESC, {$posts_table}.ID DESC",
            'date_column' => 'post_date_gmt',
            'where'       => $wpdb->prepare('gfi.is_featured = %d', 1),
        ],
        'latest-updates' => [
            'label'       => __('Latest updates', 'gta6-mods'),
            'orderby'     => "COALESCE(gfi.updated_at, {$posts_table}.post_modified_gmt) DESC, COALESCE(gfi.published_at, {$posts_table}.post_date_gmt) DESC, {$posts_table}.ID DESC",
            'date_column' => 'post_modified_gmt',
        ],
        'most-liked' => [
            'label'       => __('Most liked', 'gta6-mods'),
            'orderby'     => "COALESCE(gfi.likes, 0) DESC, COALESCE(gfi.published_at, {$posts_table}.post_date_gmt) DESC, {$posts_table}.ID DESC",
            'date_column' => 'post_date_gmt',
        ],
        'most-downloaded' => [
            'label'       => __('Most downloaded', 'gta6-mods'),
            'orderby'     => "COALESCE(gfi.downloads, 0) DESC, COALESCE(gfi.published_at, {$posts_table}.post_date_gmt) DESC, {$posts_table}.ID DESC",
            'date_column' => 'post_date_gmt',
        ],
        'highest-rated' => [
            'label'       => __('Highest rated', 'gta6-mods'),
            'orderby'     => "COALESCE(gfi.rating_average, 0) DESC, COALESCE(gfi.rating_count, 0) DESC, COALESCE(gfi.published_at, {$posts_table}.post_date_gmt) DESC, {$posts_table}.ID DESC",
            'date_column' => 'post_date_gmt',
        ],
    ];
}

function gta6mods_get_archive_since_options() {
    return [
        'today' => [
            'label' => __('Today', 'gta6-mods'),
        ],
        'yesterday' => [
            'label' => __('Yesterday', 'gta6-mods'),
        ],
        'week' => [
            'label' => __('Last week', 'gta6-mods'),
        ],
        'month' => [
            'label' => __('Last month', 'gta6-mods'),
        ],
        'year' => [
            'label' => __('Last year', 'gta6-mods'),
        ],
        'all' => [
            'label' => __('All time', 'gta6-mods'),
        ],
    ];
}

function gta6mods_normalize_tag_filter_value($value) {
    if (is_array($value)) {
        $value = implode('+', array_filter(array_map('strval', $value)));
    }

    $value = is_string($value) ? trim(wp_unslash($value)) : '';

    if ('' === $value) {
        return '';
    }

    $value = str_replace(',', '+', $value);
    $value = preg_replace('#\s+#', '+', $value);

    return trim($value, "+\t\r\n\0\x0B");
}

function gta6mods_parse_tag_filter($value) {
    $normalized = gta6mods_normalize_tag_filter_value($value);

    if ('' === $normalized) {
        return null;
    }

    $requested_slugs = array_filter(array_map('sanitize_title', explode('+', $normalized)));
    $requested_slugs = array_values(array_unique($requested_slugs));

    if (empty($requested_slugs)) {
        return null;
    }

    $slug_to_term      = [];
    $unresolved_slugs  = [];
    $hyphen_resolution = [];

    foreach ($requested_slugs as $slug) {
        $term = get_term_by('slug', $slug, 'post_tag');

        if ($term instanceof WP_Term && !is_wp_error($term)) {
            $slug_to_term[$slug] = $term;
            continue;
        }

        $unresolved_slugs[] = $slug;
    }

    if (!empty($unresolved_slugs)) {
        foreach ($unresolved_slugs as $slug) {
            if (false === strpos($slug, '-')) {
                continue;
            }

            $hyphen_parts = array_filter(array_map('sanitize_title', explode('-', $slug)));
            $hyphen_parts = array_values(array_unique($hyphen_parts));

            if (count($hyphen_parts) <= 1) {
                continue;
            }

            $part_terms = [];
            foreach ($hyphen_parts as $part_slug) {
                $part_term = get_term_by('slug', $part_slug, 'post_tag');

                if ($part_term instanceof WP_Term && !is_wp_error($part_term)) {
                    $part_terms[$part_slug] = $part_term;
                    continue;
                }

                $part_terms = [];
                break;
            }

            if (empty($part_terms)) {
                continue;
            }

            $hyphen_resolution[$slug] = $hyphen_parts;

            foreach ($hyphen_parts as $part_slug) {
                if (!isset($part_terms[$part_slug])) {
                    continue;
                }

                if (!isset($slug_to_term[$part_slug])) {
                    $slug_to_term[$part_slug] = $part_terms[$part_slug];
                }
            }
        }
    }

    $final_slugs = [];
    $seen_slugs = [];

    foreach ($requested_slugs as $slug) {
        if (isset($slug_to_term[$slug])) {
            if (!in_array($slug, $seen_slugs, true)) {
                $final_slugs[] = $slug;
                $seen_slugs[] = $slug;
            }

            continue;
        }

        if (!isset($hyphen_resolution[$slug])) {
            continue;
        }

        foreach ($hyphen_resolution[$slug] as $part_slug) {
            if (in_array($part_slug, $seen_slugs, true)) {
                continue;
            }

            $final_slugs[] = $part_slug;
            $seen_slugs[]  = $part_slug;
        }
    }

    $valid = !empty($final_slugs);

    if (!$valid) {
        return [
            'raw'    => implode('+', $requested_slugs),
            'slugs'  => $requested_slugs,
            'ids'    => [],
            'labels' => [],
            'terms'  => [],
            'valid'  => false,
        ];
    }

    $resolved_terms  = [];
    $resolved_ids    = [];
    $resolved_labels = [];

    foreach ($final_slugs as $slug) {
        if (!isset($slug_to_term[$slug])) {
            continue;
        }

        $term    = $slug_to_term[$slug];
        $term_id = (int) $term->term_id;

        if (in_array($term_id, $resolved_ids, true)) {
            continue;
        }

        $resolved_terms[]  = $term;
        $resolved_ids[]    = $term_id;
        $resolved_labels[] = $term->name;
    }

    if (empty($resolved_terms)) {
        return [
            'raw'    => implode('+', $requested_slugs),
            'slugs'  => $requested_slugs,
            'ids'    => [],
            'labels' => [],
            'terms'  => [],
            'valid'  => false,
        ];
    }

    return [
        'raw'    => implode('+', $final_slugs),
        'slugs'  => $final_slugs,
        'ids'    => $resolved_ids,
        'labels' => $resolved_labels,
        'terms'  => $resolved_terms,
        'valid'  => true,
    ];
}

function gta6mods_normalize_category_filter_value($value) {
    if (is_array($value)) {
        $value = implode('/', array_filter(array_map('strval', $value)));
    }

    $value = is_string($value) ? trim(wp_unslash($value)) : '';

    if ('' === $value) {
        return '';
    }

    $value = str_replace('\0', '', $value);
    $value = trim($value, "/\t\r\n\0\x0B ");
    $value = preg_replace('#/{2,}#', '/', $value);

    return $value;
}

function gta6mods_get_term_slug_path(WP_Term $term) {
    $slugs    = [];
    $taxonomy = $term->taxonomy;
    $current  = $term;

    while ($current instanceof WP_Term && !is_wp_error($current)) {
        $slugs[] = sanitize_title($current->slug);

        if ($current->parent <= 0) {
            break;
        }

        $current = get_term($current->parent, $taxonomy);
    }

    return implode('/', array_reverse($slugs));
}

function gta6mods_parse_category_filter($value) {
    $normalized = gta6mods_normalize_category_filter_value($value);

    if ('' === $normalized) {
        return null;
    }

    $term = get_category_by_path($normalized, false);

    if (!$term instanceof WP_Term || is_wp_error($term)) {
        $segments = explode('/', $normalized);
        $last     = end($segments);
        $term     = get_term_by('slug', sanitize_title($last), 'category');
    }

    if (!$term instanceof WP_Term || is_wp_error($term)) {
        $slugs = array_values(array_filter(array_map('sanitize_title', explode('/', $normalized))));

        return [
            'raw'   => implode('/', $slugs),
            'path'  => implode('/', $slugs),
            'id'    => 0,
            'slugs' => $slugs,
            'term'  => null,
            'valid' => false,
        ];
    }

    $path  = gta6mods_get_term_slug_path($term);
    $slugs = array_values(array_filter(array_map('sanitize_title', explode('/', $path))));

    return [
        'raw'   => $path,
        'path'  => $path,
        'id'    => (int) $term->term_id,
        'slugs' => $slugs,
        'term'  => $term,
        'valid' => true,
    ];
}

function gta6mods_get_category_filter_options() {
    $cache_key = 'gta6mods_category_filter_options';
    $cached    = wp_cache_get($cache_key, 'gta6mods');

    if (false !== $cached && is_array($cached)) {
        return $cached;
    }

    $terms = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    if (!is_array($terms) || is_wp_error($terms)) {
        return [];
    }

    $by_parent = [];

    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $by_parent[(int) $term->parent][] = $term;
    }

    foreach ($by_parent as $parent_id => $children) {
        usort(
            $by_parent[$parent_id],
            static function ($a, $b) {
                return strcasecmp($a->name, $b->name);
            }
        );
    }

    $options = [];

    $walker = static function ($parent_id, $depth, $path_prefix) use (&$walker, &$options, $by_parent) {
        if (empty($by_parent[$parent_id])) {
            return;
        }

        foreach ($by_parent[$parent_id] as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $slug      = sanitize_title($term->slug);
            $full_path = $path_prefix ? $path_prefix . '/' . $slug : $slug;
            $label     = str_repeat('— ', max(0, $depth)) . $term->name;

            $options[] = [
                'value' => $full_path,
                'label' => $label,
                'id'    => (int) $term->term_id,
            ];

            $walker((int) $term->term_id, $depth + 1, $full_path);
        }
    };

    $walker(0, 0, '');

    wp_cache_set($cache_key, $options, 'gta6mods', HOUR_IN_SECONDS);

    return $options;
}

function gta6mods_get_tag_filter_options_for_category($category_term_id) {
    global $wpdb;

    $category_term_id = (int) $category_term_id;

    if ($category_term_id <= 0) {
        return [];
    }

    $terms_table = gta6mods_get_filter_terms_table_name();
    $sql = $wpdb->prepare(
        "SELECT DISTINCT t.term_id, t.slug, t.name
        FROM {$terms_table} tag
        INNER JOIN {$terms_table} cat ON cat.post_id = tag.post_id AND cat.taxonomy = %s AND cat.term_id = %d
        INNER JOIN {$wpdb->terms} t ON t.term_id = tag.term_id
        WHERE tag.taxonomy = %s
        ORDER BY t.name ASC
        LIMIT 200",
        'category',
        $category_term_id,
        'post_tag'
    );

    $results = $wpdb->get_results($sql);

    if (!is_array($results)) {
        return [];
    }

    $options = [];

    foreach ($results as $row) {
        $slug = isset($row->slug) ? sanitize_title($row->slug) : '';

        if ('' === $slug) {
            continue;
        }

        $options[] = [
            'value' => $slug,
            'label' => isset($row->name) ? wp_strip_all_tags($row->name) : $slug,
            'id'    => isset($row->term_id) ? (int) $row->term_id : 0,
        ];
    }

    return $options;
}

function gta6mods_get_default_archive_sort() {
    return 'latest-uploads';
}

function gta6mods_get_default_archive_since() {
    return 'all';
}

function gta6mods_normalize_archive_sort($value) {
    $value   = sanitize_key($value);
    $options = gta6mods_get_archive_sort_options();

    if (isset($options[$value])) {
        return $value;
    }

    return gta6mods_get_default_archive_sort();
}

function gta6mods_normalize_archive_since($value) {
    $value   = sanitize_key($value);
    $options = gta6mods_get_archive_since_options();

    if (isset($options[$value])) {
        return $value;
    }

    return gta6mods_get_default_archive_since();
}

function gta6mods_get_archive_since_date_query($since, $column) {
    $since = gta6mods_normalize_archive_since($since);

    if ('all' === $since) {
        return null;
    }

    $now = current_datetime();

    if (!($now instanceof DateTimeImmutable)) {
        $now = new DateTimeImmutable('now', wp_timezone());
    }

    $start_of_today = $now->setTime(0, 0, 0);

    switch ($since) {
        case 'today':
            $cutoff = $start_of_today;
            break;
        case 'yesterday':
            $cutoff = $start_of_today->modify('-1 day');
            break;
        case 'week':
            $cutoff = $start_of_today->modify('-1 week');
            break;
        case 'month':
            $cutoff = $start_of_today->modify('-1 month');
            break;
        case 'year':
            $cutoff = $start_of_today->modify('-1 year');
            break;
        default:
            return null;
    }

    $cutoff = $cutoff->setTimezone(new DateTimeZone('UTC'));

    return [
        'after'     => $cutoff->format('Y-m-d H:i:s'),
        'column'    => $column,
        'inclusive' => true,
    ];
}

/**
 * Returns the compiled regex fragments for archive filter rewrite rules.
 *
 * The calculation is cached in a static variable so we only pay the cost
 * once per request. This information is reused both for the traditional
 * `/category/` base rules and the custom base-less variants generated per
 * category to keep performance optimal on high traffic archives.
 *
 * @return array{sort_pattern:string,since_pattern:string}|null
 */
function gta6mods_get_archive_filter_regex_components() {
    static $components = null;

    if (null !== $components) {
        return $components;
    }

    $sorts  = array_keys(gta6mods_get_archive_sort_options());
    $sinces = array_keys(gta6mods_get_archive_since_options());

    if (empty($sorts) || empty($sinces)) {
        $components = null;

        return $components;
    }

    $components = [
        'sort_pattern'  => implode('|', array_map('preg_quote', $sorts)),
        'since_pattern' => implode('|', array_map('preg_quote', $sinces)),
    ];

    return $components;
}

function gta6mods_register_archive_filter_rewrites() {
    global $wp_rewrite;

    $components = gta6mods_get_archive_filter_regex_components();

    if (null === $components) {
        return;
    }

    $sort_pattern  = $components['sort_pattern'];
    $since_pattern = $components['since_pattern'];

    add_rewrite_tag('%gta_sort%', '([^&]+)');
    add_rewrite_tag('%gta_since%', '([^&]+)');
    add_rewrite_tag('%gta_tag%', '([^&]+)');
    add_rewrite_tag('%gta_category%', '(.+?)');

    $category_base = trim(get_option('category_base')); 
    if ('' === $category_base) {
        $category_base = 'category';
    }
    $category_base = trim($category_base, '/');
    $category_pattern = preg_quote($category_base, '/');

    add_rewrite_rule(
        "{$category_pattern}/(.+?)(?:/({$sort_pattern}))?(?:/({$since_pattern}))?(?:/tag/([^/]+))?(?:/page/([0-9]{1,}))?/?$",
        'index.php?category_name=$matches[1]&gta_sort=$matches[2]&gta_since=$matches[3]&gta_tag=$matches[4]&paged=$matches[5]',
        'top'
    );

    $tag_base = isset($wp_rewrite->tag_base) ? $wp_rewrite->tag_base : '';
    if (empty($tag_base)) {
        $tag_base = 'tag';
    }
    $tag_base = trim($tag_base, '/');
    $tag_pattern = preg_quote($tag_base, '/');

    add_rewrite_rule(
        "{$tag_pattern}/([^/]+)(?:/({$sort_pattern}))?(?:/({$since_pattern}))?(?:/category/(.+?))?(?:/page/([0-9]{1,}))?/?$",
        'index.php?tag=$matches[1]&gta_sort=$matches[2]&gta_since=$matches[3]&gta_category=$matches[4]&paged=$matches[5]',
        'top'
    );

    $search_base = isset($wp_rewrite->search_base) ? $wp_rewrite->search_base : '';
    if (empty($search_base)) {
        $search_base = 'search';
    }
    $search_base = trim($search_base, '/');
    $search_pattern = preg_quote($search_base, '/');

    add_rewrite_rule(
        "{$search_pattern}/(.+?)(?:/({$sort_pattern}))?(?:/({$since_pattern}))?(?:/category/(.+?))?(?:/page/([0-9]{1,}))?/?$",
        'index.php?s=$matches[1]&gta_sort=$matches[2]&gta_since=$matches[3]&gta_category=$matches[4]&paged=$matches[5]',
        'top'
    );
}
add_action('init', 'gta6mods_register_archive_filter_rewrites', 1);

/**
 * Generates rewrite rules for a specific category path that include the
 * archive filter segments (sort, since, tag and pagination).
 *
 * These rules are consumed when the theme removes the legacy `/category/`
 * base so the filter URLs continue to resolve without triggering database
 * lookups for missing permalinks.
 *
 * @param string $category_path Hierarchical category slug path.
 * @return array<string, string>
 */
function gta6mods_get_category_filter_rewrite_rules_for_path($category_path) {
    $category_path = trim((string) $category_path, '/');

    if ('' === $category_path) {
        return [];
    }

    $components = gta6mods_get_archive_filter_regex_components();

    if (null === $components) {
        return [];
    }

    $sort_pattern  = $components['sort_pattern'];
    $since_pattern = $components['since_pattern'];
    $escaped_path  = preg_quote($category_path, '/');

    $rules = [];

    $rules[$escaped_path . '(?:/(' . $sort_pattern . '))?(?:/(' . $since_pattern . '))?(?:/tag/([^/]+))?(?:/page/([0-9]{1,}))?/?$']
        = 'index.php?category_name=' . $category_path . '&gta_sort=$matches[1]&gta_since=$matches[2]&gta_tag=$matches[3]&paged=$matches[4]';

    return $rules;
}

function gta6mods_register_archive_filter_query_vars($vars) {
    $vars[] = 'gta_sort';
    $vars[] = 'gta_since';
    $vars[] = 'gta_tag';
    $vars[] = 'gta_category';

    return $vars;
}
add_filter('query_vars', 'gta6mods_register_archive_filter_query_vars');

function gta6mods_is_filterable_archive_query($query) {
    return $query instanceof WP_Query && ($query->is_category() || $query->is_tag() || $query->is_search());
}

function gta6mods_apply_archive_filters($query) {
    if (!($query instanceof WP_Query) || is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!gta6mods_is_filterable_archive_query($query)) {
        return;
    }

    $raw_sort  = $query->get('gta_sort');
    $raw_since = $query->get('gta_since');

    if (empty($raw_sort)) {
        $raw_sort = filter_input(INPUT_GET, 'gta_sort', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($raw_sort)) {
            $raw_sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    if (empty($raw_since)) {
        $raw_since = filter_input(INPUT_GET, 'gta_since', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($raw_since)) {
            $raw_since = filter_input(INPUT_GET, 'since', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    $sort  = gta6mods_normalize_archive_sort($raw_sort);
    $since = gta6mods_normalize_archive_since($raw_since);

    $tag_filter       = null;
    $category_filter  = null;
    $force_empty_post = false;

    if ($query->is_category()) {
        $raw_tag = $query->get('gta_tag');

        if (empty($raw_tag)) {
            $raw_tag = filter_input(INPUT_GET, 'gta_tag', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (empty($raw_tag)) {
                $raw_tag = filter_input(INPUT_GET, 'tag', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
        }

        $tag_filter = gta6mods_parse_tag_filter($raw_tag);

        if (is_array($tag_filter)) {
            if (empty($tag_filter['valid']) && !empty($tag_filter['raw'])) {
                $force_empty_post = true;
            }
            $query->set('gta_tag', $tag_filter['raw']);
        } else {
            $query->set('gta_tag', '');
        }

        $query->set('gta_tag_filter', $tag_filter);
        $query->set('tag', '');
        $query->set('tag_id', '');
        $query->set('tag__in', []);
        $query->set('tag__and', []);
        $query->set('tag__not_in', []);
        $query->set('tag_slug__in', []);
        $query->set('tag_slug__and', []);

        if (is_array($tag_filter)) {
            $tax_query = $query->get('tax_query');
            if (is_array($tax_query)) {
                $relation = isset($tax_query['relation']) ? $tax_query['relation'] : null;
                $filtered = [];

                foreach ($tax_query as $key => $clause) {
                    if ('relation' === $key) {
                        continue;
                    }

                    if (!is_array($clause)) {
                        continue;
                    }

                    if (isset($clause['taxonomy']) && 'post_tag' === $clause['taxonomy']) {
                        continue;
                    }

                    $filtered[] = $clause;
                }

                if (!empty($relation) && !empty($filtered)) {
                    $filtered['relation'] = $relation;
                }

                $query->set('tax_query', $filtered);
            }
        }
    } else {
        $query->set('gta_tag_filter', null);
        $query->set('gta_tag', '');
    }

    if ($query->is_search() || $query->is_tag()) {
        $raw_category = $query->get('gta_category');

        if (empty($raw_category)) {
            $raw_category = filter_input(INPUT_GET, 'gta_category', FILTER_UNSAFE_RAW);
        }

        $category_filter = gta6mods_parse_category_filter($raw_category);

        if (is_array($category_filter)) {
            if (empty($category_filter['valid']) && !empty($category_filter['raw'])) {
                $force_empty_post = true;
            }
            $query->set('gta_category', $category_filter['path']);
        } else {
            $query->set('gta_category', '');
        }

        $query->set('gta_category_filter', $category_filter);
        $query->set('cat', '');
        $query->set('category__in', []);
        $query->set('category__and', []);
        $query->set('category__not_in', []);

        if (is_array($category_filter) && !empty($category_filter['path'])) {
            $tax_query = $query->get('tax_query');
            if (is_array($tax_query)) {
                $relation = isset($tax_query['relation']) ? $tax_query['relation'] : null;
                $filtered = [];

                foreach ($tax_query as $key => $clause) {
                    if ('relation' === $key) {
                        continue;
                    }

                    if (!is_array($clause)) {
                        continue;
                    }

                    if (isset($clause['taxonomy']) && 'category' === $clause['taxonomy']) {
                        continue;
                    }

                    $filtered[] = $clause;
                }

                if (!empty($relation) && !empty($filtered)) {
                    $filtered['relation'] = $relation;
                }

                $query->set('tax_query', $filtered);
            }
        }
    } else {
        $query->set('gta_category_filter', null);
        $query->set('gta_category', '');
    }

    if ($force_empty_post) {
        $query->set('post__in', [0]);
    }

    $query->set('gta_sort', $sort);
    $query->set('gta_since', $since);

    $sort_options = gta6mods_get_archive_sort_options();
    $sort_config  = isset($sort_options[$sort]) ? $sort_options[$sort] : $sort_options[gta6mods_get_default_archive_sort()];

    $date_query = gta6mods_get_archive_since_date_query($since, isset($sort_config['date_column']) ? $sort_config['date_column'] : 'post_date_gmt');

    if (null !== $date_query) {
        $query->set('date_query', [$date_query]);
    }

    if ($query->is_search()) {
        $query->set('post_type', gta6mods_get_mod_post_types());
    }

    $query->set('ignore_sticky_posts', true);
}
add_action('pre_get_posts', 'gta6mods_apply_archive_filters', 20);

function gta6mods_archive_posts_clauses($clauses, $query) {
    if (!($query instanceof WP_Query)) {
        return $clauses;
    }

    if (is_admin() || !$query->is_main_query()) {
        return $clauses;
    }

    if (!gta6mods_is_filterable_archive_query($query)) {
        return $clauses;
    }

    $sort = gta6mods_normalize_archive_sort($query->get('gta_sort'));
    $options = gta6mods_get_archive_sort_options();

    if (!isset($options[$sort])) {
        return $clauses;
    }

    global $wpdb;

    $table       = gta6mods_get_filter_index_table_name();
    $terms_table = gta6mods_get_filter_terms_table_name();

    if (false === strpos($clauses['join'], $table)) {
        $clauses['join'] .= " LEFT JOIN {$table} gfi ON gfi.post_id = {$wpdb->posts}.ID";
    }

    $tag_filter      = $query->get('gta_tag_filter');
    $category_filter = $query->get('gta_category_filter');

    $clauses['where'] .= $wpdb->prepare(' AND COALESCE(gfi.post_format, %s) = %s', 'standard', 'standard');

    if (!empty($options[$sort]['where'])) {
        $clauses['where'] .= ' AND ' . $options[$sort]['where'];
    }

    $needs_groupby = false;

    if (is_array($category_filter) && !empty($category_filter['id'])) {
        $category_id = (int) $category_filter['id'];

        if ($category_id > 0 && false === strpos($clauses['join'], ' gft_cat ')) {
            $clauses['join'] .= $wpdb->prepare(
                " INNER JOIN {$terms_table} gft_cat ON gft_cat.post_id = {$wpdb->posts}.ID AND gft_cat.taxonomy = %s AND gft_cat.term_id = %d",
                'category',
                $category_id
            );
        }

        if ($category_id > 0) {
            $needs_groupby = true;
        }
    }

    if (is_array($tag_filter) && !empty($tag_filter['ids'])) {
        $tag_ids = array_map('absint', $tag_filter['ids']);
        $tag_ids = array_filter($tag_ids);

        if (!empty($tag_ids)) {
            if (false === strpos($clauses['join'], ' gft_tag ')) {
                $clauses['join'] .= $wpdb->prepare(
                    " INNER JOIN {$terms_table} gft_tag ON gft_tag.post_id = {$wpdb->posts}.ID AND gft_tag.taxonomy = %s",
                    'post_tag'
                );
            }

            $placeholders = implode(',', array_fill(0, count($tag_ids), '%d'));
            $args         = array_merge([" AND gft_tag.term_id IN ({$placeholders})"], $tag_ids);
            $clauses['where'] .= call_user_func_array([$wpdb, 'prepare'], $args);

            if (count($tag_ids) > 1) {
                $needs_groupby = true;
                $having_sql    = $wpdb->prepare('COUNT(DISTINCT gft_tag.term_id) = %d', count($tag_ids));

                if (empty($clauses['having'])) {
                    $clauses['having'] = $having_sql;
                } else {
                    $clauses['having'] .= ' AND ' . $having_sql;
                }
            } else {
                $needs_groupby = true;
            }
        }
    }

    if ($needs_groupby) {
        $groupby_column = "{$wpdb->posts}.ID";

        if (empty($clauses['groupby'])) {
            $clauses['groupby'] = $groupby_column;
        } elseif (false === strpos($clauses['groupby'], $groupby_column)) {
            $clauses['groupby'] .= ', ' . $groupby_column;
        }
    }

    $orderby = $options[$sort]['orderby'];

    if (!empty($orderby)) {
        $clauses['orderby'] = $orderby;
    }

    return $clauses;
}
add_filter('posts_clauses', 'gta6mods_archive_posts_clauses', 20, 2);

function gta6mods_get_archive_filter_query_args_to_preserve() {
    $raw = isset($_GET) ? wp_unslash($_GET) : [];

    if (!is_array($raw)) {
        return [];
    }

    $preserved = [];

    foreach ($raw as $key => $value) {
        if (in_array($key, ['gta_sort', 'sort', 'gta_since', 'since', 'paged', 'gta_tag', 'tag', 'tag_id', 'gta_category', 'category', 'category_name', 'cat'], true)) {
            continue;
        }

        if (is_array($value)) {
            continue;
        }

        $sanitized_key = sanitize_key($key);

        if ('' === $sanitized_key) {
            continue;
        }

        $preserved[$sanitized_key] = sanitize_text_field($value);
    }

    return $preserved;
}

function gta6mods_get_archive_filter_base_url($query = null) {
    if (!$query instanceof WP_Query) {
        global $wp_query;
        $query = $wp_query;
    }

    if (!$query instanceof WP_Query) {
        return home_url('/');
    }

    if ($query->is_category() || $query->is_tag()) {
        $object = $query->get_queried_object();
        if ($object instanceof WP_Term) {
            $link = get_term_link($object);
            if (!is_wp_error($link) && !empty($link)) {
                return remove_query_arg(['gta_sort', 'gta_since', 'sort', 'since', 'paged', 'gta_tag', 'tag', 'tag_id', 'gta_category', 'category', 'category_name', 'cat'], $link);
            }
        }
    }

    if ($query->is_search()) {
        $link = get_search_link($query->get('s'));
        if (!is_wp_error($link) && !empty($link)) {
            return remove_query_arg(['gta_sort', 'gta_since', 'sort', 'since', 'paged', 'gta_tag', 'tag', 'tag_id', 'gta_category', 'category', 'category_name', 'cat'], $link);
        }
    }

    $link = get_pagenum_link(1);

    if (!empty($link)) {
        return remove_query_arg(['gta_sort', 'gta_since', 'sort', 'since', 'paged', 'gta_tag', 'tag', 'tag_id', 'gta_category', 'category', 'category_name', 'cat'], $link);
    }

    return home_url('/');
}

function gta6mods_should_use_pretty_archive_filters($base_url) {
    return is_string($base_url) && '' !== $base_url && false === strpos($base_url, '?');
}

function gta6mods_build_archive_filter_url($query = null, $sort = null, $since = null, array $preserve = [], $page = null) {
    if (!$query instanceof WP_Query) {
        global $wp_query;
        $query = $wp_query;
    }

    if (!$query instanceof WP_Query) {
        return home_url('/');
    }

    $sort  = gta6mods_normalize_archive_sort($sort);
    $since = gta6mods_normalize_archive_since($since);

    $defaults = [
        'sort'  => gta6mods_get_default_archive_sort(),
        'since' => gta6mods_get_default_archive_since(),
    ];

    $base_url  = gta6mods_get_archive_filter_base_url($query);
    $use_pretty = gta6mods_should_use_pretty_archive_filters($base_url);

    $sanitized_preserve = [];
    foreach ($preserve as $key => $value) {
        $key = sanitize_key($key);

        if ('' === $key || is_array($value)) {
            continue;
        }

        $sanitized_preserve[$key] = sanitize_text_field($value);
    }

    unset(
        $sanitized_preserve['gta_sort'],
        $sanitized_preserve['sort'],
        $sanitized_preserve['gta_since'],
        $sanitized_preserve['since'],
        $sanitized_preserve['paged'],
        $sanitized_preserve['gta_tag'],
        $sanitized_preserve['tag'],
        $sanitized_preserve['tag_id'],
        $sanitized_preserve['gta_category'],
        $sanitized_preserve['category'],
        $sanitized_preserve['category_name'],
        $sanitized_preserve['cat']
    );

    $page_number = null === $page ? null : max(1, (int) $page);

    $tag_filter      = $query->get('gta_tag_filter');
    $category_filter = $query->get('gta_category_filter');

    $tag_value = '';
    if (is_array($tag_filter) && isset($tag_filter['raw'])) {
        $tag_value = (string) $tag_filter['raw'];
    } else {
        $tag_value = (string) $query->get('gta_tag');
    }

    $category_value = '';
    if (is_array($category_filter) && isset($category_filter['path'])) {
        $category_value = (string) $category_filter['path'];
    } else {
        $category_value = (string) $query->get('gta_category');
    }

    if ($use_pretty) {
        $url = trailingslashit($base_url);
        $segments = [];

        if ($sort !== $defaults['sort']) {
            $segments[] = rawurlencode($sort);
        }

        if ($since !== $defaults['since']) {
            $segments[] = rawurlencode($since);
        }

        $filter_segments = [];

        if ($query->is_category() && '' !== $tag_value) {
            $tag_parts = array_filter(explode('+', $tag_value), 'strlen');
            $filter_segments[] = 'tag/' . implode('+', array_map('rawurlencode', $tag_parts));
        }

        if (($query->is_search() || $query->is_tag()) && '' !== $category_value) {
            $category_parts = array_filter(explode('/', $category_value), 'strlen');
            $filter_segments[] = 'category/' . implode('/', array_map('rawurlencode', $category_parts));
        }

        $all_segments = array_merge($segments, $filter_segments);

        if (!empty($all_segments)) {
            $url .= implode('/', $all_segments) . '/';
        }

        if (!empty($page_number) && $page_number > 1) {
            $url .= user_trailingslashit('page/' . $page_number);
        }

        $url = trailingslashit($url);

        if (!empty($sanitized_preserve)) {
            $url = add_query_arg($sanitized_preserve, $url);
        }

        return $url;
    }

    $url = remove_query_arg(['gta_sort', 'sort', 'gta_since', 'since', 'paged'], $base_url);

    if ($sort !== $defaults['sort']) {
        $sanitized_preserve['gta_sort'] = $sort;
    }

    if ($since !== $defaults['since']) {
        $sanitized_preserve['gta_since'] = $since;
    }

    if ($query->is_category() && '' !== $tag_value) {
        $sanitized_preserve['gta_tag'] = $tag_value;
    }

    if (($query->is_search() || $query->is_tag()) && '' !== $category_value) {
        $sanitized_preserve['gta_category'] = $category_value;
    }

    if (!empty($page_number) && $page_number > 1) {
        $sanitized_preserve['paged'] = $page_number;
    }

    if (!empty($sanitized_preserve)) {
        $url = add_query_arg($sanitized_preserve, $url);
    }

    return $url;
}

function gta6mods_get_archive_filter_state($query = null) {
    if (!$query instanceof WP_Query) {
        global $wp_query;
        $query = $wp_query;
    }

    if (!$query instanceof WP_Query) {
        return [];
    }

    $sort  = gta6mods_normalize_archive_sort($query->get('gta_sort'));
    $since = gta6mods_normalize_archive_since($query->get('gta_since'));

    $sort_options  = gta6mods_get_archive_sort_options();
    $since_options = gta6mods_get_archive_since_options();

    $preserve = gta6mods_get_archive_filter_query_args_to_preserve();
    $base_url = gta6mods_get_archive_filter_base_url($query);
    $form_action = gta6mods_build_archive_filter_url(
        $query,
        gta6mods_get_default_archive_sort(),
        gta6mods_get_default_archive_since(),
        $preserve
    );

    $format_options = function ($options) {
        $formatted = [];
        foreach ($options as $value => $data) {
            $formatted[$value] = [
                'value' => $value,
                'label' => isset($data['label']) ? $data['label'] : $value,
            ];
        }

        return $formatted;
    };

    $tag_filter      = $query->get('gta_tag_filter');
    $category_filter = $query->get('gta_category_filter');

    $tag_value = '';
    if (is_array($tag_filter) && isset($tag_filter['raw'])) {
        $tag_value = (string) $tag_filter['raw'];
    }

    $category_value = '';
    if (is_array($category_filter) && isset($category_filter['path'])) {
        $category_value = (string) $category_filter['path'];
    }

    $tag_options_list = [];
    if ($query->is_category()) {
        $term = $query->get_queried_object();
        if ($term instanceof WP_Term) {
            $tag_options_list = gta6mods_get_tag_filter_options_for_category($term->term_id);
        }
    }

    $category_options_list = [];
    if ($query->is_search() || $query->is_tag()) {
        $category_options_list = gta6mods_get_category_filter_options();
    }

    $format_filter_options = static function ($options, $default_label) {
        $formatted = [
            '' => [
                'value' => '',
                'label' => $default_label,
            ],
        ];

        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $value = isset($option['value']) ? (string) $option['value'] : '';

            if ('' === $value) {
                continue;
            }

            $formatted[$value] = [
                'value' => $value,
                'label' => isset($option['label']) ? $option['label'] : $value,
            ];
        }

        return $formatted;
    };

    $tag_options = $format_filter_options($tag_options_list, __('All tags', 'gta6-mods'));
    $category_options = $format_filter_options($category_options_list, __('All categories', 'gta6-mods'));

    $ensure_option_present = static function (&$options, $value, $label) {
        if ('' === $value || isset($options[$value])) {
            return;
        }

        $options[$value] = [
            'value' => $value,
            'label' => $label,
        ];
    };

    if ('' !== $tag_value) {
        $tag_label = $tag_value;
        if (is_array($tag_filter) && !empty($tag_filter['labels'])) {
            $tag_label = implode(', ', array_map('wp_strip_all_tags', (array) $tag_filter['labels']));
        }
        $ensure_option_present($tag_options, $tag_value, $tag_label);
    }

    if ('' !== $category_value) {
        $category_label = $category_value;
        if (is_array($category_filter) && isset($category_filter['term']) && $category_filter['term'] instanceof WP_Term) {
            $category_label = $category_filter['term']->name;
        }
        $ensure_option_present($category_options, $category_value, $category_label);
    }

    return [
        'sort'         => $sort,
        'since'        => $since,
        'sortOptions'  => $format_options($sort_options),
        'sinceOptions' => $format_options($since_options),
        'preserve'     => $preserve,
        'baseUrl'      => $base_url,
        'queryBase'    => $form_action,
        'formAction'   => $form_action,
        'pretty'       => gta6mods_should_use_pretty_archive_filters($base_url),
        'defaultSort'  => gta6mods_get_default_archive_sort(),
        'defaultSince' => gta6mods_get_default_archive_since(),
        'currentUrl'   => gta6mods_build_archive_filter_url($query, $sort, $since, $preserve),
        'tagFilter'    => [
            'value'   => $tag_value,
            'default' => '',
            'options' => $tag_options,
            'segment' => $query->is_category() ? 'tag' : '',
            'param'   => 'gta_tag',
        ],
        'categoryFilter' => [
            'value'   => $category_value,
            'default' => '',
            'options' => $category_options,
            'segment' => ($query->is_search() || $query->is_tag()) ? 'category' : '',
            'param'   => 'gta_category',
        ],
    ];
}
