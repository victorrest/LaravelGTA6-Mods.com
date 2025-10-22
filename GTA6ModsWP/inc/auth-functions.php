<?php
/**
 * Authentication helpers and REST endpoints for ultra-fast login & registration.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build a sanitized redirect target that respects ?redirect_to= and defaults to the homepage.
 */
function gta6mods_get_auth_redirect() {
    $default = home_url('/');
    if (empty($_GET['redirect_to'])) {
        return $default;
    }

    $candidate = wp_unslash($_GET['redirect_to']);
    $validated = wp_validate_redirect($candidate, $default);

    return $validated ? $validated : $default;
}

/**
 * Minimal helper to produce JSON-ready success payloads.
 */
function gta6mods_prepare_auth_success(WP_User $user, $message) {
    return [
        'message'  => $message,
        'user'     => [
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'username'     => $user->user_login,
        ],
        'redirect' => gta6mods_get_auth_redirect(),
    ];
}

/**
 * Validate the shared nonce used by the public auth forms.
 */
function gta6mods_validate_auth_nonce($nonce) {
    if (empty($nonce) || !is_string($nonce)) {
        return new WP_Error('invalid_nonce', __('Missing security token. Please refresh and try again.', 'gta6mods'));
    }

    if (!wp_verify_nonce($nonce, 'gta6mods_auth')) {
        return new WP_Error('invalid_nonce', __('Your session has expired. Please refresh the page and try again.', 'gta6mods'));
    }

    return true;
}

/**
 * Retrieve the canonical URL for the authentication page.
 */
function gta6mods_get_auth_page_url() {
    $default_url = home_url('/login-register/');
    $page        = get_page_by_path('login-register');

    if (!$page instanceof WP_Post) {
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'page-login-register.php',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!empty($pages)) {
            $page = get_post($pages[0]);
        }
    }

    if ($page instanceof WP_Post) {
        $default_url = get_permalink($page);
    }

    return apply_filters('gta6mods_auth_page_url', $default_url);
}

/**
 * Build a secure reset-password URL that reuses the decoupled auth screen.
 */
function gta6mods_get_password_reset_url($login, $key) {
    return add_query_arg(
        [
            'action' => 'rp',
            'key'    => $key,
            'login'  => $login,
        ],
        gta6mods_get_auth_page_url()
    );
}

/**
 * Determine the best-effort client identifier for rate limiting.
 */
function gta6mods_get_client_identifier() {
    $candidates = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];

    foreach ($candidates as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        $raw = wp_unslash($_SERVER[$header]);

        if ('HTTP_X_FORWARDED_FOR' === $header) {
            $parts = explode(',', $raw);
            $raw   = trim($parts[0]);
        }

        $raw = trim($raw);

        if (filter_var($raw, FILTER_VALIDATE_IP)) {
            return $raw;
        }
    }

    return 'unknown';
}

/**
 * Compose a cache key used for the authentication rate limiter.
 */
function gta6mods_get_rate_limit_key($action) {
    $fingerprint = md5(strtolower(gta6mods_get_client_identifier()));

    return sprintf('gta6mods_rl_%s_%s', sanitize_key($action), $fingerprint);
}

/**
 * Determine whether the client is currently rate limited.
 */
function gta6mods_check_rate_limit($action, $max_attempts = 5, $window = 300) {
    $key   = gta6mods_get_rate_limit_key($action);
    $state = get_transient($key);

    if (!is_array($state) || empty($state['timestamp'])) {
        return true;
    }

    $elapsed = time() - (int) $state['timestamp'];

    if ($elapsed >= $window) {
        delete_transient($key);
        return true;
    }

    $attempts = isset($state['attempts']) ? (int) $state['attempts'] : 0;

    if ($attempts < $max_attempts) {
        return true;
    }

    $retry_after = max(0, $window - $elapsed);

    return new WP_Error(
        'too_many_attempts',
        __('Too many attempts. Please wait a few minutes before trying again.', 'gta6mods'),
        [
            'status'      => 429,
            'retry_after' => $retry_after,
        ]
    );
}

/**
 * Increment the attempt counter for the provided action.
 */
function gta6mods_register_failed_attempt($action, $max_attempts = 5, $window = 300) {
    $key       = gta6mods_get_rate_limit_key($action);
    $timestamp = time();
    $state     = get_transient($key);

    if (!is_array($state) || empty($state['timestamp']) || ($timestamp - (int) $state['timestamp']) >= $window) {
        $state = [
            'attempts'  => 1,
            'timestamp' => $timestamp,
        ];
    } else {
        $state['attempts'] = isset($state['attempts']) ? (int) $state['attempts'] + 1 : 1;
        $state['timestamp'] = $timestamp;
    }

    set_transient($key, $state, $window);

    return $state['attempts'];
}

/**
 * Clear any stored failures for the provided action.
 */
function gta6mods_reset_rate_limit($action) {
    delete_transient(gta6mods_get_rate_limit_key($action));
}

/**
 * Helper to attach rate-limit metadata to an error and register the attempt.
 */
function gta6mods_register_failed_auth($action, WP_Error $error) {
    gta6mods_register_failed_attempt($action);

    $code = $error->get_error_code();
    $data = $error->get_error_data($code);

    if (!is_array($data) || !isset($data['status'])) {
        $error->add_data(['status' => 400], $code);
    }

    return $error;
}

/**
 * Register REST routes for login and registration.
 */
function gta6mods_register_auth_routes() {
    register_rest_route(
        'gta6mods/v1',
        '/login',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_login',
            'permission_callback' => '__return_true',
            'args'                => [
                'login'    => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password' => [
                    'required'          => true,
                    'sanitize_callback' => 'wp_unslash',
                ],
                'remember' => [
                    'required'          => false,
                    'sanitize_callback' => static function ($value) {
                        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    },
                    'default'           => false,
                ],
                'nonce'    => [
                    'required' => true,
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/register',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_register',
            'permission_callback' => '__return_true',
            'args'                => [
                'username'              => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_user',
                ],
                'email'                 => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                ],
                'password'              => [
                    'required'          => true,
                    'sanitize_callback' => 'wp_unslash',
                ],
                'password_confirmation' => [
                    'required'          => true,
                    'sanitize_callback' => 'wp_unslash',
                ],
                'terms'                 => [
                    'required'          => false,
                    'sanitize_callback' => static function ($value) {
                        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    },
                    'default'           => false,
                ],
                'nonce'                 => [
                    'required' => true,
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/lost-password/request',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_lost_password_request',
            'permission_callback' => '__return_true',
            'args'                => [
                'email' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                ],
                'nonce' => [
                    'required' => true,
                ],
            ],
        ]
    );

    register_rest_route(
        'gta6mods/v1',
        '/lost-password/reset',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'gta6mods_rest_lost_password_reset',
            'permission_callback' => '__return_true',
            'args'                => [
                'login'                => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_user',
                ],
                'key'                  => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password'             => [
                    'required'          => true,
                    'sanitize_callback' => 'wp_unslash',
                ],
                'password_confirmation' => [
                    'required'          => true,
                    'sanitize_callback' => 'wp_unslash',
                ],
                'nonce'                => [
                    'required' => true,
                ],
            ],
        ]
    );
}
add_action('rest_api_init', 'gta6mods_register_auth_routes');

/**
 * Handle REST login.
 */
function gta6mods_rest_login(WP_REST_Request $request) {
    nocache_headers();

    $nonce_validation = gta6mods_validate_auth_nonce($request->get_param('nonce'));
    if (is_wp_error($nonce_validation)) {
        return $nonce_validation;
    }

    $rate_limit = gta6mods_check_rate_limit('login');
    if (is_wp_error($rate_limit)) {
        return $rate_limit;
    }

    $login    = $request->get_param('login');
    $password = $request->get_param('password');
    $remember = (bool) $request->get_param('remember');

    if (empty($login) || empty($password)) {
        return gta6mods_register_failed_auth('login', new WP_Error(
            'missing_credentials',
            __('Please enter both your username/email and password.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    $signon = wp_signon(
        [
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => $remember,
        ],
        false
    );

    if (is_wp_error($signon)) {
        return gta6mods_register_failed_auth('login', new WP_Error(
            'login_failed',
            $signon->get_error_message(),
            ['status' => 401]
        ));
    }

    wp_set_current_user($signon->ID);

    gta6mods_reset_rate_limit('login');

    $payload = gta6mods_prepare_auth_success($signon, __('Successfully logged in.', 'gta6mods'));

    return new WP_REST_Response($payload, 200);
}

/**
 * Handle REST registration.
 */
function gta6mods_rest_register(WP_REST_Request $request) {
    nocache_headers();

    $nonce_validation = gta6mods_validate_auth_nonce($request->get_param('nonce'));
    if (is_wp_error($nonce_validation)) {
        return $nonce_validation;
    }

    $rate_limit = gta6mods_check_rate_limit('register');
    if (is_wp_error($rate_limit)) {
        return $rate_limit;
    }

    $username = $request->get_param('username');
    $email    = $request->get_param('email');
    $password = $request->get_param('password');
    $confirm  = $request->get_param('password_confirmation');
    $terms    = (bool) $request->get_param('terms');

    if (!$terms) {
        return gta6mods_register_failed_auth('register', new WP_Error(
            'terms_unchecked',
            __('You must agree to the Terms of Service.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        return gta6mods_register_failed_auth('register', new WP_Error(
            'missing_fields',
            __('Please fill in all required fields.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    if (!validate_username($username)) {
        return gta6mods_register_failed_auth('register', new WP_Error(
            'invalid_username',
            __('Please choose a different username. Only letters, numbers, spaces, hyphens and underscores are allowed.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    if (!is_email($email)) {
        return gta6mods_register_failed_auth('register', new WP_Error(
            'invalid_email',
            __('Please enter a valid email address.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    if ($password !== $confirm) {
        return gta6mods_register_failed_auth('register', new WP_Error(
            'password_mismatch',
            __('Passwords do not match. Please try again.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    if (username_exists($username) || email_exists($email)) {
        return gta6mods_register_failed_auth('register', new WP_Error(
            'user_exists',
            __('An account with that username or email already exists.', 'gta6mods'),
            ['status' => 409]
        ));
    }

    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return gta6mods_register_failed_auth('register', new WP_Error(
            'registration_failed',
            $user_id->get_error_message(),
            ['status' => 500]
        ));
    }

    // Update display name to something nicer by default.
    wp_update_user([
        'ID'           => $user_id,
        'display_name' => sanitize_text_field($username),
    ]);

    $user = get_user_by('id', $user_id);

    if ($user instanceof WP_User) {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, false);
        do_action('wp_login', $user->user_login, $user);
    }

    gta6mods_reset_rate_limit('register');

    $payload = gta6mods_prepare_auth_success($user, __('Registration successful. Welcome aboard!', 'gta6mods'));

    return new WP_REST_Response($payload, 200);
}

/**
 * Handle REST lost password requests.
 */
function gta6mods_rest_lost_password_request(WP_REST_Request $request) {
    nocache_headers();

    $nonce_validation = gta6mods_validate_auth_nonce($request->get_param('nonce'));
    if (is_wp_error($nonce_validation)) {
        return $nonce_validation;
    }

    $rate_limit = gta6mods_check_rate_limit('lost_password_request');
    if (is_wp_error($rate_limit)) {
        return $rate_limit;
    }

    $email            = $request->get_param('email');
    $generic_response = __('If that email is registered, a reset link is on its way.', 'gta6mods');

    if (empty($email) || !is_email($email)) {
        return gta6mods_register_failed_auth('lost_password_request', new WP_Error(
            'invalid_email',
            __('Please enter a valid email address.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    $user = get_user_by('email', $email);

    if (!$user instanceof WP_User) {
        gta6mods_register_failed_attempt('lost_password_request');

        return new WP_REST_Response([
            'message' => $generic_response,
        ], 200);
    }

    $key = get_password_reset_key($user);

    if (is_wp_error($key)) {
        return gta6mods_register_failed_auth('lost_password_request', new WP_Error(
            'reset_key_error',
            $key->get_error_message(),
            ['status' => 500]
        ));
    }

    $reset_url = gta6mods_get_password_reset_url($user->user_login, $key);

    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $subject  = sprintf(__('[%s] Password Reset', 'gta6mods'), $blogname);
    $subject  = apply_filters('retrieve_password_title', $subject, $user->user_login, $user);

    $message  = sprintf(__('Someone requested a password reset for the following account on %s:', 'gta6mods'), $blogname) . "\r\n\r\n";
    $message .= sprintf(__('Username: %s', 'gta6mods'), $user->user_login) . "\r\n\r\n";
    $message .= __('If this was a mistake, just ignore this email and nothing will happen.', 'gta6mods') . "\r\n\r\n";
    $message .= __('To reset your password, visit the following address:', 'gta6mods') . "\r\n\r\n";
    $message .= esc_url_raw($reset_url) . "\r\n";

    $message = apply_filters('retrieve_password_message', $message, $key, $user->user_login, $user);

    $default_from_email = 'help@gta6-mods.com';
    $default_from_name  = 'GTA6-Mods.com';

    $from_email = sanitize_email(apply_filters('gta6mods_auth_email_from_address', $default_from_email, $user));
    if (empty($from_email)) {
        $from_email = $default_from_email;
    }

    $from_name = wp_strip_all_tags(apply_filters('gta6mods_auth_email_from_name', $default_from_name, $user));
    if (empty($from_name)) {
        $from_name = $default_from_name;
    }

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $from_name, $from_email),
    ];

    if (!wp_mail($user->user_email, $subject, $message, $headers)) {
        return gta6mods_register_failed_auth('lost_password_request', new WP_Error(
            'email_failed',
            __('We were unable to send the reset email. Please try again later.', 'gta6mods'),
            ['status' => 500]
        ));
    }

    do_action('retrieve_password', $user->user_login);

    gta6mods_reset_rate_limit('lost_password_request');

    return new WP_REST_Response([
        'message' => $generic_response,
    ], 200);
}

/**
 * Handle REST password reset submissions.
 */
function gta6mods_rest_lost_password_reset(WP_REST_Request $request) {
    nocache_headers();

    $nonce_validation = gta6mods_validate_auth_nonce($request->get_param('nonce'));
    if (is_wp_error($nonce_validation)) {
        return $nonce_validation;
    }

    $rate_limit = gta6mods_check_rate_limit('lost_password_reset');
    if (is_wp_error($rate_limit)) {
        return $rate_limit;
    }

    $login    = $request->get_param('login');
    $key      = $request->get_param('key');
    $password = $request->get_param('password');
    $confirm  = $request->get_param('password_confirmation');

    if (empty($login) || empty($key) || empty($password) || empty($confirm)) {
        return gta6mods_register_failed_auth('lost_password_reset', new WP_Error(
            'missing_fields',
            __('Please complete all required fields.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    if ($password !== $confirm) {
        return gta6mods_register_failed_auth('lost_password_reset', new WP_Error(
            'password_mismatch',
            __('Passwords do not match. Please try again.', 'gta6mods'),
            ['status' => 400]
        ));
    }

    $user = check_password_reset_key($key, $login);

    if (is_wp_error($user)) {
        return gta6mods_register_failed_auth('lost_password_reset', new WP_Error(
            'invalid_reset_token',
            $user->get_error_message(),
            ['status' => 400]
        ));
    }

    reset_password($user, $password);

    gta6mods_reset_rate_limit('lost_password_reset');

    return new WP_REST_Response([
        'message' => __('Your password has been reset. You can now sign in.', 'gta6mods'),
    ], 200);
}
