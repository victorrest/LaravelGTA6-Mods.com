<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $wp_query;

$mods = gta6_mods_collect_archive_posts($wp_query);
$wp_query->set('gta6mods_prepared_mods', $mods);
$results_count = (int) $wp_query->found_posts;
$hero_meta = [];

$hero_meta[] = sprintf(
    _n('%s bejegyzés', '%s bejegyzés', $results_count, 'gta6-mods'),
    number_format_i18n($results_count)
);

$hero_meta[] = gta6_mods_format_updated_label($mods);

$subtitle = get_the_archive_description();
$archive_title_plain = wp_strip_all_tags(get_the_archive_title());
$icon = 'fa-solid fa-folder-open';

if (is_category()) {
    $icon = 'fa-solid fa-car';
    if (empty($subtitle)) {
        $subtitle = sprintf(
            /* translators: %s: category name */
            esc_html__('Legutóbbi bejegyzések a(z) %s kategóriában.', 'gta6-mods'),
            $archive_title_plain
        );
    }
} elseif (is_tag()) {
    $icon = 'fa-solid fa-tag';
    if (empty($subtitle)) {
        $subtitle = sprintf(
            /* translators: %s: tag name */
            esc_html__('Legutóbbi bejegyzések a(z) %s címkével.', 'gta6-mods'),
            $archive_title_plain
        );
    }
} else {
    if (empty($subtitle)) {
        $subtitle = sprintf(
            /* translators: %s: archive title */
            esc_html__('Legutóbbi bejegyzések a(z) %s archívumban.', 'gta6-mods'),
            $archive_title_plain
        );
    }
}

$chips = [];

if (is_category()) {
    $chips = gta6_mods_build_category_filter_chips();
}
$filters = gta6mods_get_archive_filter_state($wp_query);
$pagination = gta6_mods_build_pagination_model($wp_query);
$seo_data = gta6mods_get_archive_seo_data($wp_query);

get_template_part(
    'template-parts/archive/listing',
    null,
    [
        'title'         => $archive_title_plain,
        'subtitle'      => $subtitle,
        'icon'          => $icon,
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
