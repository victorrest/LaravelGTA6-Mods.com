<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function gta6_forum_register_comment_rest_fields(): void {
    register_rest_field(
        'comment',
        'vote_score',
        [
            'get_callback' => static fn(array $comment): int => (int) get_comment_meta((int) $comment['id'], '_comment_score', true),
            'schema'       => [
                'description' => __('Aggregated vote score for the comment.', 'gta6mods'),
                'type'        => 'integer',
                'context'     => ['view'],
            ],
        ]
    );

    register_rest_field(
        'comment',
        'current_user_vote',
        [
            'get_callback' => static fn(array $comment): int => gta6_forum_get_user_vote('comment', (int) $comment['id']),
            'schema'       => [
                'description' => __('The current viewer vote for the comment.', 'gta6mods'),
                'type'        => 'integer',
                'context'     => ['view'],
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6_forum_register_comment_rest_fields');
