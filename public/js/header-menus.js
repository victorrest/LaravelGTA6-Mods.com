(function () {
    'use strict';

    function setupAccountMenu() {
        const container = document.getElementById('account-menu');
        const button = document.getElementById('account-menu-button');
        const dropdown = document.getElementById('account-menu-dropdown');

        if (!container || !button || !dropdown) {
            return;
        }

        if (container.dataset.initialized === '1') {
            return;
        }

        container.dataset.initialized = '1';

        function closeMenu() {
            dropdown.classList.add('hidden');
            dropdown.setAttribute('aria-hidden', 'true');
            button.setAttribute('aria-expanded', 'false');
        }

        function openMenu() {
            dropdown.classList.remove('hidden');
            dropdown.setAttribute('aria-hidden', 'false');
            button.setAttribute('aria-expanded', 'true');
        }

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const expanded = button.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        document.addEventListener('click', (event) => {
            if (!container.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });
    }

    function setupNotificationsDropdown() {
        const config = window.gta6modsHeaderData || {};
        const userId = Number.parseInt(config.userId, 10);

        if (!Number.isFinite(userId) || userId <= 0) {
            return;
        }

        if (window.gta6AuthorConfig && Number.parseInt(window.gta6AuthorConfig.authorId, 10) === userId) {
            return;
        }

        const container = document.getElementById('notifications-container');
        const button = document.getElementById('notifications-btn');
        const dropdown = document.getElementById('notifications-dropdown');
        const content = dropdown ? dropdown.querySelector('[data-async-content="notifications"]') : null;
        const badge = container ? container.querySelector('[data-notification-badge]') : null;
        const markAllBtn = dropdown ? dropdown.querySelector('[data-action="mark-all-read"]') : null;

        if (!container || !button || !dropdown || !content) {
            return;
        }

        if (container.dataset.initialized === '1') {
            return;
        }

        const restBase = typeof config.restBase === 'string' ? config.restBase.replace(/\/+$/, '') : '';

        if (!restBase) {
            return;
        }

        const nonce = typeof config.nonce === 'string' ? config.nonce : '';
        const limit = Number.isFinite(config.limit) ? Math.max(1, Math.min(10, config.limit)) : 5;
        const strings = Object.assign(
            {
                loading: 'Loading…',
                empty: 'You have no notifications yet.',
                loadError: 'We could not load your notifications. Please try again.',
                markError: 'We could not mark your notifications as read. Please try again.',
                markAllComplete: 'All notifications marked as read.',
            },
            (config.strings && typeof config.strings === 'object') ? config.strings : {}
        );

        container.dataset.initialized = '1';
        content.dataset.loaded = content.dataset.loaded || '0';
        content.dataset.loading = content.dataset.loading || '0';
        dropdown.setAttribute('aria-hidden', dropdown.classList.contains('hidden') ? 'true' : 'false');
        button.setAttribute('aria-expanded', button.getAttribute('aria-expanded') === 'true' ? 'true' : 'false');

        const state = {
            hasFetched: content.dataset.loaded === '1',
            loading: content.dataset.loading === '1',
            lastUnreadIds: [],
        };

        function updateBadge(count) {
            const unread = Math.max(0, Number.parseInt(count, 10) || 0);

            if (badge) {
                if (unread > 0) {
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }

            button.setAttribute('data-unread-count', String(unread));

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
        }

        function closeDropdown() {
            dropdown.classList.add('hidden');
            dropdown.setAttribute('aria-hidden', 'true');
            button.setAttribute('aria-expanded', 'false');
        }

        function openDropdown() {
            dropdown.classList.remove('hidden');
            dropdown.setAttribute('aria-hidden', 'false');
            button.setAttribute('aria-expanded', 'true');
        }

        function markNotificationsRead(ids = [], options = {}) {
            const markAll = options.markAll === true;
            const idList = Array.isArray(ids) ? ids.map((value) => Number.parseInt(value, 10)).filter((value) => Number.isFinite(value) && value > 0) : [];

            if (!markAll && idList.length === 0) {
                return Promise.resolve(null);
            }

            const payload = {
                mark_all: markAll,
                notification_ids: markAll ? [] : idList,
            };

            return fetch(`${restBase}/author/${userId}/notifications/mark-read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify(payload),
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                return response.json();
            }).then((data) => {
                if (data && typeof data.count === 'number') {
                    updateBadge(data.count);
                } else {
                    updateBadge(0);
                }
                return data;
            });
        }

        function renderMessage(message, variant = 'neutral') {
            const classes = variant === 'error' ? 'py-4 text-center text-sm text-red-500' : 'py-4 text-center text-sm text-gray-500';
            content.innerHTML = `<div class="${classes}">${message}</div>`;
        }

        function fetchNotifications() {
            if (state.loading) {
                return;
            }

            state.loading = true;
            content.dataset.loading = '1';
            renderMessage(strings.loading || 'Loading…', 'neutral');

            fetch(`${restBase}/author/${userId}/notifications/recent?limit=${limit}`, {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': nonce,
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    const html = data && typeof data.html === 'string' ? data.html : '';
                    content.innerHTML = html.trim() !== ''
                        ? html
                        : `<div class="py-4 text-center text-sm text-gray-500">${strings.empty || 'You have no notifications yet.'}</div>`;

                    state.hasFetched = true;
                    content.dataset.loaded = '1';

                    const unreadIds = Array.isArray(data && data.unread_ids)
                        ? data.unread_ids.map((value) => Number.parseInt(value, 10)).filter((value) => Number.isFinite(value) && value > 0)
                        : [];

                    state.lastUnreadIds = unreadIds;

                    if (data && typeof data.count === 'number') {
                        updateBadge(data.count);
                    }

                    if (unreadIds.length > 0) {
                        markNotificationsRead(unreadIds, { updateUI: false }).catch(() => {
                            // Errors handled in markNotificationsRead.
                        });
                    }
                })
                .catch(() => {
                    state.hasFetched = false;
                    renderMessage(strings.loadError || 'We could not load your notifications. Please try again.', 'error');
                })
                .finally(() => {
                    state.loading = false;
                    content.dataset.loading = '0';
                });
        }

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const expanded = button.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                closeDropdown();
            } else {
                openDropdown();

                if (!state.hasFetched) {
                    fetchNotifications();
                }
            }
        });

        document.addEventListener('click', (event) => {
            if (!container.contains(event.target)) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDropdown();
            }
        });

        if (markAllBtn) {
            const defaultLabel = markAllBtn.textContent;
            markAllBtn.addEventListener('click', (event) => {
                event.preventDefault();

                if (state.loading) {
                    return;
                }

                markAllBtn.disabled = true;
                markAllBtn.classList.add('opacity-50', 'cursor-not-allowed');
                markAllBtn.textContent = strings.loading || 'Loading…';

                markNotificationsRead([], { markAll: true })
                    .then(() => {
                        renderMessage(strings.markAllComplete || 'All notifications marked as read.', 'neutral');
                        state.hasFetched = false;
                        state.lastUnreadIds = [];
                    })
                    .catch(() => {
                        renderMessage(strings.markError || 'We could not mark your notifications as read. Please try again.', 'error');
                    })
                    .finally(() => {
                        markAllBtn.disabled = false;
                        markAllBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        markAllBtn.textContent = defaultLabel;
                    });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupAccountMenu();
        setupNotificationsDropdown();
    });
})();
