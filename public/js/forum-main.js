(function () {
    const root = document.querySelector('[data-forum-main]');
    if (!root || !window.wp || !wp.apiFetch) {
        return;
    }

    const list = root.querySelector('[data-thread-list]');
    const loadMoreButton = root.querySelector('[data-load-more]');
    const status = root.querySelector('[data-status]');
    const sortButtons = root.querySelectorAll('[data-sort]');
    const topRangeWrapper = root.querySelector('[data-top-range-wrapper]');
    const topRangeSelect = topRangeWrapper ? topRangeWrapper.querySelector('[data-top-range]') : null;
    const flairButtons = root.querySelectorAll('[data-flair-filter]');
    const searchForm = root.querySelector('[data-forum-search]');
    const searchInput = searchForm ? searchForm.querySelector('input[name="q"]') : null;
    const headingEl = root.querySelector('[data-forum-heading]');
    const descriptionEl = root.querySelector('[data-forum-description]');
    const initialHeadingText = headingEl ? headingEl.textContent : '';
    const initialDescriptionHtml = descriptionEl ? descriptionEl.innerHTML : '';

    const ALLOWED_SORTS = ['hot', 'new', 'top'];
    const ALLOWED_TIME_RANGES = ['all-time', 'today', 'last-week', 'last-month', 'last-year'];

    let flairMetadata = {};
    try {
        const metadataAttr = root.getAttribute('data-flair-metadata');
        if (metadataAttr) {
            const parsed = JSON.parse(metadataAttr);
            if (parsed && typeof parsed === 'object') {
                flairMetadata = parsed;
            }
        }
    } catch (error) {
        flairMetadata = {};
    }

    const baseUrl = root.getAttribute('data-base-url') || window.location.href;
    const flairPath = root.getAttribute('data-flair-path') || '';
    const flairBaseUrl = root.getAttribute('data-flair-base-url') || baseUrl;
    const initialFlair = root.getAttribute('data-initial-flair') || '';
    const initialSearch = root.getAttribute('data-initial-search') || '';
    const initialTimeRangeAttr = root.getAttribute('data-initial-time-range') || '';
    const initialSortAttr = root.getAttribute('data-initial-sort') || '';
    const perPageAttr = parseInt(root.getAttribute('data-per-page'), 10);
    const initialPageAttr = parseInt(root.getAttribute('data-initial-page'), 10);
    const initialTotalPagesAttr = parseInt(root.getAttribute('data-initial-total-pages'), 10);
    const hasInitial = root.getAttribute('data-has-initial') === '1';

    const bookmarkService = window.GTA6ForumBookmarks;
    const commentLabels = GTA6ForumMain.commentLabels || { singular: '%s comment', plural: '%s comments' };
    const viewLabels = GTA6ForumMain.viewLabels || { singular: '%s view', plural: '%s views' };
    const searchField = searchForm ? searchForm.querySelector('.forum-search-field') : null;
    const searchClearButton = searchForm ? searchForm.querySelector('[data-forum-search-clear]') : null;

    let activeRequestToken = 0;
    let activeAbortController = null;

    const state = {
        page: 1,
        perPage: 10,
        sort: ALLOWED_SORTS.includes(initialSortAttr) ? initialSortAttr : 'hot',
        timeRange: ALLOWED_TIME_RANGES.includes(initialTimeRangeAttr) ? initialTimeRangeAttr : 'all-time',
        flair: initialFlair || '',
        searchTerm: initialSearch || '',
        loading: false,
        totalPages: 1,
    };

    if (Number.isFinite(perPageAttr) && perPageAttr > 0) {
        state.perPage = perPageAttr;
    }

    if (initialSortAttr && ALLOWED_SORTS.includes(initialSortAttr)) {
        state.sort = initialSortAttr;
    }

    if (initialTimeRangeAttr && ALLOWED_TIME_RANGES.includes(initialTimeRangeAttr)) {
        state.timeRange = initialTimeRangeAttr;
    }

    if (state.sort !== 'top') {
        state.timeRange = 'all-time';
    } else if (!ALLOWED_TIME_RANGES.includes(state.timeRange)) {
        state.timeRange = 'all-time';
    }

    if (Number.isFinite(initialPageAttr) && initialPageAttr > 0) {
        state.page = initialPageAttr;
    }

    if (Number.isFinite(initialTotalPagesAttr) && initialTotalPagesAttr > 0) {
        state.totalPages = initialTotalPagesAttr;
    }

    if (searchInput && initialSearch) {
        searchInput.value = initialSearch;
    }

    markSearchFieldState();

    function openShare(payload) {
        if (window.GTA6ForumShare && typeof window.GTA6ForumShare.open === 'function') {
            window.GTA6ForumShare.open(payload);
            return;
        }

        if (payload && payload.url && navigator.share) {
            navigator.share({
                title: payload.title || document.title,
                url: payload.url,
            }).catch(() => {});
            return;
        }

        if (payload && payload.url) {
            window.open(payload.url, '_blank', 'noopener');
        }
    }

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const decodeEntities = (() => {
        const textarea = document.createElement('textarea');
        return (value) => {
            if (value === null || typeof value === 'undefined') {
                return '';
            }

            textarea.innerHTML = String(value);
            return textarea.value;
        };
    })();

    const normalisePath = (value) => {
        if (!value) {
            return '';
        }

        const trimmed = value
            .replace(/^\/+|\/+$/g, '')
            .replace(/\/+/g, '/');

        if (!trimmed) {
            return '/';
        }

        return `/${trimmed}/`.replace(/\/+/g, '/');
    };

    const trimTrailingSlash = (value) => value ? value.replace(/\/+$/g, '') : value;

    let baseUrlObject;
    try {
        baseUrlObject = new URL(baseUrl || window.location.href, window.location.origin);
    } catch (error) {
        baseUrlObject = new URL(window.location.href);
    }

    const basePathNormalized = normalisePath(baseUrlObject.pathname);
    const basePathSegments = basePathNormalized ? basePathNormalized.split('/').filter(Boolean) : [];

    const flairPathNormalized = normalisePath(flairPath);
    const flairPathSegments = flairPathNormalized ? flairPathNormalized.split('/').filter(Boolean) : [];
    const flairIndicator = (() => {
        if (!flairPathSegments.length) {
            return '';
        }

        if (basePathSegments.length && flairPathSegments.length > basePathSegments.length) {
            return flairPathSegments[basePathSegments.length] || '';
        }

        return flairPathSegments[flairPathSegments.length - 1] || '';
    })();

    const decodePathSegment = (segment) => {
        if (!segment) {
            return '';
        }

        try {
            return decodeURIComponent(segment);
        } catch (error) {
            return segment;
        }
    };

    const getForumPathSegmentsFromUrl = (targetUrl) => {
        try {
            const url = new URL(targetUrl, window.location.origin);
            const path = normalisePath(url.pathname);

            if (!basePathNormalized || path.indexOf(basePathNormalized) !== 0) {
                return [];
            }

            const remainder = path.slice(basePathNormalized.length).replace(/^\/+|\/+$/g, '');
            return remainder ? remainder.split('/').filter(Boolean) : [];
        } catch (error) {
            return [];
        }
    };

    const resolveLocationContextFromUrl = (targetUrl) => {
        const segments = getForumPathSegmentsFromUrl(targetUrl);
        let flairSlug = '';
        let sortSegment = '';
        let rangeSegment = '';

        if (segments.length && flairIndicator && segments[0] === flairIndicator) {
            flairSlug = decodePathSegment(segments[1] || '');
            sortSegment = segments[2] || '';
            rangeSegment = segments[3] || '';
        } else {
            sortSegment = segments[0] || '';
            rangeSegment = segments[1] || '';
        }

        const normalisedSort = ALLOWED_SORTS.includes(sortSegment) ? sortSegment : 'hot';
        const normalisedRange = (normalisedSort === 'top' && ALLOWED_TIME_RANGES.includes(rangeSegment))
            ? rangeSegment
            : 'all-time';

        return {
            flairSlug,
            sort: normalisedSort,
            timeRange: normalisedRange,
        };
    };

    const buildFlairUrl = (slug) => {
        if (!slug) {
            return baseUrl || window.location.href;
        }

        const target = trimTrailingSlash(flairBaseUrl || baseUrl || window.location.href) || '';
        if (!target) {
            return window.location.href;
        }

        return `${target}/${encodeURIComponent(slug)}/`;
    };

    function markSearchFieldState() {
        if (!searchField || !searchInput) {
            return;
        }

        const hasValue = searchInput.value && searchInput.value.trim() !== '';
        searchField.classList.toggle('has-value', Boolean(hasValue));
    }

    function resolveSearchFromLocation() {
        try {
            const url = new URL(window.location.href);
            const term = url.searchParams.get('q');
            return term ? term.trim() : '';
        } catch (error) {
            return '';
        }
    }

    const buildStateUrl = () => {
        let target = state.flair ? buildFlairUrl(state.flair) : baseUrl;
        let url;

        try {
            url = new URL(target || window.location.href, window.location.origin);
        } catch (error) {
            url = new URL(window.location.href);
        }

        const basePath = url.pathname.replace(/\/+$/g, '');
        const segments = [];

        if (state.sort === 'new') {
            segments.push('new');
        } else if (state.sort === 'top') {
            segments.push('top');
            if (state.timeRange && state.timeRange !== 'all-time') {
                segments.push(state.timeRange);
            }
        }

        let nextPath = basePath;
        if (segments.length) {
            nextPath = `${nextPath}/${segments.join('/')}`;
        }

        url.pathname = `${nextPath}/`.replace(/\/+/g, '/');

        if (state.searchTerm) {
            url.searchParams.set('q', state.searchTerm);
        } else {
            url.searchParams.delete('q');
        }

        return url.toString();
    };

    function updateHistoryState(replace = false) {
        if (!window.history || typeof window.history.pushState !== 'function') {
            return;
        }

        const targetUrl = buildStateUrl();
        const method = replace ? 'replaceState' : 'pushState';

        window.history[method](
            {
                flair: state.flair,
                search: state.searchTerm,
                sort: state.sort,
                timeRange: state.timeRange,
            },
            '',
            targetUrl,
        );
    }

    function resolveFlairFromLocation() {
        const context = resolveLocationContextFromUrl(window.location.href);
        return context.flairSlug || '';
    }

    function resolveSortFromLocation() {
        const context = resolveLocationContextFromUrl(window.location.href);
        return context.sort;
    }

    function resolveTimeRangeFromLocation() {
        const context = resolveLocationContextFromUrl(window.location.href);
        return context.timeRange;
    }

    function updateFlairSelection() {
        flairButtons.forEach((button) => {
            const slug = button.getAttribute('data-flair') || '';
            const isActive = Boolean(state.flair) && slug === state.flair;
            button.classList.toggle('selected', isActive);
            if (isActive) {
                button.setAttribute('aria-current', 'page');
            } else {
                button.removeAttribute('aria-current');
            }
        });

        updateIntroContent();
    }

    function updateIntroContent() {
        if (!headingEl && !descriptionEl) {
            return;
        }

        const slug = state.flair || '';
        const fallback = flairMetadata && typeof flairMetadata === 'object' ? flairMetadata[''] || null : null;
        const meta = flairMetadata && typeof flairMetadata === 'object' ? flairMetadata[slug] || null : null;

        if (headingEl) {
            const metaHeading = meta && typeof meta.heading === 'string' ? meta.heading : '';
            const fallbackHeading = fallback && typeof fallback.heading === 'string' ? fallback.heading : '';
            const nextHeading = metaHeading || fallbackHeading || initialHeadingText || '';
            headingEl.textContent = nextHeading || '';
        }

        if (descriptionEl) {
            const metaDescription = meta && typeof meta.description === 'string' ? meta.description : '';
            const fallbackDescription = fallback && typeof fallback.description === 'string' ? fallback.description : '';
            const nextDescription = metaDescription || fallbackDescription || initialDescriptionHtml || '';

            if (nextDescription) {
                descriptionEl.innerHTML = nextDescription;
                descriptionEl.classList.remove('hidden');
            } else {
                descriptionEl.innerHTML = '';
                descriptionEl.classList.add('hidden');
            }
        }
    }

    function formatCommentLabel(count) {
        const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
        const template = safeCount === 1
            ? (commentLabels.singular || '%s comment')
            : (commentLabels.plural || '%s comments');
        return template.replace('%s', safeCount.toLocaleString());
    }

    function formatViewLabel(count, preformatted) {
        if (preformatted && typeof preformatted === 'string' && preformatted.trim() !== '') {
            return preformatted;
        }

        const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
        const template = safeCount === 1
            ? (viewLabels.singular || '%s view')
            : (viewLabels.plural || '%s views');

        return template.replace('%s', safeCount.toLocaleString());
    }

    function formatRelativeTime(value) {
        const date = value ? new Date(value) : null;
        if (!date || Number.isNaN(date.getTime())) {
            return '';
        }

        const now = new Date();
        let diff = Math.max(0, now.getTime() - date.getTime());
        const seconds = Math.floor(diff / 1000);
        if (seconds < 45) {
            return 'just now';
        }

        const minutes = Math.floor(seconds / 60);
        if (minutes < 2) {
            return '1 minute ago';
        }
        if (minutes < 60) {
            return `${minutes} minutes ago`;
        }

        const hours = Math.floor(minutes / 60);
        if (hours < 2) {
            return '1 hour ago';
        }
        if (hours < 24) {
            return `${hours} hours ago`;
        }

        const days = Math.floor(hours / 24);
        if (days < 2) {
            return '1 day ago';
        }
        if (days < 30) {
            return `${days} days ago`;
        }

        const months = Math.floor(days / 30);
        if (months < 2) {
            return '1 month ago';
        }
        if (months < 12) {
            return `${months} months ago`;
        }

        const years = Math.floor(days / 365);
        if (years < 2) {
            return '1 year ago';
        }

        return `${years} years ago`;
    }

    function setActiveSortButton() {
        sortButtons.forEach((button) => {
            const sortValue = button.getAttribute('data-sort');
            const isActive = sortValue === state.sort;
            button.classList.toggle('active', isActive);
            if (isActive) {
                button.classList.remove('text-gray-600');
            } else {
                button.classList.add('text-gray-600');
            }
        });

        updateTopRangeVisibility();
    }

    function updateTopRangeVisibility() {
        if (!topRangeWrapper) {
            return;
        }

        const shouldShow = state.sort === 'top';
        topRangeWrapper.classList.toggle('hidden', !shouldShow);

        if (shouldShow && topRangeSelect) {
            const desired = ALLOWED_TIME_RANGES.includes(state.timeRange) ? state.timeRange : 'all-time';
            if (topRangeSelect.value !== desired) {
                topRangeSelect.value = desired;
            }
        }
    }

    function buildThreadMeta(thread) {
        const author = thread && thread.author ? thread.author : {};
        const authorNameRaw = author && author.name ? decodeEntities(author.name) : 'Anonymous';
        const authorName = escapeHtml(authorNameRaw);
        const authorUrl = author && author.url ? escapeHtml(author.url) : '#';
        const createdAtValue = (thread && (thread.created_at || thread.date_gmt || thread.date)) || '';
        const timeAgo = formatRelativeTime(createdAtValue);
        const authorLink = `<a href="${authorUrl}" class="font-semibold text-amber-600 hover:underline">${authorName}</a>`;
        if (timeAgo) {
            return `Posted by ${authorLink} · ${escapeHtml(timeAgo)}`;
        }
        return `Posted by ${authorLink}`;
    }

    function buildFlairHtml(thread) {
        if (!thread || !Array.isArray(thread.flairs) || !thread.flairs.length) {
            return '';
        }

        return thread.flairs.map((flair) => {
            if (!flair || !flair.name) {
                return '';
            }
            const name = escapeHtml(flair.name);
            const slug = flair.slug || '';
            const link = escapeHtml(flair.link || buildFlairUrl(slug));
            const background = flair.colors && flair.colors.background
                ? `background-color: ${escapeHtml(flair.colors.background)};`
                : '';
            const textColor = flair.colors && flair.colors.text
                ? ` color: ${escapeHtml(flair.colors.text)};`
                : '';
            const style = `${background}${textColor}`.trim();
            const styleAttr = style ? ` style="${style}"` : '';
            return `<a href="${link}" class="post-flair thread-flair" rel="tag"${styleAttr}>${name}</a>`;
        }).filter(Boolean).join('');
    }

    function buildExcerptHtml(thread) {
        if (!thread) {
            return '';
        }

        const type = thread.type || 'text';
        if (type === 'link') {
            return '';
        }

        const excerptRaw = thread.excerpt ? decodeEntities(thread.excerpt) : '';
        const excerpt = excerptRaw ? escapeHtml(excerptRaw) : '';
        if (!excerpt) {
            return '';
        }

        return `<p class="text-gray-700 text-sm leading-relaxed forum-thread-excerpt">${excerpt}</p>`;
    }

    function buildMediaHtml(thread) {
        if (!thread) {
            return '';
        }

        const type = thread.type || 'text';
        const permalink = escapeHtml(thread.permalink || '#');

        if (type === 'image' && thread.image && (thread.image.preview_url || thread.image.full_url)) {
            const imageUrl = thread.image.preview_url || thread.image.full_url;
            if (!imageUrl) {
                return '';
            }
            const imageAltRaw = thread.image.alt ? decodeEntities(thread.image.alt) : (thread.title ? decodeEntities(thread.title) : '');
            const alt = escapeHtml(imageAltRaw);
            return `<a href="${permalink}" class="forum-thread-image-wrapper"><img src="${escapeHtml(imageUrl)}" alt="${alt}" class="forum-thread-image" loading="lazy" decoding="async"></a>`;
        }

        if (type === 'link' && thread.link && thread.link.url) {
            const linkUrl = escapeHtml(thread.link.url);
            const labelRaw = thread.link.display ? decodeEntities(thread.link.display) : thread.link.url;
            const label = escapeHtml(labelRaw);
            return `<a href="${linkUrl}" target="_blank" rel="nofollow noopener ugc" class="forum-thread-link"><span class="forum-thread-link__label">${label}<i class="fas fa-external-link-alt" aria-hidden="true"></i></span></a>`;
        }

        return '';
    }

    function buildThreadCardInnerHtml(thread) {
        const threadId = thread && typeof thread.id !== 'undefined' ? String(thread.id) : '';
        const rawPermalink = thread && thread.permalink ? thread.permalink : '#';
        const permalink = escapeHtml(rawPermalink || '#');
        const rawTitle = thread && thread.title ? decodeEntities(thread.title) : '';
        const title = escapeHtml(rawTitle);
        let score = 0;
        if (thread && typeof thread.score !== 'undefined') {
            score = Number(thread.score);
            if (!Number.isFinite(score)) {
                score = parseInt(thread.score, 10) || 0;
            }
        }
        const scoreLabel = escapeHtml(String(score));
        let commentCount = 0;
        if (thread && typeof thread.comment_count !== 'undefined') {
            commentCount = Number(thread.comment_count);
            if (!Number.isFinite(commentCount)) {
                commentCount = parseInt(thread.comment_count, 10) || 0;
            }
        }
        const commentLabel = escapeHtml(formatCommentLabel(commentCount));
        const shareTitle = escapeHtml(rawTitle);
        let viewCount = 0;
        if (thread && typeof thread.views !== 'undefined') {
            viewCount = Number(thread.views);
            if (!Number.isFinite(viewCount)) {
                viewCount = parseInt(thread.views, 10) || 0;
            }
        }
        const viewLabel = escapeHtml(formatViewLabel(viewCount, thread && thread.formatted_views));
        const shareUrl = permalink;
        const bookmarkEndpointRaw = thread && thread.bookmark_endpoint ? thread.bookmark_endpoint : '';
        const bookmarkEndpoint = bookmarkEndpointRaw ? escapeHtml(bookmarkEndpointRaw) : '';
        const isBookmarked = Boolean(thread && thread.is_bookmarked);
        const userVote = thread && typeof thread.current_user_vote !== 'undefined' ? Number(thread.current_user_vote) : 0;
        const upvoted = userVote === 1;
        const downvoted = userVote === -1;
        const metaHtml = buildThreadMeta(thread);
        const flairsHtml = buildFlairHtml(thread);
        const mediaHtml = buildMediaHtml(thread);
        const excerptHtml = buildExcerptHtml(thread);
        const commentsLink = (rawPermalink && rawPermalink !== '#') ? `${rawPermalink}#comments` : '#';
        const shareLabel = escapeHtml((GTA6ForumMain.texts && GTA6ForumMain.texts.share) || 'Share');
        const flairWrapperClass = flairsHtml ? 'flex flex-wrap items-center gap-2' : 'flex flex-wrap items-center gap-2 hidden';
        const upvoteClass = upvoted ? 'vote-button upvoted' : 'vote-button';
        const downvoteClass = downvoted ? 'vote-button downvoted' : 'vote-button';
        const bookmarkClasses = isBookmarked
            ? 'thread-action-button flex items-center gap-2 hover:text-pink-600 is-active'
            : 'thread-action-button flex items-center gap-2 hover:text-pink-600';
        const bookmarkLabel = escapeHtml(isBookmarked ? 'Saved' : 'Bookmark');
        const upvoteLabel = escapeHtml((GTA6ForumMain.texts && GTA6ForumMain.texts.upvote) || 'Upvote thread');
        const downvoteLabel = escapeHtml((GTA6ForumMain.texts && GTA6ForumMain.texts.downvote) || 'Downvote thread');

        const bookmarkButton = bookmarkEndpoint
            ? `
                <button type="button" class="${bookmarkClasses}" data-bookmark-button data-bookmark-endpoint="${bookmarkEndpoint}" data-bookmarked="${isBookmarked ? 'true' : 'false'}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true" data-bookmark-icon><path d="M5 2H19C19.5523 2 20 2.44772 20 3V22.1433C20 22.4194 19.7761 22.6434 19.5 22.6434C19.4061 22.6434 19.314 22.6168 19.2344 22.5669L12 18.0313L4.76559 22.5669C4.53163 22.7136 4.22306 22.6429 4.07637 22.4089C4.02647 22.3293 4 22.2373 4 22.1433V3C4 2.44772 4.44772 2 5 2ZM18 4H6V19.4324L12 15.6707L18 19.4324V4Z"></path></svg>
                    <span data-bookmark-label>${bookmarkLabel}</span>
                </button>
            `
            : '';

        return `
            <div class="flex">
                <div class="flex flex-col items-center bg-gray-50 dark:bg-gray-800 p-2 space-y-1" data-vote-wrapper${threadId ? ` data-thread-id="${escapeHtml(threadId)}"` : ''}>
                    <button class="${upvoteClass}" data-vote="up" aria-label="${upvoteLabel}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-up-icon lucide-arrow-big-up"><path d="M9 13a1 1 0 0 0-1-1H5.061a1 1 0 0 1-.75-1.811l6.836-6.835a1.207 1.207 0 0 1 1.707 0l6.835 6.835a1 1 0 0 1-.75 1.811H16a1 1 0 0 0-1 1v6a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z"/></svg>
                    </button>
                    <span class="font-bold text-sm text-gray-800 dark:text-gray-100" data-score>${scoreLabel}</span>
                    <button class="${downvoteClass}" data-vote="down" aria-label="${downvoteLabel}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-down-icon lucide-arrow-big-down"><path d="M15 11a1 1 0 0 0 1 1h2.939a1 1 0 0 1 .75 1.811l-6.835 6.836a1.207 1.207 0 0 1-1.707 0L4.31 13.81a1 1 0 0 1 .75-1.811H8a1 1 0 0 0 1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1z"/></svg>
                    </button>
                </div>
                <div class="p-4 w-full">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-gray-500 mb-2">
                        <div class="${flairWrapperClass}" data-flair-wrapper>${flairsHtml}</div>
                        <span class="text-xs text-gray-500" data-thread-meta>${metaHtml}</span>
                    </div>
                    <a href="${permalink}" class="block">
                        <h3 class="text-lg font-bold text-gray-900 mb-1 hover:text-pink-600 transition">${title}</h3>
                    </a>
                    ${mediaHtml}
                    ${excerptHtml}
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 font-semibold mt-4">
                        <span class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M12.0003 3C17.3924 3 21.8784 6.87976 22.8189 12C21.8784 17.1202 17.3924 21 12.0003 21C6.60812 21 2.12215 17.1202 1.18164 12C2.12215 6.87976 6.60812 3 12.0003 3ZM12.0003 19C16.2359 19 19.8603 16.052 20.7777 12C19.8603 7.94803 16.2359 5 12.0003 5C7.7646 5 4.14022 7.94803 3.22278 12C4.14022 16.052 7.7646 19 12.0003 19ZM12.0003 16.5C9.51498 16.5 7.50026 14.4853 7.50026 12C7.50026 9.51472 9.51498 7.5 12.0003 7.5C14.4855 7.5 16.5003 9.51472 16.5003 12C16.5003 14.4853 14.4855 16.5 12.0003 16.5ZM12.0003 14.5C13.381 14.5 14.5003 13.3807 14.5003 12C14.5003 10.6193 13.381 9.5 12.0003 9.5C10.6196 9.5 9.50026 10.6193 9.50026 12C9.50026 13.3807 10.6196 14.5 12.0003 14.5Z"></path></svg>
                            <span>${viewLabel}</span>
                        </span>
                        <a href="${escapeHtml(commentsLink)}" class="flex items-center gap-2 hover:text-pink-600">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M10 3H14C18.4183 3 22 6.58172 22 11C22 15.4183 18.4183 19 14 19V22.5C9 20.5 2 17.5 2 11C2 6.58172 5.58172 3 10 3ZM12 17H14C17.3137 17 20 14.3137 20 11C20 7.68629 17.3137 5 14 5H10C6.68629 5 4 7.68629 4 11C4 14.61 6.46208 16.9656 12 19.4798V17Z"></path></svg>
                            <span>${commentLabel}</span>
                        </a>
                        <button type="button" class="thread-action-button flex items-center gap-2 hover:text-pink-600" data-share-trigger data-share-title="${shareTitle}" data-share-url="${shareUrl}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4" aria-hidden="true"><path d="M13.1202 17.0228L8.92129 14.7324C8.19135 15.5125 7.15261 16 6 16C3.79086 16 2 14.2091 2 12C2 9.79086 3.79086 8 6 8C7.15255 8 8.19125 8.48746 8.92118 9.26746L13.1202 6.97713C13.0417 6.66441 13 6.33707 13 6C13 3.79086 14.7909 2 17 2C19.2091 2 21 3.79086 21 6C21 8.20914 19.2091 10 17 10C15.8474 10 14.8087 9.51251 14.0787 8.73246L9.87977 11.0228C9.9583 11.3355 10 11.6629 10 12C10 12.3371 9.95831 12.6644 9.87981 12.9771L14.0788 15.2675C14.8087 14.4875 15.8474 14 17 14C19.2091 14 21 15.7909 21 18C21 20.2091 19.2091 22 17 22C14.7909 22 13 20.2091 13 18C13 17.6629 13.0417 17.3355 13.1202 17.0228ZM6 14C7.10457 14 8 13.1046 8 12C8 10.8954 7.10457 10 6 10C4.89543 10 4 10.8954 4 12C4 13.1046 4.89543 14 6 14ZM17 8C18.1046 8 19 7.10457 19 6C19 4.89543 18.1046 4 17 4C15.8954 4 15 4.89543 15 6C15 7.10457 15.8954 8 17 8ZM17 20C18.1046 20 19 19.1046 19 18C19 16.8954 18.1046 16 17 16C15.8954 16 15 16.8954 15 18C15 19.1046 15.8954 20 17 20Z"></path></svg>
                            <span>${shareLabel}</span>
                        </button>
                        ${bookmarkButton}
                    </div>
                </div>
            </div>
        `;
    }

    function hydrateThreadCardElement(article, thread) {
        if (!article || article.dataset.hydrated === '1') {
            return;
        }

        const voteWrapper = article.querySelector('[data-vote-wrapper]');
        const scoreEl = article.querySelector('[data-score]');
        const upvote = article.querySelector('[data-vote="up"]');
        const downvote = article.querySelector('[data-vote="down"]');
        const bookmarkButton = article.querySelector('[data-bookmark-button]');
        const shareButton = article.querySelector('[data-share-trigger]');
        let threadId = 0;
        if (thread && typeof thread.id !== 'undefined' && thread.id !== null) {
            const numericId = Number(thread.id);
            threadId = Number.isFinite(numericId) ? numericId : parseInt(String(thread.id), 10) || 0;
        }
        if (!threadId) {
            const articleAttr = article.getAttribute('data-thread-id');
            if (articleAttr) {
                const parsedArticleId = parseInt(articleAttr, 10);
                if (Number.isFinite(parsedArticleId)) {
                    threadId = parsedArticleId;
                }
            }
        }
        if (!threadId && voteWrapper) {
            const voteAttr = voteWrapper.getAttribute('data-thread-id');
            if (voteAttr) {
                const parsedVoteId = parseInt(voteAttr, 10);
                if (Number.isFinite(parsedVoteId)) {
                    threadId = parsedVoteId;
                }
            }
        }
        const shareTitle = shareButton
            ? decodeEntities(shareButton.getAttribute('data-share-title') || '')
            : (thread && thread.title ? decodeEntities(thread.title) : '');
        const shareUrl = shareButton
            ? decodeEntities(shareButton.getAttribute('data-share-url') || '')
            : (thread && thread.permalink ? decodeEntities(thread.permalink) : '');

        if (shareButton) {
            shareButton.addEventListener('click', (event) => {
                event.preventDefault();
                openShare({
                    title: shareTitle || document.title,
                    url: shareUrl || window.location.href,
                });
            });
        }

        if (bookmarkButton) {
            updateBookmarkButtonState(bookmarkButton, bookmarkButton.dataset.bookmarked === 'true');

            bookmarkButton.addEventListener('click', (event) => {
                event.preventDefault();

                if (!bookmarkService || !bookmarkService.isLoggedIn) {
                    const message = (bookmarkService && bookmarkService.labels && bookmarkService.labels.loginRequired)
                        ? bookmarkService.labels.loginRequired
                        : 'Please sign in to save threads.';
                    alert(message);
                    return;
                }

                const endpoint = bookmarkButton.getAttribute('data-bookmark-endpoint');
                if (!endpoint) {
                    alert((bookmarkService && bookmarkService.labels && bookmarkService.labels.error) || 'We could not update your bookmark. Please try again.');
                    return;
                }

                bookmarkButton.disabled = true;

                bookmarkService.toggle(endpoint)
                    .then((data) => {
                        const isBookmarked = Boolean(data && data.is_bookmarked);
                        updateBookmarkButtonState(bookmarkButton, isBookmarked);
                    })
                    .catch((error) => {
                        if (error && error.code === 'not_logged_in') {
                            alert((bookmarkService && bookmarkService.labels && bookmarkService.labels.loginRequired) || 'Please sign in to save threads.');
                        } else {
                            alert((bookmarkService && bookmarkService.labels && bookmarkService.labels.error) || 'We could not update your bookmark. Please try again.');
                        }
                    })
                    .finally(() => {
                        bookmarkButton.disabled = false;
                    });
            });
        }

        if (voteWrapper && threadId) {
            voteWrapper.addEventListener('click', (event) => {
                const target = event.target.closest('[data-vote]');
                if (!target) {
                    return;
                }

                if (!GTA6ForumMain.isLoggedIn) {
                    alert(GTA6ForumMain.texts.loginToVote);
                    return;
                }

                const direction = target.getAttribute('data-vote');
                submitVote(threadId, direction)
                    .then((data) => {
                        if (scoreEl && typeof data.score !== 'undefined') {
                            scoreEl.textContent = data.score;
                        }
                        if (upvote) {
                            upvote.classList.toggle('upvoted', data.user_vote === 1);
                        }
                        if (downvote) {
                            downvote.classList.toggle('downvoted', data.user_vote === -1);
                        }
                    })
                    .catch(() => {
                        alert(GTA6ForumMain.texts.voteError);
                    });
            });
        }

        article.dataset.hydrated = '1';
    }

    function hydrateExistingCards() {
        if (!list) {
            return;
        }

        list.querySelectorAll('.thread-card').forEach((article) => {
            hydrateThreadCardElement(article);
        });
    }

    function updateLoadMoreVisibility() {
        if (!loadMoreButton) {
            return;
        }

        if (state.page >= state.totalPages) {
            loadMoreButton.classList.add('hidden');
        } else {
            loadMoreButton.classList.remove('hidden');
        }
    }

    function updateBookmarkButtonState(button, isBookmarked) {
        if (!button) {
            return;
        }

        button.dataset.bookmarked = isBookmarked ? 'true' : 'false';
        button.classList.toggle('is-active', Boolean(isBookmarked));

        const icon = button.querySelector('[data-bookmark-icon]');
        if (icon) {
            icon.setAttribute('data-bookmarked', isBookmarked ? 'true' : 'false');
        }

        const label = button.querySelector('[data-bookmark-label]');
        if (label && bookmarkService && bookmarkService.labels) {
            label.textContent = isBookmarked
                ? (bookmarkService.labels.added || 'Saved')
                : (bookmarkService.labels.add || 'Bookmark');
        } else if (label) {
            label.textContent = isBookmarked ? 'Saved' : 'Bookmark';
        }
    }

    function setLoading(isLoading) {
        state.loading = isLoading;
        if (loadMoreButton) {
            loadMoreButton.disabled = isLoading;
            if (isLoading) {
                loadMoreButton.classList.add('hidden');
            }
        }

        if (status) {
            if (isLoading) {
                const key = state.searchTerm ? 'searching' : 'loading';
                status.textContent = GTA6ForumMain.texts[key] || GTA6ForumMain.texts.loading;
            } else if (!status.dataset.locked) {
                status.textContent = '';
            }
        }
    }

    function renderThreads(threads, reset = false) {
        if (!list) {
            return;
        }

        if (reset) {
            list.innerHTML = '';
        }

        if (!Array.isArray(threads) || !threads.length) {
            if (reset && status) {
                status.dataset.locked = '1';
                if (state.searchTerm) {
                    const template = GTA6ForumMain.texts.searchNoResults || 'No threads matched “%s”.';
                    status.textContent = template.replace('%s', state.searchTerm);
                } else {
                    status.textContent = GTA6ForumMain.texts.noThreads;
                }
            }
            return;
        }

        if (status) {
            delete status.dataset.locked;
            status.textContent = '';
        }

        const fragment = document.createDocumentFragment();
        threads.forEach((thread) => {
            fragment.appendChild(createThreadCard(thread));
        });

        list.appendChild(fragment);
    }

    function createThreadCard(thread) {
        const article = document.createElement('article');
        article.className = 'card thread-card';
        if (thread && thread.id) {
            article.setAttribute('data-thread-id', String(thread.id));
        }
        if (thread && thread.type) {
            article.setAttribute('data-thread-type', String(thread.type));
        }
        article.innerHTML = buildThreadCardInnerHtml(thread || {});
        hydrateThreadCardElement(article, thread || null);
        return article;
    }

    function submitVote(threadId, direction) {
        const url = `${GTA6ForumMain.root}threads/${threadId}/vote`;
        return wp.apiFetch({
            url,
            method: 'POST',
            headers: {
                'X-WP-Nonce': GTA6ForumMain.nonce,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ direction }),
        });
    }

    function fetchThreads(reset = false) {
        const requestedPage = state.page;

        if (activeAbortController) {
            activeAbortController.abort();
        }

        const controller = new AbortController();
        activeAbortController = controller;
        const requestToken = ++activeRequestToken;

        setLoading(true);

        if (reset && list) {
            list.innerHTML = '';
        }

        const params = new URLSearchParams();
        params.append('page', String(state.page));
        params.append('per_page', String(state.perPage));
        params.append('sort', state.sort);
        if (state.sort === 'top') {
            params.append('time_range', state.timeRange || 'all-time');
        }
        if (state.flair) {
            params.append('flair', state.flair);
        }
        if (state.searchTerm) {
            params.append('search', state.searchTerm);
        }

        const url = `${GTA6ForumMain.root}threads?${params.toString()}`;

        wp.apiFetch({ url, signal: controller.signal })
            .then((response) => {
                if (requestToken !== activeRequestToken) {
                    return;
                }

                const pagination = response && typeof response === 'object' ? response.pagination : null;
                let totalPages = null;
                if (pagination && typeof pagination.total_pages !== 'undefined') {
                    const parsedTotal = Number(pagination.total_pages);
                    totalPages = Number.isFinite(parsedTotal)
                        ? parsedTotal
                        : parseInt(String(pagination.total_pages), 10) || 0;
                }
                if (typeof totalPages === 'number' && Number.isFinite(totalPages)) {
                    state.totalPages = Math.max(0, totalPages);
                } else {
                    state.totalPages = 1;
                }
                renderThreads(response && Array.isArray(response.threads) ? response.threads : [], reset);
                updateLoadMoreVisibility();
            })
            .catch((error) => {
                if (requestToken !== activeRequestToken) {
                    return;
                }

                if (error && (error.name === 'AbortError' || error.code === 'abort')) {
                    return;
                }

                if (requestedPage > 1) {
                    state.page = Math.max(1, requestedPage - 1);
                }

                if (status) {
                    status.dataset.locked = '1';
                    if (state.searchTerm) {
                        const template = GTA6ForumMain.texts.searchNoResults || 'No threads matched “%s”.';
                        status.textContent = template.replace('%s', state.searchTerm);
                    } else {
                        status.textContent = GTA6ForumMain.texts.noThreads;
                    }
                }

                updateLoadMoreVisibility();
            })
            .finally(() => {
                if (requestToken === activeRequestToken) {
                    activeAbortController = null;
                    setLoading(false);
                }
            });
    }

    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', () => {
            if (state.page < state.totalPages) {
                state.page += 1;
                fetchThreads(false);
            }
        });
    }

    sortButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const sortValue = button.getAttribute('data-sort') || '';
            if (!sortValue || sortValue === state.sort) {
                return;
            }

            const nextSort = ALLOWED_SORTS.includes(sortValue) ? sortValue : 'hot';
            if (nextSort === state.sort) {
                return;
            }

            state.sort = nextSort;
            state.page = 1;

            if (state.sort !== 'top') {
                state.timeRange = 'all-time';
            } else if (!ALLOWED_TIME_RANGES.includes(state.timeRange)) {
                state.timeRange = 'all-time';
            }

            setActiveSortButton();
            updateTopRangeVisibility();
            updateHistoryState();
            fetchThreads(true);
        });
    });

    if (topRangeSelect) {
        topRangeSelect.addEventListener('change', () => {
            if (state.sort !== 'top') {
                updateTopRangeVisibility();
                return;
            }

            const selectedValue = topRangeSelect.value || '';
            const normalised = ALLOWED_TIME_RANGES.includes(selectedValue) ? selectedValue : 'all-time';
            if (normalised === state.timeRange) {
                return;
            }

            state.timeRange = normalised;
            state.page = 1;
            updateHistoryState();
            fetchThreads(true);
        });
    }

    flairButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            const slug = button.getAttribute('data-flair') || '';
            const isModifier = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
            if (isModifier) {
                return;
            }
            event.preventDefault();

            if (slug === state.flair) {
                state.flair = '';
                state.page = 1;
                updateFlairSelection();
                updateHistoryState();
                fetchThreads(true);
                return;
            }

            state.flair = slug;
            state.page = 1;
            updateFlairSelection();
            updateHistoryState();
            fetchThreads(true);
        });
    });

    if (searchForm && searchInput) {
        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const nextSearch = searchInput.value ? searchInput.value.trim() : '';

            markSearchFieldState();

            if (nextSearch === state.searchTerm) {
                return;
            }

            state.searchTerm = nextSearch;
            state.page = 1;
            updateHistoryState();
            fetchThreads(true);
        });

        searchInput.addEventListener('input', () => {
            markSearchFieldState();
        });

        searchInput.addEventListener('search', () => {
            markSearchFieldState();
            if (state.searchTerm !== '' && (!searchInput.value || searchInput.value.trim() === '')) {
                state.searchTerm = '';
                state.page = 1;
                updateHistoryState();
                fetchThreads(true);
            }
        });
    }

    if (searchClearButton && searchInput) {
        searchClearButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (searchInput.value === '') {
                searchInput.focus();
                return;
            }

            searchInput.value = '';
            markSearchFieldState();
            if (state.searchTerm !== '') {
                state.searchTerm = '';
                state.page = 1;
                updateHistoryState();
                fetchThreads(true);
            }
            searchInput.focus();
        });
    }

    if (!state.flair) {
        const detectedFlair = resolveFlairFromLocation();
        if (detectedFlair) {
            state.flair = detectedFlair;
        }
    }

    updateFlairSelection();
    setActiveSortButton();

    window.addEventListener('popstate', () => {
        const slug = resolveFlairFromLocation();
        const term = resolveSearchFromLocation();
        const sortFromLocation = resolveSortFromLocation();
        const rangeFromLocation = resolveTimeRangeFromLocation();

        state.flair = slug;
        state.searchTerm = term;
        state.sort = ALLOWED_SORTS.includes(sortFromLocation) ? sortFromLocation : 'hot';
        state.timeRange = state.sort === 'top'
            ? (ALLOWED_TIME_RANGES.includes(rangeFromLocation) ? rangeFromLocation : 'all-time')
            : 'all-time';
        state.page = 1;

        if (searchInput) {
            searchInput.value = term;
            markSearchFieldState();
        }

        updateFlairSelection();
        setActiveSortButton();
        fetchThreads(true);
    });

    if (hasInitial) {
        hydrateExistingCards();
        updateLoadMoreVisibility();
    } else {
        fetchThreads(true);
    }
})();
