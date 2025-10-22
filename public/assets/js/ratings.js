(function () {
    'use strict';

    if (typeof window.GTAModsRatings === 'undefined') {
        return;
    }

    const settings = window.GTAModsRatings;

    function showLoginModal(message) {
        if (document.querySelector('.gta-mods-modal')) {
            return;
        }

        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'gta-mods-modal fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50';

        const modalContent = `
            <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm text-center relative animate-fade-in-up">
                <h3 class="text-xl font-bold text-gray-800 mb-2">Login Required</h3>
                <p class="text-gray-500 mb-6">${message}</p>
                <a href="/login" class="block w-full mb-2 p-3 rounded-lg font-semibold text-white bg-pink-600 hover:bg-pink-700 transition">Login</a>
                <button type="button" class="gta-mods-modal-close w-full mt-2 p-3 rounded-lg font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 transition">
                    Close
                </button>
            </div>
        `;

        modalOverlay.innerHTML = modalContent;
        document.body.appendChild(modalOverlay);

        const closeModal = () => {
            modalOverlay.remove();
        };

        modalOverlay.querySelector('.gta-mods-modal-close').addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', (event) => {
            if (event.target === modalOverlay) {
                closeModal();
            }
        });
    }

    function handleRating(postId, rating, container) {
        if (!settings.restUrl || !settings.restNonce) {
            console.error('REST settings for ratings are not defined.');
            return;
        }

        fetch(settings.restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': settings.restNonce,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ rating: rating }),
        })
            .then((response) => {
                if (response.status === 401 || response.status === 403) {
                    return response.json().then((err) => {
                        throw new Error(err.message || 'You must be logged in to rate.');
                    });
                }

                if (!response.ok) {
                    return response.json().then((err) => {
                        throw new Error(err.message || 'An error occurred.');
                    });
                }

                return response.json();
            })
            .then((data) => {
                updateRatingDisplay(container, data);
            })
            .catch((error) => {
                console.error('Error:', error);
                showLoginModal(error.message);
            });
    }

    function getContainerUserRating(container) {
        return Number.parseInt(container && container.dataset && container.dataset.userRating, 10) || 0;
    }

    function setContainerUserRating(container, rating) {
        if (!container) {
            return;
        }

        const normalized = Number.parseInt(rating, 10);
        container.dataset.userRating = Number.isFinite(normalized) ? String(normalized) : '0';
    }

    function parseAverageRating(container) {
        if (!container) {
            return 0;
        }

        const datasetValue = container.dataset && container.dataset.averageRating;
        if (datasetValue) {
            const parsedDataset = Number.parseFloat(datasetValue);
            if (Number.isFinite(parsedDataset)) {
                return parsedDataset;
            }
        }

        const avgEl = container.querySelector('.rating-average');
        if (avgEl) {
            const normalizedText = avgEl.textContent.replace(',', '.');
            const parsedContent = Number.parseFloat(normalizedText);
            if (Number.isFinite(parsedContent)) {
                return parsedContent;
            }
        }

        return 0;
    }

    function ensureStarOverlay(star) {
        if (!star) {
            return null;
        }

        const baseIcon = star.querySelector('i');
        if (!baseIcon) {
            return null;
        }

        baseIcon.classList.add('fa-star');
        baseIcon.classList.remove('fa-star-half-stroke');

        let overlay = star.querySelector('.rating-star-overlay');
        if (!overlay) {
            overlay = document.createElement('span');
            overlay.className = 'rating-star-overlay';
            overlay.setAttribute('aria-hidden', 'true');

            const overlayIcon = baseIcon.cloneNode(true);
            overlayIcon.classList.add('rating-star-overlay-icon');
            overlayIcon.classList.remove('text-gray-300', 'text-yellow-300', 'text-yellow-400', 'text-yellow-500', 'hover:text-yellow-300');
            overlayIcon.setAttribute('aria-hidden', 'true');
            overlayIcon.setAttribute('role', 'presentation');

            overlay.appendChild(overlayIcon);
            star.classList.add('rating-star-with-overlay');
            star.appendChild(overlay);
        }

        const overlayIcon = overlay.querySelector('i');
        if (overlayIcon) {
            overlayIcon.classList.add('fa-star');
            overlayIcon.classList.remove('fa-star-half-stroke');
        }

        if (!overlay.style.width) {
            overlay.style.width = '0%';
        }

        return { baseIcon, overlay, overlayIcon };
    }

    function getStarOverlay(star) {
        const parts = ensureStarOverlay(star);
        return parts ? parts.overlay : null;
    }

    function setOverlayFill(star, fill) {
        const normalized = Math.min(Math.max(Number.isFinite(fill) ? fill : 0, 0), 1);
        const parts = ensureStarOverlay(star);
        if (!parts) {
            return null;
        }

        parts.overlay.style.width = `${(normalized * 100).toFixed(2)}%`;
        parts.overlay.dataset.fill = normalized.toFixed(3);

        return parts.overlay;
    }

    function applyAverageFill(star, fill, persist = true) {
        const normalized = Math.min(Math.max(Number.isFinite(fill) ? fill : 0, 0), 1);
        const overlay = setOverlayFill(star, normalized);

        star.dataset.mode = 'average';
        star.classList.remove('hovered', 'active', 'text-yellow-400', 'text-yellow-500');
        star.classList.add('text-gray-300');
        star.classList.toggle('average-active', normalized > 0);

        if (overlay) {
            overlay.classList.remove('is-muted', 'is-hidden');
        }

        if (persist) {
            star.dataset.averageFill = normalized.toFixed(3);
        }
    }

    function restoreAverageFill(star) {
        const stored = Number.parseFloat(star.dataset && star.dataset.averageFill);
        if (Number.isFinite(stored)) {
            applyAverageFill(star, stored, false);
        } else {
            applyAverageFill(star, 0, false);
        }
    }

    function applyAverageRatingState(container) {
        if (!container) {
            return;
        }

        const average = parseAverageRating(container);
        if (!Number.isFinite(average)) {
            return;
        }

        container.dataset.averageRating = String(average);

        const normalizedAverage = Math.min(Math.max(Number.isFinite(average) ? average : 0, 0), 5);
        const stars = container.querySelectorAll('.rating-star');

        stars.forEach((star) => {
            const starValue = Number.parseInt(star.dataset.rating, 10) || 0;
            const fill = Math.min(Math.max(normalizedAverage - (starValue - 1), 0), 1);

            applyAverageFill(star, fill, true);
            star.classList.toggle('average-active', fill > 0);
        });
    }

    function applyUserRatingState(container, rating) {
        if (!container) {
            return;
        }

        const stars = container.querySelectorAll('.rating-star');
        const normalizedRating = Number.parseInt(rating, 10);
        const hasUserRating = Number.isFinite(normalizedRating) && normalizedRating > 0;

        container.classList.toggle('has-user-rating', hasUserRating);

        if (!hasUserRating) {
            stars.forEach((star) => {
                star.classList.remove('active');
                restoreAverageFill(star);
            });
            return;
        }

        stars.forEach((star) => {
            const starValue = Number.parseInt(star.dataset.rating, 10) || 0;
            const icon = star.querySelector('i');
            const isActive = starValue <= normalizedRating;
            const storedFill = Number.parseFloat(star.dataset.averageFill);
            const overlay = setOverlayFill(star, Number.isFinite(storedFill) ? storedFill : 0);

            star.classList.toggle('active', isActive);
            star.classList.remove('hovered');
            star.dataset.mode = 'user';
            star.classList.remove('average-active');
            star.classList.remove('text-yellow-500');

            if (overlay) {
                overlay.classList.add('is-muted');
                overlay.classList.remove('is-hidden');
            }

            if (isActive) {
                star.classList.add('text-yellow-400');
                star.classList.remove('text-gray-300');
            } else {
                star.classList.add('text-gray-300');
                star.classList.remove('text-yellow-400');
            }

            if (icon) {
                icon.classList.add('fa-star');
                icon.classList.remove('fa-star-half-stroke');
            }
        });
    }

    function applyHoverState(container, hoverRating) {
        if (!container) {
            return;
        }

        const userRating = getContainerUserRating(container);
        const stars = container.querySelectorAll('.rating-star');
        const hoverValue = Number.parseInt(hoverRating, 10) || 0;

        stars.forEach((star) => {
            const starValue = Number.parseInt(star.dataset.rating, 10) || 0;
            const icon = star.querySelector('i');
            const shouldHighlight = hoverValue > 0 && starValue <= hoverValue;
            const isUserActive = userRating > 0 && starValue <= userRating;
            const overlay = getStarOverlay(star);

            if (shouldHighlight) {
                star.dataset.mode = 'hover';
                star.classList.add('hovered');
                star.classList.add('text-yellow-500');
                star.classList.remove('text-yellow-400');
                star.classList.remove('text-gray-300');
                star.classList.remove('average-active');

                if (overlay) {
                    overlay.classList.add('is-hidden');
                }

                if (icon) {
                    icon.classList.add('fa-star');
                    icon.classList.remove('fa-star-half-stroke');
                }
            } else {
                star.classList.remove('hovered');
                star.classList.remove('text-yellow-500');

                if (isUserActive) {
                    star.dataset.mode = 'user';
                    star.classList.add('text-yellow-400');
                    star.classList.remove('text-gray-300');
                    star.classList.remove('average-active');

                    if (overlay) {
                        overlay.classList.add('is-muted');
                        overlay.classList.remove('is-hidden');
                    }

                    if (icon) {
                        icon.classList.add('fa-star');
                        icon.classList.remove('fa-star-half-stroke');
                    }
                } else if (userRating === 0) {
                    restoreAverageFill(star);

                    if (overlay) {
                        overlay.classList.remove('is-muted', 'is-hidden');
                    }

                    if (icon) {
                        icon.classList.add('fa-star');
                        icon.classList.remove('fa-star-half-stroke');
                    }
                } else {
                    star.dataset.mode = 'user';
                    star.classList.add('text-gray-300');
                    star.classList.remove('text-yellow-400');
                    star.classList.remove('average-active');

                    if (overlay) {
                        overlay.classList.add('is-muted');
                        overlay.classList.remove('is-hidden');
                    }

                    if (icon) {
                        icon.classList.add('fa-star');
                        icon.classList.remove('fa-star-half-stroke');
                    }
                }
            }
        });
    }

    function resetHoverState(container) {
        const rating = getContainerUserRating(container);
        applyUserRatingState(container, rating);
    }

    function updateRatingDisplay(container, data) {
        if (!container) {
            return;
        }

        const avgEl = container.querySelector('.rating-average');
        const countEl = container.querySelector('.rating-count');

        const averageValue = Number.parseFloat(data && data.average);
        const countValue = Number.parseInt(data && data.count, 10);
        const userRatingValue = Number.parseInt(data && data.user_rating, 10);

        const normalizedAverage = Number.isFinite(averageValue) ? averageValue : 0;
        const normalizedCount = Number.isFinite(countValue) ? countValue : 0;
        const normalizedUserRating = Number.isFinite(userRatingValue) ? userRatingValue : 0;

        if (avgEl) {
            avgEl.textContent = normalizedAverage.toLocaleString(undefined, {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
            });
        }

        if (countEl) {
            if (!countEl.dataset.template) {
                countEl.dataset.template = countEl.textContent;
            }

            const template = countEl.dataset.template;
            if (/[0-9]/u.test(template)) {
                countEl.textContent = template.replace(/[0-9][0-9\s.,\u00A0]*/u, normalizedCount.toLocaleString());
            } else {
                countEl.textContent = normalizedCount.toLocaleString();
            }
        }

        container.dataset.averageRating = String(normalizedAverage);
        applyAverageRatingState(container);
        setContainerUserRating(container, normalizedUserRating);
        applyUserRatingState(container, normalizedUserRating);
    }

    function initializeRatingContainers() {
        const containers = document.querySelectorAll('.mod-rating-container');
        containers.forEach((container) => {
            applyAverageRatingState(container);
            const userRating = getContainerUserRating(container);
            if (userRating > 0) {
                applyUserRatingState(container, userRating);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeRatingContainers);
    } else {
        initializeRatingContainers();
    }

    document.addEventListener('click', (event) => {
        const ratingStar = event.target.closest('.rating-star');
        if (!ratingStar) {
            return;
        }

        event.preventDefault();
        const container = ratingStar.closest('.mod-rating-container');
        if (!container) {
            return;
        }

        const isLoggedIn = container.dataset.isLoggedIn === 'true';
        if (!isLoggedIn) {
            showLoginModal('You must be logged in to rate.');
            return;
        }

        const postId = container.dataset.postId;
        const rating = Number.parseInt(ratingStar.dataset.rating, 10);
        if (postId && rating) {
            setContainerUserRating(container, rating);
            applyUserRatingState(container, rating);
            handleRating(postId, rating, container);
        }
    });

    document.addEventListener('mouseover', (event) => {
        const ratingStar = event.target.closest('.rating-star');
        if (!ratingStar) {
            return;
        }

        const container = ratingStar.closest('.mod-rating-container');
        if (!container) {
            return;
        }

        const rating = Number.parseInt(ratingStar.dataset.rating, 10) || 0;
        applyHoverState(container, rating);
    });

    document.addEventListener('focusin', (event) => {
        const ratingStar = event.target.closest('.rating-star');
        if (!ratingStar) {
            return;
        }

        const container = ratingStar.closest('.mod-rating-container');
        if (!container) {
            return;
        }

        const rating = Number.parseInt(ratingStar.dataset.rating, 10) || 0;
        applyHoverState(container, rating);
    });

    document.addEventListener('mouseout', (event) => {
        const container = event.target.closest('.mod-rating-container');
        if (!container) {
            return;
        }

        if (!event.relatedTarget || !container.contains(event.relatedTarget)) {
            resetHoverState(container);
        }
    });

    document.addEventListener('focusout', (event) => {
        const container = event.target.closest('.mod-rating-container');
        if (!container) {
            return;
        }

        if (!event.relatedTarget || !container.contains(event.relatedTarget)) {
            resetHoverState(container);
        }
    });
})();
