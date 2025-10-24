/**
 * Like and Bookmark Module
 * Handles like and bookmark toggle functionality on mod pages
 */

class LikeBookmarkHandler {
    constructor() {
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initHandlers());
        } else {
            this.initHandlers();
        }
    }

    initHandlers() {
        // Initialize like buttons
        document.querySelectorAll('[data-like-button]').forEach(button => {
            button.addEventListener('click', (e) => this.handleLikeClick(e, button));
        });

        // Initialize bookmark buttons
        document.querySelectorAll('[data-bookmark-button]').forEach(button => {
            button.addEventListener('click', (e) => this.handleBookmarkClick(e, button));
        });

        // Load initial states
        this.loadInitialStates();
    }

    async loadInitialStates() {
        const modId = this.getModId();
        if (!modId) {
            console.error('Mod ID not found for like/bookmark handlers');
            return;
        }

        console.log('Loading initial states for mod ID:', modId);

        try {
            // Check like status
            const likeResponse = await fetch(`/likes/${modId}/check`);
            const likeData = await likeResponse.json();

            if (likeData.success && likeData.liked) {
                this.setLikeState(true);
            }

            // Check bookmark status
            const bookmarkResponse = await fetch(`/bookmarks/${modId}/check`);
            const bookmarkData = await bookmarkResponse.json();

            if (bookmarkData.success && bookmarkData.bookmarked) {
                this.setBookmarkState(true);
            }
        } catch (error) {
            console.error('Error loading initial states:', error);
        }
    }

    async handleLikeClick(e, button) {
        e.preventDefault();
        e.stopPropagation();

        if (button.disabled) return;

        const modId = this.getModId();
        if (!modId) {
            console.error('Mod ID not found');
            return;
        }

        console.log('Toggling like for mod ID:', modId);
        button.disabled = true;

        try {
            const response = await fetch(`/likes/${modId}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                this.setLikeState(data.liked);
                this.updateLikeCount(data.likes_count);
                console.log('Like toggled successfully:', data);
            } else {
                if (response.status === 401) {
                    window.location.href = '/login';
                } else {
                    alert(data.message || 'Hiba történt');
                }
            }
        } catch (error) {
            console.error('Error toggling like:', error);
            alert('Hálózati hiba történt');
        } finally {
            button.disabled = false;
        }
    }

    async handleBookmarkClick(e, button) {
        e.preventDefault();
        e.stopPropagation();

        if (button.disabled) return;

        const modId = this.getModId();
        if (!modId) {
            console.error('Mod ID not found');
            return;
        }

        console.log('Toggling bookmark for mod ID:', modId);
        button.disabled = true;

        try {
            const response = await fetch(`/bookmarks/${modId}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                this.setBookmarkState(data.bookmarked);
                console.log('Bookmark toggled successfully:', data);
            } else {
                if (response.status === 401) {
                    window.location.href = '/login';
                } else {
                    alert(data.message || 'Hiba történt');
                }
            }
        } catch (error) {
            console.error('Error toggling bookmark:', error);
            alert('Hálózati hiba történt');
        } finally {
            button.disabled = false;
        }
    }

    setLikeState(isLiked) {
        document.querySelectorAll('[data-like-button]').forEach(button => {
            if (isLiked) {
                button.classList.remove('is-inactive');
                button.classList.add('is-active');
                button.setAttribute('aria-pressed', 'true');
            } else {
                button.classList.remove('is-active');
                button.classList.add('is-inactive');
                button.setAttribute('aria-pressed', 'false');
            }
        });
    }

    setBookmarkState(isBookmarked) {
        document.querySelectorAll('[data-bookmark-button]').forEach(button => {
            const label = button.querySelector('[data-bookmark-label]');

            if (isBookmarked) {
                button.classList.remove('is-inactive');
                button.classList.add('is-active');
                button.setAttribute('aria-pressed', 'true');
                if (label) label.textContent = 'Bookmarked';
            } else {
                button.classList.remove('is-active');
                button.classList.add('is-inactive');
                button.setAttribute('aria-pressed', 'false');
                if (label) label.textContent = 'Bookmark';
            }
        });
    }

    updateLikeCount(count) {
        document.querySelectorAll('.mod-like-total').forEach(element => {
            element.textContent = this.formatNumber(count);
        });
    }

    formatNumber(num) {
        return new Intl.NumberFormat('en-US').format(num);
    }

    getModId() {
        // Try to get mod ID from data attribute or URL
        const modElement = document.querySelector('[data-mod-id]');
        if (modElement) {
            console.log('Found mod ID from [data-mod-id]:', modElement.dataset.modId);
            return modElement.dataset.modId;
        }

        // Try to get from like button
        const likeButton = document.querySelector('[data-like-button]');
        if (likeButton && likeButton.dataset.postId) {
            console.log('Found mod ID from like button [data-post-id]:', likeButton.dataset.postId);
            return likeButton.dataset.postId;
        }

        // Fallback: try to extract from URL
        const urlParts = window.location.pathname.split('/');
        const modIndex = urlParts.indexOf('mods');
        if (modIndex !== -1 && urlParts[modIndex + 2]) {
            console.log('Found mod ID from URL:', urlParts[modIndex + 2]);
            return urlParts[modIndex + 2];
        }

        console.error('Mod ID not found anywhere!');
        return null;
    }
}

// Initialize
console.log('Initializing LikeBookmarkHandler...');
new LikeBookmarkHandler();
