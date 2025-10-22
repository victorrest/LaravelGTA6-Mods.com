(function () {
    'use strict';

    const noop = () => {};

    const getCookie = (name) => {
        if (!name || typeof document === 'undefined') {
            return null;
        }

        const cookies = document.cookie ? document.cookie.split(';') : [];
        for (let index = 0; index < cookies.length; index += 1) {
            const cookie = cookies[index].trim();
            if (cookie.startsWith(`${name}=`)) {
                return cookie.substring(name.length + 1);
            }
        }

        return null;
    };

    const hasCookie = (name) => getCookie(name) !== null;

    const setCookie = (name, value, options = {}) => {
        if (!name || typeof document === 'undefined') {
            return;
        }

        const parts = [`${name}=${value}`];
        const maxAge = typeof options.maxAge === 'number' ? Math.floor(options.maxAge) : null;
        const expires = options.expires instanceof Date ? options.expires : null;

        if (Number.isFinite(maxAge) && maxAge > 0) {
            parts.push(`max-age=${maxAge}`);
        } else if (expires) {
            parts.push(`expires=${expires.toUTCString()}`);
        }

        parts.push(`path=${options.path || '/'}`);
        parts.push(`SameSite=${options.sameSite || 'Lax'}`);

        if (options.secure) {
            parts.push('secure');
        }

        document.cookie = parts.join('; ');
    };

    const scheduleDeferred = (callback, delay) => {
        if (typeof callback !== 'function') {
            return;
        }

        const timeout = Number.isFinite(delay) ? Math.max(0, delay) : 0;

        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(() => callback(), { timeout: timeout || 2000 });
            return;
        }

        window.setTimeout(callback, timeout || 0);
    };

    const buildRestHeaders = (nonce, extra = {}) => {
        const headers = { ...extra };

        if (nonce) {
            headers['X-WP-Nonce'] = nonce;
        }

        if (!Object.prototype.hasOwnProperty.call(headers, 'X-GTA6-Nonce')) {
            const security = (typeof window !== 'undefined' && window.GTAModsSecurity)
                ? window.GTAModsSecurity
                : null;

            if (security && typeof security.trackingNonce === 'string' && security.trackingNonce) {
                headers['X-GTA6-Nonce'] = security.trackingNonce;
            }
        }

        return headers;
    };

    if (!window.GTAModsUtils) {
        window.GTAModsUtils = {};
    }

    Object.assign(window.GTAModsUtils, {
        getCookie,
        hasCookie,
        setCookie,
        scheduleDeferred,
        buildRestHeaders,
        noop,
    });
})();
