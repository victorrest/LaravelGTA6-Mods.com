<?php
/**
 * Template Name: Login & Register
 */

if (!defined('ABSPATH')) {
    exit;
}

// Redirect logged-in users away from the auth screen to avoid serving cached content unnecessarily
if (is_user_logged_in()) {
    $redirect_target = !empty($_GET['redirect_to']) ? esc_url_raw(wp_validate_redirect(wp_unslash($_GET['redirect_to']), home_url('/'))) : home_url('/');
    wp_safe_redirect($redirect_target);
    exit;
}

$auth_nonce   = wp_create_nonce('gta6mods_auth');
$redirect_url = !empty($_GET['redirect_to']) ? esc_url_raw(wp_validate_redirect(wp_unslash($_GET['redirect_to']), home_url('/'))) : esc_url_raw(home_url('/'));

$auth_page_url = get_permalink();

$auth_page_url_with_redirect = $auth_page_url;
if (!empty($redirect_url)) {
    $auth_page_url_with_redirect = add_query_arg('redirect_to', $redirect_url, $auth_page_url_with_redirect);
}

$view_param   = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
$action_param = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

$initial_view = 'login';
$reset_login  = '';
$reset_key    = '';

if ('rp' === $action_param) {
    $maybe_login = isset($_GET['login']) ? sanitize_user(wp_unslash($_GET['login'])) : '';
    $maybe_key   = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';

    if (!empty($maybe_login) && !empty($maybe_key)) {
        $initial_view = 'reset';
        $reset_login  = $maybe_login;
        $reset_key    = $maybe_key;
    }
} elseif ('register' === $view_param) {
    $initial_view = 'register';
} elseif ('lost-password' === $view_param) {
    $initial_view = 'lost';
}

$login_view_url    = esc_url_raw($auth_page_url_with_redirect);
$register_view_url = esc_url_raw(add_query_arg('view', 'register', $auth_page_url_with_redirect));
$lost_view_url     = esc_url_raw(add_query_arg('view', 'lost-password', $auth_page_url_with_redirect));
$reset_view_url    = '';

if ('reset' === $initial_view) {
    $reset_view_url = esc_url_raw(add_query_arg(
        [
            'action' => 'rp',
            'key'    => $reset_key,
            'login'  => $reset_login,
        ],
        $auth_page_url_with_redirect
    ));
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Login & Register - GTA6-Mods.com', 'gta6mods'); ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Inter, Oswald & Birthstone -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Birthstone&family=Inter:wght@400;500;700&family=Oswald:wght@600&display=swap" rel="stylesheet">

    <!-- Font Awesome (for icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0b070b;
            background-attachment: fixed;
            overflow-x: hidden;
        }
        .bg-layer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center center;
            z-index: -5;
        }
        .bg-night {
            animation: background-crossfade 100s linear infinite;
            opacity: 0;
        }
        @keyframes background-crossfade {
            0% { opacity: 0; }
            10% { opacity: 0; }
            70% { opacity: 1; }
            100% { opacity: 0; }
        }
        .vice-city-text {
            font-family: 'Birthstone', cursive;
            text-shadow: 0 0 5px rgba(255, 105, 180, 0.8), 0 0 10px rgba(255, 105, 180, 0.4);
            letter-spacing: 0.05em;
            font-weight: 400 !important;
        }
        .logo-font {
            font-weight: 500;
        }
        .glass-panel {
            background-color: rgba(17, 7, 11, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .form-input {
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #f1f5f9;
            transition: all 0.3s ease;
        }
        .form-input::placeholder { color: #94a3b8; }
        .form-input:focus {
            outline: none;
            border-color: #ec4899;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.4);
        }
        .btn-primary {
            background-color: #ec4899;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px 0 rgba(236, 72, 153, 0.4);
        }
        .btn-primary:hover {
            background-color: #db2777;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px 0 rgba(236, 72, 153, 0.5);
        }
        .tab-btn {
            border-bottom: 3px solid transparent;
            color: #94a3b8;
        }
        .tab-btn.active {
            border-bottom-color: #ec4899;
            color: #ffffff;
        }
        .btn-social {
            background-color: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-social:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        .airplane {
            position: fixed;
            top: 10%;
            animation: fly-by 50s linear infinite;
            z-index: 0;
            display: flex;
            align-items: center;
            opacity: 0;
        }
        .airplane-light-white{width:2px;height:2px;background-color:#fff;border-radius:50%;box-shadow:0 0 6px #fff;opacity:.7}.airplane-light-red,.airplane-light-green{width:2px;height:2px;border-radius:50%}.airplane-light-red{background-color:red;box-shadow:0 0 4px red;margin-right:2px;animation:strobe-pattern 3s infinite}.airplane-light-green{background-color:#00ff00;box-shadow:0 0 4px #00ff00;margin-left:2px;animation:strobe-pattern 3s infinite .5s}
        @keyframes fly-by{0%{transform:translateX(-5vw);opacity:0}2%{opacity:1}98%{opacity:1}100%{transform:translateX(105vw);opacity:0}}
        @keyframes strobe-pattern{0%{opacity:.6}3%{opacity:0}6%{opacity:.6}9%{opacity:0}50%{opacity:0}53%{opacity:.6}56%{opacity:0}100%{opacity:0}}
        .airplane-2 {
            position: fixed;
            top: 16%;
            animation: fly-by-diagonal 45s linear infinite 5s;
            z-index: 0;
            display: flex;
            align-items: center;
            opacity: 0;
        }
        .airplane-light-white-2{width:2px;height:2px;background-color:#fff;border-radius:50%;box-shadow:0 0 6px #fff;opacity:.6}.airplane-light-red-2,.airplane-light-green-2{width:2px;height:2px;border-radius:50%}.airplane-light-red-2{background-color:red;box-shadow:0 0 4px red;margin-right:2px;animation:strobe-pattern-2 2.5s infinite}.airplane-light-green-2{background-color:#00ff00;box-shadow:0 0 4px #00ff00;margin-left:2px;animation:strobe-pattern-2 2.5s infinite .3s}
        @keyframes fly-by-diagonal{0%{transform:translate(105vw,0);opacity:0}2%{opacity:1}98%{opacity:1}100%{transform:translate(-5vw, 5vh);opacity:0}}
        @keyframes strobe-pattern-2{0%{opacity:.6}4%{opacity:0}8%{opacity:.6}12%{opacity:0}100%{opacity:0}}
        .airplane-3, .airplane-4, .airplane-5, .airplane-6 {
            position: fixed;
            z-index: 0;
            display: flex;
            align-items: center;
            opacity: 0;
        }
        #starry-sky-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 33vh;
            z-index: -1;
        }
        .shooting-star {
            position: fixed;
            top: 0;
            right: 0;
            width: 250px;
            height: 2px;
            background: linear-gradient(to right, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0));
            border-radius: 9999px;
            filter: drop-shadow(0 0 6px rgba(255, 255, 255, 0.8));
            z-index: 0;
            opacity: 0;
            animation-name: shoot;
            animation-duration: 40s;
            animation-delay: 5s;
            animation-iteration-count: infinite;
            animation-timing-function: ease-in;
        }
        @keyframes shoot {
            0% {
                transform: translate(10vw, -10vh) rotate(-25deg);
                opacity: 0;
                width: 250px;
            }
            1% { opacity: 1; }
            20% {
                transform: translate(-53.75vw, 31.25vh) rotate(-25deg);
                opacity: 0;
                width: 0;
            }
            100% {
                transform: translate(-53.75vw, 31.25vh) rotate(-25deg);
                opacity: 0;
                width: 0;
            }
        }
        @media (max-width: 767px) {
            .shooting-star {
                width: 150px;
                animation-name: shoot-mobile;
                animation-duration: 40s;
                animation-delay: 5s;
                animation-iteration-count: infinite;
                animation-timing-function: ease-in;
            }
            @keyframes shoot-mobile {
                0% {
                    transform: translate(30vw, -5vh) rotate(-20deg);
                    opacity: 0;
                    width: 150px;
                }
                1% { opacity: 1; }
                8% {
                    transform: translate(-85vw, 15vh) rotate(-10deg);
                    opacity: 0;
                    width: 0;
                }
                100% {
                    transform: translate(-105vw, 15vh) rotate(-10deg);
                    opacity: 0;
                    width: 0;
                }
            }
        }
    </style>
    <?php wp_head(); ?>
</head>
<body <?php body_class('text-gray-200'); ?>>

    <div id="sunset-background" class="bg-layer" style="background-image: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0.2)), url(https://topiku.hu/wp-content/themes/backgroundsunset.png);"></div>
    <div id="night-background" class="bg-layer bg-night" style="background-image: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0.2)), url(https://topiku.hu/wp-content/themes/nightbackground.png);"></div>

    <canvas id="starry-sky-canvas"></canvas>
    <div class="shooting-star"></div>

    <div class="airplane">
        <div class="airplane-light-red"></div>
        <div class="airplane-light-white"></div>
        <div class="airplane-light-green"></div>
    </div>
    <div class="airplane-2">
        <div class="airplane-light-red-2"></div>
        <div class="airplane-light-white-2"></div>
        <div class="airplane-light-green-2"></div>
    </div>
    <div class="airplane-3">
        <div class="airplane-light-red"></div>
        <div class="airplane-light-white"></div>
        <div class="airplane-light-green"></div>
    </div>
    <div class="airplane-4">
        <div class="airplane-light-red-2"></div>
        <div class="airplane-light-white-2"></div>
        <div class="airplane-light-green-2"></div>
    </div>
    <div class="airplane-5">
        <div class="airplane-light-red"></div>
        <div class="airplane-light-white"></div>
        <div class="airplane-light-green"></div>
    </div>
    <div class="airplane-6">
        <div class="airplane-light-white"></div>
    </div>

    <div class="min-h-screen flex flex-col items-center justify-center p-4 relative z-20">
        <div class="absolute top-5 left-5 z-30 hidden sm:block">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="text-rose-50 font-semibold py-2 px-4 rounded-lg bg-black/30 hover:bg-black/50 transition duration-300 flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                <span><?php esc_html_e('Back to Home', 'gta6mods'); ?></span>
            </a>
        </div>

        <div class="w-full max-w-md mx-auto relative z-30 mt-0 sm:mt-12 md:mt-0">
            <div class="text-center mb-5">
                <p class="vice-city-text text-pink-300 text-4xl sm:text-6xl"><?php esc_html_e('Welcome to Vice City', 'gta6mods'); ?></p>
                <h1 class="font-medium text-stone-200 text-sm tracking-wider"><?php esc_html_e('Log in to your GTA6-Mods.com profile.', 'gta6mods'); ?></h1>
            </div>

            <div class="glass-panel rounded-2xl shadow-2xl overflow-hidden">
                <div id="auth-tab-container" class="flex <?php echo in_array($initial_view, ['login', 'register'], true) ? '' : 'hidden'; ?>">
                    <button id="login-tab-btn" type="button" class="tab-btn w-1/2 py-4 text-lg font-bold transition <?php echo 'login' === $initial_view ? 'active' : ''; ?>">
                        <?php esc_html_e('Login', 'gta6mods'); ?>
                    </button>
                    <button id="register-tab-btn" type="button" class="tab-btn w-1/2 py-4 text-lg font-bold transition <?php echo 'register' === $initial_view ? 'active' : ''; ?>">
                        <?php esc_html_e('Register', 'gta6mods'); ?>
                    </button>
                </div>

                <div class="p-8">
                    <div id="auth-message" class="hidden mb-4 text-sm font-medium"></div>

                    <div id="login-form" class="<?php echo 'login' === $initial_view ? '' : 'hidden'; ?>">
                        <form id="gta6mods-login" class="space-y-4" novalidate>
                            <div>
                                <label for="login-email" class="sr-only"><?php esc_html_e('Email or Username', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" id="login-email" name="login" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('Email or Username', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div>
                                <label for="login-password" class="sr-only"><?php esc_html_e('Password', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="login-password" name="password" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('Password', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center">
                                    <input id="remember-me" name="remember" type="checkbox" class="h-4 w-4 rounded border-gray-500 bg-gray-700 text-pink-600 focus:ring-pink-500">
                                    <label for="remember-me" class="ml-2 block text-gray-300"><?php esc_html_e('Remember me', 'gta6mods'); ?></label>
                                </div>
                                <a href="<?php echo esc_url($lost_view_url); ?>" id="show-lost-password" class="font-medium text-pink-500 hover:text-pink-400"><?php esc_html_e('Forgot your password?', 'gta6mods'); ?></a>
                            </div>
                            <div>
                                <button type="submit" class="w-full btn-primary font-bold py-3 px-4 rounded-lg text-lg">
                                    <?php esc_html_e('Login', 'gta6mods'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="register-form" class="<?php echo 'register' === $initial_view ? '' : 'hidden'; ?>">
                        <form id="gta6mods-register" class="space-y-4" novalidate>
                            <div>
                                <label for="register-username" class="sr-only"><?php esc_html_e('Username', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-user-tag text-gray-400"></i>
                                    </div>
                                    <input type="text" id="register-username" name="username" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('Username', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div>
                                <label for="register-email" class="sr-only"><?php esc_html_e('Email address', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-envelope text-gray-400"></i>
                                    </div>
                                    <input type="email" id="register-email" name="email" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('Email address', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div>
                                <label for="register-password" class="sr-only"><?php esc_html_e('Password', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="register-password" name="password" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('Password', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div>
                                <label for="register-password-confirm" class="sr-only"><?php esc_html_e('Confirm Password', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-check-double text-gray-400"></i>
                                    </div>
                                    <input type="password" id="register-password-confirm" name="password_confirmation" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('Confirm Password', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div class="flex items-start text-sm">
                                <div class="flex h-5 items-center">
                                    <input id="terms" name="terms" type="checkbox" class="h-4 w-4 rounded border-gray-500 bg-gray-700 text-pink-600 focus:ring-pink-500" required>
                                </div>
                                <div class="ml-3">
                                    <p class="text-gray-300"><?php esc_html_e('I agree to the', 'gta6mods'); ?> <a href="<?php echo esc_url(home_url('/terms-of-service/')); ?>" class="font-medium text-pink-500 hover:text-pink-400"><?php esc_html_e('Terms of Service', 'gta6mods'); ?></a></p>
                                </div>
                            </div>
                            <div>
                                <button type="submit" class="w-full btn-primary font-bold py-3 px-4 rounded-lg text-lg">
                                    <?php esc_html_e('Register', 'gta6mods'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="my-6 flex items-center hidden">
                        <div class="flex-grow border-t border-gray-600"></div>
                        <span class="mx-4 text-sm text-gray-400"><?php esc_html_e('or', 'gta6mods'); ?></span>
                        <div class="flex-grow border-t border-gray-600"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 hidden">
                        <button class="w-full btn-social py-2.5 px-4 rounded-lg flex items-center justify-center space-x-2">
                            <i class="fab fa-google text-red-400"></i>
                            <span>Google</span>
                        </button>
                        <button class="w-full btn-social py-2.5 px-4 rounded-lg flex items-center justify-center space-x-2">
                            <i class="fab fa-discord text-indigo-400"></i>
                            <span>Discord</span>
                        </button>
                    </div>

                    <div id="lost-password-form" class="<?php echo 'lost' === $initial_view ? '' : 'hidden'; ?>">
                        <form id="gta6mods-lost-password-request" class="space-y-4" novalidate>
                            <div class="text-center text-gray-300 text-sm">
                                <?php esc_html_e('Enter the email address associated with your account and we will send reset instructions.', 'gta6mods'); ?>
                            </div>
                            <div>
                                <label for="lost-password-email" class="sr-only"><?php esc_html_e('Email address', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-envelope text-gray-400"></i>
                                    </div>
                                    <input type="email" id="lost-password-email" name="email" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('Email address', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div>
                                <button type="submit" class="w-full btn-primary font-bold py-3 px-4 rounded-lg text-lg">
                                    <?php esc_html_e('Send reset link', 'gta6mods'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="reset-password-form" class="<?php echo 'reset' === $initial_view ? '' : 'hidden'; ?>">
                        <form id="gta6mods-reset-password" class="space-y-4" novalidate>
                            <input type="hidden" name="login" value="<?php echo esc_attr($reset_login); ?>">
                            <input type="hidden" name="key" value="<?php echo esc_attr($reset_key); ?>">
                            <div class="text-center text-gray-300 text-sm">
                                <?php esc_html_e('Choose a new password for your account.', 'gta6mods'); ?>
                            </div>
                            <div>
                                <label for="reset-password" class="sr-only"><?php esc_html_e('New password', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="reset-password" name="password" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('New password', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div>
                                <label for="reset-password-confirm" class="sr-only"><?php esc_html_e('Confirm new password', 'gta6mods'); ?></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i class="fa-solid fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="reset-password-confirm" name="password_confirmation" class="form-input w-full rounded-lg py-3 pl-10 pr-3" placeholder="<?php esc_attr_e('Confirm new password', 'gta6mods'); ?>" required>
                                </div>
                            </div>
                            <div>
                                <button type="submit" class="w-full btn-primary font-bold py-3 px-4 rounded-lg text-lg">
                                    <?php esc_html_e('Reset password', 'gta6mods'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="text-center mt-6 space-y-2">
                        <p id="login-toggle-text" class="text-sm text-gray-400 <?php echo 'login' === $initial_view ? '' : 'hidden'; ?>">
                            <?php esc_html_e("Don't have an account yet?", 'gta6mods'); ?>
                            <a id="show-register" href="<?php echo esc_url($register_view_url); ?>" class="font-semibold text-orange-300 hover:underline"><?php esc_html_e('Register here', 'gta6mods'); ?></a>
                        </p>
                        <p id="register-toggle-text" class="text-sm text-gray-400 <?php echo 'register' === $initial_view ? '' : 'hidden'; ?>">
                            <?php esc_html_e('Already have an account?', 'gta6mods'); ?>
                            <a id="show-login" href="<?php echo esc_url($login_view_url); ?>" class="font-semibold text-orange-300 hover:underline"><?php esc_html_e('Log in', 'gta6mods'); ?></a>
                        </p>
                        <p id="lost-password-toggle-text" class="text-sm text-gray-400 <?php echo 'lost' === $initial_view ? '' : 'hidden'; ?>">
                            <?php esc_html_e('Remembered your password?', 'gta6mods'); ?>
                            <a id="lost-password-back" href="<?php echo esc_url($login_view_url); ?>" class="font-semibold text-orange-300 hover:underline"><?php esc_html_e('Back to login', 'gta6mods'); ?></a>
                        </p>
                        <p id="reset-password-toggle-text" class="text-sm text-gray-400 <?php echo 'reset' === $initial_view ? '' : 'hidden'; ?>">
                            <?php esc_html_e('Finished resetting your password?', 'gta6mods'); ?>
                            <a id="reset-password-back" href="<?php echo esc_url($login_view_url); ?>" class="font-semibold text-orange-300 hover:underline"><?php esc_html_e('Return to login', 'gta6mods'); ?></a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('starry-sky-canvas');
            const ctx = canvas.getContext('2d');

            function setCanvasSize() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight * 0.33;
            }

            let stars = [];
            const starCount = 150;

            function initStars() {
                stars = [];
                for (let i = 0; i < starCount; i++) {
                    stars.push({
                        x: Math.random() * canvas.width,
                        y: Math.random() * canvas.height,
                        radius: Math.random() * 1.2,
                        alpha: Math.random() * 0.5 + 0.2,
                        dx: (Math.random() - 0.5) * 0.1,
                        dy: (Math.random() - 0.5) * 0.1
                    });
                }
            }

            function animateStars() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                stars.forEach(star => {
                    if (star.x < 0) star.x = canvas.width;
                    if (star.x > canvas.width) star.x = 0;
                    if (star.y < 0) star.y = canvas.height;
                    if (star.y > canvas.height) star.y = 0;

                    star.alpha += (Math.random() - 0.5) * 0.015;
                    if (star.alpha < 0.2) star.alpha = 0.2;
                    if (star.alpha > 0.7) star.alpha = 0.7;

                    ctx.beginPath();
                    ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(255, 255, 255, ${star.alpha})`;
                    ctx.fill();
                });

                requestAnimationFrame(animateStars);
            }

            window.addEventListener('resize', () => {
                setCanvasSize();
                initStars();
            });

            setCanvasSize();
            initStars();
            animateStars();
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const planesToRandomize = [
                { selector: '.airplane-3', delay: '10s' },
                { selector: '.airplane-4', delay: '20s' },
                { selector: '.airplane-5', delay: '35s' },
                { selector: '.airplane-6', delay: '55s' }
            ];

            const styleSheet = document.createElement('style');
            document.head.appendChild(styleSheet);

            planesToRandomize.forEach((planeInfo, index) => {
                const plane = document.querySelector(planeInfo.selector);
                if (!plane) return;

                const duration = Math.random() * 30 + 40;
                const animationName = `fly-random-${index + 3}`;

                let startX, startY, endX, endY;
                const yMax = 33;

                const startSide = Math.floor(Math.random() * 4);
                switch(startSide) {
                    case 0:
                        startX = Math.random() * 100;
                        startY = -5;
                        break;
                    case 1:
                        startX = 105;
                        startY = Math.random() * yMax;
                        break;
                    case 2:
                        startX = Math.random() * 100;
                        startY = yMax + 2;
                        break;
                    case 3:
                    default:
                        startX = -5;
                        startY = Math.random() * yMax;
                        break;
                }

                let endSide;
                do {
                    endSide = Math.floor(Math.random() * 4);
                } while (endSide === startSide);

                switch(endSide) {
                    case 0:
                        endX = Math.random() * 100;
                        endY = -5;
                        break;
                    case 1:
                        endX = 105;
                        endY = Math.random() * yMax;
                        break;
                    case 2:
                        endX = Math.random() * 100;
                        endY = yMax + 2;
                        break;
                    case 3:
                    default:
                        endX = -5;
                        endY = Math.random() * yMax;
                        break;
                }

                const transformPrefix = plane.classList.contains('airplane-6') ? 'scale(0.7) ' : '';

                const keyframes = `
                    @keyframes ${animationName} {
                        0% {
                            transform: ${transformPrefix}translate(${startX}vw, ${startY}vh);
                            opacity: 0;
                        }
                        2% { opacity: 1; }
                        98% { opacity: 1; }
                        100% {
                            transform: ${transformPrefix}translate(${endX}vw, ${endY}vh);
                            opacity: 0;
                        }
                    }
                `;
                styleSheet.innerHTML += keyframes;

                plane.style.animation = `${animationName} ${duration}s linear ${planeInfo.delay} infinite`;
            });
        });
    </script>

    <script>
        window.GTA6Auth = {
            nonce: '<?php echo esc_js($auth_nonce); ?>',
            restNonce: '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>',
            loginUrl: '<?php echo esc_url_raw(rest_url('gta6mods/v1/login')); ?>',
            registerUrl: '<?php echo esc_url_raw(rest_url('gta6mods/v1/register')); ?>',
            lostPasswordRequestUrl: '<?php echo esc_url_raw(rest_url('gta6mods/v1/lost-password/request')); ?>',
            lostPasswordResetUrl: '<?php echo esc_url_raw(rest_url('gta6mods/v1/lost-password/reset')); ?>',
            redirectUrl: '<?php echo esc_js($redirect_url); ?>',
            initialView: '<?php echo esc_js($initial_view); ?>',
            resetLogin: '<?php echo esc_js($reset_login); ?>',
            resetKey: '<?php echo esc_js($reset_key); ?>',
            resetUrl: '<?php echo esc_js($reset_view_url); ?>',
            viewUrls: {
                login: '<?php echo esc_url_raw($login_view_url); ?>',
                register: '<?php echo esc_url_raw($register_view_url); ?>',
                lost: '<?php echo esc_url_raw($lost_view_url); ?>'
            },
            messages: {
                loginSuccess: '<?php echo esc_js(__('Successfully logged in. Redirecting…', 'gta6mods')); ?>',
                registerSuccess: '<?php echo esc_js(__('Registration successful. Redirecting…', 'gta6mods')); ?>',
                lostRequestSuccess: '<?php echo esc_js(__('If that email is registered, a reset link is on its way.', 'gta6mods')); ?>',
                resetSuccess: '<?php echo esc_js(__('Your password has been reset. Redirecting to login…', 'gta6mods')); ?>',
                passwordMismatch: '<?php echo esc_js(__('The provided passwords do not match.', 'gta6mods')); ?>',
                genericError: '<?php echo esc_js(__('Something went wrong. Please try again.', 'gta6mods')); ?>'
            }
        };
    </script>
    <script src="<?php echo esc_url(get_template_directory_uri() . '/assets/js/auth.js'); ?>" defer></script>

    <?php wp_footer(); ?>
</body>
</html>
