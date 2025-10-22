<?php
/**
 * Template helper functions.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GTA6_MODS_BOOKMARK_META_KEY')) {
    define('GTA6_MODS_BOOKMARK_META_KEY', '_gta6mods_bookmarked_mods');
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

function gta6_mods_get_placeholder($type = 'featured') {
    switch ($type) {
        case 'card':
            return 'https://placehold.co/300x169/94a3b8/ffffff?text=GTA6+Mods';
        case 'news':
            return 'https://placehold.co/400x225/111827/f9fafb?text=GTA6+News';
        default:
            return 'https://placehold.co/900x500/ec4899/ffffff?text=Featured+Mod';
    }
}

/**
 * Returns the list of category slugs that users can select for mods.
 *
 * @return string[]
 */
function gta6mods_get_allowed_category_slugs() {
    return ['tools', 'vehicles', 'paint-jobs', 'weapons', 'scripts', 'player', 'maps', 'misc'];
}

/**
 * Returns a map of allowed category slugs to their WP_Term objects.
 *
 * @return array<string, WP_Term>
 */
function gta6mods_get_allowed_category_terms_map() {
    static $cache = null;

    if (null !== $cache) {
        return $cache;
    }

    $cache = [];

    foreach (gta6mods_get_allowed_category_slugs() as $slug) {
        $term = get_term_by('slug', $slug, 'category');
        if ($term instanceof WP_Term) {
            $cache[$slug] = $term;
        }
    }

    return $cache;
}

/**
 * Returns allowed categories as serializable option arrays.
 *
 * @return array<int, array{id:int, slug:string, name:string}>
 */
function gta6mods_get_allowed_category_options() {
    $options   = [];
    $terms_map = gta6mods_get_allowed_category_terms_map();

    foreach (gta6mods_get_allowed_category_slugs() as $slug) {
        if (!isset($terms_map[$slug])) {
            continue;
        }

        $term = $terms_map[$slug];
        $options[] = [
            'id'   => (int) $term->term_id,
            'slug' => $term->slug,
            'name' => $term->name,
        ];
    }

    return $options;
}

/**
 * Determines whether the provided term ID belongs to an allowed category.
 *
 * @param int $term_id Category term ID.
 *
 * @return bool
 */
function gta6mods_is_allowed_category_id($term_id) {
    $term_id = absint($term_id);

    if ($term_id <= 0) {
        return false;
    }

    $term = get_term($term_id, 'category');

    if (!$term instanceof WP_Term || is_wp_error($term)) {
        return false;
    }

    return in_array($term->slug, gta6mods_get_allowed_category_slugs(), true);
}

function gta6_mods_get_image($post_id, $size = 'large', $type = 'featured') {
    if (has_post_thumbnail($post_id)) {
        $image = get_the_post_thumbnail_url($post_id, $size);
        if ($image) {
            return $image;
        }
    }

    return gta6_mods_get_placeholder($type);
}

function gta6_mods_get_gallery_images($post_id) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return [];
    }

    $cache_group = 'gta6mods_gallery';
    $cache_key   = 'gallery_images_' . $post_id;
    $cached      = wp_cache_get($cache_key, $cache_group);
    if (false !== $cached) {
        return is_array($cached) ? $cached : [];
    }

    $raw_value         = get_post_meta($post_id, '_gta6mods_gallery_images', true);
    $raw_value_string  = is_string($raw_value) ? $raw_value : '';
    $gallery           = [];
    $should_update_meta = false;
    $seen_attachments  = [];
    $removed_ids       = function_exists('gta6mods_get_removed_gallery_ids') ? gta6mods_get_removed_gallery_ids($post_id) : [];
    $removed_lookup    = [];

    if (!empty($removed_ids)) {
        foreach ($removed_ids as $removed_id) {
            $removed_lookup[(int) $removed_id] = true;
        }
    }

    if (is_string($raw_value) && $raw_value !== '') {
        $decoded = json_decode($raw_value, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    $should_update_meta = true;
                    continue;
                }

                $attachment_id = isset($item['attachment_id']) ? absint($item['attachment_id']) : 0;
                if ($attachment_id <= 0 || isset($seen_attachments[$attachment_id]) || isset($removed_lookup[$attachment_id])) {
                    $should_update_meta = true;
                    continue;
                }

                $attachment = get_post($attachment_id);
                if (!$attachment instanceof WP_Post || 'attachment' !== $attachment->post_type) {
                    $should_update_meta = true;
                    continue;
                }

                $order = isset($item['order']) ? (int) $item['order'] : 0;

                $gallery[] = [
                    'attachment_id' => $attachment_id,
                    'order'         => $order,
                    'url'           => wp_get_attachment_url($attachment_id) ?: '',
                ];

                $seen_attachments[$attachment_id] = true;
            }
        } else {
            $should_update_meta = true;
        }
    } elseif (is_array($raw_value) && !empty($raw_value)) {
        $should_update_meta = true;
        $order_index        = 0;

        foreach ($raw_value as $value) {
            if (!is_string($value) || '' === $value) {
                continue;
            }

            $url = trim($value);
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $attachment_id = attachment_url_to_postid($url);
            if ($attachment_id <= 0 || isset($seen_attachments[$attachment_id]) || isset($removed_lookup[$attachment_id])) {
                continue;
            }

            $attachment = get_post($attachment_id);
            if (!$attachment instanceof WP_Post || 'attachment' !== $attachment->post_type) {
                continue;
            }

            $gallery[] = [
                'attachment_id' => $attachment_id,
                'order'         => $order_index++,
                'url'           => wp_get_attachment_url($attachment_id) ?: $url,
            ];

            $seen_attachments[$attachment_id] = true;
        }
    } elseif (!is_string($raw_value) && !empty($raw_value)) {
        $should_update_meta = true;
    }

    if (empty($gallery)) {
        if ($should_update_meta) {
            delete_post_meta($post_id, '_gta6mods_gallery_images');
        }

        wp_cache_set($cache_key, [], $cache_group, MINUTE_IN_SECONDS);

        return [];
    }

    usort(
        $gallery,
        static function ($a, $b) {
            return $a['order'] <=> $b['order'];
        }
    );

    $normalized_meta = [];
    foreach ($gallery as $index => &$item) {
        if ($item['order'] !== $index) {
            $should_update_meta = true;
        }

        $item['order'] = $index;
        if ($item['url'] === '') {
            $item['url'] = wp_get_attachment_url($item['attachment_id']) ?: '';
        }

        $normalized_meta[] = [
            'attachment_id' => $item['attachment_id'],
            'order'         => $index,
        ];
    }
    unset($item);

    $encoded_value = wp_json_encode($normalized_meta);

    if ($should_update_meta || $raw_value_string !== $encoded_value) {
        update_post_meta($post_id, '_gta6mods_gallery_images', $encoded_value);
    }

    wp_cache_set($cache_key, $gallery, $cache_group, HOUR_IN_SECONDS);

    return $gallery;
}

function gta6_mods_get_denormalized_author_name($post_id) {
    $post_id = absint($post_id);

    if ($post_id <= 0) {
        return '';
    }

    $cached_name = get_post_meta($post_id, GTA6_MODS_AUTHOR_DISPLAY_NAME_META_KEY, true);

    if (is_string($cached_name) && '' !== $cached_name) {
        return $cached_name;
    }

    $author_id = (int) get_post_field('post_author', $post_id);
    if ($author_id <= 0) {
        return '';
    }

    $display_name = get_the_author_meta('display_name', $author_id);

    if (!is_string($display_name)) {
        return '';
    }

    $sanitized_name = wp_strip_all_tags($display_name);

    if ('' !== $sanitized_name) {
        update_post_meta($post_id, GTA6_MODS_AUTHOR_DISPLAY_NAME_META_KEY, $sanitized_name);
    }

    return $sanitized_name;
}

function gta6_mods_get_primary_category_data($post_id, $categories = null) {
    $post_id = absint($post_id);

    $defaults = [
        'term_id' => 0,
        'name'    => '',
        'slug'    => '',
    ];

    if ($post_id <= 0) {
        return $defaults;
    }

    $cached_name = get_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_NAME_META_KEY, true);
    $cached_slug = get_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_SLUG_META_KEY, true);
    $cached_id   = (int) get_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_ID_META_KEY, true);

    if (is_string($cached_name) && '' !== $cached_name && is_string($cached_slug) && '' !== $cached_slug && $cached_id > 0) {
        return [
            'term_id' => $cached_id,
            'name'    => $cached_name,
            'slug'    => $cached_slug,
        ];
    }

    $primary_category = null;

    if (null === $categories) {
        $categories = get_the_category($post_id);
    }

    if (is_array($categories) && !empty($categories)) {
        $primary_category = $categories[0];
    }

    if (!$primary_category instanceof WP_Term) {
        $default = (int) get_option('default_category');
        if ($default > 0) {
            $term = get_term($default, 'category');
            if ($term instanceof WP_Term && !is_wp_error($term)) {
                $primary_category = $term;
            }
        }
    }

    if ($primary_category instanceof WP_Term) {
        $data = [
            'term_id' => (int) $primary_category->term_id,
            'name'    => wp_strip_all_tags($primary_category->name),
            'slug'    => sanitize_title($primary_category->slug),
        ];

        update_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_NAME_META_KEY, $data['name']);
        update_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_SLUG_META_KEY, $data['slug']);
        update_post_meta($post_id, GTA6_MODS_PRIMARY_CATEGORY_ID_META_KEY, $data['term_id']);

        return $data;
    }

    return $defaults;
}

function gta6_mods_get_primary_category_link($post_id) {
    $category_data = gta6_mods_get_primary_category_data($post_id);

    if ($category_data['term_id'] > 0) {
        $link = get_term_link($category_data['term_id'], 'category');
        if (!is_wp_error($link)) {
            return $link;
        }
    }

    if ('' !== $category_data['slug']) {
        $link = get_term_link($category_data['slug'], 'category');
        if (!is_wp_error($link)) {
            return $link;
        }
    }

    return '';
}

function gta6_mods_get_category_name($post_id) {
    $category_data = gta6_mods_get_primary_category_data($post_id);

    if ('' !== $category_data['name']) {
        return $category_data['name'];
    }

    return __('Modok', 'gta6-mods');
}

function gta6_mods_get_mod_metrics($post_id) {
    $post_id = (int) $post_id;

    if ($post_id <= 0) {
        return [
            'downloads' => 0,
            'likes'     => 0,
            'views'     => 0,
            'rating'    => 0.0,
        ];
    }

    $stats       = gta6mods_get_mod_stats($post_id);
    $rating_data = gta6_mods_get_rating_data($post_id);

    return [
        'downloads' => isset($stats['downloads']) ? (int) $stats['downloads'] : 0,
        'likes'     => isset($stats['likes']) ? (int) $stats['likes'] : 0,
        'views'     => isset($stats['views']) ? (int) $stats['views'] : 0,
        'rating'    => isset($rating_data['average']) ? (float) $rating_data['average'] : 0.0,
        'rating_count' => isset($rating_data['count']) ? (int) $rating_data['count'] : 0,
    ];
}

function gta6_mods_format_updated_label($mods) {
    if (empty($mods)) {
        return __('Frissítve: jelenleg nem érhető el', 'gta6-mods');
    }

    $latest = $mods[0];
    $latest_post = get_post($latest['id']);

    if (!$latest_post instanceof WP_Post) {
        return __('Frissítve: nem elérhető', 'gta6-mods');
    }

    $timestamp = get_post_timestamp($latest_post);
    if (!$timestamp) {
        return __('Frissítve: nem elérhető', 'gta6-mods');
    }
    $current_time = current_time('timestamp');

    if ((int) gmdate('Ymd', $timestamp) === (int) gmdate('Ymd', $current_time)) {
        return __('Frissítve: ma', 'gta6-mods');
    }

    $diff = human_time_diff($timestamp, $current_time);

    return sprintf(__('Frissítve: %s', 'gta6-mods'), $diff);
}

function gta6_mods_get_current_url_for_comparison() {
    $host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $request_uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW);

    if (empty($host) || empty($request_uri)) {
        return '';
    }

    $scheme = is_ssl() ? 'https://' : 'http://';

    return esc_url_raw($scheme . $host . wp_unslash($request_uri));
}

function gta6_mods_normalize_url_for_comparison($url) {
    $url = trim((string) $url);

    if ($url === '') {
        return '';
    }

    if (strpos($url, '//') === 0) {
        $url = (is_ssl() ? 'https:' : 'http:') . $url;
    } elseif (strpos($url, '/') === 0) {
        $url = home_url($url);
    } elseif (!preg_match('#^https?://#i', $url)) {
        $url = home_url('/' . ltrim($url, '/'));
    }

    $parts = wp_parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return '';
    }

    $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : (is_ssl() ? 'https' : 'http');
    $host = strtolower($parts['host']);
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = isset($parts['path']) ? untrailingslashit($parts['path']) : '';
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

    return $scheme . '://' . $host . $port . $path . $query;
}

function gta6_mods_build_category_filter_chips($term = null) {
    if (!$term instanceof WP_Term) {
        $term = get_queried_object();
    }

    if (!$term instanceof WP_Term) {
        return [];
    }

    $chips_from_meta = gta6_mods_get_category_filter_chip_meta($term->term_id);

    if (empty($chips_from_meta)) {
        return [];
    }

    $category_link = get_term_link($term);

    if (is_wp_error($category_link)) {
        return [];
    }

    $current_url = gta6_mods_normalize_url_for_comparison(gta6_mods_get_current_url_for_comparison());
    $category_url_compare = gta6_mods_normalize_url_for_comparison($category_link);
    $has_active_custom_chip = false;

    $chips = [];

    $chips[] = [
        'label'  => __('Összes', 'gta6-mods'),
        'url'    => $category_link,
        'active' => $current_url ? $category_url_compare === $current_url : true,
    ];

    foreach ($chips_from_meta as $chip) {
        $compare_url = gta6_mods_normalize_url_for_comparison($chip['url']);
        $is_active = $current_url !== '' && $compare_url !== '' && $compare_url === $current_url;

        if ($is_active) {
            $has_active_custom_chip = true;
        }

        $chips[] = [
            'label'  => $chip['label'],
            'url'    => $chip['url'],
            'active' => $is_active,
        ];
    }

    if ($has_active_custom_chip) {
        $chips[0]['active'] = false;
    } elseif (empty($chips[0]['active'])) {
        $chips[0]['active'] = true;
    }

    return $chips;
}

function gta6_mods_is_post_featured($post_id) {
    return (bool) get_post_meta($post_id, GTA6_MODS_FEATURED_META_KEY, true);
}

function gta6mods_generate_featured_timestamp_value($post_id = 0) {
    $post_id  = (int) $post_id;
    $raw_time = microtime(true);
    $timestamp = sprintf('%.0f', $raw_time * 1000000);

    /**
     * Filters the raw timestamp value stored when a mod is marked as featured.
     *
     * @param string $timestamp Generated timestamp value.
     * @param int    $post_id   Post ID receiving the timestamp.
     */
    $timestamp = apply_filters('gta6mods_featured_timestamp_value', $timestamp, $post_id);

    $timestamp = is_string($timestamp) ? trim($timestamp) : (string) $timestamp;
    if ('' === $timestamp) {
        $timestamp = sprintf('%.0f', microtime(true) * 1000000);
    }

    return preg_replace('/[^0-9]/', '', $timestamp);
}

function gta6mods_set_featured_timestamp($post_id, $timestamp = null) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    if (null === $timestamp) {
        $timestamp = gta6mods_generate_featured_timestamp_value($post_id);
    }

    if (!is_string($timestamp)) {
        $timestamp = (string) $timestamp;
    }

    $timestamp = preg_replace('/[^0-9]/', '', $timestamp);

    if ('' === $timestamp) {
        $timestamp = gta6mods_generate_featured_timestamp_value($post_id);
    }

    update_post_meta($post_id, GTA6_MODS_FEATURED_TIMESTAMP_META_KEY, $timestamp);
}

function gta6mods_clear_featured_timestamp($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    delete_post_meta($post_id, GTA6_MODS_FEATURED_TIMESTAMP_META_KEY);
}

function gta6mods_get_featured_timestamp($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $value = get_post_meta($post_id, GTA6_MODS_FEATURED_TIMESTAMP_META_KEY, true);
    return is_string($value) ? $value : '';
}

function gta6_mods_get_mod_version($post_id) {
    $version = get_post_meta($post_id, '_gta6mods_mod_version', true);
    return is_string($version) ? trim($version) : '';
}

function gta6_mods_get_download_count($post_id) {
    return (int) gta6mods_get_mod_stat($post_id, 'downloads');
}

function gta6_mods_get_view_count($post_id) {
    return (int) gta6mods_get_mod_stat($post_id, 'views');
}

function gta6_mods_increment_view_count($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    return (int) gta6mods_increment_mod_stat($post_id, 'views', 1);
}

function gta6_mods_get_last_download_timestamp($post_id) {
    $timestamp = get_post_meta($post_id, '_gta6mods_last_downloaded', true);
    return $timestamp ? (int) $timestamp : 0;
}

function gta6_mods_get_mod_file_data($post_id) {
    $post_id = (int) $post_id;

    if ($post_id > 0) {
        $version = GTA6Mods_Mod_Versions::get_latest_version($post_id);
        if ($version) {
            $attachment_id = isset($version['attachment_id']) ? (int) $version['attachment_id'] : 0;
            $size_human    = '';

            if ($attachment_id > 0) {
                $stored_size = (int) get_post_meta($attachment_id, '_filesize', true);
                if ($stored_size <= 0) {
                    $path = get_attached_file($attachment_id);
                    if ($path && file_exists($path)) {
                        $stored_size = (int) filesize($path);
                    }
                }

                if ($stored_size > 0) {
                    $size_human = size_format((float) $stored_size);
                }

                return [
                    'url'        => gta6_mods_get_waiting_room_url($post_id, (int) $version['id']),
                    'size_human' => $size_human,
                    'version_id' => (int) $version['id'],
                    'version'    => isset($version['version']) ? $version['version'] : '',
                ];
            }
        }
    }

    $file_meta     = get_post_meta($post_id, '_gta6mods_mod_file', true);
    $external_meta = get_post_meta($post_id, '_gta6mods_mod_external', true);

    if (is_array($file_meta) && !empty($file_meta['url'])) {
        return [
            'url'        => esc_url_raw($file_meta['url']),
            'size_human' => isset($file_meta['size_human']) ? $file_meta['size_human'] : '',
            'type'       => 'file',
        ];
    }

    if (is_array($external_meta) && !empty($external_meta['url'])) {
        return [
            'url'        => esc_url_raw($external_meta['url']),
            'size_human' => isset($external_meta['size_human']) ? $external_meta['size_human'] : '',
            'type'       => 'external',
        ];
    }

    return [
        'url'        => '',
        'size_human' => '',
        'type'       => '',
    ];
}

function gta6_mods_get_waiting_room_url($post_id, $version_id = 0, array $args = []) {
    $post_id = (int) $post_id;
    $slug    = $post_id > 0 ? get_post_field('post_name', $post_id) : '';

    if (!$slug) {
        return '';
    }

    $base = trailingslashit($slug) . 'download/';

    if (!empty($args['external_type'])) {
        $type_raw   = is_string($args['external_type']) ? strtolower($args['external_type']) : '';
        $target_id  = isset($args['external_target']) ? (int) $args['external_target'] : 0;
        $type       = in_array($type_raw, ['version', 'mod'], true) ? $type_raw : 'version';
        if ('mod' === $type) {
            return home_url($base . 'external/');
        }

        if ($target_id <= 0) {
            $target_id = max(1, (int) $version_id);
        }

        return home_url($base . 'external/' . $target_id . '/');
    }

    if ($version_id > 0) {
        return home_url($base . $version_id . '/');
    }

    return home_url($base . 'latest/');
}

function gta6_mods_get_mod_download_url($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $latest = GTA6Mods_Mod_Versions::get_latest_version($post_id);
    if ($latest) {
        return gta6_mods_get_waiting_room_url($post_id, (int) $latest['id']);
    }

    $fallback = gta6_mods_get_mod_file_data($post_id);
    if (!empty($fallback['url'])) {
        if (!empty($fallback['type']) && 'external' === $fallback['type']) {
            return gta6_mods_get_waiting_room_url(
                $post_id,
                $post_id,
                [
                    'external_type'   => 'mod',
                    'external_target' => $post_id,
                ]
            );
        }

        return $fallback['url'];
    }

    return gta6_mods_get_waiting_room_url($post_id, 0);
}

function gta6_mods_get_mod_file_size_display($post_id) {
    $data = gta6_mods_get_mod_file_data($post_id);
    $size = isset($data['size_human']) ? $data['size_human'] : '';

    if ('' !== $size) {
        return $size;
    }

    return '';
}

function gta6_mods_prepare_version_for_display($version) {
    if (empty($version) || !is_array($version)) {
        return [];
    }

    $legacy_id    = isset($version['id']) ? (int) $version['id'] : 0;
    $number       = isset($version['number']) ? sanitize_text_field($version['number']) : '';
    $downloads    = isset($version['downloads']) ? (int) $version['downloads'] : 0;
    $download_url = isset($version['download_url']) ? esc_url($version['download_url']) : '';
    $size_human   = isset($version['size_human']) ? sanitize_text_field($version['size_human']) : '';
    $date_raw     = isset($version['date']) ? $version['date'] : '';
    $date_override = isset($version['display_date']) ? $version['display_date'] : '';
    $date_display = '';
    $changelog    = [];
    $mod_id       = isset($version['mod_id']) ? (int) $version['mod_id'] : 0;
    $table_id     = isset($version['table_id']) ? (int) $version['table_id'] : 0;

    if ($date_raw) {
        $timestamp = strtotime($date_raw);
        if ($timestamp && $timestamp > 0) {
            $date_display = esc_html(date_i18n(get_option('date_format'), $timestamp));
        }
    }

    if ('' === $date_display && is_string($date_override) && '' !== $date_override) {
        $date_display = sanitize_text_field($date_override);
    }

    if (!empty($version['changelog']) && is_array($version['changelog'])) {
        foreach ($version['changelog'] as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $entry = trim($entry);
            if ('' === $entry) {
                continue;
            }
            $changelog[] = $entry;
        }
    }

    if ($mod_id > 0) {
        if ($table_id <= 0 && '' !== $number && class_exists('GTA6Mods_Mod_Versions')) {
            $matched_version = GTA6Mods_Mod_Versions::get_version_by_mod_and_number($mod_id, $number);
            if (is_array($matched_version) && !empty($matched_version['id'])) {
                $table_id = (int) $matched_version['id'];
            }
        }

        if ($table_id > 0) {
            $download_url = esc_url(gta6_mods_get_waiting_room_url($mod_id, $table_id));
        } elseif (!empty($version['source']) && is_array($version['source'])) {
            $source_type = isset($version['source']['type']) ? strtolower((string) $version['source']['type']) : '';
            if ('external' === $source_type && $legacy_id > 0) {
                $download_url = esc_url(
                    gta6_mods_get_waiting_room_url(
                        $mod_id,
                        $legacy_id,
                        [
                            'external_type'   => 'version',
                            'external_target' => $legacy_id,
                        ]
                    )
                );
            }
        }
    }

    $display_id = $table_id > 0 ? $table_id : $legacy_id;

    return [
        'id'                => $display_id,
        'number'            => $number,
        'download_url'      => $download_url,
        'size_human'        => $size_human,
        'downloads'         => $downloads,
        'downloads_display' => number_format_i18n($downloads),
        'date'              => $date_display,
        'raw_date'          => $date_raw,
        'changelog'         => $changelog,
        'source'            => isset($version['source']) && is_array($version['source']) ? $version['source'] : [],
        'is_initial'        => !empty($version['is_initial']),
        'virus_scan_url'    => isset($version['virus_scan_url']) ? esc_url($version['virus_scan_url']) : '',
        'mod_id'            => $mod_id,
        'table_id'          => $table_id,
        'legacy_post_id'    => $legacy_id,
    ];
}

/**
 * Converts a wp_mod_versions row into the display format.
 *
 * @param array $row Version row.
 *
 * @return array
 */
function gta6_mods_lookup_version_scan_url(int $version_id, int $mod_id = 0): string {
    static $cache = [];

    if ($version_id <= 0) {
        return '';
    }

    if (isset($cache[$version_id])) {
        return $cache[$version_id];
    }

    $cache[$version_id] = '';

    global $wpdb;

    $meta_table = $wpdb->postmeta;
    $version_post_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM {$meta_table} WHERE meta_key = %s AND meta_value = %d LIMIT 1",
            '_gta6mods_version_table_id',
            $version_id
        )
    );

    if ($version_post_id > 0) {
        $scan_url = get_post_meta($version_post_id, '_gta6mods_version_scan_url', true);
        if (is_string($scan_url)) {
            $scan_url = trim($scan_url);
            if ('' !== $scan_url) {
                $is_valid = false;
                if (function_exists('wp_http_validate_url')) {
                    $is_valid = (bool) wp_http_validate_url($scan_url);
                }
                if (!$is_valid && filter_var($scan_url, FILTER_VALIDATE_URL)) {
                    $is_valid = true;
                }

                if ($is_valid) {
                    $cache[$version_id] = esc_url_raw($scan_url);
                }
            }
        }
    }

    if ('' === $cache[$version_id] && $mod_id > 0) {
        $legacy_scan = get_post_meta($mod_id, '_gta6mods_version_scan_url_' . $version_id, true);
        if (is_string($legacy_scan)) {
            $legacy_scan = trim($legacy_scan);
            if ('' !== $legacy_scan) {
                $is_valid = false;
                if (function_exists('wp_http_validate_url')) {
                    $is_valid = (bool) wp_http_validate_url($legacy_scan);
                }
                if (!$is_valid && filter_var($legacy_scan, FILTER_VALIDATE_URL)) {
                    $is_valid = true;
                }

                if ($is_valid) {
                    $cache[$version_id] = esc_url_raw($legacy_scan);
                }
            }
        }
    }

    return $cache[$version_id];
}

function gta6_mods_prepare_version_from_table(array $row) {
    $mod_id        = isset($row['mod_id']) ? (int) $row['mod_id'] : 0;
    $version_id    = isset($row['id']) ? (int) $row['id'] : 0;
    $attachment_id = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
    $size_human    = '';

    if ($attachment_id > 0) {
        $stored_size = (int) get_post_meta($attachment_id, '_filesize', true);
        if ($stored_size <= 0) {
            $path = get_attached_file($attachment_id);
            if ($path && file_exists($path)) {
                $stored_size = (int) filesize($path);
            }
        }

        if ($stored_size > 0) {
            $size_human = size_format((float) $stored_size);
        }
    }

    $changelog = [];
    if (!empty($row['changelog'])) {
        foreach (preg_split('/\r?\n/', (string) $row['changelog']) as $entry) {
            $entry = trim(wp_strip_all_tags($entry));
            if ('' !== $entry) {
                $changelog[] = $entry;
            }
        }
    }

    $prepared = [
        'id'           => $version_id,
        'number'       => isset($row['version']) ? sanitize_text_field($row['version']) : '',
        'downloads'    => isset($row['download_count']) ? (int) $row['download_count'] : 0,
        'download_url' => $mod_id > 0 ? gta6_mods_get_waiting_room_url($mod_id, $version_id) : '',
        'size_human'   => $size_human,
        'date'         => isset($row['upload_date']) ? $row['upload_date'] : '',
        'changelog'    => $changelog,
        'source'       => [
            'type'          => 'file',
            'attachment_id' => $attachment_id,
            'size_human'    => $size_human,
        ],
        'is_initial'     => false,
        'virus_scan_url' => gta6_mods_lookup_version_scan_url($version_id, $mod_id),
        'mod_id'         => $mod_id,
        'table_id'       => $version_id,
    ];

    if (!empty($row['is_deprecated'])) {
        $prepared['is_deprecated'] = true;
    }

    return gta6_mods_prepare_version_for_display($prepared);
}

function gta6_mods_get_pending_versions_for_display($post_id) {
    if (!function_exists('gta6mods_get_pending_updates_for_mod')) {
        return [];
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $pending_versions = [];
    $can_view_pending = function_exists('gta6mods_user_can_view_pending_updates')
        ? gta6mods_user_can_view_pending_updates($post_id)
        : false;

    $pending_updates = gta6mods_get_pending_updates_for_mod(
        $post_id,
        [
            'orderby' => 'date',
            'order'   => 'DESC',
        ]
    );

    if (empty($pending_updates)) {
        $has_pending = function_exists('gta6mods_mod_has_pending_update')
            ? gta6mods_mod_has_pending_update($post_id)
            : false;

        if (!$can_view_pending && $has_pending) {
            $pending_versions[] = [
                'id'                => 0,
                'number'            => __('Pending', 'gta6-mods'),
                'download_url'      => '',
                'size_human'        => '',
                'downloads'         => 0,
                'downloads_display' => '',
                'date'              => '',
                'raw_date'          => '',
                'changelog'         => [],
                'source'            => [],
                'is_initial'        => false,
                'virus_scan_url'    => '',
                'is_pending'        => true,
            ];
        }

        return $pending_versions;
    }

    foreach ($pending_updates as $update_id) {
        $update_id = (int) $update_id;
        if ($update_id <= 0) {
            continue;
        }

        $version_number = get_post_meta($update_id, '_gta6mods_update_version_number', true);
        $version_number = is_string($version_number) ? trim($version_number) : '';

        $version_source = get_post_meta($update_id, '_gta6mods_update_version_source', true);
        if (!is_array($version_source) || empty($version_source)) {
            continue;
        }

        $raw_changelog = get_post_meta($update_id, '_gta6mods_update_changelog', true);
        if (function_exists('gta6mods_normalize_changelog')) {
            $normalized_changelog = gta6mods_normalize_changelog($raw_changelog);
        } else {
            $normalized_changelog = [];
            if (is_array($raw_changelog)) {
                foreach ($raw_changelog as $entry) {
                    if (!is_string($entry)) {
                        continue;
                    }
                    $entry = trim(wp_strip_all_tags($entry));
                    if ('' !== $entry) {
                        $normalized_changelog[] = $entry;
                    }
                }
            }
        }

        $submission_iso = '';

        if (function_exists('get_post_datetime')) {
            $submission_datetime = get_post_datetime($update_id, 'date');
            if ($submission_datetime instanceof DateTimeInterface) {
                $submission_iso = $submission_datetime->format('c');
            }
        }

        if ('' === $submission_iso) {
            $submission_iso = get_post_time('c', true, $update_id);
        }

        if ('' === $submission_iso) {
            $post_date = get_post_field('post_date', $update_id);
            if ($post_date) {
                $submission_iso = mysql2date('c', $post_date, false);
            }
        }

        $submission_timestamp = false;

        if (function_exists('get_post_timestamp')) {
            $timestamp = get_post_timestamp($update_id, 'date');
            if (false !== $timestamp && $timestamp > 0) {
                $submission_timestamp = (int) $timestamp;
            }
        }

        if (!$submission_timestamp) {
            $timestamp = get_post_time('U', true, $update_id);
            if ($timestamp) {
                $submission_timestamp = (int) $timestamp;
            }
        }

        if ('' === $submission_iso && $submission_timestamp) {
            $submission_iso = gmdate('c', $submission_timestamp);
        }

        $submission_display = '';

        if ($submission_timestamp) {
            $submission_display = date_i18n(get_option('date_format'), $submission_timestamp);
        } elseif ($submission_iso) {
            $iso_timestamp = strtotime($submission_iso);
            if ($iso_timestamp) {
                $submission_display = date_i18n(get_option('date_format'), $iso_timestamp);
            }
        }

        $prepared = gta6_mods_prepare_version_for_display(
            [
                'id'           => $update_id,
                'number'       => '' !== $version_number ? $version_number : __('Ismeretlen verzió', 'gta6-mods'),
                'changelog'    => $normalized_changelog,
                'downloads'    => 0,
                'source'       => [],
                'download_url' => '',
                'size_human'   => '',
                'date'         => $submission_iso,
                'display_date' => $submission_display,
                'is_initial'   => false,
                'virus_scan_url' => '',
            ]
        );

        if (empty($prepared)) {
            continue;
        }

        $prepared['is_pending'] = true;

        if (!isset($prepared['sort_timestamp']) || (int) $prepared['sort_timestamp'] <= 0) {
            if ($submission_timestamp) {
                $prepared['sort_timestamp'] = (int) $submission_timestamp;
            } elseif ($submission_iso) {
                $iso_timestamp = strtotime($submission_iso);
                $prepared['sort_timestamp'] = $iso_timestamp && $iso_timestamp > 0 ? (int) $iso_timestamp : time();
            } else {
                $prepared['sort_timestamp'] = time();
            }
        }

        $pending_versions[] = $prepared;
    }

    return $pending_versions;
}

function gta6_mods_get_mod_versions_for_display($post_id) {
    $post_id = (int) $post_id;

    $pending_versions = gta6_mods_get_pending_versions_for_display($post_id);
    $version_map      = [];
    $number_lookup    = [];

    if ($post_id > 0) {
        $rows = GTA6Mods_Mod_Versions::get_versions_for_mod($post_id);
        foreach ($rows as $row) {
            if (!empty($row['is_deprecated'])) {
                continue;
            }

            $prepared = gta6_mods_prepare_version_from_table($row);
            if (empty($prepared)) {
                continue;
            }

            $table_id = isset($prepared['table_id']) ? (int) $prepared['table_id'] : 0;
            $key      = $table_id > 0 ? 'table:' . $table_id : 'table:' . md5(wp_json_encode($prepared));

            $version_map[$key] = $prepared;

            if (!empty($prepared['number'])) {
                $number_lookup[strtolower($prepared['number'])] = $key;
            }
        }
    }

    if (function_exists('gta6mods_get_mod_versions')) {
        foreach (gta6mods_get_mod_versions($post_id) as $legacy_version) {
            $prepared = gta6_mods_prepare_version_for_display($legacy_version);
            if (empty($prepared)) {
                continue;
            }

            $key = '';

            if (!empty($prepared['table_id'])) {
                $table_key = 'table:' . (int) $prepared['table_id'];
                if (isset($version_map[$table_key])) {
                    $key = $table_key;
                }
            }

            if ('' === $key && !empty($prepared['number'])) {
                $number_key = strtolower($prepared['number']);
                if (isset($number_lookup[$number_key])) {
                    $key = $number_lookup[$number_key];
                }
            }

            if ('' === $key) {
                if (!empty($prepared['legacy_post_id'])) {
                    $key = 'legacy:' . (int) $prepared['legacy_post_id'];
                } elseif (!empty($prepared['number'])) {
                    $key = 'number:' . strtolower($prepared['number']);
                } else {
                    $key = 'legacy:' . md5(wp_json_encode($prepared));
                }
            }

            if (isset($version_map[$key])) {
                $version_map[$key] = gta6_mods_merge_version_records($version_map[$key], $prepared);
            } else {
                $version_map[$key] = $prepared;
            }

            if (!empty($prepared['number']) && !isset($number_lookup[strtolower($prepared['number'])])) {
                $number_lookup[strtolower($prepared['number'])] = $key;
            }
        }
    }

    $final_versions = [];
    foreach ($version_map as $record) {
        $final_versions[] = gta6_mods_finalize_version_record($record, $post_id);
    }

    usort($final_versions, 'gta6_mods_compare_versions_desc');

    if (!empty($pending_versions)) {
        usort($pending_versions, 'gta6_mods_compare_versions_desc');
        $final_versions = array_merge($pending_versions, $final_versions);
    }

    return $final_versions;
}

function gta6_mods_merge_version_records(array $primary, array $secondary): array {
    if (empty($primary)) {
        return $secondary;
    }

    if (empty($secondary)) {
        return $primary;
    }

    $primary['downloads'] = isset($primary['downloads']) ? (int) $primary['downloads'] : 0;
    $secondary_downloads  = isset($secondary['downloads']) ? (int) $secondary['downloads'] : 0;
    if ($secondary_downloads > $primary['downloads']) {
        $primary['downloads'] = $secondary_downloads;
    }

    if (empty($primary['downloads_display'])) {
        $primary['downloads_display'] = number_format_i18n($primary['downloads']);
    }

    if (!empty($secondary['downloads_display']) && $secondary_downloads >= $primary['downloads']) {
        $primary['downloads_display'] = $secondary['downloads_display'];
    }

    if (empty($primary['number']) && !empty($secondary['number'])) {
        $primary['number'] = $secondary['number'];
    }

    if (empty($primary['size_human']) && !empty($secondary['size_human'])) {
        $primary['size_human'] = $secondary['size_human'];
    }

    if (!empty($secondary['virus_scan_url']) && empty($primary['virus_scan_url'])) {
        $primary['virus_scan_url'] = $secondary['virus_scan_url'];
    }

    $primary_sort   = isset($primary['sort_timestamp']) ? (int) $primary['sort_timestamp'] : 0;
    $secondary_sort = isset($secondary['sort_timestamp']) ? (int) $secondary['sort_timestamp'] : 0;
    if ($secondary_sort > $primary_sort) {
        $primary['sort_timestamp'] = $secondary_sort;
        $primary_sort              = $secondary_sort;
    }

    $secondary_raw = isset($secondary['raw_date']) ? (string) $secondary['raw_date'] : '';
    if ('' !== $secondary_raw) {
        $secondary_raw_ts = strtotime($secondary_raw);
        $primary_raw      = isset($primary['raw_date']) ? (string) $primary['raw_date'] : '';
        $primary_raw_ts   = $primary_raw ? strtotime($primary_raw) : false;

        if ('' === $primary_raw || ($secondary_raw_ts && (!$primary_raw_ts || $secondary_raw_ts > $primary_raw_ts))) {
            $primary['raw_date'] = $secondary_raw;
            if ($secondary_raw_ts && $secondary_raw_ts > 0 && $secondary_raw_ts > $primary_sort) {
                $primary['sort_timestamp'] = $secondary_raw_ts;
                $primary_sort              = $secondary_raw_ts;
            }
        }
    }

    if (!empty($secondary['date']) && empty($primary['date'])) {
        $primary['date'] = $secondary['date'];
        $date_ts         = strtotime((string) $secondary['date']);
        if ($date_ts && $date_ts > 0 && $date_ts > $primary_sort) {
            $primary['sort_timestamp'] = $date_ts;
        }
    }

    if (!empty($secondary['mod_id']) && empty($primary['mod_id'])) {
        $primary['mod_id'] = (int) $secondary['mod_id'];
    }

    if (!empty($secondary['is_initial'])) {
        $primary['is_initial'] = true;
    }

    if (!empty($secondary['legacy_post_id'])) {
        $primary['legacy_post_id'] = (int) $secondary['legacy_post_id'];
    }

    $primary_changelog   = isset($primary['changelog']) && is_array($primary['changelog']) ? $primary['changelog'] : [];
    $secondary_changelog = isset($secondary['changelog']) && is_array($secondary['changelog']) ? $secondary['changelog'] : [];
    $primary['changelog'] = gta6_mods_merge_changelog_entries($primary_changelog, $secondary_changelog);

    if (!isset($primary['source']) || !is_array($primary['source'])) {
        $primary['source'] = [];
    }

    if (!empty($secondary['source']) && is_array($secondary['source'])) {
        $primary['source'] = array_merge($secondary['source'], $primary['source']);
    }

    if (!empty($secondary['table_id']) && empty($primary['table_id'])) {
        $primary['table_id'] = (int) $secondary['table_id'];
    }

    if (!empty($secondary['download_url']) && empty($primary['download_url'])) {
        $primary['download_url'] = $secondary['download_url'];
    }

    $primary['downloads_display'] = number_format_i18n($primary['downloads']);

    return $primary;
}

function gta6_mods_merge_changelog_entries(array $primary, array $secondary = []): array {
    $combined   = array_merge($primary, $secondary);
    $normalized = [];

    foreach ($combined as $entry) {
        if (!is_string($entry)) {
            continue;
        }

        $entry = trim(wp_strip_all_tags($entry));
        if ('' === $entry) {
            continue;
        }

        $normalized[$entry] = $entry;
    }

    return array_values($normalized);
}

function gta6_mods_finalize_version_record(array $record, int $post_id = 0): array {
    $record['downloads'] = isset($record['downloads']) ? (int) $record['downloads'] : 0;
    $record['downloads_display'] = number_format_i18n($record['downloads']);

    $table_id = isset($record['table_id']) ? (int) $record['table_id'] : 0;
    if ($table_id > 0) {
        $record['id'] = $table_id;
        $mod_context   = $post_id > 0 ? $post_id : (isset($record['mod_id']) ? (int) $record['mod_id'] : 0);

        if ($mod_context > 0 && empty($record['download_url'])) {
            $record['download_url'] = gta6_mods_get_waiting_room_url($mod_context, $table_id);
        }
    } elseif (!empty($record['source']) && is_array($record['source'])) {
        $source_type = isset($record['source']['type']) ? strtolower((string) $record['source']['type']) : '';
        $legacy_id   = isset($record['legacy_post_id']) ? (int) $record['legacy_post_id'] : 0;
        $mod_context = $post_id > 0 ? $post_id : (isset($record['mod_id']) ? (int) $record['mod_id'] : 0);

        if ('external' === $source_type && $mod_context > 0) {
            $target_id = $legacy_id > 0 ? $legacy_id : $mod_context;
            $record['download_url'] = gta6_mods_get_waiting_room_url(
                $mod_context,
                $target_id,
                [
                    'external_type'   => $legacy_id > 0 ? 'version' : 'mod',
                    'external_target' => $target_id,
                ]
            );
        }
    }

    if (!isset($record['changelog']) || !is_array($record['changelog'])) {
        $record['changelog'] = [];
    } else {
        $record['changelog'] = gta6_mods_merge_changelog_entries($record['changelog']);
    }

    $record['sort_timestamp'] = isset($record['sort_timestamp']) ? (int) $record['sort_timestamp'] : 0;

    $raw_date = isset($record['raw_date']) ? (string) $record['raw_date'] : '';
    if ('' !== $raw_date) {
        $timestamp = strtotime($raw_date);
        if ($timestamp && $timestamp > 0) {
            if (empty($record['date'])) {
                $record['date'] = date_i18n(get_option('date_format'), $timestamp);
            }
            if ($record['sort_timestamp'] < $timestamp) {
                $record['sort_timestamp'] = $timestamp;
            }
        } else {
            $record['raw_date'] = '';
        }
    }

    if (!empty($record['date'])) {
        $display_timestamp = strtotime($record['date']);
        if ($display_timestamp && $display_timestamp > 0 && $record['sort_timestamp'] < $display_timestamp) {
            $record['sort_timestamp'] = $display_timestamp;
        }
    }

    return $record;
}

function gta6_mods_get_version_sort_timestamp(array $record): int {
    if (isset($record['sort_timestamp'])) {
        $timestamp = (int) $record['sort_timestamp'];
        if ($timestamp > 0) {
            return $timestamp;
        }
    }

    $raw_date = isset($record['raw_date']) ? (string) $record['raw_date'] : '';
    if ('' !== $raw_date) {
        $timestamp = strtotime($raw_date);
        if ($timestamp && $timestamp > 0) {
            return (int) $timestamp;
        }
    }

    $display_date = isset($record['date']) ? (string) $record['date'] : '';
    if ('' !== $display_date) {
        $timestamp = strtotime($display_date);
        if ($timestamp && $timestamp > 0) {
            return (int) $timestamp;
        }
    }

    return 0;
}

function gta6_mods_compare_versions_desc(array $a, array $b): int {
    $a_timestamp = gta6_mods_get_version_sort_timestamp($a);
    $b_timestamp = gta6_mods_get_version_sort_timestamp($b);

    if ($a_timestamp === $b_timestamp) {
        $a_id = isset($a['id']) ? (int) $a['id'] : 0;
        $b_id = isset($b['id']) ? (int) $b['id'] : 0;

        if ($a_id === $b_id) {
            return 0;
        }

        return ($a_id < $b_id) ? 1 : -1;
    }

    return ($a_timestamp < $b_timestamp) ? 1 : -1;
}

function gta6_mods_get_author_other_mods($author_id, $exclude_mod_id = 0, $limit = 4) {
    $author_id      = (int) $author_id;
    $exclude_mod_id = (int) $exclude_mod_id;
    $limit          = max(1, (int) $limit);

    if ($author_id <= 0) {
        return [];
    }

    $cache_group = 'gta6mods_waiting_room';
    $cache_key   = implode('_', ['author', $author_id, $exclude_mod_id, $limit]);

    $cached = wp_cache_get($cache_key, $cache_group);
    if (false !== $cached) {
        return $cached;
    }

    $transient_key = 'gta6mods_author_wait_' . md5($cache_key);
    $transient     = get_transient($transient_key);
    if (false !== $transient && is_array($transient)) {
        wp_cache_set($cache_key, $transient, $cache_group, 6 * HOUR_IN_SECONDS);
        return $transient;
    }

    $query_args = [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'author'              => $author_id,
        'posts_per_page'      => $limit,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'fields'              => 'ids',
    ];

    if ($exclude_mod_id > 0) {
        $query_args['post__not_in'] = [$exclude_mod_id];
    }

    $query = new WP_Query($query_args);

    $mods = [];
    if (!empty($query->posts)) {
        foreach ($query->posts as $mod_id) {
            $mod_id = (int) $mod_id;
            if ($mod_id <= 0) {
                continue;
            }

            $title     = get_the_title($mod_id);
            $permalink = get_permalink($mod_id);
            $thumbnail = get_the_post_thumbnail_url($mod_id, 'medium_large');
            if (!$thumbnail) {
                $thumbnail = gta6_mods_get_placeholder('card');
            }

            $category_name = get_post_meta($mod_id, GTA6_MODS_PRIMARY_CATEGORY_NAME_META_KEY, true);
            if (!is_string($category_name) || '' === $category_name) {
                $terms = get_the_terms($mod_id, 'category');
                if (is_array($terms) && !empty($terms)) {
                    $category_name = $terms[0]->name;
                } else {
                    $category_name = __('Mods', 'gta6-mods');
                }
            }

            $mods[] = [
                'id'        => $mod_id,
                'title'     => $title,
                'permalink' => $permalink,
                'thumbnail' => $thumbnail,
                'category'  => $category_name,
            ];
        }
    }

    wp_cache_set($cache_key, $mods, $cache_group, 6 * HOUR_IN_SECONDS);
    set_transient($transient_key, $mods, 6 * HOUR_IN_SECONDS);

    return $mods;
}

function gta6_mods_get_current_version_for_display($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $latest = GTA6Mods_Mod_Versions::get_latest_version($post_id);
    if ($latest) {
        return gta6_mods_prepare_version_from_table($latest);
    }

    if (function_exists('gta6mods_get_current_version')) {
        if (function_exists('gta6mods_ensure_initial_version_exists')) {
            gta6mods_ensure_initial_version_exists($post_id);
        }

        $current = gta6mods_get_current_version($post_id);
        return gta6_mods_prepare_version_for_display($current);
    }

    return [];
}

function gta6_mods_format_time_ago($timestamp) {
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return '';
    }

    $current_time = current_time('timestamp');
    if ($current_time <= $timestamp) {
        return esc_html__('just now', 'gta6-mods');
    }

    $diff = human_time_diff($timestamp, $current_time);

    return sprintf(esc_html__('%s ago', 'gta6-mods'), $diff);
}

function gta6_mods_get_user_like_status($post_id) {
    if (!is_user_logged_in()) {
        return false;
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    $user_id     = get_current_user_id();
    $liked_users = get_post_meta($post_id, '_gta6mods_liked_users', true);

    if (!is_array($liked_users)) {
        return false;
    }

    foreach ($liked_users as $liked_user_id) {
        if ((int) $liked_user_id === $user_id) {
            return true;
        }
    }

    return false;
}

function gta6_mods_get_user_rating($post_id) {
    if (!is_user_logged_in()) {
        return 0;
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    $user_id = get_current_user_id();
    $ratings = get_post_meta($post_id, '_gta6mods_ratings', true);

    if (!is_array($ratings)) {
        return 0;
    }

    return isset($ratings[$user_id]) ? max(0, min(5, (int) $ratings[$user_id])) : 0;
}

function gta6_mods_get_like_count($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    return (int) gta6mods_get_mod_stat($post_id, 'likes');
}

function gta6_mods_get_user_bookmarked_mod_ids($user_id) {
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return [];
    }

    $bookmarks = get_user_meta($user_id, GTA6_MODS_BOOKMARK_META_KEY, true);

    if (!is_array($bookmarks)) {
        return [];
    }

    $bookmarks = array_map('absint', $bookmarks);
    $bookmarks = array_filter($bookmarks);

    return array_values(array_unique($bookmarks));
}

function gta6_mods_is_mod_bookmarked_by_user($post_id, $user_id = 0) {
    $post_id = (int) $post_id;

    if ($post_id <= 0) {
        return false;
    }

    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }

    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return false;
    }

    $bookmarks = gta6_mods_get_user_bookmarked_mod_ids($user_id);

    return in_array($post_id, $bookmarks, true);
}

function gta6_mods_get_rating_data($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [
            'average' => 0.0,
            'count'   => 0,
        ];
    }

    $stats   = gta6mods_get_mod_stats($post_id);
    $average = isset($stats['rating_average']) ? (float) $stats['rating_average'] : 0.0;
    $count   = isset($stats['rating_count']) ? (int) $stats['rating_count'] : 0;

    if ($average <= 0) {
        $legacy_average = get_post_meta($post_id, '_rating_average', true);
        if ('' !== $legacy_average && null !== $legacy_average) {
            $average = (float) $legacy_average;
        }
    }

    if ($count <= 0) {
        $legacy_count = get_post_meta($post_id, '_rating_count', true);
        if ('' !== $legacy_count && null !== $legacy_count) {
            $count = max(0, (int) $legacy_count);
        }
    }

    return [
        'average' => $average,
        'count'   => $count,
    ];
}

/**
 * Suggests related searches for zero-result pages.
 *
 * @param string $query Original search query.
 * @return array<int, array<string, string>>
 */
function gta6mods_get_related_searches($query) {
    $query = is_string($query) ? wp_strip_all_tags($query) : '';
    $query = trim($query);

    if ('' === $query) {
        return [];
    }

    $normalized_query = function_exists('mb_strtolower')
        ? mb_strtolower($query, 'UTF-8')
        : strtolower($query);

    $words = preg_split('/[\s\+\-_,]+/u', $normalized_query);
    $words = array_values(array_filter($words, static function ($word) {
        if (!is_string($word)) {
            return false;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);
        return $length >= 2;
    }));

    if (empty($words)) {
        $words = [$normalized_query];
    }

    $suggestions = [];
    $seen = [];
    $max = 5;

    foreach ($words as $word) {
        $tag_terms = get_terms([
            'taxonomy'   => 'post_tag',
            'search'     => $word,
            'hide_empty' => true,
            'number'     => 3,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if (!is_wp_error($tag_terms)) {
            foreach ($tag_terms as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }

                $key = 'tag-' . (int) $term->term_id;
                if (isset($seen[$key])) {
                    continue;
                }

                $link = get_term_link($term);
                if (is_wp_error($link) || empty($link)) {
                    continue;
                }

                $suggestions[] = [
                    'label' => $term->name,
                    'url'   => $link,
                ];
                $seen[$key] = true;

                if (count($suggestions) >= $max) {
                    return $suggestions;
                }
            }
        }

        $category_terms = get_terms([
            'taxonomy'   => 'category',
            'search'     => $word,
            'hide_empty' => true,
            'number'     => 3,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if (!is_wp_error($category_terms)) {
            foreach ($category_terms as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }

                $key = 'category-' . (int) $term->term_id;
                if (isset($seen[$key])) {
                    continue;
                }

                $link = get_term_link($term);
                if (is_wp_error($link) || empty($link)) {
                    continue;
                }

                $suggestions[] = [
                    'label' => sprintf(
                        /* translators: %s: category name */
                        __('Category: %s', 'gta6-mods'),
                        $term->name
                    ),
                    'url'   => $link,
                ];
                $seen[$key] = true;

                if (count($suggestions) >= $max) {
                    return $suggestions;
                }
            }
        }
    }

    foreach ($words as $word) {
        $key = 'search-' . md5($word);
        if (isset($seen[$key])) {
            continue;
        }

        $link = get_search_link($word);
        if (empty($link) || is_wp_error($link)) {
            continue;
        }

        $label = function_exists('mb_convert_case')
            ? mb_convert_case($word, MB_CASE_TITLE, 'UTF-8')
            : ucwords($word);

        $suggestions[] = [
            'label' => $label,
            'url'   => $link,
        ];
        $seen[$key] = true;

        if (count($suggestions) >= $max) {
            return $suggestions;
        }
    }

    if (count($suggestions) < $max && function_exists('gta6mods_get_default_search_terms')) {
        foreach (gta6mods_get_default_search_terms() as $term) {
            $term = trim((string) $term);
            if ('' === $term) {
                continue;
            }

            $key = 'default-' . md5(mb_strtolower($term, 'UTF-8'));
            if (isset($seen[$key])) {
                continue;
            }

            $link = get_search_link($term);
            if (empty($link) || is_wp_error($link)) {
                continue;
            }

            $suggestions[] = [
                'label' => $term,
                'url'   => $link,
            ];
            $seen[$key] = true;

            if (count($suggestions) >= $max) {
                break;
            }
        }
    }

    return array_slice($suggestions, 0, $max);
}
