<?php
/**
 * Dynamic SEO utilities for archive and search pages.
 *
 * Generates performant, context-aware metadata so every filter combination
 * surfaces high-quality titles, descriptions, social cards and structured data
 * without adding load to high-traffic listings.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns complete SEO data for current archive query.
 *
 * @param WP_Query|null $query Current query object.
 * @return array<string, mixed>
 */
function gta6mods_get_archive_seo_data($query = null) {
    static $runtime_cache = [];

    if (!$query instanceof WP_Query) {
        global $wp_query;
        $query = $wp_query;
    }

    if (!$query instanceof WP_Query || !gta6mods_is_filterable_archive_query($query)) {
        return [];
    }

    $signature = [
        'type'     => $query->is_category() ? 'category' : ($query->is_tag() ? 'tag' : 'search'),
        'term_id'  => ($query->get_queried_object() instanceof WP_Term) ? (int) $query->get_queried_object()->term_id : 0,
        'sort'     => gta6mods_normalize_archive_sort($query->get('gta_sort')),
        'since'    => gta6mods_normalize_archive_since($query->get('gta_since')),
        'tag'      => (string) $query->get('gta_tag'),
        'category' => (string) $query->get('gta_category'),
        'search'   => $query->is_search() ? (string) $query->get('s') : '',
        'page'     => max(1, (int) $query->get('paged')),
        'locale'   => get_locale(),
    ];

    $cache_key = 'seo_' . md5(wp_json_encode($signature));

    if (isset($runtime_cache[$cache_key])) {
        return $runtime_cache[$cache_key];
    }

    $cached = wp_cache_get($cache_key, 'gta6mods_seo');
    if (false !== $cached && is_array($cached)) {
        $runtime_cache[$cache_key] = $cached;
        return $cached;
    }

    $seo_data = gta6mods_generate_seo_data($query);
    $seo_data = apply_filters('gta6mods_archive_seo_data', $seo_data, $query);

    $runtime_cache[$cache_key] = $seo_data;
    wp_cache_set($cache_key, $seo_data, 'gta6mods_seo', HOUR_IN_SECONDS);

    return $seo_data;
}

/**
 * Generates the SEO payload for an archive context.
 *
 * @param WP_Query $query Query instance.
 * @return array<string, mixed>
 */
function gta6mods_generate_seo_data(WP_Query $query) {
    $mods = $query->get('gta6mods_prepared_mods');
    if (!is_array($mods)) {
        $mods = gta6_mods_collect_archive_posts($query);
        $query->set('gta6mods_prepared_mods', $mods);
    }

    $context = gta6mods_build_archive_seo_context($query, $mods);

    $title       = gta6mods_generate_dynamic_title($context);
    $description = gta6mods_generate_dynamic_description($context);
    $h1          = gta6mods_generate_archive_h1($context);
    $subtitle    = gta6mods_generate_archive_subtitle($context);

    $canonical = gta6mods_build_archive_filter_url(
        $query,
        $context['sort'],
        $context['since'],
        [],
        $context['page'] > 1 ? $context['page'] : null
    );

    $prev_url = '';
    if ($context['page'] > 1) {
        $prev_url = gta6mods_build_archive_filter_url(
            $query,
            $context['sort'],
            $context['since'],
            [],
            ($context['page'] - 1) > 1 ? $context['page'] - 1 : null
        );
    }

    $next_url = '';
    if ($context['page'] < $context['max_pages']) {
        $next_url = gta6mods_build_archive_filter_url(
            $query,
            $context['sort'],
            $context['since'],
            [],
            $context['page'] + 1
        );
    }

    $og_image = gta6mods_get_social_image($mods, $context);

    $og_title = $title;
    if ($context['count'] > 0 && '' !== $h1) {
        $og_title = sprintf(
            '%s – %s Available',
            $h1,
            number_format_i18n($context['count'])
        );
    }

    $jsonld = gta6mods_generate_jsonld_schemas(
        array_merge(
            $context,
            [
                'title'       => $title,
                'description' => $description,
                'canonical'   => $canonical,
                'h1'          => $h1,
            ]
        ),
        $mods
    );

    $preload_images = [];
    if (!empty($mods) && !empty($mods[0]['imageUrl'])) {
        $preload_images[] = $mods[0]['imageUrl'];
    }

    return [
        'title'         => $title,
        'description'   => $description,
        'og_title'      => $og_title,
        'og_description'=> $description,
        'og_image'      => $og_image,
        'canonical'     => $canonical,
        'h1'            => $h1,
        'subtitle'      => $subtitle,
        'jsonld'        => $jsonld,
        'breadcrumbs'   => $context['breadcrumbs'],
        'noindex'       => $context['page'] > 1,
        'robots'        => $context['page'] > 1 ? 'noindex, follow' : 'index, follow',
        'prev_url'      => $prev_url,
        'next_url'      => $next_url,
        'count'         => $context['count'],
        'preload'       => $preload_images,
        'context'       => $context,
    ];
}

/**
 * Creates a context array from the current archive query.
 *
 * @param WP_Query             $query Query instance.
 * @param array<int, array>    $mods  Prepared mod cards.
 * @return array<string, mixed>
 */
function gta6mods_build_archive_seo_context(WP_Query $query, array $mods) {
    $type = 'search';
    if ($query->is_category()) {
        $type = 'category';
    } elseif ($query->is_tag()) {
        $type = 'tag';
    }

    $default_sort  = gta6mods_get_default_archive_sort();
    $default_since = gta6mods_get_default_archive_since();

    $sort  = gta6mods_normalize_archive_sort($query->get('gta_sort'));
    $since = gta6mods_normalize_archive_since($query->get('gta_since'));

    $sort_meta  = gta6mods_get_archive_sort_metadata($sort);
    $since_meta = gta6mods_get_archive_since_metadata($since);

    $term = $query->get_queried_object();

    $category_term = null;
    $tag_terms     = [];

    if ('category' === $type && $term instanceof WP_Term) {
        $category_term = $term;
        $tag_filter    = $query->get('gta_tag_filter');
        if (is_array($tag_filter) && !empty($tag_filter['terms'])) {
            foreach ((array) $tag_filter['terms'] as $tag_term) {
                if ($tag_term instanceof WP_Term) {
                    $tag_terms[] = $tag_term;
                }
            }
        }
    } elseif ('tag' === $type) {
        if ($term instanceof WP_Term) {
            $tag_terms[] = $term;
        }
        $category_filter = $query->get('gta_category_filter');
        if (is_array($category_filter) && $category_filter['term'] instanceof WP_Term) {
            $category_term = $category_filter['term'];
        }
    } else {
        $category_filter = $query->get('gta_category_filter');
        if (is_array($category_filter) && $category_filter['term'] instanceof WP_Term) {
            $category_term = $category_filter['term'];
        }
    }

    $category_name = '';
    $category_slug = '';
    $category_desc = '';
    if ($category_term instanceof WP_Term) {
        $category_name = $category_term->name;
        $category_slug = $category_term->slug;
        $category_desc = term_description($category_term);
    } elseif ('category' === $type && $term instanceof WP_Term) {
        $category_name = $term->name;
        $category_slug = $term->slug;
        $category_desc = term_description($term);
    }

    $tag_names = [];
    foreach ($tag_terms as $tag_term) {
        if ($tag_term instanceof WP_Term) {
            $tag_names[] = $tag_term->name;
        }
    }
    $tag_names = array_values(array_unique(array_filter($tag_names, 'strlen')));

    $tag_strings = gta6mods_format_tag_strings($tag_names);

    $count     = (int) $query->found_posts;
    $page      = max(1, (int) $query->get('paged'));
    $max_pages = max(1, (int) $query->max_num_pages);

    $search_query = '';
    if ('search' === $type) {
        $search_query = get_search_query(false);
    }

    $category_descriptor = gta6mods_get_category_descriptor($category_slug, $category_name);

    $breadcrumbs = gta6mods_prepare_archive_breadcrumbs($type, $term, $category_term, $sort, $since, $tag_strings, $search_query);

    return [
        'type'                => $type,
        'term'                => $term instanceof WP_Term ? $term : null,
        'category_term'       => $category_term instanceof WP_Term ? $category_term : null,
        'category_name'       => $category_name,
        'category_slug'       => $category_slug,
        'category_description'=> $category_desc,
        'category_descriptor' => $category_descriptor,
        'tag_terms'           => $tag_terms,
        'tag_names'           => $tag_names,
        'tag_strings'         => $tag_strings,
        'sort'                => $sort,
        'since'               => $since,
        'sort_meta'           => $sort_meta,
        'since_meta'          => $since_meta,
        'default_sort'        => $default_sort,
        'default_since'       => $default_since,
        'count'               => $count,
        'page'                => $page,
        'max_pages'           => $max_pages,
        'mods'                => $mods,
        'search_query'        => $search_query,
        'has_results'         => $count > 0,
        'filters_label'       => gta6mods_build_filters_label($type, $category_name, $tag_strings, $sort_meta, $since_meta, $search_query, $default_sort, $default_since, $sort, $since),
        'breadcrumbs'         => $breadcrumbs,
    ];
}

/**
 * Provides per-sort metadata for headlines and descriptions.
 *
 * @param string $sort Sort key.
 * @return array<string, mixed>
 */
function gta6mods_get_archive_sort_metadata($sort) {
    $map = [
        'latest-uploads' => [
            'title'        => __('New', 'gta6-mods'),
            'description'  => __('Latest additions', 'gta6-mods'),
            'superlative'  => __('Top Additions', 'gta6-mods'),
            'keywords'     => ['latest uploads'],
        ],
        'latest-updates' => [
            'title'        => __('Recently Updated', 'gta6-mods'),
            'description'  => __('Recently updated', 'gta6-mods'),
            'superlative'  => __('Latest Updates', 'gta6-mods'),
            'keywords'     => ['recent updates'],
        ],
        'most-downloaded' => [
            'title'        => __('Popular', 'gta6-mods'),
            'description'  => __('Most popular', 'gta6-mods'),
            'superlative'  => __('Most Popular', 'gta6-mods'),
            'keywords'     => ['most downloaded', 'popular'],
        ],
        'most-liked' => [
            'title'        => __('Community Favorite', 'gta6-mods'),
            'description'  => __('Community favorites', 'gta6-mods'),
            'superlative'  => __('Most Loved', 'gta6-mods'),
            'keywords'     => ['most liked', 'community favorite'],
        ],
        'highest-rated' => [
            'title'        => __('Top-Rated', 'gta6-mods'),
            'description'  => __('Top-rated', 'gta6-mods'),
            'superlative'  => __('Highest Rated', 'gta6-mods'),
            'keywords'     => ['highest rated', 'best reviewed'],
        ],
        'featured' => [
            'title'        => __('Featured', 'gta6-mods'),
            'description'  => __('Hand-picked selections', 'gta6-mods'),
            'superlative'  => __('Featured Picks', 'gta6-mods'),
            'keywords'     => ['featured', 'curated'],
        ],
    ];

    return isset($map[$sort]) ? $map[$sort] : $map[gta6mods_get_default_archive_sort()];
}

/**
 * Provides per-since metadata for headlines.
 *
 * @param string $since Since key.
 * @return array<string, string>
 */
function gta6mods_get_archive_since_metadata($since) {
    $map = [
        'today' => [
            'title'    => __('Today', 'gta6-mods'),
            'heading'  => __('Today\'s', 'gta6-mods'),
            'sentence' => __('today', 'gta6-mods'),
        ],
        'yesterday' => [
            'title'    => __('Yesterday', 'gta6-mods'),
            'heading'  => __('Yesterday\'s', 'gta6-mods'),
            'sentence' => __('from yesterday', 'gta6-mods'),
        ],
        'week' => [
            'title'    => __('This Week', 'gta6-mods'),
            'heading'  => __('This Week\'s', 'gta6-mods'),
            'sentence' => __('this week', 'gta6-mods'),
        ],
        'month' => [
            'title'    => __('This Month', 'gta6-mods'),
            'heading'  => __('This Month\'s', 'gta6-mods'),
            'sentence' => __('this month', 'gta6-mods'),
        ],
        'year' => [
            'title'    => __('This Year', 'gta6-mods'),
            'heading'  => __('This Year\'s', 'gta6-mods'),
            'sentence' => __('this year', 'gta6-mods'),
        ],
        'all' => [
            'title'    => __('All Time', 'gta6-mods'),
            'heading'  => __('All-Time', 'gta6-mods'),
            'sentence' => __('across all time', 'gta6-mods'),
        ],
        'all-time' => [
            'title'    => __('All Time', 'gta6-mods'),
            'heading'  => __('All-Time', 'gta6-mods'),
            'sentence' => __('across all time', 'gta6-mods'),
        ],
    ];

    return isset($map[$since]) ? $map[$since] : $map[gta6mods_get_default_archive_since()];
}

/**
 * Formats a list of tag names into readable strings.
 *
 * @param array<int, string> $names Tag names.
 * @return array<string, mixed>
 */
function gta6mods_format_tag_strings(array $names) {
    $names = array_values(array_unique(array_filter(array_map('trim', $names), 'strlen')));
    $count = count($names);

    if (0 === $count) {
        return [
            'count'   => 0,
            'title'   => '',
            'extended'=> '',
        ];
    }

    if (1 === $count) {
        return [
            'count'   => 1,
            'title'   => $names[0],
            'extended'=> $names[0],
        ];
    }

    if (2 === $count) {
        return [
            'count'   => 2,
            'title'   => implode(' and ', $names),
            'extended'=> implode(' and ', $names),
        ];
    }

    $first_three = array_slice($names, 0, 3);
    if ($count > 3) {
        $first_three[2] = $first_three[2] . ' and more';
    }

    $extended = gta6mods_join_with_and($first_three);

    return [
        'count'   => $count,
        'title'   => $extended,
        'extended'=> $extended,
    ];
}

/**
 * Joins an array of strings using commas and "and" for the final element.
 *
 * @param array<int, string> $parts Parts to join.
 * @return string
 */
function gta6mods_join_with_and(array $parts) {
    $parts = array_values(array_filter($parts, 'strlen'));
    $count = count($parts);
    if (0 === $count) {
        return '';
    }
    if (1 === $count) {
        return $parts[0];
    }

    $last = array_pop($parts);
    return implode(', ', $parts) . ' and ' . $last;
}

/**
 * Builds a concise label describing the active filters.
 *
 * @param string $type          Archive type.
 * @param string $category_name Category name.
 * @param array  $tag_strings   Tag string metadata.
 * @param array  $sort_meta     Sort metadata.
 * @param array  $since_meta    Since metadata.
 * @param string $search_query  Search string.
 * @param string $default_sort  Default sort key.
 * @param string $default_since Default since key.
 * @param string $sort          Active sort.
 * @param string $since         Active since.
 * @return string
 */
function gta6mods_build_filters_label($type, $category_name, array $tag_strings, array $sort_meta, array $since_meta, $search_query, $default_sort, $default_since, $sort, $since) {
    $parts = [];

    if ('search' === $type && '' !== $search_query) {
        $parts[] = sprintf('"%s"', $search_query);
    }

    if ('' !== $tag_strings['title']) {
        $parts[] = $tag_strings['title'];
    }

    if ('' !== $category_name) {
        $parts[] = $category_name;
    }

    if ($sort !== $default_sort) {
        $parts[] = strtolower($sort_meta['description']);
    }

    if ($since !== $default_since) {
        $parts[] = $since_meta['sentence'];
    }

    $label = trim(implode(' ', $parts));

    if ('' === $label) {
        return __('GTA 6', 'gta6-mods');
    }

    return $label;
}

/**
 * Determines a descriptor for the current category slug.
 *
 * @param string $slug Category slug.
 * @param string $fallback_name Fallback name.
 * @return string
 */
function gta6mods_get_category_descriptor($slug, $fallback_name) {
    $map = [
        'vehicles'  => __('vehicle', 'gta6-mods'),
        'weapons'   => __('weapon', 'gta6-mods'),
        'graphics'  => __('graphics', 'gta6-mods'),
        'characters'=> __('character', 'gta6-mods'),
        'maps'      => __('map', 'gta6-mods'),
        'scripts'   => __('script', 'gta6-mods'),
        'tools'     => __('tool', 'gta6-mods'),
    ];

    if (isset($map[$slug])) {
        return $map[$slug];
    }

    if ('' !== $fallback_name) {
        return strtolower($fallback_name);
    }

    return __('mod', 'gta6-mods');
}

/**
 * Prepares breadcrumb data for structured data.
 *
 * @param string       $type          Archive type.
 * @param WP_Term|null $term          Queried term.
 * @param WP_Term|null $category_term Category term.
 * @param string       $sort          Sort key.
 * @param string       $since         Since key.
 * @param array        $tag_strings   Tag metadata.
 * @param string       $search_query  Search text.
 * @return array<int, array<string, string>>
 */
function gta6mods_prepare_archive_breadcrumbs($type, $term, $category_term, $sort, $since, array $tag_strings, $search_query) {
    $breadcrumbs = [
        [
            'name' => __('Home', 'gta6-mods'),
            'url'  => home_url('/'),
        ],
    ];

    if ('category' === $type && $term instanceof WP_Term) {
        $term_link = get_term_link($term);
        $breadcrumbs[] = [
            'name' => $term->name,
            'url'  => !is_wp_error($term_link) ? $term_link : '',
        ];
    } elseif ('tag' === $type && $term instanceof WP_Term) {
        $term_link = get_term_link($term);
        $breadcrumbs[] = [
            'name' => $term->name,
            'url'  => !is_wp_error($term_link) ? $term_link : '',
        ];
    } elseif ('search' === $type) {
        $search_link = get_search_link($search_query);
        $breadcrumbs[] = [
            'name' => __('Search', 'gta6-mods'),
            'url'  => $search_link,
        ];
    }

    if ($category_term instanceof WP_Term && 'category' !== $type) {
        $category_link = get_term_link($category_term);
        $breadcrumbs[] = [
            'name' => $category_term->name,
            'url'  => !is_wp_error($category_link) ? $category_link : '',
        ];
    }

    if ('' !== $tag_strings['title'] && 'category' === $type) {
        $breadcrumbs[] = [
            'name' => $tag_strings['title'],
            'url'  => '',
        ];
    }

    if ($since !== gta6mods_get_default_archive_since()) {
        $breadcrumbs[] = [
            'name' => gta6mods_get_archive_since_metadata($since)['title'],
            'url'  => '',
        ];
    }

    if ($sort !== gta6mods_get_default_archive_sort()) {
        $breadcrumbs[] = [
            'name' => gta6mods_get_archive_sort_metadata($sort)['title'],
            'url'  => '',
        ];
    }

    return $breadcrumbs;
}

/**
 * Generates a dynamic title based on archive context.
 *
 * @param array $context SEO context.
 * @return string
 */
function gta6mods_generate_dynamic_title($context) {
    $brand = 'GTA6-Mods.com';
    $title_core = '';
    $category_name = $context['category_name'];
    $tag_title     = $context['tag_strings']['title'];
    $sort          = $context['sort'];
    $since         = $context['since'];
    $sort_meta     = $context['sort_meta'];
    $since_meta    = $context['since_meta'];
    $default_sort  = $context['default_sort'];
    $default_since = $context['default_since'];

    if (!$context['has_results']) {
        $filters_label = $context['filters_label'];
        $title_core    = sprintf('No %s Mods Found | GTA 6', $filters_label);
    } elseif ('category' === $context['type']) {
        if ('' === $category_name) {
            $category_name = __('GTA 6', 'gta6-mods');
        }

        if ('' !== $tag_title) {
            $base = trim($tag_title . ' ' . $category_name . ' Mods');
            if ($sort !== $default_sort && $since !== $default_since) {
                $title_core = sprintf('%s – %s %s', $base, $since_meta['heading'], $sort_meta['superlative']);
            } elseif ($sort !== $default_sort) {
                $title_core = sprintf('%s %s', $sort_meta['title'], $base);
            } elseif ($since !== $default_since) {
                $title_core = sprintf('%s %s', $base, $since_meta['title']);
            } else {
                $title_core = $base . ' for GTA 6';
            }
        } else {
            if ($sort === $default_sort && $since === $default_since) {
                $title_core = sprintf('GTA 6 %s Mods', $category_name);
            } elseif ($since !== $default_since && $sort !== $default_sort) {
                $title_core = sprintf('%s %s Mods %s', $sort_meta['title'], $category_name, $since_meta['title']);
            } elseif ($sort !== $default_sort) {
                $title_core = sprintf('%s %s Mods for GTA 6', $sort_meta['title'], $category_name);
            } else {
                $title_core = sprintf('New %s Mods %s', $category_name, $since_meta['title']);
            }
        }
    } elseif ('tag' === $context['type']) {
        $tag_term = $context['term'];
        $tag_name = $tag_term instanceof WP_Term ? $tag_term->name : __('Tag', 'gta6-mods');
        $base_category = '' !== $category_name ? ' ' . $category_name : '';
        $base = sprintf('%s%s Mods for GTA 6', $tag_name, $base_category);

        if ($sort !== $default_sort && $since !== $default_since) {
            $title_core = sprintf('%s – %s %s', $base, $since_meta['heading'], $sort_meta['superlative']);
        } elseif ($sort !== $default_sort) {
            $title_core = sprintf('%s %s %s', $sort_meta['title'], $tag_name, '' !== $category_name ? $category_name . ' Mods' : 'Mods');
        } elseif ($since !== $default_since) {
            $title_core = sprintf('%s %s', $base, $since_meta['title']);
        } else {
            $title_core = $base;
        }
    } else {
        $query = $context['search_query'];
        $category_fragment = '' !== $category_name ? sprintf(' in %s', $category_name) : '';
        $base = sprintf('"%s" – GTA 6 Mod Search%s', $query, $category_fragment);

        if ($sort !== $default_sort && $since !== $default_since) {
            $title_core = sprintf('%s – %s %s', $base, $since_meta['heading'], $sort_meta['superlative']);
        } elseif ($sort !== $default_sort) {
            $title_core = sprintf('%s – %s Results', $base, $sort_meta['title']);
        } elseif ($since !== $default_since) {
            $title_core = sprintf('%s – %s Updates', $base, $since_meta['title']);
        } else {
            $title_core = $base;
        }
    }

    $title_core = trim($title_core);

    if ('' === $title_core) {
        $title_core = __('GTA 6 Mods', 'gta6-mods');
    }

    if (false === strpos($title_core, $brand)) {
        $title_core .= ' | ' . $brand;
    }

    if ($context['page'] > 1) {
        $title_core .= ' – Page ' . $context['page'];
    }

    if (mb_strlen($title_core, 'UTF-8') > 55) {
        $title_core = gta6mods_shorten_title($title_core);
    }

    return $title_core;
}

/**
 * Intelligent title shortening helper.
 *
 * @param string $title Raw title.
 * @return string
 */
function gta6mods_shorten_title($title) {
    $brand = ' | GTA6-Mods.com';
    if (false !== strpos($title, $brand)) {
        $core = trim(str_replace($brand, '', $title));
    } else {
        $core = $title;
    }

    $core = preg_replace('/\s+/', ' ', $core);
    $core = trim($core);

    if (mb_strlen($core, 'UTF-8') <= 55) {
        return $title;
    }

    $truncated = mb_substr($core, 0, 52, 'UTF-8');
    $last_space = mb_strrpos($truncated, ' ', 0, 'UTF-8');

    if (false !== $last_space && $last_space > 40) {
        $core = mb_substr($truncated, 0, $last_space, 'UTF-8') . '…';
    } else {
        $core = $truncated . '…';
    }

    return $core . $brand;
}

/**
 * Generates a meta description (150-160 characters).
 *
 * @param array $context SEO context.
 * @return string
 */
function gta6mods_generate_dynamic_description($context) {
    $count_formatted = number_format_i18n($context['count']);
    $category_descriptor = $context['category_descriptor'];
    $sort_meta     = $context['sort_meta'];
    $since_meta    = $context['since_meta'];
    $default_sort  = $context['default_sort'];
    $default_since = $context['default_since'];
    $tag_title     = $context['tag_strings']['extended'];

    if (!$context['has_results']) {
        $category_label = '' !== $context['category_name'] ? strtolower($context['category_name']) : __('GTA 6', 'gta6-mods');
        $description = sprintf(
            'No %s mods found. Try adjusting filters or explore related %s mods for GTA 6.',
            $context['filters_label'],
            $category_label
        );
        return gta6mods_limit_description_length($description);
    }

    if ('search' === $context['type']) {
        $category_fragment = '' !== $context['category_name']
            ? sprintf(' in the %s category', $context['category_name'])
            : __(' across multiple categories', 'gta6-mods');
        $description = sprintf(
            'Search results for "%1$s" in GTA 6 mods. %2$s results found%3$s. Community-approved quality and ongoing updates.',
            $context['search_query'],
            $count_formatted,
            $category_fragment
        );
        return gta6mods_limit_description_length($description);
    }

    $opening = '';

    if ('' !== $tag_title) {
        $opening = sprintf(
            'Browse %1$s %2$s %3$s mods for GTA 6.',
            $count_formatted,
            $tag_title,
            $category_descriptor
        );
    } elseif ($context['since'] !== $default_since && $context['sort'] !== $default_sort) {
        $opening = sprintf(
            '%1$s %2$s %3$s mods for GTA 6. %4$s releases curated from the community.',
            ucfirst($since_meta['sentence']),
            strtolower($sort_meta['description']),
            $category_descriptor,
            $count_formatted
        );
    } elseif ($context['sort'] !== $default_sort) {
        $opening = sprintf(
            '%1$s %2$s mods for GTA 6. %3$s options curated for enthusiasts.',
            $sort_meta['description'],
            $category_descriptor,
            $count_formatted
        );
    } else {
        $opening = sprintf(
            'Explore %1$s %2$s mods for GTA 6. Premium community creations and regular updates.',
            $count_formatted,
            $category_descriptor
        );
    }

    $closing = 'Community-curated, quality checked and updated frequently.';

    $description = trim($opening . ' ' . $closing);

    return gta6mods_limit_description_length($description);
}

/**
 * Limits descriptions to 160 characters while keeping sentences intact.
 *
 * @param string $description Raw description.
 * @return string
 */
function gta6mods_limit_description_length($description) {
    $description = preg_replace('/\s+/', ' ', trim($description));

    $has_mbstring = function_exists('mb_strlen') && function_exists('mb_substr') && function_exists('mb_strrpos');
    $length       = $has_mbstring ? mb_strlen($description, 'UTF-8') : strlen($description);

    if ($length >= 150 && $length <= 160) {
        return $description;
    }

    if ($length < 150) {
        $available_space = 160 - $length;

        if ($available_space >= 40) {
            $extra = ' Trusted by the GTA6-Mods community.';
        } elseif ($available_space >= 20) {
            $extra = ' Community-approved.';
        } elseif ($available_space >= 10) {
            $extra = ' Updated.';
        } else {
            return $description;
        }

        $description = trim($description . $extra);
        $length       = $has_mbstring ? mb_strlen($description, 'UTF-8') : strlen($description);

        if ($length > 160) {
            $slice = $has_mbstring ? mb_substr($description, 0, 157, 'UTF-8') : substr($description, 0, 157);
            return $slice . '…';
        }

        return $description;
    }

    $truncated = $has_mbstring ? mb_substr($description, 0, 157, 'UTF-8') : substr($description, 0, 157);
    if ($has_mbstring) {
        $last_space = mb_strrpos($truncated, ' ', 0, 'UTF-8');
    } else {
        $last_space = strrpos($truncated, ' ');
    }

    if (false !== $last_space && $last_space > 140) {
        $slice = $has_mbstring
            ? mb_substr($truncated, 0, $last_space, 'UTF-8')
            : substr($truncated, 0, $last_space);

        return $slice . '…';
    }

    return $truncated . '…';
}

/**
 * Builds the primary H1 heading.
 *
 * @param array $context SEO context.
 * @return string
 */
function gta6mods_generate_archive_h1($context) {
    if (!$context['has_results']) {
        return __('No Mods Found', 'gta6-mods');
    }

    $parts = [];

    if ($context['since'] !== $context['default_since']) {
        $parts[] = $context['since_meta']['heading'];
    }

    if ($context['sort'] !== $context['default_sort']) {
        $parts[] = $context['sort_meta']['title'];
    }

    if ('' !== $context['tag_strings']['title']) {
        $parts[] = $context['tag_strings']['title'];
    }

    $category_fragment = '' !== $context['category_name'] ? $context['category_name'] . ' Mods' : __('Mods', 'gta6-mods');

    if ('search' === $context['type'] && '' !== $context['search_query']) {
        $parts[] = $category_fragment;
        $parts[] = sprintf(__('matching "%s"', 'gta6-mods'), $context['search_query']);
        $parts[] = __('for GTA 6', 'gta6-mods');
    } else {
        $parts[] = $category_fragment;
        $parts[] = __('for GTA 6', 'gta6-mods');
    }

    $heading = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts))));

    return $heading;
}

/**
 * Generates subtitle/description under the H1.
 *
 * @param array $context SEO context.
 * @return string
 */
function gta6mods_generate_archive_subtitle($context) {
    $count_formatted = number_format_i18n($context['count']);
    $category_descriptor = $context['category_descriptor'];

    if (!$context['has_results']) {
        $category_label = '' !== $context['category_name'] ? strtolower($context['category_name']) : __('GTA 6', 'gta6-mods');
        return sprintf(
            'Browse other %s collections or adjust the filters to discover more premium GTA 6 mods.',
            $category_label
        );
    }

    $filter_fragment = '';

    if ($context['since'] !== $context['default_since'] && $context['sort'] !== $context['default_sort']) {
        $filter_fragment = sprintf('%s %s releases.', $context['since_meta']['heading'], strtolower($context['sort_meta']['description']));
    } elseif ($context['since'] !== $context['default_since']) {
        $filter_fragment = sprintf('%s curated drops.', $context['since_meta']['heading']);
    } elseif ($context['sort'] !== $context['default_sort']) {
        $filter_fragment = sprintf('%s picks from the community.', $context['sort_meta']['title']);
    } else {
        $filter_fragment = 'Community-curated and refreshed often.';
    }

    $subtitle = sprintf(
        'Browse %1$s quality %2$s mods for Grand Theft Auto 6. %3$s',
        $count_formatted,
        $category_descriptor,
        $filter_fragment
    );

    return trim($subtitle);
}

/**
 * Determines the best image for social sharing.
 *
 * @param array $mods    Mod data.
 * @param array $context SEO context.
 * @return string
 */
function gta6mods_get_social_image(array $mods, array $context) {
    foreach ($mods as $mod) {
        if (!empty($mod['imageUrl'])) {
            return $mod['imageUrl'];
        }
    }

    if ($context['category_term'] instanceof WP_Term) {
        $term_image = get_term_meta($context['category_term']->term_id, 'gta6mods_category_cover', true);
        if (!empty($term_image) && filter_var($term_image, FILTER_VALIDATE_URL)) {
            return $term_image;
        }
    }

    $default = apply_filters('gta6mods_default_og_image', get_template_directory_uri() . '/img/bg/backgroundnight.png');

    return $default;
}

/**
 * Generates structured data schemas for the archive.
 *
 * @param array $context SEO context enriched with title and canonical.
 * @param array $mods    Mod data.
 * @return array<int, array<string, mixed>>
 */
function gta6mods_generate_jsonld_schemas(array $context, array $mods) {
    $schemas = [];

    $item_list = [];
    $max_items = min(10, count($mods));
    for ($i = 0; $i < $max_items; $i++) {
        $mod = $mods[$i];
        $position = $i + 1;
        $entry = [
            '@type'               => 'SoftwareApplication',
            'position'            => $position,
            'name'                => isset($mod['title']) ? $mod['title'] : sprintf(__('Mod #%d', 'gta6-mods'), $position),
            'url'                 => isset($mod['link']) ? $mod['link'] : '',
            'image'               => isset($mod['imageUrl']) ? $mod['imageUrl'] : '',
            'applicationCategory' => 'Game',
            'operatingSystem'     => 'Windows',
        ];

        $rating_value = isset($mod['rating']) ? (float) $mod['rating'] : 0.0;
        $rating_count = isset($mod['ratingCount']) ? (int) $mod['ratingCount'] : 0;

        if ((($rating_value <= 0) || $rating_count <= 0) && function_exists('gta6_mods_get_rating_data') && isset($mod['id'])) {
            $rating_data = gta6_mods_get_rating_data($mod['id']);
            if (is_array($rating_data)) {
                if ($rating_value <= 0 && isset($rating_data['average'])) {
                    $rating_value = (float) $rating_data['average'];
                }
                if ($rating_count <= 0 && isset($rating_data['count'])) {
                    $rating_count = (int) $rating_data['count'];
                }
            }
        }

        if ($rating_value > 0 && $rating_count > 0) {
            $entry['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $rating_value,
                'ratingCount' => $rating_count,
                'bestRating'  => '5',
            ];
        }

        $downloads = isset($mod['downloads']) ? (int) $mod['downloads'] : 0;
        $likes     = isset($mod['likes']) ? (int) $mod['likes'] : 0;

        $interaction = [];
        if ($downloads > 0) {
            $interaction[] = [
                '@type'               => 'InteractionCounter',
                'interactionType'     => 'https://schema.org/DownloadAction',
                'userInteractionCount'=> $downloads,
            ];
        }
        if ($likes > 0) {
            $interaction[] = [
                '@type'               => 'InteractionCounter',
                'interactionType'     => 'https://schema.org/LikeAction',
                'userInteractionCount'=> $likes,
            ];
        }

        if (!empty($interaction)) {
            $entry['interactionStatistic'] = $interaction;
        }

        $item_list[] = $entry;
    }

    $collection = [
        '@context'     => 'https://schema.org',
        '@type'        => 'CollectionPage',
        'name'         => $context['title'],
        'description'  => $context['description'],
        'url'          => $context['canonical'],
        'numberOfItems'=> (int) $context['count'],
        'mainEntity'   => [
            '@type'            => 'ItemList',
            'numberOfItems'    => count($item_list),
            'itemListElement'  => $item_list,
        ],
    ];

    $schemas[] = $collection;

    $breadcrumb_items = [];
    foreach ($context['breadcrumbs'] as $index => $crumb) {
        if (empty($crumb['name'])) {
            continue;
        }
        $breadcrumb_items[] = [
            '@type'    => 'ListItem',
            'position' => $index + 1,
            'name'     => $crumb['name'],
            'item'     => !empty($crumb['url']) ? $crumb['url'] : $context['canonical'],
        ];
    }

    if (!empty($breadcrumb_items)) {
        $schemas[] = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $breadcrumb_items,
        ];
    }

    if ('search' === $context['type']) {
        $schemas[] = [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => 'GTA6-Mods.com',
            'url'             => home_url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'      => 'EntryPoint',
                    'urlTemplate'=> home_url('/search/{search_term_string}/'),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    return $schemas;
}

/**
 * Outputs SEO meta tags in the document head.
 */
function gta6mods_output_seo_head() {
    if (!is_archive() && !is_search()) {
        return;
    }

    $seo_data = gta6mods_get_archive_seo_data($GLOBALS['wp_query']);
    if (empty($seo_data)) {
        return;
    }

    if (!empty($seo_data['canonical'])) {
        printf("<link rel=\"canonical\" href=\"%s\" />\n", esc_url($seo_data['canonical']));
    }

    if (!empty($seo_data['prev_url'])) {
        printf("<link rel=\"prev\" href=\"%s\" />\n", esc_url($seo_data['prev_url']));
    }

    if (!empty($seo_data['next_url'])) {
        printf("<link rel=\"next\" href=\"%s\" />\n", esc_url($seo_data['next_url']));
    }

    if (!empty($seo_data['noindex'])) {
        printf("<meta name=\"robots\" content=\"%s\" />\n", esc_attr($seo_data['robots']));
    }

    if (!empty($seo_data['description'])) {
        printf("<meta name=\"description\" content=\"%s\" />\n", esc_attr($seo_data['description']));
    }

    printf("<meta name=\"author\" content=\"%s\" />\n", esc_attr('GTA6-Mods.com'));
    printf("<meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\" />\n");
    printf("<link rel=\"dns-prefetch\" href=\"%s\" />\n", esc_url('//r2.cloudflare.com'));

    $cdn_origin = apply_filters('gta6mods_cdn_origin', 'https://cdn.gta6-mods.com');
    if (!empty($cdn_origin)) {
        printf("<link rel=\"preconnect\" href=\"%s\" crossorigin />\n", esc_url($cdn_origin));
    }

    printf("<link rel=\"manifest\" href=\"%s\" />\n", esc_url(home_url('/manifest.json')));
    printf("<meta name=\"theme-color\" content=\"%s\" />\n", esc_attr('#EC4899'));
    printf("<meta name=\"apple-mobile-web-app-capable\" content=\"yes\" />\n");
    printf("<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"black-translucent\" />\n");

    if (!empty($seo_data['og_title'])) {
        printf("<meta property=\"og:title\" content=\"%s\" />\n", esc_attr($seo_data['og_title']));
    }
    if (!empty($seo_data['og_description'])) {
        printf("<meta property=\"og:description\" content=\"%s\" />\n", esc_attr($seo_data['og_description']));
    }
    if (!empty($seo_data['og_image'])) {
        printf("<meta property=\"og:image\" content=\"%s\" />\n", esc_url($seo_data['og_image']));
        printf("<meta property=\"og:image:width\" content=\"1200\" />\n");
        printf("<meta property=\"og:image:height\" content=\"630\" />\n");
    }
    if (!empty($seo_data['canonical'])) {
        printf("<meta property=\"og:url\" content=\"%s\" />\n", esc_url($seo_data['canonical']));
    }
    printf("<meta property=\"og:type\" content=\"website\" />\n");
    printf("<meta property=\"og:site_name\" content=\"%s\" />\n", esc_attr('GTA6-Mods.com'));

    printf("<meta name=\"twitter:card\" content=\"summary_large_image\" />\n");
    if (!empty($seo_data['og_title'])) {
        printf("<meta name=\"twitter:title\" content=\"%s\" />\n", esc_attr($seo_data['og_title']));
    }
    if (!empty($seo_data['og_description'])) {
        printf("<meta name=\"twitter:description\" content=\"%s\" />\n", esc_attr($seo_data['og_description']));
    }
    if (!empty($seo_data['og_image'])) {
        printf("<meta name=\"twitter:image\" content=\"%s\" />\n", esc_url($seo_data['og_image']));
    }

    if (!empty($seo_data['preload']) && is_array($seo_data['preload'])) {
        foreach ($seo_data['preload'] as $image) {
            printf("<link rel=\"preload\" as=\"image\" href=\"%s\" />\n", esc_url($image));
        }
    }

    $fonts = apply_filters('gta6mods_preload_fonts', []);
    if (is_array($fonts)) {
        foreach ($fonts as $font) {
            $href = isset($font['href']) ? $font['href'] : '';
            if ('' === $href) {
                continue;
            }
            $type = isset($font['type']) ? $font['type'] : 'font/woff2';
            printf('<link rel="preload" href="%s" as="font" type="%s" crossorigin />' . "\n", esc_url($href), esc_attr($type));
        }
    }

    if (!empty($seo_data['jsonld']) && is_array($seo_data['jsonld'])) {
        foreach ($seo_data['jsonld'] as $schema) {
            $json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json) {
                printf('<script type="application/ld+json">%s</script>' . "\n", $json);
            }
        }
    }
}
add_action('wp_head', 'gta6mods_output_seo_head', 1);

/**
 * Filters the WordPress document title.
 *
 * @param string $title Existing title.
 * @return string
 */
function gta6mods_filter_document_title($title) {
    if (is_front_page()) {
        return $title;
    }
    
    if (!is_archive() && !is_search()) {
        return $title;
    }

    $seo_data = gta6mods_get_archive_seo_data($GLOBALS['wp_query']);
    if (empty($seo_data['title'])) {
        return $title;
    }

    return $seo_data['title'];
}
add_filter('pre_get_document_title', 'gta6mods_filter_document_title');

/**
 * Outputs the requested homepage title tag before other head elements run.
 */
function gta6mods_output_front_page_title_tag() {
    if (!is_front_page()) {
        return;
    }

    remove_action('wp_head', '_wp_render_title_tag', 1);

    echo '<title>GTA6-Mods.com - Download GTA 6 PC mods, cars, scripts, tools & more</title>' . "\n";
    echo '<meta name="description" content="Browse and download thousands of GTA 6 PC mods created by our community. Car mods, weapon mods, scripts, maps and more. Join the ultimate modding platform.">' . "\n";
}
add_action('wp_head', 'gta6mods_output_front_page_title_tag', 0);