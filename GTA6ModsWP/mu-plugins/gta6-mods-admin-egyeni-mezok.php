<?php
/**
 * Plugin Name: GTA6 Mods - Admin Custom Fields Editor
 * Description: Szerkeszthető egyéni mezők superadminoknak
 * Version: 1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue scripts and styles for the custom fields editor.
 */
function gta6_mods_admin_custom_fields_scripts($hook) {
    // Only load on post.php and post-new.php
    if ('post.php' !== $hook && 'post-new.php' !== $hook) {
        return;
    }
    // Check if we are on a 'post' or 'mod_update' type edit screen
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['post', 'mod_update'], true)) {
        return;
    }
    wp_enqueue_style(
        'gta6-mods-admin-fa',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
        [],
        '6.5.1'
    );
}
add_action('admin_enqueue_scripts', 'gta6_mods_admin_custom_fields_scripts');


// Meta box hozzáadása
function gta6_mods_admin_meta_box() {
    if (!is_super_admin()) {
        return;
    }

    add_meta_box(
        'gta6_mods_editable_fields',
        '🔧 Mod Egyéni Mezők (Szerkeszthető)',
        'gta6_mods_render_editable_meta_box',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'gta6_mods_admin_meta_box');

function gta6_mods_render_editable_meta_box($post) {
    if (!is_super_admin()) {
        echo '<p>' . esc_html__('Nincs jogosultságod ehhez a művelethez.', 'gta6-mods') . '</p>';
        return;
    }

    if (!current_user_can('edit_post', $post->ID)) {
        echo '<p>' . esc_html__('Nincs jogosultságod a bejegyzés szerkesztéséhez.', 'gta6-mods') . '</p>';
        return;
    }

    wp_enqueue_media();
    wp_nonce_field('gta6_mods_save_meta', 'gta6_mods_meta_nonce');

    $additional_authors = get_post_meta($post->ID, '_gta6mods_additional_authors', true);
    $mod_file = get_post_meta($post->ID, '_gta6mods_mod_file', true);
    $mod_external = get_post_meta($post->ID, '_gta6mods_mod_external', true);
    $video_permissions = get_post_meta($post->ID, '_gta6mods_video_permissions', true);

    if (function_exists('gta6_mods_get_gallery_images')) {
        $gallery_images = gta6_mods_get_gallery_images($post->ID);
    } else {
        $gallery_images = [];
        $raw_gallery = get_post_meta($post->ID, '_gta6mods_gallery_images', true);
        if (is_string($raw_gallery) && $raw_gallery !== '') {
            $decoded_gallery = json_decode($raw_gallery, true);
            if (is_array($decoded_gallery)) {
                foreach ($decoded_gallery as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $attachment_id = isset($item['attachment_id']) ? absint($item['attachment_id']) : 0;
                    if ($attachment_id <= 0) {
                        continue;
                    }
                    $attachment_post = get_post($attachment_id);
                    if (!$attachment_post instanceof WP_Post || 'attachment' !== $attachment_post->post_type) {
                        continue;
                    }
                    $gallery_images[] = [
                        'attachment_id' => $attachment_id,
                        'order'         => isset($item['order']) ? (int) $item['order'] : 0,
                        'url'           => wp_get_attachment_url($attachment_id) ?: '',
                    ];
                }
            }
        }
    }
    
    if (!empty($gallery_images)) {
        usort(
            $gallery_images,
            static function ($a, $b) {
                $order_a = isset($a['order']) ? (int) $a['order'] : 0;
                $order_b = isset($b['order']) ? (int) $b['order'] : 0;

                return $order_a <=> $order_b;
            }
        );
    }

    $inactive_gallery_items = [];
    if (function_exists('gta6mods_get_removed_gallery_ids')) {
        $removed_ids = gta6mods_get_removed_gallery_ids($post->ID);
        if (!empty($removed_ids)) {
            foreach ($removed_ids as $removed_id) {
                $removed_id = absint($removed_id);
                if ($removed_id <= 0) {
                    continue;
                }

                $attachment_post = get_post($removed_id);
                if (!$attachment_post instanceof WP_Post || 'attachment' !== $attachment_post->post_type) {
                    continue;
                }

                $inactive_gallery_items[] = [
                    'id' => $removed_id,
                ];
            }
        }
    }

    $featured_attachment_id = (int) get_post_thumbnail_id($post->ID);

    if (function_exists('gta6mods_ensure_initial_version_exists')) {
        gta6mods_ensure_initial_version_exists($post->ID);
    }

    $current_version_id = (int) get_post_meta($post->ID, '_gta6mods_current_version_id', true);
    $version_cards      = [];

    if (function_exists('gta6mods_get_version_posts')) {
        $version_posts = gta6mods_get_version_posts($post->ID);

        if (!empty($version_posts)) {
            foreach ($version_posts as $version_post) {
                if (!$version_post instanceof WP_Post) {
                    continue;
                }

                $version_id   = (int) $version_post->ID;
                $number       = get_post_meta($version_id, '_gta6mods_version_number', true);
                $number       = is_string($number) ? trim($number) : '';
                $downloads    = (int) get_post_meta($version_id, '_gta6mods_version_download_count', true);
                $scan_url_raw = get_post_meta($version_id, '_gta6mods_version_scan_url', true);
                $scan_url     = is_string($scan_url_raw) ? trim($scan_url_raw) : '';

                $changelog_meta = get_post_meta($version_id, '_gta6mods_version_changelog', true);
                $changelog_meta = is_array($changelog_meta) ? $changelog_meta : [];
                $changelog_lines = [];

                foreach ($changelog_meta as $entry) {
                    if (!is_string($entry)) {
                        continue;
                    }

                    $entry = trim($entry);

                    if ('' !== $entry) {
                        $changelog_lines[] = $entry;
                    }
                }

                $changelog_text = implode("\n", $changelog_lines);

                $source      = get_post_meta($version_id, '_gta6mods_version_source', true);
                $source      = is_array($source) ? $source : [];
                $source_type = isset($source['type']) ? sanitize_key($source['type']) : '';

                $attachment_id = isset($source['attachment_id']) ? (int) $source['attachment_id'] : 0;
                $external_url  = isset($source['url']) ? esc_url_raw($source['url']) : '';
                $size_bytes    = isset($source['size_bytes']) ? (int) $source['size_bytes'] : 0;
                $size_human    = isset($source['size_human']) ? sanitize_text_field($source['size_human']) : '';
                $download_url  = '';

                if ('file' === $source_type && $attachment_id > 0) {
                    $download_url = wp_get_attachment_url($attachment_id) ?: '';
                    if ('' === $size_human && $size_bytes > 0) {
                        $size_human = size_format((float) $size_bytes);
                    }
                } elseif ('external' === $source_type && $external_url) {
                    $download_url = esc_url_raw($external_url);
                    if ('' === $size_human && $size_bytes > 0) {
                        $size_human = size_format((float) $size_bytes);
                    }
                } elseif ($attachment_id > 0) {
                    $source_type  = 'file';
                    $download_url = wp_get_attachment_url($attachment_id) ?: '';
                } elseif ($external_url) {
                    $source_type  = 'external';
                    $download_url = esc_url_raw($external_url);
                }

                if (!in_array($source_type, ['file', 'external'], true)) {
                    $source_type = $attachment_id > 0 ? 'file' : ($external_url ? 'external' : 'file');
                }

                $release_date = get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $version_id);
                $is_initial   = (bool) get_post_meta($version_id, '_gta6mods_version_is_initial', true);

                $form_index = count($version_cards);

                $version_cards[] = [
                    'form_index'    => $form_index,
                    'id'             => $version_id,
                    'number'         => $number,
                    'downloads'      => $downloads,
                    'changelog_text' => $changelog_text,
                    'source_type'    => $source_type,
                    'attachment_id'  => $attachment_id,
                    'external_url'   => 'external' === $source_type ? $download_url : '',
                    'download_url'   => $download_url,
                    'size_bytes'     => $size_bytes,
                    'size_human'     => $size_human,
                    'release_date'   => $release_date,
                    'is_initial'     => $is_initial,
                    'is_current'     => $version_id === $current_version_id,
                    'scan_url'       => $scan_url,
                ];
            }
        }
    }

    $authors_list = [];
    if (is_array($additional_authors)) {
        foreach ($additional_authors as $author_name) {
            if (!is_string($author_name)) {
                continue;
            }

            $author_name = trim($author_name);
            $authors_list[] = $author_name;
        }
    }

    if (empty($authors_list)) {
        $authors_list = [''];
    }

    $current_version_card = null;
    $archive_versions     = [];

    if (!empty($version_cards)) {
        foreach ($version_cards as $idx => $version_card) {
            if (!isset($version_card['form_index'])) {
                $version_card['form_index'] = $idx;
            }

            if (!empty($version_card['is_current']) && null === $current_version_card) {
                $current_version_card = $version_card;
            } else {
                $archive_versions[] = $version_card;
            }
        }
    }

    if (null === $current_version_card && !empty($archive_versions)) {
        $current_version_card = array_shift($archive_versions);
    }

    $render_version_inner_card = static function (array $version_card) {
        $form_index    = isset($version_card['form_index']) ? (int) $version_card['form_index'] : 0;
        $version_id    = isset($version_card['id']) ? (int) $version_card['id'] : 0;
        $version_label = isset($version_card['number']) && '' !== $version_card['number']
            ? $version_card['number']
            : __('—', 'gta6-mods');
        $downloads     = isset($version_card['downloads']) ? (int) $version_card['downloads'] : 0;
        $downloads_txt = number_format_i18n($downloads);
        $release_date  = isset($version_card['release_date']) ? $version_card['release_date'] : '';
        $is_initial    = !empty($version_card['is_initial']);
        $is_current    = !empty($version_card['is_current']);
        $scan_url      = isset($version_card['scan_url']) ? $version_card['scan_url'] : '';
        $source_type   = isset($version_card['source_type']) && in_array($version_card['source_type'], ['file', 'external'], true)
            ? $version_card['source_type']
            : 'file';
        $attachment_id = isset($version_card['attachment_id']) ? (int) $version_card['attachment_id'] : 0;
        $download_url  = isset($version_card['download_url']) ? $version_card['download_url'] : '';
        $external_url  = isset($version_card['external_url']) ? $version_card['external_url'] : '';
        $size_bytes    = isset($version_card['size_bytes']) ? (int) $version_card['size_bytes'] : 0;
        $size_human    = isset($version_card['size_human']) ? $version_card['size_human'] : '';
        $changelog     = isset($version_card['changelog_text']) ? $version_card['changelog_text'] : '';
        $delete_confirm = sprintf(
            __('Biztosan törlöd a(z) „%s” verziót? A bejegyzés mentésekor a verzió a lomtárba kerül.', 'gta6-mods'),
            $version_label
        );
        $delete_label = __('Verzió törlése', 'gta6-mods');
        $undo_label   = __('Törlés visszavonása', 'gta6-mods');
        $delete_notice = __('Ez a verzió törlésre kerül a bejegyzés mentésekor.', 'gta6-mods');

        ob_start();
        ?>
        <input type="hidden" name="gta6_version[<?php echo esc_attr($form_index); ?>][id]" value="<?php echo esc_attr($version_id); ?>">
        <input type="hidden" name="gta6_version[<?php echo esc_attr($form_index); ?>][delete]" value="0" data-gta6-version-delete-flag="0">
        <div class="gta6-version-card-header">
            <div class="gta6-version-header-main">
                <h4><i class="fa-solid fa-code-branch"></i><?php esc_html_e('Verzió:', 'gta6-mods'); ?> <strong data-gta6-version-title><?php echo esc_html($version_label); ?></strong></h4>
                <div class="gta6-version-meta">
                    <span><?php echo esc_html(sprintf(__('ID: #%d', 'gta6-mods'), $version_id)); ?></span>
                    <?php if ($release_date) : ?>
                        <span><i class="fa-solid fa-calendar-days"></i><?php echo esc_html($release_date); ?></span>
                    <?php endif; ?>
                    <span><i class="fa-solid fa-download"></i><?php echo esc_html($downloads_txt); ?></span>
                    <?php if ($is_initial) : ?>
                        <span class="gta6-version-badge gta6-version-badge--initial"><?php esc_html_e('Kezdeti kiadás', 'gta6-mods'); ?></span>
                    <?php endif; ?>
                    <?php if ($is_current) : ?>
                        <span class="gta6-version-badge gta6-version-badge--current"><?php esc_html_e('Aktuális', 'gta6-mods'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$is_current) : ?>
                <div class="gta6-version-actions">
                    <button
                        type="button"
                        class="button button-link-delete"
                        data-gta6-version-delete
                        data-confirm="<?php echo esc_attr($delete_confirm); ?>"
                        data-delete-label="<?php echo esc_attr($delete_label); ?>"
                        data-undo-label="<?php echo esc_attr($undo_label); ?>"
                        aria-label="<?php echo esc_attr($delete_label); ?>"
                    >
                        <i class="fa-solid fa-trash-can"></i>
                        <span><?php echo esc_html($delete_label); ?></span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="gta6-version-delete-notice" data-gta6-version-delete-notice hidden>
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?php echo esc_html($delete_notice); ?></span>
        </div>
        <div class="gta6-version-card-body">
            <div class="gta6-form-grid">
                <div class="gta6-form-field">
                    <label class="form-label" for="gta6-version-number-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Verziószám', 'gta6-mods'); ?></label>
                    <input class="form-input" type="text" id="gta6-version-number-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][number]" value="<?php echo esc_attr($version_card['number']); ?>" placeholder="1.0.0">
                </div>
                <div class="gta6-form-field">
                    <label class="form-label" for="gta6-version-downloads-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Letöltések száma', 'gta6-mods'); ?></label>
                    <input class="form-input" type="number" min="0" step="1" id="gta6-version-downloads-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][downloads]" value="<?php echo esc_attr($downloads); ?>">
                </div>
                <div class="gta6-form-field gta6-form-field--align-end">
                    <span class="form-label"><?php esc_html_e('Aktuális verzió', 'gta6-mods'); ?></span>
                    <label class="gta6-radio-label">
                        <input type="radio" name="gta6_current_version" value="<?php echo esc_attr($version_id); ?>" <?php checked($is_current); ?>>
                        <span><?php esc_html_e('Állítsd be aktuális verziónak', 'gta6-mods'); ?></span>
                    </label>
                </div>
                <div class="gta6-form-field gta6-form-field--full">
                    <label class="form-label" for="gta6-version-changelog-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Changelog (soronként egy elem)', 'gta6-mods'); ?></label>
                    <textarea class="form-textarea" id="gta6-version-changelog-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][changelog]" rows="3" placeholder="<?php esc_attr_e('Új funkció...', 'gta6-mods'); ?>"><?php echo esc_textarea($changelog); ?></textarea>
                </div>
            </div>

            <div class="gta6-version-source-wrapper">
                <label class="form-label"><?php esc_html_e('Fájl forrása', 'gta6-mods'); ?></label>
                <div class="gta6-source-toggle">
                    <?php $file_toggle_id = 'gta6-version-' . $version_id . '-source-file'; ?>
                    <?php $external_toggle_id = 'gta6-version-' . $version_id . '-source-external'; ?>
                    <input type="radio" id="<?php echo esc_attr($file_toggle_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][source_type]" value="file" data-gta6-version-source-toggle <?php checked('file' === $source_type); ?>>
                    <label for="<?php echo esc_attr($file_toggle_id); ?>"><i class="fa-solid fa-upload"></i><?php esc_html_e('Feltöltött fájl', 'gta6-mods'); ?></label>
                    <input type="radio" id="<?php echo esc_attr($external_toggle_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][source_type]" value="external" data-gta6-version-source-toggle <?php checked('external' === $source_type); ?>>
                    <label for="<?php echo esc_attr($external_toggle_id); ?>"><i class="fa-solid fa-link"></i><?php esc_html_e('Külső letöltési link', 'gta6-mods'); ?></label>
                </div>

                <div class="gta6-source-panel <?php echo 'file' === $source_type ? 'is-active' : ''; ?>" data-gta6-version-source="file">
                    <div class="gta6-form-grid">
                        <div class="gta6-form-field">
                            <label class="form-label" for="gta6-version-file-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Attachment ID', 'gta6-mods'); ?></label>
                            <input class="form-input" type="number" min="0" id="gta6-version-file-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][file_attachment_id]" value="<?php echo esc_attr($attachment_id); ?>" placeholder="<?php esc_attr_e('Attachment ID', 'gta6-mods'); ?>">
                        </div>
                        <div class="gta6-form-field">
                            <label class="form-label" for="gta6-version-file-human-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Méret (szövegesen)', 'gta6-mods'); ?></label>
                            <input class="form-input" type="text" id="gta6-version-file-human-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][file_size_human]" value="<?php echo esc_attr($size_human); ?>" placeholder="<?php esc_attr_e('pl. 850 MB', 'gta6-mods'); ?>">
                        </div>
                        <div class="gta6-form-field">
                            <label class="form-label" for="gta6-version-file-bytes-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Méret (bájt)', 'gta6-mods'); ?></label>
                            <input class="form-input" type="number" min="0" step="1" id="gta6-version-file-bytes-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][file_size_bytes]" value="<?php echo esc_attr($size_bytes); ?>">
                        </div>
                    </div>
                    <?php if ($download_url) : ?>
                        <p class="gta6-version-note">
                            <strong><?php esc_html_e('Aktuális fájl:', 'gta6-mods'); ?></strong>
                            <a href="<?php echo esc_url($download_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(basename($download_url)); ?></a>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="gta6-source-panel <?php echo 'external' === $source_type ? 'is-active' : ''; ?>" data-gta6-version-source="external">
                    <label class="form-label" for="gta6-version-external-url-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Letöltési URL', 'gta6-mods'); ?></label>
                    <input class="form-input" type="url" id="gta6-version-external-url-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][external_url]" value="<?php echo esc_attr($external_url); ?>" placeholder="https://example.com/...">
                    <?php if ($external_url) : ?>
                        <p class="gta6-version-note gta6-version-note--link">
                            <strong><?php esc_html_e('Aktuális link:', 'gta6-mods'); ?></strong>
                            <a href="<?php echo esc_url($external_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($external_url); ?></a>
                        </p>
                    <?php endif; ?>
                    <div class="gta6-form-grid">
                        <div class="gta6-form-field">
                            <label class="form-label" for="gta6-version-external-human-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Méret (szövegesen)', 'gta6-mods'); ?></label>
                            <input class="form-input" type="text" id="gta6-version-external-human-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][external_size_human]" value="<?php echo esc_attr($size_human); ?>" placeholder="<?php esc_attr_e('pl. 1.2 GB', 'gta6-mods'); ?>">
                        </div>
                        <div class="gta6-form-field">
                            <label class="form-label" for="gta6-version-external-bytes-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Méret (bájt)', 'gta6-mods'); ?></label>
                            <input class="form-input" type="number" min="0" step="1" id="gta6-version-external-bytes-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][external_size_bytes]" value="<?php echo esc_attr($size_bytes); ?>">
                        </div>
                    </div>
                </div>

                <div class="gta6-form-field gta6-form-field--full">
                    <label class="form-label" for="gta6-version-scan-<?php echo esc_attr($version_id); ?>"><?php esc_html_e('Vírusellenőrzés URL', 'gta6-mods'); ?></label>
                    <input class="form-input" type="url" id="gta6-version-scan-<?php echo esc_attr($version_id); ?>" name="gta6_version[<?php echo esc_attr($form_index); ?>][scan_url]" value="<?php echo esc_attr($scan_url); ?>" placeholder="https://www.virustotal.com/...">
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    };

    ?>
    <style>
        :root {
            --wp-admin-gray-50: #f8f9fa;
            --wp-admin-gray-100: #f0f0f1;
            --wp-admin-gray-200: #e0e0e0;
            --wp-admin-gray-300: #c3c4c7;
            --wp-admin-gray-500: #8c8f94;
            --wp-admin-gray-600: #6b7280;
            --wp-admin-gray-700: #50575e;
            --wp-admin-gray-900: #1d2327;
            --wp-admin-blue: #2271b1;
            --wp-admin-green: #00a32a;
            --wp-admin-yellow: #f59e0b;
            --wp-admin-red: #d63638;
            --border-radius: 6px;
        }

        .gta6-admin-wrapper {
            border: 1px solid var(--wp-admin-gray-300);
            border-radius: var(--border-radius);
            background: #fff;
            overflow: hidden;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
            font-size: 14px;
        }

        .gta6-tabs-nav {
            display: flex;
            border-bottom: 1px solid var(--wp-admin-gray-300);
            background: var(--wp-admin-gray-50);
            padding: 0 16px;
            gap: 8px;
            flex-wrap: wrap;
        }

        .gta6-tab-button {
            padding: 12px 16px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: var(--wp-admin-gray-700);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s ease, border-color 0.2s ease;
        }

        .gta6-tab-button:hover {
            color: var(--wp-admin-gray-900);
        }

        .gta6-tab-button.active {
            color: var(--wp-admin-blue);
            border-bottom-color: var(--wp-admin-blue);
        }

        .gta6-tab-panel {
            display: none;
            padding: 24px;
            background: #fff;
        }

        .gta6-tab-panel.active {
            display: block;
        }

        .gta6-card {
            border: 1px solid var(--wp-admin-gray-200);
            border-radius: var(--border-radius);
            background: #fff;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .gta6-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 600;
            background: var(--wp-admin-gray-50);
            border-bottom: 1px solid var(--wp-admin-gray-200);
            margin: 0;
        }

        .gta6-card-header i {
            color: var(--wp-admin-gray-500);
        }

        .gta6-card-body {
            padding: 20px;
        }

        .gta6-form-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .gta6-form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .gta6-form-field--full {
            grid-column: 1 / -1;
        }

        .gta6-form-field--align-end {
            justify-content: flex-end;
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: var(--wp-admin-gray-900);
        }

        .form-label--compact {
            font-size: 12px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            border: 1px solid var(--wp-admin-gray-300);
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
            transition: border-color 0.1s ease, box-shadow 0.1s ease;
        }

        .form-input--compact {
            padding: 6px 8px;
            font-size: 13px;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--wp-admin-blue);
            box-shadow: 0 0 0 1px var(--wp-admin-blue);
            outline: 2px solid transparent;
        }

        .form-textarea {
            min-height: 96px;
            resize: vertical;
        }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 3px;
            border: 1px solid var(--wp-admin-gray-300);
            background: #f6f7f7;
            color: var(--wp-admin-gray-900);
            padding: 0 14px;
            min-height: 34px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        .button:hover {
            background: #f0f0f1;
            border-color: var(--wp-admin-gray-500);
        }

        .button-primary {
            background: var(--wp-admin-blue);
            border-color: var(--wp-admin-blue);
            color: #fff;
        }

        .button-primary:hover {
            background: #1e66a0;
            border-color: #1e66a0;
        }

        .button-secondary {
            background: var(--wp-admin-gray-50);
        }

        .button-large {
            font-size: 14px;
            padding: 0 20px;
            min-height: 38px;
        }

        .button-link-delete {
            background: none;
            border: none;
            color: var(--wp-admin-red);
            cursor: pointer;
            padding: 6px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .button-link-delete:hover {
            background: #ffe5e5;
            color: #a60001;
        }

        .gta6-version-card {
            border: 1px solid var(--wp-admin-gray-200);
            border-radius: var(--border-radius);
            background: #fff;
            margin-bottom: 24px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
        }

        .gta6-version-card--current {
            border-color: var(--wp-admin-green);
        }

        .gta6-version-card--current .gta6-version-card-header {
            background: #f1f8f2;
            border-bottom-color: #d4e8d5;
        }

        .gta6-version-card--deleted {
            border-color: rgba(214, 54, 56, 0.6);
            border-style: dashed;
        }

        .gta6-version-card--deleted .gta6-version-card-header {
            background: #fff4f4;
            border-bottom-color: rgba(214, 54, 56, 0.3);
        }

        .gta6-version-card--deleted .gta6-version-card-body {
            opacity: 0.85;
        }

        .gta6-version-card-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 16px 20px;
            border-bottom: 1px solid var(--wp-admin-gray-200);
        }

        .gta6-version-header-main {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 220px;
        }

        .gta6-version-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }

        .gta6-version-card-header h4 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            font-size: 15px;
        }

        .gta6-version-card-header i {
            color: var(--wp-admin-gray-500);
        }

        .gta6-version-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .gta6-version-delete-notice {
            display: none;
            margin: 0 20px 12px;
            padding: 10px 14px;
            border-radius: 6px;
            background: #fff4f4;
            border: 1px solid rgba(214, 54, 56, 0.4);
            color: var(--wp-admin-red);
            font-weight: 600;
            align-items: center;
            gap: 8px;
        }

        .gta6-version-delete-notice i {
            color: var(--wp-admin-red);
        }

        .gta6-version-delete-notice:not([hidden]) {
            display: inline-flex;
        }

        .gta6-version-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 12px;
            color: var(--wp-admin-gray-700);
        }

        .gta6-version-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--wp-admin-gray-100);
            border-radius: 999px;
            padding: 4px 10px;
            font-weight: 600;
        }

        .gta6-version-badge.gta6-version-badge--initial {
            color: #fff;
            background-color: black;
        }

        .gta6-version-badge.gta6-version-badge--current {
            color: #fff;
            background-color: #48c24d;
        }

        .gta6-version-meta i {
            color: var(--wp-admin-gray-500);
        }

        .gta6-version-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .gta6-version-badge--current {
            background: var(--wp-admin-green);
            color: #fff;
        }

        .gta6-version-badge--initial {
            background: var(--wp-admin-gray-700);
            color: #fff;
        }

        .gta6-radio-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--wp-admin-gray-700);
        }

        .gta6-version-source-wrapper {
            border: 1px dashed var(--wp-admin-gray-200);
            border-radius: var(--border-radius);
            padding: 16px;
            background: var(--wp-admin-gray-50);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .gta6-source-toggle {
            display: inline-flex;
            gap: 8px;
            padding: 4px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--wp-admin-gray-200);
        }

        .gta6-source-toggle input[type="radio"] {
            display: none;
        }

        .gta6-source-toggle label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 13px;
            border-radius: 999px;
            cursor: pointer;
            color: var(--wp-admin-gray-700);
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        .gta6-source-toggle input[type="radio"]:checked + label {
            background: var(--wp-admin-blue);
            color: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .gta6-source-panel {
            display: none;
        }

        .gta6-source-panel.is-active {
            display: block;
        }

        .gta6-version-note {
            margin: 0;
            font-size: 13px;
            background: #eef2ff;
            border-radius: 6px;
            padding: 10px 12px;
            border: 1px solid #c7d2fe;
        }

        .gta6-version-note--link a {
            word-break: break-all;
            color: var(--wp-admin-blue);
            text-decoration: underline;
        }

        .gta6-note-meta {
            display: inline-block;
            margin-left: 8px;
            color: var(--wp-admin-gray-700);
            font-size: 12px;
        }

        .gta6-general-grid {
            display: grid;
            gap: 24px;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .gta6-general-column {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .gta6-authors-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .gta6-author-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .gta6-help-text {
            color: var(--wp-admin-gray-700);
            font-size: 13px;
            margin-bottom: 16px;
        }

        .gta6-gallery-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        }

        .gta6-gallery-item {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--wp-admin-gray-200);
            border-radius: var(--border-radius);
            padding: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
            transition: box-shadow 0.2s ease;
        }

        .gta6-gallery-item:hover {
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }

        .gta6-gallery-item-featured {
            border-color: var(--wp-admin-yellow);
            background: #fffbeb;
        }

        .gta6-drag-handle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid transparent;
            background: var(--wp-admin-gray-100);
            color: var(--wp-admin-gray-600);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: move;
        }

        .gta6-gallery-preview {
            width: 64px;
            height: 64px;
            border-radius: 4px;
            object-fit: cover;
            background: var(--wp-admin-gray-100);
            border: 1px solid var(--wp-admin-gray-200);
        }

        .gta6-gallery-preview-hidden {
            display: none;
        }

        .gta6-gallery-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .gta6-gallery-actions {
            display: inline-flex;
            flex-direction: column;
            gap: 6px;
        }

        .gta6-gallery-actions button {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid transparent;
            background: var(--wp-admin-gray-100);
            color: var(--wp-admin-gray-600);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .gta6-gallery-actions button:hover {
            background: #fff;
            border-color: var(--wp-admin-gray-300);
            color: var(--wp-admin-gray-900);
        }

        .gta6-feature-btn.featured {
            color: var(--wp-admin-yellow);
            border-color: rgba(245, 158, 11, 0.4);
            background: #fffbeb;
        }

        .gta6-delete-btn:hover {
            color: var(--wp-admin-red);
            border-color: rgba(214, 54, 56, 0.4);
            background: #fff5f5;
        }

        .gta6-gallery-item-dragging {
            opacity: 0.6;
        }

        .gta6-gallery-inactive-wrapper {
            margin-top: 24px;
            border-top: 1px solid var(--wp-admin-gray-200);
            padding-top: 16px;
        }

        .gta6-gallery-inactive-wrapper h4 {
            margin: 0 0 8px;
            font-size: 14px;
        }

        .gta6-gallery-inactive-wrapper p {
            margin: 0 0 12px;
            font-size: 12px;
            color: var(--wp-admin-gray-700);
        }

        .gta6-gallery-inactive-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .gta6-gallery-inactive-item {
            border: 1px dashed #fca5a5;
            background: #fff1f2;
            padding: 10px;
            border-radius: 8px;
            min-width: 120px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: #b91c1c;
        }

        .gta6-empty-state {
            border: 1px dashed var(--wp-admin-gray-300);
            border-radius: var(--border-radius);
            padding: 20px;
            background: #fafafa;
            color: var(--wp-admin-gray-700);
            margin-bottom: 24px;
        }

        .gta6-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            margin: 0 0 16px;
            color: var(--wp-admin-gray-800);
        }

        .gta6-section-title i {
            color: var(--wp-admin-gray-500);
        }

        .gta6-archive-version {
            border: 1px solid var(--wp-admin-gray-200);
            border-radius: var(--border-radius);
            overflow: hidden;
            background: #fff;
            margin-bottom: 16px;
        }

        .gta6-archive-version summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 20px;
            background: var(--wp-admin-gray-50);
            cursor: pointer;
            list-style: none;
        }

        .gta6-archive-version summary::-webkit-details-marker {
            display: none;
        }

        .gta6-archive-version summary:hover {
            background: var(--wp-admin-gray-100);
        }

        .gta6-archive-summary {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }

        .gta6-archive-summary h4 {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            font-size: 15px;
        }

        .gta6-archive-summary i {
            color: var(--wp-admin-gray-500);
        }

        .gta6-archive-version summary::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free', 'Font Awesome 6 Solid';
            font-weight: 900;
            color: var(--wp-admin-gray-500);
            transition: transform 0.2s ease;
        }

        .gta6-archive-version[open] > summary::after {
            transform: rotate(180deg);
        }

        .gta6-archive-version[open] .gta6-version-card {
            border-top: 1px solid var(--wp-admin-gray-200);
        }

        @media (max-width: 640px) {
            .gta6-tabs-nav {
                padding: 0 8px;
            }

            .gta6-tab-panel {
                padding: 20px 16px;
            }

            .gta6-card-body {
                padding: 16px;
            }

            .gta6-gallery-grid {
                grid-template-columns: 1fr;
            }

            .gta6-gallery-actions {
                flex-direction: row;
            }
        }
    </style>

    <div class="gta6-admin-wrapper">
        <div class="gta6-tabs-nav">
            <button type="button" class="gta6-tab-button active" data-tab="gta6-tab-versions"><i class="fa-solid fa-folder-open"></i><span><?php esc_html_e('Verziók', 'gta6-mods'); ?></span></button>
            <button type="button" class="gta6-tab-button" data-tab="gta6-tab-general"><i class="fa-solid fa-sliders"></i><span><?php esc_html_e('Általános', 'gta6-mods'); ?></span></button>
            <button type="button" class="gta6-tab-button" data-tab="gta6-tab-gallery"><i class="fa-solid fa-images"></i><span><?php esc_html_e('Galéria', 'gta6-mods'); ?></span></button>
        </div>

        <div class="gta6-tabs-content">
            <div id="gta6-tab-versions" class="gta6-tab-panel active">
                <?php if ($current_version_card) : ?>
                    <div class="gta6-version-card gta6-version-card--current" data-gta6-version-card>
                        <?php echo $render_version_inner_card($current_version_card); ?>
                    </div>
                <?php else : ?>
                    <div class="gta6-empty-state">
                        <p><?php esc_html_e('Ehhez a modhoz még nem tartozik verzióbejegyzés.', 'gta6-mods'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($archive_versions)) : ?>
                    <div class="gta6-archive-wrapper">
                        <h3 class="gta6-section-title"><i class="fa-solid fa-clock-rotate-left"></i><?php esc_html_e('Korábbi verziók', 'gta6-mods'); ?></h3>
                        <?php foreach ($archive_versions as $archive_card) :
                            $archive_label = isset($archive_card['number']) && '' !== $archive_card['number'] ? $archive_card['number'] : __('—', 'gta6-mods');
                            $archive_downloads = isset($archive_card['downloads']) ? number_format_i18n((int) $archive_card['downloads']) : '0';
                            $archive_release = isset($archive_card['release_date']) ? $archive_card['release_date'] : '';
                            $archive_id = isset($archive_card['id']) ? (int) $archive_card['id'] : 0;
                        ?>
                            <details class="gta6-archive-version" data-gta6-version-card>
                                <summary>
                                    <div class="gta6-archive-summary">
                                        <h4><i class="fa-solid fa-code-branch"></i><?php esc_html_e('Verzió:', 'gta6-mods'); ?> <strong data-gta6-version-title><?php echo esc_html($archive_label); ?></strong></h4>
                                        <div class="gta6-version-meta">
                                            <span><?php echo esc_html(sprintf(__('ID: #%d', 'gta6-mods'), $archive_id)); ?></span>
                                            <?php if ($archive_release) : ?>
                                                <span><i class="fa-solid fa-calendar-days"></i><?php echo esc_html($archive_release); ?></span>
                                            <?php endif; ?>
                                            <span><i class="fa-solid fa-download"></i><?php echo esc_html($archive_downloads); ?></span>
                                            <?php if (!empty($archive_card['is_initial'])) : ?>
                                                <span class="gta6-version-badge gta6-version-badge--initial"><?php esc_html_e('Kezdeti kiadás', 'gta6-mods'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </summary>
                                <div class="gta6-version-card">
                                    <?php echo $render_version_inner_card($archive_card); ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <button type="button" class="button button-secondary gta6-add-version-btn"><i class="fa-solid fa-plus"></i><span><?php esc_html_e('Új verzió hozzáadása', 'gta6-mods'); ?></span></button>
            </div>

            <div id="gta6-tab-general" class="gta6-tab-panel">
                <div class="gta6-general-grid">
                    <div class="gta6-general-column">
                        <div class="gta6-card">
                            <h3 class="gta6-card-header"><i class="fa-solid fa-users"></i><?php esc_html_e('Szerzők', 'gta6-mods'); ?></h3>
                            <div class="gta6-card-body">
                                <div id="gta6-authors-container" class="gta6-authors-list" data-placeholder="<?php esc_attr_e('Szerző neve', 'gta6-mods'); ?>" data-remove-label="<?php esc_attr_e('Szerző törlése', 'gta6-mods'); ?>">
                                    <?php foreach ($authors_list as $author_name) : ?>
                                        <div class="gta6-author-row">
                                            <input type="text" class="form-input" name="gta6_additional_authors[]" value="<?php echo esc_attr($author_name); ?>" placeholder="<?php esc_attr_e('Szerző neve', 'gta6-mods'); ?>">
                                            <button type="button" class="button-link-delete" onclick="gta6RemoveAuthorRow(this)" title="<?php esc_attr_e('Szerző törlése', 'gta6-mods'); ?>"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button button-secondary gta6-add-author-btn" onclick="gta6AddAuthor()"><i class="fa-solid fa-plus"></i><span><?php esc_html_e('Szerző hozzáadása', 'gta6-mods'); ?></span></button>
                            </div>
                        </div>

                        <div class="gta6-card">
                            <h3 class="gta6-card-header"><i class="fa-solid fa-file-zipper"></i><?php esc_html_e('Mod fájl', 'gta6-mods'); ?></h3>
                            <div class="gta6-card-body">
                                <div class="gta6-form-grid">
                                    <div class="gta6-form-field">
                                        <label class="form-label" for="gta6_mod_file_id"><?php esc_html_e('Attachment ID', 'gta6-mods'); ?></label>
                                        <input type="number" class="form-input" id="gta6_mod_file_id" name="gta6_mod_file_id" value="<?php echo esc_attr(is_array($mod_file) ? ($mod_file['id'] ?? $mod_file['attachment_id'] ?? '') : ''); ?>" placeholder="<?php esc_attr_e('Attachment ID', 'gta6-mods'); ?>">
                                    </div>
                                    <div class="gta6-form-field">
                                        <label class="form-label" for="gta6_mod_file_size"><?php esc_html_e('Méret (bájt)', 'gta6-mods'); ?></label>
                                        <input type="number" class="form-input" id="gta6_mod_file_size" name="gta6_mod_file_size" value="<?php echo esc_attr(is_array($mod_file) ? ($mod_file['size_bytes'] ?? '') : ''); ?>" placeholder="0">
                                    </div>
                                </div>
                                <div class="gta6-form-field">
                                    <label class="form-label" for="gta6_mod_file_url"><?php esc_html_e('Fájl URL', 'gta6-mods'); ?></label>
                                    <input type="url" class="form-input" id="gta6_mod_file_url" name="gta6_mod_file_url" value="<?php echo esc_attr(is_array($mod_file) ? ($mod_file['url'] ?? '') : ''); ?>" placeholder="https://...">
                                </div>
                                <?php if (is_array($mod_file) && !empty($mod_file['url'])) : ?>
                                    <p class="gta6-version-note gta6-version-note--link">
                                        <strong><?php esc_html_e('Jelenlegi fájl:', 'gta6-mods'); ?></strong>
                                        <a href="<?php echo esc_url($mod_file['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(basename($mod_file['url'])); ?></a>
                                        <?php if (!empty($mod_file['size_human'])) : ?>
                                            <span class="gta6-note-meta"><?php echo esc_html($mod_file['size_human']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="gta6-general-column">
                        <div class="gta6-card">
                            <h3 class="gta6-card-header"><i class="fa-solid fa-sliders"></i><?php esc_html_e('Beállítások', 'gta6-mods'); ?></h3>
                            <div class="gta6-card-body">
                                <div class="gta6-form-field">
                                    <label class="form-label" for="gta6_video_permissions"><?php esc_html_e('Videó Engedélyek', 'gta6-mods'); ?></label>
                                    <select id="gta6_video_permissions" name="gta6_video_permissions" class="form-select">
                                        <option value="deny" <?php selected($video_permissions, 'deny'); ?>><?php esc_html_e('Tiltva', 'gta6-mods'); ?></option>
                                        <option value="moderate" <?php selected($video_permissions, 'moderate'); ?>><?php esc_html_e('Moderált', 'gta6-mods'); ?></option>
                                        <option value="allow" <?php selected($video_permissions, 'allow'); ?>><?php esc_html_e('Engedélyezett', 'gta6-mods'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="gta6-card">
                            <h3 class="gta6-card-header"><i class="fa-solid fa-cloud-arrow-down"></i><?php esc_html_e('Külső letöltési link', 'gta6-mods'); ?></h3>
                            <div class="gta6-card-body">
                                <div class="gta6-form-field">
                                    <label class="form-label" for="gta6_mod_external_url"><?php esc_html_e('Letöltési URL', 'gta6-mods'); ?></label>
                                    <input type="url" class="form-input" id="gta6_mod_external_url" name="gta6_mod_external_url" value="<?php echo esc_attr(is_array($mod_external) ? ($mod_external['url'] ?? '') : ''); ?>" placeholder="https://...">
                                </div>
                                <div class="gta6-form-grid">
                                    <div class="gta6-form-field">
                                        <label class="form-label" for="gta6_mod_external_size_value"><?php esc_html_e('Méret érték', 'gta6-mods'); ?></label>
                                        <input type="number" step="0.01" class="form-input" id="gta6_mod_external_size_value" name="gta6_mod_external_size_value" value="<?php echo esc_attr(is_array($mod_external) ? ($mod_external['size_value'] ?? '') : ''); ?>">
                                    </div>
                                    <div class="gta6-form-field">
                                        <label class="form-label" for="gta6_mod_external_size_unit"><?php esc_html_e('Mértékegység', 'gta6-mods'); ?></label>
                                        <select id="gta6_mod_external_size_unit" name="gta6_mod_external_size_unit" class="form-select">
                                            <option value="MB" <?php selected(is_array($mod_external) ? ($mod_external['size_unit'] ?? 'MB') : 'MB', 'MB'); ?>>MB</option>
                                            <option value="GB" <?php selected(is_array($mod_external) ? ($mod_external['size_unit'] ?? 'MB') : 'MB', 'GB'); ?>>GB</option>
                                        </select>
                                    </div>
                                </div>
                                <?php if (is_array($mod_external) && !empty($mod_external['size_human'])) : ?>
                                    <p class="gta6-version-note gta6-version-note--link">
                                        <strong><?php esc_html_e('Aktuális információ:', 'gta6-mods'); ?></strong>
                                        <?php if (!empty($mod_external['url'])) : ?>
                                            <a href="<?php echo esc_url($mod_external['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($mod_external['url']); ?></a>
                                        <?php endif; ?>
                                        <span class="gta6-note-meta"><?php echo esc_html($mod_external['size_human']); ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="gta6-tab-gallery" class="gta6-tab-panel">
                <div class="gta6-card">
                    <h3 class="gta6-card-header"><i class="fa-solid fa-images"></i><?php esc_html_e('Galéria Képek', 'gta6-mods'); ?></h3>
                    <div class="gta6-card-body">
                        <p class="gta6-help-text"><?php esc_html_e('Fogd meg a bal oldali ikont a képek sorrendjének módosításához.', 'gta6-mods'); ?></p>
                        <div id="gta6-gallery-container" class="gta6-gallery-grid" data-drag-label="<?php echo esc_attr__('Sorrend módosítása', 'gta6-mods'); ?>" data-featured-id="<?php echo esc_attr($featured_attachment_id); ?>" data-attachment-placeholder="<?php esc_attr_e('Attachment ID', 'gta6-mods'); ?>" data-feature-label="<?php esc_attr_e('Kiemelés', 'gta6-mods'); ?>" data-delete-label="<?php esc_attr_e('Törlés', 'gta6-mods'); ?>">
                            <?php if (!empty($gallery_images)) : ?>
                                <?php foreach ($gallery_images as $gallery_item) :
                                    $attachment_id = isset($gallery_item['attachment_id']) ? (int) $gallery_item['attachment_id'] : 0;
                                    $order_value   = isset($gallery_item['order']) ? (int) $gallery_item['order'] : 0;
                                    $preview_url   = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
                                    if (!$preview_url && !empty($gallery_item['url'])) {
                                        $preview_url = $gallery_item['url'];
                                    }
                                    $is_featured = $featured_attachment_id > 0 && $attachment_id === $featured_attachment_id;
                                ?>
                                    <div class="gta6-gallery-item<?php echo $is_featured ? ' gta6-gallery-item-featured' : ''; ?>">
                                        <button type="button" class="gta6-drag-handle" draggable="true" aria-label="<?php echo esc_attr__('Sorrend módosítása', 'gta6-mods'); ?>"><i class="fa-solid fa-grip-vertical"></i></button>
                                        <img src="<?php echo esc_url($preview_url); ?>" alt="" class="gta6-gallery-preview<?php echo $preview_url ? '' : ' gta6-gallery-preview-hidden'; ?>" loading="lazy">
                                        <div class="gta6-gallery-info">
                                            <label class="form-label form-label--compact"><?php esc_html_e('Attachment ID', 'gta6-mods'); ?></label>
                                            <input type="number" class="form-input form-input--compact" name="gta6_gallery_attachment_ids[]" value="<?php echo esc_attr($attachment_id); ?>" placeholder="0" min="1" step="1" onchange="gta6RefreshGalleryPreview(this)">
                                            <input type="hidden" name="gta6_gallery_order[]" value="<?php echo esc_attr($order_value); ?>">
                                        </div>
                                        <div class="gta6-gallery-actions">
                                            <button type="button" class="gta6-feature-btn<?php echo $is_featured ? ' featured' : ''; ?>" onclick="gta6ToggleFeaturedFromGallery(this)" title="<?php esc_attr_e('Kiemelés', 'gta6-mods'); ?>" aria-label="<?php esc_attr_e('Kiemelés', 'gta6-mods'); ?>"><i class="<?php echo $is_featured ? 'fa-solid' : 'fa-regular'; ?> fa-star"></i></button>
                                            <button type="button" class="gta6-delete-btn" onclick="gta6RemoveGalleryItem(this)" title="<?php esc_attr_e('Törlés', 'gta6-mods'); ?>" aria-label="<?php esc_attr_e('Törlés', 'gta6-mods'); ?>"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="gta6-gallery-item">
                                    <button type="button" class="gta6-drag-handle" draggable="true" aria-label="<?php echo esc_attr__('Sorrend módosítása', 'gta6-mods'); ?>"><i class="fa-solid fa-grip-vertical"></i></button>
                                    <img src="" alt="" class="gta6-gallery-preview gta6-gallery-preview-hidden" loading="lazy">
                                    <div class="gta6-gallery-info">
                                        <label class="form-label form-label--compact"><?php esc_html_e('Attachment ID', 'gta6-mods'); ?></label>
                                        <input type="number" class="form-input form-input--compact" name="gta6_gallery_attachment_ids[]" value="" placeholder="0" min="1" step="1" onchange="gta6RefreshGalleryPreview(this)">
                                        <input type="hidden" name="gta6_gallery_order[]" value="0">
                                    </div>
                                    <div class="gta6-gallery-actions">
                                        <button type="button" class="gta6-feature-btn" onclick="gta6ToggleFeaturedFromGallery(this)" title="<?php esc_attr_e('Kiemelés', 'gta6-mods'); ?>" aria-label="<?php esc_attr_e('Kiemelés', 'gta6-mods'); ?>"><i class="fa-regular fa-star"></i></button>
                                        <button type="button" class="gta6-delete-btn" onclick="gta6RemoveGalleryItem(this)" title="<?php esc_attr_e('Törlés', 'gta6-mods'); ?>" aria-label="<?php esc_attr_e('Törlés', 'gta6-mods'); ?>"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($inactive_gallery_items)) : ?>
                            <div class="gta6-gallery-inactive-wrapper">
                                <h4><?php esc_html_e('Inaktívvá tett képek', 'gta6-mods'); ?></h4>
                                <p><?php esc_html_e('Ezek a képek jelenleg rejtve vannak a mod oldalán. Újraaktiváláshoz add hozzá őket ismét a fenti listához.', 'gta6-mods'); ?></p>
                                <div class="gta6-gallery-inactive-list">
                                    <?php foreach ($inactive_gallery_items as $inactive_item) : ?>
                                        <div class="gta6-gallery-inactive-item">
                                            <?php
                                            $thumb = wp_get_attachment_image($inactive_item['id'], [120, 120], true, ['class' => 'gta6-gallery-inactive-thumb']);
                                            echo $thumb ? $thumb : '<span>' . esc_html__('Hiányzó kép', 'gta6-mods') . '</span>';
                                            ?>
                                            <span>#<?php echo esc_html($inactive_item['id']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <button type="button" class="button button-secondary gta6-add-gallery-btn" onclick="gta6AddGalleryItem()"><i class="fa-solid fa-plus"></i><span><?php esc_html_e('Kép hozzáadása', 'gta6-mods'); ?></span></button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
    function gta6InitTabs() {
        const buttons = document.querySelectorAll('.gta6-tab-button');
        const panels = document.querySelectorAll('.gta6-tab-panel');

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-tab');

                buttons.forEach((btn) => btn.classList.toggle('active', btn === button));
                panels.forEach((panel) => {
                    panel.classList.toggle('active', panel.id === target);
                });
            });
        });
    }

    function gta6AddAuthor() {
        const container = document.getElementById('gta6-authors-container');
        if (!container) {
            return;
        }

        const placeholder = container.dataset.placeholder || 'Szerző neve';
        const removeLabel = container.dataset.removeLabel || 'Szerző törlése';

        const row = document.createElement('div');
        row.className = 'gta6-author-row';
        row.innerHTML = `
            <input type="text" class="form-input" name="gta6_additional_authors[]" value="" placeholder="${placeholder}">
            <button type="button" class="button-link-delete" onclick="gta6RemoveAuthorRow(this)" title="${removeLabel}"><i class="fa-solid fa-trash-can"></i></button>
        `;
        container.appendChild(row);
    }

    function gta6RemoveAuthorRow(button) {
        const row = button.closest('.gta6-author-row');
        if (row) {
            row.remove();
        }
    }

    function gta6GetGalleryContainer() {
        return document.getElementById('gta6-gallery-container');
    }

    function gta6GetFeaturedIdFromMeta() {
        const featuredInput = document.getElementById('_thumbnail_id');
        if (featuredInput) {
            const parsed = parseInt(featuredInput.value, 10);
            return Number.isNaN(parsed) ? 0 : parsed;
        }

        const container = gta6GetGalleryContainer();
        if (container && typeof container.dataset.featuredId !== 'undefined') {
            const fallback = parseInt(container.dataset.featuredId || '0', 10);
            return Number.isNaN(fallback) ? 0 : fallback;
        }

        return 0;
    }

    function gta6ApplyFeaturedHighlight(featuredId) {
        const container = gta6GetGalleryContainer();
        if (!container) {
            return;
        }

        const items = container.querySelectorAll('.gta6-gallery-item');
        items.forEach((item) => {
            const input = item.querySelector('input[name="gta6_gallery_attachment_ids[]"]');
            const attachmentId = input ? parseInt(input.value, 10) : 0;
            const isFeatured = featuredId > 0 && attachmentId === featuredId;

            item.classList.toggle('gta6-gallery-item-featured', isFeatured);

            const button = item.querySelector('.gta6-feature-btn');
            if (button) {
                button.classList.toggle('featured', isFeatured);
                const icon = button.querySelector('i');
                if (icon) {
                    if (isFeatured) {
                        icon.classList.remove('fa-regular');
                        icon.classList.add('fa-solid');
                    } else {
                        icon.classList.remove('fa-solid');
                        icon.classList.add('fa-regular');
                    }
                }
            }
        });
    }

    function gta6SyncFeaturedBadge() {
        const featuredId = gta6GetFeaturedIdFromMeta();
        gta6ApplyFeaturedHighlight(featuredId);
    }

    function gta6ObserveFeaturedImageChanges() {
        if (window.jQuery && typeof window.jQuery === 'function') {
            window.jQuery(document).ajaxComplete((event, xhr, settings) => {
                if (!settings || typeof settings.data !== 'string') {
                    return;
                }

                if (settings.data.indexOf('action=set-post-thumbnail') !== -1) {
                    setTimeout(gta6SyncFeaturedBadge, 200);
                }
            });
        }
    }

    function gta6RemoveGalleryItem(button) {
        const item = button.closest('.gta6-gallery-item');
        if (!item) {
            return;
        }

        item.remove();
        gta6UpdateGalleryOrder();
        gta6SyncFeaturedBadge();
    }

    function gta6UpdateGalleryOrder() {
        const container = gta6GetGalleryContainer();
        if (!container) {
            return;
        }

        const items = container.querySelectorAll('.gta6-gallery-item');
        items.forEach((item, index) => {
            const orderInput = item.querySelector('input[name="gta6_gallery_order[]"]');
            if (orderInput) {
                orderInput.value = index;
            }
        });
    }

    function gta6RefreshGalleryPreview(input) {
        const item = input.closest('.gta6-gallery-item');
        if (!item) {
            return;
        }

        const preview = item.querySelector('.gta6-gallery-preview');
        if (!preview) {
            return;
        }

        const attachmentId = parseInt(input.value, 10);
        if (!attachmentId) {
            preview.src = '';
            preview.classList.add('gta6-gallery-preview-hidden');
            gta6SyncFeaturedBadge();
            return;
        }

        if (!window.wp || !wp.media || typeof wp.media.attachment !== 'function') {
            gta6SyncFeaturedBadge();
            return;
        }

        const attachment = wp.media.attachment(attachmentId);
        attachment.fetch().then(() => {
            const sizes = attachment.get('sizes') || {};
            const thumb = sizes.thumbnail || sizes.medium || sizes.full || {};
            const url = thumb.url || attachment.get('url') || '';
            if (url) {
                preview.src = url;
                preview.classList.remove('gta6-gallery-preview-hidden');
            } else {
                preview.src = '';
                preview.classList.add('gta6-gallery-preview-hidden');
            }
            gta6SyncFeaturedBadge();
        }).catch(() => {
            preview.src = '';
            preview.classList.add('gta6-gallery-preview-hidden');
            gta6SyncFeaturedBadge();
        });
    }

    function gta6AddGalleryItem() {
        const container = gta6GetGalleryContainer();
        if (!container) {
            return;
        }

        const dragLabel = container.dataset.dragLabel || '';
        const attachmentPlaceholder = container.dataset.attachmentPlaceholder || 'Attachment ID';
        const featureTitle = container.dataset.featureLabel || 'Kiemelés';
        const deleteTitle = container.dataset.deleteLabel || 'Törlés';

        const item = document.createElement('div');
        item.className = 'gta6-gallery-item';
        item.innerHTML = `
            <button type="button" class="gta6-drag-handle" draggable="true" aria-label="${dragLabel}"><i class="fa-solid fa-grip-vertical"></i></button>
            <img src="" alt="" class="gta6-gallery-preview gta6-gallery-preview-hidden" loading="lazy">
            <div class="gta6-gallery-info">
                <label class="form-label form-label--compact">${attachmentPlaceholder}</label>
                <input type="number" class="form-input form-input--compact" name="gta6_gallery_attachment_ids[]" value="" placeholder="0" min="1" step="1" onchange="gta6RefreshGalleryPreview(this)">
                <input type="hidden" name="gta6_gallery_order[]" value="0">
            </div>
            <div class="gta6-gallery-actions">
                <button type="button" class="gta6-feature-btn" onclick="gta6ToggleFeaturedFromGallery(this)" title="${featureTitle}" aria-label="${featureTitle}"><i class="fa-regular fa-star"></i></button>
                <button type="button" class="gta6-delete-btn" onclick="gta6RemoveGalleryItem(this)" title="${deleteTitle}" aria-label="${deleteTitle}"><i class="fa-solid fa-trash-can"></i></button>
            </div>
        `;
        container.appendChild(item);
        gta6UpdateGalleryOrder();
        gta6SyncFeaturedBadge();
    }

    function gta6ToggleFeaturedFromGallery(button) {
        const item = button.closest('.gta6-gallery-item');
        if (!item) {
            return;
        }

        const input = item.querySelector('input[name="gta6_gallery_attachment_ids[]"]');
        const attachmentId = input ? parseInt(input.value, 10) : 0;
        if (!attachmentId) {
            return;
        }

        if (window.wp && wp.media && wp.media.featuredImage && typeof wp.media.featuredImage.set === 'function') {
            wp.media.featuredImage.set(attachmentId);
        } else {
            const featuredInput = document.getElementById('_thumbnail_id');
            if (featuredInput) {
                featuredInput.value = attachmentId;
                featuredInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        setTimeout(gta6SyncFeaturedBadge, 200);
    }

    function gta6InitGalleryDragAndDrop() {
        const container = gta6GetGalleryContainer();
        if (!container) {
            return;
        }

        let draggedItem = null;

        container.addEventListener('dragstart', (event) => {
            if (!event.target.classList.contains('gta6-drag-handle')) {
                return;
            }
            draggedItem = event.target.closest('.gta6-gallery-item');
            if (!draggedItem) {
                return;
            }
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
            draggedItem.classList.add('gta6-gallery-item-dragging');
        });

        container.addEventListener('dragover', (event) => {
            if (!draggedItem) {
                return;
            }
            event.preventDefault();
            const target = event.target.closest('.gta6-gallery-item');
            if (!target || target === draggedItem) {
                return;
            }
            const rect = target.getBoundingClientRect();
            const offset = event.clientY - rect.top;
            if (offset > rect.height / 2) {
                target.after(draggedItem);
            } else {
                target.before(draggedItem);
            }
        });

        container.addEventListener('drop', (event) => {
            if (!draggedItem) {
                return;
            }
            event.preventDefault();
            gta6UpdateGalleryOrder();
        });

        container.addEventListener('dragend', () => {
            if (draggedItem) {
                draggedItem.classList.remove('gta6-gallery-item-dragging');
            }
            draggedItem = null;
        });

        gta6UpdateGalleryOrder();
    }

    function gta6InitVersionSourceControls() {
        const cards = document.querySelectorAll('[data-gta6-version-card]');

        cards.forEach((card) => {
            const radios = card.querySelectorAll('[data-gta6-version-source-toggle]');
            if (!radios.length) {
                return;
            }

            const sections = {
                file: card.querySelector('[data-gta6-version-source="file"]'),
                external: card.querySelector('[data-gta6-version-source="external"]'),
            };

            const applySelection = () => {
                const active = card.querySelector('[data-gta6-version-source-toggle]:checked');
                const selected = active ? active.value : 'file';

                Object.entries(sections).forEach(([type, element]) => {
                    if (!element) {
                        return;
                    }

                    if (type === selected) {
                        element.classList.add('is-active');
                    } else {
                        element.classList.remove('is-active');
                    }
                });
            };

            radios.forEach((radio) => {
                radio.addEventListener('change', applySelection);
            });

            applySelection();
        });
    }

    function gta6InitVersionTitleSync() {
        const cards = document.querySelectorAll('[data-gta6-version-card]');

        cards.forEach((card) => {
            const input = card.querySelector('input[name$="[number]"]');
            const displays = card.querySelectorAll('[data-gta6-version-title]');
            if (!input || !displays.length) {
                return;
            }

            const update = () => {
                const value = input.value.trim();
                const text = value !== '' ? value : '—';
                displays.forEach((display) => {
                    display.textContent = text;
                });
            };

            input.addEventListener('input', update);
            update();
        });
    }

    function gta6InitVersionDeletion() {
        const containers = document.querySelectorAll('[data-gta6-version-card]');

        containers.forEach((container) => {
            const versionCard = container.matches('.gta6-version-card') ? container : container.querySelector('.gta6-version-card');
            if (!versionCard) {
                return;
            }

            const deleteButton = versionCard.querySelector('[data-gta6-version-delete]');
            const deleteFlag = versionCard.querySelector('[data-gta6-version-delete-flag]');
            if (!deleteButton || !deleteFlag) {
                return;
            }

            const notice = versionCard.querySelector('[data-gta6-version-delete-notice]');
            const icon = deleteButton.querySelector('i');
            const label = deleteButton.querySelector('span');
            const deleteLabel = deleteButton.dataset.deleteLabel || '';
            const undoLabel = deleteButton.dataset.undoLabel || deleteLabel;

            const applyState = (marked) => {
                deleteFlag.value = marked ? '1' : '0';
                deleteFlag.dataset.gta6VersionDeleteFlag = marked ? '1' : '0';

                if (notice) {
                    notice.hidden = !marked;
                }

                versionCard.classList.toggle('gta6-version-card--deleted', marked);

                if (icon) {
                    if (marked) {
                        icon.classList.remove('fa-trash-can');
                        icon.classList.add('fa-rotate-left');
                    } else {
                        icon.classList.remove('fa-rotate-left');
                        icon.classList.add('fa-trash-can');
                    }
                }

                if (label) {
                    label.textContent = marked ? undoLabel : deleteLabel;
                }
            };

            deleteButton.addEventListener('click', () => {
                const marked = deleteFlag.value === '1';
                if (!marked) {
                    const confirmMessage = deleteButton.dataset.confirm || '';
                    if (confirmMessage && !window.confirm(confirmMessage)) {
                        return;
                    }
                    applyState(true);
                } else {
                    applyState(false);
                }
            });

            applyState(deleteFlag.value === '1');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        gta6InitTabs();
        gta6InitGalleryDragAndDrop();
        gta6ObserveFeaturedImageChanges();
        gta6SyncFeaturedBadge();
        gta6InitVersionSourceControls();
        gta6InitVersionTitleSync();
        gta6InitVersionDeletion();
    });
    </script>

    <?php
}

// Mentés
function gta6_mods_save_meta_box($post_id) {
    $nonce = isset($_POST['gta6_mods_meta_nonce']) ? wp_unslash($_POST['gta6_mods_meta_nonce']) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'gta6_mods_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (get_post_type($post_id) !== 'post') {
        return;
    }

    if (!current_user_can('edit_post', $post_id) || !is_super_admin()) {
        return;
    }

    // További szerzők
    $authors = [];
    if (isset($_POST['gta6_additional_authors']) && is_array($_POST['gta6_additional_authors'])) {
        $raw_authors = wp_unslash($_POST['gta6_additional_authors']);
        foreach ($raw_authors as $author) {
            $author = sanitize_text_field($author);
            if (!empty($author) && strlen($author) <= 100) {
                $authors[] = $author;
            }
        }
    }

    if (!empty($authors)) {
        update_post_meta($post_id, '_gta6mods_additional_authors', $authors);
    } else {
        delete_post_meta($post_id, '_gta6mods_additional_authors');
    }

    // Videó engedélyek
    if (isset($_POST['gta6_video_permissions'])) {
        $video_perm = sanitize_key(wp_unslash($_POST['gta6_video_permissions']));
        update_post_meta($post_id, '_gta6mods_video_permissions', $video_perm);
    }

    // Mod fájl
    $mod_file_id = isset($_POST['gta6_mod_file_id']) ? absint(wp_unslash($_POST['gta6_mod_file_id'])) : 0;
    $mod_file_url_raw = isset($_POST['gta6_mod_file_url']) ? wp_unslash($_POST['gta6_mod_file_url']) : '';
    $mod_file_url = $mod_file_url_raw !== '' ? esc_url_raw($mod_file_url_raw) : '';
    $mod_file_size = isset($_POST['gta6_mod_file_size']) ? absint(wp_unslash($_POST['gta6_mod_file_size'])) : 0;

    if ($mod_file_id || $mod_file_url) {
        update_post_meta($post_id, '_gta6mods_mod_file', [
            'id'            => $mod_file_id,
            'attachment_id' => $mod_file_id,
            'url'           => $mod_file_url,
            'size_bytes'    => $mod_file_size,
            'size_human'    => $mod_file_size > 0 ? size_format((float) $mod_file_size) : '',
        ]);
    } else {
        delete_post_meta($post_id, '_gta6mods_mod_file');
    }

    // Külső letöltési link
    $external_url_raw = isset($_POST['gta6_mod_external_url']) ? wp_unslash($_POST['gta6_mod_external_url']) : '';
    $external_url = $external_url_raw !== '' ? esc_url_raw($external_url_raw) : '';
    if ($external_url && filter_var($external_url, FILTER_VALIDATE_URL) === false) {
        $external_url = '';
    }

    $external_size_value_raw = isset($_POST['gta6_mod_external_size_value']) ? wp_unslash($_POST['gta6_mod_external_size_value']) : '';
    $external_size_unit_raw = isset($_POST['gta6_mod_external_size_unit']) ? wp_unslash($_POST['gta6_mod_external_size_unit']) : 'MB';

    $external_size_value = (float) str_replace(',', '.', sanitize_text_field($external_size_value_raw));
    $external_size_unit_clean = strtoupper(sanitize_text_field($external_size_unit_raw));
    $external_size_unit = in_array($external_size_unit_clean, ['MB', 'GB'], true)
        ? $external_size_unit_clean
        : 'MB';

    if (!empty($external_url) && $external_size_value > 0) {
        $multiplier = 'GB' === $external_size_unit ? 1024 * 1024 * 1024 : 1024 * 1024;
        $size_bytes = (int) round($external_size_value * $multiplier);
        $display_value = $external_size_value === (float) (int) $external_size_value
            ? (string) (int) $external_size_value
            : rtrim(rtrim(number_format($external_size_value, 2, '.', ''), '0'), '.');

        update_post_meta($post_id, '_gta6mods_mod_external', [
            'url'        => $external_url,
            'size_value' => $external_size_value,
            'size_unit'  => $external_size_unit,
            'size_bytes' => $size_bytes,
            'size_human' => sprintf('%s %s', $display_value, $external_size_unit),
        ]);
        if (function_exists('gta6mods_invalidate_external_waiting_room_cache')) {
            gta6mods_invalidate_external_waiting_room_cache($post_id, $post_id, 'mod');
        }
    } else {
        delete_post_meta($post_id, '_gta6mods_mod_external');
        if (function_exists('gta6mods_invalidate_external_waiting_room_cache')) {
            gta6mods_invalidate_external_waiting_room_cache($post_id, $post_id, 'mod');
        }
    }

    // Galéria képek
    $gallery_data = [];
    $attachment_ids = isset($_POST['gta6_gallery_attachment_ids']) ? (array) wp_unslash($_POST['gta6_gallery_attachment_ids']) : [];
    $orders_input = isset($_POST['gta6_gallery_order']) ? (array) wp_unslash($_POST['gta6_gallery_order']) : [];
    $seen_attachments = [];

    foreach ($attachment_ids as $index => $attachment_id_raw) {
        $attachment_id = absint($attachment_id_raw);
        if ($attachment_id <= 0 || isset($seen_attachments[$attachment_id])) {
            continue;
        }

        $attachment_post = get_post($attachment_id);
        if (!$attachment_post instanceof WP_Post || 'attachment' !== $attachment_post->post_type) {
            continue;
        }

        $order_value = isset($orders_input[$index]) ? (int) $orders_input[$index] : $index;
        $gallery_data[] = [
            'attachment_id' => $attachment_id,
            'order'         => $order_value,
        ];
        $seen_attachments[$attachment_id] = true;
    }

    if (!empty($gallery_data)) {
        usort(
            $gallery_data,
            static function ($a, $b) {
                return $a['order'] <=> $b['order'];
            }
        );

        foreach ($gallery_data as $index => &$item) {
            $item['order'] = $index;
        }
        unset($item);

        update_post_meta($post_id, '_gta6mods_gallery_images', wp_json_encode($gallery_data));
    } else {
        delete_post_meta($post_id, '_gta6mods_gallery_images');
    }

    if (isset($_POST['gta6_version']) && is_array($_POST['gta6_version'])) {
        $versions_input    = wp_unslash($_POST['gta6_version']);
        $mod_title         = get_the_title($post_id);
        $versions_synced   = [];
        $versions_to_delete = [];
        $mod_title_string  = is_string($mod_title) ? $mod_title : '';

        foreach ($versions_input as $version_entry) {
            if (!is_array($version_entry)) {
                continue;
            }

            $version_id = isset($version_entry['id']) ? absint($version_entry['id']) : 0;
            if ($version_id <= 0) {
                continue;
            }

            $delete_flag = isset($version_entry['delete']) ? absint($version_entry['delete']) : 0;
            if (1 === $delete_flag) {
                $versions_to_delete[$version_id] = true;
                continue;
            }

            $number    = isset($version_entry['number']) ? sanitize_text_field($version_entry['number']) : '';
            $downloads = isset($version_entry['downloads']) ? (int) $version_entry['downloads'] : 0;
            if ($downloads < 0) {
                $downloads = 0;
            }

            $changelog_text = isset($version_entry['changelog']) ? (string) $version_entry['changelog'] : '';
            $changelog_rows = [];
            if ($changelog_text !== '') {
                $lines = preg_split('/\r\n|\r|\n/', $changelog_text);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        if (!is_string($line)) {
                            continue;
                        }
                        $line = trim($line);
                        if ('' === $line) {
                            continue;
                        }
                        $changelog_rows[] = sanitize_text_field($line);
                    }
                }
            }

            update_post_meta($version_id, '_gta6mods_version_number', $number);
            update_post_meta($version_id, '_gta6mods_version_download_count', $downloads);
            update_post_meta($version_id, '_gta6mods_version_changelog', $changelog_rows);

            $scan_url_raw   = isset($version_entry['scan_url']) ? trim((string) $version_entry['scan_url']) : '';
            $scan_url_clean = '';
            if ('' !== $scan_url_raw) {
                $maybe_url = esc_url_raw($scan_url_raw);
                if ($maybe_url) {
                    $scan_url_clean = $maybe_url;
                }
            }

            if ('' !== $scan_url_clean) {
                update_post_meta($version_id, '_gta6mods_version_scan_url', $scan_url_clean);
            } else {
                delete_post_meta($version_id, '_gta6mods_version_scan_url');
            }

            if ('' !== $number && '' !== $mod_title_string) {
                wp_update_post([
                    'ID'         => $version_id,
                    'post_title' => sprintf('%s – %s', $mod_title_string, $number),
                ]);
            }

            $existing_source = get_post_meta($version_id, '_gta6mods_version_source', true);
            $existing_source = is_array($existing_source) ? $existing_source : [];
            $source_type     = isset($version_entry['source_type']) ? sanitize_key($version_entry['source_type']) : '';
            $source          = [];

            if ('file' === $source_type) {
                $attachment_id = isset($version_entry['file_attachment_id']) ? absint($version_entry['file_attachment_id']) : 0;
                if ($attachment_id > 0) {
                    $size_bytes = isset($version_entry['file_size_bytes']) ? (int) $version_entry['file_size_bytes'] : 0;
                    if ($size_bytes < 0) {
                        $size_bytes = 0;
                    }
                    $size_human = isset($version_entry['file_size_human']) ? sanitize_text_field($version_entry['file_size_human']) : '';
                    if ($size_bytes > 0 && '' === $size_human) {
                        $size_human = size_format((float) $size_bytes);
                    }

                    $source = [
                        'type'          => 'file',
                        'attachment_id' => $attachment_id,
                        'size_bytes'    => $size_bytes,
                        'size_human'    => $size_human,
                    ];

                    $file_url = wp_get_attachment_url($attachment_id);
                    if ($file_url) {
                        $source['url'] = esc_url_raw($file_url);
                    }

                    if (function_exists('gta6mods_assign_attachment_to_post')) {
                        gta6mods_assign_attachment_to_post($attachment_id, $post_id);
                    }
                }
            } elseif ('external' === $source_type) {
                $external_url = isset($version_entry['external_url']) ? esc_url_raw($version_entry['external_url']) : '';
                if ($external_url && filter_var($external_url, FILTER_VALIDATE_URL)) {
                    $size_bytes = isset($version_entry['external_size_bytes']) ? (int) $version_entry['external_size_bytes'] : 0;
                    if ($size_bytes < 0) {
                        $size_bytes = 0;
                    }
                    $size_human = isset($version_entry['external_size_human']) ? sanitize_text_field($version_entry['external_size_human']) : '';
                    if ($size_bytes > 0 && '' === $size_human) {
                        $size_human = size_format((float) $size_bytes);
                    }

                    $source = [
                        'type'       => 'external',
                        'url'        => $external_url,
                        'size_bytes' => $size_bytes,
                        'size_human' => $size_human,
                    ];
                }
            }

            if (!empty($source)) {
                update_post_meta($version_id, '_gta6mods_version_source', $source);
            } else {
                $source = $existing_source;
            }

            $is_initial = (bool) get_post_meta($version_id, '_gta6mods_version_is_initial', true);
            if ($is_initial) {
                if ('' !== $number) {
                    update_post_meta($post_id, '_gta6mods_initial_version_number', $number);
                }
                update_post_meta($post_id, '_gta6mods_initial_version_download_count', $downloads);
                update_post_meta($post_id, '_gta6mods_initial_version_id', $version_id);
            }

            $versions_synced[$version_id] = [
                'number'    => $number,
                'downloads' => $downloads,
                'source'    => $source,
                'scan_url'  => $scan_url_clean,
                'is_initial'=> $is_initial,
            ];
        }

        $selected_current      = isset($_POST['gta6_current_version']) ? absint(wp_unslash($_POST['gta6_current_version'])) : 0;
        $stored_current_version = (int) get_post_meta($post_id, '_gta6mods_current_version_id', true);
        $effective_current      = $stored_current_version;

        if ($selected_current > 0 && isset($versions_synced[$selected_current])) {
            $effective_current = $selected_current;
            update_post_meta($post_id, '_gta6mods_current_version_id', $selected_current);
        } elseif ($stored_current_version > 0 && isset($versions_synced[$stored_current_version])) {
            $effective_current = $stored_current_version;
        } elseif ($stored_current_version <= 0 && !empty($versions_synced)) {
            foreach ($versions_synced as $version_key => $version_data) {
                $effective_current = $version_key;
                update_post_meta($post_id, '_gta6mods_current_version_id', $version_key);
                break;
            }
        }

        if ($effective_current > 0 && isset($versions_synced[$effective_current])) {
            $current_info = $versions_synced[$effective_current];

            if (!empty($current_info['number'])) {
                update_post_meta($post_id, '_gta6mods_mod_version', $current_info['number']);
            }

            $source = isset($current_info['source']) && is_array($current_info['source']) ? $current_info['source'] : [];

            if (isset($source['type']) && 'file' === $source['type'] && !empty($source['attachment_id'])) {
                $attachment_id = (int) $source['attachment_id'];
                $size_bytes    = isset($source['size_bytes']) ? (int) $source['size_bytes'] : 0;
                $size_human    = isset($source['size_human']) ? sanitize_text_field($source['size_human']) : '';
                if ($size_bytes > 0 && '' === $size_human) {
                    $size_human = size_format((float) $size_bytes);
                }
                $file_url = wp_get_attachment_url($attachment_id);

                update_post_meta(
                    $post_id,
                    '_gta6mods_mod_file',
                    [
                        'id'            => $attachment_id,
                        'attachment_id' => $attachment_id,
                        'url'           => $file_url ? esc_url_raw($file_url) : '',
                        'size_bytes'    => $size_bytes,
                        'size_human'    => $size_human,
                        'version_id'    => $effective_current,
                    ]
                );
                delete_post_meta($post_id, '_gta6mods_mod_external');

                if (function_exists('gta6mods_assign_attachment_to_post') && $attachment_id > 0) {
                    gta6mods_assign_attachment_to_post($attachment_id, $post_id);
                }
            } elseif (isset($source['type']) && 'external' === $source['type'] && !empty($source['url'])) {
                $external_url = esc_url_raw($source['url']);
                if ($external_url && filter_var($external_url, FILTER_VALIDATE_URL)) {
                    $size_bytes = isset($source['size_bytes']) ? (int) $source['size_bytes'] : 0;
                    $size_human = isset($source['size_human']) ? sanitize_text_field($source['size_human']) : '';
                    if ($size_bytes > 0 && '' === $size_human) {
                        $size_human = size_format((float) $size_bytes);
                    }

                    update_post_meta(
                        $post_id,
                        '_gta6mods_mod_external',
                        [
                            'url'        => $external_url,
                            'size_bytes' => $size_bytes,
                            'size_human' => $size_human,
                            'version_id' => $effective_current,
                        ]
                    );
                    delete_post_meta($post_id, '_gta6mods_mod_file');
                }
            }
        }

        if (!empty($versions_to_delete)) {
            $current_version_id   = (int) get_post_meta($post_id, '_gta6mods_current_version_id', true);
            $initial_version_id   = (int) get_post_meta($post_id, '_gta6mods_initial_version_id', true);
            $history              = get_post_meta($post_id, '_gta6mods_version_history', true);
            if (!is_array($history)) {
                $history = [];
            }

            $history_changed = false;
            $reset_initial   = false;

            foreach (array_keys($versions_to_delete) as $version_to_delete) {
                $version_to_delete = (int) $version_to_delete;
                if ($version_to_delete <= 0 || $version_to_delete === $current_version_id) {
                    continue;
                }

                $parent_id = (int) get_post_meta($version_to_delete, '_gta6mods_version_parent', true);
                if ($parent_id !== $post_id) {
                    continue;
                }

                if ('mod_version' !== get_post_type($version_to_delete)) {
                    continue;
                }

                if (function_exists('wp_trash_post')) {
                    $trashed = wp_trash_post($version_to_delete);
                    if (false === $trashed) {
                        continue;
                    }
                } else {
                    wp_delete_post($version_to_delete, true);
                }

                if (in_array($version_to_delete, $history, true)) {
                    $history = array_values(
                        array_filter(
                            $history,
                            static function ($history_id) use ($version_to_delete) {
                                return (int) $history_id !== $version_to_delete;
                            }
                        )
                    );
                    $history_changed = true;
                }

                if ($version_to_delete === $initial_version_id) {
                    $reset_initial = true;
                }
            }

            if ($history_changed) {
                if (!empty($history)) {
                    update_post_meta($post_id, '_gta6mods_version_history', $history);
                } else {
                    delete_post_meta($post_id, '_gta6mods_version_history');
                }
            }

            if ($reset_initial) {
                delete_post_meta($post_id, '_gta6mods_initial_version_id');
                delete_post_meta($post_id, '_gta6mods_initial_version_number');
                delete_post_meta($post_id, '_gta6mods_initial_version_download_count');

                if (function_exists('gta6mods_ensure_initial_version_exists')) {
                    gta6mods_ensure_initial_version_exists($post_id);
                }
            }
        }
    }
}
add_action('save_post', 'gta6_mods_save_meta_box');