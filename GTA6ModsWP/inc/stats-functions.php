<?php
/**
 * High-throughput statistics helpers.
 *
 * Provides read/write helpers for the gta_mod_stats table so that frequently
 * updated counters can avoid the wp_postmeta table and remain performant under
 * heavy load.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gta6mods_get_mod_stats_table_name')) {
    /**
     * Returns the fully-qualified stats table name.
     *
     * @return string
     */
    function gta6mods_get_mod_stats_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'gta_mod_stats';
    }
}

if (!function_exists('gta6mods_get_mod_stat_columns')) {
    /**
     * Returns the supported stat columns and their data types.
     *
     * @return array<string, array<string, mixed>>
     */
    function gta6mods_get_mod_stat_columns() {
        return [
            'downloads'      => [
                'type'   => 'int',
                'format' => '%d',
                'default' => 0,
            ],
            'likes'          => [
                'type'   => 'int',
                'format' => '%d',
                'default' => 0,
            ],
            'views'          => [
                'type'   => 'int',
                'format' => '%d',
                'default' => 0,
            ],
            'rating_average' => [
                'type'   => 'float',
                'format' => '%f',
                'default' => 0.0,
            ],
            'rating_count'   => [
                'type'   => 'int',
                'format' => '%d',
                'default' => 0,
            ],
        ];
    }
}

if (!function_exists('gta6mods_get_mod_stat_meta_map')) {
    /**
     * Returns the mapping of stat columns to legacy post meta keys.
     *
     * @return array<string, string>
     */
    function gta6mods_get_mod_stat_meta_map() {
        return [
            'downloads'      => '_gta6mods_download_count',
            'likes'          => '_gta6mods_likes',
            'views'          => '_gta6mods_view_count',
            'rating_average' => '_gta6mods_rating_average',
            'rating_count'   => '_gta6mods_rating_count',
        ];
    }
}

if (!function_exists('gta6mods_get_mod_stats_cache_ttl')) {
    /**
     * Returns the cache lifetime for statistics lookups.
     *
     * @return int
     */
    function gta6mods_get_mod_stats_cache_ttl() {
        $ttl = (int) apply_filters('gta6mods_mod_stats_cache_ttl', HOUR_IN_SECONDS);

        if ($ttl < 0) {
            $ttl = HOUR_IN_SECONDS;
        }

        return $ttl;
    }
}

if (!function_exists('gta6mods_get_mod_stats_cache_group')) {
    /**
     * Returns the cache group name for aggregated stats lookups.
     *
     * @return string
     */
    function gta6mods_get_mod_stats_cache_group() {
        return 'gta6mods_mod_stats';
    }
}

if (!function_exists('gta6mods_get_mod_stats_row_cache_group')) {
    /**
     * Returns the cache group name for raw stats row lookups.
     *
     * @return string
     */
    function gta6mods_get_mod_stats_row_cache_group() {
        return 'gta6mods_mod_stats_row';
    }
}

if (!function_exists('gta6mods_get_mod_stats_runtime_cache')) {
    /**
     * Provides a reference to an in-memory runtime cache bucket.
     *
     * @param string $bucket Bucket name.
     *
     * @return array<int, array<string, int|float>>
     */
    function &gta6mods_get_mod_stats_runtime_cache($bucket) {
        static $caches = [];

        if (!isset($caches[$bucket])) {
            $caches[$bucket] = [];
        }

        return $caches[$bucket];
    }
}

if (!function_exists('gta6mods_cache_mod_stats_row')) {
    /**
     * Stores a raw stats row in both the runtime and object caches.
     *
     * @param int   $post_id Post ID.
     * @param array $row     Normalised stats row.
     */
    function gta6mods_cache_mod_stats_row($post_id, array $row) {
        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return;
        }

        $runtime = &gta6mods_get_mod_stats_runtime_cache('row');
        $runtime[$post_id] = $row;

        wp_cache_set(
            $post_id,
            $row,
            gta6mods_get_mod_stats_row_cache_group(),
            gta6mods_get_mod_stats_cache_ttl()
        );
    }
}

if (!function_exists('gta6mods_cache_mod_stats')) {
    /**
     * Stores the aggregated stats payload in both the runtime and object caches.
     *
     * @param int   $post_id Post ID.
     * @param array $stats   Aggregated stats payload.
     */
    function gta6mods_cache_mod_stats($post_id, array $stats) {
        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return;
        }

        $runtime = &gta6mods_get_mod_stats_runtime_cache('stats');
        $runtime[$post_id] = $stats;

        wp_cache_set(
            $post_id,
            $stats,
            gta6mods_get_mod_stats_cache_group(),
            gta6mods_get_mod_stats_cache_ttl()
        );
    }
}

if (!function_exists('gta6mods_invalidate_mod_stats_cache')) {
    /**
     * Clears any cached stats for a given post.
     *
     * @param int $post_id Post ID.
     */
    function gta6mods_invalidate_mod_stats_cache($post_id) {
        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return;
        }

        $row_cache   = &gta6mods_get_mod_stats_runtime_cache('row');
        $stats_cache = &gta6mods_get_mod_stats_runtime_cache('stats');

        unset($row_cache[$post_id], $stats_cache[$post_id]);

        wp_cache_delete($post_id, gta6mods_get_mod_stats_row_cache_group());
        wp_cache_delete($post_id, gta6mods_get_mod_stats_cache_group());
    }
}

if (!function_exists('gta6mods_prime_mod_stats')) {
    /**
     * Preloads statistics for multiple posts into runtime and object caches.
     *
     * @param int[] $post_ids Post IDs to prime.
     */
    function gta6mods_prime_mod_stats(array $post_ids) {
        global $wpdb;

        if (empty($post_ids)) {
            return;
        }

        $post_ids = array_map('absint', $post_ids);
        $post_ids = array_filter($post_ids);

        if (empty($post_ids)) {
            return;
        }

        $post_ids = array_values(array_unique($post_ids));

        $row_cache   = &gta6mods_get_mod_stats_runtime_cache('row');
        $stats_cache = &gta6mods_get_mod_stats_runtime_cache('stats');

        $prime_ids = [];
        foreach ($post_ids as $post_id) {
            if (array_key_exists($post_id, $row_cache) || array_key_exists($post_id, $stats_cache)) {
                continue;
            }

            $prime_ids[$post_id] = $post_id;
        }

        if (empty($prime_ids)) {
            return;
        }

        $defaults = [];
        foreach (gta6mods_get_mod_stat_columns() as $column => $definition) {
            $defaults[$column] = $definition['default'];
        }

        $row_group   = gta6mods_get_mod_stats_row_cache_group();
        $stats_group = gta6mods_get_mod_stats_cache_group();

        $row_hits   = [];
        $stats_hits = [];

        if (function_exists('wp_cache_get_multiple')) {
            $row_hits   = wp_cache_get_multiple(array_values($prime_ids), $row_group);
            $stats_hits = wp_cache_get_multiple(array_values($prime_ids), $stats_group);
        } else {
            foreach ($prime_ids as $post_id) {
                $row_hits[$post_id]   = wp_cache_get($post_id, $row_group);
                $stats_hits[$post_id] = wp_cache_get($post_id, $stats_group);
            }
        }

        foreach ($prime_ids as $post_id) {
            $row   = isset($row_hits[$post_id]) ? $row_hits[$post_id] : false;
            $stats = isset($stats_hits[$post_id]) ? $stats_hits[$post_id] : false;

            if (false !== $row && is_array($row)) {
                gta6mods_cache_mod_stats_row($post_id, $row);
            }

            if (false !== $stats && is_array($stats)) {
                gta6mods_cache_mod_stats($post_id, array_merge($defaults, $stats));
            }

            if ((false !== $row && is_array($row)) || (false !== $stats && is_array($stats))) {
                unset($prime_ids[$post_id]);
            }
        }

        if (empty($prime_ids)) {
            return;
        }

        $columns = array_keys(gta6mods_get_mod_stat_columns());
        $table   = gta6mods_get_mod_stats_table_name();
        $in      = implode(', ', array_fill(0, count($prime_ids), '%d'));

        $sql = "SELECT post_id, " . implode(', ', $columns) . " FROM {$table} WHERE post_id IN ({$in})";
        $prepared = $wpdb->prepare($sql, array_values($prime_ids));

        if (false === $prepared) {
            return;
        }

        $results = $wpdb->get_results($prepared, ARRAY_A);

        $found = [];

        if (is_array($results)) {
            foreach ($results as $row) {
                $post_id = isset($row['post_id']) ? (int) $row['post_id'] : 0;

                if ($post_id <= 0) {
                    continue;
                }

                $normalised_row = [];

                foreach ($columns as $column) {
                    $value = isset($row[$column]) ? $row[$column] : 0;
                    $normalised_row[$column] = gta6mods_normalize_stat_value($column, $value);
                }

                gta6mods_cache_mod_stats_row($post_id, $normalised_row);
                gta6mods_cache_mod_stats($post_id, array_merge($defaults, $normalised_row));

                $found[$post_id] = true;
            }
        }

        foreach ($prime_ids as $post_id) {
            if (isset($found[$post_id])) {
                continue;
            }

            gta6mods_cache_mod_stats_row($post_id, []);
            gta6mods_cache_mod_stats($post_id, $defaults);
        }
    }
}

if (!function_exists('gta6mods_map_meta_key_to_stat')) {
    /**
     * Maps a legacy meta key to its modern stat column name.
     *
     * @param string $meta_key Meta key name.
     *
     * @return string|null
     */
    function gta6mods_map_meta_key_to_stat($meta_key) {
        $meta_key = sanitize_key($meta_key);

        $map = array_flip(gta6mods_get_mod_stat_meta_map());

        return isset($map[$meta_key]) ? $map[$meta_key] : null;
    }
}

if (!function_exists('gta6mods_normalize_stat_value')) {
    /**
     * Normalises a stat value according to the column definition.
     *
     * @param string $column Column name.
     * @param mixed  $value  Value to normalise.
     *
     * @return int|float
     */
    function gta6mods_normalize_stat_value($column, $value) {
        $columns = gta6mods_get_mod_stat_columns();

        if (!isset($columns[$column])) {
            return 0;
        }

        $definition = $columns[$column];

        if ('float' === $definition['type']) {
            $value = is_numeric($value) ? (float) $value : 0.0;
            $value = max(0.0, $value);

            return (float) number_format($value, 2, '.', '');
        }

        $value = (int) $value;

        return max(0, $value);
    }
}

if (!function_exists('gta6mods_get_mod_stats_row')) {
    /**
     * Fetches the raw stats row for a post.
     *
     * @param int $post_id Post ID.
     *
     * @return array<string, int|float>
     */
    function gta6mods_get_mod_stats_row($post_id) {
        global $wpdb;

        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return [];
        }

        $runtime = &gta6mods_get_mod_stats_runtime_cache('row');

        if (array_key_exists($post_id, $runtime)) {
            return $runtime[$post_id];
        }

        $cached = wp_cache_get($post_id, gta6mods_get_mod_stats_row_cache_group());

        if (false !== $cached) {
            $runtime[$post_id] = is_array($cached) ? $cached : [];

            return $runtime[$post_id];
        }

        $table = gta6mods_get_mod_stats_table_name();

        $columns = array_keys(gta6mods_get_mod_stat_columns());
        $select  = implode(', ', $columns);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT {$select} FROM {$table} WHERE post_id = %d",
                $post_id
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            gta6mods_cache_mod_stats_row($post_id, []);

            return [];
        }

        foreach ($columns as $column) {
            $value = isset($row[$column]) ? $row[$column] : 0;
            $row[$column] = gta6mods_normalize_stat_value($column, $value);
        }

        gta6mods_cache_mod_stats_row($post_id, $row);

        return $row;
    }
}

if (!function_exists('gta6mods_replace_mod_stats_row')) {
    /**
     * Inserts or replaces a stats row for a post.
     *
     * @param int   $post_id Post ID.
     * @param array $data    Column/value pairs.
     *
     * @return bool
     */
    function gta6mods_replace_mod_stats_row($post_id, array $data) {
        global $wpdb;

        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return false;
        }

        $columns = gta6mods_get_mod_stat_columns();

        $allowed = array_intersect_key($data, $columns);

        if (empty($allowed)) {
            return false;
        }

        $table = gta6mods_get_mod_stats_table_name();

        $fields       = array_keys($allowed);
        $placeholders = ['%d'];
        $values       = [$post_id];

        $normalized = [];
        foreach ($fields as $field) {
            $normalized_value = gta6mods_normalize_stat_value($field, $allowed[$field]);
            $placeholders[]   = $columns[$field]['format'];
            $values[]         = $normalized_value;
            $normalized[$field] = $normalized_value;
        }

        $insert_columns = implode(', ', array_merge(['post_id'], $fields));
        $insert_values  = implode(', ', $placeholders);

        $updates = [];
        foreach ($fields as $field) {
            $updates[] = sprintf('%1$s = VALUES(%1$s)', $field);
        }
        $updates[] = 'last_updated = CURRENT_TIMESTAMP';

        $sql = "INSERT INTO {$table} ({$insert_columns}) VALUES ({$insert_values}) ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        $prepared = call_user_func_array([
            $wpdb,
            'prepare',
        ], array_merge([$sql], $values));

        if (false === $prepared) {
            return false;
        }

        $result = false !== $wpdb->query($prepared);

        if ($result) {
            gta6mods_invalidate_mod_stats_cache($post_id);

            if (function_exists('gta6mods_update_filter_index_stats') && !empty($normalized)) {
                gta6mods_update_filter_index_stats($post_id, $normalized);
            }
        }

        return $result;
    }
}

if (!function_exists('gta6mods_prime_mod_stats_from_meta')) {
    /**
     * Synchronises the stats table with the legacy post meta for a post.
     *
     * @param int $post_id Post ID.
     *
     * @return array<string, int|float>
     */
    function gta6mods_prime_mod_stats_from_meta($post_id) {
        $post_id = absint($post_id);

        if ($post_id <= 0) {
            return [];
        }

        $meta_map = gta6mods_get_mod_stat_meta_map();
        $data     = [];

        foreach ($meta_map as $column => $meta_key) {
            $raw = get_post_meta($post_id, $meta_key, true);

            if ('rating_average' === $column) {
                $value = is_numeric($raw) ? (float) $raw : 0.0;
            } else {
                $value = (int) $raw;
            }

            $data[$column] = gta6mods_normalize_stat_value($column, $value);
        }

        if (!gta6mods_replace_mod_stats_row($post_id, $data)) {
            return $data;
        }

        gta6mods_cache_mod_stats_row($post_id, $data);

        return $data;
    }
}

if (!function_exists('gta6mods_get_mod_stats')) {
    /**
     * Retrieves the stats for a post, priming from meta if necessary.
     *
     * @param int $post_id Post ID.
     *
     * @return array<string, int|float>
     */
    function gta6mods_get_mod_stats($post_id) {
        $post_id = absint($post_id);

        $defaults = [];
        foreach (gta6mods_get_mod_stat_columns() as $column => $definition) {
            $defaults[$column] = $definition['default'];
        }

        if ($post_id <= 0) {
            return $defaults;
        }

        $runtime = &gta6mods_get_mod_stats_runtime_cache('stats');

        if (array_key_exists($post_id, $runtime)) {
            return $runtime[$post_id];
        }

        $cached = wp_cache_get($post_id, gta6mods_get_mod_stats_cache_group());

        if (false !== $cached && is_array($cached)) {
            $runtime[$post_id] = array_merge($defaults, $cached);

            return $runtime[$post_id];
        }

        $row = gta6mods_get_mod_stats_row($post_id);

        if (empty($row)) {
            $row = gta6mods_prime_mod_stats_from_meta($post_id);
        }

        if (empty($row)) {
            gta6mods_cache_mod_stats_row($post_id, []);

            $stats = $defaults;
        } else {
            $stats = array_merge($defaults, $row);
        }

        gta6mods_cache_mod_stats($post_id, $stats);

        return $stats;
    }
}

if (!function_exists('gta6mods_get_mod_stat')) {
    /**
     * Retrieves a single stat value for a post.
     *
     * @param int    $post_id Post ID.
     * @param string $stat    Column name.
     *
     * @return int|float
     */
    function gta6mods_get_mod_stat($post_id, $stat) {
        $columns = gta6mods_get_mod_stat_columns();

        if (!isset($columns[$stat])) {
            return 0;
        }

        $stats = gta6mods_get_mod_stats($post_id);

        if (!isset($stats[$stat])) {
            return $columns[$stat]['default'];
        }

        return $stats[$stat];
    }
}

if (!function_exists('gta6mods_update_stat_meta_cache')) {
    /**
     * Writes the stat value back to the legacy post meta store.
     *
     * @param int    $post_id Post ID.
     * @param string $stat    Column name.
     * @param mixed  $value   Value to store.
     */
    function gta6mods_update_stat_meta_cache($post_id, $stat, $value) {
        $meta_map = gta6mods_get_mod_stat_meta_map();

        if (!isset($meta_map[$stat])) {
            return;
        }

        $meta_key = $meta_map[$stat];
        $columns  = gta6mods_get_mod_stat_columns();

        if (!isset($columns[$stat])) {
            return;
        }

        if ('float' === $columns[$stat]['type']) {
            update_post_meta($post_id, $meta_key, (float) $value);
        } else {
            update_post_meta($post_id, $meta_key, (int) $value);
        }
    }
}

if (!function_exists('gta6mods_set_mod_stat')) {
    /**
     * Sets a stat to a specific value for a post.
     *
     * @param int    $post_id Post ID.
     * @param string $stat    Column name.
     * @param mixed  $value   Desired value.
     *
     * @return int|float
     */
    function gta6mods_set_mod_stat($post_id, $stat, $value) {
        $columns = gta6mods_get_mod_stat_columns();

        if (!isset($columns[$stat])) {
            return 0;
        }

        $normalised = gta6mods_normalize_stat_value($stat, $value);

        gta6mods_replace_mod_stats_row($post_id, [
            $stat => $normalised,
        ]);

        gta6mods_update_stat_meta_cache($post_id, $stat, $normalised);

        if (function_exists('gta6mods_update_filter_index_stats')) {
            gta6mods_update_filter_index_stats($post_id, [$stat => $normalised]);
        }

        return $normalised;
    }
}

if (!function_exists('gta6mods_increment_mod_stat')) {
    /**
     * Atomically increments a stat value and returns the new total.
     *
     * @param int    $post_id Post ID.
     * @param string $stat    Column name.
     * @param int    $amount  Increment amount.
     *
     * @return int|float
     */
    function gta6mods_increment_mod_stat($post_id, $stat, $amount = 1) {
        global $wpdb;

        $columns = gta6mods_get_mod_stat_columns();

        if (!isset($columns[$stat])) {
            return 0;
        }

        $amount = (int) $amount;

        if (0 === $amount) {
            return gta6mods_get_mod_stat($post_id, $stat);
        }

        $table = gta6mods_get_mod_stats_table_name();

        $sql = "INSERT INTO {$table} (post_id, {$stat}) VALUES (%d, %d) ON DUPLICATE KEY UPDATE {$stat} = {$stat} + VALUES({$stat}), last_updated = CURRENT_TIMESTAMP";

        $prepared = $wpdb->prepare($sql, absint($post_id), $amount);

        $rows_affected = 0;

        if (false !== $prepared) {
            $rows_affected = (int) $wpdb->query($prepared);
        }

        if ($rows_affected > 0) {
            gta6mods_invalidate_mod_stats_cache($post_id);
        }

        $new_value = gta6mods_get_mod_stat($post_id, $stat);

        if ('int' === $columns[$stat]['type'] && $new_value < 0) {
            $new_value = gta6mods_set_mod_stat($post_id, $stat, 0);
        } else {
            gta6mods_update_stat_meta_cache($post_id, $stat, $new_value);
        }

        if (function_exists('gta6mods_update_filter_index_stats')) {
            gta6mods_update_filter_index_stats($post_id, [$stat => $new_value]);
        }

        return $new_value;
    }
}

if (!function_exists('gta6mods_set_mod_rating_stats')) {
    /**
     * Updates the rating aggregate values for a post.
     *
     * @param int   $post_id Post ID.
     * @param float $average Average rating.
     * @param int   $count   Rating count.
     */
    function gta6mods_set_mod_rating_stats($post_id, $average, $count) {
        global $wpdb;

        $post_id = absint($post_id);
        $count   = max(0, (int) $count);
        $average = max(0.0, (float) $average);
        $average = (float) number_format($average, 2, '.', '');

        $table = gta6mods_get_mod_stats_table_name();

        $sql = "INSERT INTO {$table} (post_id, rating_average, rating_count) VALUES (%d, %s, %d) ON DUPLICATE KEY UPDATE rating_average = VALUES(rating_average), rating_count = VALUES(rating_count), last_updated = CURRENT_TIMESTAMP";

        $prepared = $wpdb->prepare($sql, $post_id, $average, $count);

        $rows_affected = 0;

        if (false !== $prepared) {
            $rows_affected = (int) $wpdb->query($prepared);
        }

        if ($rows_affected > 0) {
            gta6mods_invalidate_mod_stats_cache($post_id);
        }

        gta6mods_update_stat_meta_cache($post_id, 'rating_average', $average);
        gta6mods_update_stat_meta_cache($post_id, 'rating_count', $count);

        if (function_exists('gta6mods_update_filter_index_stats')) {
            gta6mods_update_filter_index_stats($post_id, [
                'rating_average' => $average,
                'rating_count'   => $count,
            ]);
        }
    }
}

if (!function_exists('gta6mods_get_author_post_ids_missing_stats')) {
    /**
     * Retrieves post IDs for an author that do not yet have stats rows.
     *
     * @param int $author_id Author ID.
     * @param int $limit     Maximum records to return.
     *
     * @return int[]
     */
    function gta6mods_get_author_post_ids_missing_stats($author_id, $limit = 200) {
        global $wpdb;

        $author_id = absint($author_id);
        $limit     = max(1, absint($limit));

        if ($author_id <= 0) {
            return [];
        }

        $post_types = gta6mods_get_mod_post_types();

        if (empty($post_types)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $table = gta6mods_get_mod_stats_table_name();

        $sql = "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$table} s ON p.ID = s.post_id WHERE p.post_author = %d AND p.post_status = 'publish' AND p.post_type IN ({$placeholders}) AND s.post_id IS NULL ORDER BY p.ID ASC LIMIT %d";

        $params = array_merge([$author_id], $post_types, [$limit]);

        $prepared = call_user_func_array([
            $wpdb,
            'prepare',
        ], array_merge([$sql], $params));

        if (false === $prepared) {
            return [];
        }

        $results = $wpdb->get_col($prepared);

        if (empty($results)) {
            return [];
        }

        return array_map('absint', $results);
    }
}

if (!function_exists('gta6mods_prime_author_stats')) {
    /**
     * Ensures the stats table is populated for an author's posts.
     *
     * @param int $author_id Author ID.
     * @param int $limit     Maximum posts to prime per call.
     */
    function gta6mods_prime_author_stats($author_id, $limit = 200) {
        $missing = gta6mods_get_author_post_ids_missing_stats($author_id, $limit);

        if (empty($missing)) {
            return;
        }

        foreach ($missing as $post_id) {
            gta6mods_prime_mod_stats_from_meta($post_id);
        }
    }
}

if (!function_exists('gta6mods_sum_author_stat')) {
    /**
     * Sums a stat across all published mods for an author.
     *
     * @param int    $author_id Author ID.
     * @param string $stat      Stat column.
     *
     * @return int|float
     */
    function gta6mods_sum_author_stat($author_id, $stat) {
        global $wpdb;

        $author_id = absint($author_id);

        $columns = gta6mods_get_mod_stat_columns();

        if ($author_id <= 0 || !isset($columns[$stat])) {
            return 0;
        }

        gta6mods_prime_author_stats($author_id);

        $post_types = gta6mods_get_mod_post_types();

        if (empty($post_types)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $table        = gta6mods_get_mod_stats_table_name();

        $sql = "SELECT SUM(s.{$stat}) FROM {$table} s INNER JOIN {$wpdb->posts} p ON p.ID = s.post_id WHERE p.post_author = %d AND p.post_status = 'publish' AND p.post_type IN ({$placeholders})";

        $params = array_merge([$author_id], $post_types);

        $prepared = call_user_func_array([
            $wpdb,
            'prepare',
        ], array_merge([$sql], $params));

        if (false === $prepared) {
            return 0;
        }

        $sum = $wpdb->get_var($prepared);

        if ('float' === $columns[$stat]['type']) {
            $sum = is_numeric($sum) ? (float) $sum : 0.0;
            return max(0.0, $sum);
        }

        return max(0, (int) $sum);
    }
}

if (!function_exists('gta6mods_query_top_mod_ids')) {
    /**
     * Internal helper to query top mods by stat.
     *
     * @param string $stat  Stat column.
     * @param int    $limit Maximum results.
     * @param string $order Sort direction.
     *
     * @return int[]
     */
    function gta6mods_query_top_mod_ids($stat, $limit, $order = 'DESC') {
        global $wpdb;

        $columns = gta6mods_get_mod_stat_columns();

        if (!isset($columns[$stat])) {
            return [];
        }

        $limit = max(1, absint($limit));
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $post_types = gta6mods_get_mod_post_types();

        if (empty($post_types)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $table        = gta6mods_get_mod_stats_table_name();

        $sql = "SELECT s.post_id FROM {$table} s INNER JOIN {$wpdb->posts} p ON p.ID = s.post_id WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}) ORDER BY s.{$stat} {$order}, s.last_updated DESC, s.post_id DESC LIMIT %d";

        $params = array_merge($post_types, [$limit]);

        $prepared = call_user_func_array([
            $wpdb,
            'prepare',
        ], array_merge([$sql], $params));

        if (false === $prepared) {
            return [];
        }

        $results = $wpdb->get_col($prepared);

        if (empty($results)) {
            return [];
        }

        return array_map('absint', $results);
    }
}

if (!function_exists('gta6mods_prime_top_mod_stats_from_meta')) {
    /**
     * Populates the stats table for high-value mods based on legacy meta.
     *
     * @param string $stat  Stat column.
     * @param int    $limit Maximum posts to prime.
     * @param string $order Sort direction.
     */
    function gta6mods_prime_top_mod_stats_from_meta($stat, $limit = 10, $order = 'DESC') {
        $meta_map = gta6mods_get_mod_stat_meta_map();

        if (!isset($meta_map[$stat])) {
            return;
        }

        $meta_key = $meta_map[$stat];
        $order    = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $query = new WP_Query([
            'post_type'              => gta6mods_get_mod_post_types(),
            'post_status'            => 'publish',
            'posts_per_page'         => max(1, absint($limit)),
            'meta_key'               => $meta_key,
            'orderby'                => 'meta_value_num',
            'order'                  => $order,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
            'cache_results'          => false,
        ]);

        if (empty($query->posts)) {
            return;
        }

        foreach ($query->posts as $post_id) {
            gta6mods_prime_mod_stats_from_meta((int) $post_id);
        }
    }
}

if (!function_exists('gta6mods_get_top_mod_ids')) {
    /**
     * Retrieves the top mods site-wide ordered by a stat.
     *
     * @param string $stat  Stat column.
     * @param int    $limit Maximum results.
     * @param string $order Sort direction.
     *
     * @return int[]
     */
    function gta6mods_get_top_mod_ids($stat, $limit = 10, $order = 'DESC') {
        $ids = gta6mods_query_top_mod_ids($stat, $limit, $order);

        if (!empty($ids)) {
            return $ids;
        }

        gta6mods_prime_top_mod_stats_from_meta($stat, $limit, $order);

        return gta6mods_query_top_mod_ids($stat, $limit, $order);
    }
}

if (!function_exists('gta6mods_get_author_top_mod_ids')) {
    /**
     * Retrieves an author's top mods ordered by a stat.
     *
     * @param int    $author_id Author ID.
     * @param string $stat      Stat column.
     * @param int    $limit     Maximum results.
     * @param string $order     Sort direction.
     *
     * @return int[]
     */
    function gta6mods_get_author_top_mod_ids($author_id, $stat, $limit = 3, $order = 'DESC') {
        global $wpdb;

        $author_id = absint($author_id);

        $columns = gta6mods_get_mod_stat_columns();

        if ($author_id <= 0 || !isset($columns[$stat])) {
            return [];
        }

        gta6mods_prime_author_stats($author_id);

        $limit = max(1, absint($limit));
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $post_types = gta6mods_get_mod_post_types();

        if (empty($post_types)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $table        = gta6mods_get_mod_stats_table_name();

        $sql = "SELECT s.post_id FROM {$table} s INNER JOIN {$wpdb->posts} p ON p.ID = s.post_id WHERE p.post_author = %d AND p.post_status = 'publish' AND p.post_type IN ({$placeholders}) ORDER BY s.{$stat} {$order}, s.last_updated DESC, s.post_id DESC LIMIT %d";

        $params = array_merge([$author_id], $post_types, [$limit]);

        $prepared = call_user_func_array([
            $wpdb,
            'prepare',
        ], array_merge([$sql], $params));

        if (false === $prepared) {
            return [];
        }

        $results = $wpdb->get_col($prepared);

        if (!empty($results)) {
            return array_map('absint', $results);
        }

        $meta_key = gta6mods_get_mod_stat_meta_map()[$stat];

        $fallback = new WP_Query([
            'post_type'              => gta6mods_get_mod_post_types(),
            'post_status'            => 'publish',
            'author'                 => $author_id,
            'posts_per_page'         => $limit,
            'meta_key'               => $meta_key,
            'orderby'                => 'meta_value_num',
            'order'                  => $order,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
            'cache_results'          => false,
        ]);

        if (!empty($fallback->posts)) {
            foreach ($fallback->posts as $post_id) {
                gta6mods_prime_mod_stats_from_meta((int) $post_id);
            }
        }

        return gta6mods_query_top_mod_ids($stat, $limit, $order);
    }
}
