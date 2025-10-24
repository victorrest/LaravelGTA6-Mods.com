@props(['mod'])

@php
    $primaryCategory = $mod->primary_category ?? $mod->categories->first();
    $modUrl = $primaryCategory ? route('mods.show', [$primaryCategory, $mod]) : '#';
    $thumbnailUrl = $mod->hero_image_url ?? asset('images/placeholder-mod.jpg');
@endphp

{{-- Related Mod Card Component --}}
<a href="{{ $modUrl }}" class="group block p-2 rounded-lg hover:bg-gray-50 transition">
    {{-- Thumbnail --}}
    <div class="relative overflow-hidden rounded-md">
        <img src="{{ $thumbnailUrl }}"
             alt="{{ $mod->title }}"
             class="w-full h-32 object-cover group-hover:scale-105 transition-transform duration-300"
             onerror="this.src='https://placehold.co/300x160/ec4899/white?text={{ urlencode($mod->title) }}'">

        {{-- Overlay with Stats --}}
        <div class="absolute bottom-0 left-0 right-0 p-1.5 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
            <div class="flex justify-between items-center">
                <span class="flex items-center font-semibold">
                    <i class="fas fa-star mr-1 text-yellow-400"></i>
                    {{ number_format($mod->rating ?? 0, 2) }}
                </span>
                <div class="flex items-center space-x-2">
                    <span class="flex items-center">
                        <i class="fas fa-download mr-1"></i>
                        {{ number_format($mod->downloads ?? 0) > 1000 ? number_format(($mod->downloads ?? 0) / 1000, 1) . 'k' : number_format($mod->downloads ?? 0) }}
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-thumbs-up mr-1"></i>
                        {{ number_format($mod->likes ?? 0) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Mod Info --}}
    <div class="pt-2">
        <div class="flex justify-between items-start">
            <h4 class="font-semibold text-sm text-gray-800 group-hover:text-pink-600 transition pr-2 line-clamp-2">
                {{ $mod->title }}
            </h4>
            <span class="text-xs font-bold bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full flex-shrink-0">
                {{ $mod->version ?? '1.0' }}
            </span>
        </div>
        <p class="text-xs text-gray-500">by {{ $mod->author->name }}</p>
    </div>
</a>
