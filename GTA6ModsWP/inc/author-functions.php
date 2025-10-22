<?php
/**
 * Author profile functionality.
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

if (!defined('GTA6_MODS_BOOKMARK_META_KEY')) {
    define('GTA6_MODS_BOOKMARK_META_KEY', '_gta6mods_bookmarked_mods');
}

/**
 * Returns the supported post types that should count as "mods".
 *
 * @return string[]
 */
function gta6mods_get_mod_post_types() {
    $post_types = ['post'];

    /**
     * Filters the list of post types that should be considered mods for author statistics.
     *
     * @param string[] $post_types Post types.
     */
    return apply_filters('gta6mods_mod_post_types', $post_types);
}

/**
 * Returns the available profile tabs for an author profile.
 *
 * @param bool $is_owner Whether the current viewer owns the profile.
 *
 * @return array<string, array<string, string>>
 */
function gta6mods_get_author_profile_tabs($is_owner = false) {
    $tabs = [
        'overview' => [
            'label' => __('Overview', 'gta6-mods'),
            'icon'  => 'fas fa-stream',
        ],
        'uploads' => [
            'label' => __('Uploads', 'gta6-mods'),
            'icon'  => 'fas fa-upload',
        ],
        'comments' => [
            'label' => __('Comments', 'gta6-mods'),
            'icon'  => 'fas fa-comments',
        ],
        'collections' => [
            'label' => __('Collections', 'gta6-mods'),
            'icon'  => 'fas fa-layer-group',
        ],
        'followers' => [
            'label' => __('Followers', 'gta6-mods'),
            'icon'  => 'fas fa-users',
        ],
    ];

    if ($is_owner) {
        $tabs = array_merge(
            array_slice($tabs, 0, 3, true),
            [
                'notifications' => [
                    'label' => __('Notifications', 'gta6-mods'),
                    'icon'  => 'fas fa-bell',
                ],
                'bookmarks' => [
                    'label' => __('Bookmarks', 'gta6-mods'),
                    'icon'  => 'fas fa-bookmark',
                ],
            ],
            array_slice($tabs, 3, null, true)
        );

        $tabs['settings'] = [
            'label' => __('Settings', 'gta6-mods'),
            'icon'  => 'fas fa-cog',
        ];
    }

    /**
     * Filters the available profile tabs.
     *
     * @param array<string, array<string, string>> $tabs     Tab definitions.
     * @param bool                                 $is_owner Whether the viewer owns the profile.
     */
    return apply_filters('gta6mods_author_profile_tabs', $tabs, $is_owner);
}

/**
 * Generates the HTML for the author profile tab navigation links.
 *
 * @param array<string, array<string, string>> $tabs      Tab definitions.
 * @param array<string, string>                $tab_urls  Precomputed tab URLs.
 * @param string                               $active_tab Currently active tab slug.
 *
 * @return string
 */
function gta6mods_get_author_profile_tab_links_html($tabs, $tab_urls, $active_tab) {
    $html  = '';
    $index = 0;

    foreach ($tabs as $tab_key => $tab_data) {
        $is_active = ($tab_key === $active_tab);
        $padding   = (0 === $index) ? 'px-0' : 'px-1';
        $classes   = 'profile-tab-btn py-3 ' . $padding . ' font-semibold text-gray-600 hover:text-pink-600';

        if ($is_active) {
            $classes .= ' active';
        }

        $url   = $tab_urls[$tab_key] ?? '';
        $label = $tab_data['label'] ?? ucfirst($tab_key);
        $icon  = $tab_data['icon'] ?? '';

        $html .= '<a id="tab-link-' . esc_attr($tab_key) . '" href="' . esc_url($url) . '" class="' . esc_attr($classes) . '" data-tab="' . esc_attr($tab_key) . '" data-tab-key="' . esc_attr($tab_key) . '" aria-controls="' . esc_attr($tab_key) . '" role="tab"';

        if ($is_active) {
            $html .= ' aria-current="page" aria-selected="true"';
        } else {
            $html .= ' aria-selected="false"';
        }

        $html .= '>';

        if ($icon) {
            $html .= '<i class="' . esc_attr($icon) . ' opacity-70"></i>';
        }

        $html .= '<span class="ml-2">' . esc_html($label) . '</span>';
        $html .= '</a>';

        $index++;
    }

    return $html;
}

/**
 * Returns the default tab slug.
 *
 * @return string
 */
function gta6mods_get_default_author_profile_tab() {
    return 'overview';
}

/**
 * Determines whether a user may schedule their own account for deletion from the frontend.
 *
 * Administrators and moderators (or anyone with elevated management capabilities) are
 * prevented from accessing the self-service deletion tools.
 *
 * @param int|WP_User $user User object or ID.
 *
 * @return bool
 */
function gta6mods_user_can_self_schedule_account_deletion($user) {
    if (is_numeric($user)) {
        $user = get_userdata((int) $user);
    }

    if (!$user instanceof WP_User) {
        return false;
    }

    $allowed = true;

    $protected_roles = apply_filters(
        'gta6mods_protected_account_deletion_roles',
        ['administrator', 'moderator']
    );

    foreach ($user->roles as $role) {
        if (in_array($role, $protected_roles, true)) {
            $allowed = false;
            break;
        }
    }

    if ($allowed) {
        $protected_caps = apply_filters(
            'gta6mods_protected_account_deletion_caps',
            ['manage_options', 'delete_users', 'promote_users', 'moderate_comments']
        );

        foreach ($protected_caps as $capability) {
            if (user_can($user, $capability)) {
                $allowed = false;
                break;
            }
        }
    }

    /**
     * Filters whether the provided user can self-schedule an account deletion request.
     *
     * @param bool    $allowed Whether the action is allowed.
     * @param WP_User $user    The user object.
     */
    return (bool) apply_filters('gta6mods_user_can_self_schedule_account_deletion', $allowed, $user);
}

function gta6mods_get_deleted_comment_placeholder() {
    $placeholder = __('The user account that posted this comment no longer exists.', 'gta6-mods');

    /**
     * Filters the placeholder text that replaces comments from self-deleted users.
     *
     * @param string $placeholder Placeholder text.
     */
    return apply_filters('gta6mods_deleted_comment_placeholder', $placeholder);
}

function gta6mods_make_user_content_inaccessible($user_id) {
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return;
    }

    $backup = get_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY, true);
    if (!is_array($backup)) {
        $backup = [
            'posts'    => [],
            'comments' => [],
        ];
    }

    $post_backup    = isset($backup['posts']) && is_array($backup['posts']) ? $backup['posts'] : [];
    $comment_backup = isset($backup['comments']) && is_array($backup['comments']) ? array_map('intval', $backup['comments']) : [];

    $target_statuses = apply_filters('gta6mods_deleted_account_post_statuses', ['publish']);
    if (!empty($target_statuses)) {
        $post_types = get_post_types(['public' => true], 'names');

        if (!empty($post_types)) {
            $post_ids = get_posts([
                'author'           => $user_id,
                'post_type'        => $post_types,
                'post_status'      => $target_statuses,
                'posts_per_page'   => -1,
                'fields'           => 'ids',
                'suppress_filters' => false,
            ]);

            foreach ($post_ids as $post_id) {
                $post_id = (int) $post_id;
                if ($post_id <= 0) {
                    continue;
                }

                $current_status = get_post_status($post_id);
                if (!$current_status || 'private' === $current_status) {
                    continue;
                }

                $post_object = get_post($post_id);
                if (!$post_object instanceof WP_Post || (int) $post_object->post_author !== $user_id) {
                    continue;
                }

                if (!isset($post_backup[$post_id])) {
                    $post_backup[$post_id] = $current_status;
                }

                if ('private' !== $current_status) {
                    wp_update_post([
                        'ID'          => $post_id,
                        'post_status' => 'private',
                    ]);
                }
            }
        }
    }

    $placeholder = gta6mods_get_deleted_comment_placeholder();
    $comments    = get_comments([
        'user_id' => $user_id,
        'status'  => 'all',
        'orderby' => 'comment_ID',
        'order'   => 'ASC',
        'number'  => 0,
    ]);

    foreach ($comments as $comment) {
        if (!$comment instanceof WP_Comment) {
            continue;
        }

        $comment_id = (int) $comment->comment_ID;
        if ($comment_id <= 0) {
            continue;
        }

        $existing_backup = get_comment_meta($comment_id, GTA6_MODS_DELETED_COMMENT_META_KEY, true);
        if (!is_array($existing_backup) || !array_key_exists('content', $existing_backup)) {
            add_comment_meta(
                $comment_id,
                GTA6_MODS_DELETED_COMMENT_META_KEY,
                [
                    'content'  => $comment->comment_content,
                    'approved' => $comment->comment_approved,
                ],
                true
            );
        }

        if (!in_array($comment_id, $comment_backup, true)) {
            $comment_backup[] = $comment_id;
        }

        if ($comment->comment_content !== $placeholder) {
            wp_update_comment([
                'comment_ID'      => $comment_id,
                'comment_content' => $placeholder,
            ]);
        }
    }

    $backup['posts']       = $post_backup;
    $backup['comments']    = array_values(array_unique(array_filter($comment_backup, static function ($value) {
        return is_numeric($value) && (int) $value > 0;
    })));
    $backup['updated_at']  = current_time('timestamp');

    update_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY, $backup);
}

function gta6mods_restore_user_content($user_id) {
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return;
    }

    $backup = get_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY, true);
    if (!is_array($backup)) {
        delete_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY);
        return;
    }

    $post_backup = isset($backup['posts']) && is_array($backup['posts']) ? $backup['posts'] : [];
    foreach ($post_backup as $post_id => $status) {
        $post_id = (int) $post_id;
        $status  = is_string($status) ? $status : '';

        if ($post_id <= 0 || '' === $status) {
            continue;
        }

        $status_object = get_post_status_object($status);
        if (!$status_object) {
            continue;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || (int) $post->post_author !== $user_id) {
            continue;
        }

        if (get_post_status($post_id) === $status) {
            continue;
        }

        wp_update_post([
            'ID'          => $post_id,
            'post_status' => $status,
        ]);
    }

    $comment_ids = isset($backup['comments']) && is_array($backup['comments']) ? $backup['comments'] : [];
    foreach ($comment_ids as $comment_id) {
        $comment_id = (int) $comment_id;
        if ($comment_id <= 0) {
            continue;
        }

        $comment = get_comment($comment_id);
        if (!$comment instanceof WP_Comment || (int) $comment->user_id !== $user_id) {
            delete_comment_meta($comment_id, GTA6_MODS_DELETED_COMMENT_META_KEY);
            continue;
        }

        $meta = get_comment_meta($comment_id, GTA6_MODS_DELETED_COMMENT_META_KEY, true);
        if (is_array($meta)) {
            $update = [
                'comment_ID'      => $comment_id,
                'comment_content' => isset($meta['content']) ? (string) $meta['content'] : '',
            ];

            if (isset($meta['approved'])) {
                $update['comment_approved'] = $meta['approved'];
            }

            wp_update_comment($update);
        }

        delete_comment_meta($comment_id, GTA6_MODS_DELETED_COMMENT_META_KEY);
    }

    delete_user_meta($user_id, GTA6_MODS_ACCOUNT_DELETION_CONTENT_META_KEY);
}

function gta6mods_block_deleted_accounts_login($user) {
    if (!$user instanceof WP_User) {
        return $user;
    }

    $data = gta6_mods_get_account_deletion_data($user->ID);

    if (!is_array($data) || !isset($data['status']) || 'deleted' !== $data['status']) {
        return $user;
    }

    return new WP_Error('gta6mods_account_deleted', __('Your account is no longer available. Please contact support.', 'gta6-mods'));
}
add_filter('wp_authenticate_user', 'gta6mods_block_deleted_accounts_login', 20);

/**
 * Ensures the provided tab slug is valid for the current context.
 *
 * @param string $tab      Requested tab slug.
 * @param bool   $is_owner Whether the viewer owns the profile.
 *
 * @return string
 */
function gta6mods_get_valid_author_profile_tab($tab, $is_owner = false) {
    $tab   = sanitize_key($tab);
    $tabs  = gta6mods_get_author_profile_tabs($is_owner);
    $default = gta6mods_get_default_author_profile_tab();

    if ('' === $tab || !array_key_exists($tab, $tabs)) {
        return $default;
    }

    return $tab;
}

/**
 * Builds the URL to a specific author profile tab.
 *
 * @param int|WP_User $user User object or ID.
 * @param string      $tab  Tab slug.
 * @param array       $args Additional query arguments.
 *
 * @return string
 */
function gta6mods_get_author_profile_tab_url($user, $tab, $args = []) {
    if (!($user instanceof WP_User)) {
        $user = get_user_by('id', (int) $user);
    }

    if (!($user instanceof WP_User)) {
        return '';
    }

    $is_owner = ((int) get_current_user_id() === (int) $user->ID);
    $tab      = gta6mods_get_valid_author_profile_tab($tab, $is_owner);

    $author_base = get_option('author_base');
    if (!$author_base) {
        $author_base = 'author';
    }
    $author_base = trim($author_base, '/');

    $segments = [];
    if ($author_base !== '') {
        $segments[] = $author_base;
    }
    $segments[] = $user->user_nicename;
    if ('overview' !== $tab) {
        $segments[] = $tab;
    }

    $path = implode('/', array_map('rawurlencode', $segments));
    $url  = home_url(user_trailingslashit($path));

    if (!empty($args)) {
        $url = add_query_arg($args, $url);
    }

    /**
     * Filters the generated profile tab URL.
     *
     * @param string  $url  Generated URL.
     * @param string  $tab  Tab slug.
     * @param WP_User $user User object.
     * @param array   $args Additional query arguments.
     */
    return apply_filters('gta6mods_author_profile_tab_url', $url, $tab, $user, $args);
}

/**
 * Returns the available preset avatar definitions bundled with the theme.
 *
 * @return array<string, array{id: string, url: string, path: string}>
 */
function gta6mods_get_preset_avatar_definitions() {
    static $avatars_cache = null;

    if (null !== $avatars_cache) {
        return $avatars_cache;
    }

    $filenames = [];
    for ($i = 1; $i <= 11; $i++) {
        $filenames[] = sprintf('avatar%d.webp', $i);
    }

    $base_dir = trailingslashit(get_template_directory()) . 'img/avatars/';
    $base_url = trailingslashit(get_template_directory_uri()) . 'img/avatars/';

    $avatars = [];

    foreach ($filenames as $filename) {
        $path = $base_dir . $filename;

        if (!file_exists($path)) {
            continue;
        }

        $avatars[$filename] = [
            'id'   => $filename,
            'url'  => $base_url . $filename,
            'path' => $path,
        ];
    }

    $avatars_cache = $avatars;

    return $avatars_cache;
}

/**
 * Retrieves a single preset avatar definition by identifier.
 *
 * @param string $id Avatar file name identifier.
 *
 * @return array{id: string, url: string, path: string}|null
 */
function gta6mods_get_preset_avatar_definition($id) {
    $avatars = gta6mods_get_preset_avatar_definitions();

    if (isset($avatars[$id])) {
        return $avatars[$id];
    }

    return null;
}

/**
 * Returns the default WordPress avatar URL for a user.
 *
 * @param int $user_id User ID.
 * @param int $size    Requested size.
 *
 * @return string
 */
function gta6mods_get_default_avatar_url($user_id, $size = 256) {
    $user_id = absint($user_id);
    $size    = max(1, (int) $size);

    if ($user_id <= 0) {
        return '';
    }

    return get_avatar_url($user_id, [
        'size'          => $size,
        'force_default' => true,
    ]);
}

/**
 * Retrieves avatar metadata for a given user.
 *
 * @param int $user_id User ID.
 *
 * @return array{
 *     type: string,
 *     preset: string,
 *     attachmentId: int,
 *     url: string,
 *     defaultUrl: string
 * }
 */
function gta6mods_get_user_avatar_choice($user_id) {
    $user_id = absint($user_id);
    $default = gta6mods_get_default_avatar_url($user_id, 256);

    $choice = [
        'type'         => '',
        'preset'       => '',
        'attachmentId' => 0,
        'url'          => $default,
        'defaultUrl'   => $default,
    ];

    if ($user_id <= 0) {
        return $choice;
    }

    $type = get_user_meta($user_id, '_gta6mods_avatar_type', true);

    if ('custom' === $type) {
        $attachment_id = (int) get_user_meta($user_id, '_gta6mods_avatar_custom', true);
        if ($attachment_id > 0) {
            $image = wp_get_attachment_image_src($attachment_id, 'full');
            if ($image && !empty($image[0])) {
                $choice['type']         = 'custom';
                $choice['attachmentId'] = $attachment_id;
                $choice['url']          = $image[0];
            }
        }
    } elseif ('preset' === $type) {
        $preset = get_user_meta($user_id, '_gta6mods_avatar_preset', true);
        if (!empty($preset)) {
            $definition = gta6mods_get_preset_avatar_definition($preset);
            if ($definition) {
                $choice['type']   = 'preset';
                $choice['preset'] = $preset;
                $choice['url']    = $definition['url'];
            }
        }
    }

    return $choice;
}

/**
 * Builds the URL to a specific page within an author profile tab.
 *
 * @param int|WP_User $user User object or ID.
 * @param string      $tab  Tab slug.
 * @param int         $page Page number (1-indexed).
 *
 * @return string
 */
function gta6mods_get_author_profile_tab_page_url($user, $tab, $page = 1) {
    $page = max(1, (int) $page);

    $base_url = gta6mods_get_author_profile_tab_url($user, $tab);

    if ('' === $base_url) {
        return '';
    }

    if ($page <= 1) {
        return $base_url;
    }

    $base_url = trailingslashit($base_url);

    return $base_url . 'page-' . $page . '/';
}

/**
 * Registers the pretty permalink structure for author tabs.
 */
function gta6mods_register_author_profile_rewrites() {
    global $wp_rewrite;

    if ($wp_rewrite instanceof WP_Rewrite && !empty($wp_rewrite->author_base)) {
        $author_base = $wp_rewrite->author_base;
    } else {
        $author_base = get_option('author_base');
    }

    if (!$author_base) {
        $author_base = 'author';
    }

    $author_base = trim($author_base, '/');

    $public_tabs = array_keys(gta6mods_get_author_profile_tabs(false));
    $owner_tabs  = array_keys(gta6mods_get_author_profile_tabs(true));
    $all_tabs    = array_unique(array_merge($public_tabs, $owner_tabs));
    $tab_slugs   = array_values(array_diff($all_tabs, ['overview']));

    if (empty($tab_slugs)) {
        return;
    }

    $tab_regex = implode('|', array_map(static function ($slug) {
        return preg_quote($slug, '/');
    }, $tab_slugs));

    $base_pattern = $author_base !== ''
        ? '^' . preg_quote($author_base, '/') . '/([^/]+)/'
        : '^author/([^/]+)/';

    $page_pattern = $base_pattern . '(' . $tab_regex . ')/page-([0-9]+)/?';
    add_rewrite_rule($page_pattern, 'index.php?author_name=$matches[1]&gta6mods_profile_tab=$matches[2]&tab_page=$matches[3]', 'top');

    $tab_pattern = $base_pattern . '(' . $tab_regex . ')/?';
    add_rewrite_rule($tab_pattern, 'index.php?author_name=$matches[1]&gta6mods_profile_tab=$matches[2]', 'top');
}
add_action('init', 'gta6mods_register_author_profile_rewrites');

/**
 * Flushes the author profile rewrites once after deployment when needed.
 */
function gta6mods_maybe_flush_author_profile_rewrites() {
    $target_version = '20240605';
    $stored_version = get_option('gta6mods_author_rewrite_version');

    if ($stored_version === $target_version) {
        return;
    }

    gta6mods_register_author_profile_rewrites();
    flush_rewrite_rules(false);
    update_option('gta6mods_author_rewrite_version', $target_version);
}
add_action('init', 'gta6mods_maybe_flush_author_profile_rewrites', 30);

/**
 * Registers custom query vars required for profile tabs.
 *
 * @param array $vars Existing query vars.
 *
 * @return array
 */
function gta6mods_author_profile_query_vars($vars) {
    $vars[] = 'gta6mods_profile_tab';
    $vars[] = 'tab_page';

    return $vars;
}
add_filter('query_vars', 'gta6mods_author_profile_query_vars');

/**
 * Registers the Status Update custom post type used for author timelines.
 */
function gta6mods_register_status_update_cpt() {
    $labels = [
        'name'               => _x('Status Updates', 'post type general name', 'gta6-mods'),
        'singular_name'      => _x('Status Update', 'post type singular name', 'gta6-mods'),
        'menu_name'          => _x('Status Updates', 'admin menu', 'gta6-mods'),
        'name_admin_bar'     => _x('Status Update', 'add new on admin bar', 'gta6-mods'),
        'add_new'            => _x('Add New', 'status update', 'gta6-mods'),
        'add_new_item'       => __('Add New Status Update', 'gta6-mods'),
        'new_item'           => __('New Status Update', 'gta6-mods'),
        'edit_item'          => __('Edit Status Update', 'gta6-mods'),
        'view_item'          => __('View Status Update', 'gta6-mods'),
        'all_items'          => __('Status Updates', 'gta6-mods'),
        'search_items'       => __('Search Status Updates', 'gta6-mods'),
        'not_found'          => __('No status updates found.', 'gta6-mods'),
        'not_found_in_trash' => __('No status updates found in Trash.', 'gta6-mods'),
    ];

    register_post_type(
        'status_update',
        [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'supports'            => ['author', 'editor'],
            'rewrite'             => false,
        ]
    );
}
add_action('init', 'gta6mods_register_status_update_cpt');

/**
 * Registers custom post statuses used by status updates.
 */
function gta6mods_register_status_update_statuses() {
    register_post_status(
        'gta-spam',
        [
            'label'                     => _x('Spam', 'status update', 'gta6-mods'),
            'public'                    => false,
            'internal'                  => true,
            'protected'                 => true,
            'exclude_from_search'       => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>', 'gta6-mods'),
        ]
    );
}
add_action('init', 'gta6mods_register_status_update_statuses');
add_filter('wp_insert_post_data', 'gta6mods_filter_status_update_post_data', 20, 2);

/**
 * Creates the author related custom database tables.
 */
function gta6mods_install_author_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $activity_table  = $wpdb->prefix . 'gta_activity';
    $notifications   = $wpdb->prefix . 'gta_notifications';
    $reports_table   = $wpdb->prefix . 'gta_reports';
    $stats_table     = gta6mods_get_mod_stats_table_name();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $activity_sql = "CREATE TABLE {$activity_table} (
        activity_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        action_type varchar(50) NOT NULL,
        object_id bigint(20) unsigned DEFAULT NULL,
        meta longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (activity_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) {$charset_collate};";

    $notification_sql = "CREATE TABLE {$notifications} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        recipient_user_id bigint(20) unsigned NOT NULL,
        actor_user_id bigint(20) unsigned DEFAULT NULL,
        action_type varchar(50) NOT NULL,
        object_id bigint(20) unsigned DEFAULT NULL,
        meta longtext NULL,
        is_read tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY recipient_user_id (recipient_user_id),
        KEY is_read (is_read),
        KEY created_at (created_at)
    ) {$charset_collate};";

    $reports_sql = "CREATE TABLE {$reports_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        reporter_user_id bigint(20) unsigned NOT NULL,
        reported_user_id bigint(20) unsigned DEFAULT NULL,
        object_id bigint(20) unsigned DEFAULT NULL,
        object_type varchar(50) NOT NULL,
        reason varchar(255) NOT NULL,
        details longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY reporter_user_id (reporter_user_id),
        KEY reported_user_id (reported_user_id)
    ) {$charset_collate};";

    $stats_sql = "CREATE TABLE {$stats_table} (
        post_id bigint(20) unsigned NOT NULL,
        downloads int unsigned NOT NULL DEFAULT 0,
        likes int unsigned NOT NULL DEFAULT 0,
        views int unsigned NOT NULL DEFAULT 0,
        rating_average decimal(3,2) unsigned NOT NULL DEFAULT 0.00,
        rating_count int unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (post_id),
        KEY downloads (downloads),
        KEY likes (likes),
        KEY rating_average (rating_average)
    ) {$charset_collate};";

    dbDelta($activity_sql);
    dbDelta($notification_sql);

    $notifications_table = $wpdb->prefix . 'gta_notifications';
    $index_exists        = $wpdb->get_var(
        $wpdb->prepare(
            "SHOW INDEX FROM {$notifications_table} WHERE Key_name = %s",
            'idx_recipient_unread'
        )
    );

    if (!$index_exists) {
        $wpdb->query("CREATE INDEX idx_recipient_unread ON {$notifications_table} (recipient_user_id, is_read, created_at)");
    }

    $reports_columns = $wpdb->get_results("SHOW COLUMNS FROM {$reports_table}", ARRAY_A);
    if (is_array($reports_columns)) {
        $has_id     = false;
        $id_is_auto = false;

        foreach ($reports_columns as $column) {
            if (!isset($column['Field'])) {
                continue;
            }

            if ('id' === $column['Field']) {
                $has_id     = true;
                $id_is_auto = isset($column['Extra']) && false !== strpos($column['Extra'], 'auto_increment');
            }

            if ('report_id' === $column['Field']) {
                if (!$has_id) {
                    $wpdb->query(
                        "ALTER TABLE {$reports_table} CHANGE report_id id bigint(20) unsigned NOT NULL AUTO_INCREMENT"
                    );
                    $has_id     = true;
                    $id_is_auto = true;
                }
            }
        }

        if ($has_id && !$id_is_auto) {
            $wpdb->query(
                "ALTER TABLE {$reports_table} MODIFY id bigint(20) unsigned NOT NULL AUTO_INCREMENT"
            );
            $id_is_auto = true;
        }

        if ($has_id) {
            $primary_key = $wpdb->get_var("SHOW KEYS FROM {$reports_table} WHERE Key_name = 'PRIMARY'");
            if (null === $primary_key) {
                $wpdb->query("ALTER TABLE {$reports_table} ADD PRIMARY KEY (id)");
            }
        }
    }

    dbDelta($reports_sql);
    dbDelta($stats_sql);

    update_option('gta6mods_author_tables_version', '1.1.0');
}

/**
 * Creates the author tables during theme activation and, if necessary, on init.
 */
function gta6mods_maybe_install_author_tables() {
    $installed = get_option('gta6mods_author_tables_version');

    if (false === $installed || version_compare($installed, '1.1.0', '<')) {
        gta6mods_install_author_tables();
    }
}
add_action('after_switch_theme', 'gta6mods_install_author_tables');
add_action('init', 'gta6mods_maybe_install_author_tables', 5);

/**
 * Helper to normalise integer meta updates.
 *
 * @param int    $user_id  User ID.
 * @param string $meta_key Meta key.
 * @param int    $amount   Amount to increase by.
 */
function gta6mods_increment_user_meta_counter($user_id, $meta_key, $amount = 1) {
    $user_id  = absint($user_id);
    $amount   = (int) $amount;
    $meta_key = sanitize_key($meta_key);

    if ($user_id <= 0 || 0 === $amount || '' === $meta_key) {
        return;
    }

    $current = (int) get_user_meta($user_id, $meta_key, true);
    $new     = max(0, $current + $amount);
    update_user_meta($user_id, $meta_key, $new);
}

/**
 * Records the last time an author's aggregate statistics were updated.
 *
 * @param int $author_id Author ID.
 */
function gta6mods_touch_author_stats_timestamp($author_id) {
    $author_id = absint($author_id);

    if ($author_id <= 0) {
        return;
    }

    update_user_meta($author_id, '_mod_stats_last_sync', current_time('timestamp', true));
}

/**
 * Flushes cached collections of mods for an author.
 *
 * @param int $author_id Author ID.
 */
function gta6mods_flush_author_mod_caches($author_id) {
    $author_id = absint($author_id);

    if ($author_id <= 0) {
        return;
    }

    for ($limit = 1; $limit <= 6; $limit++) {
        wp_cache_delete('gta6mods_popular_mods_v2_' . $author_id . '_' . $limit, 'gta6mods');
    }

    $meta_keys = ['_gta6mods_download_count', '_gta6mods_likes'];

    foreach ($meta_keys as $meta_key) {
        wp_cache_delete(sprintf('gta6mods_top_mod_v2_%d_%s_%s', $author_id, $meta_key, 'desc'), 'gta6mods');
        wp_cache_delete(sprintf('gta6mods_top_mod_v2_%d_%s_%s', $author_id, $meta_key, 'asc'), 'gta6mods');
    }

    $stat_keys = array_keys(gta6mods_get_mod_stat_columns());

    foreach ($stat_keys as $stat_key) {
        wp_cache_delete(sprintf('gta6mods_top_mod_v2_%d_%s_%s', $author_id, $stat_key, 'desc'), 'gta6mods');
        wp_cache_delete(sprintf('gta6mods_top_mod_v2_%d_%s_%s', $author_id, $stat_key, 'asc'), 'gta6mods');
    }
}

/**
 * Adjusts an author's aggregated download total.
 *
 * @param int $post_id Post ID.
 * @param int $delta   Increment (or decrement) value.
 */
function gta6mods_adjust_author_download_total($post_id, $delta = 1) {
    $post = get_post($post_id);

    if (!($post instanceof WP_Post)) {
        return;
    }

    $author_id = (int) $post->post_author;

    if ($author_id <= 0) {
        return;
    }

    gta6mods_increment_user_meta_counter($author_id, '_mod_download_total', $delta);
    gta6mods_touch_author_stats_timestamp($author_id);
    gta6mods_flush_author_mod_caches($author_id);
}

/**
 * Adjusts an author's aggregated like total.
 *
 * @param int $post_id Post ID.
 * @param int $delta   Increment (or decrement) value.
 */
function gta6mods_adjust_author_like_total($post_id, $delta) {
    $post = get_post($post_id);

    if (!($post instanceof WP_Post)) {
        return;
    }

    $author_id = (int) $post->post_author;

    if ($author_id <= 0) {
        return;
    }

    gta6mods_increment_user_meta_counter($author_id, '_mod_like_total', $delta);
    gta6mods_touch_author_stats_timestamp($author_id);
    gta6mods_flush_author_mod_caches($author_id);
}

/**
 * Sums a numeric post meta across all published mods for a user.
 *
 * @param int    $author_id Author ID.
 * @param string $meta_key  Meta key to sum.
 *
 * @return int
 */
function gta6mods_sum_author_post_meta($author_id, $meta_key) {
    $author_id = absint($author_id);
    $meta_key  = sanitize_key($meta_key);

    if ($author_id <= 0 || '' === $meta_key) {
        return 0;
    }

    $stat = gta6mods_map_meta_key_to_stat($meta_key);

    if (null !== $stat) {
        return gta6mods_sum_author_stat($author_id, $stat);
    }

    global $wpdb;

    $post_types = gta6mods_get_mod_post_types();

    if (empty($post_types)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    $sql = "SELECT SUM(CAST(pm.meta_value AS UNSIGNED)) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_author = %d AND p.post_status = 'publish' AND p.post_type IN ({$placeholders}) AND pm.meta_key = %s";

    $prepared = $wpdb->prepare($sql, array_merge([$author_id], $post_types, [$meta_key]));

    if (false === $prepared) {
        return 0;
    }

    $sum = (int) $wpdb->get_var($prepared);

    return max(0, $sum);
}

/**
 * Ensures the stored author stats reflect the latest per-mod metrics.
 *
 * @param int $author_id Author ID.
 */
function gta6mods_maybe_sync_author_stats($author_id) {
    $author_id = absint($author_id);

    if ($author_id <= 0) {
        return;
    }

    $last_sync = (int) get_user_meta($author_id, '_mod_stats_last_sync', true);
    $now       = current_time('timestamp', true);
    $needs_sync = (0 === $last_sync) || ($now - $last_sync > 6 * HOUR_IN_SECONDS);

    $stored_downloads = (int) get_user_meta($author_id, '_mod_download_total', true);
    $stored_likes     = (int) get_user_meta($author_id, '_mod_like_total', true);

    if (!$needs_sync && $stored_downloads > 0 && $stored_likes > 0) {
        return;
    }

    $download_sum = gta6mods_sum_author_post_meta($author_id, '_gta6mods_download_count');
    if ($download_sum !== $stored_downloads && $download_sum >= 0) {
        update_user_meta($author_id, '_mod_download_total', $download_sum);
    }

    $like_sum = gta6mods_sum_author_post_meta($author_id, '_gta6mods_likes');
    if ($like_sum !== $stored_likes && $like_sum >= 0) {
        update_user_meta($author_id, '_mod_like_total', $like_sum);
    }

    gta6mods_touch_author_stats_timestamp($author_id);
}

/**
 * Retrieves the current snapshot of author statistics.
 *
 * @param int $author_id Author ID.
 *
 * @return array
 */
function gta6mods_get_author_stats_snapshot($author_id) {
    $author_id = absint($author_id);

    $defaults = [
        'uploads'   => 0,
        'downloads' => 0,
        'likes'     => 0,
        'comments'  => 0,
        'followers' => 0,
        'videos'    => 0,
    ];

    if ($author_id <= 0) {
        return $defaults;
    }

    gta6mods_maybe_sync_author_stats($author_id);

    $stats = [
        'uploads'   => (int) get_user_meta($author_id, '_mod_upload_count', true),
        'downloads' => (int) get_user_meta($author_id, '_mod_download_total', true),
        'likes'     => (int) get_user_meta($author_id, '_mod_like_total', true),
        'comments'  => (int) get_user_meta($author_id, '_mod_comment_count', true),
        'followers' => (int) get_user_meta($author_id, '_follower_count', true),
        'videos'    => (int) get_user_meta($author_id, '_video_count', true),
    ];

    return $stats + $defaults;
}

/**
 * Logs an activity item to the custom activity table.
 *
 * @param int    $user_id     User ID performing the action.
 * @param string $action_type Action type identifier.
 * @param int    $object_id   Optional object ID related to the activity.
 * @param array  $meta        Optional associative meta data.
 */
function gta6mods_log_activity($user_id, $action_type, $object_id = 0, $meta = []) {
    global $wpdb;

    $user_id     = absint($user_id);
    $object_id   = absint($object_id);
    $action_type = sanitize_key($action_type);

    if ($user_id <= 0 || '' === $action_type) {
        return;
    }

    $table = $wpdb->prefix . 'gta_activity';

    $wpdb->insert(
        $table,
        [
            'user_id'     => $user_id,
            'action_type' => $action_type,
            'object_id'   => $object_id,
            'meta'        => !empty($meta) ? wp_json_encode($meta) : null,
            'created_at'  => current_time('mysql', true),
        ],
        ['%d', '%s', '%d', '%s', '%s']
    );
}

/**
 * Removes activity entries for a user by matching action and optional object/meta fragments.
 *
 * @param int    $user_id     User ID.
 * @param string $action_type Action type key.
 * @param int    $object_id   Optional related object ID.
 * @param string $meta_match  Optional meta fragment to match against (using LIKE).
 */
function gta6mods_remove_activity_entry($user_id, $action_type, $object_id = 0, $meta_match = '') {
    global $wpdb;

    $user_id     = absint($user_id);
    $object_id   = absint($object_id);
    $action_type = sanitize_key($action_type);

    if ($user_id <= 0 || '' === $action_type) {
        return;
    }

    $table = $wpdb->prefix . 'gta_activity';

    if ('' !== $meta_match) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE user_id = %d AND action_type = %s AND object_id = %d AND meta LIKE %s",
                $user_id,
                $action_type,
                $object_id,
                '%' . $wpdb->esc_like($meta_match) . '%'
            )
        );
        return;
    }

    $where   = ['user_id' => $user_id, 'action_type' => $action_type];
    $formats = ['%d', '%s'];

    if ($object_id > 0) {
        $where['object_id'] = $object_id;
        $formats[]          = '%d';
    }

    $wpdb->delete($table, $where, $formats);
}

/**
 * Adds a notification for a user, ensuring the list is capped at 100.
 *
 * @param int    $recipient_id Recipient user ID.
 * @param int    $actor_id     Optional actor user ID.
 * @param string $action_type  Notification type.
 * @param int    $object_id    Related object ID.
 * @param array  $meta         Optional meta data.
 */
function gta6mods_add_notification($recipient_id, $actor_id, $action_type, $object_id = 0, $meta = []) {
    global $wpdb;

    $recipient_id = absint($recipient_id);
    $actor_id     = absint($actor_id);
    $object_id    = absint($object_id);
    $action_type  = sanitize_key($action_type);

    if ($recipient_id <= 0 || '' === $action_type) {
        return;
    }

    $table = $wpdb->prefix . 'gta_notifications';

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE recipient_user_id = %d",
            $recipient_id
        )
    );

    if ($count >= 100) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE recipient_user_id = %d ORDER BY created_at ASC LIMIT %d",
                $recipient_id,
                max(1, $count - 99)
            )
        );
    }

    $wpdb->insert(
        $table,
        [
            'recipient_user_id' => $recipient_id,
            'actor_user_id'     => $actor_id > 0 ? $actor_id : null,
            'action_type'       => $action_type,
            'object_id'         => $object_id,
            'meta'              => !empty($meta) ? wp_json_encode($meta) : null,
            'created_at'        => current_time('mysql', true),
        ],
        ['%d', '%d', '%s', '%d', '%s', '%s']
    );

    gta6mods_invalidate_notification_cache($recipient_id);
}

/**
 * Retrieves the count of unread notifications for a user using the object cache.
 *
 * Falls back to querying the database if the cache is empty. The value is cached for
 * five minutes to reduce repeated queries.
 *
 * @param int $user_id User ID.
 *
 * @return int Number of unread notifications.
 */
function gta6mods_get_unread_count_cached($user_id) {
    $user_id = absint($user_id);

    if ($user_id <= 0) {
        return 0;
    }

    $cache_key    = "unread_count_{$user_id}";
    $cached_count = wp_cache_get($cache_key, 'gta6mods_notifications');

    if (false !== $cached_count && is_numeric($cached_count)) {
        return (int) $cached_count;
    }

    global $wpdb;

    $table = $wpdb->prefix . 'gta_notifications';
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE recipient_user_id = %d AND is_read = 0",
            $user_id
        )
    );

    wp_cache_set($cache_key, $count, 'gta6mods_notifications', 5 * MINUTE_IN_SECONDS);

    return $count;
}

/**
 * Retrieves unread notifications for a user with caching support.
 *
 * @param int $user_id User ID.
 * @param int $limit   Optional. Maximum number of notifications. Default 10.
 *
 * @return array<int, array<string, mixed>>
 */
function gta6mods_get_unread_notifications_cached($user_id, $limit = 10) {
    $user_id = absint($user_id);

    if ($user_id <= 0) {
        return [];
    }

    $limit = absint($limit);

    if ($limit <= 0) {
        $limit = 10;
    }

    $cache_key   = "unread_list_{$user_id}";
    $cached_list = wp_cache_get($cache_key, 'gta6mods_notifications');

    if (false !== $cached_list && is_array($cached_list)) {
        return $cached_list;
    }

    $notifications = gta6mods_get_user_notifications($user_id, $limit, true);

    wp_cache_set($cache_key, $notifications, 'gta6mods_notifications', 5 * MINUTE_IN_SECONDS);

    return $notifications;
}

/**
 * Retrieves the most recent notifications for a user with caching support.
 *
 * The returned collection includes both read and unread entries so the
 * dropdown can provide historical context while still highlighting new
 * notifications.
 *
 * @param int $user_id User ID.
 * @param int $limit   Optional. Maximum number of notifications. Default 5.
 *
 * @return array<int, array<string, mixed>>
 */
function gta6mods_get_recent_notifications_cached($user_id, $limit = 5) {
    $user_id = absint($user_id);

    if ($user_id <= 0) {
        return [];
    }

    $limit = absint($limit);

    if ($limit <= 0) {
        $limit = 5;
    }

    $cache_key   = "recent_list_{$user_id}";
    $cached_list = wp_cache_get($cache_key, 'gta6mods_notifications');

    if (false !== $cached_list && is_array($cached_list)) {
        return array_slice($cached_list, 0, $limit);
    }

    $notifications = gta6mods_get_user_notifications($user_id, $limit, false);

    wp_cache_set($cache_key, $notifications, 'gta6mods_notifications', 5 * MINUTE_IN_SECONDS);

    return $notifications;
}

/**
 * Generates a contextual link for a notification entry.
 *
 * For comment related notifications the link points directly to the comment
 * on the mod detail page using the custom `/comments/` slug, while other
 * post-related notifications fall back to the post permalink when available.
 *
 * @param array<string, mixed> $notification Notification data.
 *
 * @return string Notification target URL or an empty string if unavailable.
 */
function gta6mods_get_notification_link($notification) {
    if (!is_array($notification) || empty($notification)) {
        return '';
    }

    $action_type = isset($notification['action_type']) ? (string) $notification['action_type'] : '';
    $meta        = isset($notification['meta']) && is_array($notification['meta']) ? $notification['meta'] : [];
    $object_id   = isset($notification['object_id']) ? (int) $notification['object_id'] : 0;

    if ('commented' === $action_type) {
        $comment_id = isset($meta['comment_id']) ? (int) $meta['comment_id'] : 0;

        if ($comment_id <= 0) {
            $comment_id = $object_id;
        }

        if ($comment_id > 0) {
            $comment_link = gta6mods_get_comment_permalink($comment_id);

            if ($comment_link) {
                return $comment_link;
            }
        }

        if (!empty($meta['post_id'])) {
            $post_id   = (int) $meta['post_id'];
            $permalink = get_permalink($post_id);

            if ($permalink) {
                $permalink = trailingslashit($permalink);
                $post      = get_post($post_id);

                if (!$post instanceof WP_Post || !has_post_format('link', $post)) {
                    $permalink .= 'comments/';
                }

                if ($comment_id > 0) {
                    $permalink .= '#comment-' . $comment_id;
                }

                return $permalink;
            }
        }
    }

    $post_id = 0;

    if (!empty($meta['post_id'])) {
        $post_id = (int) $meta['post_id'];
    } elseif (in_array($action_type, ['liked'], true) && $object_id > 0) {
        $post = get_post($object_id);

        if ($post instanceof WP_Post) {
            $post_id = $post->ID;
        }
    }

    if ($post_id > 0) {
        $permalink = get_permalink($post_id);

        return $permalink ? $permalink : '';
    }

    return '';
}

/**
 * Provides icon and styling classes for a notification entry.
 *
 * @param string $action_type Notification action identifier.
 *
 * @return array{icon:string,wrapper:string} Associative array containing the icon class
 *                                          and the wrapper background/text classes.
 */
function gta6mods_get_notification_icon_styles($action_type) {
    $action_type = is_string($action_type) ? sanitize_key($action_type) : '';

    $defaults = [
        'icon'    => 'fa-bell',
        'wrapper' => 'bg-pink-100 text-pink-600',
    ];

    switch ($action_type) {
        case 'followed':
            return [
                'icon'    => 'fa-user-plus',
                'wrapper' => 'bg-blue-100 text-blue-600',
            ];
        case 'commented':
            return [
                'icon'    => 'fa-comment-dots',
                'wrapper' => 'bg-green-100 text-green-600',
            ];
        case 'liked':
            return [
                'icon'    => 'fa-star',
                'wrapper' => 'bg-yellow-100 text-yellow-600',
            ];
        case 'collection_added':
            return [
                'icon'    => 'fa-layer-group',
                'wrapper' => 'bg-purple-100 text-purple-600',
            ];
        case 'milestone':
            return [
                'icon'    => 'fa-trophy',
                'wrapper' => 'bg-red-100 text-red-600',
            ];
        default:
            return $defaults;
    }
}

/**
 * Clears cached notification data for a user.
 *
 * @param int $user_id User ID.
 */
function gta6mods_invalidate_notification_cache($user_id) {
    $user_id = absint($user_id);

    if ($user_id <= 0) {
        return;
    }

    wp_cache_delete("unread_count_{$user_id}", 'gta6mods_notifications');
    wp_cache_delete("unread_list_{$user_id}", 'gta6mods_notifications');
    wp_cache_delete("recent_list_{$user_id}", 'gta6mods_notifications');
}

/**
 * Removes notifications for inactive users. The cleanup is rate-limited via a transient.
 */
function gta6mods_cleanup_inactive_user_notifications() {
    if (false !== get_transient('gta6mods_notification_cleanup_lock')) {
        return;
    }

    global $wpdb;

    $threshold = gmdate('Y-m-d H:i:s', strtotime('-2 years'));
    $user_ids  = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value < %s",
            '_last_activity',
            $threshold
        )
    );

    $user_ids = array_filter(array_map('absint', $user_ids));

    if (!empty($user_ids)) {
        $table        = $wpdb->prefix . 'gta_notifications';
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $sql          = "DELETE FROM {$table} WHERE recipient_user_id IN ({$placeholders})";

        $prepared = $wpdb->prepare($sql, ...$user_ids);

        if ($prepared) {
            $wpdb->query($prepared);
        }
    }

    set_transient('gta6mods_notification_cleanup_lock', 1, DAY_IN_SECONDS);
}

/**
 * Tracks post status transitions to maintain author statistics and activity.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post object.
 */
function gta6mods_handle_post_status_transition($new_status, $old_status, $post) {
    if (!($post instanceof WP_Post)) {
        return;
    }

    if (!in_array($post->post_type, gta6mods_get_mod_post_types(), true)) {
        return;
    }

    $author_id = (int) $post->post_author;

    if ($author_id <= 0) {
        return;
    }

    if ('publish' === $new_status && 'publish' !== $old_status) {
        gta6mods_increment_user_meta_counter($author_id, '_mod_upload_count', 1);
        gta6mods_log_activity($author_id, 'mod_published', $post->ID, [
            'post_title' => get_the_title($post),
        ]);
    } elseif ('publish' !== $new_status && 'publish' === $old_status) {
        gta6mods_increment_user_meta_counter($author_id, '_mod_upload_count', -1);
    }
}
add_action('transition_post_status', 'gta6mods_handle_post_status_transition', 10, 3);

/**
 * Updates statistics and activity stream when comments are inserted.
 *
 * @param int        $comment_id Comment ID.
 * @param WP_Comment $comment    Comment object.
 */
function gta6mods_handle_comment_insert($comment_id, $comment) {
    if (!($comment instanceof WP_Comment)) {
        return;
    }

    if ('spam' === $comment->comment_approved || 'trash' === $comment->comment_approved) {
        return;
    }

    if (!in_array($comment->comment_approved, ['1', 'approve'], true)) {
        return;
    }

    $post = get_post($comment->comment_post_ID);

    if (!($post instanceof WP_Post)) {
        return;
    }

    if (!in_array($post->post_type, gta6mods_get_mod_post_types(), true)) {
        return;
    }

    $author_id    = (int) $post->post_author;
    $commenter_id = (int) $comment->user_id;
    $post_title   = get_the_title($post);
    $comment_meta = [
        'comment_id'  => $comment_id,
        'post_id'     => (int) $post->ID,
        'post_title'  => $post_title,
        'excerpt'     => wp_trim_words(wp_strip_all_tags($comment->comment_content), 25, '…'),
    ];

    if ($author_id > 0) {
        gta6mods_increment_user_meta_counter($author_id, '_mod_comment_count', 1);
    }

    if ($commenter_id > 0) {
        gta6mods_log_activity($commenter_id, 'mod_commented', $post->ID, $comment_meta);

        if ($author_id > 0 && $author_id !== $commenter_id) {
            gta6mods_add_notification($author_id, $commenter_id, 'commented', $comment_id, $comment_meta);
        }
    } elseif ($author_id > 0) {
        // Notify the mod author even if the commenter is a guest.
        gta6mods_add_notification($author_id, 0, 'commented', $comment_id, $comment_meta);
    }
}
add_action('wp_insert_comment', 'gta6mods_handle_comment_insert', 10, 2);

/**
 * Decrements the comment counter when a comment status changes away from approved.
 *
 * @param string     $new_status New status.
 * @param string     $old_status Old status.
 * @param WP_Comment $comment    Comment object.
 */
function gta6mods_handle_comment_status_transition($new_status, $old_status, $comment) {
    if (!($comment instanceof WP_Comment)) {
        return;
    }

    if ($new_status === $old_status || 'new' === $old_status) {
        return;
    }

    $post = get_post($comment->comment_post_ID);

    if (!($post instanceof WP_Post)) {
        return;
    }

    if (!in_array($post->post_type, gta6mods_get_mod_post_types(), true)) {
        return;
    }

    $author_id    = (int) $post->post_author;
    $commenter_id = (int) $comment->user_id;
    $comment_id   = (int) $comment->comment_ID;
    $comment_meta = [
        'comment_id' => $comment_id,
        'post_id'    => (int) $post->ID,
        'post_title' => get_the_title($post),
        'excerpt'    => wp_trim_words(wp_strip_all_tags($comment->comment_content), 25, '…'),
    ];

    if ($author_id <= 0) {
        return;
    }

    $was_approved = in_array($old_status, ['1', 'approve'], true);
    $is_approved  = in_array($new_status, ['1', 'approve'], true);

    if ($was_approved && !$is_approved) {
        gta6mods_increment_user_meta_counter($author_id, '_mod_comment_count', -1);

        if ($commenter_id > 0) {
            gta6mods_remove_activity_entry($commenter_id, 'mod_commented', $post->ID, "\"comment_id\":{$comment_id}");
        }
    } elseif (!$was_approved && $is_approved) {
        gta6mods_increment_user_meta_counter($author_id, '_mod_comment_count', 1);

        if ($commenter_id > 0) {
            gta6mods_log_activity($commenter_id, 'mod_commented', $post->ID, $comment_meta);

            if ($author_id !== $commenter_id) {
                gta6mods_add_notification($author_id, $commenter_id, 'commented', $comment_id, $comment_meta);
            }
        } else {
            gta6mods_add_notification($author_id, 0, 'commented', $comment_id, $comment_meta);
        }
    }
}
add_action('transition_comment_status', 'gta6mods_handle_comment_status_transition', 10, 3);

/**
 * Stores follow relationships between users.
 *
 * @param int $follower_id Follower user ID.
 * @param int $author_id   Author user ID.
 * @param bool $follow     True to follow, false to unfollow.
 */
function gta6mods_toggle_follow($follower_id, $author_id, $follow = true) {
    $follower_id = absint($follower_id);
    $author_id   = absint($author_id);

    if ($follower_id <= 0 || $author_id <= 0 || $follower_id === $author_id) {
        return false;
    }

    $meta_key  = '_followers';
    $followers = (array) get_user_meta($author_id, $meta_key, true);
    $following = (array) get_user_meta($follower_id, '_following', true);

    if ($follow) {
        if (!in_array($follower_id, $followers, true)) {
            $followers[] = $follower_id;
            $followers = array_values(array_unique(array_map('absint', $followers)));
            $following = array_values(array_unique(array_map('absint', $following)));

            if (!in_array($author_id, $following, true)) {
                $following[] = $author_id;
            }

            update_user_meta($author_id, $meta_key, $followers);
            update_user_meta($follower_id, '_following', $following);
            gta6mods_increment_user_meta_counter($author_id, '_follower_count', 1);
            gta6mods_remove_activity_entry($author_id, 'followed', $follower_id);
            gta6mods_log_activity($follower_id, 'following', $author_id, []);
            gta6mods_add_notification($author_id, $follower_id, 'followed');
        }
        return true;
    }

    if (in_array($follower_id, $followers, true)) {
        $followers = array_diff($followers, [$follower_id]);
        $following = array_diff($following, [$author_id]);
        update_user_meta($author_id, $meta_key, array_values(array_map('absint', $followers)));
        update_user_meta($follower_id, '_following', array_values(array_map('absint', $following)));
        gta6mods_increment_user_meta_counter($author_id, '_follower_count', -1);
        gta6mods_remove_activity_entry($author_id, 'followed', $follower_id);
    }

    return true;
}

/**
 * Records a report and increments the report counter on the reported user.
 *
 * @param array $data Report payload.
 *
 * @return bool True on success.
 */
function gta6mods_record_report($data) {
    global $wpdb;

    $defaults = [
        'reporter_user_id' => 0,
        'reported_user_id' => 0,
        'object_id'        => 0,
        'object_type'      => '',
        'reason'           => '',
        'details'          => '',
    ];

    $data = wp_parse_args($data, $defaults);

    $reporter_user_id = absint($data['reporter_user_id']);
    $reported_user_id = absint($data['reported_user_id']);
    $object_id        = absint($data['object_id']);
    $object_type      = sanitize_key($data['object_type']);
    $reason           = sanitize_text_field($data['reason']);
    $details          = wp_kses_post($data['details']);

    if ($reporter_user_id <= 0 || '' === $object_type || '' === $reason) {
        return false;
    }

    $table = $wpdb->prefix . 'gta_reports';

    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE reporter_user_id = %d AND object_id = %d AND object_type = %s LIMIT 1",
            $reporter_user_id,
            $object_id,
            $object_type
        )
    );

    if ($existing) {
        return true;
    }

    $inserted = $wpdb->insert(
        $table,
        [
            'reporter_user_id' => $reporter_user_id,
            'reported_user_id' => $reported_user_id > 0 ? $reported_user_id : null,
            'object_id'        => $object_id,
            'object_type'      => $object_type,
            'reason'           => $reason,
            'details'          => $details,
            'created_at'       => current_time('mysql', true),
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s', '%s']
    );

    if (false === $inserted) {
        return false;
    }

    if ($reported_user_id > 0) {
        gta6mods_increment_user_meta_counter($reported_user_id, '_report_count', 1);
    }

    return true;
}

/**
 * Clears stored reports for the provided status update IDs.
 *
 * @param int[] $status_ids Status update post IDs.
 */
function gta6mods_clear_status_reports($status_ids) {
    global $wpdb;

    $status_ids = array_filter(array_map('absint', (array) $status_ids));

    if (empty($status_ids)) {
        return;
    }

    $table = $wpdb->prefix . 'gta_reports';
    $placeholders = implode(',', array_fill(0, count($status_ids), '%d'));

    $select_sql = $wpdb->prepare(
        "SELECT reported_user_id, object_id FROM {$table} WHERE object_type = 'status_update' AND object_id IN ($placeholders)",
        $status_ids
    );

    $rows = $wpdb->get_results($select_sql, ARRAY_A);

    if (empty($rows)) {
        // Nothing to clear.
        return;
    }

    $delete_sql = $wpdb->prepare(
        "DELETE FROM {$table} WHERE object_type = 'status_update' AND object_id IN ($placeholders)",
        $status_ids
    );
    $wpdb->query($delete_sql);

    $decrement = [];
    foreach ($rows as $row) {
        $reported_user_id = isset($row['reported_user_id']) ? (int) $row['reported_user_id'] : 0;
        if ($reported_user_id > 0) {
            if (!isset($decrement[$reported_user_id])) {
                $decrement[$reported_user_id] = 0;
            }
            $decrement[$reported_user_id]++;
        }
    }

    if (!empty($decrement)) {
        foreach ($decrement as $user_id => $count) {
            gta6mods_increment_user_meta_counter($user_id, '_report_count', -absint($count));
        }
    }
}

/**
 * Retrieves activity entries for a user.
 *
 * @param int $user_id User ID.
 * @param int $limit   Number of results.
 *
 * @return array
 */
function gta6mods_get_user_activity($user_id, $limit = 10, $offset = 0) {
    global $wpdb;

    $user_id = absint($user_id);
    $limit   = absint($limit);
    $offset  = absint($offset);

    if ($user_id <= 0) {
        return [];
    }

    if ($limit <= 0) {
        $limit = 10;
    }

    $table = $wpdb->prefix . 'gta_activity';

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND action_type <> %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            'followed',
            $limit,
            $offset
        ),
        ARRAY_A
    );

    $results = array_map(static function ($item) {
        if (!empty($item['meta'])) {
            $meta       = json_decode($item['meta'], true);
            $item['meta'] = is_array($meta) ? $meta : [];
        } else {
            $item['meta'] = [];
        }
        return $item;
    }, $results);

    return array_values($results);
}

/**
 * Normalizes raw status update content.
 *
 * @param string $content     Raw content.
 * @param int    $max_length  Maximum character length.
 *
 * @return string
 */
function gta6mods_normalize_status_content($content, $max_length = 5000) {
    $content = (string) $content;
    if ('' === $content) {
        return '';
    }

    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = sanitize_textarea_field($content);
    $content = preg_replace('/\h+\n/u', "\n", $content);
    $content = preg_replace("/\n{3,}/", "\n\n", $content);
    $content = trim($content);

    if ('' === $content) {
        return '';
    }

    if ($max_length > 0) {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($content, 'UTF-8') > $max_length) {
                $content = mb_substr($content, 0, $max_length, 'UTF-8');
            }
        } elseif (strlen($content) > $max_length) {
            $content = substr($content, 0, $max_length);
        }
    }

    return $content;
}

/**
 * REST API sanitize callback for status update content.
 *
 * @param string          $value   Raw content value.
 * @param WP_REST_Request $request Current request.
 * @param string          $param   Parameter name.
 *
 * @return string
 */
function gta6mods_rest_sanitize_status_content($value, $request = null, $param = '') {
    return gta6mods_normalize_status_content($value);
}

/**
 * Renders status update content with link and newline formatting.
 *
 * @param string $content Stored post content.
 *
 * @return string
 */
function gta6mods_render_status_update_content($content) {
    $content = (string) $content;
    if ('' === $content) {
        return '';
    }

    $content = str_replace(['&nbsp;', "\r\n", "\r"], [' ', "\n", "\n"], $content);
    $content = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $content);
    $content = preg_replace('/<\/\s*p\s*>/i', "\n\n", $content);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
    $content = gta6mods_normalize_status_content($content);

    if ('' === $content) {
        return '';
    }

    $pattern = '/https?:\/\/(?:www\.)?gta6-mods\.com[^\s]*/i';
    $parts   = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

    if (!is_array($parts)) {
        $parts = [$content];
    }

    $html = '';

    foreach ($parts as $part) {
        if (preg_match($pattern, $part)) {
            $url   = esc_url($part);
            $title = esc_attr__('Visit this GTA6-Mods link', 'gta6-mods');
            $html .= sprintf('<a href="%1$s" class="text-pink-600 underline hover:text-pink-700" title="%2$s">%3$s</a>', $url, $title, esc_html($part));
            continue;
        }

        $html .= esc_html($part);
    }

    return nl2br($html);
}

/**
 * Ensures status updates saved via the editor are normalized.
 *
 * @param array $data    Sanitized post data.
 * @param array $postarr Raw post array.
 *
 * @return array
 */
function gta6mods_filter_status_update_post_data($data, $postarr) {
    if (empty($data['post_type']) || 'status_update' !== $data['post_type']) {
        return $data;
    }

    $normalized = gta6mods_normalize_status_content($data['post_content'] ?? '');
    $data['post_content'] = $normalized;
    $data['post_title']   = wp_trim_words($normalized, 8, '...');

    return $data;
}

/**
 * Formats an activity message for display.
 *
 * @param array $activity Activity row data.
 *
 * @return string
 */
function gta6mods_format_activity_message($activity) {
    $action = $activity['action_type'] ?? '';
    $meta   = $activity['meta'] ?? [];

    switch ($action) {
        case 'mod_published':
            $title = !empty($meta['post_title']) ? $meta['post_title'] : __('a mod', 'gta6-mods');
            /* translators: %s: Mod title. */
            return sprintf(__('Published the mod %s', 'gta6-mods'), $title);
        case 'mod_commented':
            $title = !empty($meta['post_title']) ? $meta['post_title'] : __('a mod', 'gta6-mods');
            /* translators: %s: Mod title. */
            return sprintf(__('Commented on %s', 'gta6-mods'), $title);
        case 'status_posted':
            return __('Shared a new status update', 'gta6-mods');
        case 'followed':
            return __('Gained a new follower', 'gta6-mods');
        case 'following':
            return __('Followed another creator', 'gta6-mods');
        default:
            if (!empty($meta['message'])) {
                return $meta['message'];
            }

            return __('Had some activity on the site.', 'gta6-mods');
    }
}

/**
 * Returns icon classes for an activity type.
 *
 * @param string $action_type Activity action identifier.
 *
 * @return string
 */
function gta6mods_format_activity_icon($action_type) {
    switch ($action_type) {
        case 'mod_published':
            return 'fa-upload bg-pink-100 text-pink-600';
        case 'mod_commented':
            return 'fa-comments bg-green-100 text-green-600';
        case 'status_posted':
            return 'fa-comment-dots bg-purple-100 text-purple-600';
        case 'followed':
            return 'fa-user-plus bg-blue-100 text-blue-600';
        case 'following':
            return 'fa-user-check bg-indigo-100 text-indigo-600';
        default:
            return 'fa-bolt bg-yellow-100 text-yellow-600';
    }
}

/**
 * Returns the IDs of status updates that have been reported by users.
 *
 * @return int[]
 */
function gta6mods_get_reported_status_update_ids() {
    global $wpdb;

    $table = $wpdb->prefix . 'gta_reports';
    $ids   = $wpdb->get_col("SELECT DISTINCT object_id FROM {$table} WHERE object_type = 'status_update'");

    return array_values(array_filter(array_map('absint', (array) $ids)));
}

/**
 * Retrieves how many reports a status update has received.
 *
 * @param int $status_id Status update post ID.
 *
 * @return int
 */
function gta6mods_get_status_report_count($status_id) {
    $status_id = absint($status_id);

    if ($status_id <= 0) {
        return 0;
    }

    global $wpdb;

    $table = $wpdb->prefix . 'gta_reports';

    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE object_type = 'status_update' AND object_id = %d",
            $status_id
        )
    );

    return (int) $count;
}

if (is_admin()) {
    if (!class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }
    add_action('admin_menu', 'gta6mods_register_status_update_admin_page');
    add_filter('set-screen-option', 'gta6mods_status_updates_set_screen_option', 10, 3);
    add_action('admin_notices', 'gta6mods_status_updates_admin_notices');
    add_action('admin_head', 'gta6mods_status_updates_admin_styles');
}

/**
 * Registers the status update moderation page under the Comments menu.
 */
function gta6mods_register_status_update_admin_page() {
    $hook = add_submenu_page(
        'edit-comments.php',
        __('Status Updates', 'gta6-mods'),
        __('Status Updates', 'gta6-mods'),
        'moderate_comments',
        'gta6mods-status-updates',
        'gta6mods_render_status_updates_admin_page'
    );

    if ($hook) {
        add_action("load-{$hook}", 'gta6mods_status_updates_admin_load');
    }
}

/**
 * Handles screen options and list table bootstrap when loading the moderation screen.
 */
function gta6mods_status_updates_admin_load() {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

    add_screen_option(
        'per_page',
        [
            'label'   => __('Status updates per page', 'gta6-mods'),
            'default' => 20,
            'option'  => 'gta6mods_status_updates_per_page',
        ]
    );

    global $gta6mods_status_updates_list_table;
    $gta6mods_status_updates_list_table = new GTA6Mods_Status_Update_List_Table();
    $gta6mods_status_updates_list_table->process_actions();
}

/**
 * Persists the custom per-page screen option value.
 *
 * @param mixed  $status Default value.
 * @param string $option Option name.
 * @param mixed  $value  Submitted value.
 *
 * @return mixed
 */
function gta6mods_status_updates_set_screen_option($status, $option, $value) {
    if ('gta6mods_status_updates_per_page' === $option) {
        return (int) $value;
    }

    return $status;
}

/**
 * Renders admin notices after moderation actions.
 */
function gta6mods_status_updates_admin_notices() {
    if (!isset($_GET['page']) || 'gta6mods-status-updates' !== $_GET['page']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $message_key = isset($_GET['gta6mods-status-updates-message']) ? sanitize_key(wp_unslash($_GET['gta6mods-status-updates-message'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ('' === $message_key) {
        return;
    }

    $messages = [
        'trash'         => __('The selected status updates have been moved to the Trash.', 'gta6-mods'),
        'delete'        => __('The selected status updates have been deleted.', 'gta6-mods'),
        'restore'       => __('The selected status updates have been restored.', 'gta6-mods'),
        'spam'          => __('The selected status updates have been marked as spam.', 'gta6-mods'),
        'unspam'        => __('The selected status updates have been published.', 'gta6-mods'),
        'clear_reports' => __('Reports cleared for the selected status updates.', 'gta6-mods'),
    ];

    if (!isset($messages[$message_key])) {
        return;
    }

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html($messages[$message_key])
    );
}

/**
 * Outputs lightweight styling for the status updates moderation table.
 */
function gta6mods_status_updates_admin_styles() {
    $screen = get_current_screen();

    if (!$screen || 'comments_page_gta6mods-status-updates' !== $screen->id) {
        return;
    }

    echo '<style>.status-reported td{box-shadow:inset 3px 0 0 #ec4899;background-color:#fff5f5;}</style>';
}

/**
 * List table implementation for moderating author status updates.
 */
if (class_exists('WP_List_Table')) {
    class GTA6Mods_Status_Update_List_Table extends WP_List_Table {
    /**
     * Cached list of reported status IDs.
     *
     * @var int[]
     */
    protected $reported_status_ids = [];

    /**
     * Whether the current query should order by report count.
     *
     * @var bool
     */
    protected $ordering_by_reports = false;

    /**
     * Sort direction for the reports column.
     *
     * @var string
     */
    protected $reports_order = 'DESC';

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'status-update',
            'plural'   => 'status-updates',
            'screen'   => 'comments_page_gta6mods-status-updates',
        ]);

        $reported = gta6mods_get_reported_status_update_ids();
        $this->reported_status_ids = array_filter(
            array_map(
                static function ($status_id) {
                    $post = get_post($status_id);
                    return ($post instanceof WP_Post) ? $status_id : null;
                },
                $reported
            )
        );
    }

    /**
     * Returns the list table columns.
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'content'  => __('Status update', 'gta6-mods'),
            'author'   => __('Author', 'gta6-mods'),
            'reports'  => __('Reports', 'gta6-mods'),
            'status'   => __('Status', 'gta6-mods'),
            'date'     => __('Date', 'gta6-mods'),
        ];
    }

    /**
     * Sortable columns.
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return [
            'author'  => ['author', false],
            'date'    => ['date', true],
            'reports' => ['reports', true],
        ];
    }

    /**
     * Bulk actions.
     *
     * @return array
     */
    protected function get_bulk_actions() {
        return [
            'trash'         => __('Move to Trash', 'gta6-mods'),
            'spam'          => __('Mark as spam', 'gta6-mods'),
            'unspam'        => __('Publish', 'gta6-mods'),
            'delete'        => __('Delete permanently', 'gta6-mods'),
            'clear_reports' => __('Clear reports', 'gta6-mods'),
        ];
    }

    /**
     * View filters displayed above the table.
     *
     * @return array
     */
    protected function get_views() {
        $counts      = wp_count_posts('status_update', 'readable');
        $current     = isset($_REQUEST['status']) ? sanitize_key(wp_unslash($_REQUEST['status'])) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search_term = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $base_args = ['page' => 'gta6mods-status-updates'];
        if ($search_term) {
            $base_args['s'] = $search_term;
        }

        $base_url = add_query_arg($base_args, admin_url('edit-comments.php'));

        $statuses = ['publish', 'pending', 'draft', 'gta-spam', 'trash'];
        $all_count = 0;
        foreach ($statuses as $status) {
            if (isset($counts->{$status})) {
                $all_count += (int) $counts->{$status};
            }
        }

        $views = [];
        $views['all'] = sprintf(
            '<a href="%s"%s>%s</a>',
            esc_url(remove_query_arg('status', $base_url)),
            ('all' === $current || '' === $current) ? ' class="current"' : '',
            sprintf(esc_html__('All %s', 'gta6-mods'), '<span class="count">' . number_format_i18n($all_count) . '</span>')
        );

        foreach ($statuses as $status) {
            if (empty($counts->{$status})) {
                continue;
            }

            $label = get_post_status_object($status);
            $label_text = $label && isset($label->label) ? $label->label : ucfirst($status);

            $views[$status] = sprintf(
                '<a href="%s"%s>%s</a>',
                esc_url(add_query_arg('status', $status, $base_url)),
                $current === $status ? ' class="current"' : '',
                sprintf(
                    '%s <span class="count">(%s)</span>',
                    esc_html($label_text),
                    number_format_i18n((int) $counts->{$status})
                )
            );
        }

        $reported_total = count($this->reported_status_ids);
        $views['reported'] = sprintf(
            '<a href="%s"%s>%s</a>',
            esc_url(add_query_arg('status', 'reported', $base_url)),
            ('reported' === $current) ? ' class="current"' : '',
            sprintf(
                '%s <span class="count">(%s)</span>',
                esc_html__('Only reported statuses', 'gta6-mods'),
                number_format_i18n($reported_total)
            )
        );

        return $views;
    }

    /**
     * Checkbox column output.
     *
     * @param WP_Post $item Current row item.
     *
     * @return string
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="status_ids[]" value="%d" />', (int) $item->ID);
    }

    /**
     * Content column output with row actions.
     *
     * @param WP_Post $item Current row item.
     *
     * @return string
     */
    protected function column_content($item) {
        $excerpt = wp_trim_words(wp_strip_all_tags($item->post_content), 35, '…');
        $view_url = get_author_posts_url($item->post_author);
        $edit_url = get_edit_post_link($item->ID);

        $title = $excerpt ? $excerpt : __('(no content)', 'gta6-mods');

        $actions = [];

        if ($edit_url && current_user_can('edit_post', $item->ID)) {
            $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'gta6-mods'));
        }

        if ($view_url) {
            $actions['view'] = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url($view_url), esc_html__('View author', 'gta6-mods'));
        }

        $actions = array_merge($actions, $this->get_row_actions($item));

        $display_title = $edit_url ? sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html($title)) : esc_html($title);

        return '<strong>' . $display_title . '</strong>' . $this->row_actions($actions);
    }

    /**
     * Outputs the author column.
     *
     * @param WP_Post $item Current row item.
     *
     * @return string
     */
    protected function column_author($item) {
        $author = get_user_by('id', $item->post_author);

        if (!$author) {
            return esc_html__('Unknown', 'gta6-mods');
        }

        $link = get_edit_user_link($author->ID);

        return $link ? sprintf('<a href="%s">%s</a>', esc_url($link), esc_html($author->display_name)) : esc_html($author->display_name);
    }

    /**
     * Reports column.
     *
     * @param WP_Post $item Current row item.
     *
     * @return string
     */
    protected function column_reports($item) {
        $count = 0;

        if (isset($item->gta6mods_report_count)) {
            $count = (int) $item->gta6mods_report_count;
        } else {
            $count = gta6mods_get_status_report_count($item->ID);
        }

        return $count ? number_format_i18n($count) : '&mdash;';
    }

    /**
     * Status column.
     *
     * @param WP_Post $item Current row item.
     *
     * @return string
     */
    protected function column_status($item) {
        $status_object = get_post_status_object($item->post_status);
        return $status_object && isset($status_object->label) ? esc_html($status_object->label) : esc_html($item->post_status);
    }

    /**
     * Date column.
     *
     * @param WP_Post $item Current row item.
     *
     * @return string
     */
    protected function column_date($item) {
        $timestamp = get_post_timestamp($item, 'date_gmt');

        if (!$timestamp) {
            return '&mdash;';
        }

        $time_diff = human_time_diff($timestamp, current_time('timestamp', true));

        return sprintf(
            '%s<br><span class="description">%s</span>',
            esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), get_option('date_format') . ' ' . get_option('time_format'))),
            sprintf(esc_html__('%s ago', 'gta6-mods'), esc_html($time_diff))
        );
    }

    /**
     * Fallback column renderer.
     *
     * @param WP_Post $item        Current item.
     * @param string  $column_name Column name.
     *
     * @return string
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'reports':
            case 'status':
            case 'date':
            case 'author':
                return ''; // Handled by dedicated column methods.
            default:
                return esc_html($item->$column_name ?? '');
        }
    }

    /**
     * Returns row action links for moderation actions.
     *
     * @param WP_Post $item Current row item.
     *
     * @return array
     */
    protected function get_row_actions($item) {
        $actions = [];
        $base_url = add_query_arg('page', 'gta6mods-status-updates', admin_url('edit-comments.php'));

        $actions_to_generate = [];

        if ('trash' === $item->post_status) {
            $actions_to_generate['restore'] = __('Restore', 'gta6-mods');
        } else {
            $actions_to_generate['trash'] = __('Trash', 'gta6-mods');
        }

        if ('gta-spam' === $item->post_status) {
            $actions_to_generate['unspam'] = __('Publish', 'gta6-mods');
        } else {
            $actions_to_generate['spam'] = __('Mark as spam', 'gta6-mods');
        }

        $actions_to_generate['delete'] = __('Delete permanently', 'gta6-mods');

        if (gta6mods_get_status_report_count($item->ID) > 0) {
            $actions_to_generate['clear_reports'] = __('Clear reports', 'gta6-mods');
        }

        foreach ($actions_to_generate as $action => $label) {
            if ('clear_reports' === $action) {
                if (!current_user_can('moderate_comments')) {
                    continue;
                }
            } elseif (!current_user_can('delete_post', $item->ID)) {
                continue;
            }

            $url = add_query_arg(
                [
                    'action'    => $action,
                    'status_id' => $item->ID,
                ],
                $base_url
            );

            $url = wp_nonce_url($url, "gta6mods_status_action_{$action}_{$item->ID}");

            $actions[$action] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
        }

        return $actions;
    }

    /**
     * Text shown when there are no items.
     */
    public function no_items() {
        esc_html_e('No status updates found.', 'gta6-mods');
    }

    /**
     * Applies row classes.
     *
     * @param WP_Post $item Current row item.
     *
     * @return array
     */
    protected function single_row_class($item) {
        $classes = parent::single_row_class($item);

        if (!is_array($classes)) {
            $classes = array_filter(explode(' ', (string) $classes));
        }

        if (in_array($item->ID, $this->reported_status_ids, true)) {
            $classes[] = 'status-reported';
        }

        return $classes;
    }

    /**
     * Processes moderation actions and redirects back to the screen.
     */
    public function process_actions() {
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        $valid_actions = ['trash', 'delete', 'restore', 'spam', 'unspam', 'clear_reports'];

        if (!in_array($action, $valid_actions, true) || !current_user_can('moderate_comments')) {
            return;
        }

        $ids = [];

        if (!empty($_REQUEST['status_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $status_id = absint($_REQUEST['status_id']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ($status_id > 0) {
                $ids[] = $status_id;
                check_admin_referer("gta6mods_status_action_{$action}_{$status_id}");
            }
        }

        if (!empty($_REQUEST['status_ids'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $bulk_ids = array_map('absint', (array) $_REQUEST['status_ids']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $ids      = array_merge($ids, $bulk_ids);
            $ids      = array_filter(array_unique($ids));

            if (!empty($bulk_ids)) {
                check_admin_referer('bulk-status-updates');
            }
        }

        if (empty($ids)) {
            return;
        }

        foreach ($ids as $id) {
            $post = get_post($id);

            if (!($post instanceof WP_Post) || 'status_update' !== $post->post_type) {
                continue;
            }

            if ('clear_reports' !== $action && !current_user_can('delete_post', $post)) {
                continue;
            }

            switch ($action) {
                case 'trash':
                    wp_trash_post($id);
                    break;
                case 'delete':
                    wp_delete_post($id, true);
                    gta6mods_clear_status_reports([$id]);
                    break;
                case 'restore':
                    wp_untrash_post($id);
                    break;
                case 'spam':
                    wp_update_post([
                        'ID'          => $id,
                        'post_status' => 'gta-spam',
                    ]);
                    break;
                case 'unspam':
                    wp_update_post([
                        'ID'          => $id,
                        'post_status' => 'publish',
                    ]);
                    break;
                case 'clear_reports':
                    gta6mods_clear_status_reports([$id]);
                    break;
            }
        }

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = add_query_arg('page', 'gta6mods-status-updates', admin_url('edit-comments.php'));
        }

        $redirect = remove_query_arg(['action', 'action2', 'status_id', 'status_ids', '_wpnonce', 'gta6mods-status-updates-message'], $redirect);
        $redirect = add_query_arg('gta6mods-status-updates-message', $action, $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Prepares list items for display.
     */
    public function prepare_items() {
        $per_page     = $this->get_items_per_page('gta6mods_status_updates_per_page', 20);
        $current_page = $this->get_pagenum();
        $status       = isset($_REQUEST['status']) ? sanitize_key(wp_unslash($_REQUEST['status'])) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search_term  = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby      = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order        = isset($_REQUEST['order']) ? strtoupper(sanitize_key(wp_unslash($_REQUEST['order']))) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $valid_orderby = ['date', 'author', 'reports'];
        if (!in_array($orderby, $valid_orderby, true)) {
            $orderby = 'date';
        }

        $order = ('ASC' === $order) ? 'ASC' : 'DESC';

        $this->ordering_by_reports = ('reports' === $orderby);
        if ($this->ordering_by_reports) {
            $this->reports_order = $order;
        }

        $query_args = [
            'post_type'      => 'status_update',
            'posts_per_page' => $per_page,
            'offset'         => ($current_page - 1) * $per_page,
            'orderby'        => $this->ordering_by_reports ? 'date' : $orderby,
            'order'          => $order,
            'post_status'    => ['publish', 'pending', 'draft', 'future', 'private', 'trash', 'gta-spam'],
            'ignore_sticky_posts' => true,
        ];

        if ($search_term) {
            $query_args['s'] = $search_term;
        }

        if ('all' !== $status && '' !== $status && 'reported' !== $status) {
            $query_args['post_status'] = $status;
        }

        if ('reported' === $status) {
            if (empty($this->reported_status_ids)) {
                $this->items = [];
                $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
                $this->set_pagination_args([
                    'total_items' => 0,
                    'per_page'    => $per_page,
                    'total_pages' => 0,
                ]);
                return;
            }

            $query_args['post__in']   = array_map('absint', $this->reported_status_ids);
            $query_args['post_status'] = ['publish', 'pending', 'draft', 'future', 'private', 'gta-spam', 'trash'];
        }

        if ($this->ordering_by_reports) {
            add_filter('posts_clauses', [$this, 'order_by_reports_clauses'], 10, 2);
        }

        $query = new WP_Query($query_args);

        if ($this->ordering_by_reports) {
            remove_filter('posts_clauses', [$this, 'order_by_reports_clauses'], 10);
        }

        $this->items = $query->posts;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $this->set_pagination_args([
            'total_items' => (int) $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => (int) $query->max_num_pages,
        ]);
    }

    /**
     * Adjusts the posts query to allow ordering by report count.
     *
     * @param array    $clauses Query clauses.
     * @param WP_Query $query   Current query.
     *
     * @return array
     */
    public function order_by_reports_clauses($clauses, $query) {
        if (!$this->ordering_by_reports) {
            return $clauses;
        }

        if (!($query instanceof WP_Query)) {
            return $clauses;
        }

        $post_types = (array) $query->get('post_type');
        if (!in_array('status_update', $post_types, true)) {
            return $clauses;
        }

        global $wpdb;

        $reports_table = $wpdb->prefix . 'gta_reports';

        if (false === strpos($clauses['fields'], 'gta6mods_report_count')) {
            $clauses['fields'] .= ', COALESCE(report_counts.report_count, 0) AS gta6mods_report_count';
        }

        if (false === strpos($clauses['join'], 'report_counts')) {
            $clauses['join'] .= " LEFT JOIN (SELECT object_id, COUNT(*) AS report_count FROM {$reports_table} WHERE object_type = 'status_update' GROUP BY object_id) AS report_counts ON {$wpdb->posts}.ID = report_counts.object_id";
        }

        $order = ('ASC' === $this->reports_order) ? 'ASC' : 'DESC';
        $clauses['orderby'] = " report_counts.report_count {$order}, {$wpdb->posts}.post_date DESC";

        return $clauses;
    }
    }
}

/**
 * Renders the moderation screen markup.
 */
function gta6mods_render_status_updates_admin_page() {
    global $gta6mods_status_updates_list_table;

    if (!$gta6mods_status_updates_list_table instanceof GTA6Mods_Status_Update_List_Table) {
        $gta6mods_status_updates_list_table = new GTA6Mods_Status_Update_List_Table();
    }

    $gta6mods_status_updates_list_table->prepare_items();

    $search_term = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $current_status = isset($_REQUEST['status']) ? sanitize_key(wp_unslash($_REQUEST['status'])) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $base_args = [
        'page'            => 'gta6mods-status-updates',
        'comment_status'  => 'all',
        'mode'            => 'detail',
    ];

    if ($search_term) {
        $base_args['s'] = $search_term;
    }

    $orderby_param = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $order_param   = isset($_REQUEST['order']) ? strtoupper(sanitize_key(wp_unslash($_REQUEST['order']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ($orderby_param) {
        $base_args['orderby'] = $orderby_param;
    }

    if ($order_param) {
        $base_args['order'] = $order_param;
    }

    $reported_url   = add_query_arg(array_merge($base_args, ['status' => 'reported']), admin_url('edit-comments.php'));
    $reported_class = ('reported' === $current_status) ? 'button button-primary' : 'button button-secondary';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Status Updates', 'gta6-mods'); ?></h1>
        <div class="gta6mods-status-extra-links" style="margin: 1rem 0;">
            <a class="<?php echo esc_attr($reported_class); ?>" href="<?php echo esc_url($reported_url); ?>"><?php esc_html_e('Only reported statuses', 'gta6-mods'); ?></a>
        </div>
        <hr class="wp-header-end">
        <form method="get">
            <input type="hidden" name="page" value="gta6mods-status-updates">
            <input type="hidden" name="comment_status" value="all">
            <input type="hidden" name="mode" value="detail">
            <?php
            $gta6mods_status_updates_list_table->search_box(__('Search status updates', 'gta6-mods'), 'gta6mods-status-updates');
            $gta6mods_status_updates_list_table->display();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Synchronises activity entries when status updates change status.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 */
function gta6mods_handle_status_update_status_transition($new_status, $old_status, $post) {
    if (!($post instanceof WP_Post) || 'status_update' !== $post->post_type) {
        return;
    }

    if ($new_status === $old_status) {
        return;
    }

    $author_id = (int) $post->post_author;

    if (in_array($new_status, ['trash', 'gta-spam'], true)) {
        gta6mods_remove_activity_entry($author_id, 'status_posted', $post->ID);
    } elseif ('publish' === $new_status && in_array($old_status, ['trash', 'gta-spam'], true)) {
        gta6mods_log_activity($author_id, 'status_posted', $post->ID);
    }
}
add_action('transition_post_status', 'gta6mods_handle_status_update_status_transition', 15, 3);

/**
 * Cleans up activity entries when a status update is deleted.
 *
 * @param int $post_id Post ID.
 */
function gta6mods_handle_status_update_deleted($post_id) {
    $post = get_post($post_id);

    if ($post instanceof WP_Post && 'status_update' === $post->post_type) {
        gta6mods_remove_activity_entry((int) $post->post_author, 'status_posted', $post_id);
        gta6mods_clear_status_reports([$post_id]);
    }
}
add_action('deleted_post', 'gta6mods_handle_status_update_deleted');

/**
 * Retrieves notifications for a user.
 *
 * @param int  $user_id     User ID.
 * @param int  $limit       Limit.
 * @param bool $only_unread Optional. Whether to only return unread notifications. Default false.
 *
 * @return array
 */
function gta6mods_get_user_notifications($user_id, $limit = 20, $only_unread = false) {
    global $wpdb;

    $user_id = absint($user_id);
    $limit   = absint($limit);

    if ($user_id <= 0) {
        return [];
    }

    if ($limit <= 0) {
        $limit = 20;
    }

    $table = $wpdb->prefix . 'gta_notifications';

    $where_clauses = ['recipient_user_id = %d'];
    $prepare_args  = [$user_id];

    if ($only_unread) {
        $where_clauses[] = 'is_read = 0';
    }

    $where_sql = implode(' AND ', $where_clauses);

    $prepare_args[] = $limit;

    $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d";

    $results = $wpdb->get_results(
        $wpdb->prepare($sql, ...$prepare_args),
        ARRAY_A
    );

    return array_map(static function ($item) {
        if (!empty($item['meta'])) {
            $meta = json_decode($item['meta'], true);
            $item['meta'] = is_array($meta) ? $meta : [];
        } else {
            $item['meta'] = [];
        }
        return $item;
    }, $results);
}

/**
 * Retrieves the pinned mod for an author.
 *
 * @param int $user_id User ID.
 *
 * @return WP_Post|null
 */
function gta6mods_get_pinned_mod_for_user($user_id) {
    $user_id  = absint($user_id);
    $pinned_id = absint(get_user_meta($user_id, '_pinned_mod_id', true));

    if ($pinned_id > 0) {
        $post = get_post($pinned_id);
        if ($post instanceof WP_Post && in_array($post->post_type, gta6mods_get_mod_post_types(), true)) {
            return $post;
        }
    }

    $latest = get_posts([
        'post_type'      => gta6mods_get_mod_post_types(),
        'author'         => $user_id,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    return !empty($latest) ? $latest[0] : null;
}

/**
 * Retrieves the most popular mods for a user.
 *
 * @param int $user_id User ID.
 * @param int $limit   Number of posts.
 *
 * @return WP_Post[] List of popular mods authored by the user.
 */
function gta6mods_get_popular_mods_for_user($user_id, $limit = 3) {
    $user_id = absint($user_id);
    $limit   = absint($limit);

    if ($user_id <= 0) {
        return [];
    }

    if ($limit <= 0) {
        $limit = 3;
    }

    $object_cache_group = 'gta6mods_author_posts';
    $object_cache_key   = sprintf('popular_posts_%d_%d', $user_id, $limit);
    $transient_key      = sprintf('gta6mods_popular_mod_ids_%d_%d', $user_id, $limit);

    $cached_posts = wp_cache_get($object_cache_key, $object_cache_group);

    if (false !== $cached_posts) {
        return is_array($cached_posts) ? $cached_posts : [];
    }

    $transient_ids = get_transient($transient_key);

    if (false !== $transient_ids) {
        $post_ids = array_values(array_filter(array_map('absint', (array) $transient_ids)));

        if (!empty($post_ids)) {
            $posts = get_posts([
                'post_type'              => gta6mods_get_mod_post_types(),
                'post_status'            => 'publish',
                'posts_per_page'         => count($post_ids),
                'post__in'               => $post_ids,
                'orderby'                => 'post__in',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'lazy_load_term_meta'    => false,
                'cache_results'          => true,
            ]);

            if (!empty($posts)) {
                wp_cache_set($object_cache_key, $posts, $object_cache_group, HOUR_IN_SECONDS);

                return $posts;
            }
        }

        delete_transient($transient_key);
    }

    $post_ids = gta6mods_get_author_top_mod_ids($user_id, 'downloads', $limit);

    if (empty($post_ids)) {
        $fallback_query = new WP_Query([
            'post_type'              => gta6mods_get_mod_post_types(),
            'post_status'            => 'publish',
            'author'                 => $user_id,
            'posts_per_page'         => $limit,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
            'cache_results'          => true,
        ]);

        $post_ids = array_values(array_filter(array_map('absint', (array) $fallback_query->posts)));

        if (!empty($post_ids)) {
            usort(
                $post_ids,
                static function ($a, $b) {
                    $a_downloads = gta6_mods_get_download_count($a);
                    $b_downloads = gta6_mods_get_download_count($b);

                    return $b_downloads <=> $a_downloads;
                }
            );

            $post_ids = array_slice(array_values($post_ids), 0, $limit);
        }
    }

    if (!empty($post_ids)) {
        $posts = get_posts([
            'post_type'              => gta6mods_get_mod_post_types(),
            'post_status'            => 'publish',
            'posts_per_page'         => count($post_ids),
            'post__in'               => $post_ids,
            'orderby'                => 'post__in',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
            'cache_results'          => true,
        ]);

        set_transient($transient_key, $post_ids, 2 * HOUR_IN_SECONDS);
        wp_cache_set($object_cache_key, $posts, $object_cache_group, HOUR_IN_SECONDS);

        return is_array($posts) ? $posts : [];
    }

    set_transient($transient_key, [], 2 * HOUR_IN_SECONDS);
    wp_cache_set($object_cache_key, [], $object_cache_group, HOUR_IN_SECONDS);

    return [];
}

/**
 * Retrieves the top-performing mod for a user by a specific numeric meta field.
 *
 * @param int    $user_id  User ID.
 * @param string $meta_key Meta key storing numeric stats (e.g. downloads, likes).
 * @param string $order    Order direction (ASC or DESC).
 *
 * @return WP_Post|null
 */
function gta6mods_get_top_mod_by_meta($user_id, $meta_key, $order = 'DESC') {
    $user_id  = absint($user_id);
    $meta_key = sanitize_key($meta_key);
    $order    = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

    if ($user_id <= 0 || '' === $meta_key) {
        return null;
    }

    $stat          = gta6mods_map_meta_key_to_stat($meta_key);
    $cache_fragment = null !== $stat ? $stat : $meta_key;

    $object_cache_group = 'gta6mods_author_posts';
    $object_cache_key   = sprintf('top_mod_post_%d_%s_%s', $user_id, $cache_fragment, strtolower($order));
    $transient_key      = sprintf('gta6mods_top_mod_ids_%d_%s_%s', $user_id, $cache_fragment, strtolower($order));

    $cached_post = wp_cache_get($object_cache_key, $object_cache_group);

    if (false !== $cached_post) {
        return $cached_post instanceof WP_Post ? $cached_post : null;
    }

    $transient_ids = get_transient($transient_key);

    if (false !== $transient_ids) {
        $post_ids = array_values(array_filter(array_map('absint', (array) $transient_ids)));

        if (!empty($post_ids)) {
            $posts = get_posts([
                'post_type'              => gta6mods_get_mod_post_types(),
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
                'post__in'               => $post_ids,
                'orderby'                => 'post__in',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'lazy_load_term_meta'    => false,
                'cache_results'          => true,
            ]);

            if (!empty($posts)) {
                $post = $posts[0];
                wp_cache_set($object_cache_key, $post, $object_cache_group, HOUR_IN_SECONDS);

                return $post instanceof WP_Post ? $post : null;
            }
        }

        delete_transient($transient_key);
    }

    $post_ids = [];

    if (null !== $stat) {
        $post_ids = gta6mods_get_author_top_mod_ids($user_id, $stat, 1, $order);
    }

    if (empty($post_ids)) {
        $query = new WP_Query([
            'post_type'              => gta6mods_get_mod_post_types(),
            'post_status'            => 'publish',
            'author'                 => $user_id,
            'posts_per_page'         => 1,
            'meta_key'               => $meta_key,
            'orderby'                => 'meta_value_num',
            'order'                  => $order,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
            'cache_results'          => true,
        ]);

        $post_ids = array_values(array_filter(array_map('absint', (array) $query->posts)));

        if (empty($post_ids) && null !== $stat) {
            $fallback_query = new WP_Query([
                'post_type'              => gta6mods_get_mod_post_types(),
                'post_status'            => 'publish',
                'author'                 => $user_id,
                'posts_per_page'         => 20,
                'fields'                 => 'ids',
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'lazy_load_term_meta'    => false,
                'cache_results'          => true,
            ]);

            $top_post_id = 0;
            $top_value   = null;

            foreach ($fallback_query->posts as $post_id) {
                $value = (int) get_post_meta($post_id, $meta_key, true);

                if (null === $top_value) {
                    $top_value   = $value;
                    $top_post_id = $post_id;
                    continue;
                }

                if (('DESC' === $order && $value > $top_value) || ('ASC' === $order && $value < $top_value)) {
                    $top_value   = $value;
                    $top_post_id = $post_id;
                }
            }

            if ($top_post_id > 0) {
                $post_ids = [$top_post_id];
            }
        }
    }

    if (!empty($post_ids)) {
        $posts = get_posts([
            'post_type'              => gta6mods_get_mod_post_types(),
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'post__in'               => $post_ids,
            'orderby'                => 'post__in',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
            'cache_results'          => true,
        ]);

        $post = !empty($posts) ? $posts[0] : null;

        set_transient($transient_key, $post_ids, 2 * HOUR_IN_SECONDS);
        wp_cache_set($object_cache_key, $post instanceof WP_Post ? $post : null, $object_cache_group, HOUR_IN_SECONDS);

        return $post instanceof WP_Post ? $post : null;
    }

    set_transient($transient_key, [], 2 * HOUR_IN_SECONDS);
    wp_cache_set($object_cache_key, null, $object_cache_group, HOUR_IN_SECONDS);

    return null;
}

/**
 * Retrieves the latest status update for a user.
 *
 * @param int $user_id User ID.
 *
 * @return WP_Post|null
 */
function gta6mods_get_latest_status_update($user_id) {
    $user_id = absint($user_id);

    if ($user_id <= 0) {
        return null;
    }

    $query = new WP_Query([
        'post_type'              => 'status_update',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'author'                 => $user_id,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'lazy_load_term_meta'    => false,
        'cache_results'          => true,
    ]);

    if (!empty($query->posts)) {
        $post = $query->posts[0];

        return $post instanceof WP_Post ? $post : get_post($post);
    }

    return null;
}

/**
 * Retrieves stored social links for a user.
 *
 * @param int $user_id User ID.
 *
 * @return array
 */
function gta6mods_get_social_link_definitions() {
    return [
        'website'    => [
            'icon'     => 'fas fa-globe',
            'label'    => __('Website', 'gta6-mods'),
            'prefix'   => __('website', 'gta6-mods'),
            'base_url' => '',
        ],
        'facebook'   => [
            'icon'     => 'fab fa-facebook',
            'label'    => __('Facebook', 'gta6-mods'),
            'prefix'   => 'facebook.com/',
            'base_url' => 'https://www.facebook.com/',
            'domains'  => ['facebook.com', 'www.facebook.com', 'm.facebook.com'],
        ],
        'x'          => [
            'icon'     => 'fab fa-x-twitter',
            'label'    => __('X (Twitter)', 'gta6-mods'),
            'prefix'   => 'x.com/',
            'base_url' => 'https://x.com/',
            'domains'  => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'],
        ],
        'youtube'    => [
            'icon'     => 'fab fa-youtube',
            'label'    => __('YouTube', 'gta6-mods'),
            'prefix'   => 'youtube.com/',
            'base_url' => 'https://www.youtube.com/',
            'domains'  => ['youtube.com', 'www.youtube.com', 'm.youtube.com'],
        ],
        'twitch'     => [
            'icon'     => 'fab fa-twitch',
            'label'    => __('Twitch', 'gta6-mods'),
            'prefix'   => 'twitch.tv/',
            'base_url' => 'https://www.twitch.tv/',
            'domains'  => ['twitch.tv', 'www.twitch.tv'],
        ],
        'steam'      => [
            'icon'     => 'fab fa-steam',
            'label'    => __('Steam', 'gta6-mods'),
            'prefix'   => 'steamcommunity.com/id/',
            'base_url' => 'https://steamcommunity.com/id/',
            'domains'  => ['steamcommunity.com/id', 'steamcommunity.com/profiles'],
        ],
        'socialclub' => [
            'icon'     => 'fas fa-star',
            'label'    => __('Rockstar Social Club', 'gta6-mods'),
            'prefix'   => 'socialclub.rockstargames.com/member/',
            'base_url' => 'https://socialclub.rockstargames.com/member/',
            'domains'  => ['socialclub.rockstargames.com/member'],
        ],
        'instagram'  => [
            'icon'     => 'fab fa-instagram',
            'label'    => __('Instagram', 'gta6-mods'),
            'prefix'   => 'instagram.com/',
            'base_url' => 'https://www.instagram.com/',
            'domains'  => ['instagram.com', 'www.instagram.com'],
        ],
        'flickr'     => [
            'icon'     => 'fab fa-flickr',
            'label'    => __('Flickr', 'gta6-mods'),
            'prefix'   => 'flickr.com/photos/',
            'base_url' => 'https://www.flickr.com/photos/',
            'domains'  => ['flickr.com/photos', 'www.flickr.com/photos'],
        ],
        'github'     => [
            'icon'     => 'fab fa-github',
            'label'    => __('GitHub', 'gta6-mods'),
            'prefix'   => 'github.com/',
            'base_url' => 'https://github.com/',
            'domains'  => ['github.com'],
        ],
        'patreon'    => [
            'icon'     => 'fab fa-patreon',
            'label'    => __('Patreon', 'gta6-mods'),
            'prefix'   => 'patreon.com/',
            'base_url' => 'https://www.patreon.com/',
            'domains'  => ['patreon.com', 'www.patreon.com'],
        ],
        'paypal'     => [
            'icon'     => 'fab fa-paypal',
            'label'    => __('PayPal', 'gta6-mods'),
            'prefix'   => 'PayPal',
            'base_url' => 'https://www.paypal.com/paypalme/',
            'domains'  => ['paypal.me', 'www.paypal.me', 'paypal.com/paypalme'],
        ],
        'skype'      => [
            'icon'     => 'fab fa-skype',
            'label'    => __('Skype', 'gta6-mods'),
            'prefix'   => 'Skype',
            'base_url' => 'skype:',
        ],
        'discord'    => [
            'icon'     => 'fab fa-discord',
            'label'    => __('Discord', 'gta6-mods'),
            'prefix'   => 'discord.gg/',
            'base_url' => 'https://discord.gg/',
            'domains'  => ['discord.gg', 'www.discord.gg', 'discord.com/invite'],
        ],
    ];
}

function gta6mods_normalize_social_link_value($key, $value) {
    $definitions = gta6mods_get_social_link_definitions();
    $key         = sanitize_key($key);

    if (!isset($definitions[$key])) {
        return '';
    }

    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if ('website' === $key) {
        return esc_url_raw($value);
    }

    if ('paypal' === $key) {
        $value = preg_replace('~^https?://~i', '', $value);
        $value = preg_replace('~^www\\.~i', '', $value);
        $value = preg_replace('~^paypal\\.me/~i', '', $value);
        $value = preg_replace('~^paypal\\.com/paypalme/~i', '', $value);
        $value = trim($value, "/ \t\n\r\0\x0B");

        return sanitize_text_field($value);
    }

    if ('skype' === $key) {
        $value = preg_replace('~^skype:~i', '', $value);
        $value = preg_replace('~\\?.*$~', '', $value);

        return sanitize_text_field($value);
    }

    $value = preg_replace('~^https?://~i', '', $value);
    $value = preg_replace('~^www\\.~i', '', $value);

    if (!empty($definitions[$key]['domains'])) {
        foreach ((array) $definitions[$key]['domains'] as $domain) {
            $domain = strtolower($domain);
            if ($domain === '') {
                continue;
            }
            $length = strlen($domain);
            if ($length && stripos($value, $domain) === 0) {
                $value = substr($value, $length);
                break;
            }
        }
    }

    $value = preg_replace('~^/+~', '', $value);
    $parts = preg_split('/[?#]/', $value);
    if (!empty($parts)) {
        $value = (string) $parts[0];
    }
    $value = rtrim($value, "/ \t\n\r\0\x0B");

    return sanitize_text_field($value);
}

function gta6mods_build_social_link_url($key, $value) {
    $definitions = gta6mods_get_social_link_definitions();
    $key         = sanitize_key($key);

    if (!isset($definitions[$key])) {
        return '';
    }

    $value = gta6mods_normalize_social_link_value($key, $value);

    if ($value === '') {
        return '';
    }

    if ('website' === $key) {
        return esc_url_raw($value);
    }

    if ('paypal' === $key) {
        if (is_email($value)) {
            return esc_url_raw(sprintf('https://www.paypal.com/donate?business=%s', rawurlencode($value)));
        }

        $clean = trim($value, '/');
        if ($clean === '') {
            return '';
        }

        return esc_url_raw('https://www.paypal.com/paypalme/' . rawurlencode($clean));
    }

    if ('skype' === $key) {
        return 'skype:' . rawurlencode($value);
    }

    if ('discord' === $key) {
        $clean = ltrim($value, '/');
        if (stripos($clean, 'invite/') === 0) {
            $clean = substr($clean, strlen('invite/'));
        }
        $clean = trim($clean);
        if ($clean === '') {
            return '';
        }

        return esc_url_raw('https://discord.gg/' . rawurlencode($clean));
    }

    if ('steam' === $key) {
        $clean = ltrim($value, '/');

        if (stripos($clean, 'id/') === 0 || stripos($clean, 'profiles/') === 0) {
            $path = $clean;
        } elseif (preg_match('/^\d+$/', $clean)) {
            $path = 'profiles/' . $clean;
        } else {
            $path = 'id/' . $clean;
        }

        return esc_url_raw('https://steamcommunity.com/' . ltrim($path, '/'));
    }

    $definition = $definitions[$key];
    $base       = isset($definition['base_url']) ? $definition['base_url'] : '';

    if ($base === '') {
        return '';
    }

    $clean = ltrim($value, '/');

    return esc_url_raw(trailingslashit($base) . $clean);
}

function gta6mods_resolve_social_link_urls(array $links) {
    $resolved = [];

    foreach ($links as $key => $value) {
        $key   = sanitize_key($key);
        $value = gta6mods_build_social_link_url($key, $value);

        if ($value !== '') {
            $resolved[$key] = $value;
        }
    }

    return $resolved;
}

function gta6mods_get_user_social_links($user_id) {
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return [];
    }

    $links = get_user_meta($user_id, '_social_links', true);
    if (!is_array($links)) {
        return [];
    }

    $definitions = gta6mods_get_social_link_definitions();
    $normalized  = [];

    foreach ($links as $key => $value) {
        $key = sanitize_key($key);

        if (!isset($definitions[$key])) {
            continue;
        }

        $normalized[$key] = gta6mods_normalize_social_link_value($key, $value);
    }

    return $normalized;
}

/**
 * REST API route registration.
 */
function gta6mods_register_author_rest_routes() {
    register_rest_route(
        'gta6-mods/v1',
        '/mod/(?P<id>\d+)/data',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_mod_update_data',
            'permission_callback' => 'gta6mods_rest_can_access_mod_update',
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/uploads',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_author_uploads',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/comments',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_author_comments',
            'permission_callback' => '__return_true',
            'args'                => [
                'page' => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                    'minimum'           => 1,
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/notifications',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_author_notifications',
            'permission_callback' => static function ($request) {
                $author_id = absint($request['id']);

                return is_user_logged_in() && $author_id > 0 && get_current_user_id() === $author_id;
            },
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/notifications/unread',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_unread_notifications',
            'permission_callback' => static function ($request) {
                $author_id = absint($request['id']);

                return is_user_logged_in() && $author_id > 0 && get_current_user_id() === $author_id;
            },
            'args'                => [
                'limit' => [
                    'type'              => 'integer',
                    'default'           => 5,
                    'sanitize_callback' => 'absint',
                    'minimum'           => 1,
                    'maximum'           => 10,
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/notifications/recent',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_unread_notifications',
            'permission_callback' => static function ($request) {
                $author_id = absint($request['id']);

                return is_user_logged_in() && $author_id > 0 && get_current_user_id() === $author_id;
            },
            'args'                => [
                'limit' => [
                    'type'              => 'integer',
                    'default'           => 5,
                    'sanitize_callback' => 'absint',
                    'minimum'           => 1,
                    'maximum'           => 10,
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/notifications/mark-read',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_mark_notifications_read',
            'permission_callback' => static function ($request) {
                $author_id = absint($request['id']);

                return is_user_logged_in() && $author_id > 0 && get_current_user_id() === $author_id;
            },
            'args'                => [
                'mark_all' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
                'notification_ids' => [
                    'type'        => 'array',
                    'default'     => [],
                    'items'       => [
                        'type' => 'integer',
                    ],
                    'sanitize_callback' => static function ($value) {
                        if (!is_array($value)) {
                            return [];
                        }

                        $ids = array_map('absint', $value);
                        $ids = array_filter($ids, static function ($id) {
                            return $id > 0;
                        });

                        return array_values(array_unique($ids));
                    },
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/collections',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_author_collections',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/bookmarks',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_author_bookmarks',
            'permission_callback' => function ($request) {
                if (!is_user_logged_in()) {
                    return false;
                }

                $author_id = absint($request['id']);

                return $author_id > 0 && get_current_user_id() === $author_id;
            },
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/delete-account/request',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_request_account_deletion',
            'permission_callback' => 'gta6mods_rest_can_self_manage_account_deletion',
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/delete-account/cancel',
        [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'gta6mods_rest_cancel_account_deletion',
            'permission_callback' => 'gta6mods_rest_can_self_manage_account_deletion',
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/delete-account/finalize',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_finalize_account_deletion',
            'permission_callback' => 'gta6mods_rest_can_self_manage_account_deletion',
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/mod/(?P<id>\d+)/comments',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_mod_comments',
            'permission_callback' => '__return_true',
            'args'                => [
                'page' => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/mod/(?P<id>\d+)/like',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_toggle_mod_like',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/mod/(?P<id>\d+)/bookmark',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_toggle_mod_bookmark',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/followers',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_author_followers',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/activity',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'gta6mods_rest_get_author_activity',
            'permission_callback' => '__return_true',
            'args'                => [
                'offset'   => [
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                    'minimum'           => 0,
                ],
                'per_page' => [
                    'type'              => 'integer',
                    'default'           => 8,
                    'sanitize_callback' => 'absint',
                    'minimum'           => 1,
                    'maximum'           => 20,
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/follow',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_handle_follow',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'action' => [
                    'type'              => 'string',
                    'required'          => true,
                    'validate_callback' => static function ($param) {
                        return in_array($param, ['follow', 'unfollow'], true);
                    },
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/status',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_create_status_update',
            'permission_callback' => function ($request) {
                return is_user_logged_in() && get_current_user_id() === absint($request['id']);
            },
            'args'                => [
                'content' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'gta6mods_rest_sanitize_status_content',
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/status/(?P<status_id>\d+)',
        [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'gta6mods_rest_delete_status_update',
            'permission_callback' => 'gta6mods_rest_can_manage_status_update',
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/settings',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_save_author_settings',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/avatar',
        [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'gta6mods_rest_upload_author_avatar',
                'permission_callback' => function ($request) {
                    return is_user_logged_in() && get_current_user_id() === absint($request['id']);
                },
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => 'gta6mods_rest_delete_author_avatar',
                'permission_callback' => function ($request) {
                    return is_user_logged_in() && get_current_user_id() === absint($request['id']);
                },
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/(?P<id>\d+)/banner',
        [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'gta6mods_rest_upload_author_banner',
                'permission_callback' => function ($request) {
                    return is_user_logged_in() && get_current_user_id() === absint($request['id']);
                },
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => 'gta6mods_rest_delete_author_banner',
                'permission_callback' => function ($request) {
                    return is_user_logged_in() && get_current_user_id() === absint($request['id']);
                },
            ],
        ]
    );

    register_rest_route(
        'gta6-mods/v1',
        '/author/report',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_submit_report',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_author_rest_routes');

/**
 * Prepares paginated comment data for an author profile.
 *
 * @param int $author_id Author ID.
 * @param int $page      Requested page (1-indexed).
 * @param int $per_page  Number of comments per page.
 *
 * @return array<string, mixed>|WP_Error
 */
function gta6mods_prepare_author_comments_view($author_id, $page = 1, $per_page = 10) {
    $author_id = absint($author_id);

    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid author.', 'gta6-mods'), ['status' => 400]);
    }

    $per_page = (int) apply_filters('gta6mods_author_comments_per_page', $per_page, $author_id);
    if ($per_page <= 0) {
        $per_page = 10;
    }

    $count_args = [
        'user_id'   => $author_id,
        'status'    => 'approve',
        'post_type' => gta6mods_get_mod_post_types(),
        'type'      => 'comment',
        'count'     => true,
    ];
    $count_args = apply_filters('gta6mods_author_comments_count_query_args', $count_args, $author_id);

    $total_count = (int) get_comments($count_args);

    $total_pages = $per_page > 0 ? (int) ceil($total_count / $per_page) : 1;
    if ($total_pages <= 0) {
        $total_pages = 1;
    }

    $page = max(1, (int) $page);
    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $offset = ($page - 1) * $per_page;

    $query_args = [
        'user_id'       => $author_id,
        'status'        => 'approve',
        'post_type'     => gta6mods_get_mod_post_types(),
        'type'          => 'comment',
        'number'        => $per_page,
        'offset'        => $offset,
        'orderby'       => 'comment_date_gmt',
        'order'         => 'DESC',
        'no_found_rows' => true,
    ];
    $query_args = apply_filters('gta6mods_author_comments_query_args', $query_args, $author_id, $page);

    $comments = get_comments($query_args);
    if (!is_array($comments)) {
        $comments = [];
    }

    $comments = array_values(array_filter($comments, static function ($comment) {
        return $comment instanceof WP_Comment;
    }));

    return [
        'comments'     => $comments,
        'total'        => $total_count,
        'page'         => $page,
        'total_pages'  => $total_pages,
        'per_page'     => $per_page,
        'query_args'   => $query_args,
        'count_args'   => $count_args,
    ];
}

/**
 * Renders the markup for a given author profile tab.
 *
 * @param string $tab   Tab slug.
 * @param int    $author_id Author ID.
 * @param array  $args  Additional arguments.
 *
 * @return string|WP_Error
 */
function gta6mods_render_author_tab_content($tab, $author_id, $args = []) {
    $author_id = absint($author_id);

    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid author.', 'gta6-mods'), ['status' => 400]);
    }

    $tab = sanitize_key($tab);

    switch ($tab) {
        case 'uploads':
            $page = isset($args['page']) ? (int) $args['page'] : 1;
            if ($page <= 0) {
                $page = 1;
            }

            $query = new WP_Query([
                'post_type'      => gta6mods_get_mod_post_types(),
                'author'         => $author_id,
                'post_status'    => 'publish',
                'posts_per_page' => 12,
                'paged'          => $page,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);

            $html = gta6mods_get_author_template_html('uploads', [
                'query'      => $query,
                'author_id'  => $author_id,
                'pagination' => [
                    'current' => $page,
                    'found'   => $query->max_num_pages,
                ],
                'base_url'   => $args['base_url'] ?? '',
            ]);

            wp_reset_postdata();

            return $html;

        case 'comments':
            $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 10;

            if (isset($args['comments_data']) && is_array($args['comments_data'])) {
                $prepared = $args['comments_data'];
            } else {
                $page     = isset($args['page']) ? (int) $args['page'] : 1;
                $prepared = gta6mods_prepare_author_comments_view($author_id, $page, $per_page);
            }

            if (is_wp_error($prepared)) {
                return $prepared;
            }

            return gta6mods_get_author_template_html('comments', [
                'comments'   => $prepared['comments'],
                'author_id'  => $author_id,
                'total'      => $prepared['total'],
                'pagination' => [
                    'current' => $prepared['page'],
                    'total'   => $prepared['total_pages'],
                ],
                'base_url'   => $args['base_url'] ?? '',
                'per_page'   => isset($prepared['per_page']) ? (int) $prepared['per_page'] : $per_page,
            ]);

        case 'notifications':
            if ((int) get_current_user_id() !== $author_id) {
                return new WP_Error('forbidden', __('You are not allowed to view these notifications.', 'gta6-mods'), ['status' => 403]);
            }

            gta6mods_cleanup_inactive_user_notifications();

            $notifications = gta6mods_get_user_notifications($author_id, 50);

            return gta6mods_get_author_template_html('notifications', [
                'notifications' => $notifications,
                'author_id'     => $author_id,
            ]);

        case 'collections':
            $collections = (array) get_user_meta($author_id, '_mod_collections', true);

            return gta6mods_get_author_template_html('collections', [
                'collections' => $collections,
                'author_id'   => $author_id,
            ]);

        case 'bookmarks':
            if ((int) get_current_user_id() !== $author_id) {
                return new WP_Error('forbidden', __('You are not allowed to view these bookmarks.', 'gta6-mods'), ['status' => 403]);
            }

            $bookmarks = (array) get_user_meta($author_id, '_saved_mods', true);
            $bookmarks = array_map('absint', $bookmarks);
            $bookmarks = array_filter($bookmarks);

            $posts = [];
            if (!empty($bookmarks)) {
                $posts = get_posts([
                    'post_type'      => gta6mods_get_mod_post_types(),
                    'post__in'       => $bookmarks,
                    'post_status'    => 'publish',
                    'orderby'        => 'post__in',
                    'posts_per_page' => -1,
                ]);
            }

            return gta6mods_get_author_template_html('bookmarks', [
                'posts'     => $posts,
                'author_id' => $author_id,
            ]);

        case 'followers':
            $followers_ids = (array) get_user_meta($author_id, '_followers', true);
            $followers_ids = array_map('absint', $followers_ids);
            $followers_ids = array_filter($followers_ids);

            $followers = [];
            if (!empty($followers_ids)) {
                $followers = get_users([
                    'include' => $followers_ids,
                ]);
            }

            return gta6mods_get_author_template_html('followers', [
                'followers' => $followers,
                'author_id' => $author_id,
            ]);

    }

    return new WP_Error('invalid_tab', __('Invalid tab.', 'gta6-mods'), ['status' => 400]);
}

/**
 * REST callback: author activity feed pagination.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_render_activity_items_markup($activities, $display_name, $current_user_id) {
    if (empty($activities) || !is_array($activities)) {
        return '';
    }

    ob_start();

    foreach ($activities as $activity) {
        $action        = $activity['action_type'] ?? '';
        $icon_data     = gta6mods_format_activity_icon($action);
        $icon_parts    = explode(' ', $icon_data, 2);
        $icon_class    = $icon_parts[0];
        $style_classes = $icon_parts[1] ?? 'bg-gray-100 text-gray-600';
        $timestamp     = !empty($activity['created_at']) ? strtotime($activity['created_at'] . ' UTC') : 0;
        $time_ago      = $timestamp ? sprintf(esc_html__('%s ago', 'gta6-mods'), human_time_diff($timestamp, current_time('timestamp', true))) : '';
        $time_shown    = false;
        $status_post   = null;
        $status_html   = '';
        $can_report    = false;
        $can_delete    = false;

        if ('status_posted' === $action) {
            $status_post_id = isset($activity['object_id']) ? (int) $activity['object_id'] : 0;
            $status_post    = $status_post_id ? get_post($status_post_id) : null;

            if (!($status_post instanceof WP_Post) || 'status_update' !== $status_post->post_type || in_array($status_post->post_status, ['trash', 'gta-spam'], true)) {
                continue;
            }

            $status_html = gta6mods_render_status_update_content($status_post->post_content);
            $can_report  = is_user_logged_in() && $current_user_id !== (int) $status_post->post_author;
            $can_delete  = is_user_logged_in() && ($current_user_id === (int) $status_post->post_author || current_user_can('delete_post', $status_post->ID));
        }

        ?>
        <div class="flex items-start gap-4" data-activity-item>
            <div class="<?php echo esc_attr($style_classes); ?> rounded-full h-9 w-9 flex-shrink-0 flex items-center justify-center">
                <i class="fas <?php echo esc_attr($icon_class); ?>"></i>
            </div>
            <div>
                <?php
                if ('status_posted' === $action && $status_post instanceof WP_Post) {
                    ?>
                    <p class="text-sm"><strong><?php echo esc_html($display_name); ?></strong> <?php esc_html_e('shared a new status update', 'gta6-mods'); ?></p>
                    <?php if ($status_html) : ?>
                        <div class="mt-2 p-3 bg-gray-100 text-sm text-gray-700 rounded-md">
                            &ldquo;<?php echo wp_kses_post($status_html); ?>&rdquo;
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                        <?php if ($time_ago) :
                            $time_shown = true;
                            ?>
                            <span><?php echo esc_html($time_ago); ?></span>
                        <?php endif; ?>
                        <?php if ($can_report) : ?>
                            <button type="button" class="font-semibold text-pink-600 hover:text-pink-700" data-action="report-status" data-status-id="<?php echo esc_attr($status_post->ID); ?>"><?php esc_html_e('Report', 'gta6-mods'); ?></button>
                        <?php endif; ?>
                        <?php if ($can_delete) : ?>
                            <button type="button" class="font-semibold text-gray-500 hover:text-red-600" data-action="delete-status" data-status-id="<?php echo esc_attr($status_post->ID); ?>"><?php esc_html_e('Delete', 'gta6-mods'); ?></button>
                        <?php endif; ?>
                    </div>
                    <?php
                } elseif ('mod_published' === $action) {
                    $mod_post = isset($activity['object_id']) ? get_post((int) $activity['object_id']) : null;
                    if ($mod_post instanceof WP_Post) {
                        $mod_thumb = get_the_post_thumbnail_url($mod_post, 'medium');
                        if (!$mod_thumb) {
                            $mod_thumb = apply_filters('gta6mods_mod_placeholder_image', 'https://placehold.co/300x160?text=Mod');
                        }
                        $mod_downloads = gta6_mods_get_download_count($mod_post->ID);
                        $mod_likes     = gta6_mods_get_like_count($mod_post->ID);
                        ?>
                        <p class="text-sm mb-2"><strong><?php echo esc_html($display_name); ?></strong> <?php esc_html_e('published a new mod', 'gta6-mods'); ?></p>
                        <a href="<?php echo esc_url(get_permalink($mod_post)); ?>" class="flex gap-4 p-3 rounded-lg bg-gray-50 hover:bg-gray-100 border border-gray-200 transition">
                            <img src="<?php echo esc_url($mod_thumb); ?>" class="w-24 h-14 object-cover rounded-md flex-shrink-0" alt="<?php echo esc_attr(get_the_title($mod_post)); ?>">
                            <div>
                                <h4 class="font-semibold text-gray-800"><?php echo esc_html(get_the_title($mod_post)); ?></h4>
                                <div class="flex items-center space-x-3 text-xs text-gray-500 mt-1">
                                    <span><i class="fas fa-download mr-1"></i><?php echo esc_html(number_format_i18n($mod_downloads)); ?></span>
                                    <span><i class="fas fa-thumbs-up mr-1"></i><?php echo esc_html(number_format_i18n($mod_likes)); ?></span>
                                </div>
                            </div>
                        </a>
                        <?php
                    } else {
                        ?>
                        <p class="text-sm"><?php echo esc_html(gta6mods_format_activity_message($activity)); ?></p>
                        <?php
                    }
                } elseif ('mod_commented' === $action) {
                    $mod_link   = !empty($activity['object_id']) ? get_permalink((int) $activity['object_id']) : '';
                    $mod_title  = !empty($activity['meta']['post_title']) ? $activity['meta']['post_title'] : '';
                    $comment_id = isset($activity['meta']['comment_id']) ? (int) $activity['meta']['comment_id'] : 0;
                    $comment    = $comment_id ? get_comment($comment_id) : null;
                    $excerpt    = '';
                    $attachments_markup = '';

                    if ($comment instanceof WP_Comment) {
                        $excerpt = wp_trim_words(wp_strip_all_tags($comment->comment_content), 45, '…');
                        $attachments_markup = gta6mods_get_comment_attachments_markup($comment);
                    } elseif (!empty($activity['meta']['excerpt'])) {
                        $excerpt = wp_trim_words(wp_strip_all_tags($activity['meta']['excerpt']), 45, '…');
                    }
                    ?>
                    <p class="text-sm">
                        <strong><?php echo esc_html($display_name); ?></strong>
                        <?php esc_html_e('commented on', 'gta6-mods'); ?>
                        <?php if ($mod_link && $mod_title) : ?>
                            <a href="<?php echo esc_url($mod_link); ?>" class="font-semibold text-pink-600 hover:underline"><?php echo esc_html($mod_title); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($mod_title); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($excerpt || $attachments_markup) : ?>
                        <div class="comment-bubble relative p-4 rounded-lg bg-gray-100 mt-2">
                            <?php if ($excerpt) : ?>
                                <div class="text-gray-800 leading-relaxed">
                                    &ldquo;<?php echo esc_html($excerpt); ?>&rdquo;
                                </div>
                            <?php endif; ?>
                            <?php if ($attachments_markup) : ?>
                                <?php echo $attachments_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php
                } elseif ('followed' === $action) {
                    $follower = isset($activity['object_id']) ? get_userdata((int) $activity['object_id']) : null;
                    if ($follower instanceof WP_User) {
                        ?>
                        <p class="text-sm">
                            <a href="<?php echo esc_url(get_author_posts_url($follower->ID)); ?>" class="font-semibold text-pink-600 hover:underline"><?php echo esc_html($follower->display_name); ?></a>
                            <?php printf(esc_html__('started following %s', 'gta6-mods'), esc_html($display_name)); ?>
                        </p>
                        <?php
                    } else {
                        ?>
                        <p class="text-sm"><?php echo esc_html(gta6mods_format_activity_message($activity)); ?></p>
                        <?php
                    }
                } elseif ('following' === $action) {
                    $followed_user = isset($activity['object_id']) ? get_userdata((int) $activity['object_id']) : null;
                    ?>
                    <p class="text-sm">
                        <strong><?php echo esc_html($display_name); ?></strong>
                        <?php esc_html_e('followed', 'gta6-mods'); ?>
                        <?php if ($followed_user instanceof WP_User) : ?>
                            <a href="<?php echo esc_url(get_author_posts_url($followed_user->ID)); ?>" class="font-semibold text-pink-600 hover:underline"><?php echo esc_html($followed_user->display_name); ?></a>
                        <?php else : ?>
                            <?php esc_html_e('another creator', 'gta6-mods'); ?>
                        <?php endif; ?>
                    </p>
                    <?php
                } else {
                    ?>
                    <p class="text-sm"><?php echo esc_html(gta6mods_format_activity_message($activity)); ?></p>
                    <?php
                }

                if ($time_ago && !$time_shown) {
                    ?>
                    <p class="text-xs text-gray-500 mt-1.5"><?php echo esc_html($time_ago); ?></p>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    return ob_get_clean();
}

function gta6mods_rest_get_author_activity($request) {
    $author_id = absint($request['id']);

    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid author.', 'gta6-mods'), ['status' => 400]);
    }

    $per_page = (int) $request->get_param('per_page');
    if ($per_page <= 0) {
        $per_page = 8;
    }
    $per_page = min($per_page, 20);

    $offset = max(0, (int) $request->get_param('offset'));

    $activities = gta6mods_get_user_activity($author_id, $per_page + 1, $offset);
    $has_more   = count($activities) > $per_page;

    if ($has_more) {
        $activities = array_slice($activities, 0, $per_page);
    }

    if (empty($activities)) {
        return rest_ensure_response([
            'html'        => '',
            'has_more'    => false,
            'next_offset' => $offset,
        ]);
    }

    $display_name    = get_the_author_meta('display_name', $author_id);
    $current_user_id = get_current_user_id();
    $activity_markup = gta6mods_render_activity_items_markup($activities, $display_name, $current_user_id);

    return rest_ensure_response([
        'html'        => $activity_markup,
        'has_more'    => $has_more,
        'next_offset' => $offset + count($activities),
    ]);
}

/**
 * Loads a template part and returns the rendered HTML.
 *
 * @param string $slug Template slug.
 * @param array  $args Arguments passed to the template.
 *
 * @return string
 */
function gta6mods_get_author_template_html($slug, $args = []) {
    ob_start();
    get_template_part('template-parts/author/' . $slug, null, $args);
    return ob_get_clean();
}

/**
 * Determines whether the current user can access the mod update data endpoint.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return true|WP_Error
 */
function gta6mods_rest_can_access_mod_update($request) {
    $mod_id = absint($request['id']);

    if ($mod_id <= 0) {
        return new WP_Error('rest_invalid_mod', __('Invalid mod identifier.', 'gta6-mods'), ['status' => 400]);
    }

    $mod_post = get_post($mod_id);

    if (!$mod_post instanceof WP_Post || 'post' !== $mod_post->post_type) {
        return new WP_Error('rest_mod_not_found', __('The requested mod could not be found.', 'gta6-mods'), ['status' => 404]);
    }

    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', __('You must be signed in to access this resource.', 'gta6-mods'), ['status' => 401]);
    }

    if (!current_user_can('edit_post', $mod_id)) {
        return new WP_Error('rest_forbidden', __('You do not have permission to update this mod.', 'gta6-mods'), ['status' => 403]);
    }

    $current_user_id = get_current_user_id();

    if ('pending' === $mod_post->post_status && (int) $mod_post->post_author === $current_user_id && !current_user_can('edit_others_posts')) {
        return new WP_Error('rest_forbidden', __('This mod is still pending review.', 'gta6-mods'), ['status' => 403]);
    }

    if (function_exists('gta6mods_mod_has_pending_update') && function_exists('gta6mods_user_can_bypass_pending_lock')) {
        if (gta6mods_mod_has_pending_update($mod_id) && !gta6mods_user_can_bypass_pending_lock($current_user_id)) {
            return new WP_Error('rest_forbidden', __('This mod already has an update pending review.', 'gta6-mods'), ['status' => 403]);
        }
    }

    return true;
}

/**
 * Provides the data required to populate the mod update form via REST.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_mod_update_data($request) {
    $mod_id = absint($request['id']);
    $mod_post = get_post($mod_id);

    if (!$mod_post instanceof WP_Post || 'post' !== $mod_post->post_type) {
        return new WP_Error('rest_mod_not_found', __('The requested mod could not be found.', 'gta6-mods'), ['status' => 404]);
    }

    $category_terms   = get_the_category($mod_id);
    $primary_category = !empty($category_terms) ? $category_terms[0] : null;

    $available_categories = function_exists('gta6mods_get_allowed_category_options') ? gta6mods_get_allowed_category_options() : [];

    $additional_authors = get_post_meta($mod_id, '_gta6mods_additional_authors', true);
    if (!is_array($additional_authors)) {
        $additional_authors = [];
    } else {
        $additional_authors = array_values(array_filter(array_map('sanitize_text_field', $additional_authors)));
    }

    $tags       = get_the_terms($mod_id, 'post_tag');
    $tag_names  = [];
    if (!empty($tags) && !is_wp_error($tags)) {
        foreach ($tags as $tag) {
            $tag_names[] = $tag->name;
        }
    }
    $tags_string = implode(', ', $tag_names);

    $description_payload = function_exists('gta6_mods_get_editorjs_payload') ? gta6_mods_get_editorjs_payload($mod_id) : '';

    $video_permission = get_post_meta($mod_id, '_gta6mods_video_permissions', true);
    $video_permission = in_array($video_permission, ['deny', 'moderate', 'allow'], true) ? $video_permission : 'moderate';

    $gallery_images = function_exists('gta6_mods_get_gallery_images') ? gta6_mods_get_gallery_images($mod_id) : [];
    $featured_id    = get_post_thumbnail_id($mod_id);
    $screenshot_payload = [];

    foreach ($gallery_images as $image) {
        if (!is_array($image)) {
            continue;
        }

        $attachment_id = isset($image['attachment_id']) ? (int) $image['attachment_id'] : 0;
        if ($attachment_id <= 0) {
            continue;
        }

        $url = '';
        if (!empty($image['url']) && filter_var($image['url'], FILTER_VALIDATE_URL)) {
            $url = $image['url'];
        } else {
            $url = wp_get_attachment_image_url($attachment_id, 'large');
        }

        if (!$url) {
            continue;
        }

        $screenshot_payload[] = [
            'id'         => $attachment_id,
            'url'        => esc_url_raw($url),
            'isFeatured' => ($featured_id && $attachment_id === (int) $featured_id),
            'alt'        => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
        ];
    }

    $versions        = function_exists('gta6_mods_get_mod_versions_for_display') ? gta6_mods_get_mod_versions_for_display($mod_id) : [];
    $current_version = function_exists('gta6_mods_get_current_version_for_display') ? gta6_mods_get_current_version_for_display($mod_id) : [];

    $stats = [
        'likes'     => number_format_i18n(function_exists('gta6_mods_get_like_count') ? gta6_mods_get_like_count($mod_id) : 0),
        'views'     => number_format_i18n(function_exists('gta6_mods_get_view_count') ? gta6_mods_get_view_count($mod_id) : 0),
        'downloads' => number_format_i18n(function_exists('gta6_mods_get_download_count') ? gta6_mods_get_download_count($mod_id) : 0),
    ];

    $response = [
        'modId'            => $mod_id,
        'modTitle'         => get_the_title($mod_id),
        'category'         => $primary_category ? [
            'id'   => (int) $primary_category->term_id,
            'name' => $primary_category->name,
            'slug' => $primary_category->slug,
        ] : null,
        'categories'       => $available_categories,
        'primaryAuthor'    => get_the_author_meta('display_name', $mod_post->post_author),
        'additionalAuthors'=> $additional_authors,
        'tags'             => $tags_string,
        'description'      => $description_payload,
        'videoPermission'  => $video_permission,
        'screenshots'      => $screenshot_payload,
        'versions'         => $versions,
        'currentVersion'   => $current_version,
        'stats'            => $stats,
        'pendingChangelog' => [],
    ];

    return rest_ensure_response($response);
}

/**
 * REST callback: uploads tab.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_author_uploads($request) {
    $author_id = absint($request['id']);
    $page      = max(1, (int) $request->get_param('page'));

    $html = gta6mods_render_author_tab_content('uploads', $author_id, [
        'page'     => $page,
        'base_url' => gta6mods_get_author_profile_tab_url($author_id, 'uploads'),
    ]);

    if (is_wp_error($html)) {
        return $html;
    }

    return rest_ensure_response([
        'html' => $html,
    ]);
}

/**
 * REST callback: author comments.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_author_comments($request) {
    $author_id = absint($request['id']);
    $page      = max(1, (int) $request->get_param('page'));

    $prepared = gta6mods_prepare_author_comments_view($author_id, $page);

    if (is_wp_error($prepared)) {
        return $prepared;
    }

    $html = gta6mods_get_author_template_html('comments', [
        'comments'   => $prepared['comments'],
        'author_id'  => $author_id,
        'total'      => $prepared['total'],
        'pagination' => [
            'current' => $prepared['page'],
            'total'   => $prepared['total_pages'],
        ],
        'base_url'   => gta6mods_get_author_profile_tab_url($author_id, 'comments'),
        'per_page'   => $prepared['per_page'],
    ]);

    return rest_ensure_response([
        'html' => $html,
        'page' => $prepared['page'],
    ]);
}

/**
 * REST callback: author notifications.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_author_notifications($request) {
    $author_id = absint($request['id']);

    $html = gta6mods_render_author_tab_content('notifications', $author_id);

    if (is_wp_error($html)) {
        return $html;
    }

    return rest_ensure_response([
        'html' => $html,
    ]);
}

/**
 * REST callback: recent notifications for the dropdown.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_unread_notifications($request) {
    $author_id = absint($request['id']);

    if ($author_id <= 0 || get_current_user_id() !== $author_id) {
        return new WP_Error('forbidden', __('You are not allowed to access these notifications.', 'gta6-mods'), ['status' => 403]);
    }

    $limit_param = (int) $request->get_param('limit');
    $limit       = $limit_param > 0 ? $limit_param : 5;
    $limit       = min(max($limit, 1), 10);

    $notifications = gta6mods_get_recent_notifications_cached($author_id, $limit);
    $count         = gta6mods_get_unread_count_cached($author_id);

    $unread_ids = array_values(array_filter(array_map(
        static function ($notification) {
            if (!is_array($notification)) {
                return 0;
            }

            $id = isset($notification['id']) ? (int) $notification['id'] : 0;
            if ($id <= 0) {
                return 0;
            }

            $is_read = isset($notification['is_read']) ? (int) $notification['is_read'] : 0;

            return 0 === $is_read ? $id : 0;
        },
        $notifications
    )));

    $html = gta6mods_get_author_template_html('notification-items', [
        'notifications' => $notifications,
    ]);

    return rest_ensure_response([
        'html'        => $html,
        'count'       => $count,
        'unread_ids'  => $unread_ids,
        'total_items' => count($notifications),
    ]);
}

/**
 * REST callback: mark notifications as read.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_mark_notifications_read($request) {
    $author_id = absint($request['id']);

    if ($author_id <= 0 || get_current_user_id() !== $author_id) {
        return new WP_Error('forbidden', __('You are not allowed to modify these notifications.', 'gta6-mods'), ['status' => 403]);
    }

    $nonce = $request->get_header('X-WP-Nonce');

    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Invalid security token.', 'gta6-mods'), ['status' => 403]);
    }

    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = [];
    }

    $mark_all = isset($params['mark_all'])
        ? rest_sanitize_boolean($params['mark_all'])
        : rest_sanitize_boolean($request->get_param('mark_all'));

    $notification_ids = $params['notification_ids'] ?? $request->get_param('notification_ids');
    if (!is_array($notification_ids)) {
        $notification_ids = [];
    }

    $notification_ids = array_map('absint', $notification_ids);
    $notification_ids = array_filter($notification_ids, static function ($id) {
        return $id > 0;
    });
    if (count($notification_ids) > 50) {
        $notification_ids = array_slice($notification_ids, 0, 50);
    }
    $notification_ids = array_values(array_unique($notification_ids));

    if (!$mark_all && empty($notification_ids)) {
        return new WP_Error(
            'missing_notifications',
            __('Please choose at least one notification to mark as read.', 'gta6-mods'),
            ['status' => 400]
        );
    }

    global $wpdb;

    $table   = $wpdb->prefix . 'gta_notifications';
    $updated = 0;

    $marked_ids = [];

    if ($mark_all) {
        $ids_to_mark = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE recipient_user_id = %d AND is_read = 0",
                $author_id
            )
        );

        $result = $wpdb->update(
            $table,
            ['is_read' => 1],
            ['recipient_user_id' => $author_id, 'is_read' => 0],
            ['%d'],
            ['%d', '%d']
        );

        if (false === $result) {
            return new WP_Error('mark_failed', __('Unable to update notifications.', 'gta6-mods'), ['status' => 500]);
        }

        if ($result) {
            $updated = (int) $result;
        }

        if (is_array($ids_to_mark)) {
            $marked_ids = array_values(array_map('absint', $ids_to_mark));
        }
    } elseif (!empty($notification_ids)) {
        $placeholders = implode(',', array_fill(0, count($notification_ids), '%d'));
        $sql          = "UPDATE {$table} SET is_read = 1 WHERE recipient_user_id = %d AND is_read = 0 AND id IN ({$placeholders})";

        $prepared = $wpdb->prepare($sql, array_merge([$author_id], $notification_ids));

        if ($prepared) {
            $result = $wpdb->query($prepared);

            if (false === $result) {
                return new WP_Error('mark_failed', __('Unable to update notifications.', 'gta6-mods'), ['status' => 500]);
            }

            if ($result) {
                $updated = (int) $result;
                $marked_ids = $notification_ids;
            }
        }
    }

    gta6mods_invalidate_notification_cache($author_id);

    $remaining = gta6mods_get_unread_count_cached($author_id);

    return rest_ensure_response([
        'updated' => $updated,
        'count'   => $remaining,
        'marked_ids' => $marked_ids,
    ]);
}

/**
 * REST callback: author collections.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_author_collections($request) {
    $author_id = absint($request['id']);

    $html = gta6mods_render_author_tab_content('collections', $author_id);

    if (is_wp_error($html)) {
        return $html;
    }

    return rest_ensure_response([
        'html' => $html,
    ]);
}

function gta6mods_rest_can_self_manage_account_deletion($request) {
    if (!is_user_logged_in()) {
        return false;
    }

    $author_id = absint($request['id']);

    if ($author_id <= 0 || get_current_user_id() !== $author_id) {
        return false;
    }

    $user = get_userdata($author_id);

    if (!$user instanceof WP_User) {
        return false;
    }

    return gta6mods_user_can_self_schedule_account_deletion($user);
}

/**
 * REST callback: bookmarks tab.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_author_bookmarks($request) {
    $author_id = absint($request['id']);

    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid author.', 'gta6-mods'), ['status' => 400]);
    }

    $per_page = (int) apply_filters('gta6mods_author_bookmarks_per_page', 6, $author_id);
    if ($per_page <= 0) {
        $per_page = 6;
    }

    $page = absint($request->get_param('page'));
    if ($page <= 0) {
        $page = 1;
    }

    $bookmarked_ids = gta6_mods_get_user_bookmarked_mod_ids($author_id);

    if (empty($bookmarked_ids)) {
        $legacy = get_user_meta($author_id, '_saved_mods', true);
        if (is_array($legacy)) {
            $legacy = array_map('absint', $legacy);
            $legacy = array_filter($legacy);
            if (!empty($legacy)) {
                $bookmarked_ids = array_values(array_unique($legacy));
                update_user_meta($author_id, GTA6_MODS_BOOKMARK_META_KEY, $bookmarked_ids);
                delete_user_meta($author_id, '_saved_mods');
            }
        }
    }

    $total_items = count($bookmarked_ids);
    $total_pages = max(1, (int) ceil($total_items / $per_page));

    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $offset = ($page - 1) * $per_page;
    $page_ids = array_slice($bookmarked_ids, $offset, $per_page);

    $posts = [];
    if (!empty($page_ids)) {
        $posts = get_posts([
            'post_type'      => gta6mods_get_mod_post_types(),
            'post__in'       => $page_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count($page_ids),
            'post_status'    => 'publish',
        ]);
    }

    $thread_ids = function_exists('gta6_forum_get_user_bookmarked_thread_ids')
        ? gta6_forum_get_user_bookmarked_thread_ids($author_id)
        : [];

    $thread_posts = [];
    if (!empty($thread_ids)) {
        $thread_posts = get_posts([
            'post_type'      => 'forum_thread',
            'post__in'       => $thread_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count($thread_ids),
            'post_status'    => 'publish',
        ]);
    }

    $thread_count = count($thread_ids);
    $combined_total = $total_items + $thread_count;

    ob_start();

    $hasThreads = !empty($thread_posts);
    $hasMods = !empty($posts);

    if (!$hasThreads && !$hasMods) {
        echo '<div class="text-center py-12">';
        echo '<i class="fas fa-bookmark text-4xl text-gray-300 mb-4"></i>';
        echo '<h3 class="font-bold text-lg text-gray-700">' . esc_html__('No saved items yet', 'gta6-mods') . '</h3>';
        echo '<p class="text-gray-500 mt-1">' . esc_html__('Bookmark mods or forum threads to build your collection.', 'gta6-mods') . '</p>';
        echo '</div>';
    } else {
        echo '<div class="space-y-10">';

        if ($hasThreads) {
            echo '<section class="space-y-4">';
            echo '<h3 class="text-lg font-bold text-gray-900">' . esc_html__('Saved threads', 'gta6-mods') . '</h3>';
            echo '<div class="space-y-4">';
            foreach ($thread_posts as $thread_post) {
                if (!($thread_post instanceof WP_Post)) {
                    continue;
                }

                $thread_flairs = get_the_terms($thread_post, 'forum_flair');
                $time_diff = human_time_diff(get_post_time('U', true, $thread_post), current_time('timestamp'));

                echo '<article class="card border border-transparent hover:border-pink-200 transition">';
                echo '<a class="block p-4" href="' . esc_url(get_permalink($thread_post)) . '">';

                if (!empty($thread_flairs) && !is_wp_error($thread_flairs)) {
                    echo '<div class="flex items-center gap-2 text-xs text-gray-500 mb-2">';
                    foreach ($thread_flairs as $flair) {
                        $colors = function_exists('gta6_forum_get_flair_colors') ? gta6_forum_get_flair_colors($flair->term_id) : ['background' => '#f3f4f6', 'text' => '#1f2937'];
                        echo '<span class="post-flair" style="background-color: ' . esc_attr($colors['background']) . '; color: ' . esc_attr($colors['text']) . ';">' . esc_html($flair->name) . '</span>';
                    }
                    echo '</div>';
                }

                echo '<h4 class="font-semibold text-base text-gray-900 mb-1 hover:text-pink-600 transition">' . esc_html(get_the_title($thread_post)) . '</h4>';
                echo '<p class="text-xs text-gray-500">' . esc_html__('Posted by', 'gta6-mods') . ' ' . esc_html(get_the_author_meta('display_name', $thread_post->post_author)) . ' · ' . esc_html(sprintf(_x('%s ago', 'time ago', 'gta6-mods'), $time_diff)) . '</p>';
                echo '<p class="text-sm text-gray-600 mt-2 leading-relaxed">' . esc_html(wp_trim_words($thread_post->post_content, 24)) . '</p>';

                echo '</a>';
                echo '</article>';
            }
            echo '</div>';
            echo '</section>';
        }

        if ($hasMods) {
            echo '<section class="space-y-4">';
            echo '<h3 class="text-lg font-bold text-gray-900">' . esc_html__('Saved mods', 'gta6-mods') . '</h3>';
            echo '<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">';
            foreach ($posts as $post) {
                if (!($post instanceof WP_Post)) {
                    continue;
                }

                $thumbnail = get_the_post_thumbnail_url($post, 'medium_large');
                if (!$thumbnail) {
                    $thumbnail = apply_filters('gta6mods_mod_placeholder_image', gta6_mods_get_placeholder('card'), $post->ID);
                }

                $downloads = function_exists('gta6_mods_get_download_count')
                    ? (int) gta6_mods_get_download_count($post->ID)
                    : (int) get_post_meta($post->ID, '_gta6mods_download_count', true);

                $likes = gta6_mods_get_like_count($post->ID);

                echo '<article class="card group overflow-hidden border border-transparent hover:border-pink-200 transition">';
                echo '<a class="block" href="' . esc_url(get_permalink($post)) . '">';
                echo '<div class="relative">';
                echo '<img src="' . esc_url($thumbnail) . '" alt="' . esc_attr(get_the_title($post)) . '" class="w-full h-32 object-cover group-hover:scale-105 transition-transform duration-300" />';
                echo '<div class="absolute bottom-0 left-0 right-0 p-1.5 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">';
                echo '<div class="flex justify-between items-center">';
                echo '<span class="flex items-center"><i class="fas fa-download mr-1"></i>' . esc_html(number_format_i18n($downloads)) . '</span>';
                echo '<span class="flex items-center"><i class="fas fa-thumbs-up mr-1"></i>' . esc_html(number_format_i18n($likes)) . '</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="p-3">';
                echo '<h4 class="font-semibold text-sm text-gray-800 group-hover:text-pink-600 transition truncate">' . esc_html(get_the_title($post)) . '</h4>';
                echo '<p class="text-xs text-gray-500 mt-1">' . esc_html(get_the_author_meta('display_name', $post->post_author)) . '</p>';
                echo '</div>';
                echo '</a>';
                echo '</article>';
            }
            echo '</div>';
            echo '</section>';
        }

        echo '</div>';
    }

    $html = ob_get_clean();

    ob_start();
    if ($total_pages > 1) {
        echo '<nav class="mt-6 flex justify-center" aria-label="' . esc_attr__('Bookmarks pagination', 'gta6-mods') . '">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $is_current = ($i === $page);
            $classes = $is_current
                ? 'mx-1 px-3 py-1 rounded-lg bg-pink-600 text-white font-semibold'
                : 'mx-1 px-3 py-1 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition';

            printf(
                '<a href="#bookmarks" class="%1$s" data-page="%2$d"%3$s>%2$d</a>',
                esc_attr($classes),
                (int) $i,
                $is_current ? ' aria-current="page"' : ''
            );
        }
        echo '</nav>';
    }
    $pagination_html = ob_get_clean();

    return rest_ensure_response([
        'html'            => $html,
        'pagination_html' => $pagination_html,
        'total'           => $combined_total,
        'page'            => $page,
        'total_pages'     => $total_pages,
    ]);
}

function gta6mods_rest_request_account_deletion($request) {
    $author_id = absint($request['id']);

    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid account.', 'gta6-mods'), ['status' => 400]);
    }

    $user = get_userdata($author_id);

    if (!$user instanceof WP_User) {
        return new WP_Error('invalid_author', __('Invalid account.', 'gta6-mods'), ['status' => 400]);
    }

    $existing = gta6_mods_get_account_deletion_data($author_id);
    if (is_array($existing)) {
        return new WP_Error('deletion_pending', __('You already have a pending account deletion request.', 'gta6-mods'), ['status' => 400]);
    }

    $params = $request->get_json_params();
    $confirmation = '';

    if (is_array($params) && isset($params['confirmation'])) {
        $confirmation = (string) $params['confirmation'];
    } elseif ($request->get_param('confirmation')) {
        $confirmation = (string) $request->get_param('confirmation');
    }

    $required_phrase = apply_filters('gta6mods_account_deletion_confirmation_phrase', 'Delete my account', $author_id);
    if (!empty($required_phrase) && $confirmation !== $required_phrase) {
        return new WP_Error('invalid_confirmation', __('The confirmation phrase does not match.', 'gta6-mods'), ['status' => 400]);
    }

    $payload = [
        'status'        => 'pending',
        'requested_at'  => current_time('timestamp'),
        'scheduled_for' => 0,
        'finalized_at'  => 0,
        'method'        => 'requested',
    ];

    update_user_meta($author_id, GTA6_MODS_ACCOUNT_DELETION_META_KEY, $payload);
    update_user_meta($author_id, '_account_deletion_status', 'pending');

    if (function_exists('gta6mods_log_activity')) {
        gta6mods_log_activity($author_id, 'account_deletion_requested');
    }

    $response_payload = gta6_mods_prepare_account_deletion_payload($author_id, $payload);

    return rest_ensure_response([
        'message'  => __('Account deletion request received. A moderator will review it soon.', 'gta6-mods'),
        'deletion' => $response_payload,
    ]);
}

function gta6mods_rest_cancel_account_deletion($request) {
    $author_id = absint($request['id']);

    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid account.', 'gta6-mods'), ['status' => 400]);
    }

    $existing = gta6_mods_get_account_deletion_data($author_id);

    if (!is_array($existing) || !isset($existing['status']) || 'pending' !== $existing['status']) {
        return new WP_Error('no_pending_request', __('There is no pending account deletion request to cancel.', 'gta6-mods'), ['status' => 400]);
    }

    delete_user_meta($author_id, GTA6_MODS_ACCOUNT_DELETION_META_KEY);
    delete_user_meta($author_id, '_account_deletion_status');

    if (function_exists('gta6mods_log_activity')) {
        gta6mods_log_activity($author_id, 'account_deletion_cancelled');
    }

    $payload = gta6_mods_prepare_account_deletion_payload($author_id, null);

    return rest_ensure_response([
        'message'  => __('Account deletion request cancelled.', 'gta6-mods'),
        'deletion' => $payload,
    ]);
}

function gta6mods_rest_finalize_account_deletion($request) {
    $author_id = absint($request['id']);

    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid account.', 'gta6-mods'), ['status' => 400]);
    }

    $user = get_userdata($author_id);

    if (!$user instanceof WP_User) {
        return new WP_Error('invalid_author', __('Invalid account.', 'gta6-mods'), ['status' => 400]);
    }

    $existing = gta6_mods_get_account_deletion_data($author_id);
    if (!is_array($existing) || !isset($existing['status']) || 'pending' !== $existing['status']) {
        return new WP_Error('no_pending_request', __('No pending account deletion request was found.', 'gta6-mods'), ['status' => 400]);
    }

    $params = $request->get_json_params();
    $password = '';

    if (is_array($params) && isset($params['password'])) {
        $password = (string) $params['password'];
    } elseif ($request->get_param('password')) {
        $password = (string) $request->get_param('password');
    }

    if ('' === $password) {
        return new WP_Error('password_required', __('Please enter your password to continue.', 'gta6-mods'), ['status' => 400]);
    }

    if (!wp_check_password($password, $user->user_pass, $author_id)) {
        return new WP_Error('incorrect_password', __('Your password was incorrect.', 'gta6-mods'), ['status' => 400]);
    }

    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    $caps_filter = static function ($allcaps, $caps, $args) use ($author_id) {
        if (!is_array($args) || empty($args)) {
            return $allcaps;
        }

        if ('delete_user' === $args[0] && isset($args[2]) && (int) $args[2] === $author_id) {
            $allcaps[$caps[0]] = true;
        }

        return $allcaps;
    };

    add_filter('user_has_cap', $caps_filter, 10, 3);

    if (function_exists('gta6mods_log_activity')) {
        gta6mods_log_activity($author_id, 'account_deletion_finalized');
    }

    $deletion_payload = gta6_mods_mark_account_as_deleted($author_id, 'immediate', $existing);

    $deleted = wp_delete_user($author_id);

    remove_filter('user_has_cap', $caps_filter, 10);

    if (!$deleted) {
        return new WP_Error('delete_failed', __('We could not delete your account. Please contact support.', 'gta6-mods'), ['status' => 500]);
    }

    if (function_exists('wp_destroy_user_sessions')) {
        wp_destroy_user_sessions($author_id);
    }

    wp_logout();

    return rest_ensure_response([
        'message'  => __('Your account has been deleted. You will be signed out.', 'gta6-mods'),
        'redirect' => home_url('/'),
        'deletion' => gta6_mods_prepare_account_deletion_payload($author_id, $deletion_payload),
    ]);
}

function gta6mods_rest_get_mod_comments($request) {
    $post_id = absint($request['id']);

    if ($post_id <= 0) {
        return new WP_Error('invalid_post', __('Invalid mod.', 'gta6-mods'), ['status' => 400]);
    }

    $mod_post = get_post($post_id);

    if (!($mod_post instanceof WP_Post) || !in_array($mod_post->post_type, gta6mods_get_mod_post_types(), true)) {
        return new WP_Error('invalid_post', __('Invalid mod.', 'gta6-mods'), ['status' => 404]);
    }

    $page = absint($request->get_param('page'));
    if ($page <= 0) {
        $page = 1;
    }

    $per_page = (int) get_option('comments_per_page');
    if ($per_page <= 0) {
        $per_page = 10;
    }

    $comment_count = get_comments([
        'post_id' => $post_id,
        'status'  => 'approve',
        'count'   => true,
    ]);

    $comment_count = (int) $comment_count;
    $total_pages   = max(1, (int) ceil($comment_count / $per_page));

    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $pinned_comment_id = gta6mods_get_pinned_comment_id($post_id);

    $all_comments = get_comments([
        'post_id'      => $post_id,
        'status'       => 'approve',
        'orderby'      => 'comment_date_gmt',
        'order'        => 'DESC',
        'hierarchical' => 'threaded',
    ]);

    if (!empty($all_comments) && $pinned_comment_id > 0 && function_exists('gta6mods_prioritize_pinned_comment')) {
        $all_comments = gta6mods_prioritize_pinned_comment($all_comments, $pinned_comment_id);
    }

    $max_depth = gta6mods_get_comment_max_depth($post_id);
    $walker = class_exists('GTA6_Mods_Comment_Walker')
        ? new GTA6_Mods_Comment_Walker([
            'pinned_comment_id' => $pinned_comment_id,
            'max_depth'         => $max_depth,
        ])
        : null;

    global $post;
    $previous_post = $post;
    $post = $mod_post;
    setup_postdata($mod_post);

    $form_html = gta6mods_get_comment_form_markup($post_id);

    ob_start();

    echo '<div id="gta6-comment-list" class="space-y-4">';
    if (!empty($all_comments)) {
        wp_list_comments([
            'style'      => 'div',
            'short_ping' => false,
            'avatar_size'=> 40,
            'walker'     => $walker,
            'max_depth'  => $max_depth,
            'page'       => $page,
            'per_page'   => $per_page,
        ], $all_comments);
    }
    echo '</div>';

    $comments_html = ob_get_clean();

    ob_start();
    if ($total_pages > 1) {
        echo '<nav class="mt-6 flex justify-center" aria-label="' . esc_attr__('Comments pagination', 'gta6-mods') . '">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $is_current = ($i === $page);
            $classes = $is_current
                ? 'mx-1 px-3 py-1 rounded-lg bg-pink-600 text-white font-semibold'
                : 'mx-1 px-3 py-1 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition';

            printf(
                '<a href="#mod-comments" class="%1$s" data-page="%2$d"%3$s>%2$d</a>',
                esc_attr($classes),
                (int) $i,
                $is_current ? ' aria-current="page"' : ''
            );
        }
        echo '</nav>';
    }

    $pagination_html = ob_get_clean();

    $comment_label_template = esc_html__('Kommentek (%s)', 'gta6-mods');
    $comment_label          = sprintf($comment_label_template, number_format_i18n($comment_count));

    ob_start();
    ?>
    <div id="gta6-comments">
        <div id="kommentek" class="space-y-6">
            <div class="flex flex-row items-center justify-between gap-4">
                <h3 class="font-bold text-lg text-gray-900" data-comment-count-label data-template-singular="<?php echo esc_attr__('%s Comment', 'gta6-mods'); ?>" data-template-plural="<?php echo esc_attr__('%s Comments', 'gta6-mods'); ?>"><?php echo esc_html($comment_label); ?></h3>
                <div class="flex items-center gap-x-2">
                    <label for="gta6-comment-sort" class="sr-only"><?php esc_html_e('Sort comments', 'gta6-mods'); ?></label>
                    <select id="gta6-comment-sort" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-pink-300 focus:ring-2 focus:ring-pink-500 cursor-pointer" aria-label="<?php esc_attr_e('Sort comments', 'gta6-mods'); ?>">
                        <option value="best"><?php esc_html_e('Best', 'gta6-mods'); ?></option>
                        <option value="newest"><?php esc_html_e('Newest', 'gta6-mods'); ?></option>
                        <option value="oldest"><?php esc_html_e('Oldest', 'gta6-mods'); ?></option>
                    </select>
                </div>
            </div>

            <div
                id="gta6-comment-feedback"
                class="fixed top-4 right-4 z-50 flex flex-col items-end gap-3 pointer-events-none"
                aria-live="polite"
                aria-atomic="true"
            ></div>

            <?php if ('' !== $form_html) : ?>
                <?php echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>

            <?php echo $comments_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php if ('' !== $pagination_html) : ?>
                <?php echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>

            <p id="gta6-no-comments" class="text-sm text-gray-500<?php echo $comment_count > 0 ? ' hidden' : ''; ?>"><?php esc_html_e('No comments yet. Be the first to share your thoughts!', 'gta6-mods'); ?></p>
        </div>
    </div>
    <?php
    $container_html = ob_get_clean();

    if ($previous_post instanceof WP_Post) {
        $post = $previous_post;
        setup_postdata($previous_post);
    } else {
        wp_reset_postdata();
    }

    return rest_ensure_response([
        'count'            => $comment_count,
        'page'             => $page,
        'total_pages'      => $total_pages,
        'comments_html'    => $comments_html,
        'pagination_html'  => $pagination_html,
        'form_html'        => $form_html,
        'html'             => $container_html,
    ]);
}

function gta6mods_rest_toggle_mod_like($request) {
    $post_id = absint($request['id']);

    if ($post_id <= 0 || !in_array(get_post_type($post_id), gta6mods_get_mod_post_types(), true)) {
        return new WP_Error('invalid_post', __('Invalid mod.', 'gta6-mods'), ['status' => 400]);
    }

    $user_id = get_current_user_id();

    if ($user_id <= 0) {
        return new WP_Error('not_logged_in', __('You must be logged in to like this mod.', 'gta6-mods'), ['status' => 401]);
    }

    $likes_key       = '_gta6mods_likes';
    $liked_users_key = '_gta6mods_liked_users';
    $liked_users     = get_post_meta($post_id, $liked_users_key, true);

    if (!is_array($liked_users)) {
        $liked_users = [];
    }

    $liked_users = array_map('absint', $liked_users);
    $liked_users = array_filter($liked_users);
    $liked_users = array_unique($liked_users);

    $already_liked = in_array($user_id, $liked_users, true);

    if ($already_liked) {
        $liked_users = array_values(array_diff($liked_users, [$user_id]));
    } else {
        $liked_users[] = $user_id;
    }

    $total_likes = count($liked_users);

    update_post_meta($post_id, $liked_users_key, $liked_users);
    gta6mods_set_mod_stat($post_id, 'likes', $total_likes);

    wp_cache_delete($post_id, 'post_meta');
    delete_transient('gta6_front_page_data_v1');

    $delta = $already_liked ? -1 : 1;
    gta6mods_adjust_author_like_total($post_id, $delta);

    return rest_ensure_response([
        'liked' => !$already_liked,
        'count' => $total_likes,
    ]);
}

function gta6mods_rest_toggle_mod_bookmark($request) {
    $post_id = absint($request['id']);

    if ($post_id <= 0 || !in_array(get_post_type($post_id), gta6mods_get_mod_post_types(), true)) {
        return new WP_Error('invalid_post', __('Invalid mod.', 'gta6-mods'), ['status' => 400]);
    }

    $user_id = get_current_user_id();

    if ($user_id <= 0) {
        return new WP_Error('not_logged_in', __('You must be logged in to bookmark this mod.', 'gta6-mods'), ['status' => 401]);
    }

    $bookmarks = gta6_mods_get_user_bookmarked_mod_ids($user_id);

    $is_bookmarked = in_array($post_id, $bookmarks, true);

    if ($is_bookmarked) {
        $bookmarks = array_values(array_diff($bookmarks, [$post_id]));
    } else {
        $bookmarks[] = $post_id;
    }

    update_user_meta($user_id, GTA6_MODS_BOOKMARK_META_KEY, $bookmarks);

    return rest_ensure_response([
        'is_bookmarked' => !$is_bookmarked,
    ]);
}

/**
 * REST callback: followers tab.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_get_author_followers($request) {
    $author_id = absint($request['id']);

    $html = gta6mods_render_author_tab_content('followers', $author_id);

    if (is_wp_error($html)) {
        return $html;
    }

    return rest_ensure_response([
        'html' => $html,
    ]);
}

/**
 * Handles follow/unfollow REST requests.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_handle_follow($request) {
    $author_id = absint($request['id']);
    $action    = $request->get_param('action');
    $nonce     = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    $current_user_id = get_current_user_id();

    if ($current_user_id <= 0 || $author_id <= 0) {
        return new WP_Error('invalid_request', __('Invalid request.', 'gta6-mods'), ['status' => 400]);
    }

    $follow = 'follow' === $action;

    $result = gta6mods_toggle_follow($current_user_id, $author_id, $follow);

    if (!$result) {
        return new WP_Error('follow_failed', __('Unable to update follow status.', 'gta6-mods'), ['status' => 500]);
    }

    $followers_count = (int) get_user_meta($author_id, '_follower_count', true);
    $is_following    = in_array($author_id, (array) get_user_meta($current_user_id, '_following', true), true);

    return rest_ensure_response([
        'success'         => true,
        'followers_count' => $followers_count,
        'is_following'    => $is_following,
    ]);
}

/**
 * Creates a status update on behalf of the current author.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_create_status_update($request) {
    $author_id       = absint($request['id']);
    $current_user_id = get_current_user_id();
    $nonce           = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    if ($author_id <= 0 || $current_user_id !== $author_id) {
        return new WP_Error('forbidden', __('You are not allowed to publish this status update.', 'gta6-mods'), ['status' => 403]);
    }

    $content = gta6mods_normalize_status_content($request->get_param('content'));

    if ('' === $content) {
        return new WP_Error('empty_content', __('Status update content cannot be empty.', 'gta6-mods'), ['status' => 400]);
    }

    $post_id = wp_insert_post([
        'post_type'    => 'status_update',
        'post_author'  => $author_id,
        'post_title'   => wp_trim_words($content, 8, '...'),
        'post_content' => $content,
        'post_status'  => 'publish',
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    gta6mods_log_activity($author_id, 'status_posted', $post_id);

    $activities   = gta6mods_get_user_activity($author_id, 1, 0);
    $display_name = get_the_author_meta('display_name', $author_id);
    $activity_html = '';

    if (!empty($activities)) {
        $activity_html = gta6mods_render_activity_items_markup($activities, $display_name, $current_user_id);
    }

    return rest_ensure_response([
        'success'   => true,
        'status_id' => $post_id,
        'html'      => $activity_html,
    ]);
}

/**
 * Permission callback to determine whether the current user can manage a status update.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return true|WP_Error
 */
function gta6mods_rest_can_manage_status_update($request) {
    if (!is_user_logged_in()) {
        return new WP_Error('forbidden', __('You are not allowed to manage this status update.', 'gta6-mods'), ['status' => 403]);
    }

    $status_id = absint($request['status_id']);

    if ($status_id <= 0) {
        return new WP_Error('invalid_status', __('Invalid status update.', 'gta6-mods'), ['status' => 400]);
    }

    $post = get_post($status_id);

    if (!($post instanceof WP_Post) || 'status_update' !== $post->post_type) {
        return new WP_Error('not_found', __('Status update not found.', 'gta6-mods'), ['status' => 404]);
    }

    $current = get_current_user_id();

    if ($current === (int) $post->post_author || user_can($current, 'delete_post', $post)) {
        return true;
    }

    return new WP_Error('forbidden', __('You are not allowed to manage this status update.', 'gta6-mods'), ['status' => 403]);
}

/**
 * Deletes or trashes a status update via the REST API.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_delete_status_update($request) {
    $nonce = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    $status_id = absint($request['status_id']);
    $post      = get_post($status_id);

    if (!($post instanceof WP_Post) || 'status_update' !== $post->post_type) {
        return new WP_Error('not_found', __('Status update not found.', 'gta6-mods'), ['status' => 404]);
    }

    if ('trash' === $post->post_status) {
        $deleted = wp_delete_post($status_id, true);

        if (false === $deleted) {
            return new WP_Error('delete_failed', __('Unable to delete the status update.', 'gta6-mods'), ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'state'   => 'deleted',
        ]);
    }

    $trashed = wp_trash_post($status_id);

    if (!$trashed || is_wp_error($trashed)) {
        return new WP_Error('delete_failed', __('Unable to delete the status update.', 'gta6-mods'), ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'state'   => 'trashed',
    ]);
}

/**
 * Saves settings submitted by the author from the profile page.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_save_author_settings($request) {
    $nonce = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    $user_id = get_current_user_id();

    $fields = $request->get_json_params();

    if (!is_array($fields)) {
        $fields = [];
    }

    if (isset($fields['email'])) {
        $email = sanitize_email($fields['email']);
        if (!empty($email)) {
            wp_update_user([
                'ID'         => $user_id,
                'user_email' => $email,
            ]);
        }
    }

    if (isset($fields['bio'])) {
        $bio_raw      = wp_kses_post($fields['bio']);
        $plain_bio    = wp_strip_all_tags($bio_raw, true);
        $trimmed_bio  = wp_html_excerpt($plain_bio, 160, '');
        update_user_meta($user_id, 'description', $trimmed_bio);
    }

    if (isset($fields['links']) && is_array($fields['links'])) {
        $definitions = gta6mods_get_social_link_definitions();
        $sanitized   = [];

        foreach ($fields['links'] as $key => $value) {
            $key = sanitize_key($key);

            if (!isset($definitions[$key])) {
                continue;
            }

            $normalized = gta6mods_normalize_social_link_value($key, $value);

            if ($normalized === '') {
                continue;
            }

            $sanitized[$key] = $normalized;
        }

        update_user_meta($user_id, '_social_links', $sanitized);
    }

    $avatar_updated = false;
    $clear_avatar   = false;

    if (isset($fields['clearAvatar'])) {
        $clear_avatar = filter_var($fields['clearAvatar'], FILTER_VALIDATE_BOOLEAN);
    }

    if ($clear_avatar) {
        delete_user_meta($user_id, '_gta6mods_avatar_type');
        delete_user_meta($user_id, '_gta6mods_avatar_preset');
        delete_user_meta($user_id, '_gta6mods_avatar_custom');
        $avatar_updated = true;
    } elseif (isset($fields['avatarPreset'])) {
        $preset = sanitize_file_name(wp_unslash($fields['avatarPreset']));

        if ('' !== $preset) {
            $definition = gta6mods_get_preset_avatar_definition($preset);
            if (null === $definition) {
                return new WP_Error('invalid_avatar_preset', __('The selected avatar is not available.', 'gta6-mods'), ['status' => 400]);
            }

            update_user_meta($user_id, '_gta6mods_avatar_type', 'preset');
            update_user_meta($user_id, '_gta6mods_avatar_preset', $definition['id']);
            delete_user_meta($user_id, '_gta6mods_avatar_custom');
            $avatar_updated = true;
        }
    }

    $avatar_choice = $avatar_updated ? gta6mods_get_user_avatar_choice($user_id) : null;

    if (null === $avatar_choice) {
        $avatar_choice = gta6mods_get_user_avatar_choice($user_id);
    }

    return rest_ensure_response([
        'success' => true,
        'message' => __('Profile settings saved.', 'gta6-mods'),
        'avatar'  => $avatar_choice,
    ]);
}

/**
 * Handles avatar uploads from the author settings screen.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_upload_author_avatar($request) {
    $nonce = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    $author_id = absint($request['id']);
    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid author.', 'gta6-mods'), ['status' => 400]);
    }

    $current_user = get_current_user_id();
    if ($current_user !== $author_id) {
        return new WP_Error('forbidden', __('You must be logged in as this author to upload an avatar.', 'gta6-mods'), ['status' => 403]);
    }

    $files     = $request->get_file_params();
    $file_keys = ['avatar_file', 'avatar', 'file'];
    $file      = null;

    foreach ($file_keys as $key) {
        if (isset($files[$key]) && !empty($files[$key]['tmp_name'])) {
            $file = $files[$key];
            break;
        }
    }

    if (!$file || empty($file['tmp_name'])) {
        return new WP_Error('missing_file', __('No avatar file received.', 'gta6-mods'), ['status' => 400]);
    }

    $max_size = (int) apply_filters('gta6mods_author_avatar_max_size', MB_IN_BYTES, $author_id);
    if (!empty($file['size']) && (int) $file['size'] > $max_size) {
        return new WP_Error('file_too_large', __('The avatar image exceeds the maximum size of 1 MB.', 'gta6-mods'), ['status' => 400]);
    }

    $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    $allowed   = ['jpg', 'jpeg', 'png', 'webp'];

    if (empty($file_type['ext']) || !in_array(strtolower($file_type['ext']), $allowed, true)) {
        return new WP_Error('invalid_file_type', __('Please upload a JPG, PNG, or WebP image.', 'gta6-mods'), ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = [
        'test_form' => false,
        'mimes'     => [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        ],
    ];

    $uploaded = wp_handle_upload($file, $overrides);

    if (isset($uploaded['error'])) {
        return new WP_Error('upload_error', $uploaded['error'], ['status' => 400]);
    }

    $attachment = [
        'post_mime_type' => $uploaded['type'],
        'post_title'     => sanitize_file_name($file['name']),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);

    if (is_wp_error($attachment_id)) {
        return $attachment_id;
    }

    wp_update_post([
        'ID'          => $attachment_id,
        'post_author' => $author_id,
    ]);

    $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
    if (!is_wp_error($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    update_post_meta($attachment_id, '_gta6mods_upload_context', 'author_avatar');

    update_user_meta($author_id, '_gta6mods_avatar_type', 'custom');
    update_user_meta($author_id, '_gta6mods_avatar_custom', (int) $attachment_id);
    delete_user_meta($author_id, '_gta6mods_avatar_preset');

    $avatar_choice = gta6mods_get_user_avatar_choice($author_id);
    $avatar_url    = $avatar_choice['url'] ?? '';

    return rest_ensure_response([
        'success'        => true,
        'message'        => __('Avatar updated.', 'gta6-mods'),
        'avatar'         => $avatar_choice,
        'url'            => $avatar_url,
        'attachment_id'  => (int) $attachment_id,
    ]);
}

/**
 * Deletes the currently uploaded custom avatar for the logged-in user.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_delete_author_avatar($request) {
    $nonce = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    $author_id = absint($request['id']);
    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid author.', 'gta6-mods'), ['status' => 400]);
    }

    $current_user = get_current_user_id();
    if ($current_user !== $author_id) {
        return new WP_Error('forbidden', __('You must be logged in as this author to delete an avatar.', 'gta6-mods'), ['status' => 403]);
    }

    $attachment_id = (int) get_user_meta($author_id, '_gta6mods_avatar_custom', true);

    if ($attachment_id > 0) {
        $attachment_author = (int) get_post_field('post_author', $attachment_id);
        $owns_attachment   = $attachment_author === $author_id;

        if (!$owns_attachment && !current_user_can('delete_post', $attachment_id)) {
            return new WP_Error('cannot_delete', __('You do not have permission to delete this avatar image.', 'gta6-mods'), ['status' => 403]);
        }

        $deleted = wp_delete_attachment($attachment_id, true);
        if (!$deleted && get_post($attachment_id)) {
            return new WP_Error('delete_failed', __('Unable to delete the avatar image.', 'gta6-mods'), ['status' => 500]);
        }
    }

    delete_user_meta($author_id, '_gta6mods_avatar_type');
    delete_user_meta($author_id, '_gta6mods_avatar_preset');
    delete_user_meta($author_id, '_gta6mods_avatar_custom');

    $avatar_choice = gta6mods_get_user_avatar_choice($author_id);

    return rest_ensure_response([
        'success'       => true,
        'message'       => __('Avatar deleted.', 'gta6-mods'),
        'avatar'        => $avatar_choice,
        'url'           => $avatar_choice['url'] ?? '',
        'attachment_id' => 0,
    ]);
}

/**
 * Handles banner uploads from the author settings screen.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_upload_author_banner($request) {
    $nonce = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    $author_id = absint($request['id']);
    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid author.', 'gta6-mods'), ['status' => 400]);
    }

    $current_user = get_current_user_id();
    if ($current_user !== $author_id) {
        return new WP_Error('forbidden', __('You must be logged in as this author to upload a banner.', 'gta6-mods'), ['status' => 403]);
    }

    $files     = $request->get_file_params();
    $file_keys = ['banner_file', 'banner', 'file'];
    $file      = null;

    foreach ($file_keys as $key) {
        if (isset($files[$key]) && !empty($files[$key]['tmp_name'])) {
            $file = $files[$key];
            break;
        }
    }

    if (!$file || empty($file['tmp_name'])) {
        return new WP_Error('missing_file', __('No banner file received.', 'gta6-mods'), ['status' => 400]);
    }

    $max_size = (int) apply_filters('gta6mods_author_banner_max_size', 2 * MB_IN_BYTES, $author_id);
    if (!empty($file['size']) && (int) $file['size'] > $max_size) {
        return new WP_Error('file_too_large', __('The banner image exceeds the maximum size of 2 MB.', 'gta6-mods'), ['status' => 400]);
    }

    $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    $allowed   = ['jpg', 'jpeg', 'png', 'webp'];

    if (empty($file_type['ext']) || !in_array(strtolower($file_type['ext']), $allowed, true)) {
        return new WP_Error('invalid_file_type', __('Please upload a JPG, PNG, or WebP image.', 'gta6-mods'), ['status' => 400]);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = [
        'test_form' => false,
        'mimes'     => [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        ],
    ];

    $uploaded = wp_handle_upload($file, $overrides);

    if (isset($uploaded['error'])) {
        return new WP_Error('upload_failed', $uploaded['error'], ['status' => 400]);
    }

    $attachment = [
        'post_mime_type' => $uploaded['type'],
        'post_title'     => sanitize_file_name($file['name']),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);

    if (is_wp_error($attachment_id)) {
        return $attachment_id;
    }

    wp_update_post([
        'ID'          => $attachment_id,
        'post_author' => $author_id,
    ]);

    $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
    if (!is_wp_error($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    $url = wp_get_attachment_image_url($attachment_id, 'full');
    if (!$url) {
        $url = wp_get_attachment_url($attachment_id);
    }

    update_user_meta($author_id, '_profile_banner_id', (int) $attachment_id);
    update_user_meta($author_id, '_profile_banner', esc_url_raw($url));
    update_post_meta($attachment_id, '_gta6mods_uploaded_by', $author_id);
    update_post_meta($attachment_id, '_gta6mods_upload_context', 'author_background');

    return rest_ensure_response([
        'success'        => true,
        'url'            => $url,
        'attachment_id'  => (int) $attachment_id,
        'message'        => __('Banner image updated.', 'gta6-mods'),
    ]);
}

/**
 * Removes the stored banner association for the current user.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_delete_author_banner($request) {
    $nonce = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    $author_id = absint($request['id']);
    if ($author_id <= 0) {
        return new WP_Error('invalid_author', __('Invalid author.', 'gta6-mods'), ['status' => 400]);
    }

    $current_user = get_current_user_id();
    if ($current_user !== $author_id) {
        return new WP_Error('forbidden', __('You must be logged in as this author to remove a banner.', 'gta6-mods'), ['status' => 403]);
    }

    $attachment_id = (int) get_user_meta($author_id, '_profile_banner_id', true);
    if ($attachment_id > 0) {
        $attachment_author = (int) get_post_field('post_author', $attachment_id);
        $owns_attachment   = $attachment_author === $author_id;

        if (!$owns_attachment && !current_user_can('delete_post', $attachment_id)) {
            return new WP_Error('cannot_delete', __('You do not have permission to delete this banner image.', 'gta6-mods'), ['status' => 403]);
        }

        $deleted = wp_delete_attachment($attachment_id, true);
        if (!$deleted && get_post($attachment_id)) {
            return new WP_Error('delete_failed', __('Unable to remove the banner image.', 'gta6-mods'), ['status' => 500]);
        }
    }

    delete_user_meta($author_id, '_profile_banner');
    delete_user_meta($author_id, '_profile_banner_id');

    return rest_ensure_response([
        'success'       => true,
        'message'       => __('Banner image removed.', 'gta6-mods'),
        'url'           => '',
        'attachment_id' => 0,
    ]);
}

/**
 * Handles incoming report submissions.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function gta6mods_rest_submit_report($request) {
    $nonce = $request->get_header('X-WP-Nonce');

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', __('Security check failed.', 'gta6-mods'), ['status' => 403]);
    }

    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = [];
    }

    $params['reporter_user_id'] = get_current_user_id();

    if (empty($params['reporter_user_id'])) {
        return new WP_Error('forbidden', __('You must be logged in to report content.', 'gta6-mods'), ['status' => 403]);
    }

    $params['reported_user_id'] = isset($params['reported_user_id']) ? absint($params['reported_user_id']) : 0;

    if ($params['reported_user_id'] && $params['reported_user_id'] === (int) $params['reporter_user_id']) {
        return new WP_Error('cannot_report_self', __('You cannot report your own content.', 'gta6-mods'), ['status' => 400]);
    }

    $params['object_id']   = isset($params['object_id']) ? absint($params['object_id']) : 0;
    $params['object_type'] = isset($params['object_type']) ? sanitize_key($params['object_type']) : '';
    $params['reason']      = isset($params['reason']) ? sanitize_text_field($params['reason']) : 'report';
    $params['details']     = isset($params['details']) ? wp_kses_post($params['details']) : '';

    if ($params['object_id'] <= 0 || '' === $params['object_type']) {
        return new WP_Error('invalid_report', __('Invalid report payload.', 'gta6-mods'), ['status' => 400]);
    }

    if ('status_update' === $params['object_type']) {
        $status_post = get_post($params['object_id']);
        if (!($status_post instanceof WP_Post) || 'status_update' !== $status_post->post_type) {
            return new WP_Error('not_found', __('Status update not found.', 'gta6-mods'), ['status' => 404]);
        }
        if (!$params['reported_user_id']) {
            $params['reported_user_id'] = (int) $status_post->post_author;
        }
    }

    $result = gta6mods_record_report($params);

    if (!$result) {
        return new WP_Error('report_failed', __('Unable to submit the report.', 'gta6-mods'), ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'message' => __('Report submitted. Thank you for helping us keep the community safe.', 'gta6-mods'),
    ]);
}

/**
 * Filters avatar URLs to respect custom uploads or preset selections.
 *
 * @param string          $url         The avatar URL.
 * @param mixed           $id_or_email The user identifier or email.
 * @param array<string,mixed> $args    Avatar arguments.
 *
 * @return string
 */
function gta6mods_filter_custom_avatar_url($url, $id_or_email, $args) {
    if (!empty($args['force_default'])) {
        return $url;
    }

    $user_id = 0;

    if (is_numeric($id_or_email)) {
        $user_id = (int) $id_or_email;
    } elseif ($id_or_email instanceof WP_User) {
        $user_id = (int) $id_or_email->ID;
    } elseif ($id_or_email instanceof WP_Post) {
        $user_id = (int) $id_or_email->post_author;
    } elseif ($id_or_email instanceof WP_Comment) {
        $user_id = (int) $id_or_email->user_id;
    } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
        $user_id = (int) $id_or_email->user_id;
    }

    if ($user_id <= 0 && is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        if ($user instanceof WP_User) {
            $user_id = (int) $user->ID;
        }
    }

    if ($user_id <= 0) {
        return $url;
    }

    $choice = gta6mods_get_user_avatar_choice($user_id);

    if ('custom' === $choice['type'] && $choice['attachmentId'] > 0) {
        $size  = isset($args['size']) ? max(1, (int) $args['size']) : 96;
        $image = image_downsize($choice['attachmentId'], [$size, $size]);

        if (is_array($image) && !empty($image[0])) {
            return $image[0];
        }

        $full = wp_get_attachment_image_src($choice['attachmentId'], 'full');
        if ($full && !empty($full[0])) {
            return $full[0];
        }
    }

    if ('preset' === $choice['type'] && !empty($choice['preset'])) {
        $definition = gta6mods_get_preset_avatar_definition($choice['preset']);
        if ($definition) {
            return $definition['url'];
        }
    }

    return $choice['url'] ?: $url;
}
add_filter('get_avatar_url', 'gta6mods_filter_custom_avatar_url', 20, 3);
