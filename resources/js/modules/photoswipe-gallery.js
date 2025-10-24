/**
 * PhotoSwipe Gallery Module
 * Handles image and video gallery with PhotoSwipe
 * Includes video action bar for manage/feature/report/delete
 */

import PhotoSwipeLightbox from 'photoswipe/lightbox';
import PhotoSwipe from 'photoswipe';
import 'photoswipe/style.css';

class ModGallery {
    constructor() {
        this.lightbox = null;
        this.galleryData = [];
        this.canManageVideos = false;
        this.modId = null;
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initGallery());
        } else {
            this.initGallery();
        }
    }

    initGallery() {
        // Find gallery data
        const galleryDataEl = document.querySelector('[id^="gallery-data-"]');
        if (!galleryDataEl) return;

        try {
            this.galleryData = JSON.parse(galleryDataEl.textContent);
            this.modId = galleryDataEl.id.replace('gallery-data-', '');

            // Check if user can manage videos
            const galleryContainer = document.querySelector('.card');
            this.canManageVideos = galleryContainer?.dataset?.canManageVideos === 'true';

            this.initPhotoSwipe();
            this.initLoadMore();
            this.initClickHandlers();
        } catch (error) {
            console.error('Error initializing gallery:', error);
        }
    }

    initPhotoSwipe() {
        const self = this;

        this.lightbox = new PhotoSwipeLightbox({
            dataSource: this.galleryData.map((item, index) => {
                if (item.type === 'video') {
                    return {
                        html: this.createVideoHTML(item),
                        width: item.width || 1920,
                        height: item.height || 1080,
                        alt: item.alt,
                        isVideo: true,
                        videoData: item
                    };
                } else {
                    return {
                        src: item.src,
                        width: item.width || 1920,
                        height: item.height || 1080,
                        alt: item.alt,
                        isVideo: false
                    };
                }
            }),
            pswpModule: PhotoSwipe,
            bgOpacity: 0.95,
            showHideAnimationType: 'fade',
            spacing: 0.1,
            loop: true,
            pinchToClose: true,
            closeOnVerticalDrag: true,
            preload: [1, 2]
        });

        // Add custom UI elements for videos
        this.lightbox.on('uiRegister', function() {
            // Video action bar will be added via HTML template
        });

        // Handle video initialization when slide changes
        this.lightbox.on('change', () => {
            const currentSlide = this.lightbox.pswp.currSlide;
            if (currentSlide?.data?.isVideo) {
                this.initVideoSlide(currentSlide);
            }
        });

        // Initialize first slide if it's a video
        this.lightbox.on('afterInit', () => {
            const firstSlide = this.lightbox.pswp.currSlide;
            if (firstSlide?.data?.isVideo) {
                this.initVideoSlide(firstSlide);
            }
        });

        this.lightbox.init();
    }

    createVideoHTML(videoItem) {
        const youtubeId = videoItem.youtube_id;
        const submitterName = videoItem.submitter_name || 'Anonymous';
        const isFeatured = videoItem.is_featured || false;
        const canManage = this.canManageVideos;
        const videoId = videoItem.video_id;

        return `
            <div class="pswp-video-container">
                <div class="pswp-video-wrapper">
                    <iframe
                        src="https://www.youtube.com/embed/${youtubeId}?autoplay=1&rel=0"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        class="pswp-video-iframe"
                    ></iframe>
                </div>

                <!-- Video Action Bar -->
                <div class="pswp-video-actions">
                    <div class="pswp-video-info">
                        <div class="flex items-center gap-2">
                            <i class="fab fa-youtube text-red-600"></i>
                            <span class="text-sm text-white/90">Uploaded by <strong>${this.escapeHtml(submitterName)}</strong></span>
                            ${isFeatured ? '<span class="px-2 py-0.5 bg-yellow-500 text-xs font-bold rounded">FEATURED</span>' : ''}
                        </div>
                    </div>
                    <div class="pswp-video-buttons">
                        ${canManage ? `
                            ${!isFeatured ? `
                                <button class="pswp-action-btn pswp-action-feature" data-video-id="${videoId}" title="Feature this video">
                                    <i class="fas fa-star"></i>
                                    <span>Feature</span>
                                </button>
                            ` : `
                                <button class="pswp-action-btn pswp-action-unfeature" data-video-id="${videoId}" title="Unfeature this video">
                                    <i class="fas fa-star-half-alt"></i>
                                    <span>Unfeature</span>
                                </button>
                            `}
                            <button class="pswp-action-btn pswp-action-delete" data-video-id="${videoId}" title="Delete this video">
                                <i class="fas fa-trash"></i>
                                <span>Delete</span>
                            </button>
                        ` : ''}
                        <button class="pswp-action-btn pswp-action-report" data-video-id="${videoId}" title="Report this video">
                            <i class="fas fa-flag"></i>
                            <span>Report</span>
                        </button>
                        <a href="https://www.youtube.com/watch?v=${youtubeId}" target="_blank" rel="noopener noreferrer" class="pswp-action-btn" title="Open on YouTube">
                            <i class="fab fa-youtube"></i>
                            <span>YouTube</span>
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    initVideoSlide(slide) {
        // Add event listeners to action buttons
        const container = slide.container;

        // Feature button
        const featureBtn = container.querySelector('.pswp-action-feature');
        if (featureBtn) {
            featureBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.featureVideo(featureBtn.dataset.videoId);
            });
        }

        // Unfeature button
        const unfeatureBtn = container.querySelector('.pswp-action-unfeature');
        if (unfeatureBtn) {
            unfeatureBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.unfeatureVideo(unfeatureBtn.dataset.videoId);
            });
        }

        // Delete button
        const deleteBtn = container.querySelector('.pswp-action-delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.deleteVideo(deleteBtn.dataset.videoId);
            });
        }

        // Report button
        const reportBtn = container.querySelector('.pswp-action-report');
        if (reportBtn) {
            reportBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.reportVideo(reportBtn.dataset.videoId);
            });
        }
    }

    initLoadMore() {
        const loadMoreBtn = document.getElementById('load-more-gallery');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', () => {
                document.querySelectorAll('.gallery-hidden-thumb').forEach(thumb => {
                    thumb.classList.remove('hidden');
                });
                loadMoreBtn.style.display = 'none';
            });
        }
    }

    initClickHandlers() {
        // Handle clicks on gallery items
        document.querySelectorAll('[data-pswp-index]').forEach(element => {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                const index = parseInt(element.dataset.pswpIndex);
                this.openGallery(index);
            });
        });
    }

    openGallery(index) {
        if (this.lightbox) {
            this.lightbox.loadAndOpen(index);
        }
    }

    // Video management actions
    async featureVideo(videoId) {
        if (!confirm('Feature this video? It will appear first in the gallery.')) return;

        try {
            const response = await fetch(`/api/videos/${videoId}/feature`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();

            if (response.ok) {
                alert(result.message || 'Video featured successfully!');
                location.reload();
            } else {
                alert(result.message || 'Failed to feature video');
            }
        } catch (error) {
            console.error('Error featuring video:', error);
            alert('Network error occurred');
        }
    }

    async unfeatureVideo(videoId) {
        if (!confirm('Remove featured status from this video?')) return;

        try {
            const response = await fetch(`/api/videos/${videoId}/unfeature`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();

            if (response.ok) {
                alert(result.message || 'Video unfeatured successfully!');
                location.reload();
            } else {
                alert(result.message || 'Failed to unfeature video');
            }
        } catch (error) {
            console.error('Error unfeaturing video:', error);
            alert('Network error occurred');
        }
    }

    async deleteVideo(videoId) {
        if (!confirm('Are you sure you want to delete this video? This action cannot be undone.')) return;

        try {
            const response = await fetch(`/api/videos/${videoId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();

            if (response.ok) {
                alert(result.message || 'Video deleted successfully!');
                location.reload();
            } else {
                alert(result.message || 'Failed to delete video');
            }
        } catch (error) {
            console.error('Error deleting video:', error);
            alert('Network error occurred');
        }
    }

    async reportVideo(videoId) {
        const reason = prompt('Why are you reporting this video?');
        if (!reason || reason.trim().length < 10) {
            alert('Please provide a detailed reason (at least 10 characters)');
            return;
        }

        try {
            const response = await fetch(`/api/videos/${videoId}/report`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ reason: reason.trim() })
            });

            const result = await response.json();

            if (response.ok) {
                alert(result.message || 'Video reported successfully. Thank you for helping keep our community safe.');
            } else {
                alert(result.message || 'Failed to report video');
            }
        } catch (error) {
            console.error('Error reporting video:', error);
            alert('Network error occurred');
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize gallery
new ModGallery();

export default ModGallery;
