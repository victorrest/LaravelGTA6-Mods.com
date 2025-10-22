<?php
/**
 * Admin area functions (meta boxes, taxonomy fields).
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GTA6_MODS_ACCOUNT_DELETION_META_KEY')) {
    define('GTA6_MODS_ACCOUNT_DELETION_META_KEY', '_gta6mods_account_deletion');
}

if (!defined('GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY')) {
    define('GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY', '_gta6mods_account_deletion_content');
}

if (!defined('GTA6_MODS_DELETED_COMMENT_META_KEY')) {
    define('GTA6_MODS_DELETED_COMMENT_META_KEY', '_gta6mods_deleted_comment');
}

/**
 * Renders Editor.js JSON data to basic HTML for preview.
 *
 * @param string $json_data The JSON string from Editor.js.
 * @return string The content formatted as simple HTML.
 */
function gta6mods_render_editorjs_to_html($json_data) {
    if (empty($json_data)) {
        return '';
    }

    $data = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['blocks']) || !is_array($data['blocks'])) {
        return '';
    }

    $html = '<div class="prose max-w-none">';
    foreach ($data['blocks'] as $block) {
        $type = isset($block['type']) ? $block['type'] : 'paragraph';
        $block_data = isset($block['data']) ? $block['data'] : [];

        switch ($type) {
            case 'header':
                $level = isset($block_data['level']) ? (int) $block_data['level'] : 2;
                $text = isset($block_data['text']) ? $block_data['text'] : '';
                $html .= sprintf('<h%d>%s</h%d>', $level, wp_kses_post($text), $level);
                break;

            case 'paragraph':
                $text = isset($block_data['text']) ? $block_data['text'] : '';
                $html .= '<p>' . wp_kses_post($text) . '</p>';
                break;

            case 'list':
                $style = isset($block_data['style']) && $block_data['style'] === 'ordered' ? 'ol' : 'ul';
                $items = isset($block_data['items']) && is_array($block_data['items']) ? $block_data['items'] : [];
                if (!empty($items)) {
                    $html .= "<{$style}>";
                    foreach ($items as $item) {
                        $html .= '<li>' . wp_kses_post($item) . '</li>';
                    }
                    $html .= "</{$style}>";
                }
                break;

            case 'quote':
                $text = isset($block_data['text']) ? $block_data['text'] : '';
                $caption = isset($block_data['caption']) ? $block_data['caption'] : '';
                $html .= '<blockquote><p>' . wp_kses_post($text) . '</p>';
                if ($caption) {
                    $html .= '<footer>' . wp_kses_post($caption) . '</footer>';
                }
                $html .= '</blockquote>';
                break;

            case 'delimiter':
                $html .= '<hr>';
                break;
            
            case 'table':
                 $withHeadings = !empty($block_data['withHeadings']);
                 $content = isset($block_data['content']) && is_array($block_data['content']) ? $block_data['content'] : [];
                 
                 if (!empty($content)) {
                     $html .= '<table>';
                     
                     if ($withHeadings) {
                         $header_row = array_shift($content);
                         if ($header_row && is_array($header_row)) {
                             $html .= '<thead><tr>';
                             foreach ($header_row as $cell) {
                                 $html .= '<th>' . wp_kses_post($cell) . '</th>';
                             }
                             $html .= '</tr></thead>';
                         }
                     }
 
                     if(!empty($content)) {
                         $html .= '<tbody>';
                         foreach ($content as $row) {
                             if (!is_array($row)) continue;
                             $html .= '<tr>';
                             foreach ($row as $cell) {
                                 $html .= '<td>' . wp_kses_post($cell) . '</td>';
                             }
                             $html .= '</tr>';
                         }
                         $html .= '</tbody>';
                     }

                     $html .= '</table>';
                 }
                 break;
            
            case 'embed':
            case 'youtube':
                $video_id = gta6_mods_extract_youtube_id($block_data['url'] ?? $block_data['source'] ?? $block_data['embed'] ?? '');
                if ($video_id) {
                    $embed_url = sprintf('https://www.youtube.com/embed/%s?rel=0', $video_id);
                    $html .= '<div class="responsive-video-wrapper"><iframe src="' . esc_url($embed_url) . '" frameborder="0" allowfullscreen></iframe></div>';
                    if (!empty($block_data['caption'])) {
                         $html .= '<figcaption>' . wp_kses_post($block_data['caption']) . '</figcaption>';
                    }
                }
                break;

            case 'code':
                $code_content = isset($block_data['code']) ? $block_data['code'] : '';
                $html .= '<pre><code>' . esc_html($code_content) . '</code></pre>';
                break;
        }
    }
    $html .= '</div>';

    return $html;
}


function gta6_mods_get_category_filter_chip_meta($term_id) {
    $stored = get_term_meta($term_id, 'gta6_mods_filter_chips', true);

    if (!is_array($stored)) {
        return [];
    }

    $chips = [];

    foreach ($stored as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = isset($item['label']) ? sanitize_text_field($item['label']) : '';
        $url = isset($item['url']) ? esc_url_raw($item['url']) : '';

        if ($label === '' || $url === '') {
            continue;
        }

        $chips[] = [
            'label' => $label,
            'url'   => $url,
        ];
    }

    return $chips;
}

function gta6_mods_render_category_filter_chip_fields($term = null) {
    $chips = [];

    if ($term instanceof WP_Term) {
        $chips = gta6_mods_get_category_filter_chip_meta($term->term_id);
    }

    if (empty($chips)) {
        $chips = [
            [
                'label' => '',
                'url'   => '',
            ],
        ];
    }

    wp_nonce_field('gta6_mods_save_filter_chips', 'gta6_mods_filter_chips_nonce');

    ?>
    <div id="gta6-mods-filter-chips-wrapper">
        <?php foreach ($chips as $index => $chip) : ?>
            <div class="gta6-mods-filter-chip-row" data-index="<?php echo esc_attr((string) $index); ?>">
                <input type="text" name="gta6_mods_filter_chips[<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr($chip['label']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Címke címe', 'gta6-mods'); ?>" />
                <input type="url" name="gta6_mods_filter_chips[<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr($chip['url']); ?>" class="regular-text" placeholder="<?php esc_attr_e('https://példa.hu', 'gta6-mods'); ?>" />
                <button type="button" class="button-link-delete gta6-mods-remove-filter-chip" aria-label="<?php esc_attr_e('Elem eltávolítása', 'gta6-mods'); ?>">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="button" id="gta6-mods-add-filter-chip"><?php esc_html_e('Új elem hozzáadása', 'gta6-mods'); ?></button>
    <p class="description"><?php esc_html_e('Állítsd be a kategória saját gyorsszűrő linkjeit cím és URL párok megadásával.', 'gta6-mods'); ?></p>
    <script>
        (function() {
            const wrapper = document.getElementById('gta6-mods-filter-chips-wrapper');
            const addButton = document.getElementById('gta6-mods-add-filter-chip');

            if (!wrapper || !addButton) {
                return;
            }

            addButton.addEventListener('click', () => {
                const index = wrapper.querySelectorAll('.gta6-mods-filter-chip-row').length;
                const row = document.createElement('div');
                row.className = 'gta6-mods-filter-chip-row';
                row.innerHTML = `
                    <input type="text" name="gta6_mods_filter_chips[${index}][label]" class="regular-text" placeholder="<?php echo esc_js(esc_html__('Címke címe', 'gta6-mods')); ?>" />
                    <input type="url" name="gta6_mods_filter_chips[${index}][url]" class="regular-text" placeholder="<?php echo esc_js(esc_html__('https://példa.hu', 'gta6-mods')); ?>" />
                    <button type="button" class="button-link-delete gta6-mods-remove-filter-chip" aria-label="<?php echo esc_js(esc_html__('Elem eltávolítása', 'gta6-mods')); ?>">&times;</button>
                `;
                wrapper.appendChild(row);
            });

            wrapper.addEventListener('click', (event) => {
                if (event.target.classList.contains('gta6-mods-remove-filter-chip')) {
                    event.preventDefault();
                    const row = event.target.closest('.gta6-mods-filter-chip-row');
                    if (row && wrapper.querySelectorAll('.gta6-mods-filter-chip-row').length > 1) {
                        row.remove();
                    } else if (row) {
                        const inputs = row.querySelectorAll('input');
                        inputs.forEach((input) => {
                            input.value = '';
                        });
                    }
                }
            });
        })();
    </script>
    <style>
        #gta6-mods-filter-chips-wrapper {
            display: grid;
            gap: 8px;
            max-width: 640px;
        }

        #gta6-mods-filter-chips-wrapper .gta6-mods-filter-chip-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 8px;
            align-items: center;
        }

        #gta6-mods-filter-chips-wrapper .gta6-mods-filter-chip-row .button-link-delete {
            color: #b32d2e;
            font-size: 18px;
            text-decoration: none;
        }
    </style>
    <?php
}

function gta6mods_user_has_pending_account_deletion($user_id) {
    $data = gta6_mods_get_account_deletion_data($user_id);

    return is_array($data) && isset($data['status']) && 'pending' === $data['status'];
}

function gta6mods_user_has_finalized_account_deletion($user_id) {
    $data = gta6_mods_get_account_deletion_data($user_id);

    return is_array($data) && isset($data['status']) && 'deleted' === $data['status'];
}

function gta6mods_get_account_status_meta_pattern($status) {
    $status = sanitize_key($status);

    if ('' === $status) {
        return '';
    }

    return '"status";s:' . strlen($status) . ':"' . $status . '"';
}

function gta6mods_count_pending_account_deletions() {
    $pattern = gta6mods_get_account_status_meta_pattern('pending');

    if ('' === $pattern) {
        return 0;
    }

    $query = new WP_User_Query([
        'number'      => 1,
        'fields'      => 'ids',
        'meta_query'  => [
            [
                'key'     => GTA6_MODS_ACCOUNT_DELETION_META_KEY,
                'value'   => $pattern,
                'compare' => 'LIKE',
            ],
        ],
        'count_total' => true,
        'gta6mods_skip_status_filter' => true,
    ]);

    return (int) $query->get_total();
}

function gta6mods_count_deleted_accounts() {
    $pattern = gta6mods_get_account_status_meta_pattern('deleted');

    if ('' === $pattern) {
        return 0;
    }

    $query = new WP_User_Query([
        'number'       => 1,
        'fields'       => 'ids',
        'meta_query'   => [
            [
                'key'     => GTA6_MODS_ACCOUNT_DELETION_META_KEY,
                'value'   => $pattern,
                'compare' => 'LIKE',
            ],
        ],
        'count_total'  => true,
        'gta6mods_skip_status_filter' => true,
    ]);

    return (int) $query->get_total();
}

function gta6mods_users_account_deletion_views($views) {
    if (!current_user_can('list_users')) {
        return $views;
    }

    $status = isset($_GET['gta6mods_account_status']) ? sanitize_key($_GET['gta6mods_account_status']) : '';

    $pending_count = gta6mods_count_pending_account_deletions();
    $pending_label = sprintf(
        '%s <span class="count">(%s)</span>',
        esc_html__('Törlésre vár', 'gta6-mods'),
        number_format_i18n($pending_count)
    );

    $pending_url = add_query_arg('gta6mods_account_status', 'pending', 'users.php');
    $pending_attr = ('pending' === $status) ? ' class="current" aria-current="page"' : '';
    $views['gta6mods_pending'] = '<a href="' . esc_url($pending_url) . '"' . $pending_attr . '>' . $pending_label . '</a>';

    $deleted_count = gta6mods_count_deleted_accounts();
    $deleted_label = sprintf(
        '%s <span class="count">(%s)</span>',
        esc_html__('Törölt', 'gta6-mods'),
        number_format_i18n($deleted_count)
    );

    $deleted_url  = add_query_arg('gta6mods_account_status', 'deleted', 'users.php');
    $deleted_attr = ('deleted' === $status) ? ' class="current" aria-current="page"' : '';
    $views['gta6mods_deleted'] = '<a href="' . esc_url($deleted_url) . '"' . $deleted_attr . '>' . $deleted_label . '</a>';

    return $views;
}
add_filter('views_users', 'gta6mods_users_account_deletion_views');

function gta6mods_filter_account_status_users_query($query) {
    if (!is_admin()) {
        return;
    }

    if ($query->get('gta6mods_skip_status_filter')) {
        return;
    }

    $status = isset($_GET['gta6mods_account_status']) ? sanitize_key($_GET['gta6mods_account_status']) : '';
    if (!in_array($status, ['pending', 'deleted'], true)) {
        return;
    }

    $meta_query   = (array) $query->get('meta_query');
    $pattern = gta6mods_get_account_status_meta_pattern($status);

    if ('' === $pattern) {
        return;
    }

    $meta_query[] = [
        'key'     => GTA6_MODS_ACCOUNT_DELETION_META_KEY,
        'value'   => $pattern,
        'compare' => 'LIKE',
    ];

    $query->set('meta_query', $meta_query);
}
add_action('pre_get_users', 'gta6mods_filter_account_status_users_query');

function gta6mods_add_restore_user_row_action($actions, $user) {
    if (!current_user_can('delete_users')) {
        return $actions;
    }

    $deletion_data = gta6_mods_get_account_deletion_data($user->ID);
    $status        = is_array($deletion_data) && isset($deletion_data['status']) ? $deletion_data['status'] : '';

    if (!in_array($status, ['pending', 'deleted'], true)) {
        return $actions;
    }

    if ('pending' === $status) {
        $cancel_args = [
            'action'  => 'gta6mods_restore_user',
            'user_id' => (int) $user->ID,
        ];

        if (isset($_GET['gta6mods_account_status'])) {
            $cancel_args['gta6mods_account_status'] = sanitize_key($_GET['gta6mods_account_status']);
        }

        $cancel_url = add_query_arg($cancel_args, 'users.php');
        $cancel_url = wp_nonce_url($cancel_url, 'gta6mods_restore_user_' . $user->ID);

        $actions['gta6mods_cancel'] = '<a href="' . esc_url($cancel_url) . '">' . esc_html__('Cancel deletion request', 'gta6-mods') . '</a>';

        $finalize_args = [
            'action'  => 'gta6mods_finalize_user',
            'user_id' => (int) $user->ID,
        ];

        if (isset($_GET['gta6mods_account_status'])) {
            $finalize_args['gta6mods_account_status'] = sanitize_key($_GET['gta6mods_account_status']);
        }

        $finalize_url = add_query_arg($finalize_args, 'users.php');
        $finalize_url = wp_nonce_url($finalize_url, 'gta6mods_finalize_user_' . $user->ID);

        $actions['gta6mods_finalize'] = '<a href="' . esc_url($finalize_url) . '">' . esc_html__('Deactivate account', 'gta6-mods') . '</a>';

        return $actions;
    }

    $restore_args = [
        'action'  => 'gta6mods_restore_user',
        'user_id' => (int) $user->ID,
    ];

    if (isset($_GET['gta6mods_account_status'])) {
        $restore_args['gta6mods_account_status'] = sanitize_key($_GET['gta6mods_account_status']);
    }

    $restore_url = add_query_arg($restore_args, 'users.php');
    $restore_url = wp_nonce_url($restore_url, 'gta6mods_restore_user_' . $user->ID);

    $actions['gta6mods_restore'] = '<a href="' . esc_url($restore_url) . '">' . esc_html__('Restore account', 'gta6-mods') . '</a>';

    return $actions;
}
add_filter('user_row_actions', 'gta6mods_add_restore_user_row_action', 10, 2);

function gta6mods_handle_restore_user_action() {
    if (!is_admin()) {
        return;
    }

    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    if ('gta6mods_restore_user' !== $action) {
        return;
    }

    if (!current_user_can('delete_users')) {
        wp_die(__('You are not allowed to perform this action.', 'gta6-mods'));
    }

    $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    if ($user_id <= 0) {
        return;
    }

    check_admin_referer('gta6mods_restore_user_' . $user_id);

    if (function_exists('gta6mods_restore_user_content')) {
        gta6mods_restore_user_content($user_id);
    } else {
        delete_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY);
    }

    delete_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_META_KEY);

    $redirect_args = [];
    if (isset($_GET['gta6mods_account_status'])) {
        $redirect_args['gta6mods_account_status'] = sanitize_key($_GET['gta6mods_account_status']);
    }

    $redirect_args['gta6mods_restored'] = '1';

    wp_safe_redirect(add_query_arg($redirect_args, admin_url('users.php')));
    exit;
}
add_action('load-users.php', 'gta6mods_handle_restore_user_action');

function gta6mods_show_restore_notice() {
    if (!is_admin()) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || 'users' !== $screen->id) {
        return;
    }

    if (isset($_GET['gta6mods_restored'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Account restored successfully.', 'gta6-mods') . '</p></div>';
    }

    if (isset($_GET['gta6mods_finalized'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Account deactivated successfully.', 'gta6-mods') . '</p></div>';
    }
}
add_action('admin_notices', 'gta6mods_show_restore_notice');

function gta6mods_handle_finalize_user_action() {
    if (!is_admin()) {
        return;
    }

    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    if ('gta6mods_finalize_user' !== $action) {
        return;
    }

    if (!current_user_can('delete_users')) {
        wp_die(__('You are not allowed to perform this action.', 'gta6-mods'));
    }

    $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    if ($user_id <= 0) {
        return;
    }

    check_admin_referer('gta6mods_finalize_user_' . $user_id);

    $existing = gta6_mods_get_account_deletion_data($user_id);
    if (!is_array($existing) || !isset($existing['status']) || 'pending' !== $existing['status']) {
        return;
    }

    gta6_mods_mark_account_as_deleted($user_id, 'moderated', $existing);

    if (function_exists('wp_destroy_user_sessions')) {
        wp_destroy_user_sessions($user_id);
    }

    $redirect_args = [];
    if (isset($_GET['gta6mods_account_status'])) {
        $redirect_args['gta6mods_account_status'] = sanitize_key($_GET['gta6mods_account_status']);
    }

    $redirect_args['gta6mods_finalized'] = '1';

    wp_safe_redirect(add_query_arg($redirect_args, admin_url('users.php')));
    exit;
}
add_action('load-users.php', 'gta6mods_handle_finalize_user_action');

function gta6_mods_category_add_filter_chip_fields($taxonomy) {
    ?>
    <div class="form-field">
        <label for="gta6-mods-filter-chips"><?php esc_html_e('Gyorsszűrő linkek', 'gta6-mods'); ?></label>
        <?php gta6_mods_render_category_filter_chip_fields(); ?>
    </div>
    <?php
}
add_action('category_add_form_fields', 'gta6_mods_category_add_filter_chip_fields', 10, 1);

function gta6_mods_category_edit_filter_chip_fields($term) {
    ?>
    <tr class="form-field">
        <th scope="row"><?php esc_html_e('Gyorsszűrő linkek', 'gta6-mods'); ?></th>
        <td>
            <?php gta6_mods_render_category_filter_chip_fields($term); ?>
        </td>
    </tr>
    <?php
}
add_action('category_edit_form_fields', 'gta6_mods_category_edit_filter_chip_fields', 10, 1);

function gta6_mods_save_category_filter_chips($term_id) {
    if (!current_user_can('manage_categories')) {
        return;
    }

    if (!isset($_POST['gta6_mods_filter_chips_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gta6_mods_filter_chips_nonce'])), 'gta6_mods_save_filter_chips')) {
        return;
    }

    $submitted = isset($_POST['gta6_mods_filter_chips']) ? wp_unslash($_POST['gta6_mods_filter_chips']) : [];

    if (!is_array($submitted)) {
        $submitted = [];
    }

    $chips = [];

    foreach ($submitted as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = isset($item['label']) ? sanitize_text_field(wp_unslash($item['label'])) : '';
        $url_raw = isset($item['url']) ? wp_unslash($item['url']) : '';
        $url_raw = is_string($url_raw) ? $url_raw : '';
        $url = $url_raw !== '' ? esc_url_raw($url_raw) : '';

        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }

        $label_length = function_exists('mb_strlen') ? mb_strlen($label) : strlen($label);

        if ($label_length > 100) {
            $label = function_exists('mb_substr') ? mb_substr($label, 0, 100) : substr($label, 0, 100);
        }

        if ($label === '' || $url === '') {
            continue;
        }

        $chips[] = [
            'label' => $label,
            'url'   => $url,
        ];
    }

    if (empty($chips)) {
        delete_term_meta($term_id, 'gta6_mods_filter_chips');
        return;
    }

    update_term_meta($term_id, 'gta6_mods_filter_chips', $chips);
}
add_action('created_category', 'gta6_mods_save_category_filter_chips', 10, 1);
add_action('edited_category', 'gta6_mods_save_category_filter_chips', 10, 1);

function gta6_mods_clear_front_page_cache($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'post') {
        return;
    }

    delete_transient('gta6_front_page_data_v1');
}
add_action('save_post', 'gta6_mods_clear_front_page_cache', 10, 1);
add_action('delete_post', 'gta6_mods_clear_front_page_cache', 10, 1);

function gta6_mods_register_post_meta_box() {
    add_meta_box(
        'gta6_mods_post_settings',
        __('Mod beállítások', 'gta6-mods'),
        'gta6_mods_render_post_meta_box',
        'post',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'gta6_mods_register_post_meta_box');

function gta6_mods_render_post_meta_box($post) {
    wp_nonce_field('gta6_mods_post_settings', 'gta6_mods_post_settings_nonce');

    $is_featured         = (bool) get_post_meta($post->ID, GTA6_MODS_FEATURED_META_KEY, true);
    $version             = get_post_meta($post->ID, '_gta6mods_mod_version', true);
    $featured_timestamp  = gta6mods_get_featured_timestamp($post->ID);
    $timestamp_output    = '';

    if ($is_featured && $featured_timestamp) {
        $timestamp_value = (int) preg_replace('/[^0-9]/', '', $featured_timestamp);
        if ($timestamp_value > 0) {
            $timestamp_output = date_i18n('Y-m-d H:i:s', (int) floor($timestamp_value / 1000000));
        }
    }

    ?>
    <p>
        <label for="gta6mods-is-featured">
            <input type="checkbox" id="gta6mods-is-featured" name="gta6mods_is_featured" value="1" <?php checked($is_featured); ?>>
            <?php esc_html_e('Bejegyzés megjelölése kiemeltként', 'gta6-mods'); ?>
        </label>
    </p>
    <?php if ($timestamp_output) : ?>
        <p class="description"><?php esc_html_e('Kiemelt beállítás ideje:', 'gta6-mods'); ?> <?php echo esc_html($timestamp_output); ?></p>
    <?php endif; ?>
    <p>
        <label for="gta6mods-mod-version" class="screen-reader-text"><?php esc_html_e('Mod verziója', 'gta6-mods'); ?></label>
        <input type="text" id="gta6mods-mod-version" name="gta6mods_mod_version" value="<?php echo esc_attr($version); ?>" class="widefat" placeholder="<?php esc_attr_e('Verzió (pl. 1.2a)', 'gta6-mods'); ?>">
    </p>
    <?php
}

function gta6_mods_save_post_meta_box($post_id) {
    if (!isset($_POST['gta6_mods_post_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gta6_mods_post_settings_nonce'])), 'gta6_mods_post_settings')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $is_featured = isset($_POST['gta6mods_is_featured']) ? 1 : 0;

    if ($is_featured) {
        update_post_meta($post_id, GTA6_MODS_FEATURED_META_KEY, 1);
        gta6mods_set_featured_timestamp($post_id);
    } else {
        delete_post_meta($post_id, GTA6_MODS_FEATURED_META_KEY);
        gta6mods_clear_featured_timestamp($post_id);
    }

    if (isset($_POST['gta6mods_mod_version'])) {
        $version = sanitize_text_field(wp_unslash($_POST['gta6mods_mod_version']));
        if ('' !== $version) {
            update_post_meta($post_id, '_gta6mods_mod_version', $version);
        } else {
            delete_post_meta($post_id, '_gta6mods_mod_version');
        }
    }
}
add_action('save_post_post', 'gta6_mods_save_post_meta_box');

// --- MOD UPDATE METABOXES --- //

function gta6mods_register_mod_update_metaboxes() {
    remove_meta_box('submitdiv', 'mod_update', 'side');
    add_meta_box('gta6mods_update_actions', __('Moderátori műveletek', 'gta6-mods'), 'gta6mods_render_update_actions_metabox', 'mod_update', 'side', 'high');
    add_meta_box('gta6mods_update_overview', __('Frissítési kérelem', 'gta6-mods'), 'gta6mods_render_update_overview_metabox', 'mod_update', 'normal', 'high');
}
add_action('add_meta_boxes', 'gta6mods_register_mod_update_metaboxes');

function gta6mods_render_update_actions_metabox(WP_Post $post) {
    $approve_confirm = __('Biztosan elfogadod a frissítést?', 'gta6-mods');
    $reject_confirm  = __('Biztosan elutasítod a frissítést?', 'gta6-mods');
    $status          = get_post_status($post);

    wp_nonce_field('gta6mods_update_review_meta', 'gta6mods_update_review_nonce');

    ?>
    <div class="submitbox" id="submitpost">
        <div id="minor-publishing">
            <div id="misc-publishing-actions">
                <div class="misc-pub-section misc-pub-post-status">
                    <?php esc_html_e('Státusz:'); ?>
                    <strong id="post-status-display"><?php echo esc_html(get_post_status_object($status)->label); ?></strong>
                </div>
                <div class="misc-pub-section curtime misc-pub-curtime">
                    <span id="timestamp"><?php printf(esc_html__('Beküldve: %s'), '<b>' . esc_html(get_the_date( 'Y.m.d. H:i:s', $post )) . '</b>'); ?></span>
                </div>
            </div>
            <div class="clear"></div>
        </div>

        <div id="major-publishing-actions">
            <?php if ('pending' === $status) : ?>
                <div id="publishing-action">
                    <span class="spinner"></span>
                    <button type="button" class="button button-large" id="gta6mods_reject_btn" data-handler="gta6mods_reject_update">
                        <?php esc_html_e('Elutasítás', 'gta6-mods'); ?>
                    </button>
                    <button type="button" class="button button-primary button-large" id="gta6mods_approve_btn" data-handler="gta6mods_approve_update">
                        <?php esc_html_e('Elfogadás', 'gta6-mods'); ?>
                    </button>
                </div>
            <?php endif; ?>
            <div class="clear"></div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const approveBtn = document.getElementById('gta6mods_approve_btn');
        const rejectBtn = document.getElementById('gta6mods_reject_btn');

        if (!approveBtn || !rejectBtn) {
            return;
        }

        const handleAction = (event, handler, confirmMsg) => {
            const scanInput = document.getElementById('gta6mods_update_version_scan_url');
            if (handler === 'gta6mods_approve_update' && scanInput) {
                const isVersionAttempt = document.getElementById('gta6-tab-version-content').style.display !== 'none';
                if (isVersionAttempt && scanInput.value.trim() === '') {
                    alert('<?php echo esc_js(__('Kérlek, add meg a vírusellenőrzés linkjét, mielőtt elfogadod a frissítést!', 'gta6-mods')); ?>');
                    document.querySelector('[data-tab="gta6-tab-version-content"]').click();
                    scanInput.focus();
                    return;
                }
            }
            
            if (confirmMsg && !confirm(confirmMsg)) {
                return;
            }

            const scanUrl = scanInput ? scanInput.value.trim() : '';
            const btn = event.currentTarget || event.target;
            btn.disabled = true;
            
            const spinner = btn.closest('#major-publishing-actions').querySelector('.spinner');
            if(spinner) spinner.classList.add('is-active');

            const form = document.createElement('form');
            form.method = 'post';
            form.action = '<?php echo esc_url(admin_url('admin-post.php')); ?>';
            form.innerHTML = `
                <input type="hidden" name="action" value="${handler}">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_js(wp_create_nonce('gta6mods_update_action_' . $post->ID)); ?>">
                <input type="hidden" name="update_id" value="<?php echo (int) $post->ID; ?>">
                <input type="hidden" name="gta6mods_update_version_scan_url" value="${scanUrl}">
            `;
            document.body.appendChild(form);
            form.submit();
        };

        approveBtn.addEventListener('click', event => handleAction(event, 'gta6mods_approve_update', <?php echo wp_json_encode($approve_confirm); ?>));
        rejectBtn.addEventListener('click', event => handleAction(event, 'gta6mods_reject_update', <?php echo wp_json_encode($reject_confirm); ?>));
    });
    </script>
    <?php
}

function gta6mods_render_update_overview_metabox(WP_Post $post) {
    $payload      = gta6mods_get_update_payload($post->ID);
    $mod_id       = isset($payload['mod_id']) ? (int) $payload['mod_id'] : 0;
    $mod_post     = $mod_id ? get_post($mod_id) : null;
    $submitted_by = get_user_by('id', get_post_meta($post->ID, '_gta6mods_update_submitted_by', true));

    // Current Values
    $current_title = $mod_post ? get_the_title($mod_post) : '—';
    $current_categories = $mod_post ? get_the_terms($mod_post->ID, 'category') : [];
    $current_category = !empty($current_categories) && !is_wp_error($current_categories) ? $current_categories[0]->name : '—';
    $current_tags = $mod_post ? get_the_terms($mod_post->ID, 'post_tag') : [];
    $current_tags_str = !empty($current_tags) && !is_wp_error($current_tags) ? implode(', ', wp_list_pluck($current_tags, 'name')) : '—';

    // Author data
    $current_main_author_obj = $mod_post ? get_user_by('id', $mod_post->post_author) : null;
    $current_main_author = $current_main_author_obj ? $current_main_author_obj->display_name : '';
    $current_additional_authors = $mod_post ? get_post_meta($mod_post->ID, '_gta6mods_additional_authors', true) : [];
    $current_additional_authors = is_array($current_additional_authors) ? $current_additional_authors : [];
    $current_all_authors_arr = array_merge([$current_main_author], $current_additional_authors);
    $current_authors_str = implode(', ', array_filter($current_all_authors_arr));

    // Proposed values
    $new_title = !empty($payload['new_title']) ? $payload['new_title'] : $current_title;
    $new_category_id = !empty($payload['new_category_id']) ? (int)$payload['new_category_id'] : 0;
    $new_category_term = $new_category_id ? get_term($new_category_id, 'category') : null;
    $new_category = $new_category_term && !is_wp_error($new_category_term) ? $new_category_term->name : $current_category;
    $new_tags = !empty($payload['tags']) ? implode(', ', $payload['tags']) : $current_tags_str;

    $new_additional_authors = isset($payload['authors']) && is_array($payload['authors']) ? $payload['authors'] : $current_additional_authors;
    $new_all_authors_arr = array_merge([$current_main_author], $new_additional_authors);
    $new_authors_str = implode(', ', array_filter($new_all_authors_arr));

    $has_version_update = !empty($payload['version_number']) || !empty($payload['version_source']);

    ?>
    <div class="gta6-admin-wrapper">
        <div class="gta6-tabs-nav">
            <button type="button" class="gta6-tab-button active" data-tab="gta6-tab-overview"><i class="fa-solid fa-table-list"></i><span><?php esc_html_e('Áttekintés', 'gta6-mods'); ?></span></button>
            <button type="button" class="gta6-tab-button" data-tab="gta6-tab-description"><i class="fa-solid fa-file-lines"></i><span><?php esc_html_e('Leírás', 'gta6-mods'); ?></span></button>
            <button type="button" class="gta6-tab-button" data-tab="gta6-tab-gallery"><i class="fa-solid fa-images"></i><span><?php esc_html_e('Galéria', 'gta6-mods'); ?></span></button>
            <button type="button" class="gta6-tab-button" data-tab="gta6-tab-version-content" style="<?php echo $has_version_update ? '' : 'display:none;'; ?>"><i class="fa-solid fa-code-branch"></i><span><?php esc_html_e('Új Verzió', 'gta6-mods'); ?></span></button>
        </div>

        <div class="gta6-tabs-content">
            <!-- Overview Tab -->
            <div id="gta6-tab-overview" class="gta6-tab-panel active">
                <table class="gta6-diff-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Mező', 'gta6-mods'); ?></th>
                            <th><?php esc_html_e('Jelenlegi érték', 'gta6-mods'); ?></th>
                            <th><?php esc_html_e('Javasolt érték', 'gta6-mods'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('Mod címe', 'gta6-mods'); ?></th>
                            <td><?php echo esc_html($current_title); ?></td>
                            <td><?php echo gta6_mods_diff_highlight($current_title, $new_title); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Szerzők', 'gta6-mods'); ?></th>
                            <td><?php echo esc_html($current_authors_str); ?></td>
                            <td><?php echo gta6_mods_diff_highlight($current_authors_str, $new_authors_str); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Kategória', 'gta6-mods'); ?></th>
                            <td><?php echo esc_html($current_category); ?></td>
                            <td><?php echo gta6_mods_diff_highlight($current_category, $new_category); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Címkék', 'gta6-mods'); ?></th>
                            <td><?php echo esc_html($current_tags_str); ?></td>
                            <td><?php echo gta6_mods_diff_highlight($current_tags_str, $new_tags); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Description Tab -->
            <div id="gta6-tab-description" class="gta6-tab-panel">
                 <?php
                    $current_content_json = $mod_post ? gta6_mods_get_editorjs_payload($mod_post->ID) : '';
                    $current_content_html = gta6mods_render_editorjs_to_html($current_content_json);

                    $new_content_json = !empty($payload['description_json']) ? $payload['description_json'] : '';
                    $new_content_html = '';
                    if (!empty($new_content_json)) {
                        $new_content_html = gta6mods_render_editorjs_to_html($new_content_json);
                    }
                    if (empty($new_content_html) && !empty($payload['description'])) {
                        $new_content_html = apply_filters('the_content', $payload['description']);
                    }
                    
                    if (trim($current_content_html) === trim($new_content_html)) {
                         echo '<p><em>' . esc_html__('A leírás nem változott.', 'gta6-mods') . '</em></p>';
                         echo '<hr><h4>' . esc_html__('Aktuális leírás', 'gta6-mods') . '</h4>';
                         echo '<div class="gta6-content-preview">' . $current_content_html . '</div>'; // WPCS: XSS ok.
                    } else {
                        // Create a plain text version for a clean diff, decoding entities first
                        $current_plain_text = html_entity_decode(wp_strip_all_tags(str_replace(['<br>', '<br/>', '</p>', '</li>', '</ul>', '</ol>', '</tr>', '</th>', '</td>'], "\n", $current_content_html)), ENT_QUOTES, 'UTF-8');
                        $new_plain_text = html_entity_decode(wp_strip_all_tags(str_replace(['<br>', '<br/>', '</p>', '</li>', '</ul>', '</ol>', '</tr>', '</th>', '</td>'], "\n", $new_content_html)), ENT_QUOTES, 'UTF-8');

                        $diff = wp_text_diff($current_plain_text, $new_plain_text, [
                            'title' => __('Szöveges változások', 'gta6-mods'),
                            'title_left' => __('Jelenlegi', 'gta6-mods'),
                            'title_right' => __('Javasolt', 'gta6-mods'),
                        ]);
                        
                        echo '<h4>' . esc_html__('Változások kiemelve', 'gta6-mods') . '</h4>';
                        echo '<p class="description">' . esc_html__('Itt csak a szöveges tartalom változásai látszanak, a formázás nem.', 'gta6-mods') . '</p>';
                        echo $diff; // WPCS: XSS ok.

                        echo '<hr style="margin: 24px 0;">';

                        echo '<h4>' . esc_html__('Formázott előnézet', 'gta6-mods') . '</h4>';
                        echo '<p class="description">' . esc_html__('Itt láthatod a formázott, végleges kinézetet.', 'gta6-mods') . '</p>';

                        ?>
                        <div class="gta6-diff-grid">
                            <div>
                                <h5><?php esc_html_e('Jelenlegi leírás', 'gta6-mods'); ?></h5>
                                <div class="gta6-content-preview">
                                    <?php echo $current_content_html; // WPCS: XSS ok. ?>
                                </div>
                            </div>
                            <div>
                                <h5><?php esc_html_e('Javasolt leírás', 'gta6-mods'); ?></h5>
                                <div class="gta6-content-preview">
                                    <?php echo $new_content_html; // WPCS: XSS ok. ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                 ?>
            </div>

            <!-- Gallery Tab -->
            <div id="gta6-tab-gallery" class="gta6-tab-panel">
                <?php
                    $deleted_screens = isset($payload['deleted_screenshots']) && is_array($payload['deleted_screenshots']) ? array_map('absint', $payload['deleted_screenshots']) : [];
                    $new_screens_meta = isset($payload['new_screenshots']) && is_array($payload['new_screenshots']) ? $payload['new_screenshots'] : [];
                    $new_screens = [];
                    foreach($new_screens_meta as $screen) {
                        if (!empty($screen['attachment_id'])) {
                            $new_screens[] = (int) $screen['attachment_id'];
                        }
                    }
                ?>
                <?php if(empty($deleted_screens) && empty($new_screens)): ?>
                    <p><em><?php esc_html_e('A galéria nem változott.', 'gta6-mods'); ?></em></p>
                <?php else: ?>
                    <?php if(!empty($new_screens)): ?>
                        <h4><?php esc_html_e('Új képek', 'gta6-mods'); ?></h4>
                        <div class="gta6-screenshot-grid">
                        <?php foreach($new_screens as $att_id): ?>
                            <div class="gta6-thumb gta6-thumb--added"><?php echo wp_get_attachment_image($att_id, [120, 120]); ?></div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($deleted_screens)): ?>
                        <h4><?php esc_html_e('Törölt képek', 'gta6-mods'); ?></h4>
                        <div class="gta6-screenshot-grid">
                        <?php foreach($deleted_screens as $att_id): ?>
                            <div class="gta6-thumb gta6-thumb--removed"><?php echo wp_get_attachment_image($att_id, [120, 120]); ?></div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Version Tab -->
            <div id="gta6-tab-version-content" class="gta6-tab-panel" style="<?php echo $has_version_update ? '' : 'display:none;'; ?>">
                <?php
                $version_number = isset($payload['version_number']) ? $payload['version_number'] : '';
                $current_version_number = $mod_post ? get_post_meta($mod_post->ID, '_gta6mods_mod_version', true) : '';
                $changelog      = gta6mods_normalize_changelog(isset($payload['changelog']) ? $payload['changelog'] : []);
                $version_source = isset($payload['version_source']) && is_array($payload['version_source']) ? $payload['version_source'] : [];
                $scan_url       = isset($payload['version_scan_url']) ? $payload['version_scan_url'] : '';

                if ('' === $version_number && empty($version_source) && empty($changelog)) {
                    echo '<p><em>' . esc_html__('A beküldés nem tartalmazott új verziót.', 'gta6-mods') . '</em></p>';
                } else {
                    ?>
                    <table class="gta6-diff-table">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e('Verziószám', 'gta6-mods'); ?></th>
                                <td>
                                    <?php
                                    if ($current_version_number) {
                                        echo '<span class="gta6-version-change">' . esc_html($current_version_number) . ' <span class="arrow">→</span> ' . esc_html($version_number) . '</span>';
                                    } else {
                                        echo esc_html($version_number);
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Changelog', 'gta6-mods'); ?></th>
                                <td>
                                    <?php if (!empty($changelog)): ?>
                                        <ul class="ul-disc">
                                        <?php foreach($changelog as $item): ?>
                                            <li><?php echo esc_html($item); ?></li>
                                        <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <em><?php esc_html_e('Nincs megadva.', 'gta6-mods'); ?></em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Fájl forrása', 'gta6-mods'); ?></th>
                                <td>
                                    <?php
                                    if ('file' === ($version_source['type'] ?? '') && !empty($version_source['attachment_id'])) {
                                        $file_url = wp_get_attachment_url((int)$version_source['attachment_id']);
                                        echo esc_html__('Fájlfeltöltés:', 'gta6-mods') . ' <a href="' . esc_url($file_url) . '" target="_blank">' . esc_html(basename($file_url)) . '</a> (' . esc_html($version_source['size_human'] ?? 'N/A') . ')';
                                    } elseif ('external' === ($version_source['type'] ?? '')) {
                                        echo esc_html__('Külső link:', 'gta6-mods') . ' <a href="' . esc_url($version_source['url']) . '" target="_blank">' . esc_html($version_source['url']) . '</a> (' . esc_html($version_source['size_human'] ?? 'N/A') . ')';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="gta6mods_update_version_scan_url"><?php esc_html_e('Vírusellenőrzés URL', 'gta6-mods'); ?></label></th>
                                <td>
                                    <input type="url" id="gta6mods_update_version_scan_url" name="gta6mods_update_version_scan_url" value="<?php echo esc_attr($scan_url); ?>" class="widefat" placeholder="https://www.virustotal.com/...">
                                    <p class="description"><?php esc_html_e('Add meg a vírusellenőrzés eredményét (pl. VirusTotal), mielőtt jóváhagyod a frissítést.', 'gta6-mods'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

function gta6_mods_diff_highlight($old, $new) {
    if ($old == $new) {
        return esc_html($new);
    }
    return '<span class="gta6-diff-changed">' . esc_html($new) . '</span>';
}

function gta6mods_handle_update_approval() {
    $update_id = isset($_POST['update_id']) ? absint($_POST['update_id']) : 0;
    if ($update_id <= 0 || !current_user_can('edit_post', $update_id)) {
        wp_die(__('Nincs jogosultságod ehhez a művelethez.', 'gta6-mods'));
    }

    check_admin_referer('gta6mods_update_action_' . $update_id);

    $scan_url = isset($_POST['gta6mods_update_version_scan_url']) ? wp_unslash($_POST['gta6mods_update_version_scan_url']) : '';
    gta6mods_store_update_scan_url($update_id, $scan_url);

    $result = gta6mods_apply_mod_update($update_id);
    if (is_wp_error($result)) {
        $message = rawurlencode($result->get_error_message());
        wp_safe_redirect(add_query_arg(['post' => $update_id, 'action' => 'edit', 'gta6mods_notice' => 'error', 'gta6mods_message' => $message], admin_url('post.php')));
        exit;
    }

    wp_update_post(['ID' => $update_id, 'post_status' => 'publish']);
    wp_safe_redirect(add_query_arg(['post' => $update_id, 'action' => 'edit', 'gta6mods_notice' => 'approved'], admin_url('post.php')));
    exit;
}
add_action('admin_post_gta6mods_approve_update', 'gta6mods_handle_update_approval');

function gta6mods_handle_update_rejection() {
    $update_id = isset($_POST['update_id']) ? absint($_POST['update_id']) : 0;
    if ($update_id <= 0 || !current_user_can('edit_post', $update_id)) {
        wp_die(__('Nincs jogosultságod ehhez a művelethez.', 'gta6-mods'));
    }

    check_admin_referer('gta6mods_update_action_' . $update_id);

    wp_update_post(['ID' => $update_id, 'post_status' => 'gta6mods_rejected']);
    wp_safe_redirect(add_query_arg(['post' => $update_id, 'action' => 'edit', 'gta6mods_notice' => 'rejected'], admin_url('post.php')));
    exit;
}
add_action('admin_post_gta6mods_reject_update', 'gta6mods_handle_update_rejection');

function gta6mods_mod_update_admin_notices() {
    if (!isset($_GET['gta6mods_notice'])) {
        return;
    }

    $notice  = sanitize_key(wp_unslash($_GET['gta6mods_notice']));
    $message = isset($_GET['gta6mods_message']) ? sanitize_text_field(wp_unslash($_GET['gta6mods_message'])) : '';

    if ('approved' === $notice) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('A frissítés sikeresen jóváhagyásra került.', 'gta6-mods') . '</p></div>';
    } elseif ('rejected' === $notice) {
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('A frissítési kérelem elutasításra került.', 'gta6-mods') . '</p></div>';
    } elseif ('error' === $notice && $message) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}
add_action('admin_notices', 'gta6mods_mod_update_admin_notices');

function gta6mods_mod_update_columns($columns) {
    $new_columns = [];
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if ('title' === $key) {
            $new_columns['mod_reference'] = __('Kapcsolódó mod', 'gta6-mods');
            $new_columns['submitted_by']  = __('Beküldte', 'gta6-mods');
        }
    }
    return $new_columns;
}
add_filter('manage_mod_update_posts_columns', 'gta6mods_mod_update_columns');

function gta6mods_mod_update_custom_column($column, $post_id) {
    if ('mod_reference' === $column) {
        $mod_id = (int) get_post_meta($post_id, '_gta6mods_update_mod_id', true);
        if ($mod_id > 0) {
            echo '<a href="' . esc_url(get_edit_post_link($mod_id)) . '">' . esc_html(get_the_title($mod_id)) . '</a>';
        } else {
            esc_html_e('Ismeretlen', 'gta6-mods');
        }
    } elseif ('submitted_by' === $column) {
        $submitted_by = (int) get_post_meta($post_id, '_gta6mods_update_submitted_by', true);
        if ($submitted_by > 0) {
            $user = get_user_by('id', $submitted_by);
            if ($user instanceof WP_User) {
                echo esc_html($user->display_name);
                return;
            }
        }
        $author = get_user_by('id', get_post_field('post_author', $post_id));
        echo $author instanceof WP_User ? esc_html($author->display_name) : esc_html__('Ismeretlen', 'gta6-mods');
    }
}
add_action('manage_mod_update_posts_custom_column', 'gta6mods_mod_update_custom_column', 10, 2);

function gta6mods_store_update_scan_url($post_id, $scan_url_raw) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    $scan_url_raw = is_string($scan_url_raw) ? trim($scan_url_raw) : '';

    if ('' === $scan_url_raw) {
        delete_post_meta($post_id, '_gta6mods_update_version_scan_url');
        return;
    }

    $validated = esc_url_raw($scan_url_raw);

    if ($validated && wp_http_validate_url($validated)) {
        update_post_meta($post_id, '_gta6mods_update_version_scan_url', $validated);
    } else {
        delete_post_meta($post_id, '_gta6mods_update_version_scan_url');
    }
}

function gta6mods_save_update_review_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!isset($_POST['gta6mods_update_review_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gta6mods_update_review_nonce']), 'gta6mods_update_review_meta')) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $scan_url_raw = isset($_POST['gta6mods_update_version_scan_url']) ? wp_unslash($_POST['gta6mods_update_version_scan_url']) : '';
    gta6mods_store_update_scan_url($post_id, $scan_url_raw);
}
add_action('save_post_mod_update', 'gta6mods_save_update_review_meta');

function gta6mods_mod_update_sortable_columns($columns) {
    $columns['mod_reference'] = 'mod_reference';
    $columns['submitted_by']  = 'submitted_by';
    return $columns;
}
add_filter('manage_edit-mod_update_sortable_columns', 'gta6mods_mod_update_sortable_columns');

function gta6mods_mod_update_admin_styles() {
    $screen = get_current_screen();
    if (!$screen || 'mod_update' !== $screen->post_type) {
        return;
    }
    $css_path = get_template_directory() . '/assets/css/admin-update-view.css';
    if (file_exists($css_path)) {
        echo '<style>' . file_get_contents($css_path) . '</style>';
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.gta6-tab-button');
            const panels = document.querySelectorAll('.gta6-tab-panel');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-tab');
                    
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    panels.forEach(p => {
                        if (p.id === target) {
                            p.classList.add('active');
                        } else {
                            p.classList.remove('active');
                        }
                    });
                });
            });
        });
    </script>
    <?php
}
add_action('admin_head', 'gta6mods_mod_update_admin_styles');


function gta6mods_add_pending_update_menu_badge() {
    global $submenu;

    if (!isset($submenu['edit.php']) || !is_array($submenu['edit.php'])) {
        return;
    }

    $counts = wp_count_posts('mod_update');
    $pending = isset($counts->pending) ? (int) $counts->pending : 0;

    foreach ($submenu['edit.php'] as $index => $menu_item) {
        if (!isset($menu_item[2]) || 'edit.php?post_type=mod_update' !== $menu_item[2]) {
            continue;
        }

        $label_text = isset($menu_item[0]) ? wp_strip_all_tags($menu_item[0]) : '';
        $label = esc_html($label_text);

        if ($pending > 0) {
            $label .= sprintf(' <span class="gta6mods-menu-badge">%d</span>', $pending);
        }

        $submenu['edit.php'][$index][0] = $label;
        break;
    }
}
add_action('admin_menu', 'gta6mods_add_pending_update_menu_badge', 99);

function gta6mods_admin_menu_badge_styles() {
    echo '<style>
    .gta6mods-menu-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        padding: 0 6px;
        border-radius: 9999px;
        background-color: #ec4899;
        color: #fff;
        font-size: 11px;
        line-height: 1.6;
        font-weight: 600;
    }
    </style>';
}
add_action('admin_head', 'gta6mods_admin_menu_badge_styles');

/**
 * Registers the stats migration tools page.
 */
function gta6mods_register_stats_migration_page() {
    add_theme_page(
        __('Mod Stats Migration', 'gta6-mods'),
        __('Mod Stats Migration', 'gta6-mods'),
        'manage_options',
        'gta6mods-stats-migration',
        'gta6mods_render_stats_migration_page'
    );
}
add_action('admin_menu', 'gta6mods_register_stats_migration_page');

/**
 * Renders the stats migration management interface.
 */
function gta6mods_render_stats_migration_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'gta6-mods'));
    }

    $last_id   = (int) get_option('gta6mods_stats_migration_last_id', 0);
    $processed = (int) get_option('gta6mods_stats_migration_processed', 0);
    $complete  = (bool) get_option('gta6mods_stats_migration_complete', false);
    $log       = get_option('gta6mods_stats_migration_log', []);

    if (!is_array($log)) {
        $log = [];
    }

    $status_message = '';
    $status_class   = 'notice-info';

    if (isset($_GET['migration_message'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status_message = sanitize_text_field(wp_unslash(rawurldecode((string) $_GET['migration_message']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    if (isset($_GET['migration_status'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status_value = sanitize_key((string) wp_unslash($_GET['migration_status'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ('complete' === $status_value) {
            $status_class = 'notice-success';
        } elseif ('error' === $status_value) {
            $status_class = 'notice-error';
        } elseif ('warning' === $status_value) {
            $status_class = 'notice-warning';
        }
    }

    $batch_default = 100;

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Mod Stats Migration', 'gta6-mods'); ?></h1>

        <p><?php esc_html_e('Use this tool to migrate frequently updated statistics from post meta into the dedicated gta_mod_stats table. Run the migration in batches to avoid overloading the database.', 'gta6-mods'); ?></p>

        <?php if ('' !== $status_message) : ?>
            <div class="notice <?php echo esc_attr($status_class); ?> is-dismissible">
                <p><?php echo esc_html($status_message); ?></p>
            </div>
        <?php endif; ?>

        <table class="widefat striped" style="max-width: 600px; margin-top: 1em;">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Total processed rows', 'gta6-mods'); ?></th>
                    <td><?php echo esc_html(number_format_i18n($processed)); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Last processed post ID', 'gta6-mods'); ?></th>
                    <td><?php echo esc_html(number_format_i18n($last_id)); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Migration complete', 'gta6-mods'); ?></th>
                    <td><?php echo $complete ? esc_html__('Yes', 'gta6-mods') : esc_html__('No', 'gta6-mods'); ?></td>
                </tr>
            </tbody>
        </table>

        <h2><?php esc_html_e('Run Migration Batch', 'gta6-mods'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('gta6mods_run_stats_migration'); ?>
            <input type="hidden" name="action" value="gta6mods_run_stats_migration">
            <p>
                <label for="gta6mods-migration-batch-size"><?php esc_html_e('Batch size', 'gta6-mods'); ?></label>
                <input type="number" min="10" max="1000" step="10" name="batch_size" id="gta6mods-migration-batch-size" value="<?php echo esc_attr((string) $batch_default); ?>" />
            </p>
            <?php submit_button(__('Run Next Batch', 'gta6-mods')); ?>
        </form>

        <h2><?php esc_html_e('Rollback', 'gta6-mods'); ?></h2>
        <p><?php esc_html_e('If you need to revert the migration progress, you can clear the stats table and reset the migration state. The legacy post meta values remain untouched.', 'gta6-mods'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to truncate the stats table?', 'gta6-mods')); ?>');">
            <?php wp_nonce_field('gta6mods_run_stats_migration_rollback'); ?>
            <input type="hidden" name="action" value="gta6mods_run_stats_migration_rollback">
            <?php submit_button(__('Rollback Stats Table', 'gta6-mods'), 'secondary'); ?>
        </form>

        <h2><?php esc_html_e('Migration Log', 'gta6-mods'); ?></h2>
        <?php if (empty($log)) : ?>
            <p><?php esc_html_e('No migration activity recorded yet.', 'gta6-mods'); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width: 800px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'gta6-mods'); ?></th>
                        <th><?php esc_html_e('Type', 'gta6-mods'); ?></th>
                        <th><?php esc_html_e('Message', 'gta6-mods'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log as $entry) :
                        $type    = isset($entry['type']) ? sanitize_key($entry['type']) : 'info';
                        $message = isset($entry['message']) ? (string) $entry['message'] : '';
                        $time    = isset($entry['timestamp']) ? $entry['timestamp'] : '';
                    ?>
                        <tr>
                            <td><?php echo esc_html($time); ?></td>
                            <td><?php echo esc_html(ucfirst($type)); ?></td>
                            <td><?php echo esc_html($message); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Logs a migration event.
 *
 * @param string $message Message to record.
 * @param string $type    Message type.
 */
function gta6mods_log_stats_migration($message, $type = 'info') {
    $log = get_option('gta6mods_stats_migration_log', []);

    if (!is_array($log)) {
        $log = [];
    }

    $log_entry = [
        'timestamp' => current_time('mysql'),
        'type'      => sanitize_key($type),
        'message'   => wp_strip_all_tags($message),
    ];

    array_unshift($log, $log_entry);
    $log = array_slice($log, 0, 20);

    update_option('gta6mods_stats_migration_log', $log);
}

/**
 * Processes a migration batch request.
 */
function gta6mods_handle_stats_migration_request() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to perform this action.', 'gta6-mods'));
    }

    check_admin_referer('gta6mods_run_stats_migration');

    $batch_size = isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 100;
    $batch_size = max(10, min(1000, $batch_size));

    $result = gta6mods_run_stats_migration_batch($batch_size);

    $message = __('No mods required migration.', 'gta6-mods');
    $status  = 'partial';

    if ($result['processed'] > 0) {
        $message = sprintf(
            /* translators: 1: processed rows, 2: last post ID */
            __('Processed %1$s mods. Last processed post ID: %2$s.', 'gta6-mods'),
            number_format_i18n($result['processed']),
            number_format_i18n($result['last_id'])
        );
    }

    if (!empty($result['complete'])) {
        $status  = 'complete';
        $message = sprintf('%s %s', $message, __('Migration complete.', 'gta6-mods'));
    }

    gta6mods_log_stats_migration($message, 'info');

    $redirect = add_query_arg(
        [
            'page'              => 'gta6mods-stats-migration',
            'migration_status'  => $status,
            'migration_message' => rawurlencode($message),
        ],
        admin_url('themes.php')
    );

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_gta6mods_run_stats_migration', 'gta6mods_handle_stats_migration_request');

/**
 * Executes a batch of stats migrations.
 *
 * @param int $batch_size Number of posts to process.
 *
 * @return array{processed:int,last_id:int,complete:bool}
 */
function gta6mods_run_stats_migration_batch($batch_size = 100) {
    global $wpdb;

    $batch_size = max(1, absint($batch_size));

    $post_types = gta6mods_get_mod_post_types();

    if (empty($post_types)) {
        return [
            'processed' => 0,
            'last_id'   => (int) get_option('gta6mods_stats_migration_last_id', 0),
            'complete'  => true,
        ];
    }

    $last_id = (int) get_option('gta6mods_stats_migration_last_id', 0);

    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$placeholders}) AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT %d";

    $params   = array_merge($post_types, [$last_id, $batch_size]);
    $prepared = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $params));

    if (false === $prepared) {
        return [
            'processed' => 0,
            'last_id'   => $last_id,
            'complete'  => false,
        ];
    }

    $post_ids = $wpdb->get_col($prepared);

    if (empty($post_ids)) {
        update_option('gta6mods_stats_migration_complete', true);

        return [
            'processed' => 0,
            'last_id'   => $last_id,
            'complete'  => true,
        ];
    }

    $processed = 0;
    $last_processed_id = $last_id;

    foreach ($post_ids as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            continue;
        }

        gta6mods_prime_mod_stats_from_meta($post_id);
        $processed++;
        $last_processed_id = $post_id;
    }

    $total_processed = (int) get_option('gta6mods_stats_migration_processed', 0) + $processed;

    update_option('gta6mods_stats_migration_last_id', $last_processed_id);
    update_option('gta6mods_stats_migration_processed', $total_processed);

    $complete = $processed < $batch_size;

    if ($complete) {
        update_option('gta6mods_stats_migration_complete', true);
    }

    return [
        'processed' => $processed,
        'last_id'   => $last_processed_id,
        'complete'  => $complete,
    ];
}

/**
 * Handles a rollback request for the stats migration.
 */
function gta6mods_handle_stats_migration_rollback() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to perform this action.', 'gta6-mods'));
    }

    check_admin_referer('gta6mods_run_stats_migration_rollback');

    gta6mods_run_stats_migration_rollback();
    gta6mods_log_stats_migration(__('Stats table truncated and migration state reset.', 'gta6-mods'), 'warning');

    $redirect = add_query_arg(
        [
            'page'              => 'gta6mods-stats-migration',
            'migration_status'  => 'warning',
            'migration_message' => rawurlencode(__('Stats table truncated and migration state reset.', 'gta6-mods')),
        ],
        admin_url('themes.php')
    );

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_gta6mods_run_stats_migration_rollback', 'gta6mods_handle_stats_migration_rollback');

/**
 * Clears the stats table and resets migration tracking options.
 */
function gta6mods_run_stats_migration_rollback() {
    global $wpdb;

    $table = gta6mods_get_mod_stats_table_name();

    $wpdb->query("DELETE FROM {$table}");

    delete_option('gta6mods_stats_migration_last_id');
    delete_option('gta6mods_stats_migration_processed');
    delete_option('gta6mods_stats_migration_complete');
    delete_option('gta6mods_stats_migration_log');
}

/**
 * Adds version history meta box to mod edit screens.
 */
function gta6mods_add_version_history_meta_box(): void {
    $post_types = function_exists('gta6mods_get_mod_post_types') ? gta6mods_get_mod_post_types() : ['post'];

    foreach ($post_types as $post_type) {
        add_meta_box(
            'gta6mods-version-history',
            __('Verziótörténet', 'gta6-mods'),
            'gta6mods_render_version_history_meta_box',
            $post_type,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'gta6mods_add_version_history_meta_box');

/**
 * Renders the version history table.
 *
 * @param WP_Post $post Current post.
 */
function gta6mods_render_version_history_meta_box(WP_Post $post): void {
    $versions = GTA6Mods_Mod_Versions::get_versions_for_mod($post->ID, 10);

    if (empty($versions)) {
        echo '<p>' . esc_html__('Még nincs elérhető verzió a gyorsított táblában. A következő feltöltéskor automatikusan létrejön.', 'gta6-mods') . '</p>';
        return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Verzió', 'gta6-mods') . '</th>';
    echo '<th>' . esc_html__('Feltöltés ideje', 'gta6-mods') . '</th>';
    echo '<th>' . esc_html__('Letöltések', 'gta6-mods') . '</th>';
    echo '<th>' . esc_html__('Állapot', 'gta6-mods') . '</th>';
    echo '<th>' . esc_html__('Műveletek', 'gta6-mods') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($versions as $version) {
        $version_id   = (int) $version['id'];
        $download_url = gta6_mods_get_waiting_room_url($post->ID, $version_id);
        $deprecated   = !empty($version['is_deprecated']);
        $status_label = $deprecated ? __('Elavult', 'gta6-mods') : __('Aktív', 'gta6-mods');
        $toggle_state = $deprecated ? 0 : 1;
        $toggle_label = $deprecated ? __('Újra aktiválás', 'gta6-mods') : __('Elavultként jelölés', 'gta6-mods');
        $toggle_url   = wp_nonce_url(
            add_query_arg(
                [
                    'action'     => 'gta6mods_toggle_version_deprecated',
                    'version_id' => $version_id,
                    'mod_id'     => $post->ID,
                    'state'      => $toggle_state,
                ],
                admin_url('admin-post.php')
            ),
            'gta6mods_toggle_version_' . $version_id
        );

        echo '<tr>';
        echo '<td>' . esc_html($version['version']) . '</td>';
        echo '<td>' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $version['upload_date'])) . '</td>';
        echo '<td>' . esc_html(number_format_i18n((int) $version['download_count'])) . '</td>';
        echo '<td>' . esc_html($status_label) . '</td>';
        echo '<td class="column-actions">';
        echo '<a class="button button-secondary" href="' . esc_url($download_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Letöltési oldal', 'gta6-mods') . '</a> ';
        echo '<a class="button" href="' . esc_url($toggle_url) . '">' . esc_html($toggle_label) . '</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * Handles marking versions as deprecated from the admin.
 */
function gta6mods_handle_toggle_version_deprecated(): void {
    $version_id = isset($_GET['version_id']) ? absint($_GET['version_id']) : 0;
    $mod_id     = isset($_GET['mod_id']) ? absint($_GET['mod_id']) : 0;
    $state      = isset($_GET['state']) ? (int) $_GET['state'] : 0;

    if ($version_id <= 0 || $mod_id <= 0) {
        wp_die(__('Érvénytelen kérés.', 'gta6-mods'));
    }

    check_admin_referer('gta6mods_toggle_version_' . $version_id);

    if (!current_user_can('edit_post', $mod_id)) {
        wp_die(__('Nincs jogosultságod a módosításhoz.', 'gta6-mods'));
    }

    GTA6Mods_Mod_Versions::set_deprecated($version_id, $state === 1);

    $redirect = get_edit_post_link($mod_id, 'url');
    if (!$redirect) {
        $redirect = admin_url('edit.php');
    }

    wp_safe_redirect(add_query_arg('gta6mods_version_updated', '1', $redirect));
    exit;
}
add_action('admin_post_gta6mods_toggle_version_deprecated', 'gta6mods_handle_toggle_version_deprecated');

/**
 * Enqueues assets for the analytics widget.
 */
function gta6mods_admin_dashboard_assets($hook): void {
    if ('index.php' !== $hook) {
        return;
    }

    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
}
add_action('admin_enqueue_scripts', 'gta6mods_admin_dashboard_assets');

/**
 * Registers the analytics dashboard widget.
 */
function gta6mods_register_download_dashboard_widget(): void {
    wp_add_dashboard_widget(
        'gta6mods-download-analytics',
        __('GTA6 Mods letöltési statisztika', 'gta6-mods'),
        'gta6mods_render_download_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'gta6mods_register_download_dashboard_widget');

/**
 * Renders the download analytics widget.
 */
function gta6mods_render_download_dashboard_widget(): void {
    $stats       = GTA6Mods_Mod_Versions::get_download_stats();
    $top_version = GTA6Mods_Mod_Versions::get_most_popular_version();

    $chart_rows = wp_cache_get('gta6mods_dashboard_top_versions', 'gta6mods_admin');
    if (false === $chart_rows) {
        $chart_rows = GTA6Mods_Mod_Versions::get_top_versions(7);
        wp_cache_set('gta6mods_dashboard_top_versions', $chart_rows, 'gta6mods_admin', HOUR_IN_SECONDS);
    }

    $labels = [];
    $values = [];

    foreach ($chart_rows as $row) {
        $mod_title = isset($row['post_title']) && $row['post_title'] !== '' ? $row['post_title'] : __('Ismeretlen mod', 'gta6-mods');
        $labels[]  = sprintf('%s – %s', $mod_title, $row['version']);
        $values[]  = (int) $row['download_count'];
    }

    echo '<p><strong>' . esc_html__('Összesített letöltések:', 'gta6-mods') . '</strong> ' . esc_html(number_format_i18n((int) $stats['total'])) . '</p>';

    if ($top_version) {
        $top_label = isset($top_version['post_title']) && $top_version['post_title'] !== '' ? $top_version['post_title'] : __('Ismeretlen mod', 'gta6-mods');
        echo '<p><strong>' . esc_html__('Legnépszerűbb verzió:', 'gta6-mods') . '</strong> ' . esc_html($top_label . ' – ' . $top_version['version']) . ' (' . esc_html(number_format_i18n((int) $top_version['download_count'])) . ')</p>';
    }

    echo '<canvas id="gta6mods-download-trend" height="180"></canvas>';

    $chart_data = [
        'labels' => $labels,
        'datasets' => [
            [
                'label'           => __('Letöltések', 'gta6-mods'),
                'data'            => $values,
                'backgroundColor' => 'rgba(8, 247, 254, 0.4)',
                'borderColor'     => 'rgba(8, 247, 254, 1)',
                'borderWidth'     => 2,
            ],
        ],
    ];

    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            return;
        }

        const canvas = document.getElementById('gta6mods-download-trend');
        if (!canvas) {
            return;
        }

        const chartData = <?php echo wp_json_encode($chart_data); ?>;

        new Chart(canvas, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                        },
                    },
                },
            },
        });
    });
    </script>
    <?php
}



