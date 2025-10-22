<?php
/**
 * Lightweight cache helper utilities.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gta6_get_cloudflare_credentials')) {
    /**
     * Retrieves Cloudflare credentials securely from wp-config.php constants.
     *
     * @return array{zone_id: string|null, api_token: string|null}
     */
    function gta6_get_cloudflare_credentials(): array {
        $credentials = [
            'zone_id'   => defined('CLOUDFLARE_ZONE_ID') ? (string) CLOUDFLARE_ZONE_ID : null,
            'api_token' => defined('CLOUDFLARE_API_TOKEN') ? (string) CLOUDFLARE_API_TOKEN : null,
        ];

        if (empty($credentials['zone_id']) || empty($credentials['api_token'])) {
            error_log('Cloudflare Credentials Error: CLOUDFLARE_ZONE_ID or CLOUDFLARE_API_TOKEN is not defined in wp-config.php.');
        }

        return $credentials;
    }
}

if (!function_exists('gta6mods_cache_build_key')) {
    /**
     * Builds a cache key when falling back to the transient API.
     */
    function gta6mods_cache_build_key(string $group, string $key): string {
        $group = trim($group);

        return $group !== '' ? sprintf('%s_%s', $group, $key) : $key;
    }
}

if (!function_exists('gta6mods_cache_get')) {
    /**
     * Retrieves a cached value with graceful fallback when no persistent object cache is available.
     *
     * @param string $key     Cache key.
     * @param string $group   Cache group.
     * @param mixed  $default Default value when nothing is stored.
     *
     * @return mixed
     */
    function gta6mods_cache_get(string $key, string $group = '', $default = false) {
        if (wp_using_ext_object_cache()) {
            $value = wp_cache_get($key, $group);

            return false === $value ? $default : $value;
        }

        $stored = get_transient(gta6mods_cache_build_key($group, $key));

        return false === $stored ? $default : $stored;
    }
}

if (!function_exists('gta6mods_cache_set')) {
    /**
     * Stores a value in cache using either the external object cache or transients.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to store.
     * @param string $group Cache group.
     * @param int    $ttl   Lifetime in seconds.
     *
     * @return bool
     */
    function gta6mods_cache_set(string $key, $value, string $group = '', int $ttl = 0): bool {
        if (wp_using_ext_object_cache()) {
            return wp_cache_set($key, $value, $group, $ttl);
        }

        $transient_key = gta6mods_cache_build_key($group, $key);

        return set_transient($transient_key, $value, $ttl);
    }
}

if (!function_exists('gta6mods_cache_delete')) {
    /**
     * Deletes a cached value.
     */
    function gta6mods_cache_delete(string $key, string $group = ''): bool {
        if (wp_using_ext_object_cache()) {
            return wp_cache_delete($key, $group);
        }

        $transient_key = gta6mods_cache_build_key($group, $key);

        return delete_transient($transient_key);
    }
}

if (!function_exists('gta6mods_cache_incr')) {
    /**
     * Atomically increments a cached numeric value.
     *
     * @param string $key    Cache key.
     * @param int    $offset Increment amount.
     * @param string $group  Cache group.
     * @param int    $ttl    Lifetime in seconds.
     */
    function gta6mods_cache_incr(string $key, int $offset = 1, string $group = '', int $ttl = 0) {
        $offset = max(1, $offset);

        if (wp_using_ext_object_cache()) {
            $value = wp_cache_incr($key, $offset, $group);

            if (false === $value) {
                wp_cache_add($key, $offset, $group, $ttl);
                $value = $offset;
            } elseif ($ttl > 0) {
                wp_cache_set($key, $value, $group, $ttl);
            }

            return $value;
        }

        $transient_key = gta6mods_cache_build_key($group, $key);
        $current       = get_transient($transient_key);
        if (false === $current || !is_numeric($current)) {
            $current = 0;
        }

        $current += $offset;
        set_transient($transient_key, $current, $ttl);

        return $current;
    }
}

if (!function_exists('gta6mods_cache_decr')) {
    /**
     * Atomically decrements a cached numeric value.
     *
     * @param string $key    Cache key.
     * @param int    $offset Decrement amount.
     * @param string $group  Cache group.
     * @param int    $ttl    Lifetime in seconds when falling back to transients.
     */
    function gta6mods_cache_decr(string $key, int $offset = 1, string $group = '', int $ttl = 0) {
        $offset = max(1, $offset);

        if (wp_using_ext_object_cache()) {
            $value = wp_cache_decr($key, $offset, $group);

            if (false === $value) {
                return false;
            }

            if ($ttl > 0) {
                wp_cache_set($key, $value, $group, $ttl);
            }

            return $value;
        }

        $transient_key = gta6mods_cache_build_key($group, $key);
        $current       = get_transient($transient_key);

        if (false === $current || !is_numeric($current)) {
            return false;
        }

        $current = max(0, (int) $current - $offset);
        set_transient($transient_key, $current, $ttl);

        return $current;
    }
}

if (!function_exists('gta6mods_get_request_ip')) {
    /**
     * Resolves the visitor IP using common proxy headers.
     */
    function gta6mods_get_request_ip(): string {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $header) {
            if (empty($_SERVER[$header])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                continue;
            }

            $value = $_SERVER[$header]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            if ('HTTP_X_FORWARDED_FOR' === $header) {
                $parts = explode(',', $value);
                $value = trim($parts[0]);
            }

            $value = trim((string) $value);

            if ('' !== $value) {
                return sanitize_text_field($value);
            }
        }

        return 'unknown';
    }
}

if (!function_exists('gta6mods_get_version_cache_nonce')) {
    /**
     * Returns the cache nonce for a version-specific payload.
     */
    function gta6mods_get_version_cache_nonce(int $version_id): string {
        $version_id = absint($version_id);

        if ($version_id <= 0) {
            return '0';
        }

        $nonce = gta6mods_cache_get('nonce_' . $version_id, 'gta6mods_version_nonce');

        if (!is_string($nonce) || '' === $nonce) {
            $nonce = wp_generate_uuid4();
            gta6mods_cache_set('nonce_' . $version_id, $nonce, 'gta6mods_version_nonce');
        }

        return $nonce;
    }
}

if (!function_exists('gta6mods_bump_version_cache_nonce')) {
    /**
     * Invalidates cached waiting room output and token data for a version.
     */
    function gta6mods_bump_version_cache_nonce(int $version_id): void {
        $version_id = absint($version_id);

        if ($version_id <= 0) {
            return;
        }

        $nonce = wp_generate_uuid4();
        gta6mods_cache_set('nonce_' . $version_id, $nonce, 'gta6mods_version_nonce');
        gta6mods_cache_delete('waiting_room_html_' . $version_id, 'gta6mods_waiting_room');
        gta6mods_cache_delete('waiting_room_html_external_version_' . $version_id, 'gta6mods_waiting_room');
    }
}

if (!function_exists('gta6mods_invalidate_external_waiting_room_cache')) {
    /**
     * Invalidates cached waiting room output for external download flows.
     */
    function gta6mods_invalidate_external_waiting_room_cache(int $mod_id, int $target_id = 0, string $type = 'mod'): void {
        $mod_id   = absint($mod_id);
        $target_id = absint($target_id);

        if ($mod_id <= 0) {
            return;
        }

        gta6mods_cache_delete(sprintf('waiting_room_html_external_mod_%d', $mod_id), 'gta6mods_waiting_room');

        if ('version' === strtolower($type) && $target_id > 0) {
            $version_cache_key = sprintf('waiting_room_html_external_version_%d', $target_id);

            gta6mods_cache_delete($version_cache_key, 'gta6mods_waiting_room');
        }
    }
}

if (!function_exists('gta6mods_get_waiting_room_security_mode')) {
    /**
     * Returns the configured waiting room security mode.
     */
    function gta6mods_get_waiting_room_security_mode(): string {
        $mode = get_option('gta6mods_waiting_room_security_mode', 'strict');

        return in_array($mode, ['reduced', 'strict'], true) ? $mode : 'strict';
    }
}

if (!function_exists('gta6mods_waiting_room_is_reduced_security')) {
    /**
     * Determines whether the waiting room runs in reduced security mode.
     */
    function gta6mods_waiting_room_is_reduced_security(): bool {
        return 'reduced' === gta6mods_get_waiting_room_security_mode();
    }
}

if (!function_exists('gta6mods_get_waiting_room_client_token')) {
    /**
     * Provides a stable client token for relaxed security environments.
     */
    function gta6mods_get_waiting_room_client_token(): string {
        static $token = null;

        if (null !== $token) {
            return $token;
        }

        if (!gta6mods_waiting_room_is_reduced_security()) {
            $token = '';

            return $token;
        }

        $cookie_name = apply_filters('gta6mods_waiting_room_client_cookie_name', 'gta6mods_waiting_room');
        $candidate   = '';

        if (isset($_COOKIE[$cookie_name])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw = wp_unslash($_COOKIE[$cookie_name]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw = sanitize_text_field($raw);

            if (preg_match('/^[A-Za-z0-9\-]{10,}$/', $raw)) {
                $candidate = $raw;
            }
        }

        if ('' === $candidate) {
            $candidate = wp_generate_uuid4();
            $expires   = time() + YEAR_IN_SECONDS;
            $path      = defined('COOKIEPATH') ? (string) COOKIEPATH : '/';
            $secure    = is_ssl();

            $cookie_args = [
                'expires'  => $expires,
                'path'     => $path,
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ];

            if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
                $cookie_args['domain'] = COOKIE_DOMAIN;
            }

            if (!headers_sent()) {
                setcookie($cookie_name, $candidate, $cookie_args);

                if (defined('SITECOOKIEPATH') && SITECOOKIEPATH !== $path) {
                    $alt_args         = $cookie_args;
                    $alt_args['path'] = (string) SITECOOKIEPATH;
                    setcookie($cookie_name, $candidate, $alt_args);
                }
            }
        }

        $token = $candidate;

        return $token;
    }
}

if (!function_exists('gta6mods_get_waiting_room_identifier')) {
    /**
     * Resolves the visitor identifier depending on the configured security mode.
     */
    function gta6mods_get_waiting_room_identifier(?string $ip = null): string {
        $ip = is_string($ip) ? trim($ip) : '';

        if ('' === $ip) {
            $ip = gta6mods_get_request_ip();
        }

        if (gta6mods_waiting_room_is_reduced_security()) {
            $token = gta6mods_get_waiting_room_client_token();

            if ('' === $token) {
                static $ephemeral_token = null;

                if (null === $ephemeral_token) {
                    $ephemeral_token = 'anon_' . wp_generate_uuid4();
                }

                $token = $ephemeral_token;
            }

            return $token;
        }

        return $ip !== '' ? $ip : 'unknown';
    }
}

if (!function_exists('gta6mods_get_rate_limit_fingerprint')) {
    /**
     * Builds a fingerprint for rate limiting and throttling.
     */
    function gta6mods_get_rate_limit_fingerprint(string $namespace = 'download', ?int $timestamp = null, ?int $bucket_size = null): string {
        $ip = gta6mods_get_request_ip();

        $user_agent_raw = isset($_SERVER['HTTP_USER_AGENT'])
            ? (string) $_SERVER['HTTP_USER_AGENT'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : 'unknown';

        $user_agent = trim(wp_strip_all_tags($user_agent_raw));
        if ('' === $user_agent) {
            $user_agent = 'unknown';
        }

        $user_agent = substr($user_agent, 0, 190);

        if (null === $timestamp || $timestamp <= 0) {
            $timestamp = time();
        }

        if (null === $bucket_size || $bucket_size <= 0) {
            $bucket_size = MINUTE_IN_SECONDS;
        }

        $bucket    = (int) floor($timestamp / $bucket_size);
        $salt_seed = $namespace . '|' . $bucket_size . '|' . $bucket;
        $salt      = hash_hmac('sha256', $salt_seed, wp_salt('nonce'));

        $identifier = gta6mods_get_waiting_room_identifier($ip);

        return hash('sha256', implode('|', [$identifier, $user_agent, $salt]));
    }
}
