<?php
/**
 * Mod update and version management functions.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers custom post types and statuses for mod updates and versions.
 */
function gta6mods_register_mod_update_post_types() {
    $update_labels = [
        'name'               => _x('Mod frissítések', 'post type general name', 'gta6-mods'),
        'singular_name'      => _x('Mod frissítés', 'post type singular name', 'gta6-mods'),
        'menu_name'          => _x('Mod frissítések', 'admin menu', 'gta6-mods'),
        'name_admin_bar'     => _x('Mod frissítés', 'add new on admin bar', 'gta6-mods'),
        'add_new'            => _x('Új hozzáadása', 'mod update', 'gta6-mods'),
        'add_new_item'       => __('Új frissítés', 'gta6-mods'),
        'new_item'           => __('Új frissítés', 'gta6-mods'),
        'edit_item'          => __('Frissítés szerkesztése', 'gta6-mods'),
        'view_item'          => __('Frissítés megtekintése', 'gta6-mods'),
        'all_items'          => __('Frissítések', 'gta6-mods'),
        'search_items'       => __('Frissítések keresése', 'gta6-mods'),
        'parent_item_colon'  => __('Szülő frissítések:', 'gta6-mods'),
        'not_found'          => __('Nem található frissítés.', 'gta6-mods'),
        'not_found_in_trash' => __('A kukában sincs frissítés.', 'gta6-mods'),
    ];

    register_post_type(
        'mod_update',
        [
            'labels'             => $update_labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php',
            'show_in_admin_bar'  => true,
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'rewrite'            => false,
            'menu_icon'          => 'dashicons-update',
        ]
    );

    $version_labels = [
        'name'               => _x('Mod verziók', 'post type general name', 'gta6-mods'),
        'singular_name'      => _x('Mod verzió', 'post type singular name', 'gta6-mods'),
        'menu_name'          => _x('Mod verziók', 'admin menu', 'gta6-mods'),
        'name_admin_bar'     => _x('Mod verzió', 'add new on admin bar', 'gta6-mods'),
        'add_new'            => _x('Új verzió', 'mod version', 'gta6-mods'),
        'add_new_item'       => __('Új verzió hozzáadása', 'gta6-mods'),
        'new_item'           => __('Új verzió', 'gta6-mods'),
        'edit_item'          => __('Verzió szerkesztése', 'gta6-mods'),
        'view_item'          => __('Verzió megtekintése', 'gta6-mods'),
        'all_items'          => __('Verziók', 'gta6-mods'),
        'search_items'       => __('Verziók keresése', 'gta6-mods'),
        'not_found'          => __('Nem található verzió.', 'gta6-mods'),
        'not_found_in_trash' => __('A kukában sincs verzió.', 'gta6-mods'),
    ];

    register_post_type(
        'mod_version',
        [
            'labels'            => $version_labels,
            'public'            => false,
            'show_ui'           => false,
            'supports'          => ['title'],
            'capability_type'   => 'post',
            'map_meta_cap'      => true,
            'hierarchical'      => false,
            'rewrite'           => false,
        ]
    );
}
add_action('init', 'gta6mods_register_mod_update_post_types');

/**
 * Registers custom post statuses used by mod updates.
 */
function gta6mods_register_mod_update_statuses() {
    register_post_status(
        'gta6mods_rejected',
        [
            'label'                     => _x('Elutasítva', 'mod update status', 'gta6-mods'),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Elutasítva <span class="count">(%s)</span>', 'Elutasítva <span class="count">(%s)</span>', 'gta6-mods'),
        ]
    );
}
add_action('init', 'gta6mods_register_mod_update_statuses');

/**
 * Returns the stored initial version ID if available.
 *
 * @param int $post_id Mod post ID.
 *
 * @return int
 */
function gta6mods_get_initial_version_id($post_id) {
    $post_id = absint($post_id);

    if ($post_id <= 0) {
        return 0;
    }

    $initial_id = (int) get_post_meta($post_id, '_gta6mods_initial_version_id', true);

    if ($initial_id > 0) {
        $version_post = get_post($initial_id);
        if ($version_post instanceof WP_Post && 'mod_version' === $version_post->post_type) {
            return $initial_id;
        }
    }

    return 0;
}

/**
 * Ensures that an initial release version post exists for the given mod.
 *
 * @param int $post_id Mod post ID.
 *
 * @return int Version post ID or 0 on failure.
 */
function gta6mods_ensure_initial_version_exists($post_id) {
    $post_id = absint($post_id);

    if ($post_id <= 0) {
        return 0;
    }

    $existing_id = gta6mods_get_initial_version_id($post_id);
    if ($existing_id > 0) {
        return $existing_id;
    }

    $existing_versions_query = new WP_Query(
        [
            'post_type'      => 'mod_version',
            'post_status'    => ['publish'],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'   => '_gta6mods_version_parent',
                    'value' => $post_id,
                ],
            ],
        ]
    );

    if ($existing_versions_query->have_posts()) {
        $posts = $existing_versions_query->posts;
        wp_reset_postdata();

        if (!empty($posts)) {
            $initial_post = $posts[0];
            if ($initial_post instanceof WP_Post) {
                update_post_meta($initial_post->ID, '_gta6mods_version_is_initial', 1);
                update_post_meta($post_id, '_gta6mods_initial_version_id', $initial_post->ID);

                $initial_number = get_post_meta($initial_post->ID, '_gta6mods_version_number', true);
                if (is_string($initial_number) && '' !== trim($initial_number)) {
                    update_post_meta($post_id, '_gta6mods_initial_version_number', trim($initial_number));
                }

                $initial_downloads = (int) get_post_meta($initial_post->ID, '_gta6mods_version_download_count', true);
                update_post_meta($post_id, '_gta6mods_initial_version_download_count', max(0, $initial_downloads));

                $history = get_post_meta($post_id, '_gta6mods_version_history', true);
                if (!is_array($history)) {
                    $history = [];
                }
                if (!in_array($initial_post->ID, $history, true)) {
                    $history[] = (int) $initial_post->ID;
                    update_post_meta($post_id, '_gta6mods_version_history', $history);
                }

                return (int) $initial_post->ID;
            }
        }
    }

    wp_reset_postdata();

    $mod_post = get_post($post_id);
    $allowed_types = function_exists('gta6mods_get_mod_post_types') ? gta6mods_get_mod_post_types() : ['post'];

    if (!$mod_post instanceof WP_Post || !in_array($mod_post->post_type, $allowed_types, true)) {
        return 0;
    }

    $file_meta     = get_post_meta($post_id, '_gta6mods_mod_file', true);
    $external_meta = get_post_meta($post_id, '_gta6mods_mod_external', true);

    $source = [];

    if (is_array($file_meta) && !empty($file_meta['attachment_id'])) {
        $attachment_id = (int) $file_meta['attachment_id'];
        if ($attachment_id > 0) {
            $source = [
                'type'          => 'file',
                'attachment_id' => $attachment_id,
                'size_bytes'    => isset($file_meta['size_bytes']) ? (int) $file_meta['size_bytes'] : 0,
                'size_human'    => isset($file_meta['size_human']) ? sanitize_text_field($file_meta['size_human']) : '',
                'url'           => isset($file_meta['url']) ? esc_url_raw($file_meta['url']) : '',
            ];
        }
    } elseif (is_array($external_meta) && !empty($external_meta['url'])) {
        $source = [
            'type'       => 'external',
            'url'        => esc_url_raw($external_meta['url']),
            'size_bytes' => isset($external_meta['size_bytes']) ? (int) $external_meta['size_bytes'] : 0,
            'size_human' => isset($external_meta['size_human']) ? sanitize_text_field($external_meta['size_human']) : '',
        ];
    }

    if (empty($source)) {
        return 0;
    }

    $version_number = get_post_meta($post_id, '_gta6mods_initial_version_number', true);
    if (!is_string($version_number) || '' === trim($version_number)) {
        $version_number = get_post_meta($post_id, '_gta6mods_mod_version', true);
    }
    if (!is_string($version_number) || '' === trim($version_number)) {
        $version_number = '1.0';
    }
    $version_number = trim($version_number);

    $post_date     = get_post_field('post_date', $post_id);
    $post_date_gmt = get_post_field('post_date_gmt', $post_id);

    $version_post_id = wp_insert_post(
        [
            'post_type'     => 'mod_version',
            'post_status'   => 'publish',
            'post_parent'   => $post_id,
            'post_title'    => sprintf('%s – %s', get_the_title($post_id), $version_number),
            'post_author'   => $mod_post->post_author,
            'post_date'     => $post_date,
            'post_date_gmt' => $post_date_gmt,
        ],
        true
    );

    if (is_wp_error($version_post_id) || $version_post_id <= 0) {
        return 0;
    }

    $download_total    = function_exists('gta6_mods_get_download_count') ? (int) gta6_mods_get_download_count($post_id) : (int) get_post_meta($post_id, '_gta6mods_download_count', true);
    $initial_downloads = (int) get_post_meta($post_id, '_gta6mods_initial_version_download_count', true);
    if ($initial_downloads <= 0 && $download_total > 0) {
        $initial_downloads = $download_total;
    }

    update_post_meta($version_post_id, '_gta6mods_version_number', $version_number);
    update_post_meta($version_post_id, '_gta6mods_version_parent', $post_id);
    update_post_meta($version_post_id, '_gta6mods_version_changelog', []);
    update_post_meta($version_post_id, '_gta6mods_version_download_count', max(0, $initial_downloads));
    update_post_meta($version_post_id, '_gta6mods_version_source', $source);
    update_post_meta($version_post_id, '_gta6mods_version_is_initial', 1);

    update_post_meta($post_id, '_gta6mods_initial_version_id', $version_post_id);
    update_post_meta($post_id, '_gta6mods_initial_version_number', $version_number);
    update_post_meta($post_id, '_gta6mods_initial_version_download_count', max(0, $initial_downloads));

    $history = get_post_meta($post_id, '_gta6mods_version_history', true);
    if (!is_array($history)) {
        $history = [];
    }
    $history[] = (int) $version_post_id;
    $history = array_values(array_unique(array_map('intval', $history)));
    update_post_meta($post_id, '_gta6mods_version_history', $history);

    if ((int) get_post_meta($post_id, '_gta6mods_current_version_id', true) <= 0) {
        update_post_meta($post_id, '_gta6mods_current_version_id', $version_post_id);
    }

    if ('file' === $source['type'] && !empty($file_meta)) {
        $file_meta['version_id'] = $version_post_id;
        update_post_meta($post_id, '_gta6mods_mod_file', $file_meta);
        gta6mods_invalidate_external_waiting_room_cache($post_id, $version_post_id, 'version');
    } elseif ('external' === $source['type'] && !empty($external_meta)) {
        $external_meta['version_id'] = $version_post_id;
        update_post_meta($post_id, '_gta6mods_mod_external', $external_meta);
        gta6mods_invalidate_external_waiting_room_cache($post_id, $version_post_id, 'version');
    }

    return $version_post_id;
}

/**
 * Returns the attachment IDs that are marked as removed for a mod.
 *
 * @param int $post_id Mod post ID.
 *
 * @return int[]
 */
function gta6mods_get_removed_gallery_ids($post_id) {
    $raw = get_post_meta($post_id, '_gta6mods_gallery_images_removed', true);

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('absint', $decoded))); 
        }
    }

    if (is_array($raw)) {
        return array_values(array_filter(array_map('absint', $raw)));
    }

    return [];
}

/**
 * Stores the attachment IDs that should be marked as removed for a mod.
 *
 * @param int   $post_id Mod post ID.
 * @param int[] $attachment_ids Attachment IDs.
 */
function gta6mods_set_removed_gallery_ids($post_id, $attachment_ids) {
    $post_id        = absint($post_id);
    $attachment_ids = array_values(array_filter(array_map('absint', (array) $attachment_ids)));

    if ($post_id <= 0) {
        return;
    }

    if (!empty($attachment_ids)) {
        update_post_meta($post_id, '_gta6mods_gallery_images_removed', wp_json_encode($attachment_ids));
    } else {
        delete_post_meta($post_id, '_gta6mods_gallery_images_removed');
    }
}

/**
 * Retrieves all mod version posts for a mod.
 *
 * @param int $post_id Mod post ID.
 *
 * @return WP_Post[]
 */
function gta6mods_get_version_posts($post_id) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return [];
    }

    $query = new WP_Query(
        [
            'post_type'      => 'mod_version',
            'post_status'    => ['publish'],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_gta6mods_version_parent',
                    'value' => $post_id,
                ],
            ],
        ]
    );

    if (!$query->have_posts()) {
        return [];
    }

    return $query->posts;
}

/**
 * Converts a mod version post into a normalized array.
 *
 * @param WP_Post $version Version post object.
 *
 * @return array
 */
function gta6mods_prepare_version_array($version) {
    if (!$version instanceof WP_Post) {
        return [];
    }

    $version_id   = (int) $version->ID;
    $parent_id    = (int) get_post_meta($version_id, '_gta6mods_version_parent', true);
    $number       = get_post_meta($version_id, '_gta6mods_version_number', true);
    $number       = is_string($number) ? trim($number) : '';
    $changelog    = get_post_meta($version_id, '_gta6mods_version_changelog', true);
    $changelog    = is_array($changelog) ? array_values(array_filter(array_map('sanitize_text_field', $changelog))) : [];
    $downloads    = (int) get_post_meta($version_id, '_gta6mods_version_download_count', true);
    $source       = get_post_meta($version_id, '_gta6mods_version_source', true);
    $source       = is_array($source) ? $source : [];
    $release_date = get_the_date('c', $version_id);
    $is_initial   = (bool) get_post_meta($version_id, '_gta6mods_version_is_initial', true);
    $virus_scan   = get_post_meta($version_id, '_gta6mods_version_scan_url', true);
    $virus_scan   = is_string($virus_scan) ? esc_url_raw($virus_scan) : '';

    $download_url        = '';
    $size_human          = '';
    $table_id            = (int) get_post_meta($version_id, '_gta6mods_version_table_id', true);
    $attachment_id_value = 0;
    $source_type         = isset($source['type']) ? $source['type'] : '';
    $external_url        = '';

    if ($table_id <= 0 && $parent_id > 0 && '' !== $number && class_exists('GTA6Mods_Mod_Versions')) {
        $matched_version = GTA6Mods_Mod_Versions::get_version_by_mod_and_number($parent_id, $number);
        if (is_array($matched_version) && !empty($matched_version['id'])) {
            $table_id = (int) $matched_version['id'];
            update_post_meta($version_id, '_gta6mods_version_table_id', $table_id);
        }
    }

    if ('file' === $source_type) {
        if (empty($source['attachment_id']) && !empty($source['id'])) {
            $source['attachment_id'] = $source['id'];
        }

        if (empty($source['attachment_id']) && !empty($source['ID'])) {
            $source['attachment_id'] = $source['ID'];
        }

        if (!empty($source['attachment_id'])) {
            $attachment_id_value = absint($source['attachment_id']);
        } elseif (!empty($source['url']) && function_exists('attachment_url_to_postid')) {
            $maybe_attachment = attachment_url_to_postid($source['url']);
            if ($maybe_attachment) {
                $attachment_id_value = (int) $maybe_attachment;
            }
        }

        if ($attachment_id_value > 0) {
            if (!empty($source['size_human'])) {
                $size_human = sanitize_text_field($source['size_human']);
            } elseif (!empty($source['size_bytes'])) {
                $size_human = size_format((float) $source['size_bytes']);
            }
        }
    } elseif ('external' === $source_type && !empty($source['url'])) {
        $external_url = esc_url_raw($source['url']);
        if (!empty($source['size_human'])) {
            $size_human = sanitize_text_field($source['size_human']);
        }
    }

    $changelog_text = '';
    if (!empty($changelog)) {
        $changelog_text = implode("\n", array_map('sanitize_text_field', $changelog));
    }

    if ($table_id <= 0 && $parent_id > 0 && class_exists('GTA6Mods_Mod_Versions')) {
        $matched_row = null;

        if ('' !== $number) {
            $matched_row = GTA6Mods_Mod_Versions::get_version_by_mod_and_number($parent_id, $number);
        }

        if ((!is_array($matched_row) || empty($matched_row['id'])) && $attachment_id_value > 0) {
            $matched_row = GTA6Mods_Mod_Versions::get_version_by_mod_and_attachment($parent_id, $attachment_id_value);
        }

        if ((!is_array($matched_row) || empty($matched_row['id'])) && $attachment_id_value > 0) {
            $upload_date_gmt = get_post_time('mysql', true, $version_id);
            if (!$upload_date_gmt) {
                $upload_date_gmt = current_time('mysql', true);
            }

            $normalized_version = '' !== $number ? $number : ('legacy-' . $version_id);
            $inserted_id = GTA6Mods_Mod_Versions::insert_version(
                $parent_id,
                $normalized_version,
                $attachment_id_value,
                $changelog_text,
                $upload_date_gmt,
                false
            );

            if ($inserted_id > 0) {
                update_post_meta($version_id, '_gta6mods_version_table_id', $inserted_id);
                $matched_row = GTA6Mods_Mod_Versions::get_version($inserted_id);
            }
        }

        if (is_array($matched_row) && !empty($matched_row['id'])) {
            $table_id = (int) $matched_row['id'];
            if ((int) get_post_meta($version_id, '_gta6mods_version_table_id', true) !== $table_id) {
                update_post_meta($version_id, '_gta6mods_version_table_id', $table_id);
            }
        }
    }

    if ($parent_id > 0 && $table_id > 0) {
        $download_url = gta6_mods_get_waiting_room_url($parent_id, $table_id);
    } elseif ($attachment_id_value > 0) {
        $download_url = wp_get_attachment_url($attachment_id_value) ?: '';
    } elseif ('' !== $external_url) {
        $download_url = gta6_mods_get_waiting_room_url(
            $parent_id,
            $version_id,
            [
                'external_type'   => 'version',
                'external_target' => $version_id,
            ]
        );
    }

    return [
        'id'           => $version_id,
        'number'       => $number,
        'changelog'    => $changelog,
        'downloads'    => max(0, $downloads),
        'source'       => array_merge(
            $source,
            [
                'attachment_id' => $attachment_id_value,
                'url'           => isset($source['url']) ? esc_url_raw($source['url']) : '',
            ]
        ),
        'download_url' => $download_url,
        'size_human'   => $size_human,
        'date'         => $release_date,
        'is_initial'   => $is_initial,
        'virus_scan_url' => $virus_scan,
        'mod_id'       => $parent_id,
        'table_id'     => $table_id,
    ];
}

/**
 * Returns all versions for a mod as normalized arrays.
 *
 * @param int $post_id Mod post ID.
 *
 * @return array[]
 */
function gta6mods_get_mod_versions($post_id) {
    gta6mods_ensure_initial_version_exists($post_id);

    $versions = [];

    foreach (gta6mods_get_version_posts($post_id) as $version_post) {
        $prepared = gta6mods_prepare_version_array($version_post);
        if (!empty($prepared)) {
            $versions[] = $prepared;
        }
    }

    return $versions;
}

/**
 * Returns the current version array for a mod.
 *
 * @param int $post_id Mod post ID.
 *
 * @return array
 */
function gta6mods_get_current_version($post_id) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return [];
    }

    $current_id = (int) get_post_meta($post_id, '_gta6mods_current_version_id', true);
    if ($current_id > 0) {
        $post = get_post($current_id);
        if ($post instanceof WP_Post && 'mod_version' === $post->post_type) {
            $prepared = gta6mods_prepare_version_array($post);
            if (!empty($prepared)) {
                return $prepared;
            }
        }
    }

    $versions = gta6mods_get_mod_versions($post_id);
    return isset($versions[0]) ? $versions[0] : [];
}

/**
 * Normalizes changelog entries.
 *
 * @param array|string $changelog Raw changelog data.
 *
 * @return array
 */
function gta6mods_normalize_changelog($changelog) {
    if (is_string($changelog)) {
        $decoded = json_decode($changelog, true);
        $changelog = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($changelog)) {
        return [];
    }

    $normalized = [];
    foreach ($changelog as $entry) {
        if (is_string($entry)) {
            $entry = trim(wp_strip_all_tags($entry));
        } elseif (is_array($entry) && isset($entry['text'])) {
            $entry = trim(wp_strip_all_tags($entry['text']));
        } else {
            continue;
        }

        if ('' !== $entry) {
            $normalized[] = $entry;
        }
    }

    return $normalized;
}

/**
 * Ensures that an attachment belongs to the target post.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $parent_id     Parent post ID.
 */
function gta6mods_assign_attachment_to_post($attachment_id, $parent_id) {
    $attachment_id = absint($attachment_id);
    $parent_id     = absint($parent_id);

    if ($attachment_id <= 0 || $parent_id <= 0) {
        return;
    }

    wp_update_post(
        [
            'ID'          => $attachment_id,
            'post_parent' => $parent_id,
        ]
    );
}

/**
 * Applies a pending mod update to the original mod post.
 *
 * @param int $update_post_id Update post ID.
 *
 * @return true|WP_Error
 */
function gta6mods_apply_mod_update($update_post_id) {
    $update_post_id = absint($update_post_id);
    if ($update_post_id <= 0) {
        return new WP_Error('invalid-update', __('Érvénytelen frissítés azonosító.', 'gta6-mods'));
    }

    $update_post = get_post($update_post_id);
    if (!$update_post instanceof WP_Post || 'mod_update' !== $update_post->post_type) {
        return new WP_Error('invalid-update', __('Érvénytelen frissítés.', 'gta6-mods'));
    }

    $mod_id = (int) get_post_meta($update_post_id, '_gta6mods_update_mod_id', true);
    if ($mod_id <= 0) {
        return new WP_Error('missing-mod', __('Hiányzik a kapcsolódó mod azonosítója.', 'gta6-mods'));
    }

    $mod_post = get_post($mod_id);
    $allowed_types = function_exists('gta6mods_get_mod_post_types') ? gta6mods_get_mod_post_types() : ['post'];

    if (!$mod_post instanceof WP_Post || !in_array($mod_post->post_type, $allowed_types, true)) {
        return new WP_Error('invalid-mod', __('A hivatkozott mod nem található.', 'gta6-mods'));
    }

    gta6mods_ensure_initial_version_exists($mod_id);

    $description_stored = get_post_meta($update_post_id, '_gta6mods_update_description', true);
    $description_stored = is_string($description_stored) ? $description_stored : '';
    $description_json   = get_post_meta($update_post_id, '_gta6mods_update_description_json', true);
    $description_json   = is_string($description_json) ? $description_json : '';
    $normalized_description_json = '' !== $description_json ? gta6_mods_normalize_editorjs_json($description_json) : '';

    $description = '' !== $normalized_description_json
        ? gta6_mods_editorjs_to_gutenberg_blocks($normalized_description_json)
        : wp_kses_post($description_stored);

    $tags = get_post_meta($update_post_id, '_gta6mods_update_tags', true);
    $tags = is_array($tags) ? array_values(array_filter(array_map('sanitize_text_field', $tags))) : [];

    $video_permission = get_post_meta($update_post_id, '_gta6mods_update_video_permission', true);
    $video_permission = is_string($video_permission) ? sanitize_key($video_permission) : 'moderate';

    $authors = get_post_meta($update_post_id, '_gta6mods_update_authors', true);
    $authors = is_array($authors) ? array_values(array_filter(array_map('sanitize_text_field', $authors))) : [];

    $deleted_screenshots = get_post_meta($update_post_id, '_gta6mods_update_deleted_screenshots', true);
    $deleted_screenshots = array_values(array_filter(array_map('absint', (array) $deleted_screenshots)));

    $gallery_order = get_post_meta($update_post_id, '_gta6mods_update_screenshot_order', true);
    $gallery_order = is_array($gallery_order) ? array_values(array_filter(array_map('sanitize_text_field', $gallery_order))) : [];

    $featured_identifier = get_post_meta($update_post_id, '_gta6mods_update_featured_identifier', true);
    $featured_identifier = is_string($featured_identifier) ? sanitize_text_field($featured_identifier) : '';

    $new_screenshots = get_post_meta($update_post_id, '_gta6mods_update_new_screenshots', true);
    $new_screenshots = is_array($new_screenshots) ? $new_screenshots : [];

    $new_version_number = get_post_meta($update_post_id, '_gta6mods_update_version_number', true);
    $new_version_number = is_string($new_version_number) ? trim($new_version_number) : '';

    $new_version_changelog = gta6mods_normalize_changelog(get_post_meta($update_post_id, '_gta6mods_update_changelog', true));
    $new_version_source    = get_post_meta($update_post_id, '_gta6mods_update_version_source', true);
    $new_version_source    = is_array($new_version_source) ? $new_version_source : [];

    $new_title = get_post_meta($update_post_id, '_gta6mods_update_mod_title', true);
    $new_title = is_string($new_title) ? trim($new_title) : '';

    $new_category_id = (int) get_post_meta($update_post_id, '_gta6mods_update_category_id', true);

    // Update core post fields.
    if ('' !== $description) {
        wp_update_post([
            'ID'           => $mod_id,
            'post_content' => $description,
        ]);

        if ('' !== $normalized_description_json) {
            gta6_mods_store_editorjs_payload($mod_id, $normalized_description_json);
        } else {
            gta6_mods_store_editorjs_payload($mod_id, gta6_mods_convert_content_to_editorjs($description));
        }
    }

    wp_set_post_terms($mod_id, $tags, 'post_tag', false);

    if ('' !== $new_title && $new_title !== get_the_title($mod_id)) {
        wp_update_post([
            'ID'         => $mod_id,
            'post_title' => $new_title,
        ]);
    }

    if ($new_category_id > 0) {
        $new_category_term = get_term($new_category_id, 'category');
        if ($new_category_term instanceof WP_Term && (!function_exists('gta6mods_is_allowed_category_id') || gta6mods_is_allowed_category_id($new_category_id))) {
            wp_set_post_terms($mod_id, [$new_category_term->term_id], 'category', false);
        }
    }

    if (!empty($authors)) {
        update_post_meta($mod_id, '_gta6mods_additional_authors', $authors);
    } else {
        delete_post_meta($mod_id, '_gta6mods_additional_authors');
    }

    update_post_meta($mod_id, '_gta6mods_video_permissions', $video_permission);

    // Handle gallery removals.
    $existing_removed = gta6mods_get_removed_gallery_ids($mod_id);
    $merged_removed   = array_unique(array_merge($existing_removed, $deleted_screenshots));
    gta6mods_set_removed_gallery_ids($mod_id, $merged_removed);

    $final_gallery_meta = [];
    $featured_attachment_id = 0;

    foreach ($gallery_order as $identifier) {
        if (strpos($identifier, 'existing:') === 0) {
            $attachment_id = absint(substr($identifier, 9));
            if ($attachment_id <= 0) {
                continue;
            }

            if (in_array($attachment_id, $merged_removed, true)) {
                continue;
            }

            $order_index = count($final_gallery_meta);
            $final_gallery_meta[] = [
                'attachment_id' => $attachment_id,
                'order'         => $order_index,
            ];

            if ($featured_identifier === $identifier) {
                $featured_attachment_id = $attachment_id;
            }
        } elseif (strpos($identifier, 'new:') === 0) {
            $temp_key = sanitize_key(substr($identifier, 4));
            if ('' === $temp_key || !isset($new_screenshots[$temp_key])) {
                continue;
            }

            $attachment_id = isset($new_screenshots[$temp_key]['attachment_id']) ? absint($new_screenshots[$temp_key]['attachment_id']) : 0;
            if ($attachment_id <= 0) {
                continue;
            }

            gta6mods_assign_attachment_to_post($attachment_id, $mod_id);

            $order_index = count($final_gallery_meta);
            $final_gallery_meta[] = [
                'attachment_id' => $attachment_id,
                'order'         => $order_index,
            ];

            if ($featured_identifier === $identifier) {
                $featured_attachment_id = $attachment_id;
            }
        }
    }

    if (!empty($final_gallery_meta)) {
        update_post_meta($mod_id, '_gta6mods_gallery_images', wp_json_encode($final_gallery_meta));
    } else {
        delete_post_meta($mod_id, '_gta6mods_gallery_images');
    }

    if ($featured_attachment_id <= 0 && !empty($final_gallery_meta)) {
        $featured_attachment_id = (int) $final_gallery_meta[0]['attachment_id'];
    }

    if ($featured_attachment_id > 0) {
        set_post_thumbnail($mod_id, $featured_attachment_id);
    }

    // Handle new version creation.
    if ('' !== $new_version_number && !empty($new_version_source)) {
        $version_post_id = wp_insert_post(
            [
                'post_type'   => 'mod_version',
                'post_status' => 'publish',
                'post_parent' => $mod_id,
                'post_title'  => sprintf('%s – %s', get_the_title($mod_id), $new_version_number),
            ],
            true
        );

        if (!is_wp_error($version_post_id) && $version_post_id > 0) {
            update_post_meta($version_post_id, '_gta6mods_version_number', $new_version_number);
            update_post_meta($version_post_id, '_gta6mods_version_parent', $mod_id);
            update_post_meta($version_post_id, '_gta6mods_version_changelog', $new_version_changelog);
            update_post_meta($version_post_id, '_gta6mods_version_download_count', 0);
            update_post_meta($version_post_id, '_gta6mods_version_source', $new_version_source);
            
            // ========== KRITIKUS RÉSZ - VÍRUSELLENŐRZÉSI LINK MENTÉSE ==========
            $new_version_scan_url = get_post_meta($update_post_id, '_gta6mods_update_version_scan_url', true);
            if (is_string($new_version_scan_url) && '' !== trim($new_version_scan_url)) {
                $validated_scan_url = esc_url_raw(trim($new_version_scan_url));
                if ($validated_scan_url && wp_http_validate_url($validated_scan_url)) {
                    update_post_meta($version_post_id, '_gta6mods_version_scan_url', $validated_scan_url);
                }
            }
            // ====================================================================
            
            update_post_meta($version_post_id, '_gta6mods_version_submitted_update', $update_post_id);

            $history = get_post_meta($mod_id, '_gta6mods_version_history', true);
            if (!is_array($history)) {
                $history = [];
            }
            array_unshift($history, $version_post_id);
            $history = array_values(array_unique(array_map('intval', $history)));
            update_post_meta($mod_id, '_gta6mods_version_history', $history);

            update_post_meta($mod_id, '_gta6mods_current_version_id', $version_post_id);
            update_post_meta($mod_id, '_gta6mods_mod_version', $new_version_number);

            if (isset($new_version_source['type']) && 'file' === $new_version_source['type'] && !empty($new_version_source['attachment_id'])) {
                $attachment_id = absint($new_version_source['attachment_id']);
                gta6mods_assign_attachment_to_post($attachment_id, $mod_id);

                $size_bytes = isset($new_version_source['size_bytes']) ? (int) $new_version_source['size_bytes'] : 0;
                $size_human = isset($new_version_source['size_human']) ? sanitize_text_field($new_version_source['size_human']) : ($size_bytes > 0 ? size_format((float) $size_bytes) : '');

                update_post_meta(
                    $mod_id,
                    '_gta6mods_mod_file',
                    [
                        'id'            => $attachment_id,
                        'attachment_id' => $attachment_id,
                        'url'           => wp_get_attachment_url($attachment_id),
                        'size_bytes'    => $size_bytes,
                        'size_human'    => $size_human,
                        'version_id'    => $version_post_id,
                    ]
                );
                delete_post_meta($mod_id, '_gta6mods_mod_external');
            } elseif (isset($new_version_source['type']) && 'external' === $new_version_source['type'] && !empty($new_version_source['url'])) {
                $size_bytes = isset($new_version_source['size_bytes']) ? (int) $new_version_source['size_bytes'] : 0;
                $size_human = isset($new_version_source['size_human']) ? sanitize_text_field($new_version_source['size_human']) : ($size_bytes > 0 ? size_format((float) $size_bytes) : '');

                update_post_meta(
                    $mod_id,
                    '_gta6mods_mod_external',
                    [
                        'url'        => esc_url_raw($new_version_source['url']),
                        'size_bytes' => $size_bytes,
                        'size_human' => $size_human,
                        'version_id' => $version_post_id,
                    ]
                );
                delete_post_meta($mod_id, '_gta6mods_mod_file');
            }

            $attachment_for_version = 0;
            if (isset($new_version_source['type']) && 'file' === $new_version_source['type'] && !empty($new_version_source['attachment_id'])) {
                $attachment_for_version = (int) $new_version_source['attachment_id'];
            }

            if ($attachment_for_version > 0) {
                $changelog_text = '';
                if (!empty($new_version_changelog)) {
                    $lines = [];
                    foreach ($new_version_changelog as $entry) {
                        if (!is_string($entry)) {
                            continue;
                        }
                        $entry = trim(wp_strip_all_tags($entry));
                        if ('' !== $entry) {
                            $lines[] = $entry;
                        }
                    }
                    if (!empty($lines)) {
                        $changelog_text = implode("\n", $lines);
                    }
                }

                $inserted_version_id = GTA6Mods_Mod_Versions::insert_version(
                    $mod_id,
                    $new_version_number,
                    $attachment_for_version,
                    $changelog_text,
                    null,
                    true
                );

                if ($inserted_version_id > 0) {
                    update_post_meta($version_post_id, '_gta6mods_version_table_id', $inserted_version_id);
                }
            }
        }
    }

    return true;
}

/**
 * Stores structured data about new screenshots uploaded with an update.
 *
 * @param int   $update_post_id Update post ID.
 * @param array $screenshots    Screenshot data indexed by temporary key.
 */
function gta6mods_set_update_new_screenshots($update_post_id, $screenshots) {
    $normalized = [];

    foreach ((array) $screenshots as $key => $data) {
        if (!is_array($data)) {
            continue;
        }

        $attachment_id = isset($data['attachment_id']) ? absint($data['attachment_id']) : 0;
        if ($attachment_id <= 0) {
            continue;
        }

        $normalized[sanitize_key($key)] = [
            'attachment_id' => $attachment_id,
        ];
    }

    update_post_meta($update_post_id, '_gta6mods_update_new_screenshots', $normalized);
}

/**
 * Normalizes and stores version source data for an update.
 *
 * @param int   $update_post_id Update post ID.
 * @param array $source         Source data.
 */
function gta6mods_set_update_version_source($update_post_id, $source) {
    $normalized = [];

    if (is_array($source)) {
        $type = isset($source['type']) ? sanitize_key($source['type']) : '';
        if ('file' === $type) {
            $attachment_id = isset($source['attachment_id']) ? absint($source['attachment_id']) : 0;
            if ($attachment_id > 0) {
                $normalized = [
                    'type'          => 'file',
                    'attachment_id' => $attachment_id,
                    'size_bytes'    => isset($source['size_bytes']) ? (int) $source['size_bytes'] : 0,
                    'size_human'    => isset($source['size_human']) ? sanitize_text_field($source['size_human']) : '',
                ];
            }
        } elseif ('external' === $type && !empty($source['url'])) {
            $normalized = [
                'type'       => 'external',
                'url'        => esc_url_raw($source['url']),
                'size_bytes' => isset($source['size_bytes']) ? (int) $source['size_bytes'] : 0,
                'size_human' => isset($source['size_human']) ? sanitize_text_field($source['size_human']) : '',
            ];
        }
    }

    if (!empty($normalized)) {
        update_post_meta($update_post_id, '_gta6mods_update_version_source', $normalized);
    } else {
        delete_post_meta($update_post_id, '_gta6mods_update_version_source');
    }
}

/**
 * Helper to format update data for admin display.
 *
 * @param int $update_post_id Update post ID.
 *
 * @return array
 */
function gta6mods_get_update_payload($update_post_id) {
    $update_post_id = absint($update_post_id);
    if ($update_post_id <= 0) {
        return [];
    }

    $mod_id = (int) get_post_meta($update_post_id, '_gta6mods_update_mod_id', true);
    $mod_post = $mod_id > 0 ? get_post($mod_id) : null;

    $payload = [
        'mod_id'             => $mod_id,
        'mod_title'          => $mod_post instanceof WP_Post ? get_the_title($mod_post) : '',
        'new_title'          => get_post_meta($update_post_id, '_gta6mods_update_mod_title', true),
        'new_category_id'    => (int) get_post_meta($update_post_id, '_gta6mods_update_category_id', true),
        'description'        => get_post_meta($update_post_id, '_gta6mods_update_description', true),
        'description_json'   => get_post_meta($update_post_id, '_gta6mods_update_description_json', true),
        'tags'               => get_post_meta($update_post_id, '_gta6mods_update_tags', true),
        'video_permission'   => get_post_meta($update_post_id, '_gta6mods_update_video_permission', true),
        'authors'            => get_post_meta($update_post_id, '_gta6mods_update_authors', true),
        'deleted_screenshots'=> get_post_meta($update_post_id, '_gta6mods_update_deleted_screenshots', true),
        'gallery_order'      => get_post_meta($update_post_id, '_gta6mods_update_screenshot_order', true),
        'featured_identifier'=> get_post_meta($update_post_id, '_gta6mods_update_featured_identifier', true),
        'new_screenshots'    => get_post_meta($update_post_id, '_gta6mods_update_new_screenshots', true),
        'version_number'     => get_post_meta($update_post_id, '_gta6mods_update_version_number', true),
        'changelog'          => get_post_meta($update_post_id, '_gta6mods_update_changelog', true),
        'version_source'     => get_post_meta($update_post_id, '_gta6mods_update_version_source', true),
        'version_scan_url'   => get_post_meta($update_post_id, '_gta6mods_update_version_scan_url', true),
    ];

    return $payload;
}

/**
 * Determines whether a user jogosultságai elégségesek a függőben lévő frissítések megtekintéséhez.
 *
 * @param int $mod_id  Mod bejegyzés azonosítója.
 * @param int $user_id Opcionális felhasználói azonosító.
 *
 * @return bool
 */
function gta6mods_user_can_view_pending_updates($mod_id, $user_id = 0) {
    $mod_id = absint($mod_id);

    if ($mod_id <= 0) {
        return false;
    }

    $user_id = (int) ($user_id > 0 ? $user_id : get_current_user_id());

    if ($user_id <= 0) {
        return false;
    }

    if (user_can($user_id, 'manage_options') || user_can($user_id, 'edit_others_posts')) {
        return true;
    }

    return user_can($user_id, 'edit_post', $mod_id);
}

/**
 * Visszaadja a modhoz tartozó függőben lévő frissítések listáját a megfelelő jogosultságok birtokában.
 *
 * @param int   $mod_id Mod bejegyzés azonosítója.
 * @param array $args   Opcionális lekérdezési paraméterek.
 *
 * @return array
 */
function gta6mods_get_pending_updates_for_mod($mod_id, $args = []) {
    $mod_id = absint($mod_id);

    if ($mod_id <= 0) {
        return [];
    }

    if (!gta6mods_user_can_view_pending_updates($mod_id)) {
        return [];
    }

    $defaults = [
        'posts_per_page' => -1,
        'offset'         => 0,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ];

    $query_args = wp_parse_args($args, $defaults);

    $requested_orderby = strtolower((string) $query_args['orderby']);
    if (!in_array($requested_orderby, ['date', 'modified', 'id'], true)) {
        $requested_orderby = 'date';
    }

    $order = ('ASC' === strtoupper($query_args['order'])) ? 'ASC' : 'DESC';

    $fields = strtolower((string) $query_args['fields']);
    if ('ids' === $fields) {
        $fields = 'ids';
    } elseif ('all_with_meta' === $fields) {
        $fields = 'all_with_meta';
    } else {
        $fields = 'all';
    }

    $prepared_args = [
        'post_type'        => 'mod_update',
        'post_status'      => 'pending',
        'posts_per_page'   => (int) $query_args['posts_per_page'],
        'offset'           => max(0, (int) $query_args['offset']),
        'orderby'          => ('id' === $requested_orderby) ? 'ID' : $requested_orderby,
        'order'            => $order,
        'fields'           => $fields,
        'no_found_rows'    => true,
        'suppress_filters' => false,
        'meta_query'       => [
            [
                'key'   => '_gta6mods_update_mod_id',
                'value' => $mod_id,
            ],
        ],
    ];

    return get_posts($prepared_args);
}

/**
 * Checks whether the provided mod has at least one pending update request.
 *
 * @param int $mod_id Mod post ID.
 *
 * @return bool
 */
function gta6mods_mod_has_pending_update($mod_id) {
    global $wpdb;

    $mod_id = absint($mod_id);

    if ($mod_id <= 0) {
        return false;
    }

    if (gta6mods_user_can_view_pending_updates($mod_id)) {
        $pending = gta6mods_get_pending_updates_for_mod($mod_id, [
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        return !empty($pending);
    }

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(1)
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
                AND p.post_status = 'pending'
                AND pm.meta_key = %s
                AND pm.meta_value = %d",
            'mod_update',
            '_gta6mods_update_mod_id',
            $mod_id
        )
    );

    return $count > 0;
}

/**
 * Determines if a user can bypass the pending update submission lock.
 *
 * @param int $user_id Optional user ID. Defaults to current user.
 *
 * @return bool
 */
function gta6mods_user_can_bypass_pending_lock($user_id = 0) {
    $user_id = absint($user_id);

    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }

    if ($user_id <= 0) {
        return false;
    }

    return user_can($user_id, 'manage_options') || user_can($user_id, 'edit_others_posts');
}
