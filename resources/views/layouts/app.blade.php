<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? ($siteBrand['name'] ?? config('app.name')) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Russo+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-fb0d1NZrf9hlr4evPiF+jYwIV/pY5Pja/qvpDMAYA9YV3gCBXlI3VzBkYOPxfN3E3xaQa58ae0q/QAdz5dVW6w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @stack('styles')
</head>
<body class="text-gray-700 min-h-screen flex flex-col">
    <header class="shadow-lg relative">
        <div class="header-background bg-cover bg-center" aria-hidden="true"></div>
        <div class="header-content relative z-10">
            <div class="header-top-bar">
                <div class="container mx-auto px-4 flex items-center justify-between py-2">
                    <a href="{{ route('home') }}" class="logo-font" aria-label="Return to homepage">
                        {{ $siteBrand['name'] ?? config('app.name') }}
                    </a>
                    <div class="flex items-center space-x-4">
                        @auth
                            <a href="{{ route('mods.upload') }}" class="hidden md:flex text-white text-sm font-medium bg-white/10 rounded-full px-3 py-1 transition hover:bg-white/20 hover:shadow-[0_0_15px_rgba(111,30,118,0.65)] items-center gap-x-2">
                                <i class="fa-solid fa-upload"></i>
                                <span>Upload</span>
                            </a>
                        @endauth
                        <a class="text-white transition hover:opacity-75" href="{{ route('mods.index') }}" aria-label="Search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </a>
                        @auth
                            <div class="relative">
                                <button type="button" class="text-white transition hover:opacity-75" aria-label="View notifications">
                                    <i class="fa-solid fa-bell fa-lg"></i>
                                </button>
                            </div>
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" class="flex items-center gap-2 rounded-full focus:outline-none text-white" @click="open = !open">
                                    <span class="sr-only">Open account menu</span>
                                    <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim(auth()->user()->email ?? ''))) }}?s=72&d=mp" alt="{{ auth()->user()->name }} avatar" class="h-9 w-9 rounded-full object-cover">
                                    <i class="fa-solid fa-chevron-down hidden md:inline-block text-white text-xs"></i>
                                </button>
                                <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-3 w-56 bg-white rounded-lg shadow-xl z-50 border text-gray-700" role="menu">
                                    <div class="px-4 py-3 border-b">
                                        <p class="text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                                    </div>
                                    <nav class="py-1" aria-label="Account menu">
                                        <a href="{{ route('mods.my') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-100" role="menuitem">
                                            <i class="fa-solid fa-cloud-arrow-up text-gray-400"></i>
                                            <span>My uploads</span>
                                        </a>
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
                    </div>
                </div>
            </div>
            <div class="header-nav-bar">
                <div class="container mx-auto px-0 md:px-4 relative">
                    @if (($isHome ?? false))
                        <div class="flex flex-col content-center items-center text-center pt-3 md:pt-4 px-4 md:px-0">
                            <h1 class="welcome-text tracking-normal text-orange-200 text-4xl md:text-6xl -mb-2 md:-mb-2">
                                Welcome to {{ $siteBrand['name'] ?? config('app.name') }}
                            </h1>
                            <p class="welcome-side tracking-tight px-0 md:px-2 text-stone-200 text-[11.8px] -mb-4 md:-mb-6 md:text-sm mt-2">
                                {{ $siteBrand['tagline'] ?? '' }} Browse vehicles, weapons, maps, scripts and more by category.
                            </p>
                        </div>
                    @endif
                    <nav class="category-carousel" aria-label="Primary navigation">
                        <div class="category-carousel-track">
                            @foreach ($siteNavigation as $item)
                                <a href="{{ route('mods.index', ['category' => $item['slug']]) }}" class="category-chip">
                                    <i class="fa-solid {{ $item['icon'] }} mr-2"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </header>

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
                    {{ $siteBrand['name'] ?? config('app.name') }} is a modern Laravel platform designed for lightning fast GTA 6 mod discovery, downloads and community collaboration.
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.14.1/cdn.min.js" integrity="sha512-1v4zI9zEDFedHlzBDvFdvjCQEf7aRwngIBPaUr9d9uXicWhmZrxq9dp30sCBoJok8nJ2v9WMT6Qy4seAby2TeQ==" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
    <script src="{{ asset('js/header-menus.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
