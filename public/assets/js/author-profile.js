(function () {
    'use strict';

    const utils = window.GTAModsUtils || {};

    const fallbackGetCookie = (name) => {
        if (!name || typeof document === 'undefined') {
            return null;
        }

        const cookies = document.cookie ? document.cookie.split(';') : [];
        for (let i = 0; i < cookies.length; i += 1) {
            const cookie = cookies[i].trim();
            if (cookie.startsWith(`${name}=`)) {
                return cookie.substring(name.length + 1);
            }
        }
        return null;
    };

    const getCookieValue = typeof utils.getCookie === 'function' ? utils.getCookie : fallbackGetCookie;

    const hasCookie = typeof utils.hasCookie === 'function'
        ? utils.hasCookie
        : (name) => getCookieValue(name) !== null;

    const fallbackSetCookie = (name, value, maxAgeSeconds, secure = false) => {
        if (!name || typeof document === 'undefined') {
            return;
        }

        const parts = [`${name}=${value}`];
        if (typeof maxAgeSeconds === 'number' && Number.isFinite(maxAgeSeconds)) {
            parts.push(`max-age=${Math.max(0, Math.floor(maxAgeSeconds))}`);
        }
        parts.push('path=/');
        parts.push('SameSite=Lax');
        if (secure) {
            parts.push('secure');
        }
        document.cookie = parts.join('; ');
    };

    const setCookie = typeof utils.setCookie === 'function'
        ? (name, value, maxAgeSeconds, secure = false) => {
            if (!name) {
                return;
            }

            const options = {
                path: '/',
                sameSite: 'Lax',
            };

            if (typeof maxAgeSeconds === 'number' && Number.isFinite(maxAgeSeconds)) {
                options.maxAge = Math.max(0, Math.floor(maxAgeSeconds));
            }

            if (secure) {
                options.secure = true;
            }

            utils.setCookie(name, value, options);
        }
        : fallbackSetCookie;

    const fallbackScheduleDeferred = (callback, delay) => {
        const timeout = Math.max(0, typeof delay === 'number' ? delay : 0);
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(() => callback(), { timeout: timeout || 2000 });
            return;
        }

        window.setTimeout(callback, timeout || 2000);
    };

    const scheduleDeferred = typeof utils.scheduleDeferred === 'function'
        ? utils.scheduleDeferred
        : fallbackScheduleDeferred;

    const buildRestHeaders = typeof utils.buildRestHeaders === 'function'
        ? utils.buildRestHeaders
        : (nonce, extra = {}) => {
            const headers = { ...extra };
            if (nonce) {
                headers['X-WP-Nonce'] = nonce;
            }
            return headers;
        };

    const formatNumber = (value) => {
        if (typeof value === 'number') {
            return value.toLocaleString();
        }
        if (typeof value === 'string') {
            return value;
        }
        const parsed = Number(value);
        if (!Number.isNaN(parsed)) {
            return parsed.toLocaleString();
        }
        return '';
    };

    const createRelativeFormatter = () => {
        if (typeof Intl === 'object' && typeof Intl.RelativeTimeFormat === 'function') {
            try {
                return new Intl.RelativeTimeFormat(document.documentElement.lang || 'en', { numeric: 'auto' });
            } catch (error) {
                return null;
            }
        }
        return null;
    };

    const relativeFormatter = createRelativeFormatter();

    const initialise = () => {
        if (typeof window.GTAModsAuthorProfile === 'undefined') {
            return;
        }

        const data = window.GTAModsAuthorProfile || {};
        const restBase = typeof data.restBase === 'string' ? data.restBase.replace(/\/$/, '') : '';
        const authorId = Number.parseInt(data.authorId, 10);
        const isOwner = Boolean(data.isOwnProfile);
        const profileViewEndpoint = typeof data.profileViewEndpoint === 'string' ? data.profileViewEndpoint : '';
        const activityEndpoint = typeof data.activityEndpoint === 'string' ? data.activityEndpoint : '';
        const viewCountElement = document.querySelector('[data-author-profile-views-count]');
        const activityContainer = document.querySelector('[data-author-activity]');
        const activityLabel = activityContainer ? activityContainer.querySelector('[data-author-activity-label]') : null;
        const defaultLabelClass = activityLabel ? activityLabel.className : '';
        const initialState = activityContainer ? activityContainer.getAttribute('data-state') : '';
        const offlineLabelClass = initialState === 'offline' ? defaultLabelClass : '';
        const onlineClasses = ['inline-flex', 'items-center', 'gap-2', 'text-green-600', 'font-semibold'];
        const activityList = document.querySelector('[data-activity-list]');
        const activityLoadMoreContainer = document.querySelector('[data-activity-load-more-container]');
        const activityLoadMoreButton = activityLoadMoreContainer ? activityLoadMoreContainer.querySelector('[data-load-more="activity"]') : null;
        const notificationsContainer = document.getElementById('notifications-container');
        const notificationsButton = document.getElementById('notifications-btn');
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        const notificationsContent = notificationsDropdown ? notificationsDropdown.querySelector('[data-async-content="notifications"]') : null;
        const notificationsBadge = notificationsContainer ? notificationsContainer.querySelector('[data-notification-badge]') : null;
        const notificationsMarkAllButton = notificationsDropdown ? notificationsDropdown.querySelector('[data-action="mark-all-read"]') : null;
        const followButton = document.getElementById('gta6mods-follow-btn');
        const followerCountElements = [];

        document.querySelectorAll('[data-followers-count]').forEach((element) => {
            if (element instanceof HTMLElement && !followerCountElements.includes(element)) {
                followerCountElements.push(element);
            }
        });

        const fallbackFollowersCountElement = document.getElementById('gta6mods-followers-count');
        if (fallbackFollowersCountElement && !followerCountElements.includes(fallbackFollowersCountElement)) {
            followerCountElements.push(fallbackFollowersCountElement);
        }

        let lastActivityTimestamp = Number(data.lastActivityTimestamp) || 0;
        const activityWindow = Number(data.activityWindow) || (20 * 60);
        const serverNow = Number(data.serverNow) || Math.floor(Date.now() / 1000);
        const serverOffsetMs = Date.now() - (serverNow * 1000);

        const getApproxServerNow = () => Math.floor((Date.now() - serverOffsetMs) / 1000);

        const formatRelativeTime = (timestampSeconds, fallbackLabel) => {
            if (!timestampSeconds) {
                return fallbackLabel || '';
            }

            const nowSeconds = getApproxServerNow();
            let diffSeconds = timestampSeconds - nowSeconds;
            if (diffSeconds > 0) {
                diffSeconds = -diffSeconds;
            }
            const absDiff = Math.abs(diffSeconds);

            const formatWithUnit = (value, unit) => {
                if (relativeFormatter) {
                    return relativeFormatter.format(value, unit);
                }

                const absolute = Math.abs(value);
                const unitLabel = (() => {
                    switch (unit) {
                        case 'second':
                            return absolute === 1 ? 'second' : 'seconds';
                        case 'minute':
                            return absolute === 1 ? 'minute' : 'minutes';
                        case 'hour':
                            return absolute === 1 ? 'hour' : 'hours';
                        case 'day':
                            return absolute === 1 ? 'day' : 'days';
                        case 'week':
                            return absolute === 1 ? 'week' : 'weeks';
                        case 'month':
                            return absolute === 1 ? 'month' : 'months';
                        default:
                            return absolute === 1 ? 'year' : 'years';
                    }
                })();

                return `${absolute} ${unitLabel} ago`;
            };

            if (absDiff < 60) {
                return formatWithUnit(Math.round(diffSeconds), 'second');
            }
            if (absDiff < 3600) {
                return formatWithUnit(-Math.round(absDiff / 60), 'minute');
            }
            if (absDiff < 86400) {
                return formatWithUnit(-Math.round(absDiff / 3600), 'hour');
            }
            if (absDiff < 604800) {
                return formatWithUnit(-Math.round(absDiff / 86400), 'day');
            }
            if (absDiff < 2629800) {
                return formatWithUnit(-Math.round(absDiff / 604800), 'week');
            }
            if (absDiff < 31557600) {
                return formatWithUnit(-Math.round(absDiff / 2629800), 'month');
            }
            return formatWithUnit(-Math.round(absDiff / 31557600), 'year');
        };

        const normalizeTabKey = (value) => (typeof value === 'string' ? value.toLowerCase() : '');
        const strings = typeof data.strings === 'object' && data.strings !== null ? data.strings : {};
        const tabEndpoints = (data.tabEndpoints && typeof data.tabEndpoints === 'object') ? data.tabEndpoints : {};
        const tabUrls = (data.tabUrls && typeof data.tabUrls === 'object') ? data.tabUrls : {};
        const restNonce = data.restNonce || data.nonce || '';
        const activityStrings = {
            loadMore: strings.loadMoreActivity || (activityLoadMoreButton ? activityLoadMoreButton.textContent.trim() : ''),
            loading: strings.loading || 'Loading…',
            error: strings.error || 'Something went wrong. Please try again.',
            empty: strings.noActivity || '',
        };
        const notificationsStrings = {
            loading: strings.notificationsLoading || strings.loading || 'Loading…',
            empty: strings.notificationsEmpty || 'You have no notifications yet.',
            loadError: strings.notificationsLoadError || strings.error || 'Something went wrong. Please try again.',
            markError: strings.notificationsMarkError || strings.error || 'Something went wrong. Please try again.',
            markAllComplete: strings.notificationsMarkAllComplete || '',
        };
        const followStrings = {
            follow: strings.follow || 'Follow',
            following: strings.following || 'Following',
            loading: strings.loading || 'Loading…',
            error: strings.error || 'Something went wrong. Please try again.',
        };
        const showToastMessage = (message, variant = 'info') => {
            if (!message) {
                return;
            }
            if (typeof window.GTAModsShowToast === 'function') {
                window.GTAModsShowToast(message, variant);
            }
        };

        const bannerInput = document.getElementById('gta6mods-settings-banner');
        const bannerPreview = document.getElementById('gta6mods-banner-preview');
        const bannerRemoveButton = document.getElementById('gta6mods-remove-banner');
        const headerBackground = document.querySelector('.header-background');
        const avatarInput = document.getElementById('gta6mods-settings-avatar');
        const avatarPreview = document.getElementById('gta6mods-avatar-preview');
        const avatarDeleteButton = document.getElementById('gta6mods-delete-avatar');
        const avatarDeleteModal = document.getElementById('gta6mods-delete-avatar-modal');
        const avatarDeleteOverlay = avatarDeleteModal ? avatarDeleteModal.querySelector('[data-avatar-delete-overlay]') : null;
        const avatarDeleteCancelButtons = avatarDeleteModal
            ? Array.from(avatarDeleteModal.querySelectorAll('[data-avatar-delete-cancel]'))
            : [];
        const avatarDeleteConfirm = document.getElementById('gta6mods-delete-avatar-confirm');
        const presetGrid = document.getElementById('avatar-selection-grid');
        const presetSelectedClasses = ['ring-2', 'ring-pink-500', 'ring-offset-2'];
        const presetButtons = presetGrid
            ? Array.from(presetGrid.querySelectorAll('[data-avatar-id]'))
            : [];
        const saveChangesButton = document.getElementById('gta6mods-save-profile');
        const heroAvatarElements = Array.from(document.querySelectorAll('[data-author-primary-avatar], #account-menu-button img'));
        const heroAvatarDefaults = new Map();

        heroAvatarElements.forEach((element) => {
            if (element instanceof HTMLImageElement) {
                const defaultSrc = element.getAttribute('data-default-avatar')
                    || element.getAttribute('src')
                    || '';
                const defaultSrcset = element.getAttribute('srcset') || '';
                heroAvatarDefaults.set(element, {
                    type: 'image',
                    src: defaultSrc,
                    srcset: defaultSrcset,
                });
            } else if (element) {
                heroAvatarDefaults.set(element, {
                    type: 'background',
                    value: element.style.backgroundImage || '',
                });
            }
        });

        const mediaOperations = {
            avatar: {
                hasPending: () => false,
                commit: async () => {},
            },
            banner: {
                hasPending: () => false,
                commit: async () => {},
            },
        };

        const initializeBannerManagement = () => {
            if (!isOwner || !restBase || !Number.isFinite(authorId) || authorId <= 0) {
                return;
            }

            const bannerMaxSize = Number(data.bannerMaxSize || (2 * 1024 * 1024));
            const bannerStrings = {
                tooLarge: strings.bannerTooLarge || strings.error || 'File is too large.',
                uploaded: strings.bannerUploaded || strings.saved || '',
                removed: strings.bannerRemoved || strings.saved || '',
                confirm: strings.bannerRemoveConfirm || '',
                error: strings.error || 'Something went wrong. Please try again.',
            };

            const endpoint = `${restBase}/author/${authorId}/banner`;

            let bannerCommittedUrl = typeof data.bannerUrl === 'string' ? data.bannerUrl : '';
            let pendingBannerFile = null;
            let pendingBannerObjectUrl = null;
            let bannerUploadInProgress = false;

            const updateBannerPreview = (url, options = {}) => {
                const displayUrl = typeof url === 'string' ? url : '';
                const commit = Boolean(options.commit);

                if (bannerPreview) {
                    const placeholder = bannerPreview.querySelector('.gta6mods-banner-empty');
                    if (displayUrl) {
                        bannerPreview.classList.add('has-banner');
                        bannerPreview.style.backgroundImage = `url('${displayUrl}')`;
                        if (placeholder) {
                            placeholder.classList.add('hidden');
                        }
                        if (bannerRemoveButton) {
                            bannerRemoveButton.classList.remove('hidden');
                            bannerRemoveButton.style.display = '';
                            bannerRemoveButton.disabled = false;
                        }
                    } else {
                        bannerPreview.classList.remove('has-banner');
                        bannerPreview.style.removeProperty('background-image');
                        if (placeholder) {
                            placeholder.classList.remove('hidden');
                        }
                        if (bannerRemoveButton) {
                            bannerRemoveButton.classList.add('hidden');
                            bannerRemoveButton.style.display = 'none';
                            bannerRemoveButton.disabled = false;
                        }
                    }
                }

                if (headerBackground) {
                    if (displayUrl) {
                        const serialized = JSON.stringify(displayUrl);
                        headerBackground.style.setProperty('--header-bg-image', `url(${serialized})`);
                    } else {
                        headerBackground.style.removeProperty('--header-bg-image');
                    }
                }

                if (commit) {
                    bannerCommittedUrl = displayUrl;
                    data.bannerUrl = displayUrl;
                    if (window.GTAModsAuthorProfile) {
                        window.GTAModsAuthorProfile.bannerUrl = displayUrl;
                    }
                }
            };

            const setBannerLoading = (isLoading) => {
                if (bannerInput) {
                    bannerInput.disabled = isLoading;
                }
                if (bannerRemoveButton) {
                    bannerRemoveButton.disabled = isLoading;
                }
            };

            const uploadBannerFile = async (file) => {
                const formData = new FormData();
                formData.append('banner_file', file, file.name);

                const headers = {};
                if (restNonce) {
                    headers['X-WP-Nonce'] = restNonce;
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body: formData,
                });

                const payload = await response.json().catch(() => null);
                if (!response.ok) {
                    const message = payload && payload.message ? payload.message : bannerStrings.error;
                    throw new Error(message);
                }

                return payload || {};
            };

            const deleteBannerFile = async () => {
                const headers = {};
                if (restNonce) {
                    headers['X-WP-Nonce'] = restNonce;
                }

                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers,
                });

                const payload = await response.json().catch(() => null);
                if (!response.ok) {
                    const message = payload && payload.message ? payload.message : bannerStrings.error;
                    throw new Error(message);
                }

                return payload || {};
            };

            const resetPendingBanner = () => {
                if (pendingBannerObjectUrl) {
                    URL.revokeObjectURL(pendingBannerObjectUrl);
                }
                pendingBannerObjectUrl = null;
                pendingBannerFile = null;
                if (bannerInput) {
                    bannerInput.value = '';
                }
            };

            updateBannerPreview(bannerCommittedUrl, { commit: true });

            if (bannerInput) {
                bannerInput.addEventListener('change', () => {
                    const files = bannerInput.files;
                    if (!files || !files.length) {
                        resetPendingBanner();
                        updateBannerPreview(bannerCommittedUrl, { commit: true });
                        return;
                    }

                    const file = files[0];
                    if (file.size && file.size > bannerMaxSize) {
                        showToastMessage(bannerStrings.tooLarge, 'warning');
                        bannerInput.value = '';
                        return;
                    }

                    const allowedBannerExt = /\.(jpe?g|png|webp)$/i;
                    if (file.type && !/image\/(jpe?g|png|webp)/i.test(file.type)) {
                        showToastMessage(bannerStrings.error, 'error');
                        bannerInput.value = '';
                        return;
                    }

                    if (!file.type && file.name && !allowedBannerExt.test(file.name)) {
                        showToastMessage(bannerStrings.error, 'error');
                        bannerInput.value = '';
                        return;
                    }

                    if (pendingBannerObjectUrl) {
                        URL.revokeObjectURL(pendingBannerObjectUrl);
                    }

                    pendingBannerFile = file;
                    pendingBannerObjectUrl = URL.createObjectURL(file);
                    updateBannerPreview(pendingBannerObjectUrl);
                });
            }

            const commitPendingBannerUpload = async () => {
                if (!pendingBannerFile || bannerUploadInProgress) {
                    return null;
                }

                bannerUploadInProgress = true;
                setBannerLoading(true);

                const file = pendingBannerFile;
                const previousUrl = bannerCommittedUrl;

                try {
                    const payload = await uploadBannerFile(file);
                    const finalUrl = payload && typeof payload.url === 'string' ? payload.url : '';
                    updateBannerPreview(finalUrl, { commit: true });
                    if (bannerStrings.uploaded) {
                        showToastMessage(bannerStrings.uploaded, 'success');
                    }
                    return payload;
                } catch (error) {
                    updateBannerPreview(previousUrl, { commit: true });
                    showToastMessage(error && error.message ? error.message : bannerStrings.error, 'error');
                    throw error;
                } finally {
                    resetPendingBanner();
                    bannerUploadInProgress = false;
                    setBannerLoading(false);
                }
            };

            mediaOperations.banner.hasPending = () => Boolean(pendingBannerFile);
            mediaOperations.banner.commit = commitPendingBannerUpload;

            if (bannerRemoveButton) {
                bannerRemoveButton.addEventListener('click', async () => {
                    if (bannerRemoveButton.disabled) {
                        return;
                    }

                    if (pendingBannerFile) {
                        resetPendingBanner();
                        updateBannerPreview(bannerCommittedUrl, { commit: true });
                        return;
                    }

                    const confirmation = bannerStrings.confirm
                        ? window.confirm(bannerStrings.confirm)
                        : window.confirm('Remove banner?');

                    if (!confirmation) {
                        return;
                    }

                    setBannerLoading(true);

                    try {
                        await deleteBannerFile();
                        resetPendingBanner();
                        updateBannerPreview('', { commit: true });
                        if (bannerStrings.removed) {
                            showToastMessage(bannerStrings.removed, 'success');
                        }
                    } catch (error) {
                        showToastMessage(error && error.message ? error.message : bannerStrings.error, 'error');
                    } finally {
                        setBannerLoading(false);
                    }
                });
            }
        };

        const initializeAvatarManagement = () => {
            if (!isOwner || !restBase || !Number.isFinite(authorId) || authorId <= 0) {
                return;
            }

            const avatarMaxSize = Number(data.avatarMaxSize || (1024 * 1024));
            const avatarStrings = {
                tooLarge: strings.avatarTooLarge || strings.error || 'File is too large.',
                uploadFailed: strings.avatarUploadFailed || strings.error || 'Something went wrong. Please try again.',
                deleteSuccess: strings.avatarDeleteSuccess || strings.saved || '',
                deleteFailed: strings.avatarDeleteFailed || strings.error || 'Something went wrong. Please try again.',
                uploadSuccess: strings.saved || '',
            };

            const endpoint = `${restBase}/author/${authorId}/avatar`;

            let avatarState = (data.avatar && typeof data.avatar === 'object') ? { ...data.avatar } : {};
            if (avatarState && typeof avatarState === 'object') {
                if (!avatarState.defaultUrl && avatarState.url) {
                    avatarState.defaultUrl = avatarState.url;
                }
            }

            let committedAvatarState = avatarState && typeof avatarState === 'object' ? { ...avatarState } : {};
            let pendingAvatarFile = null;
            let avatarObjectUrl = null;
            let avatarUploadInProgress = false;
            let avatarDeleteInProgress = false;
            let selectedPreset = (avatarState && avatarState.type === 'preset')
                ? avatarState.preset || null
                : null;
            let avatarPresetDirty = false;

            const canDeleteCurrentAvatar = () => {
                if (!avatarState || typeof avatarState !== 'object') {
                    return false;
                }
                if (pendingAvatarFile) {
                    return false;
                }
                const attachmentId = Number.parseInt(avatarState.attachmentId, 10);
                return avatarState.type === 'custom'
                    && Number.isFinite(attachmentId)
                    && attachmentId > 0;
            };

            const getCurrentAvatarUrl = () => {
                if (!avatarState || typeof avatarState !== 'object') {
                    return '';
                }
                const url = avatarState.url || avatarState.defaultUrl;
                return typeof url === 'string' ? url : '';
            };

            const updateAvatarPreviewImage = (url) => {
                if (!avatarPreview) {
                    return;
                }
                const displayUrl = (typeof url === 'string' && url) ? url : getCurrentAvatarUrl();
                if (displayUrl) {
                    avatarPreview.src = displayUrl;
                }
            };

            const refreshPresetSelectionUI = () => {
                if (!presetButtons.length) {
                    return;
                }

                presetButtons.forEach((button) => {
                    const isActive = Boolean(selectedPreset) && button.dataset.avatarId === selectedPreset;
                    presetSelectedClasses.forEach((className) => {
                        if (!className) {
                            return;
                        }
                        if (isActive) {
                            button.classList.add(className);
                        } else {
                            button.classList.remove(className);
                        }
                    });
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            const getPresetUrlById = (presetId) => {
                if (!presetId || !presetButtons.length) {
                    return '';
                }

                let presetUrl = '';
                presetButtons.some((button) => {
                    if (button.dataset.avatarId === presetId && button.dataset.avatarUrl) {
                        presetUrl = button.dataset.avatarUrl;
                        return true;
                    }
                    return false;
                });

                return presetUrl;
            };

            const cleanupObjectUrl = () => {
                if (avatarObjectUrl) {
                    URL.revokeObjectURL(avatarObjectUrl);
                    avatarObjectUrl = null;
                }
            };

            const updateAvatarDeleteButton = () => {
                if (!avatarDeleteButton) {
                    return;
                }
                const canDelete = canDeleteCurrentAvatar();

                avatarDeleteButton.classList.toggle('hidden', !canDelete);
                avatarDeleteButton.setAttribute('aria-hidden', canDelete ? 'false' : 'true');
                avatarDeleteButton.disabled = !canDelete || avatarUploadInProgress;
            };

            const applyAvatarState = (nextState, options = {}) => {
                if (!nextState || typeof nextState !== 'object') {
                    return;
                }

                avatarState = {
                    ...avatarState,
                    ...nextState,
                };

                if (!avatarState.defaultUrl && nextState.defaultUrl) {
                    avatarState.defaultUrl = nextState.defaultUrl;
                }

                data.avatar = avatarState;
                if (window.GTAModsAuthorProfile) {
                    window.GTAModsAuthorProfile.avatar = avatarState;
                }

                const displayUrl = avatarState.url || avatarState.defaultUrl || '';
                const displaySrcset = typeof avatarState.srcset === 'string' ? avatarState.srcset : '';
                updateAvatarPreviewImage(displayUrl);

                heroAvatarElements.forEach((element) => {
                    const defaults = heroAvatarDefaults.get(element) || {};

                    if (element instanceof HTMLImageElement) {
                        const defaultSrc = defaults.src || '';
                        const urlToUse = displayUrl || defaultSrc;

                        if (urlToUse) {
                            element.src = urlToUse;
                        }

                        if (displaySrcset) {
                            element.setAttribute('srcset', displaySrcset);
                        } else if (!displayUrl && defaults.srcset) {
                            element.setAttribute('srcset', defaults.srcset);
                        } else {
                            element.removeAttribute('srcset');
                        }
                    } else if (element) {
                        const defaultBackground = defaults.value || '';

                        if (displayUrl) {
                            element.style.backgroundImage = `url('${displayUrl}')`;
                        } else if (defaultBackground) {
                            element.style.backgroundImage = defaultBackground;
                        } else {
                            element.style.removeProperty('background-image');
                        }
                    }
                });

                if (avatarState.type === 'preset') {
                    selectedPreset = avatarState.preset || null;
                } else if (avatarState.type === 'custom') {
                    selectedPreset = null;
                }

                if (options.commit) {
                    committedAvatarState = { ...avatarState };
                    avatarPresetDirty = false;
                }

                refreshPresetSelectionUI();
                updateAvatarDeleteButton();
            };

            const setAvatarLoading = (isLoading) => {
                avatarUploadInProgress = isLoading;
                if (avatarInput) {
                    avatarInput.disabled = isLoading;
                }
                if (avatarPreview) {
                    avatarPreview.classList.toggle('opacity-60', isLoading);
                }
                if (isLoading) {
                    if (avatarDeleteButton) {
                        avatarDeleteButton.disabled = true;
                    }
                } else {
                    updateAvatarDeleteButton();
                }
            };

            const setAvatarDeleteLoading = (isLoading) => {
                avatarDeleteInProgress = isLoading;
                if (avatarDeleteConfirm) {
                    avatarDeleteConfirm.disabled = isLoading;
                    avatarDeleteConfirm.classList.toggle('opacity-60', isLoading);
                }
                if (avatarDeleteButton) {
                    avatarDeleteButton.disabled = isLoading || avatarUploadInProgress || !canDeleteCurrentAvatar();
                }
            };

            const handleAvatarDeleteKeydown = (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeAvatarDeleteModal();
                }
            };

            const openAvatarDeleteModal = () => {
                if (!avatarDeleteModal || !canDeleteCurrentAvatar()) {
                    return;
                }

                avatarDeleteModal.classList.remove('hidden');
                avatarDeleteModal.setAttribute('aria-hidden', 'false');
                document.addEventListener('keydown', handleAvatarDeleteKeydown);
                window.setTimeout(() => {
                    if (avatarDeleteConfirm) {
                        avatarDeleteConfirm.focus();
                    }
                }, 0);
            };

            const closeAvatarDeleteModal = () => {
                if (!avatarDeleteModal) {
                    return;
                }

                avatarDeleteModal.classList.add('hidden');
                avatarDeleteModal.setAttribute('aria-hidden', 'true');
                document.removeEventListener('keydown', handleAvatarDeleteKeydown);
            };

            const uploadAvatarFile = async (file) => {
                const formData = new FormData();
                formData.append('avatar_file', file, file.name);

                const headers = {};
                if (restNonce) {
                    headers['X-WP-Nonce'] = restNonce;
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body: formData,
                });

                const payload = await response.json().catch(() => null);
                if (!response.ok) {
                    const message = payload && payload.message ? payload.message : avatarStrings.uploadFailed;
                    throw new Error(message);
                }

                return payload || {};
            };

            const deleteAvatarFile = async () => {
                const headers = {};
                if (restNonce) {
                    headers['X-WP-Nonce'] = restNonce;
                }

                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers,
                });

                const payload = await response.json().catch(() => null);
                if (!response.ok) {
                    const message = payload && payload.message ? payload.message : avatarStrings.deleteFailed;
                    throw new Error(message);
                }

                return payload || {};
            };

            const saveAvatarPresetSelection = async () => {
                const headers = { 'Content-Type': 'application/json' };
                if (restNonce) {
                    headers['X-WP-Nonce'] = restNonce;
                }

                const payload = {};
                if (selectedPreset) {
                    payload.avatarPreset = selectedPreset;
                } else {
                    payload.clearAvatar = true;
                }

                const response = await fetch(`${restBase}/author/settings`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body: JSON.stringify(payload),
                });

                const dataPayload = await response.json().catch(() => null);
                if (!response.ok) {
                    const message = dataPayload && dataPayload.message
                        ? dataPayload.message
                        : strings.error || avatarStrings.deleteFailed;
                    throw new Error(message);
                }

                return dataPayload || {};
            };

            applyAvatarState(avatarState, { commit: true });

            if (avatarInput) {
                avatarInput.addEventListener('change', () => {
                    const files = avatarInput.files;
                    cleanupObjectUrl();

                    if (!files || !files.length) {
                        pendingAvatarFile = null;
                        applyAvatarState(committedAvatarState, { commit: true });
                        return;
                    }

                    const file = files[0];
                    if (file.size && file.size > avatarMaxSize) {
                        showToastMessage(avatarStrings.tooLarge, 'warning');
                        avatarInput.value = '';
                        pendingAvatarFile = null;
                        applyAvatarState(committedAvatarState, { commit: true });
                        return;
                    }

                    const allowedAvatarExt = /\.(jpe?g|png|webp)$/i;
                    if (file.type && !/image\/(jpe?g|png|webp)/i.test(file.type)) {
                        showToastMessage(avatarStrings.uploadFailed, 'error');
                        avatarInput.value = '';
                        pendingAvatarFile = null;
                        applyAvatarState(committedAvatarState, { commit: true });
                        return;
                    }

                    if (!file.type && file.name && !allowedAvatarExt.test(file.name)) {
                        showToastMessage(avatarStrings.uploadFailed, 'error');
                        avatarInput.value = '';
                        pendingAvatarFile = null;
                        applyAvatarState(committedAvatarState, { commit: true });
                        return;
                    }

                    pendingAvatarFile = file;
                    avatarPresetDirty = false;
                    selectedPreset = null;
                    avatarObjectUrl = URL.createObjectURL(file);
                    updateAvatarPreviewImage(avatarObjectUrl);
                    refreshPresetSelectionUI();
                    updateAvatarDeleteButton();
                });
            }

            const commitPendingAvatarChanges = async () => {
                let result = null;

                if (pendingAvatarFile && !avatarUploadInProgress) {
                    setAvatarLoading(true);

                    const file = pendingAvatarFile;
                    const previousState = committedAvatarState ? { ...committedAvatarState } : {};

                    try {
                        const payload = await uploadAvatarFile(file);
                        let nextState = null;
                        if (payload && payload.avatar) {
                            nextState = payload.avatar;
                        } else if (payload && typeof payload.url === 'string') {
                            nextState = {
                                url: payload.url,
                                attachmentId: payload.attachment_id,
                                type: 'custom',
                            };
                        }

                        if (nextState) {
                            applyAvatarState(nextState, { commit: true });
                        }
                        if (avatarStrings.uploadSuccess) {
                            showToastMessage(avatarStrings.uploadSuccess, 'success');
                        }
                        result = payload;
                    } catch (error) {
                        applyAvatarState(previousState, { commit: true });
                        showToastMessage(error && error.message ? error.message : avatarStrings.uploadFailed, 'error');
                        throw error;
                    } finally {
                        pendingAvatarFile = null;
                        if (avatarInput) {
                            avatarInput.value = '';
                        }
                        cleanupObjectUrl();
                        setAvatarLoading(false);
                    }
                }

                if (avatarPresetDirty) {
                    try {
                        const payload = await saveAvatarPresetSelection();
                        if (payload && payload.avatar) {
                            applyAvatarState(payload.avatar, { commit: true });
                        }
                        const successMessage = strings.saved || avatarStrings.uploadSuccess;
                        if (successMessage) {
                            showToastMessage(successMessage, 'success');
                        }
                        result = payload || result;
                        avatarPresetDirty = false;
                    } catch (error) {
                        showToastMessage(error && error.message ? error.message : strings.error, 'error');
                        throw error;
                    }
                }

                return result;
            };

            mediaOperations.avatar.hasPending = () => Boolean(pendingAvatarFile) || avatarPresetDirty;
            mediaOperations.avatar.commit = commitPendingAvatarChanges;

            if (presetButtons.length) {
                presetButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const id = button.dataset.avatarId || '';
                        const url = button.dataset.avatarUrl || '';

                        if (!id || !url) {
                            return;
                        }

                        if (selectedPreset === id) {
                            selectedPreset = null;
                            avatarPresetDirty = true;
                            updateAvatarPreviewImage();
                        } else {
                            selectedPreset = id;
                            avatarPresetDirty = true;
                            updateAvatarPreviewImage(url);
                        }

                        pendingAvatarFile = null;
                        if (avatarInput) {
                            avatarInput.value = '';
                        }
                        cleanupObjectUrl();
                        refreshPresetSelectionUI();
                        updateAvatarDeleteButton();
                    });
                });

                if (selectedPreset) {
                    const presetUrl = getPresetUrlById(selectedPreset);
                    if (presetUrl) {
                        updateAvatarPreviewImage(presetUrl);
                    }
                }

                refreshPresetSelectionUI();
            } else {
                refreshPresetSelectionUI();
            }

            if (avatarDeleteButton) {
                avatarDeleteButton.addEventListener('click', () => {
                    if (avatarDeleteButton.disabled) {
                        return;
                    }
                    openAvatarDeleteModal();
                });
            }

            if (avatarDeleteOverlay) {
                avatarDeleteOverlay.addEventListener('click', () => {
                    if (!avatarDeleteInProgress) {
                        closeAvatarDeleteModal();
                    }
                });
            }

            avatarDeleteCancelButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (!avatarDeleteInProgress) {
                        closeAvatarDeleteModal();
                    }
                });
            });

            if (avatarDeleteConfirm) {
                avatarDeleteConfirm.addEventListener('click', async () => {
                    if (avatarDeleteInProgress) {
                        return;
                    }

                    if (!canDeleteCurrentAvatar()) {
                        return;
                    }

                    setAvatarDeleteLoading(true);

                    try {
                        const pendingPresetSelection = avatarPresetDirty ? selectedPreset : null;
                        const payload = await deleteAvatarFile();
                        closeAvatarDeleteModal();
                        pendingAvatarFile = null;
                        cleanupObjectUrl();
                        if (payload && payload.avatar) {
                            applyAvatarState(payload.avatar, { commit: true });
                        } else {
                            applyAvatarState({ url: payload && payload.url ? payload.url : '' }, { commit: true });
                        }

                        if (pendingPresetSelection) {
                            selectedPreset = pendingPresetSelection;
                            avatarPresetDirty = true;
                            const presetUrl = getPresetUrlById(pendingPresetSelection);
                            if (presetUrl) {
                                updateAvatarPreviewImage(presetUrl);
                            } else {
                                updateAvatarPreviewImage();
                            }
                            refreshPresetSelectionUI();
                        } else {
                            avatarPresetDirty = false;
                            selectedPreset = null;
                            refreshPresetSelectionUI();
                        }

                        updateAvatarDeleteButton();
                        if (avatarStrings.deleteSuccess) {
                            showToastMessage(avatarStrings.deleteSuccess, 'success');
                        }
                    } catch (error) {
                        closeAvatarDeleteModal();
                        showToastMessage(error && error.message ? error.message : avatarStrings.deleteFailed, 'error');
                    } finally {
                        setAvatarDeleteLoading(false);
                    }
                });
            }
        };

        const notificationsLimitRaw = Number(data.notificationsDropdownLimit);
        const notificationsLimit = Number.isFinite(notificationsLimitRaw)
            ? Math.max(1, Math.min(10, Math.floor(notificationsLimitRaw)))
            : 5;
        const notificationsState = {
            loaded: notificationsContent ? notificationsContent.getAttribute('data-loaded') === '1' : false,
            loading: notificationsContent ? notificationsContent.getAttribute('data-loading') === '1' : false,
            lastUnreadIds: [],
        };
        const activityState = {
            offset: activityLoadMoreButton ? Number.parseInt(activityLoadMoreButton.getAttribute('data-offset') || '0', 10) || 0 : 0,
            loading: false,
            loaded: false,
            hasMore: true,
            controller: null,
        };
        const tabPanels = new Map();
        const tabButtons = [];
        const tabCache = new Map();
        const pendingRequests = new Map();

        const getPanelEntry = (key) => {
            if (!key || !tabPanels.has(key)) {
                return null;
            }
            return tabPanels.get(key);
        };

        const getAllPanelsForKey = (key) => {
            const entry = getPanelEntry(key);
            if (!entry || !Array.isArray(entry.elements)) {
                return [];
            }

            return entry.elements.filter((panel) => panel instanceof HTMLElement);
        };

        const getPrimaryPanelForKey = (key) => {
            const entry = getPanelEntry(key);
            if (!entry) {
                return null;
            }

            if (entry.primary instanceof HTMLElement) {
                return entry.primary;
            }

            const panels = getAllPanelsForKey(key);
            return panels.length > 0 ? panels[0] : null;
        };
        let currentTab = normalizeTabKey(data.activeTab) || 'overview';
        let currentPage = Math.max(1, Number.parseInt(data.initialPage, 10) || 1);

        const openMediaLightbox = (anchor) => {
            if (!anchor) {
                return;
            }

            const href = anchor.getAttribute('href') || anchor.dataset.href || '';
            if (!href) {
                return;
            }

            if (typeof PhotoSwipeLightbox !== 'function' || typeof PhotoSwipe === 'undefined') {
                window.open(href, '_blank');
                return;
            }

            const thumbnailImage = anchor.querySelector('img');
            const widthAttr = Number.parseInt(anchor.dataset.pswpWidth || '0', 10);
            const heightAttr = Number.parseInt(anchor.dataset.pswpHeight || '0', 10);

            const dataSource = [{
                src: href,
                width: widthAttr,
                height: heightAttr,
                alt: thumbnailImage && typeof thumbnailImage.alt === 'string' ? thumbnailImage.alt : '',
                element: thumbnailImage || anchor,
            }];

            const lightbox = new PhotoSwipeLightbox({
                dataSource,
                pswpModule: PhotoSwipe,
                showHideAnimationType: 'zoom',
            });

            const openLightbox = () => {
                lightbox.init();
                lightbox.loadAndOpen(0);
            };

            if (widthAttr > 0 && heightAttr > 0) {
                openLightbox();
                return;
            }

            if (thumbnailImage && thumbnailImage.complete && thumbnailImage.naturalWidth > 0 && thumbnailImage.naturalHeight > 0) {
                dataSource[0].width = thumbnailImage.naturalWidth;
                dataSource[0].height = thumbnailImage.naturalHeight;
                openLightbox();
                return;
            }

            const fallbackWidth = (thumbnailImage && (thumbnailImage.naturalWidth || thumbnailImage.width || thumbnailImage.clientWidth)) || 480;
            const fallbackHeight = (thumbnailImage && (thumbnailImage.naturalHeight || thumbnailImage.height || thumbnailImage.clientHeight)) || 270;

            const preloadImage = new Image();
            preloadImage.onload = () => {
                dataSource[0].width = preloadImage.naturalWidth || fallbackWidth;
                dataSource[0].height = preloadImage.naturalHeight || fallbackHeight;
                openLightbox();
            };
            preloadImage.onerror = () => {
                dataSource[0].width = fallbackWidth;
                dataSource[0].height = fallbackHeight;
                openLightbox();
            };
            preloadImage.src = href;
        };

        const bindLightboxDelegation = (root) => {
            if (!root || root.dataset.lightboxDelegation === '1') {
                return;
            }

            root.addEventListener('click', (event) => {
                const lightboxItem = event.target.closest('.comment-lightbox-item');
                if (!lightboxItem || !root.contains(lightboxItem)) {
                    return;
                }

                event.preventDefault();
                openMediaLightbox(lightboxItem);
            });

            root.dataset.lightboxDelegation = '1';
        };

        const collectTabElements = () => {
            tabPanels.clear();
            document.querySelectorAll('[data-tab-content]').forEach((panel) => {
                const key = normalizeTabKey(panel.getAttribute('data-tab-content'));
                if (!key) {
                    return;
                }

                const entry = tabPanels.get(key) || { elements: [], primary: null };
                entry.elements.push(panel);

                const isTabPanel = panel.classList && panel.classList.contains('tab-content');
                if (!entry.primary) {
                    entry.primary = panel;
                } else if (isTabPanel && !(entry.primary && entry.primary.classList && entry.primary.classList.contains('tab-content'))) {
                    entry.primary = panel;
                }

                tabPanels.set(key, entry);

                const asyncContainer = panel.querySelector('[data-author-async-content]');
                if (asyncContainer) {
                    bindLightboxDelegation(asyncContainer);
                }
            });

            tabButtons.length = 0;
            document.querySelectorAll('[data-tab-key]').forEach((button) => {
                tabButtons.push(button);
            });
        };

        const setTabVisibility = (activeKey) => {
            tabPanels.forEach((entry, key) => {
                const isActive = key === activeKey;
                const panels = entry && Array.isArray(entry.elements) ? entry.elements : [];

                panels.forEach((panel) => {
                    if (!(panel instanceof HTMLElement)) {
                        return;
                    }

                    if (isActive) {
                        panel.classList.remove('hidden');
                        panel.setAttribute('aria-hidden', 'false');
                    } else {
                        panel.classList.add('hidden');
                        panel.setAttribute('aria-hidden', 'true');
                    }
                });
            });
        };

        const buildTabUrl = (tabKey, pageNumber) => {
            const normalizedKey = normalizeTabKey(tabKey);
            const baseUrlRaw = (tabUrls && typeof tabUrls === 'object' && typeof tabUrls[normalizedKey] === 'string')
                ? tabUrls[normalizedKey]
                : '';

            if (!baseUrlRaw) {
                return '';
            }

            const normalizedPage = Math.max(1, Number.parseInt(pageNumber, 10) || 1);
            if (normalizedPage <= 1) {
                return baseUrlRaw;
            }

            try {
                const origin = (typeof window !== 'undefined' && window.location && window.location.origin)
                    ? window.location.origin
                    : undefined;
                const targetUrl = origin ? new URL(baseUrlRaw, origin) : new URL(baseUrlRaw);
                const pathname = targetUrl.pathname.endsWith('/') ? targetUrl.pathname : `${targetUrl.pathname}/`;
                targetUrl.pathname = `${pathname}page-${normalizedPage}/`;
                return targetUrl.toString();
            } catch (error) {
                const trimmed = baseUrlRaw.endsWith('/') ? baseUrlRaw : `${baseUrlRaw}/`;
                return `${trimmed}page-${normalizedPage}/`;
            }
        };

        const updateHistoryState = (tabKey, pageNumber) => {
            if (typeof window === 'undefined' || !window.history || typeof window.history.replaceState !== 'function') {
                return;
            }

            const targetUrl = buildTabUrl(tabKey, pageNumber);
            if (!targetUrl || targetUrl === window.location.href) {
                return;
            }

            try {
                window.history.replaceState(window.history.state, '', targetUrl);
            } catch (error) {
                try {
                    window.history.replaceState({}, '', targetUrl);
                } catch (innerError) {
                    // Ignore history failures silently.
                }
            }
        };

        const updateTabButtons = (activeKey) => {
            tabButtons.forEach((button) => {
                const key = normalizeTabKey(button.getAttribute('data-tab-key'));
                if (!key) {
                    return;
                }

                const isActive = key === activeKey;
                if (isActive) {
                    button.classList.add('active');
                    button.setAttribute('aria-selected', 'true');
                    button.setAttribute('aria-current', 'page');
                } else {
                    button.classList.remove('active');
                    button.setAttribute('aria-selected', 'false');
                    button.removeAttribute('aria-current');
                }
            });
        };

        const setLoadingState = (panel, isLoading) => {
            if (!panel) {
                return;
            }
            const loader = panel.querySelector('[data-author-loading]');
            if (loader) {
                loader.classList.toggle('hidden', !isLoading);
            }
        };

        const renderPanelContent = (panel, html) => {
            if (!panel) {
                return;
            }
            const target = panel.querySelector('[data-author-async-content]');
            if (target) {
                target.innerHTML = html || '';
                bindLightboxDelegation(target);
            } else {
                panel.innerHTML = html || '';
                bindLightboxDelegation(panel);
            }
        };

        const buildEndpointUrl = (tabKey, page) => {
            const endpoint = tabEndpoints[tabKey];
            if (!endpoint) {
                return null;
            }

            try {
                const url = new URL(endpoint, window.location.origin);
                if (page > 1) {
                    url.searchParams.set('page', String(page));
                }
                return url.toString();
            } catch (error) {
                if (page > 1) {
                    const separator = endpoint.includes('?') ? '&' : '?';
                    return `${endpoint}${separator}page=${encodeURIComponent(String(page))}`;
                }
                return endpoint;
            }
        };

        const buildActivityRequestUrl = (offsetValue) => {
            if (!restBase || !Number.isFinite(authorId) || authorId <= 0) {
                return null;
            }

            const endpoint = `${restBase}/author/${authorId}/activity`;

            try {
                const url = new URL(endpoint, window.location.origin);
                if (offsetValue > 0) {
                    url.searchParams.set('offset', String(offsetValue));
                }
                return url.toString();
            } catch (error) {
                if (offsetValue > 0) {
                    const separator = endpoint.includes('?') ? '&' : '?';
                    return `${endpoint}${separator}offset=${encodeURIComponent(String(offsetValue))}`;
                }
                return endpoint;
            }
        };

        const setActivityButtonLoading = (button, isLoading) => {
            if (!button) {
                return;
            }

            if (!button.dataset.defaultText) {
                button.dataset.defaultText = button.textContent;
            }

            if (isLoading) {
                const label = activityStrings.loading || button.dataset.defaultText || '';
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
                button.classList.add('opacity-60', 'pointer-events-none');
                button.innerHTML = `<span class="inline-block h-4 w-4 border-2 border-current border-t-transparent rounded-full animate-spin align-middle mr-2" aria-hidden="true"></span>${label}`;
            } else {
                const label = activityStrings.loadMore || button.dataset.defaultText || '';
                button.disabled = false;
                button.setAttribute('aria-disabled', 'false');
                button.classList.remove('opacity-60', 'pointer-events-none');
                button.textContent = label;
            }
        };

        const clearActivityMessages = () => {
            if (!activityList) {
                return;
            }

            activityList.querySelectorAll('[data-activity-empty],[data-activity-error]').forEach((element) => {
                element.remove();
            });
        };

        const appendActivityHtml = (html) => {
            if (!activityList || !html) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            const fragment = document.createDocumentFragment();

            while (wrapper.firstChild) {
                fragment.appendChild(wrapper.firstChild);
            }

            activityList.appendChild(fragment);
        };

        const insertActivityHtmlAtTop = (html) => {
            if (!activityList || !html) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            const fragment = document.createDocumentFragment();

            while (wrapper.firstChild) {
                fragment.appendChild(wrapper.firstChild);
            }

            activityList.insertBefore(fragment, activityList.firstChild || null);
        };

        const showActivityEmptyMessage = () => {
            if (!activityList || !activityStrings.empty) {
                return;
            }

            const message = document.createElement('div');
            message.className = 'text-sm text-gray-500 text-center py-6';
            message.textContent = activityStrings.empty;
            message.setAttribute('data-activity-empty', '1');
            activityList.appendChild(message);
        };

        const showActivityErrorMessage = () => {
            if (!activityList || !activityStrings.error) {
                return;
            }

            const message = document.createElement('div');
            message.className = 'text-sm text-red-500 text-center py-6';
            message.textContent = activityStrings.error;
            message.setAttribute('data-activity-error', '1');
            activityList.appendChild(message);
        };

        const updateNotificationBadge = (count) => {
            const unread = Math.max(0, Number.parseInt(count, 10) || 0);

            if (notificationsBadge) {
                if (unread > 0) {
                    notificationsBadge.classList.remove('hidden');
                } else {
                    notificationsBadge.classList.add('hidden');
                }
            }

            if (notificationsButton) {
                notificationsButton.setAttribute('data-unread-count', String(unread));
            }

            if (!Array.isArray(window.faviconBadgeQueue)) {
                window.faviconBadgeQueue = [];
            }

            if (window.faviconBadge && typeof window.faviconBadge.update === 'function') {
                if (unread > 0) {
                    window.faviconBadge.update(unread);
                } else if (typeof window.faviconBadge.reset === 'function') {
                    window.faviconBadge.reset();
                }
            } else {
                window.faviconBadgeQueue.push(unread);
            }

            return unread;
        };

        const setNotificationItemRead = (id) => {
            if (!notificationsContent) {
                return;
            }

            const item = notificationsContent.querySelector(`[data-notification-id="${id}"]`);

            if (!item) {
                return;
            }

            item.setAttribute('data-notification-unread', '0');
            item.setAttribute('data-notification-status', 'read');
            item.classList.remove('bg-sky-50', 'hover:bg-sky-100');
            if (!item.classList.contains('hover:bg-gray-50')) {
                item.classList.add('hover:bg-gray-50');
            }

            const srLabel = item.querySelector('span.sr-only');
            const readLabel = item.getAttribute('data-notification-read-label');
            if (srLabel && readLabel) {
                srLabel.textContent = readLabel;
            }

            const messageElement = item.querySelector('p');
            if (messageElement) {
                messageElement.classList.remove('font-semibold', 'text-gray-900');
                if (!messageElement.classList.contains('font-medium')) {
                    messageElement.classList.add('font-medium');
                }
                if (!messageElement.classList.contains('text-gray-700')) {
                    messageElement.classList.add('text-gray-700');
                }
            }

            const timeElement = item.querySelector('p.mt-1');
            if (timeElement) {
                timeElement.classList.remove('text-gray-500');
                if (!timeElement.classList.contains('text-gray-400')) {
                    timeElement.classList.add('text-gray-400');
                }
            }
        };

        const renderNotificationsMessage = (message, variant = 'neutral') => {
            if (!notificationsContent) {
                return;
            }

            if (!message) {
                notificationsContent.innerHTML = '';
                return;
            }

            const classes = variant === 'error'
                ? 'py-4 text-center text-sm text-red-500'
                : 'py-4 text-center text-sm text-gray-500';
            notificationsContent.innerHTML = `<div class="${classes}">${message}</div>`;
        };

        const markNotificationsRead = (ids = [], options = {}) => {
            if (!notificationsContent || !restBase || !Number.isFinite(authorId) || authorId <= 0) {
                return Promise.resolve(null);
            }

            const markAll = options.markAll === true;
            const updateUI = options.updateUI !== false;
            let sanitized = [];

            if (!markAll && Array.isArray(ids)) {
                sanitized = ids
                    .map((value) => Number.parseInt(value, 10))
                    .filter((value) => Number.isFinite(value) && value > 0);
            }

            if (!markAll && sanitized.length === 0) {
                return Promise.resolve(null);
            }

            const headers = { 'Content-Type': 'application/json' };
            if (restNonce) {
                headers['X-WP-Nonce'] = restNonce;
            }

            return fetch(`${restBase}/author/${authorId}/notifications/mark-read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify({
                    mark_all: markAll,
                    notification_ids: markAll ? [] : sanitized,
                }),
            })
                .then((response) => response.json().catch(() => null).then((payload) => {
                    if (!response.ok) {
                        const message = payload && payload.message ? payload.message : notificationsStrings.markError;
                        throw new Error(message);
                    }

                    return payload || {};
                }))
                .then((payload) => {
                    if (payload && typeof payload.count === 'number') {
                        updateNotificationBadge(payload.count);
                    }

                    let idsToUpdate = [];

                    if (markAll) {
                        idsToUpdate = Array.from(
                            notificationsContent.querySelectorAll('[data-notification-id]')
                        ).map((element) => Number.parseInt(element.getAttribute('data-notification-id') || '0', 10));
                    } else if (Array.isArray(payload.marked_ids) && payload.marked_ids.length > 0) {
                        idsToUpdate = payload.marked_ids.map((value) => Number.parseInt(value, 10));
                    } else {
                        idsToUpdate = sanitized;
                    }

                    idsToUpdate = idsToUpdate.filter((value) => Number.isFinite(value) && value > 0);

                    if (updateUI) {
                        idsToUpdate.forEach((value) => setNotificationItemRead(value));
                    }

                    if (markAll) {
                        notificationsState.lastUnreadIds = [];
                    } else if (idsToUpdate.length > 0) {
                        notificationsState.lastUnreadIds = notificationsState.lastUnreadIds.filter(
                            (value) => !idsToUpdate.includes(value)
                        );
                    }

                    return payload;
                });
        };

        const closeNotificationsDropdown = () => {
            if (!notificationsDropdown || !notificationsButton) {
                return;
            }

            notificationsDropdown.classList.add('hidden');
            notificationsDropdown.setAttribute('aria-hidden', 'true');
            notificationsButton.setAttribute('aria-expanded', 'false');
        };

        const loadNotifications = () => {
            if (!notificationsContent || notificationsState.loading || notificationsState.loaded) {
                return;
            }

            if (!restBase || !Number.isFinite(authorId) || authorId <= 0) {
                return;
            }

            notificationsState.loading = true;
            notificationsContent.dataset.loading = '1';
            renderNotificationsMessage(notificationsStrings.loading, 'neutral');

            const headers = {};
            if (restNonce) {
                headers['X-WP-Nonce'] = restNonce;
            }

            fetch(`${restBase}/author/${authorId}/notifications/recent?limit=${notificationsLimit}`, {
                credentials: 'same-origin',
                headers,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((payload) => {
                    const html = payload && typeof payload.html === 'string' ? payload.html.trim() : '';

                    if (html) {
                        notificationsContent.innerHTML = html;
                    } else {
                        renderNotificationsMessage(notificationsStrings.empty, 'neutral');
                    }

                    notificationsState.loaded = true;
                    notificationsState.loading = false;
                    notificationsContent.dataset.loaded = '1';
                    notificationsContent.dataset.loading = '0';

                    const unreadIds = Array.isArray(payload && payload.unread_ids)
                        ? payload.unread_ids
                            .map((value) => Number.parseInt(value, 10))
                            .filter((value) => Number.isFinite(value) && value > 0)
                        : [];

                    notificationsState.lastUnreadIds = unreadIds;

                    if (payload && typeof payload.count === 'number') {
                        updateNotificationBadge(payload.count);
                    }

                    if (unreadIds.length > 0) {
                        markNotificationsRead(unreadIds, { updateUI: false }).catch(() => {
                            // Errors are surfaced via markNotificationsRead; ignore here.
                        });
                    }
                })
                .catch(() => {
                    notificationsState.loaded = false;
                    notificationsContent.dataset.loaded = '0';
                    renderNotificationsMessage(notificationsStrings.loadError, 'error');
                })
                .finally(() => {
                    notificationsState.loading = false;
                    if (notificationsContent) {
                        notificationsContent.dataset.loading = '0';
                    }
                });
        };

        const openNotificationsDropdown = () => {
            if (!notificationsDropdown || !notificationsButton) {
                return;
            }

            notificationsDropdown.classList.remove('hidden');
            notificationsDropdown.setAttribute('aria-hidden', 'false');
            notificationsButton.setAttribute('aria-expanded', 'true');

            if (!notificationsState.loaded) {
                loadNotifications();
            }
        };

        const setupFollowButton = () => {
            if (!followButton || !restBase || !Number.isFinite(authorId) || authorId <= 0 || isOwner) {
                return;
            }

            if (followButton.dataset.followHandlerBound === '1') {
                return;
            }

            followButton.dataset.followHandlerBound = '1';

            const labelElement = followButton.querySelector('.follow-label');
            let feedbackElement = null;

            const ensureFeedbackElement = () => {
                if (feedbackElement && feedbackElement.isConnected) {
                    return feedbackElement;
                }

                feedbackElement = document.createElement('p');
                feedbackElement.className = 'mt-2 text-sm hidden';
                feedbackElement.setAttribute('data-follow-feedback', '1');

                const container = followButton.parentElement;
                if (container) {
                    container.appendChild(feedbackElement);
                } else {
                    followButton.insertAdjacentElement('afterend', feedbackElement);
                }

                return feedbackElement;
            };

            const clearFollowMessage = () => {
                if (!feedbackElement) {
                    return;
                }

                feedbackElement.textContent = '';
                feedbackElement.classList.add('hidden');
                feedbackElement.classList.remove('text-red-600', 'text-green-600', 'text-gray-500');
            };

            const showFollowMessage = (message, variant = 'error') => {
                if (!message) {
                    clearFollowMessage();
                    return;
                }

                const target = ensureFeedbackElement();
                target.textContent = message;
                target.classList.remove('hidden', 'text-red-600', 'text-green-600', 'text-gray-500');

                if (variant === 'error') {
                    target.classList.add('text-red-600');
                } else if (variant === 'success') {
                    target.classList.add('text-green-600');
                } else {
                    target.classList.add('text-gray-500');
                }
            };

            const applyFollowState = (isFollowing) => {
                const following = Boolean(isFollowing);
                const followClasses = ['bg-pink-600', 'hover:bg-pink-700', 'text-white'];
                const unfollowClasses = ['bg-gray-200', 'hover:bg-gray-300', 'text-gray-700'];

                followButton.dataset.following = following ? '1' : '0';
                followButton.setAttribute('aria-pressed', following ? 'true' : 'false');

                if (labelElement) {
                    labelElement.textContent = following ? followStrings.following : followStrings.follow;
                }

                if (following) {
                    followButton.classList.remove(...followClasses);
                    followButton.classList.add(...unfollowClasses);
                } else {
                    followButton.classList.add(...followClasses);
                    followButton.classList.remove(...unfollowClasses);
                }
            };

            const setFollowButtonLoading = (isLoading) => {
                followButton.dataset.loading = isLoading ? '1' : '0';
                followButton.disabled = isLoading;
                followButton.classList.toggle('opacity-60', isLoading);
                followButton.classList.toggle('pointer-events-none', isLoading);

                if (labelElement) {
                    if (isLoading) {
                        labelElement.dataset.originalText = labelElement.textContent || '';
                        labelElement.textContent = followStrings.loading;
                    } else {
                        labelElement.textContent = followButton.dataset.following === '1'
                            ? followStrings.following
                            : followStrings.follow;
                    }
                }
            };

            applyFollowState(followButton.dataset.following === '1');

            followButton.addEventListener('click', () => {
                if (followButton.dataset.loading === '1') {
                    return;
                }

                const requestUrl = restBase ? `${restBase}/author/${authorId}/follow` : '';
                if (!requestUrl) {
                    showFollowMessage(followStrings.error, 'error');
                    return;
                }

                const currentlyFollowing = followButton.dataset.following === '1';
                const action = currentlyFollowing ? 'unfollow' : 'follow';

                setFollowButtonLoading(true);
                clearFollowMessage();

                const headers = { 'Content-Type': 'application/json' };
                if (restNonce) {
                    headers['X-WP-Nonce'] = restNonce;
                }

                fetch(requestUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body: JSON.stringify({ action }),
                })
                    .then((response) => response.json().catch(() => null).then((payload) => {
                        if (!response.ok) {
                            const message = payload && payload.message ? payload.message : followStrings.error;
                            throw new Error(message);
                        }

                        return payload || {};
                    }))
                    .then((payload) => {
                        const newState = Boolean(payload.is_following);
                        applyFollowState(newState);

                        if (Object.prototype.hasOwnProperty.call(payload, 'followers_count')) {
                            const formatted = formatNumber(payload.followers_count);
                            followerCountElements.forEach((element) => {
                                if (element) {
                                    element.textContent = formatted;
                                    element.setAttribute('data-followers-count', String(payload.followers_count));
                                }
                            });
                        }

                        clearFollowMessage();
                    })
                    .catch((error) => {
                        const message = error && error.message ? error.message : followStrings.error;
                        showFollowMessage(message, 'error');
                        applyFollowState(currentlyFollowing);
                    })
                    .finally(() => {
                        setFollowButtonLoading(false);
                    });
            });
        };

        const setupNotificationsDropdown = () => {
            if (!notificationsButton || !notificationsDropdown || !notificationsContent) {
                return;
            }

            if (notificationsContainer && notificationsContainer.dataset.notificationsBound === '1') {
                return;
            }

            if (notificationsContainer) {
                notificationsContainer.dataset.notificationsBound = '1';
                notificationsContainer.dataset.initialized = '1';
            }

            notificationsContent.dataset.loaded = notificationsContent.dataset.loaded || '0';
            notificationsContent.dataset.loading = notificationsContent.dataset.loading || '0';

            notificationsButton.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                const isHidden = notificationsDropdown.classList.contains('hidden');
                if (isHidden) {
                    openNotificationsDropdown();
                } else {
                    closeNotificationsDropdown();
                }
            });

            document.addEventListener('click', (event) => {
                if (notificationsContainer && notificationsContainer.contains(event.target)) {
                    return;
                }

                closeNotificationsDropdown();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeNotificationsDropdown();
                }
            });

            if (notificationsMarkAllButton) {
                const defaultLabel = notificationsMarkAllButton.textContent || '';

                notificationsMarkAllButton.addEventListener('click', (event) => {
                    event.preventDefault();

                    notificationsMarkAllButton.disabled = true;
                    notificationsMarkAllButton.classList.add('opacity-50', 'cursor-not-allowed');
                    notificationsMarkAllButton.textContent = notificationsStrings.loading;

                    markNotificationsRead([], { markAll: true })
                        .then(() => {
                            renderNotificationsMessage(
                                notificationsStrings.markAllComplete || notificationsStrings.empty,
                                'neutral'
                            );
                            notificationsState.loaded = false;
                            notificationsContent.dataset.loaded = '0';
                        })
                        .catch(() => {
                            renderNotificationsMessage(notificationsStrings.markError, 'error');
                        })
                        .finally(() => {
                            notificationsMarkAllButton.disabled = false;
                            notificationsMarkAllButton.classList.remove('opacity-50', 'cursor-not-allowed');
                            notificationsMarkAllButton.textContent = defaultLabel;
                        });
                });
            }

            if (notificationsContent && notificationsContent.dataset.dismissBound !== '1') {
                notificationsContent.addEventListener('click', (event) => {
                    const dismissButton = event.target.closest('[data-action="notification-dismiss"]');

                    if (!dismissButton) {
                        return;
                    }

                    event.preventDefault();

                    if (dismissButton.dataset.loading === '1') {
                        return;
                    }

                    const idAttr = dismissButton.getAttribute('data-notification-id') || dismissButton.dataset.notificationId || '';
                    const notificationId = Number.parseInt(idAttr, 10);

                    if (!Number.isFinite(notificationId) || notificationId <= 0) {
                        return;
                    }

                    const originalLabel = dismissButton.textContent || '';

                    dismissButton.dataset.loading = '1';
                    dismissButton.disabled = true;
                    dismissButton.classList.add('opacity-50', 'pointer-events-none');
                    dismissButton.textContent = notificationsStrings.loading;

                    markNotificationsRead([notificationId])
                        .then(() => {
                            setNotificationItemRead(notificationId);
                            const parentItem = dismissButton.closest('[data-notification-id]');
                            if (parentItem) {
                                const errorMessage = parentItem.querySelector('[data-notification-error]');
                                if (errorMessage) {
                                    errorMessage.remove();
                                }
                            }
                            dismissButton.remove();
                        })
                        .catch((error) => {
                            const message = error && error.message ? error.message : notificationsStrings.markError;
                            const parentItem = dismissButton.closest('[data-notification-id]');

                            if (parentItem && !parentItem.querySelector('[data-notification-error]')) {
                                const feedback = document.createElement('p');
                                feedback.className = 'mt-2 text-xs text-red-600';
                                feedback.setAttribute('data-notification-error', '1');
                                feedback.textContent = message;
                                parentItem.appendChild(feedback);
                            }

                            dismissButton.disabled = false;
                            dismissButton.classList.remove('opacity-50', 'pointer-events-none');
                            dismissButton.textContent = originalLabel;
                        })
                        .finally(() => {
                            dismissButton.dataset.loading = '0';
                        });
                });

                notificationsContent.dataset.dismissBound = '1';
            }
        };

        const setupStatusUpdateForm = () => {
            if (!isOwner || !restBase || !Number.isFinite(authorId) || authorId <= 0) {
                return;
            }

            const wrapper = document.getElementById('status-update-wrapper');
            const textarea = document.getElementById('status-update-textarea');
            const actions = document.getElementById('status-update-actions');
            const counter = document.getElementById('status-update-counter');
            const submit = document.getElementById('status-update-submit');

            if (!wrapper || !textarea || !actions || !submit) {
                return;
            }

            const maxLengthAttr = Number.parseInt(textarea.getAttribute('data-maxlength') || '', 10);
            const statusMaxLengthRaw = Number(data.statusMaxLength);
            const statusMaxLength = Number.isFinite(statusMaxLengthRaw) && statusMaxLengthRaw > 0
                ? Math.floor(statusMaxLengthRaw)
                : (Number.isFinite(maxLengthAttr) && maxLengthAttr > 0 ? maxLengthAttr : 5000);

            const statusStrings = {
                empty: strings.statusEmpty || 'Status update content cannot be empty.',
                error: strings.statusError || strings.error || 'Something went wrong. Please try again.',
                success: strings.statusSuccess || '',
                loading: strings.loading || 'Loading…',
            };

            let statusFeedback = wrapper.querySelector('[data-status-feedback]');
            const ensureStatusFeedback = () => {
                if (statusFeedback && statusFeedback.parentElement) {
                    return statusFeedback;
                }

                statusFeedback = document.createElement('p');
                statusFeedback.className = 'mt-2 text-sm hidden';
                statusFeedback.setAttribute('data-status-feedback', '1');
                wrapper.appendChild(statusFeedback);
                return statusFeedback;
            };

            const clearStatusMessage = () => {
                if (!statusFeedback) {
                    return;
                }

                statusFeedback.textContent = '';
                statusFeedback.classList.add('hidden');
                statusFeedback.classList.remove('text-red-600', 'text-green-600', 'text-gray-500');
            };

            const showStatusMessage = (message, variant = 'neutral') => {
                if (!message) {
                    clearStatusMessage();
                    return;
                }

                const target = ensureStatusFeedback();
                target.textContent = message;
                target.classList.remove('hidden', 'text-red-600', 'text-green-600', 'text-gray-500');

                if (variant === 'error') {
                    target.classList.add('text-red-600');
                } else if (variant === 'success') {
                    target.classList.add('text-green-600');
                } else {
                    target.classList.add('text-gray-500');
                }
            };

            const updateCounter = (length) => {
                if (counter) {
                    counter.textContent = `${length}/${statusMaxLength}`;
                }
            };

            const normalizeLineEndings = (value) => value.replace(/\r\n?/g, '\n');

            const getStatusPlainText = () => normalizeLineEndings((textarea.textContent || '').replace(/\u00a0/g, ' '));

            const placeCaretAtEnd = (element) => {
                element.focus();
                const selection = window.getSelection();
                if (!selection) {
                    return;
                }
                const range = document.createRange();
                range.selectNodeContents(element);
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            };

            const enforceMaxLength = () => {
                let text = getStatusPlainText();
                const characters = Array.from(text);

                if (characters.length > statusMaxLength) {
                    const truncated = characters.slice(0, statusMaxLength).join('');
                    textarea.textContent = truncated;
                    placeCaretAtEnd(textarea);
                    text = truncated;
                }

                return text;
            };

            const syncEmptyState = (value) => {
                const trimmed = value.trim();
                if (trimmed) {
                    textarea.classList.remove('is-empty');
                } else {
                    textarea.classList.add('is-empty');
                }
            };

            const setSubmitLoading = (isLoading) => {
                if (!submit.dataset.defaultLabel) {
                    submit.dataset.defaultLabel = submit.textContent || '';
                }

                if (isLoading) {
                    submit.disabled = true;
                    submit.classList.add('opacity-60', 'pointer-events-none');
                    submit.textContent = statusStrings.loading;
                } else {
                    submit.disabled = false;
                    submit.classList.remove('opacity-60', 'pointer-events-none');
                    submit.textContent = submit.dataset.defaultLabel || submit.textContent || '';
                }
            };

            textarea.addEventListener('focus', () => {
                actions.classList.remove('hidden');
                const text = enforceMaxLength();
                syncEmptyState(text);
                updateCounter(Array.from(text).length);
                clearStatusMessage();
            });

            textarea.addEventListener('input', () => {
                const text = enforceMaxLength();
                syncEmptyState(text);
                updateCounter(Array.from(text).length);
                clearStatusMessage();
            });

            textarea.addEventListener('blur', () => {
                const text = getStatusPlainText();
                const trimmed = text.trim();
                if (!trimmed) {
                    actions.classList.add('hidden');
                    textarea.classList.add('is-empty');
                    updateCounter(0);
                }
            });

            textarea.addEventListener('paste', (event) => {
                event.preventDefault();
                const pasted = (event.clipboardData || window.clipboardData).getData('text');
                if (typeof document.execCommand === 'function') {
                    document.execCommand('insertText', false, pasted);
                } else {
                    const selection = window.getSelection();
                    if (selection && selection.rangeCount > 0) {
                        selection.deleteFromDocument();
                        selection.getRangeAt(0).insertNode(document.createTextNode(pasted));
                        placeCaretAtEnd(textarea);
                    } else {
                        textarea.textContent += pasted;
                        placeCaretAtEnd(textarea);
                    }
                }
            });

            let isSubmitting = false;

            submit.addEventListener('click', () => {
                if (isSubmitting) {
                    return;
                }

                let text = enforceMaxLength();
                text = normalizeLineEndings(text).replace(/\u00a0/g, ' ');
                const trimmed = text.trim();

                if (!trimmed) {
                    showStatusMessage(statusStrings.empty, 'error');
                    return;
                }

                const requestUrl = restBase ? `${restBase}/author/${authorId}/status` : '';
                if (!requestUrl) {
                    showStatusMessage(statusStrings.error, 'error');
                    return;
                }

                isSubmitting = true;
                setSubmitLoading(true);
                clearStatusMessage();

                const headers = { 'Content-Type': 'application/json' };
                if (restNonce) {
                    headers['X-WP-Nonce'] = restNonce;
                }

                fetch(requestUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body: JSON.stringify({ content: trimmed }),
                })
                    .then((response) => response.json().catch(() => null).then((payload) => {
                        if (!response.ok) {
                            const message = payload && payload.message ? payload.message : statusStrings.error;
                            throw new Error(message);
                        }
                        return payload || {};
                    }))
                    .then((payload) => {
                        textarea.textContent = '';
                        textarea.classList.add('is-empty');
                        actions.classList.add('hidden');
                        updateCounter(0);

                        const html = payload && typeof payload.html === 'string' ? payload.html.trim() : '';
                        if (html) {
                            clearActivityMessages();
                            insertActivityHtmlAtTop(html);
                            activityState.loaded = true;
                            activityState.offset = Math.max(0, Number(activityState.offset) || 0) + 1;
                            if (activityLoadMoreButton) {
                                activityLoadMoreButton.dataset.offset = String(activityState.offset);
                            }
                        }

                        if (statusStrings.success) {
                            showStatusMessage(statusStrings.success, 'success');
                            window.setTimeout(() => {
                                clearStatusMessage();
                            }, 4000);
                        }
                    })
                    .catch((error) => {
                        const message = error && error.message ? error.message : statusStrings.error;
                        showStatusMessage(message, 'error');
                    })
                    .finally(() => {
                        isSubmitting = false;
                        setSubmitLoading(false);
                    });
            });
        };

        const loadActivityItems = async (offset = 0, buttonElement = activityLoadMoreButton) => {
            if (!activityList || activityState.loading) {
                return;
            }

            const requestedOffset = Number.parseInt(typeof offset === 'number' ? String(offset) : offset, 10);
            const normalizedOffset = Number.isFinite(requestedOffset) && requestedOffset >= 0 ? requestedOffset : activityState.offset;
            const requestUrl = buildActivityRequestUrl(normalizedOffset);

            if (!requestUrl) {
                return;
            }

            if (activityState.controller && typeof activityState.controller.abort === 'function') {
                activityState.controller.abort();
            }

            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            activityState.controller = controller;
            activityState.loading = true;

            if (buttonElement) {
                setActivityButtonLoading(buttonElement, true);
            }

            if (normalizedOffset === 0) {
                activityList.innerHTML = '';
            }

            clearActivityMessages();

            const headers = {};
            if (restNonce) {
                headers['X-WP-Nonce'] = restNonce;
            }

            try {
                const response = await fetch(requestUrl, {
                    credentials: 'same-origin',
                    headers,
                    signal: controller ? controller.signal : undefined,
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const payload = await response.json();
                const html = payload && typeof payload.html === 'string' ? payload.html.trim() : '';
                const nextOffset = payload && Number.isFinite(Number.parseInt(payload.next_offset, 10))
                    ? Number.parseInt(payload.next_offset, 10)
                    : normalizedOffset;
                const hasMore = Boolean(payload && payload.has_more);

                if (html) {
                    appendActivityHtml(html);
                } else if (normalizedOffset === 0) {
                    showActivityEmptyMessage();
                }

                activityState.offset = nextOffset;
                activityState.hasMore = hasMore;
                activityState.loaded = true;

                if (activityLoadMoreButton) {
                    activityLoadMoreButton.dataset.offset = String(nextOffset);
                }

                if (activityLoadMoreContainer) {
                    activityLoadMoreContainer.classList.toggle('hidden', !hasMore);
                }
            } catch (error) {
                if (controller && controller.signal && controller.signal.aborted) {
                    return;
                }

                if (normalizedOffset === 0) {
                    activityList.innerHTML = '';
                }

                showActivityErrorMessage();
            } finally {
                activityState.loading = false;

                if (activityState.controller === controller) {
                    activityState.controller = null;
                }

                if (buttonElement) {
                    setActivityButtonLoading(buttonElement, false);
                }
            }
        };

        const handleActivityLoadMoreClick = (event) => {
            event.preventDefault();

            if (activityState.loading) {
                return;
            }

            const button = event.currentTarget;
            const offsetValue = Number.parseInt(button ? button.getAttribute('data-offset') || `${activityState.offset}` : `${activityState.offset}`, 10);
            const normalizedOffset = Number.isFinite(offsetValue) && offsetValue >= 0 ? offsetValue : activityState.offset;

            loadActivityItems(normalizedOffset, button).catch(() => {});
        };

        const ensureActivityLoaded = () => {
            if (!activityList || activityState.loaded || activityState.loading) {
                return;
            }

            loadActivityItems(activityState.offset, activityLoadMoreButton).catch(() => {});
        };

        const attachPaginationHandlers = (panel, tabKey) => {
            if (!panel || panel.dataset.paginationBound === '1') {
                return;
            }

            panel.addEventListener('click', (event) => {
                const link = event.target.closest('a[data-upload-page], a[data-comment-page], a[data-author-page], a[data-page]');
                if (!link || !panel.contains(link)) {
                    return;
                }

                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                event.preventDefault();

                let targetPage = Number.parseInt(
                    link.getAttribute('data-upload-page')
                    || link.getAttribute('data-comment-page')
                    || link.getAttribute('data-author-page')
                    || link.getAttribute('data-page')
                    || '',
                    10
                );

                if (!Number.isFinite(targetPage) || targetPage < 1) {
                    try {
                        const url = new URL(link.href, window.location.origin);
                        const param = url.searchParams.get('tab_page') || url.searchParams.get('page');
                        if (param) {
                            targetPage = Number.parseInt(param, 10);
                        }
                    } catch (error) {
                        targetPage = NaN;
                    }
                }

                if (!Number.isFinite(targetPage) || targetPage < 1) {
                    targetPage = 1;
                }

                switchToTab(tabKey, targetPage, { force: true });
            });

            panel.dataset.paginationBound = '1';
        };

        const loadAuthorTabContent = async (tabKey, page = 1, options = {}) => {
            const normalizedKey = normalizeTabKey(tabKey);
            if (!normalizedKey || !tabPanels.has(normalizedKey)) {
                return;
            }

            const panel = getPrimaryPanelForKey(normalizedKey);
            if (!panel) {
                return;
            }
            const targetPage = Math.max(1, Number.parseInt(page, 10) || 1);
            const cacheKey = `${normalizedKey}:${targetPage}`;

            if (!options.force && tabCache.has(cacheKey)) {
                renderPanelContent(panel, tabCache.get(cacheKey));
                panel.dataset.loaded = '1';
                panel.dataset.page = String(targetPage);
                setLoadingState(panel, false);
                attachPaginationHandlers(panel, normalizedKey);
                return;
            }

            const requestUrl = buildEndpointUrl(normalizedKey, targetPage);
            if (!requestUrl) {
                panel.dataset.loaded = '1';
                panel.dataset.page = String(targetPage);
                setLoadingState(panel, false);
                return;
            }

            if (pendingRequests.has(normalizedKey)) {
                const controller = pendingRequests.get(normalizedKey);
                if (controller && typeof controller.abort === 'function') {
                    controller.abort();
                }
            }

            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            if (controller) {
                pendingRequests.set(normalizedKey, controller);
            }

            setLoadingState(panel, true);
            renderPanelContent(panel, '');
            panel.dataset.loaded = '0';
            panel.dataset.page = String(targetPage);

            const headers = {};
            if (restNonce) {
                headers['X-WP-Nonce'] = restNonce;
            }

            try {
                const response = await fetch(requestUrl, {
                    credentials: 'same-origin',
                    headers,
                    signal: controller ? controller.signal : undefined,
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const contentType = response.headers.get('content-type') || '';
                let payload;
                if (contentType.includes('application/json')) {
                    payload = await response.json();
                } else {
                    const text = await response.text();
                    payload = { html: text };
                }

                if (controller && pendingRequests.get(normalizedKey) !== controller) {
                    return;
                }

                if (controller) {
                    pendingRequests.delete(normalizedKey);
                }

                const resolvedPage = payload && payload.page ? Math.max(1, Number.parseInt(payload.page, 10) || targetPage) : targetPage;
                const html = payload && typeof payload.html === 'string' ? payload.html : '';

                tabCache.set(cacheKey, html);
                renderPanelContent(panel, html);
                panel.dataset.loaded = '1';
                panel.dataset.page = String(resolvedPage);
                setLoadingState(panel, false);
                attachPaginationHandlers(panel, normalizedKey);
            } catch (error) {
                if (controller && controller.signal && controller.signal.aborted) {
                    return;
                }

                if (controller) {
                    pendingRequests.delete(normalizedKey);
                }

                const errorMessage = strings.tabError || strings.error || 'Unable to load this section.';
                renderPanelContent(panel, `<div class="py-12 text-center text-sm text-red-500">${errorMessage}</div>`);
                panel.dataset.loaded = '0';
                setLoadingState(panel, false);
            }
        };

        const switchToTab = (tabKey, page = 1, options = {}) => {
            const normalizedKey = normalizeTabKey(tabKey);
            if (!normalizedKey || !tabPanels.has(normalizedKey)) {
                return;
            }

            const targetPage = Math.max(1, Number.parseInt(page, 10) || 1);
            if (!options.force && normalizedKey === currentTab && targetPage === currentPage) {
                return;
            }

            currentTab = normalizedKey;
            currentPage = targetPage;

            if (!options || options.suppressHistory !== true) {
                updateHistoryState(currentTab, currentPage);
            }

            setTabVisibility(currentTab);
            updateTabButtons(currentTab);
            loadAuthorTabContent(currentTab, currentPage, options);

            if (normalizedKey === 'overview') {
                ensureActivityLoaded();
            }
        };

        const bindSettingsShortcuts = () => {
            const triggers = document.querySelectorAll('[data-action="open-settings-tab"]');
            triggers.forEach((trigger) => {
                if (!(trigger instanceof HTMLElement)) {
                    return;
                }
                if (trigger.dataset.settingsHandlerBound === '1') {
                    return;
                }

                trigger.addEventListener('click', (event) => {
                    if (trigger.tagName === 'A') {
                        event.preventDefault();
                    }

                    const targetTab = trigger.getAttribute('data-tab-key') || 'settings';
                    switchToTab(targetTab, 1, { force: true });

                    window.requestAnimationFrame(() => {
                        let panel = getPrimaryPanelForKey('settings');
                        if (!panel) {
                            const panels = getAllPanelsForKey('settings');
                            panel = panels.find((element) => element && typeof element.scrollIntoView === 'function') || null;
                        }

                        if (panel && typeof panel.scrollIntoView === 'function') {
                            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
                });

                trigger.dataset.settingsHandlerBound = '1';
            });
        };

        const handleTabButtonClick = (event) => {
            const button = event.currentTarget;
            if (!button) {
                return;
            }

            if (button.tagName === 'A' || button.hasAttribute('href')) {
                event.preventDefault();
            }

            const key = button.getAttribute('data-tab-key');
            const desiredPage = Number.parseInt(button.getAttribute('data-tab-page') || `${currentPage}`, 10);
            switchToTab(key, desiredPage);
        };

        const initializeTabs = () => {
            collectTabElements();

            tabButtons.forEach((button) => {
                if (button.dataset.tabHandlerBound === '1') {
                    return;
                }

                button.addEventListener('click', handleTabButtonClick);
                button.dataset.tabHandlerBound = '1';
            });

            if (!tabPanels.size) {
                return;
            }

            if (!tabPanels.has(currentTab)) {
                const iterator = tabPanels.keys();
                const first = iterator.next();
                currentTab = first && !first.done ? first.value : 'overview';
            }

            setTabVisibility(currentTab);
            updateTabButtons(currentTab);

            const activePanel = getPrimaryPanelForKey(currentTab);
            if (activePanel && activePanel.dataset.loaded !== '1') {
                loadAuthorTabContent(currentTab, currentPage, { force: true }).catch(() => {});
            } else if (activePanel) {
                attachPaginationHandlers(activePanel, currentTab);
            }
        };

        const updateActivityDisplay = (state) => {
            if (!activityContainer || !activityLabel) {
                return;
            }

            if (state === 'online') {
                activityContainer.setAttribute('data-state', 'online');
                const baseClass = offlineLabelClass ? `${offlineLabelClass} ` : '';
                activityLabel.className = `${baseClass}${onlineClasses.join(' ')}`.trim();
                activityLabel.textContent = '';

                const dot = document.createElement('span');
                dot.className = 'inline-block h-2.5 w-2.5 rounded-full bg-green-500';
                dot.setAttribute('aria-hidden', 'true');

                const text = document.createElement('span');
                text.textContent = data.labels && data.labels.online ? data.labels.online : 'Now online';

                activityLabel.appendChild(dot);
                activityLabel.appendChild(text);
            } else {
                activityContainer.setAttribute('data-state', 'offline');
                activityLabel.className = offlineLabelClass;

                const fallbackLabel = data.labels && data.labels.offlineFallback ? data.labels.offlineFallback : '';
                const relativeText = lastActivityTimestamp ? formatRelativeTime(lastActivityTimestamp, fallbackLabel) : fallbackLabel;
                activityLabel.textContent = relativeText;
            }
        };

        const evaluateActivity = () => {
            if (!activityContainer) {
                return;
            }

            if (!lastActivityTimestamp) {
                updateActivityDisplay('offline');
                return;
            }

            const diff = getApproxServerNow() - lastActivityTimestamp;
            if (diff <= activityWindow) {
                updateActivityDisplay('online');
            } else {
                updateActivityDisplay('offline');
            }
        };

        const incrementProfileViews = () => {
            if (!data.allowProfileViewIncrement || !viewCountElement) {
                return;
            }

            if (!profileViewEndpoint || !data.profileViewCookie) {
                return;
            }

            if (hasCookie(data.profileViewCookie)) {
                return;
            }

            const sendRequest = () => {
                fetch(profileViewEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: buildRestHeaders(restNonce),
                })
                    .then(async (response) => {
                        const payload = await response.json().catch(() => null);
                        if (!response.ok || !payload || typeof payload !== 'object') {
                            return null;
                        }
                        return payload;
                    })
                    .then((payload) => {
                        if (!payload) {
                            return;
                        }

                        const formatted = payload.formattedViews || payload.views;
                        if (formatted) {
                            viewCountElement.textContent = formatNumber(formatted);
                        }

                        const throttle = Number(data.profileViewThrottle) || 3600;
                        setCookie(data.profileViewCookie, '1', throttle, Boolean(data.isSecure));
                    })
                    .catch(() => {
                        // ignore errors silently
                    });
            };

            scheduleDeferred(sendRequest, Number(data.viewDelay) || 1500);
        };

        const maybeUpdateLastActivity = () => {
            if (!data.shouldTrackActivity || !activityEndpoint) {
                return;
            }

            const cookieName = data.activityCookie || 'gta6_activity_throttle';
            if (hasCookie(cookieName)) {
                return;
            }

            const sendRequest = () => {
                try {
                    fetch(activityEndpoint, {
                        method: 'POST',
                        headers: buildRestHeaders(restNonce, { 'Content-Type': 'application/json' }),
                        credentials: 'same-origin',
                        keepalive: true,
                        body: JSON.stringify({}),
                    }).then(async (response) => {
                        const payload = await response.json().catch(() => null);
                        if (!response.ok || !payload || typeof payload !== 'object') {
                            throw new Error('request_failed');
                        }
                        return payload;
                    })
                        .then((payload) => {
                            if (payload && typeof payload.timestamp !== 'undefined') {
                                lastActivityTimestamp = Number(payload.timestamp);
                                if (data.isOwnProfile) {
                                    updateActivityDisplay('online');
                                }
                            }
                        }).catch(() => {
                            // ignore errors
                        });
                } catch (error) {
                    // ignore unexpected errors
                }

                const throttle = Number(data.activityThrottle) || activityWindow;
                setCookie(cookieName, '1', throttle, Boolean(data.isSecure));
            };

            scheduleDeferred(sendRequest, Number(data.viewDelay) || 1500);
        };

        bindLightboxDelegation(activityList);

        let mediaSaveInProgress = false;

        const processPendingMediaUploads = async () => {
            if (!isOwner || !restBase) {
                return;
            }

            const pendingTasks = [];

            if (mediaSaveInProgress) {
                return;
            }

            if (typeof mediaOperations.avatar.hasPending === 'function'
                && typeof mediaOperations.avatar.commit === 'function'
                && mediaOperations.avatar.hasPending()) {
                pendingTasks.push(mediaOperations.avatar.commit().catch((error) => error));
            }

            if (typeof mediaOperations.banner.hasPending === 'function'
                && typeof mediaOperations.banner.commit === 'function'
                && mediaOperations.banner.hasPending()) {
                pendingTasks.push(mediaOperations.banner.commit().catch((error) => error));
            }

            if (!pendingTasks.length) {
                return;
            }

            mediaSaveInProgress = true;
            try {
                await Promise.all(pendingTasks);
            } finally {
                mediaSaveInProgress = false;
            }
        };

        if (saveChangesButton) {
            saveChangesButton.addEventListener('click', () => {
                window.requestAnimationFrame(() => {
                    processPendingMediaUploads().catch(() => {});
                });
            });
        }

        initializeBannerManagement();
        initializeAvatarManagement();

        if (activityLoadMoreButton && activityLoadMoreButton.dataset.activityHandlerBound !== '1') {
            activityLoadMoreButton.addEventListener('click', handleActivityLoadMoreClick);
            activityLoadMoreButton.dataset.activityHandlerBound = '1';
        }

        setupNotificationsDropdown();
        setupFollowButton();
        setupStatusUpdateForm();

        initializeTabs();
        bindSettingsShortcuts();

        if (currentTab === 'overview') {
            ensureActivityLoaded();
        }

        if (typeof window.GTAModsAuthorProfile === 'object' && window.GTAModsAuthorProfile !== null) {
            window.GTAModsAuthorProfile.loadAuthorTabContent = loadAuthorTabContent;
            window.GTAModsAuthorProfile.setActiveTab = (tabKey, page = 1, opts = {}) => {
                const options = (typeof opts === 'object' && opts !== null) ? opts : {};
                switchToTab(tabKey, page, options);
            };
            window.GTAModsAuthorProfile.loadActivityItems = loadActivityItems;
            window.GTAModsAuthorProfile.followRestEnabled = true;
            window.GTAModsAuthorProfile.notificationsRestEnabled = true;
            window.GTAModsAuthorProfile.avatarRestEnabled = true;
            window.GTAModsAuthorProfile.bannerRestEnabled = true;
        }

        incrementProfileViews();
        evaluateActivity();
        maybeUpdateLastActivity();

        window.setInterval(evaluateActivity, 60000);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();
