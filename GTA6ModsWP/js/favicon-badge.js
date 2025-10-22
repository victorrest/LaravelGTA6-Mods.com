(function () {
    'use strict';

    class FaviconBadge {
        constructor() {
            this.size = 32;
            this.canvas = document.createElement('canvas');
            this.canvas.width = this.size;
            this.canvas.height = this.size;
            this.context = this.canvas.getContext('2d');
            this.originalHref = null;
            this.loading = false;
            this.pendingCount = null;

            this.captureOriginalFavicon();
        }

        captureOriginalFavicon() {
            const links = Array.from(document.querySelectorAll('link[rel*="icon"]'));
            if (links.length > 0) {
                this.originalHref = links[0].href;
            }
        }

        update(count) {
            const value = Number.parseInt(count, 10);
            if (!Number.isFinite(value) || value <= 0) {
                this.reset();
                return;
            }

            if (this.loading) {
                this.pendingCount = value;
                return;
            }

            const img = new Image();
            img.crossOrigin = 'anonymous';
            this.loading = true;
            this.pendingCount = null;

            img.onload = () => {
                this.drawBadge(img, value);
                this.loading = false;

                if (Number.isFinite(this.pendingCount)) {
                    const pending = this.pendingCount;
                    this.pendingCount = null;
                    this.update(pending);
                }
            };

            img.onerror = () => {
                this.drawBadge(null, value);
                this.loading = false;

                if (Number.isFinite(this.pendingCount)) {
                    const pending = this.pendingCount;
                    this.pendingCount = null;
                    this.update(pending);
                }
            };

            if (this.originalHref) {
                img.src = this.originalHref;
            } else {
                img.onerror();
            }
        }

        drawBadge(image, count) {
            const ctx = this.context;
            ctx.clearRect(0, 0, this.size, this.size);

            if (image instanceof Image) {
                ctx.drawImage(image, 0, 0, this.size, this.size);
            }

            const radius = Math.floor(this.size * 0.22);
            const centerX = this.size - radius * 1.1;
            const centerY = radius * 1.1;

            ctx.beginPath();
            ctx.fillStyle = '#EF4444';
            ctx.strokeStyle = '#FFFFFF';
            ctx.lineWidth = Math.max(2, Math.floor(this.size * 0.06));
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();

            this.replaceFavicon(this.canvas.toDataURL('image/png'));
        }

        replaceFavicon(href) {
            const head = document.head || document.getElementsByTagName('head')[0];
            if (!head) {
                return;
            }

            Array.from(document.querySelectorAll('link[rel*="icon"]')).forEach((link) => {
                link.parentNode?.removeChild(link);
            });

            const link = document.createElement('link');
            link.rel = 'icon';
            link.type = 'image/png';
            link.href = href;
            head.appendChild(link);
        }

        reset() {
            if (!this.originalHref) {
                return;
            }

            const head = document.head || document.getElementsByTagName('head')[0];
            if (!head) {
                return;
            }

            Array.from(document.querySelectorAll('link[rel*="icon"]')).forEach((link) => {
                link.parentNode?.removeChild(link);
            });

            const link = document.createElement('link');
            link.rel = 'icon';
            link.href = this.originalHref;
            head.appendChild(link);
        }
    }

    if (!(window.faviconBadge instanceof FaviconBadge)) {
        window.faviconBadge = new FaviconBadge();
    }

    if (!Array.isArray(window.faviconBadgeQueue)) {
        window.faviconBadgeQueue = [];
    }

    if (window.faviconBadgeQueue.length > 0) {
        const lastValue = window.faviconBadgeQueue[window.faviconBadgeQueue.length - 1];
        window.faviconBadgeQueue = [];
        window.faviconBadge.update(lastValue);
    }
})();
