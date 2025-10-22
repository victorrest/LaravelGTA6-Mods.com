<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gta6mods_generate_video_sitemap')) {
    /**
     * Generates the video sitemap XML payload.
     *
     * @return string
     */
    function gta6mods_generate_video_sitemap() {
        global $wpdb;

        $table_name = gta6mods_get_video_table_name();
        $post_types = gta6mods_get_sitemap_post_types();

        if (empty($post_types)) {
            $post_types = ['post'];
        }

        $placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
        $sql          = "SELECT
                v.youtube_id,
                v.youtube_url,
                v.video_title,
                v.video_description,
                v.duration,
                v.thumbnail_path,
                v.moderated_at,
                v.submitted_at,
                v.mod_id,
                p.post_title,
                p.post_excerpt,
                p.post_content
            FROM {$table_name} v
            INNER JOIN {$wpdb->posts} p ON v.mod_id = p.ID
            WHERE v.status = 'approved'
              AND p.post_status = 'publish'
              AND p.post_type IN ({$placeholders})
            ORDER BY v.moderated_at DESC, v.id DESC
            LIMIT 2000";

        $prepared = $wpdb->prepare($sql, $post_types);
        $rows     = $wpdb->get_results($prepared, ARRAY_A);

        if (empty($rows)) {
            return '';
        }

        $grouped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mod_id = isset($row['mod_id']) ? (int) $row['mod_id'] : 0;
            if ($mod_id <= 0) {
                continue;
            }

            if (!isset($grouped[$mod_id])) {
                $grouped[$mod_id] = [];
            }

            $grouped[$mod_id][] = $row;
        }

        if (empty($grouped)) {
            return '';
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        foreach ($grouped as $mod_id => $videos) {
            $permalink = get_permalink($mod_id);
            if (!$permalink) {
                continue;
            }

            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_xml(esc_url_raw($permalink)) . '</loc>' . "\n";

            foreach ($videos as $video) {
                if (!is_array($video) || empty($video['youtube_id'])) {
                    continue;
                }

                $video_title = !empty($video['video_title']) ? $video['video_title'] : get_the_title($mod_id);
                if (!is_string($video_title) || '' === $video_title) {
                    $video_title = __('Mod video', 'gta6-mods');
                }

                $description_source = '';
                if (!empty($video['video_description'])) {
                    $description_source = $video['video_description'];
                } elseif (!empty($video['post_excerpt'])) {
                    $description_source = $video['post_excerpt'];
                } elseif (!empty($video['post_content'])) {
                    $description_source = $video['post_content'];
                }

                $video_description = wp_trim_words(wp_strip_all_tags($description_source), 50, '');
                if ('' === $video_description) {
                    $video_description = __('Gameplay footage for this mod.', 'gta6-mods');
                }

                $thumbnail = '';
                if (!empty($video['thumbnail_path'])) {
                    $thumbnail = home_url($video['thumbnail_path']);
                } else {
                    $thumbnail = sprintf('https://i.ytimg.com/vi/%s/hqdefault.jpg', rawurlencode($video['youtube_id']));
                }

                $duration = !empty($video['duration']) && preg_match('/^PT(\d+H)?(\d+M)?(\d+S)?$/', $video['duration'])
                    ? $video['duration']
                    : 'PT0M0S';

                $upload_source = !empty($video['moderated_at']) ? $video['moderated_at'] : $video['submitted_at'];
                $upload_time   = $upload_source ? strtotime($upload_source) : false;
                $upload_date   = $upload_time ? gmdate('c', $upload_time) : gmdate('c');

                $player_url  = sprintf('https://www.youtube.com/embed/%s', rawurlencode($video['youtube_id']));
                $content_url = !empty($video['youtube_url'])
                    ? $video['youtube_url']
                    : sprintf('https://www.youtube.com/watch?v=%s', rawurlencode($video['youtube_id']));

                $xml .= '    <video:video>' . "\n";
                $xml .= '      <video:thumbnail_loc>' . esc_xml(esc_url_raw($thumbnail)) . '</video:thumbnail_loc>' . "\n";
                $xml .= '      <video:title>' . esc_xml($video_title) . '</video:title>' . "\n";
                $xml .= '      <video:description>' . esc_xml($video_description) . '</video:description>' . "\n";
                $xml .= '      <video:player_loc allow_embed="yes">' . esc_xml(esc_url_raw($player_url)) . '</video:player_loc>' . "\n";
                $xml .= '      <video:content_loc>' . esc_xml(esc_url_raw($content_url)) . '</video:content_loc>' . "\n";
                $xml .= '      <video:duration>' . esc_xml($duration) . '</video:duration>' . "\n";
                $xml .= '      <video:publication_date>' . esc_xml($upload_date) . '</video:publication_date>' . "\n";
                $xml .= '    </video:video>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
