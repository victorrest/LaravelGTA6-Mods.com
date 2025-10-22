(function () {
    'use strict';

    const utils = window.GTAModsUtils || {};
    const getCookie = (typeof utils.getCookie === 'function') ? utils.getCookie : () => null;
    const hasCookie = (typeof utils.hasCookie === 'function') ? utils.hasCookie : () => false;
    const setCookie = (typeof utils.setCookie === 'function') ? utils.setCookie : () => {};
    const buildHeaders = (nonce) => (typeof utils.buildRestHeaders === 'function'
        ? utils.buildRestHeaders(nonce)
        : (nonce ? { 'X-WP-Nonce': nonce } : {}));

    const escapeHTML = (value) => {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const formatNumber = (value) => {
        if (typeof value === 'number') {
            return value.toLocaleString();
        }
        if (typeof value === 'string') {
            return value;
        }
        return '';
    };

    document.addEventListener('DOMContentLoaded', () => {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu-panel');
        const closeMenuButton = document.getElementById('close-menu-button');
        const menuBackdrop = document.getElementById('menu-backdrop');

        const openMobileMenu = () => {
            if (!mobileMenu || !menuBackdrop) {
                return;
            }

            mobileMenu.classList.remove('translate-x-full');
            mobileMenu.classList.add('translate-x-0');
            mobileMenu.setAttribute('aria-hidden', 'false');
            menuBackdrop.classList.remove('hidden');
            menuBackdrop.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        };

        const closeMobileMenu = () => {
            if (!mobileMenu || !menuBackdrop) {
                return;
            }

            mobileMenu.classList.remove('translate-x-0');
            mobileMenu.classList.add('translate-x-full');
            mobileMenu.setAttribute('aria-hidden', 'true');
            menuBackdrop.classList.add('hidden');
            menuBackdrop.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        };

        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', (event) => {
                event.preventDefault();
                openMobileMenu();
            });
        }

        if (closeMenuButton) {
            closeMenuButton.addEventListener('click', (event) => {
                event.preventDefault();
                closeMobileMenu();
            });
        }

        if (menuBackdrop) {
            menuBackdrop.addEventListener('click', closeMobileMenu);
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMobileMenu();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                closeMobileMenu();
            }
        });

        const navSlider = document.getElementById('horizontal-nav');

        if (navSlider) {
            let isPointerDown = false;
            let startX = 0;
            let scrollLeft = 0;
            let isDragging = false;
            let velocity = 0;
            let momentumId = 0;

            const stopMomentum = () => {
                if (momentumId) {
                    cancelAnimationFrame(momentumId);
                    momentumId = 0;
                }
            };

            const momentumLoop = () => {
                navSlider.scrollLeft += velocity;
                velocity *= 0.94;

                if (Math.abs(velocity) > 0.5) {
                    momentumId = requestAnimationFrame(momentumLoop);
                } else {
                    navSlider.style.scrollSnapType = 'x mandatory';
                    momentumId = 0;
                }
            };

            const handlePointerDown = (event) => {
                if (window.innerWidth >= 768 || (event.pointerType && event.pointerType !== 'mouse')) {
                    return;
                }

                isPointerDown = true;
                isDragging = false;
                navSlider.classList.add('active');
                navSlider.style.scrollSnapType = 'none';
                startX = event.clientX;
                scrollLeft = navSlider.scrollLeft;
                velocity = 0;
                stopMomentum();

                if (typeof navSlider.setPointerCapture === 'function' && event.pointerId !== undefined) {
                    try {
                        navSlider.setPointerCapture(event.pointerId);
                    } catch (err) {
                        // ignore capture errors
                    }
                }
            };

            const handlePointerMove = (event) => {
                if (!isPointerDown || window.innerWidth >= 768) {
                    return;
                }

                event.preventDefault();
                isDragging = true;

                const x = event.clientX;
                const walk = x - startX;
                const previous = navSlider.scrollLeft;
                navSlider.scrollLeft = scrollLeft - walk;
                velocity = navSlider.scrollLeft - previous;
            };

            const handlePointerUp = (event) => {
                if (!isPointerDown) {
                    return;
                }

                isPointerDown = false;
                navSlider.classList.remove('active');

                if (isDragging) {
                    momentumLoop();
                    window.setTimeout(() => {
                        isDragging = false;
                    }, 0);
                } else {
                    navSlider.style.scrollSnapType = 'x mandatory';
                }

                if (typeof navSlider.releasePointerCapture === 'function' && event.pointerId !== undefined) {
                    try {
                        navSlider.releasePointerCapture(event.pointerId);
                    } catch (err) {
                        // ignore release errors
                    }
                }
            };

            const handlePointerCancel = (event) => {
                handlePointerUp(event);
            };

            navSlider.addEventListener('pointerdown', handlePointerDown);
            navSlider.addEventListener('pointermove', handlePointerMove);
            navSlider.addEventListener('pointerup', handlePointerUp);
            navSlider.addEventListener('pointercancel', handlePointerCancel);
            navSlider.addEventListener('pointerleave', (event) => {
                if (!isPointerDown) {
                    return;
                }

                handlePointerUp(event);
            });

            const navLinks = navSlider.querySelectorAll('a');
            if (navLinks.length) {
                navLinks.forEach((link) => {
                    link.addEventListener('click', (event) => {
                        if (isDragging) {
                            event.preventDefault();
                            event.stopImmediatePropagation();
                        }
                    });
                });
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768) {
                    stopMomentum();
                    navSlider.style.scrollSnapType = 'x mandatory';
                }
            });
        }

        if (typeof window.GTAModsActivity !== 'undefined') {
            const activity = window.GTAModsActivity || {};
            if (activity.shouldTrack && activity.restEndpoint) {
                const cookieName = activity.cookieName || 'gta6_activity_throttle';
                if (!hasCookie(cookieName)) {
                    const sendActivity = () => {
                        fetch(activity.restEndpoint, {
                            method: 'POST',
                            credentials: 'same-origin',
                            keepalive: true,
                            headers: buildHeaders(activity.restNonce),
                        }).catch(() => {
                            // ignore fetch errors
                        });

                        const maxAge = Math.max(60, Number(activity.throttleSeconds) || 1200);
                        setCookie(cookieName, '1', {
                            maxAge,
                            secure: Boolean(activity.isSecure),
                        });
                    };

                    const delay = Math.max(0, Number(activity.delayMs) || 0);
                    if (typeof window.requestIdleCallback === 'function') {
                        window.requestIdleCallback(sendActivity, { timeout: delay || 2000 });
                    } else {
                        window.setTimeout(sendActivity, delay || 2000);
                    }
                }
            }
        }

        const shareCopyButtons = document.querySelectorAll('[data-copy-url]');

        const setTemporaryState = (element, className, timeout = 1500) => {
            element.classList.add(className);
            window.setTimeout(() => {
                element.classList.remove(className);
            }, timeout);
        };

        if (shareCopyButtons.length) {
            shareCopyButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const url = button.getAttribute('data-copy-url');

                    if (!url) {
                        return;
                    }

                    const fallbackCopy = () => {
                        const textArea = document.createElement('textarea');
                        textArea.value = url;
                        textArea.setAttribute('readonly', '');
                        textArea.style.position = 'absolute';
                        textArea.style.left = '-9999px';
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                    };

                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        navigator.clipboard.writeText(url).then(() => {
                            setTemporaryState(button, 'text-pink-600');
                        }).catch(() => {
                            fallbackCopy();
                            setTemporaryState(button, 'text-pink-600');
                        });
                    } else {
                        fallbackCopy();
                        setTemporaryState(button, 'text-pink-600');
                    }
                });
            });
        }

        if (typeof window.GTAModsData === 'undefined') {
            return;
        }

        const data = window.GTAModsData || {};
        const featuredMods = Array.isArray(data.featuredMods) ? data.featuredMods : [];
        const popularMods = Array.isArray(data.popularMods) ? data.popularMods : [];
        const latestMods = Array.isArray(data.latestMods) ? data.latestMods : [];
        const latestNews = Array.isArray(data.latestNews) ? data.latestNews : [];


        // Featured carousel
        const sliderContainer = document.getElementById('featured-slider-container');

        const getSliderDatasetValue = (attribute, fallback) => {
            if (!sliderContainer || !sliderContainer.dataset) {
                return fallback;
            }

            const value = sliderContainer.dataset[attribute];
            return typeof value === 'string' && value.length ? value : fallback;
        };

        const featuredLabels = {
            by: getSliderDatasetValue('byLabel', 'by'),
            featured: getSliderDatasetValue('featuredLabel', 'Featured'),
            loading: getSliderDatasetValue('loadingText', 'Loading featured mods…'),
            empty: getSliderDatasetValue('emptyLabel', 'No featured mods available.'),
            previous: getSliderDatasetValue('prevLabel', 'Previous featured mod'),
            next: getSliderDatasetValue('nextLabel', 'Next featured mod'),
        };

        const FeaturedSlider = {
            config: {
                autoplayDuration: 5000,
                fallbackImage: 'https://placehold.co/900x500/ec4899/ffffff?text=GTA6+Mod',
                swipeThreshold: 50,
            },
            state: {
                container: sliderContainer,
                sanitizedMods: [],
                labels: featuredLabels,
                index: 0,
                activeImageSlot: 0,
                autoplayTimer: 0,
                elements: {
                    mainLink: null,
                    images: [],
                    title: null,
                    author: null,
                    textWrapper: null,
                    prevButton: null,
                    nextButton: null,
                    navSegments: [],
                },
            },
            sanitizeMods(mods) {
                if (!Array.isArray(mods)) {
                    return [];
                }

                return mods.slice(0, 4).map((item) => {
                    const mod = (item && typeof item === 'object') ? item : {};

                    return {
                        title: typeof mod.title === 'string' ? mod.title : '',
                        author: typeof mod.author === 'string' ? mod.author : '',
                        link: typeof mod.link === 'string' && mod.link ? mod.link : '#',
                        image: typeof mod.image === 'string' && mod.image ? mod.image : this.config.fallbackImage,
                    };
                });
            },
            ensureMarkup() {
                const container = this.state.container;

                if (!container) {
                    return false;
                }

                if (container.dataset && container.dataset.hydrated === 'true') {
                    return this.state.sanitizedMods.length > 0;
                }

                if (!this.state.sanitizedMods.length) {
                    container.innerHTML = '<div class="p-8 text-center text-gray-400 flex items-center justify-center min-h-[300px]">' + escapeHTML(this.state.labels.empty) + '</div>';
                    container.dataset.hydrated = 'true';
                    return false;
                }

                const firstMod = this.state.sanitizedMods[0];
                const segmentsMarkup = this.state.sanitizedMods.map((mod, index) => {
                    const activeClass = index === 0 ? ' active' : '';
                    const width = index === 0 ? '100' : '0';
                    return '<div class="featured-nav-segment' + activeClass + '" data-index="' + escapeHTML(String(index)) + '"><div class="progress-bar-inner" style="width: ' + width + '%;"></div></div>';
                }).join('');

                container.innerHTML = `
                    <a href="${escapeHTML(firstMod.link)}" id="featured-main-display" class="block relative group rounded-lg overflow-hidden">
                        <div id="featured-image-container" class="relative w-full aspect-video bg-gray-800">
                            <img id="featured-image-1" src="${escapeHTML(firstMod.image)}" alt="${escapeHTML(firstMod.title)}" class="absolute inset-0 w-full h-full object-cover" style="opacity: 1;">
                            <img id="featured-image-2" src="" alt="" class="absolute inset-0 w-full h-full object-cover" style="opacity: 0;">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                        </div>
                        <div class="featured-badge"><i class="fas fa-star fa-xs mr-1.5" aria-hidden="true"></i>${escapeHTML(this.state.labels.featured)}</div>
                        <div id="featured-nav-container"><div class="flex items-center gap-2">${segmentsMarkup}</div></div>
                        <div id="featured-text-content" class="absolute bottom-0 left-0 p-4 md:p-6 text-white w-full">
                            <h3 id="featured-title" class="text-lg sm:text-xl md:text-2xl font-bold leading-tight mb-1">${escapeHTML(firstMod.title)}</h3>
                            <p id="featured-author" class="text-sm text-gray-200">${escapeHTML(this.state.labels.by)} <span class="font-semibold">${escapeHTML(firstMod.author)}</span></p>
                        </div>
                        <button type="button" id="featured-prev" class="absolute left-3 top-1/2 -translate-y-1/2 bg-black/40 text-white text-[12px] sm:text-base rounded-full w-6 h-6 sm:w-10 sm:h-10 flex items-center justify-center opacity-100 group-hover:opacity-100 transform-gpu transition-all duration-300 hover:bg-black/60 hover:scale-110 focus:outline-none z-30" aria-label="${escapeHTML(this.state.labels.previous)}"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" id="featured-next" class="absolute right-3 top-1/2 -translate-y-1/2 bg-black/40 text-white text-[12px] sm:text-base rounded-full w-6 h-6 sm:w-10 sm:h-10 flex items-center justify-center opacity-100 group-hover:opacity-100 transform-gpu transition-all duration-300 hover:bg-black/60 hover:scale-110 focus:outline-none z-30" aria-label="${escapeHTML(this.state.labels.next)}"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                    </a>
                `;

                container.dataset.hydrated = 'true';
                return true;
            },
            cacheElements() {
                const container = this.state.container;
                if (!container) {
                    return;
                }

                this.state.elements = {
                    mainLink: document.getElementById('featured-main-display'),
                    images: [
                        document.getElementById('featured-image-1'),
                        document.getElementById('featured-image-2'),
                    ].filter((img) => img instanceof HTMLImageElement),
                    title: document.getElementById('featured-title'),
                    author: document.getElementById('featured-author'),
                    textWrapper: document.getElementById('featured-text-content'),
                    prevButton: document.getElementById('featured-prev'),
                    nextButton: document.getElementById('featured-next'),
                    navSegments: Array.from(container.querySelectorAll('.featured-nav-segment')),
                };

                this.state.elements.images.forEach((image) => {
                    if (!image) {
                        return;
                    }

                    image.addEventListener('error', () => {
                        if (image.src !== this.config.fallbackImage) {
                            image.src = this.config.fallbackImage;
                        }
                    });
                });
            },
            setImageSource(image, src, alt) {
                if (!image) {
                    return;
                }

                image.onerror = () => {
                    image.onerror = null;
                    image.src = this.config.fallbackImage;
                };

                image.src = src || this.config.fallbackImage;
                image.alt = alt || '';
            },
            updateTextContent(mod, isInitial) {
                const { title, author, textWrapper } = this.state.elements;

                if (!title || !author || !textWrapper) {
                    return;
                }

                const applyContent = () => {
                    title.textContent = mod.title || '';
                    author.innerHTML = escapeHTML(this.state.labels.by) + ' <span class="font-semibold">' + escapeHTML(mod.author) + '</span>';
                };

                if (isInitial) {
                    applyContent();
                    textWrapper.style.opacity = 1;
                    textWrapper.style.transform = 'translateY(0)';
                    return;
                }

                textWrapper.style.transform = 'translateY(10px)';
                textWrapper.style.opacity = 0;

                window.setTimeout(() => {
                    applyContent();
                    textWrapper.style.transform = 'translateY(0)';
                    textWrapper.style.opacity = 1;
                }, 150);
            },
            updateProgress(activeIndex) {
                const { navSegments } = this.state.elements;

                const durationSeconds = this.config.autoplayDuration / 1000;

                navSegments.forEach((segment, index) => {
                    if (!segment) {
                        return;
                    }

                    const progress = segment.querySelector('.progress-bar-inner');
                    const isActive = index === activeIndex;

                    segment.classList.toggle('active', isActive);

                    if (!progress) {
                        return;
                    }

                    progress.style.transition = 'none';

                    if (isActive) {
                        progress.style.width = '0%';
                        void progress.offsetWidth;
                        progress.style.transition = `width ${durationSeconds}s linear`;
                        progress.style.width = '100%';
                    } else if (index < activeIndex) {
                        progress.style.width = '100%';
                    } else {
                        progress.style.width = '0%';
                    }
                });
            },
            updateImages(mod, isInitial) {
                const { images } = this.state.elements;

                if (!images.length) {
                    return;
                }

                const currentSlot = this.state.activeImageSlot;
                const nextSlot = (currentSlot + 1) % images.length;
                const currentImage = images[currentSlot];
                const nextImage = images[nextSlot];

                if (isInitial) {
                    this.setImageSource(currentImage, mod.image, mod.title);
                    if (nextImage) {
                        nextImage.style.opacity = 0;
                        nextImage.classList.remove('ken-burns');
                    }

                    if (currentImage) {
                        currentImage.style.opacity = 1;
                        currentImage.classList.add('ken-burns');
                    }

                    return;
                }

                this.setImageSource(nextImage, mod.image, mod.title);

                if (nextImage) {
                    nextImage.style.opacity = 1;
                    nextImage.classList.add('ken-burns');
                }

                if (currentImage) {
                    currentImage.style.opacity = 0;
                    currentImage.classList.remove('ken-burns');
                }

                this.state.activeImageSlot = nextSlot;
            },
            updateSlide(index, isInitial = false) {
                const mods = this.state.sanitizedMods;
                const total = mods.length;

                if (total <= 0) {
                    return;
                }

                const normalizedIndex = ((index % total) + total) % total;
                const mod = mods[normalizedIndex];

                if (!mod) {
                    return;
                }

                const { mainLink } = this.state.elements;

                if (mainLink) {
                    mainLink.setAttribute('href', mod.link || '#');
                }

                this.updateImages(mod, isInitial);
                this.updateTextContent(mod, isInitial);
                this.updateProgress(normalizedIndex);
                this.state.index = normalizedIndex;
            },
            pauseAutoplay() {
                if (this.state.autoplayTimer) {
                    window.clearInterval(this.state.autoplayTimer);
                    this.state.autoplayTimer = 0;
                }
            },
            startAutoplay() {
                this.pauseAutoplay();

                if (this.state.sanitizedMods.length <= 1) {
                    return;
                }

                this.state.autoplayTimer = window.setInterval(() => {
                    const nextIndex = (this.state.index + 1) % this.state.sanitizedMods.length;
                    this.updateSlide(nextIndex);
                }, this.config.autoplayDuration);
            },
            goToSlide(index) {
                this.pauseAutoplay();
                this.updateSlide(index);
                this.startAutoplay();
            },
            disableNavigationControls(shouldDisable) {
                const { prevButton, nextButton } = this.state.elements;
                [prevButton, nextButton].forEach((button) => {
                    if (!button) {
                        return;
                    }

                    if (shouldDisable) {
                        button.setAttribute('aria-disabled', 'true');
                        button.setAttribute('tabindex', '-1');
                        button.classList.add('pointer-events-none');
                    } else {
                        button.setAttribute('aria-disabled', 'false');
                        button.removeAttribute('tabindex');
                        button.classList.remove('pointer-events-none');
                    }
                });
            },
            handleSwipe(startX, endX) {
                if (this.state.sanitizedMods.length <= 1) {
                    return false;
                }

                const swipeDistance = endX - startX;

                if (Math.abs(swipeDistance) < this.config.swipeThreshold) {
                    return false;
                }

                if (swipeDistance < 0) {
                    const nextIndex = (this.state.index + 1) % this.state.sanitizedMods.length;
                    this.goToSlide(nextIndex);
                } else {
                    const previousIndex = (this.state.index - 1 + this.state.sanitizedMods.length) % this.state.sanitizedMods.length;
                    this.goToSlide(previousIndex);
                }

                return true;
            },
            bindEvents() {
                const slider = this;
                const { navSegments, prevButton, nextButton, mainLink, container } = this.state.elements;

                navSegments.forEach((segment) => {
                    segment.addEventListener('click', (event) => {
                        event.preventDefault();

                        const index = parseInt(segment.dataset.index || '0', 10);
                        if (Number.isNaN(index) || index === slider.state.index) {
                            return;
                        }

                        slider.goToSlide(index);
                    });
                });

                if (prevButton) {
                    prevButton.addEventListener('click', (event) => {
                        event.preventDefault();

                        if (slider.state.sanitizedMods.length <= 1) {
                            return;
                        }

                        const targetIndex = (slider.state.index - 1 + slider.state.sanitizedMods.length) % slider.state.sanitizedMods.length;
                        slider.goToSlide(targetIndex);
                    });
                }

                if (nextButton) {
                    nextButton.addEventListener('click', (event) => {
                        event.preventDefault();

                        if (slider.state.sanitizedMods.length <= 1) {
                            return;
                        }

                        const targetIndex = (slider.state.index + 1) % slider.state.sanitizedMods.length;
                        slider.goToSlide(targetIndex);
                    });
                }

                if (container) {
                    container.style.setProperty('--featured-autoplay-duration', `${this.config.autoplayDuration / 1000}s`);

                    container.addEventListener('mouseenter', () => {
                        slider.pauseAutoplay();
                    });

                    container.addEventListener('mouseleave', () => {
                        if (slider.state.sanitizedMods.length > 1) {
                            slider.startAutoplay();
                        }
                    });
                }

                if (mainLink) {
                    let touchStartX = 0;

                    mainLink.addEventListener('touchstart', (event) => {
                        if (!event.changedTouches || !event.changedTouches.length) {
                            return;
                        }

                        touchStartX = event.changedTouches[0].screenX;
                        slider.pauseAutoplay();
                    }, { passive: true });

                    mainLink.addEventListener('touchend', (event) => {
                        if (!event.changedTouches || !event.changedTouches.length) {
                            if (slider.state.sanitizedMods.length > 1) {
                                slider.startAutoplay();
                            }
                            return;
                        }

                        const touchEndX = event.changedTouches[0].screenX;
                        const changed = slider.handleSwipe(touchStartX, touchEndX);

                        if (!changed && slider.state.sanitizedMods.length > 1) {
                            slider.startAutoplay();
                        }
                    }, { passive: true });
                }

                document.addEventListener('visibilitychange', () => {
                    if (slider.state.sanitizedMods.length <= 1) {
                        return;
                    }

                    if (document.hidden) {
                        slider.pauseAutoplay();
                    } else {
                        slider.startAutoplay();
                    }
                });
            },
            init(container, mods, labels) {
                if (!container) {
                    return;
                }

                this.state.container = container;
                this.state.labels = labels;
                this.state.sanitizedMods = this.sanitizeMods(mods);
                this.state.index = 0;
                this.state.activeImageSlot = 0;

                if (!this.ensureMarkup()) {
                    return;
                }

                this.cacheElements();

                if (!this.state.sanitizedMods.length) {
                    return;
                }

                this.updateSlide(0, true);
                this.disableNavigationControls(this.state.sanitizedMods.length <= 1);
                this.bindEvents();

                if (this.state.sanitizedMods.length > 1) {
                    this.startAutoplay();
                }
            },
        };

        FeaturedSlider.init(sliderContainer, featuredMods, featuredLabels);


        const renderModCards = (containerId, mods) => {
            const container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            if (container.dataset && container.dataset.hydrated === 'true') {
                return;
            }

            if (!mods.length) {
                container.innerHTML = '<div class="col-span-full text-center text-gray-500">' + escapeHTML('Nincs megjeleníthető tartalom.') + '</div>';
                return;
            }

            const fragment = document.createDocumentFragment();

            mods.forEach((mod) => {
                const card = document.createElement('div');
                card.className = 'card hover:shadow-xl transition duration-300';

                const link = document.createElement('a');
                link.href = mod.link || '#';
                link.className = 'block';

                const figure = document.createElement('div');
                figure.className = 'relative';

                const image = document.createElement('img');
                image.src = mod.image || 'https://placehold.co/300x169/94a3b8/ffffff?text=GTA6+Mods';
                image.alt = mod.title || '';
                image.className = 'w-full h-auto object-cover rounded-t-xl';
                figure.appendChild(image);

                const overlay = document.createElement('div');
                overlay.className = 'absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs';

                const overlayFlex = document.createElement('div');
                overlayFlex.className = 'flex justify-between items-center';

                const ratingSpan = document.createElement('span');
                ratingSpan.className = 'flex items-center font-semibold text-yellow-400';
                ratingSpan.innerHTML = '<i class="fa-solid fa-star mr-1"></i>' + escapeHTML(mod.rating || '5.0');

                const statsWrapper = document.createElement('div');
                statsWrapper.className = 'flex items-center space-x-3';

                const likesSpan = document.createElement('span');
                likesSpan.className = 'flex items-center';
                likesSpan.innerHTML = '<i class="fa-solid fa-thumbs-up mr-1"></i>' + escapeHTML(formatNumber(mod.likes) || '0');

                const downloadsSpan = document.createElement('span');
                downloadsSpan.className = 'flex items-center';
                downloadsSpan.innerHTML = '<i class="fa-solid fa-download mr-1"></i>' + escapeHTML(formatNumber(mod.downloads) || '0');

                statsWrapper.appendChild(likesSpan);
                statsWrapper.appendChild(downloadsSpan);

                overlayFlex.appendChild(ratingSpan);
                overlayFlex.appendChild(statsWrapper);
                overlay.appendChild(overlayFlex);
                figure.appendChild(overlay);

                const content = document.createElement('div');
                content.className = 'p-3';

                const title = document.createElement('h3');
                title.className = 'font-semibold text-gray-900 text-sm truncate';
                title.title = mod.title || '';
                title.textContent = mod.title || '';

                const meta = document.createElement('div');
                meta.className = 'flex justify-between items-center text-xs text-gray-500 mt-1';
                const authorText = mod.author ? 'by ' + mod.author : '';
                meta.innerHTML = '<span class="flex items-center"><i class="fa-solid fa-user mr-1"></i> ' + escapeHTML(authorText) + '</span>';

                content.appendChild(title);
                content.appendChild(meta);

                link.appendChild(figure);
                link.appendChild(content);
                card.appendChild(link);

                fragment.appendChild(card);
            });

            container.innerHTML = '';
            container.appendChild(fragment);
        };

        renderModCards('popular-mods-grid', popularMods);
        renderModCards('latest-mods-grid', latestMods);

        const renderNewsCards = (containerId, newsItems) => {
            const container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            if (container.dataset && container.dataset.hydrated === 'true') {
                return;
            }

            if (!newsItems.length) {
                container.innerHTML = '<div class="card p-6 text-center text-gray-500">' + escapeHTML('Nincs hír a listában.') + '</div>';
                return;
            }

            const fragment = document.createDocumentFragment();

            newsItems.forEach((news) => {
                const card = document.createElement('div');
                card.className = 'card p-4 md:p-5 hover:shadow-xl transition duration-300';

                const link = document.createElement('a');
                link.href = news.link || '#';
                link.className = 'flex flex-col md:flex-row gap-4 md:gap-5 items-start';

                const imageWrapper = document.createElement('div');
                imageWrapper.className = 'w-full h-32 md:w-48 md:h-28 flex-shrink-0 rounded-lg overflow-hidden';

                const image = document.createElement('img');
                image.src = news.image || 'https://placehold.co/400x225/111827/f9fafb?text=GTA6+News';
                image.alt = news.title || '';
                image.className = 'w-full h-full object-cover';
                imageWrapper.appendChild(image);

                const content = document.createElement('div');
                content.className = 'flex-grow md:border-l md:border-gray-200 md:pl-5';

                const meta = document.createElement('div');
                meta.className = 'flex flex-wrap items-center space-x-3 mb-2 text-xs';
                meta.innerHTML = '<span class="bg-pink-100 text-pink-800 font-semibold px-2 py-0.5 rounded-full shadow-sm">' + escapeHTML(news.category || '') + '</span>' +
                    '<span class="text-gray-500 mt-1 md:mt-0"><i class="fa-solid fa-calendar-days mr-1"></i>' + escapeHTML(news.date || '') + '</span>';

                const title = document.createElement('h3');
                title.className = 'font-bold text-lg text-gray-900 hover:text-pink-600 transition';
                title.textContent = news.title || '';

                const summary = document.createElement('p');
                summary.className = 'text-gray-600 mt-1 text-sm';
                summary.textContent = news.summary || '';

                const readMore = document.createElement('span');
                readMore.className = 'mt-3 inline-flex items-center text-xs font-semibold text-pink-600 hover:underline';
                readMore.innerHTML = escapeHTML('Tovább olvasom') + ' <i class="fa-solid fa-chevron-right ml-1 text-sm"></i>';

                content.appendChild(meta);
                content.appendChild(title);
                content.appendChild(summary);
                content.appendChild(readMore);

                link.appendChild(imageWrapper);
                link.appendChild(content);
                card.appendChild(link);
                fragment.appendChild(card);
            });

            container.innerHTML = '';
            container.appendChild(fragment);
        };

        renderNewsCards('latest-news-list', latestNews);
    });
})();
