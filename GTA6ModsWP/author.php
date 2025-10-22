<?php
/**
 * Author profile template.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_script('gta6mods-tailwind', 'https://cdn.tailwindcss.com', [], null, false);
wp_enqueue_style('gta6mods-author-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sofia+Sans+Condensed:wght@800&display=swap', [], null);
wp_enqueue_style('gta6mods-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');
wp_enqueue_style('photoswipe', 'https://unpkg.com/photoswipe@5/dist/photoswipe.css', [], '5.4.4');
wp_enqueue_script('photoswipe', 'https://unpkg.com/photoswipe@5/dist/umd/photoswipe.umd.min.js', [], '5.4.4', true);
wp_enqueue_script('photoswipe-lightbox', 'https://unpkg.com/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js', ['photoswipe'], '5.4.4', true);

$user = get_queried_object();

if (!($user instanceof WP_User)) {
    get_header();
    get_template_part('template-parts/content', 'none');
    get_footer();
    return;
}

$author_id       = (int) $user->ID;
$current_user_id = get_current_user_id();

$account_deletion_state = gta6_mods_get_account_deletion_data($author_id);
$is_account_deleted     = is_array($account_deletion_state) && isset($account_deletion_state['status']) && 'deleted' === $account_deletion_state['status'];

if ($is_account_deleted) {
    get_header();
    ?>
    <div class="max-w-5xl mx-auto px-4 py-16 text-center">
        <h1 class="text-3xl font-semibold text-gray-900 mb-4"><?php esc_html_e('This profile is no longer available.', 'gta6-mods'); ?></h1>
        <p class="text-base text-gray-600"><?php esc_html_e('The owner requested permanent deletion, so their public data is hidden.', 'gta6-mods'); ?></p>
    </div>
    <?php
    get_footer();
    return;
}

$display_name     = $user->display_name;
$profile_title    = get_user_meta($author_id, '_profile_title', true);
$profile_title    = $profile_title ? $profile_title : __('Mod Creator', 'gta6-mods');
$member_since     = wp_date(get_option('date_format'), strtotime($user->user_registered));
$last_activity    = get_user_meta($author_id, '_last_activity', true);
$last_activity_ts = $last_activity ? strtotime($last_activity . ' UTC') : false;
$current_time_gmt = current_time('timestamp', true);
$activity_window  = 20 * MINUTE_IN_SECONDS;
$last_active_text = $last_activity_ts ? sprintf(esc_html__('%s ago', 'gta6-mods'), human_time_diff($last_activity_ts, $current_time_gmt)) : esc_html__('No recent activity', 'gta6-mods');
$is_online        = $last_activity_ts ? (($current_time_gmt - $last_activity_ts) < $activity_window) : false;
$profile_views    = (int) get_user_meta($author_id, '_profile_view_count', true);
$avatar_url          = get_avatar_url($author_id, ['size' => 256]);
$default_avatar_url  = gta6mods_get_default_avatar_url($author_id, 256);
$avatar_choice       = gta6mods_get_user_avatar_choice($author_id);
if (empty($avatar_choice['url'])) {
    $avatar_choice['url'] = $avatar_url;
}
if (empty($avatar_choice['defaultUrl'])) {
    $avatar_choice['defaultUrl'] = $default_avatar_url ? $default_avatar_url : $avatar_url;
}
$preset_avatars = array_values(gta6mods_get_preset_avatar_definitions());
$preset_avatar_options = array_map(
    static function ($avatar) {
        return [
            'id'  => $avatar['id'],
            'url' => $avatar['url'],
        ];
    },
    $preset_avatars
);
$avatar_preview_url = $avatar_choice['url'] ? $avatar_choice['url'] : ($avatar_choice['defaultUrl'] ? $avatar_choice['defaultUrl'] : $avatar_url);
$banner_url       = get_user_meta($author_id, '_profile_banner', true);
$bio              = get_user_meta($author_id, 'description', true);
$bio              = $bio ? wp_kses_post($bio) : '';
$bio_plain        = wp_strip_all_tags($bio, true);
$bio_form_value   = wp_html_excerpt($bio_plain, 160, '');
$bio_char_count   = function_exists('mb_strlen') ? mb_strlen($bio_form_value) : strlen($bio_form_value);

$stats = gta6mods_get_author_stats_snapshot($author_id);

$pinned_mod       = gta6mods_get_pinned_mod_for_user($author_id);
$popular_mods     = gta6mods_get_popular_mods_for_user($author_id, 3);
$top_download_mod = gta6mods_get_top_mod_by_meta($author_id, '_gta6mods_download_count');
$top_liked_mod    = gta6mods_get_top_mod_by_meta($author_id, '_gta6mods_likes');
$social_links        = gta6mods_get_user_social_links($author_id);
$social_definitions  = gta6mods_get_social_link_definitions();
$visible_social_links = gta6mods_resolve_social_link_urls($social_links);

$is_owner     = (int) $current_user_id === $author_id;
$is_following = false;
if (!$is_owner && is_user_logged_in()) {
    $is_following = in_array($author_id, (array) get_user_meta(get_current_user_id(), '_following', true), true);
}

$account_delete_phrase   = 'Delete my account';
$can_self_delete_account = $is_owner ? gta6mods_user_can_self_schedule_account_deletion($user) : false;

$deletion_status       = is_array($account_deletion_state) ? $account_deletion_state['status'] : '';
$deletion_requested_at = is_array($account_deletion_state) ? (int) $account_deletion_state['requested_at'] : 0;
$deletion_finalized_at = is_array($account_deletion_state) ? (int) $account_deletion_state['finalized_at'] : 0;

if (!$can_self_delete_account || 'pending' !== $deletion_status || $deletion_requested_at <= 0) {
    if ('pending' !== $deletion_status) {
        $deletion_status      = '';
        $deletion_requested_at = 0;
    }
}

$deletion_requested = $is_owner && $can_self_delete_account && 'pending' === $deletion_status && $deletion_requested_at > 0;

$account_deletion_config = [
    'enabled'        => $can_self_delete_account,
    'requested'      => $can_self_delete_account ? $deletion_requested : false,
    'requestedAt'    => $can_self_delete_account && $deletion_requested ? $deletion_requested_at : 0,
    'phrase'         => $account_delete_phrase,
    'status'         => $deletion_status,
    'finalizedAt'    => ('deleted' === $deletion_status) ? $deletion_finalized_at : 0,
    'finalized'      => ('deleted' === $deletion_status),
    'requestNonce'   => ($can_self_delete_account && 'deleted' !== $deletion_status) ? wp_create_nonce('gta6mods_request_account_deletion') : '',
    'cancelNonce'    => ($can_self_delete_account && $deletion_requested) ? wp_create_nonce('gta6mods_cancel_account_deletion') : '',
    'deleteNowNonce' => ($can_self_delete_account && $deletion_requested) ? wp_create_nonce('gta6mods_delete_account_now') : '',
];

$author_script_path = get_template_directory() . '/assets/js/author-profile.js';
$has_author_script  = file_exists($author_script_path);
if ($has_author_script) {
    wp_enqueue_script(
        'gta6-mods-author-profile',
        get_template_directory_uri() . '/assets/js/author-profile.js',
        [],
        filemtime($author_script_path),
        true
    );
}

$rest_base  = esc_url_raw(rest_url('gta6-mods/v1'));
$rest_nonce = wp_create_nonce('wp_rest');
$tracking_rest_base   = untrailingslashit(rest_url('gta6mods/v1'));
$profile_view_endpoint = esc_url_raw($tracking_rest_base . '/profile/' . $author_id . '/view');
$activity_endpoint     = esc_url_raw($tracking_rest_base . '/user/activity');

$tabs          = gta6mods_get_author_profile_tabs($is_owner);
$requested_tab = get_query_var('gta6mods_profile_tab');
$active_tab    = gta6mods_get_valid_author_profile_tab($requested_tab, $is_owner);
$tab_page      = (int) get_query_var('tab_page');

if ($tab_page <= 0) {
    $tab_page = isset($_GET['tab_page']) ? (int) $_GET['tab_page'] : 1;
}

if ($tab_page <= 0) {
    $tab_page = 1;
}

$tab_urls = [];
foreach ($tabs as $tab_key => $tab_definition) {
    $tab_urls[$tab_key] = gta6mods_get_author_profile_tab_url($user, $tab_key);
}

$tab_endpoints = [];
if (isset($tabs['uploads'])) {
    $tab_endpoints['uploads'] = rest_url('gta6-mods/v1/author/' . $author_id . '/uploads');
}
if (isset($tabs['comments'])) {
    $tab_endpoints['comments'] = rest_url('gta6-mods/v1/author/' . $author_id . '/comments');
}
if (isset($tabs['notifications']) && $is_owner) {
    $tab_endpoints['notifications'] = rest_url('gta6-mods/v1/author/' . $author_id . '/notifications');
}
if (isset($tabs['bookmarks']) && $is_owner) {
    $tab_endpoints['bookmarks'] = rest_url('gta6-mods/v1/author/' . $author_id . '/bookmarks');
}
if (isset($tabs['collections'])) {
    $tab_endpoints['collections'] = rest_url('gta6-mods/v1/author/' . $author_id . '/collections');
}
if (isset($tabs['followers'])) {
    $tab_endpoints['followers'] = rest_url('gta6-mods/v1/author/' . $author_id . '/followers');
}
$tab_endpoints = array_filter($tab_endpoints);

$localized_strings = [
    'loading'                     => esc_html__('Loading…', 'gta6-mods'),
    'error'                       => esc_html__('Something went wrong. Please try again.', 'gta6-mods'),
    'tabError'                    => esc_html__('Unable to load this section.', 'gta6-mods'),
    'notificationsLoading'        => esc_html__('Loading…', 'gta6-mods'),
    'notificationsEmpty'          => esc_html__('You have no notifications yet.', 'gta6-mods'),
    'notificationsLoadError'      => esc_html__('We could not load your notifications. Please try again.', 'gta6-mods'),
    'notificationsMarkError'      => esc_html__('We could not mark your notifications as read. Please try again.', 'gta6-mods'),
    'notificationsMarkAllComplete'=> esc_html__('All notifications marked as read.', 'gta6-mods'),
    'statusEmpty'                 => esc_html__('Status update content cannot be empty.', 'gta6-mods'),
    'statusError'                 => esc_html__('We could not publish your status update. Please try again.', 'gta6-mods'),
    'statusSuccess'               => esc_html__('Status update published.', 'gta6-mods'),
    'avatarTooLarge'              => esc_html__('Please choose an avatar smaller than 1 MB.', 'gta6-mods'),
    'avatarUploadFailed'          => esc_html__('We could not upload your avatar. Please try again.', 'gta6-mods'),
    'avatarDeleteSuccess'         => esc_html__('Avatar deleted.', 'gta6-mods'),
    'avatarDeleteFailed'          => esc_html__('We could not remove your avatar. Please try again.', 'gta6-mods'),
    'bannerUploaded'              => esc_html__('Banner image updated.', 'gta6-mods'),
    'bannerTooLarge'              => esc_html__('Please choose an image smaller than 2 MB.', 'gta6-mods'),
    'bannerRemoveConfirm'         => esc_html__('Remove your banner image?', 'gta6-mods'),
    'bannerRemoved'               => esc_html__('Banner image removed.', 'gta6-mods'),
];

if ($has_author_script) {
    wp_localize_script(
        'gta6-mods-author-profile',
        'GTAModsAuthorProfile',
        [
            'authorId'                 => $author_id,
            'allowProfileViewIncrement' => !$is_owner,
            'profileViewCookie'        => 'gta6mods_profile_viewed_' . $author_id,
            'profileViewThrottle'      => HOUR_IN_SECONDS,
            'viewDelay'                => 1500,
            'isSecure'                 => is_ssl(),
            'shouldTrackActivity'      => is_user_logged_in(),
            'activityCookie'           => 'gta6_activity_throttle',
            'activityThrottle'         => $activity_window,
            'activityWindow'           => $activity_window,
            'isOwnProfile'             => $is_owner,
            'lastActivityTimestamp'    => $last_activity_ts ? (int) $last_activity_ts : 0,
            'lastActivityText'         => $last_active_text,
            'serverNow'                => $current_time_gmt,
            'profileViews'             => $profile_views,
            'profileViewEndpoint'      => $profile_view_endpoint,
            'activityEndpoint'         => $activity_endpoint,
            'tabEndpoints'             => $tab_endpoints,
            'tabUrls'                  => $tab_urls,
            'activeTab'                => $active_tab,
            'initialPage'              => $tab_page,
            'restBase'                 => $rest_base,
            'restNonce'                => $rest_nonce,
            'statusMaxLength'          => 5000,
            'avatarMaxSize'            => MB_IN_BYTES,
            'bannerMaxSize'            => 2 * MB_IN_BYTES,
            'notificationsDropdownLimit' => 5,
            'notificationsRestEnabled' => true,
            'followRestEnabled'   => true,
            'statusRestEnabled'        => true,
            'avatarRestEnabled'        => true,
            'bannerRestEnabled'        => true,
            'avatar'                   => $avatar_choice,
            'bannerUrl'                => $banner_url ? $banner_url : '',
            'presetAvatars'            => $preset_avatar_options,
            'strings'                  => $localized_strings,
            'labels'                   => [
                'online'          => esc_html__('Now online', 'gta6-mods'),
                'offlineFallback' => esc_html__('No recent activity', 'gta6-mods'),
            ],
        ]
    );
}

$tab_links_html = gta6mods_get_author_profile_tab_links_html($tabs, $tab_urls, $active_tab);

$profile_main_card_classes = 'card';
if ('overview' !== $active_tab) {
    $profile_main_card_classes .= ' mt-6';
}

$is_overview_active = ('overview' === $active_tab);

$tab_label     = $tabs[$active_tab]['label'] ?? ucfirst($active_tab);
$canonical_url = gta6mods_get_author_profile_tab_page_url($user, $active_tab, $tab_page);
if ('' === $canonical_url && isset($tab_urls[$active_tab])) {
    $canonical_url = $tab_urls[$active_tab];
}

$site_name  = get_bloginfo('name');
$meta_title = '';
if ('overview' === $active_tab) {
    $meta_title = sprintf(__('%1$s profile | %2$s', 'gta6-mods'), $display_name, $site_name);
} else {
    $meta_title = sprintf(__('%1$s – %2$s | %3$s', 'gta6-mods'), $display_name, $tab_label, $site_name);
}
if ($tab_page > 1) {
    $meta_title = sprintf(__('%1$s – Page %2$d', 'gta6-mods'), $meta_title, $tab_page);
}

$stat_uploads   = isset($stats['uploads']) ? (int) $stats['uploads'] : 0;
$stat_downloads = isset($stats['downloads']) ? (int) $stats['downloads'] : 0;
$stat_likes     = isset($stats['likes']) ? (int) $stats['likes'] : 0;
$stat_followers = isset($stats['followers']) ? (int) $stats['followers'] : 0;

$meta_description_parts = [];
$meta_description_parts[] = sprintf(__('%1$s – %2$s.', 'gta6-mods'), $display_name, $profile_title);
$meta_description_parts[] = sprintf(__('Joined: %s.', 'gta6-mods'), $member_since);
$meta_description_parts[] = sprintf(__('Uploads: %1$s · Downloads: %2$s · Likes: %3$s', 'gta6-mods'), number_format_i18n($stat_uploads), number_format_i18n($stat_downloads), number_format_i18n($stat_likes));
if ($stat_followers > 0) {
    $meta_description_parts[] = sprintf(__('Followers: %s.', 'gta6-mods'), number_format_i18n($stat_followers));
}
if ($bio_form_value) {
    $meta_description_parts[] = $bio_form_value;
}
if ('overview' !== $active_tab) {
    $meta_description_parts[] = sprintf(__('Current tab: %s.', 'gta6-mods'), $tab_label);
}
if ($tab_page > 1) {
    $meta_description_parts[] = sprintf(__('Page %d.', 'gta6-mods'), $tab_page);
}

$meta_description = trim(implode(' ', array_filter($meta_description_parts)));
$meta_description = wp_html_excerpt(wp_strip_all_tags($meta_description, true), 200, '…');

$meta_image   = $banner_url ? $banner_url : $avatar_url;
$twitter_card = $banner_url ? 'summary_large_image' : 'summary';

$schema_description = $bio_form_value ? wp_strip_all_tags($bio_form_value, true) : wp_strip_all_tags($meta_description, true);
$member_since_iso   = $user->user_registered ? gmdate('c', strtotime($user->user_registered . ' UTC')) : '';

$schema_data = [
    '@context'      => 'https://schema.org',
    '@type'         => 'Person',
    'name'          => $display_name,
    'alternateName' => $user->user_nicename,
    'jobTitle'      => $profile_title,
];

if ($canonical_url) {
    $schema_data['url'] = $canonical_url;
}
if ($meta_image) {
    $schema_data['image'] = $meta_image;
}
if ($schema_description) {
    $schema_data['description'] = $schema_description;
}
if ($member_since_iso) {
    $schema_data['memberSince'] = $member_since_iso;
}
if (!empty($visible_social_links)) {
    $schema_data['sameAs'] = array_values($visible_social_links);
}

$schema_interactions = [];
if ($stat_uploads > 0) {
    $schema_interactions[] = [
        '@type'                => 'InteractionCounter',
        'interactionType'      => 'https://schema.org/UploadAction',
        'userInteractionCount' => $stat_uploads,
    ];
}
if ($stat_likes > 0) {
    $schema_interactions[] = [
        '@type'                => 'InteractionCounter',
        'interactionType'      => 'https://schema.org/LikeAction',
        'userInteractionCount' => $stat_likes,
    ];
}
if ($stat_followers > 0) {
    $schema_interactions[] = [
        '@type'                => 'InteractionCounter',
        'interactionType'      => 'https://schema.org/FollowAction',
        'userInteractionCount' => $stat_followers,
    ];
}

if (!empty($schema_interactions)) {
    $schema_data['interactionStatistic'] = $schema_interactions;
}

$schema_json = '';
$schema_encoded = wp_json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (false !== $schema_encoded && null !== $schema_encoded) {
    $schema_json = $schema_encoded;
}

$profile_title_filter = static function ($title) use ($meta_title) {
    return $meta_title ? $meta_title : $title;
};

$profile_head_action = static function () use ($meta_title, $meta_description, $canonical_url, $meta_image, $twitter_card, $user, $schema_json) {
    if ($meta_description) {
        printf('<meta name="description" content="%s" />' . "\n", esc_attr($meta_description));
        printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($meta_description));
        printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($meta_description));
    }

    if ($meta_title) {
        printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($meta_title));
        printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($meta_title));
    }

    if ($canonical_url) {
        printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical_url));
        printf('<meta property="og:url" content="%s" />' . "\n", esc_url($canonical_url));
    }

    echo "<meta property=\"og:type\" content=\"profile\" />\n";
    printf('<meta property="profile:username" content="%s" />' . "\n", esc_attr($user->user_nicename));

    if ($meta_image) {
        printf('<meta property="og:image" content="%s" />' . "\n", esc_url($meta_image));
        printf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($meta_image));
    }

    printf('<meta name="twitter:card" content="%s" />' . "\n", esc_attr($twitter_card));

    if ($schema_json) {
        echo '<script type="application/ld+json">' . $schema_json . '</script>' . "\n";
    }
};

add_filter('pre_get_document_title', $profile_title_filter, 100);
add_action('wp_head', $profile_head_action, 1);

get_header();

?>
<div class="gta6mods-author-profile text-gray-700">
    <style>
        body.author {
            font-family: 'Inter', sans-serif;
            background-color: #F2F2F2;
            color: #374151;
        }
        body.author .brand-font {
            font-family: 'Sofia Sans Condensed', sans-serif;
            font-weight: 800;
        }
<?php if ($banner_url) : ?>
        body.author .header-background {
            --header-bg-image: url('<?php echo esc_url($banner_url); ?>');
        }
<?php endif; ?>
        .gta6mods-author-profile .btn-action {
            background-color: #ec4899;
            color: #ffffff;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px 0 rgba(236, 72, 153, 0.3);
        }
        .gta6mods-author-profile .btn-action:hover {
            background-color: #db2777;
        }
        .gta6mods-author-profile .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            transition: background-color 0.2s ease-in-out;
        }
        .gta6mods-author-profile .btn-secondary:hover {
            background-color: #d1d5db;
        }
        .gta6mods-author-profile .btn-danger {
            background-color: #ef4444;
            color: #ffffff;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.3);
        }
        .gta6mods-author-profile .btn-danger:hover {
            background-color: #dc2626;
        }
        .gta6mods-author-profile .card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb78;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 1%), 0 2px 4px -2px rgb(0 0 0 / 4%);
        }
        .gta6mods-author-profile .author-summary-card {
            overflow: visible;
        }
        .gta6mods-author-profile .profile-tab-btn {
            border-bottom: 3px solid transparent;
            transition: color 0.2s, border-color 0.2s;
            white-space: nowrap;
        }
        .gta6mods-author-profile .profile-tab-btn.active {
            color: #ec4899;
            border-bottom-color: #ec4899;
        }
        .gta6mods-author-profile .settings-tab-btn {
            background-color: transparent;
            color: #4b5563;
            border-radius: 0.5rem;
            transition: background-color 0.2s, color 0.2s;
        }
        .gta6mods-author-profile .settings-tab-btn.active {
            background-color: #fce7f3;
            color: #be185d;
        }
        .gta6mods-author-profile .comment-on-mod {
            font-size: 0.8rem;
            background-color: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            color: #4b5563;
        }
        .gta6mods-author-profile .hidden {
            display: none !important;
        }
        .gta6mods-author-profile .bio-counter {
            margin-top: 0.25rem;
            text-align: right;
            font-size: 0.75rem;
            color: #9ca3af;
        }
        .gta6mods-author-profile .form-input,
        .gta6mods-author-profile .form-textarea,
        .gta6mods-author-profile .form-select {
            border: 1px solid #d1d5db;
        }
        .gta6mods-author-profile .form-input:focus,
        .gta6mods-author-profile .form-textarea:focus,
        .gta6mods-author-profile .form-select:focus {
            border-color: #ec4899;
            box-shadow: 0 0 0 2px #fbcfe8;
            outline: none;
        }
        .gta6mods-author-profile .verification-badge {
            color: #3b82f6;
            cursor: pointer;
        }
        .gta6mods-author-profile .badge-icon {
            transition: transform 0.2s;
        }
        .gta6mods-author-profile .badge-icon:hover {
            transform: scale(1.1);
        }
        .gta6mods-author-profile .gta6mods-banner-preview {
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            border: 1px dashed #d1d5db;
            height: 8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 0.75rem;
            padding: 0 1rem;
            text-align: center;
            background-size: cover;
            background-position: center;
        }
        .gta6mods-author-profile .gta6mods-banner-preview.has-banner {
            border-style: solid;
            color: transparent;
        }
        .gta6mods-author-profile .gta6mods-banner-remove {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(17, 24, 39, 0.75);
            color: #fff;
            border-radius: 9999px;
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(17, 24, 39, 0.35);
        }
        .gta6mods-author-profile .gta6mods-banner-remove.hidden {
            display: none;
        }
        .gta6mods-author-profile .gta6mods-banner-remove:hover {
            background: rgba(236, 72, 153, 0.85);
            transform: translateY(-1px);
        }
        .gta6mods-author-profile .gta6mods-banner-remove:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        .gta6mods-author-profile .gta6mods-banner-empty {
            pointer-events: none;
        }
        .gta6mods-author-profile .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .gta6mods-author-profile .modal {
            transition: opacity 0.3s ease;
        }
        .gta6mods-author-profile #status-update-textarea.is-empty::before {
            content: attr(data-placeholder);
            color: #9ca3af;
            pointer-events: none;
        }
        .gta6mods-author-profile .status-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .gta6mods-author-profile .status-counter {
            font-size: 0.75rem;
            color: #9ca3af;
        }
        .gta6mods-author-profile .input-group {
            display: flex;
            align-items: center;
        }
        .gta6mods-author-profile .input-group .input-group-addon {
            background-color: #f9fafb;
            border: 1px solid #d1d5db;
            padding: 0.5rem 0.75rem;
            border-right: 0;
            border-radius: 0.375rem 0 0 0.375rem;
            color: #6b7280;
            white-space: nowrap;
            font-size: 0.875rem;
        }
        .gta6mods-author-profile .input-group input {
            border-radius: 0 0.375rem 0.375rem 0;
            border: 1px solid #d1d5db;
        }
    </style>
    <main class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-8">
                <div id="profile-tabs-container" class="border-gray-200 px-0">
                    <div class="relative">
                        <div id="tabs-wrapper" class="flex items-center">
                            <nav id="visible-tabs" class="flex -mb-px space-x-4 sm:space-x-6 overflow-hidden px-0" role="tablist" aria-label="<?php esc_attr_e('Profil fülek', 'gta6-mods'); ?>"><?php echo $tab_links_html; ?></nav>
                            <div id="more-tabs-container" class="relative hidden -mb-px">
                                <button id="more-tabs-btn" class="profile-tab-btn py-3 px-4 flex items-center font-semibold text-gray-600 hover:text-pink-600" type="button">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div id="more-tabs-dropdown" class="absolute right-0 top-full mt-1 w-48 bg-white rounded-md shadow-lg z-20 border hidden py-1"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="pinned-mod-section" class="<?php echo $is_overview_active ? '' : 'hidden'; ?>" data-tab-content="overview" aria-hidden="<?php echo $is_overview_active ? 'false' : 'true'; ?>">
                    <?php if ($pinned_mod instanceof WP_Post) :
                        $pinned_thumb = get_the_post_thumbnail_url($pinned_mod, 'large');
                        if (!$pinned_thumb) {
                            $pinned_thumb = apply_filters('gta6mods_mod_placeholder_image', 'https://placehold.co/800x450?text=Mod');
                        }
                        $pinned_rating_data = gta6_mods_get_rating_data($pinned_mod->ID);
                        $pinned_rating      = isset($pinned_rating_data['average']) ? (float) $pinned_rating_data['average'] : 0.0;
                        $pinned_downloads   = gta6_mods_get_download_count($pinned_mod->ID);
                        $pinned_likes       = gta6_mods_get_like_count($pinned_mod->ID);
                        ?>
                        <div class="mb-6 mt-6 card overflow-hidden group relative shadow-lg">
                            <img src="<?php echo esc_url($pinned_thumb); ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo esc_attr(get_the_title($pinned_mod)); ?>">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                            <div class="relative p-6 flex flex-col justify-between h-full min-h-[300px] text-white">
                                <div>
                                    <span class="inline-block bg-pink-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                                        <i class="fas fa-thumbtack mr-2"></i><?php esc_html_e('Pinned mod', 'gta6-mods'); ?>
                                    </span>
                                </div>
                                <div>
                                    <h3 class="brand-font text-4xl font-bold mb-2 tracking-wide" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                                        <?php echo esc_html(get_the_title($pinned_mod)); ?>
                                    </h3>
                                    <div class="flex items-center space-x-6 text-sm font-medium">
                                        <span title="<?php esc_attr_e('Rating', 'gta6-mods'); ?>" class="flex items-center gap-1.5">
                                            <i class="fas fa-star text-yellow-400"></i>
                                            <strong><?php echo esc_html(number_format_i18n($pinned_rating, 2)); ?></strong>
                                        </span>
                                        <span title="<?php esc_attr_e('Downloads', 'gta6-mods'); ?>" class="flex items-center gap-1.5">
                                            <i class="fas fa-download"></i>
                                            <?php echo esc_html(number_format_i18n($pinned_downloads)); ?>
                                        </span>
                                        <span title="<?php esc_attr_e('Likes', 'gta6-mods'); ?>" class="flex items-center gap-1.5">
                                            <i class="fas fa-thumbs-up"></i>
                                            <?php echo esc_html(number_format_i18n($pinned_likes)); ?>
                                        </span>
                                    </div>
                                    <a href="<?php echo esc_url(get_permalink($pinned_mod)); ?>" class="absolute inset-0" aria-label="<?php esc_attr_e('View pinned mod', 'gta6-mods'); ?>"></a>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="mt-6 mb-6 card p-6 text-center">
                            <h3 class="text-xl font-semibold text-gray-800"><?php esc_html_e('No pinned mod yet', 'gta6-mods'); ?></h3>
                            <p class="text-sm text-gray-500 mt-2"><?php esc_html_e('Pin one of your mods to highlight it here.', 'gta6-mods'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="<?php echo esc_attr($profile_main_card_classes); ?>" id="profile-main-card">
                    <div class="p-4 sm:p-6">
                        <div id="all-tabs-source" class="hidden" aria-hidden="true"><?php echo $tab_links_html; ?></div>

                        <div
                            id="overview"
                            class="tab-content<?php echo $is_overview_active ? '' : ' hidden'; ?>"
                            data-tab-content="overview"
                            data-tab-panel="overview"
                            data-loaded="1"
                            data-page="1"
                            role="tabpanel"
                            aria-labelledby="tab-link-overview"
                            aria-hidden="<?php echo $is_overview_active ? 'false' : 'true'; ?>"
                            tabindex="0"
                        >
                            <?php if ($is_owner) :
                                $status_placeholder = sprintf(__('Share an update, %s...', 'gta6-mods'), $display_name);
                                $status_classes     = 'w-full p-3 bg-transparent outline-none min-h-[44px] is-empty';
                                ?>
                                <div id="status-update-wrapper" class="mb-8 group">
                                    <div class="flex items-start gap-4">
                                        <div class="bg-gray-100 text-gray-500 rounded-full h-10 w-10 flex-shrink-0 flex items-center justify-center mt-1 transition-colors duration-300 group-focus-within:bg-purple-100 group-focus-within:text-purple-600">
                                            <i class="fas fa-comment-dots"></i>
                                        </div>
                                        <div class="flex-1">
                                            <div class="border border-gray-200 rounded-lg focus-within:ring-2 focus-within:ring-purple-500 focus-within:border-transparent transition-all bg-white">
                                                <div id="status-update-textarea" class="<?php echo esc_attr($status_classes); ?>" contenteditable="true" data-placeholder="<?php echo esc_attr($status_placeholder); ?>" data-maxlength="5000"></div>
                                                <div id="status-update-actions" class="hidden status-actions px-3 pb-2">
                                                    <span id="status-update-counter" class="status-counter">0/5000</span>
                                                    <button class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-5 rounded-lg text-sm transition shadow-lg shadow-purple-500/30" type="button" id="status-update-submit"><?php esc_html_e('Publish', 'gta6-mods'); ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <h3 class="text-lg font-bold text-gray-800 mb-4 pt-4 border-t"><?php esc_html_e('Recent activity', 'gta6-mods'); ?></h3>
                            <?php get_template_part('template-parts/author/activity-items'); ?>

                            <h3 class="text-lg font-bold text-gray-800 mb-4 pt-6 border-t"><?php esc_html_e('Most popular mods', 'gta6-mods'); ?></h3>
                            <?php if (!empty($popular_mods)) : ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($popular_mods as $popular_mod) :
                                        if (!$popular_mod instanceof WP_Post) {
                                            $popular_mod = get_post($popular_mod);
                                        }

                                        if (!$popular_mod instanceof WP_Post) {
                                            continue;
                                        }

                                        $popular_mod_id = (int) $popular_mod->ID;
                                        $thumb           = get_the_post_thumbnail_url($popular_mod, 'medium');

                                        if (!$thumb) {
                                            $thumb = apply_filters('gta6mods_mod_placeholder_image', 'https://placehold.co/300x160?text=Mod');
                                        }

                                        $rating_data = gta6_mods_get_rating_data($popular_mod_id);
                                        $rating      = isset($rating_data['average']) ? (float) $rating_data['average'] : 0.0;
                                        $downloads   = gta6_mods_get_download_count($popular_mod_id);
                                        $likes       = gta6_mods_get_like_count($popular_mod_id);
                                        $category    = get_the_term_list($popular_mod_id, 'category', '', ', ');
                                        ?>
                                        <article class="card group overflow-hidden border border-transparent hover:border-pink-200 transition">
                                            <a href="<?php echo esc_url(get_permalink($popular_mod)); ?>" class="block">
                                                <div class="relative">
                                                    <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr(get_the_title($popular_mod)); ?>" class="w-full h-32 object-cover transition-transform duration-300 group-hover:scale-105">
                                                    <div class="absolute bottom-0 left-0 right-0 p-1.5 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                                                        <div class="flex justify-between items-center">
                                                            <span class="flex items-center font-semibold">
                                                                <i class="fas fa-star mr-1 text-yellow-400"></i><?php echo esc_html(number_format_i18n($rating, 2)); ?>
                                                            </span>
                                                            <div class="flex items-center space-x-2">
                                                                <span class="flex items-center"><i class="fas fa-download mr-1"></i><?php echo esc_html(number_format_i18n($downloads)); ?></span>
                                                                <span class="flex items-center"><i class="fas fa-thumbs-up mr-1"></i><?php echo esc_html(number_format_i18n($likes)); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                            <div class="p-3">
                                                <a href="<?php echo esc_url(get_permalink($popular_mod)); ?>" class="block">
                                                    <h4 class="font-semibold text-sm text-gray-800 transition truncate group-hover:text-pink-600"><?php echo esc_html(get_the_title($popular_mod)); ?></h4>
                                                </a>
                                                <p class="text-xs text-gray-500 mt-1"><?php echo $category ? wp_kses_post($category) : esc_html__('Uncategorised', 'gta6-mods'); ?></p>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <p class="text-sm text-gray-500"><?php esc_html_e('Publish a few mods to see the top performers here.', 'gta6-mods'); ?></p>
                            <?php endif; ?>
                        </div>
                        <div
                            id="uploads"
                            class="tab-content<?php echo 'uploads' === $active_tab ? '' : ' hidden'; ?>"
                            data-tab-content="uploads"
                            data-tab-panel="uploads"
                            data-loaded="0"
                            data-page="<?php echo esc_attr('uploads' === $active_tab ? $tab_page : 1); ?>"
                            role="tabpanel"
                            aria-labelledby="tab-link-uploads"
                            aria-hidden="<?php echo 'uploads' === $active_tab ? 'false' : 'true'; ?>"
                            tabindex="0"
                        >
                            <div class="text-center py-12 text-sm text-gray-500" data-author-loading>
                                <?php esc_html_e('Loading...', 'gta6-mods'); ?>
                            </div>
                            <div data-author-async-content class="space-y-6"></div>
                        </div>
                        <div
                            id="comments"
                            class="tab-content<?php echo 'comments' === $active_tab ? '' : ' hidden'; ?>"
                            data-tab-content="comments"
                            data-tab-panel="comments"
                            data-loaded="0"
                            data-page="<?php echo esc_attr('comments' === $active_tab ? $tab_page : 1); ?>"
                            role="tabpanel"
                            aria-labelledby="tab-link-comments"
                            aria-hidden="<?php echo 'comments' === $active_tab ? 'false' : 'true'; ?>"
                            tabindex="0"
                        >
                            <div class="text-center py-12 text-sm text-gray-500" data-author-loading>
                                <?php esc_html_e('Loading...', 'gta6-mods'); ?>
                            </div>
                            <div data-author-async-content class="space-y-6"></div>
                        </div>
                        <?php if ($is_owner) : ?>
                            <div
                                id="notifications"
                                class="tab-content<?php echo 'notifications' === $active_tab ? '' : ' hidden'; ?>"
                                data-tab-content="notifications"
                                data-tab-panel="notifications"
                                data-loaded="0"
                                data-page="1"
                                role="tabpanel"
                                aria-labelledby="tab-link-notifications"
                                aria-hidden="<?php echo 'notifications' === $active_tab ? 'false' : 'true'; ?>"
                                tabindex="0"
                            >
                                <div class="text-center py-12 text-sm text-gray-500" data-author-loading>
                                    <?php esc_html_e('Loading...', 'gta6-mods'); ?>
                                </div>
                                <div data-author-async-content class="space-y-6"></div>
                            </div>
                            <div
                                id="bookmarks"
                                class="tab-content<?php echo 'bookmarks' === $active_tab ? '' : ' hidden'; ?>"
                                data-tab-content="bookmarks"
                                data-tab-panel="bookmarks"
                                data-loaded="0"
                                data-page="1"
                                role="tabpanel"
                                aria-labelledby="tab-link-bookmarks"
                                aria-hidden="<?php echo 'bookmarks' === $active_tab ? 'false' : 'true'; ?>"
                                tabindex="0"
                            >
                                <div class="text-center py-12 text-sm text-gray-500" data-author-loading>
                                    <?php esc_html_e('Loading...', 'gta6-mods'); ?>
                                </div>
                                <div data-author-async-content class="space-y-6"></div>
                            </div>
                        <?php endif; ?>
                        <div
                            id="collections"
                            class="tab-content<?php echo 'collections' === $active_tab ? '' : ' hidden'; ?>"
                            data-tab-content="collections"
                            data-tab-panel="collections"
                            data-loaded="0"
                            data-page="1"
                            role="tabpanel"
                            aria-labelledby="tab-link-collections"
                            aria-hidden="<?php echo 'collections' === $active_tab ? 'false' : 'true'; ?>"
                            tabindex="0"
                        >
                            <div class="text-center py-12 text-sm text-gray-500" data-author-loading>
                                <?php esc_html_e('Loading...', 'gta6-mods'); ?>
                            </div>
                            <div data-author-async-content class="space-y-6"></div>
                        </div>
                        <div
                            id="followers"
                            class="tab-content<?php echo 'followers' === $active_tab ? '' : ' hidden'; ?>"
                            data-tab-content="followers"
                            data-tab-panel="followers"
                            data-loaded="0"
                            data-page="1"
                            role="tabpanel"
                            aria-labelledby="tab-link-followers"
                            aria-hidden="<?php echo 'followers' === $active_tab ? 'false' : 'true'; ?>"
                            tabindex="0"
                        >
                            <div class="text-center py-12 text-sm text-gray-500" data-author-loading>
                                <?php esc_html_e('Loading...', 'gta6-mods'); ?>
                            </div>
                            <div data-author-async-content class="space-y-6"></div>
                        </div>

                        <?php if ($is_owner) : ?>
                            <div
                                id="settings"
                                class="tab-content<?php echo 'settings' === $active_tab ? '' : ' hidden'; ?>"
                                data-tab-content="settings"
                                data-tab-panel="settings"
                                data-loaded="1"
                                data-page="1"
                                role="tabpanel"
                                aria-labelledby="tab-link-settings"
                                aria-hidden="<?php echo 'settings' === $active_tab ? 'false' : 'true'; ?>"
                                tabindex="0"
                            >
                                <div class="flex flex-col md:flex-row gap-8 lg:gap-12">
                                    <nav id="settings-tabs-nav" class="flex md:flex-col md:w-1/4" aria-label="<?php esc_attr_e('Settings Tabs', 'gta6-mods'); ?>">
                                        <button data-settings-tab="profile" class="settings-tab-btn font-semibold text-left p-3 flex items-center gap-3 active" type="button">
                                            <i class="fas fa-user-circle fa-fw w-5"></i><span><?php esc_html_e('Profile', 'gta6-mods'); ?></span>
                                        </button>
                                        <button data-settings-tab="accounts" class="settings-tab-btn font-semibold text-left p-3 flex items-center gap-3" type="button">
                                            <i class="fas fa-link fa-fw w-5"></i><span><?php esc_html_e('Accounts', 'gta6-mods'); ?></span>
                                        </button>
                                        <button data-settings-tab="security" class="settings-tab-btn font-semibold text-left p-3 flex items-center gap-3" type="button">
                                            <i class="fas fa-shield-alt fa-fw w-5"></i><span><?php esc_html_e('Security', 'gta6-mods'); ?></span>
                                        </button>
                                    </nav>
                                    <div class="flex-1">
                                        <div id="settings-profile" class="settings-tab-content space-y-12">
                                            <div>
                                                <h3 class="brand-font text-2xl font-bold text-gray-800 mb-1 tracking-wide"><?php esc_html_e('General settings', 'gta6-mods'); ?></h3>
                                                <p class="text-gray-500 mb-6"><?php esc_html_e('Update your basic profile information and branding.', 'gta6-mods'); ?></p>
                                                <div class="space-y-6">
                                                    <div>
                                                        <label for="gta6mods-settings-username" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Username', 'gta6-mods'); ?></label>
                                                        <input type="text" id="gta6mods-settings-username" value="<?php echo esc_attr($user->user_login); ?>" class="w-full p-2 rounded-md form-input bg-gray-100 cursor-not-allowed" disabled>
                                                    </div>
                                                    <div>
                                                        <label for="gta6mods-settings-email" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Email address', 'gta6-mods'); ?></label>
                                                        <input type="email" id="gta6mods-settings-email" value="<?php echo esc_attr($user->user_email); ?>" class="w-full p-2 rounded-md form-input">
                                                    </div>
                                                    <div>
                                                        <label for="gta6mods-settings-bio" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Bio', 'gta6-mods'); ?></label>
                                                        <textarea id="gta6mods-settings-bio" rows="3" maxlength="160" class="w-full p-2 rounded-md form-textarea" placeholder="<?php esc_attr_e('Tell the community about yourself...', 'gta6-mods'); ?>"><?php echo esc_textarea($bio_form_value); ?></textarea>
                                                        <div class="bio-counter"><span id="gta6mods-bio-counter"><?php echo esc_html($bio_char_count); ?>/160</span></div>
                                                    </div>
                                                    <div>
                                                        <label for="gta6mods-settings-banner" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Banner image', 'gta6-mods'); ?></label>
                                                        <div id="gta6mods-banner-preview" class="gta6mods-banner-preview<?php echo $banner_url ? ' has-banner' : ''; ?>" data-empty-text="<?php echo esc_attr__('No banner uploaded yet.', 'gta6-mods'); ?>" <?php if ($banner_url) : ?>style="background-image: url('<?php echo esc_url($banner_url); ?>');"<?php endif; ?>>
                                                            <button type="button" id="gta6mods-remove-banner" class="gta6mods-banner-remove<?php echo $banner_url ? '' : ' hidden'; ?>" aria-label="<?php esc_attr_e('Remove banner', 'gta6-mods'); ?>"<?php if (!$banner_url) : ?> style="display: none;"<?php endif; ?>>
                                                                <span class="sr-only"><?php esc_html_e('Remove banner', 'gta6-mods'); ?></span>
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                            <span class="gta6mods-banner-empty<?php echo $banner_url ? ' hidden' : ''; ?>"><?php esc_html_e('No banner uploaded yet.', 'gta6-mods'); ?></span>
                                                        </div>
                                                        <input type="file" id="gta6mods-settings-banner" accept="image/*" class="mt-3 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                                                        <p class="text-xs text-gray-400 mt-1"><?php esc_html_e('Upload a JPG or PNG up to 2 MB. The image appears at the top of your author page.', 'gta6-mods'); ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                <h3 class="brand-font text-2xl font-bold text-gray-800 mb-1 tracking-wide"><?php esc_html_e('Profile avatar', 'gta6-mods'); ?></h3>
                                                <p class="text-gray-500 mb-6"><?php esc_html_e('Upload a custom avatar or choose one of the presets below.', 'gta6-mods'); ?></p>
                                                <div class="space-y-4">
                                                    <div>
                                                        <label for="gta6mods-settings-avatar" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Upload avatar', 'gta6-mods'); ?></label>
                                                        <div class="flex items-center gap-4">
                                                            <div class="relative w-24 h-24">
                                                                <img id="gta6mods-avatar-preview" src="<?php echo esc_url($avatar_preview_url); ?>" alt="<?php esc_attr_e('Avatar preview', 'gta6-mods'); ?>" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md bg-gray-100">
                                                            </div>
                                                            <div class="flex-1">
                                                                <input type="file" id="gta6mods-settings-avatar" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                                                                <p class="text-xs text-gray-400 mt-1"><?php esc_html_e('Upload a JPG or PNG up to 1 MB. Images are stored in your media library.', 'gta6-mods'); ?></p>
                                                                <div class="flex items-center gap-2 mt-3">
                                                                    <button type="button" id="gta6mods-delete-avatar" class="hidden inline-flex items-center gap-2 text-sm font-semibold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition" aria-hidden="true">
                                                                        <i class="fas fa-trash-can"></i>
                                                                        <span><?php esc_html_e('Delete image', 'gta6-mods'); ?></span>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Preset avatars', 'gta6-mods'); ?></p>
                                                        <div id="avatar-selection-grid" class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-3">
                                                            <?php if (!empty($preset_avatars)) : ?>
                                                                <?php foreach ($preset_avatars as $preset_avatar) : ?>
                                                                    <button type="button" class="preset-avatar relative rounded-full overflow-hidden aspect-square border-2 border-transparent transition hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2" data-avatar-id="<?php echo esc_attr($preset_avatar['id']); ?>" data-avatar-url="<?php echo esc_url($preset_avatar['url']); ?>" aria-pressed="false" title="<?php esc_attr_e('Select avatar', 'gta6-mods'); ?>">
                                                                        <span class="sr-only"><?php esc_html_e('Select this avatar', 'gta6-mods'); ?></span>
                                                                        <img src="<?php echo esc_url($preset_avatar['url']); ?>" alt="" class="w-full h-full object-cover">
                                                                    </button>
                                                                <?php endforeach; ?>
                                                            <?php else : ?>
                                                                <p class="text-sm text-gray-500 col-span-full"><?php esc_html_e('Preset avatars are not available right now.', 'gta6-mods'); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="pt-8 border-t">
                                                <button class="btn-action font-semibold py-3 px-8 rounded-lg text-base transition" type="button" id="gta6mods-save-profile"><?php esc_html_e('Save changes', 'gta6-mods'); ?></button>
                                            </div>
                                        </div>
                                        <div id="settings-accounts" class="settings-tab-content hidden space-y-12">
                                            <div>
                                                <h3 class="brand-font text-2xl font-bold text-gray-800 mb-1 tracking-wide"><?php esc_html_e('Social accounts', 'gta6-mods'); ?></h3>
                                                <p class="text-gray-500 mb-6"><?php esc_html_e('Share the places where people can follow your work.', 'gta6-mods'); ?></p>
                                                <div class="grid grid-cols-1 gap-y-4 text-sm max-w-xl" id="gta6mods-social-links">
                                                    <?php
                                                    foreach ($social_definitions as $key => $definition) :
                                                        $value  = $social_links[$key] ?? '';
                                                        $prefix = $definition['prefix'] ?? '';
                                                        ?>
                                                        <div class="input-group">
                                                            <span class="input-group-addon flex items-center">
                                                                <i class="<?php echo esc_attr($definition['icon']); ?> fa-fw w-4 text-center mr-2"></i>
                                                                <?php if (!empty($prefix)) : ?>
                                                                    <span class="social-prefix"><?php echo esc_html($prefix); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                            <input type="text" class="w-full p-2 form-input" data-link-key="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="pt-8 border-t">
                                                <button class="btn-action font-semibold py-3 px-8 rounded-lg text-base transition" type="button" id="gta6mods-save-links"><?php esc_html_e('Save links', 'gta6-mods'); ?></button>
                                            </div>
                                        </div>
                                        <div id="settings-security" class="settings-tab-content hidden space-y-12">
                                            <div>
                                                <h3 class="brand-font text-2xl font-bold text-gray-800 mb-1 tracking-wide"><?php esc_html_e('Change password', 'gta6-mods'); ?></h3>
                                                <p class="text-gray-500 mb-6"><?php esc_html_e('Make sure your account is protected with a strong password.', 'gta6-mods'); ?></p>
                                                <form id="gta6mods-password-form" class="space-y-5 max-w-md" novalidate>
                                                    <div>
                                                        <label for="gta6mods-current-password" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Current password', 'gta6-mods'); ?></label>
                                                        <input type="password" id="gta6mods-current-password" class="w-full p-2 rounded-md form-input" autocomplete="current-password">
                                                    </div>
                                                    <div>
                                                        <label for="gta6mods-new-password" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('New password', 'gta6-mods'); ?></label>
                                                        <input type="password" id="gta6mods-new-password" class="w-full p-2 rounded-md form-input" autocomplete="new-password" aria-describedby="gta6mods-password-help gta6mods-password-strength-text">
                                                        <p id="gta6mods-password-help" class="text-xs text-gray-500 mt-2"><?php esc_html_e('Use at least 12 characters including uppercase, lowercase, numbers and symbols.', 'gta6-mods'); ?></p>
                                                        <div class="mt-3" aria-live="polite">
                                                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                                                <div id="gta6mods-password-strength-bar" class="h-2 w-0 bg-red-500 transition-all duration-300 ease-out"></div>
                                                            </div>
                                                            <p id="gta6mods-password-strength-text" class="text-xs text-gray-500 mt-2"></p>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label for="gta6mods-confirm-password" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Confirm new password', 'gta6-mods'); ?></label>
                                                        <input type="password" id="gta6mods-confirm-password" class="w-full p-2 rounded-md form-input" autocomplete="new-password">
                                                    </div>
                                                    <?php wp_nonce_field('gta6mods_change_password', 'gta6mods_password_nonce'); ?>
                                                    <div class="pt-2">
                                                        <button class="btn-secondary font-semibold py-2 px-5 rounded-lg text-sm transition inline-flex items-center gap-2" type="button" id="gta6mods-save-password">
                                                            <span class="hidden" data-password-saving-spinner aria-hidden="true"><i class="fas fa-circle-notch fa-spin"></i></span>
                                                            <span data-password-saving-label><?php esc_html_e('Save password', 'gta6-mods'); ?></span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                            <?php if ($can_self_delete_account) : ?>
                                                <div class="bg-red-50 border-l-4 border-red-400 p-6 rounded-r-lg" id="gta6mods-danger-zone">
                                                    <h3 class="brand-font text-2xl font-bold text-red-800 mb-1 tracking-wide"><?php esc_html_e('Danger zone', 'gta6-mods'); ?></h3>
                                                    <div data-danger-view="idle" class="<?php echo $deletion_requested ? 'hidden' : ''; ?>">
                                                        <p class="text-red-700 mb-4"><?php esc_html_e('Requesting account deletion will alert our moderators to review your profile.', 'gta6-mods'); ?></p>
                                                        <p class="text-sm text-red-600 mb-4"><?php
                                                            printf(
                                                                esc_html__('To confirm, please type "%s" in the confirmation dialog.', 'gta6-mods'),
                                                                esc_html($account_delete_phrase)
                                                            );
                                                        ?></p>
                                                        <button class="btn-danger font-semibold py-2 px-5 rounded-lg text-sm transition inline-flex items-center justify-center gap-2" type="button" id="gta6mods-delete-account-button">
                                                            <i class="fas fa-user-slash"></i>
                                                            <span><?php echo esc_html($account_delete_phrase); ?></span>
                                                        </button>
                                                    </div>
                                                    <div data-danger-view="scheduled" class="<?php echo $deletion_requested ? '' : 'hidden'; ?>">
                                                        <p class="text-red-700 mb-4"><?php esc_html_e('We received your deletion request. A moderator will review it shortly.', 'gta6-mods'); ?></p>
                                                        <p class="text-sm text-red-600 mb-4"><?php esc_html_e('You can continue using your account while we review the request. Cancel it or delete your account immediately below.', 'gta6-mods'); ?></p>
                                                        <div class="flex flex-col sm:flex-row gap-3">
                                                            <button class="bg-white border border-red-400 text-red-700 hover:bg-red-100 font-semibold py-2 px-5 rounded-lg text-sm transition inline-flex items-center justify-center gap-2" type="button" id="gta6mods-cancel-delete-button">
                                                                <span class="hidden" data-cancel-spinner aria-hidden="true"><i class="fas fa-circle-notch fa-spin"></i></span>
                                                                <span data-cancel-label><?php esc_html_e('Cancel deletion request', 'gta6-mods'); ?></span>
                                                            </button>
                                                            <button class="btn-danger font-semibold py-2 px-5 rounded-lg text-sm transition inline-flex items-center justify-center gap-2" type="button" id="gta6mods-delete-now-button">
                                                                <i class="fas fa-bolt"></i>
                                                                <span data-delete-now-label><?php esc_html_e('Delete immediately', 'gta6-mods'); ?></span>
                                                            </button>
                                                        </div>
                                                        <p class="text-xs text-red-600 mt-4"><?php esc_html_e('A moderator will deactivate your account after the review unless you cancel the request.', 'gta6-mods'); ?></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="collection-detail-view" class="hidden"></div>
            </div>
            <aside class="lg:col-span-4 lg:mt-10">
                <div class="sticky top-6 space-y-6">
                    <div class="card p-6 text-center author-summary-card">
                        <img
                            src="<?php echo esc_url($avatar_url); ?>"
                            alt="<?php echo esc_attr($display_name); ?>"
                            class="w-32 h-32 rounded-full mx-auto border-4 border-white shadow-lg -mt-20 object-cover"
                            data-author-primary-avatar
                            data-default-avatar="<?php echo esc_url($avatar_choice['defaultUrl'] ?? $default_avatar_url ?? $avatar_url); ?>"
                        >
                        <h2 class="brand-font text-3xl font-bold mt-4 inline-flex items-center gap-2 tracking-wide">
                            <?php echo esc_html($display_name); ?>
                            <i class="fas fa-check-circle verification-badge text-xl" title="<?php esc_attr_e('Verified creator', 'gta6-mods'); ?>"></i>
                        </h2>
                        <?php if (!empty($visible_social_links)) : ?>
                            <div class="mt-2 flex flex-wrap justify-center gap-2 text-pink-600" aria-label="<?php esc_attr_e('Social links', 'gta6-mods'); ?>">
                                <?php foreach ($visible_social_links as $network_key => $network_url) :
                                    $definition = $social_definitions[$network_key];
                                    $icon_class = $definition['icon'] ?? 'fas fa-link';
                                    $label      = $definition['label'] ?? ucfirst($network_key);
                                    if ('website' === $network_key) {
                                        $title = sprintf(esc_html__("Visit %s's website", 'gta6-mods'), $display_name);
                                    } else {
                                        $title = sprintf(esc_html__('Visit %1$s on %2$s', 'gta6-mods'), $display_name, $label);
                                    }
                                    ?>
                                    <a href="<?php echo esc_url($network_url, ['http', 'https', 'mailto', 'skype']); ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-pink-100 hover:text-pink-600" target="_blank" rel="nofollow noopener" title="<?php echo esc_attr($title); ?>" aria-label="<?php echo esc_attr($title); ?>">
                                        <i class="<?php echo esc_attr($icon_class); ?>"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="text-sm text-pink-600 font-semibold"><?php echo esc_html($profile_title); ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-500 mt-2"><?php printf(esc_html__('%s member', 'gta6-mods'), esc_html($member_since)); ?></p>
                        <p class="text-sm text-gray-500 mt-1 flex items-center justify-center gap-2" data-author-activity data-state="<?php echo esc_attr($is_online ? 'online' : 'offline'); ?>">
                            <span data-author-activity-label class="<?php echo $is_online ? 'inline-flex items-center gap-2 text-green-600 font-semibold' : ''; ?>">
                                <?php if ($is_online) : ?>
                                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500" aria-hidden="true"></span>
                                    <span><?php esc_html_e('Now online', 'gta6-mods'); ?></span>
                                <?php else : ?>
                                    <?php echo esc_html($last_active_text); ?>
                                <?php endif; ?>
                            </span>
                        </p>
                        <p class="text-sm text-gray-500 mt-1" data-author-profile-views>
                            <?php
                            $profile_views_text = sprintf(
                                wp_kses(
                                    __('%s profile views', 'gta6-mods'),
                                    ['span' => ['data-author-profile-views-count' => true]]
                                ),
                                '<span data-author-profile-views-count>' . esc_html(number_format_i18n($profile_views)) . '</span>'
                            );
                            echo $profile_views_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </p>
                        <?php if ($bio) : ?>
                            <div class="mt-4 text-sm text-gray-600 prose prose-sm mx-auto">
                                <?php echo wp_kses_post(wpautop($bio)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-4 flex items-center gap-2 relative">
                            <?php if ($is_owner) : ?>
                                <a href="<?php echo esc_url($tab_urls['settings'] ?? '#'); ?>" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-3 rounded-lg text-sm transition text-center" data-action="open-settings-tab" data-tab-key="settings">
                                    <i class="fas fa-cog pr-2"></i><?php esc_html_e('Settings', 'gta6-mods'); ?>
                                </a>
                                <button id="share-button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-3 rounded-lg text-sm transition" type="button">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            <?php else : ?>
                                <button class="bg-pink-600 hover:bg-pink-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition flex-grow flex items-center justify-center gap-2" type="button" id="gta6mods-follow-btn" data-following="<?php echo esc_attr($is_following ? '1' : '0'); ?>">
                                    <i class="fas fa-user-plus"></i>
                                    <span class="follow-label"><?php echo esc_html($is_following ? __('Following', 'gta6-mods') : __('Follow', 'gta6-mods')); ?></span>
                                </button>
                                <button id="share-button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-3 rounded-lg text-sm transition" type="button">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card p-4">
                        <h3 class="font-bold text-gray-800 mb-3"><?php esc_html_e('Statistics', 'gta6-mods'); ?></h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                            <div class="text-center"><p class="font-bold text-xl text-pink-600"><?php echo esc_html(number_format_i18n($stats['uploads'])); ?></p><p class="text-gray-500"><?php esc_html_e('Uploads', 'gta6-mods'); ?></p></div>
                            <div class="text-center"><p class="font-bold text-xl text-pink-600"><?php echo esc_html(number_format_i18n($stats['downloads'])); ?></p><p class="text-gray-500"><?php esc_html_e('Downloads', 'gta6-mods'); ?></p></div>
                            <div class="text-center"><p class="font-bold text-xl text-pink-600"><?php echo esc_html(number_format_i18n($stats['likes'])); ?></p><p class="text-gray-500"><?php esc_html_e('Likes', 'gta6-mods'); ?></p></div>
                            <div class="text-center"><p class="font-bold text-xl text-pink-600"><?php echo esc_html(number_format_i18n($stats['comments'])); ?></p><p class="text-gray-500"><?php esc_html_e('Comments', 'gta6-mods'); ?></p></div>
                            <div class="text-center"><p class="font-bold text-xl text-pink-600" id="gta6mods-followers-count"><?php echo esc_html(number_format_i18n($stats['followers'])); ?></p><p class="text-gray-500"><?php esc_html_e('Followers', 'gta6-mods'); ?></p></div>
                            <div class="text-center"><p class="font-bold text-xl text-pink-600"><?php echo esc_html(number_format_i18n($stats['videos'])); ?></p><p class="text-gray-500"><?php esc_html_e('Videos', 'gta6-mods'); ?></p></div>
                        </div>
                        <div class="border-t border-gray-200 mt-4 pt-4 space-y-4">
                            <?php if ($top_download_mod instanceof WP_Post) :
                                $download_thumb = get_the_post_thumbnail_url($top_download_mod, 'thumbnail');
                                if (!$download_thumb) {
                                    $download_thumb = apply_filters('gta6mods_mod_placeholder_image', 'https://placehold.co/64x36?text=Mod');
                                }
                                $download_total = gta6_mods_get_download_count($top_download_mod->ID);
                                ?>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-2"><?php esc_html_e('Top download', 'gta6-mods'); ?></h4>
                                    <a href="<?php echo esc_url(get_permalink($top_download_mod)); ?>" class="flex items-center gap-3 group">
                                        <img src="<?php echo esc_url($download_thumb); ?>" alt="<?php echo esc_attr(get_the_title($top_download_mod)); ?>" class="w-16 h-9 object-cover rounded flex-shrink-0">
                                        <div>
                                            <p class="font-semibold text-sm text-gray-800 group-hover:text-pink-600 transition truncate"><?php echo esc_html(get_the_title($top_download_mod)); ?></p>
                                            <p class="text-sm text-gray-500"><?php printf(esc_html__('%s downloads', 'gta6-mods'), esc_html(number_format_i18n($download_total))); ?></p>
                                        </div>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($top_liked_mod instanceof WP_Post) :
                                $like_thumb = get_the_post_thumbnail_url($top_liked_mod, 'thumbnail');
                                if (!$like_thumb) {
                                    $like_thumb = apply_filters('gta6mods_mod_placeholder_image', 'https://placehold.co/64x36?text=Mod');
                                }
                                $like_total = gta6_mods_get_like_count($top_liked_mod->ID);
                                ?>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-2"><?php esc_html_e('Most liked', 'gta6-mods'); ?></h4>
                                    <a href="<?php echo esc_url(get_permalink($top_liked_mod)); ?>" class="flex items-center gap-3 group">
                                        <img src="<?php echo esc_url($like_thumb); ?>" alt="<?php echo esc_attr(get_the_title($top_liked_mod)); ?>" class="w-16 h-9 object-cover rounded flex-shrink-0">
                                        <div>
                                            <p class="font-semibold text-sm text-gray-800 group-hover:text-pink-600 transition truncate"><?php echo esc_html(get_the_title($top_liked_mod)); ?></p>
                                            <p class="text-sm text-gray-500"><?php printf(esc_html__('%s likes', 'gta6-mods'), esc_html(number_format_i18n($like_total))); ?></p>
                                        </div>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if (!($top_download_mod instanceof WP_Post) && !($top_liked_mod instanceof WP_Post)) : ?>
                                <p class="text-sm text-gray-500"><?php esc_html_e('Upload a few mods to unlock your personal highlights.', 'gta6-mods'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card p-4">
                        <h3 class="font-bold text-gray-800 mb-3"><?php esc_html_e('Achievements', 'gta6-mods'); ?></h3>
                        <div class="flex flex-wrap gap-4 items-center justify-center">
                            <i class="fas fa-medal text-4xl text-yellow-500 badge-icon" title="<?php esc_attr_e('Mod of the week', 'gta6-mods'); ?>"></i>
                            <i class="fas fa-star text-4xl text-amber-600 badge-icon" title="<?php esc_attr_e('Veteran creator', 'gta6-mods'); ?>"></i>
                            <i class="fas fa-download text-4xl text-green-500 badge-icon" title="<?php esc_attr_e('One million downloads', 'gta6-mods'); ?>"></i>
                            <i class="fas fa-fire text-4xl text-red-500 badge-icon" title="<?php esc_attr_e('Trending mod', 'gta6-mods'); ?>"></i>
                            <i class="fas fa-handshake text-4xl text-blue-500 badge-icon" title="<?php esc_attr_e('Helpful community member', 'gta6-mods'); ?>"></i>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>

</div>

<div id="gta6mods-password-confirm-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="gta6mods-password-confirm-title" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="absolute inset-0 bg-gray-900/60" data-password-confirm-overlay></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-auto p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 id="gta6mods-password-confirm-title" class="text-lg font-semibold text-gray-900"><?php esc_html_e('Confirm password change', 'gta6-mods'); ?></h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none" data-password-confirm-cancel aria-label="<?php esc_attr_e('Close dialog', 'gta6-mods'); ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-sm text-gray-600"><?php esc_html_e('Are you sure you want to update your password now?', 'gta6-mods'); ?></p>
            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400" data-password-confirm-cancel>
                    <?php esc_html_e('Cancel', 'gta6-mods'); ?>
                </button>
                <button type="button" class="px-4 py-2 text-sm font-semibold text-white bg-pink-600 rounded-lg hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500 inline-flex items-center gap-2" id="gta6mods-password-confirm">
                    <span class="hidden" data-password-confirm-spinner aria-hidden="true"><i class="fas fa-circle-notch fa-spin"></i></span>
                    <span data-password-confirm-label><?php esc_html_e('Change password', 'gta6-mods'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($can_self_delete_account) : ?>
<div id="gta6mods-delete-account-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="gta6mods-delete-account-title" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="absolute inset-0 bg-gray-900/60" data-delete-overlay></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-auto p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 id="gta6mods-delete-account-title" class="text-lg font-semibold text-gray-900"><?php esc_html_e('Request account deletion', 'gta6-mods'); ?></h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none" data-delete-cancel aria-label="<?php esc_attr_e('Close dialog', 'gta6-mods'); ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-sm text-gray-600"><?php esc_html_e('Submitting this request notifies our moderators. You can keep using your account until a moderator deactivates it.', 'gta6-mods'); ?></p>
            <div>
                <label for="gta6mods-delete-account-confirm" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Type the confirmation phrase to continue', 'gta6-mods'); ?></label>
                <input type="text" id="gta6mods-delete-account-confirm" class="w-full p-2 rounded-md form-input" placeholder="<?php echo esc_attr($account_delete_phrase); ?>" autocomplete="off" data-delete-confirm-input>
                <p class="mt-2 text-xs text-red-600 hidden" data-delete-error><?php esc_html_e('The confirmation phrase does not match.', 'gta6-mods'); ?></p>
            </div>
            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400" data-delete-cancel>
                    <?php esc_html_e('Cancel', 'gta6-mods'); ?>
                </button>
                <button type="button" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 inline-flex items-center gap-2" data-delete-confirm>
                    <span class="hidden" data-delete-spinner aria-hidden="true"><i class="fas fa-circle-notch fa-spin"></i></span>
                    <span data-delete-label><?php esc_html_e('Schedule deletion', 'gta6-mods'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<div id="gta6mods-delete-account-now-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="gta6mods-delete-account-now-title" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="absolute inset-0 bg-gray-900/60" data-delete-now-overlay></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-auto p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 id="gta6mods-delete-account-now-title" class="text-lg font-semibold text-gray-900"><?php esc_html_e('Delete account immediately', 'gta6-mods'); ?></h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none" data-delete-now-cancel aria-label="<?php esc_attr_e('Close dialog', 'gta6-mods'); ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-sm text-gray-600"><?php esc_html_e('This will permanently delete your account and all of its content right away.', 'gta6-mods'); ?></p>
            <div>
                <label for="gta6mods-delete-account-password" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Enter your password to confirm', 'gta6-mods'); ?></label>
                <input type="password" id="gta6mods-delete-account-password" class="w-full p-2 rounded-md form-input" autocomplete="current-password" data-delete-now-password>
                <p class="mt-2 text-xs text-red-600 hidden" data-delete-now-error><?php esc_html_e('Please enter your password.', 'gta6-mods'); ?></p>
            </div>
            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400" data-delete-now-cancel>
                    <?php esc_html_e('Cancel', 'gta6-mods'); ?>
                </button>
                <button type="button" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 inline-flex items-center gap-2" data-delete-now-confirm>
                    <span class="hidden" data-delete-now-spinner aria-hidden="true"><i class="fas fa-circle-notch fa-spin"></i></span>
                    <span data-delete-now-label><?php esc_html_e('Delete account now', 'gta6-mods'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="gta6mods-delete-avatar-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="gta6mods-delete-avatar-title" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="absolute inset-0 bg-gray-900/60" data-avatar-delete-overlay></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-auto p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 id="gta6mods-delete-avatar-title" class="text-lg font-semibold text-gray-900"><?php esc_html_e('Delete avatar', 'gta6-mods'); ?></h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none" data-avatar-delete-cancel aria-label="<?php esc_attr_e('Close dialog', 'gta6-mods'); ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-sm text-gray-600"><?php esc_html_e('Are you sure you want to delete your uploaded avatar? This action cannot be undone.', 'gta6-mods'); ?></p>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400" data-avatar-delete-cancel>
                    <?php esc_html_e('Cancel', 'gta6-mods'); ?>
                </button>
                <button type="button" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 inline-flex items-center gap-2" id="gta6mods-delete-avatar-confirm">
                    <i class="fas fa-trash"></i>
                    <span><?php esc_html_e('Delete image', 'gta6-mods'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<div id="gta6mods-toast-root" class="fixed inset-x-4 top-4 z-50 flex flex-col gap-3 pointer-events-none sm:inset-auto sm:right-6 sm:top-6 sm:w-full sm:max-w-sm sm:items-end" aria-live="polite" aria-atomic="true"></div>

<script type="module">
const gta6AuthorConfig = <?php echo wp_json_encode([
    'restBase'        => $rest_base,
    'ajaxUrl'         => admin_url('admin-ajax.php'),
    'authorId'        => $author_id,
    'nonce'           => $rest_nonce,
    'isOwner'         => $is_owner,
    'bannerUrl'       => $banner_url ? $banner_url : '',
    'avatar'          => $avatar_choice,
    'presetAvatars'   => $preset_avatar_options,
    'avatarMaxSize'   => MB_IN_BYTES,
    'maxBannerSize'   => 2 * MB_IN_BYTES,
    'statusMaxLength' => 5000,
    'activeTab'       => $active_tab,
    'initialPage'     => $tab_page,
    'tabUrls'         => $tab_urls,
    'tabEndpoints'    => $tab_endpoints,
    'baseUrl'         => $tab_urls['overview'] ?? gta6mods_get_author_profile_tab_url($user, 'overview'),
    'notificationDuration' => 6000,
    'passwordNonce'   => wp_create_nonce('gta6mods_change_password'),
    'passwordRateLimit' => 5,
    'accountDeletion' => $account_deletion_config,
    'strings'         => [
        'loading'            => __('Loading…', 'gta6-mods'),
        'error'              => __('Something went wrong. Please try again.', 'gta6-mods'),
        'saved'              => __('Profile settings saved.', 'gta6-mods'),
        'follow'             => __('Follow', 'gta6-mods'),
        'following'          => __('Following', 'gta6-mods'),
        'statusEmpty'        => __('Status update content cannot be empty.', 'gta6-mods'),
        'reportConfirm'      => __('Report this status update?', 'gta6-mods'),
        'reportSuccess'      => __('Thank you. Our moderators will review this update shortly.', 'gta6-mods'),
        'reported'           => __('Reported', 'gta6-mods'),
        'deleteConfirm'      => __('Delete this status update?', 'gta6-mods'),
        'deleteSuccess'      => __('Status update removed.', 'gta6-mods'),
        'bannerUploaded'     => __('Banner image updated.', 'gta6-mods'),
        'bannerTooLarge'     => __('Please choose an image smaller than 2 MB.', 'gta6-mods'),
        'bannerRemoveConfirm'=> __('Remove your banner image?', 'gta6-mods'),
        'bannerRemoved'      => __('Banner image removed.', 'gta6-mods'),
        'avatarTooLarge'     => __('Please choose an avatar smaller than 1 MB.', 'gta6-mods'),
        'avatarUploadFailed' => __('We could not upload your avatar. Please try again.', 'gta6-mods'),
        'avatarDeleteSuccess'=> __('Avatar deleted.', 'gta6-mods'),
        'avatarDeleteFailed' => __('We could not remove your avatar. Please try again.', 'gta6-mods'),
        'loadMoreActivity'   => __('Load more activity', 'gta6-mods'),
        'noActivity'         => __('No activity has been recorded yet.', 'gta6-mods'),
        'tabError'           => __('Unable to load this section.', 'gta6-mods'),
        'notificationDismiss'=> __('Dismiss notification', 'gta6-mods'),
        'noNotifications' => __('You have no notifications yet.', 'gta6-mods'),
        'notificationsLoadError' => __('We could not load your notifications. Please try again.', 'gta6-mods'),
        'notificationsMarkError' => __('We could not mark your notifications as read. Please try again.', 'gta6-mods'),
        'markAllComplete'     => __('All notifications marked as read.', 'gta6-mods'),
        'passwordFieldsRequired' => __('Please fill in all password fields.', 'gta6-mods'),
        'passwordMismatch'       => __('The new passwords do not match.', 'gta6-mods'),
        'passwordWeak'           => __('Your new password does not meet the strength requirements.', 'gta6-mods'),
        'passwordSameAsCurrent'  => __('The new password must be different from your current password.', 'gta6-mods'),
        'passwordChangeSuccess'  => __('Password updated successfully.', 'gta6-mods'),
        'passwordStrengthVeryWeak' => __('Very weak', 'gta6-mods'),
        'passwordStrengthWeak'      => __('Weak', 'gta6-mods'),
        'passwordStrengthMedium'    => __('Medium', 'gta6-mods'),
        'passwordStrengthStrong'    => __('Strong', 'gta6-mods'),
        'passwordStrengthVeryStrong'=> __('Very strong', 'gta6-mods'),
        'deletionConfirmationRequired' => __('Please type "Delete my account" to confirm.', 'gta6-mods'),
        'deletionConfirmationMismatch' => __('The confirmation phrase does not match.', 'gta6-mods'),
        'deletionRequestSuccess'       => __('Account deletion request received. A moderator will review it soon.', 'gta6-mods'),
        'deletionCancelSuccess'        => __('Account deletion request cancelled.', 'gta6-mods'),
        'deletionPasswordRequired'     => __('Please enter your password to continue.', 'gta6-mods'),
        'deletionImmediateSuccess'     => __('Your account has been deleted. You will be signed out.', 'gta6-mods'),
        'deletionImmediateCountdownTemplate' => __('Your account has been deleted. You will be signed out in %s.', 'gta6-mods'),
        'deletionImmediateCountdownUnitSingular' => __('%s second', 'gta6-mods'),
        'deletionImmediateCountdownUnitPlural' => __('%s seconds', 'gta6-mods'),
    ],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

let cachedRestProfileConfig = null;

function resolveRestProfileConfig() {
    if (cachedRestProfileConfig && typeof cachedRestProfileConfig === 'object') {
        return cachedRestProfileConfig;
    }

    if (typeof window !== 'undefined'
        && typeof window.GTAModsAuthorProfile === 'object'
        && window.GTAModsAuthorProfile !== null) {
        cachedRestProfileConfig = window.GTAModsAuthorProfile;
        return cachedRestProfileConfig;
    }

    return null;
}

function isRestFeatureEnabled(flag) {
    const config = resolveRestProfileConfig();
    return Boolean(config && config[flag]);
}

function isRestNotificationsEnabled() {
    return isRestFeatureEnabled('notificationsRestEnabled');
}

function isRestStatusEnabled() {
    return isRestFeatureEnabled('statusRestEnabled');
}

function isRestFollowEnabled() {
    return isRestFeatureEnabled('followRestEnabled');
}

function isRestAvatarEnabled() {
    return isRestFeatureEnabled('avatarRestEnabled');
}

function isRestBannerEnabled() {
    return isRestFeatureEnabled('bannerRestEnabled');
}
const accountDeletionEndpoints = (gta6AuthorConfig.accountDeletionEndpoints && typeof gta6AuthorConfig.accountDeletionEndpoints === 'object')
    ? gta6AuthorConfig.accountDeletionEndpoints
    : {};

const tabSources = document.getElementById('all-tabs-source');
const visibleTabs = document.getElementById('visible-tabs');
const moreTabsContainer = document.getElementById('more-tabs-container');
const moreTabsBtn = document.getElementById('more-tabs-btn');
const moreTabsDropdown = document.getElementById('more-tabs-dropdown');
const notificationsContainer = document.getElementById('notifications-container');
const notificationsBadge = notificationsContainer ? notificationsContainer.querySelector('[data-notification-badge]') : null;
const notificationsState = {
    hasFetched: false,
    loading: false,
    lastFetchedIds: [],
};
let notificationsBtnRef = null;
let notificationsDropdownRef = null;
let notificationsContentRef = null;
let notificationsMarkAllButton = null;
let notificationsMarkRequest = null;
const toastRoot = document.getElementById('gta6mods-toast-root');
const toastDefaultDuration = Math.max(3000, parseInt(gta6AuthorConfig.notificationDuration, 10) || 6000);

function hideToast(element) {
    if (!element) {
        return;
    }
    element.classList.add('opacity-0', 'translate-y-2');
    element.classList.remove('opacity-100', 'translate-y-0');
    window.setTimeout(() => {
        element.remove();
    }, 200);
}

function showToast(message, variant = 'info', options = {}) {
    if (!toastRoot || !message) {
        return;
    }

    const variants = {
        success: {
            border: 'border-green-500',
            iconBg: 'bg-green-500',
            icon: 'fas fa-check',
            text: 'text-green-900',
        },
        error: {
            border: 'border-red-500',
            iconBg: 'bg-red-500',
            icon: 'fas fa-times',
            text: 'text-red-900',
        },
        warning: {
            border: 'border-amber-500',
            iconBg: 'bg-amber-500',
            icon: 'fas fa-exclamation',
            text: 'text-amber-900',
        },
        info: {
            border: 'border-sky-500',
            iconBg: 'bg-sky-500',
            icon: 'fas fa-info',
            text: 'text-sky-900',
        },
    };

    const style = variants[variant] ? variants[variant] : variants.info;
    const toast = document.createElement('div');
    toast.className = `pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-black/5 border-l-4 ${style.border} transform transition duration-200 ease-out translate-y-2 opacity-0`;
    toast.setAttribute('role', variant === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-live', variant === 'error' ? 'assertive' : 'polite');

    const wrapper = document.createElement('div');
    wrapper.className = 'p-4 flex items-start gap-3';

    const iconWrapper = document.createElement('div');
    iconWrapper.className = `${style.iconBg} text-white flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full`; 
    const icon = document.createElement('i');
    icon.className = style.icon;
    iconWrapper.appendChild(icon);

    const content = document.createElement('div');
    content.className = 'flex-1';
    const messageEl = document.createElement('p');
    messageEl.className = `text-sm font-medium text-gray-900 ${style.text}`;
    messageEl.textContent = message;
    content.appendChild(messageEl);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 rounded-full p-1';
    closeBtn.setAttribute('aria-label', gta6AuthorConfig.strings.notificationDismiss || 'Dismiss notification');
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.addEventListener('click', () => hideToast(toast));

    wrapper.appendChild(iconWrapper);
    wrapper.appendChild(content);
    wrapper.appendChild(closeBtn);
    toast.appendChild(wrapper);

    toastRoot.appendChild(toast);

    window.requestAnimationFrame(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    });

    const duration = Math.max(0, options.duration != null ? options.duration : toastDefaultDuration);
    if (duration > 0) {
        window.setTimeout(() => hideToast(toast), duration);
    }

    return {
        toast,
        message: messageEl,
        closeButton: closeBtn,
    };
}

window.GTAModsShowToast = showToast;

function ensureNotificationsElements() {
    if (!notificationsDropdownRef) {
        notificationsDropdownRef = document.getElementById('notifications-dropdown');
    }

    if (!notificationsBtnRef) {
        notificationsBtnRef = document.getElementById('notifications-btn');
    }

    if (!notificationsContentRef && notificationsDropdownRef) {
        notificationsContentRef = notificationsDropdownRef.querySelector('[data-async-content="notifications"]');
    }

    if (!notificationsMarkAllButton && notificationsDropdownRef) {
        notificationsMarkAllButton = notificationsDropdownRef.querySelector('[data-action="mark-all-read"]');
    }
}

function updateNotificationsBadge(count) {
    const unread = Math.max(0, Number.parseInt(count, 10) || 0);

    if (notificationsBadge) {
        if (unread > 0) {
            notificationsBadge.classList.remove('hidden');
        } else {
            notificationsBadge.classList.add('hidden');
        }
    }

    ensureNotificationsElements();

    if (notificationsBtnRef) {
        notificationsBtnRef.setAttribute('data-unread-count', String(unread));
    }

    if (window.faviconBadge && typeof window.faviconBadge.update === 'function') {
        if (unread > 0) {
            window.faviconBadge.update(unread);
        } else {
            window.faviconBadge.reset();
        }
    } else {
        if (!Array.isArray(window.faviconBadgeQueue)) {
            window.faviconBadgeQueue = [];
        }

        window.faviconBadgeQueue.push(unread);
    }

    return unread;
}

function closeNotificationsDropdown() {
    ensureNotificationsElements();

    if (!notificationsDropdownRef) {
        return;
    }

    notificationsDropdownRef.classList.add('hidden');
    notificationsDropdownRef.setAttribute('aria-hidden', 'true');

    if (notificationsBtnRef) {
        notificationsBtnRef.setAttribute('aria-expanded', 'false');
    }

    if (notificationsContentRef) {
        notificationsContentRef.dataset.loading = '0';
        notificationsContentRef.dataset.loaded = '0';
    }

    notificationsState.hasFetched = false;
    notificationsState.loading = false;
    notificationsState.lastFetchedIds = [];
}

function setNotificationsAsRead(ids = [], options = {}) {
    ensureNotificationsElements();

    if (!notificationsContentRef) {
        return;
    }

    const markAll = options.markAll === true;
    const unreadItemClasses = ['bg-sky-50', 'hover:bg-sky-100'];
    const readItemClasses = ['hover:bg-gray-50'];
    let targetIds = [];

    if (markAll) {
        targetIds = Array.from(
            notificationsContentRef.querySelectorAll('[data-notification-id]')
        ).map((element) => Number.parseInt(element.getAttribute('data-notification-id') || '0', 10));
    } else if (Array.isArray(ids)) {
        targetIds = ids.map((value) => Number.parseInt(value, 10));
    }

    targetIds = Array.from(new Set(targetIds.filter((value) => Number.isFinite(value) && value > 0)));

    if (targetIds.length === 0) {
        return;
    }

    targetIds.forEach((id) => {
        const item = notificationsContentRef.querySelector(`[data-notification-id="${id}"]`);

        if (!item) {
            return;
        }

        item.setAttribute('data-notification-unread', '0');
        item.setAttribute('data-notification-status', 'read');

        unreadItemClasses.forEach((className) => {
            item.classList.remove(className);
        });

        readItemClasses.forEach((className) => {
            if (!item.classList.contains(className)) {
                item.classList.add(className);
            }
        });

        const srLabel = item.querySelector('span.sr-only');
        if (srLabel) {
            const readLabel = item.getAttribute('data-notification-read-label');
            if (typeof readLabel === 'string' && readLabel.length) {
                srLabel.textContent = readLabel;
            }
        }

        const message = item.querySelector('div > p');
        if (message) {
            message.classList.remove('font-semibold', 'text-gray-900');
            message.classList.add('font-medium', 'text-gray-700');
        }

        const timestamp = item.querySelector('div > p.mt-1');
        if (timestamp) {
            timestamp.classList.remove('text-gray-500');
            timestamp.classList.add('text-gray-400');
        }
    });
}

function markNotificationsRead(ids = [], options = {}) {
    ensureNotificationsElements();

    const markAll = options.markAll === true;
    const shouldUpdateUI = options.updateUI !== false;
    const sanitizedIds = Array.isArray(ids)
        ? ids
            .map((value) => Number.parseInt(value, 10))
            .filter((value) => Number.isFinite(value) && value > 0)
        : [];

    if (!markAll && sanitizedIds.length === 0) {
        return Promise.resolve({
            updated: 0,
            count: Number.parseInt(notificationsBtnRef?.getAttribute('data-unread-count') || '0', 10) || 0,
        });
    }

    const payload = markAll ? { mark_all: true } : { notification_ids: sanitizedIds };

    notificationsMarkRequest = fetch(`${gta6AuthorConfig.restBase}/author/${gta6AuthorConfig.authorId}/notifications/mark-read`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': gta6AuthorConfig.nonce,
        },
        body: JSON.stringify(payload),
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        })
        .then((data) => {
            if (typeof data.count === 'number') {
                updateNotificationsBadge(data.count);
            }

            if (shouldUpdateUI) {
                if (markAll) {
                    setNotificationsAsRead([], { markAll: true });
                } else if (sanitizedIds.length > 0) {
                    const idsToUpdate = Array.isArray(data.marked_ids) && data.marked_ids.length > 0
                        ? data.marked_ids
                        : sanitizedIds;
                    setNotificationsAsRead(idsToUpdate);
                }
            }

            notificationsState.lastFetchedIds = [];

            return data;
        })
        .catch((error) => {
            const message = gta6AuthorConfig.strings.notificationsMarkError || gta6AuthorConfig.strings.error;
            showToast(message, 'error');
            throw error;
        })
        .finally(() => {
            notificationsMarkRequest = null;
        });

    return notificationsMarkRequest;
}

function loadRecentNotifications() {
    ensureNotificationsElements();

    if (!notificationsContentRef || notificationsState.loading || notificationsState.hasFetched) {
        return;
    }

    notificationsState.loading = true;
    notificationsContentRef.dataset.loading = '1';
    notificationsContentRef.innerHTML = `<div class="py-4 text-center text-sm text-gray-500">${gta6AuthorConfig.strings.loading}</div>`;

    fetch(`${gta6AuthorConfig.restBase}/author/${gta6AuthorConfig.authorId}/notifications/recent`, {
        credentials: 'same-origin',
        headers: {
            'X-WP-Nonce': gta6AuthorConfig.nonce,
        },
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        })
        .then((data) => {
            notificationsState.hasFetched = true;
            notificationsContentRef.dataset.loaded = '1';

            if (data && typeof data.html === 'string' && data.html.length) {
                notificationsContentRef.innerHTML = data.html;
            } else {
                notificationsContentRef.innerHTML = `<div class="py-6 text-center text-sm text-gray-500">${gta6AuthorConfig.strings.noNotifications}</div>`;
            }

            let unreadIds = [];

            if (Array.isArray(data.unread_ids)) {
                unreadIds = data.unread_ids
                    .map((value) => Number.parseInt(value, 10))
                    .filter((value) => Number.isFinite(value) && value > 0);
            }

            if (unreadIds.length === 0) {
                unreadIds = Array.from(
                    notificationsContentRef.querySelectorAll('[data-notification-id][data-notification-unread="1"]')
                )
                    .map((element) => Number.parseInt(element.getAttribute('data-notification-id') || '0', 10))
                    .filter((value) => Number.isFinite(value) && value > 0);
            }

            notificationsState.lastFetchedIds = unreadIds;

            if (typeof data.count === 'number') {
                updateNotificationsBadge(data.count);
            }

            if (unreadIds.length > 0) {
                markNotificationsRead(unreadIds, { updateUI: false }).catch(() => {
                    // Errors are surfaced through markNotificationsRead.
                });
            }
        })
        .catch(() => {
            notificationsState.hasFetched = false;
            const message = gta6AuthorConfig.strings.notificationsLoadError || gta6AuthorConfig.strings.error;
            notificationsContentRef.innerHTML = `<div class="py-4 text-center text-sm text-red-500">${message}</div>`;
            showToast(message, 'error');
        })
        .finally(() => {
            notificationsState.loading = false;
            if (notificationsContentRef) {
                notificationsContentRef.dataset.loading = '0';
            }
        });
}

const tabContentGroups = new Map();
const tabPanels = new Map();
const tabUrls = new Map();
const tabEndpoints = new Map();
const tabState = {
    currentKey: typeof gta6AuthorConfig.activeTab === 'string' ? gta6AuthorConfig.activeTab.toLowerCase() : 'overview',
    currentPage: new Map(),
};

let settingsShortcutButton = null;

if (gta6AuthorConfig.tabUrls && typeof gta6AuthorConfig.tabUrls === 'object') {
    Object.entries(gta6AuthorConfig.tabUrls).forEach(([key, value]) => {
        if (typeof key === 'string' && typeof value === 'string' && value.length) {
            tabUrls.set(key, value);
        }
    });
}

if (gta6AuthorConfig.tabEndpoints && typeof gta6AuthorConfig.tabEndpoints === 'object') {
    Object.entries(gta6AuthorConfig.tabEndpoints).forEach(([key, value]) => {
        if (typeof key === 'string' && typeof value === 'string' && value.length) {
            tabEndpoints.set(key, value);
        }
    });
}

function ensureTrailingSlash(url) {
    return url.endsWith('/') ? url : `${url}/`;
}

function normalizeUrl(url) {
    try {
        const normalized = new URL(url, window.location.origin);
        normalized.hash = '';
        return normalized.href;
    } catch (error) {
        return url;
    }
}

function collectTabElements() {
    tabContentGroups.clear();
    tabPanels.clear();

    document.querySelectorAll('[data-tab-content]').forEach((element) => {
        const key = element.getAttribute('data-tab-content');
        if (!key) {
            return;
        }

        const normalizedKey = key.toLowerCase();
        if (!tabContentGroups.has(normalizedKey)) {
            tabContentGroups.set(normalizedKey, new Set());
        }
        tabContentGroups.get(normalizedKey).add(element);

        const panelKey = element.getAttribute('data-tab-panel');
        if (panelKey && !tabPanels.has(panelKey)) {
            tabPanels.set(panelKey, element);
        }
    });
}

function updateSettingsShortcutState() {
    if (!settingsShortcutButton) {
        return;
    }

    const shortcutKey = settingsShortcutButton.getAttribute('data-tab-key');
    if (!shortcutKey) {
        return;
    }

    const isActive = shortcutKey.toLowerCase() === tabState.currentKey;

    settingsShortcutButton.dataset.tabActive = isActive ? '1' : '0';

    if (isActive) {
        settingsShortcutButton.setAttribute('aria-current', 'page');
    } else {
        settingsShortcutButton.removeAttribute('aria-current');
    }
}

function updateTabMarkupStates(container) {
    if (!container) {
        return;
    }

    container.querySelectorAll('[data-tab-key]').forEach((button) => {
        const key = button.getAttribute('data-tab-key');
        if (!key) {
            return;
        }

        const normalizedKey = key.toLowerCase();
        const isActive = normalizedKey === tabState.currentKey;

        if (button.classList.contains('profile-tab-btn')) {
            if (isActive) {
                button.classList.add('active');
                button.setAttribute('aria-current', 'page');
                button.setAttribute('aria-selected', 'true');
            } else {
                button.classList.remove('active');
                button.removeAttribute('aria-current');
                button.setAttribute('aria-selected', 'false');
            }
        } else {
            if (isActive) {
                button.classList.add('text-pink-600');
                button.setAttribute('aria-current', 'page');
                button.setAttribute('aria-selected', 'true');
            } else {
                button.classList.remove('text-pink-600');
                button.removeAttribute('aria-current');
                button.setAttribute('aria-selected', 'false');
            }
        }
    });
}

function bindTabButtons(container) {
    if (!container) {
        return;
    }

    container.querySelectorAll('[data-tab-key]').forEach((button) => {
        if (button.dataset.tabBound === '1') {
            return;
        }

        button.addEventListener('click', handleTabButtonClick);
        button.dataset.tabBound = '1';
    });
}

function handleTabButtonClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const button = event.currentTarget;
    const tabKey = button.getAttribute('data-tab-key');
    if (!tabKey) {
        return;
    }

    event.preventDefault();

    if (moreTabsDropdown && moreTabsDropdown.contains(button)) {
        moreTabsDropdown.classList.add('hidden');
    }

    setActiveTab(tabKey, { targetUrl: button.getAttribute('href') || button.href });
}

function normalizeTabKey(key) {
    if (typeof key === 'string' && key.length) {
        const normalizedKey = key.toLowerCase();
        if (tabContentGroups.has(normalizedKey) || tabPanels.has(normalizedKey) || tabUrls.has(normalizedKey)) {
            return normalizedKey;
        }
    }

    if (tabContentGroups.has('overview')) {
        return 'overview';
    }

    const iterator = tabContentGroups.keys();
    const first = iterator.next();
    if (!first.done) {
        return first.value;
    }

    return null;
}

function setTabVisibility(activeKey) {
    tabContentGroups.forEach((elements, key) => {
        const isActive = key === activeKey;
        elements.forEach((element) => {
            if (isActive) {
                element.classList.remove('hidden');
                element.setAttribute('aria-hidden', 'false');
            } else {
                element.classList.add('hidden');
                element.setAttribute('aria-hidden', 'true');
            }
        });
    });
}

function buildTabUrl(tabKey, page) {
    if (!tabUrls.has(tabKey)) {
        return null;
    }

    const baseUrl = tabUrls.get(tabKey);
    if (!baseUrl) {
        return null;
    }

    const targetPage = Math.max(1, parseInt(page, 10) || 1);
    if (targetPage <= 1) {
        return baseUrl;
    }

    return `${ensureTrailingSlash(baseUrl)}page-${targetPage}/`;
}

function updateHistoryState(tabKey, page, replace, targetUrl) {
    if (!window.history || typeof window.history.pushState !== 'function') {
        return;
    }

    const desiredUrl = targetUrl || buildTabUrl(tabKey, page);
    const method = replace ? 'replaceState' : 'pushState';
    const normalizedCurrent = normalizeUrl(window.location.href);
    const normalizedTarget = desiredUrl ? normalizeUrl(desiredUrl) : null;

    if (normalizedTarget && normalizedTarget !== normalizedCurrent) {
        window.history[method]({ tabKey, page }, '', normalizedTarget);
    } else {
        window.history[method]({ tabKey, page }, '', window.location.href);
    }
}

function resolveStateFromUrl(inputUrl) {
    try {
        const url = new URL(inputUrl, window.location.origin);
        const normalizedHref = url.href;
        let matchedTab = null;
        let matchedPage = 1;

        tabUrls.forEach((tabUrl, key) => {
            if (!tabUrl || matchedTab) {
                return;
            }

            const normalizedTabUrl = new URL(tabUrl, window.location.origin).href;
            if (normalizedHref === normalizedTabUrl) {
                matchedTab = key;
                matchedPage = 1;
                return;
            }

            if (normalizedHref.startsWith(normalizedTabUrl)) {
                const remainder = normalizedHref.substring(normalizedTabUrl.length);
                if (!remainder || remainder === '/') {
                    matchedTab = key;
                    matchedPage = 1;
                    return;
                }

                const pageMatch = remainder.match(/^page-(\d+)\/?/);
                if (pageMatch) {
                    matchedTab = key;
                    matchedPage = parseInt(pageMatch[1], 10) || 1;
                }
            }
        });

        if (!matchedTab) {
            return {
                tabKey: normalizeTabKey(gta6AuthorConfig.activeTab || 'overview'),
                page: Math.max(1, parseInt(gta6AuthorConfig.initialPage, 10) || 1),
            };
        }

        return {
            tabKey: normalizeTabKey(matchedTab),
            page: matchedPage,
        };
    } catch (error) {
        return {
            tabKey: normalizeTabKey(gta6AuthorConfig.activeTab || 'overview'),
            page: Math.max(1, parseInt(gta6AuthorConfig.initialPage, 10) || 1),
        };
    }
}

function handleTabContentRendered(tabKey, panel) {
    if (tabKey === 'uploads') {
        bindUploadsPagination(panel);
    }

    if (tabKey === 'comments') {
        bindCommentsPagination(panel);
    }

    if (tabKey === 'comments' || tabKey === 'overview') {
        bindCommentLightbox(panel);
    }
}

function bindUploadsPagination(panel) {
    if (!panel || panel.dataset.uploadPaginationBound === '1') {
        return;
    }

    panel.addEventListener('click', (event) => {
        const link = event.target.closest('[data-upload-page]');
        if (!link || !panel.contains(link)) {
            return;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const targetPage = parseInt(link.dataset.uploadPage || '1', 10);
        if (!targetPage || targetPage < 1) {
            return;
        }

        event.preventDefault();
        setActiveTab('uploads', {
            page: targetPage,
            targetUrl: link.href,
            force: true,
        });
    });

    panel.dataset.uploadPaginationBound = '1';
}

function bindCommentsPagination(panel) {
    if (!panel || panel.dataset.commentPaginationBound === '1') {
        return;
    }

    panel.addEventListener('click', (event) => {
        const link = event.target.closest('[data-comment-page]');
        if (!link || !panel.contains(link)) {
            return;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const targetPage = parseInt(link.dataset.commentPage || '1', 10);
        if (!targetPage || targetPage < 1) {
            return;
        }

        event.preventDefault();
        setActiveTab('comments', {
            page: targetPage,
            targetUrl: link.href,
            force: true,
        });
    });

    panel.dataset.commentPaginationBound = '1';
}

function bindCommentLightbox(panel) {
    if (!panel || panel.dataset.commentLightboxBound === '1') {
        return;
    }

    panel.addEventListener('click', (event) => {
        const lightboxItem = event.target.closest('.comment-lightbox-item');
        if (!lightboxItem || !panel.contains(lightboxItem)) {
            return;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        const thumbnailImage = lightboxItem.querySelector('img');
        let lightboxOpened = false;
        const dataSource = [{
            src: lightboxItem.href,
            width: parseInt(lightboxItem.dataset.pswpWidth || '0', 10),
            height: parseInt(lightboxItem.dataset.pswpHeight || '0', 10),
            alt: thumbnailImage?.alt || '',
            element: thumbnailImage || lightboxItem,
        }];

        const openLightbox = () => {
            if (lightboxOpened) {
                return;
            }
            lightboxOpened = true;

            const lightbox = new PhotoSwipeLightbox({
                dataSource,
                pswpModule: PhotoSwipe,
                showHideAnimationType: 'zoom',
            });

            lightbox.addFilter('thumbBounds', () => {
                if (!thumbnailImage) {
                    return null;
                }

                const rect = thumbnailImage.getBoundingClientRect();
                return {
                    x: rect.left,
                    y: rect.top,
                    w: rect.width,
                };
            });

            lightbox.on('close', () => {
                lightbox.destroy();
            });

            lightbox.init();
            lightbox.loadAndOpen(0);
        };

        const fallbackWidth = thumbnailImage?.naturalWidth || thumbnailImage?.width || 480;
        const fallbackHeight = thumbnailImage?.naturalHeight || thumbnailImage?.height || 270;

        const ensureDimensionsAndOpen = () => {
            if (dataSource[0].width > 0 && dataSource[0].height > 0) {
                openLightbox();
                return;
            }

            const preloadImage = new Image();
            preloadImage.onload = () => {
                dataSource[0].width = preloadImage.naturalWidth || fallbackWidth;
                dataSource[0].height = preloadImage.naturalHeight || fallbackHeight;
                openLightbox();
            };
            preloadImage.onerror = () => {
                dataSource[0].width = fallbackWidth;
                dataSource[0].height = fallbackHeight;
                openLightbox();
            };
            preloadImage.src = dataSource[0].src;
        };

        const fallbackToNewTab = () => {
            window.open(lightboxItem.href, '_blank', 'noopener,noreferrer');
        };

        const tryOpenLightbox = () => {
            if (typeof PhotoSwipeLightbox === 'function' && typeof PhotoSwipe !== 'undefined') {
                ensureDimensionsAndOpen();
                return;
            }

            const waitStart = Date.now();
            const waitForLibrary = () => {
                if (typeof PhotoSwipeLightbox === 'function' && typeof PhotoSwipe !== 'undefined') {
                    ensureDimensionsAndOpen();
                } else if (Date.now() - waitStart < 1200) {
                    window.requestAnimationFrame(waitForLibrary);
                } else {
                    fallbackToNewTab();
                }
            };

            waitForLibrary();
        };

        tryOpenLightbox();
    });

    panel.dataset.commentLightboxBound = '1';
}

function loadTabPanel(tabKey, page) {
    const panel = tabPanels.get(tabKey);
    if (!panel) {
        return Promise.resolve();
    }

    const targetPage = Math.max(1, parseInt(page, 10) || 1);
    const currentPage = parseInt(panel.dataset.page || '1', 10);

    if (panel.dataset.loaded === '1' && currentPage === targetPage) {
        return Promise.resolve();
    }

    const endpoint = tabEndpoints.get(tabKey);
    if (!endpoint) {
        panel.dataset.loaded = '1';
        panel.dataset.page = String(targetPage);
        tabState.currentPage.set(tabKey, targetPage);
        return Promise.resolve();
    }

    const params = new URLSearchParams();
    if (targetPage > 1) {
        params.set('page', String(targetPage));
    }

    const url = params.toString() ? `${endpoint}?${params.toString()}` : endpoint;
    const token = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    panel.dataset.requestToken = token;
    panel.dataset.loading = '1';
    panel.dataset.loaded = '0';
    panel.innerHTML = `<div class="py-12 text-center text-sm text-gray-500">${gta6AuthorConfig.strings.loading || 'Loading…'}</div>`;

    const headers = {};
    if (gta6AuthorConfig.nonce) {
        headers['X-WP-Nonce'] = gta6AuthorConfig.nonce;
    }

    return fetch(url, {
        credentials: 'same-origin',
        headers,
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        })
        .then((data) => {
            if (panel.dataset.requestToken !== token) {
                return;
            }

            const resolvedPage = data && typeof data.page !== 'undefined'
                ? Math.max(1, parseInt(data.page, 10) || targetPage)
                : targetPage;

            panel.innerHTML = data && data.html ? data.html : '';
            panel.dataset.loaded = '1';
            panel.dataset.page = String(resolvedPage);
            panel.dataset.loading = '0';
            panel.dataset.requestToken = '';
            tabState.currentPage.set(tabKey, resolvedPage);

            if (resolvedPage !== targetPage && tabState.currentKey === tabKey) {
                updateHistoryState(tabKey, resolvedPage, true);
            }
            handleTabContentRendered(tabKey, panel);
        })
        .catch(() => {
            if (panel.dataset.requestToken !== token) {
                return;
            }

            const errorMessage = gta6AuthorConfig.strings.tabError || gta6AuthorConfig.strings.error || 'Unable to load this section.';
            panel.innerHTML = `<div class="py-12 text-center text-sm text-red-500">${errorMessage}</div>`;
            panel.dataset.loaded = '0';
            panel.dataset.loading = '0';
            panel.dataset.requestToken = '';
        });
}

function setActiveTab(nextKey, options = {}) {
    const { page = 1, skipHistory = false, replaceHistory = false, targetUrl = null, force = false } = options;
    const normalizedKey = normalizeTabKey(nextKey);

    if (!normalizedKey) {
        return;
    }

    const targetPage = Math.max(1, parseInt(page, 10) || 1);
    const currentPage = tabState.currentPage.get(normalizedKey) || 1;

    if (!force && normalizedKey === tabState.currentKey && targetPage === currentPage) {
        return;
    }

    tabState.currentKey = normalizedKey;
    tabState.currentPage.set(normalizedKey, targetPage);

    setTabVisibility(normalizedKey);
    updateTabMarkupStates(tabSources);
    updateTabMarkupStates(visibleTabs);
    updateTabMarkupStates(moreTabsDropdown);

    if (moreTabsDropdown) {
        moreTabsDropdown.classList.add('hidden');
    }

    if (!skipHistory) {
        const url = targetUrl || buildTabUrl(normalizedKey, targetPage);
        updateHistoryState(normalizedKey, targetPage, replaceHistory, url);
    }

    loadTabPanel(normalizedKey, targetPage);
    updateSettingsShortcutState();
}

function initializeAuthorTabState() {
    const resolved = resolveStateFromUrl(window.location.href);
    const normalizedKey = normalizeTabKey(resolved.tabKey);
    const initialPage = Math.max(1, parseInt(resolved.page, 10) || 1);

    tabState.currentKey = normalizedKey || tabState.currentKey || 'overview';
    tabState.currentPage.set(tabState.currentKey, initialPage);

    tabPanels.forEach((panel, key) => {
        const panelPage = parseInt(panel.dataset.page || '1', 10);
        if (panel.dataset.loaded === '1') {
            tabState.currentPage.set(key, panelPage);
            handleTabContentRendered(key, panel);
        }
    });

    setTabVisibility(tabState.currentKey);
    updateTabMarkupStates(tabSources);
    updateTabMarkupStates(visibleTabs);
    updateTabMarkupStates(moreTabsDropdown);

    const initialUrl = buildTabUrl(tabState.currentKey, initialPage) || window.location.href;
    if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState({ tabKey: tabState.currentKey, page: initialPage }, '', initialUrl);
    }

    updateSettingsShortcutState();
}

function cloneTabs() {
    if (!tabSources || !visibleTabs) {
        return;
    }

    updateTabMarkupStates(tabSources);

    const allTabs = Array.from(tabSources.children);

    if (moreTabsContainer) {
        moreTabsContainer.classList.add('hidden');
    }
    if (moreTabsDropdown) {
        moreTabsDropdown.replaceChildren();
    }

    const wrapper = document.getElementById('tabs-wrapper');
    if (!wrapper) {
        return;
    }

    const maxWidth = wrapper.offsetWidth;
    let usedWidth = 0;
    const visible = [];
    const hidden = [];

    allTabs.forEach((tab) => {
        const measurement = tab.cloneNode(true);
        measurement.style.visibility = 'hidden';
        measurement.style.position = 'absolute';
        document.body.appendChild(measurement);
        const width = measurement.offsetWidth + 24;
        document.body.removeChild(measurement);

        if (usedWidth + width > maxWidth - 80) {
            hidden.push(tab);
        } else {
            visible.push(tab);
            usedWidth += width;
        }
    });

    const visibleClones = visible.map((tab) => tab.cloneNode(true));
    visibleTabs.replaceChildren(...visibleClones);
    bindTabButtons(visibleTabs);
    updateTabMarkupStates(visibleTabs);

    if (hidden.length && moreTabsDropdown && moreTabsContainer) {
        moreTabsContainer.classList.remove('hidden');
        const dropdownClones = hidden.map((tab) => {
            const dropdownItem = tab.cloneNode(true);
            dropdownItem.classList.remove('profile-tab-btn');
            dropdownItem.classList.add('block', 'w-full', 'text-left', 'px-2', 'py-2', 'hover:bg-gray-100', 'text-sm');
            return dropdownItem;
        });
        moreTabsDropdown.replaceChildren(...dropdownClones);
        bindTabButtons(moreTabsDropdown);
        updateTabMarkupStates(moreTabsDropdown);
    }
}

function initTabs() {
    cloneTabs();

    if (visibleTabs) {
        visibleTabs.addEventListener('click', () => {
            if (moreTabsDropdown) {
                moreTabsDropdown.classList.add('hidden');
            }
        });
    }

    if (moreTabsBtn && moreTabsDropdown) {
        moreTabsBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            moreTabsDropdown.classList.toggle('hidden');
        });
    }

    if (moreTabsDropdown) {
        moreTabsDropdown.addEventListener('click', () => {
            moreTabsDropdown.classList.add('hidden');
        });
    }

    document.addEventListener('click', (event) => {
        if (moreTabsContainer && !moreTabsContainer.contains(event.target)) {
            moreTabsDropdown?.classList.add('hidden');
        }
        if (notificationsContainer) {
            if (!notificationsContainer.contains(event.target)) {
                closeNotificationsDropdown();
            }
        } else if (document.getElementById('notifications-dropdown')) {
            closeNotificationsDropdown();
        }
    });

    const tabsWrapper = document.getElementById('tabs-wrapper');
    if (tabsWrapper && typeof ResizeObserver === 'function') {
        new ResizeObserver(cloneTabs).observe(tabsWrapper);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    collectTabElements();
    settingsShortcutButton = document.querySelector('[data-action="open-settings-tab"]');

    notificationsBtnRef = document.getElementById('notifications-btn');
    notificationsDropdownRef = document.getElementById('notifications-dropdown');
    notificationsContentRef = notificationsDropdownRef ? notificationsDropdownRef.querySelector('[data-async-content="notifications"]') : null;
    notificationsMarkAllButton = notificationsDropdownRef ? notificationsDropdownRef.querySelector('[data-action="mark-all-read"]') : null;

    if (notificationsContentRef) {
        notificationsContentRef.dataset.loaded = notificationsContentRef.dataset.loaded || '0';
        notificationsContentRef.dataset.loading = notificationsContentRef.dataset.loading || '0';
    }

    if (settingsShortcutButton && settingsShortcutButton.dataset.tabBound !== '1') {
        settingsShortcutButton.addEventListener('click', handleTabButtonClick);
        settingsShortcutButton.dataset.tabBound = '1';
    }

    initializeAuthorTabState();
    initTabs();

    window.addEventListener('popstate', (event) => {
        const state = event.state || {};
        let targetKey = state.tabKey;
        let targetPage = state.page;

        if (!targetKey) {
            const resolved = resolveStateFromUrl(window.location.href);
            targetKey = resolved.tabKey;
            targetPage = resolved.page;
        }

        setActiveTab(targetKey, {
            page: targetPage,
            skipHistory: true,
            force: true,
        });
    });

    if (!isRestNotificationsEnabled() && notificationsBtnRef && notificationsDropdownRef && notificationsContentRef) {
        notificationsBtnRef.addEventListener('click', (event) => {
            event.stopPropagation();

            const isHidden = notificationsDropdownRef.classList.contains('hidden');

            if (isHidden) {
                notificationsDropdownRef.classList.remove('hidden');
                notificationsDropdownRef.setAttribute('aria-hidden', 'false');
                notificationsBtnRef.setAttribute('aria-expanded', 'true');
                loadRecentNotifications();
            } else {
                closeNotificationsDropdown();
            }
        });
    }

    if (!isRestNotificationsEnabled() && notificationsMarkAllButton) {
        notificationsMarkAllButton.addEventListener('click', (event) => {
            event.preventDefault();

            if (notificationsMarkRequest) {
                return;
            }

            notificationsMarkAllButton.disabled = true;
            notificationsMarkAllButton.classList.add('opacity-50', 'cursor-not-allowed');

            markNotificationsRead([], { markAll: true })
                .then((data) => {
                    if (notificationsContentRef) {
                        setNotificationsAsRead([], { markAll: true });

                        if (!notificationsContentRef.querySelector('[data-notification-id]')) {
                            notificationsContentRef.innerHTML = `<div class="py-6 text-center text-sm text-gray-500">${gta6AuthorConfig.strings.noNotifications}</div>`;
                            notificationsContentRef.dataset.loaded = '1';
                        }
                    }

                    notificationsState.hasFetched = true;
                    notificationsState.lastFetchedIds = [];

                    if (data && typeof data.count === 'number') {
                        updateNotificationsBadge(data.count);
                    } else {
                        updateNotificationsBadge(0);
                    }

                    if (gta6AuthorConfig.strings.markAllComplete) {
                        showToast(gta6AuthorConfig.strings.markAllComplete, 'success');
                    }
                })
                .catch(() => {
                    // Errors are handled inside markNotificationsRead.
                })
                .finally(() => {
                    notificationsMarkAllButton.disabled = false;
                    notificationsMarkAllButton.classList.remove('opacity-50', 'cursor-not-allowed');
                });
        });
    }

    const followBtn = document.getElementById('gta6mods-follow-btn');
    if (!isRestFollowEnabled() && followBtn) {
        followBtn.addEventListener('click', () => {
            const following = followBtn.dataset.following === '1';
            const action = following ? 'unfollow' : 'follow';
            followBtn.disabled = true;
            fetch(`${gta6AuthorConfig.restBase}/author/${gta6AuthorConfig.authorId}/follow`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': gta6AuthorConfig.nonce,
                },
                body: JSON.stringify({ action }),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    followBtn.dataset.following = data.is_following ? '1' : '0';
                    followBtn.querySelector('.follow-label').textContent = data.is_following ? gta6AuthorConfig.strings.following : gta6AuthorConfig.strings.follow;
                    const countEl = document.getElementById('gta6mods-followers-count');
                    if (countEl) {
                        countEl.textContent = new Intl.NumberFormat().format(data.followers_count);
                    }
                })
                .catch(() => {
                    showToast(gta6AuthorConfig.strings.error, 'error');
                })
                .finally(() => {
                    followBtn.disabled = false;
                });
        });
    }

    document.addEventListener('click', (event) => {
        const loadMoreBtn = event.target.closest('[data-load-more="activity"]');
        if (loadMoreBtn) {
            event.preventDefault();
            if (loadMoreBtn.dataset.loading === '1') {
                return;
            }
            const list = document.getElementById('gta6mods-activity-list');
            if (!list) {
                return;
            }
            const offset = parseInt(loadMoreBtn.dataset.offset || `${gta6AuthorConfig.activityPerPage}`, 10);
            const perPage = parseInt(loadMoreBtn.dataset.perPage || `${gta6AuthorConfig.activityPerPage}`, 10);
            loadMoreBtn.dataset.loading = '1';
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = gta6AuthorConfig.strings.loading;
            const params = new URLSearchParams({ offset, per_page: perPage });
            fetch(`${gta6AuthorConfig.restBase}/author/${gta6AuthorConfig.authorId}/activity?${params.toString()}`, {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': gta6AuthorConfig.nonce,
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    if (data.html) {
                        list.insertAdjacentHTML('beforeend', data.html);
                    }
                    if (data.has_more) {
                        loadMoreBtn.dataset.offset = data.next_offset ? data.next_offset : offset + perPage;
                        loadMoreBtn.dataset.loading = '0';
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.textContent = gta6AuthorConfig.strings.loadMoreActivity;
                    } else {
                        loadMoreBtn.remove();
                    }
                })
                .catch(() => {
                    showToast(gta6AuthorConfig.strings.error, 'error');
                    loadMoreBtn.dataset.loading = '0';
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = gta6AuthorConfig.strings.loadMoreActivity;
                });
            return;
        }

        const reportBtn = event.target.closest('[data-action="report-status"]');
        if (reportBtn) {
            event.preventDefault();
            const statusId = parseInt(reportBtn.dataset.statusId, 10);
            if (!statusId || reportBtn.dataset.state === 'done') {
                return;
            }
            if (!window.confirm(gta6AuthorConfig.strings.reportConfirm)) {
                return;
            }
            reportBtn.disabled = true;
            fetch(`${gta6AuthorConfig.restBase}/author/report`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': gta6AuthorConfig.nonce,
                },
                body: JSON.stringify({
                    object_id: statusId,
                    object_type: 'status_update',
                    reported_user_id: gta6AuthorConfig.authorId,
                    reason: 'status_inappropriate',
                }),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then(() => {
                    reportBtn.dataset.state = 'done';
                    reportBtn.textContent = gta6AuthorConfig.strings.reported;
                    showToast(gta6AuthorConfig.strings.reportSuccess, 'success');
                })
                .catch(() => {
                    reportBtn.disabled = false;
                    showToast(gta6AuthorConfig.strings.error, 'error');
                });
        }

        const deleteBtn = event.target.closest('[data-action="delete-status"]');
        if (deleteBtn) {
            event.preventDefault();
            const statusId = parseInt(deleteBtn.dataset.statusId, 10);
            if (!statusId) {
                return;
            }
            if (!window.confirm(gta6AuthorConfig.strings.deleteConfirm)) {
                return;
            }
            deleteBtn.disabled = true;
            fetch(`${gta6AuthorConfig.restBase}/author/status/${statusId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': gta6AuthorConfig.nonce,
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then(() => {
                    const activityItem = deleteBtn.closest('li');
                    if (activityItem) {
                        activityItem.remove();
                    }
                    showToast(gta6AuthorConfig.strings.deleteSuccess, 'success');
                })
                .catch(() => {
                    deleteBtn.disabled = false;
                    showToast(gta6AuthorConfig.strings.error, 'error');
                });
        }
    });

    if (gta6AuthorConfig.isOwner) {
        const settingsNav = document.getElementById('settings-tabs-nav');
        const settingsPanels = document.querySelectorAll('#settings .settings-tab-content');
        if (settingsNav && settingsPanels.length) {
            settingsNav.addEventListener('click', (event) => {
                const button = event.target.closest('.settings-tab-btn');
                if (!button) {
                    return;
                }
                event.preventDefault();
                const tab = button.dataset.settingsTab;
                if (!tab) {
                    return;
                }
                settingsNav.querySelectorAll('.settings-tab-btn').forEach((navButton) => {
                    navButton.classList.toggle('active', navButton === button);
                });
                settingsPanels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.id !== `settings-${tab}`);
                });
            });
        }

        const textarea = document.getElementById('status-update-textarea');
        const actions = document.getElementById('status-update-actions');
        if (textarea && actions && !isRestStatusEnabled()) {
            const statusCounter = document.getElementById('status-update-counter');
            const statusMaxLength = parseInt(textarea.getAttribute('data-maxlength') || `${gta6AuthorConfig.statusMaxLength || 5000}`, 10);

            const normalizeLineEndings = (value) => value.replace(/\r\n?/g, '\n');
            const getStatusPlainText = () => normalizeLineEndings((textarea.innerText || '').replace(/\u00a0/g, ' '));
            const updateStatusCounter = (length) => {
                if (statusCounter) {
                    statusCounter.textContent = `${length}/${statusMaxLength}`;
                }
            };
            const placeCaretAtEnd = (element) => {
                element.focus();
                const selection = window.getSelection();
                if (!selection) {
                    return;
                }
                const range = document.createRange();
                range.selectNodeContents(element);
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            };
            const enforceLimit = () => {
                let text = getStatusPlainText();
                if (text.length > statusMaxLength) {
                    text = text.slice(0, statusMaxLength);
                    textarea.textContent = text;
                    placeCaretAtEnd(textarea);
                }
                return text;
            };

            textarea.addEventListener('focus', () => {
                actions.classList.remove('hidden');
                const text = enforceLimit();
                textarea.classList.toggle('is-empty', text.trim() === '');
                updateStatusCounter(text.length);
            });

            textarea.addEventListener('paste', (event) => {
                event.preventDefault();
                const pasted = (event.clipboardData || window.clipboardData).getData('text');
                if (typeof document.execCommand === 'function') {
                    document.execCommand('insertText', false, pasted);
                } else {
                    const selection = window.getSelection();
                    if (selection && selection.rangeCount > 0) {
                        selection.deleteFromDocument();
                        selection.getRangeAt(0).insertNode(document.createTextNode(pasted));
                        placeCaretAtEnd(textarea);
                    } else {
                        textarea.textContent += pasted;
                        placeCaretAtEnd(textarea);
                    }
                }
            });

            textarea.addEventListener('input', () => {
                const text = enforceLimit();
                const trimmed = text.trim();
                textarea.classList.toggle('is-empty', trimmed === '');
                updateStatusCounter(text.length);
            });

            textarea.addEventListener('blur', () => {
                if (getStatusPlainText().trim() === '') {
                    actions.classList.add('hidden');
                    textarea.classList.add('is-empty');
                    updateStatusCounter(0);
                }
            });

            const submit = document.getElementById('status-update-submit');
            submit?.addEventListener('click', () => {
                let text = enforceLimit();
                text = normalizeLineEndings(text).replace(/\u00a0/g, ' ');
                const trimmed = text.trim();
                if (!trimmed) {
                    showToast(gta6AuthorConfig.strings.statusEmpty, 'warning');
                    return;
                }

                submit.disabled = true;
                fetch(`${gta6AuthorConfig.restBase}/author/${gta6AuthorConfig.authorId}/status`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': gta6AuthorConfig.nonce,
                    },
                    body: JSON.stringify({ content: trimmed }),
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.json();
                    })
                    .then(() => {
                        textarea.textContent = '';
                        textarea.classList.add('is-empty');
                        actions.classList.add('hidden');
                        updateStatusCounter(0);
                        window.location.reload();
                    })
                    .catch(() => {
                        showToast(gta6AuthorConfig.strings.error, 'error');
                    })
                    .finally(() => {
                        submit.disabled = false;
                    });
            });
        }

        const saveProfile = document.getElementById('gta6mods-save-profile');
        const saveLinks = document.getElementById('gta6mods-save-links');
        const emailField = document.getElementById('gta6mods-settings-email');
        const bioField = document.getElementById('gta6mods-settings-bio');
        const bioMaxLength = 160;
        const linksWrapper = document.getElementById('gta6mods-social-links');
        const bannerInput = document.getElementById('gta6mods-settings-banner');
        const bannerPreview = document.getElementById('gta6mods-banner-preview');
        const bannerRemove = document.getElementById('gta6mods-remove-banner');
        const headerBackground = document.querySelector('.header-background');
        const maxBannerSize = Number(gta6AuthorConfig.maxBannerSize || 2097152);
        const avatarInput = document.getElementById('gta6mods-settings-avatar');
        const avatarPreview = document.getElementById('gta6mods-avatar-preview');
        const presetGrid = document.getElementById('avatar-selection-grid');
        const avatarMaxSize = Number(gta6AuthorConfig.avatarMaxSize || 1048576);
        const avatarDeleteButton = document.getElementById('gta6mods-delete-avatar');
        const avatarDeleteModal = document.getElementById('gta6mods-delete-avatar-modal');
        const avatarDeleteConfirm = document.getElementById('gta6mods-delete-avatar-confirm');
        const avatarDeleteOverlay = avatarDeleteModal ? avatarDeleteModal.querySelector('[data-avatar-delete-overlay]') : null;
        const avatarDeleteCancelButtons = avatarDeleteModal ? avatarDeleteModal.querySelectorAll('[data-avatar-delete-cancel]') : [];
        const currentPasswordField = document.getElementById('gta6mods-current-password');
        const newPasswordField = document.getElementById('gta6mods-new-password');
        const confirmPasswordField = document.getElementById('gta6mods-confirm-password');
        const passwordNonceField = document.getElementById('gta6mods_password_nonce');
        const savePasswordButton = document.getElementById('gta6mods-save-password');
        const passwordSavingSpinner = savePasswordButton ? savePasswordButton.querySelector('[data-password-saving-spinner]') : null;
        const passwordSavingLabel = savePasswordButton ? savePasswordButton.querySelector('[data-password-saving-label]') : null;
        const passwordStrengthBar = document.getElementById('gta6mods-password-strength-bar');
        const passwordStrengthLabel = document.getElementById('gta6mods-password-strength-text');
        const passwordModal = document.getElementById('gta6mods-password-confirm-modal');
        const passwordModalOverlay = passwordModal ? passwordModal.querySelector('[data-password-confirm-overlay]') : null;
        const passwordModalCancelButtons = passwordModal ? passwordModal.querySelectorAll('[data-password-confirm-cancel]') : [];
        const passwordModalConfirm = document.getElementById('gta6mods-password-confirm');
        const passwordModalSpinner = passwordModal ? passwordModal.querySelector('[data-password-confirm-spinner]') : null;
        const passwordModalLabel = passwordModal ? passwordModal.querySelector('[data-password-confirm-label]') : null;

        let currentAvatar = { ...(gta6AuthorConfig.avatar || {}) };
        if (currentAvatar && typeof currentAvatar === 'object' && !currentAvatar.defaultUrl && gta6AuthorConfig.avatar && gta6AuthorConfig.avatar.defaultUrl) {
            currentAvatar.defaultUrl = gta6AuthorConfig.avatar.defaultUrl;
        }

        let passwordChangeInProgress = false;
        const passwordStrengthClasses = ['bg-red-500', 'bg-orange-500', 'bg-amber-500', 'bg-lime-500', 'bg-green-500'];
        const passwordStrengthKeys = ['passwordStrengthVeryWeak', 'passwordStrengthWeak', 'passwordStrengthMedium', 'passwordStrengthStrong', 'passwordStrengthVeryStrong'];

        function getPasswordNonce() {
            if (passwordNonceField && passwordNonceField.value) {
                return passwordNonceField.value;
            }
            if (typeof gta6AuthorConfig.passwordNonce === 'string') {
                return gta6AuthorConfig.passwordNonce;
            }
            return '';
        }

        function setPasswordNonce(value) {
            if (!value) {
                return;
            }
            if (passwordNonceField) {
                passwordNonceField.value = value;
            }
            gta6AuthorConfig.passwordNonce = value;
        }

        function setPasswordSavingState(isSaving) {
            if (savePasswordButton) {
                savePasswordButton.disabled = isSaving;
            }
            if (passwordSavingSpinner) {
                passwordSavingSpinner.classList.toggle('hidden', !isSaving);
            }
            if (passwordSavingLabel) {
                passwordSavingLabel.classList.toggle('opacity-60', isSaving);
            }
        }

        function setPasswordModalState(isSaving) {
            if (passwordModalConfirm) {
                passwordModalConfirm.disabled = isSaving;
            }
            if (passwordModalSpinner) {
                passwordModalSpinner.classList.toggle('hidden', !isSaving);
            }
            if (passwordModalLabel) {
                passwordModalLabel.classList.toggle('opacity-60', isSaving);
            }
        }

        function getPasswordStrengthScore(password) {
            if (!password) {
                return 0;
            }

            let score = 0;
            if (password.length >= 12) {
                score += 1;
            }
            if (/[a-z]/.test(password)) {
                score += 1;
            }
            if (/[A-Z]/.test(password)) {
                score += 1;
            }
            if (/\d/.test(password)) {
                score += 1;
            }
            if (/[^\da-zA-Z]/.test(password)) {
                score += 1;
            }
            return score;
        }

        function getPasswordStrengthLabel(score) {
            const index = Math.min(passwordStrengthKeys.length - 1, Math.max(0, score - 1));
            const key = passwordStrengthKeys[index];
            if (gta6AuthorConfig.strings && key && gta6AuthorConfig.strings[key]) {
                return gta6AuthorConfig.strings[key];
            }
            return '';
        }

        function updatePasswordStrength(password) {
            const score = getPasswordStrengthScore(password);
            const percent = Math.min(100, Math.max(0, (score / 5) * 100));

            if (passwordStrengthBar) {
                passwordStrengthClasses.forEach((className) => passwordStrengthBar.classList.remove(className));
                if (score > 0) {
                    passwordStrengthBar.classList.add(passwordStrengthClasses[Math.min(passwordStrengthClasses.length - 1, score - 1)]);
                } else {
                    passwordStrengthBar.classList.add(passwordStrengthClasses[0]);
                }
                passwordStrengthBar.style.width = `${percent}%`;
            }

            if (passwordStrengthLabel) {
                passwordStrengthLabel.textContent = password ? getPasswordStrengthLabel(score) : '';
            }

            return score;
        }

        if (newPasswordField) {
            updatePasswordStrength(newPasswordField.value || '');
            newPasswordField.addEventListener('input', () => {
                updatePasswordStrength(newPasswordField.value || '');
            });
        }

        const handlePasswordModalKeydown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                if (!passwordChangeInProgress) {
                    closePasswordModal();
                }
            }
        };

        function openPasswordModal() {
            if (!passwordModal) {
                return;
            }

            passwordModal.classList.remove('hidden');
            passwordModal.setAttribute('aria-hidden', 'false');
            document.addEventListener('keydown', handlePasswordModalKeydown);
            window.setTimeout(() => {
                passwordModalConfirm?.focus();
            }, 0);
        }

        function closePasswordModal() {
            if (!passwordModal) {
                return;
            }

            passwordModal.classList.add('hidden');
            passwordModal.setAttribute('aria-hidden', 'true');
            document.removeEventListener('keydown', handlePasswordModalKeydown);
            setPasswordModalState(false);
            window.setTimeout(() => {
                currentPasswordField?.focus();
            }, 0);
        }

        function resetPasswordForm() {
            if (currentPasswordField) {
                currentPasswordField.value = '';
                currentPasswordField.disabled = false;
            }
            if (newPasswordField) {
                newPasswordField.value = '';
                newPasswordField.disabled = false;
                updatePasswordStrength('');
            }
            if (confirmPasswordField) {
                confirmPasswordField.value = '';
                confirmPasswordField.disabled = false;
            }
        }

        function validatePasswordInputs() {
            const currentValue = currentPasswordField?.value || '';
            const newValue = newPasswordField?.value || '';
            const confirmValue = confirmPasswordField?.value || '';

            if (!currentValue || !newValue || !confirmValue) {
                showToast(gta6AuthorConfig.strings.passwordFieldsRequired, 'warning');
                return null;
            }

            if (newValue !== confirmValue) {
                showToast(gta6AuthorConfig.strings.passwordMismatch, 'warning');
                return null;
            }

            if (currentValue === newValue) {
                showToast(gta6AuthorConfig.strings.passwordSameAsCurrent, 'warning');
                return null;
            }

            const score = updatePasswordStrength(newValue);
            if (score < 5) {
                showToast(gta6AuthorConfig.strings.passwordWeak, 'warning');
                return null;
            }

            return { currentValue, newValue, confirmValue };
        }

        function performPasswordChange(payload) {
            if (!gta6AuthorConfig.ajaxUrl) {
                showToast(gta6AuthorConfig.strings.error, 'error');
                return;
            }

            passwordChangeInProgress = true;
            setPasswordSavingState(true);
            setPasswordModalState(true);

            if (currentPasswordField) {
                currentPasswordField.disabled = true;
            }
            if (newPasswordField) {
                newPasswordField.disabled = true;
            }
            if (confirmPasswordField) {
                confirmPasswordField.disabled = true;
            }

            const formData = new URLSearchParams();
            formData.append('action', 'gta6mods_change_password');
            formData.append('nonce', getPasswordNonce());
            formData.append('current_password', payload.currentValue);
            formData.append('new_password', payload.newValue);
            formData.append('confirm_password', payload.confirmValue);

            fetch(gta6AuthorConfig.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: formData.toString(),
            })
                .then((response) => response.json().then((json) => ({ ok: response.ok, json })).catch(() => ({ ok: response.ok, json: null })))
                .then(({ ok, json }) => {
                    if (!ok || !json || !json.success) {
                        const message = json && json.data && json.data.message ? json.data.message : gta6AuthorConfig.strings.error;
                        throw new Error(message);
                    }

                    if (json.data && json.data.nonce) {
                        setPasswordNonce(json.data.nonce);
                    }

                    const successMessage = json.data && json.data.message ? json.data.message : gta6AuthorConfig.strings.passwordChangeSuccess;
                    passwordChangeInProgress = false;
                    showToast(successMessage, 'success');
                    resetPasswordForm();
                    closePasswordModal();
                })
                .catch((error) => {
                    const message = error && error.message ? error.message : gta6AuthorConfig.strings.error;
                    showToast(message, 'error');
                })
                .finally(() => {
                    passwordChangeInProgress = false;
                    setPasswordSavingState(false);
                    setPasswordModalState(false);
                    if (currentPasswordField) {
                        currentPasswordField.disabled = false;
                    }
                    if (newPasswordField) {
                        newPasswordField.disabled = false;
                    }
                    if (confirmPasswordField) {
                        confirmPasswordField.disabled = false;
                    }
                });
        }

        savePasswordButton?.addEventListener('click', () => {
            if (passwordChangeInProgress) {
                return;
            }

            const payload = validatePasswordInputs();
            if (!payload) {
                return;
            }

            openPasswordModal();
        });

        passwordModalConfirm?.addEventListener('click', () => {
            if (passwordChangeInProgress) {
                return;
            }

            const payload = validatePasswordInputs();
            if (!payload) {
                closePasswordModal();
                return;
            }

            performPasswordChange(payload);
        });

        passwordModalOverlay?.addEventListener('click', () => {
            if (!passwordChangeInProgress) {
                closePasswordModal();
            }
        });

        passwordModalCancelButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                if (!passwordChangeInProgress) {
                    closePasswordModal();
                }
            });
        });

        const dangerZoneSection = document.getElementById('gta6mods-danger-zone');
        const deletionIdleView = dangerZoneSection ? dangerZoneSection.querySelector('[data-danger-view="idle"]') : null;
        const deletionScheduledView = dangerZoneSection ? dangerZoneSection.querySelector('[data-danger-view="scheduled"]') : null;
        const scheduleDeletionButton = document.getElementById('gta6mods-delete-account-button');
        const cancelDeletionButton = document.getElementById('gta6mods-cancel-delete-button');
        const cancelDeletionSpinner = cancelDeletionButton ? cancelDeletionButton.querySelector('[data-cancel-spinner]') : null;
        const cancelDeletionLabel = cancelDeletionButton ? cancelDeletionButton.querySelector('[data-cancel-label]') : null;
        const deleteNowButton = document.getElementById('gta6mods-delete-now-button');
        const deletionRequestModal = document.getElementById('gta6mods-delete-account-modal');
        const deletionRequestOverlay = deletionRequestModal ? deletionRequestModal.querySelector('[data-delete-overlay]') : null;
        const deletionRequestCancelButtons = deletionRequestModal ? deletionRequestModal.querySelectorAll('[data-delete-cancel]') : [];
        const deletionRequestConfirm = deletionRequestModal ? deletionRequestModal.querySelector('[data-delete-confirm]') : null;
        const deletionRequestInput = deletionRequestModal ? deletionRequestModal.querySelector('[data-delete-confirm-input]') : null;
        const deletionRequestError = deletionRequestModal ? deletionRequestModal.querySelector('[data-delete-error]') : null;
        const deletionRequestSpinner = deletionRequestModal ? deletionRequestModal.querySelector('[data-delete-spinner]') : null;
        const deletionRequestLabel = deletionRequestModal ? deletionRequestModal.querySelector('[data-delete-label]') : null;
        const immediateDeletionModal = document.getElementById('gta6mods-delete-account-now-modal');
        const immediateDeletionOverlay = immediateDeletionModal ? immediateDeletionModal.querySelector('[data-delete-now-overlay]') : null;
        const immediateDeletionCancelButtons = immediateDeletionModal ? immediateDeletionModal.querySelectorAll('[data-delete-now-cancel]') : [];
        const immediateDeletionConfirm = immediateDeletionModal ? immediateDeletionModal.querySelector('[data-delete-now-confirm]') : null;
        const immediateDeletionPassword = immediateDeletionModal ? immediateDeletionModal.querySelector('[data-delete-now-password]') : null;
        const immediateDeletionError = immediateDeletionModal ? immediateDeletionModal.querySelector('[data-delete-now-error]') : null;
        const immediateDeletionSpinner = immediateDeletionModal ? immediateDeletionModal.querySelector('[data-delete-now-spinner]') : null;
        const immediateDeletionLabel = immediateDeletionModal ? immediateDeletionModal.querySelector('[data-delete-now-label]') : null;
        const accountDeletionConfig =
            gta6AuthorConfig.accountDeletion && typeof gta6AuthorConfig.accountDeletion === 'object'
                ? gta6AuthorConfig.accountDeletion
                : {};
        const accountDeletionEnabled = Boolean(accountDeletionConfig.enabled);
        const accountDeletionFinalized = Boolean(accountDeletionConfig.finalized);
        const accountDeletionAllowed = accountDeletionEnabled && !accountDeletionFinalized;
        const deletionPhrase = accountDeletionConfig && accountDeletionConfig.phrase ? accountDeletionConfig.phrase : 'Delete my account';

        const accountDeletionState = {
            requested: Boolean(
                accountDeletionAllowed &&
                accountDeletionConfig &&
                accountDeletionConfig.requested
            ),
            status: accountDeletionConfig && accountDeletionConfig.status ? String(accountDeletionConfig.status) : '',
            finalized: accountDeletionFinalized,
            finalizedAt:
                Number(
                    accountDeletionConfig &&
                        typeof accountDeletionConfig.finalizedAt !== 'undefined' &&
                        accountDeletionConfig.finalizedAt
                ) || 0,
        };

        const getAccountDeletionEndpoint = (key) => {
            if (!accountDeletionEndpoints || typeof accountDeletionEndpoints !== 'object') {
                return '';
            }

            const url = accountDeletionEndpoints[key];
            return typeof url === 'string' ? url : '';
        };

        const performAccountDeletionRequest = async (method, key, payload = null) => {
            const endpoint = getAccountDeletionEndpoint(key);

            if (!endpoint) {
                throw new Error(gta6AuthorConfig.strings.error || 'Something went wrong. Please try again.');
            }

            const headers = {
                'X-WP-Nonce': gta6AuthorConfig.nonce || '',
            };

            const options = {
                method,
                credentials: 'same-origin',
                headers,
            };

            if (payload !== null) {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(payload);
            }

            const response = await fetch(endpoint, options);
            const json = await response.json().catch(() => null);

            if (!response.ok || !json) {
                const message = json && (json.message || (json.data && json.data.message))
                    ? json.message || json.data.message
                    : (gta6AuthorConfig.strings.error || 'Something went wrong. Please try again.');
                throw new Error(message);
            }

            return json;
        };

        const requestAccountDeletionRest = (confirmation) => performAccountDeletionRequest('POST', 'request', { confirmation });
        const cancelAccountDeletionRest = () => performAccountDeletionRequest('DELETE', 'cancel');
        const finalizeAccountDeletionRest = (password) => performAccountDeletionRequest('POST', 'finalize', { password });

        function applyAccountDeletionState(data) {
            if (!gta6AuthorConfig.accountDeletion || typeof gta6AuthorConfig.accountDeletion !== 'object') {
                gta6AuthorConfig.accountDeletion = {};
            }

            const requested = Boolean(data && data.requested);
            const requestedAt = data && typeof data.requested_at !== 'undefined' ? Number(data.requested_at) : (data && typeof data.requestedAt !== 'undefined' ? Number(data.requestedAt) : undefined);
            const finalized = Boolean(data && (data.finalized || data.status === 'deleted'));
            const finalizedAt = data && typeof data.finalized_at !== 'undefined' ? Number(data.finalized_at) : (data && typeof data.finalizedAt !== 'undefined' ? Number(data.finalizedAt) : 0);
            const status = data && typeof data.status === 'string' ? data.status : '';

            accountDeletionState.requested = requested;
            accountDeletionState.finalized = finalized;
            accountDeletionState.finalizedAt = finalized ? (finalizedAt || Date.now()) : 0;
            accountDeletionState.status = status;

            gta6AuthorConfig.accountDeletion.requested = accountDeletionState.requested;
            if (typeof requestedAt !== 'undefined') {
                gta6AuthorConfig.accountDeletion.requestedAt = requestedAt;
            }
            gta6AuthorConfig.accountDeletion.finalized = accountDeletionState.finalized;
            gta6AuthorConfig.accountDeletion.finalizedAt = accountDeletionState.finalizedAt;
            gta6AuthorConfig.accountDeletion.status = accountDeletionState.status;
        }

        function updateAccountDeletionView() {
            if (!dangerZoneSection) {
                return;
            }

            if (!accountDeletionAllowed || accountDeletionState.finalized) {
                dangerZoneSection.classList.add('hidden');
                return;
            }

            dangerZoneSection.classList.remove('hidden');

            if (deletionIdleView) {
                deletionIdleView.classList.toggle('hidden', accountDeletionState.requested);
            }
            if (deletionScheduledView) {
                deletionScheduledView.classList.toggle('hidden', !accountDeletionState.requested);
            }
        }

        function setCancellationLoading(isLoading) {
            if (!cancelDeletionButton) {
                return;
            }

            cancelDeletionButton.disabled = isLoading;
            if (cancelDeletionSpinner) {
                cancelDeletionSpinner.classList.toggle('hidden', !isLoading);
            }
            if (cancelDeletionLabel) {
                cancelDeletionLabel.classList.toggle('opacity-60', isLoading);
            }
        }

        function openDeletionRequestModal() {
            if (!deletionRequestModal) {
                return;
            }

            deletionRequestModal.classList.remove('hidden');
            deletionRequestModal.setAttribute('aria-hidden', 'false');
            window.setTimeout(() => {
                deletionRequestInput?.focus();
            }, 20);
        }

        function closeDeletionRequestModal() {
            if (!deletionRequestModal) {
                return;
            }

            deletionRequestModal.classList.add('hidden');
            deletionRequestModal.setAttribute('aria-hidden', 'true');
            if (deletionRequestInput) {
                deletionRequestInput.disabled = false;
                deletionRequestInput.value = '';
            }
            if (deletionRequestError) {
                deletionRequestError.classList.add('hidden');
            }
            setDeletionRequestLoading(false);
        }

        function setDeletionRequestLoading(isLoading) {
            if (deletionRequestConfirm) {
                deletionRequestConfirm.disabled = isLoading;
            }
            if (deletionRequestSpinner) {
                deletionRequestSpinner.classList.toggle('hidden', !isLoading);
            }
            if (deletionRequestLabel) {
                deletionRequestLabel.classList.toggle('opacity-60', isLoading);
            }
            if (deletionRequestInput) {
                deletionRequestInput.disabled = isLoading;
            }
        }

        function openImmediateDeletionModal() {
            if (!immediateDeletionModal) {
                return;
            }

            immediateDeletionModal.classList.remove('hidden');
            immediateDeletionModal.setAttribute('aria-hidden', 'false');
            if (immediateDeletionError) {
                immediateDeletionError.classList.add('hidden');
            }
            if (immediateDeletionPassword) {
                immediateDeletionPassword.disabled = false;
                immediateDeletionPassword.value = '';
                window.setTimeout(() => {
                    immediateDeletionPassword?.focus();
                }, 20);
            }
            setImmediateDeletionLoading(false);
        }

        function closeImmediateDeletionModal() {
            if (!immediateDeletionModal) {
                return;
            }

            immediateDeletionModal.classList.add('hidden');
            immediateDeletionModal.setAttribute('aria-hidden', 'true');
            if (immediateDeletionPassword) {
                immediateDeletionPassword.disabled = false;
                immediateDeletionPassword.value = '';
            }
            if (immediateDeletionError) {
                immediateDeletionError.classList.add('hidden');
            }
            setImmediateDeletionLoading(false);
        }

        function setImmediateDeletionLoading(isLoading) {
            if (immediateDeletionConfirm) {
                immediateDeletionConfirm.disabled = isLoading;
            }
            if (immediateDeletionSpinner) {
                immediateDeletionSpinner.classList.toggle('hidden', !isLoading);
            }
            if (immediateDeletionLabel) {
                immediateDeletionLabel.classList.toggle('opacity-60', isLoading);
            }
            if (immediateDeletionPassword) {
                immediateDeletionPassword.disabled = isLoading;
            }
        }

        function formatImmediateDeletionCountdown(seconds) {
            const normalizedSeconds = Number.isFinite(seconds) ? Math.max(1, Math.floor(seconds)) : 1;
            const template = gta6AuthorConfig.strings.deletionImmediateCountdownTemplate
                || gta6AuthorConfig.strings.deletionImmediateSuccess
                || gta6AuthorConfig.strings.saved
                || '';
            const singular = gta6AuthorConfig.strings.deletionImmediateCountdownUnitSingular || '%s second';
            const plural = gta6AuthorConfig.strings.deletionImmediateCountdownUnitPlural || '%s seconds';
            const unitTemplate = normalizedSeconds === 1 ? singular : plural;
            const replacePlaceholder = (text, value) => {
                if (!text || typeof text !== 'string') {
                    return value;
                }

                return text.replace(/%([0-9]+\$)?s/g, value);
            };

            const unitLabel = replacePlaceholder(unitTemplate, normalizedSeconds);

            if (template && /%([0-9]+\$)?s/.test(template)) {
                return replacePlaceholder(template, unitLabel);
            }

            if (template) {
                return `${template} ${unitLabel}`.trim();
            }

            return unitLabel;
        }

        if (dangerZoneSection && gta6AuthorConfig.isOwner && accountDeletionAllowed) {
            updateAccountDeletionView();

            scheduleDeletionButton?.addEventListener('click', () => {
                if (deletionRequestError) {
                    deletionRequestError.classList.add('hidden');
                }

                openDeletionRequestModal();
            });

            deletionRequestInput?.addEventListener('input', () => {
                if (deletionRequestError) {
                    deletionRequestError.classList.add('hidden');
                }
            });

            deletionRequestInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    deletionRequestConfirm?.click();
                }
            });

            deletionRequestConfirm?.addEventListener('click', () => {
                const confirmation = deletionRequestInput ? deletionRequestInput.value.trim() : '';
                if (confirmation !== deletionPhrase) {
                    if (deletionRequestError) {
                        const message = gta6AuthorConfig.strings.deletionConfirmationMismatch || gta6AuthorConfig.strings.deletionConfirmationRequired || gta6AuthorConfig.strings.error;
                        deletionRequestError.textContent = message;
                        deletionRequestError.classList.remove('hidden');
                    } else {
                        showToast(gta6AuthorConfig.strings.deletionConfirmationRequired || gta6AuthorConfig.strings.error, 'error');
                    }
                    return;
                }

                setDeletionRequestLoading(true);
                requestAccountDeletionRest(confirmation)
                    .then((data) => {
                        if (data && data.deletion) {
                            applyAccountDeletionState(data.deletion);
                        }

                        updateAccountDeletionView();
                        closeDeletionRequestModal();

                        const message =
                            (data && data.message)
                                ? data.message
                                : (gta6AuthorConfig.strings.deletionRequestSuccess || gta6AuthorConfig.strings.saved);
                        showToast(message, 'success');
                    })
                    .catch((error) => {
                        const message = error && error.message ? error.message : gta6AuthorConfig.strings.error;
                        showToast(message, 'error');
                    })
                    .finally(() => {
                        setDeletionRequestLoading(false);
                    });
            });

            deletionRequestOverlay?.addEventListener('click', () => {
                if (deletionRequestConfirm && !deletionRequestConfirm.disabled) {
                    closeDeletionRequestModal();
                }
            });

            deletionRequestCancelButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (deletionRequestConfirm && deletionRequestConfirm.disabled) {
                        return;
                    }
                    closeDeletionRequestModal();
                });
            });

            cancelDeletionButton?.addEventListener('click', () => {
                if (!accountDeletionState.requested) {
                    return;
                }

                setCancellationLoading(true);

                cancelAccountDeletionRest()
                    .then((data) => {
                        if (data && data.deletion) {
                            applyAccountDeletionState(data.deletion);
                        } else {
                            applyAccountDeletionState({
                                requested: false,
                                finalized: false,
                                status: '',
                                finalized_at: 0,
                            });
                        }

                        updateAccountDeletionView();

                        const message =
                            (data && data.message)
                                ? data.message
                                : (gta6AuthorConfig.strings.deletionCancelSuccess || gta6AuthorConfig.strings.saved);
                        showToast(message, 'success');
                    })
                    .catch((error) => {
                        const message = error && error.message ? error.message : gta6AuthorConfig.strings.error;
                        showToast(message, 'error');
                    })
                    .finally(() => {
                        setCancellationLoading(false);
                    });
            });

            deleteNowButton?.addEventListener('click', () => {
                if (!accountDeletionState.requested) {
                    showToast(gta6AuthorConfig.strings.error, 'error');
                    return;
                }

                openImmediateDeletionModal();
            });

            immediateDeletionPassword?.addEventListener('input', () => {
                if (immediateDeletionError) {
                    immediateDeletionError.classList.add('hidden');
                }
            });

            immediateDeletionPassword?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    immediateDeletionConfirm?.click();
                }
            });

            immediateDeletionConfirm?.addEventListener('click', () => {
                const password = immediateDeletionPassword ? immediateDeletionPassword.value : '';
                if (!password) {
                    if (immediateDeletionError) {
                        const message = gta6AuthorConfig.strings.deletionPasswordRequired || gta6AuthorConfig.strings.error;
                        immediateDeletionError.textContent = message;
                        immediateDeletionError.classList.remove('hidden');
                    } else {
                        showToast(gta6AuthorConfig.strings.deletionPasswordRequired || gta6AuthorConfig.strings.error, 'error');
                    }
                    immediateDeletionPassword?.focus();
                    return;
                }

                setImmediateDeletionLoading(true);

                finalizeAccountDeletionRest(password)
                    .then((data) => {
                        if (data && data.deletion) {
                            applyAccountDeletionState(data.deletion);
                        }

                        const message =
                            (data && data.message)
                                ? data.message
                                : (gta6AuthorConfig.strings.deletionImmediateSuccess || gta6AuthorConfig.strings.saved);
                        const redirectUrl = data && data.redirect ? String(data.redirect) : '';

                        closeImmediateDeletionModal();

                        if (redirectUrl) {
                            const countdownSeconds = 5;
                            const toastInstance = showToast(
                                formatImmediateDeletionCountdown(countdownSeconds),
                                'success',
                                { duration: (countdownSeconds + 2) * 1000 }
                            );

                            let countdownTimer = null;

                            if (toastInstance && toastInstance.message) {
                                let remaining = countdownSeconds;

                                const updateCountdown = (value) => {
                                    toastInstance.message.textContent = formatImmediateDeletionCountdown(value);
                                };

                                updateCountdown(remaining);

                                countdownTimer = window.setInterval(() => {
                                    remaining -= 1;
                                    if (remaining > 0) {
                                        updateCountdown(remaining);
                                    } else {
                                        window.clearInterval(countdownTimer);
                                    }
                                }, 1000);
                            } else {
                                showToast(message, 'success');
                            }

                            window.setTimeout(() => {
                                if (countdownTimer) {
                                    window.clearInterval(countdownTimer);
                                }
                                window.location.href = redirectUrl;
                            }, countdownSeconds * 1000);
                        } else {
                            showToast(message, 'success');
                            window.setTimeout(() => {
                                window.location.reload();
                            }, 800);
                        }
                    })
                    .catch((error) => {
                        const message = error && error.message ? error.message : gta6AuthorConfig.strings.error;
                        if (immediateDeletionError) {
                            immediateDeletionError.textContent = message;
                            immediateDeletionError.classList.remove('hidden');
                        } else {
                            showToast(message, 'error');
                        }
                    })
                    .finally(() => {
                        setImmediateDeletionLoading(false);
                        if (immediateDeletionPassword) {
                            immediateDeletionPassword.disabled = false;
                        }
                    });
            });

            immediateDeletionOverlay?.addEventListener('click', () => {
                if (immediateDeletionConfirm && !immediateDeletionConfirm.disabled) {
                    closeImmediateDeletionModal();
                }
            });

            immediateDeletionCancelButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (immediateDeletionConfirm && immediateDeletionConfirm.disabled) {
                        return;
                    }
                    closeImmediateDeletionModal();
                });
            });
        }

        let avatarObjectUrl = null;
        let avatarFile = null;
        let selectedPreset = null;
        let avatarPresetDirty = false;
        let avatarDeleteInProgress = false;
        const presetButtons = [];
        const presetSelectedClasses = ['ring-2', 'ring-pink-500', 'ring-offset-2'];

        if (currentAvatar && currentAvatar.type === 'preset' && currentAvatar.preset) {
            selectedPreset = currentAvatar.preset;
        }

        function clearAvatarObjectUrl() {
            if (avatarObjectUrl) {
                URL.revokeObjectURL(avatarObjectUrl);
                avatarObjectUrl = null;
            }
        }

        function canDeleteCurrentAvatar() {
            if (!currentAvatar || typeof currentAvatar !== 'object') {
                return false;
            }
            return currentAvatar.type === 'custom' && Number(currentAvatar.attachmentId || 0) > 0 && !avatarFile;
        }

        function updateAvatarDeleteButton() {
            if (!avatarDeleteButton) {
                return;
            }

            if (canDeleteCurrentAvatar()) {
                avatarDeleteButton.classList.remove('hidden');
                avatarDeleteButton.setAttribute('aria-hidden', 'false');
                avatarDeleteButton.disabled = false;
            } else {
                avatarDeleteButton.classList.add('hidden');
                avatarDeleteButton.setAttribute('aria-hidden', 'true');
                avatarDeleteButton.disabled = true;
            }
        }

        const handleAvatarDeleteKeydown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeAvatarDeleteModal();
            }
        };

        function openAvatarDeleteModal() {
            if (!avatarDeleteModal || !canDeleteCurrentAvatar()) {
                return;
            }

            avatarDeleteModal.classList.remove('hidden');
            avatarDeleteModal.setAttribute('aria-hidden', 'false');
            document.addEventListener('keydown', handleAvatarDeleteKeydown);
            window.setTimeout(() => {
                avatarDeleteConfirm?.focus();
            }, 0);
        }

        function closeAvatarDeleteModal() {
            if (!avatarDeleteModal) {
                return;
            }

            avatarDeleteModal.classList.add('hidden');
            avatarDeleteModal.setAttribute('aria-hidden', 'true');
            document.removeEventListener('keydown', handleAvatarDeleteKeydown);
        }

        function requestAvatarDeletion() {
            return fetch(`${gta6AuthorConfig.restBase}/author/avatar`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': gta6AuthorConfig.nonce,
                },
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('avatar_delete_failed');
                }
                return response.json();
            });
        }

        function performAvatarDeletion() {
            if (!canDeleteCurrentAvatar() || avatarDeleteInProgress) {
                closeAvatarDeleteModal();
                return;
            }

            avatarDeleteInProgress = true;
            if (avatarDeleteConfirm) {
                avatarDeleteConfirm.disabled = true;
            }
            if (avatarDeleteButton) {
                avatarDeleteButton.disabled = true;
            }

            const pendingPresetSelection = avatarPresetDirty ? selectedPreset : null;

            requestAvatarDeletion()
                .then((data) => {
                    closeAvatarDeleteModal();
                    clearAvatarObjectUrl();
                    avatarFile = null;
                    if (avatarInput) {
                        avatarInput.value = '';
                    }

                    const avatarData = data && data.avatar ? data.avatar : null;
                    updateCurrentAvatar(avatarData || {});

                    if (pendingPresetSelection) {
                        selectedPreset = pendingPresetSelection;
                        avatarPresetDirty = true;
                        let presetUrl = '';
                        presetButtons.forEach((button) => {
                            if (!presetUrl && button.dataset.avatarId === selectedPreset && button.dataset.avatarUrl) {
                                presetUrl = button.dataset.avatarUrl;
                            }
                        });
                        if (presetUrl) {
                            updateAvatarPreview(presetUrl);
                        }
                        refreshPresetSelectionUI();
                    } else {
                        avatarPresetDirty = false;
                        selectedPreset = null;
                        refreshPresetSelectionUI();
                    }

                    updateAvatarDeleteButton();

                    if (gta6AuthorConfig.strings && gta6AuthorConfig.strings.avatarDeleteSuccess) {
                        showToast(gta6AuthorConfig.strings.avatarDeleteSuccess, 'success');
                    }
                })
                .catch((error) => {
                    console.error(error);
                    if (gta6AuthorConfig.strings && gta6AuthorConfig.strings.avatarDeleteFailed) {
                        showToast(gta6AuthorConfig.strings.avatarDeleteFailed, 'error');
                    }
                })
                .finally(() => {
                    avatarDeleteInProgress = false;
                    if (avatarDeleteConfirm) {
                        avatarDeleteConfirm.disabled = false;
                    }
                    if (avatarDeleteButton) {
                        avatarDeleteButton.disabled = false;
                    }
                });
        }

        function getCurrentAvatarUrl() {
            if (currentAvatar && typeof currentAvatar === 'object') {
                return currentAvatar.url || currentAvatar.defaultUrl || '';
            }
            return '';
        }

        function updateAvatarPreview(url) {
            if (!avatarPreview) {
                return;
            }

            const displayUrl = url || getCurrentAvatarUrl();
            if (displayUrl) {
                avatarPreview.src = displayUrl;
            }
        }

        function refreshPresetSelectionUI() {
            presetButtons.forEach((button) => {
                const isActive = !!selectedPreset && button.dataset.avatarId === selectedPreset;
                presetSelectedClasses.forEach((className) => {
                    if (isActive) {
                        button.classList.add(className);
                    } else {
                        button.classList.remove(className);
                    }
                });
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        function updateCurrentAvatar(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            currentAvatar = {
                ...currentAvatar,
                ...data,
            };

            if (!currentAvatar.defaultUrl && data.defaultUrl) {
                currentAvatar.defaultUrl = data.defaultUrl;
            }

            gta6AuthorConfig.avatar = { ...currentAvatar };

            if (currentAvatar.type === 'preset') {
                selectedPreset = currentAvatar.preset || null;
            } else {
                selectedPreset = null;
            }

            refreshPresetSelectionUI();
            updateAvatarPreview(currentAvatar.url || currentAvatar.defaultUrl || '');
            updateAvatarDeleteButton();
        }

        function uploadAvatar(file) {
            const formData = new FormData();
            formData.append('avatar', file, file.name);

            return fetch(`${gta6AuthorConfig.restBase}/author/avatar`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': gta6AuthorConfig.nonce,
                },
                body: formData,
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('avatar_upload_failed');
                }
                return response.json();
            });
        }

        updateAvatarPreview(getCurrentAvatarUrl());
        updateAvatarDeleteButton();

        if (presetGrid) {
            const buttons = presetGrid.querySelectorAll('[data-avatar-id]');
            buttons.forEach((button) => {
                presetButtons.push(button);
                button.addEventListener('click', () => {
                    const id = button.dataset.avatarId;
                    const url = button.dataset.avatarUrl;
                    if (!id || !url) {
                        return;
                    }

                    if (selectedPreset === id) {
                        selectedPreset = null;
                        avatarPresetDirty = true;
                        updateAvatarPreview(getCurrentAvatarUrl());
                    } else {
                        selectedPreset = id;
                        avatarPresetDirty = true;
                        updateAvatarPreview(url);
                    }

                    avatarFile = null;
                    if (avatarInput) {
                        avatarInput.value = '';
                    }
                    clearAvatarObjectUrl();
                    refreshPresetSelectionUI();
                    updateAvatarDeleteButton();
                });
            });

            refreshPresetSelectionUI();
        }

        if (!isRestAvatarEnabled()) {
            avatarInput?.addEventListener('change', () => {
                const files = avatarInput.files;
                clearAvatarObjectUrl();

                if (!files || !files.length) {
                    avatarFile = null;
                    updateAvatarPreview(getCurrentAvatarUrl());
                    updateAvatarDeleteButton();
                    return;
                }

                const file = files[0];
                if (file.size > avatarMaxSize) {
                    showToast(gta6AuthorConfig.strings.avatarTooLarge, 'warning');
                    avatarInput.value = '';
                    avatarFile = null;
                    updateAvatarPreview(getCurrentAvatarUrl());
                    updateAvatarDeleteButton();
                    return;
                }

                avatarFile = file;
                avatarPresetDirty = false;
                selectedPreset = null;
                avatarObjectUrl = URL.createObjectURL(file);
                updateAvatarPreview(avatarObjectUrl);
                refreshPresetSelectionUI();
                updateAvatarDeleteButton();
            });

            avatarDeleteButton?.addEventListener('click', () => {
                openAvatarDeleteModal();
            });

            avatarDeleteOverlay?.addEventListener('click', () => {
                if (!avatarDeleteInProgress) {
                    closeAvatarDeleteModal();
                }
            });

            avatarDeleteCancelButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (!avatarDeleteInProgress) {
                        closeAvatarDeleteModal();
                    }
                });
            });

            avatarDeleteConfirm?.addEventListener('click', () => {
                performAvatarDeletion();
            });
        }

        function updateBannerUI(url) {
            if (bannerPreview) {
                const placeholder = bannerPreview.querySelector('.gta6mods-banner-empty');
                if (url) {
                    bannerPreview.classList.add('has-banner');
                    bannerPreview.style.backgroundImage = `url('${url}')`;
                    placeholder?.classList.add('hidden');
                    if (bannerRemove) {
                        bannerRemove.classList.remove('hidden');
                        bannerRemove.style.display = '';
                        bannerRemove.disabled = false;
                    }
                } else {
                    bannerPreview.classList.remove('has-banner');
                    bannerPreview.style.removeProperty('background-image');
                    if (placeholder) {
                        if (bannerPreview.dataset.emptyText) {
                            placeholder.textContent = bannerPreview.dataset.emptyText;
                        }
                        placeholder.classList.remove('hidden');
                    }
                    if (bannerRemove) {
                        bannerRemove.classList.add('hidden');
                        bannerRemove.style.display = 'none';
                        bannerRemove.disabled = false;
                    }
                }
            }

            if (headerBackground) {
                if (url) {
                    const serializedUrl = JSON.stringify(url);
                    headerBackground.style.setProperty('--header-bg-image', `url(${serializedUrl})`);
                } else {
                    headerBackground.style.removeProperty('--header-bg-image');
                }
            }

            gta6AuthorConfig.bannerUrl = url || '';
        }

        if (bannerPreview) {
            updateBannerUI(gta6AuthorConfig.bannerUrl);
        }

        if (!isRestBannerEnabled()) {
            bannerInput?.addEventListener('change', () => {
                const files = bannerInput.files;
                if (!files || !files.length) {
                    return;
                }
                const file = files[0];
                if (file.size > maxBannerSize) {
                    showToast(gta6AuthorConfig.strings.bannerTooLarge, 'warning');
                    bannerInput.value = '';
                    return;
                }

                const formData = new FormData();
                formData.append('banner', file, file.name);

                bannerInput.disabled = true;
                if (bannerRemove) {
                    bannerRemove.disabled = true;
                }

                fetch(`${gta6AuthorConfig.restBase}/author/banner`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': gta6AuthorConfig.nonce,
                    },
                    body: formData,
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.json();
                    })
                    .then((data) => {
                        if (data && data.url) {
                            updateBannerUI(data.url);
                            showToast(gta6AuthorConfig.strings.bannerUploaded, 'success');
                        } else {
                            throw new Error('Missing URL');
                        }
                    })
                    .catch(() => {
                        showToast(gta6AuthorConfig.strings.error, 'error');
                    })
                    .finally(() => {
                        bannerInput.disabled = false;
                        bannerInput.value = '';
                        if (bannerRemove) {
                            bannerRemove.disabled = false;
                        }
                    });
            });

            bannerRemove?.addEventListener('click', () => {
                if (!window.confirm(gta6AuthorConfig.strings.bannerRemoveConfirm || 'Remove banner?')) {
                    return;
                }

                bannerRemove.disabled = true;

                fetch(`${gta6AuthorConfig.restBase}/author/banner`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': gta6AuthorConfig.nonce,
                    },
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.json();
                    })
                    .then(() => {
                        updateBannerUI('');
                        showToast(gta6AuthorConfig.strings.bannerRemoved, 'success');
                    })
                    .catch(() => {
                        showToast(gta6AuthorConfig.strings.error, 'error');
                    })
                    .finally(() => {
                        bannerRemove.disabled = false;
                    });
            });
        }

        function postSettings(payload) {
            return fetch(`${gta6AuthorConfig.restBase}/author/settings`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': gta6AuthorConfig.nonce,
                },
                body: JSON.stringify(payload),
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                return response.json();
            });
        }

        if (bioField) {
            const bioCounter = document.getElementById('gta6mods-bio-counter');
            const updateBioCounter = () => {
                if (bioCounter) {
                    bioCounter.textContent = `${bioField.value.length}/${bioMaxLength}`;
                }
            };

            updateBioCounter();

            bioField.addEventListener('input', () => {
                if (bioField.value.length > bioMaxLength) {
                    bioField.value = bioField.value.slice(0, bioMaxLength);
                }
                updateBioCounter();
            });
        }

        saveProfile?.addEventListener('click', () => {
            if (saveProfile.disabled) {
                return;
            }

            saveProfile.disabled = true;

            const bioValue = bioField?.value ? bioField.value.slice(0, bioMaxLength) : '';
            if (bioField && bioField.value !== bioValue) {
                bioField.value = bioValue;
            }

            const payload = {
                email: emailField?.value || '',
                bio: bioValue,
            };

            const shouldHandleAvatar = !isRestAvatarEnabled();

            if (shouldHandleAvatar && avatarPresetDirty) {
                if (selectedPreset) {
                    payload.avatarPreset = selectedPreset;
                } else if (!avatarFile) {
                    payload.clearAvatar = true;
                }
            }

            (async () => {
                let success = false;

                try {
                    if (shouldHandleAvatar && avatarFile) {
                        const uploadResponse = await uploadAvatar(avatarFile);
                        if (uploadResponse && uploadResponse.avatar) {
                            updateCurrentAvatar(uploadResponse.avatar);
                        }
                        success = true;
                        avatarFile = null;
                        if (avatarInput) {
                            avatarInput.value = '';
                        }
                        clearAvatarObjectUrl();
                    }

                    const responseData = await postSettings(payload);
                    if (shouldHandleAvatar && responseData && responseData.avatar) {
                        updateCurrentAvatar(responseData.avatar);
                    }
                    success = true;
                    if (shouldHandleAvatar) {
                        avatarPresetDirty = false;
                    }

                    if (success) {
                        showToast(gta6AuthorConfig.strings.saved, 'success');
                    }
                } catch (error) {
                    console.error(error);
                    if (error && error.message === 'avatar_upload_failed') {
                        showToast(gta6AuthorConfig.strings.avatarUploadFailed, 'error');
                    } else {
                        showToast(gta6AuthorConfig.strings.error, 'error');
                    }
                    if (shouldHandleAvatar && !avatarFile) {
                        updateAvatarPreview(getCurrentAvatarUrl());
                    }
                } finally {
                    saveProfile.disabled = false;
                }
            })();
        });

        saveLinks?.addEventListener('click', () => {
            if (!linksWrapper) {
                return;
            }
            const linkInputs = linksWrapper.querySelectorAll('[data-link-key]');
            const links = {};
            linkInputs.forEach((input) => {
                links[input.dataset.linkKey] = input.value.trim();
            });
            saveLinks.disabled = true;
            postSettings({ links })
                .then((data) => {
                    if (data && data.avatar) {
                        updateCurrentAvatar(data.avatar);
                    }
                    showToast(gta6AuthorConfig.strings.saved, 'success');
                })
                .catch(() => {
                    showToast(gta6AuthorConfig.strings.error, 'error');
                })
                .finally(() => {
                    saveLinks.disabled = false;
                });
        });

        if (typeof window !== 'undefined') {
            window.GTAModsAuthorProfile = window.GTAModsAuthorProfile || {};
            window.GTAModsAuthorProfile.requestDeletion = requestAccountDeletionRest;
            window.GTAModsAuthorProfile.cancelDeletion = cancelAccountDeletionRest;
            window.GTAModsAuthorProfile.finalizeDeletion = finalizeAccountDeletionRest;
        }
    }
});
</script>

<?php
get_footer();

remove_filter('pre_get_document_title', $profile_title_filter, 100);
remove_action('wp_head', $profile_head_action, 1);
?>
