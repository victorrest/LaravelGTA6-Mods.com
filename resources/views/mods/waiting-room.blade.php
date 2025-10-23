@extends('layouts.app', ['title' => 'Preparing download…'])

@section('content')
    @php
        $heroImage = $mod->hero_image_url;
        $versionLabelParts = [];
        if (!empty($mod->version)) {
            $versionLabelParts[] = '<span class="font-semibold">Verzió:</span> ' . e($mod->version);
        }
        if (!empty($mod->file_size_label)) {
            $versionLabelParts[] = '<span class="font-semibold">Méret:</span> ' . e($mod->file_size_label);
        }
        $metaLine = implode(' | ', $versionLabelParts);
        $redirectTemplate = $externalDomain
            ? 'Néhány másodpercen belül átirányítunk ide: :domain (:seconds másodperc).'
            : 'A letöltés :seconds másodpercen belül indul.';
    @endphp

    <section class="space-y-8" aria-labelledby="waiting-room-title">
        <header class="card overflow-hidden">
            <div class="relative">
                <img src="{{ $heroImage }}" alt="{{ $mod->title }}" class="w-full h-64 object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 p-6 text-white">
                    <p class="text-sm text-pink-200 uppercase tracking-wide mb-2">Letöltés előkészítése</p>
                    <h1 id="waiting-room-title" class="text-3xl font-bold leading-tight flex flex-wrap items-center gap-2">
                        <span>{{ $mod->title }}</span>
                        <span class="text-lg font-semibold text-pink-200">{{ $mod->version }}</span>
                    </h1>
                    <p class="text-sm text-gray-200 mt-1">
                        by <span class="font-semibold text-white">{{ $mod->author->name }}</span>
                    </p>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2 space-y-6">
                <div class="card p-6 space-y-5" data-waiting-room-root>
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-1">
                                {{ $downloadToken->is_external ? 'Külső letöltési link előkészítése…' : 'Letöltés előkészítése…' }}
                            </h2>
                            <p class="text-sm text-gray-600">
                                {{ $downloadToken->is_external ? 'Kérjük, várj néhány másodpercet, amíg előkészítjük az átirányítást.' : 'Kérjük, várj néhány másodpercet, amíg előkészítjük a fájlod.' }}
                            </p>
                        </div>
                        <div class="text-center md:text-right">
                            <p class="text-4xl font-extrabold text-pink-600" data-waiting-room-counter data-countdown="{{ $countdownSeconds }}">{{ $countdownSeconds }}</p>
                            <p class="text-xs uppercase tracking-wide text-gray-500 mt-1">másodperc</p>
                        </div>
                    </div>

                    <div class="relative w-full h-3 rounded-full bg-gray-200 overflow-hidden">
                        <div class="absolute inset-y-0 left-0 bg-pink-500 transition-all duration-1000" style="width: 0%;" data-waiting-room-progress></div>
                    </div>

                    <p class="text-sm text-gray-600" data-waiting-room-message data-template="{{ $redirectTemplate }}" @if($externalDomain) data-domain="{{ $externalDomain }}" @endif>
                        {{ $externalDomain ? 'Néhány másodpercen belül átirányítunk ide: ' . $externalDomain : 'A letöltés hamarosan indul.' }}
                    </p>

                    <form id="waiting-room-form" method="POST" action="{{ route('mods.download.complete', $downloadToken) }}" class="space-y-3" data-waiting-room-form>
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-white font-semibold shadow transition disabled:opacity-60 disabled:cursor-not-allowed bg-pink-600 hover:bg-pink-700" disabled data-waiting-room-button data-preparing-label="{{ $downloadToken->is_external ? 'Külső link előkészítése…' : 'Letöltés előkészítése…' }}" data-ready-label="{{ $downloadToken->is_external ? 'Tovább a letöltéshez' : 'Letöltés indítása' }}">
                            <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                            <span>{{ $downloadToken->is_external ? 'Külső link előkészítése…' : 'Letöltés előkészítése…' }}</span>
                        </button>
                    </form>

                    <div class="text-xs text-gray-500">
                        <p>Ha a letöltés nem indul el automatikusan, kattints a fenti gombra a letöltés indításához.</p>
                        <p class="mt-1">A folytatással elfogadod a közösségi irányelveket és a felhasználási feltételeket.</p>
                    </div>
                </div>

                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Mit tartalmaz a letöltés?</h2>
                    <p class="text-sm text-gray-600 leading-relaxed">Ez a letöltés a közösség által beküldött fájlokat tartalmazza. A biztonságod érdekében mindig ellenőrizd a fájlokat egy naprakész víruskeresővel.</p>
                    @if (!empty($metaLine))
                        <p class="text-sm text-gray-500">{!! $metaLine !!}</p>
                    @endif
                </div>
            </div>

            <aside class="space-y-6">
                <div class="card overflow-hidden">
                    <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="block group">
                        <img src="{{ $heroImage }}" alt="{{ $mod->title }}" class="w-full h-40 object-cover group-hover:scale-[1.02] transition-transform duration-300">
                        <div class="p-4 space-y-1">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-pink-600 transition">{{ $mod->title }}</h3>
                            <p class="text-sm text-gray-500">{{ $mod->author->name }}</p>
                            @if (!empty($metaLine))
                                <p class="text-xs text-gray-400">{!! $metaLine !!}</p>
                            @endif
                        </div>
                    </a>
                </div>

                <div class="card p-5 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">További modok a szerzőtől</h2>
                    <ul class="space-y-3">
                        @forelse ($authorMods as $authorMod)
                            <li>
                                <a href="{{ route('mods.show', [$authorMod->primary_category, $authorMod]) }}" class="flex items-center gap-3 group">
                                    <img src="{{ $authorMod->hero_image_url }}" alt="{{ $authorMod->title }}" class="w-16 h-16 object-cover rounded-lg">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 group-hover:text-pink-600 transition">{{ $authorMod->title }}</p>
                                        <p class="text-xs text-gray-500">{{ $authorMod->categories->pluck('name')->take(2)->join(', ') }}</p>
                                    </div>
                                </a>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">A szerzőnek jelenleg nincsenek további modjai.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        </div>
    </section>
@endsection
