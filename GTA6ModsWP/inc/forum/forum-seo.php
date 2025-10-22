<?php
/**
 * Forum SEO Meta Tags and Content Optimisation
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates dynamic SEO meta tags for forum threads.
 */
function gta6m_forum_thread_meta_tags(): void
{
    if (!is_singular('forum_thread')) {
        return;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return;
    }

    $cache_key   = gta6m_generate_cache_key($post_id, 'thread_meta', 'v3');
    $cache_group = 'gta6m_seo';
    $cached      = wp_cache_get($cache_key, $cache_group);

    if (false !== $cached) {
        echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }

    gta6m_track_cache_key($cache_key, $cache_group);

    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    $first_image       = gta6m_extract_first_image_url($post->post_content);
    $og_image          = $first_image ?: 'https://gta6-mods.com/assets/og-default-forum.jpg';
    $meta_desc         = gta6m_generate_meta_description($post->post_content);
    $document_title    = gta6m_get_thread_document_title($post_id);
    $brand_label       = gta6m_get_forum_brand_label();
    $thread_title_text = gta6m_normalize_seo_text(get_the_title($post_id));

    $author_id = (int) $post->post_author;

    $meta_desc_attr      = gta6m_prepare_attribute_text($meta_desc);
    $document_title_attr = gta6m_prepare_attribute_text($document_title);
    $thread_title_attr   = gta6m_prepare_attribute_text($thread_title_text);
    $brand_label_attr    = gta6m_prepare_attribute_text($brand_label);

    $last_comment_ids = get_comments([
        'post_id' => $post_id,
        'number'  => 1,
        'orderby' => 'comment_date_gmt',
        'order'   => 'DESC',
        'status'  => 'approve',
        'fields'  => 'ids',
        'no_found_rows' => true,
    ]);

    $date_modified = get_the_modified_date('c', $post_id);
    if (!empty($last_comment_ids)) {
        $comment = get_comment($last_comment_ids[0]);
        if ($comment) {
            $comment_date = get_comment_date('c', $comment);
            if ($comment_date && strtotime($comment_date) > strtotime($date_modified)) {
                $date_modified = $comment_date;
            }
        }
    }

    ob_start();
    ?>
    <!-- Forum Thread SEO Meta Tags -->
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="description" content="<?php echo gta6m_escape_attr_preserve_apostrophes($meta_desc_attr); ?>">
    <link rel="canonical" href="<?php echo esc_url(get_permalink($post_id)); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo gta6m_escape_attr_preserve_apostrophes($document_title_attr); ?>">
    <meta property="og:description" content="<?php echo gta6m_escape_attr_preserve_apostrophes($meta_desc_attr); ?>">
    <meta property="og:url" content="<?php echo esc_url(get_permalink($post_id)); ?>">
    <meta property="og:image" content="<?php echo esc_url($og_image); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo gta6m_escape_attr_preserve_apostrophes($thread_title_attr); ?>">
    <meta property="og:site_name" content="<?php echo gta6m_escape_attr_preserve_apostrophes($brand_label_attr); ?>">
    <meta property="article:published_time" content="<?php echo esc_attr(get_the_date('c', $post_id)); ?>">
    <meta property="article:modified_time" content="<?php echo esc_attr($date_modified); ?>">
    <meta property="article:author" content="<?php echo esc_url(get_author_posts_url($author_id)); ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo gta6m_escape_attr_preserve_apostrophes($document_title_attr); ?>">
    <meta name="twitter:description" content="<?php echo gta6m_escape_attr_preserve_apostrophes($meta_desc_attr); ?>">
    <meta name="twitter:image" content="<?php echo esc_url($og_image); ?>">
    <meta name="twitter:image:alt" content="<?php echo gta6m_escape_attr_preserve_apostrophes($thread_title_attr); ?>">

    <?php if ($first_image) : ?>
        <link rel="preload" as="image" href="<?php echo esc_url($first_image); ?>" fetchpriority="high">
    <?php endif; ?>

    <link rel="dns-prefetch" href="//cdn.gta6-mods.com">
    <link rel="preconnect" href="https://cdn.gta6-mods.com" crossorigin>

    <?php echo gta6m_forum_thread_structured_data($post_id, $date_modified); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php
    $output = ob_get_clean();
    wp_cache_set($cache_key, $output, $cache_group, HOUR_IN_SECONDS * 6);

    echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('wp_head', 'gta6m_forum_thread_meta_tags', 1);

/**
 * Generates structured data for forum threads.
 */
function gta6m_forum_thread_structured_data(int $post_id, string $date_modified): string
{
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }

    $comment_count    = (int) get_comments_number($post_id);
    $first_image      = gta6m_extract_first_image_url($post->post_content);
    $flairs           = get_the_terms($post_id, 'forum_flair');
    $author_id        = (int) $post->post_author;
    $thread_title     = gta6m_normalize_seo_text(get_the_title($post_id));
    $author_name      = gta6m_normalize_seo_text(get_the_author_meta('display_name', $author_id));
    $meta_description = gta6m_generate_meta_description($post->post_content);

    $structured_data = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'              => 'DiscussionForumPosting',
                '@id'                => get_permalink($post_id) . '#discussion',
                'headline'           => $thread_title,
                'description'        => $meta_description,
                'datePublished'      => get_the_date('c', $post_id),
                'dateModified'       => $date_modified,
                'author'             => [
                    '@type' => 'Person',
                    'name'  => $author_name,
                    'url'   => get_author_posts_url($author_id),
                ],
                'interactionStatistic'=> [
                    '@type'               => 'InteractionCounter',
                    'interactionType'     => 'https://schema.org/CommentAction',
                    'userInteractionCount'=> $comment_count,
                ],
                'mainEntityOfPage'    => [
                    '@type' => 'WebPage',
                    '@id'   => get_permalink($post_id),
                ],
            ],
            [
                '@type'           => 'BreadcrumbList',
                'itemListElement' => gta6m_generate_breadcrumb_list($post_id, $flairs),
            ],
        ],
    ];

    if ($first_image) {
        $image_object = [
            '@type' => 'ImageObject',
            'url'   => $first_image,
        ];

        $dimensions = gta6m_get_image_dimensions_from_metadata($first_image);
        if (is_array($dimensions)) {
            $image_object['width']  = $dimensions['width'];
            $image_object['height'] = $dimensions['height'];
        }

        $structured_data['@graph'][0]['image'] = $image_object;
    }

    return sprintf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

/**
 * Generates SEO meta tags for flair archive pages.
 */
function gta6m_forum_flair_meta_tags(): void
{
    if (!is_tax('forum_flair')) {
        return;
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return;
    }

    $paged     = max(1, (int) get_query_var('paged', 1));
    $max_pages = isset($GLOBALS['wp_query']->max_num_pages) ? (int) $GLOBALS['wp_query']->max_num_pages : 1;

    $term_modified = (int) get_term_meta($term->term_id, '_last_modified', true);
    if (0 === $term_modified) {
        $term_modified = time();
        update_term_meta($term->term_id, '_last_modified', $term_modified);
    }

    $cache_key   = sprintf('gta6m_flair_meta_%d_%d_%d_v3', $term->term_id, $paged, $term_modified);
    $cache_group = 'gta6m_seo';
    $cached      = wp_cache_get($cache_key, $cache_group);

    if (false !== $cached) {
        echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }

    gta6m_track_cache_key($cache_key, $cache_group);

    $term_name      = gta6m_normalize_seo_text($term->name);
    $thread_count   = gta6m_get_flair_thread_count($term->term_id);
    $latest_image   = gta6m_get_latest_flair_thread_image($term->term_id);
    $og_image       = $latest_image ?: 'https://gta6-mods.com/assets/og-default-forum.jpg';
    $document_title = gta6m_get_flair_document_title($term, $paged);
    $brand_label    = gta6m_get_forum_brand_label();

    if (is_wp_error($term_link = get_term_link($term))) {
        return;
    }

    $meta_desc = $paged > 1
        ? sprintf(
            /* translators: %1$s: flair name, %2$s: thread count formatted, %3$d: page number */
            __('Discover the latest GTA 6 discussions and mods tagged with "%1$s". %2$s threads available. Page %3$d.', 'gta6mods'),
            $term_name,
            number_format_i18n($thread_count),
            $paged
        )
        : sprintf(
            /* translators: %1$s: flair name, %2$s: thread count formatted */
            __('Find the latest GTA 6 discussions and mods tagged with "%1$s". Join our community for updates, guides, and more. %2$s threads available.', 'gta6mods'),
            $term_name,
            number_format_i18n($thread_count)
        );

    $meta_desc = gta6m_normalize_seo_text($meta_desc);
    $meta_desc_attr      = gta6m_prepare_attribute_text($meta_desc);
    $document_title_attr = gta6m_prepare_attribute_text($document_title);
    $brand_label_attr    = gta6m_prepare_attribute_text($brand_label);

    $robots_directive = $paged > 1 ? 'noindex, follow' : 'index, follow';

    ob_start();
    ?>
    <!-- Flair Archive SEO Meta Tags -->
    <meta name="robots" content="<?php echo esc_attr($robots_directive); ?>, max-snippet:-1, max-image-preview:large">
    <meta name="description" content="<?php echo gta6m_escape_attr_preserve_apostrophes($meta_desc_attr); ?>">

    <?php if ($paged > 1) : ?>
        <link rel="canonical" href="<?php echo esc_url(add_query_arg('page', $paged, $term_link)); ?>">
        <link rel="prev" href="<?php echo esc_url($paged > 2 ? add_query_arg('page', $paged - 1, $term_link) : $term_link); ?>">
        <?php if ($paged < $max_pages) : ?>
            <link rel="next" href="<?php echo esc_url(add_query_arg('page', $paged + 1, $term_link)); ?>">
        <?php endif; ?>
    <?php else : ?>
        <link rel="canonical" href="<?php echo esc_url($term_link); ?>">
        <?php if ($max_pages > 1) : ?>
            <link rel="next" href="<?php echo esc_url(add_query_arg('page', 2, $term_link)); ?>">
        <?php endif; ?>
    <?php endif; ?>

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo gta6m_escape_attr_preserve_apostrophes($document_title_attr); ?>">
    <meta property="og:description" content="<?php echo gta6m_escape_attr_preserve_apostrophes($meta_desc_attr); ?>">
    <meta property="og:url" content="<?php echo esc_url($term_link); ?>">
    <meta property="og:image" content="<?php echo esc_url($og_image); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="<?php echo gta6m_escape_attr_preserve_apostrophes($brand_label_attr); ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo gta6m_escape_attr_preserve_apostrophes($document_title_attr); ?>">
    <meta name="twitter:description" content="<?php echo gta6m_escape_attr_preserve_apostrophes($meta_desc_attr); ?>">
    <meta name="twitter:image" content="<?php echo esc_url($og_image); ?>">

    <?php
    if (1 === $paged) {
        echo gta6m_flair_archive_structured_data($term); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    $output = ob_get_clean();
    wp_cache_set($cache_key, $output, $cache_group, HOUR_IN_SECONDS * 6);

    echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('wp_head', 'gta6m_forum_flair_meta_tags', 1);

/**
 * Gets thread count for flair (cached with term meta tracking).
 */
function gta6m_get_flair_thread_count(int $term_id): int
{
    $cache_key   = 'gta6m_flair_count_' . $term_id;
    $cache_group = 'gta6m_counts';
    $cached      = wp_cache_get($cache_key, $cache_group);

    if (false !== $cached) {
        return (int) $cached;
    }

    $count = (int) get_term_meta($term_id, '_thread_count', true);
    if ($count <= 0) {
        $count = gta6m_recalculate_flair_thread_count($term_id);
    }

    wp_cache_set($cache_key, $count, $cache_group, HOUR_IN_SECONDS * 6);
    gta6m_track_cache_key($cache_key, $cache_group);

    return $count;
}

/**
 * Gets latest thread image from flair (NO external calls).
 */
function gta6m_get_latest_flair_thread_image(int $term_id): string|false
{
    $cache_key   = 'gta6m_flair_img_' . $term_id;
    $cache_group = 'gta6m_images';
    $cached      = wp_cache_get($cache_key, $cache_group);

    if (false !== $cached) {
        return 'none' === $cached ? false : (string) $cached;
    }

    $threads = get_posts([
        'post_type'              => 'forum_thread',
        'post_status'            => 'publish',
        'posts_per_page'         => 10,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => [
            [
                'taxonomy' => 'forum_flair',
                'field'    => 'term_id',
                'terms'    => $term_id,
            ],
        ],
    ]);

    $image = false;
    foreach ($threads as $thread_id) {
        $content     = get_post_field('post_content', $thread_id);
        $first_image = $content ? gta6m_extract_first_image_url($content) : false;
        if ($first_image) {
            $image = $first_image;
            break;
        }
    }

    wp_cache_set($cache_key, $image ?: 'none', $cache_group, HOUR_IN_SECONDS * 6);
    gta6m_track_cache_key($cache_key, $cache_group);

    return $image ?: false;
}

/**
 * Generates structured data for flair archives.
 */
function gta6m_flair_archive_structured_data(WP_Term $term): string
{
    $threads = get_posts([
        'post_type'              => 'forum_thread',
        'post_status'            => 'publish',
        'posts_per_page'         => 15,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => [
            [
                'taxonomy' => 'forum_flair',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ],
        ],
    ]);

    $item_list = [];
    $position  = 1;

    foreach ($threads as $thread_id) {
        $comment_count = (int) get_comments_number($thread_id);
        $content       = get_post_field('post_content', $thread_id);
        $first_image   = $content ? gta6m_extract_first_image_url($content) : false;
        $author_id     = (int) get_post_field('post_author', $thread_id);
        $thread_title  = gta6m_normalize_seo_text(get_the_title($thread_id));
        $author_name   = gta6m_normalize_seo_text(get_the_author_meta('display_name', $author_id));

        $item = [
            '@type'   => 'DiscussionForumPosting',
            'position'=> $position,
            'name'    => $thread_title,
            'url'     => get_permalink($thread_id),
            'author'  => [
                '@type' => 'Person',
                'name'  => $author_name,
            ],
            'interactionStatistic' => [
                '@type'               => 'InteractionCounter',
                'interactionType'     => 'https://schema.org/CommentAction',
                'userInteractionCount'=> $comment_count,
            ],
        ];

        if ($first_image) {
            $item['image'] = $first_image;
        }

        $item_list[] = $item;
        $position++;
    }

    $thread_count = gta6m_get_flair_thread_count($term->term_id);
    $term_link    = get_term_link($term);

    if (is_wp_error($term_link)) {
        return '';
    }

    $term_name      = gta6m_normalize_seo_text($term->name);
    $structured_data = [
        '@context' => 'https://schema.org',
        '@type'    => 'CollectionPage',
        '@id'      => $term_link,
        'name'     => sprintf(
            /* translators: %s: flair name */
            __('GTA 6 %s Mods & Discussions', 'gta6mods'),
            $term_name
        ),
        'description' => sprintf(
            /* translators: %1$s: flair name, %2$s: thread count */
            __('Community discussions and mods tagged with "%1$s". %2$s active threads.', 'gta6mods'),
            $term_name,
            number_format_i18n($thread_count)
        ),
        'url' => $term_link,
        'breadcrumb' => [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'   => 'ListItem',
                    'position'=> 1,
                    'name'    => __('Home', 'gta6mods'),
                    'item'    => home_url('/'),
                ],
                [
                    '@type'   => 'ListItem',
                    'position'=> 2,
                    'name'    => __('Forum', 'gta6mods'),
                    'item'    => function_exists('gta6_forum_get_main_url') ? gta6_forum_get_main_url() : home_url('/forum/'),
                ],
                [
                    '@type'   => 'ListItem',
                    'position'=> 3,
                    'name'    => $term_name,
                    'item'    => $term_link,
                ],
            ],
        ],
        'mainEntity' => [
            '@type'           => 'ItemList',
            'numberOfItems'   => $thread_count,
            'itemListElement' => $item_list,
        ],
    ];

    return sprintf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

/**
 * Optimizes images in forum content (NO external calls!).
 */
function gta6m_optimize_forum_images(string $content): string
{
    if (!is_singular('forum_thread')) {
        return $content;
    }

    if ('' === $content || false === strpos($content, '<img')) {
        return $content;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return $content;
    }

    $cache_key   = gta6m_generate_cache_key($post_id, 'opt_images', md5($content));
    $cache_group = 'gta6m_content';
    $cached      = wp_cache_get($cache_key, $cache_group);

    if (false !== $cached) {
        return (string) $cached;
    }

    gta6m_track_cache_key($cache_key, $cache_group);

    $dom              = new DOMDocument();
    $previous_setting = libxml_use_internal_errors(true);
    $dom->loadHTML(
        mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'),
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous_setting);

    $images      = $dom->getElementsByTagName('img');
    $image_index = 1;
    $post_title  = gta6m_normalize_seo_text(get_the_title($post_id));

    foreach ($images as $img) {
        if (!$img->hasAttribute('alt') || '' === trim($img->getAttribute('alt'))) {
            $alt_text = sprintf(
                /* translators: %1$s: thread title, %2$d: image number */
                __('%1$s - Image %2$d', 'gta6mods'),
                $post_title,
                $image_index
            );
            $img->setAttribute('alt', $alt_text);
        }

        if (!$img->hasAttribute('title')) {
            $img->setAttribute('title', $img->getAttribute('alt'));
        }

        if (!$img->hasAttribute('width') || !$img->hasAttribute('height')) {
            $src = $img->getAttribute('src');
            if ($src) {
                $dimensions = gta6m_get_image_dimensions_from_metadata($src);
                if (is_array($dimensions)) {
                    $img->setAttribute('width', (string) $dimensions['width']);
                    $img->setAttribute('height', (string) $dimensions['height']);
                }
            }
        }

        $img->setAttribute('loading', $image_index <= 2 ? 'eager' : 'lazy');

        if (1 === $image_index) {
            $img->setAttribute('fetchpriority', 'high');
        }

        $img->setAttribute('decoding', 'async');

        $aria_label = sprintf(
            /* translators: %s: image alt text */
            __('Image: %s', 'gta6mods'),
            $img->getAttribute('alt')
        );
        $img->setAttribute('aria-label', $aria_label);

        $image_index++;
    }

    $optimized = $dom->saveHTML();
    wp_cache_set($cache_key, $optimized, $cache_group, DAY_IN_SECONDS);

    return $optimized;
}
add_filter('the_content', 'gta6m_optimize_forum_images', 20);

/**
 * Optimizes links in forum content.
 */
function gta6m_optimize_forum_links(string $content): string
{
    if (!is_singular('forum_thread')) {
        return $content;
    }

    if ('' === $content || false === strpos($content, '<a')) {
        return $content;
    }

    $post_id = get_the_ID();
    if (!$post_id) {
        return $content;
    }

    $cache_key   = gta6m_generate_cache_key($post_id, 'opt_links', md5($content));
    $cache_group = 'gta6m_content';
    $cached      = wp_cache_get($cache_key, $cache_group);

    if (false !== $cached) {
        return (string) $cached;
    }

    gta6m_track_cache_key($cache_key, $cache_group);

    $dom              = new DOMDocument();
    $previous_setting = libxml_use_internal_errors(true);
    $dom->loadHTML(
        mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'),
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous_setting);

    $links     = $dom->getElementsByTagName('a');
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);

    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if ('' === $href || '#' === $href) {
            continue;
        }

        $link_host  = wp_parse_url($href, PHP_URL_HOST);
        $is_external = $link_host && $link_host !== $site_host;

        if ($is_external) {
            $link->setAttribute('rel', 'noopener noreferrer nofollow');
            $link->setAttribute('target', '_blank');

            if (!$link->hasAttribute('title')) {
                $anchor_text = trim($link->nodeValue);
                $title       = sprintf(
                    /* translators: %s: link text */
                    __('Visit external link: %s (opens in new tab)', 'gta6mods'),
                    $anchor_text
                );
                $link->setAttribute('title', $title);
            }

            $aria_label = sprintf(
                /* translators: %s: link destination */
                __('External link to %s', 'gta6mods'),
                $link_host ?: __('external website', 'gta6mods')
            );
            $link->setAttribute('aria-label', $aria_label);
        } else {
            if (!$link->hasAttribute('title')) {
                $anchor_text = trim($link->nodeValue);
                if ('' !== $anchor_text) {
                    $link->setAttribute('title', $anchor_text);
                }
            }

            if (!$link->hasAttribute('aria-label')) {
                $aria_label = sprintf(
                    /* translators: %s: link text */
                    __('Navigate to: %s', 'gta6mods'),
                    trim($link->nodeValue)
                );
                $link->setAttribute('aria-label', $aria_label);
            }
        }
    }

    $optimized = $dom->saveHTML();
    wp_cache_set($cache_key, $optimized, $cache_group, DAY_IN_SECONDS);

    return $optimized;
}
add_filter('the_content', 'gta6m_optimize_forum_links', 21);

/**
 * Gets popular threads with caching.
 */
function gta6m_get_popular_threads_cached(int $count = 5): array
{
    $count      = max(1, $count);
    $cache_key   = 'gta6m_popular_threads_' . $count;
    $cache_group = 'gta6m_widgets';
    $cached      = wp_cache_get($cache_key, $cache_group);

    if (false !== $cached) {
        return is_array($cached) ? $cached : [];
    }

    $threads = get_posts([
        'post_type'              => 'forum_thread',
        'post_status'            => 'publish',
        'posts_per_page'         => $count,
        'orderby'                => 'comment_count',
        'order'                  => 'DESC',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    wp_cache_set($cache_key, $threads, $cache_group, HOUR_IN_SECONDS * 6);
    gta6m_track_cache_key($cache_key, $cache_group);

    return $threads;
}

/**
 * Clears all forum SEO caches when content changes.
 */
function gta6m_clear_forum_seo_caches(int $post_id): void
{
    if ('forum_thread' !== get_post_type($post_id)) {
        return;
    }

    gta6m_clear_cache_pattern(sprintf('gta6m_thread_meta_%d_*', $post_id), 'gta6m_seo');
    gta6m_clear_cache_pattern(sprintf('gta6m_opt_images_%d_*', $post_id), 'gta6m_content');
    gta6m_clear_cache_pattern(sprintf('gta6m_opt_links_%d_*', $post_id), 'gta6m_content');

    gta6m_clear_cache_pattern('gta6m_sitemap_threads_*', 'gta6m_sitemaps');
    wp_cache_delete('gta6m_sitemap_threads_max', 'gta6m_sitemaps');
    wp_cache_delete('gta6m_thread_lastmod_' . $post_id, 'gta6m_dates');

    gta6m_clear_cache_pattern('gta6m_popular_threads_*', 'gta6m_widgets');

    $flairs = get_the_terms($post_id, 'forum_flair');
    if (is_array($flairs)) {
        $timestamp = time();
        foreach ($flairs as $flair) {
            if (!$flair instanceof WP_Term) {
                continue;
            }

            update_term_meta($flair->term_id, '_last_modified', $timestamp);

            gta6m_recalculate_flair_thread_count($flair->term_id);

            wp_cache_delete('gta6m_flair_count_' . $flair->term_id, 'gta6m_counts');
            wp_cache_delete('gta6m_flair_img_' . $flair->term_id, 'gta6m_images');
            wp_cache_delete('gta6m_flair_lastmod_' . $flair->term_id, 'gta6m_dates');

            gta6m_clear_cache_pattern(sprintf('gta6m_flair_meta_%d_*', $flair->term_id), 'gta6m_seo');
        }

        wp_cache_delete('gta6m_sitemap_flairs', 'gta6m_sitemaps');
    }

    gta6m_clear_sidebar_caches();
}
add_action('save_post_forum_thread', 'gta6m_clear_forum_seo_caches');
add_action('delete_post', 'gta6m_clear_forum_seo_caches');

/**
 * Clears caches when new comments are added to threads.
 */
function gta6m_clear_caches_on_comment(int $comment_id): void
{
    $comment = get_comment($comment_id);
    if (!$comment) {
        return;
    }

    $post_id = (int) $comment->comment_post_ID;
    if ($post_id > 0 && 'forum_thread' === get_post_type($post_id)) {
        gta6m_clear_forum_seo_caches($post_id);
    }
}
add_action('wp_insert_comment', 'gta6m_clear_caches_on_comment');

/**
 * Starts sidebar output buffering for optimisation.
 */
function gta6m_optimize_sidebar_before($index = null, bool $has_widgets = true): void
{
    unset($index, $has_widgets);

    global $gta6m_sidebar_buffer_active;

    if (!empty($gta6m_sidebar_buffer_active)) {
        return;
    }

    $gta6m_sidebar_buffer_active = true;
    ob_start('gta6m_optimize_sidebar_content');
}
add_action('dynamic_sidebar_before', 'gta6m_optimize_sidebar_before', 10, 2);

/**
 * Flushes optimised sidebar output buffer.
 */
function gta6m_optimize_sidebar_after($index = null, bool $has_widgets = true): void
{
    unset($index, $has_widgets);

    global $gta6m_sidebar_buffer_active;

    if (!empty($gta6m_sidebar_buffer_active) && ob_get_level() > 0) {
        ob_end_flush();
    }

    $gta6m_sidebar_buffer_active = false;
}
add_action('dynamic_sidebar_after', 'gta6m_optimize_sidebar_after', 10, 2);

/**
 * Optimises sidebar markup and caches the result.
 */
function gta6m_optimize_sidebar_content(string $sidebar_content): string
{
    if ('' === trim($sidebar_content)) {
        return $sidebar_content;
    }

    $is_logged_in = is_user_logged_in();
    $cache_key    = sprintf(
        'gta6m_sidebar_%s_%s',
        $is_logged_in ? 'logged' : 'guest',
        md5($sidebar_content)
    );
    $cache_group  = 'gta6m_sidebar';

    $cached = wp_cache_get($cache_key, $cache_group);
    if (false !== $cached) {
        return (string) $cached;
    }

    $dom              = new DOMDocument();
    $previous_setting = libxml_use_internal_errors(true);
    $dom->loadHTML(
        mb_convert_encoding($sidebar_content, 'HTML-ENTITIES', 'UTF-8'),
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous_setting);

    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        $img->setAttribute('loading', 'lazy');
        $img->setAttribute('decoding', 'async');

        if (!$img->hasAttribute('width') || !$img->hasAttribute('height')) {
            $src = $img->getAttribute('src');
            if ('' !== $src) {
                $dimensions = gta6m_get_image_dimensions_from_metadata($src);
                if (is_array($dimensions)) {
                    $img->setAttribute('width', (string) $dimensions['width']);
                    $img->setAttribute('height', (string) $dimensions['height']);
                }
            }
        }

        if (!$img->hasAttribute('alt') || '' === trim($img->getAttribute('alt'))) {
            $img->setAttribute('alt', __('Sidebar image', 'gta6mods'));
        }
    }

    $links     = $dom->getElementsByTagName('a');
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);

    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if ('' === $href) {
            continue;
        }

        if (!$link->hasAttribute('title')) {
            $text = trim($link->textContent);
            if ('' !== $text) {
                $link->setAttribute('title', $text);
            }
        }

        $link_host  = wp_parse_url($href, PHP_URL_HOST);
        $is_external = $link_host && $link_host !== $site_host;

        if ($is_external) {
            $link->setAttribute('rel', 'noopener noreferrer nofollow');
            $link->setAttribute('target', '_blank');
        }
    }

    $headings = [];
    for ($i = 1; $i <= 6; $i++) {
        $tags = $dom->getElementsByTagName('h' . $i);
        foreach ($tags as $tag) {
            $headings[] = ['level' => $i, 'element' => $tag];
        }
    }

    foreach ($headings as $heading) {
        $level   = $heading['level'];
        $element = $heading['element'];

        if ($level < 3 && $element->parentNode) {
            $new_heading = $dom->createElement('h3', $element->textContent);

            if ($element->hasAttributes()) {
                foreach ($element->attributes as $attr) {
                    $new_heading->setAttribute($attr->nodeName, $attr->nodeValue);
                }
            }

            $element->parentNode->replaceChild($new_heading, $element);
        }
    }

    $optimized = $dom->saveHTML();

    wp_cache_set($cache_key, $optimized, $cache_group, HOUR_IN_SECONDS * 6);
    gta6m_track_cache_key($cache_key, $cache_group);

    return $optimized;
}

/**
 * Clears sidebar and avatar caches.
 */
function gta6m_clear_sidebar_caches(...$args): void
{
    unset($args);

    gta6m_clear_cache_pattern('gta6m_sidebar_*', 'gta6m_sidebar');
    gta6m_clear_cache_pattern('gta6m_avatar_*', 'gta6m_avatars');
    gta6m_clear_cache_pattern('gta6m_popular_threads_sd_*', 'gta6m_structured_data');
}
add_action('edit_user_profile_update', 'gta6m_clear_sidebar_caches');
add_action('profile_update', 'gta6m_clear_sidebar_caches');

/**
 * Removes the comment feed link for forum threads to avoid 404 responses.
 */
function gta6m_disable_forum_thread_feed(): void
{
    if (is_singular('forum_thread')) {
        remove_action('wp_head', 'feed_links_extra', 3);
    }
}
add_action('wp', 'gta6m_disable_forum_thread_feed', 20);

/**
 * Filters the document title for forum contexts to avoid duplicate tags.
 */
function gta6m_filter_forum_document_title(string $title): string
{
    if (is_singular('forum_thread')) {
        $post_id = get_queried_object_id();
        if ($post_id) {
            return gta6m_get_thread_document_title((int) $post_id);
        }

        return $title;
    }

    if (is_tax('forum_flair')) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $paged = max(1, (int) get_query_var('paged', 1));

            return gta6m_get_flair_document_title($term, $paged);
        }
    }

    return $title;
}
add_filter('pre_get_document_title', 'gta6m_filter_forum_document_title', 20);
