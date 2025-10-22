@extends('layouts.app', ['title' => $title ?? 'Admin - ' . ($siteBrand['name'] ?? config('app.name'))])

@php($navigation = [
    ['label' => 'Vezérlőpult', 'icon' => 'fa-gauge', 'route' => 'admin.dashboard', 'match' => 'admin.dashboard'],
    ['label' => 'Modok', 'icon' => 'fa-rocket', 'route' => 'admin.mods.index', 'match' => 'admin.mods.*'],
    ['label' => 'Kategóriák', 'icon' => 'fa-layer-group', 'route' => 'admin.categories.index', 'match' => 'admin.categories.*'],
    ['label' => 'Hírek', 'icon' => 'fa-newspaper', 'route' => 'admin.news.index', 'match' => 'admin.news.*'],
    ['label' => 'Felhasználók', 'icon' => 'fa-users', 'route' => 'admin.users.index', 'match' => 'admin.users.*'],
    ['label' => 'Fórum', 'icon' => 'fa-comments', 'route' => 'admin.forum.index', 'match' => 'admin.forum.*'],
    ['label' => 'Hozzászólások', 'icon' => 'fa-message', 'route' => 'admin.comments.index', 'match' => 'admin.comments.*'],
])

@section('content')
    <div class="grid gap-6 xl:grid-cols-[280px_1fr]">
        <aside class="card p-4 h-max">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-screwdriver-wrench text-pink-500"></i>
                Admin menü
            </h2>
            <nav class="space-y-1 text-sm">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}"
                        @class([
                            'flex items-center gap-3 px-3 py-2 rounded-xl transition',
                            'bg-pink-600 text-white shadow-lg shadow-pink-600/30' => request()->routeIs($item['match']),
                            'bg-white/60 text-gray-700 hover:bg-pink-50 hover:text-pink-600' => !request()->routeIs($item['match']),
                        ])>
                        <i class="fa-solid {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>
        <div class="space-y-6">
            @hasSection('admin-header')
                <div class="space-y-1">
                    @yield('admin-header')
                </div>
            @endif

            @yield('admin-content')
        </div>
    </div>
@endsection
