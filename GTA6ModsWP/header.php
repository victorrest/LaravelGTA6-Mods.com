<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_user_id        = (int) get_current_user_id();
$notification_owner_id  = $current_user_id > 0 ? $current_user_id : 0;
$notifications_tab_url  = $notification_owner_id > 0
    ? gta6mods_get_author_profile_tab_url($notification_owner_id, 'notifications')
    : '';
$current_user           = $notification_owner_id > 0 ? get_user_by('id', $notification_owner_id) : null;
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('text-gray-700'); ?>>
<?php wp_body_open(); ?>
<header class="shadow-lg">
    <div class="header-background bg-cover bg-center" aria-hidden="true"></div>
    <div class="header-content">
        <div class="header-top-bar">
            <div class="container mx-auto px-4 flex items-center justify-between py-2">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="logo-font" aria-label="<?php esc_attr_e('Ugrás a főoldalra', 'gta6-mods'); ?>">
                    <?php bloginfo('name'); ?>
                </a>
                <div class="flex items-center space-x-4">
                    <a href="<?php echo esc_url( home_url( '/upload/' ) ); ?>" title="<?php esc_attr_e( 'Upload a new GTA 6 mod', 'gta6-mods' ); ?>" rel="bookmark" aria-label="<?php esc_attr_e( 'Upload a new GTA 6 mod', 'gta6-mods' ); ?>" class="hidden md:flex text-white text-sm font-medium bg-white/10 rounded-full px-3 py-1 transition hover:bg-white/20 hover:shadow-[0_0_15px_rgba(111,30,118,0.65)] items-center gap-x-2">
                        <i class="fa-solid fa-upload"></i>
                        <span><?php esc_html_e('Upload', 'gta6-mods'); ?></span>
                    </a>
                    <a class="text-white transition hover:opacity-75" href="<?php echo esc_url(home_url('/?s=')); ?>" aria-label="<?php esc_attr_e('Keresés', 'gta6-mods'); ?>">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </a>
                    <?php if ($notification_owner_id > 0 && $current_user instanceof WP_User) : ?>
                        <?php
                        $unread_count       = gta6mods_get_unread_count_cached($notification_owner_id);
                        $notification_label = __('Show notifications', 'gta6-mods');

                        if ($unread_count > 0) {
                            $notification_label = sprintf(
                                /* translators: %s: number of unread notifications */
                                _n(
                                    'Show notifications (%s unread)',
                                    'Show notifications (%s unread)',
                                    $unread_count,
                                    'gta6-mods'
                                ),
                                number_format_i18n($unread_count)
                            );
                        }

                        $account_links = [
                            [
                                'label' => __('My profile', 'gta6-mods'),
                                'url'   => gta6mods_get_author_profile_tab_url($notification_owner_id, 'overview'),
                                'icon'  => 'fa-solid fa-user',
                            ],
                            [
                                'label' => __('Uploads', 'gta6-mods'),
                                'url'   => gta6mods_get_author_profile_tab_url($notification_owner_id, 'uploads'),
                                'icon'  => 'fa-solid fa-cloud-arrow-up',
                            ],
                            [
                                'label' => __('Comments', 'gta6-mods'),
                                'url'   => gta6mods_get_author_profile_tab_url($notification_owner_id, 'comments'),
                                'icon'  => 'fa-solid fa-comments',
                            ],
                            [
                                'label' => __('Notifications', 'gta6-mods'),
                                'url'   => $notifications_tab_url,
                                'icon'  => 'fa-solid fa-bell',
                            ],
                            [
                                'label' => __('Bookmarks', 'gta6-mods'),
                                'url'   => gta6mods_get_author_profile_tab_url($notification_owner_id, 'bookmarks'),
                                'icon'  => 'fa-solid fa-bookmark',
                            ],
                            [
                                'label' => __('Settings', 'gta6-mods'),
                                'url'   => gta6mods_get_author_profile_tab_url($notification_owner_id, 'settings'),
                                'icon'  => 'fa-solid fa-gear',
                            ],
                            [
                                'label' => __('Logout', 'gta6-mods'),
                                'url'   => wp_logout_url(home_url('/')),
                                'icon'  => 'fa-solid fa-arrow-right-from-bracket',
                            ],
                        ];

                        $account_links = array_values(array_filter(
                            $account_links,
                            static function ($link) {
                                return !empty($link['url']);
                            }
                        ));

                        $avatar_alt = sprintf(
                            /* translators: %s: user display name */
                            __('Avatar of %s', 'gta6-mods'),
                            $current_user->display_name
                        );
                        $avatar_html = get_avatar(
                            $notification_owner_id,
                            36,
                            '',
                            $avatar_alt,
                            ['class' => 'h-9 w-9 rounded-full object-cover']
                        );
                        ?>
                        <div id="notifications-container" class="relative" data-notifications-owner="<?php echo esc_attr($notification_owner_id); ?>">
                            <button id="notifications-btn" class="text-white transition hover:opacity-75 relative" type="button" aria-expanded="false" aria-controls="notifications-dropdown" aria-label="<?php echo esc_attr($notification_label); ?>" data-unread-count="<?php echo esc_attr($unread_count); ?>">
                                <i class="fa-solid fa-bell fa-lg"></i>
                                <span data-notification-badge class="absolute -top-1 -right-1 flex h-3 w-3<?php echo $unread_count > 0 ? '' : ' hidden'; ?>" aria-hidden="true">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-700 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                                </span>
                            </button>
                            <div id="notifications-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-lg shadow-xl z-50 border text-gray-700" aria-hidden="true">
                                <div class="p-3 border-b flex justify-between items-center">
                                    <h3 class="font-bold text-gray-900"><?php esc_html_e('Notifications', 'gta6-mods'); ?></h3>
                                    <button type="button" class="text-sm text-pink-600 hover:underline whitespace-nowrap flex-shrink-0" data-action="mark-all-read">
                                        <?php esc_html_e('Mark all as read', 'gta6-mods'); ?>
                                    </button>
                                </div>
                                <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto" data-async-content="notifications" data-loaded="0" data-loading="0"></div>
                                <div class="p-2 bg-gray-50 text-center rounded-b-lg">
                                    <a href="<?php echo esc_url($notifications_tab_url ? $notifications_tab_url : '#'); ?>" class="text-sm font-semibold text-pink-600 hover:underline">
                                        <?php esc_html_e('View all notifications', 'gta6-mods'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div id="account-menu" class="relative">
                            <button id="account-menu-button" class="flex items-center gap-2 rounded-full focus:outline-none" type="button" aria-haspopup="true" aria-controls="account-menu-dropdown" aria-expanded="false">
                                <span class="sr-only"><?php esc_html_e('Open account menu', 'gta6-mods'); ?></span>
                                <?php echo $avatar_html; ?>
                                <i class="fa-solid fa-chevron-down hidden md:inline-block text-white text-xs"></i>
                            </button>
                            <div id="account-menu-dropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-lg shadow-xl z-50 border text-gray-700" role="menu" aria-hidden="true">
                                <div class="px-4 py-3 border-b">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo esc_html($current_user->display_name); ?></p>
                                    <?php if (!empty($current_user->user_email)) : ?>
                                        <p class="text-xs text-gray-500 truncate"><?php echo esc_html($current_user->user_email); ?></p>
                                    <?php endif; ?>
                                </div>
                                <nav class="py-1" aria-label="<?php esc_attr_e('Account menu', 'gta6-mods'); ?>">
                                    <?php foreach ($account_links as $link) : ?>
                                        <a href="<?php echo esc_url($link['url']); ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100" role="menuitem">
                                            <i class="<?php echo esc_attr($link['icon']); ?> text-gray-400"></i>
                                            <span><?php echo esc_html($link['label']); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </nav>
                            </div>
                        </div>
                    <script>
                        (function (count) {
                            if (!Array.isArray(window.faviconBadgeQueue)) {
                                window.faviconBadgeQueue = [];
                            }

                            if (window.faviconBadge && typeof window.faviconBadge.update === 'function') {
                                if (count > 0) {
                                    window.faviconBadge.update(count);
                                } else if (typeof window.faviconBadge.reset === 'function') {
                                    window.faviconBadge.reset();
                                }
                            } else {
                                window.faviconBadgeQueue.push(count);
                            }
                        })(<?php echo (int) $unread_count; ?>);
                    </script>
                    <?php else : ?>
                    <a class="text-white transition hover:opacity-75" href="<?php echo esc_url(wp_login_url()); ?>" aria-label="<?php esc_attr_e('Fiók', 'gta6-mods'); ?>">
                        <i class="fa-solid fa-circle-user fa-lg"></i>
                    </a>
                    <?php endif; ?>
                    <button id="mobile-menu-button" class="md:hidden text-white transition hover:opacity-75 focus:outline-none" aria-label="<?php esc_attr_e('Mobil menü megnyitása', 'gta6-mods'); ?>">
                        <i class="fa-solid fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="header-nav-bar">
            <div class="container mx-auto px-0 md:px-4 relative carousel-wrapper">
                <?php if (is_front_page()) : ?>
                    <div class="flex flex-col content-center items-center text-center pt-3 md:pt-4 px-4 md:px-0">
                        <h1 class="welcome-text tracking-normal text-orange-200 text-4xl md:text-6xl -mb-2 md:-mb-2"><?php esc_html_e('Welcome to GTA6-Mods.com', 'gta6-mods'); ?></h1>
                        <p class="welcome-side tracking-tight px-0 md:px-2 text-stone-200 text-[11.8px] -mb-4 md:-mb-6 md:text-sm mt-2"><?php esc_html_e('Discover the latest GTA 6 PC mods created by our community. Browse vehicles, weapons, maps, scripts and more by category:', 'gta6-mods'); ?></p>
                    </div>
                <?php endif; ?>

                <?php
                $nav_items = [
                    [
                        'slug'         => 'tools',
                        'label'        => __('Tools', 'gta6-mods'),
                        'icon_classes' => 'fa-solid fa-screwdriver-wrench text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                    ],
                    [
                        'slug'         => 'vehicles',
                        'label'        => __('Vehicles', 'gta6-mods'),
                        'icon_classes' => 'fa-solid fa-car text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                    ],
                    [
                        'slug'         => 'paint-jobs',
                        'label'        => __('Paint Jobs', 'gta6-mods'),
                        'icon_classes' => 'fa-solid fa-paint-roller text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                    ],
                    [
                        'slug'         => 'weapons',
                        'label'        => __('Weapons', 'gta6-mods'),
                        'icon_classes' => 'fa-solid fa-gun text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                    ],
                    [
                        'slug'         => 'scripts',
                        'label'        => __('Scripts', 'gta6-mods'),
                        'icon_classes' => 'fa-solid fa-code text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                    ],
                    [
                        'slug'         => 'player',
                        'label'        => __('Player', 'gta6-mods'),
                        'icon_classes' => 'fa-solid fa-shirt text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                    ],
                    [
                        'slug'         => 'maps',
                        'label'        => __('Maps', 'gta6-mods'),
                        'icon_classes' => 'fa-solid fa-map text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                    ],
                    [
                        'slug'         => 'misc',
                        'label'        => __('Misc', 'gta6-mods'),
                        'icon_classes' => 'fa-solid fa-atom text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                    ],
                    [
                        'slug'          => null,
                        'label'         => __('More', 'gta6-mods'),
                        'icon_classes'  => 'fa-solid fa-ellipsis text-2xl md:text-4xl lg:text-5xl xl:text-6xl',
                        'force_disabled'=> true,
                    ],
                ];
                $allowed_terms_map = function_exists('gta6mods_get_allowed_category_terms_map') ? gta6mods_get_allowed_category_terms_map() : [];
                $nav_classes       = 'flex overflow-x-auto whitespace-nowrap py-2 md:py-6 text-white md:flex-wrap md:justify-center md:overflow-x-visible md:whitespace-normal items-center gap-x-0 sm:gap-x-4 md:gap-x-1 lg:gap-x-5 xl:gap-x-8';

                if (is_front_page()) {
                    $nav_classes .= ' mt-4 md:mt-6';
                }
                ?>
                <nav id="horizontal-nav" class="<?php echo esc_attr($nav_classes); ?>">
                    <?php foreach ($nav_items as $item) :
                        $slug        = isset($item['slug']) ? $item['slug'] : null;
                        $term_exists = $slug && isset($allowed_terms_map[$slug]);
                        $is_disabled = !$term_exists || !empty($item['force_disabled']);
                        $link_attrs  = $is_disabled ? 'data-disabled="true" aria-disabled="true"' : 'data-category="' . esc_attr($slug) . '"';
                        $link_classes = 'flex flex-col items-center gap-2 px-3 py-2 rounded-lg hover:bg-white/10 transition opacity-75 hover:opacity-100 flex-shrink-0';
                        if ($is_disabled) {
                            $link_classes .= ' cursor-default pointer-events-none';
                        }
                    ?>
                        <a href="#" <?php echo $link_attrs; ?> class="<?php echo esc_attr($link_classes); ?>">
                            <i class="<?php echo esc_attr($item['icon_classes']); ?>"></i>
                            <span class="text-xs font-bold tracking-wide uppercase"><?php echo esc_html($item['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </div>
</header>

<div id="menu-backdrop" class="hidden fixed inset-0 bg-black/50 z-40 transition-opacity duration-300 md:hidden" aria-hidden="true"></div>

<div id="mobile-menu-panel" class="fixed top-0 right-0 h-full w-64 max-w-full bg-gray-800 text-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 md:hidden" role="dialog" aria-modal="true" aria-labelledby="mobile-menu-title" aria-hidden="true">
    <div class="p-4 border-b border-white/10 h-[54px] flex justify-between items-center bg-pink-600/90 backdrop-blur-sm">
        <h3 id="mobile-menu-title" class="font-bold text-lg"><?php esc_html_e('Menu', 'gta6-mods'); ?></h3>
        <button id="close-menu-button" class="text-white hover:text-gray-200" type="button" aria-label="<?php esc_attr_e('Mobil menü bezárása', 'gta6-mods'); ?>">
            <i class="fa-solid fa-xmark fa-lg"></i>
        </button>
    </div>
    <div class="p-4 border-b border-white/10">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="relative">
            <label for="mobile-search" class="sr-only"><?php esc_html_e('Keresés', 'gta6-mods'); ?></label>
            <input id="mobile-search" type="search" name="s" placeholder="<?php esc_attr_e('Keresés…', 'gta6-mods'); ?>" class="w-full p-2 pl-10 rounded-md text-gray-800 bg-white/90 focus:outline-none focus:ring-2 focus:ring-pink-400" autocomplete="off">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
        </form>
    </div>
    <div class="p-4 border-b border-white/10">
        <a href="<?php echo esc_url( home_url( '/upload/' ) ); ?>" class="w-full bg-gray-900 font-bold py-2.5 px-4 rounded-lg flex items-center justify-center text-sm transition shadow-lg hover:bg-gray-950">
            <i class="fa-solid fa-upload mr-3"></i>
            <span><?php esc_html_e('Upload', 'gta6-mods'); ?></span>
        </a>
    </div>
    <div class="p-4 space-y-3 overflow-y-auto">
        <div class="flex justify-between items-center p-2 rounded-lg hover:bg-white/10 transition">
            <span class="font-semibold"><?php esc_html_e('Dark Mode', 'gta6-mods'); ?></span>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="dark-mode-toggle" class="sr-only peer">
                <span class="w-11 h-6 bg-gray-600 rounded-full peer peer-focus:ring-2 peer-focus:ring-pink-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-600"></span>
            </label>
        </div>
        <nav class="space-y-1" aria-label="<?php esc_attr_e('Mobil menü navigáció', 'gta6-mods'); ?>">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                <i class="fa-solid fa-house mr-3 w-5"></i>
                <span><?php esc_html_e('Home', 'gta6-mods'); ?></span>
            </a>
            <a href="#" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                <i class="fa-solid fa-gear mr-3 w-5"></i>
                <span><?php esc_html_e('Settings', 'gta6-mods'); ?></span>
            </a>
            <a href="#" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                <i class="fa-solid fa-circle-question mr-3 w-5"></i>
                <span><?php esc_html_e('Help Center', 'gta6-mods'); ?></span>
            </a>
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                    <i class="fa-solid fa-right-from-bracket mr-3 w-5"></i>
                    <span><?php esc_html_e('Sign Out', 'gta6-mods'); ?></span>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url(wp_login_url()); ?>" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                    <i class="fa-solid fa-right-to-bracket mr-3 w-5"></i>
                    <span><?php esc_html_e('Sign In', 'gta6-mods'); ?></span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>
