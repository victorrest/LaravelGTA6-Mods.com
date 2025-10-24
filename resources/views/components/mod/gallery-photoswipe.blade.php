@props([
    'items' => [],
    'galleryJson' => '[]',
    'defaultImage' => null,
    'mod',
    'canManageVideos' => false,
])

@php
    $mainItem = $items[0] ?? null;
    $thumbnails = array_slice($items, 1);
    $visibleThumbnails = array_slice($thumbnails, 0, 5);
    $hiddenThumbnails = array_slice($thumbnails, 5);
    $totalItems = count($items);
    $modId = $mod->id;
    $modTitle = $mod->title;
@endphp

<section
    class="card overflow-hidden"
    data-mod-gallery
    data-can-manage-videos="{{ $canManageVideos ? 'true' : 'false' }}"
    data-mod-id="{{ $modId }}"
>
    @if ($mainItem)
        <div class="relative aspect-video w-full overflow-hidden bg-gray-900" data-gallery-featured>
            <button
                type="button"
                class="group relative block h-full w-full focus:outline-none"
                data-pswp-index="0"
            >
                <img
                    src="{{ $mainItem['src'] }}"
                    alt="{{ $mainItem['alt'] ?? $modTitle }}"
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    loading="eager"
                    decoding="async"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
                @if (($mainItem['type'] ?? 'image') === 'video')
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-red-600/90 text-white shadow-lg transition group-hover:bg-red-700">
                            <i class="fab fa-youtube text-3xl"></i>
                        </span>
                    </div>
                @endif
                <div class="absolute left-4 bottom-4 flex flex-col gap-1 text-white">
                    <span class="text-sm uppercase tracking-wide text-white/70">Featured media</span>
                    <h3 class="text-lg font-semibold leading-tight">{{ $mainItem['title'] ?? $modTitle }}</h3>
                </div>
            </button>
        </div>

        @if ($thumbnails)
            <div class="p-4 md:p-5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-gray-700">
                        <i class="fa-solid fa-images text-pink-500"></i>
                        <span class="text-sm font-semibold uppercase tracking-wide">Media gallery</span>
                    </div>
                    <span class="text-xs font-semibold text-gray-400">{{ $totalItems }} item{{ $totalItems === 1 ? '' : 's' }}</span>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5" data-gallery-thumbnails>
                    @foreach ($visibleThumbnails as $index => $item)
                        <button
                            type="button"
                            class="relative aspect-video overflow-hidden rounded-lg border border-transparent transition hover:border-pink-500"
                            data-pswp-index="{{ $index + 1 }}"
                        >
                            <img
                                src="{{ $item['thumbnail_small'] ?? $item['src'] }}"
                                alt="{{ $item['alt'] ?? $modTitle }}"
                                class="h-full w-full object-cover transition duration-300 hover:scale-110"
                                loading="lazy"
                                decoding="async"
                            >
                            @if (($item['type'] ?? 'image') === 'video')
                                <span class="absolute inset-0 flex items-center justify-center bg-black/30 text-white">
                                    <i class="fab fa-youtube"></i>
                                </span>
                            @endif
                        </button>
                    @endforeach

                    @foreach ($hiddenThumbnails as $hiddenIndex => $item)
                        <button
                            type="button"
                            class="hidden aspect-video overflow-hidden rounded-lg border border-transparent transition hover:border-pink-500"
                            data-gallery-hidden-thumb
                            data-pswp-index="{{ $hiddenIndex + 1 + count($visibleThumbnails) }}"
                        >
                            <img
                                src="{{ $item['thumbnail_small'] ?? $item['src'] }}"
                                alt="{{ $item['alt'] ?? $modTitle }}"
                                class="h-full w-full object-cover transition duration-300 hover:scale-110"
                                loading="lazy"
                                decoding="async"
                            >
                            @if (($item['type'] ?? 'image') === 'video')
                                <span class="absolute inset-0 flex items-center justify-center bg-black/30 text-white">
                                    <i class="fab fa-youtube"></i>
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                @if ($hiddenThumbnails)
                    <button
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-full border-2 border-pink-500 px-4 py-2 text-sm font-semibold text-pink-600 transition hover:bg-pink-50"
                        data-gallery-load-more
                        data-hidden-count="{{ count($hiddenThumbnails) }}"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Show {{ count($hiddenThumbnails) }} more media
                    </button>
                @endif
            </div>
        @endif
    @else
        <div class="flex aspect-video items-center justify-center bg-gray-100">
            <div class="text-center text-gray-500">
                <i class="fa-regular fa-image text-5xl"></i>
                <p class="mt-2 text-sm">No media uploaded yet.</p>
            </div>
        </div>
    @endif
</section>

<script id="gallery-data-{{ $modId }}" type="application/json">{!! $galleryJson !!}</script>

@auth
    <x-mod.video-submit-modal :modId="$modId" />
@endauth
