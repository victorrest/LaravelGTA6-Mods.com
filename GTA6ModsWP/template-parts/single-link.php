<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_post_id   = get_the_ID();
$current_permalink = get_permalink();
$categories        = get_the_category();
$primary_category  = !empty($categories) ? $categories[0] : null;
$author_id         = (int) get_the_author_meta('ID');
$author_name       = get_the_author();
$author_url        = get_author_posts_url($author_id);

$breadcrumb_items = [
    [
        'label' => esc_html__('Főoldal', 'gta6-mods'),
        'url'   => home_url('/'),
    ],
];

if ($primary_category instanceof WP_Term) {
    $breadcrumb_items[] = [
        'label' => esc_html($primary_category->name),
        'url'   => get_category_link($primary_category),
    ];
}

$breadcrumb_items[] = [
    'label' => wp_strip_all_tags(get_the_title()),
    'url'   => '',
];

$post_time_diff = human_time_diff(get_the_time('U'), current_time('timestamp'));
$published_label = sprintf(
    /* translators: %s: human readable time difference */
    esc_html__('%s ezelőtt', 'gta6-mods'),
    esc_html($post_time_diff)
);

$comment_count = get_comments_number();
$comments_text = sprintf(
    _n('%s hozzászólás', '%s hozzászólás', $comment_count, 'gta6-mods'),
    number_format_i18n($comment_count)
);

$share_url   = rawurlencode($current_permalink);
$share_title = rawurlencode(get_the_title());

$badge_label = $primary_category instanceof WP_Term ? $primary_category->name : esc_html__('Hír', 'gta6-mods');
?>
<main class="container mx-auto p-4 lg:p-6 lg:grid lg:grid-cols-12 lg:gap-8">
    <article class="lg:col-span-8 space-y-6">
        <div class="card p-5 md:p-8 space-y-6">
            <nav class="breadcrumb mb-2 text-sm text-gray-500" aria-label="<?php esc_attr_e('Morzsa navigáció', 'gta6-mods'); ?>">
                <ol class="flex items-center space-x-2 whitespace-nowrap overflow-hidden">
                    <?php foreach ($breadcrumb_items as $index => $item) : ?>
                        <li class="flex items-center max-w-full">
                            <?php if ($item['url']) : ?>
                                <a href="<?php echo esc_url($item['url']); ?>" class="hover:text-pink-600 truncate block max-w-[8rem] sm:max-w-none"><?php echo esc_html($item['label']); ?></a>
                                <?php if ($index < count($breadcrumb_items) - 1) : ?>
                                    <i class="fa-solid fa-chevron-right mx-2 text-xs"></i>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="text-gray-400 truncate block max-w-[10rem] sm:max-w-none" title="<?php echo esc_attr($item['label']); ?>"><?php echo esc_html($item['label']); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <header class="space-y-3">
                <span class="bg-pink-100 text-pink-800 text-xs font-semibold px-2.5 py-1 rounded-full shadow-sm"><?php echo esc_html($badge_label); ?></span>
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 leading-tight"><?php the_title(); ?></h1>
                <div class="flex flex-wrap items-center text-sm text-gray-500 gap-x-4 gap-y-2">
                    <span class="flex items-center"><i class="fa-solid fa-user mr-1.5"></i><?php esc_html_e('Írta:', 'gta6-mods'); ?> <a href="<?php echo esc_url($author_url); ?>" class="font-semibold text-pink-600 hover:underline"><?php echo esc_html($author_name); ?></a></span>
                    <span class="flex items-center"><i class="fa-solid fa-calendar-days mr-1.5"></i><?php echo esc_html($published_label); ?></span>
                    <span class="flex items-center"><i class="fa-solid fa-comments mr-1.5"></i><a href="<?php echo esc_url(get_comments_link()); ?>" class="hover:underline"><?php echo esc_html($comments_text); ?></a></span>
                </div>
            </header>

            <div class="my-6">
                <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('large', ['class' => 'w-full rounded-xl shadow-lg object-cover']); ?>
                <?php else : ?>
                    <img src="<?php echo esc_url(gta6_mods_get_placeholder('news')); ?>" alt="<?php the_title_attribute(); ?>" class="w-full rounded-xl shadow-lg object-cover">
                <?php endif; ?>
            </div>

            <div class="link-post-prose prose max-w-none text-gray-700 leading-relaxed">
                <?php the_content(); ?>
            </div>

            <footer class="pt-6 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-600"><?php esc_html_e('Címkék:', 'gta6-mods'); ?></span>
                        <?php
                        $post_tags = get_the_tags();
                        if ($post_tags) :
                            foreach ($post_tags as $tag) :
                                ?>
                                <a href="<?php echo esc_url(get_tag_link($tag)); ?>" class="text-xs bg-gray-200 hover:bg-pink-200 text-gray-700 hover:text-pink-800 px-3 py-1 rounded-full transition">#<?php echo esc_html($tag->name); ?></a>
                                <?php
                            endforeach;
                        else :
                            ?>
                            <span class="text-xs text-gray-400"><?php esc_html_e('Nincsenek címkék', 'gta6-mods'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-semibold text-gray-600"><?php esc_html_e('Megosztás:', 'gta6-mods'); ?></span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_attr($share_url); ?>" class="text-gray-500 hover:text-blue-600 transition text-xl" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Megosztás Facebookon', 'gta6-mods'); ?>"><i class="fa-brands fa-facebook-square"></i></a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo esc_attr($share_url); ?>&text=<?php echo esc_attr($share_title); ?>" class="text-gray-500 hover:text-sky-500 transition text-xl" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Megosztás Twitteren', 'gta6-mods'); ?>"><i class="fa-brands fa-twitter-square"></i></a>
                        <a href="https://www.reddit.com/submit?url=<?php echo esc_attr($share_url); ?>&title=<?php echo esc_attr($share_title); ?>" class="text-gray-500 hover:text-orange-500 transition text-xl" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Megosztás Redditen', 'gta6-mods'); ?>"><i class="fa-brands fa-reddit-square"></i></a>
                        <button type="button" class="text-gray-500 hover:text-gray-800 transition text-xl" data-copy-url="<?php echo esc_url($current_permalink); ?>" aria-label="<?php esc_attr_e('Hivatkozás másolása', 'gta6-mods'); ?>">
                            <i class="fa-solid fa-link"></i>
                        </button>
                    </div>
                </div>
            </footer>
        </div>

        <div class="card p-5 md:p-8 flex items-start space-x-4">
            <div class="flex-shrink-0">
                <?php echo get_avatar($author_id, 80, '', '', ['class' => 'w-16 h-16 rounded-full object-cover']); ?>
            </div>
            <div>
                <p class="text-sm text-gray-500"><?php esc_html_e('A szerzőről', 'gta6-mods'); ?></p>
                <h2 class="font-bold text-lg text-gray-900"><?php echo esc_html($author_name); ?></h2>
                <?php
                $author_bio = get_the_author_meta('description');
                if ($author_bio) :
                    ?>
                    <p class="text-sm text-gray-600 mt-1"><?php echo esc_html($author_bio); ?></p>
                <?php else : ?>
                    <p class="text-sm text-gray-600 mt-1"><?php esc_html_e('A szerző még nem adott meg bemutatkozót.', 'gta6-mods'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (comments_open() || get_comments_number()) : ?>
            <section id="comments" class="card p-5 md:p-8">
                <?php comments_template(); ?>
            </section>
        <?php endif; ?>
    </article>

    <aside class="lg:col-span-4 space-y-6 mt-8 lg:mt-0">
        <div class="card p-5">
            <h2 class="font-bold text-lg mb-3 text-gray-900"><?php esc_html_e('Keresés', 'gta6-mods'); ?></h2>
            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="relative">
                <label for="sidebar-search" class="sr-only"><?php esc_html_e('Keresés a hírek között', 'gta6-mods'); ?></label>
                <input type="search" id="sidebar-search" name="s" placeholder="<?php esc_attr_e('Keress a hírek között...', 'gta6-mods'); ?>" class="w-full pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-400 focus:border-transparent transition" value="<?php echo esc_attr(get_search_query()); ?>">
                <i class="fa-solid fa-magnifying-glass absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </form>
        </div>

        <div class="card p-5 sticky top-6 space-y-4">
            <h2 class="font-bold text-lg text-gray-900"><?php esc_html_e('Népszerű Hírek', 'gta6-mods'); ?></h2>
            <ul class="space-y-4">
                <?php
                $popular_news = new WP_Query([
                    'post_type'           => 'post',
                    'posts_per_page'      => 3,
                    'post_status'         => 'publish',
                    'post__not_in'        => [$current_post_id],
                    'ignore_sticky_posts' => true,
                    'tax_query'           => [
                        [
                            'taxonomy' => 'post_format',
                            'field'    => 'slug',
                            'terms'    => ['post-format-link'],
                        ],
                    ],
                ]);

                if ($popular_news->have_posts()) :
                    while ($popular_news->have_posts()) :
                        $popular_news->the_post();
                        $news_time_diff = human_time_diff(get_the_time('U'), current_time('timestamp'));
                        ?>
                        <li>
                            <a href="<?php the_permalink(); ?>" class="flex items-center space-x-3 group">
                                <img src="<?php echo esc_url(gta6_mods_get_image(get_the_ID(), 'medium', 'news')); ?>" alt="<?php the_title_attribute(); ?>" class="w-24 h-16 object-cover rounded-lg flex-shrink-0">
                                <div>
                                    <p class="font-semibold text-gray-800 group-hover:text-pink-600 transition text-sm leading-tight"><?php the_title(); ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?php printf(esc_html__('%s ezelőtt', 'gta6-mods'), esc_html($news_time_diff)); ?></p>
                                </div>
                            </a>
                        </li>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                    ?>
                    <li class="text-sm text-gray-500"><?php esc_html_e('Jelenleg nincs több hír.', 'gta6-mods'); ?></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="p-4 text-center border-2 border-dashed border-gray-300 bg-gray-100 rounded-xl sticky top-60">
            <span class="text-xs font-semibold text-gray-400"><?php esc_html_e('HIRDETÉS (300x250)', 'gta6-mods'); ?></span>
            <div class="mt-3 w-full h-64 bg-white border border-gray-300 flex items-center justify-center rounded-lg shadow-inner">
                <p class="text-gray-500 text-sm font-bold"><?php esc_html_e('STICKY AD BLOCK', 'gta6-mods'); ?></p>
            </div>
        </div>
    </aside>
</main>
