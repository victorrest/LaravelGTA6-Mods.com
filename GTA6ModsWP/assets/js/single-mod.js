(function () {
    'use strict';

    const RELATED_PLACEHOLDER_IMAGE = 'https://placehold.co/600x400/1f2937/374151?text=GTA6';
    const numberFormatter = (typeof Intl !== 'undefined' && typeof Intl.NumberFormat === 'function')
        ? new Intl.NumberFormat('hu-HU')
        : null;
    const ratingFormatter = (typeof Intl !== 'undefined' && typeof Intl.NumberFormat === 'function')
        ? new Intl.NumberFormat('hu-HU', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
        : null;

    const utils = window.GTAModsUtils || {};
    const getCookie = (typeof utils.getCookie === 'function') ? utils.getCookie : () => null;
    const hasCookie = (typeof utils.hasCookie === 'function') ? utils.hasCookie : () => false;
    const setCookie = (typeof utils.setCookie === 'function') ? utils.setCookie : () => {};
    const scheduleDeferred = (typeof utils.scheduleDeferred === 'function')
        ? utils.scheduleDeferred
        : (callback, delay) => {
            if (typeof callback !== 'function') {
                return;
            }
            const timeout = Number.isFinite(delay) ? Math.max(0, delay) : 0;
            window.setTimeout(callback, timeout);
        };

    function escapeHTML(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        const stringValue = String(value);
        const match = /[&<>'"]/u;
        if (!match.test(stringValue)) {
            return stringValue;
        }

        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        };

        return stringValue.replace(/[&<>'"]/gu, (char) => map[char] || char);
    }

    function formatNumber(value) {
        const numeric = Number(value);
        if (!Number.isFinite(numeric) || numeric < 0) {
            return '0';
        }

        if (numberFormatter) {
            return numberFormatter.format(numeric);
        }

        return String(Math.round(numeric));
    }

    function parseDownloadCount(element) {
        if (!element) {
            return 0;
        }

        const rawAttr = element.getAttribute('data-download-count-raw');
        if (rawAttr) {
            const parsedAttr = parseInt(rawAttr, 10);
            if (Number.isFinite(parsedAttr)) {
                return parsedAttr;
            }
        }

        const text = element.textContent || '';
        const normalized = text.replace(/[^0-9]/g, '');
        const parsedText = parseInt(normalized, 10);
        if (Number.isFinite(parsedText)) {
            return parsedText;
        }

        return 0;
    }

    function updateDownloadElements(elements, nextValue) {
        if (!elements || !elements.length) {
            return;
        }

        const formatted = formatNumber(nextValue);

        elements.forEach((element) => {
            element.textContent = formatted;
            element.setAttribute('data-download-count-raw', String(nextValue));
        });
    }

    function formatRatingAverage(value) {
        const numeric = Number(value);
        if (!Number.isFinite(numeric) || numeric <= 0) {
            return '—';
        }

        if (ratingFormatter) {
            return ratingFormatter.format(numeric);
        }

        const rounded = Math.round(numeric * 10) / 10;
        return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
    }

    function resolveUrl(value) {
        if (typeof value === 'string' && value.trim()) {
            return value;
        }
        return '#';
    }

    function resolveImage(value) {
        if (typeof value === 'string' && value.trim()) {
            return value;
        }
        return RELATED_PLACEHOLDER_IMAGE;
    }

    function buildRestUrl(base, params = {}) {
        if (!base) {
            return '';
        }

        try {
            const url = new URL(base, window.location.origin);
            Object.entries(params).forEach(([key, value]) => {
                if (typeof value === 'undefined' || value === null || value === '') {
                    return;
                }
                url.searchParams.set(key, String(value));
            });
            return url.toString();
        } catch (error) {
            return base;
        }
    }

    function renderRelatedMods(mods) {
        const containers = document.querySelectorAll('[data-related-mods-root]');
        if (!containers.length) {
            return;
        }

        const hasMods = Array.isArray(mods) && mods.length > 0;

        const buildCardList = () => {
            if (!hasMods) {
                return '<div class="p-4 text-sm text-gray-500">Jelenleg nincs kapcsolódó mod.</div>';
            }

            let listHtml = '<div class="p-2 space-y-3">';

            mods.forEach((mod) => {
                if (!mod || typeof mod !== 'object') {
                    return;
                }

                const safeTitle = escapeHTML(typeof mod.title === 'string' ? mod.title : '');
                const displayTitle = safeTitle || 'Ismeretlen mod';
                const safeAuthor = escapeHTML(typeof mod.author === 'string' ? mod.author : '');
                const displayAuthor = safeAuthor || 'Ismeretlen';
                const permalink = escapeHTML(resolveUrl(mod.permalink));
                const thumbnail = escapeHTML(resolveImage(mod.thumbnail));

                const ratingAverage = escapeHTML(formatRatingAverage(mod.rating && mod.rating.average));
                const ratingCount = escapeHTML(formatNumber(mod.rating && mod.rating.count));
                const downloadTotal = escapeHTML(formatNumber(mod.metrics && mod.metrics.downloads));
                const likeTotal = escapeHTML(formatNumber(mod.metrics && mod.metrics.likes));
                const versionLabel = escapeHTML(typeof mod.version === 'string' && mod.version.trim() ? mod.version : '1.0');
                const ratingTitle = ratingAverage === '—'
                    ? 'Még nincs értékelés'
                    : `Átlagos értékelés: ${ratingAverage} (${ratingCount} értékelés)`;
                const safeRatingTitle = escapeHTML(ratingTitle);

                listHtml += `
                    <a href="${permalink}" class="group block p-2 rounded-lg hover:bg-gray-50">
                        <div class="relative overflow-hidden rounded-md">
                            <img src="${thumbnail}" alt="${displayTitle}" class="w-full h-32 object-cover" loading="lazy">
                            <div class="absolute bottom-0 left-0 right-0 p-1.5 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="flex items-center font-semibold" title="${safeRatingTitle}">
                                        <i class="fa-solid fa-star mr-1 text-yellow-400" aria-hidden="true"></i>${ratingAverage}
                                    </span>
                                    <div class="flex items-center space-x-2">
                                        <span class="flex items-center" title="Letöltések: ${downloadTotal}">
                                            <i class="fa-solid fa-download mr-1" aria-hidden="true"></i>${downloadTotal}
                                        </span>
                                        <span class="flex items-center" title="Kedvelések: ${likeTotal}">
                                            <i class="fa-solid fa-thumbs-up mr-1" aria-hidden="true"></i>${likeTotal}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-2">
                            <div class="flex justify-between items-start">
                                <h4 class="font-semibold text-sm text-gray-800 group-hover:text-pink-600 transition pr-2" title="${displayTitle}">${displayTitle}</h4>
                                <span class="text-xs font-bold bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full flex-shrink-0">${versionLabel}</span>
                            </div>
                            <p class="text-xs text-gray-500">by ${displayAuthor}</p>
                        </div>
                    </a>
                `;
            });

            listHtml += '</div>';
            return listHtml;
        };

        const cardHtml = `
            <h3 class="text-lg font-bold text-gray-900 p-4">Hasonló Modok</h3>
            ${buildCardList()}
        `;

        containers.forEach((container) => {
            container.innerHTML = cardHtml;
        });
    }

    const onReady = () => {
        const config = window.GTAModsSingle || {};
        const restEndpoints = (config.restEndpoints && typeof config.restEndpoints === 'object') ? config.restEndpoints : {};
        const restNonce = typeof config.restNonce === 'string' ? config.restNonce : '';
        const restHeaders = {};

        if (restNonce) {
            restHeaders['X-WP-Nonce'] = restNonce;
        }

        const trackingNonce = (typeof window !== 'undefined'
            && window.GTAModsSecurity
            && typeof window.GTAModsSecurity.trackingNonce === 'string')
            ? window.GTAModsSecurity.trackingNonce
            : '';

        if (trackingNonce) {
            restHeaders['X-GTA6-Nonce'] = trackingNonce;
        }

        const downloadEndpoint = typeof restEndpoints.download === 'string' ? restEndpoints.download : '';

        const applyDownloadPayload = (payload) => {
            if (!payload || typeof payload !== 'object') {
                return;
            }

            const totalCounters = document.querySelectorAll('[data-download-count]');
            const resolvedTotal = Number.isFinite(payload.downloads) ? payload.downloads : null;
            const formattedDownloads = payload.formattedDownloads || (resolvedTotal !== null ? formatNumber(resolvedTotal) : '');

            if (formattedDownloads && totalCounters.length) {
                totalCounters.forEach((element) => {
                    element.textContent = formattedDownloads;
                    if (resolvedTotal !== null) {
                        element.setAttribute('data-download-count-raw', String(resolvedTotal));
                    }
                });
            }

            const versionId = Number.isFinite(payload.versionId) ? payload.versionId : 0;
            const versionCount = Number.isFinite(payload.versionDownloads) ? payload.versionDownloads : null;
            const versionFormatted = payload.formattedVersionDownloads
                || (versionCount !== null ? formatNumber(versionCount) : '');

            if (versionId && versionFormatted) {
                const versionElements = document.querySelectorAll(`[data-version-downloads="${versionId}"]`);
                versionElements.forEach((element) => {
                    element.textContent = versionFormatted;
                    if (versionCount !== null) {
                        element.setAttribute('data-download-count-raw', String(versionCount));
                    }
                });
            }

            if (payload.lastDownloadedHuman) {
                const lastDownloadedElements = document.querySelectorAll('[data-last-downloaded]');
                lastDownloadedElements.forEach((element) => {
                    element.textContent = payload.lastDownloadedHuman;
                });
            }
        };

        const incrementDownloadDisplays = (versionId) => {
            const totalCounters = document.querySelectorAll('[data-download-count]');
            if (totalCounters.length) {
                const current = parseDownloadCount(totalCounters[0]);
                const nextValue = current + 1;
                updateDownloadElements(totalCounters, nextValue);
            }

            if (versionId) {
                const versionElements = document.querySelectorAll(`[data-version-downloads="${versionId}"]`);
                if (versionElements.length) {
                    const currentVersion = parseDownloadCount(versionElements[0]);
                    const nextVersion = currentVersion + 1;
                    updateDownloadElements(versionElements, nextVersion);
                }
            }
        };

        const sendDownloadIncrement = (versionId) => {
            if (!downloadEndpoint) {
                return Promise.resolve(null);
            }

            const headers = { ...restHeaders };
            headers['Content-Type'] = 'application/json';

            const body = versionId ? { versionId } : {};

            return fetch(downloadEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify(body),
                keepalive: true,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((payload) => {
                    applyDownloadPayload(payload);
                    return payload;
                })
                .catch(() => null);
        };

        const userState = (config.user && typeof config.user === 'object') ? config.user : {};
        const userIsLoggedIn = Boolean(userState.isLoggedIn);
        const userStateEndpoint = typeof restEndpoints.userState === 'string' ? restEndpoints.userState : '';

        if (config.postId) {
            const cookiePrefix = config.viewCookiePrefix || 'gta6mods_viewed_';
            const cookieName = cookiePrefix + String(config.postId);
            const viewCounterElements = document.querySelectorAll('[data-mod-view-count]');

            if (!hasCookie(cookieName) && restEndpoints.view) {
                const sendViewIncrement = () => {
                    fetch(restEndpoints.view, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { ...restHeaders },
                    })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('Request failed');
                            }
                            return response.json();
                        })
                        .then((data) => {
                            if (!data) {
                                return;
                            }
                            const formatted = data.formattedViews || data.views;
                            if (formatted && viewCounterElements.length) {
                                viewCounterElements.forEach((element) => {
                                    element.textContent = formatted;
                                });
                            }
                            setCookie(cookieName, '1', {
                                maxAge: Number(config.viewThrottle) || 3600,
                                secure: Boolean(config.isSecure),
                            });
                        })
                        .catch(() => {
                            // silently ignore errors
                        });
                };

                scheduleDeferred(sendViewIncrement, Number(config.viewDelay) || 1500);
            }
        }

        const galleryContainer = document.getElementById('single-gallery-container');
        let galleryLoadMoreButton = document.querySelector('[data-gallery-load-more]');
        let galleryLightbox = null;

        const baseLightboxPadding = { top: 0, bottom: 0, left: 0, right: 0 };
        const videoLightboxPadding = { top: 48, bottom: 128, left: 24, right: 24 };

        const computeLightboxPadding = (itemData) => {
            const source = itemData && itemData.gta6Type === 'video'
                ? videoLightboxPadding
                : baseLightboxPadding;

            return {
                top: source.top,
                bottom: source.bottom,
                left: source.left,
                right: source.right,
            };
        };

        const videoConfig = window.gta6modsVideoData || {};
        const videoStrings = (videoConfig && videoConfig.i18n) ? videoConfig.i18n : {};

        const galleryModTitle = galleryContainer && typeof galleryContainer.getAttribute === 'function'
            ? (galleryContainer.getAttribute('data-gallery-mod-title') || '').trim()
            : '';

        const getGalleryLinks = () => (
            galleryContainer
                ? Array.from(galleryContainer.querySelectorAll('a.gallery-item'))
                : []
        );

        const getGalleryThumbnailLinks = () => (
            galleryContainer
                ? Array.from(galleryContainer.querySelectorAll('a.gallery-item[data-gallery-role="thumbnail"]'))
                : []
        );

        const getFeaturedWrapper = () => (
            galleryContainer
                ? galleryContainer.querySelector('[data-gallery-featured-wrapper]')
                : null
        );

        const initializeFeaturedVideoPreview = () => {
            const wrapper = getFeaturedWrapper();
            if (!wrapper) {
                return;
            }

            if ((wrapper.getAttribute('data-gallery-featured-type') || '').toLowerCase() !== 'video') {
                return;
            }

            const stage = wrapper.querySelector('[data-featured-video-stage]');
            const trigger = wrapper.querySelector('[data-featured-video-trigger]');

            if (!stage || !trigger) {
                return;
            }

            if (trigger.getAttribute('data-player-bound') === '1') {
                return;
            }

            trigger.setAttribute('data-player-bound', '1');

            const mountPlayer = () => {
                if (stage.getAttribute('data-video-playing') === '1') {
                    return;
                }

                const youtubeId = trigger.getAttribute('data-youtube-id');
                if (!youtubeId) {
                    return;
                }

                stage.setAttribute('data-video-playing', '1');

                const videoTitle = trigger.getAttribute('data-video-title') || '';
                const playerTitleBase = getVideoText('youtubePlayerTitle', 'YouTube video player');
                const iframeTitle = videoTitle ? `${playerTitleBase}: ${videoTitle}` : playerTitleBase;

                const frameWrapper = document.createElement('div');
                frameWrapper.className = 'absolute inset-0';

                const iframe = document.createElement('iframe');
                iframe.className = 'h-full w-full rounded-md sm:rounded-lg';
                iframe.src = `https://www.youtube.com/embed/${encodeURIComponent(youtubeId)}?autoplay=1&rel=0&modestbranding=1&playsinline=1`;
                iframe.title = iframeTitle;
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                iframe.setAttribute('allowfullscreen', 'allowfullscreen');
                iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');

                frameWrapper.appendChild(iframe);

                stage.innerHTML = '';
                stage.appendChild(frameWrapper);

                wrapper.setAttribute('data-gallery-featured-playing', '1');
            };

            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                mountPlayer();
            });
        };

        const getVideoText = (key, fallback) => {
            if (videoStrings && Object.prototype.hasOwnProperty.call(videoStrings, key)) {
                const value = videoStrings[key];
                if (typeof value === 'string' && value.length) {
                    return value;
                }
            }

            return fallback;
        };

        const buildVideoSlideHtml = (videoData = {}) => {
            const youtubeId = escapeHTML(videoData.youtubeId || '');
            const addedBy = escapeHTML(videoData.addedBy || '');
            const profileUrl = escapeHTML(resolveUrl(videoData.profileUrl || '#'));
            const videoId = escapeHTML(videoData.videoId || '');
            const videoTitle = escapeHTML(videoData.videoTitle || '');
            const isReported = Boolean(videoData.isReported);
            const isFeatured = Boolean(videoData.isFeatured);
            const canManage = Boolean(videoData.canManage);
            const canFeature = Boolean(videoData.canFeature);
            const reportText = escapeHTML(getVideoText(isReported ? 'reportAlready' : 'reportVideo', isReported ? 'Reported' : 'Report'));
            const featureText = escapeHTML(getVideoText(isFeatured ? 'featureActive' : 'featureVideo', isFeatured ? 'Featured' : 'Feature this video'));
            const addedByLabel = escapeHTML(getVideoText('addedBy', 'Added by'));
            const deleteLabel = escapeHTML(getVideoText('deleteVideo', 'Delete'));

            const featureButton = canManage && canFeature
                ? `
                    <button
                        type="button"
                        class="pswp-video-action${isFeatured ? ' is-active' : ''}"
                        data-video-feature="${videoId}"
                        data-video-id="${videoId}"
                        data-featured="${isFeatured ? '1' : '0'}"
                        aria-pressed="${isFeatured ? 'true' : 'false'}"
                    >
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        <span data-feature-label>${featureText}</span>
                    </button>
                `
                : '';

            const ownerActions = canManage
                ? `
                    <div class="pswp-video-owner-actions">
                        ${featureButton}
                        <button
                            type="button"
                            class="pswp-video-action pswp-video-action-danger"
                            data-video-delete="${videoId}"
                            data-video-id="${videoId}"
                        >
                            <i class="fa-solid fa-ban" aria-hidden="true"></i>
                            ${deleteLabel}
                        </button>
                    </div>
                `
                : '';

            return `
                <div class="pswp-video-content text-white">
                    <div class="pswp-video-player">
                        <div class="pswp-video-frame" data-youtube-id="${youtubeId}" data-video-title="${videoTitle}"></div>
                    </div>
                    <div class="pswp-video-meta">
                        ${videoTitle ? `<div class="pswp-video-title">${videoTitle}</div>` : ''}
                        <div class="pswp-video-meta-footer">
                            <div class="pswp-video-author">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <span>
                                    ${addedByLabel}
                                    <a href="${profileUrl}">${addedBy}</a>
                                </span>
                            </div>
                            <div class="pswp-video-actions">
                                <button
                                    type="button"
                                    class="pswp-video-action${isReported ? ' is-disabled' : ''}"
                                    data-video-report="${videoId}"
                                    data-video-id="${videoId}"
                                    data-reported="${isReported ? '1' : '0'}"
                                    aria-disabled="${isReported ? 'true' : 'false'}"
                                >
                                    <i class="fa-solid fa-flag" aria-hidden="true"></i>
                                    <span data-report-label>${reportText}</span>
                                </button>
                            </div>
                        </div>
                        ${ownerActions}
                    </div>
                </div>
            `;
        };

        const refreshGalleryIndexes = () => {
            const links = getGalleryLinks();
            links.forEach((link, index) => {
                link.setAttribute('data-gallery-index', String(index));
            });
        };

        const buildGallerySlides = () => getGalleryLinks().map((link) => {
            const itemType = link.getAttribute('data-gallery-type') || 'image';

            if (itemType === 'video') {
                const dataset = link.dataset || {};
                const videoData = {
                    youtubeId: dataset.youtubeId || link.getAttribute('data-youtube-id') || '',
                    addedBy: dataset.addedBy || link.getAttribute('data-added-by') || '',
                    profileUrl: dataset.profileUrl || link.getAttribute('data-profile-url') || '',
                    videoId: dataset.videoId || link.getAttribute('data-video-id') || '',
                    videoTitle: dataset.videoTitle || link.getAttribute('data-video-title') || '',
                    isReported: dataset.isReported === '1' || link.getAttribute('data-is-reported') === '1',
                    isFeatured: dataset.isFeatured === '1' || link.getAttribute('data-is-featured') === '1',
                    canManage: dataset.canManage === '1' || link.getAttribute('data-can-manage') === '1',
                    canFeature: dataset.canFeature === '1' || link.getAttribute('data-can-feature') === '1',
                    reportCount: dataset.reportCount || link.getAttribute('data-report-count') || '0',
                };

                return {
                    element: link,
                    data: {
                        html: buildVideoSlideHtml(videoData),
                        gta6Type: 'video',
                        video: videoData,
                    },
                };
            }

            const width = Number.parseInt(link.getAttribute('data-pswp-width'), 10) || 1920;
            const height = Number.parseInt(link.getAttribute('data-pswp-height'), 10) || 1080;
            const img = link.querySelector('img');
            const alt = img ? img.getAttribute('alt') || '' : '';
            const src = resolveUrl(link.getAttribute('href') || (img ? img.getAttribute('src') : ''));

            return {
                element: link,
                data: {
                    src,
                    width,
                    height,
                    alt,
                    gta6Type: 'image',
                },
            };
        });

        const parseGalleryIndex = (link) => {
            const indexAttr = link ? link.getAttribute('data-gallery-index') : null;
            if (!indexAttr) {
                const links = getGalleryLinks();
                const fallbackIndex = links.indexOf(link);
                return fallbackIndex >= 0 ? fallbackIndex : null;
            }

            const parsed = Number(indexAttr);
            if (!Number.isNaN(parsed)) {
                return parsed;
            }

            const links = getGalleryLinks();
            const fallbackIndex = links.indexOf(link);
            return fallbackIndex >= 0 ? fallbackIndex : null;
        };

        const openLightboxAt = (index) => {
            if (typeof PhotoSwipe !== 'function') {
                return false;
            }

            refreshGalleryIndexes();
            const slides = buildGallerySlides();
            if (!slides.length) {
                return false;
            }

            const maxIndex = slides.length - 1;
            const requestedIndex = typeof index === 'number' && !Number.isNaN(index) ? index : 0;
            const targetIndex = Math.min(Math.max(0, requestedIndex), maxIndex);

            const initialSlide = slides[targetIndex] ? slides[targetIndex].data : null;

            const pswp = new PhotoSwipe({
                dataSource: slides.map((item) => item.data),
                index: targetIndex,
                bgOpacity: 0.94,
                padding: computeLightboxPadding(initialSlide),
                paddingFn: (viewportSize, itemData) => computeLightboxPadding(itemData),
                showHideAnimationType: 'fade',
                wheelToZoom: false,
            });

            pswp.on('uiRegister', () => {
                if (!pswp || !pswp.ui || typeof pswp.ui.registerElement !== 'function') {
                    return;
                }

                pswp.ui.registerElement({
                    name: 'gta6modsCaption',
                    order: 9,
                    isButton: false,
                    appendTo: 'root',
                    className: 'pswp__gta6mods-caption',
                    onInit: (element, pswpInstance) => {
                        if (!element || !pswpInstance) {
                            return;
                        }

                        const updateCaption = () => {
                            if (!element) {
                                return;
                            }

                            const currentSlide = pswpInstance.currSlide;
                            const isImageSlide = currentSlide
                                && currentSlide.data
                                && currentSlide.data.gta6Type !== 'video';

                            if (isImageSlide && galleryModTitle) {
                                element.textContent = galleryModTitle;
                                element.removeAttribute('hidden');
                            } else {
                                element.textContent = '';
                                element.setAttribute('hidden', 'hidden');
                            }
                        };

                        element.setAttribute('hidden', 'hidden');

                        pswpInstance.on('afterInit', updateCaption);
                        pswpInstance.on('change', updateCaption);
                        pswpInstance.on('contentActivate', updateCaption);
                    },
                });
            });

            const updateRootVideoClass = (isVideo) => {
                if (pswp.element) {
                    if (isVideo) {
                        pswp.element.classList.add('pswp--video');
                    } else {
                        pswp.element.classList.remove('pswp--video');
                    }
                }
            };

            pswp.on('contentActivate', (eventData) => {
                const content = eventData ? eventData.content : null;
                const data = content && content.data ? content.data : {};
                const isVideo = data.gta6Type === 'video';
                const nextPadding = computeLightboxPadding(data);
                if (pswp.options) {
                    pswp.options.padding = nextPadding;
                }
                updateRootVideoClass(isVideo);

                if (isVideo && content && content.element && content.element.querySelector) {
                    const frame = content.element.querySelector('.pswp-video-frame');
                    if (frame && !frame.querySelector('iframe')) {
                        const videoInfo = data.video || {};
                        const youtubeId = videoInfo.youtubeId || '';
                        const title = videoInfo.videoTitle || '';
                        if (youtubeId) {
                            const baseTitle = getVideoText('youtubePlayerTitle', 'YouTube video player');
                            const computedTitle = title ? `${baseTitle}: ${title}` : baseTitle;
                            frame.innerHTML = `
                                <iframe
                                    src="https://www.youtube.com/embed/${encodeURIComponent(youtubeId)}?autoplay=1&rel=0"
                                    title="${escapeHTML(computedTitle)}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            `;
                        }
                    }
                }
            });

            pswp.on('contentDeactivate', (eventData) => {
                const content = eventData ? eventData.content : null;
                if (content && content.element) {
                    const frame = content.element.querySelector('.pswp-video-frame');
                    if (frame) {
                        frame.innerHTML = '';
                    }
                }
            });

            pswp.on('close', () => {
                updateRootVideoClass(false);
                galleryLightbox = null;
            });

            pswp.init();
            galleryLightbox = pswp;
            window.gta6ModsGalleryLightbox = pswp;
            return true;
        };

        if (galleryContainer) {
            galleryContainer.addEventListener('click', (event) => {
                const targetLink = event.target instanceof Element
                    ? event.target.closest('a.gallery-item')
                    : null;

                if (!targetLink || !galleryContainer.contains(targetLink)) {
                    return;
                }

                const targetIndex = parseGalleryIndex(targetLink);
                if (openLightboxAt(targetIndex)) {
                    event.preventDefault();
                }
            });
        }

        refreshGalleryIndexes();

        const likeButtons = Array.from(document.querySelectorAll('[data-like-button]'));
        const likeTotals = Array.from(document.querySelectorAll('.mod-like-total'));
        const bookmarkButtons = Array.from(document.querySelectorAll('[data-bookmark-button]'));
        const commentsRoot = document.querySelector('[data-mod-comments-root]');
        const bookmarkLabels = (config.bookmarks && config.bookmarks.labels) ? config.bookmarks.labels : {};
        const commentTabLink = document.querySelector('[data-tab-key="comments"][data-comment-label-template]');
        const commentTabLabel = commentTabLink ? commentTabLink.querySelector('[data-comment-tab-label]') : null;
        const commentTabTemplate = commentTabLink ? commentTabLink.getAttribute('data-comment-label-template') : '';
        const commentStrings = (config.comments && config.comments.strings) ? config.comments.strings : {};

        let currentLikeState = Boolean(config.likes && config.likes.liked);
        let currentLikeCount = Number(config.likes && config.likes.count) || 0;
        let currentBookmarkState = Boolean(config.bookmarks && config.bookmarks.isBookmarked);
        let currentCommentsPage = 1;
        let currentCommentCount = Number(config.comments && config.comments.count) || 0;
        let currentCommentOrder = 'best';
        let latestSinglePageData = null;
        let latestUserState = null;
        let commentsLoaded = false;
        let commentsLoading = false;

        const formatCount = (value) => {
            const parsed = Number(value);
            if (Number.isFinite(parsed)) {
                return parsed.toLocaleString();
            }
            return '0';
        };

        const updateLikeUI = () => {
            likeButtons.forEach((button) => {
                const activeClass = button.getAttribute('data-like-active-class') || '';
                const inactiveClass = button.getAttribute('data-like-inactive-class') || '';
                button.classList.remove(activeClass);
                button.classList.remove(inactiveClass);
                button.classList.add(currentLikeState ? activeClass : inactiveClass);
                button.setAttribute('aria-pressed', currentLikeState ? 'true' : 'false');
            });

            likeTotals.forEach((element) => {
                element.textContent = formatCount(currentLikeCount);
            });
        };

        const updateBookmarkUI = () => {
            bookmarkButtons.forEach((button) => {
                const activeClass = button.getAttribute('data-bookmark-active-class') || '';
                const inactiveClass = button.getAttribute('data-bookmark-inactive-class') || '';
                button.classList.remove(activeClass);
                button.classList.remove(inactiveClass);
                button.classList.add(currentBookmarkState ? activeClass : inactiveClass);
                button.setAttribute('aria-pressed', currentBookmarkState ? 'true' : 'false');

                const label = button.querySelector('[data-bookmark-label]');
                if (label) {
                    label.textContent = currentBookmarkState
                        ? (bookmarkLabels.added || 'Bookmarked')
                        : (bookmarkLabels.add || 'Bookmark');
                }
            });
        };

        const setCommentTabLabel = (count) => {
            if (!commentTabLabel || !commentTabTemplate) {
                return;
            }

            const safeCount = Math.max(0, Number(count) || 0);
            commentTabLabel.textContent = commentTabTemplate.replace('%s', formatCount(safeCount));
        };

        const updateMetricsDisplay = (metrics = {}) => {
            if (!metrics || typeof metrics !== 'object') {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(metrics, 'views')) {
                const formattedViews = formatNumber(metrics.views);
                const viewCounters = document.querySelectorAll('[data-mod-view-count]');
                viewCounters.forEach((element) => {
                    element.textContent = formattedViews;
                });
            }

            if (Object.prototype.hasOwnProperty.call(metrics, 'downloads')) {
                const formattedDownloads = formatNumber(metrics.downloads);
                const downloadCounters = document.querySelectorAll('[data-download-count]');
                downloadCounters.forEach((element) => {
                    element.textContent = formattedDownloads;
                });
            }

            if (Object.prototype.hasOwnProperty.call(metrics, 'likes')) {
                const parsedLikes = Number(metrics.likes);
                if (Number.isFinite(parsedLikes)) {
                    currentLikeCount = parsedLikes;
                    updateLikeUI();
                }
            }
        };

        const fetchRelatedModsFallback = () => {
            if (!restEndpoints.related) {
                return;
            }

            fetch(restEndpoints.related)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    if (Array.isArray(data)) {
                        renderRelatedMods(data);
                    }
                })
                .catch((error) => {
                    console.error('Error loading related mods fallback:', error);
                    renderRelatedMods([]);
                });
        };

        const fetchSinglePageData = async (options = {}) => {
            if (!restEndpoints.single) {
                return null;
            }

            const params = {};
            if (options.includeComments) {
                params.include_comments = '1';
            }
            if (options.orderby) {
                params.orderby = options.orderby;
            }
            if (options.perPage) {
                params.per_page = options.perPage;
            }

            const requestUrl = buildRestUrl(restEndpoints.single, params);
            const response = await fetch(requestUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { ...restHeaders },
            });

            if (response.status === 304) {
                return null;
            }

            if (!response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        };

        const fetchUserState = async () => {
            if (!userIsLoggedIn || !userStateEndpoint) {
                return null;
            }

            const response = await fetch(userStateEndpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { ...restHeaders },
            });

            if (response.status === 204) {
                return null;
            }

            if (!response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        };

        const applyUserState = (state) => {
            if (!state || typeof state !== 'object') {
                return;
            }

            latestUserState = state;

            if (Object.prototype.hasOwnProperty.call(state, 'liked')) {
                currentLikeState = Boolean(state.liked);
            }

            if (Object.prototype.hasOwnProperty.call(state, 'bookmarked')) {
                currentBookmarkState = Boolean(state.bookmarked);
            }

            if (Object.prototype.hasOwnProperty.call(state, 'rating')) {
                const ratingValue = Number(state.rating) || 0;
                document.querySelectorAll('.mod-rating-container').forEach((container) => {
                    container.dataset.userRating = String(ratingValue);
                });
            }

            if (Object.prototype.hasOwnProperty.call(state, 'is_logged_in') && state.is_logged_in) {
                document.querySelectorAll('.mod-rating-container').forEach((container) => {
                    container.dataset.isLoggedIn = 'true';
                });
            }

            updateLikeUI();
            updateBookmarkUI();
        };

        const applySinglePageData = (data) => {
            if (!data || typeof data !== 'object') {
                return;
            }

            latestSinglePageData = data;

            if (data.mod && data.mod.metrics) {
                updateMetricsDisplay(data.mod.metrics);
            }

            if (Array.isArray(data.related_mods)) {
                renderRelatedMods(data.related_mods);
            }

            if (data.comments && typeof data.comments.count !== 'undefined') {
                currentCommentCount = Number(data.comments.count) || 0;
                setCommentTabLabel(currentCommentCount);

                if (typeof data.comments.orderby === 'string' && data.comments.orderby) {
                    currentCommentOrder = data.comments.orderby;
                }

                if (typeof data.comments.page !== 'undefined') {
                    const parsedPage = Number(data.comments.page);
                    if (Number.isFinite(parsedPage) && parsedPage > 0) {
                        currentCommentsPage = parsedPage;
                    }
                }
            }

            updateLikeUI();
            updateBookmarkUI();
        };

        const showCommentsLoader = () => {
            if (!commentsRoot) {
                return;
            }
            const loadingLabel = commentStrings.loading || 'Loading…';
            commentsRoot.innerHTML = `<div class="py-12 text-center text-sm text-gray-500">${loadingLabel}</div>`;
        };

        const showCommentsError = () => {
            if (!commentsRoot) {
                return;
            }
            const message = commentStrings.error || 'Something went wrong. Please try again.';
            commentsRoot.innerHTML = `<div class="py-12 text-center text-sm text-red-500">${message}</div>`;
        };

        const bindCommentPagination = (rootElement) => {
            if (!rootElement) {
                return;
            }

            const links = rootElement.querySelectorAll('[data-page]');
            links.forEach((link) => {
                if (!(link instanceof HTMLElement)) {
                    return;
                }

                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const targetPage = Number.parseInt(link.getAttribute('data-page') || '', 10);
                    if (Number.isFinite(targetPage) && targetPage > 0) {
                        loadModComments(targetPage);
                    }
                });
            });
        };

        const renderCommentsPayload = (payload) => {
            if (!commentsRoot) {
                return false;
            }

            if (!payload || typeof payload.html !== 'string') {
                showCommentsError();
                return false;
            }

            commentsRoot.innerHTML = payload.html;
            bindCommentPagination(commentsRoot);

            if (typeof payload.count !== 'undefined') {
                currentCommentCount = Number(payload.count) || 0;
            }

            if (typeof payload.page !== 'undefined') {
                const parsedPage = Number(payload.page);
                if (Number.isFinite(parsedPage) && parsedPage > 0) {
                    currentCommentsPage = parsedPage;
                }
            }

            if (typeof payload.orderby === 'string' && payload.orderby) {
                currentCommentOrder = payload.orderby;
            }

            setCommentTabLabel(currentCommentCount);

            const sortDropdown = commentsRoot.querySelector('#gta6-comment-sort');
            if (sortDropdown && typeof currentCommentOrder === 'string') {
                sortDropdown.value = currentCommentOrder;
                if (!sortDropdown.dataset.singleModSortBound) {
                    sortDropdown.addEventListener('change', () => {
                        const selected = sortDropdown.value;
                        if (selected) {
                            currentCommentOrder = selected;
                        }
                    });
                    sortDropdown.dataset.singleModSortBound = '1';
                }
            }

            const commentsContainer = commentsRoot.querySelector('#gta6-comments');
            if (commentsContainer && window.GTAModsComments && typeof window.GTAModsComments.init === 'function') {
                window.GTAModsComments.init(commentsContainer);
                if (typeof window.GTAModsComments.scrollToHash === 'function') {
                    window.setTimeout(() => {
                        window.GTAModsComments.scrollToHash();
                    }, 120);
                }
            }

            return true;
        };

        const buildJsonOptions = (method = 'POST', payload = null) => {
            const options = {
                method,
                credentials: 'same-origin',
                headers: { ...restHeaders },
            };

            if (payload !== null) {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(payload);
            }

            return options;
        };

        const handleLikeToggle = async (event) => {
            event.preventDefault();

            if (!restEndpoints.like || !userIsLoggedIn) {
                return;
            }

            const button = event.currentTarget;
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.disabled = true;

            try {
                const response = await fetch(restEndpoints.like, buildJsonOptions('POST', {}));
                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();
                if (!data || typeof data.liked === 'undefined') {
                    throw new Error('Invalid response');
                }

                currentLikeState = Boolean(data.liked);
                if (typeof data.count !== 'undefined') {
                    currentLikeCount = Number(data.count) || 0;
                }
                updateLikeUI();
            } catch (error) {
                console.error(error);
            } finally {
                button.disabled = false;
            }
        };

        const handleBookmarkToggle = async (event) => {
            event.preventDefault();

            if (!restEndpoints.bookmark || !userIsLoggedIn) {
                return;
            }

            const button = event.currentTarget;
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.disabled = true;

            try {
                const response = await fetch(restEndpoints.bookmark, buildJsonOptions('POST', {}));
                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();
                if (!data || typeof data.is_bookmarked === 'undefined') {
                    throw new Error('Invalid response');
                }

                currentBookmarkState = Boolean(data.is_bookmarked);
                updateBookmarkUI();
            } catch (error) {
                console.error(error);
                const errorLabel = bookmarkLabels.error || 'We could not update your bookmark. Please try again.';
                window.alert(errorLabel);
            } finally {
                button.disabled = false;
            }
        };

        const loadModComments = async (page = 1, options = {}) => {
            if (!commentsRoot || !restEndpoints.comments) {
                return false;
            }

            const { showLoader = true, orderby = currentCommentOrder } = options;

            currentCommentsPage = Math.max(1, Number.parseInt(page, 10) || 1);

            if (showLoader) {
                showCommentsLoader();
            }

            try {
                const requestUrl = buildRestUrl(restEndpoints.comments, {
                    page: currentCommentsPage,
                    orderby,
                });

                const response = await fetch(requestUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { ...restHeaders },
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const payload = await response.json();

                if (!payload || typeof payload.html !== 'string') {
                    throw new Error('Invalid response');
                }

                return renderCommentsPayload(payload);
            } catch (error) {
                console.error(error);
                showCommentsError();
            }

            return false;
        };

        const loadInitialComments = async () => {
            if (!commentsRoot) {
                return false;
            }

            let success = false;

            if (restEndpoints.single) {
                try {
                    const data = await fetchSinglePageData({
                        includeComments: true,
                        orderby: currentCommentOrder,
                    });

                    if (data) {
                        applySinglePageData(data);

                        if (data.comments && typeof data.comments.html === 'string') {
                            success = renderCommentsPayload(data.comments) || success;
                        }
                    }
                } catch (error) {
                    console.error('Error loading single page comments:', error);
                }
            }

            if (!success) {
                success = await loadModComments(1, { showLoader: false });
            }

            if (!success) {
                showCommentsError();
            }

            return success;
        };

        const ensureCommentsLoaded = () => {
            if (!commentsRoot) {
                return Promise.resolve(false);
            }

            if (commentsLoaded) {
                if (window.GTAModsComments && typeof window.GTAModsComments.scrollToHash === 'function') {
                    window.GTAModsComments.scrollToHash();
                }
                return Promise.resolve(true);
            }

            if (commentsLoading) {
                return Promise.resolve(false);
            }

            commentsLoading = true;
            showCommentsLoader();

            return loadInitialComments()
                .then((success) => {
                    commentsLoaded = success;
                    if (success && window.GTAModsComments && typeof window.GTAModsComments.scrollToHash === 'function') {
                        window.setTimeout(() => {
                            window.GTAModsComments.scrollToHash();
                        }, 140);
                    }
                    return success;
                })
                .finally(() => {
                    commentsLoading = false;
                });
        };

        const loadInitialSingleData = async () => {
            if (!restEndpoints.single) {
                fetchRelatedModsFallback();
                return;
            }

            try {
                const requests = [fetchSinglePageData({ includeComments: false })];
                if (userIsLoggedIn && userStateEndpoint) {
                    requests.push(fetchUserState());
                }

                const results = await Promise.allSettled(requests);
                const publicResult = results[0];
                const userResult = results.length > 1 ? results[1] : null;

                if (publicResult && publicResult.status === 'rejected') {
                    console.error('Error loading single page data:', publicResult.reason);
                }

                if (userResult && userResult.status === 'rejected') {
                    console.error('Error loading user state:', userResult.reason);
                }

                const resolvedPublicData = publicResult && publicResult.status === 'fulfilled'
                    ? publicResult.value
                    : null;

                if (resolvedPublicData) {
                    applySinglePageData(resolvedPublicData);
                } else if (latestSinglePageData) {
                    applySinglePageData(latestSinglePageData);
                }

                if (userResult && userResult.status === 'fulfilled' && userResult.value) {
                    applyUserState(userResult.value);
                }

                const relatedMods = resolvedPublicData && Array.isArray(resolvedPublicData.related_mods)
                    ? resolvedPublicData.related_mods
                    : (latestSinglePageData && Array.isArray(latestSinglePageData.related_mods)
                        ? latestSinglePageData.related_mods
                        : null);

                if (!Array.isArray(relatedMods)) {
                    fetchRelatedModsFallback();
                }
            } catch (error) {
                console.error('Error loading single page data:', error);
                fetchRelatedModsFallback();

                if (userIsLoggedIn && userStateEndpoint) {
                    fetchUserState()
                        .then((state) => {
                            if (state) {
                                applyUserState(state);
                            }
                        })
                        .catch((userError) => {
                            console.error('Error loading user state:', userError);
                        });
                }
            }
        };

        if (likeButtons.length && userIsLoggedIn) {
            likeButtons.forEach((button) => {
                button.addEventListener('click', handleLikeToggle);
            });
            updateLikeUI();
        }

        if (bookmarkButtons.length && userIsLoggedIn) {
            bookmarkButtons.forEach((button) => {
                button.addEventListener('click', handleBookmarkToggle);
            });
            updateBookmarkUI();
        } else if (bookmarkButtons.length) {
            updateBookmarkUI();
        }

        setCommentTabLabel(currentCommentCount);

        const isLoggedIn = Boolean(config.isLoggedIn || userIsLoggedIn);
        if (isLoggedIn) {
            document.querySelectorAll('.mod-rating-container').forEach((container) => {
                container.dataset.isLoggedIn = 'true';
            });
        }

        loadInitialSingleData();

        if (commentsRoot) {
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            ensureCommentsLoaded();
                            obs.disconnect();
                        }
                    });
                }, {
                    root: null,
                    rootMargin: '0px 0px 200px 0px',
                    threshold: 0.1,
                });

                observer.observe(commentsRoot);
            } else {
                scheduleDeferred(() => {
                    ensureCommentsLoaded();
                }, 1500);
            }
        }

        // MODIFICATION START: Responsive gallery thumbnail logic
        const galleryThumbnailsContainer = document.getElementById('single-gallery-thumbnails');

        if (galleryThumbnailsContainer) {
            const mobileLimit = 3;
            const desktopLimit = 5;

            const updateGalleryLayout = () => {
                const thumbnails = getGalleryThumbnailLinks();
                const currentLoadMoreButton = document.querySelector('[data-gallery-load-more]');

                if (!thumbnails.length) {
                    if (currentLoadMoreButton && currentLoadMoreButton.parentElement) {
                        currentLoadMoreButton.parentElement.style.display = 'none';
                    }
                    return;
                }

                if (!currentLoadMoreButton || !document.body.contains(currentLoadMoreButton)) {
                    thumbnails.forEach((thumb) => {
                        thumb.classList.remove('hidden');
                    });
                    return;
                }

                const isMobile = window.innerWidth < 640;
                const limit = isMobile ? mobileLimit : desktopLimit;

                thumbnails.forEach((thumb) => {
                    if (thumb.classList.contains('extra-thumbnail')) {
                        return;
                    }

                    const indexValue = thumb.getAttribute('data-gallery-index');
                    const index = Number.parseInt(indexValue, 10);

                    if (Number.isFinite(index) && index > limit) {
                        thumb.classList.add('hidden');
                    } else {
                        thumb.classList.remove('hidden');
                    }
                });

                if (currentLoadMoreButton) {
                    const template = currentLoadMoreButton.dataset.loadMoreTextTemplate || 'Load more images and videos (%d)';

                    const hiddenCount = thumbnails.reduce((count, thumb) => (
                        thumb.classList.contains('hidden') ? count + 1 : count
                    ), 0);

                    if (hiddenCount > 0) {
                        if (currentLoadMoreButton.parentElement) {
                            currentLoadMoreButton.parentElement.style.display = 'block';
                        }

                        let textNodeUpdated = false;
                        const loadMoreLabel = template.replace('%d', hiddenCount);

                        currentLoadMoreButton.setAttribute('title', loadMoreLabel);
                        currentLoadMoreButton.setAttribute('aria-label', loadMoreLabel);

                        for (const node of currentLoadMoreButton.childNodes) {
                            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim().length > 0) {
                                node.textContent = ` ${loadMoreLabel} `;
                                textNodeUpdated = true;
                                break;
                            }
                        }

                        if (!textNodeUpdated) {
                            const iconHTML = currentLoadMoreButton.querySelector('i')?.outerHTML || '';
                            currentLoadMoreButton.innerHTML = `${iconHTML} ${loadMoreLabel}`;
                        }
                    } else if (currentLoadMoreButton.parentElement) {
                        currentLoadMoreButton.parentElement.style.display = 'none';
                    }
                }
            };

            if (galleryLoadMoreButton) {
                const newLoadMoreButton = galleryLoadMoreButton.cloneNode(true);
                galleryLoadMoreButton.parentNode.replaceChild(newLoadMoreButton, galleryLoadMoreButton);
                galleryLoadMoreButton = newLoadMoreButton;

                galleryLoadMoreButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    getGalleryThumbnailLinks().forEach((thumb) => {
                        thumb.classList.remove('hidden');
                    });
                    if (galleryLoadMoreButton.parentElement) {
                        galleryLoadMoreButton.parentElement.remove();
                    }
                    document.dispatchEvent(new CustomEvent('gta6mods:gallery:updated'));
                });
            }

            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(updateGalleryLayout, 150);
            });

            document.addEventListener('gta6mods:gallery:updated', () => {
                refreshGalleryIndexes();
                updateGalleryLayout();
                initializeFeaturedVideoPreview();
            });

            document.addEventListener('gta6mods:gallery:featured-change', (event) => {
                const wrapper = getFeaturedWrapper();
                if (!wrapper) {
                    return;
                }

                const detail = event && event.detail ? event.detail : {};
                if (detail.type === 'video' && detail.videoId) {
                    wrapper.setAttribute('data-gallery-featured-type', 'video');
                    wrapper.setAttribute('data-gallery-featured-video-id', String(detail.videoId));
                } else {
                    wrapper.setAttribute('data-gallery-featured-type', 'image');
                    wrapper.removeAttribute('data-gallery-featured-video-id');
                }

                initializeFeaturedVideoPreview();
            });

            updateGalleryLayout();
            initializeFeaturedVideoPreview();
        }
        // MODIFICATION END

        const shareModal = document.getElementById('gta6mods-share-modal');
        const shareOpenButton = document.querySelector('[data-share-modal-target]');
        const shareCloseButtons = document.querySelectorAll('[data-share-modal-close]');

        const closeShareModal = () => {
            if (!shareModal) {
                return;
            }

            shareModal.classList.add('hidden');
            shareModal.setAttribute('aria-hidden', 'true');
            document.documentElement.classList.remove('overflow-hidden');
            document.body.classList.remove('overflow-hidden');
        };

        if (shareModal && shareOpenButton) {
            shareOpenButton.addEventListener('click', (event) => {
                event.preventDefault();
                shareModal.classList.remove('hidden');
                shareModal.setAttribute('aria-hidden', 'false');
            });
        }

        if (shareModal) {
            shareModal.addEventListener('click', (event) => {
                if (event.target === shareModal) {
                    closeShareModal();
                }
            });
        }

        if (shareCloseButtons.length) {
            shareCloseButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    closeShareModal();
                });
            });
        }

        const tabButtons = Array.from(document.querySelectorAll('[data-tab-key][data-tab-target]'));
        if (tabButtons.length) {
            const tabSections = new Map();
            const tabUrls = new Map();
            tabButtons.forEach((button) => {
                const key = button.getAttribute('data-tab-key');
                const targetId = button.getAttribute('data-tab-target');
                if (!key || !targetId || tabSections.has(key)) {
                    return;
                }

                const section = document.getElementById(targetId);
                if (section) {
                    tabSections.set(key, section);
                }

                const buttonUrl = button.getAttribute('href') || button.href;
                if (buttonUrl) {
                    tabUrls.set(key, button.href || buttonUrl);
                }
            });

            const ACTIVE_BUTTON_CLASSES = ['active', 'text-pink-600'];
            const INACTIVE_BUTTON_CLASSES = ['text-gray-600', 'hover:text-pink-600'];
            const defaultTabKey = tabButtons.find((button) => button.getAttribute('data-tab-key') === 'description')?.getAttribute('data-tab-key') || tabButtons[0].getAttribute('data-tab-key');
            let currentTabKey = null;

            const normalizeTabKey = (key) => {
                const lowerKey = typeof key === 'string' ? key.toLowerCase() : '';
                if (lowerKey && tabSections.has(lowerKey)) {
                    return lowerKey;
                }
                if (defaultTabKey && tabSections.has(defaultTabKey)) {
                    return defaultTabKey;
                }
                return tabSections.keys().next().value || null;
            };

            const setButtonActiveState = (button, isActive) => {
                if (!button) {
                    return;
                }

                if (isActive) {
                    button.classList.add(...ACTIVE_BUTTON_CLASSES);
                    button.classList.remove(...INACTIVE_BUTTON_CLASSES);
                    button.setAttribute('aria-current', 'page');
                } else {
                    button.classList.remove(...ACTIVE_BUTTON_CLASSES);
                    button.classList.add(...INACTIVE_BUTTON_CLASSES);
                    button.removeAttribute('aria-current');
                }
            };

            const setSectionActiveState = (section, isActive) => {
                if (!section) {
                    return;
                }

                if (isActive) {
                    section.classList.remove('hidden');
                    section.removeAttribute('aria-hidden');
                } else {
                    section.classList.add('hidden');
                    section.setAttribute('aria-hidden', 'true');
                }
            };

            const updateHistoryState = (tabKey, replace) => {
                if (!window.history || typeof window.history.pushState !== 'function') {
                    return;
                }

                let targetUrl = null;
                if (tabKey && tabUrls.has(tabKey)) {
                    targetUrl = tabUrls.get(tabKey);
                } else if (!tabKey && defaultTabKey && tabUrls.has(defaultTabKey)) {
                    targetUrl = tabUrls.get(defaultTabKey);
                }

                if (!targetUrl) {
                    const url = new URL(window.location.href);
                    if (tabKey && tabKey !== defaultTabKey) {
                        url.searchParams.set('tab', tabKey);
                    } else {
                        url.searchParams.delete('tab');
                    }
                    targetUrl = url.toString();
                }

                const activeHash = window.location.hash;
                if (activeHash && activeHash.indexOf('#comment-') === 0) {
                    try {
                        const parsed = new URL(targetUrl, window.location.origin);
                        if (!parsed.hash || parsed.hash.indexOf('#comment-') !== 0) {
                            parsed.hash = activeHash;
                        }
                        targetUrl = parsed.toString();
                    } catch (error) {
                        // Ignore malformed URLs
                    }
                }

                if (!targetUrl || targetUrl === window.location.href) {
                    return;
                }

                const method = replace ? 'replaceState' : 'pushState';
                window.history[method]({}, '', targetUrl);
            };

            const resolveTabFromUrl = (inputUrl) => {
                try {
                    const url = new URL(inputUrl, window.location.origin);
                    const queryTab = url.searchParams.get('tab');
                    if (queryTab) {
                        return queryTab.toLowerCase();
                    }

                    const hashValue = (url.hash || '').toLowerCase();
                    if (hashValue.indexOf('#comment-') === 0) {
                        return 'comments';
                    }

                    const segments = url.pathname
                        .toLowerCase()
                        .split('/')
                        .filter((segment) => segment.length > 0);

                    if (segments.length > 0) {
                        const candidate = segments[segments.length - 1];
                        if (tabSections.has(candidate)) {
                            return candidate;
                        }
                    }
                } catch (error) {
                    // Ignore malformed URLs
                }

                return null;
            };

            const updateTabState = (nextKey, options = {}) => {
                const { skipHistory = false, replaceHistory = false, force = false } = options;
                const normalizedKey = normalizeTabKey(nextKey);
                if (!normalizedKey) {
                    return;
                }

                if (!force && normalizedKey === currentTabKey) {
                    return;
                }

                tabButtons.forEach((button) => {
                    const buttonKey = button.getAttribute('data-tab-key');
                    const isActive = buttonKey === normalizedKey;
                    setButtonActiveState(button, isActive);
                });

                tabSections.forEach((section, key) => {
                    const isActive = key === normalizedKey;
                    setSectionActiveState(section, isActive);
                });

                currentTabKey = normalizedKey;

                if (currentTabKey === 'comments') {
                    ensureCommentsLoaded();
                }

                if (!skipHistory) {
                    updateHistoryState(normalizedKey, replaceHistory);
                }
            };

            const urlInitialKey = resolveTabFromUrl(window.location.href);
            const initialButton = tabButtons.find((button) => button.getAttribute('aria-current') === 'page');
            const fallbackInitial = initialButton ? initialButton.getAttribute('data-tab-key') : defaultTabKey;
            const initialKey = urlInitialKey || fallbackInitial;
            updateTabState(initialKey, { replaceHistory: true, force: true });

            tabButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    if (event.defaultPrevented) {
                        return;
                    }

                    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                        return;
                    }

                    const targetKey = button.getAttribute('data-tab-key');
                    if (!targetKey) {
                        return;
                    }

                    event.preventDefault();
                    updateTabState(targetKey);
                });
            });

            window.addEventListener('popstate', () => {
                const targetKey = resolveTabFromUrl(window.location.href);
                updateTabState(targetKey, { skipHistory: true, force: true });
            });

            const commentShortcutButtons = document.querySelectorAll('[data-scroll-to-comments="true"]');
            if (commentShortcutButtons.length) {
                commentShortcutButtons.forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        updateTabState('comments');
                        ensureCommentsLoaded().then(() => {
                            const commentsSection = document.getElementById('tab-comments');
                            if (commentsSection) {
                                commentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        });
                    });
                });
            }
        }

        const moreOptionsToggle = document.querySelector('[data-more-options-toggle]');
        const moreOptionsMenu = document.querySelector('[data-more-options-menu]');
        if (moreOptionsToggle && moreOptionsMenu) {
            const closeMoreOptions = () => {
                if (!moreOptionsMenu.classList.contains('hidden')) {
                    moreOptionsMenu.classList.add('hidden');
                }
                moreOptionsMenu.setAttribute('aria-hidden', 'true');
                moreOptionsToggle.setAttribute('aria-expanded', 'false');
            };

            const openMoreOptions = () => {
                moreOptionsMenu.classList.remove('hidden');
                moreOptionsMenu.setAttribute('aria-hidden', 'false');
                moreOptionsToggle.setAttribute('aria-expanded', 'true');
            };

            moreOptionsToggle.addEventListener('click', (event) => {
                event.preventDefault();
                if (moreOptionsMenu.classList.contains('hidden')) {
                    openMoreOptions();
                } else {
                    closeMoreOptions();
                }
            });

            document.addEventListener('click', (event) => {
                if (moreOptionsMenu.classList.contains('hidden')) {
                    return;
                }

                if (!moreOptionsMenu.contains(event.target)
                    && event.target !== moreOptionsToggle
                    && !moreOptionsToggle.contains(event.target)) {
                    closeMoreOptions();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeMoreOptions();
                }
            });
        }

        const copyLinkButton = document.querySelector('[data-copy-link]');
        if (copyLinkButton) {
            copyLinkButton.addEventListener('click', () => {
                if (!window.GTAModsSingle || !GTAModsSingle.shareUrl) {
                    return;
                }

                const shareUrl = GTAModsSingle.shareUrl;
                const originalHtml = copyLinkButton.innerHTML;

                const fallbackCopy = () => {
                    const input = document.createElement('input');
                    input.value = shareUrl;
                    input.style.position = 'absolute';
                    input.style.left = '-9999px';
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                };

                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(shareUrl).then(() => {
                        copyLinkButton.innerHTML = '<i class="fas fa-check mr-2"></i>' + (GTAModsSingle.copiedLabel || 'Link copied!');
                        copyLinkButton.classList.add('bg-green-200', 'text-green-800');
                        window.setTimeout(() => {
                            copyLinkButton.innerHTML = originalHtml;
                            copyLinkButton.classList.remove('bg-green-200', 'text-green-800');
                        }, 2000);
                    }).catch(() => {
                        fallbackCopy();
                        copyLinkButton.innerHTML = '<i class="fas fa-check mr-2"></i>' + (GTAModsSingle.copiedLabel || 'Link copied!');
                        copyLinkButton.classList.add('bg-green-200', 'text-green-800');
                        window.setTimeout(() => {
                            copyLinkButton.innerHTML = originalHtml;
                            copyLinkButton.classList.remove('bg-green-200', 'text-green-800');
                        }, 2000);
                    });
                } else {
                    fallbackCopy();
                    copyLinkButton.innerHTML = '<i class="fas fa-check mr-2"></i>' + (GTAModsSingle.copiedLabel || 'Link copied!');
                    copyLinkButton.classList.add('bg-green-200', 'text-green-800');
                    window.setTimeout(() => {
                        copyLinkButton.innerHTML = originalHtml;
                        copyLinkButton.classList.remove('bg-green-200', 'text-green-800');
                    }, 2000);
                }
            });
        }

        const registerDownloadHandler = (button) => {
            const downloadUrl = button.getAttribute('data-download-url');
            if (!downloadUrl) {
                button.setAttribute('disabled', 'disabled');
                button.setAttribute('aria-disabled', 'true');
                return;
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                const versionIdAttr = button.getAttribute('data-version-id');
                const versionId = versionIdAttr ? parseInt(versionIdAttr, 10) : 0;

                incrementDownloadDisplays(versionId);
                sendDownloadIncrement(versionId);

                window.location.href = downloadUrl;
            });
        };

        if (window.GTAModsSingle) {
            const downloadButtons = document.querySelectorAll('[data-download-url]');
            downloadButtons.forEach((button) => {
                registerDownloadHandler(button);
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
})();
