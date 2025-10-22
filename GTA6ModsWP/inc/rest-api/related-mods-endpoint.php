<?php
/**
 * REST API endpoint for fetching related mods.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the REST API route for related mods.
 */
function gta6mods_register_related_mods_rest_route() {
    register_rest_route(
        'gta6-mods/v1',
        '/mod/(?P<id>\d+)/related',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_related_mods',
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                    'required' => true,
                ],
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_related_mods_rest_route');

/**
 * Handles the REST request for related mods using a cache-first strategy.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
 */
function gta6mods_rest_get_related_mods(WP_REST_Request $request) {
    $post_id = (int) $request['id'];
    $post_type = get_post_type($post_id);
    $allowed_types = function_exists('gta6mods_get_mod_post_types') ? gta6mods_get_mod_post_types() : ['post'];

    if ($post_id <= 0 || !in_array($post_type, $allowed_types, true)) {
        return gta6mods_rest_prepare_response([], $request, [
            'public' => true,
        ]);
    }

    $prepared_mods = gta6mods_get_related_mods_data($post_id);

    $last_modified = (int) get_post_modified_time('U', true, $post_id);
    if ($last_modified <= 0) {
        $last_modified = time();
    }

    $posts_last_changed = wp_cache_get_last_changed('posts');
    $etag_suffix        = is_string($posts_last_changed) ? $posts_last_changed : (string) $last_modified;
    $etag_value         = sprintf('related-%d-%s', $post_id, $etag_suffix);

    return gta6mods_rest_prepare_response(
        $prepared_mods,
        $request,
        [
            'public'        => true,
            'last_modified' => $last_modified,
            'etag'          => $etag_value,
        ]
    );
}

/**
 * Builds the payload for the related mods REST response.
 *
 * @param int[] $related_ids Related post IDs.
 * @return array<int, array<string, mixed>>
 */
function gta6mods_prepare_related_mods_payload(array $related_ids) {
    $mods = [];
    $allowed_types = function_exists('gta6mods_get_mod_post_types') ? gta6mods_get_mod_post_types() : ['post'];

    if (empty($related_ids)) {
        return $mods;
    }

    update_postmeta_cache($related_ids);

    if (function_exists('gta6mods_prime_mod_stats')) {
        gta6mods_prime_mod_stats($related_ids);
    }

    foreach ($related_ids as $related_post_id) {
        $related_post_id = (int) $related_post_id;
        if ($related_post_id <= 0 || !in_array(get_post_type($related_post_id), $allowed_types, true)) {
            continue;
        }

        $author_id = (int) get_post_field('post_author', $related_post_id);

        $rating_data = [
            'average' => 0.0,
            'count'   => 0,
        ];

        if (function_exists('gta6_mods_get_rating_data')) {
            $rating_data = array_merge(
                $rating_data,
                (array) gta6_mods_get_rating_data($related_post_id)
            );
        }

        $downloads = function_exists('gta6_mods_get_download_count')
            ? (int) gta6_mods_get_download_count($related_post_id)
            : 0;

        $likes = function_exists('gta6_mods_get_like_count')
            ? (int) gta6_mods_get_like_count($related_post_id)
            : 0;

        $version_data = function_exists('gta6_mods_get_current_version_for_display')
            ? (array) gta6_mods_get_current_version_for_display($related_post_id)
            : [];

        $version_number = '';
        if (!empty($version_data['number'])) {
            $version_number = (string) $version_data['number'];
        } elseif (function_exists('gta6_mods_get_mod_version')) {
            $version_number = (string) gta6_mods_get_mod_version($related_post_id);
        }

        if (is_string($version_number)) {
            $version_number = trim($version_number);
        }

        $mods[] = [
            'id'        => $related_post_id,
            'title'     => wp_strip_all_tags(get_the_title($related_post_id)),
            'permalink' => esc_url_raw(get_permalink($related_post_id)),
            'thumbnail' => esc_url_raw(get_the_post_thumbnail_url($related_post_id, 'medium_large')),
            'author'    => $author_id > 0 ? wp_strip_all_tags(get_the_author_meta('display_name', $author_id)) : '',
            'rating'    => [
                'average' => isset($rating_data['average']) ? (float) $rating_data['average'] : 0.0,
                'count'   => isset($rating_data['count']) ? (int) $rating_data['count'] : 0,
            ],
            'metrics'   => [
                'downloads' => $downloads,
                'likes'     => $likes,
            ],
            'version'   => $version_number,
        ];
    }

    return $mods;
}

/**
 * Retrieves related mods data for a given post ID using the cached strategy.
 *
 * @param int $post_id Post ID.
 * @return array<int, array<string, mixed>>
 */
function gta6mods_get_related_mods_data($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== 'post') {
        return [];
    }

    $legacy_cache_key = 'related_mods_' . $post_id;
    delete_transient($legacy_cache_key);

    $cache_key      = 'related_mods_v2_' . $post_id;
    $cached_payload = get_transient($cache_key);

    if (false !== $cached_payload && isset($cached_payload['ids']) && is_array($cached_payload['ids'])) {
        return gta6mods_prepare_related_mods_payload($cached_payload['ids']);
    }

    $related_ids = [];
    $limit       = 4;

    $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
    if (!empty($tags)) {
        $tag_args = [
            'tag__in'             => $tags,
            'post__not_in'        => [$post_id],
            'posts_per_page'      => $limit,
            'ignore_sticky_posts' => 1,
            'no_found_rows'       => true,
            'fields'              => 'ids',
        ];
        $related_ids = get_posts($tag_args);
    }

    if (count($related_ids) < $limit) {
        $categories = wp_get_post_categories($post_id);
        if (!empty($categories)) {
            $cat_args = [
                'category__in'        => $categories,
                'post__not_in'        => array_merge([$post_id], $related_ids),
                'posts_per_page'      => $limit - count($related_ids),
                'ignore_sticky_posts' => 1,
                'no_found_rows'       => true,
                'fields'              => 'ids',
            ];
            $cat_ids     = get_posts($cat_args);
            $related_ids = array_merge($related_ids, $cat_ids);
        }
    }

    $related_ids = array_slice(array_unique(array_map('absint', $related_ids)), 0, $limit);

    $prepared_mods = gta6mods_prepare_related_mods_payload($related_ids);

    set_transient(
        $cache_key,
        [
            'ids'       => $related_ids,
            'generated' => time(),
        ],
        4 * HOUR_IN_SECONDS
    );

    return $prepared_mods;
}

/**
 * Clears the related mods cache when a post is saved or deleted.
 */
function gta6mods_clear_related_mods_cache($post_id) {
    if (get_post_type($post_id) === 'post') {
        delete_transient('related_mods_' . $post_id);
        delete_transient('related_mods_v2_' . $post_id);
    }
}
add_action('save_post', 'gta6mods_clear_related_mods_cache');
add_action('delete_post', 'gta6mods_clear_related_mods_cache');

/**
 * Clears related mods cache when key post meta values change.
 *
 * @param int    $meta_id    The meta ID.
 * @param int    $object_id  The object ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 */
function gta6mods_maybe_clear_related_mods_cache_on_meta($meta_id, $object_id, $meta_key, $meta_value) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
    if (get_post_type($object_id) !== 'post') {
        return;
    }

    $watched_meta_keys = [
        '_gta6mods_download_count',
        '_gta6mods_likes',
        '_gta6mods_rating_average',
        '_gta6mods_rating_count',
        '_thumbnail_id',
        '_gta6mods_mod_version',
        '_gta6mods_initial_version_number',
    ];

    if (in_array($meta_key, $watched_meta_keys, true)) {
        gta6mods_clear_related_mods_cache($object_id);
    }
}
add_action('updated_post_meta', 'gta6mods_maybe_clear_related_mods_cache_on_meta', 10, 4);
add_action('added_post_meta', 'gta6mods_maybe_clear_related_mods_cache_on_meta', 10, 4);
add_action('deleted_post_meta', 'gta6mods_maybe_clear_related_mods_cache_on_meta', 10, 4);
