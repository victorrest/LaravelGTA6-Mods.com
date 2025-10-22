<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wp_query;

$raw_query = get_search_query(false);
$search_query = is_string($raw_query) ? trim(wp_strip_all_tags($raw_query)) : '';
$results_count = (int) $wp_query->found_posts;
$search_length = function_exists('mb_strlen') ? mb_strlen($search_query, 'UTF-8') : strlen($search_query);
$word_tokens = preg_split('/[\s\+\-]+/u', $search_query, -1, PREG_SPLIT_NO_EMPTY);
$word_count = is_array($word_tokens) ? count($word_tokens) : 0;
$has_special = (bool) preg_match('/[^a-zA-Z0-9\s\-\+]/u', $search_query);

$should_index = (
    $results_count >= 5 &&
    $search_length >= 3 &&
    $search_length <= 50 &&
    !$has_special &&
    $word_count <= 5
);

$formatted_count = number_format_i18n($results_count);
$fallback_query = '' !== $search_query ? $search_query : __('összes', 'gta6-mods');
$page_title = sprintf(
    /* translators: 1: search term, 2: result count */
    __('%1$s - GTA 6 Mod Search Results (%2$s found) | GTA6-Mods.com', 'gta6-mods'),
    $fallback_query,
    $formatted_count
);

if ($results_count > 0) {
    $meta_description = sprintf(
        /* translators: 1: result count, 2: search term */
        __('Found %1$s GTA 6 mods matching "%2$s". Download high-quality %2$s mods, tested and verified by our community.', 'gta6-mods'),
        $formatted_count,
        $fallback_query
    );
    $seo_subtitle = sprintf(
        /* translators: %s: search term */
        __('Top GTA 6 mods for "%s" curated by our players.', 'gta6-mods'),
        $fallback_query
    );
} else {
    $meta_description = sprintf(
        /* translators: %s: search term */
        __('No GTA 6 mods matched "%s". Try a different keyword or explore our trending downloads.', 'gta6-mods'),
        $fallback_query
    );
    $seo_subtitle = sprintf(
        /* translators: %s: search term */
        __('No direct matches for "%s" — discover related GTA 6 mods below.', 'gta6-mods'),
        $fallback_query
    );
}

add_filter(
    'gta6mods_archive_seo_data',
    function ($seo_data, $query) use ($page_title, $meta_description, $seo_subtitle, $should_index, $search_query, $formatted_count) {
        if (!($query instanceof WP_Query) || !$query->is_search()) {
            return $seo_data;
        }

        $seo_data['title'] = $page_title;
        $seo_data['description'] = $meta_description;
        $seo_data['og_title'] = $page_title;
        $seo_data['og_description'] = $meta_description;
        $seo_data['subtitle'] = $seo_subtitle;
        $seo_data['h1'] = sprintf(
            /* translators: 1: search term, 2: result count */
            __('Search results for "%1$s" (%2$s)', 'gta6-mods'),
            $search_query,
            $formatted_count
        );

        if ($should_index) {
            $seo_data['noindex'] = false;
            $seo_data['robots'] = 'index, follow';
        } else {
            $seo_data['noindex'] = true;
            $seo_data['robots'] = 'noindex, follow';
        }

        return $seo_data;
    },
    10,
    2
);

get_header();

$mods = gta6_mods_collect_archive_posts($wp_query);
$wp_query->set('gta6mods_prepared_mods', $mods);
$hero_meta = [];

$hero_meta[] = sprintf(
    _n('%s találat', '%s találat', $results_count, 'gta6-mods'),
    $formatted_count
);

$hero_meta[] = gta6_mods_format_updated_label($mods);

$subtitle = esc_html__('Itt találod a keresésednek megfelelő modokat és híreket.', 'gta6-mods');
if (0 === $results_count) {
    $subtitle = esc_html__('Sajnos nincs találat, de ezek a keresések segíthetnek.', 'gta6-mods');
}

$chips = [];
if (0 === $results_count) {
    $related = gta6mods_get_related_searches($search_query);
    foreach ($related as $suggestion) {
        if (empty($suggestion['label']) || empty($suggestion['url'])) {
            continue;
        }

        $chips[] = [
            'label' => $suggestion['label'],
            'url'   => $suggestion['url'],
        ];
    }
}

$filters = gta6mods_get_archive_filter_state($wp_query);
$pagination = gta6_mods_build_pagination_model($wp_query);
$seo_data = gta6mods_get_archive_seo_data($wp_query);

get_template_part(
    'template-parts/archive/listing',
    null,
    [
        'title'         => sprintf(
            /* translators: %s: search query */
            esc_html__('Keresési eredmények: %s', 'gta6-mods'),
            $search_query ? esc_html($search_query) : esc_html__('összes', 'gta6-mods')
        ),
        'subtitle'      => $subtitle,
        'icon'          => 'fa-solid fa-magnifying-glass',
        'hero_meta'     => $hero_meta,
        'mods'          => $mods,
        'chips'         => $chips,
        'filters'       => $filters,
        'results_count' => $results_count,
        'pagination'    => $pagination,
        'seo_data'      => $seo_data,
    ]
);

get_footer();
