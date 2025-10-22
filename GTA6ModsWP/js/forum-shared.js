(function () {
    if (!window.wp || !wp.apiFetch) {
        return;
    }

    const config = window.GTA6ForumSharedData || {};
    const shareModal = document.querySelector('[data-forum-share-modal]');
    const shareCloseButtons = shareModal ? shareModal.querySelectorAll('[data-share-close]') : [];
    const shareLinks = shareModal ? shareModal.querySelectorAll('[data-share-network]') : [];
    const copyButton = shareModal ? shareModal.querySelector('[data-share-copy]') : null;
    const feedbackEl = shareModal ? shareModal.querySelector('[data-share-feedback]') : null;
    const titleEl = shareModal ? shareModal.querySelector('[data-share-modal-title]') : null;
    const descriptionEl = shareModal ? shareModal.querySelector('[data-share-modal-description]') : null;

    let currentShare = {
        title: '',
        url: '',
    };

    const texts = {
        modalTitle: (config.share && config.share.modalTitle) ? config.share.modalTitle : 'Share this thread',
        modalDescription: (config.share && config.share.modalDescription) ? config.share.modalDescription : 'Pick a platform to spread the word.',
        copySuccess: (config.share && config.share.copySuccess) ? config.share.copySuccess : 'Link copied to clipboard!',
        copyError: (config.share && config.share.copyError) ? config.share.copyError : 'We could not copy the link. Please copy it manually.',
    };

    const bookmarkTexts = {
        add: (config.bookmarks && config.bookmarks.add) ? config.bookmarks.add : 'Bookmark',
        added: (config.bookmarks && config.bookmarks.added) ? config.bookmarks.added : 'Saved',
        loginRequired: (config.bookmarks && config.bookmarks.loginRequired) ? config.bookmarks.loginRequired : 'Please sign in to save threads.',
        error: (config.bookmarks && config.bookmarks.error) ? config.bookmarks.error : 'We could not update your bookmark. Please try again.',
    };

    function closeShareModal() {
        if (!shareModal) {
            return;
        }

        shareModal.classList.add('hidden');
        shareModal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('overflow-hidden');
        document.body.classList.remove('overflow-hidden');
        if (feedbackEl) {
            feedbackEl.textContent = '';
            feedbackEl.classList.remove('text-red-600', 'text-green-600');
        }
    }

    function buildShareUrl(network, url, title) {
        const encodedUrl = encodeURIComponent(url);
        const encodedTitle = encodeURIComponent(title);

        switch (network) {
            case 'facebook':
                return `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
            case 'twitter':
                return `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`;
            case 'vk':
                return `https://vk.com/share.php?url=${encodedUrl}`;
            case 'reddit':
                return `https://www.reddit.com/submit?url=${encodedUrl}&title=${encodedTitle}`;
            case 'whatsapp':
                return `https://api.whatsapp.com/send?text=${encodedTitle}%20${encodedUrl}`;
            case 'bluesky':
                return `https://bsky.app/intent/compose?text=${encodedTitle}%20${encodedUrl}`;
            default:
                return url;
        }
    }

    function updateShareLinks() {
        if (!shareModal) {
            return;
        }

        shareLinks.forEach((link) => {
            const network = link.getAttribute('data-share-network');
            const href = buildShareUrl(network, currentShare.url, currentShare.title);
            link.setAttribute('href', href);
        });

        if (titleEl) {
            titleEl.textContent = texts.modalTitle;
        }

        if (descriptionEl) {
            descriptionEl.textContent = texts.modalDescription;
        }
    }

    async function copyLink() {
        if (!copyButton) {
            return;
        }

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(currentShare.url);
            } else {
                const temp = document.createElement('textarea');
                temp.value = currentShare.url;
                temp.setAttribute('readonly', '');
                temp.style.position = 'absolute';
                temp.style.left = '-9999px';
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            }

            if (feedbackEl) {
                feedbackEl.textContent = texts.copySuccess;
                feedbackEl.classList.remove('text-red-600');
                feedbackEl.classList.add('text-green-600');
            }
        } catch (error) {
            if (feedbackEl) {
                feedbackEl.textContent = texts.copyError;
                feedbackEl.classList.remove('text-green-600');
                feedbackEl.classList.add('text-red-600');
            }
        }
    }

    function openShareModal(payload) {
        if (!shareModal) {
            return;
        }

        currentShare = {
            title: payload && payload.title ? payload.title : '',
            url: payload && payload.url ? payload.url : window.location.href,
        };

        updateShareLinks();

        shareModal.classList.remove('hidden');
        shareModal.setAttribute('aria-hidden', 'false');

        if (feedbackEl) {
            feedbackEl.textContent = '';
        }
    }

    if (shareModal) {
        shareModal.addEventListener('click', (event) => {
            if (event.target === shareModal) {
                closeShareModal();
            }
        });
    }

    shareCloseButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            closeShareModal();
        });
    });

    if (copyButton) {
        copyButton.addEventListener('click', (event) => {
            event.preventDefault();
            copyLink();
        });
    }

    window.GTA6ForumShare = {
        open: openShareModal,
        close: closeShareModal,
    };

    window.GTA6ForumBookmarks = {
        isLoggedIn: Boolean(config.isLoggedIn),
        labels: bookmarkTexts,
        toggle(endpoint) {
            if (!endpoint) {
                return Promise.reject({ code: 'invalid_endpoint' });
            }

            if (!this.isLoggedIn) {
                return Promise.reject({ code: 'not_logged_in' });
            }

            return wp.apiFetch({
                url: endpoint,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': config.nonce,
                    'Content-Type': 'application/json',
                },
            });
        },
    };
})();
