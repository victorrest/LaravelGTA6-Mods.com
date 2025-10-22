<?php
/**
 * High-performance view counter utilities for forum threads.
 *
 * @package gta6modswp
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function gta6_forum_get_thread_view_cache_key(int $threadId): string
{
    return 'thread:' . $threadId;
}

function gta6_forum_get_thread_views(int $threadId): int
{
    if ($threadId <= 0 || 'forum_thread' !== get_post_type($threadId)) {
        return 0;
    }

    $cacheKey = gta6_forum_get_thread_view_cache_key($threadId);
    $cached   = wp_cache_get($cacheKey, 'gta6_forum_views');

    if (false !== $cached) {
        return (int) $cached;
    }

    $stored = (int) get_post_meta($threadId, '_thread_views', true);
    wp_cache_set($cacheKey, $stored, 'gta6_forum_views', DAY_IN_SECONDS);

    return $stored;
}

function gta6_forum_schedule_view_sync(int $threadId): void
{
    if ($threadId <= 0) {
        return;
    }

    $pendingKey = gta6_forum_get_thread_view_cache_key($threadId);

    if (!wp_cache_get($pendingKey, 'gta6_forum_views_pending')) {
        wp_cache_set($pendingKey, 1, 'gta6_forum_views_pending', 3 * MINUTE_IN_SECONDS);

        if (!wp_next_scheduled('gta6_forum_flush_thread_views', [$threadId])) {
            wp_schedule_single_event(time() + 2 * MINUTE_IN_SECONDS, 'gta6_forum_flush_thread_views', [$threadId]);
        }
    }
}

function gta6_forum_increment_thread_views(int $threadId): int
{
    if ($threadId <= 0 || 'forum_thread' !== get_post_type($threadId)) {
        return 0;
    }

    $current  = gta6_forum_get_thread_views($threadId);
    $cacheKey = gta6_forum_get_thread_view_cache_key($threadId);
    $views    = wp_cache_incr($cacheKey, 1, 'gta6_forum_views');

    if (false === $views) {
        $views = $current + 1;
        wp_cache_set($cacheKey, $views, 'gta6_forum_views', DAY_IN_SECONDS);
    }

    gta6_forum_schedule_view_sync($threadId);

    return (int) $views;
}

function gta6_forum_flush_thread_views(int $threadId): void
{
    if ($threadId <= 0 || 'forum_thread' !== get_post_type($threadId)) {
        return;
    }

    $cacheKey = gta6_forum_get_thread_view_cache_key($threadId);
    $views    = wp_cache_get($cacheKey, 'gta6_forum_views');

    if (false === $views) {
        $views = (int) get_post_meta($threadId, '_thread_views', true);
    }

    $views = max(0, (int) $views);
    update_post_meta($threadId, '_thread_views', $views);

    wp_cache_set($cacheKey, $views, 'gta6_forum_views', DAY_IN_SECONDS);
    wp_cache_delete($cacheKey, 'gta6_forum_views_pending');
}
add_action('gta6_forum_flush_thread_views', 'gta6_forum_flush_thread_views');

function gta6_forum_format_view_count(int $count): string
{
    $count = max(0, $count);
    $label = _n('%s view', '%s views', $count, 'gta6mods');

    return sprintf($label, number_format_i18n($count));
}

function gta6_forum_rest_increment_thread_views(WP_REST_Request $request): WP_REST_Response
{
    $threadId = (int) $request->get_param('id');

    if ($threadId <= 0 || 'forum_thread' !== get_post_type($threadId)) {
        return new WP_REST_Response([
            'message' => __('Thread not found.', 'gta6mods'),
        ], 404);
    }

    $views = gta6_forum_increment_thread_views($threadId);

    return new WP_REST_Response([
        'id'        => $threadId,
        'views'     => $views,
        'formatted' => gta6_forum_format_view_count($views),
    ]);
}

function gta6_forum_register_view_routes(): void
{
    register_rest_route(
        'gta6-forum/v1',
        '/threads/(?P<id>\d+)/views',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6_forum_rest_increment_thread_views',
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => static function ($value): bool {
                        return is_numeric($value) && (int) $value > 0;
                    },
                ],
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6_forum_register_view_routes');
