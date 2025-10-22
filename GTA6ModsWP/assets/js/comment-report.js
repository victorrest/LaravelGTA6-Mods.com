(() => {
    const data = window.GTA6CommentReport || {};
    const utils = window.GTAModsUtils || {};
    const buildRestHeaders = typeof utils.buildRestHeaders === 'function'
        ? utils.buildRestHeaders
        : (nonce, extra = {}) => {
            const headers = { ...extra };
            if (nonce) {
                headers['X-WP-Nonce'] = nonce;
            }
            return headers;
        };
    const restBase = typeof data.restBase === 'string' ? data.restBase.replace(/\/$/, '') : '';
    const restNonce = typeof data.restNonce === 'string' ? data.restNonce : '';
    const loginRequiredMessage = data.messages && data.messages.loginRequired
        ? data.messages.loginRequired
        : 'Login required.';
    const genericErrorMessage = data.messages && data.messages.genericError
        ? data.messages.genericError
        : 'Something went wrong. Please try again.';
    const overlay = document.getElementById('gta6-report-overlay');
    const modal = document.getElementById('gta6-report-modal');
    const closeButtons = document.querySelectorAll('[data-gta6-report-close]');
    const form = document.getElementById('gta6-report-form');
    const submitBtn = document.getElementById('gta6-report-submit');
    const success = document.getElementById('gta6-report-success');
    const formWrapper = document.getElementById('gta6-report-form-wrapper');
    const footer = document.getElementById('gta6-report-footer');
    const feedback = document.getElementById('gta6-report-feedback');
    const otherContainer = document.getElementById('gta6-report-other');
    const detailsField = document.getElementById('gta6-report-details');
    const progressTrack = modal ? modal.querySelector('[data-progress-track]') : null;
    if (!overlay || !modal || !form || !submitBtn) { return; }
    const successProgress = progressTrack ? progressTrack.querySelector('[data-progress-bar]') : null;
    const SUCCESS_TIMEOUT = 7000;
    let activeCommentId = null;
    let closeTimer = null;
    let successVisible = false;
    let successPaused = false;
    let remainingTime = SUCCESS_TIMEOUT;
    let startTime = null;
    const reasonInputs = form.querySelectorAll('input[name="report_reason"]');
    const hideFeedback = () => { feedback.classList.add('hidden'); feedback.textContent = ''; feedback.classList.remove('text-green-600'); feedback.classList.add('text-red-600'); };
    const showFeedback = (message, isError = true) => { feedback.textContent = message || ''; feedback.classList.remove('hidden'); feedback.classList.toggle('text-red-600', isError); feedback.classList.toggle('text-green-600', !isError); };
    const getTimestamp = () => ((typeof performance !== 'undefined' && performance && typeof performance.now === 'function') ? performance.now() : Date.now());
    const getScaleX = (element) => {
        if (!element) { return 1; }
        const computed = window.getComputedStyle(element);
        const transform = computed.transform || computed.webkitTransform || '';
        if (!transform || transform === 'none') { return 1; }
        const match = transform.match(/matrix(3d)?\((.+)\)/);
        if (!match) { return 1; }
        const values = match[2].split(',').map((value) => parseFloat(value.trim())).filter((value) => !Number.isNaN(value));
        return values.length ? values[0] : 1;
    };
    const showProgressTrack = () => { if (progressTrack) { progressTrack.classList.remove('hidden'); } };
    const hideProgressTrack = () => { if (progressTrack) { progressTrack.classList.add('hidden'); } };
    const animateProgress = (duration, fromScale = 1) => {
        if (!successProgress) { return; }
        const safeScale = Number.isFinite(fromScale) ? Math.max(Math.min(fromScale, 1), 0) : 1;
        successProgress.style.transition = 'none';
        successProgress.style.transform = `scaleX(${safeScale})`;
        void successProgress.offsetWidth;
        successProgress.style.transition = `transform ${duration}ms linear`;
        requestAnimationFrame(() => { successProgress.style.transform = 'scaleX(0)'; });
    };
    const startCountdown = (duration = SUCCESS_TIMEOUT, fromScale = 1) => {
        remainingTime = typeof duration === 'number' && duration > 0 ? duration : SUCCESS_TIMEOUT;
        startTime = getTimestamp();
        if (closeTimer) { clearTimeout(closeTimer); }
        closeTimer = setTimeout(closeModal, remainingTime);
        animateProgress(remainingTime, fromScale);
    };
    const pauseCountdown = () => {
        if (!successVisible || successPaused) { return; }
        successPaused = true;
        if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
        if (startTime) {
            const elapsed = getTimestamp() - startTime;
            remainingTime = Math.max(remainingTime - elapsed, 0);
        }
        if (successProgress) {
            const currentScale = getScaleX(successProgress);
            const safeScale = Number.isFinite(currentScale) ? Math.max(Math.min(currentScale, 1), 0) : 1;
            successProgress.style.transition = 'none';
            successProgress.style.transform = `scaleX(${safeScale})`;
        }
    };
    const resumeCountdown = () => {
        if (!successVisible || !successPaused) { return; }
        if (remainingTime <= 0) { closeModal(); return; }
        successPaused = false;
        const currentScale = getScaleX(successProgress);
        startCountdown(remainingTime, currentScale);
    };
    const resetProgress = () => {
        remainingTime = SUCCESS_TIMEOUT;
        startTime = null;
        successPaused = false;
        if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
        if (successProgress) {
            successProgress.style.transition = 'none';
            successProgress.style.transform = 'scaleX(1)';
        }
        hideProgressTrack();
    };
    const resetForm = () => {
        reasonInputs.forEach((input, index) => { input.checked = index === 0; });
        if (detailsField) { detailsField.value = ''; }
        if (otherContainer) { otherContainer.classList.add('hidden'); }
        hideFeedback();
        formWrapper.classList.remove('hidden');
        footer.classList.remove('hidden');
        if (success) { success.classList.add('hidden'); }
        successVisible = false;
        resetProgress();
    };
    const openModal = (commentId) => { activeCommentId = commentId; resetForm(); overlay.classList.remove('hidden'); requestAnimationFrame(() => { modal.classList.remove('scale-95', 'opacity-0'); modal.classList.add('scale-100', 'opacity-100'); }); };
    const closeModal = () => {
        modal.classList.remove('scale-100', 'opacity-100');
        modal.classList.add('scale-95', 'opacity-0');
        successVisible = false;
        if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
        setTimeout(() => { overlay.classList.add('hidden'); resetForm(); }, 200);
    };
    reasonInputs.forEach((input) => input.addEventListener('change', (event) => { if (!otherContainer) { return; } if (event.target.value === 'other') { otherContainer.classList.remove('hidden'); if (detailsField) { detailsField.focus(); } } else { otherContainer.classList.add('hidden'); } }));
    document.addEventListener('click', (event) => { const trigger = event.target.closest('.gta6-comment-report-btn'); if (trigger) { const commentId = parseInt(trigger.getAttribute('data-comment-id'), 10); if (commentId) { openModal(commentId); } return; } if (event.target === overlay) { closeModal(); } });
    closeButtons.forEach((btn) => btn.addEventListener('click', closeModal));
    if (success) {
        success.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') { pauseCountdown(); }
        });
        success.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') { resumeCountdown(); }
        });
        success.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'touch' || event.pointerType === 'pen') {
                if (successPaused) {
                    resumeCountdown();
                } else {
                    pauseCountdown();
                }
            }
        });
    }
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !overlay.classList.contains('hidden')) { closeModal(); } });
    submitBtn.addEventListener('click', (event) => {
        event.preventDefault();
        hideFeedback();
        if (!data.isLoggedIn) { showFeedback(loginRequiredMessage, true); return; }
        if (!activeCommentId) { showFeedback(genericErrorMessage, true); return; }
        if (!restBase) { showFeedback(genericErrorMessage, true); return; }
        const selected = form.querySelector('input[name="report_reason"]:checked');
        const reason = selected ? selected.value : '';
        const details = reason === 'other' && detailsField ? detailsField.value.trim() : '';
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
        const endpoint = `${restBase}/${activeCommentId}/report`;
        const body = { reason };
        if (details) {
            body.details = details;
        }
        const headers = buildRestHeaders(restNonce, { 'Content-Type': 'application/json' });
        fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify(body) })
            .then(async (response) => {
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload || typeof payload !== 'object') {
                    const message = payload && payload.message ? payload.message : genericErrorMessage;
                    throw new Error(message);
                }
                return payload;
            })
            .then(() => {
                formWrapper.classList.add('hidden');
                footer.classList.add('hidden');
                if (success) {
                    success.classList.remove('hidden');
                }
                resetProgress();
                successVisible = true;
                showProgressTrack();
                requestAnimationFrame(() => { startCountdown(SUCCESS_TIMEOUT); });
            })
            .catch((error) => {
                const message = error && error.message ? error.message : genericErrorMessage;
                showFeedback(message, true);
            })
            .finally(() => { submitBtn.disabled = false; submitBtn.classList.remove('opacity-70', 'cursor-not-allowed'); });
    });
})();
