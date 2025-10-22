@extends('admin.layout', ['title' => 'Admin vezérlőpult'])

@php($pendingStatus = App\Models\Mod::STATUS_PENDING)

@section('admin-header')
    <h1 class="text-3xl font-bold text-gray-900">Vezérlőpult</h1>
    <p class="text-sm text-gray-500">Áttekintés a közösség és a mod könyvtár legfontosabb mutatóiról.</p>
@endsection

@section('admin-content')
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="card p-5 bg-gradient-to-br from-pink-500/90 to-purple-600/90 text-white">
            <h2 class="text-sm uppercase tracking-wide text-white/70">Összes mod</h2>
            <p class="text-3xl font-semibold mt-2">{{ number_format($stats['total_mods']) }}</p>
            <p class="text-xs text-white/80 mt-1">{{ number_format($stats['published_mods']) }} publikálva • {{ number_format($stats['pending_mods']) }} várólistán</p>
        </div>
        <div class="card p-5 bg-white">
            <h2 class="text-sm uppercase tracking-wide text-gray-500">Felhasználók</h2>
            <p class="text-3xl font-semibold text-gray-900 mt-2">{{ number_format($stats['total_users']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Aktív közösségi tagok, kreatívok és moderátorok</p>
        </div>
        <div class="card p-5 bg-white">
            <h2 class="text-sm uppercase tracking-wide text-gray-500">Közösségi aktivitás</h2>
            <p class="text-3xl font-semibold text-gray-900 mt-2">{{ number_format($stats['forum_threads']) }} témakör</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($stats['total_comments']) }} mod-hozzászólás • {{ number_format($stats['news_articles']) }} hír</p>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <header class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Legutóbbi mod beküldések</h2>
                <a href="{{ route('admin.mods.index', ['status' => $pendingStatus]) }}" class="text-sm font-semibold text-pink-600">Moderálás</a>
            </header>
            <ul class="space-y-4 text-sm text-gray-600">
                @forelse ($recentMods as $mod)
                    <li class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('admin.mods.edit', $mod) }}" class="font-semibold text-gray-900 hover:text-pink-600">{{ $mod->title }}</a>
                            <p class="text-xs text-gray-500">{{ $mod->author?->name }} • {{ $mod->statusLabel() }}</p>
                        </div>
                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ $mod->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">Még nincs beküldött mod.</li>
                @endforelse
            </ul>
        </div>
        <div class="card p-5">
            <header class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Új közösségi tagok</h2>
                <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-pink-600">Felhasználók</a>
            </header>
            <ul class="space-y-3 text-sm text-gray-600">
                @forelse ($recentUsers as $user)
                    <li class="flex items-center justify-between">
                        <span>{{ $user->name }}</span>
                        <span class="text-xs text-gray-400">{{ $user->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">Nincs új felhasználó.</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="card p-5">
        <header class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Friss fórumaktivitás</h2>
            <a href="{{ route('admin.forum.index') }}" class="text-sm font-semibold text-pink-600">Fórum kezelése</a>
        </header>
        <ul class="space-y-4 text-sm text-gray-600">
            @forelse ($recentThreads as $thread)
                <li class="flex items-start justify-between gap-3">
                    <div>
                        <a href="{{ route('admin.forum.edit', $thread) }}" class="font-semibold text-gray-900 hover:text-pink-600">{{ $thread->title }}</a>
                        <p class="text-xs text-gray-500">{{ $thread->author?->name }} • {{ $thread->replies_count }} válasz</p>
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ optional($thread->last_posted_at)->diffForHumans() ?? $thread->created_at->diffForHumans() }}</span>
                </li>
            @empty
                <li class="text-sm text-gray-500">Még nincsenek fórumtémák.</li>
            @endforelse
        </ul>
    </section>
@endsection
