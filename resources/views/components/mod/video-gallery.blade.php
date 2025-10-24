@props(['videos', 'modId', 'modTitle', 'canManage' => false])

@php
    $videos = collect($videos)->values();
    $initialLimit = 6;
    $hasMore = $videos->count() > $initialLimit;
    $featuredVideo = $videos->where('is_featured', true)->first();
@endphp

<div class="mod-video-gallery space-y-6" data-video-gallery-root>
    @if($featuredVideo)
        {{-- Featured Video --}}
        <div class="featured-video-container">
            <div class="relative aspect-video rounded-xl overflow-hidden bg-black shadow-2xl">
                <iframe
                    src="https://www.youtube.com/embed/{{ $featuredVideo->youtube_id }}?rel=0"
                    title="{{ $featuredVideo->video_title }}"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                    class="absolute inset-0 w-full h-full"
                ></iframe>
            </div>
            @if($featuredVideo->video_title)
                <div class="mt-3 flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fa-solid fa-star text-yellow-400 mr-2"></i>{{ $featuredVideo->video_title }}
                        </h3>
                        @if($featuredVideo->video_description)
                            <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $featuredVideo->video_description }}</p>
                        @endif
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fa-solid fa-user mr-1"></i>Beküldve: <strong>{{ $featuredVideo->submitter->name }}</strong>
                        </p>
                    </div>
                    @if($canManage)
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs font-medium bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                            onclick="unfeaturedVideo({{ $featuredVideo->id }})"
                        >
                            <i class="fa-solid fa-star-half-stroke mr-1"></i>Nem kiemelt
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Video Grid --}}
    @if($videos->count() > ($featuredVideo ? 1 : 0))
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="video-grid">
            @foreach($videos->reject(fn($v) => $v->is_featured)->take($initialLimit) as $video)
                <div class="video-item group relative" data-video-item data-video-id="{{ $video->id }}">
                    <a
                        href="https://www.youtube.com/watch?v={{ $video->youtube_id }}"
                        data-video-pswp
                        data-youtube-id="{{ $video->youtube_id }}"
                        data-video-title="{{ $video->video_title }}"
                        data-video-description="{{ $video->video_description }}"
                        data-submitter="{{ $video->submitter->name }}"
                        class="block relative aspect-video rounded-lg overflow-hidden bg-gray-900 hover:ring-2 hover:ring-pink-500 transition-all duration-300"
                    >
                        <img
                            src="{{ $video->thumbnail_small_url }}"
                            alt="{{ $video->video_title }}"
                            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        >
                        <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/50 transition-colors duration-300">
                            <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <i class="fa-brands fa-youtube text-white text-2xl"></i>
                            </div>
                        </div>
                        @if($video->duration)
                            <div class="absolute bottom-2 right-2 px-2 py-1 bg-black/80 text-white text-xs rounded">
                                {{ $video->duration }}
                            </div>
                        @endif
                    </a>
                    <div class="mt-2">
                        <h4 class="text-sm font-medium text-gray-900 line-clamp-2 group-hover:text-pink-600 transition">
                            {{ $video->video_title ?: 'YouTube videó' }}
                        </h4>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fa-solid fa-user mr-1"></i>{{ $video->submitter->name }}
                        </p>
                    </div>

                    @if($canManage)
                        <div class="mt-2 flex items-center gap-2">
                            <button
                                type="button"
                                class="flex-1 px-3 py-1.5 text-xs font-medium bg-yellow-500 text-white rounded hover:bg-yellow-600 transition"
                                onclick="featureVideo({{ $video->id }})"
                            >
                                <i class="fa-solid fa-star mr-1"></i>Kiemelés
                            </button>
                            <button
                                type="button"
                                class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded hover:bg-red-700 transition"
                                onclick="deleteVideo({{ $video->id }})"
                            >
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    @elseif(auth()->check())
                        <button
                            type="button"
                            class="mt-2 w-full px-3 py-1.5 text-xs font-medium bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition"
                            onclick="reportVideo({{ $video->id }})"
                        >
                            <i class="fa-solid fa-flag mr-1"></i>Jelentés
                        </button>
                    @endif
                </div>
            @endforeach

            {{-- Hidden videos for load more --}}
            @if($hasMore)
                @foreach($videos->reject(fn($v) => $v->is_featured)->skip($initialLimit) as $video)
                    <div class="video-item group relative hidden" data-video-item data-hidden-video data-video-id="{{ $video->id }}">
                        <a
                            href="https://www.youtube.com/watch?v={{ $video->youtube_id }}"
                            data-video-pswp
                            data-youtube-id="{{ $video->youtube_id }}"
                            data-video-title="{{ $video->video_title }}"
                            data-video-description="{{ $video->video_description }}"
                            data-submitter="{{ $video->submitter->name }}"
                            class="block relative aspect-video rounded-lg overflow-hidden bg-gray-900 hover:ring-2 hover:ring-pink-500 transition-all duration-300"
                        >
                            <img
                                src="{{ $video->thumbnail_small_url }}"
                                alt="{{ $video->video_title }}"
                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                loading="lazy"
                            >
                            <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/50 transition-colors duration-300">
                                <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                    <i class="fa-brands fa-youtube text-white text-2xl"></i>
                                </div>
                            </div>
                        </a>
                        <div class="mt-2">
                            <h4 class="text-sm font-medium text-gray-900 line-clamp-2">{{ $video->video_title ?: 'YouTube videó' }}</h4>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fa-solid fa-user mr-1"></i>{{ $video->submitter->name }}
                            </p>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        @if($hasMore)
            <div class="text-center">
                <button
                    type="button"
                    id="load-more-videos"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105"
                    data-load-more-videos-btn
                >
                    <i class="fa-brands fa-youtube"></i>
                    <span>További videók betöltése</span>
                    <span class="px-2 py-0.5 bg-white/20 rounded-full text-xs">{{ $videos->count() - $initialLimit - ($featuredVideo ? 1 : 0) }}</span>
                </button>
            </div>
        @endif
    @endif

    {{-- Add Video Button --}}
    @auth
        <div class="text-center border-t pt-6">
            <button
                type="button"
                id="add-video-btn"
                class="inline-flex items-center gap-2 px-6 py-3 bg-white border-2 border-pink-600 text-pink-600 font-semibold rounded-lg hover:bg-pink-50 transition-all duration-300"
                data-modal-trigger="video-submit-modal"
            >
                <i class="fa-brands fa-youtube text-xl"></i>
                <span>YouTube videó hozzáadása</span>
            </button>
        </div>
    @endauth
</div>

@push('scripts')
<script>
// PhotoSwipe for videos
document.addEventListener('DOMContentLoaded', function() {
    const videoLinks = document.querySelectorAll('[data-video-pswp]');

    videoLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            const youtubeId = this.dataset.youtubeId;
            const title = this.dataset.videoTitle;
            const description = this.dataset.videoDescription;
            const submitter = this.dataset.submitter;

            openVideoLightbox(youtubeId, title, description, submitter);
        });
    });

    // Load more videos
    const loadMoreBtn = document.querySelector('[data-load-more-videos-btn]');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const hiddenVideos = document.querySelectorAll('[data-hidden-video]');
            hiddenVideos.forEach(video => {
                video.classList.remove('hidden');
            });
            loadMoreBtn.style.display = 'none';
        });
    }
});

function openVideoLightbox(youtubeId, title, description, submitter) {
    const pswp = new PhotoSwipe({
        dataSource: [{
            html: `
                <div class="video-lightbox-content">
                    <div class="aspect-video w-full">
                        <iframe
                            src="https://www.youtube.com/embed/${youtubeId}?autoplay=1&rel=0"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                            class="w-full h-full"
                        ></iframe>
                    </div>
                    <div class="p-4 bg-gray-900 text-white">
                        <h3 class="text-lg font-semibold">${title}</h3>
                        ${description ? `<p class="text-sm text-gray-300 mt-2">${description}</p>` : ''}
                        <p class="text-xs text-gray-400 mt-3">
                            <i class="fa-solid fa-user mr-1"></i>Beküldve: <strong>${submitter}</strong>
                        </p>
                    </div>
                </div>
            `
        }],
        bgOpacity: 0.95,
        padding: { top: 50, bottom: 50, left: 20, right: 20 },
    });

    pswp.init();
}

// Video management functions
function featureVideo(videoId) {
    if (!confirm('Biztosan kiemeled ezt a videót?')) return;

    fetch(`/api/videos/${videoId}/feature`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Hiba történt a videó kiemelésekor.');
    });
}

function unfeaturedVideo(videoId) {
    if (!confirm('Biztosan megszünteted a kiemelést?')) return;

    fetch(`/api/videos/${videoId}/feature`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Hiba történt.');
    });
}

function deleteVideo(videoId) {
    if (!confirm('Biztosan törölni szeretnéd ezt a videót?')) return;

    fetch(`/api/videos/${videoId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Hiba történt a videó törlésekor.');
    });
}

function reportVideo(videoId) {
    if (!confirm('Biztosan jelenteni szeretnéd ezt a videót?')) return;

    fetch(`/api/videos/${videoId}/report`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Hiba történt a jelentés küldésekor.');
    });
}
</script>
@endpush
