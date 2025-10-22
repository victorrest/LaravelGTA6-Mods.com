<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Synchronises cached vote scores with the database every five minutes.
 */
function gta6_forum_sync_scores_from_redis(): void {
    $group   = 'gta6_forum_sync';
    $flagKey = 'dirty-scores';

    $payload = wp_cache_get($flagKey, $group);
    if (!is_array($payload) || (empty($payload['threads']) && empty($payload['comments']))) {
        return;
    }

    wp_cache_delete($flagKey, $group);

    if (!empty($payload['threads'])) {
        foreach (array_keys($payload['threads']) as $threadId) {
            $score = (int) wp_cache_get('thread_score:' . $threadId, 'gta6_forum_scores');
            update_post_meta($threadId, '_thread_score', $score);
        }
    }

    if (!empty($payload['comments'])) {
        foreach (array_keys($payload['comments']) as $commentId) {
            $score = (int) wp_cache_get('comment_score:' . $commentId, 'gta6_forum_scores');
            update_comment_meta($commentId, '_comment_score', $score);
        }
    }
}
add_action('gta6_sync_scores_from_redis', 'gta6_forum_sync_scores_from_redis');

/**
 * Recalculates the hot score for the top trending threads.
 */
function gta6_forum_recalculate_hot_scores(): void {
    $query = new WP_Query([
        'post_type'      => 'forum_thread',
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ]);

    if (empty($query->posts)) {
        return;
    }

    foreach ($query->posts as $postId) {
        $score = (int) get_post_meta($postId, '_thread_score', true);
        $timestamp = (int) get_post_time('U', true, $postId);
        $hotScore = gta6_forum_calculate_hot_score($score, $timestamp);

        update_post_meta($postId, '_hot_score', $hotScore);
    }
}
add_action('gta6_recalculate_hot_scores', 'gta6_forum_recalculate_hot_scores');

function gta6_forum_calculate_hot_score(int $score, int $timestamp): float {
    $order = log(max(abs($score), 1), 10);
    $sign = $score > 0 ? 1 : ($score < 0 ? -1 : 0);
    $seconds = $timestamp - 1134028003; // Reddit style epoch offset.

    return round($sign * $order + ($seconds / 45000), 7);
}
