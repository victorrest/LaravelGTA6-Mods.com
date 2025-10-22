(function ($) {
    'use strict';

    const VideoGallery = {
        init() {
            this.data = window.gta6modsVideoData || {};
            this.$document = $(document);
            this.$body = $('body');
            this.$legacyGrid = $('#videoGalleryGrid');
            this.$thumbnailGrid = $('#single-gallery-thumbnails');
            this.useLegacyGrid = this.$legacyGrid.length > 0;
            this.$grid = this.useLegacyGrid ? this.$legacyGrid : this.$thumbnailGrid;
            this.mediaQuery = window.matchMedia ? window.matchMedia('(max-width: 639px)') : null;
            this.mobileExpanded = false;
            this.toastContainer = null;
            this.activeReportModal = null;
            this.activeDeleteModal = null;
            this.activeFeatureModal = null;
            this.reportSuccessOverlay = null;
            this.reportSuccessProgressBar = null;
            this.reportSuccessTimer = null;
            this.reportSuccessDuration = 7000;
            this.reportSuccessRemaining = this.reportSuccessDuration;
            this.reportSuccessStart = null;
            this.reportSuccessPaused = false;
            this.reportSuccessHandlers = null;
            this.resizeRaf = null;
            this.ensureSubmitModal();
            this.bindEvents();
            this.applyResponsiveLayout();
            this.bindResponsiveListeners();
        },

        bindEvents() {
            this.$document
                .on('click', '[data-video-submit-modal]', (event) => this.onSubmitButtonClick(event))
                .on('click', '.video-modal-close', () => this.closeSubmitModal())
                .on('click', '.video-modal-overlay', (event) => {
                    if (event.target === event.currentTarget) {
                        this.closeSubmitModal();
                    }
                })
                .on('submit', '#videoSubmitForm', (event) => this.submitVideo(event))
                .on('click', '[data-video-report]', (event) => this.reportVideo(event))
                .on('click', '[data-video-delete]', (event) => this.deleteVideo(event))
                .on('click', '[data-video-feature]', (event) => this.featureVideo(event));

            if (this.useLegacyGrid) {
                this.$document
                    .on('click', '[data-video-load-more]', (event) => this.loadMoreVideos(event))
                    .on('click', '.video-gallery-item', (event) => this.openLightbox(event));
            }
        },

        bindResponsiveListeners() {
            if (!this.useLegacyGrid) {
                return;
            }

            const handler = () => {
                this.applyResponsiveLayout();
            };

            if (this.mediaQuery) {
                if (typeof this.mediaQuery.addEventListener === 'function') {
                    this.mediaQuery.addEventListener('change', handler);
                } else if (typeof this.mediaQuery.addListener === 'function') {
                    this.mediaQuery.addListener(handler);
                }
            }

            this.onResize = () => {
                if (this.resizeRaf) {
                    window.cancelAnimationFrame(this.resizeRaf);
                }

                this.resizeRaf = window.requestAnimationFrame(() => {
                    this.applyResponsiveLayout();
                });
            };

            window.addEventListener('resize', this.onResize, { passive: true });
        },

        ensureSubmitModal() {
            if ($('#videoSubmitModal').length) {
                return;
            }

            const modalHtml = `
                <div id="videoSubmitModal" class="video-modal-overlay hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="videoSubmitModalTitle" aria-hidden="true">
                    <div class="video-modal-dialog bg-white rounded-xl shadow-2xl w-full max-w-md relative">
                        <button type="button" class="video-modal-close absolute top-3 right-3 text-gray-400 hover:text-gray-700" aria-label="${this.escapeHtml(this.getText('close', 'Close'))}">
                            <i class="fa-solid fa-xmark fa-lg" aria-hidden="true"></i>
                        </button>
                        <form id="videoSubmitForm" class="space-y-4 p-6">
                            <h3 id="videoSubmitModalTitle" class="text-xl font-semibold text-gray-900">
                                ${this.escapeHtml(this.getText('submitVideo', 'Submit Video'))}
                            </h3>
                            <input type="hidden" name="mod_id" value="">
                            <div class="space-y-2">
                                <label for="videoYoutubeUrl" class="block text-sm font-medium text-gray-700">
                                    ${this.escapeHtml(this.getText('youtubeUrl', 'YouTube URL'))}
                                </label>
                                <input
                                    id="videoYoutubeUrl"
                                    name="youtube_url"
                                    type="url"
                                    required
                                    aria-required="true"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-pink-500 focus:outline-none focus:ring-2 focus:ring-pink-500"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                    aria-describedby="videoModalHelp videoModalRateLimit"
                                >
                                <p id="videoModalHelp" class="text-xs text-gray-500">
                                    ${this.escapeHtml(this.getText('urlHelp', 'Paste the full YouTube video link.'))}
                                </p>
                                <p id="videoModalRateLimit" class="text-xs font-semibold text-amber-600 flex items-center gap-2">
                                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                    <span>${this.escapeHtml(this.getText('rateLimit', 'You can submit up to 3 videos per day.'))}</span>
                                </p>
                            </div>
                            <div class="video-message text-sm" role="alert" aria-live="polite"></div>
                            <button type="submit" class="w-full rounded-md bg-pink-600 py-2 px-4 font-semibold text-white transition hover:bg-pink-700">
                                ${this.escapeHtml(this.getText('submitForModeration', 'Submit for Moderation'))}
                            </button>
                        </form>
                    </div>
                </div>
            `;

            this.$body.append(modalHtml);
        },

        onSubmitButtonClick(event) {
            event.preventDefault();

            if (!this.data.isLoggedIn) {
                this.showLoginModal({
                    message: this.getText('loginModalMessage', 'You must be logged in to continue.'),
                });
                return;
            }

            const trigger = event.currentTarget;
            const modId = trigger ? $(trigger).data('mod-id') : null;
            this.openSubmitModal(modId);
        },

        openSubmitModal(modId) {
            const $modal = $('#videoSubmitModal');
            const $form = $modal.find('#videoSubmitForm');

            if (!$modal.length || !$form.length) {
                return;
            }

            const targetModId = modId || this.data.modId || '';
            $form[0].reset();
            $form.find('[name="mod_id"]').val(targetModId);
            $form.find('.video-message').empty();
            $form.find('button[type="submit"]').prop('disabled', false).text(this.getText('submitForModeration', 'Submit for Moderation'));
            $form.find('#videoYoutubeUrl').focus();

            $modal.attr('aria-hidden', 'false').removeClass('hidden');
            window.requestAnimationFrame(() => {
                $modal.addClass('is-visible');
            });
        },

        closeSubmitModal() {
            const $modal = $('#videoSubmitModal');
            if (!$modal.length) {
                return;
            }

            $modal.removeClass('is-visible');
            window.setTimeout(() => {
                $modal.attr('aria-hidden', 'true').addClass('hidden');
            }, 180);
        },

        submitVideo(event) {
            event.preventDefault();

            const $form = $(event.currentTarget);
            const $button = $form.find('button[type="submit"]');
            const $message = $form.find('.video-message');

            $button.prop('disabled', true).html(`<i class="fa-solid fa-spinner fa-spin mr-2"></i>${this.escapeHtml(this.getText('submitting', 'Submitting…'))}`);
            $message.empty();

            $.ajax({
                url: `${this.data.restUrl || ''}gta6mods/v1/videos/submit`,
                method: 'POST',
                beforeSend: (xhr) => {
                    if (this.data.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', this.data.nonce);
                    }
                },
                data: {
                    mod_id: $form.find('[name="mod_id"]').val(),
                    youtube_url: $form.find('[name="youtube_url"]').val(),
                },
            })
                .done((response) => {
                    const message = response && response.message ? response.message : this.getText('submitSuccess', 'Video submitted successfully! It will appear after moderation.');
                    $message.html(`
                        <div class="rounded-md border border-green-400 bg-green-100 px-4 py-3 text-green-700">
                            <i class="fa-solid fa-check-circle mr-2" aria-hidden="true"></i>${this.escapeHtml(message)}
                        </div>
                    `);

                    window.setTimeout(() => {
                        this.closeSubmitModal();
                    }, 2000);
                })
                .fail((xhr) => {
                    let error = this.getText('submitError', 'Failed to submit video. Please try again.');

                    if (xhr && xhr.status === 429) {
                        error = this.getText('rateLimitError', 'You have reached your daily limit of 3 videos. Please try again tomorrow.');
                    } else if (xhr && xhr.status === 409) {
                        error = this.getText('duplicateError', 'This video has already been submitted for this mod.');
                    } else if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        error = xhr.responseJSON.message;
                    }

                    $message.html(`
                        <div class="rounded-md border border-red-400 bg-red-100 px-4 py-3 text-red-700">
                            <i class="fa-solid fa-exclamation-circle mr-2" aria-hidden="true"></i>${this.escapeHtml(error)}
                        </div>
                    `);

                    $button.prop('disabled', false).text(this.getText('submitForModeration', 'Submit for Moderation'));
                });
        },

        loadMoreVideos(event) {
            event.preventDefault();

            if (!this.$grid || !this.$grid.length) {
                return;
            }

            const $button = $(event.currentTarget);
            const $hiddenItems = this.$grid.find('.video-gallery-item.hidden');

            $button.remove();

            if (!$hiddenItems.length) {
                return;
            }

            this.mobileExpanded = true;
            this.$grid.removeClass('is-mobile-limited');

            $hiddenItems.each((index, element) => {
                const $item = $(element);
                $item.removeClass('hidden').addClass('revealing').removeAttr('data-mobile-hidden');

                window.setTimeout(() => {
                    $item.addClass('revealing-active');
                }, index * 60);

                window.setTimeout(() => {
                    $item.removeClass('revealing revealing-active');
                }, 400 + index * 60);
            });

            window.requestAnimationFrame(() => {
                this.applyResponsiveLayout();
                this.notifyGalleryUpdated();
            });
        },

        applyResponsiveLayout() {
            if (!this.useLegacyGrid || !this.$grid || !this.$grid.length) {
                return;
            }

            const isMobile = this.mediaQuery ? this.mediaQuery.matches : window.innerWidth < 640;
            const $placeholder = this.$grid.find('[data-video-load-more]');
            const $items = this.$grid.find('.video-gallery-item');
            const loadMoreTemplate = this.getText('loadMoreCount', 'Load %d more');

            if (!isMobile || this.mobileExpanded) {
                this.$grid.removeClass('is-mobile-limited');
                this.$grid.find('.video-gallery-item[data-mobile-hidden="true"]').each((index, element) => {
                    $(element).removeClass('hidden').removeAttr('data-mobile-hidden');
                });

                if ($placeholder.length) {
                    const hiddenCount = this.$grid.find('.video-gallery-item.hidden').length;

                    if (hiddenCount > 0) {
                        $placeholder.removeClass('hidden').attr('data-hidden-count', hiddenCount);
                        const desktopLabel = $placeholder.find('[data-hidden-count-label]');
                        const desktopText = loadMoreTemplate.replace('%d', hiddenCount);
                        if (desktopLabel.length) {
                            desktopLabel.text(desktopText);
                        } else {
                            $placeholder.find('span').last().text(desktopText);
                        }
                    } else {
                        $placeholder.addClass('hidden').attr('data-hidden-count', '0');
                    }

                    if (!$placeholder.is(':last-child')) {
                        this.$grid.append($placeholder);
                    }
                }

                return;
            }

            this.$grid.addClass('is-mobile-limited');

            this.$grid.find('.video-gallery-item[data-mobile-hidden="true"]').each((index, element) => {
                $(element).removeClass('hidden').removeAttr('data-mobile-hidden');
            });

            const totalVideos = $items.length;
            const mobileVisibleLimit = totalVideos > 4 ? 3 : totalVideos;
            let visibleCount = 0;

            $items.each((index, element) => {
                const $element = $(element);

                if ($element.hasClass('hidden')) {
                    return;
                }

                visibleCount += 1;

                if (visibleCount > mobileVisibleLimit) {
                    $element.addClass('hidden').attr('data-mobile-hidden', 'true');
                }
            });

            if ($placeholder.length) {
                const totalHiddenVideos = this.$grid.find('.video-gallery-item.hidden').length;

                if (totalHiddenVideos > 0) {
                    const labelText = loadMoreTemplate.replace('%d', totalHiddenVideos);
                    $placeholder.removeClass('hidden').attr('data-hidden-count', totalHiddenVideos);

                    const label = $placeholder.find('[data-hidden-count-label]');
                    if (label.length) {
                        label.text(labelText);
                    } else {
                        $placeholder.find('span').last().text(labelText);
                    }

                    const $visibleItems = $items.filter((index, element) => !$(element).hasClass('hidden'));
                    if ($visibleItems.length) {
                        const insertIndex = Math.min(2, $visibleItems.length - 1);
                        $visibleItems.eq(insertIndex).after($placeholder);
                    } else {
                        this.$grid.prepend($placeholder);
                    }
                } else {
                    $placeholder.addClass('hidden').attr('data-hidden-count', '0');
                }
            }
        },

        openLightbox(event) {
            event.preventDefault();

            const clickedElement = event.currentTarget;
            if (!clickedElement || typeof window.PhotoSwipe !== 'function') {
                const youtubeId = clickedElement ? $(clickedElement).data('youtube-id') : null;
                if (youtubeId) {
                    window.open(`https://www.youtube.com/watch?v=${youtubeId}`, '_blank', 'noopener');
                }
                return;
            }

            const items = Array.from(document.querySelectorAll('.video-gallery-item')).map((element) => {
                const dataset = element.dataset || {};
                const videoId = dataset.videoId || element.getAttribute('data-video-id');
                const isReported = dataset.isReported === '1' || element.getAttribute('data-is-reported') === '1';
                const isFeatured = dataset.isFeatured === '1' || element.getAttribute('data-is-featured') === '1';
                const canManage = dataset.canManage === '1' || !!this.data.canManage;
                const canFeature = dataset.canFeature === '1' || !!this.data.canFeature;

                return {
                    element,
                    slide: {
                        html: this.buildSlideHtml({
                            youtubeId: dataset.youtubeId || element.getAttribute('data-youtube-id'),
                            addedBy: dataset.addedBy || element.getAttribute('data-added-by'),
                            profileUrl: dataset.profileUrl || element.getAttribute('data-profile-url'),
                            videoId,
                            videoTitle: dataset.videoTitle || element.getAttribute('data-video-title'),
                            isReported,
                            isFeatured,
                            canManage,
                            canFeature,
                            reportCount: dataset.reportCount || element.getAttribute('data-report-count') || '0',
                        }),
                    },
                };
            });

            const startIndex = Math.max(
                0,
                items.findIndex((item) => item.element === clickedElement)
            );

            const pswp = new window.PhotoSwipe({
                dataSource: items.map((item) => item.slide),
                index: startIndex,
                bgOpacity: 0.94,
                padding: { top: 48, bottom: 144, left: 24, right: 24 },
                showHideAnimationType: 'fade',
                wheelToZoom: false,
            });

            pswp.on('contentActivate', (eventData) => {
                const frame = eventData && eventData.content && eventData.content.element
                    ? eventData.content.element.querySelector('.pswp-video-frame')
                    : null;

                if (frame && !frame.querySelector('iframe')) {
                    const youtubeId = frame.getAttribute('data-youtube-id');
                    const title = frame.getAttribute('data-video-title') || '';
                    if (youtubeId) {
                        const baseTitle = this.getText('youtubePlayerTitle', 'YouTube video player');
                        const frameTitle = title ? `${baseTitle}: ${title}` : baseTitle;
                        frame.innerHTML = `
                            <iframe
                                src="https://www.youtube.com/embed/${encodeURIComponent(youtubeId)}?autoplay=1&rel=0"
                                title="${this.escapeHtml(frameTitle)}"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        `;
                    }
                }

                if (pswp.element) {
                    pswp.element.classList.add('pswp--video');
                }
            });

            pswp.on('contentDeactivate', (eventData) => {
                const frame = eventData && eventData.content && eventData.content.element
                    ? eventData.content.element.querySelector('.pswp-video-frame')
                    : null;

                if (frame) {
                    frame.innerHTML = '';
                }
            });

            pswp.on('close', () => {
                if (pswp.element) {
                    pswp.element.classList.remove('pswp--video');
                }
            });

            pswp.init();
        },

        reportVideo(event) {
            event.preventDefault();
            event.stopPropagation();

            const $button = $(event.currentTarget);
            const videoId = $button.data('video-id');

            if (!videoId) {
                return;
            }

            if (!this.data.isLoggedIn) {
                this.showLoginModal({
                    message: this.getText('loginModalReportMessage', 'You must be logged in to report a video.'),
                });
                return;
            }

            if (this.isVideoReported(videoId)) {
                this.updateReportButtonState($button, true);
                this.showToast('info', this.getText('reportAlreadySubmitted', 'You have already reported this video.'));
                return;
            }

            this.openReportModal(videoId, $button);
        },

        deleteVideo(event) {
            event.preventDefault();
            event.stopPropagation();

            if (!this.canManageVideos()) {
                this.showToast('error', this.getText('noPermission', 'You do not have permission to perform this action.'));
                return;
            }

            const $button = $(event.currentTarget);
            const videoId = $button.data('video-id');

            if (!videoId) {
                return;
            }

            this.openDeleteModal(videoId, $button);
        },

        openDeleteModal(videoId, $trigger) {
            this.closeDeleteModal();
            this.closeReportModal();
            this.closeFeatureModal();

            const isAuthor = this.isCurrentUserModAuthor();
            const modalTitleKey = isAuthor ? 'deleteModalTitleAuthor' : 'deleteModalTitle';
            const modalTitleDefault = isAuthor ? 'Delete this video?' : 'Hide this video?';
            const modalDescriptionKey = isAuthor ? 'deleteModalDescriptionAuthor' : 'deleteModalDescription';
            const modalDescriptionDefault = isAuthor
                ? 'You created this mod, so you can delete videos submitted to it. Deleted videos cannot be republished and this action cannot be undone.'
                : 'This will hide the video from the gallery but keep it available to moderators.';
            const confirmKey = isAuthor ? 'deleteModalConfirmAuthor' : 'deleteModalConfirm';
            const confirmDefault = isAuthor ? 'Delete video' : 'Hide video';

            const overlay = document.createElement('div');
            overlay.className = 'video-delete-modal fixed inset-0 z-[2147483647] flex items-center justify-center bg-black bg-opacity-60 p-4';
            overlay.style.zIndex = '2147483647';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'videoDeleteModalTitle');
            overlay.innerHTML = `
                <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl p-6" role="document">
                    <h3 id="videoDeleteModalTitle" class="text-xl font-semibold text-gray-900 mb-2">${this.escapeHtml(this.getText(modalTitleKey, modalTitleDefault))}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-6">${this.escapeHtml(this.getText(modalDescriptionKey, modalDescriptionDefault))}</p>
                    <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                        <button type="button" data-delete-cancel class="w-full sm:w-auto rounded-lg bg-gray-200 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-300 transition">${this.escapeHtml(this.getText('deleteModalCancel', 'Cancel'))}</button>
                        <button type="button" data-delete-confirm data-confirm-key="${confirmKey}" class="w-full sm:w-auto rounded-lg bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700 transition">${this.escapeHtml(this.getText(confirmKey, confirmDefault))}</button>
                    </div>
                </div>
            `;

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    this.closeDeleteModal();
                }
            });

            const confirmButton = overlay.querySelector('[data-delete-confirm]');
            const cancelButton = overlay.querySelector('[data-delete-cancel]');

            if (cancelButton) {
                cancelButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.closeDeleteModal();
                });
            }

            if (confirmButton) {
                confirmButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.submitDelete(videoId, $trigger, confirmButton);
                });
            }

            document.body.appendChild(overlay);
            this.activeDeleteModal = overlay;

            window.requestAnimationFrame(() => {
                if (confirmButton) {
                    confirmButton.focus();
                }
            });
        },

        closeDeleteModal() {
            if (this.activeDeleteModal) {
                this.activeDeleteModal.remove();
                this.activeDeleteModal = null;
            }
        },

        submitDelete(videoId, $trigger, confirmButton) {
            if (!videoId) {
                return;
            }

            const $button = $trigger && $trigger.length ? $trigger : null;
            const originalHtml = $button ? $button.html() : '';
            const originalConfirmLabel = confirmButton ? confirmButton.innerHTML : '';

            if ($button) {
                $button.prop('disabled', true).attr('aria-disabled', 'true');
                $button.html(`<i class="fa-solid fa-spinner fa-spin mr-2"></i>${this.escapeHtml(this.getText('loading', 'Loading…'))}`);
            }

            if (confirmButton) {
                confirmButton.disabled = true;
                confirmButton.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>${this.escapeHtml(this.getText('loading', 'Loading…'))}`;
            }

            $.ajax({
                url: `${this.data.restUrl || ''}gta6mods/v1/videos/${encodeURIComponent(videoId)}`,
                method: 'DELETE',
                beforeSend: (xhr) => {
                    if (this.data.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', this.data.nonce);
                    }
                },
            })
                .done((response) => {
                    const defaultMessage = this.isCurrentUserModAuthor()
                        ? this.getText('deleteSuccessAuthor', 'Video deleted.')
                        : this.getText('deleteSuccess', 'Video hidden from gallery.');
                    const message = response && response.message
                        ? response.message
                        : defaultMessage;
                    this.removeVideoFromGallery(videoId);
                    this.closeDeleteModal();
                    this.showToast('success', message);
                })
                .fail((xhr) => {
                    const error = xhr && xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : this.getText('deleteError', 'Failed to update video.');
                    this.showToast('error', error);
                })
                .always(() => {
                    if ($button) {
                        $button.prop('disabled', false).attr('aria-disabled', 'false').html(originalHtml);
                    }

                    if (confirmButton) {
                        confirmButton.disabled = false;
                        const confirmKey = confirmButton.getAttribute('data-confirm-key') || 'deleteModalConfirm';
                        const confirmDefault = confirmKey === 'deleteModalConfirmAuthor' ? 'Delete video' : 'Hide video';
                        confirmButton.innerHTML = originalConfirmLabel || this.escapeHtml(this.getText(confirmKey, confirmDefault));
                    }
                });
        },

        buildSlideHtml({ youtubeId, addedBy, profileUrl, videoId, videoTitle, isReported, isFeatured, canManage, canFeature }) {
            const safeYoutubeId = this.escapeHtml(youtubeId || '');
            const safeAddedBy = this.escapeHtml(addedBy || '');
            const safeProfileUrl = this.escapeHtml(profileUrl || '#');
            const safeVideoId = this.escapeHtml(videoId || '');
            const safeVideoTitle = this.escapeHtml(videoTitle || '');
            const reported = Boolean(isReported);
            const featured = Boolean(isFeatured);
            const manage = Boolean(canManage);
            const allowFeature = Boolean(canFeature);
            const reportText = reported
                ? this.escapeHtml(this.getText('reportAlready', 'Reported'))
                : this.escapeHtml(this.getText('reportVideo', 'Report'));
            const featureText = featured
                ? this.escapeHtml(this.getText('featureActive', 'Featured'))
                : this.escapeHtml(this.getText('featureVideo', 'Feature this video'));

            return `
                <div class="pswp-video-content text-white">
                    <div class="pswp-video-player">
                        <div class="pswp-video-frame" data-youtube-id="${safeYoutubeId}" data-video-title="${safeVideoTitle}"></div>
                    </div>
                    <div class="pswp-video-meta">
                        ${safeVideoTitle ? `
                            <div class="pswp-video-title">${safeVideoTitle}</div>
                        ` : ''}
                        <div class="pswp-video-meta-footer">
                            <div class="pswp-video-author">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <span>
                                    ${this.escapeHtml(this.getText('addedBy', 'Added by'))}
                                    <a href="${safeProfileUrl}">${safeAddedBy}</a>
                                </span>
                            </div>
                            <div class="pswp-video-actions">
                                <button
                                    type="button"
                                    class="pswp-video-action${reported ? ' is-disabled' : ''}"
                                    data-video-report="${safeVideoId}"
                                    data-video-id="${safeVideoId}"
                                    data-reported="${reported ? '1' : '0'}"
                                    aria-disabled="${reported ? 'true' : 'false'}"
                                >
                                    <i class="fa-solid fa-flag" aria-hidden="true"></i>
                                    <span data-report-label>${reportText}</span>
                                </button>
                            </div>
                        </div>
                        ${manage ? `
                            <div class="pswp-video-owner-actions">
                                ${allowFeature ? `
                                    <button
                                        type="button"
                                        class="pswp-video-action${featured ? ' is-active' : ''}"
                                        data-video-feature="${safeVideoId}"
                                        data-video-id="${safeVideoId}"
                                        data-featured="${featured ? '1' : '0'}"
                                        aria-pressed="${featured ? 'true' : 'false'}"
                                    >
                                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                                        <span data-feature-label>${featureText}</span>
                                    </button>
                                ` : ''}
                                <button
                                    type="button"
                                    class="pswp-video-action pswp-video-action-danger"
                                    data-video-delete="${safeVideoId}"
                                    data-video-id="${safeVideoId}"
                                >
                                    <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                    ${this.escapeHtml(this.getText('deleteVideo', 'Delete'))}
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        },

        canManageVideos() {
            return Boolean(this.data && (this.data.canManage || this.data.canModerate));
        },

        isCurrentUserModAuthor() {
            if (!this.data) {
                return false;
            }

            const currentUserId = parseInt(this.data.currentUserId, 10);
            const modAuthorId = parseInt(this.data.modAuthorId, 10);

            if (Number.isNaN(currentUserId) || Number.isNaN(modAuthorId)) {
                return false;
            }

            return currentUserId > 0 && modAuthorId > 0 && currentUserId === modAuthorId;
        },

        isVideoReported(videoId) {
            if (!videoId) {
                return false;
            }

            if (this.$grid && this.$grid.length) {
                const $item = this.$grid.find(`.video-gallery-item[data-video-id="${videoId}"]`).first();
                if ($item.length && $item.attr('data-is-reported') === '1') {
                    return true;
                }
            }

            const $button = this.$document.find(`[data-video-report][data-video-id="${videoId}"]`).first();
            if ($button.length && ($button.attr('data-reported') === '1' || $button.data('reported') === 1)) {
                return true;
            }

            return false;
        },

        openReportModal(videoId, $trigger) {
            this.closeReportModal();
            this.closeDeleteModal();
            this.closeFeatureModal();
            this.closeReportSuccessModal();

            const overlay = document.createElement('div');
            overlay.className = 'video-report-modal fixed inset-0 z-[2147483647] flex items-center justify-center bg-black bg-opacity-60 p-4';
            overlay.style.zIndex = '2147483647';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'videoReportModalTitle');
            overlay.innerHTML = `
                <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl p-6" role="document">
                    <h3 id="videoReportModalTitle" class="text-xl font-semibold text-gray-900 mb-2">${this.escapeHtml(this.getText('reportModalTitle', 'Report this video'))}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-6">${this.escapeHtml(this.getText('reportModalDescription', 'If the video has been removed or is inappropriate, report it so a moderator can review and take action.'))}</p>
                    <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                        <button type="button" data-report-cancel class="w-full sm:w-auto rounded-lg bg-gray-200 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-300 transition">${this.escapeHtml(this.getText('reportModalCancel', 'Cancel'))}</button>
                        <button type="button" data-report-confirm class="w-full sm:w-auto rounded-lg bg-pink-600 px-4 py-2 font-semibold text-white hover:bg-pink-700 transition">${this.escapeHtml(this.getText('reportModalConfirm', 'Submit report'))}</button>
                    </div>
                </div>
            `;

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    this.closeReportModal();
                }
            });

            const confirmButton = overlay.querySelector('[data-report-confirm]');
            const cancelButton = overlay.querySelector('[data-report-cancel]');

            if (cancelButton) {
                cancelButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.closeReportModal();
                });
            }

            if (confirmButton) {
                confirmButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.submitReport(videoId, $trigger, confirmButton);
                });
            }

            document.body.appendChild(overlay);
            this.activeReportModal = overlay;

            window.requestAnimationFrame(() => {
                if (confirmButton) {
                    confirmButton.focus();
                }
            });
        },

        closeReportModal() {
            if (this.activeReportModal) {
                this.activeReportModal.remove();
                this.activeReportModal = null;
            }
        },

        showReportSuccessModal(message) {
            this.closeReportSuccessModal();

            const overlay = document.createElement('div');
            overlay.className = 'video-report-success fixed inset-0 z-[2147483647] flex items-center justify-center bg-black bg-opacity-60 p-4';
            overlay.style.zIndex = '2147483647';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'videoReportSuccessTitle');

            const title = this.getText('reportSuccessTitle', 'Thank you!');
            let primary = this.getText('reportSuccessBodyPrimary', 'Your report was received and will be reviewed shortly.');
            let secondary = this.getText('reportSuccessBodySecondary', 'Thank you for helping us maintain the site’s quality!');
            const closeLabel = this.getText('close', 'Close');

            if (typeof message === 'string' && message.trim().length) {
                const segments = message.match(/[^.!?]+[.!?]?/g);
                if (segments && segments.length) {
                    const trimmed = segments
                        .map((segment) => segment.trim())
                        .filter(Boolean)
                        .filter((segment) => !/^(thanks|thank you)!?$/i.test(segment));

                    if (trimmed.length) {
                        const [firstSegment, ...remainingSegments] = trimmed;
                        primary = firstSegment || '';

                        if (!primary.length && remainingSegments.length) {
                            primary = remainingSegments.shift();
                        }

                        if (remainingSegments.length) {
                            const combined = remainingSegments.join(' ').trim();
                            if (combined.length) {
                                secondary = combined;
                            }
                        } else if (!primary.length) {
                            primary = this.getText('reportSuccessBodyPrimary', 'Your report was received and will be reviewed shortly.');
                        }
                    }
                }
            }

            const combinedBody = [primary, secondary]
                .map((part) => (typeof part === 'string' ? part.trim() : ''))
                .filter(Boolean)
                .join(' ');
            const fallbackBody = [
                this.getText('reportSuccessBodyPrimary', 'Your report was received and will be reviewed shortly.'),
                this.getText('reportSuccessBodySecondary', 'Thank you for helping us maintain the site’s quality!'),
            ]
                .map((part) => (typeof part === 'string' ? part.trim() : ''))
                .filter(Boolean)
                .join(' ');
            const finalBody = combinedBody.length ? combinedBody : fallbackBody;

            overlay.innerHTML = `
                <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl p-6 pt-8 relative text-center overflow-hidden" data-success-content>
                    <button type="button" data-success-close class="absolute top-3 right-3 text-gray-400 hover:text-gray-600" aria-label="${this.escapeHtml(closeLabel)}">
                        <i class="fa-solid fa-xmark fa-lg" aria-hidden="true"></i>
                    </button>
                    <h3 id="videoReportSuccessTitle" class="text-2xl font-semibold text-green-600">${this.escapeHtml(title)}</h3>
                    <p class="mt-3 text-sm text-gray-700">${this.escapeHtml(finalBody)}</p>
                    <div data-success-track class="pointer-events-none absolute left-0 right-0 bottom-0 h-1 bg-emerald-100">
                        <span data-success-progress class="absolute inset-0 bg-green-500" style="transform: scaleX(1);"></span>
                    </div>
                </div>
            `;

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    this.closeReportSuccessModal();
                }
            });

            const closeButton = overlay.querySelector('[data-success-close]');
            if (closeButton) {
                closeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.closeReportSuccessModal();
                });
            }

            const progressBar = overlay.querySelector('[data-success-progress]');
            const contentBox = overlay.querySelector('[data-success-content]');

            document.body.appendChild(overlay);
            this.reportSuccessOverlay = overlay;
            this.reportSuccessProgressBar = progressBar;
            this.reportSuccessRemaining = this.reportSuccessDuration;
            this.reportSuccessPaused = false;
            this.startReportSuccessCountdown(this.reportSuccessDuration);

            if (contentBox) {
                const handlePointerEnter = (event) => {
                    if (event.pointerType === 'mouse') {
                        this.pauseReportSuccessCountdown();
                    }
                };

                const handlePointerLeave = (event) => {
                    if (event.pointerType === 'mouse') {
                        this.resumeReportSuccessCountdown();
                    }
                };

                const handlePointerDown = (event) => {
                    if (event.pointerType === 'touch' || event.pointerType === 'pen') {
                        if (event.target && event.target.closest('[data-success-close]')) {
                            return;
                        }
                        if (this.reportSuccessPaused) {
                            this.resumeReportSuccessCountdown();
                        } else {
                            this.pauseReportSuccessCountdown();
                        }
                    }
                };

                contentBox.addEventListener('pointerenter', handlePointerEnter);
                contentBox.addEventListener('pointerleave', handlePointerLeave);
                contentBox.addEventListener('pointerdown', handlePointerDown);

                this.reportSuccessHandlers = {
                    contentBox,
                    handlePointerEnter,
                    handlePointerLeave,
                    handlePointerDown,
                };
            }
        },

        getTimestamp() {
            return (typeof performance !== 'undefined' && performance && typeof performance.now === 'function')
                ? performance.now()
                : Date.now();
        },

        getScaleX(element) {
            if (!element) {
                return 1;
            }

            const computed = window.getComputedStyle(element);
            const transform = computed.transform || computed.webkitTransform || '';

            if (!transform || transform === 'none') {
                return 1;
            }

            const match = transform.match(/matrix(3d)?\((.+)\)/);
            if (!match) {
                return 1;
            }

            const values = match[2].split(',').map((value) => parseFloat(value.trim())).filter((value) => !Number.isNaN(value));

            if (!values.length) {
                return 1;
            }

            return values[0];
        },

        animateReportSuccessProgress(duration, fromScale = 1) {
            if (!this.reportSuccessProgressBar) {
                return;
            }

            const bar = this.reportSuccessProgressBar;
            bar.style.transformOrigin = 'left center';
            bar.style.transition = 'none';
            const safeScale = Number.isFinite(fromScale) ? Math.max(Math.min(fromScale, 1), 0) : 1;
            bar.style.transform = `scaleX(${safeScale})`;
            // eslint-disable-next-line no-unused-expressions
            bar.offsetWidth;
            bar.style.transition = `transform ${duration}ms linear`;
            window.requestAnimationFrame(() => {
                bar.style.transform = 'scaleX(0)';
            });
        },

        startReportSuccessCountdown(duration, fromScale = 1) {
            const remaining = typeof duration === 'number' && duration > 0
                ? duration
                : this.reportSuccessDuration;

            this.reportSuccessRemaining = remaining;
            this.reportSuccessStart = this.getTimestamp();

            if (this.reportSuccessTimer) {
                window.clearTimeout(this.reportSuccessTimer);
            }

            this.animateReportSuccessProgress(remaining, fromScale);

            this.reportSuccessTimer = window.setTimeout(() => {
                this.closeReportSuccessModal();
            }, remaining);
        },

        pauseReportSuccessCountdown() {
            if (!this.reportSuccessOverlay || this.reportSuccessPaused) {
                return;
            }

            this.reportSuccessPaused = true;

            if (this.reportSuccessTimer) {
                window.clearTimeout(this.reportSuccessTimer);
                this.reportSuccessTimer = null;
            }

            if (this.reportSuccessStart) {
                const elapsed = this.getTimestamp() - this.reportSuccessStart;
                this.reportSuccessRemaining = Math.max(this.reportSuccessRemaining - elapsed, 0);
            }

            if (this.reportSuccessProgressBar) {
                const scaleX = this.getScaleX(this.reportSuccessProgressBar);
                const clampedScale = Number.isFinite(scaleX) ? Math.max(Math.min(scaleX, 1), 0) : 1;
                this.reportSuccessProgressBar.style.transition = 'none';
                this.reportSuccessProgressBar.style.transform = `scaleX(${clampedScale})`;
            }
        },

        resumeReportSuccessCountdown() {
            if (!this.reportSuccessOverlay || !this.reportSuccessPaused) {
                return;
            }

            if (this.reportSuccessRemaining <= 0) {
                this.closeReportSuccessModal();
                return;
            }

            this.reportSuccessPaused = false;
            const currentScale = this.getScaleX(this.reportSuccessProgressBar);
            this.startReportSuccessCountdown(this.reportSuccessRemaining, currentScale);
        },

        teardownReportSuccessHandlers() {
            if (this.reportSuccessHandlers && this.reportSuccessHandlers.contentBox) {
                const {
                    contentBox,
                    handlePointerEnter,
                    handlePointerLeave,
                    handlePointerDown,
                } = this.reportSuccessHandlers;

                contentBox.removeEventListener('pointerenter', handlePointerEnter);
                contentBox.removeEventListener('pointerleave', handlePointerLeave);
                contentBox.removeEventListener('pointerdown', handlePointerDown);
            }

            this.reportSuccessHandlers = null;
        },

        closeReportSuccessModal() {
            if (this.reportSuccessTimer) {
                window.clearTimeout(this.reportSuccessTimer);
                this.reportSuccessTimer = null;
            }

            this.teardownReportSuccessHandlers();

            if (this.reportSuccessOverlay) {
                this.reportSuccessOverlay.remove();
                this.reportSuccessOverlay = null;
            }

            this.reportSuccessProgressBar = null;
            this.reportSuccessRemaining = this.reportSuccessDuration;
            this.reportSuccessStart = null;
            this.reportSuccessPaused = false;
        },

        submitReport(videoId, $trigger, confirmButton) {
            if (!videoId) {
                return;
            }

            const originalLabel = confirmButton ? confirmButton.innerHTML : '';
            if (confirmButton) {
                confirmButton.disabled = true;
                confirmButton.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>${this.escapeHtml(this.getText('loading', 'Loading…'))}`;
            }

            $.ajax({
                url: `${this.data.restUrl || ''}gta6mods/v1/videos/${encodeURIComponent(videoId)}/report`,
                method: 'POST',
                beforeSend: (xhr) => {
                    if (this.data.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', this.data.nonce);
                    }
                },
            })
                .done((response) => {
                    this.updateReportState(videoId, true, response && typeof response.report_count !== 'undefined' ? response.report_count : undefined);
                    this.closeReportModal();
                    this.showReportSuccessModal(response && response.message ? response.message : '');
                })
                .fail((xhr) => {
                    if (xhr && xhr.status === 409) {
                        this.updateReportState(videoId, true);
                        this.closeReportModal();
                        this.showToast('info', this.getText('reportAlreadySubmitted', 'You have already reported this video.'));
                        return;
                    }

                    const error = xhr && xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : this.getText('reportError', 'Failed to report video.');
                    this.showToast('error', error);
                })
                .always(() => {
                    if (confirmButton) {
                        confirmButton.disabled = false;
                        confirmButton.innerHTML = originalLabel || this.escapeHtml(this.getText('reportModalConfirm', 'Submit report'));
                    }
                });
        },

        updateReportState(videoId, reported, reportCount) {
            if (this.$grid && this.$grid.length) {
                const $items = this.$grid.find(`.video-gallery-item[data-video-id="${videoId}"]`);
                if ($items.length) {
                    $items.attr('data-is-reported', reported ? '1' : '0');

                    if (typeof reportCount !== 'undefined') {
                        $items.attr('data-report-count', reportCount);
                    }
                }
            }

            const $buttons = this.$document.find(`[data-video-report][data-video-id="${videoId}"]`);
            if ($buttons.length) {
                $buttons.each((index, element) => {
                    this.updateReportButtonState($(element), reported);
                });
            }
        },

        updateReportButtonState($button, reported) {
            if (!$button || !$button.length) {
                return;
            }

            const isReported = Boolean(reported);
            const labelText = this.getText(isReported ? 'reportAlready' : 'reportVideo', isReported ? 'Reported' : 'Report');
            const $label = $button.find('[data-report-label]');

            if ($label.length) {
                $label.text(labelText);
            } else {
                $button.text(labelText);
            }

            if (isReported) {
                $button.attr('data-reported', '1').attr('aria-disabled', 'true').addClass('is-disabled');
                if ($button.is('button')) {
                    $button.prop('disabled', true);
                }
            } else {
                $button.attr('data-reported', '0').attr('aria-disabled', 'false').removeClass('is-disabled');
                if ($button.is('button')) {
                    $button.prop('disabled', false);
                }
            }
        },

        featureVideo(event) {
            event.preventDefault();
            event.stopPropagation();

            if (!this.canManageVideos()) {
                this.showToast('error', this.getText('noPermission', 'You do not have permission to perform this action.'));
                return;
            }

            const $button = $(event.currentTarget);
            const videoId = $button.data('video-id');

            if (!videoId) {
                return;
            }

            if ($button.attr('data-featured') === '1') {
                this.unfeatureVideo(videoId, $button);
                return;
            }

            this.openFeatureModal(videoId, $button);
        },

        unfeatureVideo(videoId, $trigger) {
            if (!videoId) {
                return;
            }

            const $button = $trigger && $trigger.length ? $trigger : null;
            const originalHtml = $button ? $button.html() : '';

            if ($button) {
                $button.prop('disabled', true).attr('aria-disabled', 'true').addClass('is-loading');
            }

            $.ajax({
                url: `${this.data.restUrl || ''}gta6mods/v1/videos/${encodeURIComponent(videoId)}/feature`,
                method: 'DELETE',
                beforeSend: (xhr) => {
                    if (this.data.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', this.data.nonce);
                    }
                },
            })
                .done((response) => {
                    const message = response && response.message
                        ? response.message
                        : this.getText('featureRemoved', 'Video is no longer featured.');
                    this.clearFeaturedVideo(videoId);
                    this.showToast('success', message);
                })
                .fail((xhr) => {
                    const error = xhr && xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : this.getText('featureRemoveError', 'Failed to remove featured video.');
                    this.showToast('error', error);
                    if ($button) {
                        $button.html(originalHtml);
                        $button.attr('data-featured', '1');
                    }
                })
                .always(() => {
                    if ($button) {
                        $button.removeClass('is-loading');
                        $button.prop('disabled', false).attr('aria-disabled', 'false');
                        if ($button.attr('data-featured') === '1') {
                            $button.html(originalHtml);
                        }
                    }
                });
        },

        openFeatureModal(videoId, $trigger) {
            this.closeFeatureModal();
            this.closeReportModal();
            this.closeDeleteModal();

            const isAuthor = this.isCurrentUserModAuthor();
            const modalTitleKey = isAuthor ? 'featureModalTitleAuthor' : 'featureModalTitle';
            const modalTitleDefault = isAuthor ? 'Feature this video?' : 'Feature this video?';
            const modalDescriptionKey = isAuthor ? 'featureModalDescriptionAuthor' : 'featureModalDescription';
            const modalDescriptionDefault = isAuthor
                ? 'You created this mod, so you can feature one video. The featured video appears first in the gallery and is likely what visitors will watch most.'
                : 'Feature this video to move it to the front of the gallery so it is seen first by visitors.';

            const overlay = document.createElement('div');
            overlay.className = 'video-feature-modal fixed inset-0 z-[2147483647] flex items-center justify-center bg-black bg-opacity-60 p-4';
            overlay.style.zIndex = '2147483647';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'videoFeatureModalTitle');
            overlay.innerHTML = `
                <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl p-6" role="document">
                    <h3 id="videoFeatureModalTitle" class="text-xl font-semibold text-gray-900 mb-2">${this.escapeHtml(this.getText(modalTitleKey, modalTitleDefault))}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-6">${this.escapeHtml(this.getText(modalDescriptionKey, modalDescriptionDefault))}</p>
                    <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                        <button type="button" data-feature-cancel class="w-full sm:w-auto rounded-lg bg-gray-200 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-300 transition">${this.escapeHtml(this.getText('featureModalCancel', 'Cancel'))}</button>
                        <button type="button" data-feature-confirm class="w-full sm:w-auto rounded-lg bg-pink-600 px-4 py-2 font-semibold text-white hover:bg-pink-700 transition">${this.escapeHtml(this.getText('featureModalConfirm', 'Feature video'))}</button>
                    </div>
                </div>
            `;

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    this.closeFeatureModal();
                }
            });

            const confirmButton = overlay.querySelector('[data-feature-confirm]');
            const cancelButton = overlay.querySelector('[data-feature-cancel]');

            if (cancelButton) {
                cancelButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.closeFeatureModal();
                });
            }

            if (confirmButton) {
                confirmButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.submitFeature(videoId, $trigger, confirmButton);
                });
            }

            document.body.appendChild(overlay);
            this.activeFeatureModal = overlay;

            window.requestAnimationFrame(() => {
                if (confirmButton) {
                    confirmButton.focus();
                }
            });
        },

        closeFeatureModal() {
            if (this.activeFeatureModal) {
                this.activeFeatureModal.remove();
                this.activeFeatureModal = null;
            }
        },

        submitFeature(videoId, $trigger, confirmButton) {
            if (!videoId) {
                return;
            }

            const $button = $trigger && $trigger.length ? $trigger : null;
            const originalHtml = $button ? $button.html() : '';
            const originalConfirmLabel = confirmButton ? confirmButton.innerHTML : '';

            if ($button) {
                $button.prop('disabled', true).attr('aria-disabled', 'true').addClass('is-loading');
                $button.html(`<i class="fa-solid fa-spinner fa-spin mr-2"></i>${this.escapeHtml(this.getText('loading', 'Loading…'))}`);
            }

            if (confirmButton) {
                confirmButton.disabled = true;
                confirmButton.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>${this.escapeHtml(this.getText('loading', 'Loading…'))}`;
            }

            $.ajax({
                url: `${this.data.restUrl || ''}gta6mods/v1/videos/${encodeURIComponent(videoId)}/feature`,
                method: 'POST',
                beforeSend: (xhr) => {
                    if (this.data.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', this.data.nonce);
                    }
                },
            })
                .done((response) => {
                    const message = response && response.message
                        ? response.message
                        : this.getText('featureSuccess', 'Featured video updated.');
                    this.updateFeaturedVideo(videoId);
                    this.closeFeatureModal();
                    this.showToast('success', message);
                })
                .fail((xhr) => {
                    const error = xhr && xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : this.getText('featureError', 'Failed to feature video.');
                    this.showToast('error', error);
                })
                .always(() => {
                    if ($button) {
                        $button.removeClass('is-loading');
                        $button.prop('disabled', false).attr('aria-disabled', 'false');
                        if ($button.attr('data-featured') !== '1') {
                            $button.html(originalHtml);
                        }
                    }

                    if (confirmButton && confirmButton.isConnected) {
                        confirmButton.disabled = false;
                        confirmButton.innerHTML = originalConfirmLabel || this.escapeHtml(this.getText('featureModalConfirm', 'Feature video'));
                    }
                });
        },

        updateFeaturedVideo(videoId) {
            if (!this.$grid || !this.$grid.length) {
                return;
            }

            const selector = `.video-gallery-item[data-video-id="${videoId}"]`;
            const $target = this.$grid.find(selector).first();
            const targetElement = $target.length ? $target.get(0) : null;

            this.$grid.find('.video-gallery-item').each((index, element) => {
                const $element = $(element);
                const isTarget = String($element.attr('data-video-id')) === String(videoId);
                $element.attr('data-is-featured', isTarget ? '1' : '0');
                if (isTarget) {
                    $element.removeClass('hidden').removeAttr('data-mobile-hidden');
                }
            });

            if ($target.length) {
                const $placeholder = this.$grid.find('[data-video-load-more]');
                const $promotedImage = this.$grid.find('[data-gallery-promoted="true"]').first();

                $target.detach();

                if ($promotedImage.length) {
                    $promotedImage.after($target);
                } else {
                    this.$grid.prepend($target);
                }

                if ($placeholder.length) {
                    this.$grid.append($placeholder);
                }
            }

            const $featureButtons = this.$document.find('[data-video-feature]');
            $featureButtons.each((index, element) => {
                const $btn = $(element);
                const isTarget = String($btn.data('video-id')) === String(videoId);
                const $label = $btn.find('[data-feature-label]');

                if (isTarget) {
                    $btn.attr('data-featured', '1').attr('aria-pressed', 'true').attr('aria-disabled', 'false').addClass('is-active');
                    $btn.prop('disabled', false);
                    if ($label.length) {
                        $label.text(this.getText('featureActive', 'Featured'));
                    } else {
                        $btn.text(this.getText('featureActive', 'Featured'));
                    }
                } else {
                    $btn.attr('data-featured', '0').attr('aria-pressed', 'false').attr('aria-disabled', 'false').removeClass('is-active');
                    $btn.prop('disabled', false);
                    if ($label.length) {
                        $label.text(this.getText('featureVideo', 'Feature this video'));
                    } else {
                        $btn.text(this.getText('featureVideo', 'Feature this video'));
                    }
                }
            });

            this.promoteFeaturedVideo(targetElement);
            this.dispatchFeaturedChange('video', { videoId });

            window.requestAnimationFrame(() => {
                this.applyResponsiveLayout();
                this.notifyGalleryUpdated();
            });
        },

        clearFeaturedVideo(videoId) {
            if (this.$grid && this.$grid.length) {
                const selector = `.video-gallery-item[data-video-id="${videoId}"]`;
                const $target = this.$grid.find(selector).first();
                if ($target.length) {
                    $target.attr('data-is-featured', '0');
                }
            }

            const $featureButtons = this.$document.find('[data-video-feature]');
            $featureButtons.each((index, element) => {
                const $btn = $(element);
                const $label = $btn.find('[data-feature-label]');
                $btn.attr('data-featured', '0').attr('aria-pressed', 'false').attr('aria-disabled', 'false').removeClass('is-active');
                $btn.prop('disabled', false);
                if ($label.length) {
                    $label.text(this.getText('featureVideo', 'Feature this video'));
                } else {
                    $btn.text(this.getText('featureVideo', 'Feature this video'));
                }
            });

            this.restoreFeaturedImage();
            this.dispatchFeaturedChange('image', { videoId });

            window.requestAnimationFrame(() => {
                this.applyResponsiveLayout();
                this.notifyGalleryUpdated();
            });
        },

        removeVideoFromGallery(videoId) {
            if (!this.$grid || !this.$grid.length) {
                return;
            }

            const selector = `.video-gallery-item[data-video-id="${videoId}"]`;
            const $items = this.$grid.find(selector);

            if ($items.length) {
                $items.remove();
                this.$document.find(`[data-video-feature][data-video-id="${videoId}"]`).remove();
                this.$document.find(`[data-video-report][data-video-id="${videoId}"]`).remove();
                this.$document.find(`[data-video-delete][data-video-id="${videoId}"]`).remove();

                window.requestAnimationFrame(() => {
                    this.applyResponsiveLayout();
                    this.notifyGalleryUpdated();
                });
            }
        },

        getFeaturedWrapper() {
            return document.querySelector('[data-gallery-featured-wrapper]');
        },

        getDefaultImagePayload() {
            const wrapper = this.getFeaturedWrapper();
            if (!wrapper) {
                return null;
            }

            const raw = wrapper.getAttribute('data-gallery-featured-default-image');
            if (!raw) {
                return null;
            }

            try {
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : null;
            } catch (error) {
                return null;
            }
        },

        ensureDefaultImageThumbnail(wrapper) {
            const payload = this.getDefaultImagePayload();
            if (!payload) {
                return;
            }

            const grid = document.getElementById('single-gallery-thumbnails');
            if (!grid) {
                return;
            }

            const existingPromoted = grid.querySelector('[data-gallery-promoted="true"]');
            if (existingPromoted) {
                existingPromoted.classList.remove('hidden');
                existingPromoted.removeAttribute('data-gallery-hidden');
                return;
            }

            const identifier = payload.identifier ? String(payload.identifier) : '';
            if (identifier) {
                const hasIdentifier = Array.from(grid.querySelectorAll('a.gallery-item')).some((link) => {
                    return (link.getAttribute('data-gallery-identifier') || '') === identifier;
                });
                if (hasIdentifier) {
                    return;
                }
            }

            const link = document.createElement('a');
            link.className = 'gallery-item relative aspect-video block';
            link.href = payload.src || '#';
            link.setAttribute('data-gallery-role', 'thumbnail');
            link.setAttribute('data-gallery-type', 'image');
            link.setAttribute('data-pswp-width', String(payload.width || 1920));
            link.setAttribute('data-pswp-height', String(payload.height || 1080));
            if (payload.sequence) {
                link.setAttribute('data-gallery-sequence', String(payload.sequence));
            }
            if (identifier) {
                link.setAttribute('data-gallery-identifier', identifier);
            }
            if (payload.thumbnail_small) {
                link.setAttribute('data-thumbnail-small', payload.thumbnail_small);
            }
            if (payload.thumbnail_small_width) {
                link.setAttribute('data-thumbnail-small-width', String(payload.thumbnail_small_width));
            }
            if (payload.thumbnail_small_height) {
                link.setAttribute('data-thumbnail-small-height', String(payload.thumbnail_small_height));
            }
            if (payload.thumbnail_large) {
                link.setAttribute('data-thumbnail-large', payload.thumbnail_large);
            }
            if (payload.thumbnail_large_width) {
                link.setAttribute('data-thumbnail-large-width', String(payload.thumbnail_large_width));
            }
            if (payload.thumbnail_large_height) {
                link.setAttribute('data-thumbnail-large-height', String(payload.thumbnail_large_height));
            }
            link.setAttribute('aria-haspopup', 'dialog');
            link.setAttribute('aria-expanded', 'false');
            if (payload.title) {
                link.setAttribute('title', payload.title);
            }
            if (payload.aria) {
                link.setAttribute('aria-label', payload.aria);
            } else if (payload.title) {
                link.setAttribute('aria-label', payload.title);
            }
            link.setAttribute('data-gallery-promoted', 'true');

            const img = document.createElement('img');
            const defaultThumbnail = payload.thumbnail_small || payload.thumbnail || payload.src || '';
            img.src = defaultThumbnail;
            img.alt = payload.alt || '';
            if (payload.title) {
                img.title = payload.title;
            }
            img.className = 'w-full h-full object-cover rounded-md sm:rounded-lg';
            img.width = payload.thumbnail_small_width || payload.width || 1920;
            img.height = payload.thumbnail_small_height || payload.height || 1080;
            img.loading = 'lazy';
            img.decoding = 'async';
            img.setAttribute('fetchpriority', 'low');

            const sizes = wrapper ? wrapper.getAttribute('data-gallery-thumbnail-sizes') : '';
            if (sizes) {
                img.setAttribute('sizes', sizes);
            }

            link.appendChild(img);

            if (grid.firstChild) {
                grid.insertBefore(link, grid.firstChild);
            } else {
                grid.appendChild(link);
            }
        },

        buildFeaturedVideoHtml(element, wrapper) {
            if (!element) {
                return '';
            }

            const dataset = element.dataset || {};
            const width = element.getAttribute('data-pswp-width') || dataset.pswpWidth || '1920';
            const height = element.getAttribute('data-pswp-height') || dataset.pswpHeight || '1080';
            const sequence = element.getAttribute('data-gallery-sequence') || dataset.gallerySequence || '';
            const identifier = element.getAttribute('data-gallery-identifier') || dataset.galleryIdentifier || '';
            const youtubeId = dataset.youtubeId || element.getAttribute('data-youtube-id') || '';
            const addedBy = dataset.addedBy || element.getAttribute('data-added-by') || '';
            const profileUrl = dataset.profileUrl || element.getAttribute('data-profile-url') || '';
            const videoId = dataset.videoId || element.getAttribute('data-video-id') || '';
            const videoTitle = dataset.videoTitle || element.getAttribute('data-video-title') || '';
            const videoStatus = dataset.videoStatus || element.getAttribute('data-video-status') || '';
            const isReported = dataset.isReported === '1' || element.getAttribute('data-is-reported') === '1';
            const isFeatured = dataset.isFeatured === '1' || element.getAttribute('data-is-featured') === '1';
            const canManage = dataset.canManage === '1' || element.getAttribute('data-can-manage') === '1';
            const canFeature = dataset.canFeature === '1' || element.getAttribute('data-can-feature') === '1';
            const reportCount = dataset.reportCount || element.getAttribute('data-report-count') || '0';
            const ariaLabel = element.getAttribute('aria-label') || videoTitle;
            const thumbnail = element.querySelector('img');
            const datasetThumbLarge = dataset.thumbnailLarge || element.getAttribute('data-thumbnail-large') || '';
            const datasetThumbSmall = dataset.thumbnailSmall || element.getAttribute('data-thumbnail-small') || '';
            const datasetThumbLargeWidth = dataset.thumbnailLargeWidth || element.getAttribute('data-thumbnail-large-width') || '';
            const datasetThumbLargeHeight = dataset.thumbnailLargeHeight || element.getAttribute('data-thumbnail-large-height') || '';
            const datasetThumbSmallWidth = dataset.thumbnailSmallWidth || element.getAttribute('data-thumbnail-small-width') || '';
            const datasetThumbSmallHeight = dataset.thumbnailSmallHeight || element.getAttribute('data-thumbnail-small-height') || '';
            const imageSrc = thumbnail ? thumbnail.getAttribute('src') : '';
            const imageAlt = thumbnail ? thumbnail.getAttribute('alt') || '' : '';
            const imageTitle = thumbnail ? thumbnail.getAttribute('title') || videoTitle : videoTitle;
            const featuredSizes = wrapper ? wrapper.getAttribute('data-gallery-featured-sizes') || '' : '';

            const toPositiveInt = (value, fallback) => {
                const parsed = parseInt(value, 10);
                return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
            };

            const fallbackWidth = toPositiveInt(width, 1920);
            const fallbackHeight = toPositiveInt(height, 1080);
            const previewSource = datasetThumbLarge || imageSrc || datasetThumbSmall || '';
            const previewWidth = toPositiveInt(datasetThumbLargeWidth || datasetThumbSmallWidth, fallbackWidth);
            const previewHeight = toPositiveInt(datasetThumbLargeHeight || datasetThumbSmallHeight, fallbackHeight);
            
            const fallbackTitle = videoTitle || this.getText('videoPlaceholder', 'this video');
            const template = this.getText('playFeaturedVideo', 'Play featured video: %s');
            let playLabel = template;

            if (template.includes('%s')) {
                playLabel = template.replace('%s', fallbackTitle);
            } else if (template.includes('{{title}}')) {
                playLabel = template.replace('{{title}}', fallbackTitle);
            } else {
                playLabel = `${template} ${fallbackTitle}`;
            }

            const escapedPlayLabel = this.escapeHtml(playLabel);
            const escapedVideoTitle = this.escapeHtml(videoTitle);
            const escapedYoutubeId = this.escapeHtml(youtubeId);
            const escapedVideoId = this.escapeHtml(videoId);
            const escapedAddedBy = this.escapeHtml(addedBy);
            const escapedProfileUrl = this.escapeHtml(profileUrl);
            const escapedReportCount = this.escapeHtml(reportCount);
            const escapedWidth = this.escapeHtml(width);
            const escapedHeight = this.escapeHtml(height);
            const escapedSequence = this.escapeHtml(sequence);
            const escapedIdentifier = this.escapeHtml(identifier);
            const escapedImageAlt = this.escapeHtml(imageAlt);
            const escapedImageTitle = this.escapeHtml(imageTitle);
            const escapedAria = this.escapeHtml(ariaLabel);
            const featuredSizesAttr = featuredSizes ? `sizes="${this.escapeHtml(featuredSizes)}"` : '';
            const anchorHref = element.getAttribute('href') || '';
            const hiddenImageSrc = anchorHref && anchorHref !== '#' ? anchorHref : (imageSrc || '');
            const escapedHiddenImageSrc = this.escapeHtml(hiddenImageSrc);
            const previewSourceFinal = previewSource || (imageSrc || hiddenImageSrc || '');
            const escapedPreviewSrc = this.escapeHtml(previewSourceFinal || '');
            const previewSrcsetParts = [];

            if (previewSourceFinal) {
                previewSrcsetParts.push(`${previewSourceFinal} ${previewWidth}w`);
            }

            if (hiddenImageSrc && hiddenImageSrc !== previewSourceFinal) {
                previewSrcsetParts.push(`${hiddenImageSrc} ${fallbackWidth}w`);
            }

            const previewSrcsetAttr = previewSrcsetParts.length ? `srcset="${this.escapeHtml(previewSrcsetParts.join(', '))}"` : '';
            const escapedPreviewWidth = this.escapeHtml(String(previewWidth));
            const escapedPreviewHeight = this.escapeHtml(String(previewHeight));
            const thumbSmallWidth = toPositiveInt(datasetThumbSmallWidth, previewWidth);
            const thumbSmallHeight = toPositiveInt(datasetThumbSmallHeight, previewHeight);
            const thumbLargeWidth = toPositiveInt(datasetThumbLargeWidth, previewWidth);
            const thumbLargeHeight = toPositiveInt(datasetThumbLargeHeight, previewHeight);
            const escapedThumbSmall = this.escapeHtml(datasetThumbSmall || previewSourceFinal || '');
            const escapedThumbLarge = this.escapeHtml(datasetThumbLarge || previewSourceFinal || '');
            const escapedThumbSmallWidth = this.escapeHtml(String(thumbSmallWidth));
            const escapedThumbSmallHeight = this.escapeHtml(String(thumbSmallHeight));
            const escapedThumbLargeWidth = this.escapeHtml(String(thumbLargeWidth));
            const escapedThumbLargeHeight = this.escapeHtml(String(thumbLargeHeight));

            return `
            <div class="relative h-full w-full" data-featured-video-preview>
                <div class="relative h-full w-full" data-featured-video-stage>
                    <button
                        type="button"
                        class="group relative block h-full w-full overflow-hidden rounded-md sm:rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-pink-500"
                        data-featured-video-trigger
                        data-youtube-id="${escapedYoutubeId}"
                        data-video-id="${escapedVideoId}"
                        data-video-title="${escapedVideoTitle}"
                        title="${escapedVideoTitle}"
                        aria-label="${escapedPlayLabel}"
                    >
                        <span class="sr-only">${escapedPlayLabel}</span>
                        <img
                            src="${escapedPreviewSrc}"
                            alt="${escapedImageAlt}"
                            title="${escapedImageTitle}"
                            class="h-full w-full object-cover"
                            width="${escapedPreviewWidth}"
                            height="${escapedPreviewHeight}"
                            loading="eager"
                            decoding="async"
                            fetchpriority="high"
                            ${featuredSizesAttr}
                            ${previewSrcsetAttr}
                        >
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/40 transition group-hover:bg-black/60" data-featured-video-overlay>
                            <div class="play-button" aria-hidden="true">
                                <div class="play-button-icon"></div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
            <a href="#"
                class="gallery-item video-gallery-item hidden"
                data-gallery-featured-link
                data-gallery-index="0"
                data-gallery-role="featured"
                data-gallery-type="video"
                data-pswp-width="${escapedWidth}"
                data-pswp-height="${escapedHeight}"
                ${sequence ? `data-gallery-sequence="${escapedSequence}"` : ''}
                ${identifier ? `data-gallery-identifier="${escapedIdentifier}"` : ''}
                ${escapedThumbSmall ? `data-thumbnail-small="${escapedThumbSmall}"` : ''}
                ${escapedThumbSmallWidth ? `data-thumbnail-small-width="${escapedThumbSmallWidth}"` : ''}
                ${escapedThumbSmallHeight ? `data-thumbnail-small-height="${escapedThumbSmallHeight}"` : ''}
                ${escapedThumbLarge ? `data-thumbnail-large="${escapedThumbLarge}"` : ''}
                ${escapedThumbLargeWidth ? `data-thumbnail-large-width="${escapedThumbLargeWidth}"` : ''}
                ${escapedThumbLargeHeight ? `data-thumbnail-large-height="${escapedThumbLargeHeight}"` : ''}
                data-youtube-id="${escapedYoutubeId}"
                data-added-by="${escapedAddedBy}"
                data-profile-url="${escapedProfileUrl}"
                data-video-id="${escapedVideoId}"
                data-video-title="${escapedVideoTitle}"
                data-video-status="${this.escapeHtml(videoStatus)}"
                data-is-reported="${isReported ? '1' : '0'}"
                data-is-featured="${isFeatured ? '1' : '0'}"
                data-can-manage="${canManage ? '1' : '0'}"
                data-can-feature="${canFeature ? '1' : '0'}"
                data-report-count="${escapedReportCount}"
                title="${escapedVideoTitle}"
                aria-label="${escapedAria}"
                aria-haspopup="dialog"
                aria-expanded="false"
                tabindex="-1"
                hidden
            >
                <img
                    src="${escapedHiddenImageSrc}"
                    alt="${escapedImageAlt}"
                    title="${escapedImageTitle}"
                    class="hidden"
                    width="${escapedWidth}"
                    height="${escapedHeight}"
                    loading="lazy"
                    decoding="async"
                    ${featuredSizesAttr}
                >
            </a>
            `;
        },

        buildFeaturedImageHtml(payload, wrapper) {
            if (!payload) {
                return '';
            }

            const width = payload.width || 1920;
            const height = payload.height || 1080;
            const sizes = wrapper ? wrapper.getAttribute('data-gallery-featured-sizes') || '' : '';
            const sequence = payload.sequence ? String(payload.sequence) : '';
            const identifier = payload.identifier ? String(payload.identifier) : '';
            const title = payload.title || '';
            const aria = payload.aria || title;
            const alt = payload.alt || '';
            const src = payload.src || '#';

            return `
            <a
                href="${this.escapeHtml(src)}"
                class="gallery-item block w-full h-full"
                data-gallery-featured-link
                data-gallery-index="0"
                data-gallery-role="featured"
                data-gallery-type="image"
                data-pswp-width="${this.escapeHtml(String(width))}"
                data-pswp-height="${this.escapeHtml(String(height))}"
                ${sequence ? `data-gallery-sequence="${this.escapeHtml(sequence)}"` : ''}
                ${identifier ? `data-gallery-identifier="${this.escapeHtml(identifier)}"` : ''}
                title="${this.escapeHtml(title)}"
                aria-label="${this.escapeHtml(aria)}"
                aria-haspopup="dialog"
                aria-expanded="false"
            >
                <img
                    src="${this.escapeHtml(src)}"
                    alt="${this.escapeHtml(alt)}"
                    title="${this.escapeHtml(title)}"
                    class="w-full h-full object-cover"
                    width="${this.escapeHtml(String(width))}"
                    height="${this.escapeHtml(String(height))}"
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                    ${sizes ? `sizes="${this.escapeHtml(sizes)}"` : ''}
                    data-gallery-featured-image
                >
            </a>
            `;
        },

        promoteFeaturedVideo(targetElement) {
            const wrapper = this.getFeaturedWrapper();
            if (!wrapper) {
                return;
            }

            if (!targetElement) {
                this.restoreFeaturedImage();
                return;
            }

            this.ensureDefaultImageThumbnail(wrapper);

            const html = this.buildFeaturedVideoHtml(targetElement, wrapper);
            if (html) {
                wrapper.innerHTML = html;
                wrapper.setAttribute('data-gallery-featured-type', 'video');
                wrapper.removeAttribute('data-gallery-featured-playing');
                const videoId = targetElement.getAttribute('data-video-id') || '';
                if (videoId) {
                    wrapper.setAttribute('data-gallery-featured-video-id', videoId);
                } else {
                    wrapper.removeAttribute('data-gallery-featured-video-id');
                }
            }
        },

        restoreFeaturedImage() {
            const wrapper = this.getFeaturedWrapper();
            if (!wrapper) {
                return;
            }

            const payload = this.getDefaultImagePayload();
            if (!payload) {
                return;
            }

            const html = this.buildFeaturedImageHtml(payload, wrapper);
            if (html) {
                wrapper.innerHTML = html;
                wrapper.setAttribute('data-gallery-featured-type', 'image');
                wrapper.removeAttribute('data-gallery-featured-playing');
                wrapper.removeAttribute('data-gallery-featured-video-id');
            }

            const grid = document.getElementById('single-gallery-thumbnails');
            if (grid) {
                const promoted = grid.querySelector('[data-gallery-promoted="true"]');
                if (promoted && promoted.parentNode) {
                    promoted.parentNode.removeChild(promoted);
                }
            }
        },

        showToast(type, message) {
            if (!message) {
                return;
            }

            this.ensureToastContainer();

            if (!this.toastContainer) {
                return;
            }

            const palette = this.getToastClasses(type);
            const toast = document.createElement('div');
            toast.className = `video-toast pointer-events-auto flex items-center gap-3 rounded-xl border-l-4 px-4 py-3 shadow-xl max-w-[400px] ${palette.container}`;
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

            const iconWrapper = document.createElement('span');
            iconWrapper.className = `text-lg ${palette.icon}`;
            iconWrapper.innerHTML = `<i class="fa-solid ${palette.iconClass}" aria-hidden="true"></i>`;

            const text = document.createElement('div');
            text.className = 'flex-1 text-sm font-medium';
            text.textContent = message;

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'ml-auto text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 focus:outline-none';
            closeButton.textContent = this.getText('toastClose', 'Dismiss');

            const removeToast = () => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            };

            closeButton.addEventListener('click', (event) => {
                event.preventDefault();
                window.clearTimeout(timeoutId);
                removeToast();
            });

            toast.appendChild(iconWrapper);
            toast.appendChild(text);
            toast.appendChild(closeButton);

            this.toastContainer.appendChild(toast);

            const timeoutId = window.setTimeout(() => {
                removeToast();
            }, 5000);
        },

        ensureToastContainer() {
            if (this.toastContainer) {
                return;
            }

            const container = document.createElement('div');
            container.className = 'video-toast-container pointer-events-none fixed top-5 right-5 z-[2147483646] flex flex-col items-end gap-3';
            container.style.zIndex = '2147483646';
            document.body.appendChild(container);
            this.toastContainer = container;
        },

        getToastClasses(type) {
            switch (type) {
                case 'error':
                    return {
                        container: 'bg-red-50 text-red-800 border-red-500',
                        icon: 'text-red-500',
                        iconClass: 'fa-triangle-exclamation',
                    };
                case 'info':
                    return {
                        container: 'bg-blue-50 text-blue-800 border-blue-500',
                        icon: 'text-blue-500',
                        iconClass: 'fa-circle-info',
                    };
                default:
                    return {
                        container: 'bg-green-50 text-green-800 border-green-500',
                        icon: 'text-green-500',
                        iconClass: 'fa-circle-check',
                    };
            }
        },

        showLoginModal(options = {}) {
            if (document.querySelector('.gta-mods-modal')) {
                return;
            }

            const title = options.title || this.getText('loginModalTitle', 'Login Required');
            const message = options.message || this.getText('loginModalMessage', 'You must be logged in to continue.');
            const buttonLabel = options.buttonLabel || this.getText('loginButton', 'Log in');
            const closeLabel = this.getText('close', 'Close');
            const loginUrl = options.loginUrl || this.data.loginUrl || '/login';

            const overlay = document.createElement('div');
            overlay.className = 'gta-mods-modal fixed inset-0 z-[2147483647] flex items-center justify-center bg-black bg-opacity-60 p-4';
            overlay.style.zIndex = '2147483647';
            overlay.innerHTML = `
                <div class="bg-white w-full max-w-sm rounded-xl p-6 text-center shadow-2xl">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">${this.escapeHtml(title)}</h3>
                    <p class="text-gray-600 mb-6">${this.escapeHtml(message)}</p>
                    <a href="${this.escapeHtml(loginUrl)}" class="mb-3 block w-full rounded-lg bg-pink-600 py-3 font-semibold text-white hover:bg-pink-700 transition">${this.escapeHtml(buttonLabel)}</a>
                    <button type="button" class="gta-mods-modal-close w-full rounded-lg bg-gray-200 py-3 font-semibold text-gray-700 hover:bg-gray-300 transition">${this.escapeHtml(closeLabel)}</button>
                </div>
            `;

            const closeModal = () => {
                overlay.remove();
            };

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    closeModal();
                }
            });

            overlay.querySelector('.gta-mods-modal-close').addEventListener('click', (event) => {
                event.preventDefault();
                closeModal();
            });

            document.body.appendChild(overlay);
        },

        getText(key, fallback) {
            if (this.data && this.data.i18n && Object.prototype.hasOwnProperty.call(this.data.i18n, key)) {
                const value = this.data.i18n[key];
                if (typeof value === 'string' && value.length) {
                    return value;
                }
            }

            return fallback;
        },

        escapeHtml(value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }

            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '`': '&#96;',
            };

            return String(value).replace(/[&<>'"`]/g, (character) => map[character] || character);
        },

        notifyGalleryUpdated() {
            if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
                document.dispatchEvent(new CustomEvent('gta6mods:gallery:updated'));
            }
        },

        dispatchFeaturedChange(type, detail = {}) {
            if (typeof document === 'undefined' || typeof document.dispatchEvent !== 'function') {
                return;
            }

            const payload = {
                type,
                videoId: detail.videoId || null,
            };

            document.dispatchEvent(new CustomEvent('gta6mods:gallery:featured-change', {
                detail: payload,
            }));
        },
    };

    $(document).ready(() => {
        VideoGallery.init();
        if (typeof window !== 'undefined') {
            window.gta6modsVideoGallery = VideoGallery;
        }
    });
})(jQuery);
