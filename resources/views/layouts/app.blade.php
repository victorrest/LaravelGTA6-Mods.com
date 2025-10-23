<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? ($siteBrand['name'] ?? config('app.name')) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @stack('styles')
</head>
<body class="text-gray-700 min-h-screen flex flex-col">
    <header class="shadow-lg">
        <div class="header-background bg-cover bg-center" aria-hidden="true"></div>
        <div class="header-content">
            <div class="header-top-bar">
                <div class="container mx-auto px-4 flex items-center justify-between py-2">
                    <a href="{{ route('home') }}" class="logo-font" aria-label="Return to homepage">
                        {{ $siteBrand['name'] ?? config('app.name') }}
                    </a>
                    <div class="flex items-center space-x-4">
                        @auth
                            <a href="{{ route('mods.upload') }}" title="Upload a new GTA 6 mod" class="hidden md:flex text-white text-sm font-medium bg-white/10 rounded-full px-3 py-1 transition hover:bg-white/20 hover:shadow-[0_0_15px_rgba(111,30,118,0.65)] items-center gap-x-2">
                                <i class="fa-solid fa-upload"></i>
                                <span>Upload</span>
                            </a>
                        @endauth
                        <a class="text-white transition hover:opacity-75" href="{{ route('mods.index') }}" aria-label="Search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </a>
                        @auth
                            <div id="notifications-container" class="relative hidden">
                                <button id="notifications-btn" type="button" class="text-white transition hover:opacity-75" aria-label="View notifications" aria-expanded="false" aria-controls="notifications-dropdown">
                                    <i class="fa-solid fa-bell fa-lg"></i>
                                </button>
                                <div id="notifications-dropdown" class="hidden absolute right-0 mt-3 w-64 bg-white text-gray-700 rounded-xl shadow-xl border z-50" aria-hidden="true">
                                    <div class="px-4 py-3 border-b text-sm font-semibold">Notifications</div>
                                    <div data-async-content="notifications" class="p-4 text-sm text-gray-500">No notifications yet.</div>
                                </div>
                            </div>
                            <div id="account-menu" class="relative">
                                <button id="account-menu-button" type="button" class="flex items-center gap-2 rounded-full focus:outline-none text-white" aria-expanded="false" aria-haspopup="true" aria-controls="account-menu-dropdown">
                                    <span class="sr-only">Open account menu</span>
                                    <img src="{{ auth()->user()->getAvatarUrl(72) }}" alt="{{ auth()->user()->name }} avatar" class="h-9 w-9 rounded-full object-cover" id="header-user-avatar">
                                    <i class="fa-solid fa-chevron-down hidden md:inline-block text-white text-xs"></i>
                                </button>
                                <div id="account-menu-dropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-lg shadow-xl z-50 border text-gray-700" role="menu" aria-hidden="true">
                                    <div class="px-4 py-3 border-b">
                                        <p class="text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                                    </div>
                                    <nav class="py-1" aria-label="Account menu">
                                        <a href="{{ route('author.profile', auth()->user()->name) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100" role="menuitem">
                                            <i class="fa-solid fa-user text-gray-400"></i>
                                            <span>My Profile</span>
                                        </a>
                                        <a href="{{ route('author.profile', auth()->user()->name) }}?tab=uploads" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100" role="menuitem">
                                            <i class="fa-solid fa-upload text-gray-400"></i>
                                            <span>My Uploads</span>
                                        </a>
                                        <a href="{{ route('author.profile', auth()->user()->name) }}?tab=bookmarks" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100" role="menuitem">
                                            <i class="fa-solid fa-bookmark text-gray-400"></i>
                                            <span>Bookmarks</span>
                                        </a>
                                        <a href="{{ route('author.profile', auth()->user()->name) }}?tab=notifications" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100" role="menuitem">
                                            <i class="fa-solid fa-bell text-gray-400"></i>
                                            <span>Notifications</span>
                                        </a>
                                        <a href="{{ route('author.profile', auth()->user()->name) }}?tab=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100" role="menuitem">
                                            <i class="fa-solid fa-cog text-gray-400"></i>
                                            <span>Settings</span>
                                        </a>
                                        <div class="border-t my-1"></div>
                                        @if (auth()->user()->isAdmin())
                                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100" role="menuitem">
                                                <i class="fa-solid fa-screwdriver-wrench text-gray-400"></i>
                                                <span>Admin Panel</span>
                                            </a>
                                        @endif
                                        <form action="{{ route('logout') }}" method="POST">
                                            @csrf
                                            <button type="submit" class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100">
                                                <i class="fa-solid fa-arrow-right-from-bracket text-gray-400"></i>
                                                <span>Logout</span>
                                            </button>
                                        </form>
                                    </nav>
                                </div>
                            </div>
                        @else
                            <a class="text-white transition hover:opacity-75" href="{{ route('login') }}" aria-label="Account">
                                <i class="fa-solid fa-circle-user fa-lg"></i>
                            </a>
                        @endauth
                        <button id="mobile-menu-button" class="md:hidden text-white transition hover:opacity-75 focus:outline-none" aria-label="Open menu">
                            <i class="fa-solid fa-bars fa-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="header-nav-bar">
                <div class="container mx-auto px-0 md:px-4 relative carousel-wrapper">
                    @if ($isHome ?? false)
                        <div class="flex flex-col content-center items-center text-center pt-3 md:pt-4 px-4 md:px-0">
                            <h1 class="welcome-text tracking-normal text-orange-200 text-4xl md:text-6xl -mb-2 md:-mb-2">
                                Welcome to {{ $siteBrand['name'] ?? config('app.name') }}
                            </h1>
                            <p class="welcome-side tracking-tight px-0 md:px-2 text-stone-200 text-[11.8px] -mb-4 md:-mb-6 md:text-sm mt-2">
                                {{ $siteBrand['tagline'] ?? '' }} Browse vehicles, weapons, maps, scripts and more by category.
                            </p>
                        </div>
                    @endif
                    @php
                        $navClasses = 'flex overflow-x-auto whitespace-nowrap py-2 md:py-6 text-white md:flex-wrap md:justify-center md:overflow-x-visible md:whitespace-normal items-center gap-x-0 sm:gap-x-4 md:gap-x-1 lg:gap-x-5 xl:gap-x-8';
                        if ($isHome ?? false) {
                            $navClasses .= ' mt-4 md:mt-6';
                        }
                    @endphp
                    <nav id="horizontal-nav" class="{{ $navClasses }}" aria-label="Primary navigation">
                        @foreach ($siteNavigation as $item)
                            <a href="{{ route('mods.index', ['category' => $item['slug']]) }}" class="flex flex-col items-center gap-2 px-3 py-2 rounded-lg hover:bg-white/10 transition opacity-75 hover:opacity-100 flex-shrink-0">
                                <i class="fa-solid {{ $item['icon'] }} text-2xl md:text-4xl lg:text-5xl xl:text-6xl"></i>
                                <span class="text-xs font-bold tracking-wide uppercase">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <div id="menu-backdrop" class="hidden fixed inset-0 bg-black/50 z-40 transition-opacity duration-300 md:hidden" aria-hidden="true"></div>

    <div id="mobile-menu-panel" class="fixed top-0 right-0 h-full w-64 max-w-full bg-gray-800 text-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 md:hidden" role="dialog" aria-modal="true" aria-labelledby="mobile-menu-title" aria-hidden="true">
        <div class="p-4 border-b border-white/10 h-[54px] flex justify-between items-center bg-pink-600/90 backdrop-blur-sm">
            <h3 id="mobile-menu-title" class="font-bold text-lg">Menu</h3>
            <button id="close-menu-button" class="text-white hover:text-gray-200" type="button" aria-label="Close menu">
                <i class="fa-solid fa-xmark fa-lg"></i>
            </button>
        </div>
        <div class="p-4 border-b border-white/10">
            <form role="search" method="GET" action="{{ route('mods.index') }}" class="relative">
                <label for="mobile-search" class="sr-only">Search</label>
                <input id="mobile-search" type="search" name="search" placeholder="Search…" class="w-full p-2 pl-10 rounded-md text-gray-800 bg-white/90 focus:outline-none focus:ring-2 focus:ring-pink-400" autocomplete="off">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
            </form>
        </div>
        <div class="p-4 border-b border-white/10">
            <a href="{{ route('mods.upload') }}" class="w-full bg-gray-900 font-bold py-2.5 px-4 rounded-lg flex items-center justify-center text-sm transition shadow-lg hover:bg-gray-950">
                <i class="fa-solid fa-upload mr-3"></i>
                <span>Upload</span>
            </a>
        </div>
        <div class="p-4 space-y-3 overflow-y-auto">
            <div class="flex justify-between items-center p-2 rounded-lg hover:bg-white/10 transition">
                <span class="font-semibold">Dark Mode</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="dark-mode-toggle" class="sr-only peer">
                    <span class="w-11 h-6 bg-gray-600 rounded-full peer peer-focus:ring-2 peer-focus:ring-pink-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-600"></span>
                </label>
            </div>
            <nav class="space-y-1" aria-label="Mobile menu navigation">
                <a href="{{ route('home') }}" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                    <i class="fa-solid fa-house mr-3 w-5"></i>
                    <span>Home</span>
                </a>
                @foreach ($siteNavigation as $item)
                    <a href="{{ route('mods.index', ['category' => $item['slug']]) }}" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                        <i class="fa-solid {{ $item['icon'] }} mr-3 w-5"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-white/10 pt-3 mt-3 space-y-2 text-sm">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                            <i class="fa-solid fa-screwdriver-wrench mr-3 w-5"></i>
                            <span>Admin vezérlőpult</span>
                        </a>
                    @endif
                    <a href="{{ route('mods.my') }}" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                        <i class="fa-solid fa-cloud-arrow-up mr-3 w-5"></i>
                        <span>My uploads</span>
                    </a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full p-2 rounded-lg hover:bg-white/10 transition flex items-center text-left">
                            <i class="fa-solid fa-arrow-right-from-bracket mr-3 w-5"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                        <i class="fa-solid fa-right-to-bracket mr-3 w-5"></i>
                        <span>Login</span>
                    </a>
                    <a href="{{ route('register') }}" class="block p-2 rounded-lg hover:bg-white/10 transition flex items-center">
                        <i class="fa-solid fa-user-plus mr-3 w-5"></i>
                        <span>Register</span>
                    </a>
                @endauth
            </div>
        </div>
    </div>

    <main class="flex-1 container mx-auto p-4 lg:p-6 space-y-10">
        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="bg-gray-900 text-gray-200 mt-16">
        <div class="container mx-auto px-4 py-10 grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-lg font-semibold mb-4">About {{ $siteBrand['name'] ?? config('app.name') }}</h3>
                <p class="text-sm text-gray-400 leading-relaxed">
                    {{ $siteBrand['name'] ?? config('app.name') }} is a modern Laravel platform designed for lightning fast GTA6 mod discovery, downloads and community collaboration.
                </p>
            </div>
            <div>
                <h3 class="text-lg font-semibold mb-4">Community</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="{{ route('forum.index') }}" class="hover:text-white transition">Forum</a></li>
                    <li><a href="{{ route('mods.index') }}" class="hover:text-white transition">Browse mods</a></li>
                    <li><a href="{{ route('mods.upload') }}" class="hover:text-white transition">Upload a mod</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-lg font-semibold mb-4">Stay updated</h3>
                <p class="text-sm text-gray-400 leading-relaxed">
                    Join our newsletter to never miss the latest GTA 6 mods, scripts and tools released by the community.
                </p>
            </div>
        </div>
        <div class="border-t border-gray-800 py-4 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} {{ $siteBrand['name'] ?? config('app.name') }}. All rights reserved.
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.14.1/cdn.min.js" integrity="sha512-ytM6hP1K9BkRTjUQZpxZKFjJ2TvE4QXaK7phVymsm7NimaI5H09TWWW6f2JMbonLp4ftYU6xfwQGoe3C8jta9A==" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
    <script src="{{ asset('assets/js/utils.js') }}" defer></script>
    <script src="{{ asset('assets/js/theme.js') }}" defer></script>
    <script src="{{ asset('js/header-menus.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
