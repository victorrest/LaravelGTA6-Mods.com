<?php
/**
 * Comment related functions for the GTA6 Mods theme.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns the list of post types that support the enhanced comment experience.
 *
 * @return array<string>
 */
function gta6mods_get_commentable_post_types(): array {
    $default_types = ['post', 'forum_thread'];
    $types         = apply_filters('gta6mods_commentable_post_types', $default_types);

    if (!is_array($types)) {
        return $default_types;
    }

    $sanitized = array_map(static function ($type) {
        return is_string($type) ? sanitize_key($type) : '';
    }, $types);

    $sanitized = array_filter($sanitized, static function ($type) {
        return '' !== $type;
    });

    if (empty($sanitized)) {
        return $default_types;
    }

    return array_values(array_unique($sanitized));
}

function gta6mods_get_allowed_comment_html(): array
{
    static $allowed = null;

    if (null === $allowed) {
        $allowed = [
            'a'          => [
                'href'   => true,
                'target' => true,
                'rel'    => true,
            ],
            'strong'     => [],
            'em'         => [],
            'code'       => [],
            'ul'         => [],
            'ol'         => [],
            'li'         => [],
            'blockquote' => [],
            'p'          => [],
            'br'         => [],
            'img'        => [
                'src'      => true,
                'alt'      => true,
                'title'    => true,
                'width'    => true,
                'height'   => true,
                'loading'  => true,
                'decoding' => true,
            ],
        ];
    }

    return $allowed;
}

/**
 * Determines the maximum allowed comment depth for a post.
 */
function gta6mods_get_comment_max_depth(int $post_id = 0): int {
    if ($post_id <= 0) {
        $post_id = get_the_ID() ?: 0;
    }

    $default_max_depth = (int) get_option('thread_comments_depth');
    if ($default_max_depth <= 0) {
        $default_max_depth = 3;
    }

    $max_depth = $default_max_depth;

    if ($post_id > 0) {
        $post_type = get_post_type($post_id);
        if ('forum_thread' === $post_type) {
            $max_depth = 2;
        }
    }

    $max_depth = (int) apply_filters('gta6mods_comment_max_depth', $max_depth, $post_id);

    return max(1, $max_depth);
}

/**
 * Calculates the depth of a comment by walking up the parent chain.
 */
function gta6mods_calculate_comment_depth(WP_Comment $comment): int {
    $depth      = 1;
    $parent_id  = (int) $comment->comment_parent;
    $visited    = [];

    while ($parent_id > 0) {
        if (isset($visited[$parent_id])) {
            break;
        }
        $visited[$parent_id] = true;

        $parent = get_comment($parent_id);
        if (!$parent instanceof WP_Comment) {
            break;
        }

        $depth++;
        $parent_id = (int) $parent->comment_parent;
    }

    return $depth;
}

function gta6mods_normalize_comment_content(string $content, string $context = ''): string
{
    $content = trim(str_replace(["\r\n", "\r"], "\n", $content));

    if ('' === $content) {
        return '';
    }

    $content = preg_replace("/\n{3,}/", "\n\n", $content);

    if ('forum_thread' === $context) {
        $content = preg_replace('#<br\s*/?>#i', '<br />', $content);

        $content = wp_kses($content, gta6mods_get_allowed_comment_html());

        if (!preg_match('/<(?:p|br|ul|ol|li|blockquote|img|code|strong|em|a)\b/i', $content)) {
            $content = wpautop($content);
        }

        return force_balance_tags($content);
    }

    $content = wp_kses_post($content);

    return force_balance_tags(wpautop($content));
}

function gta6mods_comment_append_attribute_to_tag(string $tag, string $attribute, string $value): string
{
    $trimmed = preg_replace('/\s*>$/', '', $tag);
    if (!is_string($trimmed) || '' === $trimmed) {
        $trimmed = $tag;
    }

    return $trimmed . ' ' . $attribute . '="' . esc_attr($value) . '">';
}

function gta6mods_enforce_comment_link_attributes(string $content): string
{
    if (false === stripos($content, '<a ')) {
        return $content;
    }

    $required = ['nofollow', 'noopener', 'ugc'];

    return preg_replace_callback('/<a\s+[^>]*href=("|\')[^"\']+("|\')[^>]*>/i', static function (array $matches) use ($required) {
        $tag = $matches[0];

        if (stripos($tag, 'rel=') !== false) {
            $tag = preg_replace_callback('/rel=("|\')(.*?)("|\')/i', static function ($relMatch) use ($required) {
                $values = preg_split('/\s+/', strtolower($relMatch[2]));
                $values = array_filter(array_map('trim', is_array($values) ? $values : []));
                $values = array_unique(array_merge($values, $required));

                return 'rel="' . esc_attr(implode(' ', $values)) . '"';
            }, $tag);
        } else {
            $tag = gta6mods_comment_append_attribute_to_tag($tag, 'rel', implode(' ', $required));
        }

        if (stripos($tag, 'target=') === false) {
            $tag = gta6mods_comment_append_attribute_to_tag($tag, 'target', '_blank');
        }

        return $tag;
    }, $content);
}

function gta6mods_preprocess_comment_rich_text(array $commentdata): array
{
    if (empty($commentdata['comment_content'])) {
        return $commentdata;
    }

    $postId = isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0;
    if ($postId <= 0) {
        return $commentdata;
    }

    $postType = get_post_type($postId);
    if (!in_array($postType, gta6mods_get_commentable_post_types(), true)) {
        return $commentdata;
    }

    $commentdata['comment_content'] = gta6mods_normalize_comment_content((string) $commentdata['comment_content'], $postType);

    if ('forum_thread' === $postType) {
        gta6mods_prepare_forum_comment_kses();
    }

    return $commentdata;
}
add_filter('preprocess_comment', 'gta6mods_preprocess_comment_rich_text', 15);

function gta6mods_prepare_forum_comment_kses(): void
{
    global $gta6mods_forum_comment_filter_state;

    if (!is_array($gta6mods_forum_comment_filter_state)) {
        $gta6mods_forum_comment_filter_state = [
            'initialized' => false,
            'restored'    => false,
        ];
    }

    if ($gta6mods_forum_comment_filter_state['initialized'] && !$gta6mods_forum_comment_filter_state['restored']) {
        return;
    }

    $gta6mods_forum_comment_filter_state['initialized'] = true;
    $gta6mods_forum_comment_filter_state['restored']    = false;

    add_filter('pre_comment_content', 'gta6mods_forum_comment_kses', 1);
    add_action('comment_post', 'gta6mods_restore_default_comment_kses', 0);
    add_action('wp_insert_comment', 'gta6mods_restore_default_comment_kses', 0);
    add_action('shutdown', 'gta6mods_restore_default_comment_kses');
}

function gta6mods_forum_comment_kses($content)
{
    remove_filter('pre_comment_content', 'wp_filter_post_kses');
    remove_filter('pre_comment_content', 'wp_filter_kses');

    return wp_kses($content, gta6mods_get_allowed_comment_html());
}

function gta6mods_restore_default_comment_kses(): void
{
    global $gta6mods_forum_comment_filter_state;

    if (!is_array($gta6mods_forum_comment_filter_state)) {
        $gta6mods_forum_comment_filter_state = [
            'initialized' => false,
            'restored'    => false,
        ];
    }

    if ($gta6mods_forum_comment_filter_state['restored']) {
        return;
    }

    $gta6mods_forum_comment_filter_state['restored']    = true;
    $gta6mods_forum_comment_filter_state['initialized'] = false;

    remove_filter('pre_comment_content', 'gta6mods_forum_comment_kses', 1);

    if (function_exists('wp_filter_post_kses') && !has_filter('pre_comment_content', 'wp_filter_post_kses')) {
        add_filter('pre_comment_content', 'wp_filter_post_kses');
    }

    if (function_exists('wp_filter_kses') && !has_filter('pre_comment_content', 'wp_filter_kses')) {
        add_filter('pre_comment_content', 'wp_filter_kses');
    }
}

function gta6mods_filter_comment_text($comment_text, $comment, $args) {
    if (!$comment instanceof WP_Comment) {
        return $comment_text;
    }

    $postId = (int) $comment->comment_post_ID;
    if ($postId <= 0) {
        return $comment_text;
    }

    $postType = get_post_type($postId);
    if (!in_array($postType, gta6mods_get_commentable_post_types(), true)) {
        return $comment_text;
    }

    return gta6mods_enforce_comment_link_attributes($comment_text);
}
add_filter('comment_text', 'gta6mods_filter_comment_text', 20, 3);

/**
 * Builds the HTML markup for comment attachments (images or GIFs).
 *
 * @param WP_Comment $comment Comment object.
 *
 * @return string
 */
function gta6mods_get_comment_attachments_markup($comment) {
    if (!($comment instanceof WP_Comment)) {
        return '';
    }

    $comment_id = (int) $comment->comment_ID;
    if ($comment_id <= 0) {
        return '';
    }

    $image_id = (int) get_comment_meta($comment_id, 'gta6_comment_image_id', true);
    $gif_url  = trim((string) get_comment_meta($comment_id, 'gta6_comment_gif_url', true));

    if ($image_id <= 0 && '' === $gif_url) {
        return '';
    }

    $comment_text_plain = wp_strip_all_tags(get_comment_text($comment));
    $image_alt_text     = esc_attr(wp_trim_words($comment_text_plain, 15, '...'));

    if ('' === trim($image_alt_text)) {
        $image_alt_text = sprintf(
            /* translators: %s: comment author display name. */
            esc_attr__('Image in a comment by %s', 'gta6-mods'),
            esc_attr(get_comment_author($comment))
        );
    }

    $attachments_markup = '';

    if ($image_id > 0) {
        $thumb_data = wp_get_attachment_image_src($image_id, 'thumbnail');
        $full_data  = wp_get_attachment_image_src($image_id, 'full');

        if ($thumb_data && $full_data) {
            $attachments_markup .= '<div class="comment-media pswp-gallery">';
            $attachments_markup .= '<a href="' . esc_url($full_data[0]) . '"'
                . ' data-pswp-width="' . esc_attr($full_data[1]) . '"'
                . ' data-pswp-height="' . esc_attr($full_data[2]) . '"'
                . ' target="_blank"'
                . ' class="comment-lightbox-item">';
            $attachments_markup .= '<img src="' . esc_url($thumb_data[0]) . '"'
                . ' alt="' . $image_alt_text . '"'
                . ' title="' . $image_alt_text . '"'
                . ' width="' . esc_attr($thumb_data[1]) . '"'
                . ' height="' . esc_attr($thumb_data[2]) . '"'
                . ' class="comment-attachment-image">';
            $attachments_markup .= '</a></div>';
        }
    }

    if ('' !== $gif_url) {
        $attachments_markup .= '<div class="comment-media pswp-gallery">';
        $attachments_markup .= '<a href="' . esc_url($gif_url) . '" target="_blank" class="comment-lightbox-item" data-pswp-width="0" data-pswp-height="0">';
        $attachments_markup .= '<img src="' . esc_url($gif_url) . '"'
            . ' alt="' . $image_alt_text . '"'
            . ' title="' . $image_alt_text . '"'
            . ' class="comment-attachment-image">';
        $attachments_markup .= '</a></div>';
    }

    if ('' === $attachments_markup) {
        return '';
    }

    return '<div class="comment-attachments mt-2 mb-3 space-y-3">' . $attachments_markup . '</div>';
}

if (!function_exists('gta6mods_is_comment_retracted')) {
    /**
     * Determines whether a comment has been retracted by its author.
     *
     * @param WP_Comment|int $comment Comment object or ID.
     *
     * @return bool
     */
    function gta6mods_is_comment_retracted($comment) {
        if (is_numeric($comment)) {
            $comment = get_comment((int) $comment);
        }

        if (!$comment instanceof WP_Comment) {
            return false;
        }

        $flag = get_comment_meta($comment->comment_ID, 'gta6mods_retracted', true);

        return !empty($flag);
    }
}

if (!function_exists('gta6mods_get_retracted_comment_text')) {
    /**
     * Returns the placeholder text for a retracted comment.
     *
     * @return string
     */
    function gta6mods_get_retracted_comment_text() {
        $text = esc_html__('The user deleted their comment.', 'gta6-mods');

        /**
         * Filters the placeholder text displayed for retracted comments.
         *
         * @param string $text Placeholder text.
         */
        return apply_filters('gta6mods_retracted_comment_text', $text);
    }
}

if (!function_exists('gta6mods_get_pinned_comment_id')) {
    /**
     * Retrieves the pinned comment ID for a post.
     *
     * @param int $post_id Post identifier.
     *
     * @return int
     */
    function gta6mods_get_pinned_comment_id($post_id) {
        $post_id = (int) $post_id;

        if ($post_id <= 0) {
            return 0;
        }

        $comment_id = (int) get_post_meta($post_id, 'gta6mods_pinned_comment', true);

        return $comment_id > 0 ? $comment_id : 0;
    }
}

if (!function_exists('gta6mods_comment_can_be_pinned')) {
    /**
     * Determines if a comment can be pinned.
     *
     * @param WP_Comment $comment Comment instance.
     *
     * @return bool
     */
    function gta6mods_comment_can_be_pinned($comment) {
        if (!$comment instanceof WP_Comment) {
            return false;
        }

        // Only allow pinning of top-level comments that have not been retracted.
        if ((int) $comment->comment_parent !== 0 || gta6mods_is_comment_retracted($comment)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('gta6mods_normalize_comment_link')) {
    /**
     * Ensures comment permalinks consistently use the /comments/ endpoint without pagination remnants.
     *
     * @param string $link Raw comment link.
     *
     * @return string Normalised comment link.
     */
    function gta6mods_normalize_comment_link($link) {
        if (!is_string($link) || '' === $link) {
            return '';
        }

        $parsed = wp_parse_url($link);

        if (!is_array($parsed)) {
            return $link;
        }

        $path = isset($parsed['path']) ? $parsed['path'] : '';
        if ('' === $path) {
            return $link;
        }

        $path = preg_replace('#/comments/(?:comments/)+#', '/comments/', $path);
        if (null === $path) {
            $path = $parsed['path'];
        }

        $path = preg_replace('#/comments//+#', '/comments/', $path);
        if (null === $path) {
            $path = $parsed['path'];
        }

        $allow_pagination_segments = apply_filters(
            'gta6mods_allow_comment_pagination_segments',
            false,
            $link,
            $parsed
        );

        if (!$allow_pagination_segments) {
            $path = preg_replace('#/comments/comment-page-\d+/#', '/comments/', $path);
            if (null === $path) {
                $path = $parsed['path'];
            }

            $path = preg_replace('#/comments/comment-page-\d+$#', '/comments/', $path);
            if (null === $path) {
                $path = $parsed['path'];
            }
        }

        $authority = '';

        if (!empty($parsed['host'])) {
            $authority = $parsed['host'];

            if (!empty($parsed['user'])) {
                $authority = $parsed['user']
                    . (!empty($parsed['pass']) ? ':' . $parsed['pass'] : '')
                    . '@' . $authority;
            }

            if (!empty($parsed['port'])) {
                $authority .= ':' . $parsed['port'];
            }
        }

        $normalized = '';

        if (!empty($parsed['scheme'])) {
            $normalized .= $parsed['scheme'] . '://';
        } elseif ('' !== $authority) {
            $normalized .= '//';
        }

        $normalized .= $authority . $path;

        $query_args = [];
        if (!empty($parsed['query'])) {
            wp_parse_str($parsed['query'], $query_args);
        }

        if (!empty($query_args)) {
            $normalized = add_query_arg($query_args, $normalized);
        } elseif (isset($parsed['query']) && '' !== $parsed['query']) {
            $normalized .= '?' . $parsed['query'];
        }

        if (isset($parsed['fragment']) && '' !== $parsed['fragment']) {
            $normalized .= '#' . $parsed['fragment'];
        }

        return $normalized;
    }
}

if (!function_exists('gta6mods_get_comment_permalink')) {
    /**
     * Builds a permalink for a comment that respects custom comment slugs.
     *
     * @param WP_Comment|int $comment Comment instance or ID.
     *
     * @return string
     */
    function gta6mods_get_comment_permalink($comment) {
        if (is_numeric($comment)) {
            $comment = get_comment((int) $comment);
        }

        if (!$comment instanceof WP_Comment) {
            return '';
        }

        $post         = get_post($comment->comment_post_ID);
        $original_link = get_comment_link($comment);

        if (!$post instanceof WP_Post) {
            return $original_link ? gta6mods_normalize_comment_link((string) $original_link) : '';
        }

        $permalink = get_permalink($post);
        if (!$permalink) {
            return $original_link ? gta6mods_normalize_comment_link((string) $original_link) : '';
        }

        $permalink     = trailingslashit($permalink);
        $is_link_format = (function_exists('get_post_format') && 'link' === get_post_format($post));

        if (!$is_link_format) {
            $permalink .= 'comments/';
        }

        $comment_page = 0;
        if (function_exists('get_page_of_comment')) {
            $comment_order        = strtolower((string) get_option('comment_order', 'asc'));
            $page_of_comment_args = [
                'type'      => 'comment',
                'per_page'  => (int) get_option('comments_per_page'),
                'max_depth' => gta6mods_get_comment_max_depth((int) $post->ID),
            ];

            if ('desc' === $comment_order) {
                $page_of_comment_args['reverse_top_level'] = true;
            }

            $comment_page = get_page_of_comment($comment, $page_of_comment_args);
        }

        $comment_page = (int) $comment_page;
        $include_pagination_segment = apply_filters(
            'gta6mods_comment_permalink_include_pagination',
            false,
            $comment,
            $comment_page,
            $post
        );

        if ($include_pagination_segment && $comment_page > 1) {
            $permalink .= 'comment-page-' . $comment_page . '/';
        }

        $query_args = [];
        if ($original_link) {
            $original_parts = wp_parse_url($original_link);
            if (!empty($original_parts['query'])) {
                wp_parse_str($original_parts['query'], $query_args);
            }
        }

        if (!empty($query_args)) {
            $permalink = add_query_arg($query_args, $permalink);
        }

        $permalink .= '#comment-' . (int) $comment->comment_ID;

        return gta6mods_normalize_comment_link($permalink);
    }
}

if (!function_exists('gta6mods_user_can_retract_comment')) {
    /**
     * Determines whether a user may retract a specific comment.
     *
     * @param WP_Comment|int $comment Comment object or ID.
     * @param int            $user_id Optional user ID. Defaults to current user.
     *
     * @return bool
     */
    function gta6mods_user_can_retract_comment($comment, $user_id = 0) {
        if (is_numeric($comment)) {
            $comment = get_comment((int) $comment);
        }

        if (!$comment instanceof WP_Comment) {
            return false;
        }

        $user_id = (int) ($user_id ?: get_current_user_id());

        if ($user_id <= 0) {
            return false;
        }

        if ((int) $comment->user_id === $user_id) {
            return true;
        }

        if (user_can($user_id, 'moderate_comments')) {
            return true;
        }

        $user = get_userdata($user_id);
        if ($user instanceof WP_User && in_array('moderator', (array) $user->roles, true)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('gta6mods_user_can_pin_comment')) {
    /**
     * Determines whether a user has permission to pin a comment.
     *
     * @param WP_Comment|int $comment Comment object or ID.
     * @param int            $user_id Optional user ID. Defaults to current user.
     *
     * @return bool
     */
    function gta6mods_user_can_pin_comment($comment, $user_id = 0) {
        if (is_numeric($comment)) {
            $comment = get_comment((int) $comment);
        }

        if (!$comment instanceof WP_Comment) {
            return false;
        }

        $user_id = (int) ($user_id ?: get_current_user_id());

        if ($user_id <= 0) {
            return false;
        }

        if (user_can($user_id, 'moderate_comments')) {
            return true;
        }

        $user = get_userdata($user_id);

        if ($user instanceof WP_User && in_array('moderator', (array) $user->roles, true)) {
            return true;
        }

        $post = get_post($comment->comment_post_ID);

        if ($post instanceof WP_Post && (int) $post->post_author === $user_id) {
            return true;
        }

        return false;
    }
}

if (!function_exists('gta6mods_mark_comment_retracted')) {
    /**
     * Marks a comment as retracted and handles related bookkeeping.
     *
     * @param int $comment_id Comment ID.
     * @param int $user_id    Acting user ID.
     *
     * @return array<string, bool>|WP_Error
     */
    function gta6mods_mark_comment_retracted($comment_id, $user_id = 0) {
        $comment_id = (int) $comment_id;
        $user_id    = (int) $user_id;

        $comment = get_comment($comment_id);
        if (!$comment instanceof WP_Comment) {
            return new WP_Error('invalid_comment', __('Invalid comment.', 'gta6-mods'), ['status' => 404]);
        }

        if (gta6mods_is_comment_retracted($comment)) {
            return ['pinned_removed' => false];
        }

        update_comment_meta($comment_id, 'gta6mods_retracted', 1);
        update_comment_meta($comment_id, 'gta6mods_retracted_at', current_time('mysql'));
        if ($user_id > 0) {
            update_comment_meta($comment_id, 'gta6mods_retracted_by', $user_id);
        }

        $post_id        = (int) $comment->comment_post_ID;
        $pinned_removed = false;

        if ($post_id > 0) {
            $pinned_comment_id = gta6mods_get_pinned_comment_id($post_id);
            if ($pinned_comment_id === $comment_id) {
                delete_post_meta($post_id, 'gta6mods_pinned_comment');
                $pinned_removed = true;
            }
        }

        clean_comment_cache($comment_id);

        if ($post_id > 0) {
            clean_post_cache($post_id);
        }

        /**
         * Fires when a comment is retracted via the frontend tools.
         *
         * @param int $comment_id Comment ID.
         * @param int $user_id    Acting user ID.
         */
        do_action('gta6mods_comment_retracted', $comment_id, $user_id);

        return ['pinned_removed' => $pinned_removed];
    }
}

if (!function_exists('gta6mods_restore_retracted_comment')) {
    /**
     * Restores a previously retracted comment.
     *
     * @param int $comment_id Comment ID.
     * @param int $user_id    Optional acting user ID.
     *
     * @return bool|WP_Error
     */
    function gta6mods_restore_retracted_comment($comment_id, $user_id = 0) {
        $comment_id = (int) $comment_id;
        $user_id    = (int) $user_id;

        $comment = get_comment($comment_id);
        if (!$comment instanceof WP_Comment) {
            return new WP_Error('invalid_comment', __('Invalid comment.', 'gta6-mods'), ['status' => 404]);
        }

        if (!gta6mods_is_comment_retracted($comment)) {
            return true;
        }

        delete_comment_meta($comment_id, 'gta6mods_retracted');
        delete_comment_meta($comment_id, 'gta6mods_retracted_at');
        delete_comment_meta($comment_id, 'gta6mods_retracted_by');

        clean_comment_cache($comment_id);

        $post_id = (int) $comment->comment_post_ID;
        if ($post_id > 0) {
            clean_post_cache($post_id);
        }

        /**
         * Fires when a comment is restored from retracted state.
         *
         * @param int $comment_id Comment ID.
         * @param int $user_id    Acting user ID.
         */
        do_action('gta6mods_comment_restored', $comment_id, $user_id);

        return true;
    }
}

if (!function_exists('gta6mods_set_post_pinned_comment')) {
    /**
     * Updates the pinned comment for a post.
     *
     * @param int $post_id    Post ID.
     * @param int $comment_id Comment ID. Provide 0 to remove the pin.
     *
     * @return bool
     */
    function gta6mods_set_post_pinned_comment($post_id, $comment_id) {
        $post_id    = (int) $post_id;
        $comment_id = (int) $comment_id;

        if ($post_id <= 0) {
            return false;
        }

        if ($comment_id > 0) {
            update_post_meta($post_id, 'gta6mods_pinned_comment', $comment_id);
        } else {
            delete_post_meta($post_id, 'gta6mods_pinned_comment');
        }

        clean_post_cache($post_id);

        /**
         * Fires when the pinned comment for a post changes.
         *
         * @param int $post_id    Post ID.
         * @param int $comment_id Comment ID (0 if cleared).
         */
        do_action('gta6mods_post_pinned_comment_updated', $post_id, $comment_id);

        return true;
    }
}

if (!function_exists('gta6mods_prioritize_pinned_comment')) {
    /**
     * Moves the pinned comment to the front of a comments array.
     *
     * @param array<int, mixed> $comments          List of comment objects or IDs.
     * @param int               $pinned_comment_id Pinned comment ID.
     *
     * @return array<int, mixed>
     */
    function gta6mods_prioritize_pinned_comment($comments, $pinned_comment_id) {
        if (!is_array($comments) || empty($comments)) {
            return $comments;
        }

        $pinned_comment_id = (int) $pinned_comment_id;
        if ($pinned_comment_id <= 0) {
            return $comments;
        }

        $target_index = null;

        foreach ($comments as $index => $comment) {
            $candidate_id = $comment instanceof WP_Comment
                ? (int) $comment->comment_ID
                : (int) $comment;

            if ($candidate_id === $pinned_comment_id) {
                $target_index = $index;
                break;
            }
        }

        if (null === $target_index) {
            return $comments;
        }

        $pinned = $comments[$target_index];
        unset($comments[$target_index]);
        array_unshift($comments, $pinned);

        return array_values($comments);
    }
}

/**
 * Builds the comment form markup for a given post.
 *
 * @param int $post_id Post ID.
 *
 * @return string
 */
function gta6mods_get_comment_editor_toolbar(): string {
    $buttons = [
        ['action' => 'bold',  'icon' => 'fas fa-bold',      'label' => __('Bold', 'gta6-mods')],
        ['action' => 'italic','icon' => 'fas fa-italic',    'label' => __('Italic', 'gta6-mods')],
        ['action' => 'link',  'icon' => 'fas fa-link',      'label' => __('Link', 'gta6-mods')],
        ['action' => 'code',  'icon' => 'fas fa-code',      'label' => __('Code', 'gta6-mods')],
        ['action' => 'img',   'icon' => 'fas fa-image',     'label' => __('Insert image', 'gta6-mods')],
        ['action' => 'ol',    'icon' => 'fas fa-list-ol',   'label' => __('Ordered list', 'gta6-mods')],
        ['action' => 'ul',    'icon' => 'fas fa-list-ul',   'label' => __('Unordered list', 'gta6-mods')],
    ];

    ob_start();
    ?>
    <div class="comment-editor-toolbar" data-comment-toolbar>
        <?php foreach ($buttons as $button) : ?>
            <button type="button" class="comment-editor-button" data-comment-action="<?php echo esc_attr($button['action']); ?>" aria-label="<?php echo esc_attr($button['label']); ?>">
                <i class="<?php echo esc_attr($button['icon']); ?>" aria-hidden="true"></i>
            </button>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function gta6mods_get_comment_form_markup($post_id) {
    $post_id = absint($post_id);

    if ($post_id <= 0 || !comments_open($post_id)) {
        return '';
    }

    $commenter = wp_get_current_commenter();

    $is_forum_context = false;

    if (function_exists('gta6_forum_is_forum_context') && gta6_forum_is_forum_context()) {
        $is_forum_context = true;
    } elseif ('forum_thread' === get_post_type($post_id)) {
        $is_forum_context = true;
    }

    $toolbar_markup = $is_forum_context ? gta6mods_get_comment_editor_toolbar() : '';

    $comment_field  = '<div class="comment-box-container border border-gray-300 rounded-lg focus-within:ring-2 focus-within:ring-pink-500 focus-within:border-transparent overflow-hidden transition-all"' . ($is_forum_context ? ' data-editor-mode="plain"' : '') . '>';
    $comment_field .= '<div class="comment-rich-editor">';
    if ('' !== $toolbar_markup) {
        $comment_field .= $toolbar_markup;
    }
    $comment_field .= '<div class="comment-box-textarea w-full p-3 bg-transparent is-empty" contenteditable="true" data-placeholder="' . esc_attr__('Write a comment...', 'gta6-mods') . '"' . ($is_forum_context ? ' data-editor-mode="plain"' : '') . '></div></div>';
    $comment_field .= '<textarea id="comment" name="comment" class="comment-form-hidden-fields" required></textarea>';
    $comment_field .= '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('gta6_comments_nonce')) . '">';
    $comment_field .= '<input type="hidden" name="comment_image_id" value="">';
    $comment_field .= '<input type="hidden" name="comment_gif_url" value="">';
    $comment_field .= '<input type="hidden" name="comment_mentioned_users" value="">';
    $comment_field .= '<input type="hidden" name="gta6_comment_meta_nonce" value="' . esc_attr(wp_create_nonce('gta6_comment_meta')) . '">';
    $comment_field .= '<input type="file" name="comment_image_file" accept="image/*" class="comment-image-input comment-form-hidden-fields">';
    $comment_field .= '<div class="image-preview-container hidden px-3 mt-2">';
    $comment_field .= '<div class="relative inline-block">';
    $comment_field .= '<img src="" alt="" class="image-preview max-h-24 rounded">';
    $comment_field .= '<button type="button" class="remove-image-btn absolute -top-2 -right-2 bg-gray-800 hover:bg-black text-white rounded-full h-6 w-6 flex items-center justify-center text-sm font-bold transition-colors">&times;</button>';
    $comment_field .= '</div></div>';
    $comment_field .= '<div class="gif-preview-container hidden px-3 mt-2">';
    $comment_field .= '<div class="relative inline-block">';
    $comment_field .= '<img src="" alt="" class="gif-preview max-h-24 rounded">';
    $comment_field .= '<button type="button" class="remove-gif-btn absolute -top-2 -right-2 bg-gray-800 hover:bg-black text-white rounded-full h-6 w-6 flex items-center justify-center text-sm font-bold transition-colors">&times;</button>';
    $comment_field .= '</div></div>';
    $comment_field .= '<div class="comment-actions-bar hidden px-3 pt-2 pb-3' . ($is_forum_context ? ' bg-gray-50' : '') . ' flex justify-between items-center">';
    $comment_field .= '<div class="flex items-center text-gray-500">';
    if (!$is_forum_context) {
        $comment_field .= '<button type="button" class="upload-image-btn hover:text-gray-800" title="' . esc_attr__('Upload image', 'gta6-mods') . '"><i class="fas fa-image fa-fw"></i></button>';
        $comment_field .= '<button type="button" class="gif-btn ml-3 hover:text-gray-800 font-bold text-sm" title="' . esc_attr__('Insert GIF', 'gta6-mods') . '">GIF</button>';
        $comment_field .= '<button type="button" class="mention-btn ml-3 hover:text-gray-800" title="' . esc_attr__('Mention user', 'gta6-mods') . '"><i class="fas fa-at fa-fw"></i></button>';
    }
    $comment_field .= '</div>';
    $comment_field .= '<div class="flex items-center gap-x-2">';
    $comment_field .= '<button type="submit" class="bg-pink-500 hover:bg-pink-600 text-white font-semibold py-2 px-5 rounded-lg text-sm transition">' . esc_html__('Post', 'gta6-mods') . '</button>';
    $comment_field .= '</div>';
    $comment_field .= '</div></div>';

    $fields = [];

    if (!is_user_logged_in()) {
        $fields['author'] = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div class="space-y-1"><label for="author" class="block text-sm font-medium text-gray-700">' . esc_html__('Name', 'gta6-mods') . '</label><input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-500" required></div>';
        $fields['email']  = '<div class="space-y-1"><label for="email" class="block text-sm font-medium text-gray-700">' . esc_html__('Email', 'gta6-mods') . '</label><input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-500" required></div></div>';
    }

    $fields['cookies'] = '';

    ob_start();

    comment_form(
        [
            'class_form'           => 'space-y-4',
            'title_reply'          => '',
            'comment_field'        => $comment_field,
            'fields'               => $fields,
            'submit_button'        => '',
            'submit_field'         => '%2$s',
            'label_submit'         => esc_html__('Post', 'gta6-mods'),
            'logged_in_as'         => '',
            'comment_notes_before' => '',
            'comment_notes_after'  => '',
        ],
        $post_id
    );

    $form_markup = ob_get_clean();

    if ('' === trim($form_markup)) {
        return '';
    }

    return '<div class="gta6-comment-form">' . $form_markup . '</div>';
}

/**
 * Enqueue assets for the comment system.
 */
function gta6_mods_enqueue_comment_assets() {
    if (!is_singular() || !comments_open()) {
        return;
    }

    wp_enqueue_style(
        'photoswipe',
        'https://unpkg.com/photoswipe@5/dist/photoswipe.css',
        [],
        '5.4.4'
    );

    wp_enqueue_script(
        'photoswipe',
        'https://unpkg.com/photoswipe@5/dist/umd/photoswipe.umd.min.js',
        [],
        '5.4.4',
        true
    );

    wp_enqueue_script(
        'photoswipe-lightbox',
        'https://unpkg.com/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js',
        ['photoswipe'],
        '5.4.4',
        true
    );

    $comments_css = get_template_directory() . '/assets/css/comments.css';
    if (file_exists($comments_css)) {
        wp_enqueue_style('gta6-mods-comments', get_template_directory_uri() . '/assets/css/comments.css', [], filemtime($comments_css));
    }

    $comments_js = get_template_directory() . '/assets/js/comments.js';
    if (file_exists($comments_js)) {
        $script_dependencies = ['jquery', 'wp-i18n', 'photoswipe-lightbox'];
        if (wp_script_is('gta6-mods-utils', 'registered') || wp_script_is('gta6-mods-utils', 'enqueued')) {
            $script_dependencies[] = 'gta6-mods-utils';
        }

        wp_enqueue_script('gta6-mods-comments', get_template_directory_uri() . '/assets/js/comments.js', $script_dependencies, filemtime($comments_js), true);

        $current_user = wp_get_current_user();
        $post_id      = get_queried_object_id();
        $form_markup  = gta6mods_get_comment_form_markup($post_id);
        wp_localize_script(
            'gta6-mods-comments',
            'GTA6Comments',
            [
                'restEndpoints' => [
                    'comments'   => rest_url('gta6mods/v1/comments/' . $post_id),
                    'mentions'   => rest_url('gta6mods/v1/comments/mentions'),
                    'commentLike'=> trailingslashit(rest_url('gta6mods/v1/comments')),
                    'commentBase'=> trailingslashit(rest_url('gta6mods/v1/comments')),
                ],
                'restNonce'    => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
                'nonce'        => wp_create_nonce('gta6_comments_nonce'),
                'giphy_api_key' => apply_filters('gta6_mods_giphy_api_key', 'FpkTiLsw3tLjFv533ZaylhdKME4Ugj1O'),
                'post_id'       => $post_id ? (int) $post_id : 0,
                'user'          => [
                    'logged_in'   => is_user_logged_in(),
                    'id'          => is_user_logged_in() ? (int) $current_user->ID : 0,
                    'display_name'=> is_user_logged_in() ? $current_user->display_name : '',
                ],
                'formHtml'      => $form_markup,
                'strings'       => [
                    'mustLogIn'          => esc_html__('You must be logged in to perform this action.', 'gta6-mods'),
                    'loadingGifs'        => esc_html__('Loading GIFs...', 'gta6-mods'),
                    'noGifsFound'        => esc_html__('No GIFs found.', 'gta6-mods'),
                    'gifsError'          => esc_html__('Could not fetch GIFs.', 'gta6-mods'),
                'commentPosted'      => esc_html__('Your comment has been posted.', 'gta6-mods'),
                'commentPending'     => esc_html__('Your comment is awaiting moderation.', 'gta6-mods'),
                'loadMoreComments'   => esc_html__('Load more comments', 'gta6-mods'),
                'loadingComments'    => esc_html__('Loading…', 'gta6-mods'),
                'viewMoreReplies'    => esc_html__('Show %d more replies', 'gta6-mods'),
                'hideReplies'        => esc_html__('Hide replies', 'gta6-mods'),
                'errorGeneric'       => esc_html__('Something went wrong. Please try again.', 'gta6-mods'),
                'replyPlaceholder'   => esc_html__('Replying to @%s...', 'gta6-mods'),
                'imageUploadInvalid' => esc_html__('Please choose a valid image file.', 'gta6-mods'),
                'deleteConfirm'      => esc_html__('Are you sure you want to delete this comment?', 'gta6-mods'),
                'deleteConfirmTitle' => esc_html__('Delete comment?', 'gta6-mods'),
                'deleteConfirmConfirm' => esc_html__('Delete', 'gta6-mods'),
                'deleteConfirmCancel' => esc_html__('Cancel', 'gta6-mods'),
                'deleteSuccess'      => esc_html__('Your comment has been deleted.', 'gta6-mods'),
                'deleteError'        => esc_html__('We could not delete the comment. Please try again.', 'gta6-mods'),
                'copySuccess'        => esc_html__('Comment link copied to clipboard.', 'gta6-mods'),
                'copyError'          => esc_html__('We could not copy the comment link.', 'gta6-mods'),
                'pinSuccess'         => esc_html__('Comment pinned successfully.', 'gta6-mods'),
                'unpinSuccess'       => esc_html__('Comment unpinned.', 'gta6-mods'),
                'pinError'           => esc_html__('Failed to update the pinned comment.', 'gta6-mods'),
                'retractedText'      => gta6mods_get_retracted_comment_text(),
                'pinLabel'           => esc_html__('Pin comment', 'gta6-mods'),
                'unpinLabel'         => esc_html__('Unpin', 'gta6-mods'),
                'pinnedLabel'        => esc_html__('Pinned comment', 'gta6-mods'),
                'dismissToast'       => esc_html__('Dismiss', 'gta6-mods'),
            ],
        ]
    );

        wp_set_script_translations('gta6-mods-comments', 'gta6-mods');
    }
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_comment_assets', 20);

/**
 * Custom comment walker class.
 */
if (!class_exists('GTA6_Mods_Comment_Walker')) {
    class GTA6_Mods_Comment_Walker extends Walker_Comment {
        public $tree_type = 'comment';
        public $db_fields = [
            'parent' => 'comment_parent',
            'id'     => 'comment_ID',
        ];
        protected $pinned_comment_id = 0;
        protected $visible_replies_limit = 3;
        protected $max_depth_override = 0;
        protected $sibling_positions = [];
        protected $siblings_total = [];
        protected $child_totals = [];
        protected $parent_stack = [];
        protected $current_sibling_info = [
            'parent_id' => 0,
            'index'     => 0,
            'total'     => 0,
        ];

        public function __construct($args = []) {
            if (is_array($args)) {
                if (isset($args['pinned_comment_id'])) {
                    $this->pinned_comment_id = (int) $args['pinned_comment_id'];
                }

                if (isset($args['max_depth'])) {
                    $this->max_depth_override = max(0, (int) $args['max_depth']);
                }
            }
        }

        protected function get_max_depth_for_comment(WP_Comment $comment): int {
            if ($this->max_depth_override > 0) {
                return $this->max_depth_override;
            }

            return gta6mods_get_comment_max_depth((int) $comment->comment_post_ID);
        }

        public function set_pinned_comment_id($comment_id) {
            $this->pinned_comment_id = (int) $comment_id;
        }

        protected function is_comment_pinned($comment_id) {
            return $this->pinned_comment_id > 0 && (int) $comment_id === $this->pinned_comment_id;
        }

        protected function should_hide_comment(WP_Comment $comment) {
            $parent_id = (int) $comment->comment_parent;

            if ($parent_id <= 0) {
                return false;
            }

            if ($this->current_sibling_info['parent_id'] === $parent_id && $this->current_sibling_info['total'] > 0) {
                return $this->current_sibling_info['index'] > $this->visible_replies_limit;
            }

            $query_args = [
                'post_id' => (int) $comment->comment_post_ID,
                'parent'  => $parent_id,
                'status'  => 'approve',
                'orderby' => 'comment_date_gmt',
                'order'   => 'ASC',
                'number'  => 0,
            ];

            if ('0' === $comment->comment_approved && $comment->comment_author_email) {
                $query_args['include_unapproved'] = [$comment->comment_author_email];
            }

            $children = get_comments($query_args);

            $found = false;
            foreach ($children as $child) {
                if ($child instanceof WP_Comment && (int) $child->comment_ID === (int) $comment->comment_ID) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $children[] = $comment;
            }

            if (empty($children)) {
                return false;
            }

            usort(
                $children,
                static function ($a, $b) {
                    if (!$a instanceof WP_Comment || !$b instanceof WP_Comment) {
                        return 0;
                    }

                    $a_count = gta6mods_get_cached_comment_like_count($a);
                    $b_count = gta6mods_get_cached_comment_like_count($b);

                    if ($a_count === $b_count) {
                        return strcmp($b->comment_date_gmt, $a->comment_date_gmt);
                    }

                    return $b_count <=> $a_count;
                }
            );

            $this->child_totals[$parent_id] = count($children);

            $position = 1;
            foreach ($children as $child) {
                if ($child instanceof WP_Comment && (int) $child->comment_ID === (int) $comment->comment_ID) {
                    break;
                }
                $position++;
            }

            return $position > $this->visible_replies_limit;
        }

        public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output) {
            if (!$element) {
                return;
            }

            $id_field     = $this->db_fields['id'];
            $parent_field = $this->db_fields['parent'];

            $element_id = isset($element->{$id_field}) ? (int) $element->{$id_field} : 0;
            $parent_id  = isset($element->{$parent_field}) ? (int) $element->{$parent_field} : 0;

            if (!isset($this->siblings_total[$parent_id])) {
                $this->siblings_total[$parent_id] = isset($children_elements[$parent_id]) && is_array($children_elements[$parent_id])
                    ? count($children_elements[$parent_id])
                    : 0;
                $this->sibling_positions[$parent_id] = 0;
            }

            if (!isset($this->sibling_positions[$parent_id])) {
                $this->sibling_positions[$parent_id] = 0;
            }

            $this->sibling_positions[$parent_id]++;

            $this->current_sibling_info = [
                'parent_id' => $parent_id,
                'index'     => $this->sibling_positions[$parent_id],
                'total'     => $this->siblings_total[$parent_id],
            ];

            if ($element_id > 0 && isset($children_elements[$element_id]) && is_array($children_elements[$element_id])) {
                $this->child_totals[$element_id] = count($children_elements[$element_id]);
            } elseif ($element_id > 0 && !isset($this->child_totals[$element_id])) {
                $this->child_totals[$element_id] = 0;
            }

            parent::display_element($element, $children_elements, $max_depth, $depth, $args, $output);
        }

        public function start_lvl(&$output, $depth = 0, $args = []) {
            $GLOBALS['comment_depth'] = $depth + 1;
            $parent_id = 0;
            if (isset($GLOBALS['comment']) && $GLOBALS['comment'] instanceof WP_Comment) {
                $parent_id = (int) $GLOBALS['comment']->comment_ID;
            }

            $this->parent_stack[] = $parent_id;

            $hidden_count = 0;
            if ($parent_id > 0) {
                $child_total = isset($this->child_totals[$parent_id]) ? (int) $this->child_totals[$parent_id] : 0;
                if ($child_total > $this->visible_replies_limit) {
                    $hidden_count = $child_total - $this->visible_replies_limit;
                }
            }

            $output .= sprintf(
                '<div class="comment-replies space-y-4 mt-4"%s%s>' . "\n",
                $parent_id > 0 ? ' data-parent-id="' . esc_attr((string) $parent_id) . '"' : '',
                $hidden_count > 0 ? ' data-hidden-count="' . esc_attr((string) $hidden_count) . '"' : ''
            );
        }

        public function end_lvl(&$output, $depth = 0, $args = []) {
            $GLOBALS['comment_depth'] = $depth + 1;
            $parent_id = array_pop($this->parent_stack);

            $output .= "</div>\n";

            if ($parent_id > 0) {
                $child_total = isset($this->child_totals[$parent_id]) ? (int) $this->child_totals[$parent_id] : 0;
                if ($child_total > $this->visible_replies_limit) {
                    $hidden_count = $child_total - $this->visible_replies_limit;
                    $button_label = sprintf(
                        /* translators: %d: number of hidden replies */
                        _n('Show %d more reply', 'Show %d more replies', $hidden_count, 'gta6-mods'),
                        $hidden_count
                    );

                    $output .= sprintf(
                        '<button type="button" class="gta6-reply-toggle mt-3 text-xs font-semibold text-pink-600 hover:text-pink-700 transition" data-action="toggle-replies" data-parent-id="%1$s" data-hidden-count="%2$s" data-state="collapsed" data-default-label="%3$s">%4$s</button>' . "\n",
                        esc_attr((string) $parent_id),
                        esc_attr((string) $hidden_count),
                        esc_attr($button_label),
                        esc_html($button_label)
                    );
                }
            }
        }

        public function start_el(&$output, $comment, $depth = 0, $args = [], $id = 0) {
            if (!($comment instanceof WP_Comment)) {
                return;
            }

            $depth++;
            $GLOBALS['comment_depth'] = $depth;
            $GLOBALS['comment'] = $comment;

            $comment_id = (int) $comment->comment_ID;
            $comment_parent = (int) $comment->comment_parent;
            $comment_classes = get_comment_class('comment-wrapper', $comment);
            $is_retracted    = gta6mods_is_comment_retracted($comment);
            $is_pinned       = $this->is_comment_pinned($comment_id);
            $is_hidden_reply = $this->should_hide_comment($comment);

            if ($is_retracted) {
                $comment_classes[] = 'comment-wrapper--retracted';
            }

            if ($is_pinned) {
                $comment_classes[] = 'comment-wrapper--pinned';
            }

            if ($is_hidden_reply) {
                $comment_classes[] = 'hidden';
                $comment_classes[] = 'gta6-reply-hidden';
            }

            $classes = implode(' ', array_unique(array_map('sanitize_html_class', $comment_classes)));
            $avatar     = get_avatar($comment, 40, '', '', ['class' => 'rounded-full w-10 h-10']);
            $author     = get_comment_author($comment);
            $permalink  = gta6mods_get_comment_permalink($comment);
            if ('' === $permalink) {
                $permalink = get_comment_link($comment);
            }
            $time_diff  = human_time_diff(get_comment_time('U', true), current_time('timestamp', true));
            $time_label = sprintf(
                /* translators: %s: human-readable time difference */
                esc_html__('%s ago', 'gta6-mods'),
                $time_diff
            );

            $post       = get_post($comment->comment_post_ID);
            $is_creator = ($post instanceof WP_Post) && ((int) $post->post_author === (int) $comment->user_id);

            $current_user_id  = get_current_user_id();
            $is_comment_owner = $current_user_id > 0 && (int) $comment->user_id === $current_user_id;
            $can_pin_comment  = gta6mods_comment_can_be_pinned($comment) && gta6mods_user_can_pin_comment($comment, $current_user_id);

            $comment_content = apply_filters('comment_text', get_comment_text($comment), $comment, $args);

            $attachments = gta6mods_get_comment_attachments_markup($comment);

            $like_data   = gta6_mods_get_comment_like_data($comment_id);
            $like_count  = (int) $like_data['count'];
            $user_liked  = (bool) $like_data['user_liked'];

            $author_url = get_author_posts_url($comment->user_id);
            $author_html = esc_html($author);

            $deleted_placeholder = function_exists('gta6mods_get_deleted_comment_placeholder')
                ? gta6mods_get_deleted_comment_placeholder()
                : __('The user account that posted this comment no longer exists.', 'gta6-mods');

            $author_deleted = false;
            if ($comment->user_id > 0 && function_exists('gta6_mods_get_account_deletion_data')) {
                $deletion_data = gta6_mods_get_account_deletion_data($comment->user_id);
                if (is_array($deletion_data) && isset($deletion_data['status']) && 'deleted' === $deletion_data['status']) {
                    $author_deleted = true;
                }
            }

            if ($author_deleted) {
                $avatar = '<div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500" role="img" aria-label="' . esc_attr__('Deleted user avatar', 'gta6-mods') . '"><i class="fas fa-user-slash"></i></div>';
                $author_html = '<span class="comment-author-deleted text-gray-500">' . esc_html__('Deleted user', 'gta6-mods') . '</span>';
                $comment_content = wpautop(esc_html($deleted_placeholder));
                $author_url = '';
            }

            if ($comment->user_id > 0 && $author_url) {
                $author_html = sprintf(
                    '<a href="%s" class="comment-author-link" title="%s">%s</a>',
                    esc_url($author_url),
                    esc_attr(sprintf(__('View profile of %s', 'gta6-mods'), $author)),
                    esc_html($author)
                );
            }

            $reply_reference = $author_deleted ? esc_html__('Deleted user', 'gta6-mods') : $author;

            if ($author_deleted || $is_retracted) {
                $like_button_html = sprintf(
                    '<span class="flex items-center text-gray-400 cursor-not-allowed" aria-disabled="true">'
                    . '<i class="fas fa-thumbs-up mr-1"></i> <span class="comment-like-count">%s</span>'
                    . '</span>',
                    esc_html($like_count)
                );
            } else {
                $like_button_html = sprintf(
                    '<button type="button" class="comment-like-btn hover:text-pink-600 flex items-center" data-comment-id="%1$s" aria-pressed="%2$s">'
                    . '<i class="fas fa-thumbs-up mr-1"></i> <span class="comment-like-count">%3$s</span>'
                    . '</button>',
                    esc_attr($comment_id),
                    $user_liked ? 'true' : 'false',
                    esc_html($like_count)
                );
            }

            if ($is_retracted) {
                $comment_content = '<em class="comment-retracted-text">' . esc_html(gta6mods_get_retracted_comment_text()) . '</em>';
                $attachments     = '';
            }

            $menu_markup = '';
            if (!$author_deleted) {
                $menu_items = [];

                if ($can_pin_comment) {
                    $menu_items[] = sprintf(
                        '<button type="button" class="comment-menu-item flex items-center w-full px-4 py-2 text-gray-700 hover:bg-gray-100" data-action="pin-comment" data-pin-state="%1$s" data-comment-id="%2$d"><i class="fas fa-thumbtack fa-fw mr-2"></i>%3$s</button>',
                        $is_pinned ? 'unpin' : 'pin',
                        $comment_id,
                        esc_html($is_pinned ? __('Unpin', 'gta6-mods') : __('Pin comment', 'gta6-mods'))
                    );
                }

                if ($permalink) {
                    $menu_items[] = sprintf(
                        '<button type="button" class="comment-menu-item flex items-center w-full px-4 py-2 text-gray-700 hover:bg-gray-100" data-action="copy-comment-link" data-comment-id="%1$d" data-comment-link="%2$s"><i class="fas fa-link fa-fw mr-2"></i>%3$s</button>',
                        $comment_id,
                        esc_url($permalink),
                        esc_html__('Copy link', 'gta6-mods')
                    );
                }

                if ($is_comment_owner && !$is_retracted && gta6mods_user_can_retract_comment($comment, $current_user_id)) {
                    $menu_items[] = sprintf(
                        '<button type="button" class="comment-menu-item flex items-center w-full px-4 py-2 text-red-600 hover:bg-red-50" data-action="retract-comment" data-comment-id="%1$d"><i class="fas fa-trash-alt fa-fw mr-2"></i>%2$s</button>',
                        $comment_id,
                        esc_html__('Delete', 'gta6-mods')
                    );
                } elseif (!$is_retracted) {
                    $menu_items[] = '<!--gta6-report-placeholder-->';
                }

                if (!empty($menu_items)) {
                    $menu_markup = implode('', $menu_items);
                    $menu_markup = apply_filters('gta6_mods_comment_menu_items', $menu_markup, $comment);
                }
            }

            $max_depth_allowed = $this->get_max_depth_for_comment($comment);
            $can_reply        = $max_depth_allowed > $depth;
            $is_forum_context = function_exists('gta6_forum_is_forum_context') && gta6_forum_is_forum_context();

            ob_start();
            ?>
            <div id="comment-<?php echo esc_attr($comment_id); ?>" class="<?php echo esc_attr($classes); ?>" data-comment-id="<?php echo esc_attr($comment_id); ?>" data-comment-parent="<?php echo esc_attr((string) $comment_parent); ?>" data-comment-retracted="<?php echo $is_retracted ? '1' : '0'; ?>" data-comment-pinned="<?php echo $is_pinned ? '1' : '0'; ?>" data-comment-depth="<?php echo esc_attr((string) $depth); ?>" data-like-count="<?php echo esc_attr((string) $like_count); ?>"<?php echo $is_hidden_reply ? ' data-hidden-default="1"' : ''; ?><?php echo $permalink ? ' data-comment-permalink="' . esc_url($permalink) . '"' : ''; ?>>
                <div class="comment-instance flex space-x-3">
                    <div class="clickable-area flex-shrink-0 flex flex-col items-center" role="button" tabindex="0" aria-expanded="true">
                        <?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="collapsed-icon hidden w-10 h-10 rounded-full bg-gray-200 items-center justify-center text-gray-500 hover:bg-gray-300">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="comment-thread-line w-0.5 mt-2 bg-gray-200 hover:bg-pink-500 flex-grow"></div>
                    </div>
                    <div class="comment-main-content flex-1">
                        <p class="font-semibold text-gray-900 text-sm">
                            <?php echo $author_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php if ($is_creator) : ?>
                                <span class="text-xs text-white font-semibold bg-pink-500 px-1.5 py-0.5 rounded-full ml-1"><?php esc_html_e('Creator', 'gta6-mods'); ?></span>
                            <?php endif; ?>
                            <time datetime="<?php echo esc_attr(get_comment_date('c', $comment->comment_ID)); ?>">
                                <a href="<?php echo esc_url($permalink); ?>" 
                                   class="text-xs text-gray-500 font-normal whitespace-nowrap ml-1"
                                   title="<?php echo esc_attr(get_comment_date('F j, Y \a\t g:i a', $comment->comment_ID)); ?>">
                                    <?php echo esc_html($time_label); ?>
                                </a>
                            </time>
                            <span class="collapsed-text hidden text-xs font-normal text-gray-500">[+]</span>
                        </p>
                        <div class="comment-body">
                            <?php if ($is_pinned) : ?>
                                <div class="comment-pinned-badge flex items-center text-xs font-semibold text-pink-600 mb-1">
                                    <i class="fas fa-thumbtack fa-fw mr-1"></i>
                                    <span><?php esc_html_e('Pinned comment', 'gta6-mods'); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="mt-1 text-gray-800 leading-relaxed comment-content"><?php
                                $rendered_comment_content = wpautop($comment_content);
                                echo wp_kses($rendered_comment_content, gta6mods_get_allowed_comment_html());
                            ?></div>
                            <?php if ($attachments) : ?>
                                <?php echo $attachments; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                            <?php if ('0' === $comment->comment_approved) : ?>
                                <p class="mt-2 text-xs text-yellow-500"><?php esc_html_e('Your comment is awaiting moderation.', 'gta6-mods'); ?></p>
                            <?php endif; ?>
                            <div class="flex items-center space-x-4 text-xs text-gray-500 mt-2">
                                <?php if ($can_reply) : ?>
                                    <button type="button" class="reply-btn hover:text-pink-600 font-semibold" data-comment-id="<?php echo esc_attr($comment_id); ?>" data-comment-author="<?php echo esc_attr($reply_reference); ?>"><?php esc_html_e('Reply', 'gta6-mods'); ?></button>
                                <?php endif; ?>
                                <?php echo $like_button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php if (!$author_deleted && '' !== trim($menu_markup)) : ?>
                                    <div class="relative">
                                        <button type="button" class="menu-toggle-btn hover:text-pink-600">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="comment-menu hidden absolute bottom-full left-0 mb-2 w-40 bg-white rounded-md shadow-lg z-10 border border-gray-200 text-sm">
                                            <?php echo wp_kses_post($menu_markup); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($can_reply) : ?>
                                <div id="reply-form-container-<?php echo esc_attr($comment_id); ?>" class="reply-form-container hidden mt-4">
                                    <div class="comment-box-container border border-gray-300 rounded-lg focus-within:ring-2 focus-within:ring-pink-500 overflow-hidden transition-all relative"<?php echo $is_forum_context ? ' data-editor-mode="plain"' : ''; ?>>
                                        <div class="comment-rich-editor">
                                            <?php if ($is_forum_context) : ?>
                                                <?php echo gta6mods_get_comment_editor_toolbar(); ?>
                                            <?php endif; ?>
                                            <div class="comment-box-textarea w-full p-3 text-sm bg-transparent border-0 focus:ring-0 is-empty" contenteditable="true" data-placeholder="<?php printf(esc_attr__('Replying to @%s...', 'gta6-mods'), esc_attr($reply_reference)); ?>"<?php echo $is_forum_context ? ' data-editor-mode="plain"' : ''; ?>></div>
                                        </div>
                                        <div class="image-preview-container hidden px-3 mt-2">
                                            <div class="relative inline-block">
                                                <img src="" class="image-preview max-h-24 rounded" alt="<?php esc_attr_e('Image preview', 'gta6-mods'); ?>">
                                                <button type="button" class="remove-image-btn absolute -top-2 -right-2 bg-gray-800 hover:bg-black text-white rounded-full h-6 w-6 flex items-center justify-center text-sm font-bold transition-colors">&times;</button>
                                            </div>
                                        </div>
                                        <div class="gif-preview-container hidden px-3 mt-2">
                                            <div class="relative inline-block">
                                                <img src="" alt="" class="gif-preview max-h-24 rounded">
                                                <button type="button" class="remove-gif-btn absolute -top-2 -right-2 bg-gray-800 hover:bg-black text-white rounded-full h-6 w-6 flex items-center justify-center text-sm font-bold transition-colors">&times;</button>
                                            </div>
                                        </div>
                                        <div class="comment-actions-bar hidden px-3 pt-1.5 pb-3<?php echo $is_forum_context ? ' bg-gray-50' : ''; ?> flex justify-between items-center">
                                            <div class="flex items-center text-gray-500">
                                                <input type="file" class="comment-image-input comment-form-hidden-fields" accept="image/*">
                                                <?php if (!$is_forum_context) : ?>
                                                    <button type="button" title="<?php esc_attr_e('Upload image', 'gta6-mods'); ?>" class="upload-image-btn hover:text-gray-800"><i class="fas fa-image fa-fw"></i></button>
                                                    <button type="button" title="<?php esc_attr_e('Insert GIF', 'gta6-mods'); ?>" class="gif-btn ml-3 hover:text-gray-800 font-bold text-xs">GIF</button>
                                                    <button type="button" title="<?php esc_attr_e('Mention user', 'gta6-mods'); ?>" class="mention-btn ml-3 hover:text-gray-800"><i class="fas fa-at fa-fw"></i></button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center gap-x-2">
                                                <button type="button" class="cancel-reply-btn text-gray-500 font-semibold py-1.5 px-3 rounded-md text-xs transition hover:bg-gray-100" data-comment-id="<?php echo esc_attr($comment_id); ?>"><?php esc_html_e('Cancel', 'gta6-mods'); ?></button>
                                                <button type="button" class="post-reply-btn bg-pink-500 hover:bg-pink-600 text-white font-semibold py-1.5 px-3 rounded-lg text-xs transition" data-comment-id="<?php echo esc_attr($comment_id); ?>"><?php esc_html_e('Reply', 'gta6-mods'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php
            $output .= ob_get_clean();
        }

        public function end_el(&$output, $comment, $depth = 0, $args = []) {
            $output .= "</div>\n"; // .comment-wrapper
        }
    }
}

/**
 * Get like data for a specific comment.
 */
function gta6_mods_get_comment_like_data($comment_id) {
    $comment_id = (int) $comment_id;
    if ($comment_id <= 0) {
        return ['count' => 0, 'user_liked' => false];
    }

    $likes = get_comment_meta($comment_id, 'gta6_comment_likes', true);
    $likes = is_array($likes) ? array_values(array_unique(array_filter(array_map('absint', $likes)))) : [];

    $user_liked = is_user_logged_in() ? in_array(get_current_user_id(), $likes, true) : false;

    return ['count' => count($likes), 'user_liked' => $user_liked];
}

/**
 * Save custom comment meta fields.
 */
function gta6_mods_save_comment_meta($comment_id) {
    $nonce = isset($_POST['gta6_comment_meta_nonce']) ? wp_unslash($_POST['gta6_comment_meta_nonce']) : '';

    if ($nonce && wp_verify_nonce($nonce, 'gta6_comment_meta')) {
        $comment = get_comment($comment_id);

        if ($comment instanceof WP_Comment) {
            $image_id = isset($_POST['comment_image_id']) ? absint($_POST['comment_image_id']) : 0;

            if ($image_id > 0) {
                $attachment = get_post($image_id);

                if ($attachment instanceof WP_Post && 'attachment' === $attachment->post_type) {
                    update_comment_meta($comment_id, 'gta6_comment_image_id', $image_id);
                } else {
                    delete_comment_meta($comment_id, 'gta6_comment_image_id');
                }
            } else {
                delete_comment_meta($comment_id, 'gta6_comment_image_id');
            }

            $gif_url_raw = isset($_POST['comment_gif_url']) ? wp_unslash($_POST['comment_gif_url']) : '';
            $gif_url_raw = is_string($gif_url_raw) ? trim($gif_url_raw) : '';
            $gif_url      = '' !== $gif_url_raw ? esc_url_raw($gif_url_raw) : '';

            if ($gif_url) {
                update_comment_meta($comment_id, 'gta6_comment_gif_url', $gif_url);
            } else {
                delete_comment_meta($comment_id, 'gta6_comment_gif_url');
            }

            $mentions_raw = isset($_POST['comment_mentioned_users']) ? wp_unslash($_POST['comment_mentioned_users']) : '';
            $mentions_raw = is_string($mentions_raw) ? $mentions_raw : '';
            $mention_ids  = [];

            if ('' !== $mentions_raw) {
                $pieces = array_map('trim', explode(',', $mentions_raw));

                foreach ($pieces as $piece) {
                    if ('' === $piece) {
                        continue;
                    }

                    $user_id = absint($piece);
                    if ($user_id <= 0) {
                        continue;
                    }

                    $user = get_user_by('id', $user_id);
                    if ($user) {
                        $mention_ids[] = $user_id;
                    }
                }
            }

            $mention_ids = array_values(array_unique($mention_ids));

            if (!empty($mention_ids)) {
                update_comment_meta($comment_id, 'gta6_comment_mentioned_users', $mention_ids);
            } else {
                delete_comment_meta($comment_id, 'gta6_comment_mentioned_users');
            }
        }
    }

    // Update like count cache whenever a comment is posted or its status changes.
    gta6_mods_update_comment_thread_likes($comment_id);
}

if (is_admin()) {
    add_filter('comment_status_links', 'gta6mods_add_retracted_comment_status_link');
    add_action('pre_get_comments', 'gta6mods_filter_retracted_comments_admin');
    add_filter('comment_row_actions', 'gta6mods_add_retracted_comment_row_action', 10, 2);
    add_action('admin_post_gta6mods_restore_comment', 'gta6mods_handle_restore_comment');
    add_action('admin_notices', 'gta6mods_render_restore_comment_notice');
}

if (!function_exists('gta6mods_add_retracted_comment_status_link')) {
    /**
     * Adds the "Retracted" view to the comments screen in wp-admin.
     *
     * @param array<string, string> $status_links Status links.
     *
     * @return array<string, string>
     */
    function gta6mods_add_retracted_comment_status_link($status_links) {
        $count = get_comments([
            'count'      => true,
            'status'     => 'all',
            'meta_key'   => 'gta6mods_retracted',
            'meta_value' => '1',
        ]);

        $count = (int) $count;

        $url   = add_query_arg('comment_status', 'gta6mods_retracted');
        $class = (isset($_REQUEST['comment_status']) && 'gta6mods_retracted' === $_REQUEST['comment_status']) ? 'class="current"' : '';

        $status_links['gta6mods_retracted'] = sprintf(
            '<a href="%1$s" %2$s>%3$s <span class="count">(%4$d)</span></a>',
            esc_url($url),
            $class,
            esc_html__('Retracted', 'gta6-mods'),
            $count
        );

        return $status_links;
    }
}

if (!function_exists('gta6mods_filter_retracted_comments_admin')) {
    /**
     * Adjusts the admin comments query when viewing the retracted list.
     *
     * @param WP_Comment_Query $query Comment query instance.
     */
    function gta6mods_filter_retracted_comments_admin($query) {
        if (!is_admin() || !$query instanceof WP_Comment_Query) {
            return;
        }

        if (!isset($_REQUEST['comment_status']) || 'gta6mods_retracted' !== $_REQUEST['comment_status']) {
            return;
        }

        $meta_query = isset($query->query_vars['meta_query']) && is_array($query->query_vars['meta_query'])
            ? $query->query_vars['meta_query']
            : [];

        $meta_query[] = [
            'key'   => 'gta6mods_retracted',
            'value' => '1',
        ];

        $query->query_vars['meta_query']     = $meta_query;
        $query->query_vars['comment_status'] = 'all';
    }
}

if (!function_exists('gta6mods_add_retracted_comment_row_action')) {
    /**
     * Adds a restore button to retracted comments within wp-admin.
     *
     * @param array<string, string> $actions Existing row actions.
     * @param WP_Comment            $comment Comment object.
     *
     * @return array<string, string>
     */
    function gta6mods_add_retracted_comment_row_action($actions, $comment) {
        if (!$comment instanceof WP_Comment) {
            $comment = get_comment($comment);
        }

        if (!$comment instanceof WP_Comment) {
            return $actions;
        }

        if (!gta6mods_is_comment_retracted($comment) || !current_user_can('moderate_comments')) {
            return $actions;
        }

        $url = wp_nonce_url(
            add_query_arg(
                [
                    'action'     => 'gta6mods_restore_comment',
                    'comment_id' => (int) $comment->comment_ID,
                ],
                admin_url('admin-post.php')
            ),
            'gta6mods_restore_comment_' . (int) $comment->comment_ID
        );

        $actions['gta6mods_restore'] = sprintf(
            '<a href="%1$s" class="button button-small">%2$s</a>',
            esc_url($url),
            esc_html__('Restore comment', 'gta6-mods')
        );

        return $actions;
    }
}

if (!function_exists('gta6mods_handle_restore_comment')) {
    /**
     * Handles restoration requests triggered from the admin area.
     */
    function gta6mods_handle_restore_comment() {
        if (!current_user_can('moderate_comments')) {
            wp_die(esc_html__('You are not allowed to restore comments.', 'gta6-mods'));
        }

        $comment_id = isset($_GET['comment_id']) ? (int) $_GET['comment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ($comment_id <= 0) {
            wp_safe_redirect(admin_url('edit-comments.php'));
            exit;
        }

        check_admin_referer('gta6mods_restore_comment_' . $comment_id);

        $result = gta6mods_restore_retracted_comment($comment_id, get_current_user_id());

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('edit-comments.php');
        }

        $redirect = remove_query_arg(['gta6mods_restore', 'gta6mods_restore_error'], $redirect);

        if (is_wp_error($result)) {
            $message = $result->get_error_message();
            if ('' === $message) {
                $message = __('The comment could not be restored.', 'gta6-mods');
            }
            $redirect = add_query_arg(
                'gta6mods_restore_error',
                rawurlencode($message),
                $redirect
            );
        } else {
            $redirect = add_query_arg('gta6mods_restore', '1', $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }
}

if (!function_exists('gta6mods_render_restore_comment_notice')) {
    /**
     * Displays admin notices after a restore attempt.
     */
    function gta6mods_render_restore_comment_notice() {
        if (!is_admin() || !current_user_can('moderate_comments')) {
            return;
        }

        if (isset($_GET['gta6mods_restore']) && '1' === $_GET['gta6mods_restore']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Comment restored successfully.', 'gta6-mods') . '</p></div>';
        }

        if (isset($_GET['gta6mods_restore_error'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $message = sanitize_text_field(wp_unslash((string) $_GET['gta6mods_restore_error']));
            if ('' === $message) {
                $message = __('The comment could not be restored.', 'gta6-mods');
            }
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
add_action('comment_post', 'gta6_mods_save_comment_meta');
add_action('wp_set_comment_status', 'gta6_mods_update_comment_thread_likes');


/**
 * AJAX handler to search for users to mention.
 */
function gta6_mods_ajax_search_users() {
    check_ajax_referer('gta6_comments_nonce', 'nonce');

    $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';

    $query_args = [
        'number'  => 5,
        'orderby' => 'display_name',
        'order'   => 'ASC',
    ];

    if ('' !== $query) {
        $query_args['search']         = '*' . esc_attr($query) . '*';
        $query_args['search_columns'] = ['user_login', 'user_nicename', 'display_name'];
    }

    $user_query = new WP_User_Query($query_args);

    $users = [];
    foreach ($user_query->get_results() as $user) {
        $deletion_data = function_exists('gta6_mods_get_account_deletion_data')
            ? gta6_mods_get_account_deletion_data($user->ID)
            : null;

        if (is_array($deletion_data) && isset($deletion_data['status']) && 'deleted' === $deletion_data['status']) {
            continue;
        }

        $users[] = [
            'id'       => (int) $user->ID,
            'name'     => $user->display_name ?: $user->user_login,
            'username' => $user->user_login,
            'avatar'   => get_avatar_url($user->ID, ['size' => 40]),
        ];
    }

    wp_send_json_success($users);
}
add_action('wp_ajax_gta6_search_users', 'gta6_mods_ajax_search_users');
add_action('wp_ajax_nopriv_gta6_search_users', 'gta6_mods_ajax_search_users');

/**
 * AJAX handler for liking/unliking a comment.
 */
function gta6_mods_ajax_like_comment() {
    check_ajax_referer('gta6_like_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => esc_html__('You must be logged in to like comments.', 'gta6-mods')], 403);
    }

    $comment_id = isset($_POST['comment_id']) ? absint($_POST['comment_id']) : 0;
    $comment    = $comment_id ? get_comment($comment_id) : null;
    if (!$comment instanceof WP_Comment) {
        wp_send_json_error(['message' => esc_html__('Invalid comment.', 'gta6-mods')], 404);
    }

    $user_id = get_current_user_id();
    $likes   = get_comment_meta($comment_id, 'gta6_comment_likes', true);
    if (!is_array($likes)) {
        $likes = [];
    }

    $likes = array_map('absint', $likes);
    $liked = in_array($user_id, $likes, true);

    if ($liked) {
        $likes = array_diff($likes, [$user_id]);
        $liked = false;
    } else {
        $likes[] = $user_id;
        $liked   = true;
    }

    $likes = array_values(array_unique(array_filter($likes)));

    update_comment_meta($comment_id, 'gta6_comment_likes', $likes);
    
    // Gyorsítótár frissítése a like/unlike után
    gta6_mods_update_comment_thread_likes($comment_id);

    wp_send_json_success(['count' => count($likes), 'liked' => $liked]);
}
add_action('wp_ajax_gta6_like_comment', 'gta6_mods_ajax_like_comment');

/**
 * Handle image uploads from the comment form.
 */
function gta6_mods_handle_comment_image_upload($post_id, $file = null) {
    if (null === $file) {
        if (!isset($_FILES['comment_image_file'])) {
            return 0;
        }

        $file = $_FILES['comment_image_file'];
    }
    if (!is_array($file) || empty($file['name'])) {
        return 0;
    }

    if (!empty($file['error']) && UPLOAD_ERR_OK !== (int) $file['error']) {
        return new WP_Error('comment_image_upload', esc_html__('Image upload failed. Please try again.', 'gta6-mods'));
    }

    $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    if (empty($filetype['type']) || strpos($filetype['type'], 'image/') !== 0) {
        return new WP_Error('comment_image_type', esc_html__('Please choose a valid image file.', 'gta6-mods'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $allowed_mimes = array_filter(
        get_allowed_mime_types(),
        static function ($mime) {
            return strpos($mime, 'image/') === 0;
        }
    );

    $upload = wp_handle_upload(
        $file,
        [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ]
    );

    if (isset($upload['error'])) {
        return new WP_Error('comment_image_upload', $upload['error']);
    }

    $attachment = [
        'post_mime_type' => $upload['type'],
        'post_title'     => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_author'    => get_current_user_id(),
    ];

    $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

    if (is_wp_error($attachment_id)) {
        if (isset($upload['file']) && file_exists($upload['file'])) {
            wp_delete_file($upload['file']);
        }

        return $attachment_id;
    }

    $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    if (!is_wp_error($metadata) && !empty($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    return $attachment_id;
}

/**
 * AJAX handler for submitting a new comment.
 */
function gta6_mods_ajax_submit_comment() {
    check_ajax_referer('gta6_comments_nonce', 'nonce');

    $post_id = isset($_POST['comment_post_ID']) ? absint($_POST['comment_post_ID']) : 0;
    $post    = $post_id ? get_post($post_id) : null;

    if (!$post instanceof WP_Post || !comments_open($post_id)) {
        wp_send_json_error(['message' => esc_html__('Comments are closed for this post.', 'gta6-mods')], 400);
    }

    $comment_content = isset($_POST['comment']) ? trim((string) wp_unslash($_POST['comment'])) : '';
    if ('' === $comment_content) {
        wp_send_json_error(['message' => esc_html__('Please enter a comment before submitting.', 'gta6-mods')], 400);
    }

    $comment_parent = isset($_POST['comment_parent']) ? absint($_POST['comment_parent']) : 0;

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $author       = $current_user->display_name ?: $current_user->user_login;
        $email        = $current_user->user_email;
        $url          = $current_user->user_url;
    } else {
        $author = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';
        $email  = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $url    = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';

        if ('' === $author || '' === $email) {
            wp_send_json_error(['message' => esc_html__('Name and email are required to post a comment.', 'gta6-mods')], 400);
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => esc_html__('Please provide a valid email address.', 'gta6-mods')], 400);
        }
    }

    $cookies_consent = isset($_POST['wp-comment-cookies-consent']) ? 'yes' : '';

    $attachment_id = 0;
    if (!empty($_FILES['comment_image_file']['name'])) {
        $upload = gta6_mods_handle_comment_image_upload($post_id);

        if (is_wp_error($upload)) {
            wp_send_json_error(['message' => $upload->get_error_message()], 400);
        }

        $attachment_id             = (int) $upload;
        $_POST['comment_image_id'] = (string) $attachment_id;
    } else {
        unset($_POST['comment_image_id']);
    }

    $submission = [
        'comment_post_ID'            => $post_id,
        'comment_parent'             => $comment_parent,
        'comment'                    => $comment_content,
        'comment_type'               => '',
        'author'                     => isset($author) ? $author : '',
        'email'                      => isset($email) ? $email : '',
        'url'                        => isset($url) ? $url : '',
        'wp-comment-cookies-consent' => $cookies_consent,
    ];

    if (is_user_logged_in()) {
        $submission['user_ID'] = get_current_user_id();
    }

    $comment = wp_handle_comment_submission($submission);

    if (is_wp_error($comment)) {
        if ($attachment_id > 0) {
            wp_delete_attachment($attachment_id, true);
        }
        wp_send_json_error(['message' => $comment->get_error_message()], 400);
    }

    if (!$comment instanceof WP_Comment) {
        if ($attachment_id > 0) {
            wp_delete_attachment($attachment_id, true);
        }
        wp_send_json_error(['message' => esc_html__('Unable to save your comment. Please try again.', 'gta6-mods')], 500);
    }

    $comment_id = (int) $comment->comment_ID;

    $approved_count = get_comments_number($post_id);
    $display_count  = ('1' === $comment->comment_approved) ? $approved_count : ($approved_count + 1);

    $pinned_comment_id = gta6mods_get_pinned_comment_id($post_id);

    $max_depth = gta6mods_get_comment_max_depth($post_id);

    $html = wp_list_comments(
        [
            'style'      => 'div',
            'short_ping' => false,
            'avatar_size'=> 40,
            'walker'     => new GTA6_Mods_Comment_Walker([
                'pinned_comment_id' => $pinned_comment_id,
                'max_depth'         => $max_depth,
            ]),
            'max_depth'  => $max_depth,
            'echo'       => false,
        ],
        [$comment]
    );

    $status  = (string) $comment->comment_approved;
    $message = ('0' === $status)
        ? esc_html__('Your comment is awaiting moderation.', 'gta6-mods')
        : esc_html__('Your comment has been posted.', 'gta6-mods');

    wp_send_json_success(
        [
            'comment_id' => $comment_id,
            'parent_id'  => $comment_parent,
            'status'     => $status,
            'html'       => $html,
            'counts'     => [
                'approved' => (int) $approved_count,
                'display'  => (int) $display_count,
            ],
            'message'    => $message,
        ]
    );
}
add_action('wp_ajax_gta6_submit_comment', 'gta6_mods_ajax_submit_comment');
add_action('wp_ajax_nopriv_gta6_submit_comment', 'gta6_mods_ajax_submit_comment');

/**
 * Format @mentions in comment text.
 */
function gta6_mods_format_comment_mentions($comment_text, $comment, $args) {
    if (!($comment instanceof WP_Comment)) {
        return $comment_text;
    }

    $mention_ids = get_comment_meta($comment->comment_ID, 'gta6_comment_mentioned_users', true);
    if (!is_array($mention_ids) || empty($mention_ids)) {
        return $comment_text;
    }

    $mention_map = [];
    foreach ($mention_ids as $user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            continue;
        }
        $user = get_user_by('id', $user_id);
        if (!$user) {
            continue;
        }
        $mention_map[strtolower($user->user_login)] = [
            'username' => $user->user_login,
            'url'      => get_author_posts_url($user_id),
        ];
    }

    if (empty($mention_map)) {
        return $comment_text;
    }

    $pattern = '/@([A-Za-z0-9_\.-]+)/u';
    return preg_replace_callback(
        $pattern,
        static function ($matches) use ($mention_map) {
            $username = $matches[1];
            $key      = strtolower($username);
            if (!isset($mention_map[$key])) {
                return $matches[0];
            }
            $username_raw = $mention_map[$key]['username'];
            $url   = esc_url($mention_map[$key]['url']);
            $label = esc_html('@' . $username_raw);
            $title = esc_attr(sprintf(__('View profile of %s', 'gta6-mods'), $username_raw));

            return sprintf('<a class="mention-tag" href="%s" title="%s">%s</a>', $url, $title, $label);
        },
        $comment_text
    );
}
add_filter('comment_text', 'gta6_mods_format_comment_mentions', 20, 3);

/**
 * Recursively get all descendant comments for a given comment.
 *
 * @param int $comment_id The ID of the parent comment.
 * @param int $post_id The ID of the post.
 * @return WP_Comment[] A flat array of descendant comment objects.
 */
function gta6_mods_get_all_comment_descendants($comment_id, $post_id) {
    $descendants = [];
    $children = get_comments([
        'post_id' => $post_id,
        'parent' => $comment_id,
        'status' => 'approve',
    ]);

    if ($children) {
        foreach ($children as $child) {
            $descendants[] = $child;
            $grandchildren = gta6_mods_get_all_comment_descendants($child->comment_ID, $post_id);
            if ($grandchildren) {
                $descendants = array_merge($descendants, $grandchildren);
            }
        }
    }
    return $descendants;
}

/**
 * Updates the total like count for a comment thread, stored in the top-level parent's meta.
 *
 * @param int|WP_Comment $comment_id_or_object The comment that was changed.
 */
function gta6_mods_update_comment_thread_likes($comment_id_or_object) {
    $comment = get_comment($comment_id_or_object);
    if (!$comment) {
        return;
    }

    // 1. Find the top-level parent of the current comment
    $top_parent_id = (int) $comment->comment_ID;
    $current_comment = $comment;
    while ((int) $current_comment->comment_parent > 0) {
        $parent = get_comment($current_comment->comment_parent);
        if (!$parent) {
            break;
        }
        $top_parent_id = (int) $parent->comment_ID;
        $current_comment = $parent;
    }

    // 2. Get all descendants of the top-level parent
    $descendants = gta6_mods_get_all_comment_descendants($top_parent_id, $comment->comment_post_ID);

    // Add the parent itself to the list
    $all_comments_in_thread = $descendants;
    $all_comments_in_thread[] = get_comment($top_parent_id);

    // 3. Sum up the likes
    $total_likes = 0;
    foreach ($all_comments_in_thread as $thread_comment) {
        if ($thread_comment) {
            $like_data = gta6_mods_get_comment_like_data($thread_comment->comment_ID);
            $total_likes += (int) $like_data['count'];
        }
    }

    // 4. Update the meta field for the top-level parent comment
    update_comment_meta($top_parent_id, '_gta6_comment_thread_like_count', $total_likes);
}

