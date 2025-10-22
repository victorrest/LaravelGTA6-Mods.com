(function () {
    'use strict';

    const config = window.GTAModsTracking || null;
    if (!config || !config.restEndpoints) {
        return;
    }

    const utils = window.GTAModsUtils || {};
    const getCookie = (typeof utils.getCookie === 'function') ? utils.getCookie : () => null;
    const setCookieUtil = (typeof utils.setCookie === 'function') ? utils.setCookie : () => {};
    const buildHeaders = (typeof utils.buildRestHeaders === 'function')
        ? utils.buildRestHeaders
        : (nonce, extra = {}) => (nonce ? { ...extra, 'X-WP-Nonce': nonce } : { ...extra });

    const rest = (typeof config.restEndpoints === 'object') ? config.restEndpoints : {};
    const restNonce = typeof config.restNonce === 'string' ? config.restNonce : '';

    const onReady = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    };

    const postJson = (endpoint, payload = {}) => {
        if (!endpoint) {
            return Promise.resolve(null);
        }

        const headers = buildHeaders(restNonce, { 'Content-Type': 'application/json' });

        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify(payload),
        }).then((response) => {
            if (!response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        });
    };

    const updatePostViews = (data) => {
        if (!data) {
            return;
        }

        const element = document.querySelector('[data-post-view-count]');
        if (!element) {
            return;
        }

        if (typeof data.count === 'number') {
            element.dataset.count = String(data.count);
        }

        if (data.formatted) {
            element.textContent = data.formatted;
        }
    };

    const updateProfileViews = (data) => {
        if (!data) {
            return;
        }

        const element = document.querySelector('[data-profile-view-count]');
        if (!element) {
            return;
        }

        if (typeof data.count === 'number') {
            element.dataset.count = String(data.count);
        }

        const template = element.dataset.template;
        if (template && template.indexOf('%s') !== -1 && data.formatted) {
            element.textContent = template.replace('%s', data.formatted);
        } else if (data.label) {
            element.textContent = data.label;
        }
    };

    const updateLastActivity = (data) => {
        if (!data) {
            return;
        }

        const container = document.querySelector('[data-profile-last-activity]');
        if (!container) {
            return;
        }

        if (data.iso) {
            container.dataset.lastActivity = data.iso;
        }

        const indicator = container.querySelector('[data-profile-online-indicator]');
        const label = container.querySelector('[data-profile-last-activity-label]');

        if (indicator) {
            if (data.isOnline) {
                indicator.classList.remove('hidden');
            } else {
                indicator.classList.add('hidden');
            }
        }

        if (label && data.label) {
            label.textContent = data.label;
        }
    };

    const trackPostView = () => {
        const postId = Number(config.postId);
        const endpoint = rest.postView;

        if (!postId || !endpoint) {
            return;
        }

        const cookieName = `gta6mods_viewed_${postId}`;
        if (getCookie(cookieName)) {
            return;
        }

        const ttl = Number(config.postViewCookieTTL) || 3600;
        postJson(endpoint)
            .then((data) => {
                if (data) {
                    updatePostViews({
                        count: data.views,
                        formatted: data.formattedViews,
                    });
                }
            })
            .catch(() => {
                // Silently ignore errors to avoid disrupting the user experience.
            });

        setCookieUtil(cookieName, '1', {
            maxAge: ttl,
            secure: Boolean(config.isSecure),
        });
    };

    const trackProfileView = () => {
        if (!config.profileViewEnabled) {
            return;
        }

        const authorId = Number(config.profileAuthorId);
        const endpoint = rest.profileView;

        if (!authorId || !endpoint) {
            return;
        }

        const cookieName = `gta6mods_profile_viewed_${authorId}`;
        if (getCookie(cookieName)) {
            return;
        }

        const ttl = Number(config.profileViewCookieTTL) || 3600;
        postJson(endpoint)
            .then((data) => {
                if (data) {
                    updateProfileViews({
                        count: data.views,
                        formatted: data.formattedViews,
                    });
                }
            })
            .catch(() => {
                // Ignore errors silently.
            });

        setCookieUtil(cookieName, '1', {
            maxAge: ttl,
        });
    };

    const trackActivity = () => {
        if (!config.activityEnabled) {
            return;
        }

        const endpoint = rest.activity;
        if (!endpoint) {
            return;
        }

        const cookieName = 'gta6_activity_throttle';
        if (getCookie(cookieName)) {
            return;
        }

        const ttl = Number(config.activityThrottle) || (20 * 60);
        postJson(endpoint)
            .then((data) => {
                if (data) {
                    updateLastActivity({
                        timestamp: data.timestamp,
                        label: data.timestampHuman,
                    });
                }
            })
            .catch(() => {
                // Ignore errors silently.
            });

        setCookieUtil(cookieName, '1', {
            maxAge: ttl,
            secure: Boolean(config.isSecure),
        });
    };

    const init = () => {
        trackPostView();
        trackProfileView();
        trackActivity();
    };

    onReady(() => {
        const delay = Number(config.delay);
        if (Number.isFinite(delay) && delay > 0) {
            window.setTimeout(init, delay);
        } else {
            init();
        }
    });
})();
