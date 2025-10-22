<?php
/**
 * Forum SEO Helper Functions
 *
 * CRITICAL: NO external HTTP calls, NO file system access during render
 * All data from WordPress internal APIs only
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extracts first image URL from content (NO external calls)
 */
function gta6m_extract_first_image_url(string $content): string|false
{
    if ('' === $content || false === strpos($content, '<img')) {
        return false;
    }

    preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

    if (empty($matches[1])) {
        return false;
    }

    return esc_url_raw($matches[1]);
}

/**
 * Normalises text for SEO usage by stripping tags, decoding entities and
 * replacing typographic quotes with ASCII characters.
 */
function gta6m_normalize_seo_text(string $text): string
{
    if ('' === $text) {
        return '';
    }

    $text = wp_strip_all_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $text = strtr(
        $text,
        [
            '‘' => "'",
            '’' => "'",
            '‚' => "'",
            '‛' => "'",
            '“' => '"',
            '”' => '"',
            '„' => '"',
            '‟' => '"',
        ]
    );

    $text = preg_replace('/[\x00-\x1F\x7F]+/u', '', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';

    return trim($text);
}

/**
 * Escapes attribute text while preserving ASCII apostrophes for readability.
 */
function gta6m_escape_attr_preserve_apostrophes(string $text): string
{
    if ('' === $text) {
        return '';
    }

    $text = str_replace("\"", chr(39), $text);

    $escaped = esc_attr($text);

    return str_replace(['&#039;', '&#8216;', '&#8217;'], "'", $escaped);
}

/**
 * Converts double quotes to single quotes before attribute escaping.
 */
function gta6m_prepare_attribute_text(string $text): string
{
    if ('' === $text) {
        return '';
    }

    return str_replace("\"", chr(39), $text);
}

/**
 * Gets image dimensions from WordPress attachment metadata ONLY.
 */
function gta6m_get_image_dimensions_from_metadata(string $image_url): array|false
{
    if ('' === $image_url) {
        return false;
    }

    $attachment_id = attachment_url_to_postid($image_url);

    if (!$attachment_id) {
        return false;
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    if (!is_array($metadata) || !isset($metadata['width'], $metadata['height'])) {
        return false;
    }

    return [
        'width'  => (int) $metadata['width'],
        'height' => (int) $metadata['height'],
    ];
}

/**
 * Generates SEO-optimized meta description (155-160 chars, natural ending).
 */
function gta6m_generate_meta_description(string $content): string
{
    $content = gta6m_normalize_seo_text($content);

    if ('' === $content) {
        return '';
    }

    if (mb_strlen($content) <= 160) {
        return $content;
    }

    $truncated        = mb_substr($content, 0, 155);
    $last_period      = mb_strrpos($truncated, '.');
    $last_exclamation = mb_strrpos($truncated, '!');
    $last_question    = mb_strrpos($truncated, '?');

    $natural_end = max($last_period ?: 0, $last_exclamation ?: 0, $last_question ?: 0);

    if ($natural_end > 100) {
        return mb_substr($content, 0, $natural_end + 1);
    }

    $last_space = mb_strrpos($truncated, ' ');
    if (false === $last_space) {
        return mb_substr($truncated, 0, 155) . '…';
    }

    return mb_substr($truncated, 0, $last_space) . '…';
}

/**
 * Generates cache key including post modification time to prevent stale cache.
 */
function gta6m_generate_cache_key(int $post_id, string $type, string $suffix = ''): string
{
    $modified = get_post_modified_time('U', true, $post_id);
    if (!$modified) {
        $modified = (string) current_time('timestamp', true);
    }

    $base = sprintf('gta6m_%s_%d_%s', sanitize_key($type), $post_id, $modified);

    if ('' === $suffix) {
        return $base;
    }

    return $base . '_' . sanitize_key($suffix);
}

/**
 * Clears cache keys matching a pattern (works with Redis/Memcached).
 */
function gta6m_clear_cache_pattern(string $pattern, string $group): void
{
    global $wp_object_cache;

    if (isset($wp_object_cache) && method_exists($wp_object_cache, 'redis_instance')) {
        $redis = $wp_object_cache->redis_instance();
        if ($redis) {
            $iterator = null;
            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if (false === $keys) {
                    break;
                }
                foreach ($keys as $key) {
                    wp_cache_delete($key, $group);
                }
            } while ($iterator > 0);
        }
    }

    $tracker_key = 'gta6m_cache_keys_' . $group;
    $known_keys  = wp_cache_get($tracker_key, $group);

    if (is_array($known_keys)) {
        foreach ($known_keys as $index => $known_key) {
            if (fnmatch($pattern, $known_key)) {
                wp_cache_delete($known_key, $group);
                unset($known_keys[$index]);
            }
        }
        wp_cache_set($tracker_key, array_values($known_keys), $group, DAY_IN_SECONDS);
    }
}

/**
 * Tracks cache keys for pattern-based deletion.
 */
function gta6m_track_cache_key(string $key, string $group): void
{
    $tracker_key = 'gta6m_cache_keys_' . $group;
    $known_keys  = wp_cache_get($tracker_key, $group);

    if (!is_array($known_keys)) {
        $known_keys = [];
    }

    if (!in_array($key, $known_keys, true)) {
        $known_keys[] = $key;
        wp_cache_set($tracker_key, $known_keys, $group, DAY_IN_SECONDS);
    }
}

/**
 * Generates breadcrumb list for structured data.
 *
 * @param int        $post_id Thread post ID.
 * @param array|bool $flairs  Flair terms.
 */
function gta6m_generate_breadcrumb_list(int $post_id, $flairs): array
{
    $breadcrumbs = [
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
    ];

    $position = 3;

    if (is_array($flairs) && !empty($flairs)) {
        $flair      = reset($flairs);
        if ($flair instanceof WP_Term) {
            $flair_link = get_term_link($flair);
            if (!is_wp_error($flair_link)) {
                $breadcrumbs[] = [
                    '@type'   => 'ListItem',
                    'position'=> $position,
                    'name'    => gta6m_normalize_seo_text($flair->name),
                    'item'    => $flair_link,
                ];
                $position++;
            }
        }
    }

    $thread_title = gta6m_normalize_seo_text(get_the_title($post_id));

    $breadcrumbs[] = [
        '@type'   => 'ListItem',
        'position'=> $position,
        'name'    => $thread_title,
        'item'    => get_permalink($post_id),
    ];

    return $breadcrumbs;
}

/**
 * Returns the forum brand label used for document titles.
 */
function gta6m_get_forum_brand_label(): string
{
    $site_name = trim((string) get_bloginfo('name'));
    $host      = wp_parse_url(home_url(), PHP_URL_HOST);

    if (false !== stripos($site_name, 'gta6-mods')) {
        $brand = 'GTA6-Mods.com';
    } elseif (!empty($host)) {
        $brand = $host;
    } elseif ('' !== $site_name) {
        $brand = $site_name;
    } else {
        $brand = __('Community', 'gta6mods');
    }

    $label = sprintf(
        /* translators: %s: forum brand name */
        __('%s Forums', 'gta6mods'),
        $brand
    );

    $label = gta6m_normalize_seo_text($label);

    return (string) apply_filters('gta6m_forum_brand_label', $label);
}

/**
 * Builds the document title for an individual forum thread.
 */
function gta6m_get_thread_document_title(int $post_id): string
{
    $thread_title = gta6m_normalize_seo_text((string) get_the_title($post_id));
    if ('' === $thread_title) {
        $thread_title = __('Forum Thread', 'gta6mods');
    }

    $title = sprintf(
        /* translators: 1: thread title, 2: forum brand label */
        __('%1$s - %2$s', 'gta6mods'),
        $thread_title,
        gta6m_get_forum_brand_label()
    );

    return gta6m_normalize_seo_text($title);
}

/**
 * Builds the document title for a flair archive page.
 */
function gta6m_get_flair_document_title(WP_Term $term, int $paged = 1): string
{
    $flair_name = gta6m_normalize_seo_text($term->name);

    $base = sprintf(
        /* translators: %s: flair name */
        __('GTA 6 %s Mods & Discussions', 'gta6mods'),
        $flair_name
    );

    if ($paged > 1) {
        $base = sprintf(
            /* translators: 1: base title, 2: page number */
            __('%1$s - Page %2$d', 'gta6mods'),
            $base,
            $paged
        );
    }

    $title = sprintf(
        /* translators: 1: flair title, 2: forum brand label */
        __('%1$s - %2$s', 'gta6mods'),
        $base,
        gta6m_get_forum_brand_label()
    );

    return gta6m_normalize_seo_text($title);
}

/**
 * Recalculates flair thread count and stores it in term meta.
 */
function gta6m_recalculate_flair_thread_count(int $term_id): int
{
    $term = get_term($term_id, 'forum_flair');
    if ($term instanceof WP_Term) {
        update_term_meta($term_id, '_thread_count', (int) $term->count);

        return (int) $term->count;
    }

    update_term_meta($term_id, '_thread_count', 0);

    return 0;
}

/**
 * Outputs structured data for a list of popular threads.
 */
function gta6m_popular_threads_structured_data(array $threads): void
{
    if (empty($threads)) {
        return;
    }

    $items    = [];
    $position = 1;

    foreach ($threads as $thread) {
        if (!is_object($thread) || empty($thread->ID)) {
            continue;
        }

        $items[] = [
            '@type'   => 'ListItem',
            'position'=> $position,
            'url'     => get_permalink($thread->ID),
            'name'    => get_the_title($thread->ID),
        ];
        $position++;
    }

    if (empty($items)) {
        return;
    }

    $structured_data = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => __('Popular Discussions', 'gta6mods'),
        'itemListElement' => $items,
    ];

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Optimizes avatar markup across the forum surfaces.
 */
function gta6m_optimize_avatar(string $avatar, $id_or_email, int $size, string $default, string $alt): string
{
    if ('' === $avatar) {
        return $avatar;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        mb_convert_encoding($avatar, 'HTML-ENTITIES', 'UTF-8'),
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $img = $dom->getElementsByTagName('img')->item(0);
    if (!$img) {
        return $avatar;
    }

    $user      = null;
    $cache_key = null;

    if (is_numeric($id_or_email)) {
        $user_id   = (int) $id_or_email;
        $cache_key = 'id_' . $user_id;
    } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
        $user_id   = (int) $id_or_email->user_id;
        $cache_key = 'id_' . $user_id;
    } elseif (is_string($id_or_email)) {
        $email     = sanitize_email($id_or_email);
        $cache_key = $email ? 'email_' . $email : null;
    }

    static $user_cache = [];

    if (null !== $cache_key && isset($user_cache[$cache_key])) {
        $user = $user_cache[$cache_key];
    } elseif (isset($user_id)) {
        $user = get_user_by('id', $user_id);
    } elseif (!empty($email)) {
        $user = get_user_by('email', $email);
    }

    if (null !== $cache_key) {
        $user_cache[$cache_key] = $user;
    }

    $display_name = $user instanceof WP_User ? $user->display_name : $alt;
    if ('' === trim((string) $display_name)) {
        $display_name = __('User', 'gta6mods');
    }

    $display_name_attr = sanitize_text_field($display_name);

    $img->setAttribute('width', (string) $size);
    $img->setAttribute('height', (string) $size);

    if (!$img->hasAttribute('alt') || '' === trim($img->getAttribute('alt'))) {
        $optimized_alt = sprintf(
            /* translators: %s: user display name */
            __('%s - Profile Picture', 'gta6mods'),
            $display_name_attr
        );
        $img->setAttribute('alt', $optimized_alt);
    }

    if (!$img->hasAttribute('title')) {
        $title = sprintf(
            /* translators: %s: user display name */
            __('View %s\'s profile', 'gta6mods'),
            $display_name_attr
        );
        $img->setAttribute('title', $title);
    }

    static $avatar_count = 0;
    $avatar_count++;

    if (is_singular('forum_thread') && 1 === $avatar_count) {
        $img->setAttribute('loading', 'eager');
        $img->setAttribute('fetchpriority', 'high');
    } else {
        $img->setAttribute('loading', 'lazy');
    }

    $img->setAttribute('decoding', 'async');

    $aria_label = sprintf(
        /* translators: %s: user display name */
        __('Avatar of %s', 'gta6mods'),
        $display_name_attr
    );
    $img->setAttribute('aria-label', $aria_label);

    $existing_class = $img->getAttribute('class');
    $new_class      = trim($existing_class . ' gta6m-avatar-optimized');
    $img->setAttribute('class', $new_class);

    return (string) $dom->saveHTML($img);
}
add_filter('get_avatar', 'gta6m_optimize_avatar', 10, 5);

/**
 * Retrieves an optimised avatar with SEO-friendly wrapper.
 */
function gta6m_get_optimized_thread_avatar(int $author_id, int $thread_id, int $size = 48): string
{
    $cache_key   = sprintf('gta6m_avatar_%d_%d_%d', $author_id, $thread_id, $size);
    $cache_group = 'gta6m_avatars';

    $cached = wp_cache_get($cache_key, $cache_group);
    if (false !== $cached) {
        return $cached;
    }

    $user = get_user_by('id', $author_id);
    if (!$user instanceof WP_User) {
        return '';
    }

    $avatar = get_avatar($author_id, $size);
    if ('' === $avatar) {
        return '';
    }

    $profile_url = get_author_posts_url($author_id);
    $title       = sprintf(
        /* translators: %s: author display name */
        __('View all posts by %s', 'gta6mods'),
        sanitize_text_field($user->display_name)
    );
    $aria_label  = sprintf(
        /* translators: %s: author display name */
        __('Profile of %s', 'gta6mods'),
        sanitize_text_field($user->display_name)
    );

    $output = sprintf(
        '<a href="%s" class="gta6m-avatar-link" title="%s" aria-label="%s" rel="author">%s</a>',
        esc_url($profile_url),
        esc_attr($title),
        esc_attr($aria_label),
        $avatar
    );

    wp_cache_set($cache_key, $output, $cache_group, HOUR_IN_SECONDS * 12);
    gta6m_track_cache_key($cache_key, $cache_group);

    return $output;
}
