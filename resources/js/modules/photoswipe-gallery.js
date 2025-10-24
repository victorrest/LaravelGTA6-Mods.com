import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const escapeHtml = (value = '') => {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const buildBadge = (isFeatured) => `
    <span class="inline-flex items-center gap-1 rounded-full bg-pink-600/20 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-pink-200 ${isFeatured ? '' : 'hidden'}" data-featured-badge>
        <i class="fa-solid fa-star text-pink-300"></i>
        Featured
    </span>
`;

const buildDuration = (duration) => {
    if (!duration) {
        return '';
    }

    return `
        <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-200">
            ${escapeHtml(duration)}
        </span>
    `;
};

const buildStatusPill = (status) => {
    if (!status || status === 'approved') {
        return '';
    }

    let label = 'Pending review';
    let classes = 'bg-amber-500/20 text-amber-200';

    if (status === 'reported') {
        label = 'Flagged';
        classes = 'bg-rose-500/20 text-rose-200';
    } else if (status === 'rejected') {
        label = 'Rejected';
        classes = 'bg-rose-600/25 text-rose-100';
    }

    return `
        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] ${classes}" data-status-pill>
            <i class="fa-solid fa-shield-halved"></i>
            ${label}
        </span>
    `;
};

const buildManageActions = (media) => {
    if (!media.can_manage) {
        return '';
    }

    const featureUrl = media.feature_url ? escapeHtml(media.feature_url) : null;
    const unfeatureUrl = media.unfeature_url ? escapeHtml(media.unfeature_url) : null;
    const deleteUrl = media.delete_url ? escapeHtml(media.delete_url) : null;

    const featureButton = featureUrl
        ? `
            <button type="button" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 font-semibold text-white transition hover:bg-white/20" data-feature-video data-feature-url="${featureUrl}" style="display: ${media.is_featured ? 'none' : 'inline-flex'};">
                <i class="fa-solid fa-star"></i>
                Feature video
            </button>
        `
        : '';

    const unfeatureButton = unfeatureUrl
        ? `
            <button type="button" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 font-semibold text-white transition hover:bg-white/20" data-unfeature-video data-unfeature-url="${unfeatureUrl}" style="display: ${media.is_featured ? 'inline-flex' : 'none'};">
                <i class="fa-solid fa-star-half-stroke"></i>
                Remove feature
            </button>
        `
        : '';

    const deleteButton = deleteUrl
        ? `
            <button type="button" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 font-semibold text-white transition hover:bg-white/20" data-delete-video data-delete-url="${deleteUrl}">
                <i class="fa-solid fa-trash"></i>
                Remove video
            </button>
        `
        : '';

    return `${featureButton}${unfeatureButton}${deleteButton}`;
};

const buildSubmitter = (media) => {
    const name = escapeHtml(media.submitter_name ?? 'Community');
    const time = media.submitted_human ? `<p class="mt-1 text-[11px] uppercase tracking-[0.3em] text-gray-400">${escapeHtml(media.submitted_human)}</p>` : '';

    if (media.submitter_url) {
        const profileUrl = escapeHtml(media.submitter_url);
        return `
            <div class="text-right text-xs text-gray-300">
                <p class="font-semibold text-white">${name}</p>
                <a href="${profileUrl}" class="text-pink-300 hover:text-pink-200 transition" target="_blank" rel="noopener">View profile</a>
                ${time}
            </div>
        `;
    }

    return `
        <div class="text-right text-xs text-gray-300">
            <p class="font-semibold text-white">${name}</p>
            ${time}
        </div>
    `;
};

const createVideoMarkup = (media) => {
    const title = escapeHtml(media.title || media.alt || 'Community submission');
    const description = media.description ? `<p class="text-xs text-gray-300 leading-relaxed whitespace-pre-line">${escapeHtml(media.description)}</p>` : '';
    const youtubeUrl = escapeHtml(media.youtube_url || `https://www.youtube.com/watch?v=${encodeURIComponent(media.youtube_id)}`);
    const reportButton = media.report_url
        ? `
            <button type="button" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 font-semibold text-white transition hover:bg-white/20" data-report-video data-report-url="${escapeHtml(media.report_url)}" data-video-id="${escapeHtml(String(media.video_id ?? ''))}">
                <i class="fa-solid fa-flag"></i>
                Report
            </button>
        `
        : '';
    const moderationNote = (() => {
        if (media.status && media.status !== 'approved') {
            const noteMap = {
                pending: 'This submission is awaiting moderator approval.',
                reported: 'Community reports flagged this video for review.',
                rejected: 'This video was rejected by moderation.',
            };

            const note = noteMap[media.status] || `Status: ${escapeHtml(media.status)}`;
            const tone = media.status === 'pending' ? 'text-amber-200' : 'text-rose-200';
            return `<p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.3em] ${tone}">${note}</p>`;
        }

        if (media.report_count && media.report_count > 0) {
            return `<p class="mt-2 text-[11px] text-amber-200 uppercase tracking-[0.3em]">Reports from community: ${escapeHtml(String(media.report_count))}</p>`;
        }

        return '';
    })();

    return `
        <div class="pswp-video-slide flex h-full w-full flex-col items-center justify-center gap-4 px-4 py-6 sm:px-6">
            <div class="pswp-video-frame aspect-video w-full max-w-5xl overflow-hidden rounded-2xl bg-black shadow-2xl" data-youtube-id="${escapeHtml(media.youtube_id ?? '')}"></div>
            <div class="pswp-video-meta w-full max-w-5xl overflow-hidden rounded-2xl border border-pink-500/30 bg-gray-950/95 text-gray-100 shadow-lg">
                <div class="border-b border-white/5 px-5 py-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            ${buildBadge(media.is_featured)}
                            ${buildStatusPill(media.status)}
                        </div>
                        ${buildDuration(media.duration)}
                    </div>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-2">
                            <p class="text-xs uppercase tracking-[0.3em] text-pink-300">Video submission</p>
                            <h3 class="text-lg font-semibold text-white">${title}</h3>
                            ${description}
                        </div>
                        ${buildSubmitter(media)}
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 px-5 py-4 text-sm">
                    <a href="${youtubeUrl}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-red-600 px-4 py-2 font-semibold text-white transition hover:bg-red-700">
                        <i class="fa-brands fa-youtube"></i>
                        Watch on YouTube
                    </a>
                    ${reportButton}
                    <button type="button" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 font-semibold text-white transition hover:bg-white/20" data-copy-video-link="${youtubeUrl}">
                        <i class="fa-solid fa-link"></i>
                        Copy link
                    </button>
                    ${buildManageActions(media)}
                    <span class="sr-only" data-feedback-role></span>
                </div>
                <div class="px-5 pb-4 text-xs text-gray-400" data-feedback>${moderationNote}</div>
            </div>
        </div>
    `;
};

const sendRequest = async (url, method = 'POST') => {
    if (!url) {
        throw new Error('Missing endpoint');
    }

    const headers = { Accept: 'application/json' };
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const response = await fetch(url, {
        method,
        headers,
    });

    let payload = null;

    try {
        payload = await response.json();
    } catch (error) {
        payload = null;
    }

    if (!response.ok) {
        const message = payload?.message || `Request failed (${response.status})`;
        throw new Error(message);
    }

    return payload;
};

const showFeedback = (container, srOnly, message, tone = 'info') => {
    if (!container) {
        return;
    }

    const baseClasses = 'mt-1 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold';
    let toneClasses = 'bg-white/10 text-gray-100';

    if (tone === 'success') {
        toneClasses = 'bg-emerald-600/20 text-emerald-200';
    } else if (tone === 'error') {
        toneClasses = 'bg-rose-600/20 text-rose-100';
    }

    const defaultNote = container.dataset.defaultMessage || '';
    const feedbackMarkup = `<span class="${baseClasses} ${toneClasses}"><i class="fa-solid fa-circle-info"></i>${escapeHtml(message)}</span>`;
    container.innerHTML = defaultNote
        ? `${feedbackMarkup}<div class="mt-2 text-[11px] text-gray-400">${defaultNote}</div>`
        : feedbackMarkup;

    if (srOnly) {
        srOnly.textContent = message;
    }
};

const attachCopyHandler = (button, media, feedbackEl, srOnly) => {
    if (!button) {
        return;
    }

    const url = button.dataset.copyVideoLink;
    if (!url) {
        return;
    }

    button.addEventListener('click', async () => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
            } else {
                const temp = document.createElement('input');
                temp.value = url;
                temp.setAttribute('type', 'text');
                temp.setAttribute('readonly', 'true');
                temp.style.position = 'absolute';
                temp.style.left = '-9999px';
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            }

            showFeedback(feedbackEl, srOnly, 'Link copied to clipboard.', 'success');
        } catch (error) {
            showFeedback(feedbackEl, srOnly, 'Copy failed. Please copy manually.', 'error');
        }
    });
};

const attachReportHandler = (button, media, feedbackEl, srOnly) => {
    if (!button) {
        return;
    }

    const reportUrl = button.dataset.reportUrl;
    if (!reportUrl) {
        return;
    }

    button.addEventListener('click', async () => {
        try {
            await sendRequest(reportUrl);
            showFeedback(feedbackEl, srOnly, 'Thank you for reporting this video. Our team will review it shortly.', 'success');
        } catch (error) {
            showFeedback(feedbackEl, srOnly, error.message || 'Reporting failed.', 'error');
        }
    });
};

const toggleFeaturedState = (container, media, isFeatured) => {
    media.is_featured = isFeatured;

    const badge = container.querySelector('[data-featured-badge]');
    if (badge) {
        badge.style.display = isFeatured ? 'inline-flex' : 'none';
    }

    const featureBtn = container.querySelector('[data-feature-video]');
    const unfeatureBtn = container.querySelector('[data-unfeature-video]');

    if (featureBtn) {
        featureBtn.style.display = isFeatured ? 'none' : 'inline-flex';
    }

    if (unfeatureBtn) {
        unfeatureBtn.style.display = isFeatured ? 'inline-flex' : 'none';
    }
};

const attachFeatureHandlers = (container, media, feedbackEl, srOnly) => {
    const featureBtn = container.querySelector('[data-feature-video]');
    const unfeatureBtn = container.querySelector('[data-unfeature-video]');

    if (featureBtn) {
        const featureUrl = featureBtn.dataset.featureUrl;
        if (featureUrl) {
            featureBtn.addEventListener('click', async () => {
                try {
                    await sendRequest(featureUrl);
                    toggleFeaturedState(container, media, true);
                    showFeedback(feedbackEl, srOnly, 'Video is now featured.', 'success');
                } catch (error) {
                    showFeedback(feedbackEl, srOnly, error.message || 'Unable to feature the video.', 'error');
                }
            });
        }
    }

    if (unfeatureBtn) {
        const unfeatureUrl = unfeatureBtn.dataset.unfeatureUrl;
        if (unfeatureUrl) {
            unfeatureBtn.addEventListener('click', async () => {
                try {
                    await sendRequest(unfeatureUrl, 'DELETE');
                    toggleFeaturedState(container, media, false);
                    showFeedback(feedbackEl, srOnly, 'Video feature removed.', 'success');
                } catch (error) {
                    showFeedback(feedbackEl, srOnly, error.message || 'Unable to update featured status.', 'error');
                }
            });
        }
    }
};

const attachDeleteHandler = (container, media, feedbackEl, srOnly) => {
    const deleteBtn = container.querySelector('[data-delete-video]');
    if (!deleteBtn) {
        return;
    }

    const deleteUrl = deleteBtn.dataset.deleteUrl;
    if (!deleteUrl) {
        return;
    }

    deleteBtn.addEventListener('click', async () => {
        const confirmDelete = window.confirm('Are you sure you want to remove this video from the gallery?');
        if (!confirmDelete) {
            return;
        }

        try {
            await sendRequest(deleteUrl, 'DELETE');
            showFeedback(feedbackEl, srOnly, 'Video removed. Reloading…', 'success');
            setTimeout(() => window.location.reload(), 1200);
        } catch (error) {
            showFeedback(feedbackEl, srOnly, error.message || 'Unable to remove the video.', 'error');
        }
    });
};

const initVideoSlide = (element, media) => {
    const frame = element.querySelector('.pswp-video-frame');
    const feedbackEl = element.querySelector('[data-feedback]');
    const srOnly = element.querySelector('[data-feedback-role]');

    if (feedbackEl && !feedbackEl.dataset.defaultMessage) {
        feedbackEl.dataset.defaultMessage = feedbackEl.innerHTML || '';
    }

    if (frame && media.youtube_id) {
        const iframe = document.createElement('iframe');
        iframe.className = 'h-full w-full';
        iframe.src = `https://www.youtube.com/embed/${encodeURIComponent(media.youtube_id)}?autoplay=1&rel=0&modestbranding=1&playsinline=1`;
        iframe.title = media.title || 'YouTube video player';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.allowFullscreen = true;
        frame.appendChild(iframe);
    }

    attachCopyHandler(element.querySelector('[data-copy-video-link]'), media, feedbackEl, srOnly);
    attachReportHandler(element.querySelector('[data-report-video]'), media, feedbackEl, srOnly);
    attachFeatureHandlers(element, media, feedbackEl, srOnly);
    attachDeleteHandler(element, media, feedbackEl, srOnly);
};

const initGallery = (galleryEl) => {
    const modId = galleryEl.getAttribute('data-pswp-gallery');
    const dataEl = document.getElementById(`gallery-data-${modId}`);

    if (!dataEl) {
        return;
    }

    let mediaItems = [];

    try {
        mediaItems = JSON.parse(dataEl.textContent || '[]');
    } catch (error) {
        console.error('Failed to parse gallery data', error);
        return;
    }

    const loadMoreBtn = galleryEl.querySelector('[data-load-more]');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            galleryEl.querySelectorAll('.gallery-hidden-thumb').forEach((thumb) => {
                thumb.classList.remove('hidden');
            });
            loadMoreBtn.remove();
        });
    }

    const lightbox = new PhotoSwipeLightbox({
        gallery: galleryEl,
        children: '[data-pswp-index]',
        pswpModule: () => import('photoswipe'),
        showHideAnimationType: 'fade',
        paddingFn: (viewportSize) => (viewportSize.x < 640 ? 16 : 32),
        secondaryZoomLevel: 1,
    });

    lightbox.addFilter('itemData', (itemData, trigger) => {
        const index = Number(trigger.dataset.pswpIndex || 0);
        const media = mediaItems[index];

        if (!media) {
            return itemData;
        }

        if (media.type === 'video') {
            return {
                type: 'html',
                html: createVideoMarkup(media),
                originalMedia: media,
            };
        }

        return {
            src: media.src,
            msrc: media.thumbnail || media.src,
            width: media.width || 1920,
            height: media.height || 1080,
            alt: media.alt || '',
            originalMedia: media,
        };
    });

    lightbox.on('contentActivate', (event) => {
        const media = event.slide?.data?.originalMedia;
        if (!media) {
            return;
        }

        if (media.type === 'video') {
            initVideoSlide(event.content.element, media);
        }
    });

    lightbox.on('contentDeactivate', (event) => {
        const iframe = event.content.element?.querySelector?.('.pswp-video-frame iframe');
        if (iframe) {
            iframe.src = 'about:blank';
        }
    });

    lightbox.init();
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-pswp-gallery]').forEach((gallery) => {
        initGallery(gallery);
    });
});
