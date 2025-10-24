@extends('admin.layout')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('title', 'Frissítés megtekintése')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $mod->title }} – v{{ $revision->version }}</h1>
                <p class="text-sm text-gray-500">Beküldte: {{ $revision->author->name }} · {{ $revision->created_at->diffForHumans() }}</p>
            </div>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('admin.mod-revisions.approve', $revision) }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-green-700">Jóváhagyás</button>
                </form>
                <form method="POST" action="{{ route('admin.mod-revisions.reject', $revision) }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-red-700">Elutasítás</button>
                </form>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">
            <div class="space-y-6">
                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Frissítés részletei</h2>
                    <dl class="grid gap-3 md:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase text-gray-500">Új verzió</dt>
                            <dd class="text-sm text-gray-900">{{ $revision->version }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-gray-500">Javasolt letöltési URL</dt>
                            <dd class="text-sm text-gray-900">
                                @if ($revision->payload['download_url'] ?? false)
                                    <a href="{{ $revision->payload['download_url'] }}" target="_blank" class="text-pink-600 hover:text-pink-700">{{ $revision->payload['download_url'] }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-gray-500">Fájlméret</dt>
                            <dd class="text-sm text-gray-900">{{ $revision->payload['file_size'] ?? '—' }} MB</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-gray-500">Kategóriák</dt>
                            <dd class="text-sm text-gray-900">{{ collect($revision->payload['category_ids'] ?? [])->map(fn ($id) => optional($mod->categories->firstWhere('id', $id))->name)->filter()->join(', ') }}</dd>
                        </div>
                    </dl>
                    @if ($revision->changelog)
                        <div class="mt-4">
                            <h3 class="text-sm font-semibold text-gray-800">Changelog</h3>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">{{ $revision->changelog }}</p>
                        </div>
                    @endif
                </div>

                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Leírás előnézet</h2>
                    <div class="editorjs-content">
                        {!! \App\Support\EditorJs::render($revision->payload['description']) !!}
                    </div>
                </div>

                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Új média</h2>
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Hero kép</h3>
                            @if ($revision->media_manifest['hero_image'] ?? false)
                                <img src="{{ Storage::disk('public')->url($revision->media_manifest['hero_image']) }}" alt="Hero" class="mt-2 h-48 w-full rounded-2xl object-cover">
                            @else
                                <p class="text-xs text-gray-500">Nincs új hero kép.</p>
                            @endif
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Új galéria képek</h3>
                            @if (!empty($revision->media_manifest['gallery_images']))
                                <div class="mt-2 grid grid-cols-2 gap-3 md:grid-cols-3">
                                    @foreach ($revision->media_manifest['gallery_images'] as $image)
                                        <img src="{{ Storage::disk('public')->url($image) }}" alt="Galéria" class="h-28 w-full rounded-xl object-cover">
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-500">Nincsenek új képek.</p>
                            @endif
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Új mod fájl</h3>
                            @if ($revision->media_manifest['mod_file']['path'] ?? false)
                                <div class="mt-2 rounded-xl bg-gray-50 p-3 text-sm text-gray-700">
                                    {{ $revision->media_manifest['mod_file']['original_name'] ?? basename($revision->media_manifest['mod_file']['path']) }}
                                </div>
                            @else
                                <p class="text-xs text-gray-500">Nem érkezett új fájl.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <aside class="card p-6 space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Eredeti mod</h2>
                    <p class="text-sm text-gray-600">Verzió: {{ $mod->version }}</p>
                    <p class="text-sm text-gray-600">Utolsó frissítés: {{ optional($mod->updated_at)->format('Y.m.d H:i') }}</p>
                    <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" target="_blank" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white shadow hover:bg-gray-700">Megnyitás nyilvános oldalon</a>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Eltávolítandó galéria képek</h3>
                    @if (!empty($revision->media_manifest['removed_gallery_image_ids']))
                        <ul class="mt-2 space-y-1 text-xs text-gray-600">
                            @foreach ($mod->galleryImages->whereIn('id', $revision->media_manifest['removed_gallery_image_ids']) as $image)
                                <li>#{{ $image->id }} – {{ $image->path }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-gray-500">Nem jelölt meg törlendő képet.</p>
                    @endif
                </div>
            </aside>
        </div>
    </div>
@endsection
