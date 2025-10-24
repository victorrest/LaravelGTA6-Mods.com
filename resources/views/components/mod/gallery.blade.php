@props(['images', 'modTitle'])

@php
    $images = collect($images)->values();
    $initialLimit = 8;
    $hasMore = $images->count() > $initialLimit;
@endphp

<div class="mod-gallery" data-gallery-root>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3" id="gallery-grid">
        @foreach($images->take($initialLimit) as $index => $image)
            <a
                href="{{ $image['src'] }}"
                data-pswp-width="{{ $image['width'] ?? 1920 }}"
                data-pswp-height="{{ $image['height'] ?? 1080 }}"
                target="_blank"
                class="gallery-item group relative block overflow-hidden rounded-xl border border-gray-200 aspect-video hover:border-pink-500 transition-all duration-300"
            >
                <img
                    src="{{ $image['src'] }}"
                    alt="{{ $image['alt'] ?? $modTitle }}"
                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                    loading="lazy"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between text-white text-xs">
                        <span><i class="fa-solid fa-expand mr-1"></i>{{ $index + 1 }}/{{ $images->count() }}</span>
                        <i class="fa-solid fa-search-plus"></i>
                    </div>
                </div>
            </a>
        @endforeach

        {{-- Hidden images for load more --}}
        @if($hasMore)
            @foreach($images->skip($initialLimit) as $index => $image)
                <a
                    href="{{ $image['src'] }}"
                    data-pswp-width="{{ $image['width'] ?? 1920 }}"
                    data-pswp-height="{{ $image['height'] ?? 1080 }}"
                    target="_blank"
                    class="gallery-item group relative block overflow-hidden rounded-xl border border-gray-200 aspect-video hover:border-pink-500 transition-all duration-300 hidden"
                    data-hidden-image
                >
                    <img
                        src="{{ $image['src'] }}"
                        alt="{{ $image['alt'] ?? $modTitle }}"
                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between text-white text-xs">
                            <span><i class="fa-solid fa-expand mr-1"></i>{{ $initialLimit + $index + 1 }}/{{ $images->count() }}</span>
                            <i class="fa-solid fa-search-plus"></i>
                        </div>
                    </div>
                </a>
            @endforeach
        @endif
    </div>

    @if($hasMore)
        <div class="mt-4 text-center">
            <button
                type="button"
                id="load-more-images"
                class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-pink-600 to-purple-600 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105"
                data-load-more-btn
            >
                <i class="fa-solid fa-images"></i>
                <span>További képek betöltése</span>
                <span class="px-2 py-0.5 bg-white/20 rounded-full text-xs">{{ $images->count() - $initialLimit }}</span>
            </button>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize PhotoSwipe Lightbox
    const lightbox = new PhotoSwipeLightbox({
        gallery: '#gallery-grid',
        children: 'a.gallery-item',
        pswpModule: PhotoSwipe,
        bgOpacity: 0.9,
        padding: { top: 50, bottom: 50, left: 20, right: 20 },
    });

    lightbox.init();

    // Load more functionality
    const loadMoreBtn = document.querySelector('[data-load-more-btn]');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const hiddenImages = document.querySelectorAll('[data-hidden-image]');
            hiddenImages.forEach(img => {
                img.classList.remove('hidden');
            });
            loadMoreBtn.style.display = 'none';

            // Reinitialize lightbox to include newly visible images
            lightbox.init();
        });
    }
});
</script>
@endpush
