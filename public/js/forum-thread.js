(function () {
    const root = document.querySelector('[data-thread-view]');
    if (!root || !window.GTA6ForumThread || !window.wp || !wp.apiFetch) {
        return;
    }

    const threadId = Number.parseInt(root.getAttribute('data-thread-id'), 10);
    const voteWrapper = root.querySelector('[data-thread-vote]');
    const scoreEl = root.querySelector('[data-thread-score]');
    const commentCountEl = root.querySelector('[data-thread-comment-count]');
    const commentsRoot = root.querySelector('[data-thread-comments-root]');
    const shareButtons = root.querySelectorAll('[data-share-trigger]');
    const bookmarkButtons = root.querySelectorAll('[data-bookmark-button]');
    const viewCountLabels = root.querySelectorAll('[data-thread-view-count-label]');

    const bookmarkEndpoint = (GTA6ForumThread.bookmark && GTA6ForumThread.bookmark.endpoint)
        ? GTA6ForumThread.bookmark.endpoint
        : (bookmarkButtons.length ? bookmarkButtons[0].getAttribute('data-bookmark-endpoint') : '');
    const bookmarkLabels = (window.GTA6ForumBookmarks && window.GTA6ForumBookmarks.labels)
        ? window.GTA6ForumBookmarks.labels
        : { add: 'Bookmark', added: 'Saved', loginRequired: 'Please sign in to save threads.', error: 'We could not update your bookmark. Please try again.' };

    const forumTexts = GTA6ForumThread.texts || {};
    const threadTexts = GTA6ForumThread.threadTexts || {};
    const commentsConfig = (GTA6ForumThread.comments && typeof GTA6ForumThread.comments === 'object') ? GTA6ForumThread.comments : {};
    const commentsEndpoint = typeof commentsConfig.endpoint === 'string' ? commentsConfig.endpoint : '';
    const commentOrder = typeof commentsConfig.orderby === 'string' ? commentsConfig.orderby : 'best';

    const commentMessages = {
        loading: threadTexts.loadingComments || 'Loading comments…',
        error: threadTexts.commentError || 'Unable to load comments.',
        empty: threadTexts.noComments || 'No comments yet. Start the conversation!',
    };

    const viewConfig = GTA6ForumThread.views || {};
    const viewEndpoint = typeof viewConfig.endpoint === 'string' ? viewConfig.endpoint : '';
    const initialViewCount = Number.isFinite(Number(viewConfig.count)) ? Number(viewConfig.count) : 0;
    const viewLabels = {
        singular: viewConfig.singular || '%s view',
        plural: viewConfig.plural || '%s views',
    };

    const formatViewLabel = (count) => {
        const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
        const template = safeCount === 1 ? viewLabels.singular : viewLabels.plural;
        return template.replace('%s', safeCount.toLocaleString());
    };

    const updateViewCountDisplay = (count) => {
        const label = formatViewLabel(count);
        viewCountLabels.forEach((element) => {
            element.textContent = label;
        });
    };

    const registerThreadView = () => {
        if (!viewEndpoint || registerThreadView.__sent) {
            return;
        }

        registerThreadView.__sent = true;

        const options = {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            keepalive: true,
        };

        fetch(viewEndpoint, options)
            .then((response) => (response && response.ok) ? response.json() : null)
            .then((payload) => {
                if (payload && typeof payload.views !== 'undefined') {
                    updateViewCountDisplay(Number(payload.views));
                }
            })
            .catch(() => {});
    };

    const scheduleViewRegistration = () => {
        if (!viewEndpoint) {
            return;
        }

        const ensureRegistered = () => {
            registerThreadView();
            document.removeEventListener('visibilitychange', onVisibilityChange);
            window.removeEventListener('pagehide', registerThreadView);
        };

        const onVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                ensureRegistered();
            }
        };

        if (document.visibilityState === 'visible') {
            window.setTimeout(registerThreadView, 600);
        } else {
            document.addEventListener('visibilitychange', onVisibilityChange);
        }

        window.addEventListener('pagehide', registerThreadView, { once: true });
    };

    function openShare(payload) {
        if (window.GTA6ForumShare && typeof window.GTA6ForumShare.open === 'function') {
            window.GTA6ForumShare.open(payload);
            return;
        }

        if (payload && payload.url && navigator.share) {
            navigator.share({
                title: payload.title || document.title,
                url: payload.url,
            }).catch(() => {});
            return;
        }

        if (payload && payload.url) {
            window.open(payload.url, '_blank', 'noopener');
        }
    }

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatCommentCount = (count) => {
        const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
        const template = safeCount === 1
            ? (threadTexts.commentSingular || '%s comment')
            : (threadTexts.commentPlural || '%s comments');
        return template.replace('%s', safeCount.toLocaleString());
    };

    const setThreadCommentCount = (count) => {
        const labelTarget = root.querySelector('[data-thread-comment-count-label]') || commentCountEl;
        if (!labelTarget) {
            return;
        }
        labelTarget.textContent = formatCommentCount(count);
    };

    const commentEditorRoots = new WeakSet();
    const COMMENT_EDITOR_ALLOWED_TAGS = new Set(['P', 'BR', 'STRONG', 'EM', 'A', 'CODE', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'IMG']);

    const isPlainEditor = (editor) => editor && editor.dataset && editor.dataset.editorMode === 'plain';

    const getPlainEditorState = (editor) => {
        if (!editor) {
            return {
                text: '',
                selectionStart: 0,
                selectionEnd: 0,
                selectedText: '',
            };
        }

        const sourceText = typeof editor.textContent === 'string'
            ? editor.textContent
            : (typeof editor.innerText === 'string' ? editor.innerText : '');
        const text = sourceText.replace(/\u00a0/g, ' ');
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) {
            const length = text.length;
            return {
                text,
                selectionStart: length,
                selectionEnd: length,
                selectedText: '',
            };
        }

        const range = selection.getRangeAt(0);
        if (!editor.contains(range.commonAncestorContainer)) {
            const length = text.length;
            return {
                text,
                selectionStart: length,
                selectionEnd: length,
                selectedText: '',
            };
        }

        const preRange = range.cloneRange();
        preRange.selectNodeContents(editor);
        preRange.setEnd(range.startContainer, range.startOffset);

        const selectionStart = preRange.toString().length;
        const selectedText = range.toString();
        const selectionEnd = selectionStart + selectedText.length;

        return {
            text,
            selectionStart,
            selectionEnd,
            selectedText,
        };
    };

    const setPlainEditorValue = (editor, value, caretPosition) => {
        if (!editor) {
            return;
        }

        editor.textContent = value;
        refreshEditorEmptyState(editor);

        const selection = window.getSelection();
        if (!selection) {
            return;
        }

        const node = editor.firstChild;
        if (!node) {
            selection.removeAllRanges();
            return;
        }

        const target = typeof caretPosition === 'number'
            ? Math.max(0, Math.min(caretPosition, node.length))
            : node.length;

        const range = document.createRange();
        range.setStart(node, target);
        range.collapse(true);

        selection.removeAllRanges();
        selection.addRange(range);
    };

    const applyPlainEditorAction = (editor, action) => {
        if (!editor) {
            return;
        }

        const state = getPlainEditorState(editor);
        const before = state.text.slice(0, state.selectionStart);
        const after = state.text.slice(state.selectionEnd);
        const selected = state.selectedText;

        let replacement = selected;

        switch (action) {
            case 'bold':
                replacement = `<strong>${selected || 'Bold text'}</strong>`;
                break;
            case 'italic':
                replacement = `<em>${selected || 'Italic text'}</em>`;
                break;
            case 'code':
                replacement = `<code>${selected || 'code'}</code>`;
                break;
            case 'img': {
                const trimmed = selected ? selected.trim() : '';
                const altText = trimmed ? escapeHtml(trimmed) : escapeHtml('Image description');
                replacement = `<img src="https://example.com/image.jpg" alt="${altText}" />`;
                break;
            }
            case 'blockquote':
                replacement = `<blockquote>${selected || 'Quote'}</blockquote>`;
                break;
            case 'link': {
                const label = selected || 'Link text';
                replacement = `<a href="https://example.com" target="_blank" rel="noopener noreferrer">${label}</a>`;
                break;
            }
            case 'ol':
            case 'ul': {
                const items = selected
                    ? selected.split(/\r?\n/).map((item) => item.trim()).filter(Boolean)
                    : ['First item', 'Second item'];
                const tagName = action === 'ol' ? 'ol' : 'ul';
                replacement = `<${tagName}>\n${items.map((item) => `    <li>${item}</li>`).join('\n')}\n</${tagName}>`;
                break;
            }
            default:
                break;
        }

        const nextValue = `${before}${replacement}${after}`;
        const caretPosition = before.length + replacement.length;
        setPlainEditorValue(editor, nextValue, caretPosition);
    };

    const sanitizeUrlForEditor = (value) => {
        if (!value) {
            return '';
        }
        try {
            const parsed = new URL(value, window.location.origin);
            const protocol = parsed.protocol.toLowerCase();
            if (protocol !== 'http:' && protocol !== 'https:') {
                return '';
            }
            return parsed.href;
        } catch (error) {
            return '';
        }
    };

    const sanitizeEditorNodes = (nodes, parentTag = null) => {
        let html = '';
        nodes.forEach((node) => {
            html += sanitizeEditorNode(node, parentTag);
        });
        return html;
    };

    const sanitizeEditorNode = (node, parentTag) => {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent.replace(/\u00a0/g, ' ');
            if (!text.trim()) {
                return '';
            }
            const escaped = escapeHtml(text).replace(/\n+/g, '<br>');
            if (!parentTag) {
                return `<p>${escaped}</p>`;
            }
            return escaped;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return '';
        }

        let tagName = node.tagName.toUpperCase();
        if (tagName === 'B') {
            tagName = 'STRONG';
        } else if (tagName === 'I') {
            tagName = 'EM';
        } else if (tagName === 'DIV') {
            tagName = 'P';
        }

        if (!COMMENT_EDITOR_ALLOWED_TAGS.has(tagName)) {
            return sanitizeEditorNodes(Array.from(node.childNodes), parentTag);
        }

        if (tagName === 'P' || tagName === 'BLOCKQUOTE') {
            const inner = sanitizeEditorNodes(Array.from(node.childNodes), tagName).trim();
            if (!inner) {
                return '';
            }
            const lower = tagName.toLowerCase();
            return `<${lower}>${inner}</${lower}>`;
        }

        if (tagName === 'BR') {
            return '<br>';
        }

        if (tagName === 'UL' || tagName === 'OL') {
            const items = Array.from(node.childNodes)
                .map((child) => sanitizeEditorNode(child, tagName))
                .filter((item) => item && item.trim() !== '');
            if (!items.length) {
                return '';
            }
            const lower = tagName.toLowerCase();
            return `<${lower}>\n${items.join('\n')}\n</${lower}>`;
        }

        if (tagName === 'LI') {
            const inner = sanitizeEditorNodes(Array.from(node.childNodes), 'LI').trim();
            if (!inner) {
                return '';
            }
            const content = `<li>${inner}</li>`;
            if (parentTag === 'UL' || parentTag === 'OL') {
                return `    ${content}`;
            }
            return content;
        }

        if (tagName === 'A') {
            const href = sanitizeUrlForEditor(node.getAttribute('href') || '');
            const inner = sanitizeEditorNodes(Array.from(node.childNodes), 'A').trim() || escapeHtml(node.textContent || '');
            if (!href) {
                return inner;
            }
            return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${inner}</a>`;
        }

        if (tagName === 'IMG') {
            const src = sanitizeUrlForEditor(node.getAttribute('src') || '');
            if (!src) {
                return '';
            }

            const attributes = [`src="${escapeHtml(src)}"`];
            const alt = node.getAttribute('alt');
            if (alt) {
                attributes.push(`alt="${escapeHtml(alt.trim())}"`);
            }

            const title = node.getAttribute('title');
            if (title) {
                attributes.push(`title="${escapeHtml(title.trim())}"`);
            }

            const width = parseInt(node.getAttribute('width') || '', 10);
            if (Number.isFinite(width) && width > 0) {
                attributes.push(`width="${width}"`);
            }

            const height = parseInt(node.getAttribute('height') || '', 10);
            if (Number.isFinite(height) && height > 0) {
                attributes.push(`height="${height}"`);
            }

            const loading = (node.getAttribute('loading') || '').toLowerCase();
            if (['lazy', 'eager', 'auto'].includes(loading)) {
                attributes.push(`loading="${escapeHtml(loading)}"`);
            }

            const decoding = (node.getAttribute('decoding') || '').toLowerCase();
            if (['async', 'auto', 'sync'].includes(decoding)) {
                attributes.push(`decoding="${escapeHtml(decoding)}"`);
            }

            return `<img ${attributes.join(' ')} />`;
        }

        const inner = sanitizeEditorNodes(Array.from(node.childNodes), tagName).trim();
        if (!inner) {
            return '';
        }

        return `<${tagName.toLowerCase()}>${inner}</${tagName.toLowerCase()}>`;
    };

    const buildPlainEditorHtml = (value) => {
        const normalized = value.replace(/\r\n?/g, '\n');
        const segments = normalized.split(/\n{2,}/);
        const fragment = segments
            .map((segment) => segment.trim())
            .filter((segment) => segment !== '')
            .map((segment) => `<p>${segment.replace(/\n/g, '<br>')}</p>`)
            .join('');

        if (!fragment) {
            return '';
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = fragment;

        return sanitizeEditorNodes(Array.from(wrapper.childNodes)).trim();
    };

    const getEditorContent = (editor) => {
        if (!editor) {
            return { html: '', text: '' };
        }

        if (isPlainEditor(editor)) {
            const sourceText = typeof editor.textContent === 'string'
                ? editor.textContent
                : (typeof editor.innerText === 'string' ? editor.innerText : '');
            const raw = (sourceText || '').replace(/\u00a0/g, ' ');
            const normalized = raw.replace(/\r\n?/g, '\n');
            const html = buildPlainEditorHtml(normalized);
            return {
                html,
                text: normalized.trim(),
            };
        }

        const clone = editor.cloneNode(true);
        clone.querySelectorAll('span.mention-tag').forEach((span) => {
            const username = span.dataset.username || span.textContent.replace(/^@/, '');
            span.replaceWith(document.createTextNode(`@${username}`));
        });
        clone.querySelectorAll('[contenteditable]').forEach((el) => {
            el.removeAttribute('contenteditable');
        });
        clone.normalize();

        const html = sanitizeEditorNodes(Array.from(clone.childNodes)).trim();
        const plainText = clone.textContent.replace(/\u00a0/g, ' ').trim();

        return { html, text: plainText };
    };

    const refreshEditorEmptyState = (editor) => {
        if (!editor) {
            return;
        }
        if (editor.textContent.trim() === '') {
            editor.classList.add('is-empty');
        } else {
            editor.classList.remove('is-empty');
        }
    };

    const getActiveRange = (editor) => {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return null;
        }
        const range = selection.getRangeAt(0);
        if (!editor.contains(range.commonAncestorContainer)) {
            return null;
        }
        return range;
    };

    const placeCaretAtEnd = (editor) => {
        const range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false);
        const selection = window.getSelection();
        if (selection) {
            selection.removeAllRanges();
            selection.addRange(range);
        }
        return range;
    };

    const insertHtmlSnippet = (editor, html, range) => {
        const targetRange = range && !range.collapsed ? range : placeCaretAtEnd(editor);
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const fragment = document.createDocumentFragment();
        let lastNode = null;
        while (temp.firstChild) {
            lastNode = temp.firstChild;
            fragment.appendChild(temp.firstChild);
        }
        targetRange.deleteContents();
        targetRange.insertNode(fragment);
        if (lastNode) {
            const selection = window.getSelection();
            if (selection) {
                const newRange = document.createRange();
                newRange.setStartAfter(lastNode);
                newRange.collapse(true);
                selection.removeAllRanges();
                selection.addRange(newRange);
            }
        }
    };

    const wrapSelectionWithTag = (editor, range, tagName, placeholderText) => {
        const selection = window.getSelection();
        const activeRange = range || placeCaretAtEnd(editor);
        if (!activeRange || activeRange.collapsed) {
            insertHtmlSnippet(editor, `<${tagName}>${escapeHtml(placeholderText)}</${tagName}>`, activeRange);
            return;
        }
        const wrapper = document.createElement(tagName);
        wrapper.appendChild(activeRange.extractContents());
        activeRange.insertNode(wrapper);
        if (selection) {
            selection.removeAllRanges();
            const newRange = document.createRange();
            newRange.selectNodeContents(wrapper);
            newRange.collapse(false);
            selection.addRange(newRange);
        }
    };

    const applyLinkAction = (editor, range) => {
        const selection = window.getSelection();
        const defaultLabel = 'Link text';
        const promptMessage = threadTexts.linkPrompt || 'Enter the URL to link to:';
        const invalidMessage = threadTexts.invalidLink || 'Please enter a valid URL starting with http:// or https://.';
        const userInput = window.prompt(promptMessage, 'https://');
        if (userInput === null) {
            return;
        }
        const sanitized = sanitizeUrlForEditor(userInput.trim());
        if (!sanitized) {
            window.alert(invalidMessage);
            return;
        }

        const activeRange = range || placeCaretAtEnd(editor);
        if (!activeRange || activeRange.collapsed) {
            const linkHtml = `<a href="${escapeHtml(sanitized)}" target="_blank" rel="noopener noreferrer">${escapeHtml(defaultLabel)}</a>`;
            insertHtmlSnippet(editor, linkHtml, activeRange);
            return;
        }

        const anchor = document.createElement('a');
        anchor.setAttribute('href', sanitized);
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
        anchor.appendChild(activeRange.extractContents());
        activeRange.insertNode(anchor);
        if (selection) {
            selection.removeAllRanges();
            const newRange = document.createRange();
            newRange.selectNodeContents(anchor);
            newRange.collapse(false);
            selection.addRange(newRange);
        }
    };

    const insertListAtSelection = (editor, range, type) => {
        const selection = window.getSelection();
        const hasSelection = range && !range.collapsed;
        const values = hasSelection
            ? range.toString().split(/\r?\n/).map((item) => item.trim()).filter(Boolean)
            : [];
        const placeholderItems = ['First item', 'Second item'];
        const items = values.length ? values : placeholderItems;
        const tagName = type === 'ol' ? 'ol' : 'ul';
        const listHtml = `<${tagName}>\n${items.map((item) => `    <li>${escapeHtml(item)}</li>`).join('\n')}\n</${tagName}>`;
        insertHtmlSnippet(editor, listHtml, hasSelection ? range : null);
        if (selection) {
            selection.collapseToEnd();
        }
    };

    const ensureActionsBarVisible = (container) => {
        if (!container) {
            return;
        }
        const actionsBar = container.querySelector('.comment-actions-bar');
        if (actionsBar) {
            actionsBar.classList.remove('hidden');
        }
    };

    const updateHiddenFields = (container) => {
        if (!container) {
            return;
        }
        const editor = container.querySelector('.comment-box-textarea');
        if (!editor) {
            return;
        }
        const { html } = getEditorContent(editor);
        const hiddenTextarea = container.querySelector('textarea[name="comment"]');
        if (hiddenTextarea) {
            hiddenTextarea.value = html;
            hiddenTextarea.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const mentionHidden = container.querySelector('input[name="comment_mentioned_users"]');
        if (mentionHidden) {
            if (isPlainEditor(editor)) {
                mentionHidden.value = '';
            } else {
                const ids = Array.from(editor.querySelectorAll('.mention-tag[data-user-id]')).map((span) => span.dataset.userId);
                const uniqueIds = Array.from(new Set(ids.filter((value) => value && value !== '0')));
                mentionHidden.value = uniqueIds.join(',');
            }
        }
        refreshEditorEmptyState(editor);
    };

    const applyEditorAction = (editor, container, action) => {
        if (!editor) {
            return;
        }

        editor.focus();
        if (isPlainEditor(editor)) {
            applyPlainEditorAction(editor, action);
            ensureActionsBarVisible(container);
            updateHiddenFields(container);
            return;
        }

        const range = getActiveRange(editor) || placeCaretAtEnd(editor);

        switch (action) {
            case 'bold':
                wrapSelectionWithTag(editor, range, 'strong', 'Bold text');
                break;
            case 'italic':
                wrapSelectionWithTag(editor, range, 'em', 'Italic text');
                break;
            case 'code':
                wrapSelectionWithTag(editor, range, 'code', 'code');
                break;
            case 'img': {
                const selectedLabel = range && !range.collapsed ? range.toString().trim() : '';
                const altText = selectedLabel ? escapeHtml(selectedLabel) : escapeHtml('Image description');
                const snippet = `<img src="https://example.com/image.jpg" alt="${altText}" />`;
                insertHtmlSnippet(editor, snippet, range && !range.collapsed ? range : null);
                break;
            }
            case 'link':
                applyLinkAction(editor, range);
                break;
            case 'ol':
            case 'ul':
                insertListAtSelection(editor, range, action);
                break;
            case 'blockquote':
                wrapSelectionWithTag(editor, range, 'blockquote', 'Quote');
                break;
            default:
                break;
        }

        ensureActionsBarVisible(container);
        updateHiddenFields(container);
    };

    const enhanceCommentEditors = (root) => {
        if (!root || commentEditorRoots.has(root)) {
            return;
        }

        root.addEventListener('click', (event) => {
            const target = event.target instanceof HTMLElement ? event.target.closest('[data-comment-action]') : null;
            if (!target) {
                return;
            }

            const container = target.closest('.comment-box-container');
            if (!container) {
                return;
            }

            const editor = container.querySelector('.comment-box-textarea');
            if (!editor) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
            }

            applyEditorAction(editor, container, target.getAttribute('data-comment-action'));
        }, true);

        root.addEventListener('keydown', (event) => {
            const editor = event.target instanceof HTMLElement && event.target.classList.contains('comment-box-textarea')
                ? event.target
                : null;
            if (!editor || !isPlainEditor(editor)) {
                return;
            }
            if (event.key !== 'Enter' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }

            event.preventDefault();
            const state = getPlainEditorState(editor);
            const before = state.text.slice(0, state.selectionStart);
            const after = state.text.slice(state.selectionEnd);
            const nextValue = `${before}\n${after}`;
            const caretPosition = before.length + 1;
            setPlainEditorValue(editor, nextValue, caretPosition);
            const container = editor.closest('.comment-box-container');
            if (container) {
                ensureActionsBarVisible(container);
                updateHiddenFields(container);
            } else {
                refreshEditorEmptyState(editor);
            }
        });

        commentEditorRoots.add(root);
    };

    function submitVote(direction) {
        const url = `${GTA6ForumThread.root}threads/${threadId}/vote`;
        return wp.apiFetch({
            url,
            method: 'POST',
            headers: {
                'X-WP-Nonce': GTA6ForumThread.nonce,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ direction }),
        });
    }

    function updateBookmarkButtonState(button, isBookmarked) {
        if (!button) {
            return;
        }

        button.dataset.bookmarked = isBookmarked ? 'true' : 'false';
        button.classList.toggle('is-active', Boolean(isBookmarked));

        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.remove('fas', 'far');
            icon.classList.add(isBookmarked ? 'fas' : 'far');
        }

        const label = button.querySelector('[data-bookmark-label]');
        if (label) {
            label.textContent = isBookmarked ? (bookmarkLabels.added || 'Saved') : (bookmarkLabels.add || 'Bookmark');
        }
    }

    updateViewCountDisplay(initialViewCount);
    scheduleViewRegistration();

    if (shareButtons.length) {
        shareButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const payload = {
                    title: (GTA6ForumThread.share && GTA6ForumThread.share.title) || button.getAttribute('data-share-title') || document.title,
                    url: (GTA6ForumThread.share && GTA6ForumThread.share.url) || button.getAttribute('data-share-url') || window.location.href,
                };

                openShare(payload);
            });
        });
    }

    function updateAllBookmarkButtons(isBookmarked) {
        bookmarkButtons.forEach((button) => {
            updateBookmarkButtonState(button, isBookmarked);
        });
    }

    if (bookmarkButtons.length) {
        updateAllBookmarkButtons(Boolean(GTA6ForumThread.bookmark && GTA6ForumThread.bookmark.isBookmarked));

        bookmarkButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                if (!window.GTA6ForumBookmarks || !window.GTA6ForumBookmarks.isLoggedIn) {
                    alert(bookmarkLabels.loginRequired || 'Please sign in to save threads.');
                    return;
                }

                if (!bookmarkEndpoint) {
                    alert(bookmarkLabels.error || 'We could not update your bookmark. Please try again.');
                    return;
                }

                bookmarkButtons.forEach((btn) => {
                    btn.disabled = true;
                });

                window.GTA6ForumBookmarks.toggle(bookmarkEndpoint)
                    .then((data) => {
                        const isBookmarked = Boolean(data && data.is_bookmarked);
                        updateAllBookmarkButtons(isBookmarked);
                    })
                    .catch((error) => {
                        if (error && error.code === 'not_logged_in') {
                            alert(bookmarkLabels.loginRequired || 'Please sign in to save threads.');
                        } else {
                            alert(bookmarkLabels.error || 'We could not update your bookmark. Please try again.');
                        }
                    })
                    .finally(() => {
                        bookmarkButtons.forEach((btn) => {
                            btn.disabled = false;
                        });
                    });
            });
        });
    }

    if (voteWrapper) {
        voteWrapper.addEventListener('click', (event) => {
            const button = event.target.closest('[data-vote]');
            if (!button) {
                return;
            }

            if (!GTA6ForumThread.isLoggedIn) {
                alert(forumTexts.loginToVote || 'Please sign in to vote.');
                return;
            }

            const direction = button.getAttribute('data-vote');
            submitVote(direction)
                .then((data) => {
                    if (scoreEl && typeof data.score !== 'undefined') {
                        scoreEl.textContent = data.score;
                    }
                    voteWrapper.querySelectorAll('.vote-button').forEach((btn) => {
                        btn.classList.remove('upvoted', 'downvoted');
                    });
                    if (data.user_vote === 1) {
                        const upButton = voteWrapper.querySelector('[data-vote="up"]');
                        if (upButton) {
                            upButton.classList.add('upvoted');
                        }
                    } else if (data.user_vote === -1) {
                        const downButton = voteWrapper.querySelector('[data-vote="down"]');
                        if (downButton) {
                            downButton.classList.add('downvoted');
                        }
                    }
                })
                .catch(() => alert(forumTexts.voteError || 'Unable to register your vote. Please try again.'));
        });
    }

    const showCommentsLoader = () => {
        if (!commentsRoot) {
            return;
        }
        commentsRoot.innerHTML = `<div class="py-12 text-center text-sm text-gray-500">${escapeHtml(commentMessages.loading)}</div>`;
    };

    const showCommentsError = (message) => {
        if (!commentsRoot) {
            return;
        }
        commentsRoot.innerHTML = `<div class="py-12 text-center text-sm text-red-500">${escapeHtml(message || commentMessages.error)}</div>`;
    };

    const initialiseCommentEnhancements = (container) => {
        if (!container) {
            return;
        }

        enhanceCommentEditors(container);

        if (!window.GTAModsComments || typeof window.GTAModsComments.init !== 'function') {
            return;
        }

        window.GTAModsComments.init(container);
        if (typeof window.GTAModsComments.scrollToHash === 'function') {
            window.setTimeout(() => {
                window.GTAModsComments.scrollToHash();
            }, 120);
        }
    };

    const renderCommentsPayload = (payload) => {
        if (!commentsRoot || !payload || typeof payload.html !== 'string') {
            showCommentsError(commentMessages.error);
            return false;
        }

        commentsRoot.innerHTML = payload.html;
        const commentsContainer = commentsRoot.querySelector('#gta6-comments');
        if (commentsContainer) {
            initialiseCommentEnhancements(commentsContainer);
        }

        if (typeof payload.count !== 'undefined') {
            setThreadCommentCount(Number(payload.count));
        }

        return true;
    };

    const loadThreadComments = () => {
        if (!commentsRoot || !commentsEndpoint) {
            return;
        }

        showCommentsLoader();

        const requestUrl = new URL(commentsEndpoint, window.location.origin);
        if (commentOrder) {
            requestUrl.searchParams.set('orderby', commentOrder);
        }

        fetch(requestUrl.toString(), {
            method: 'GET',
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                return response.json();
            })
            .then((payload) => {
                if (!renderCommentsPayload(payload) && commentsRoot) {
                    commentsRoot.innerHTML = `<div class="py-12 text-center text-sm text-gray-500">${escapeHtml(commentMessages.empty)}</div>`;
                }
            })
            .catch(() => {
                showCommentsError(commentMessages.error);
            });
    };

    const initialiseCommentsFromDom = () => {
        if (!commentsRoot) {
            return false;
        }
        const existingContainer = commentsRoot.querySelector('#gta6-comments');
        if (!existingContainer) {
            return false;
        }
        initialiseCommentEnhancements(existingContainer);
        return true;
    };

    if (typeof commentsConfig.count !== 'undefined') {
        setThreadCommentCount(Number(commentsConfig.count));
    }

    if (commentsRoot) {
        const hasInitialMarkup = initialiseCommentsFromDom();
        if (!hasInitialMarkup) {
            if (commentsEndpoint) {
                loadThreadComments();
            } else {
                commentsRoot.innerHTML = `<div class="py-12 text-center text-sm text-gray-500">${escapeHtml(commentMessages.empty)}</div>`;
            }
        }
    }

    window.addEventListener('gta6mods:comments:count-updated', (event) => {
        if (!event || !event.detail || typeof event.detail.count === 'undefined') {
            return;
        }
        const newCount = Number(event.detail.count);
        if (Number.isFinite(newCount)) {
            setThreadCommentCount(newCount);
        }
    });
})();
