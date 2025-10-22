<?php
/**
 * Author comments tab template.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

$raw_comments = $args['comments'] ?? [];
$comments = array_values(array_filter($raw_comments, static function ($comment) {
    return $comment instanceof WP_Comment;
}));

$author_id  = isset($args['author_id']) ? (int) $args['author_id'] : 0;
$total      = isset($args['total']) ? (int) $args['total'] : count($comments);
$pagination = $args['pagination'] ?? [];
$current    = max(1, (int) ($pagination['current'] ?? 1));
$total_pages = max(1, (int) ($pagination['total'] ?? 1));
$per_page   = isset($args['per_page']) ? (int) $args['per_page'] : 10;
$base_url   = $args['base_url'] ?? '';

if ('' === $base_url && $author_id > 0) {
    $base_url = gta6mods_get_author_profile_tab_url($author_id, 'comments');
}

$formatted_total = number_format_i18n($total);
?>
<h3
    class="text-xl font-bold text-gray-800 mb-4"
    data-comment-count-label
>
    <?php
    printf(
        /* translators: %s: total comment count. */
        esc_html__('Latest Comments (%s)', 'gta6-mods'),
        esc_html($formatted_total)
    );
    ?>
</h3>
<?php if (!empty($comments)) : ?>
    <div class="space-y-6">
        <?php foreach ($comments as $comment) :
            $comment_content = apply_filters('comment_text', get_comment_text($comment), $comment, []);
            $time_diff       = human_time_diff(get_comment_time('U', true), current_time('timestamp', true));
            $time_label      = sprintf(
                /* translators: %s: human-readable time difference. */
                esc_html__('%s ago', 'gta6-mods'),
                $time_diff
            );

            $post        = get_post($comment->comment_post_ID);
            $post_link   = $post ? get_permalink($post) : '';
            $post_title  = $post ? get_the_title($post) : __('Unknown Mod', 'gta6-mods');
            $attachments = gta6mods_get_comment_attachments_markup($comment);
            ?>
            <div class="comment-item flex items-start gap-4">
                <div class="flex-shrink-0">
                    <?php echo get_avatar($comment, 40, '', '', ['class' => 'w-10 h-10 rounded-full flex-shrink-0 mt-1']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="flex-1">
                    <div class="comment-bubble relative p-4 rounded-lg bg-gray-100">
                        <div class="text-gray-800 leading-relaxed">
                            <?php echo wp_kses_post($comment_content); ?>
                        </div>
                        <?php if (!empty($attachments)) : ?>
                            <?php echo $attachments; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-gray-500 mt-2 pl-4">
                        <?php if ($post_link) : ?>
                            <span>
                                <?php
                                echo wp_kses_post(
                                    sprintf(
                                        /* translators: %s: linked mod title. */
                                        __('Comment on %s', 'gta6-mods'),
                                        '<a href="' . esc_url($post_link) . '" class="font-semibold text-pink-600 hover:underline">' . esc_html($post_title) . '</a>'
                                    )
                                );
                                ?>
                            </span>
                            <span class="text-gray-400 mx-1">&middot;</span>
                        <?php endif; ?>
                        <span class="text-xs"><?php echo esc_html($time_label); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else : ?>
    <div class="text-center py-12 border border-dashed border-gray-200 rounded-lg">
        <i class="fas fa-comments text-4xl text-gray-300 mb-4"></i>
        <h4 class="font-bold text-lg text-gray-700"><?php esc_html_e('No comments yet', 'gta6-mods'); ?></h4>
        <p class="text-gray-500 mt-1"><?php esc_html_e('Start a conversation by leaving a comment on your favourite mods.', 'gta6-mods'); ?></p>
    </div>
<?php endif; ?>

<?php if ($total_pages > 1) :
    $current = min($current, $total_pages);
    $pages   = [];
    $pages[] = 1;
    $range   = 1;
    $start   = max(2, $current - $range);
    $end     = min($total_pages - 1, $current + $range);

    if ($start > 2) {
        $pages[] = 'gap';
    }

    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }

    if ($end < $total_pages - 1) {
        $pages[] = 'gap';
    }

    if ($total_pages > 1) {
        $pages[] = $total_pages;
    }

    $prev_disabled = $current <= 1;
    $prev_target   = max(1, $current - 1);
    $next_disabled = $current >= $total_pages;
    $next_target   = min($total_pages, $current + 1);

    $prev_url = $author_id > 0
        ? gta6mods_get_author_profile_tab_page_url($author_id, 'comments', $prev_target)
        : ($base_url ? add_query_arg('tab_page', $prev_target, $base_url) : '');
    $next_url = $author_id > 0
        ? gta6mods_get_author_profile_tab_page_url($author_id, 'comments', $next_target)
        : ($base_url ? add_query_arg('tab_page', $next_target, $base_url) : '');
    $pagination_label = sprintf(
        /* translators: 1: current page number. 2: total page count. */
        __('Comments pagination (page %1$s of %2$s)', 'gta6-mods'),
        number_format_i18n($current),
        number_format_i18n($total_pages)
    );

    ?>
    <nav
        id="comments-pagination"
        class="flex items-center justify-between border-t border-gray-200 px-4 sm:px-0 mt-8 pt-4"
        aria-label="<?php echo esc_attr($pagination_label); ?>"
        data-comment-pagination="1"
    >
        <div class="-mt-px flex w-0 flex-1">
            <?php if ($prev_disabled) : ?>
                <span class="prev-page inline-flex items-center border-t-2 border-transparent pr-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 invisible" aria-disabled="true">
                    <i class="fas fa-arrow-long-left mr-3 h-5 w-5 text-gray-400"></i>
                    <?php esc_html_e('Previous', 'gta6-mods'); ?>
                </span>
            <?php else : ?>
                <a
                    href="<?php echo esc_url($prev_url); ?>"
                    class="prev-page inline-flex items-center border-t-2 border-transparent pr-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700"
                    data-comment-page="<?php echo esc_attr($prev_target); ?>"
                >
                    <i class="fas fa-arrow-long-left mr-3 h-5 w-5 text-gray-400"></i>
                    <?php esc_html_e('Previous', 'gta6-mods'); ?>
                </a>
            <?php endif; ?>
        </div>
        <div class="flex -mt-px flex-1 justify-center" id="pagination-numbers">
            <?php foreach ($pages as $page) : ?>
                <?php if ('gap' === $page) : ?>
                    <span class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-400 select-none">…</span>
                <?php else :
                    $page_number = (int) $page;
                    $is_current  = ($page_number === $current);
                    $page_url    = $author_id > 0
                        ? gta6mods_get_author_profile_tab_page_url($author_id, 'comments', $page_number)
                        : ($base_url ? add_query_arg('tab_page', $page_number, $base_url) : '');
                    ?>
                    <?php if ($is_current) : ?>
                        <span class="inline-flex items-center border-t-2 px-4 pt-4 text-sm font-medium border-pink-500 text-pink-600" aria-current="page"><?php echo esc_html($page_number); ?></span>
                    <?php else : ?>
                        <a
                            href="<?php echo esc_url($page_url); ?>"
                            class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                            data-comment-page="<?php echo esc_attr($page_number); ?>"
                        >
                            <?php echo esc_html($page_number); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="-mt-px flex w-0 flex-1 justify-end">
            <?php if ($next_disabled) : ?>
                <span class="next-page inline-flex items-center border-t-2 border-transparent pl-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700 invisible" aria-disabled="true">
                    <?php esc_html_e('Next', 'gta6-mods'); ?>
                    <i class="fas fa-arrow-long-right ml-3 h-5 w-5 text-gray-400"></i>
                </span>
            <?php else : ?>
                <a
                    href="<?php echo esc_url($next_url); ?>"
                    class="next-page inline-flex items-center border-t-2 border-transparent pl-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700"
                    data-comment-page="<?php echo esc_attr($next_target); ?>"
                >
                    <?php esc_html_e('Next', 'gta6-mods'); ?>
                    <i class="fas fa-arrow-long-right ml-3 h-5 w-5 text-gray-400"></i>
                </a>
            <?php endif; ?>
        </div>
    </nav>
<?php endif; ?>
