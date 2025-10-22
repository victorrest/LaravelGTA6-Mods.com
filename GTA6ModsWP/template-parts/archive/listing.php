<?php
if (!defined('ABSPATH')) {
    exit;
}

$defaults = [
    'title'         => '',
    'subtitle'      => '',
    'icon'          => 'fa-solid fa-folder-open',
    'hero_meta'     => [],
    'mods'          => [],
    'chips'         => [],
    'filters'       => [],
    'results_count' => 0,
    'updated_label' => '',
    'pagination'    => [],
];

$incoming_data = isset($args) && is_array($args) ? $args : get_query_var('gta6_mods_listing_data');
$listing_data = wp_parse_args(is_array($incoming_data) ? $incoming_data : [], $defaults);

$hero_meta = is_array($listing_data['hero_meta']) ? $listing_data['hero_meta'] : [];
$chips = is_array($listing_data['chips']) ? $listing_data['chips'] : [];
$mods = is_array($listing_data['mods']) ? $listing_data['mods'] : [];
$pagination = is_array($listing_data['pagination']) ? $listing_data['pagination'] : [];
$filters = is_array($listing_data['filters']) ? $listing_data['filters'] : [];
$seo_data = isset($listing_data['seo_data']) && is_array($listing_data['seo_data']) ? $listing_data['seo_data'] : [];

$h1_text = isset($seo_data['h1']) && '' !== $seo_data['h1'] ? $seo_data['h1'] : $listing_data['title'];
$subtitle_text = isset($seo_data['subtitle']) && '' !== $seo_data['subtitle'] ? $seo_data['subtitle'] : $listing_data['subtitle'];

$results_count = (int) $listing_data['results_count'];
$results_label = number_format_i18n($results_count);

$selected_sort = isset($filters['sort']) ? sanitize_key($filters['sort']) : gta6mods_get_default_archive_sort();
$selected_since = isset($filters['since']) ? sanitize_key($filters['since']) : gta6mods_get_default_archive_since();
$sort_options = isset($filters['sortOptions']) && is_array($filters['sortOptions']) ? $filters['sortOptions'] : [];
$since_options = isset($filters['sinceOptions']) && is_array($filters['sinceOptions']) ? $filters['sinceOptions'] : [];
$preserve_args = isset($filters['preserve']) && is_array($filters['preserve']) ? $filters['preserve'] : [];
$form_action = isset($filters['formAction']) ? $filters['formAction'] : '';
$base_url = isset($filters['baseUrl']) ? $filters['baseUrl'] : '';
$query_base = isset($filters['queryBase']) ? $filters['queryBase'] : '';
$use_pretty_links = !empty($filters['pretty']);
$default_sort = isset($filters['defaultSort']) ? sanitize_key($filters['defaultSort']) : gta6mods_get_default_archive_sort();
$default_since = isset($filters['defaultSince']) ? sanitize_key($filters['defaultSince']) : gta6mods_get_default_archive_since();

$tag_filter_config = isset($filters['tagFilter']) && is_array($filters['tagFilter']) ? $filters['tagFilter'] : [];
$tag_options = isset($tag_filter_config['options']) && is_array($tag_filter_config['options']) ? $tag_filter_config['options'] : [];
$tag_selected_value = isset($tag_filter_config['value']) ? (string) $tag_filter_config['value'] : '';
$tag_default_value = isset($tag_filter_config['default']) ? (string) $tag_filter_config['default'] : '';
$tag_segment = isset($tag_filter_config['segment']) ? sanitize_key($tag_filter_config['segment']) : '';
$tag_param = isset($tag_filter_config['param']) ? sanitize_key($tag_filter_config['param']) : 'gta_tag';
$show_tag_filter = !empty($tag_segment) && (!empty($tag_selected_value) || count($tag_options) > 1);

$category_filter_config = isset($filters['categoryFilter']) && is_array($filters['categoryFilter']) ? $filters['categoryFilter'] : [];
$category_options = isset($category_filter_config['options']) && is_array($category_filter_config['options']) ? $category_filter_config['options'] : [];
$category_selected_value = isset($category_filter_config['value']) ? (string) $category_filter_config['value'] : '';
$category_default_value = isset($category_filter_config['default']) ? (string) $category_filter_config['default'] : '';
$category_segment = isset($category_filter_config['segment']) ? sanitize_key($category_filter_config['segment']) : '';
$category_param = isset($category_filter_config['param']) ? sanitize_key($category_filter_config['param']) : 'gta_category';
$show_category_filter = !empty($category_segment) && (!empty($category_selected_value) || count($category_options) > 1);

$preserve_json = '{}';
if (!empty($preserve_args)) {
    $encoded_preserve = wp_json_encode($preserve_args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded_preserve) {
        $preserve_json = $encoded_preserve;
    }
}

$base_data = $base_url ? esc_url_raw($base_url) : '';
$query_base_data = $query_base ? esc_url_raw($query_base) : ($form_action ? esc_url_raw($form_action) : '');

$mods_json = wp_json_encode($mods, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<section id="category-hero" class="relative text-white overflow-hidden">
    <div class="container mx-auto px-4 py-8 text-center category-hero-content">
        <?php if (!empty($listing_data['icon'])) : ?>
            <i class="<?php echo esc_attr($listing_data['icon']); ?> text-6xl text-pink-400"></i>
        <?php endif; ?>
        <?php if (!empty($h1_text)) : ?>
            <h1 class="logo-font text-4xl mt-2"><?php echo esc_html($h1_text); ?></h1>
        <?php endif; ?>
        <?php if (!empty($subtitle_text)) : ?>
            <p class="text-gray-300 mt-1"><?php echo esc_html($subtitle_text); ?></p>
        <?php endif; ?>
        <?php if (!empty($hero_meta)) : ?>
            <div class="flex justify-center gap-2 mt-3 text-sm flex-wrap">
                <?php foreach ($hero_meta as $index => $meta_item) :
                    $badge_classes = $index === 0 ? 'bg-pink-600' : 'bg-gray-700';
                    ?>
                    <span class="<?php echo esc_attr($badge_classes); ?> px-3 py-1 rounded-full"><?php echo esc_html($meta_item); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<main class="container mx-auto px-4 py-6">
    <div>
        <section>
            <div class="card p-4 mb-6">
                <?php if (!empty($chips)) : ?>
                    <div class="flex items-center space-x-2 overflow-x-auto whitespace-nowrap pb-3 mb-3 custom-scrollbar-thin">
                        <?php foreach ($chips as $chip) :
                            $is_active = !empty($chip['active']);
                            $base_classes = 'px-3 py-1 text-sm rounded-full transition-colors';
                            $class = $is_active
                                ? 'bg-pink-100 text-pink-800 hover:bg-pink-200'
                                : 'bg-gray-100 text-gray-800 hover:bg-gray-200';
                            ?>
                            <a href="<?php echo esc_url($chip['url']); ?>" class="<?php echo esc_attr(trim($base_classes . ' ' . $class)); ?>"><?php echo esc_html($chip['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <form
                        id="archiveFilters"
                        method="get"
                        action="<?php echo esc_url($form_action); ?>"
                        class="flex items-center gap-x-4 gap-y-2 flex-wrap"
                        data-base-url="<?php echo esc_attr($base_data); ?>"
                        data-query-base="<?php echo esc_attr($query_base_data); ?>"
                        data-pretty="<?php echo esc_attr($use_pretty_links ? '1' : '0'); ?>"
                        data-default-sort="<?php echo esc_attr($default_sort); ?>"
                        data-default-since="<?php echo esc_attr($default_since); ?>"
                        data-tag-segment="<?php echo esc_attr($tag_segment); ?>"
                        data-tag-param="<?php echo esc_attr($tag_param); ?>"
                        data-default-tag="<?php echo esc_attr($tag_default_value); ?>"
                        data-category-segment="<?php echo esc_attr($category_segment); ?>"
                        data-category-param="<?php echo esc_attr($category_param); ?>"
                        data-default-category="<?php echo esc_attr($category_default_value); ?>"
                        data-preserve='<?php echo esc_attr($preserve_json); ?>'
                    >
                        <?php foreach ($preserve_args as $arg_key => $arg_value) :
                            $arg_key = sanitize_key($arg_key);
                            if ('' === $arg_key) {
                                continue;
                            }
                            ?>
                            <input type="hidden" name="<?php echo esc_attr($arg_key); ?>" value="<?php echo esc_attr($arg_value); ?>">
                        <?php endforeach; ?>
                        <div>
                            <label for="archiveSince" class="text-sm font-medium mr-2"><?php esc_html_e('Since:', 'gta6-mods'); ?></label>
                            <select id="archiveSince" name="gta_since" class="px-2 py-1 border rounded-lg text-sm bg-gray-50">
                                <?php foreach ($since_options as $option) :
                                    $value = isset($option['value']) ? sanitize_key($option['value']) : '';
                                    $label = isset($option['label']) ? $option['label'] : $value;
                                    ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_since, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="archiveSort" class="text-sm font-medium mr-2"><?php esc_html_e('Sort by:', 'gta6-mods'); ?></label>
                            <select id="archiveSort" name="gta_sort" class="px-2 py-1 border rounded-lg text-sm bg-gray-50">
                                <?php foreach ($sort_options as $option) :
                                    $value = isset($option['value']) ? sanitize_key($option['value']) : '';
                                    $label = isset($option['label']) ? $option['label'] : $value;
                                    ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_sort, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($show_tag_filter) : ?>
                            <div>
                                <label for="archiveTag" class="text-sm font-medium mr-2"><?php esc_html_e('Tag:', 'gta6-mods'); ?></label>
                                <select id="archiveTag" name="<?php echo esc_attr($tag_param); ?>" class="px-2 py-1 border rounded-lg text-sm bg-gray-50">
                                    <?php foreach ($tag_options as $option) :
                                        $value = isset($option['value']) ? (string) $option['value'] : '';
                                        $label = isset($option['label']) ? $option['label'] : $value;
                                        ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($tag_selected_value, $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_category_filter) : ?>
                            <div>
                                <label for="archiveCategory" class="text-sm font-medium mr-2"><?php esc_html_e('Category:', 'gta6-mods'); ?></label>
                                <select id="archiveCategory" name="<?php echo esc_attr($category_param); ?>" class="px-2 py-1 border rounded-lg text-sm bg-gray-50">
                                    <?php foreach ($category_options as $option) :
                                        $value = isset($option['value']) ? (string) $option['value'] : '';
                                        $label = isset($option['label']) ? $option['label'] : $value;
                                        ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($category_selected_value, $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <noscript>
                            <button type="submit" class="px-3 py-1 rounded-lg bg-gray-900 text-white text-sm"><?php esc_html_e('Apply', 'gta6-mods'); ?></button>
                        </noscript>
                    </form>
                    <div class="flex items-center gap-4 justify-between">
                        <p class="text-sm text-gray-600 whitespace-nowrap"><span><?php echo esc_html($results_label); ?></span> <?php esc_html_e('találat', 'gta6-mods'); ?></p>
                        <div class="flex border border-gray-300 rounded-lg overflow-hidden">
                            <button id="gridBtn" class="p-2 bg-gray-900 text-white" aria-label="<?php esc_attr_e('Rács nézet', 'gta6-mods'); ?>">
                                <i class="fa-solid fa-table-cells"></i>
                            </button>
                            <button id="listBtn" class="p-2 bg-white text-gray-600 border-l" aria-label="<?php esc_attr_e('Lista nézet', 'gta6-mods'); ?>">
                                <i class="fa-solid fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="modsContainer"></div>

            <?php if (!empty($pagination)) : ?>
                <nav class="mt-8 flex items-center justify-center gap-1 flex-wrap" aria-label="<?php esc_attr_e('Lapozás', 'gta6-mods'); ?>">
                    <?php
                    $pagination_items = [
                        $pagination['first'] ?? null,
                        $pagination['previous'] ?? null,
                    ];
                    foreach ($pagination_items as $item) {
                        if (!$item) {
                            continue;
                        }
                        if (!empty($item['disabled'])) {
                            echo '<span class="pagination-btn opacity-50 cursor-not-allowed" aria-disabled="true">' . esc_html($item['label']) . '</span>';
                        } else {
                            echo '<a class="pagination-btn" href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a>';
                        }
                    }

                    if (!empty($pagination['pages'])) {
                        foreach ($pagination['pages'] as $page) {
                            if (($page['type'] ?? '') === 'ellipsis') {
                                echo '<span class="px-2 text-gray-400">...</span>';
                                continue;
                            }

                            $is_current = !empty($page['current']);
                            $attributes = $is_current ? ' aria-current="page"' : '';
                            $classes = 'pagination-btn';
                            echo '<a class="' . esc_attr($classes) . '" href="' . esc_url($page['url']) . '"' . $attributes . '>' . esc_html($page['number']) . '</a>';
                        }
                    }

                    $pagination_items = [
                        $pagination['next'] ?? null,
                        $pagination['last'] ?? null,
                    ];
                    foreach ($pagination_items as $item) {
                        if (!$item) {
                            continue;
                        }
                        if (!empty($item['disabled'])) {
                            echo '<span class="pagination-btn opacity-50 cursor-not-allowed" aria-disabled="true">' . esc_html($item['label']) . '</span>';
                        } else {
                            echo '<a class="pagination-btn" href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a>';
                        }
                    }
                    ?>
                </nav>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modsData = <?php echo $mods_json ? $mods_json : '[]'; ?>;
        const container = document.getElementById('modsContainer');
        const gridBtn = document.getElementById('gridBtn');
        const listBtn = document.getElementById('listBtn');
        const filtersForm = document.getElementById('archiveFilters');
        const sinceSelect = document.getElementById('archiveSince');
        const sortSelect = document.getElementById('archiveSort');
        const tagSelect = document.getElementById('archiveTag');
        const categorySelect = document.getElementById('archiveCategory');

        const prettyFiltersEnabled = filtersForm ? filtersForm.dataset.pretty === '1' : false;
        const baseFilterUrl = filtersForm ? filtersForm.dataset.baseUrl || '' : '';
        const queryFilterBase = filtersForm ? filtersForm.dataset.queryBase || '' : '';
        const defaultSortValue = filtersForm ? filtersForm.dataset.defaultSort || '' : '';
        const defaultSinceValue = filtersForm ? filtersForm.dataset.defaultSince || '' : '';
        const tagSegment = filtersForm ? filtersForm.dataset.tagSegment || '' : '';
        const categorySegment = filtersForm ? filtersForm.dataset.categorySegment || '' : '';
        const defaultTagValue = filtersForm ? filtersForm.dataset.defaultTag || '' : '';
        const defaultCategoryValue = filtersForm ? filtersForm.dataset.defaultCategory || '' : '';
        const tagParam = filtersForm ? filtersForm.dataset.tagParam || 'gta_tag' : 'gta_tag';
        const categoryParam = filtersForm ? filtersForm.dataset.categoryParam || 'gta_category' : 'gta_category';

        let preservedArgs = {};
        if (filtersForm) {
            try {
                preservedArgs = JSON.parse(filtersForm.dataset.preserve || '{}');
            } catch (error) {
                preservedArgs = {};
            }

            if (tagParam) {
                delete preservedArgs[tagParam];
            }
            delete preservedArgs.tag;
            if (categoryParam) {
                delete preservedArgs[categoryParam];
            }
            delete preservedArgs.cat;
        }

        const parseUrl = (value) => {
            try {
                return new URL(value, window.location.origin);
            } catch (error) {
                return new URL(window.location.href);
            }
        };

        const escapeHtml = (value) => {
            if (value === null || value === undefined) {
                return '';
            }

            if (typeof value === 'object') {
                try {
                    value = JSON.stringify(value);
                } catch (error) {
                    return '';
                }
            }

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/'/g, '&#39;')
                .replace(/\//g, '&#x2F;');
        };

        const escapeAttribute = (value) => {
            if (value === null || value === undefined) {
                return '';
            }

            if (typeof value === 'object') {
                try {
                    value = JSON.stringify(value);
                } catch (error) {
                    return '';
                }
            }

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        };

        const buildPrettyUrl = (sortValue, sinceValue, tagValue, categoryValue) => {
            const base = baseFilterUrl || queryFilterBase || window.location.href;
            const baseUrl = parseUrl(base);
            let path = baseUrl.pathname.replace(/\/+$/, '');

            if (path === '') {
                path = '/';
            }

            if (!path.endsWith('/')) {
                path += '/';
            }

            const segments = [];

            if (sortValue && sortValue !== defaultSortValue) {
                segments.push(encodeURIComponent(sortValue));
            }

            if (sinceValue && sinceValue !== defaultSinceValue) {
                segments.push(encodeURIComponent(sinceValue));
            }

            const filterSegments = [];

            if (tagSegment && tagValue && tagValue !== defaultTagValue) {
                const tagParts = tagValue.split('+').filter((part) => part.length > 0);
                filterSegments.push(`${tagSegment}/${tagParts.map((part) => encodeURIComponent(part)).join('+')}`);
            }

            if (categorySegment && categoryValue && categoryValue !== defaultCategoryValue) {
                const categoryParts = categoryValue.split('/').filter((part) => part.length > 0);
                filterSegments.push(`${categorySegment}/${categoryParts.map((part) => encodeURIComponent(part)).join('/')}`);
            }

            const allSegments = [...segments, ...filterSegments];

            if (allSegments.length) {
                path += allSegments.join('/') + '/';
            }

            let finalPath = path.replace(/\/{2,}/g, '/');
            if (!finalPath.endsWith('/')) {
                finalPath += '/';
            }

            const params = new URLSearchParams();
            Object.entries(preservedArgs).forEach(([key, value]) => {
                if (value !== undefined && value !== null && String(value).length > 0) {
                    params.set(key, value);
                }
            });

            const queryString = params.toString();
            const absolutePath = baseUrl.origin + finalPath;

            return queryString ? `${absolutePath}?${queryString}` : absolutePath;
        };

        const buildQueryUrl = (sortValue, sinceValue, tagValue, categoryValue) => {
            const base = queryFilterBase || baseFilterUrl || window.location.href;
            const url = parseUrl(base);

            ['gta_sort', 'sort'].forEach((key) => url.searchParams.delete(key));
            ['gta_since', 'since'].forEach((key) => url.searchParams.delete(key));
            url.searchParams.delete('paged');
            if (tagParam) {
                url.searchParams.delete(tagParam);
                if (tagParam !== 'tag') {
                    url.searchParams.delete('tag');
                }
            }
            if (categoryParam) {
                url.searchParams.delete(categoryParam);
                if (categoryParam !== 'cat') {
                    url.searchParams.delete('cat');
                }
            }

            Object.entries(preservedArgs).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    url.searchParams.set(key, value);
                }
            });

            if (sortValue && sortValue !== defaultSortValue) {
                url.searchParams.set('gta_sort', sortValue);
            }

            if (sinceValue && sinceValue !== defaultSinceValue) {
                url.searchParams.set('gta_since', sinceValue);
            }

            if (tagSegment && tagParam && tagValue && tagValue !== defaultTagValue) {
                url.searchParams.set(tagParam, tagValue);
            }

            if (categorySegment && categoryParam && categoryValue && categoryValue !== defaultCategoryValue) {
                url.searchParams.set(categoryParam, categoryValue);
            }

            return url.toString();
        };

        const navigateWithFilters = () => {
            const sortValue = sortSelect ? sortSelect.value : defaultSortValue;
            const sinceValue = sinceSelect ? sinceSelect.value : defaultSinceValue;
            const tagValue = tagSelect ? tagSelect.value : defaultTagValue;
            const categoryValue = categorySelect ? categorySelect.value : defaultCategoryValue;
            const targetUrl = prettyFiltersEnabled
                ? buildPrettyUrl(sortValue, sinceValue, tagValue, categoryValue)
                : buildQueryUrl(sortValue, sinceValue, tagValue, categoryValue);

            window.location.href = targetUrl;
        };

        const createGridCardHTML = (mod, index) => {
            const link = escapeAttribute(mod.link || '#');
            const imageUrl = escapeAttribute(mod.imageUrl || '');
            const imageAlt = escapeAttribute(mod.imageAlt || `${mod.title || ''} - GTA 6 mod thumbnail`);
            const imageWidth = Number(mod.imageWidth) || 1280;
            const imageHeight = Number(mod.imageHeight) || 720;
            const loading = index < 3 ? 'eager' : 'lazy';
            const fetchPriority = index === 0 ? 'high' : 'auto';
            const title = escapeHtml(mod.title || '');
            const author = escapeHtml(mod.author || '');
            const date = escapeHtml(mod.date || '');
            const category = escapeHtml(mod.category || '');
            const rating = Number(mod.rating || 0).toFixed(1);
            const likes = Number(mod.likes || 0).toLocaleString('hu-HU');
            const downloads = Number(mod.downloads || 0).toLocaleString('hu-HU');
            const featuredBadge = Boolean(mod.isFeatured);

            return `
            <div class="card mod-card transition duration-300">
                <a href="${link}" class="block">
                    <div class="relative">
                        <img src="${imageUrl}" alt="${imageAlt}" loading="${loading}" decoding="async" fetchpriority="${fetchPriority}" width="${imageWidth}" height="${imageHeight}" class="w-full aspect-video object-cover rounded-t-lg">
                        ${featuredBadge ? `<div class="absolute top-2 left-2"><span class="bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-1 rounded-full"><?php echo esc_html__('KIEMELT', 'gta6-mods'); ?></span></div>` : ''}
                        <div class="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                            <div class="flex justify-between items-center">
                                <span class="flex items-center font-semibold text-yellow-400"><i class="fa-solid fa-star mr-1"></i>${rating}</span>
                                <div class="flex items-center space-x-3">
                                    <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1"></i>${likes}</span>
                                    <span class="flex items-center"><i class="fa-solid fa-download mr-1"></i>${downloads}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 space-y-2">
                        <h4 class="font-semibold text-gray-900 text-sm line-clamp-2">${title}</h4>
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span class="flex items-center"><i class="fa-solid fa-user mr-1.5"></i>${author}</span>
                            <span class="flex items-center"><i class="fa-regular fa-calendar mr-1.5"></i>${date}</span>
                        </div>
                        ${category ? `<div><span class="inline-flex items-center px-2.5 py-1 rounded-full bg-gray-100 text-xs text-gray-700"><i class="fa-solid fa-tags mr-1"></i>${category}</span></div>` : ''}
                    </div>
                </a>
            </div>
        `;
        };

        const createListCardHTML = (mod, index) => {
            const link = escapeAttribute(mod.link || '#');
            const imageUrl = escapeAttribute(mod.imageUrl || '');
            const imageAlt = escapeAttribute(mod.imageAlt || `${mod.title || ''} - GTA 6 mod thumbnail`);
            const imageWidth = Number(mod.imageWidth) || 1280;
            const imageHeight = Number(mod.imageHeight) || 720;
            const loading = index < 3 ? 'eager' : 'lazy';
            const fetchPriority = index === 0 ? 'high' : 'auto';
            const title = escapeHtml(mod.title || '');
            const author = escapeHtml(mod.author || '');
            const date = escapeHtml(mod.date || '');
            const category = escapeHtml(mod.category || '');
            const rating = Number(mod.rating || 0).toFixed(1);
            const likes = Number(mod.likes || 0).toLocaleString('hu-HU');
            const downloads = Number(mod.downloads || 0).toLocaleString('hu-HU');
            const featuredBadge = Boolean(mod.isFeatured);
            const tags = Array.isArray(mod.tags) ? mod.tags.map((tag) => `<span class="px-2 py-1 rounded bg-gray-100 border">${escapeHtml(tag)}</span>`).join('') : '';

            return `
            <div class="card transition duration-300">
                <a href="${link}" class="flex flex-col sm:flex-row gap-5 p-4 items-center w-full hover:bg-gray-50/50">
                    <div class="relative w-full sm:w-64 flex-shrink-0">
                        <img src="${imageUrl}" alt="${imageAlt}" loading="${loading}" decoding="async" fetchpriority="${fetchPriority}" width="${imageWidth}" height="${imageHeight}" class="w-full h-auto object-cover rounded-md aspect-video">
                        ${featuredBadge ? `<div class="absolute top-1 left-1"><span class="bg-yellow-400 text-yellow-900 text-[10px] font-bold px-1.5 py-0.5 rounded"><?php echo esc_html__('KIEMELT', 'gta6-mods'); ?></span></div>` : ''}
                    </div>
                    <div class="flex-grow w-full">
                        <h4 class="font-semibold text-gray-900 text-lg mb-1 line-clamp-2">${title}</h4>
                        <p class="text-base text-gray-600 mb-1">${author}</p>
                        <p class="text-sm text-gray-500 mb-3 flex items-center gap-3">
                            ${category ? `<span class="flex items-center"><i class="fa-solid fa-tags mr-1"></i>${category}</span>` : ''}
                            <span class="flex items-center"><i class="fa-regular fa-calendar mr-1"></i>${date}</span>
                        </p>
                        <div class="flex flex-wrap gap-1 text-xs">
                            ${tags}
                        </div>
                    </div>
                    <div class="flex-shrink-0 w-full sm:w-auto flex sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-2 pt-3 sm:pt-0 border-t sm:border-t-0 sm:border-l pl-0 sm:pl-8 mt-3 sm:mt-0">
                        <div class="flex sm:flex-col items-end gap-x-3 gap-y-1 text-base text-gray-500">
                            <span class="flex items-center text-yellow-500 font-bold"><i class="fa-solid fa-star mr-1.5 w-4 text-center"></i>${rating}</span>
                            <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1.5 w-4 text-center"></i>${likes}</span>
                            <span class="flex items-center"><i class="fa-solid fa-download mr-1.5 w-4 text-center"></i>${downloads}</span>
                        </div>
                    </div>
                </a>
            </div>
        `;
        };

        const renderMods = (view = 'grid') => {
            if (!container) {
                return;
            }

            container.innerHTML = '';

            const adHTMLGrid = `<div class="card p-4 text-center text-gray-400 font-semibold flex items-center justify-center h-[90px] w-full sm:col-span-2 md:col-span-3 lg:col-span-4 bg-gray-50 border-dashed border-2 border-gray-300"><?php echo esc_html__('Hirdetés (728×90)', 'gta6-mods'); ?></div>`;
            const adHTMLList = `<div class="card p-4 text-center text-gray-400 font-semibold flex items-center justify-center h-[90px] w-full bg-gray-50 border-dashed border-2 border-gray-300"><?php echo esc_html__('Hirdetés (728×90)', 'gta6-mods'); ?></div>`;

            const cardTemplate = view === 'grid' ? createGridCardHTML : createListCardHTML;
            const adTemplate = view === 'grid' ? adHTMLGrid : adHTMLList;

            if (view === 'grid') {
                container.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6';
            } else {
                container.className = 'space-y-4';
            }

            if (!modsData.length) {
                container.innerHTML = `<div class="card p-6 text-center text-gray-500">${<?php echo wp_json_encode(esc_html__('Jelenleg nincs megjeleníthető tartalom.', 'gta6-mods')); ?>}</div>`;
                return;
            }

            modsData.forEach((mod, index) => {
                container.insertAdjacentHTML('beforeend', cardTemplate(mod, index));

                const itemsRendered = index + 1;
                const remainingItems = modsData.length - itemsRendered;

                if (itemsRendered % 8 === 0 && remainingItems > 0 && remainingItems >= 5) {
                    container.insertAdjacentHTML('beforeend', adTemplate);
                }
            });
        };

        if (gridBtn && listBtn) {
            gridBtn.addEventListener('click', () => {
                renderMods('grid');
                gridBtn.classList.add('bg-gray-900', 'text-white');
                listBtn.classList.remove('bg-gray-900', 'text-white');
            });

            listBtn.addEventListener('click', () => {
                renderMods('list');
                listBtn.classList.add('bg-gray-900', 'text-white');
                gridBtn.classList.remove('bg-gray-900', 'text-white');
            });
        }

        if (filtersForm) {
            filtersForm.addEventListener('submit', (event) => {
                event.preventDefault();
                navigateWithFilters();
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', navigateWithFilters);
        }

        if (sinceSelect) {
            sinceSelect.addEventListener('change', navigateWithFilters);
        }

        if (tagSelect) {
            tagSelect.addEventListener('change', navigateWithFilters);
        }

        if (categorySelect) {
            categorySelect.addEventListener('change', navigateWithFilters);
        }

        renderMods('grid');
    });
</script>
