(function () {
    'use strict';

    let editor = null;

    const youtubeStrings = {
        urlLabel: 'YouTube URL',
        urlPlaceholder: 'Paste a YouTube link (e.g. https://youtu.be/...)',
        helper: 'Supports standard, share and Shorts links.',
        captionLabel: 'Caption',
        captionPlaceholder: 'Optional caption',
        placeholder: 'Paste a YouTube link to preview the video.',
        invalidMessage: 'Please enter a valid YouTube URL.',
    };

    const debounce = (fn, delay = 300) => {
        let timeoutId;
        return (...args) => {
            window.clearTimeout(timeoutId);
            timeoutId = window.setTimeout(() => fn(...args), delay);
        };
    };

    const extractYoutubeId = (url) => {
        if (!url) {
            return '';
        }

        try {
            const parsed = new URL(url.trim());
            const hostname = parsed.hostname.toLowerCase();
            const normalizedHost = hostname.replace(/^www\./, '');

            if (normalizedHost === 'youtu.be') {
                const pathParts = parsed.pathname.split('/').filter(Boolean);
                return pathParts[0] || '';
            }

            if (normalizedHost === 'youtube.com' || normalizedHost === 'm.youtube.com' || normalizedHost.endsWith('.youtube.com') || normalizedHost === 'youtube-nocookie.com') {
                if (parsed.pathname.startsWith('/embed/')) {
                    return parsed.pathname.split('/')[2] || '';
                }
                if (parsed.pathname.startsWith('/shorts/')) {
                    return parsed.pathname.split('/')[2] || '';
                }
                if (parsed.pathname.startsWith('/live/')) {
                    return parsed.pathname.split('/')[2] || '';
                }
                return parsed.searchParams.get('v') || '';
            }
        } catch (error) {
            return '';
        }

        return '';
    };

    const getYoutubeEmbedUrl = (videoId) => (videoId ? `https://www.youtube.com/embed/${videoId}?rel=0` : '');
    const getYoutubeCanonicalUrl = (videoId) => (videoId ? `https://www.youtube.com/watch?v=${videoId}` : '');

    const state = {
        screenshots: [],
        deletedExisting: new Set(),
        changelog: [],
        currentVersionNumber: '',
        newScreenshotTempIndex: 0,
        additionalAuthors: [],
        submitButtonDefaultLabel: '',
        uploadingTitleDefault: '',
        uploadingStatusDefault: '',
    };

    const dom = {};

    const utils = (typeof window !== 'undefined' && window.GTAModsUtils) ? window.GTAModsUtils : {};
    const buildRestHeaders = typeof utils.buildRestHeaders === 'function'
        ? utils.buildRestHeaders
        : (nonce, extra = {}) => {
            const headers = { ...extra };
            if (nonce) {
                headers['X-WP-Nonce'] = nonce;
            }
            return headers;
        };

    const numberFormatter = (typeof Intl !== 'undefined' && typeof Intl.NumberFormat === 'function')
        ? new Intl.NumberFormat('hu-HU')
        : null;

    const formatNumber = (value) => {
        const numeric = Number(value);
        if (!Number.isFinite(numeric) || numeric < 0) {
            return '0';
        }

        if (numberFormatter) {
            return numberFormatter.format(numeric);
        }

        return String(Math.round(numeric));
    };

    const parseCountFromElement = (element) => {
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
    };

    const updateElementsWithCount = (elements, nextValue) => {
        if (!elements || !elements.length) {
            return;
        }

        const formatted = formatNumber(nextValue);

        elements.forEach((element) => {
            element.textContent = formatted;
            element.setAttribute('data-download-count-raw', String(nextValue));
        });
    };

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

        if (dom.statsDownloads && formattedDownloads) {
            dom.statsDownloads.textContent = formattedDownloads;
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
            const first = totalCounters[0];
            const current = parseCountFromElement(first);
            const nextValue = current + 1;
            updateElementsWithCount(totalCounters, nextValue);
            if (dom.statsDownloads) {
                dom.statsDownloads.textContent = formatNumber(nextValue);
                dom.statsDownloads.setAttribute('data-download-count-raw', String(nextValue));
            }
        }

        if (versionId) {
            const versionElements = document.querySelectorAll(`[data-version-downloads="${versionId}"]`);
            if (versionElements.length) {
                const currentVersion = parseCountFromElement(versionElements[0]);
                const nextVersion = currentVersion + 1;
                updateElementsWithCount(versionElements, nextVersion);
            }
        }
    };

    const sendDownloadIncrement = (versionId) => {
        const config = window.GTAModsUpdatePage || {};
        const endpoint = typeof config.downloadIncrementUrl === 'string' ? config.downloadIncrementUrl : '';
        if (!endpoint) {
            return Promise.resolve(null);
        }

        const headers = buildRestHeaders(config.restNonce, config.trackingNonce ? { 'X-GTA6-Nonce': config.trackingNonce } : {});
        headers['Content-Type'] = 'application/json';

        const body = versionId ? { versionId } : {};

        return fetch(endpoint, {
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

    const dynamicDomKeys = [
        'form',
        'errorsContainer',
        'fileNameInput',
        'categorySelect',
        'authorsContainer',
        'addAuthorBtn',
        'tagsInput',
        'description',
        'editorContainer',
        'videoPermissions',
        'screenshotInput',
        'screenshotDropzone',
        'screenshotsContainer',
        'addChangelogBtn',
        'newChangelogInput',
        'changelogList',
        'fileUploadWrapper',
        'modUrlWrapper',
        'showUrlViewBtn',
        'showFileViewBtn',
        'modFileInput',
        'modDropzoneLabel',
        'modDropzoneContent',
        'modFilePreview',
        'modUrlInput',
        'fileSizeValueInput',
        'fileSizeUnitSelect',
        'versionScanInput',
        'newVersionInput',
        'versionsContainer',
        'submitButton',
        'statsLikes',
        'statsViews',
        'statsDownloads',
    ];

    const escapeHTML = (value) => {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const resetDynamicDomReferences = () => {
        dynamicDomKeys.forEach((key) => {
            if (key === 'videoPermissions') {
                dom[key] = [];
            } else {
                dom[key] = null;
            }
        });
    };

    const captureStaticDomReferences = () => {
        dom.root = document.getElementById('update-mod-root');
        dom.uploadOverlay = document.getElementById('uploading-overlay');
        dom.uploadSpinner = document.getElementById('uploading-spinner');
        dom.progressContainer = document.getElementById('progress-container');
        dom.uploadProgressBar = document.getElementById('upload-progress-bar');
        dom.uploadProgressText = document.getElementById('upload-progress-text');
        dom.uploadSpeedText = document.getElementById('upload-speed-text');
        dom.uploadEtaText = document.getElementById('upload-eta-text');
        dom.uploadingTitle = document.getElementById('uploading-title');
        dom.uploadingStatus = document.getElementById('uploading-status');

        if (dom.uploadingTitle && !state.uploadingTitleDefault) {
            state.uploadingTitleDefault = dom.uploadingTitle.textContent;
        }

        if (dom.uploadingStatus && !state.uploadingStatusDefault) {
            state.uploadingStatusDefault = dom.uploadingStatus.textContent;
        }
    };

    const captureDynamicDomReferences = () => {
        resetDynamicDomReferences();

        dom.form = document.getElementById('update-form');
        dom.errorsContainer = document.getElementById('form-errors');
        dom.fileNameInput = document.getElementById('file-name');
        dom.categorySelect = document.getElementById('category');
        dom.authorsContainer = document.getElementById('authors-container');
        dom.addAuthorBtn = document.getElementById('add-author-btn');
        dom.tagsInput = document.getElementById('tags');
        dom.description = document.getElementById('description');
        dom.editorContainer = document.getElementById('editorjs-container');
        dom.videoPermissions = document.querySelectorAll('input[name="video-permissions"]');
        dom.screenshotInput = document.getElementById('screenshot-upload');
        dom.screenshotDropzone = document.getElementById('screenshot-dropzone');
        dom.screenshotsContainer = document.getElementById('screenshots-container');
        dom.addChangelogBtn = document.getElementById('add-changelog-btn');
        dom.newChangelogInput = document.getElementById('new-changelog-item');
        dom.changelogList = document.getElementById('changelog-list');
        dom.fileUploadWrapper = document.getElementById('file-upload-view');
        dom.modUrlWrapper = document.getElementById('url-view');
        dom.showUrlViewBtn = document.getElementById('show-url-view-btn');
        dom.showFileViewBtn = document.getElementById('show-file-view-btn');
        dom.modFileInput = document.getElementById('mod-file-input');
        dom.modDropzoneLabel = document.getElementById('mod-dropzone-label');
        dom.modDropzoneContent = document.getElementById('mod-dropzone-content');
        dom.modFilePreview = document.getElementById('mod-file-preview');
        dom.modUrlInput = document.getElementById('mod-url-input');
        dom.fileSizeValueInput = document.getElementById('file-size-input');
        dom.fileSizeUnitSelect = document.getElementById('file-size-unit');
        dom.versionScanInput = document.getElementById('version-scan-url');
        dom.newVersionInput = document.getElementById('new-version');
        dom.versionsContainer = document.getElementById('existing-versions');
        dom.submitButton = dom.form ? dom.form.querySelector('button[type="submit"]') : null;
        dom.statsLikes = dom.root ? dom.root.querySelector('[data-total-likes]') : null;
        dom.statsViews = dom.root ? dom.root.querySelector('[data-total-views]') : null;
        dom.statsDownloads = dom.root ? dom.root.querySelector('[data-total-downloads]') : null;

        if (dom.submitButton) {
            state.submitButtonDefaultLabel = dom.submitButton.textContent;
        }
    };

    const buildFormTemplate = (config) => {
        const text = config && config.text ? config.text : {};
        const labels = config && config.labels ? config.labels : {};
        const cancelUrl = config && typeof config.modPermalink === 'string' ? config.modPermalink : '#';

        const getText = (key, fallback) => escapeHTML(Object.prototype.hasOwnProperty.call(text, key) ? text[key] : fallback);

        return `
            <div class="card p-6 md:p-8">
                <form id="update-form" action="#" method="post" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <h3 class="text-xl font-bold text-gray-900 -mb-2">${getText('editBasicInformation', 'Edit Basic Information')}</h3>
                            <div>
                                <label for="file-name" class="form-label">${getText('fileNameLabel', 'File Name')}</label>
                                <input type="text" id="file-name" name="file-name" class="form-input" required>
                            </div>
                            <div>
                                <label for="category" class="form-label">${getText('categoryLabel', 'Category')}</label>
                                <select id="category" name="category" class="form-select"></select>
                            </div>
                            <div>
                                <label class="form-label">${getText('authorsLabel', 'Author(s)')}</label>
                                <div id="authors-container" class="space-y-2"></div>
                                <button type="button" id="add-author-btn" class="mt-2 text-sm font-semibold text-pink-600 hover:text-pink-800 transition">+ ${getText('addAuthor', 'Add Author')}</button>
                            </div>
                            <div>
                                <label for="tags" class="form-label">${getText('tagsLabel', 'Tags')}</label>
                                <input type="text" id="tags" name="tags" class="form-input" placeholder="${getText('tagsPlaceholder', 'e.g. car, addon, tuning')}">
                            </div>
                            <div>
                                <label id="description-label" for="editorjs-container" class="form-label">${getText('descriptionLabel', 'Description')}</label>
                                <div id="editorjs-container" role="textbox" aria-multiline="true" aria-labelledby="description-label"></div>
                                <input type="hidden" id="description" name="description">
                                <p class="text-xs text-gray-500 mt-1">${getText('descriptionHelper', 'Use the formatting toolbar to describe your mod and provide installation notes.')}</p>
                            </div>
                        </div>
                        <div class="lg:col-span-1 space-y-6">
                            <div>
                                <label class="form-label">${getText('manageScreenshots', 'Manage Screenshots')}</label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="screenshot-upload" id="screenshot-dropzone" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400"></i>
                                            <p class="my-2 text-sm text-gray-500"><span class="font-semibold">${getText('clickToUpload', 'Click to upload')}</span> ${getText('orDragAndDrop', 'or drag and drop')}</p>
                                            <p class="text-xs text-gray-500">${getText('screenshotFileTypes', 'JPG, PNG, WEBP (max. 10MB)')}</p>
                                        </div>
                                        <input id="screenshot-upload" type="file" class="hidden" accept="image/png, image/jpeg, image/webp" multiple>
                                    </label>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">${getText('screenshotNote', 'The first image becomes the featured image. Drag to reorder.')}</p>
                                <div id="screenshots-container" class="flex flex-wrap gap-4 mt-4"></div>
                            </div>
                            <div>
                                <h3 class="form-label">${getText('fileSettings', 'File Settings')}</h3>
                                <div class="info-box space-y-3">
                                    <h4 class="font-semibold text-gray-800">${getText('videoPermissionsTitle', 'Video Upload Permissions')}</h4>
                                    <div class="flex items-center"><input id="video-deny" name="video-permissions" type="radio" class="h-4 w-4 text-pink-600 border-gray-300 focus:ring-pink-500" value="deny"><label for="video-deny" class="ml-3 block text-sm font-medium text-gray-700">${getText('videoDeny', 'Deny')}</label></div>
                                    <div class="flex items-center"><input id="video-moderate" name="video-permissions" type="radio" class="h-4 w-4 text-pink-600 border-gray-300 focus:ring-pink-500" value="moderate"><label for="video-moderate" class="ml-3 block text-sm font-medium text-gray-700">${getText('videoModerate', 'Self moderate')}</label></div>
                                    <div class="flex items-center"><input id="video-allow" name="video-permissions" type="radio" class="h-4 w-4 text-pink-600 border-gray-300 focus:ring-pink-500" value="allow"><label for="video-allow" class="ml-3 block text-sm font-medium text-gray-700">${getText('videoAllow', 'Allow')}</label></div>
                                </div>
                            </div>
                            <div class="info-box">
                                <h3 class="text-gray-900 font-bold mb-3">${getText('totalStats', 'Total Stats')}</h3>
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div><span class="block text-2xl font-bold text-gray-800" data-total-likes>0</span><span class="text-xs text-gray-500">${getText('likes', 'Likes')}</span></div>
                                    <div><span class="block text-2xl font-bold text-gray-800" data-total-views>0</span><span class="text-xs text-gray-500">${getText('views', 'Views')}</span></div>
                                    <div><span class="block text-2xl font-bold text-gray-800" data-total-downloads>0</span><span class="text-xs text-gray-500">${getText('downloads', 'Downloads')}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="border-t mt-10 pt-6">
                        <h3 class="text-2xl font-bold text-gray-800">${getText('versionsTitle', 'Version(s)')}</h3>
                        <p class="text-gray-600 mt-1 mb-4">${getText('versionsSubtitle', 'You can only upload new versions. Existing files cannot be removed or modified.')}</p>
                        <div class="space-y-3">
                            <details class="rounded-lg" id="version-upload-details">
                                <summary class="list-none p-4 rounded-lg border-2 border-dashed border-gray-300 text-center text-gray-500 font-semibold cursor-pointer hover:border-pink-500 hover:text-pink-600 transition open:rounded-b-none open:border-solid open:border-gray-300 open:text-pink-600"><i class="fas fa-plus-circle mr-2"></i>${getText('uploadNewVersion', 'Upload a New Version')}</summary>
                                <div class="p-6 rounded-b-lg border-2 border-solid border-gray-300 border-t-0 bg-gray-50">
                                    <div class="space-y-6">
                                        <div>
                                            <label for="new-version" class="form-label">${getText('newVersionNumber', 'New Version Number')}</label>
                                            <input type="text" id="new-version" name="new-version" class="form-input" placeholder="${getText('newVersionPlaceholder', 'e.g. 2.2.0')}">
                                            <p class="text-xs text-gray-500 mt-1">${getText('newVersionHelper', 'The new version number must be higher than the current one.')}</p>
                                        </div>
                                        <div id="file-upload-view">
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="form-label !mb-0">${getText('newModFile', 'New Mod File')}</label>
                                                <button type="button" id="show-url-view-btn" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition">${getText('orProvideDownloadLink', 'Or provide a download link')}</button>
                                            </div>
                                            <div id="mod-file-upload-container">
                                                <label for="mod-file-input" id="mod-dropzone-label" class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-white hover:bg-gray-100">
                                                    <div id="mod-dropzone-content" class="flex flex-col items-center justify-center pt-5 pb-6 text-center">
                                                        <i class="fas fa-file-archive text-5xl text-gray-400"></i>
                                                        <p class="my-2 text-gray-500"><span class="font-semibold">${getText('clickToUpload', 'Click to upload')}</span> ${getText('orDragAndDrop', 'or drag and drop')}</p>
                                                        <p class="text-xs text-gray-500">${getText('allowedFileTypes', 'Allowed: .zip, .rar, .7z, .oiv (max. 400MB)')}</p>
                                                    </div>
                                                    <div id="mod-file-preview" class="hidden items-center justify-center p-4 text-center"></div>
                                                    <input type="file" id="mod-file-input" class="hidden" accept=".zip,.rar,.7z,.oiv">
                                                </label>
                                            </div>
                                        </div>
                                        <div id="url-view" class="hidden">
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="form-label !mb-0" for="mod-url-input">${getText('downloadUrlLabel', 'Download URL')}</label>
                                                <button type="button" id="show-file-view-btn" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition">${getText('orUploadFile', 'Or upload a file')}</button>
                                            </div>
                                            <div class="space-y-4">
                                                <input type="url" id="mod-url-input" name="mod-url" class="form-url" placeholder="${getText('downloadUrlPlaceholder', 'https://...')}">
                                                <div>
                                                    <label for="file-size-input" class="form-label text-sm">${getText('fileSizeLabel', 'File Size')}</label>
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" id="file-size-input" name="file-size" class="form-input" placeholder="850" min="0">
                                                        <select id="file-size-unit" name="file-size-unit" class="form-select !w-auto">
                                                            <option value="MB">MB</option>
                                                            <option value="GB">GB</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="new-changelog-item" class="form-label">${getText('changelogLabel', 'Changelog (What changed in this version?)')}</label>
                                            <div id="changelog-list" class="space-y-2 mb-3"></div>
                                            <div class="flex gap-2">
                                                <input type="text" id="new-changelog-item" class="form-input flex-1" placeholder="${getText('changelogPlaceholder', 'e.g. Fixed handling issues')}">
                                                <button type="button" id="add-changelog-btn" class="btn-secondary flex-shrink-0 font-semibold px-4 py-2 rounded-lg">${getText('addButton', 'Add')}</button>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">${getText('changelogHelper', 'Add each change as a separate entry.')}</p>
                                        </div>
                                    </div>
                                </div>
                            </details>
                            <div id="existing-versions" class="space-y-3"></div>
                        </div>
                    </div>
                    <div id="form-errors" class="my-6"></div>
                    <div class="flex items-center justify-end space-x-4 border-t mt-8 pt-6">
                        <a href="${escapeHTML(cancelUrl)}" class="font-bold px-6 py-2 rounded-lg transition btn-secondary">${getText('cancel', 'Cancel')}</a>
                        <button type="submit" class="text-lg font-bold px-8 py-3 rounded-lg transition btn-action">${getText('submitUpdate', 'Submit Update')}</button>
                    </div>
                </form>
            </div>
        `;
    };

    const renderLoadingState = () => {
        if (!dom.root) {
            return;
        }

        resetDynamicDomReferences();

        const labels = (window.GTAModsUpdatePage && window.GTAModsUpdatePage.labels) || {};
        const message = labels.loadingModData || 'Loading mod data…';

        dom.root.innerHTML = `
            <div class="card p-6 md:p-8 flex items-center justify-center">
                <div class="flex items-center gap-3 text-gray-500">
                    <span class="inline-flex h-5 w-5 border-2 border-pink-500 border-t-transparent rounded-full animate-spin"></span>
                    <span>${escapeHTML(message)}</span>
                </div>
            </div>
        `;
    };

    const renderErrorState = (message, retryCallback) => {
        if (!dom.root) {
            return;
        }

        resetDynamicDomReferences();

        const labels = (window.GTAModsUpdatePage && window.GTAModsUpdatePage.labels) || {};
        const displayMessage = message || labels.loadError || 'Failed to load mod data.';
        const retryLabel = labels.retry || 'Retry';
        const shouldShowRetry = typeof retryCallback === 'function';

        dom.root.innerHTML = `
            <div class="card p-6 md:p-8">
                <div class="space-y-4 text-center">
                    <p class="text-gray-700">${escapeHTML(displayMessage)}</p>
                    ${shouldShowRetry ? `<button type="button" class="px-4 py-2 font-semibold rounded-lg btn-action" data-retry-fetch>${escapeHTML(retryLabel)}</button>` : ''}
                </div>
            </div>
        `;

        if (shouldShowRetry) {
            const retryButton = dom.root.querySelector('[data-retry-fetch]');
            if (retryButton) {
                retryButton.addEventListener('click', () => retryCallback());
            }
        }
    };

    const fetchModDataFromAPI = async () => {
        const config = window.GTAModsUpdatePage || {};
        const headers = { Accept: 'application/json' };

        if (config.restNonce) {
            headers['X-WP-Nonce'] = config.restNonce;
        }

        const response = await fetch(config.modDataUrl, {
            credentials: 'same-origin',
            headers,
        });

        let payload = null;

        if (response.status !== 204) {
            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }
        }

        if (!response.ok) {
            const errorMessage = payload && typeof payload === 'object' && payload !== null && payload.message
                ? payload.message
                : (config.labels && config.labels.loadError) || 'Failed to load mod data.';
            throw new Error(errorMessage);
        }

        if (!payload || typeof payload !== 'object') {
            throw new Error((config.labels && config.labels.loadError) || 'Failed to load mod data.');
        }

        return payload;
    };

    const renderFullForm = (initialData) => {
        if (!dom.root) {
            return;
        }

        state.screenshots = [];
        state.deletedExisting = new Set();
        state.changelog = [];
        state.newScreenshotTempIndex = 0;
        state.additionalAuthors = [];

        Object.assign(window.GTAModsUpdatePage, initialData || {});

        dom.root.innerHTML = buildFormTemplate(window.GTAModsUpdatePage);

        captureDynamicDomReferences();
        clearErrors();

        populateBasicInfo();
        setupCategoryNavInteractions();
        hydrateAuthors();
        hydrateChangelog();
        hydrateScreenshots();
        renderScreenshots();
        renderChangelog();
        renderAuthors();
        populateVersions();
        setupDragAndDrop();
        bindEvents();
        initializeEditor();
    };

    const loadModData = async () => {
        const config = window.GTAModsUpdatePage || {};

        if (!dom.root) {
            return;
        }

        if (!config.modDataUrl) {
            renderErrorState((config.labels && config.labels.loadError) || 'Failed to load mod data.');
            return;
        }

        renderLoadingState();

        try {
            const data = await fetchModDataFromAPI();
            renderFullForm(data);
        } catch (error) {
            console.error('Failed to load mod data', error);
            const fallback = (window.GTAModsUpdatePage && window.GTAModsUpdatePage.labels && window.GTAModsUpdatePage.labels.loadError)
                ? window.GTAModsUpdatePage.labels.loadError
                : 'Failed to load mod data.';
            renderErrorState(error && error.message ? error.message : fallback, loadModData);
        }
    };

    let categoryNavLinks = [];

    const getCategoryNavLinks = () => {
        if (!categoryNavLinks.length) {
            categoryNavLinks = Array.from(document.querySelectorAll('.header-nav-bar nav a'));
        }

        return categoryNavLinks;
    };

    const prepareUploadOverlay = () => {
        if (dom.uploadSpinner) {
            dom.uploadSpinner.classList.remove('hidden');
        }

        if (dom.progressContainer) {
            dom.progressContainer.classList.add('hidden');
        }

        if (dom.uploadProgressBar) {
            dom.uploadProgressBar.style.width = '0%';
        }

        if (dom.uploadProgressText) {
            dom.uploadProgressText.textContent = '0%';
        }

        if (dom.uploadSpeedText) {
            dom.uploadSpeedText.textContent = '';
        }

        if (dom.uploadEtaText) {
            dom.uploadEtaText.textContent = '';
        }

        if (dom.uploadingTitle) {
            const defaultTitle = state.uploadingTitleDefault || dom.uploadingTitle.textContent;
            dom.uploadingTitle.textContent = defaultTitle;
        }

        if (dom.uploadingStatus && state.uploadingStatusDefault) {
            dom.uploadingStatus.textContent = state.uploadingStatusDefault;
        }
    };

    const showUploadingOverlay = () => {
        if (dom.uploadOverlay) {
            dom.uploadOverlay.classList.remove('hidden');
        }
    };

    const hideUploadingOverlay = () => {
        if (dom.uploadOverlay) {
            dom.uploadOverlay.classList.add('hidden');
        }
    };

    const createElement = (tag, options = {}) => {
        const element = document.createElement(tag);
        if (options.className) {
            element.className = options.className;
        }
        if (options.html) {
            element.innerHTML = options.html;
        }
        if (options.text) {
            element.textContent = options.text;
        }
        if (options.attrs) {
            Object.entries(options.attrs).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    element.setAttribute(key, value);
                }
            });
        }
        return element;
    };

    const syncAuthorStateFromDOM = () => {
        if (!dom.authorsContainer) {
            return;
        }

        const inputs = dom.authorsContainer.querySelectorAll('input[name="authors[]"]');
        const values = [];

        inputs.forEach((input, index) => {
            if (index === 0) {
                return;
            }
            values.push(input.value);
        });

        state.additionalAuthors = values;
    };

    const renderAuthors = () => {
        if (!dom.authorsContainer || !window.GTAModsUpdatePage) {
            return;
        }

        dom.authorsContainer.innerHTML = '';
        const data = window.GTAModsUpdatePage;
        const primaryAuthor = data.primaryAuthor || '';
        const additionalAuthors = Array.isArray(state.additionalAuthors) ? state.additionalAuthors : [];

        const createAuthorRow = (value, removable, index) => {
            const wrapper = createElement('div', { className: 'flex items-center space-x-2' });
            const input = createElement('input', {
                className: 'form-input flex-1',
                attrs: {
                    type: 'text',
                    name: 'authors[]',
                    value: value,
                    placeholder: window.GTAModsUpdatePage.labels.authorPlaceholder,
                },
            });

            if (!removable) {
                input.disabled = true;
                input.classList.add('bg-gray-100', 'cursor-not-allowed');
            }

            wrapper.appendChild(input);

            if (removable) {
                const removeBtn = createElement('button', {
                    className: 'text-gray-400 hover:text-red-500 transition-colors',
                    html: '<i class="fas fa-times-circle"></i>',
                    attrs: { type: 'button' },
                });
                removeBtn.addEventListener('click', () => {
                    syncAuthorStateFromDOM();
                    if (Array.isArray(state.additionalAuthors)) {
                        state.additionalAuthors.splice(index, 1);
                    }
                    renderAuthors();
                });
                wrapper.appendChild(removeBtn);
            }

            dom.authorsContainer.appendChild(wrapper);
        };

        if (primaryAuthor) {
            createAuthorRow(primaryAuthor, false, -1);
        }

        additionalAuthors.forEach((author, index) => {
            createAuthorRow(author, true, index);
        });
    };

    const renderChangelog = () => {
        if (!dom.changelogList) {
            return;
        }

        dom.changelogList.innerHTML = '';
        if (!state.changelog.length) {
            dom.changelogList.innerHTML = '<div class="flex items-center justify-center p-3 rounded-md border-2 border-dashed text-sm text-gray-500 italic bg-gray-50">' + window.GTAModsUpdatePage.labels.changelogEmpty + '</div>';
            return;
        }

        state.changelog.forEach((item, index) => {
            const row = createElement('div', { className: 'flex items-center gap-2 bg-white p-2 rounded-md border text-sm' });
            const textSpan = createElement('span', { className: 'flex-1 text-gray-800', text: item });
            const removeBtn = createElement('button', {
                className: 'remove-changelog-btn text-gray-400 hover:text-red-500 transition-colors p-1 rounded-full',
                html: '<i class="fas fa-times"></i>',
                attrs: { type: 'button', 'data-index': String(index) },
            });

            removeBtn.addEventListener('click', () => {
                state.changelog.splice(index, 1);
                renderChangelog();
            });

            row.appendChild(textSpan);
            row.appendChild(removeBtn);
            dom.changelogList.appendChild(row);
        });
    };

    const renderScreenshots = () => {
        if (!dom.screenshotsContainer) {
            return;
        }

        dom.screenshotsContainer.innerHTML = '';
        if (!state.screenshots.length) {
            const placeholder = createElement('div', {
                className: 'w-full text-center text-sm text-gray-500 border border-dashed border-gray-300 rounded-lg py-6',
                text: window.GTAModsUpdatePage.labels.noScreenshots,
            });
            dom.screenshotsContainer.appendChild(placeholder);
            return;
        }

        state.screenshots.forEach((item, index) => {
            const wrapper = createElement('div', {
                className: 'relative group aspect-video rounded-lg cursor-grab w-[calc(50%-0.5rem)]',
                attrs: { draggable: 'true', 'data-key': item.key },
            });

            const img = createElement('img', {
                className: 'w-full h-full object-cover rounded-md pointer-events-none',
                attrs: {
                    src: item.url,
                    alt: item.alt || '',
                },
            });

            const numberBadge = createElement('span', {
                className: 'absolute top-2 left-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white text-xs font-bold z-10 pointer-events-none',
                text: String(index + 1),
            });

            const deleteBtn = createElement('button', {
                className: 'absolute top-2 right-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white hover:bg-red-500 transition-colors z-10',
                html: '<i class="fas fa-times text-sm"></i>',
                attrs: { type: 'button' },
            });

            deleteBtn.addEventListener('click', (event) => {
                event.preventDefault();
                if (item.type === 'existing') {
                    state.deletedExisting.add(item.attachmentId);
                }
                if (item.type === 'new' && item.url) {
                    try {
                        URL.revokeObjectURL(item.url);
                    } catch (err) {
                        // Ignore revoke errors silently
                    }
                }
                state.screenshots = state.screenshots.filter((screenshot) => screenshot.key !== item.key);
                if (!state.screenshots.some((screenshot) => screenshot.isFeatured)) {
                    if (state.screenshots[0]) {
                        state.screenshots[0].isFeatured = true;
                    }
                }
                renderScreenshots();
            });

            const radioLabel = createElement('label', {
                className: 'absolute bottom-2 right-2 flex items-center p-1.5 bg-black/60 rounded-full cursor-pointer text-white text-xs backdrop-blur-sm transition-all',
            });

            const radioInput = createElement('input', {
                attrs: {
                    type: 'radio',
                    name: 'featured_image',
                },
            });
            radioInput.className = 'hidden peer';
            radioInput.checked = item.isFeatured;
            radioInput.addEventListener('change', () => {
                state.screenshots = state.screenshots.map((screenshot) => ({
                    ...screenshot,
                    isFeatured: screenshot.key === item.key,
                }));
                renderScreenshots();
            });

            const customRadio = createElement('span', {
                className: 'w-4 h-4 rounded-full border-2 border-white flex-shrink-0 mr-1.5 peer-checked:bg-pink-500 peer-checked:border-pink-500 transition-colors duration-200',
            });

            radioLabel.appendChild(radioInput);
            radioLabel.appendChild(customRadio);
            radioLabel.appendChild(document.createTextNode('Featured'));

            if (item.isFeatured) {
                wrapper.classList.add('ring-2', 'ring-pink-500', 'ring-offset-2', 'ring-offset-white');
            }

            wrapper.appendChild(img);
            wrapper.appendChild(numberBadge);
            wrapper.appendChild(deleteBtn);
            wrapper.appendChild(radioLabel);
            dom.screenshotsContainer.appendChild(wrapper);
        });
    };

    const setupDragAndDrop = () => {
        if (!dom.screenshotsContainer) {
            return;
        }

        let draggedKey = null;

        dom.screenshotsContainer.addEventListener('dragstart', (event) => {
            const target = event.target.closest('[data-key]');
            if (!target) {
                return;
            }
            draggedKey = target.getAttribute('data-key');
            target.classList.add('dragging');
        });

        dom.screenshotsContainer.addEventListener('dragend', (event) => {
            const target = event.target.closest('[data-key]');
            if (target) {
                target.classList.remove('dragging');
            }
            draggedKey = null;
            Array.from(dom.screenshotsContainer.querySelectorAll('.drag-over')).forEach((el) => el.classList.remove('drag-over'));
        });

        dom.screenshotsContainer.addEventListener('dragover', (event) => {
            event.preventDefault();
            const target = event.target.closest('[data-key]');
            Array.from(dom.screenshotsContainer.querySelectorAll('.drag-over')).forEach((el) => el.classList.remove('drag-over'));
            if (target) {
                target.classList.add('drag-over');
            }
        });

        dom.screenshotsContainer.addEventListener('drop', (event) => {
            event.preventDefault();
            const target = event.target.closest('[data-key]');
            Array.from(dom.screenshotsContainer.querySelectorAll('.drag-over')).forEach((el) => el.classList.remove('drag-over'));
            if (!target || !draggedKey) {
                return;
            }
            const targetKey = target.getAttribute('data-key');
            if (!targetKey || targetKey === draggedKey) {
                return;
            }

            const draggedIndex = state.screenshots.findIndex((item) => item.key === draggedKey);
            const targetIndex = state.screenshots.findIndex((item) => item.key === targetKey);
            if (draggedIndex === -1 || targetIndex === -1) {
                return;
            }

            const [draggedItem] = state.screenshots.splice(draggedIndex, 1);
            state.screenshots.splice(targetIndex, 0, draggedItem);
            renderScreenshots();
        });
    };

    const addScreenshotFiles = (files) => {
        if (!files || !files.length) {
            return;
        }

        Array.from(files).forEach((file) => {
            if (!file.type.startsWith('image/')) {
                return;
            }
            const tempId = 'tmp_' + (++state.newScreenshotTempIndex);
            const url = URL.createObjectURL(file);
            state.screenshots.push({
                key: 'new:' + tempId,
                type: 'new',
                tempId,
                url,
                file,
                isFeatured: state.screenshots.every((item) => !item.isFeatured),
            });
        });

        renderScreenshots();
        if (dom.screenshotInput) {
            dom.screenshotInput.value = '';
        }
    };

    const ensureFeaturedScreenshotIsFirst = (options = {}) => {
        const { reRender = false } = options;

        if (!state.screenshots.length) {
            return;
        }

        let featuredIndex = state.screenshots.findIndex((item) => item.isFeatured);
        let didMutate = false;

        if (featuredIndex === -1) {
            state.screenshots[0].isFeatured = true;
            featuredIndex = 0;
            didMutate = true;
        }

        if (featuredIndex > 0) {
            const [featuredItem] = state.screenshots.splice(featuredIndex, 1);
            state.screenshots.unshift(featuredItem);
            didMutate = true;
        }

        if (reRender && didMutate) {
            renderScreenshots();
        }
    };

    const populateVersions = () => {
        if (!dom.versionsContainer || !window.GTAModsUpdatePage) {
            return;
        }

        const data = window.GTAModsUpdatePage;
        const versions = Array.isArray(data.versions) ? data.versions : [];
        const currentVersionId = data.currentVersion ? data.currentVersion.id : 0;

        dom.versionsContainer.innerHTML = '';

        versions.forEach((version) => {
            const isCurrent = version.id === currentVersionId;
            const wrapper = createElement('div', {
                className: 'flex flex-wrap items-center justify-between gap-y-3 gap-x-6 p-4 border rounded-lg version-item' + (isCurrent ? ' bg-green-50 border-green-200 current-version' : ''),
            });

            const infoWrapper = createElement('div', { className: 'flex-1 min-w-[250px]' });
            const header = createElement('div', { className: 'flex items-center gap-x-3 mb-1' });
            const versionLabel = createElement('span', { className: 'text-lg font-bold ' + (isCurrent ? 'text-green-600' : 'text-gray-700'), text: 'v' + version.number });
            header.appendChild(versionLabel);
            if (version.is_initial && window.GTAModsUpdatePage && window.GTAModsUpdatePage.labels && window.GTAModsUpdatePage.labels.initialBadge) {
                header.appendChild(createElement('span', { className: 'text-xs font-semibold text-gray-700 bg-gray-200 px-2.5 py-0.5 rounded-full', text: window.GTAModsUpdatePage.labels.initialBadge }));
            }
            if (isCurrent) {
                header.appendChild(createElement('span', { className: 'text-xs font-semibold text-gray-50 bg-green-500 px-2.5 py-0.5 rounded-full', text: window.GTAModsUpdatePage.labels.currentBadge }));
            }
            if (version.date) {
                header.appendChild(createElement('span', { className: 'text-xs text-gray-500', text: version.date }));
            }
            infoWrapper.appendChild(header);

            if (version.changelog && version.changelog.length) {
                const description = createElement('p', { className: 'text-sm text-gray-600 mt-1' });
                description.innerHTML = version.changelog.map((entry) => '<span class="block">• ' + entry + '</span>').join('');
                infoWrapper.appendChild(description);
            }

            wrapper.appendChild(infoWrapper);

            const actions = createElement('div', { className: 'flex items-center gap-4' });
            const downloadsWrapper = createElement('div', { className: 'flex items-center gap-2 text-sm text-gray-500' });
            downloadsWrapper.innerHTML = '<i class="fas fa-download fa-fw"></i><span data-version-downloads="' + version.id + '">' + version.downloads_display + '</span>';
            actions.appendChild(downloadsWrapper);

            if (version.download_url) {
                const button = createElement('button', {
                    className: 'flex items-center justify-center gap-2 py-2 px-4 rounded-md text-sm font-semibold whitespace-nowrap btn-secondary',
                    html: '<i class="fas fa-file-archive"></i><span>' + window.GTAModsUpdatePage.labels.download + '</span>',
                    attrs: { type: 'button', 'data-download-url': version.download_url, 'data-version-id': String(version.id) },
                });
                button.addEventListener('click', () => {
                    const targetUrl = button.dataset.downloadUrl;
                    if (!targetUrl) {
                        return;
                    }

                    const versionId = parseInt(button.dataset.versionId || '', 10) || 0;
                    incrementDownloadDisplays(versionId);
                    sendDownloadIncrement(versionId);

                    window.open(targetUrl, '_blank', 'noopener');
                });
                actions.appendChild(button);
            }

            if (version.virus_scan_url) {
                const scanLink = createElement('a', {
                    className: 'text-xs font-semibold text-green-600 hover:text-green-800 transition underline',
                    text: window.GTAModsUpdatePage.labels.virusScan || 'Virus Scan',
                    attrs: { href: version.virus_scan_url, target: '_blank', rel: 'noopener noreferrer' },
                });
                actions.appendChild(scanLink);
            }

            wrapper.appendChild(actions);
            dom.versionsContainer.appendChild(wrapper);
        });
    };

    const showError = (message) => {
        if (!dom.errorsContainer) {
            return;
        }
        const messages = Array.isArray(message)
            ? message
                .map((item) => (typeof item === 'string' ? item.trim() : String(item || '').trim()))
                .filter((item) => item.length > 0)
            : [typeof message === 'string' ? message : String(message || '')];

        dom.errorsContainer.innerHTML = '';
        if (!messages.length) {
            return;
        }

        const errorList = createElement('ul', { className: 'list-disc list-inside text-sm text-red-600 bg-red-100 p-4 rounded-lg' });
        messages.forEach((msg) => {
            errorList.appendChild(createElement('li', { text: msg }));
        });
        dom.errorsContainer.appendChild(errorList);
        dom.errorsContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const clearErrors = () => {
        if (dom.errorsContainer) {
            dom.errorsContainer.innerHTML = '';
        }
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        clearErrors();

        if (!window.GTAModsUpdatePage) {
            return;
        }

        const errors = [];
        const modTitleValue = dom.fileNameInput ? dom.fileNameInput.value.trim() : '';
        if (modTitleValue === '') {
            errors.push(window.GTAModsUpdatePage.labels.titleRequired || 'Please provide a mod title.');
        }

        const categoryValue = dom.categorySelect ? dom.categorySelect.value.trim() : '';
        if (!categoryValue) {
            errors.push(window.GTAModsUpdatePage.labels.categoryRequired || 'Please select a category.');
        }

        if (errors.length) {
            showError(errors);
            return;
        }

        ensureFeaturedScreenshotIsFirst({ reRender: true });

        const formData = new FormData();
        formData.append('action', 'gta6mods_submit_mod_update');
        formData.append('nonce', window.GTAModsUpdatePage.nonce);
        formData.append('mod_id', window.GTAModsUpdatePage.modId);
        formData.append('mod_title', modTitleValue);
        formData.append('category_id', categoryValue);
        formData.append('tags', dom.tagsInput ? dom.tagsInput.value : '');
        formData.append('description', dom.description ? dom.description.value : '');

        const videoPermission = document.querySelector('input[name="video-permissions"]:checked');
        if (videoPermission) {
            formData.append('video_permissions', videoPermission.value);
        }

        if (dom.authorsContainer) {
            const authorInputs = dom.authorsContainer.querySelectorAll('input[name="authors[]"]');
            authorInputs.forEach((input, index) => {
                if (index === 0) {
                    return;
                }
                const value = input.value.trim();
                if (value !== '') {
                    formData.append('authors[]', value);
                }
            });
        }

        formData.append('deleted_existing_screenshots', JSON.stringify(Array.from(state.deletedExisting)));
        formData.append('screenshot_order', JSON.stringify(state.screenshots.map((item) => item.key)));
        const featured = state.screenshots.find((item) => item.isFeatured);
        if (featured) {
            formData.append('featured_identifier', featured.key);
        }

        formData.append('changelog', JSON.stringify(state.changelog));

        if (dom.newVersionInput) {
            formData.append('new_version', dom.newVersionInput.value.trim());
        }

        if (dom.fileSizeValueInput) {
            formData.append('file_size_value', dom.fileSizeValueInput.value.trim());
        }
        if (dom.fileSizeUnitSelect) {
            formData.append('file_size_unit', dom.fileSizeUnitSelect.value);
        }
        if (dom.modUrlInput && !dom.modUrlWrapper.classList.contains('hidden')) {
            formData.append('mod_url', dom.modUrlInput.value.trim());
        }

        if (dom.versionScanInput) {
            formData.append('version_scan_url', dom.versionScanInput.value.trim());
        }

        state.screenshots.filter((item) => item.type === 'new' && item.file).forEach((item) => {
            formData.append('new_screenshots[' + item.tempId + ']', item.file, item.file.name);
        });

        if (dom.modFileInput && dom.modFileInput.files.length) {
            formData.append('mod_file', dom.modFileInput.files[0]);
        }

        if (dom.submitButton) {
            dom.submitButton.disabled = true;
            dom.submitButton.textContent = window.GTAModsUpdatePage.labels.submitting || dom.submitButton.textContent;
        }

        prepareUploadOverlay();
        showUploadingOverlay();

        const xhr = new XMLHttpRequest();
        let lastLoaded = 0;
        let lastTime = Date.now();

        xhr.upload.addEventListener('progress', (event) => {
            if (!event || !event.lengthComputable) {
                return;
            }

            const currentTime = Date.now();
            const deltaTime = (currentTime - lastTime) / 1000;
            const deltaLoaded = event.loaded - lastLoaded;

            if (deltaTime > 0 && deltaLoaded >= 0) {
                const speed = deltaLoaded / deltaTime;
                const speedMBps = (speed / 1024 / 1024).toFixed(2);
                if (dom.uploadSpeedText) {
                    dom.uploadSpeedText.textContent = `${speedMBps} MB/s`;
                }

                const bytesRemaining = event.total - event.loaded;
                const timeRemaining = speed > 0 ? bytesRemaining / speed : Infinity;
                if (Number.isFinite(timeRemaining) && dom.uploadEtaText) {
                    let etaString;
                    if (timeRemaining > 60) {
                        etaString = `approx. ${Math.round(timeRemaining / 60)} min`;
                    } else {
                        etaString = `approx. ${Math.max(1, Math.round(timeRemaining))} sec`;
                    }
                    dom.uploadEtaText.textContent = `ETA: ${etaString}`;
                }
            }

            lastLoaded = event.loaded;
            lastTime = currentTime;

            const percentComplete = event.total > 0 ? Math.round((event.loaded / event.total) * 100) : 0;
            if (dom.uploadProgressBar) {
                dom.uploadProgressBar.style.width = `${percentComplete}%`;
            }
            if (dom.uploadProgressText) {
                dom.uploadProgressText.textContent = `${percentComplete}%`;
            }

            if (dom.uploadingStatus) {
                const loadedSize = (event.loaded / 1024 / 1024).toFixed(2);
                const totalSize = (event.total / 1024 / 1024).toFixed(2);
                dom.uploadingStatus.textContent = `Uploaded: ${loadedSize} MB / ${totalSize} MB`;
            }

            if (dom.progressContainer && dom.progressContainer.classList.contains('hidden')) {
                dom.progressContainer.classList.remove('hidden');
                if (dom.uploadSpinner) {
                    dom.uploadSpinner.classList.add('hidden');
                }
            }
        });

        xhr.addEventListener('load', () => {
            let payload = null;

            try {
                payload = JSON.parse(xhr.responseText);
            } catch (error) {
                payload = null;
            }

            if (!payload || !payload.success) {
                const message = payload && payload.data && payload.data.message ? payload.data.message : window.GTAModsUpdatePage.labels.genericError;
                hideUploadingOverlay();
                if (dom.submitButton) {
                    dom.submitButton.disabled = false;
                    dom.submitButton.textContent = state.submitButtonDefaultLabel || 'Submit Update';
                }
                showError(message);
                return;
            }

            if (dom.submitButton) {
                dom.submitButton.disabled = true;
                dom.submitButton.textContent = window.GTAModsUpdatePage.labels.submitted || dom.submitButton.textContent;
            }

            if (dom.uploadingTitle) {
                dom.uploadingTitle.textContent = window.GTAModsUpdatePage.labels.submitted || dom.uploadingTitle.textContent;
            }
            if (dom.uploadingStatus) {
                dom.uploadingStatus.textContent = window.GTAModsUpdatePage.labels.redirecting || dom.uploadingStatus.textContent;
            }

            const redirectTarget = (payload.data && payload.data.redirect_url) ? payload.data.redirect_url : (window.GTAModsUpdatePage.modPermalink || window.location.href);

            window.setTimeout(() => {
                window.location.href = redirectTarget;
            }, 800);
        });

        xhr.addEventListener('error', () => {
            hideUploadingOverlay();
            if (dom.submitButton) {
                dom.submitButton.disabled = false;
                dom.submitButton.textContent = state.submitButtonDefaultLabel || 'Submit Update';
            }
            showError(window.GTAModsUpdatePage.labels.genericError);
        });

        xhr.open('POST', window.GTAModsUpdatePage.ajaxUrl, true);
        xhr.send(formData);
    };

    const populateCategoryOptions = () => {
        if (!dom.categorySelect) {
            return '';
        }

        const data = window.GTAModsUpdatePage || {};
        const categories = Array.isArray(data.categories) ? data.categories : [];
        const currentCategory = data.category || null;

        dom.categorySelect.innerHTML = '';

        let selectedSlug = '';
        const seenIds = new Set();

        if (currentCategory && Object.prototype.hasOwnProperty.call(currentCategory, 'id')) {
            const option = createElement('option', {
                attrs: {
                    value: String(currentCategory.id),
                    'data-slug': currentCategory.slug || '',
                },
                text: currentCategory.name || '',
            });
            option.selected = true;
            dom.categorySelect.appendChild(option);
            seenIds.add(String(currentCategory.id));
            selectedSlug = currentCategory.slug || '';
        }

        categories.forEach((category, index) => {
            if (!category || !Object.prototype.hasOwnProperty.call(category, 'id')) {
                return;
            }

            const id = String(category.id);
            if (seenIds.has(id)) {
                if (!selectedSlug && currentCategory && String(currentCategory.id) === id) {
                    selectedSlug = currentCategory.slug || category.slug || '';
                    if (dom.categorySelect.options.length) {
                        dom.categorySelect.options[0].dataset.slug = selectedSlug;
                    }
                }
                return;
            }

            const option = createElement('option', {
                attrs: {
                    value: id,
                    'data-slug': category.slug || '',
                },
                text: category.name || '',
            });

            if (!currentCategory && index === 0) {
                option.selected = true;
                selectedSlug = category.slug || '';
            }

            dom.categorySelect.appendChild(option);
            seenIds.add(id);
        });

        if (!dom.categorySelect.options.length) {
            dom.categorySelect.disabled = true;
            dom.categorySelect.classList.add('bg-gray-100', 'cursor-not-allowed');
        } else {
            dom.categorySelect.disabled = false;
            dom.categorySelect.classList.remove('bg-gray-100', 'cursor-not-allowed');
        }

        return selectedSlug;
    };

    const updateCategoryNavHighlight = (slug) => {
        const navLinks = getCategoryNavLinks();

        if (!navLinks.length) {
            return;
        }

        navLinks.forEach((link) => {
            link.classList.remove('category-active', 'category-dimmed');
        });

        if (!slug) {
            return;
        }

        navLinks.forEach((link) => {
            if (link.dataset.category === slug) {
                link.classList.add('category-active');
            } else {
                link.classList.add('category-dimmed');
            }
        });
    };

    const setupCategoryNavInteractions = () => {
        if (!dom.categorySelect) {
            return;
        }

        const navLinks = getCategoryNavLinks();

        if (!navLinks.length) {
            return;
        }

        dom.categorySelect.addEventListener('change', (event) => {
            const selectedOption = event.target.selectedOptions[0];
            updateCategoryNavHighlight(selectedOption ? selectedOption.dataset.slug : '');
        });

        navLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                if (link.dataset.disabled === 'true') {
                    event.preventDefault();
                    return;
                }

                const slug = link.dataset.category;
                if (!slug) {
                    return;
                }

                event.preventDefault();

                const matchingOption = Array.from(dom.categorySelect.options).find((option) => option.dataset.slug === slug);
                if (matchingOption) {
                    dom.categorySelect.value = matchingOption.value;
                    dom.categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        const initialOption = dom.categorySelect.selectedOptions[0];
        updateCategoryNavHighlight(initialOption ? initialOption.dataset.slug : '');
    };

    const populateBasicInfo = () => {
        if (!window.GTAModsUpdatePage) {
            return;
        }

        const data = window.GTAModsUpdatePage;

        if (dom.fileNameInput) {
            dom.fileNameInput.value = data.modTitle || '';
        }
        const selectedCategorySlug = populateCategoryOptions();
        if (dom.tagsInput) {
            dom.tagsInput.value = data.tags || '';
        }
        if (dom.description) {
            dom.description.value = data.description || '';
        }
        if (dom.videoPermissions) {
            const selected = data.videoPermission || 'moderate';
            dom.videoPermissions.forEach((radio) => {
                radio.checked = radio.value === selected;
            });
        }
        if (dom.statsLikes) {
            dom.statsLikes.textContent = data.stats.likes;
        }
        if (dom.statsViews) {
            dom.statsViews.textContent = data.stats.views;
        }
        if (dom.statsDownloads) {
            dom.statsDownloads.textContent = data.stats.downloads;
        }

        if (selectedCategorySlug) {
            updateCategoryNavHighlight(selectedCategorySlug);
        }
    };

    const hydrateScreenshots = () => {
        if (!window.GTAModsUpdatePage || !Array.isArray(window.GTAModsUpdatePage.screenshots)) {
            return;
        }

        state.screenshots = window.GTAModsUpdatePage.screenshots.map((item, index) => ({
            key: 'existing:' + item.id,
            type: 'existing',
            attachmentId: item.id,
            url: item.url,
            isFeatured: item.isFeatured || (!index && !window.GTAModsUpdatePage.screenshots.some((shot) => shot.isFeatured)),
        }));

        renderScreenshots();
    };

    const hydrateAuthors = () => {
        const data = window.GTAModsUpdatePage;
        state.additionalAuthors = Array.isArray(data.additionalAuthors) ? [...data.additionalAuthors] : [];
        renderAuthors();
    };

    const hydrateChangelog = () => {
        state.changelog = Array.isArray(window.GTAModsUpdatePage.pendingChangelog) ? [...window.GTAModsUpdatePage.pendingChangelog] : [];
        renderChangelog();
    };

    const bindEvents = () => {
        if (dom.addAuthorBtn) {
            dom.addAuthorBtn.addEventListener('click', () => {
                syncAuthorStateFromDOM();
                state.additionalAuthors.push('');
                renderAuthors();
            });
        }

        if (dom.addChangelogBtn && dom.newChangelogInput) {
            const addItem = () => {
                const value = dom.newChangelogInput.value.trim();
                if (value !== '') {
                    state.changelog.push(value);
                    dom.newChangelogInput.value = '';
                    renderChangelog();
                }
                dom.newChangelogInput.focus();
            };
            dom.addChangelogBtn.addEventListener('click', addItem);
            dom.newChangelogInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addItem();
                }
            });
        }

        if (dom.screenshotInput) {
            dom.screenshotInput.addEventListener('change', (event) => addScreenshotFiles(event.target.files));
        }

        if (dom.screenshotDropzone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evt) => {
                dom.screenshotDropzone.addEventListener(evt, (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                });
            });
            ['dragenter', 'dragover'].forEach((evt) => {
                dom.screenshotDropzone.addEventListener(evt, () => dom.screenshotDropzone.classList.add('bg-pink-50', 'border-pink-400'));
            });
            ['dragleave', 'drop'].forEach((evt) => {
                dom.screenshotDropzone.addEventListener(evt, () => dom.screenshotDropzone.classList.remove('bg-pink-50', 'border-pink-400'));
            });
            dom.screenshotDropzone.addEventListener('drop', (event) => addScreenshotFiles(event.dataTransfer.files));
        }

        if (dom.showUrlViewBtn && dom.showFileViewBtn && dom.fileUploadWrapper && dom.modUrlWrapper) {
            dom.showUrlViewBtn.addEventListener('click', () => {
                dom.fileUploadWrapper.classList.add('hidden');
                dom.modUrlWrapper.classList.remove('hidden');
                if (dom.modFileInput) {
                    dom.modFileInput.value = '';
                    if (dom.modFilePreview) {
                        dom.modFilePreview.classList.add('hidden');
                        dom.modFilePreview.classList.remove('flex');
                    }
                    if (dom.modDropzoneContent) {
                        dom.modDropzoneContent.classList.remove('hidden');
                    }
                }
            });
            dom.showFileViewBtn.addEventListener('click', () => {
                dom.modUrlWrapper.classList.add('hidden');
                dom.fileUploadWrapper.classList.remove('hidden');
                if (dom.modUrlInput) {
                    dom.modUrlInput.value = '';
                }
                if (dom.fileSizeValueInput) {
                    dom.fileSizeValueInput.value = '';
                }
            });
        }

        if (dom.modFileInput) {
            dom.modFileInput.addEventListener('change', () => {
                if (!dom.modFileInput.files.length) {
                    if (dom.modFilePreview) {
                        dom.modFilePreview.classList.add('hidden');
                        dom.modFilePreview.classList.remove('flex');
                    }
                    if (dom.modDropzoneContent) {
                        dom.modDropzoneContent.classList.remove('hidden');
                    }
                    return;
                }

                const file = dom.modFileInput.files[0];
                if (dom.modDropzoneContent) {
                    dom.modDropzoneContent.classList.add('hidden');
                }
                if (dom.modFilePreview) {
                    dom.modFilePreview.innerHTML = '<div class="flex flex-col items-center"><i class="fas fa-check-circle text-green-500 text-4xl"></i><p class="font-semibold mt-2 break-all">' + file.name + '</p><p class="text-sm text-gray-500">' + (file.size / 1024 / 1024).toFixed(2) + ' MB</p><button type="button" class="mt-2 text-sm font-semibold text-red-600 hover:text-red-800 transition" data-remove-mod-file>' + window.GTAModsUpdatePage.labels.removeFile + '</button></div>';
                    dom.modFilePreview.classList.remove('hidden');
                    dom.modFilePreview.classList.add('flex');
                    const removeButton = dom.modFilePreview.querySelector('[data-remove-mod-file]');
                    if (removeButton) {
                        removeButton.addEventListener('click', () => {
                            dom.modFileInput.value = '';
                            dom.modFilePreview.classList.add('hidden');
                            dom.modFilePreview.classList.remove('flex');
                            if (dom.modDropzoneContent) {
                                dom.modDropzoneContent.classList.remove('hidden');
                            }
                        });
                    }
                }
            });
        }

        if (dom.modDropzoneLabel) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evt) => {
                dom.modDropzoneLabel.addEventListener(evt, (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                });
            });
            ['dragenter', 'dragover'].forEach((evt) => {
                dom.modDropzoneLabel.addEventListener(evt, () => dom.modDropzoneLabel.classList.add('bg-pink-50', 'border-pink-400'));
            });
            ['dragleave', 'drop'].forEach((evt) => {
                dom.modDropzoneLabel.addEventListener(evt, () => dom.modDropzoneLabel.classList.remove('bg-pink-50', 'border-pink-400'));
            });
            dom.modDropzoneLabel.addEventListener('drop', (event) => {
                const files = event.dataTransfer.files;
                if (!dom.modFileInput || !files || !files.length) {
                    return;
                }

                if (typeof DataTransfer !== 'undefined') {
                    const transfer = new DataTransfer();
                    transfer.items.add(files[0]);
                    dom.modFileInput.files = transfer.files;
                }

                dom.modFileInput.dispatchEvent(new Event('change'));
            });
        }

        if (dom.form) {
            dom.form.addEventListener('submit', handleSubmit);
        }
    };

    const initializeEditor = () => {
        if (typeof EditorJS === 'undefined' || !dom.editorContainer || !dom.description) {
            return;
        }

        const descriptionInput = dom.description;
        const editorContainer = dom.editorContainer;
        let inlineToolbarObserver = null;
        let backspaceHandlerAttached = false;

        const applyInlineToolbarMode = (toolbarEl) => {
            const toolbar = toolbarEl || document.querySelector('.ce-inline-toolbar');
            if (!toolbar) {
                return;
            }

            if (window.innerWidth < 768) {
                toolbar.classList.add('ce-inline-toolbar--compact');
            } else {
                toolbar.classList.remove('ce-inline-toolbar--compact');
            }
        };

        const observeInlineToolbar = () => {
            if (inlineToolbarObserver) {
                inlineToolbarObserver.disconnect();
            }

            inlineToolbarObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node instanceof HTMLElement && node.classList.contains('ce-inline-toolbar')) {
                            applyInlineToolbarMode(node);
                        }
                    });
                });
            });

            inlineToolbarObserver.observe(document.body, { childList: true, subtree: true });
        };

        const ensureEditorContainerFocusability = () => {
            if (!editorContainer) {
                return;
            }

            if (!editorContainer.hasAttribute('tabindex')) {
                editorContainer.setAttribute('tabindex', '0');
            }

            editorContainer.addEventListener('focus', () => {
                if (editor && typeof editor.focus === 'function') {
                    try {
                        editor.focus();
                    } catch (focusError) {
                        console.warn('Editor focus failed', focusError);
                    }
                }
            });
        };

        const focusContentEditable = (element, position = 'end') => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(element);
            range.collapse(position !== 'start');
            selection.removeAllRanges();
            selection.addRange(range);
        };

        const moveCaretAfterBlockDeletion = (blockIndex) => {
            window.requestAnimationFrame(() => {
                const targetIndex = Math.max(0, blockIndex - 1);
                let caretPlaced = false;

                if (typeof editor?.caret?.setToBlock === 'function') {
                    try {
                        editor.caret.setToBlock(targetIndex, 'end');
                        caretPlaced = true;
                    } catch (caretError) {
                        caretPlaced = false;
                    }
                }

                if (!caretPlaced && typeof editor?.blocks?.insert === 'function') {
                    try {
                        const insertIndex = Math.max(0, blockIndex - 1);
                        editor.blocks.insert('paragraph', { text: '' }, undefined, insertIndex, true);

                        window.requestAnimationFrame(() => {
                            if (typeof editor?.caret?.setToBlock === 'function') {
                                try {
                                    editor.caret.setToBlock(insertIndex, 'end');
                                } catch (finalCaretError) {
                                    console.warn('Failed to focus fallback paragraph', finalCaretError);
                                }
                            }
                        });
                    } catch (insertError) {
                        console.warn('Failed to insert fallback paragraph after deleting list block', insertError);
                    }
                }
            });
        };

        const handleRedactorBackspace = (event) => {
            if (event.key !== 'Backspace' || event.defaultPrevented) {
                return;
            }

            if (event.__gta6ListHandled) {
                return;
            }

            if (!editor || !editor.blocks || typeof editor.blocks.getCurrentBlockIndex !== 'function') {
                return;
            }

            const blockIndex = editor.blocks.getCurrentBlockIndex();
            if (blockIndex <= 0) {
                return;
            }

            const block = editor.blocks.getBlockByIndex(blockIndex);
            if (!block) {
                return;
            }

            const blockName = typeof block.name === 'string' ? block.name.toLowerCase() : '';
            if (['list', 'checklist'].includes(blockName)) {
                return;
            }

            const holder = block.holder;
            if (!holder) {
                return;
            }

            const holderDataset = holder.dataset || {};
            const datasetType = typeof holderDataset.type === 'string' ? holderDataset.type.toLowerCase() : '';
            const datasetTool = typeof holderDataset.tool === 'string' ? holderDataset.tool.toLowerCase() : '';
            if (['list', 'checklist'].includes(datasetType) || ['list', 'checklist'].includes(datasetTool)) {
                return;
            }

            if (holder.closest('.gta6-youtube-tool')) {
                return;
            }

            const hasInteractiveChild = holder.querySelector('iframe, img, video, audio, table, pre, code, .gta6-youtube-tool');
            if (hasInteractiveChild) {
                return;
            }

            const selection = window.getSelection();
            if (selection && !selection.isCollapsed) {
                return;
            }

            const textContent = holder.innerText.replace(/\u200B/g, '').trim();
            if (textContent.length > 0) {
                return;
            }

            event.preventDefault();

            try {
                editor.blocks.delete(blockIndex);
            } catch (deleteError) {
                console.warn('Failed to delete empty block', deleteError);
                return;
            }

            moveCaretAfterBlockDeletion(blockIndex);
        };

        const attachBackspaceHandler = () => {
            if (backspaceHandlerAttached || !editorContainer) {
                return;
            }

            const redactor = editorContainer.querySelector('.codex-editor__redactor');
            if (!redactor) {
                return;
            }

            redactor.addEventListener('keydown', handleRedactorBackspace);
            backspaceHandlerAttached = true;
        };

        const patchListBackspaceBehavior = () => {
            const ListTool = window.List;
            if (!ListTool || ListTool.__gta6BackspacePatched) {
                return;
            }

            const originalBackspace = (ListTool.prototype && typeof ListTool.prototype.backspace === 'function')
                ? ListTool.prototype.backspace
                : null;

            const getItemElements = (instance) => {
                if (!instance || !instance._elements || !instance._elements.wrapper || !instance.CSS || !instance.CSS.item) {
                    return [];
                }

                return Array.from(instance._elements.wrapper.querySelectorAll(`.${instance.CSS.item}`));
            };

            ListTool.prototype.backspace = function backspace(event) {
                if (!event) {
                    return originalBackspace ? originalBackspace.call(this, event) : undefined;
                }

                if (event.defaultPrevented) {
                    return originalBackspace ? originalBackspace.call(this, event) : undefined;
                }

                const selection = window.getSelection();
                if (!selection || !selection.isCollapsed) {
                    return originalBackspace ? originalBackspace.call(this, event) : undefined;
                }

                const anchorNode = selection.anchorNode;
                if (!anchorNode) {
                    return originalBackspace ? originalBackspace.call(this, event) : undefined;
                }

                const itemClass = this?.CSS?.item;
                const wrapper = this?._elements?.wrapper;
                if (!itemClass || !wrapper) {
                    return originalBackspace ? originalBackspace.call(this, event) : undefined;
                }

                const currentItem = anchorNode.nodeType === Node.ELEMENT_NODE
                    ? anchorNode.closest(`.${itemClass}`)
                    : anchorNode.parentElement?.closest(`.${itemClass}`);

                if (!currentItem) {
                    return originalBackspace ? originalBackspace.call(this, event) : undefined;
                }

                const items = getItemElements(this);
                const currentIndex = items.indexOf(currentItem);
                if (currentIndex !== 0) {
                    return originalBackspace ? originalBackspace.call(this, event) : undefined;
                }

                const currentContent = currentItem.innerText.replace(/\u200B/g, '').trim();
                if (currentContent.length > 0) {
                    return originalBackspace ? originalBackspace.call(this, event) : undefined;
                }

                event.preventDefault();
                event.stopPropagation();
                event.__gta6ListHandled = true;

                const remainingItems = items.filter((item) => item !== currentItem);
                currentItem.remove();

                if (!remainingItems.length) {
                    if (typeof this.api?.blocks?.delete === 'function') {
                        const blockIndex = editor?.blocks?.getCurrentBlockIndex?.() ?? -1;
                        try {
                            this.api.blocks.delete(blockIndex);
                        } catch (deleteError) {
                            console.warn('Failed to delete list block after removing final item', deleteError);
                        }

                        moveCaretAfterBlockDeletion(blockIndex);
                    }
                } else {
                    const previousItem = currentIndex > 0 ? remainingItems[currentIndex - 1] : null;
                    const fallbackIndex = Math.min(currentIndex, remainingItems.length - 1);
                    const fallbackItem = remainingItems[fallbackIndex];
                    const focusItem = previousItem || fallbackItem;
                    const focusPosition = previousItem ? 'end' : 'start';

                    if (focusItem) {
                        const editableTarget = focusItem.matches('[contenteditable="true"]')
                            ? focusItem
                            : focusItem.querySelector('[contenteditable="true"]');

                        if (editableTarget instanceof HTMLElement) {
                            focusContentEditable(editableTarget, focusPosition);
                        }
                    }

                    remainingItems.forEach((itemEl, index) => {
                        if (itemEl.dataset) {
                            itemEl.dataset.item = String(index + 1);
                        }
                    });
                }

                if (typeof this?.api?.dispatchChange === 'function') {
                    try {
                        this.api.dispatchChange();
                    } catch (dispatchError) {
                        console.warn('Failed to dispatch change after list item removal', dispatchError);
                    }
                } else if (typeof this?.api?.events?.emit === 'function') {
                    try {
                        this.api.events.emit('block-changed');
                    } catch (emitError) {
                        console.warn('Failed to emit block change after list item removal', emitError);
                    }
                }

                return undefined;
            };

            ListTool.__gta6BackspacePatched = true;
        };

        class GTA6YoutubeTool {
            constructor({ data = {}, api, readOnly }) {
                this.api = api;
                this.readOnly = readOnly;
                this.data = data || {};
                this.wrapper = null;
                this.urlInput = null;
                this.captionInput = null;
                this.previewContainer = null;

                const initialVideoId = this.data.videoId || extractYoutubeId(this.data.url || this.data.originalUrl || '');
                if (initialVideoId) {
                    this.data.videoId = initialVideoId;
                    if (!this.data.url) {
                        this.data.url = getYoutubeCanonicalUrl(initialVideoId);
                    }
                    if (!this.data.embedUrl) {
                        this.data.embedUrl = getYoutubeEmbedUrl(initialVideoId);
                    }
                }

                if (!this.data.originalUrl && this.data.url) {
                    this.data.originalUrl = this.data.url;
                }

                const hasExistingUrl = typeof this.data.url === 'string' && this.data.url.trim().length > 0;
                this.shouldAutofocusUrl = !hasExistingUrl;
                this.hasAutofocusedUrl = false;
            }

            static get toolbox() {
                return {
                    title: 'YouTube',
                    icon: '<svg width="17" height="15" viewBox="0 0 17 15" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M16.58 3.12a2.06 2.06 0 00-1.46-1.46C13.84 1.25 8.5 1.25 8.5 1.25s-5.34 0-6.62.41A2.06 2.06 0 00.42 3.12 21.48 21.48 0 000 7.5a21.48 21.48 0 00.42 4.38 2.06 2.06 0 001.46 1.46c1.28.41 6.62.41 6.62.41s5.34 0 6.62-.41a2.06 2.06 0 001.46-1.46A21.48 21.48 0 0017 7.5a21.48 21.48 0 00-.42-4.38zM6.8 10.3V4.7l4.43 2.8z"/></svg>',
                };
            }

            static get sanitize() {
                return {
                    url: false,
                    videoId: false,
                    embedUrl: false,
                    originalUrl: false,
                    service: false,
                    caption: true,
                };
            }

            static get isReadOnlySupported() {
                return true;
            }

            render() {
                this.wrapper = document.createElement('div');
                this.wrapper.classList.add('gta6-youtube-tool');

                const videoId = this.data.videoId || extractYoutubeId(this.data.url || this.data.originalUrl || '');

                if (this.readOnly) {
                    const embedSrc = this.data.embedUrl || getYoutubeEmbedUrl(videoId);
                    if (videoId && embedSrc) {
                        const preview = document.createElement('div');
                        preview.className = 'gta6-editor-embed';
                        preview.innerHTML = `<iframe src="${embedSrc}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`;
                        this.wrapper.appendChild(preview);
                    }

                    if (this.data.caption) {
                        const caption = document.createElement('p');
                        caption.className = 'text-center text-sm text-gray-500 italic mt-2';
                        caption.textContent = this.data.caption;
                        this.wrapper.appendChild(caption);
                    }

                    return this.wrapper;
                }

                const urlLabel = document.createElement('label');
                urlLabel.className = 'gta6-youtube-label';
                urlLabel.textContent = youtubeStrings.urlLabel;

                this.urlInput = document.createElement('input');
                this.urlInput.type = 'url';
                this.urlInput.className = 'gta6-youtube-input';
                this.urlInput.placeholder = youtubeStrings.urlPlaceholder;
                this.urlInput.value = this.data.originalUrl || this.data.url || '';

                const helperText = document.createElement('p');
                helperText.className = 'gta6-youtube-helper';
                helperText.textContent = youtubeStrings.helper;

                this.previewContainer = document.createElement('div');
                this.previewContainer.className = 'gta6-youtube-preview';

                const captionLabel = document.createElement('label');
                captionLabel.className = 'gta6-youtube-label';
                captionLabel.textContent = youtubeStrings.captionLabel;

                this.captionInput = document.createElement('textarea');
                this.captionInput.className = 'gta6-youtube-textarea';
                this.captionInput.placeholder = youtubeStrings.captionPlaceholder;
                this.captionInput.value = this.data.caption || '';

                this.wrapper.appendChild(urlLabel);
                this.wrapper.appendChild(this.urlInput);
                this.wrapper.appendChild(helperText);
                this.wrapper.appendChild(this.previewContainer);
                this.wrapper.appendChild(captionLabel);
                this.wrapper.appendChild(this.captionInput);

                const stopPropagation = (element) => {
                    if (!element) {
                        return;
                    }

                    ['click', 'mousedown', 'touchstart'].forEach((eventName) => {
                        element.addEventListener(eventName, (event) => event.stopPropagation());
                    });

                    element.addEventListener('keydown', (event) => event.stopPropagation());
                };

                stopPropagation(this.urlInput);
                stopPropagation(this.captionInput);

                const markManualInteraction = () => {
                    this.shouldAutofocusUrl = false;
                };

                if (this.wrapper) {
                    ['pointerdown', 'focusin'].forEach((eventName) => {
                        this.wrapper.addEventListener(eventName, markManualInteraction);
                    });
                }

                this.urlInput.addEventListener('focus', markManualInteraction);
                this.urlInput.addEventListener('input', () => {
                    const value = this.urlInput.value.trim();
                    this.data.originalUrl = value;
                    this.data.videoId = extractYoutubeId(value);
                    this.data.url = this.data.videoId ? getYoutubeCanonicalUrl(this.data.videoId) : value;
                    this.shouldAutofocusUrl = false;
                    this.updatePreview();
                });
                this.urlInput.addEventListener('change', () => {
                    const value = this.urlInput.value.trim();
                    this.data.originalUrl = value;
                    this.data.videoId = extractYoutubeId(value);
                    this.data.url = this.data.videoId ? getYoutubeCanonicalUrl(this.data.videoId) : value;
                    this.shouldAutofocusUrl = false;
                    this.updatePreview();
                });

                if (this.captionInput) {
                    this.captionInput.addEventListener('input', () => {
                        this.data.caption = this.captionInput.value.trim();
                    });
                }

                this.updatePreview();

                if (!this.readOnly && this.shouldAutofocusUrl && !this.hasAutofocusedUrl) {
                    window.requestAnimationFrame(() => {
                        if (!this.urlInput) {
                            return;
                        }

                        const activeElement = document.activeElement;
                        if (activeElement && activeElement !== document.body && this.wrapper && !this.wrapper.contains(activeElement)) {
                            return;
                        }

                        try {
                            this.urlInput.focus({ preventScroll: true });
                        } catch (focusError) {
                            this.urlInput.focus();
                        }
                        this.urlInput.select();
                        this.hasAutofocusedUrl = true;
                        this.shouldAutofocusUrl = false;
                    });
                }

                return this.wrapper;
            }

            updatePreview() {
                if (!this.previewContainer) {
                    return;
                }

                this.previewContainer.innerHTML = '';
                const urlValue = this.urlInput ? this.urlInput.value.trim() : (this.data.originalUrl || this.data.url || '');
                const videoId = extractYoutubeId(urlValue);

                if (videoId) {
                    this.data.videoId = videoId;
                    this.data.embedUrl = getYoutubeEmbedUrl(videoId);
                    this.data.url = getYoutubeCanonicalUrl(videoId);
                    const preview = document.createElement('div');
                    preview.className = 'gta6-youtube-preview';
                    preview.innerHTML = `<iframe src="${this.data.embedUrl}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`;
                    this.previewContainer.appendChild(preview);
                } else {
                    this.data.videoId = '';
                    this.data.embedUrl = '';
                    this.data.url = urlValue;
                    const placeholder = document.createElement('div');
                    placeholder.className = 'gta6-youtube-placeholder';
                    if (urlValue.length > 0) {
                        placeholder.classList.add('is-error');
                        placeholder.textContent = youtubeStrings.invalidMessage;
                    } else {
                        placeholder.textContent = youtubeStrings.placeholder;
                    }
                    this.previewContainer.appendChild(placeholder);
                }
            }

            save() {
                const urlValue = this.urlInput ? this.urlInput.value.trim() : (this.data.originalUrl || this.data.url || '');
                const captionValue = this.captionInput ? this.captionInput.value.trim() : '';
                const videoId = extractYoutubeId(urlValue);
                const canonicalUrl = videoId ? getYoutubeCanonicalUrl(videoId) : urlValue;
                const embedUrl = videoId ? getYoutubeEmbedUrl(videoId) : '';

                return {
                    service: videoId ? 'youtube' : '',
                    url: canonicalUrl,
                    originalUrl: urlValue,
                    embedUrl,
                    videoId,
                    caption: captionValue,
                };
            }

            validate(savedData) {
                return Boolean(savedData.videoId);
            }
        }

        let initialData;
        if (descriptionInput.value) {
            try {
                initialData = JSON.parse(descriptionInput.value);
            } catch (error) {
                console.warn('Invalid Editor.js data found in description field.', error);
            }
        }

        const debouncedSyncEditorData = debounce((apiInstance) => {
            apiInstance.saver.save()
                .then((savedData) => {
                    if (descriptionInput) {
                        descriptionInput.value = JSON.stringify(savedData);
                    }
                })
                .catch((error) => {
                    console.error('Editor.js saving failed: ', error);
                });
        }, 350);

        const tools = {
            header: {
                class: window.Header,
                inlineToolbar: true,
                config: {
                    placeholder: 'Enter a header',
                    levels: [2, 3, 4],
                    defaultLevel: 2,
                },
            },
            list: {
                class: window.List,
                inlineToolbar: true,
            },
            quote: {
                class: window.Quote,
                inlineToolbar: true,
            },
            delimiter: window.Delimiter,
            table: {
                class: window.Table,
                inlineToolbar: true,
            },
            underline: window.Underline,
            embed: {
                class: window.Embed,
                inlineToolbar: true,
                config: {
                    services: {
                        youtube: true,
                    },
                },
            },
            youtube: {
                class: GTA6YoutubeTool,
            },
            code: {
                class: window.CodeTool,
                placeholder: 'Enter a code snippet',
            },
        };

        editor = new EditorJS({
            holder: 'editorjs-container',
            placeholder: 'Provide information and installation instructions...',
            tools,
            data: initialData,
            onChange(apiInstance) {
                debouncedSyncEditorData(apiInstance);
                applyInlineToolbarMode();
            },
        });

        editor.isReady
            .then(() => {
                patchListBackspaceBehavior();
                ensureEditorContainerFocusability();
                observeInlineToolbar();
                applyInlineToolbarMode();
                attachBackspaceHandler();

                if (descriptionInput) {
                    editor.save()
                        .then((savedData) => {
                            descriptionInput.value = JSON.stringify(savedData);
                        })
                        .catch((error) => {
                            console.error('Failed to capture initial editor data', error);
                        });
                }
            })
            .catch((error) => {
                console.error('Editor.js initialization failed: ', error);
            });

        window.addEventListener('resize', () => applyInlineToolbarMode());
        window.addEventListener('orientationchange', () => applyInlineToolbarMode());
        window.addEventListener('load', () => applyInlineToolbarMode());
    };

    const init = () => {
        captureStaticDomReferences();

        if (!dom.root || !window.GTAModsUpdatePage) {
            return;
        }

        loadModData();
    };

    document.addEventListener('DOMContentLoaded', init);
})();
