@props(['images', 'videos', 'modTitle', 'modId', 'canManageVideos' => false, 'userLoggedIn' => false])

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
            'width' => 1920,
            'height' => 1080,
            'sequence' => $imageSequence++,
        ];
    }

    // Process videos
    foreach ($videos as $video) {
        $thumbnailUrl = $video->thumbnail_url ?? "https://i.ytimg.com/vi/{$video->youtube_id}/hqdefault.jpg";
        $thumbnailSmall = method_exists($video, 'getAttribute') ? $video->thumbnail_small_url ?? $thumbnailUrl : $thumbnailUrl;
        $thumbnailLarge = method_exists($video, 'getAttribute') ? $video->thumbnail_large_url ?? $thumbnailUrl : $thumbnailUrl;
        $submitter = $video->submitter;
        $submitterName = $submitter->name ?? 'Community member';
        $submitterProfileUrl = $submitter ? route('author.profile', $submitter->name) : null;
        $canManageVideo = $canManageVideos;
        $canReportVideo = $userLoggedIn && !$canManageVideo;

        $allMediaItems[] = [
            'type' => 'video',
            'youtube_id' => $video->youtube_id,
            'src' => $thumbnailUrl,
            'thumbnail' => $thumbnailUrl,
            'alt' => $video->title ?? $modTitle,
            'width' => 1920,
            'height' => 1080,
            'sequence' => $videoSequence++,
            'video_id' => $video->id,
            'submitter_name' => $submitterName,
            'submitter_profile_url' => $submitterProfileUrl,
            'is_featured' => (bool) ($video->is_featured ?? false),
            'status' => $video->status ?? 'approved',
            'report_count' => (int) ($video->report_count ?? 0),
            'youtube_url' => $video->youtube_url ?? null,
            'title' => $video->video_title ?? $video->title ?? $modTitle,
            'description' => $video->video_description ?? null,
            'thumbnail_small' => $thumbnailSmall,
            'thumbnail_large' => $thumbnailLarge,
            'can_manage' => $canManageVideo,
            'can_feature' => $canManageVideo,
            'can_remove' => $canManageVideo,
            'can_report' => $canReportVideo,
            'submitted_at' => optional($video->submitted_at)->diffForHumans(),
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
@php($visibleThumbnailCount = count($visibleThumbnails))

<div class="card overflow-hidden" data-mod-gallery="{{ $modId }}" data-user-auth="{{ $userLoggedIn ? '1' : '0' }}" data-can-manage-videos="{{ $canManageVideos ? '1' : '0' }}" data-gallery-title="{{ e($modTitle) }}">
    @if($mainMedia)
        {{-- Main Media Display --}}
        <div class="relative w-full aspect-video bg-gray-900 cursor-pointer group" data-pswp-index="0">
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
        </div>

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
                <div class="grid grid-cols-5 gap-2" id="gallery-thumbnails-{{ $modId }}">
                    @foreach($visibleThumbnails as $index => $item)
                        <div class="relative aspect-video rounded-md overflow-hidden cursor-pointer border-2 {{ $index === 0 ? 'border-pink-500' : 'border-transparent hover:border-pink-500' }} transition group"
                             data-pswp-index="{{ $index + 1 }}">
                            <img src="{{ $item['thumbnail'] }}"
                                 alt="{{ $item['alt'] }}"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">

                            @if($item['type'] === 'video')
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/40 transition">
                                    <i class="fab fa-youtube text-white text-xl"></i>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Hidden thumbnails (loaded on click) --}}
                    @foreach($hiddenThumbnails as $index => $item)
                        <div class="hidden gallery-hidden-thumb relative aspect-video rounded-md overflow-hidden cursor-pointer border-2 border-transparent hover:border-pink-500 transition group"
                             data-pswp-index="{{ $index + 1 + $visibleThumbnailCount }}">
                            <img src="{{ $item['thumbnail'] }}"
                                 alt="{{ $item['alt'] }}"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">

                            @if($item['type'] === 'video')
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/40 transition">
                                    <i class="fab fa-youtube text-white text-xl"></i>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Load More Button --}}
                @if(count($hiddenThumbnails) > 0)
                    <button type="button" id="load-more-gallery-{{ $modId }}"
                            class="w-full mt-4 py-3 px-4 rounded-lg border-2 border-pink-500 text-pink-600 font-semibold hover:bg-pink-50 transition duration-300 ease-in-out flex items-center justify-center">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modId = {{ $modId }};
    const galleryDataEl = document.getElementById(`gallery-data-${modId}`);
    const galleryRoot = document.querySelector(`[data-mod-gallery="${modId}"]`);

    if (!galleryDataEl || !galleryRoot) {
        return;
    }

    const galleryTitle = galleryRoot.dataset.galleryTitle || '';
    const rawText = galleryDataEl.textContent || '[]';
    let parsedData;

    try {
        parsedData = JSON.parse(rawText);
    } catch (error) {
        parsedData = [];
        console.error('Failed to parse gallery data', error);
    }

    if (!Array.isArray(parsedData) || !parsedData.length) {
        return;
    }

    const escapeHtml = (value) => {
        if (typeof value !== 'string') {
            return '';
        }

        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };

        return value.replace(/[&<>"']/g, (char) => map[char] || char);
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const request = async (url, { method = 'POST', body = null } = {}) => {
        const headers = {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        };

        if (body) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(url, {
            method,
            headers,
            body: body ? JSON.stringify(body) : undefined,
        });

        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }

        if (!response.ok) {
            throw new Error(data.message || 'Unexpected error.');
        }

        return data;
    };

    const ensureVideoFlags = (video) => {
        const canManageFallback = galleryRoot.dataset.canManageVideos === '1';
        const isAuthenticated = galleryRoot.dataset.userAuth === '1';

        return {
            ...video,
            can_manage: typeof video.can_manage === 'boolean' ? video.can_manage : canManageFallback,
            can_feature: typeof video.can_feature === 'boolean' ? video.can_feature : canManageFallback,
            can_remove: typeof video.can_remove === 'boolean' ? video.can_remove : canManageFallback,
            can_report: typeof video.can_report === 'boolean'
                ? video.can_report
                : (isAuthenticated && !(typeof video.can_manage === 'boolean' ? video.can_manage : canManageFallback)),
        };
    };

    const renderVideoBadges = (video) => {
        const badges = [];

        if (video.is_featured) {
            badges.push('<span class="px-2 py-0.5 rounded-full bg-yellow-400/90 text-[10px] font-semibold text-black uppercase tracking-wide">Featured</span>');
        }

        if (video.status && video.status !== 'approved') {
            badges.push(`<span class="px-2 py-0.5 rounded-full bg-amber-500/80 text-[10px] font-semibold text-black uppercase tracking-wide">${escapeHtml(String(video.status))}</span>`);
        }

        if (Number(video.report_count) > 0) {
            badges.push(`<span class="px-2 py-0.5 rounded-full bg-red-500/90 text-[10px] font-semibold text-white uppercase tracking-wide">${escapeHtml(String(video.report_count))} reports</span>`);
        }

        return badges;
    };

    const buildVideoSlideHtml = (rawVideo) => {
        const video = ensureVideoFlags(rawVideo);
        const youtubeUrl = video.youtube_url || `https://www.youtube.com/watch?v=${video.youtube_id}`;
        const title = escapeHtml(video.title || 'Video showcase');
        const submitterName = escapeHtml(video.submitter_name || 'Community member');
        const submitter = video.submitter_profile_url
            ? `<a href="${escapeHtml(video.submitter_profile_url)}" class="text-pink-200 hover:text-white font-semibold" target="_blank" rel="noopener noreferrer">${submitterName}</a>`
            : `<span class="text-pink-200 font-semibold">${submitterName}</span>`;
        const submittedAt = video.submitted_at ? `<span class="ml-2 text-xs text-gray-400">${escapeHtml(video.submitted_at)}</span>` : '';
        const badges = renderVideoBadges(video);
        const badgesMarkup = badges.length
            ? `<div class="flex flex-wrap gap-2 mt-2" data-video-badges>${badges.join('')}</div>`
            : '<div class="flex flex-wrap gap-2 mt-2 hidden" data-video-badges></div>';
        const descriptionMarkup = video.description
            ? `<p class="mt-3 text-sm text-gray-200/90 leading-relaxed max-h-32 overflow-y-auto pr-1">${escapeHtml(video.description)}</p>`
            : '';

        const actions = [];
        actions.push(`<a href="${escapeHtml(youtubeUrl)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg shadow hover:bg-red-700 transition" data-video-open="youtube"><i class="fab fa-youtube"></i><span>YouTube</span></a>`);

        if (video.can_report) {
            actions.push(`<button type="button" class="inline-flex items-center gap-2 px-4 py-2 border border-pink-400/80 text-xs font-semibold text-pink-100 rounded-lg hover:bg-pink-500/20 transition" data-video-report="${video.video_id}"><i class="fa-solid fa-flag"></i><span>Report</span></button>`);
        }

        if (video.can_manage) {
            const featureClasses = video.is_featured
                ? 'bg-yellow-400 text-gray-900'
                : 'bg-pink-600 text-white';

            const featureLabel = video.is_featured ? 'Unfeature' : 'Feature';
            actions.push(`<button type="button" class="inline-flex items-center gap-2 px-4 py-2 ${featureClasses} text-xs font-semibold rounded-lg hover:opacity-90 transition" data-video-feature="${video.video_id}" data-video-feature-state="${video.is_featured ? 'featured' : 'available'}"><i class="fa-solid fa-star"></i><span>${featureLabel}</span></button>`);
        }

        if (video.can_remove) {
            actions.push(`<button type="button" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-500/60 text-xs font-semibold text-gray-200 rounded-lg hover:bg-gray-700/60 transition" data-video-remove="${video.video_id}"><i class="fa-solid fa-trash"></i><span>Remove</span></button>`);
        }

        const actionsMarkup = actions.length
            ? `<div class="flex flex-wrap gap-2" data-video-action-bar>${actions.join('')}</div>`
            : '';

        return `
            <div class="pswp-video-slide flex flex-col h-full">
                <div class="pswp-video-frame relative flex-1 rounded-xl overflow-hidden bg-black" data-video-embed>
                    <div class="absolute inset-0 flex items-center justify-center text-gray-400 text-sm tracking-wide">Loading video…</div>
                </div>
                <div class="pswp-video-footer mt-4 bg-gray-900/95 border border-gray-700/70 rounded-2xl p-4 text-left text-gray-100 shadow-lg shadow-black/20">
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-[220px] flex-1">
                                <div class="text-[11px] uppercase tracking-wider text-pink-300/90">Video showcase</div>
                                <h3 class="text-lg font-semibold text-white mt-1">${title}</h3>
                                <div class="text-sm text-gray-300 mt-1">Submitted by ${submitter}${submittedAt}</div>
                                ${badgesMarkup}
                            </div>
                            ${actionsMarkup}
                        </div>
                        ${descriptionMarkup}
                        <div class="pswp-video-message hidden text-sm font-semibold" data-video-message></div>
                    </div>
                </div>
            </div>
        `;
    };

    const galleryData = parsedData.map((item, index) => {
        if (item && item.type === 'video') {
            return ensureVideoFlags(item);
        }

        return item;
    });

    const buildSlides = () => galleryData.map((item, index) => {
        if (item && item.type === 'video') {
            const video = ensureVideoFlags(item);
            return {
                html: buildVideoSlideHtml(video),
                width: Number(video.width) || 1920,
                height: Number(video.height) || 1080,
                pswpType: 'video',
                video,
                index,
            };
        }

        return {
            src: item?.src,
            msrc: item?.thumbnail || item?.src,
            width: Number(item?.width) || 1920,
            height: Number(item?.height) || 1080,
            alt: item?.alt || galleryTitle,
            pswpType: 'image',
            index,
        };
    });

    const computePadding = (data) => {
        if (data && data.pswpType === 'video') {
            return { top: 24, bottom: 184, left: 16, right: 16 };
        }

        return { top: 24, bottom: 48, left: 16, right: 16 };
    };

    const updateVideoBadges = (slideElement, video) => {
        const badgeContainer = slideElement.querySelector('[data-video-badges]');
        if (!badgeContainer) {
            return;
        }

        const badges = renderVideoBadges(video);
        if (!badges.length) {
            badgeContainer.innerHTML = '';
            badgeContainer.classList.add('hidden');
            return;
        }

        badgeContainer.innerHTML = badges.join('');
        badgeContainer.classList.remove('hidden');
    };

    const setFeatureButtonState = (button, isFeatured) => {
        if (!button) {
            return;
        }

        const iconHtml = '<i class="fa-solid fa-star"></i><span>' + (isFeatured ? 'Unfeature' : 'Feature') + '</span>';
        button.innerHTML = iconHtml;
        button.dataset.videoFeatureState = isFeatured ? 'featured' : 'available';
        button.classList.toggle('bg-yellow-400', isFeatured);
        button.classList.toggle('text-gray-900', isFeatured);
        button.classList.toggle('bg-pink-600', !isFeatured);
        button.classList.toggle('text-white', !isFeatured);
    };

    const attachVideoActions = (slideElement, video, slideIndex) => {
        if (!slideElement || !video) {
            return;
        }

        const messageEl = slideElement.querySelector('[data-video-message]');
        const showMessage = (type, text) => {
            if (!messageEl) {
                return;
            }

            const baseClasses = ['hidden', 'text-emerald-400', 'text-red-400', 'text-amber-300'];
            messageEl.classList.remove(...baseClasses);
            messageEl.textContent = text;

            if (type === 'success') {
                messageEl.classList.add('text-emerald-400');
            } else if (type === 'error') {
                messageEl.classList.add('text-red-400');
            } else {
                messageEl.classList.add('text-amber-300');
            }

            messageEl.classList.remove('hidden');
        };

        if (messageEl) {
            messageEl.textContent = '';
            messageEl.classList.add('hidden');
        }

        const reportButton = slideElement.querySelector('[data-video-report]');
        if (reportButton && !reportButton.dataset.pswpBound) {
            reportButton.dataset.pswpBound = '1';
            reportButton.addEventListener('click', async () => {
                if (!csrfToken) {
                    window.location.href = '/login';
                    return;
                }

                reportButton.disabled = true;
                reportButton.classList.add('opacity-60');
                showMessage('info', 'Reporting video…');

                try {
                    const data = await request(`/api/videos/${video.video_id}/report`);
                    showMessage('success', data.message || 'Video reported.');
                    reportButton.innerHTML = '<i class="fa-solid fa-flag"></i><span>Reported</span>';
                    video.can_report = false;
                    video.report_count = (Number(video.report_count) || 0) + 1;
                    updateVideoBadges(slideElement, video);
                    if (typeof slideIndex === 'number' && galleryData[slideIndex]) {
                        galleryData[slideIndex] = {
                            ...galleryData[slideIndex],
                            can_report: false,
                            report_count: video.report_count,
                        };
                    }
                } catch (error) {
                    showMessage('error', error.message);
                    reportButton.disabled = false;
                    reportButton.classList.remove('opacity-60');
                }
            });
        }

        const featureButton = slideElement.querySelector('[data-video-feature]');
        if (featureButton && !featureButton.dataset.pswpBound) {
            featureButton.dataset.pswpBound = '1';
            featureButton.addEventListener('click', async () => {
                if (!csrfToken) {
                    window.location.href = '/login';
                    return;
                }

                const isFeatured = featureButton.dataset.videoFeatureState === 'featured';
                featureButton.disabled = true;
                featureButton.classList.add('opacity-80');
                showMessage('info', isFeatured ? 'Removing featured state…' : 'Featuring video…');

                try {
                    if (isFeatured) {
                        await request(`/api/videos/${video.video_id}/feature`, { method: 'DELETE' });
                    } else {
                        await request(`/api/videos/${video.video_id}/feature`);
                    }

                    video.is_featured = !isFeatured;
                    setFeatureButtonState(featureButton, video.is_featured);
                    updateVideoBadges(slideElement, video);
                    showMessage('success', video.is_featured ? 'Video is now featured.' : 'Video feature removed.');
                    if (typeof slideIndex === 'number' && galleryData[slideIndex]) {
                        galleryData[slideIndex] = {
                            ...galleryData[slideIndex],
                            is_featured: video.is_featured,
                        };
                    }
                } catch (error) {
                    showMessage('error', error.message);
                } finally {
                    featureButton.disabled = false;
                    featureButton.classList.remove('opacity-80');
                }
            });
        }

        const removeButton = slideElement.querySelector('[data-video-remove]');
        if (removeButton && !removeButton.dataset.pswpBound) {
            removeButton.dataset.pswpBound = '1';
            removeButton.addEventListener('click', async () => {
                if (!csrfToken) {
                    window.location.href = '/login';
                    return;
                }

                const confirmed = window.confirm('Remove this video from the gallery?');
                if (!confirmed) {
                    return;
                }

                removeButton.disabled = true;
                removeButton.classList.add('opacity-70');
                showMessage('info', 'Removing video…');

                try {
                    const data = await request(`/api/videos/${video.video_id}`, { method: 'DELETE' });
                    showMessage('success', data.message || 'Video removed. Refreshing…');
                    setTimeout(() => window.location.reload(), 900);
                } catch (error) {
                    showMessage('error', error.message);
                    removeButton.disabled = false;
                    removeButton.classList.remove('opacity-70');
                }
            });
        }
    };

    const loadMoreBtn = document.getElementById(`load-more-gallery-${modId}`);
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            galleryRoot.querySelectorAll('.gallery-hidden-thumb').forEach((thumb) => {
                thumb.classList.remove('hidden');
            });
            loadMoreBtn.remove();
        });
    }

    const openLightboxAt = (index) => {
        if (typeof window.PhotoSwipe !== 'function') {
            return false;
        }

        const slides = buildSlides();
        if (!slides.length) {
            return false;
        }

        const safeIndex = Math.max(0, Math.min(index, slides.length - 1));
        const initialSlide = slides[safeIndex];

        const pswp = new window.PhotoSwipe({
            dataSource: slides,
            index: safeIndex,
            bgOpacity: 0.94,
            wheelToZoom: false,
            showHideAnimationType: 'fade',
            padding: computePadding(initialSlide),
            paddingFn: (_viewportSize, itemData) => computePadding(itemData),
        });

        pswp.on('uiRegister', () => {
            pswp.ui?.registerElement({
                name: 'gta6modsCaption',
                order: 9,
                isButton: false,
                appendTo: 'root',
                className: 'pswp__gta6mods-caption text-sm text-gray-200 text-center w-full px-6 pb-6',
                onInit: (element, instance) => {
                    if (!element || !instance) {
                        return;
                    }

                    const updateCaption = () => {
                        const current = instance.currSlide;
                        if (current && current.data && current.data.pswpType === 'image' && galleryTitle) {
                            element.textContent = galleryTitle;
                            element.removeAttribute('hidden');
                        } else {
                            element.textContent = '';
                            element.setAttribute('hidden', 'hidden');
                        }
                    };

                    element.setAttribute('hidden', 'hidden');
                    instance.on('change', updateCaption);
                    instance.on('afterInit', updateCaption);
                },
            });
        });

        pswp.on('contentActivate', (event) => {
            const content = event?.content;
            const data = content?.data;
            const isVideo = data?.pswpType === 'video';

            if (pswp.element) {
                pswp.element.classList.toggle('pswp--video', Boolean(isVideo));
            }

            if (isVideo) {
                const slideElement = content?.element;
                const slideIndex = typeof data?.index === 'number' ? data.index : safeIndex;
                const sourceVideo = data.video || galleryData[slideIndex] || null;

                if (slideElement && sourceVideo) {
                    const frame = slideElement.querySelector('[data-video-embed]');
                    if (frame && !frame.querySelector('iframe')) {
                        const youtubeId = sourceVideo.youtube_id;
                        if (youtubeId) {
                            frame.innerHTML = `
                                <iframe
                                    src="https://www.youtube.com/embed/${encodeURIComponent(youtubeId)}?autoplay=1&rel=0&modestbranding=1"
                                    title="${escapeHtml(sourceVideo.title || 'YouTube video')}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen
                                ></iframe>
                            `;
                        }
                    }

                    attachVideoActions(slideElement, sourceVideo, slideIndex);
                }
            } else if (content?.element && content.element.tagName === 'IMG') {
                content.element.alt = data?.alt || galleryTitle || '';
            }
        });

        pswp.on('contentDeactivate', (event) => {
            const element = event?.content?.element;
            if (element) {
                const frame = element.querySelector('[data-video-embed]');
                if (frame) {
                    frame.innerHTML = '';
                }
            }
        });

        pswp.on('close', () => {
            if (pswp.element) {
                pswp.element.classList.remove('pswp--video');
            }
        });

        pswp.init();
        return true;
    };

    galleryRoot.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-pswp-index]') : null;
        if (!trigger) {
            return;
        }

        const index = Number.parseInt(trigger.getAttribute('data-pswp-index'), 10);
        if (!Number.isFinite(index)) {
            return;
        }

        if (openLightboxAt(index)) {
            event.preventDefault();
        }
    });
});
</script>
@endpush

{{-- Add Video Button (for authenticated users) --}}
@auth
    <x-mod.video-submit-modal :modId="$modId" />
@endauth
