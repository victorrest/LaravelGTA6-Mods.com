(function () {
    const root = document.querySelector('[data-forum-create]');
    if (!root || !window.wp || !wp.apiFetch || !window.GTA6ForumCreate) {
        return;
    }

    const form = root.querySelector('[data-create-form]');
    const submitButton = form.querySelector('[data-submit]');
    const statusEl = form.querySelector('[data-create-status]');
    const tabButtons = root.querySelectorAll('[data-tab]');
    const panels = form.querySelectorAll('[data-tab-panel]');
    const flairPicker = form.querySelector('[data-flair-options]');
    const flairHiddenInput = form.querySelector('[data-selected-flair]');
    const imageSourceRoot = form.querySelector('[data-image-source]');
    const imageSourceButtons = imageSourceRoot ? imageSourceRoot.querySelectorAll('[data-image-source-option]') : [];
    const imageSourcePanels = form.querySelectorAll('[data-image-source-panel]');
    const fileInput = form.querySelector('[data-field="image-file"]');
    const uploadZone = form.querySelector('[data-upload-zone]');
    const uploadFileName = form.querySelector('[data-upload-filename]');
    const editorToolbar = form.querySelector('[data-editor-toolbar]');
    const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
    const allowedHosts = Array.isArray(GTA6ForumCreate.relatedModHosts)
        ? Array.from(new Set(GTA6ForumCreate.relatedModHosts.map((host) => String(host || '').toLowerCase()).filter(Boolean)))
        : ['gta6-mods.com', 'www.gta6-mods.com'];
    const invalidModUrlMessage = (GTA6ForumCreate.createTexts && GTA6ForumCreate.createTexts.invalidModUrl)
        ? GTA6ForumCreate.createTexts.invalidModUrl
        : 'Please enter a valid GTA6-Mods.com link (https://gta6-mods.com/...).';
    const VALID_TABS = ['text', 'image', 'link'];
    const initialTabAttr = root.getAttribute('data-initial-tab');

    let activeTab = VALID_TABS.includes(initialTabAttr) ? initialTabAttr : 'text';
    let activeImageSource = 'upload';
    let selectedFile = null;

    function escapeAttribute(value) {
        return value.replace(/[&<>"']/g, (char) => {
            switch (char) {
                case '&':
                    return '&amp;';
                case '<':
                    return '&lt;';
                case '>':
                    return '&gt;';
                case '"':
                    return '&quot;';
                case "'":
                    return '&#39;';
                default:
                    return char;
            }
        });
    }


    function escapeHtml(value) {
        return value.replace(/[&<>"']/g, (char) => {
            switch (char) {
                case '&':
                    return '&amp;';
                case '<':
                    return '&lt;';
                case '>':
                    return '&gt;';
                case '"':
                    return '&quot;';
                case "'":
                    return '&#39;';
                default:
                    return char;
            }
        });
    }
    function getActivePanel() {
        return form.querySelector(`[data-tab-panel="${activeTab}"]`);
    }

    function getField(name, tab = activeTab) {
        const panel = form.querySelector(`[data-tab-panel="${tab}"]`);
        if (!panel) {
            return null;
        }
        return panel.querySelector(`[data-field="${name}"]`);
    }

    function getTitleValue() {
        const field = getField('title');
        return field ? field.value.trim() : '';
    }

    function getBodyValue() {
        const field = getField('content');
        return field ? field.value.trim() : '';
    }

    function getImageUrlValue() {
        const field = getField('image-url');
        return field ? field.value.trim() : '';
    }

    function getLinkValue() {
        const field = getField('link');
        return field ? field.value.trim() : '';
    }

    function getModUrlValue() {
        const field = getField('mod-url');
        return field ? field.value.trim() : '';
    }

    function validateRelatedModUrlField() {
        const field = getField('mod-url');
        if (!field) {
            return true;
        }

        const value = field.value.trim();
        if (!value) {
            field.setCustomValidity('');
            return true;
        }

        let parsed;
        try {
            parsed = new URL(value);
        } catch (error) {
            field.setCustomValidity(invalidModUrlMessage);
            return false;
        }

        const host = parsed.hostname.toLowerCase();
        if (!allowedHosts.includes(host)) {
            field.setCustomValidity(invalidModUrlMessage);
            return false;
        }

        if (parsed.protocol !== 'https:') {
            parsed.protocol = 'https:';
            field.value = parsed.toString();
        }

        field.setCustomValidity('');
        return true;
    }

    function clearStatus() {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = '';
        statusEl.classList.remove('forum-status-error');
    }

    function renderStatus(message, isError = false) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.toggle('forum-status-error', Boolean(isError));
    }

    function setButtonBusy(isBusy) {
        submitButton.disabled = isBusy;
        submitButton.classList.toggle('is-busy', Boolean(isBusy));
    }

    function showPanel(panel) {
        panel.classList.remove('hidden');
        panel.setAttribute('aria-hidden', 'false');
    }

    function hidePanel(panel) {
        panel.classList.add('hidden');
        panel.setAttribute('aria-hidden', 'true');
    }

    function updateUploadLabel() {
        if (!uploadFileName) {
            return;
        }
        uploadFileName.textContent = selectedFile ? selectedFile.name : '';
    }

    function updateTypeInUrl(tab) {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        try {
            const url = new URL(window.location.href);
            if (!tab || tab === 'text') {
                url.searchParams.delete('type');
            } else {
                url.searchParams.set('type', tab);
            }

            const nextState = Object.assign({}, window.history.state || {}, { type: tab });
            window.history.replaceState(nextState, '', url.toString());
        } catch (error) {
            // Ignore malformed URLs
        }
    }

    function switchTab(tab, options = {}) {
        const { updateHistory = true, force = false } = options;
        if (!tab || !VALID_TABS.includes(tab)) {
            return;
        }

        const tabChanged = tab !== activeTab;
        if (tabChanged) {
            activeTab = tab;
        } else if (!force) {
            if (updateHistory) {
                updateTypeInUrl(tab);
            }
            return;
        }

        panels.forEach((panel) => {
            const isTarget = panel.getAttribute('data-tab-panel') === tab;
            if (isTarget) {
                showPanel(panel);
            } else {
                hidePanel(panel);
            }
        });

        tabButtons.forEach((button) => {
            const isActiveButton = button.getAttribute('data-tab') === tab;
            button.classList.toggle('active', isActiveButton);
        });

        if (tab !== 'image') {
            selectedFile = null;
            if (fileInput) {
                fileInput.value = '';
            }
            updateUploadLabel();
        }

        clearStatus();
        validate();

        if (updateHistory) {
            updateTypeInUrl(tab);
        }
    }

    function switchImageSource(source) {
        activeImageSource = source;

        imageSourceButtons.forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-image-source-option') === source);
        });

        imageSourcePanels.forEach((panel) => {
            if (panel.getAttribute('data-image-source-panel') === source) {
                showPanel(panel);
            } else {
                hidePanel(panel);
            }
        });

        if (source === 'upload') {
            const imageUrlField = getField('image-url', 'image');
            if (imageUrlField) {
                imageUrlField.value = '';
            }
        } else {
            selectedFile = null;
            if (fileInput) {
                fileInput.value = '';
            }
            updateUploadLabel();
        }

        clearStatus();
        validate();
    }

    function validate() {
        const hasTitle = getTitleValue().length >= 8;
        const hasFlair = flairHiddenInput && flairHiddenInput.value !== '';
        let isValid = true;

        if (activeTab === 'image') {
            if (activeImageSource === 'upload') {
                isValid = Boolean(selectedFile);
            } else {
                isValid = getImageUrlValue() !== '';
            }
        } else if (activeTab === 'link') {
            isValid = getLinkValue() !== '';
        }

        const isModUrlValid = validateRelatedModUrlField();

        submitButton.disabled = !(hasTitle && hasFlair && isValid && isModUrlValid);
    }

    function getContrastColor(hex) {
        if (!hex || typeof hex !== 'string') {
            return '#111827';
        }
        const normalized = hex.replace('#', '');
        if (normalized.length !== 6) {
            return '#111827';
        }
        const r = parseInt(normalized.slice(0, 2), 16);
        const g = parseInt(normalized.slice(2, 4), 16);
        const b = parseInt(normalized.slice(4, 6), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.6 ? '#111827' : '#ffffff';
    }

    function hexToRgba(hex, alpha) {
        if (!hex || typeof hex !== 'string') {
            return `rgba(17, 24, 39, ${alpha})`;
        }

        const normalized = hex.replace('#', '');
        if (normalized.length !== 6) {
            return `rgba(17, 24, 39, ${alpha})`;
        }

        const r = parseInt(normalized.slice(0, 2), 16);
        const g = parseInt(normalized.slice(2, 4), 16);
        const b = parseInt(normalized.slice(4, 6), 16);

        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function populateFlairs() {
        if (!flairPicker) {
            return;
        }

        flairPicker.textContent = '';

        const flairs = GTA6ForumCreate.flairs || [];
        flairs.forEach((flair) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.flair = flair.slug;
            button.textContent = flair.name;

            const background = flair.colors && flair.colors.background ? flair.colors.background : '#f1f5f9';
            const textColor = flair.colors && flair.colors.text ? flair.colors.text : getContrastColor(background);

            button.style.backgroundColor = background;
            button.style.color = textColor;
            button.style.setProperty('--flair-ring-color', hexToRgba(textColor, 0.35));

            flairPicker.appendChild(button);
        });
    }

    function selectFlair(slug, button) {
        if (!flairHiddenInput) {
            return;
        }

        flairHiddenInput.value = slug;
        flairPicker.querySelectorAll('button').forEach((btn) => {
            btn.classList.toggle('is-selected', btn === button);
        });
        validate();
    }

    function insertFormatting(action) {
        const textarea = getField('content', 'text');
        if (!textarea) {
            return;
        }

        const value = textarea.value;
        const selectionStart = textarea.selectionStart || 0;
        const selectionEnd = textarea.selectionEnd || 0;
        const selectedText = value.slice(selectionStart, selectionEnd);
        let replacement = selectedText;

        switch (action) {
            case 'bold':
                replacement = `<strong>${selectedText || 'Bold text'}</strong>`;
                break;
            case 'italic':
                replacement = `<em>${selectedText || 'Italic text'}</em>`;
                break;
            case 'link': {
                const label = selectedText || 'Link text';
                replacement = `<a href="https://example.com" target="_blank" rel="noopener noreferrer">${label}</a>`;
                break;
            }
            case 'code':
                replacement = `<code>${selectedText || 'code'}</code>`;
                break;
            case 'img': {
                const altText = selectedText || 'Image description';
                const sampleSrc = 'https://example.com/image.jpg';
                replacement = `<img src="${sampleSrc}" alt="${escapeAttribute(altText)}" />`;
                break;
            }
            case 'ol': {
                const items = selectedText
                    ? selectedText.split(/\r?\n/).map((item) => item.trim()).filter(Boolean)
                    : ['First item', 'Second item'];
                replacement = `<ol>\n${items.map((item) => `    <li>${escapeHtml(item)}</li>`).join('\n')}\n</ol>`;
                break;
            }
            case 'ul': {
                const items = selectedText
                    ? selectedText.split(/\r?\n/).map((item) => item.trim()).filter(Boolean)
                    : ['First item', 'Second item'];
                replacement = `<ul>\n${items.map((item) => `    <li>${escapeHtml(item)}</li>`).join('\n')}\n</ul>`;
                break;
            }
            default:
                break;
        }

        const before = value.slice(0, selectionStart);
        const after = value.slice(selectionEnd);
        textarea.value = `${before}${replacement}${after}`;

        const cursorPosition = before.length + replacement.length;
        textarea.focus();
        textarea.setSelectionRange(cursorPosition, cursorPosition);
        textarea.dispatchEvent(new Event('input'));
    }

    function buildPayload(media) {
        const payload = {
            title: getTitleValue(),
            flair: flairHiddenInput ? flairHiddenInput.value : '',
            content: '',
            type: activeTab,
            external_url: '',
            media_id: 0,
            related_mod_url: getModUrlValue(),
        };

        if (activeTab === 'text') {
            payload.content = getBodyValue();
            return payload;
        }

        if (activeTab === 'image') {
            if (activeImageSource === 'upload' && media) {
                payload.media_id = media.id;
                const altText = getTitleValue() || 'Forum image';
                payload.content = `<figure class="forum-embedded-image"><img src="${media.url}" alt="${escapeAttribute(altText)}" loading="lazy" /></figure>`;
            } else {
                const imageUrl = getImageUrlValue();
                payload.external_url = imageUrl;
                payload.content = `<figure class="forum-embedded-image"><img src="${imageUrl}" alt="" loading="lazy" /></figure>`;
            }
            return payload;
        }

        if (activeTab === 'link') {
            const url = getLinkValue();
            payload.external_url = url;
            payload.content = url;
        }

        return payload;
    }

    function uploadImage(file) {
        const data = new FormData();
        data.append('file', file, file.name);
        const title = getTitleValue();
        if (title) {
            data.append('title', title.substring(0, 80));
        }

        return wp.apiFetch({
            path: '/wp/v2/media',
            method: 'POST',
            headers: {
                'X-WP-Nonce': GTA6ForumCreate.nonce,
            },
            body: data,
        }).then((response) => ({
            id: response.id,
            url: response.source_url,
        }));
    }

    function createThread(payload) {
        return wp.apiFetch({
            url: `${GTA6ForumCreate.root}threads`,
            method: 'POST',
            headers: {
                'X-WP-Nonce': GTA6ForumCreate.nonce,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        })
            .then((response) => {
                renderStatus(GTA6ForumCreate.createTexts.success);
                if (response.thread && response.thread.permalink) {
                    window.location.href = response.thread.permalink;
                }
            })
            .catch((error) => {
                console.error(error);
                renderStatus(GTA6ForumCreate.createTexts.error, true);
                setButtonBusy(false);
                validate();
            });
    }

    function submitForm(event) {
        event.preventDefault();
        if (submitButton.disabled) {
            return;
        }

        clearStatus();
        setButtonBusy(true);

        if (activeTab === 'image' && activeImageSource === 'upload') {
            if (!selectedFile) {
                renderStatus(GTA6ForumCreate.createTexts.imageRequired, true);
                setButtonBusy(false);
                return;
            }

            renderStatus(GTA6ForumCreate.createTexts.uploading);
            uploadImage(selectedFile)
                .then((media) => {
                    if (!media || !media.id || !media.url) {
                        throw new Error('Invalid media response');
                    }
                    renderStatus(GTA6ForumCreate.createTexts.submitting);
                    return createThread(buildPayload(media));
                })
                .catch((error) => {
                    console.error(error);
                    renderStatus(GTA6ForumCreate.createTexts.uploadError, true);
                    setButtonBusy(false);
                    validate();
                });
            return;
        }

        renderStatus(GTA6ForumCreate.createTexts.submitting);
        if (!validateRelatedModUrlField()) {
            setButtonBusy(false);
            const modUrlField = getField('mod-url');
            if (modUrlField) {
                modUrlField.reportValidity();
                modUrlField.focus();
            }
            renderStatus(invalidModUrlMessage, true);
            return;
        }
        createThread(buildPayload());
    }

    function handleFileSelection(files) {
        const sourceFiles = files || (fileInput && fileInput.files ? fileInput.files : null);
        if (!sourceFiles || sourceFiles.length === 0) {
            selectedFile = null;
            updateUploadLabel();
            validate();
            return;
        }

        const file = sourceFiles[0];

        if (file.size > MAX_UPLOAD_BYTES) {
            selectedFile = null;
            if (fileInput) {
                fileInput.value = '';
            }
            updateUploadLabel();
            renderStatus(GTA6ForumCreate.createTexts.fileTooLarge, true);
            validate();
            return;
        }

        selectedFile = file;
        updateUploadLabel();
        clearStatus();
        validate();
    }

    function handleDragOver(event) {
        event.preventDefault();
        if (uploadZone) {
            uploadZone.classList.add('is-dragging');
        }
    }

    function handleDragLeave(event) {
        event.preventDefault();
        if (uploadZone) {
            uploadZone.classList.remove('is-dragging');
        }
    }

    function handleDrop(event) {
        event.preventDefault();
        if (uploadZone) {
            uploadZone.classList.remove('is-dragging');
        }
        if (event.dataTransfer && event.dataTransfer.files) {
            handleFileSelection(event.dataTransfer.files);
        }
    }

    function disableForm() {
        renderStatus(GTA6ForumCreate.createTexts.loginRequired, true);
        form.querySelectorAll('input, textarea, button').forEach((field) => {
            if (field !== statusEl) {
                field.disabled = true;
            }
        });
    }

    function bindValidation() {
        const fields = form.querySelectorAll('[data-field]');
        fields.forEach((field) => {
            field.addEventListener('input', validate);
            field.addEventListener('change', validate);
        });
    }

    if (!GTA6ForumCreate.isLoggedIn) {
        disableForm();
        return;
    }

    populateFlairs();
    bindValidation();

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const nextTab = button.getAttribute('data-tab') || '';
            if (!nextTab) {
                return;
            }
            switchTab(nextTab);
        });
    });

    imageSourceButtons.forEach((button) => {
        button.addEventListener('click', () => switchImageSource(button.getAttribute('data-image-source-option')));
    });

    if (flairPicker) {
        flairPicker.addEventListener('click', (event) => {
            const target = event.target instanceof HTMLElement ? event.target.closest('button[data-flair]') : null;
            if (!target) {
                return;
            }
            event.preventDefault();
            selectFlair(target.dataset.flair || '', target);
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', () => handleFileSelection());
    }

    if (uploadZone) {
        uploadZone.addEventListener('dragover', handleDragOver);
        uploadZone.addEventListener('dragleave', handleDragLeave);
        uploadZone.addEventListener('drop', handleDrop);
    }

    if (editorToolbar) {
        editorToolbar.addEventListener('click', (event) => {
            const target = event.target instanceof HTMLElement ? event.target.closest('[data-editor-action]') : null;
            if (!target) {
                return;
            }
            event.preventDefault();
            insertFormatting(target.getAttribute('data-editor-action'));
        });
    }

    form.addEventListener('submit', submitForm);

    switchTab(activeTab, { updateHistory: false, force: true });
    switchImageSource(activeImageSource);
})();
