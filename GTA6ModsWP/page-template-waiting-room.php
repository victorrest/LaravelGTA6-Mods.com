<?php
/**
 * Waiting room download template.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = get_query_var('gta6mods_waiting_room_context');
if (!is_array($context) || empty($context)) {
    $context = gta6mods_get_waiting_room_context_data();
}

$mod     = isset($context['mod']) && $context['mod'] instanceof WP_Post ? $context['mod'] : null;
$version = isset($context['version']) && is_array($context['version']) ? $context['version'] : null;
$is_external = !empty($context['is_external']);
$external_url = $is_external && !empty($context['external_url']) ? $context['external_url'] : '';
$external_domain = $is_external && !empty($context['external_domain']) ? $context['external_domain'] : '';
$external_display_domain = $external_domain;
if ($is_external && '' === $external_display_domain && '' !== $external_url) {
    $parsed_domain = wp_parse_url($external_url, PHP_URL_HOST);
    if (is_string($parsed_domain) && '' !== $parsed_domain) {
        $external_display_domain = preg_replace('/^www\./i', '', sanitize_text_field($parsed_domain));
    }
}

if (!$mod || !$version) {
    status_header(404);
    get_template_part('404');
    return;
}

$mod_id           = (int) $mod->ID;
$mod_permalink    = get_permalink($mod);
$mod_title        = get_the_title($mod);
$author_id        = (int) $mod->post_author;
$author_name      = get_the_author_meta('display_name', $author_id);
$author_name      = $author_name ? $author_name : get_the_author_meta('user_nicename', $author_id);
$author_name      = $author_name ? $author_name : __('Unknown author', 'gta6-mods');
$author_url       = get_author_posts_url($author_id);
$version_id       = isset($version['id']) ? (int) $version['id'] : 0;
$version_number   = isset($version['version']) ? sanitize_text_field($version['version']) : '';
$attachment_id = isset($version['attachment_id']) ? (int) $version['attachment_id'] : 0;

$file_size = '';
if ($attachment_id > 0) {
    $stored_size = (int) get_post_meta($attachment_id, '_filesize', true);
    if ($stored_size <= 0) {
        $attachment_path = get_attached_file($attachment_id);
        if ($attachment_path && file_exists($attachment_path)) {
            $stored_size = (int) filesize($attachment_path);
        }
    }

    if ($stored_size > 0) {
        $file_size = size_format((float) $stored_size);
    }
}

$thumbnail_id     = get_post_thumbnail_id($mod);
$thumbnail_data   = $thumbnail_id ? wp_get_attachment_image_src($thumbnail_id, 'large') : false;
$thumbnail_url    = ($thumbnail_data && isset($thumbnail_data[0])) ? $thumbnail_data[0] : '';
$thumbnail_width  = ($thumbnail_data && isset($thumbnail_data[1])) ? (int) $thumbnail_data[1] : 0;
$thumbnail_height = ($thumbnail_data && isset($thumbnail_data[2])) ? (int) $thumbnail_data[2] : 0;
$thumbnail_srcset = $thumbnail_id ? wp_get_attachment_image_srcset($thumbnail_id, 'large') : '';
$thumbnail_sizes  = $thumbnail_id ? wp_get_attachment_image_sizes($thumbnail_id, 'large') : '';

if (!$thumbnail_url) {
    $thumbnail_url = gta6_mods_get_placeholder('featured');
}

$countdown_seconds = (int) apply_filters('gta6mods_waiting_room_countdown', 5);
if ($countdown_seconds <= 0) {
    $countdown_seconds = 5;
}

$author_more_mods = gta6_mods_get_author_other_mods($author_id, $mod_id, 4);
$terms_url        = apply_filters('gta6mods_terms_of_use_url', home_url('/terms-of-use/'));

$version_label_parts = [];
if ('' !== $version_number) {
    $version_label_parts[] = sprintf(
        '<span class="font-semibold">%s</span> %s',
        esc_html__('Version:', 'gta6-mods'),
        esc_html($version_number)
    );
}
if ('' !== $file_size) {
    $version_label_parts[] = sprintf(
        '<span class="font-semibold">%s</span> %s',
        esc_html__('Size:', 'gta6-mods'),
        esc_html($file_size)
    );
}
$meta_line = '';
if (!empty($version_label_parts)) {
    $meta_line = implode(' | ', $version_label_parts);
}

$attribute_builder = static function (array $attributes): string {
    $compiled = [];
    foreach ($attributes as $name => $value) {
        if (null === $value || '' === $value) {
            continue;
        }

        if (is_bool($value)) {
            if ($value) {
                $compiled[] = esc_attr($name);
            }
            continue;
        }

        $compiled[] = sprintf('%s="%s"', esc_attr($name), esc_attr($value));
    }

    return implode(' ', $compiled);
};

$heading_text = $is_external
    ? sprintf(__('Preparing external link for %s…', 'gta6-mods'), $mod_title)
    : sprintf(__('Downloading %s...', 'gta6-mods'), $mod_title);
$subheading_text = $is_external
    ? __('Please wait a few seconds before we send you to the external download.', 'gta6-mods')
    : __('Please wait a few seconds while we prepare your file.', 'gta6-mods');
$button_preparing_text = $is_external ? __('Preparing external link…', 'gta6-mods') : __('Preparing download…', 'gta6-mods');
$button_ready_text     = $is_external ? __('Continue to external site', 'gta6-mods') : __('Download Now', 'gta6-mods');
if ($is_external && '' !== $external_display_domain) {
    $countdown_template = sprintf(
        /* translators: 1: external domain, 2: seconds placeholder */
        __('You will be redirected to %1$s in %2$s seconds.', 'gta6-mods'),
        $external_display_domain,
        '%d'
    );
} else {
    $countdown_template = $is_external
        ? __('You will be redirected in %d seconds.', 'gta6-mods')
        : __('Download starts in %d seconds.', 'gta6-mods');
}

$mod_page_aria = sprintf(
    /* translators: %s: mod title */
    __('Open %s mod page', 'gta6-mods'),
    $mod_title
);

$thumbnail_link_attrs = $attribute_builder([
    'href'                    => $mod_permalink,
    'class'                   => 'wr-mod-media-link focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 rounded-lg overflow-hidden block',
    'title'                   => $mod_page_aria,
    'aria-label'              => $mod_page_aria,
    'rel'                     => 'bookmark',
    'data-analytics-event'    => 'waiting_room_thumbnail',
    'data-analytics-mod-id'   => (string) $mod_id,
    'data-analytics-version-id' => (string) $version_id,
]);

$mod_title_link_attrs = $attribute_builder([
    'href'                    => $mod_permalink,
    'class'                   => 'text-gray-900 no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 rounded-sm',
    'title'                   => $mod_page_aria,
    'aria-label'              => $mod_page_aria,
    'rel'                     => 'bookmark',
    'data-analytics-event'    => 'waiting_room_title',
    'data-analytics-mod-id'   => (string) $mod_id,
    'data-analytics-version-id' => (string) $version_id,
]);

$author_link_attrs = '';
if ($author_url) {
    $author_link_attrs = $attribute_builder([
        'href'                  => $author_url,
        'class'                 => 'font-semibold text-amber-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 rounded-sm',
        'title'                 => sprintf(
            /* translators: %s: author name */
            __('Browse more mods by %s', 'gta6-mods'),
            $author_name
        ),
        'aria-label'            => sprintf(
            /* translators: %s: author name */
            __('Browse more mods by %s', 'gta6-mods'),
            $author_name
        ),
        'rel'                   => 'author',
        'data-analytics-event'  => 'waiting_room_author',
        'data-analytics-author' => (string) $author_id,
        'data-analytics-mod-id' => (string) $mod_id,
    ]);
}

$terms_anchor = '';
if ($terms_url) {
    $terms_anchor = sprintf(
        '<a %s>%s</a>',
        $attribute_builder([
            'href'                 => $terms_url,
            'class'                => 'underline hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 rounded-sm',
            'title'                => __('Review the GTA6-Mods.com Terms of Use', 'gta6-mods'),
            'aria-label'           => __('Review the GTA6-Mods.com Terms of Use', 'gta6-mods'),
            'rel'                  => 'bookmark',
            'data-analytics-event' => 'waiting_room_terms',
        ]),
        esc_html__('GTA6-Mods.com Terms of Use', 'gta6-mods')
    );
} else {
    $terms_anchor = esc_html__('GTA6-Mods.com Terms of Use', 'gta6-mods');
}

$terms_text = sprintf(
    /* translators: %s: Terms of Use link */
    __('By downloading and installing this file, you agree to the %s.', 'gta6-mods'),
    $terms_anchor
);

$more_by_author_heading_link = $author_url
    ? sprintf(
        '<a %s>%s</a>',
        $attribute_builder([
            'href'                 => $author_url,
            'class'                => 'text-pink-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 rounded-sm',
            'title'                => sprintf(
                /* translators: %s: author name */
                __('View all mods from %s', 'gta6-mods'),
                $author_name
            ),
            'aria-label'           => sprintf(
                /* translators: %s: author name */
                __('View all mods from %s', 'gta6-mods'),
                $author_name
            ),
            'rel'                  => 'bookmark',
            'data-analytics-event' => 'waiting_room_author_more',
            'data-analytics-author'=> (string) $author_id,
        ]),
        esc_html($author_name)
    )
    : esc_html($author_name);

$more_by_author_heading = sprintf(
    /* translators: %s: author name */
    __('More by %s', 'gta6-mods'),
    $more_by_author_heading_link
);

$waiting_room_strings = [
    'countdown' => sprintf(
        $countdown_template,
        $countdown_seconds
    ),
];

if ($is_external && '' !== $external_url) {
    $direct_download_url = $external_url;
} else {
    $direct_download_url = add_query_arg(
        [
            'vid' => $version_id,
            'nojs' => 1,
        ],
        home_url('/download-file/')
    );
}

global $wp_query;
$wp_query->is_page = true;

get_header();
?>

<main class="container mx-auto p-4 lg:p-6" data-waiting-room data-version-id="<?php echo esc_attr((string) $version_id); ?>">
    <p class="sr-only" data-countdown-text><?php echo esc_html($waiting_room_strings['countdown']); ?></p>

    <div class="mb-6 text-center" data-wr-top-ad>
        <?php if (has_action('gta6mods_waiting_room_top_ad')) : ?>
            <?php do_action('gta6mods_waiting_room_top_ad', $mod_id, $version_id); ?>
        <?php else : ?>
            <div class="bg-gray-200 w-full max-w-[728px] h-[90px] mx-auto rounded-lg flex items-center justify-center">
                <p class="text-gray-500"><?php esc_html_e('728x90 Ad', 'gta6-mods'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="max-w-4xl mx-auto card p-6 md:p-8" data-wr-card>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 leading-tight"><?php echo esc_html($heading_text); ?></h1>
                <p class="text-gray-500 mt-2"><?php echo esc_html($subheading_text); ?></p>

                <div class="mt-6 p-4 bg-gray-50 rounded-lg border" data-wr-mod-overview>
                    <div class="wr-mod-overview">
                        <div class="wr-mod-media">
                            <a <?php echo $thumbnail_link_attrs; ?>>
                                <div class="wr-mod-media-inner">
                                    <img
                                        src="<?php echo esc_url($thumbnail_url); ?>"
                                        alt="<?php echo esc_attr($mod_title); ?>"
                                        class="wr-mod-media-image"
                                        loading="lazy"
                                        decoding="async"
                                        fetchpriority="high"
                                        <?php if ($thumbnail_srcset) : ?>srcset="<?php echo esc_attr($thumbnail_srcset); ?>"<?php endif; ?>
                                        <?php if ($thumbnail_sizes) : ?>sizes="<?php echo esc_attr($thumbnail_sizes); ?>"<?php endif; ?>
                                        <?php if ($thumbnail_width > 0) : ?>width="<?php echo esc_attr((string) $thumbnail_width); ?>"<?php endif; ?>
                                        <?php if ($thumbnail_height > 0) : ?>height="<?php echo esc_attr((string) $thumbnail_height); ?>"<?php endif; ?>
                                    >
                                </div>
                            </a>
                        </div>
                        <div class="wr-mod-details">
                            <h2 class="font-bold text-[18px]/6 text-gray-800">
                                <a <?php echo $mod_title_link_attrs; ?>><?php echo esc_html($mod_title); ?></a>
                            </h2>
                            <p class="text-[14px] pt-1 text-gray-500">
                                <?php
                                printf(
                                    /* translators: %s: author name */
                                    esc_html__('by %s', 'gta6-mods'),
                                    $author_url ? '<a ' . $author_link_attrs . '>' . esc_html($author_name) . '</a>' : '<span class="font-semibold text-amber-600">' . esc_html($author_name) . '</span>'
                                );
                                ?>
                            </p>
                            <?php if ('' !== $meta_line) : ?>
                                <div class="mt-2 text-xs text-gray-600">
                                    <p><?php echo wp_kses_post($meta_line); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($is_external) : ?>
                    <?php
                    $external_notice_label = '' !== $external_display_domain
                        ? '<strong>' . esc_html($external_display_domain) . '</strong>'
                        : esc_html__('the external host', 'gta6-mods');
                    ?>
                    <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 flex items-start space-x-3" data-external-notice>
                        <span class="mt-1 text-amber-500" aria-hidden="true">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </span>
                        <div>
                            <p class="font-semibold"><?php esc_html_e('You are leaving GTA6-Mods.com', 'gta6-mods'); ?></p>
                            <p class="text-sm mt-1"><?php
                                printf(
                                    /* translators: %s: external domain */
                                    esc_html__('The download is hosted on %s. Continue only if you trust this source.', 'gta6-mods'),
                                    $external_notice_label
                                );
                                ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-6">
                    <button id="download-button" type="button" disabled class="btn-download font-bold py-4 px-6 rounded-xl transition flex items-center justify-center w-full text-lg" aria-disabled="true" aria-busy="true" data-download-button>
                        <span class="spinner-holder" data-button-spinner aria-hidden="true">
                            <svg class="animate-spin h-5 w-5 mr-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        <span class="icon-holder" data-button-icon aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 mr-2">
                                <path d="M12 15V3"></path>
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <path d="m7 10 5 5 5-5"></path>
                            </svg>
                        </span>
                        <span class="button-label" data-button-label>
                            <span data-button-text><?php echo esc_html($button_preparing_text); ?></span>
                            <span class="countdown-inline" data-countdown-wrapper>
                                (<span id="countdown" data-countdown-value><?php echo esc_html((string) $countdown_seconds); ?></span>)
                            </span>
                        </span>
                    </button>
                </div>

                <?php if ($is_external && '' !== $external_display_domain) : ?>
                    <p class="text-sm text-gray-500 mt-3 text-center">
                        <?php
                        printf(
                            /* translators: %s: external domain */
                            esc_html__('Destination: %s', 'gta6-mods'),
                            esc_html($external_display_domain)
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <noscript>
                    <div class="mt-6">
                        <a href="<?php echo esc_url($direct_download_url); ?>" class="btn-download font-bold py-4 px-6 rounded-xl transition flex items-center justify-center w-full text-lg" rel="nofollow<?php echo $is_external ? ' noopener external' : ''; ?>"<?php echo $is_external ? ' target="_blank"' : ''; ?> data-analytics-event="waiting_room_nojs_download" data-analytics-version-id="<?php echo esc_attr((string) $version_id); ?>" data-analytics-mod-id="<?php echo esc_attr((string) $mod_id); ?>">
                            <?php echo esc_html($is_external ? __('Open external link (JavaScript disabled)', 'gta6-mods') : __('Download Now (JavaScript Disabled)', 'gta6-mods')); ?>
                        </a>
                        <p class="text-xs text-gray-500 mt-2"><?php esc_html_e('JavaScript is required for the countdown timer.', 'gta6-mods'); ?></p>
                    </div>
                </noscript>

                <div class="mt-4 text-center">
                    <a href="<?php echo esc_url($mod_permalink); ?>" class="text-sm text-gray-500 hover:text-pink-600 transition inline-flex items-center focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 rounded-sm" title="<?php echo esc_attr($mod_page_aria); ?>" aria-label="<?php echo esc_attr($mod_page_aria); ?>" rel="bookmark" data-analytics-event="waiting_room_back" data-analytics-mod-id="<?php echo esc_attr((string) $mod_id); ?>" data-analytics-version-id="<?php echo esc_attr((string) $version_id); ?>">
                        <i class="fa-solid fa-arrow-left mr-2" aria-hidden="true"></i>
                        <?php esc_html_e('Back to mod page', 'gta6-mods'); ?>
                    </a>
                </div>

                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-400"><?php echo wp_kses_post($terms_text); ?></p>
                </div>
            </div>

            <aside class="md:col-span-1">
                <div class="text-center" data-wr-sidebar-ad>
                    <span class="text-xs text-gray-400"><?php esc_html_e('Advertisement', 'gta6-mods'); ?></span>
                    <?php if (has_action('gta6mods_waiting_room_sidebar_ad')) : ?>
                        <div class="mt-2">
                            <?php do_action('gta6mods_waiting_room_sidebar_ad', $mod_id, $version_id); ?>
                        </div>
                    <?php else : ?>
                        <div class="bg-gray-200 w-full h-full min-h-[250px] mt-2 rounded-lg flex items-center justify-center">
                            <p class="text-gray-500"><?php esc_html_e('300x250 Ad', 'gta6-mods'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>

    <div class="mt-8 text-center" data-wr-bottom-ad>
        <?php if (has_action('gta6mods_waiting_room_bottom_ad')) : ?>
            <?php do_action('gta6mods_waiting_room_bottom_ad', $mod_id, $version_id); ?>
        <?php else : ?>
            <div class="bg-gray-200 w-full max-w-[728px] h-[90px] mx-auto rounded-lg flex items-center justify-center">
                <p class="text-gray-500"><?php esc_html_e('728x90 Ad', 'gta6-mods'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($author_more_mods)) : ?>
        <div class="mt-12" data-wr-author-grid>
            <h2 class="text-2xl font-bold text-center mb-6 text-gray-800"><?php echo wp_kses_post($more_by_author_heading); ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($author_more_mods as $author_mod) :
                    $card_title    = isset($author_mod['title']) ? $author_mod['title'] : '';
                    $card_link     = isset($author_mod['permalink']) ? $author_mod['permalink'] : '';
                    $card_image    = isset($author_mod['thumbnail']) ? $author_mod['thumbnail'] : '';
                    $card_category = isset($author_mod['category']) ? $author_mod['category'] : '';
                    $card_attrs    = $attribute_builder([
                        'href'                 => $card_link,
                        'class'                => 'group block focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 rounded-lg overflow-hidden',
                        'title'                => sprintf(
                            /* translators: %s: mod title */
                            __('Open %s mod page', 'gta6-mods'),
                            $card_title
                        ),
                        'aria-label'           => sprintf(
                            /* translators: %s: mod title */
                            __('Open %s mod page', 'gta6-mods'),
                            $card_title
                        ),
                        'rel'                  => 'bookmark',
                        'data-analytics-event' => 'waiting_room_author_mod',
                        'data-analytics-mod-id'=> isset($author_mod['id']) ? (string) $author_mod['id'] : '',
                    ]);
                    ?>
                    <div class="card">
                        <a <?php echo $card_attrs; ?>>
                            <div class="relative overflow-hidden rounded-t-lg">
                                <img src="<?php echo esc_url($card_image); ?>" alt="<?php echo esc_attr($card_title); ?>" class="w-full h-40 object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async" fetchpriority="low">
                            </div>
                            <div class="p-4">
                                <h4 class="font-semibold text-gray-800 group-hover:text-pink-600 transition truncate"><?php echo esc_html($card_title); ?></h4>
                                <?php if ($card_category) : ?>
                                    <p class="text-sm text-gray-500 mt-1"><?php echo esc_html($card_category); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
