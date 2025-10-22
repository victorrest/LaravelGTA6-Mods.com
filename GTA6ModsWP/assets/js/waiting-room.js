(function () {
    'use strict';

    const config = window.gta6modsWaitingRoom || {};
    const root = document.querySelector('[data-waiting-room]');
    if (!root) {
        return;
    }

    const button = root.querySelector('[data-download-button]');
    const countdownText = root.querySelector('[data-countdown-text]');
    const countdownWrapper = root.querySelector('[data-countdown-wrapper]');
    const countdownValue = root.querySelector('[data-countdown-value]');
    const buttonLabel = root.querySelector('[data-button-text]');
    const buttonContainer = root.querySelector('[data-button-label]');
    const buttonSpinner = root.querySelector('[data-button-spinner]');
    const buttonIcon = root.querySelector('[data-button-icon]');
    const mode = typeof config.mode === 'string' ? config.mode : 'internal';
    const isExternal = mode === 'external';
    const rawVersionId = typeof config.versionId === 'undefined' ? '' : config.versionId;
    const numericVersionId = parseInt(rawVersionId, 10);
    const hasNumericVersionId = Number.isFinite(numericVersionId) && !Number.isNaN(numericVersionId);
    const countdownSeconds = Math.max(parseInt(config.countdownSeconds, 10) || 5, 1);
    const sessionKey = typeof config.sessionKey === 'string' && config.sessionKey
        ? config.sessionKey
        : `gta6mods_wait_${hasNumericVersionId ? numericVersionId : String(rawVersionId || '0')}`;
    const strings = config.strings || {};
    const externalUrl = typeof config.externalUrl === 'string' ? config.externalUrl : '';
    const downloadEndpoint = typeof config.downloadEndpoint === 'string' ? config.downloadEndpoint : '';
    const restHeaders = {};
    if (typeof config.nonce === 'string' && config.nonce) {
        restHeaders['X-WP-Nonce'] = config.nonce;
    }
    const trackingNonce = (typeof window !== 'undefined'
        && window.GTAModsSecurity
        && typeof window.GTAModsSecurity.trackingNonce === 'string')
        ? window.GTAModsSecurity.trackingNonce
        : '';
    if (trackingNonce) {
        restHeaders['X-GTA6-Nonce'] = trackingNonce;
    }
    const shouldTrackOnWaitingRoom = Boolean(downloadEndpoint);
    let hasTrackedDownload = false;
    if (!button) {
        return;
    }

    if (!isExternal && !hasNumericVersionId) {
        return;
    }

    let startTimestamp = window.sessionStorage.getItem(sessionKey);
    const now = Date.now();
    if (!startTimestamp) {
        startTimestamp = now;
        window.sessionStorage.setItem(sessionKey, String(startTimestamp));
    } else {
        startTimestamp = parseInt(startTimestamp, 10);
        if (Number.isNaN(startTimestamp) || startTimestamp > now) {
            startTimestamp = now;
            window.sessionStorage.setItem(sessionKey, String(startTimestamp));
        }
    }

    let tokenResolved = false;
    let tokenUrl = '';
    let clickHandlerAttached = false;

    const sendDownloadIncrement = (versionId) => {
        if (!shouldTrackOnWaitingRoom) {
            return Promise.resolve(null);
        }

        const headers = { ...restHeaders };
        headers['Content-Type'] = 'application/json';

        const body = {};
        if (Number.isFinite(versionId) && versionId > 0) {
            body.versionId = versionId;
        }

        return fetch(downloadEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify(body),
            keepalive: true,
        }).catch(() => null);
    };

    function setButtonState(isReady, text) {
        if (isReady) {
            button.classList.add('is-ready');
            button.disabled = false;
            button.removeAttribute('disabled');
            button.setAttribute('aria-disabled', 'false');
            button.setAttribute('aria-busy', 'false');
            if (buttonLabel) {
                buttonLabel.textContent = text || strings.ready || 'Download ready';
            }
            if (countdownWrapper) {
                countdownWrapper.classList.add('is-hidden');
            }
            if (buttonSpinner) {
                buttonSpinner.style.display = 'none';
            }
            if (buttonIcon) {
                buttonIcon.style.display = 'inline-flex';
            }
            if (tokenUrl) {
                attachClickHandler();
            }
        } else {
            button.classList.remove('is-ready');
            button.disabled = true;
            button.setAttribute('disabled', 'disabled');
            button.setAttribute('aria-disabled', 'true');
            button.setAttribute('aria-busy', 'true');
            if (buttonLabel) {
                buttonLabel.textContent = text || strings.preparing || 'Preparing download…';
            }
            if (countdownWrapper) {
                countdownWrapper.classList.remove('is-hidden');
            }
            if (buttonSpinner) {
                buttonSpinner.style.display = 'inline-flex';
            }
            if (buttonIcon) {
                buttonIcon.style.display = 'none';
            }
        }
    }

    function updateCountdownLabel(secondsLeft) {
        const rounded = Math.max(Math.ceil(secondsLeft), 0);
        if (strings.countdown) {
            if (countdownText) {
                countdownText.textContent = strings.countdown.replace('%d', rounded);
            }
        } else {
            if (countdownText) {
                countdownText.textContent = `Download starts in ${rounded} seconds.`;
            }
        }
    }

    function updateCountdownValue(secondsLeft) {
        const rounded = Math.max(Math.ceil(secondsLeft), 0);
        if (countdownValue) {
            countdownValue.textContent = String(rounded);
        }
        if (!countdownText && buttonLabel) {
            const label = strings.countdown ? strings.countdown.replace('%d', rounded) : `Download starts in ${rounded} seconds.`;
            buttonLabel.textContent = label;
        }
    }

    function handleError(message) {
        setButtonState(false, message || strings.requestFailed);
        if (countdownText) {
            countdownText.textContent = message || strings.requestFailed || 'Something went wrong. Try again.';
        }
        window.sessionStorage.removeItem(sessionKey);
        if (countdownWrapper) {
            countdownWrapper.classList.add('is-hidden');
        }
        if (buttonContainer) {
            buttonContainer.classList.add('has-error');
        }
    }

    function attachClickHandler() {
        if (clickHandlerAttached) {
            return;
        }

        button.addEventListener('click', (event) => {
            if (!tokenUrl) {
                event.preventDefault();
                return;
            }

            if (shouldTrackOnWaitingRoom && !hasTrackedDownload) {
                hasTrackedDownload = true;
                sendDownloadIncrement(hasNumericVersionId ? numericVersionId : null);
            }

            if (isExternal) {
                event.preventDefault();
                window.open(tokenUrl, '_blank', 'noopener');
            } else {
                window.location.href = tokenUrl;
            }
        });

        clickHandlerAttached = true;
    }

    async function requestToken() {
        if (tokenResolved) {
            return;
        }

        tokenResolved = true;
        setButtonState(false, strings.preparing);

        if (isExternal) {
            if (externalUrl) {
                tokenUrl = externalUrl;
                setButtonState(true, strings.ready);
                attachClickHandler();
                if (countdownText) {
                    countdownText.textContent = strings.ready || 'Open external link';
                }
            } else {
                handleError(strings.requestFailed || 'Failed to prepare external link.');
            }
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 10000);

        try {
            const response = await window.fetch(config.restUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce || '',
                },
                body: JSON.stringify({
                    version_id: numericVersionId,
                }),
                signal: controller.signal,
            });

            window.clearTimeout(timeout);

            if (response.status === 429) {
                handleError(strings.rateLimited || 'Too many attempts. Please slow down.');
                return;
            }

            if (!response.ok) {
                handleError(strings.requestFailed || 'Failed to prepare download.');
                return;
            }

            const payload = await response.json();
            if (!payload || !payload.download_url) {
                handleError(strings.requestFailed || 'Failed to prepare download.');
                return;
            }

            tokenUrl = payload.download_url;
            setButtonState(true, strings.ready);
            attachClickHandler();
            if (countdownText) {
                countdownText.textContent = strings.ready || 'Start download';
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                handleError(strings.requestFailed || 'Timeout requesting token.');
            } else {
                handleError(strings.requestFailed || 'Failed to prepare download.');
            }
        }
    }

    function tick() {
        const elapsedSeconds = (Date.now() - startTimestamp) / 1000;
        const secondsLeft = countdownSeconds - elapsedSeconds;

        if (secondsLeft <= 0) {
            updateCountdownLabel(0);
            updateCountdownValue(0);
            requestToken();
            return;
        }

        updateCountdownLabel(secondsLeft);
        updateCountdownValue(secondsLeft);

        window.requestAnimationFrame(tick);
    }

    setButtonState(false, strings.preparing);
    updateCountdownLabel(countdownSeconds);
    window.requestAnimationFrame(tick);
})();
