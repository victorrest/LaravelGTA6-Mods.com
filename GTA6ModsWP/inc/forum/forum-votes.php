<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

enum GTA6_Forum_Vote: int {
    case Up = 1;
    case Down = -1;
    case Neutral = 0;
}

/**
 * Handles voting for both threads and comments.
 */
function gta6_forum_register_vote_routes(): void {
    $namespace = 'gta6-forum/v1';

    register_rest_route(
        $namespace,
        '/threads/(?P<id>\d+)/vote',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => static function (WP_REST_Request $request): WP_REST_Response {
                $postId = (int) $request['id'];
                $vote   = gta6_forum_parse_vote_from_request($request);

                if (!get_post($postId) || 'forum_thread' !== get_post_type($postId)) {
                    return new WP_REST_Response([
                        'message' => __('Thread not found.', 'gta6mods'),
                    ], 404);
                }

                $result = gta6_forum_process_vote($postId, 'thread', $vote);

                return new WP_REST_Response($result, 200);
            },
            'permission_callback' => 'gta6_forum_can_vote',
            'args'                => [
                'direction' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'type'              => 'string',
                ],
            ],
        ]
    );

    register_rest_route(
        $namespace,
        '/comments/(?P<id>\d+)/vote',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => static function (WP_REST_Request $request): WP_REST_Response {
                $commentId = (int) $request['id'];
                $vote      = gta6_forum_parse_vote_from_request($request);

                $comment = get_comment($commentId);
                if (!$comment instanceof WP_Comment || 'forum_thread' !== get_post_type((int) $comment->comment_post_ID)) {
                    return new WP_REST_Response([
                        'message' => __('Comment not found.', 'gta6mods'),
                    ], 404);
                }

                $result = gta6_forum_process_vote($commentId, 'comment', $vote);

                return new WP_REST_Response($result, 200);
            },
            'permission_callback' => 'gta6_forum_can_vote',
            'args'                => [
                'direction' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'type'              => 'string',
                ],
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6_forum_register_vote_routes');

function gta6_forum_can_vote(): bool {
    return is_user_logged_in() || !gta6_forum_is_rate_limited();
}

function gta6_forum_is_rate_limited(): bool {
    $fingerprint = gta6_forum_get_request_fingerprint();
    $key         = 'vote-rate:' . $fingerprint;
    $group       = 'gta6_forum_rl';

    $current = wp_cache_get($key, $group);
    if (false === $current) {
        wp_cache_set($key, 1, $group, MINUTE_IN_SECONDS);

        return false;
    }

    if ($current >= 60) {
        return true;
    }

    wp_cache_incr($key, 1, $group);

    return false;
}

function gta6_forum_parse_vote_from_request(WP_REST_Request $request): GTA6_Forum_Vote {
    return match (strtolower((string) $request['direction'])) {
        'up'      => GTA6_Forum_Vote::Up,
        'down'    => GTA6_Forum_Vote::Down,
        'neutral' => GTA6_Forum_Vote::Neutral,
        default   => GTA6_Forum_Vote::Neutral,
    };
}

/**
 * Processes a vote for a thread or comment.
 *
 * @param int               $objectId Thread or comment ID.
 * @param 'thread'|'comment' $type Type of the voted entity.
 */
function gta6_forum_process_vote(int $objectId, string $type, GTA6_Forum_Vote $vote): array {
    global $wpdb;

    $table = 'thread' === $type ? gta6_forum_thread_votes_table() : gta6_forum_comment_votes_table();
    $userId = get_current_user_id();
    $fingerprint = $userId > 0 ? null : gta6_forum_get_request_fingerprint();

    $column = 'thread' === $type ? 'thread_id' : 'comment_id';

    $where = [$column => $objectId];
    if ($userId > 0) {
        $where['user_id'] = $userId;
    } elseif ($fingerprint) {
        $where['voter_fingerprint'] = $fingerprint;
    }

    $previousVote = 0;
    if (!empty($where)) {
        $clauses = [];
        $params  = [];
        foreach ($where as $columnName => $value) {
            $clauses[] = sprintf('%s = %s', $columnName, is_int($value) ? '%d' : '%s');
            $params[]  = $value;
        }

        $prepared = $wpdb->prepare(
            'SELECT vote FROM ' . $table . ' WHERE ' . implode(' AND ', $clauses) . ' LIMIT 1',
            $params
        );
        $previousVote = (int) $wpdb->get_var($prepared);
    }

    $targetVote = $vote->value;
    if ($targetVote === $previousVote) {
        $targetVote = GTA6_Forum_Vote::Neutral->value;
    }

    if (0 === $targetVote) {
        // Remove the existing vote entry.
        if (!empty($where)) {
            $wpdb->delete($table, $where);
        }
    } else {
        $data = [
            $column            => $objectId,
            'vote'             => $targetVote,
            'created_at'       => current_time('mysql', true),
            'updated_at'       => current_time('mysql', true),
        ];

        if ($userId > 0) {
            $data['user_id'] = $userId;
        }

        if ($fingerprint) {
            $data['voter_fingerprint'] = $fingerprint;
        }

        if (!isset($data['user_id'])) {
            unset($data['user_id']);
        }
        if (!isset($data['voter_fingerprint'])) {
            unset($data['voter_fingerprint']);
        }

        // Upsert using ON DUPLICATE KEY to keep the request lightweight.
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        foreach ($data as $value) {
            $placeholders[] = is_int($value) ? '%d' : '%s';
            $values[] = $value;
        }

        $updates = implode(', ', array_map(static fn(string $columnName): string => sprintf('%s = VALUES(%s)', $columnName, $columnName), $columns));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            $updates
        );

        $wpdb->query($wpdb->prepare($sql, $values));
    }

    $delta = $targetVote - $previousVote;
    $score = gta6_forum_adjust_score_cache($type, $objectId, $delta);
    gta6_forum_flag_score_as_dirty($type, $objectId);
    wp_cache_set('thread-list-bust', (string) microtime(true), 'gta6_forum_rest', HOUR_IN_SECONDS);

    return [
        'score'            => $score,
        'user_vote'        => $targetVote,
        'previous_vote'    => $previousVote,
        'delta'            => $delta,
        'object_id'        => $objectId,
        'type'             => $type,
    ];
}

function gta6_forum_adjust_score_cache(string $type, int $objectId, int $delta): int {
    $key = $type . '_score:' . $objectId;
    $group = 'gta6_forum_scores';

    $current = wp_cache_get($key, $group);
    if (false === $current) {
        $current = 'thread' === $type
            ? (int) get_post_meta($objectId, '_thread_score', true)
            : (int) get_comment_meta($objectId, '_comment_score', true);

        wp_cache_set($key, $current, $group, DAY_IN_SECONDS);
    }

    $newScore = $current + $delta;

    if ($delta > 0) {
        $newScore = wp_cache_incr($key, $delta, $group);
        if (false === $newScore) {
            $newScore = $current + $delta;
        }
    } elseif ($delta < 0) {
        $newScore = wp_cache_decr($key, abs($delta), $group);
        $target = $current + $delta;
        if (false === $newScore || $newScore !== $target) {
            $newScore = $target;
        }
    } else {
        $newScore = (int) $current;
    }

    wp_cache_set($key, (int) $newScore, $group, DAY_IN_SECONDS);

    return (int) $newScore;
}

function gta6_forum_flag_score_as_dirty(string $type, int $objectId): void {
    $group = 'gta6_forum_sync';
    $flagKey = 'dirty-scores';

    $payload = wp_cache_get($flagKey, $group);
    if (!is_array($payload)) {
        $payload = [
            'threads'  => [],
            'comments' => [],
        ];
    }

    $bucket = 'thread' === $type ? 'threads' : 'comments';
    $payload[$bucket][$objectId] = time();

    wp_cache_set($flagKey, $payload, $group, DAY_IN_SECONDS);
}

function gta6_forum_get_request_fingerprint(): string {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return wp_hash($ip . '|' . $userAgent);
}

function gta6_forum_get_user_vote(string $type, int $objectId): int {
    global $wpdb;

    $table  = 'thread' === $type ? gta6_forum_thread_votes_table() : gta6_forum_comment_votes_table();
    $column = 'thread' === $type ? 'thread_id' : 'comment_id';
    $userId = get_current_user_id();

    $where = [$column => $objectId];
    if ($userId > 0) {
        $where['user_id'] = $userId;
    } else {
        $where['voter_fingerprint'] = gta6_forum_get_request_fingerprint();
    }

    $clauses = [];
    $params  = [];
    foreach ($where as $columnName => $value) {
        $clauses[] = sprintf('%s = %s', $columnName, is_int($value) ? '%d' : '%s');
        $params[]  = $value;
    }

    $prepared = $wpdb->prepare(
        'SELECT vote FROM ' . $table . ' WHERE ' . implode(' AND ', $clauses) . ' LIMIT 1',
        $params
    );

    return (int) $wpdb->get_var($prepared);
}
