<?php
/**
 * Helper utilities for REST API responses.
 *
 * @package GTA6-Mods
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gta6mods_rest_normalize_etag')) {
    /**
     * Normalizes an ETag header value for comparison.
     *
     * @param string $etag Raw ETag header value.
     * @return string Normalized ETag without weakness prefix or quotes.
     */
    function gta6mods_rest_normalize_etag($etag) {
        if (!is_string($etag)) {
            return '';
        }

        $normalized = trim($etag);
        if ('' === $normalized) {
            return '';
        }

        $has_weak_prefix = function_exists('str_starts_with')
            ? str_starts_with($normalized, 'W/')
            : (substr($normalized, 0, 2) === 'W/');

        if ($has_weak_prefix) {
            $normalized = substr($normalized, 2);
        }

        return trim($normalized, '"');
    }
}

if (!function_exists('gta6mods_rest_request_has_valid_nonce')) {
    /**
     * Determines whether a REST request carries a valid nonce for the given action.
     *
     * @param WP_REST_Request $request Request instance.
     * @param string          $action  Nonce action. Defaults to 'wp_rest'.
     * @return bool True when the nonce is present and valid.
     */
    function gta6mods_rest_request_has_valid_nonce(WP_REST_Request $request, $action = 'wp_rest') {
        $nonce_header = $request->get_header('x-wp-nonce');
        if ($nonce_header && wp_verify_nonce($nonce_header, $action)) {
            return true;
        }

        $param_nonce = $request->get_param('_wpnonce');
        if ($param_nonce && wp_verify_nonce($param_nonce, $action)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('gta6mods_rest_request_has_valid_comment_nonce')) {
    /**
     * Validates the nonce supplied with a REST comment submission request.
     *
     * @param WP_REST_Request $request Request instance.
     * @return bool True when the request passes the nonce checks.
     */
    function gta6mods_rest_request_has_valid_comment_nonce(WP_REST_Request $request) {
        if (gta6mods_rest_request_has_valid_nonce($request, 'wp_rest')) {
            return true;
        }

        $form_nonce = $request->get_param('nonce');
        if ($form_nonce && wp_verify_nonce($form_nonce, 'gta6_comments_nonce')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('gta6mods_rest_request_has_valid_tracking_nonce')) {
    /**
     * Validates tracking requests to prevent unauthorised counter inflation.
     *
     * @param WP_REST_Request $request Request instance.
     * @return bool
     */
    function gta6mods_rest_request_has_valid_tracking_nonce(WP_REST_Request $request) {
        if (gta6mods_rest_request_has_valid_nonce($request, 'wp_rest')) {
            return true;
        }

        $header_nonce = $request->get_header('X-GTA6-Nonce');
        if ($header_nonce && wp_verify_nonce($header_nonce, 'gta6mods_tracking')) {
            return true;
        }

        $param_nonce = $request->get_param('_gta6_nonce');
        if ($param_nonce && wp_verify_nonce($param_nonce, 'gta6mods_tracking')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('gta6mods_rest_prepare_response')) {
    /**
     * Prepares a REST response with optional cache headers.
     *
     * @param mixed           $data     Payload for the response.
     * @param WP_REST_Request $request  Original REST request.
     * @param array|bool      $options  Cache options or legacy boolean for public/private.
     *
     * @return WP_REST_Response
     */
    function gta6mods_rest_prepare_response($data, WP_REST_Request $request, $options = []) {
        if (is_bool($options)) {
            $options = [
                'public' => $options,
            ];
        }

        $options = is_array($options) ? $options : [];

        $is_public   = array_key_exists('public', $options) ? (bool) $options['public'] : true;
        $status_code = isset($options['status']) ? (int) $options['status'] : 200;

        if ($is_public) {
            $max_age                = isset($options['max_age']) ? max(0, (int) $options['max_age']) : 300;
            $stale_while_revalidate = isset($options['stale_while_revalidate']) ? max(0, (int) $options['stale_while_revalidate']) : 600;
            $cdn_max_age            = isset($options['cdn_max_age']) ? max(0, (int) $options['cdn_max_age']) : 3600;
            $cloudflare_max_age     = isset($options['cloudflare_max_age']) ? max(0, (int) $options['cloudflare_max_age']) : 14400;
            $vary_header            = isset($options['vary']) ? (string) $options['vary'] : 'Accept-Encoding, Accept-Language';
            $last_modified          = isset($options['last_modified']) ? (int) $options['last_modified'] : 0;

            $etag_raw = '';
            if (!empty($options['etag'])) {
                $etag_raw = (string) $options['etag'];
            } else {
                $json_payload = wp_json_encode($data);
                if (is_string($json_payload) && '' !== $json_payload) {
                    $etag_raw = sha1($json_payload);
                }
            }

            $normalized_etag = gta6mods_rest_normalize_etag($etag_raw);
            $etag_header      = '' !== $normalized_etag ? 'W/"' . $normalized_etag . '"' : '';

            $request_etag = gta6mods_rest_normalize_etag($request->get_header('If-None-Match'));

            if ('' !== $normalized_etag && '' !== $request_etag && $request_etag === $normalized_etag) {
                $response = new WP_REST_Response(null, 304);
            } else {
                $response = new WP_REST_Response($data, $status_code);
            }

            $headers = [
                'Cache-Control'                => sprintf('public, max-age=%d, stale-while-revalidate=%d', $max_age, $stale_while_revalidate),
                'CDN-Cache-Control'            => sprintf('public, max-age=%d', $cdn_max_age),
                'Cloudflare-CDN-Cache-Control' => sprintf('max-age=%d', $cloudflare_max_age),
                'Vary'                         => $vary_header,
            ];

            if ('' !== $etag_header) {
                $headers['ETag'] = $etag_header;
            }

            if ($last_modified > 0) {
                $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $last_modified) . ' GMT';
            }

            $response->set_headers($headers);

            return $response;
        }

        $vary_header = isset($options['vary']) ? (string) $options['vary'] : 'Cookie, Accept-Encoding, Accept-Language';

        $response = new WP_REST_Response($data, $status_code);
        $response->set_headers([
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
            'Vary'          => $vary_header,
        ]);

        return $response;
    }
}

if (!function_exists('gta6mods_rest_request_has_valid_nonce')) {
    /**
     * Determines whether a REST request carries a valid nonce for the given action.
     *
     * @param WP_REST_Request $request Request instance.
     * @param string          $action  Nonce action. Defaults to 'wp_rest'.
     * @return bool True when the nonce is present and valid.
     */
    function gta6mods_rest_request_has_valid_nonce(WP_REST_Request $request, $action = 'wp_rest') {
        $nonce_header = $request->get_header('x-wp-nonce');
        if ($nonce_header && wp_verify_nonce($nonce_header, $action)) {
            return true;
        }

        $param_nonce = $request->get_param('_wpnonce');
        if ($param_nonce && wp_verify_nonce($param_nonce, $action)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('gta6mods_rest_request_has_valid_comment_nonce')) {
    /**
     * Validates the nonce supplied with a REST comment submission request.
     *
     * @param WP_REST_Request $request Request instance.
     * @return bool True when the request passes the nonce checks.
     */
    function gta6mods_rest_request_has_valid_comment_nonce(WP_REST_Request $request) {
        if (gta6mods_rest_request_has_valid_nonce($request, 'wp_rest')) {
            return true;
        }

        $form_nonce = $request->get_param('nonce');
        if ($form_nonce && wp_verify_nonce($form_nonce, 'gta6_comments_nonce')) {
            return true;
        }

        return false;
    }
}
