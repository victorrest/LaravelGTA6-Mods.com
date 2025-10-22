<?php
/**
 * Editor.js integration functions.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gta6_mods_extract_youtube_id')) {
    /**
     * Extract a YouTube video ID from different URL formats.
     *
     * @param string $url Potential YouTube URL or identifier.
     * @return string Sanitized YouTube video ID if found, empty string otherwise.
     */
    function gta6_mods_extract_youtube_id($url) {
        if (empty($url) || !is_string($url)) {
            return '';
        }

        $patterns = [
            '~(?:youtu\.be/)([A-Za-z0-9_-]{11})~',
            '~(?:youtube(?:-nocookie)?\.com/(?:embed/|shorts/|live/))([A-Za-z0-9_-]{11})~',
            '~(?:youtube(?:-nocookie)?\.com/watch\?[^\s]*v=)([A-Za-z0-9_-]{11})~',
            '~^([A-Za-z0-9_-]{11})$~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) && !empty($matches[1])) {
                return substr(preg_replace('/[^A-Za-z0-9_-]/', '', $matches[1]), 0, 11);
            }
        }

        return '';
    }
}

/**
 * Converts Editor.js JSON data to Gutenberg block format.
 *
 * @param string $json_data The JSON string from Editor.js.
 * @return string The content formatted as Gutenberg blocks.
 */
function gta6_mods_editorjs_to_gutenberg_blocks($json_data) {
    $data = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['blocks']) || !is_array($data['blocks'])) {
        return '';
    }

    $gutenberg_content = '';
    foreach ($data['blocks'] as $block) {
        $type = isset($block['type']) ? $block['type'] : 'paragraph';
        $block_data = isset($block['data']) ? $block['data'] : [];
        $text = isset($block_data['text']) ? $block_data['text'] : '';

        switch ($type) {
            case 'header':
                $level = isset($block_data['level']) ? (int) $block_data['level'] : 2;
                $gutenberg_content .= sprintf(
                    '<!-- wp:heading {"level":%d} --><h%d>%s</h%d><!-- /wp:heading -->',
                    $level,
                    $level,
                    wp_kses_post($text),
                    $level
                );
                break;

            case 'paragraph':
                $gutenberg_content .= '<!-- wp:paragraph --><p>' . wp_kses_post($text) . '</p><!-- /wp:paragraph -->';
                break;

            case 'list':
                $style = isset($block_data['style']) && $block_data['style'] === 'ordered' ? 'ol' : 'ul';
                $items = isset($block_data['items']) && is_array($block_data['items']) ? $block_data['items'] : [];
                if (!empty($items)) {
                    $list_items = '';
                    foreach ($items as $item) {
                        $list_items .= '<li>' . wp_kses_post($item) . '</li>';
                    }
                    $attributes = ('ol' === $style) ? ' {"ordered":true}' : '';
                    $gutenberg_content .= sprintf(
                        '<!-- wp:list%s --><%s>%s</%s><!-- /wp:list -->',
                        $attributes,
                        $style,
                        $list_items,
                        $style
                    );
                }
                break;

            case 'quote':
                $caption = isset($block_data['caption']) ? $block_data['caption'] : '';
                $gutenberg_content .= '<!-- wp:quote --><blockquote class="wp-block-quote"><p>' . wp_kses_post($text) . '</p>';
                if ($caption) {
                    $gutenberg_content .= '<cite>' . wp_kses_post($caption) . '</cite>';
                }
                $gutenberg_content .= '</blockquote><!-- /wp:quote -->';
                break;

            case 'delimiter':
                $gutenberg_content .= '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
                break;
            
            case 'table':
                 $withHeadings = !empty($block_data['withHeadings']);
                 $content = isset($block_data['content']) && is_array($block_data['content']) ? $block_data['content'] : [];
                 
                 if (!empty($content)) {
                     $figure_attributes = '{"body":[]}';
                     if($withHeadings) {
                        $figure_attributes = '{"head":[{"cells":[]}],"body":[]}';
                     }
                     
                     $gutenberg_content .= '<!-- wp:table ' . $figure_attributes . ' --><figure class="wp-block-table"><table>';
                     
                     if ($withHeadings) {
                         $header_row = array_shift($content);
                         if ($header_row && is_array($header_row)) {
                             $gutenberg_content .= '<thead><tr>';
                             foreach ($header_row as $cell) {
                                 $gutenberg_content .= '<th>' . wp_kses_post($cell) . '</th>';
                             }
                             $gutenberg_content .= '</tr></thead>';
                         }
                     }
 
                     if(!empty($content)) {
                         $gutenberg_content .= '<tbody>';
                         foreach ($content as $row) {
                             if (!is_array($row)) continue;
                             $gutenberg_content .= '<tr>';
                             foreach ($row as $cell) {
                                 $gutenberg_content .= '<td>' . wp_kses_post($cell) . '</td>';
                             }
                             $gutenberg_content .= '</tr>';
                         }
                         $gutenberg_content .= '</tbody>';
                     }
 
                     $gutenberg_content .= '</table></figure><!-- /wp:table -->';
                 }
                 break;
            
            case 'embed':
                $service = isset($block_data['service']) ? $block_data['service'] : '';
                $source = isset($block_data['source']) ? esc_url($block_data['source']) : '';
                $caption = isset($block_data['caption']) ? $block_data['caption'] : '';

                if ('youtube' === $service && !empty($source)) {
                     $block_attributes = [
                        'url' => $source,
                        'type' => 'video',
                        'providerNameSlug' => 'youtube',
                        'responsive' => true,
                        'className' => 'wp-embed-aspect-16-9 wp-has-aspect-ratio'
                    ];

                    $gutenberg_content .= '<!-- wp:embed ' . wp_json_encode($block_attributes) . ' -->';
                    $gutenberg_content .= '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">';
                    $gutenberg_content .= "\n" . $source . "\n";
                    $gutenberg_content .= '</div>';
                    if ($caption) {
                        $gutenberg_content .= '<figcaption class="wp-element-caption">' . wp_kses_post($caption) . '</figcaption>';
                    }
                    $gutenberg_content .= '</figure><!-- /wp:embed -->';
                }
                break;

            case 'youtube':
                $video_id = '';
                if (!empty($block_data['videoId'])) {
                    $video_id = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $block_data['videoId']), 0, 11);
                }

                $original_url = !empty($block_data['originalUrl']) ? esc_url_raw($block_data['originalUrl']) : '';
                $canonical_url = !empty($block_data['url']) ? esc_url_raw($block_data['url']) : '';
                $embed_url = !empty($block_data['embedUrl']) ? esc_url_raw($block_data['embedUrl']) : '';
                $caption = isset($block_data['caption']) ? $block_data['caption'] : '';

                if (!$video_id && $original_url) {
                    $video_id = gta6_mods_extract_youtube_id($original_url);
                }
                if (!$video_id && $canonical_url) {
                    $video_id = gta6_mods_extract_youtube_id($canonical_url);
                }
                if (!$video_id && $embed_url) {
                    $video_id = gta6_mods_extract_youtube_id($embed_url);
                }

                if ($video_id && !$canonical_url) {
                    $canonical_url = sprintf('https://www.youtube.com/watch?v=%s', $video_id);
                }
                if ($video_id && !$embed_url) {
                    $embed_url = sprintf('https://www.youtube.com/embed/%s?rel=0', $video_id);
                }

                if ($video_id && $canonical_url && $embed_url) {
                    $block_attributes = [
                        'url' => esc_url_raw($canonical_url),
                        'type' => 'video',
                        'providerNameSlug' => 'youtube',
                        'responsive' => true,
                        'className' => 'wp-embed-aspect-16-9 wp-has-aspect-ratio',
                    ];

                    $gutenberg_content .= '<!-- wp:embed ' . wp_json_encode($block_attributes) . ' -->';
                    $gutenberg_content .= '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">';
                    $gutenberg_content .= "\n" . esc_url($canonical_url) . "\n";
                    $gutenberg_content .= '</div>';
                    if ($caption) {
                        $gutenberg_content .= '<figcaption class="wp-element-caption">' . wp_kses_post($caption) . '</figcaption>';
                    }
                    $gutenberg_content .= '</figure><!-- /wp:embed -->';
                } elseif ($original_url || $canonical_url) {
                    $fallback = esc_url($original_url ?: $canonical_url);
                    if ($fallback) {
                        $gutenberg_content .= '<!-- wp:paragraph --><p><a href="' . $fallback . '">' . $fallback . '</a></p><!-- /wp:paragraph -->';
                    }
                }
                break;

            case 'code':
                $code_content = isset($block_data['code']) ? $block_data['code'] : '';
                $gutenberg_content .= '<!-- wp:code --><pre class="wp-block-code"><code>' . esc_html($code_content) . '</code></pre><!-- /wp:code -->';
                break;
        }
        $gutenberg_content .= "\n\n";
    }

    return $gutenberg_content;
}


/**
 * Enqueue scripts and styles for Editor.js on the upload page.
 */
function gta6_mods_enqueue_editorjs_assets() {
    if (is_page_template('page-upload-mod.php') || is_page_template('page-update-mod.php')) {
        wp_enqueue_script('editorjs-core', 'https://cdn.jsdelivr.net/npm/@editorjs/editorjs@2.28.2', [], '2.28.2', true);
        wp_enqueue_script('editorjs-header', 'https://cdn.jsdelivr.net/npm/@editorjs/header@2.7.0', ['editorjs-core'], '2.7.0', true);
        wp_enqueue_script('editorjs-list', 'https://cdn.jsdelivr.net/npm/@editorjs/list@1.8.0', ['editorjs-core'], '1.8.0', true);
        wp_enqueue_script('editorjs-quote', 'https://cdn.jsdelivr.net/npm/@editorjs/quote@2.5.0', ['editorjs-core'], '2.5.0', true);
        wp_enqueue_script('editorjs-delimiter', 'https://cdn.jsdelivr.net/npm/@editorjs/delimiter@1.3.0', ['editorjs-core'], '1.3.0', true);
        wp_enqueue_script('editorjs-table', 'https://cdn.jsdelivr.net/npm/@editorjs/table@2.2.2', ['editorjs-core'], '2.2.2', true);
        wp_enqueue_script('editorjs-underline', 'https://cdn.jsdelivr.net/npm/@editorjs/underline@1.1.0', ['editorjs-core'], '1.1.0', true);
        wp_enqueue_script('editorjs-embed', 'https://cdn.jsdelivr.net/npm/@editorjs/embed@2.7.0', ['editorjs-core'], '2.7.0', true);
        wp_enqueue_script('editorjs-code', 'https://cdn.jsdelivr.net/npm/@editorjs/code@2.9.0', ['editorjs-core'], '2.9.0', true);
    }
}
add_action('wp_enqueue_scripts', 'gta6_mods_enqueue_editorjs_assets');

/**
 * Sanitize and normalize Editor.js payloads coming from the front-end.
 *
 * @param string $json Raw JSON payload.
 *
 * @return string Sanitized JSON string or empty string on failure.
 */
function gta6_mods_normalize_editorjs_json($json) {
    if (!is_string($json) || '' === trim($json)) {
        return '';
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded) || empty($decoded['blocks']) || !is_array($decoded['blocks'])) {
        return '';
    }

    $blocks = [];
    foreach ($decoded['blocks'] as $block) {
        if (!is_array($block) || empty($block['type'])) {
            continue;
        }

        $type = sanitize_key($block['type']);
        $data = isset($block['data']) && is_array($block['data']) ? $block['data'] : [];

        switch ($type) {
            case 'paragraph':
            case 'header':
            case 'list':
            case 'quote':
            case 'delimiter':
            case 'table':
            case 'underline':
            case 'embed':
            case 'code':
            case 'youtube':
                $blocks[] = [
                    'type' => $type,
                    'data' => $data,
                ];
                break;
            default:
                // Skip unsupported tools to avoid unexpected markup.
                break;
        }
    }

    if (empty($blocks)) {
        return '';
    }

    $normalized = [
        'time'   => isset($decoded['time']) ? (int) $decoded['time'] : time(),
        'version'=> isset($decoded['version']) ? sanitize_text_field($decoded['version']) : '2.28.2',
        'blocks' => $blocks,
    ];

    return wp_json_encode($normalized);
}

/**
 * Converts Gutenberg content back into an Editor.js compatible structure.
 *
 * @param string $content Post content containing Gutenberg blocks.
 *
 * @return string JSON encoded Editor.js data.
 */
function gta6_mods_convert_content_to_editorjs($content) {
    if (!is_string($content) || '' === trim($content)) {
        return wp_json_encode([
            'time'   => time(),
            'version'=> '2.28.2',
            'blocks' => [],
        ]);
    }

    $blocks = parse_blocks($content);
    $editor_blocks = [];

    if (empty($blocks)) {
        $sanitized = wp_kses_post($content);
        if ('' !== trim($sanitized)) {
            $editor_blocks[] = [
                'type' => 'paragraph',
                'data' => [ 'text' => $sanitized ],
            ];
        }

        return wp_json_encode([
            'time'   => time(),
            'version'=> '2.28.2',
            'blocks' => $editor_blocks,
        ]);
    }

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $block_name = isset($block['blockName']) ? $block['blockName'] : '';
        $attrs      = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];
        $inner_html = isset($block['innerHTML']) ? $block['innerHTML'] : '';
        $inner_html = is_string($inner_html) ? trim($inner_html) : '';

        switch ($block_name) {
            case 'core/paragraph':
                $editor_blocks[] = [
                    'type' => 'paragraph',
                    'data' => [ 'text' => wp_kses_post($inner_html) ],
                ];
                break;
            case 'core/heading':
                $level = isset($attrs['level']) ? (int) $attrs['level'] : 2;
                if ($level < 2 || $level > 4) {
                    $level = 2;
                }
                $editor_blocks[] = [
                    'type' => 'header',
                    'data' => [
                        'level' => $level,
                        'text'  => wp_kses_post($inner_html),
                    ],
                ];
                break;
            case 'core/list':
                $items = [];
                if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    foreach ($block['innerBlocks'] as $inner_block) {
                        if (!isset($inner_block['innerHTML'])) {
                            continue;
                        }
                        $items[] = wp_kses_post(trim($inner_block['innerHTML']));
                    }
                } else {
                    if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $inner_html, $matches)) {
                        foreach ($matches[1] as $item_html) {
                            $items[] = wp_kses_post(trim($item_html));
                        }
                    }
                }

                $style = (!empty($attrs['ordered']) && true === $attrs['ordered']) ? 'ordered' : 'unordered';

                if (!empty($items)) {
                    $editor_blocks[] = [
                        'type' => 'list',
                        'data' => [
                            'style' => $style,
                            'items' => array_values($items),
                        ],
                    ];
                }
                break;
            case 'core/quote':
                $cite = isset($attrs['cite']) ? sanitize_text_field($attrs['cite']) : '';
                $editor_blocks[] = [
                    'type' => 'quote',
                    'data' => [
                        'text'    => wp_kses_post($inner_html),
                        'caption' => $cite,
                    ],
                ];
                break;
            case 'core/code':
                $code = isset($block['innerContent'][0]) ? $block['innerContent'][0] : $inner_html;
                $editor_blocks[] = [
                    'type' => 'code',
                    'data' => [
                        'code' => html_entity_decode(wp_strip_all_tags($code)),
                    ],
                ];
                break;
            case 'core/embed':
                $url = isset($attrs['url']) ? esc_url_raw($attrs['url']) : '';
                if ($url && false !== strpos($url, 'youtu')) {
                    $editor_blocks[] = [
                        'type' => 'youtube',
                        'data' => [
                            'url'         => $url,
                            'originalUrl' => $url,
                        ],
                    ];
                } elseif ($url) {
                    $editor_blocks[] = [
                        'type' => 'embed',
                        'data' => [
                            'service' => 'generic',
                            'source'  => $url,
                            'embed'   => $url,
                        ],
                    ];
                }
                break;
            default:
                $text = wp_kses_post($inner_html);
                if ('' !== $text) {
                    $editor_blocks[] = [
                        'type' => 'paragraph',
                        'data' => [ 'text' => $text ],
                    ];
                }
                break;
        }
    }

    return wp_json_encode([
        'time'   => time(),
        'version'=> '2.28.2',
        'blocks' => $editor_blocks,
    ]);
}

/**
 * Retrieves the stored Editor.js payload for a post or converts it from content.
 *
 * @param int $post_id Post ID.
 *
 * @return string JSON encoded Editor.js data.
 */
function gta6_mods_get_editorjs_payload($post_id) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return wp_json_encode([
            'time'   => time(),
            'version'=> '2.28.2',
            'blocks' => [],
        ]);
    }

    $stored = get_post_meta($post_id, '_gta6mods_description_json', true);
    if (is_string($stored) && '' !== trim($stored)) {
        $normalized = gta6_mods_normalize_editorjs_json($stored);
        if ('' !== $normalized) {
            return $normalized;
        }
    }

    $content = get_post_field('post_content', $post_id);
    return gta6_mods_convert_content_to_editorjs($content);
}

/**
 * Stores Editor.js payload meta for a post.
 *
 * @param int    $post_id Post ID.
 * @param string $json    Editor.js JSON.
 */
function gta6_mods_store_editorjs_payload($post_id, $json) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    $normalized = gta6_mods_normalize_editorjs_json($json);
    if ('' === $normalized) {
        delete_post_meta($post_id, '_gta6mods_description_json');
        return;
    }

    update_post_meta($post_id, '_gta6mods_description_json', $normalized);
}

