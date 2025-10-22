<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function gta6_forum_on_new_comment(int $commentId, $commentApproved, array $commentData): void {
    $status = is_string($commentApproved) ? $commentApproved : (string) $commentApproved;
    if (in_array($status, ['spam', 'trash'], true) || empty($commentData['comment_post_ID'])) {
        return;
    }

    $postType = get_post_type((int) $commentData['comment_post_ID']);
    if ('forum_thread' !== $postType) {
        return;
    }

    wp_schedule_single_event(time(), 'gta6_forum_create_notification_job', [
        'comment_id' => $commentId,
    ]);
}
add_action('comment_post', 'gta6_forum_on_new_comment', 10, 3);

function gta6_forum_create_notification_job(array $args): void {
    if (empty($args['comment_id'])) {
        return;
    }

    $comment = get_comment((int) $args['comment_id']);
    if (!$comment instanceof WP_Comment) {
        return;
    }

    $post = get_post((int) $comment->comment_post_ID);
    if (!$post instanceof WP_Post || 'forum_thread' !== $post->post_type) {
        return;
    }

    if ((int) $comment->user_id === (int) $post->post_author) {
        return;
    }

    gta6_forum_insert_notification([
        'user_id'    => (int) $post->post_author,
        'thread_id'  => (int) $post->ID,
        'comment_id' => (int) $comment->comment_ID,
        'payload'    => wp_json_encode([
            'comment_author' => get_comment_author($comment),
            'excerpt'        => wp_trim_words($comment->comment_content, 20),
        ]),
    ]);
}
add_action('gta6_forum_create_notification_job', 'gta6_forum_create_notification_job');

function gta6_forum_insert_notification(array $data): void {
    global $wpdb;

    $defaults = [
        'user_id'    => 0,
        'thread_id'  => 0,
        'comment_id' => 0,
        'status'     => 'unread',
        'notification_type' => 'comment',
        'payload'    => null,
    ];

    $data = wp_parse_args($data, $defaults);

    if ($data['user_id'] <= 0 || $data['thread_id'] <= 0 || $data['comment_id'] <= 0) {
        return;
    }

    $wpdb->insert(
        gta6_forum_notifications_table(),
        [
            'user_id'    => $data['user_id'],
            'thread_id'  => $data['thread_id'],
            'comment_id' => $data['comment_id'],
            'notification_type' => $data['notification_type'],
            'status'     => $data['status'],
            'payload'    => $data['payload'],
            'created_at' => current_time('mysql', true),
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s', '%s']
    );
}
