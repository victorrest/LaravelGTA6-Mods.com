@props(['images', 'videos', 'modTitle', 'modId', 'canManageVideos' => false])

@php
    $allMediaItems = [];
    $imageSequence = 1;
    $videoSequence = 1;

    // Process images
    foreach ($images as $image) {
        $allMediaItems[] = [
            'type' => 'image',
            'src' => $image['src'],
            'thumbnail' => $image['src'],
            'alt' => $image['alt'] ?? $modTitle,
            'width' => \Illuminate\Support\Arr::get($image, 'width', 1920),
            'height' => \Illuminate\Support\Arr::get($image, 'height', 1080),
            'sequence' => $imageSequence++,
        ];
    }

    // Process videos
    foreach ($videos as $video) {
        $submitter = $video->submitter;
        $thumbnailLarge = $video->thumbnail_large_url ?? $video->thumbnail_url;
        $thumbnailSmall = $video->thumbnail_small_url ?? $thumbnailLarge;

        $durationLabel = null;

        if (!empty($video->duration)) {
            try {
                $interval = \Carbon\CarbonInterval::make($video->duration);
                if ($interval) {
                    $seconds = (int) $interval->totalSeconds;
                    if ($seconds >= 3600) {
                        $durationLabel = gmdate('H:i:s', $seconds);
                    } elseif ($seconds > 0) {
                        $durationLabel = gmdate('i:s', $seconds);
                    }
                }
            } catch (\Exception $e) {
                $durationLabel = null;
            }
        }

        $allMediaItems[] = [
            'type' => 'video',
            'youtube_id' => $video->youtube_id,
            'src' => $thumbnailLarge,
            'thumbnail' => $thumbnailSmall,
            'alt' => $video->video_title ?: $modTitle,
            'title' => $video->video_title,
            'width' => 1920,
            'height' => 1080,
            'sequence' => $videoSequence++,
            'video_id' => $video->id,
            'submitter_name' => $submitter->name ?? 'Community Member',
            'submitter_url' => $submitter ? route('author.profile', $submitter->username ?? $submitter->id) : null,
            'is_featured' => (bool) $video->is_featured,
            'status' => $video->status,
            'duration' => $durationLabel,
            'raw_duration' => $video->duration,
            'description' => $video->video_description,
            'youtube_url' => $video->youtube_url ?: "https://www.youtube.com/watch?v={$video->youtube_id}",
            'report_url' => auth()->check() ? route('api.videos.report', $video) : null,
            'feature_url' => $canManageVideos ? route('api.videos.feature', $video) : null,
            'unfeature_url' => $canManageVideos ? route('api.videos.unfeature', $video) : null,
            'delete_url' => $canManageVideos ? route('api.videos.destroy', $video) : null,
            'can_manage' => (bool) $canManageVideos,
            'submitted_human' => optional($video->created_at)->diffForHumans(),
            'report_count' => $video->report_count,
        ];
    }

    // Move featured video to first position if exists
    $featuredIndex = null;
    foreach ($allMediaItems as $index => $item) {
        if ($item['type'] === 'video' && ($item['is_featured'] ?? false)) {
            $featuredIndex = $index;
            break;
        }
    }

    if ($featuredIndex !== null && $featuredIndex > 0) {
        $featuredItem = $allMediaItems[$featuredIndex];
        unset($allMediaItems[$featuredIndex]);
        array_unshift($allMediaItems, $featuredItem);
        $allMediaItems = array_values($allMediaItems);
    }

    $mainMedia = $allMediaItems[0] ?? null;
    $thumbnails = array_slice($allMediaItems, 1);
    $visibleThumbnails = array_slice($thumbnails, 0, 5);
    $hiddenThumbnails = array_slice($thumbnails, 5);
@endphp

{{-- Gallery Container --}}
<div class="card overflow-hidden" data-pswp-gallery="{{ $modId }}">
    @if($mainMedia)
        {{-- Main Media Display --}}
        <button type="button" class="relative w-full aspect-video bg-gray-900 cursor-pointer group focus:outline-none" data-pswp-index="0">
            @if($mainMedia['type'] === 'image')
                <img src="{{ $mainMedia['src'] }}"
                     alt="{{ $mainMedia['alt'] }}"
                     class="absolute inset-0 w-full h-full object-cover group-hover:opacity-90 transition">
            @else
                {{-- Video Thumbnail --}}
                <img src="{{ $mainMedia['thumbnail'] }}"
                     alt="{{ $mainMedia['alt'] }}"
                     class="absolute inset-0 w-full h-full object-cover group-hover:opacity-90 transition">
                {{-- Play Icon --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-20 h-20 bg-red-600/90 rounded-full flex items-center justify-center group-hover:bg-red-700 transition">
                        <i class="fab fa-youtube text-white text-3xl ml-1"></i>
                    </div>
                </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent pointer-events-none"></div>

            {{-- Media Type Badge --}}
            <div class="absolute top-3 right-3 z-10">
                @if($mainMedia['type'] === 'video')
                    <span class="bg-red-600 text-white text-xs font-bold px-2 py-1 rounded">
                        <i class="fab fa-youtube mr-1"></i>VIDEO
                    </span>
                @endif
            </div>
        </button>

        {{-- Thumbnails Grid --}}
        @if(count($thumbnails) > 0)
            <div class="p-4 md:p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-images text-pink-600"></i>
                        <span>Gallery</span>
                    </h3>
                    <span class="text-sm text-gray-500 font-medium">{{ count($allMediaItems) }} items</span>
                </div>

                {{-- Thumbnails --}}
                <div class="grid grid-cols-5 gap-2" id="gallery-thumbnails">
                    @foreach($visibleThumbnails as $index => $item)
                        <button type="button" class="relative aspect-video rounded-md overflow-hidden cursor-pointer border-2 {{ $index === 0 ? 'border-pink-500' : 'border-transparent hover:border-pink-500' }} transition group focus:outline-none"
                                data-pswp-index="{{ $index + 1 }}">
                            <img src="{{ $item['thumbnail'] }}"
                                 alt="{{ $item['alt'] }}"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">

                            @if($item['type'] === 'video')
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/40 transition">
                                    <i class="fab fa-youtube text-white text-xl"></i>
                                </div>
                            @endif
                        </button>
                    @endforeach

                    {{-- Hidden thumbnails (loaded on click) --}}
                    @foreach($hiddenThumbnails as $index => $item)
                        <button type="button" class="hidden gallery-hidden-thumb relative aspect-video rounded-md overflow-hidden cursor-pointer border-2 border-transparent hover:border-pink-500 transition group focus:outline-none"
                                data-pswp-index="{{ $index + count($visibleThumbnails) + 1 }}">
                            <img src="{{ $item['thumbnail'] }}"
                                 alt="{{ $item['alt'] }}"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">

                            @if($item['type'] === 'video')
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/40 transition">
                                    <i class="fab fa-youtube text-white text-xl"></i>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Load More Button --}}
                @if(count($hiddenThumbnails) > 0)
                    <button type="button"
                            class="w-full mt-4 py-3 px-4 rounded-lg border-2 border-pink-500 text-pink-600 font-semibold hover:bg-pink-50 transition duration-300 ease-in-out flex items-center justify-center"
                            data-load-more>
                        <i class="fas fa-images mr-2"></i>
                        <span>Load {{ count($hiddenThumbnails) }} More</span>
                    </button>
                @endif
            </div>
        @endif
    @else
        {{-- No Media Placeholder --}}
        <div class="aspect-video bg-gray-100 flex items-center justify-center">
            <div class="text-center text-gray-400">
                <i class="fa-regular fa-image text-6xl mb-3"></i>
                <p class="text-sm">No media available</p>
            </div>
        </div>
    @endif
</div>

{{-- Hidden data for PhotoSwipe --}}
<script id="gallery-data-{{ $modId }}" type="application/json">
    {!! json_encode($allMediaItems) !!}
</script>

{{-- Add Video Button (for authenticated users) --}}
@auth
    <x-mod.video-submit-modal :modId="$modId" />
@endauth
