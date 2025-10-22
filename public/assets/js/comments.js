(function () {
    'use strict';

    const data = window.GTA6Comments || {};
    const utils = window.GTAModsUtils || {};
    const restEndpoints = (data.restEndpoints && typeof data.restEndpoints === 'object') ? data.restEndpoints : {};
    const restNonce = typeof data.restNonce === 'string' ? data.restNonce : '';
    const formTemplate = typeof data.formHtml === 'string' ? data.formHtml : '';
    const baseHeaders = (typeof utils.buildRestHeaders === 'function')
        ? utils.buildRestHeaders(restNonce)
        : (restNonce ? { 'X-WP-Nonce': restNonce } : {});
    const commentBase = (typeof restEndpoints.commentBase === 'string' && restEndpoints.commentBase)
        ? restEndpoints.commentBase
        : (typeof restEndpoints.commentLike === 'string' ? restEndpoints.commentLike : '');
    const strings = (data.strings && typeof data.strings === 'object') ? data.strings : {};
    const loadMoreLabel = typeof strings.loadMoreComments === 'string' ? strings.loadMoreComments : 'Load more comments';
    const loadingCommentsLabel = typeof strings.loadingComments === 'string' ? strings.loadingComments : 'Loading…';
    const hideRepliesLabel = typeof strings.hideReplies === 'string' ? strings.hideReplies : 'Hide replies';
    const retractedPlaceholder = typeof strings.retractedText === 'string'
        ? strings.retractedText
        : 'The user deleted their comment.';
    const pinLabel = typeof strings.pinLabel === 'string' ? strings.pinLabel : 'Pin comment';
    const unpinLabel = typeof strings.unpinLabel === 'string' ? strings.unpinLabel : 'Unpin';
    const pinnedBadgeLabel = typeof strings.pinnedLabel === 'string' ? strings.pinnedLabel : 'Pinned comment';
    const deleteConfirmTitle = typeof strings.deleteConfirmTitle === 'string' ? strings.deleteConfirmTitle : 'Delete comment?';
    const deleteConfirmConfirm = typeof strings.deleteConfirmConfirm === 'string' ? strings.deleteConfirmConfirm : 'Delete';
    const deleteConfirmCancel = typeof strings.deleteConfirmCancel === 'string' ? strings.deleteConfirmCancel : 'Cancel';

    let currentPinnedCommentId = null;

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    };

    const initComments = (root) => {
        if (!(root instanceof HTMLElement) || root.dataset.commentsInitialized === '1') {
            return;
        }

        root.dataset.commentsInitialized = '1';

        if (!root.querySelector('.gta6-comment-form') && formTemplate) {
            const insertionTarget = root.querySelector('#gta6-comment-list');
            if (insertionTarget) {
                insertionTarget.insertAdjacentHTML('beforebegin', formTemplate);
            } else {
                const fallbackTarget = root.querySelector('#kommentek') || root;
                fallbackTarget.insertAdjacentHTML('beforeend', formTemplate);
            }
        }

        let commentForm = root.querySelector('#commentform');
        const mentionSuggestions = document.getElementById('gta6-mention-suggestions');
        const giphyModal = document.getElementById('gta6-giphy-modal');
        const giphyResults = document.getElementById('gta6-giphy-results');
        const giphySearchInput = document.getElementById('gta6-giphy-search');
        const giphyCloseButton = document.getElementById('gta6-giphy-close');
        let commentList = root.querySelector('#gta6-comment-list');
        const noCommentsMessage = root.querySelector('#gta6-no-comments');
        const feedback = root.querySelector('#gta6-comment-feedback');
        const commentCountHeading = root.querySelector('[data-comment-count-label]');
        const sortDropdown = root.querySelector('#gta6-comment-sort');

        let activeGifContainer = null;
        let mentionSearchTimer = null;
        let currentMentionState = null;
        let deleteModalOverlay = null;
        let deleteModalDialog = null;
        let deleteModalResolver = null;
        let deleteModalMessage = null;
        let deleteModalTitle = null;
        let deleteModalConfirmButton = null;
        let deleteModalCancelButton = null;

        initialiseCommentBoxes(root);
        setupEventDelegation(root);
        setupFormSubmit(commentForm);
        setupGiphyModal();
        setupSortHandler(sortDropdown);
        setupLoadMoreHandler(root);
        detectPinnedCommentFromDom(root);
        applyReplyCollapsing(root);
        if (!window.gta6CommentsHashListener) {
            window.addEventListener('hashchange', () => {
                window.setTimeout(scrollToCommentFromHash, 60);
            });
            window.gta6CommentsHashListener = true;
        }
        window.setTimeout(scrollToCommentFromHash, 120);

        if (commentForm) {
            commentForm.setAttribute('enctype', 'multipart/form-data');
        }
        
        function createSkeletonLoader(count = 3) {
            const skeleton = document.createElement('div');
            skeleton.className = 'space-y-4';
            // Ha van komment, annyi csontvázat generálunk, amennyi van. Ha nincs, akkor 5-öt, hogy ne legyen üres.
            const numSkeletons = count > 0 ? count : 5; 

            for (let i = 0; i < numSkeletons; i++) {
                const item = document.createElement('div');
                item.className = 'animate-pulse flex space-x-3';
                item.innerHTML = `
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-gray-200 rounded-full"></div>
                    </div>
                    <div class="flex-1 space-y-3 py-1">
                        <div class="h-2 bg-gray-200 rounded w-1/4"></div>
                        <div class="space-y-2">
                            <div class="h-2 bg-gray-200 rounded"></div>
                            <div class="h-2 bg-gray-200 rounded w-5/6"></div>
                        </div>
                    </div>
                `;
                skeleton.appendChild(item);
            }
            return skeleton;
        }

        function setupSortHandler(dropdown) {
            if (!dropdown) return;

            dropdown.addEventListener('change', () => {
                const sortOrder = dropdown.value;
                const postId = data.post_id;

                if (!postId || !commentList) return;

                clearFeedback();

                const commentCount = commentList.querySelectorAll('.comment-wrapper').length;
                const skeleton = createSkeletonLoader(commentCount);
                commentList.innerHTML = '';
                commentList.appendChild(skeleton);

                if (!restEndpoints.comments) {
                    return;
                }

                const url = new URL(restEndpoints.comments, window.location.origin);
                url.searchParams.set('orderby', sortOrder || 'best');
                url.searchParams.set('page', '1');

                fetch(url.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { ...baseHeaders },
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.json();
                    })
                    .then((payload) => {
                        if (!payload || typeof payload.html !== 'string') {
                            throw new Error('Invalid response');
                        }

                        const parser = new window.DOMParser();
                        const doc = parser.parseFromString(payload.html, 'text/html');
                        const parsedRoot = doc.querySelector('#gta6-comments');

                        if (!parsedRoot) {
                            throw new Error('Missing comments wrapper');
                        }

                        const parsedList = parsedRoot.querySelector('#gta6-comment-list');
                        if (parsedList) {
                            if (commentList && commentList.parentNode) {
                                commentList.replaceWith(parsedList);
                            }
                            commentList = parsedList;
                            initialiseCommentBoxes(commentList);
                            applyReplyCollapsing(commentList);
                        } else if (commentList) {
                            commentList.innerHTML = '';
                        }

                        const parsedFormWrapper = parsedRoot.querySelector('.gta6-comment-form');
                        if (parsedFormWrapper) {
                            const currentFormWrapper = root.querySelector('.gta6-comment-form');
                            if (currentFormWrapper) {
                                currentFormWrapper.replaceWith(parsedFormWrapper);
                            } else {
                                const kommentek = root.querySelector('#kommentek');
                                if (kommentek) {
                                    kommentek.insertBefore(parsedFormWrapper, kommentek.querySelector('#gta6-comment-list'));
                                } else {
                                    root.appendChild(parsedFormWrapper);
                                }
                            }
                            commentForm = parsedFormWrapper.querySelector('#commentform');
                            if (commentForm) {
                                commentForm.setAttribute('enctype', 'multipart/form-data');
                            }
                            initialiseCommentBoxes(parsedFormWrapper);
                            setupFormSubmit(commentForm);
                        }

                        updatePaginationFromMarkup(parsedRoot);

                        const parsedHeading = parsedRoot.querySelector('[data-comment-count-label]');
                        if (parsedHeading && commentCountHeading) {
                            if (parsedHeading.dataset.templateSingular) {
                                commentCountHeading.dataset.templateSingular = parsedHeading.dataset.templateSingular;
                            }
                            if (parsedHeading.dataset.templatePlural) {
                                commentCountHeading.dataset.templatePlural = parsedHeading.dataset.templatePlural;
                            }
                            commentCountHeading.textContent = parsedHeading.textContent;
                        }

                        if (sortDropdown && typeof payload.orderby === 'string') {
                            sortDropdown.value = payload.orderby;
                        }

                        const parsedNoComments = parsedRoot.querySelector('#gta6-no-comments');
                        if (parsedNoComments && noCommentsMessage) {
                            noCommentsMessage.className = parsedNoComments.className;
                            noCommentsMessage.textContent = parsedNoComments.textContent;
                        }

                        if (noCommentsMessage) {
                            const hasComments = !!(commentList && commentList.querySelector('.comment-wrapper'));
                            if (hasComments) {
                                noCommentsMessage.classList.add('hidden');
                            } else {
                                noCommentsMessage.classList.remove('hidden');
                            }
                        }

                        const payloadPinned = (typeof payload.pinned_comment_id !== 'undefined')
                            ? parseInt(payload.pinned_comment_id, 10)
                            : null;
                        if (Number.isInteger(payloadPinned) && payloadPinned > 0) {
                            setPinnedComment(payloadPinned);
                        } else {
                            detectPinnedCommentFromDom(commentList || root);
                        }
                        window.setTimeout(scrollToCommentFromHash, 120);

                        if (typeof payload.count !== 'undefined') {
                            updateCommentCount(payload.count);
                        }
                    })
                    .catch(() => {
                        if (commentList) {
                            commentList.innerHTML = '';
                        }
                        showFeedback(data.strings.errorGeneric || 'Something went wrong.', 'error');
                    });
            });
        }

        function setupLoadMoreHandler(scope) {
            const target = scope instanceof HTMLElement ? scope : root;
            const pagination = target.querySelector('#gta6-comment-pagination');

            if (!pagination) {
                return;
            }

            const button = pagination.querySelector('[data-action="load-more-comments"]');
            if (!button) {
                return;
            }

            button.dataset.loading = '0';
            button.disabled = false;

            const defaultLabel = button.querySelector('[data-default-label]');
            if (defaultLabel) {
                defaultLabel.textContent = loadMoreLabel;
                defaultLabel.classList.remove('hidden');
            }

            const loadingLabel = button.querySelector('[data-loading-label]');
            if (loadingLabel) {
                loadingLabel.textContent = loadingCommentsLabel;
                loadingLabel.classList.add('hidden');
            }
        }

        function updatePaginationFromMarkup(parsedRoot) {
            const parsedPagination = parsedRoot ? parsedRoot.querySelector('#gta6-comment-pagination') : null;
            const currentPagination = root.querySelector('#gta6-comment-pagination');
            const kommentek = root.querySelector('#kommentek');

            if (parsedPagination) {
                if (currentPagination) {
                    currentPagination.replaceWith(parsedPagination);
                } else if (kommentek) {
                    kommentek.appendChild(parsedPagination);
                } else {
                    root.appendChild(parsedPagination);
                }
            } else if (currentPagination) {
                currentPagination.remove();
            }

            setupLoadMoreHandler(root);
        }

        function handleLoadMore(button) {
            if (!restEndpoints.comments || !commentList) {
                return;
            }

            const pagination = button.closest('#gta6-comment-pagination');
            if (!pagination) {
                return;
            }

            if (button.dataset.loading === '1') {
                return;
            }

            const currentPage = parseInt(pagination.dataset.currentPage || '1', 10);
            const totalPages = parseInt(pagination.dataset.totalPages || '1', 10);
            const perPage = parseInt(pagination.dataset.perPage || '15', 10);
            const orderValue = (sortDropdown && sortDropdown.value) || pagination.dataset.orderby || 'best';

            if (currentPage >= totalPages) {
                return;
            }

            button.dataset.loading = '1';
            button.disabled = true;

            const defaultLabel = button.querySelector('[data-default-label]');
            const loadingLabel = button.querySelector('[data-loading-label]');

            if (defaultLabel) {
                defaultLabel.classList.add('hidden');
            }
            if (loadingLabel) {
                loadingLabel.textContent = loadingCommentsLabel;
                loadingLabel.classList.remove('hidden');
            }

            const url = new URL(restEndpoints.comments, window.location.origin);
            url.searchParams.set('orderby', orderValue || 'best');
            url.searchParams.set('page', String(currentPage + 1));
            if (perPage > 0) {
                url.searchParams.set('per_page', String(perPage));
            }

            fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { ...baseHeaders },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((payload) => {
                    if (!payload || typeof payload.html !== 'string') {
                        throw new Error('Invalid response');
                    }

                    const parser = new window.DOMParser();
                    const doc = parser.parseFromString(payload.html, 'text/html');
                    const parsedRoot = doc.querySelector('#gta6-comments');

                    if (!parsedRoot) {
                        throw new Error('Missing comments wrapper');
                    }

                    const parsedList = parsedRoot.querySelector('#gta6-comment-list');
                    if (parsedList && commentList) {
                        applyReplyCollapsing(parsedList);
                        const newComments = Array.from(parsedList.children);
                        newComments.forEach((node) => {
                            if (node instanceof HTMLElement) {
                                commentList.appendChild(node);
                            }
                        });
                        initialiseCommentBoxes(commentList);
                    }

                    updatePaginationFromMarkup(parsedRoot);

                    const parsedHeading = parsedRoot.querySelector('[data-comment-count-label]');
                    if (parsedHeading && commentCountHeading) {
                        if (parsedHeading.dataset.templateSingular) {
                            commentCountHeading.dataset.templateSingular = parsedHeading.dataset.templateSingular;
                        }
                        if (parsedHeading.dataset.templatePlural) {
                            commentCountHeading.dataset.templatePlural = parsedHeading.dataset.templatePlural;
                        }
                        commentCountHeading.textContent = parsedHeading.textContent;
                    }

                    const parsedNoComments = parsedRoot.querySelector('#gta6-no-comments');
                    if (parsedNoComments && noCommentsMessage) {
                        noCommentsMessage.className = parsedNoComments.className;
                        noCommentsMessage.textContent = parsedNoComments.textContent;
                    }

                    if (noCommentsMessage) {
                        const hasComments = !!(commentList && commentList.querySelector('.comment-wrapper'));
                        if (hasComments) {
                            noCommentsMessage.classList.add('hidden');
                        } else {
                            noCommentsMessage.classList.remove('hidden');
                        }
                    }

                    if (typeof payload.count !== 'undefined') {
                        updateCommentCount(payload.count);
                    }

                    const payloadPinned = (typeof payload.pinned_comment_id !== 'undefined')
                        ? parseInt(payload.pinned_comment_id, 10)
                        : null;

                    if (Number.isInteger(payloadPinned) && payloadPinned > 0) {
                        setPinnedComment(payloadPinned);
                    } else {
                        detectPinnedCommentFromDom(commentList || root);
                    }
                })
                .catch((error) => {
                    const message = (error && error.message)
                        ? error.message
                        : (strings.errorGeneric || 'Something went wrong. Please try again.');
                    showFeedback(message, 'error');
                })
                .finally(() => {
                    if (button && button.isConnected) {
                        button.dataset.loading = '0';
                        button.disabled = false;
                        if (defaultLabel) {
                            defaultLabel.textContent = loadMoreLabel;
                            defaultLabel.classList.remove('hidden');
                        }
                        if (loadingLabel) {
                            loadingLabel.textContent = loadingCommentsLabel;
                            loadingLabel.classList.add('hidden');
                        }
                    }
                });
        }

        function formatReplyToggleLabel(count) {
            if (!Number.isInteger(count) || count <= 0) {
                return '';
            }

            let template = '';

            if (typeof strings.viewMoreReplies === 'string' && strings.viewMoreReplies) {
                template = strings.viewMoreReplies;
            } else {
                template = count === 1
                    ? 'Show %d more reply'
                    : 'Show %d more replies';
            }

            if (template.includes('%d')) {
                return template.replace('%d', String(count));
            }

            return template;
        }

        function getDirectHiddenReplies(container) {
            if (!(container instanceof HTMLElement)) {
                return [];
            }

            return Array.from(container.children).filter(
                (child) => child instanceof HTMLElement && child.classList.contains('gta6-reply-hidden')
            );
        }

        function ensureReplyToggleForContainer(repliesContainer, parentComment, newCommentElement, explicitParentId) {
            if (!(repliesContainer instanceof HTMLElement)) {
                return;
            }

            const hiddenReplies = getDirectHiddenReplies(repliesContainer);
            const hiddenCount = hiddenReplies.length;

            const resolveParentId = () => {
                if (explicitParentId) {
                    return String(explicitParentId);
                }

                if (repliesContainer.dataset.parentId) {
                    return repliesContainer.dataset.parentId;
                }

                if (parentComment && typeof parentComment.id === 'string') {
                    const match = parentComment.id.match(/comment-(\d+)/);
                    if (match && match[1]) {
                        return match[1];
                    }
                }

                return '';
            };

            const parentId = resolveParentId();

            if (parentId) {
                repliesContainer.dataset.parentId = parentId;
            }

            if (hiddenCount <= 0) {
                delete repliesContainer.dataset.hiddenCount;

                if (parentComment && parentId) {
                    const existingToggle = parentComment.querySelector(`.gta6-reply-toggle[data-parent-id="${parentId}"]`);
                    if (existingToggle) {
                        existingToggle.remove();
                    }
                }

                return;
            }

            repliesContainer.dataset.hiddenCount = String(hiddenCount);

            const label = formatReplyToggleLabel(hiddenCount);
            const toggleSelector = parentId
                ? `.gta6-reply-toggle[data-parent-id="${parentId}"]`
                : '.gta6-reply-toggle';

            let toggleButton = null;

            if (parentComment) {
                toggleButton = parentComment.querySelector(toggleSelector);
            }

            if (!toggleButton) {
                const siblingToggle = repliesContainer.nextElementSibling;
                if (siblingToggle instanceof HTMLElement && siblingToggle.classList.contains('gta6-reply-toggle')) {
                    toggleButton = siblingToggle;
                }
            }

            if (!toggleButton) {
                toggleButton = document.createElement('button');
                toggleButton.type = 'button';
                toggleButton.className = 'gta6-reply-toggle mt-3 text-xs font-semibold text-pink-600 hover:text-pink-700 transition';
                toggleButton.dataset.action = 'toggle-replies';
                toggleButton.dataset.state = 'collapsed';
                if (parentId) {
                    toggleButton.dataset.parentId = parentId;
                }
                toggleButton.dataset.hiddenCount = String(hiddenCount);
                if (label) {
                    toggleButton.dataset.defaultLabel = label;
                    toggleButton.textContent = label;
                }
                repliesContainer.insertAdjacentElement('afterend', toggleButton);
            } else {
                toggleButton.dataset.hiddenCount = String(hiddenCount);
                if (label) {
                    toggleButton.dataset.defaultLabel = label;
                    if (toggleButton.dataset.state !== 'expanded') {
                        toggleButton.textContent = label;
                    }
                }
                if (!toggleButton.dataset.state) {
                    toggleButton.dataset.state = 'collapsed';
                }
            }

            if (toggleButton && toggleButton.dataset.state === 'expanded' && newCommentElement instanceof HTMLElement) {
                newCommentElement.classList.remove('hidden');
            }
        }

        function applyReplyCollapsing(scope) {
            const target = scope instanceof HTMLElement ? scope : root;
            const replyContainers = target.querySelectorAll('.comment-replies');

            replyContainers.forEach((repliesContainer) => {
                if (!repliesContainer.dataset.replyState) {
                    repliesContainer.dataset.replyState = 'collapsed';
                }
            });

            const toggleButtons = target.querySelectorAll('.gta6-reply-toggle');
            toggleButtons.forEach((button) => {
                if (!button.dataset.defaultLabel) {
                    button.dataset.defaultLabel = button.textContent.trim();
                }
                if (!button.dataset.state) {
                    button.dataset.state = 'collapsed';
                }
            });
        }

        function toggleReplies(button) {
            let parentWrapper = button.closest('.comment-wrapper');

            if (!parentWrapper) {
                const parentId = button.dataset.parentId;
                if (parentId) {
                    parentWrapper = root.querySelector(`#comment-${parentId}`);
                }
            }

            if (!parentWrapper) {
                return;
            }

            const repliesContainer = Array.from(parentWrapper.children).find(
                (child) => child instanceof HTMLElement && child.classList.contains('comment-replies')
            );

            if (!repliesContainer) {
                return;
            }

            const hiddenReplies = getDirectHiddenReplies(repliesContainer);

            if (hiddenReplies.length === 0) {
                button.remove();
                return;
            }

            const isExpanded = button.dataset.state === 'expanded';

            if (!isExpanded) {
                hiddenReplies.forEach((reply) => {
                    reply.classList.remove('hidden');
                });
                button.dataset.state = 'expanded';
                button.textContent = hideRepliesLabel;
                repliesContainer.dataset.replyState = 'expanded';
            } else {
                hiddenReplies.forEach((reply) => {
                    if (!reply.classList.contains('hidden')) {
                        reply.classList.add('hidden');
                    }
                });
                button.dataset.state = 'collapsed';
                if (button.dataset.defaultLabel) {
                    button.textContent = button.dataset.defaultLabel;
                }
                repliesContainer.dataset.replyState = 'collapsed';
            }
        }

        function updateCollapseVisual(wrapper) {
            if (!wrapper) {
                return;
            }
            const clickableArea = wrapper.querySelector('.clickable-area');
            if (!clickableArea) {
                return;
            }
            const avatar = clickableArea.querySelector('.avatar');
            const collapsedIcon = clickableArea.querySelector('.collapsed-icon');
            const isCollapsed = wrapper.classList.contains('is-collapsed');
            if (avatar) {
                avatar.classList.toggle('hidden', isCollapsed);
            }
            if (collapsedIcon) {
                collapsedIcon.classList.toggle('hidden', !isCollapsed);
            }
            clickableArea.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        }

        function toggleCommentWrapper(wrapper) {
            if (!wrapper) {
                return;
            }

            wrapper.classList.toggle('is-collapsed');
            updateCollapseVisual(wrapper);

            if (wrapper.dataset.collapsedByPin === '1' && !wrapper.classList.contains('is-collapsed')) {
                delete wrapper.dataset.collapsedByPin;
            }
        }

        function initialiseCommentBoxes(scope) {
            const editors = scope.querySelectorAll('.comment-box-textarea');
            editors.forEach((editor) => {
                if (!editor.dataset.defaultPlaceholder) {
                    editor.dataset.defaultPlaceholder = editor.dataset.placeholder || '';
                }
                refreshEditorEmptyState(editor);
            });
            const wrappers = scope.querySelectorAll('.comment-wrapper');
            wrappers.forEach((wrapper) => updateCollapseVisual(wrapper));
        }

        function setupEventDelegation(scope) {
            scope.addEventListener('focusin', (event) => {
                if (event.target.classList.contains('comment-box-textarea')) {
                    const container = event.target.closest('.comment-box-container');
                    if (!container) {
                        return;
                    }
                    clearFeedback();
                    event.target.classList.remove('is-empty');
                    const actions = container.querySelector('.comment-actions-bar');
                    if (actions) {
                        actions.classList.remove('hidden');
                    }
                }
            });

            scope.addEventListener('focusout', (event) => {
                if (event.target.classList.contains('comment-box-textarea')) {
                    const container = event.target.closest('.comment-box-container');
                    if (!container) {
                        return;
                    }
                    setTimeout(() => {
                        const actions = container.querySelector('.comment-actions-bar');
                        const suggestionsHasFocus = mentionSuggestions && mentionSuggestions.contains(document.activeElement);
                        if (!container.contains(document.activeElement) && !suggestionsHasFocus) {
                            if (actions) {
                                actions.classList.add('hidden');
                            }
                            hideMentionSuggestions();
                            if (event.target.textContent.trim() === '' && !container.classList.contains('has-preview')) {
                                event.target.classList.add('is-empty');
                            }
                        }
                    }, 120);
                }
            });

            scope.addEventListener('input', (event) => {
                if (event.target.classList.contains('comment-box-textarea')) {
                    const editor = event.target;
                    const container = editor.closest('.comment-box-container');
                    if (!container) {
                        return;
                    }
                    // The is-empty class is managed by focusin and focusout handlers
                    // to prevent the placeholder from reappearing during editing.
                    updateHiddenFields(container);
                    handleMentionInput(editor);
                }
            });

            scope.addEventListener('change', (event) => {
                if (event.target.classList.contains('comment-image-input')) {
                    handleImageSelection(event.target);
                }
            });

            scope.addEventListener('keydown', (event) => {
                if (
                    event.target.classList.contains('comment-box-textarea')
                    && isPlainEditor(event.target)
                    && event.key === 'Enter'
                    && !event.shiftKey
                    && !event.altKey
                    && !event.ctrlKey
                    && !event.metaKey
                ) {
                    event.preventDefault();
                    const editor = event.target;
                    const state = getPlainEditorState(editor);
                    const before = state.text.slice(0, state.selectionStart);
                    const after = state.text.slice(state.selectionEnd);
                    const nextValue = `${before}\n${after}`;
                    const caretPosition = before.length + 1;
                    setPlainEditorValue(editor, nextValue, caretPosition);
                    const container = editor.closest('.comment-box-container');
                    if (container) {
                        updateHiddenFields(container);
                    } else {
                        refreshEditorEmptyState(editor);
                    }
                    return;
                }

                if (event.target.classList.contains('comment-box-textarea') && event.key === 'Backspace') {
                    const selection = window.getSelection();
                    if (!selection || selection.rangeCount === 0) {
                        return;
                    }
                    const range = selection.getRangeAt(0);
                    if (!range.collapsed) {
                        return;
                    }
                    const container = event.target.closest('.comment-box-container');
                    if (!container) {
                        return;
                    }
                    const editor = container.querySelector('.comment-box-textarea');
                    if (!editor) {
                        return;
                    }
                    const node = range.startContainer;
                    if (node && node.nodeType === Node.TEXT_NODE && range.startOffset === 0) {
                        const prev = node.previousSibling;
                        if (prev && prev.nodeType === Node.ELEMENT_NODE && prev.classList.contains('mention-tag')) {
                            event.preventDefault();
                            prev.remove();
                            updateHiddenFields(container);
                        }
                    } else if (node && node.nodeType === Node.ELEMENT_NODE) {
                        const child = node.childNodes[range.startOffset - 1];
                        if (child && child.nodeType === Node.ELEMENT_NODE && child.classList && child.classList.contains('mention-tag')) {
                            event.preventDefault();
                            child.remove();
                            updateHiddenFields(container);
                        }
                    }
                }

                if (event.target.classList.contains('clickable-area') && (event.key === 'Enter' || event.key === ' ')) {
                    event.preventDefault();
                    const wrapper = event.target.closest('.comment-wrapper');
                    toggleCommentWrapper(wrapper);
                }
            });

            scope.addEventListener('click', (event) => {
                // MODIFICATION START: Handle lightbox for each image individually
                const lightboxItem = event.target.closest('.comment-lightbox-item');
                if (lightboxItem) {
                    event.preventDefault();

                    if (typeof PhotoSwipeLightbox !== 'function' || typeof PhotoSwipe === 'undefined') {
                        window.open(lightboxItem.href, '_blank');
                        return;
                    }
                    
                    const thumbnailImage = lightboxItem.querySelector('img');

                    const dataSource = [{
                        src: lightboxItem.href,
                        width: parseInt(lightboxItem.dataset.pswpWidth || '0', 10),
                        height: parseInt(lightboxItem.dataset.pswpHeight || '0', 10),
                        alt: thumbnailImage?.alt || '',
                        element: thumbnailImage || lightboxItem
                    }];

                    const lightbox = new PhotoSwipeLightbox({
                        dataSource,
                        pswpModule: PhotoSwipe,
                        showHideAnimationType: 'zoom'
                    });

                    const openLightbox = () => {
                        lightbox.init();
                        lightbox.loadAndOpen(0);
                    };

                    if (dataSource[0].width > 0 && dataSource[0].height > 0) {
                        openLightbox();
                        return;
                    }

                    const fallbackWidth = thumbnailImage?.naturalWidth || thumbnailImage?.width || 480;
                    const fallbackHeight = thumbnailImage?.naturalHeight || thumbnailImage?.height || 270;

                    const preloadImage = new Image();
                    preloadImage.onload = () => {
                        dataSource[0].width = preloadImage.naturalWidth || fallbackWidth;
                        dataSource[0].height = preloadImage.naturalHeight || fallbackHeight;
                        openLightbox();
                    };
                    preloadImage.onerror = () => {
                        dataSource[0].width = fallbackWidth;
                        dataSource[0].height = fallbackHeight;
                        openLightbox();
                    };
                    preloadImage.src = dataSource[0].src;
                    return;
                }
                // MODIFICATION END

                const loadMoreButton = event.target.closest('[data-action="load-more-comments"]');
                if (loadMoreButton) {
                    event.preventDefault();
                    handleLoadMore(loadMoreButton);
                    return;
                }

                const toggleRepliesButton = event.target.closest('[data-action="toggle-replies"]');
                if (toggleRepliesButton) {
                    event.preventDefault();
                    toggleReplies(toggleRepliesButton);
                    return;
                }

                const uploadButton = event.target.closest('.upload-image-btn');
                if (uploadButton) {
                    event.preventDefault();
                    const container = uploadButton.closest('.comment-box-container');
                    if (container) {
                        const fileInput = container.querySelector('.comment-image-input');
                        if (fileInput) {
                            fileInput.click();
                        }
                    }
                    return;
                }

                const removeImageButton = event.target.closest('.remove-image-btn');
                if (removeImageButton) {
                    event.preventDefault();
                    const container = removeImageButton.closest('.comment-box-container');
                    if (container) {
                        clearImagePreview(container);
                    }
                    return;
                }

                const gifButton = event.target.closest('.gif-btn');
                if (gifButton) {
                    event.preventDefault();
                    const container = gifButton.closest('.comment-box-container');
                    if (container) {
                        openGiphy(container);
                    }
                    return;
                }

                const removeGifButton = event.target.closest('.remove-gif-btn');
                if (removeGifButton) {
                    event.preventDefault();
                    const container = removeGifButton.closest('.comment-box-container');
                    if (container) {
                        clearGifPreview(container);
                    }
                    return;
                }

                const toolbarButton = event.target.closest('[data-comment-action]');
                if (toolbarButton) {
                    event.preventDefault();
                    const container = toolbarButton.closest('.comment-box-container');
                    if (!container) {
                        return;
                    }
                    const editor = container.querySelector('.comment-box-textarea');
                    if (editor) {
                        applyEditorAction(editor, toolbarButton.getAttribute('data-comment-action'));
                    }
                    return;
                }

                const mentionButton = event.target.closest('.mention-btn');
                if (mentionButton) {
                    event.preventDefault();
                    const container = mentionButton.closest('.comment-box-container');
                    if (!container) {
                        return;
                    }
                    const editor = container.querySelector('.comment-box-textarea');
                    if (!editor) {
                        return;
                    }
                    insertAtCursor(editor, '@');
                    handleMentionInput(editor);
                    editor.focus();
                    return;
                }

                const replyBtn = event.target.closest('.reply-btn');
                if (replyBtn) {
                    event.preventDefault();
                    const commentId = replyBtn.dataset.commentId;
                    toggleReplyForm(commentId);
                    return;
                }

                const cancelReplyBtn = event.target.closest('.cancel-reply-btn');
                if (cancelReplyBtn) {
                    event.preventDefault();
                    const commentId = cancelReplyBtn.dataset.commentId;
                    const formContainer = document.getElementById(`reply-form-container-${commentId}`);
                    if (formContainer) {
                        formContainer.classList.add('hidden');
                    }
                    return;
                }

                const postReplyBtn = event.target.closest('.post-reply-btn');
                if (postReplyBtn) {
                    event.preventDefault();
                    const commentId = postReplyBtn.dataset.commentId;
                    const replyContainer = document.getElementById(`reply-form-container-${commentId}`);
                    if (replyContainer) {
                        submitReply(replyContainer, commentId, postReplyBtn);
                    }
                    return;
                }

                const likeButton = event.target.closest('.comment-like-btn');
                if (likeButton) {
                    event.preventDefault();
                    handleLikeButton(likeButton);
                    return;
                }

                const retractButton = event.target.closest('[data-action="retract-comment"]');
                if (retractButton) {
                    event.preventDefault();
                    handleCommentRetract(retractButton);
                    return;
                }

                const pinButton = event.target.closest('[data-action="pin-comment"]');
                if (pinButton) {
                    event.preventDefault();
                    handleCommentPin(pinButton);
                    return;
                }

                const copyLinkButton = event.target.closest('[data-action="copy-comment-link"]');
                if (copyLinkButton) {
                    event.preventDefault();
                    handleCommentCopyLink(copyLinkButton);
                    return;
                }

                const menuToggle = event.target.closest('.menu-toggle-btn');
                if (menuToggle) {
                    event.preventDefault();
                    toggleCommentMenu(menuToggle);
                    return;
                }

                const clickableArea = event.target.closest('.clickable-area');
                if (clickableArea) {
                    const wrapper = clickableArea.closest('.comment-wrapper');
                    toggleCommentWrapper(wrapper);
                    return;
                }
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('.menu-toggle-btn')) {
                    closeAllMenus();
                }

                const mentionItem = event.target.closest('.mention-item');
                if (mentionItem && mentionSuggestions && mentionSuggestions.contains(mentionItem)) {
                    event.preventDefault();
                    applyMentionSelection(mentionItem.dataset.userId, mentionItem.dataset.username, mentionItem.dataset.avatar);
                }
            });
        }

        const ALLOWED_EDITOR_TAGS = new Set(['P', 'BR', 'STRONG', 'EM', 'A', 'CODE', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'IMG']);

        function isPlainEditor(editor) {
            return !!(editor && editor.dataset && editor.dataset.editorMode === 'plain');
        }

        function getPlainEditorState(editor) {
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
        }

        function setPlainEditorValue(editor, value, caretPosition) {
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
        }

        function applyPlainEditorAction(editor, action) {
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
                case 'blockquote':
                    replacement = `<blockquote>${selected || 'Quote'}</blockquote>`;
                    break;
                case 'img': {
                    const sampleSrc = strings.editorImageSample || 'https://example.com/image.jpg';
                    const placeholderAlt = strings.editorImageAlt || 'Image description';
                    const trimmed = selected ? selected.trim() : '';
                    const altText = trimmed ? escapeHtml(trimmed) : escapeHtml(placeholderAlt);
                    replacement = `<img src="${sampleSrc}" alt="${altText}" />`;
                    break;
                }
                case 'link': {
                    const promptMessage = strings.editorLinkPrompt || 'Enter the URL to link to:';
                    const invalidMessage = strings.editorLinkInvalid || 'Please enter a valid URL starting with http:// or https://.';
                    const input = window.prompt(promptMessage, 'https://');
                    if (input === null) {
                        return;
                    }
                    const sanitized = sanitizeUrl(input.trim());
                    if (!sanitized) {
                        window.alert(invalidMessage);
                        return;
                    }
                    const label = selected || 'Link text';
                    replacement = `<a href="${sanitized}" target="_blank" rel="noopener noreferrer">${label}</a>`;
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
        }

        function sanitizeUrl(value) {
            if (!value) {
                return '';
            }
            try {
                const url = new URL(value, window.location.origin);
                const protocol = url.protocol.toLowerCase();
                if (protocol !== 'http:' && protocol !== 'https:') {
                    return '';
                }
                return url.href;
            } catch (error) {
                return '';
            }
        }

        function sanitizeEditorNodes(nodes, parentTag = null) {
            let html = '';
            nodes.forEach((node) => {
                html += sanitizeEditorNode(node, parentTag);
            });
            return html;
        }

        function sanitizeEditorNode(node, parentTag) {
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

            if (!ALLOWED_EDITOR_TAGS.has(tagName)) {
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
                const href = sanitizeUrl(node.getAttribute('href') || '');
                const inner = sanitizeEditorNodes(Array.from(node.childNodes), 'A').trim() || escapeHtml(node.textContent || '');
                if (!href) {
                    return inner;
                }
                return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${inner}</a>`;
            }

            if (tagName === 'IMG') {
                const src = sanitizeUrl(node.getAttribute('src') || '');
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
        }

        function buildPlainEditorHtml(value) {
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
        }

        function getEditorContent(editor) {
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

            return {
                html,
                text: plainText,
            };
        }

        function refreshEditorEmptyState(editor) {
            if (!editor) {
                return;
            }
            if (editor.textContent.trim() === '') {
                editor.classList.add('is-empty');
            } else {
                editor.classList.remove('is-empty');
            }
        }

        function getActiveRange(editor) {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) {
                return null;
            }
            const range = selection.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) {
                return null;
            }
            return range;
        }

        function placeCaretAtEnd(editor) {
            const range = document.createRange();
            range.selectNodeContents(editor);
            range.collapse(false);
            const selection = window.getSelection();
            if (selection) {
                selection.removeAllRanges();
                selection.addRange(range);
            }
            return range;
        }

        function insertHtmlSnippet(editor, html, range) {
            const targetRange = range || placeCaretAtEnd(editor);
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
        }

        function wrapSelectionWithTag(editor, range, tagName, placeholderText) {
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
        }

        function applyLinkAction(editor, range) {
            const selection = window.getSelection();
            const defaultHref = 'https://example.com';
            const defaultLabel = 'Link text';
            const activeRange = range || placeCaretAtEnd(editor);
            if (!activeRange || activeRange.collapsed) {
                const linkHtml = `<a href="${escapeHtml(defaultHref)}" target="_blank" rel="noopener noreferrer">${escapeHtml(defaultLabel)}</a>`;
                insertHtmlSnippet(editor, linkHtml, activeRange);
                return;
            }
            const anchor = document.createElement('a');
            anchor.setAttribute('href', defaultHref);
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
        }

        function insertListAtSelection(editor, range, type) {
            const selection = window.getSelection();
            const placeholderItems = ['First item', 'Second item'];
            const hasSelection = range && !range.collapsed;
            const values = hasSelection
                ? range.toString().split(/\r?\n/).map((item) => item.trim()).filter(Boolean)
                : [];
            const items = values.length ? values : placeholderItems;
            const tagName = type === 'ol' ? 'ol' : 'ul';
            const listHtml = `<${tagName}>\n${items.map((item) => `    <li>${escapeHtml(item)}</li>`).join('\n')}\n</${tagName}>`;
            insertHtmlSnippet(editor, listHtml, range && !range.collapsed ? range : null);
            if (selection) {
                selection.collapseToEnd();
            }
        }

        function applyEditorAction(editor, action) {
            if (!editor) {
                return;
            }
            editor.focus();
            const range = getActiveRange(editor) || placeCaretAtEnd(editor);
            const container = editor.closest('.comment-box-container');
            if (container) {
                const actionsBar = container.querySelector('.comment-actions-bar');
                if (actionsBar) {
                    actionsBar.classList.remove('hidden');
                }
            }

            if (isPlainEditor(editor)) {
                applyPlainEditorAction(editor, action);
                if (container) {
                    updateHiddenFields(container);
                }
                return;
            }

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
                    const sampleSrc = strings.editorImageSample || 'https://example.com/image.jpg';
                    const placeholderAlt = strings.editorImageAlt || 'Image description';
                    const label = range && !range.collapsed ? range.toString().trim() : '';
                    const altText = label ? escapeHtml(label) : escapeHtml(placeholderAlt);
                    const snippet = `<img src="${sampleSrc}" alt="${altText}" />`;
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
                default:
                    break;
            }

            refreshEditorEmptyState(editor);
            if (container) {
                updateHiddenFields(container);
            }
        }

        function toggleReplyForm(commentId) {
            const targetForm = document.getElementById(`reply-form-container-${commentId}`);
            if (!targetForm) {
                return;
            }

            const isHidden = targetForm.classList.contains('hidden');

            document.querySelectorAll('.reply-form-container').forEach(form => {
                form.classList.add('hidden');
            });

            if (isHidden) {
                targetForm.classList.remove('hidden');
                const editor = targetForm.querySelector('.comment-box-textarea');
                if (editor) {
                    editor.focus();
                    const actions = targetForm.querySelector('.comment-actions-bar');
                    if (actions) {
                        actions.classList.remove('hidden');
                    }
                }
            }
        }

        function updateHiddenFields(container) {
            const editor = container.querySelector('.comment-box-textarea');
            if (!editor) {
                return;
            }
            const { html } = getEditorContent(editor);
            const hiddenTextarea = container.querySelector('textarea[name="comment"]');
            if (hiddenTextarea) {
                hiddenTextarea.value = html;
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
        }

        function handleMentionInput(editor) {
            if (isPlainEditor(editor)) {
                hideMentionSuggestions();
                return;
            }
            if (!mentionSuggestions) {
                return;
            }
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) {
                hideMentionSuggestions();
                return;
            }
            const range = selection.getRangeAt(0);
            if (!editor.contains(range.startContainer)) {
                hideMentionSuggestions();
                return;
            }

            const preCursor = range.startContainer.nodeType === Node.TEXT_NODE
                ? range.startContainer.textContent.substring(0, range.startOffset)
                : '';
            const mentionMatch = preCursor.match(/@([\p{L}0-9_\.-]*)$/u);
            if (!mentionMatch) {
                hideMentionSuggestions();
                currentMentionState = null;
                return;
            }

            const query = mentionMatch[1] || '';
            const startOffset = range.startOffset - (query.length + 1);
            if (startOffset < 0) {
                hideMentionSuggestions();
                currentMentionState = null;
                return;
            }

            const mentionRange = range.cloneRange();
            try {
                mentionRange.setStart(range.startContainer, startOffset);
            } catch (error) {
                hideMentionSuggestions();
                currentMentionState = null;
                return;
            }

            currentMentionState = {
                editor,
                range: mentionRange,
                query,
            };

            const rect = mentionRange.getBoundingClientRect();
            positionMentionSuggestions(rect);
            fetchMentionSuggestions(query);
        }

        function positionMentionSuggestions(rect) {
            if (!mentionSuggestions) {
                return;
            }
            mentionSuggestions.style.top = `${window.scrollY + rect.bottom + 6}px`;
            mentionSuggestions.style.left = `${window.scrollX + rect.left}px`;
        }

        function fetchMentionSuggestions(query) {
            if (!mentionSuggestions) {
                return;
            }
            clearTimeout(mentionSearchTimer);
            mentionSearchTimer = setTimeout(() => {
                if (!restEndpoints.mentions) {
                    hideMentionSuggestions();
                    return;
                }

                const url = new URL(restEndpoints.mentions, window.location.origin);
                if (query) {
                    url.searchParams.set('search', query);
                }

                window.fetch(url.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { ...baseHeaders },
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.json();
                    })
                    .then((json) => {
                        if (!json || !Array.isArray(json)) {
                            hideMentionSuggestions();
                            return;
                        }
                        renderMentionSuggestions(json);
                    })
                    .catch(() => hideMentionSuggestions());
            }, 180);
        }

        function renderMentionSuggestions(users) {
            if (!mentionSuggestions) {
                return;
            }
            if (!currentMentionState) {
                hideMentionSuggestions();
                return;
            }
            if (!users.length) {
                mentionSuggestions.innerHTML = `<div class="px-4 py-2 text-sm text-gray-500">${wp.i18n.__("No matches found", 'gta6-mods')}</div>`;
                mentionSuggestions.classList.remove('hidden');
                return;
            }
            const items = users.map((user) => (
                `<button type="button" class="mention-item w-full text-left px-3 py-2 flex items-center gap-3 hover:bg-gray-100" data-user-id="${user.id}" data-username="${user.username}" data-avatar="${user.avatar}">
                    <img src="${user.avatar}" alt="" class="w-8 h-8 rounded-full">
                    <span class="flex flex-col">
                        <span class="font-semibold text-sm text-gray-800">${user.name}</span>
                        <span class="text-xs text-gray-500">@${user.username}</span>
                    </span>
                </button>`
            )).join('');
            mentionSuggestions.innerHTML = items;
            mentionSuggestions.classList.remove('hidden');
        }

        function hideMentionSuggestions() {
            if (mentionSuggestions) {
                mentionSuggestions.classList.add('hidden');
                mentionSuggestions.innerHTML = '';
            }
        }

        function applyMentionSelection(userId, username) {
            if (!currentMentionState) {
                return;
            }
            const { editor, range } = currentMentionState;
            editor.focus();

            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            range.deleteContents();

            const mentionSpan = document.createElement('span');
            mentionSpan.className = 'mention-tag';
            mentionSpan.setAttribute('contenteditable', 'false');
            mentionSpan.dataset.username = username;
            mentionSpan.dataset.userId = userId;
            mentionSpan.textContent = `@${username}`;
            range.insertNode(mentionSpan);

            const space = document.createTextNode(' ');
            mentionSpan.after(space);

            selection.removeAllRanges();
            const newRange = document.createRange();
            newRange.setStartAfter(space);
            newRange.collapse(true);
            selection.addRange(newRange);

            updateHiddenFields(editor.closest('.comment-box-container'));
            hideMentionSuggestions();
            currentMentionState = null;
        }

        function insertAtCursor(editor, text) {
            editor.focus();
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) {
                editor.append(text);
                return;
            }
            const range = selection.getRangeAt(0);
            range.deleteContents();
            range.insertNode(document.createTextNode(text));
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        function toggleCommentMenu(toggleButton) {
            const menu = toggleButton.nextElementSibling;
            if (!menu) {
                return;
            }
            const isHidden = menu.classList.contains('hidden');
            closeAllMenus();
            if (isHidden) {
                menu.classList.remove('hidden');
            }
        }

        function closeAllMenus() {
            document.querySelectorAll('.comment-menu').forEach((menu) => menu.classList.add('hidden'));
        }

        function detectPinnedCommentFromDom(scope) {
            const rootScope = scope instanceof HTMLElement ? scope : document;
            const pinnedElement = rootScope.querySelector('[data-comment-pinned="1"]');
            if (pinnedElement) {
                const pinnedId = parseInt(pinnedElement.dataset.commentId || '0', 10);
                if (pinnedId) {
                    setPinnedComment(pinnedId);
                    return;
                }
            }
            setPinnedComment(null);
        }

        function applyPinnedReplyCollapsing(pinnedId) {
            const previouslyCollapsed = document.querySelectorAll('.comment-wrapper[data-collapsed-by-pin="1"]');
            previouslyCollapsed.forEach((wrapper) => {
                if (!(wrapper instanceof HTMLElement)) {
                    return;
                }
                wrapper.classList.remove('is-collapsed');
                updateCollapseVisual(wrapper);
                delete wrapper.dataset.collapsedByPin;
            });

            if (!pinnedId) {
                return;
            }

            const pinnedWrapper = document.getElementById(`comment-${pinnedId}`);
            if (!pinnedWrapper) {
                return;
            }

            const repliesContainer = Array.from(pinnedWrapper.children).find(
                (child) => child instanceof HTMLElement && child.classList.contains('comment-replies')
            );

            if (!repliesContainer) {
                return;
            }

            Array.from(repliesContainer.children).forEach((child) => {
                if (!(child instanceof HTMLElement) || !child.classList.contains('comment-wrapper')) {
                    return;
                }
                child.dataset.collapsedByPin = '1';
                if (!child.classList.contains('is-collapsed')) {
                    child.classList.add('is-collapsed');
                    updateCollapseVisual(child);
                }
            });
        }

        function setPinnedComment(newPinnedId) {
            const parsedId = Number.isInteger(newPinnedId) ? newPinnedId : parseInt(newPinnedId || '0', 10);
            const targetId = parsedId > 0 ? parsedId : null;
            const previousPinned = currentPinnedCommentId;
            currentPinnedCommentId = targetId;

            applyPinnedReplyCollapsing(targetId);

            const wrappers = document.querySelectorAll('[data-comment-id]');
            wrappers.forEach((wrapper) => {
                if (!(wrapper instanceof HTMLElement)) {
                    return;
                }
                const id = parseInt(wrapper.dataset.commentId || '0', 10);
                const isPinned = targetId !== null && id === targetId;
                updatePinnedDisplay(wrapper, isPinned);

                const pinButton = wrapper.querySelector('[data-action="pin-comment"]');
                if (pinButton) {
                    pinButton.dataset.pinState = isPinned ? 'unpin' : 'pin';
                    pinButton.innerHTML = `<i class="fas fa-thumbtack fa-fw mr-2"></i>${escapeHtml(isPinned ? unpinLabel : pinLabel)}`;
                }
            });

            if (targetId !== null && targetId !== previousPinned) {
                movePinnedCommentToTop(targetId);
            }
        }

        function updatePinnedDisplay(wrapper, isPinned) {
            if (!(wrapper instanceof HTMLElement)) {
                return;
            }

            wrapper.dataset.commentPinned = isPinned ? '1' : '0';
            wrapper.classList.toggle('comment-wrapper--pinned', Boolean(isPinned));

            let badge = wrapper.querySelector('.comment-pinned-badge');
            if (isPinned) {
                if (!badge) {
                    badge = document.createElement('div');
                    badge.className = 'comment-pinned-badge flex items-center text-xs font-semibold text-pink-600 mb-1';
                    badge.innerHTML = `<i class="fas fa-thumbtack fa-fw mr-1"></i><span>${escapeHtml(pinnedBadgeLabel)}</span>`;
                    const body = wrapper.querySelector('.comment-body');
                    if (body) {
                        const firstContent = body.querySelector('.comment-content');
                        if (firstContent) {
                            body.insertBefore(badge, firstContent);
                        } else {
                            body.insertBefore(badge, body.firstChild);
                        }
                    }
                }
            } else if (badge) {
                badge.remove();
            }
        }

        function movePinnedCommentToTop(commentId) {
            const wrapper = document.getElementById(`comment-${commentId}`);
            const list = document.getElementById('gta6-comment-list');
            if (!wrapper || !list) {
                return;
            }

            if (wrapper.parentElement === list) {
                list.insertBefore(wrapper, list.firstChild);
            }
        }

        function handleCommentPin(button) {
            if (!(button instanceof HTMLElement)) {
                return;
            }

            const commentId = parseInt(button.dataset.commentId || '0', 10);
            if (!commentId || !commentBase) {
                showFeedback(strings.pinError || 'Failed to update the pinned comment.', 'error');
                return;
            }

            const shouldUnpin = button.dataset.pinState === 'unpin';

            fetch(`${commentBase}${commentId}/pin`, {
                method: shouldUnpin ? 'DELETE' : 'POST',
                credentials: 'same-origin',
                headers: { ...baseHeaders },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then(() => {
                    closeAllMenus();
                    if (shouldUnpin) {
                        setPinnedComment(null);
                        showFeedback(strings.unpinSuccess || 'Comment unpinned.', 'info');
                    } else {
                        setPinnedComment(commentId);
                        showFeedback(strings.pinSuccess || 'Comment pinned successfully.', 'info');
                    }
                })
                .catch(() => {
                    showFeedback(strings.pinError || 'Failed to update the pinned comment.', 'error');
                });
        }

        function ensureDeleteModal() {
            if (deleteModalOverlay) {
                return;
            }

            deleteModalOverlay = document.createElement('div');
            deleteModalOverlay.id = 'gta6-comment-delete-modal';
            deleteModalOverlay.className = 'gta6-comment-delete-modal fixed inset-0 z-[2147483646] hidden flex items-center justify-center bg-black bg-opacity-60 p-4';
            deleteModalOverlay.innerHTML = `
                <div class="gta6-comment-delete-dialog bg-white rounded-2xl shadow-2xl w-full max-w-sm transform transition-all duration-200 ease-out scale-95 opacity-0" data-comment-delete-dialog role="dialog" aria-modal="true" aria-labelledby="gta6-comment-delete-title" aria-describedby="gta6-comment-delete-message">
                    <div class="px-6 pt-6">
                        <h3 id="gta6-comment-delete-title" class="text-lg font-semibold text-gray-900 mb-2"></h3>
                        <p id="gta6-comment-delete-message" class="text-sm text-gray-600 leading-relaxed"></p>
                    </div>
                    <div class="px-6 pb-6 pt-4 flex items-center justify-end gap-3">
                        <button type="button" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 font-medium hover:bg-gray-300 transition" data-comment-delete-cancel>${escapeHtml(deleteConfirmCancel)}</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2" data-comment-delete-confirm>${escapeHtml(deleteConfirmConfirm)}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(deleteModalOverlay);

            deleteModalDialog = deleteModalOverlay.querySelector('[data-comment-delete-dialog]');
            deleteModalMessage = deleteModalOverlay.querySelector('#gta6-comment-delete-message');
            deleteModalTitle = deleteModalOverlay.querySelector('#gta6-comment-delete-title');
            deleteModalConfirmButton = deleteModalOverlay.querySelector('[data-comment-delete-confirm]');
            deleteModalCancelButton = deleteModalOverlay.querySelector('[data-comment-delete-cancel]');

            if (deleteModalCancelButton) {
                deleteModalCancelButton.addEventListener('click', () => closeDeleteModal(false));
            }

            if (deleteModalConfirmButton) {
                deleteModalConfirmButton.addEventListener('click', () => closeDeleteModal(true));
            }

            deleteModalOverlay.addEventListener('click', (event) => {
                if (event.target === deleteModalOverlay) {
                    closeDeleteModal(false);
                }
            });

            window.addEventListener('keydown', handleDeleteModalKeydown);
        }

        function handleDeleteModalKeydown(event) {
            if (event.key === 'Escape' && deleteModalOverlay && !deleteModalOverlay.classList.contains('hidden')) {
                event.preventDefault();
                closeDeleteModal(false);
            }
        }

        function closeDeleteModal(result) {
            if (!deleteModalOverlay || !deleteModalDialog) {
                if (deleteModalResolver) {
                    const resolver = deleteModalResolver;
                    deleteModalResolver = null;
                    resolver(Boolean(result));
                }
                return;
            }

            deleteModalDialog.classList.remove('scale-100', 'opacity-100');
            deleteModalDialog.classList.add('scale-95', 'opacity-0');

            window.setTimeout(() => {
                if (deleteModalOverlay) {
                    deleteModalOverlay.classList.add('hidden');
                }
            }, 180);

            if (deleteModalResolver) {
                const resolver = deleteModalResolver;
                deleteModalResolver = null;
                resolver(Boolean(result));
            }
        }

        function requestDeleteConfirmation(message) {
            ensureDeleteModal();

            if (!deleteModalOverlay || !deleteModalDialog || !deleteModalMessage || !deleteModalTitle) {
                const fallbackMessage = message || (strings.deleteConfirm || 'Are you sure you want to delete this comment?');
                return Promise.resolve(window.confirm(fallbackMessage));
            }

            const modalMessage = message || (strings.deleteConfirm || 'Are you sure you want to delete this comment?');
            deleteModalTitle.textContent = deleteConfirmTitle;
            deleteModalMessage.textContent = modalMessage;

            deleteModalOverlay.classList.remove('hidden');

            requestAnimationFrame(() => {
                deleteModalDialog.classList.remove('scale-95', 'opacity-0');
                deleteModalDialog.classList.add('scale-100', 'opacity-100');
            });

            window.setTimeout(() => {
                if (deleteModalConfirmButton) {
                    deleteModalConfirmButton.focus();
                }
            }, 80);

            return new Promise((resolve) => {
                deleteModalResolver = resolve;
            });
        }

        function applyCommentRetractedState(commentId) {
            const wrapper = document.getElementById(`comment-${commentId}`);
            if (!wrapper) {
                return;
            }

            wrapper.dataset.commentRetracted = '1';
            wrapper.classList.add('comment-wrapper--retracted');

            const content = wrapper.querySelector('.comment-content');
            if (content) {
                content.innerHTML = '';
                const emphasis = document.createElement('em');
                emphasis.className = 'comment-retracted-text';
                emphasis.textContent = retractedPlaceholder;
                content.appendChild(emphasis);
            }

            const attachments = wrapper.querySelectorAll('.comment-attachments');
            attachments.forEach((element) => element.remove());

            const likeButton = wrapper.querySelector('.comment-like-btn');
            if (likeButton) {
                const countText = likeButton.querySelector('.comment-like-count')
                    ? likeButton.querySelector('.comment-like-count').textContent
                    : '0';
                const replacement = document.createElement('span');
                replacement.className = 'flex items-center text-gray-400 cursor-not-allowed';
                replacement.setAttribute('aria-disabled', 'true');
                replacement.innerHTML = `<i class="fas fa-thumbs-up mr-1"></i> <span class="comment-like-count">${escapeHtml(countText || '0')}</span>`;
                likeButton.replaceWith(replacement);
            }

            const retractButton = wrapper.querySelector('[data-action="retract-comment"]');
            if (retractButton) {
                retractButton.remove();
            }
        }

        async function handleCommentRetract(button) {
            if (!(button instanceof HTMLElement)) {
                return;
            }

            if (!data.user || !data.user.logged_in) {
                const mustLogIn = strings.mustLogIn || 'You must be logged in to perform this action.';
                window.alert(mustLogIn);
                return;
            }

            const commentId = parseInt(button.dataset.commentId || '0', 10);
            if (!commentId || !commentBase) {
                showFeedback(strings.deleteError || 'We could not delete the comment. Please try again.', 'error');
                return;
            }

            const confirmMessage = strings.deleteConfirm || 'Are you sure you want to delete this comment?';
            const confirmed = await requestDeleteConfirmation(confirmMessage);
            if (!confirmed) {
                closeAllMenus();
                return;
            }

            fetch(`${commentBase}${commentId}/retract`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { ...baseHeaders },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((json) => {
                    applyCommentRetractedState(commentId);
                    closeAllMenus();
                    if (json && json.pinned_removed) {
                        setPinnedComment(null);
                    }
                    showFeedback(strings.deleteSuccess || 'Your comment has been deleted.', 'info');
                })
                .catch(() => {
                    showFeedback(strings.deleteError || 'We could not delete the comment. Please try again.', 'error');
                });
        }

        function handleCommentCopyLink(button) {
            if (!(button instanceof HTMLElement)) {
                return;
            }

            let link = button.dataset.commentLink || '';
            if (!link) {
                const wrapper = button.closest('[data-comment-permalink]');
                if (wrapper && wrapper.dataset.commentPermalink) {
                    link = wrapper.dataset.commentPermalink;
                }
            }

            if (!link) {
                showFeedback(strings.copyError || 'We could not copy the comment link.', 'error');
                return;
            }

            copyTextToClipboard(link)
                .then(() => {
                    closeAllMenus();
                    showFeedback(strings.copySuccess || 'Comment link copied to clipboard.', 'info');
                })
                .catch(() => {
                    showFeedback(strings.copyError || 'We could not copy the comment link.', 'error');
                });
        }

        function copyTextToClipboard(text) {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                return navigator.clipboard.writeText(text);
            }

            return new Promise((resolve, reject) => {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'absolute';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    const successful = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    if (successful) {
                        resolve();
                    } else {
                        reject(new Error('copy-failed'));
                    }
                } catch (err) {
                    document.body.removeChild(textarea);
                    reject(err);
                }
            });
        }

        function scrollToCommentFromHash(retryCount = 0, overrideHash = null) {
            const currentHash = typeof overrideHash === 'string' ? overrideHash : (window.location.hash || '');
            if (!currentHash || currentHash.indexOf('#comment-') !== 0) {
                return false;
            }

            const target = document.querySelector(currentHash);
            if (!target) {
                if (retryCount < 15) {
                    window.setTimeout(() => scrollToCommentFromHash(retryCount + 1, currentHash), 220);
                }
                return false;
            }

            if (typeof target.scrollIntoView === 'function') {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            target.classList.add('comment-wrapper--highlight');
            window.setTimeout(() => {
                target.classList.remove('comment-wrapper--highlight');
            }, 5200);

            if (window.history && typeof window.history.replaceState === 'function') {
                try {
                    const url = new URL(window.location.href);
                    if (url.hash !== currentHash) {
                        url.hash = currentHash;
                        window.history.replaceState(null, '', url.toString());
                    }
                } catch (error) {
                    // Ignore malformed URLs
                }
            }

            return true;
        }

        function handleImageSelection(input) {
            const container = input.closest('.comment-box-container');
            if (!container) {
                return;
            }

            if (!input.files || input.files.length === 0) {
                clearImagePreview(container);
                return;
            }

            const file = input.files[0];
            if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                input.value = '';
                clearImagePreview(container);
                showFeedback((data.strings && data.strings.imageUploadInvalid) ? data.strings.imageUploadInvalid : 'Please choose a valid image file.', 'error');
                return;
            }

            const reader = new window.FileReader();
            reader.onload = () => {
                setImagePreview(container, 0, reader.result);
                const hiddenField = container.querySelector('input[name="comment_image_id"]');
                if (hiddenField) {
                    hiddenField.value = '';
                }
            };
            reader.readAsDataURL(file);
        }

        function setImagePreview(container, attachmentId, url) {
            const previewContainer = container.querySelector('.image-preview-container');
            const previewImage = previewContainer ? previewContainer.querySelector('img') : null;
            if (previewContainer && previewImage) {
                previewImage.src = url;
                previewContainer.classList.remove('hidden');
                container.classList.add('has-preview');
                const actions = container.querySelector('.comment-actions-bar');
                if (actions) {
                    actions.classList.remove('hidden');
                }
            }
        }

        function clearImagePreview(container) {
            const previewContainer = container.querySelector('.image-preview-container');
            const previewImage = previewContainer ? previewContainer.querySelector('img') : null;
            if (previewContainer && previewImage) {
                previewImage.src = '';
                previewContainer.classList.add('hidden');
                const fileInput = container.querySelector('.comment-image-input');
                if (fileInput) {
                    fileInput.value = '';
                }
                const gifPreview = container.querySelector('.gif-preview-container');
                const gifVisible = gifPreview && !gifPreview.classList.contains('hidden');
                if (!gifVisible) {
                    container.classList.remove('has-preview');
                }
            }
        }

        function openGiphy(container) {
            activeGifContainer = container;
            if (!giphyModal || !giphyResults) {
                return;
            }
            giphyModal.classList.remove('hidden');
            const loadingText = (data.strings && data.strings.loadingGifs) ? data.strings.loadingGifs : 'Loading GIFs...';
            giphyResults.innerHTML = `<div class="col-span-full text-center p-6 text-gray-400">${loadingText}</div>`;
            if (giphySearchInput) {
                giphySearchInput.value = '';
            }
            fetchGifs();
        }

        function setupGiphyModal() {
            if (!giphyModal) {
                return;
            }
            if (giphyCloseButton) {
                giphyCloseButton.addEventListener('click', () => {
                    giphyModal.classList.add('hidden');
                });
            }
            giphyModal.addEventListener('click', (event) => {
                if (event.target === giphyModal) {
                    giphyModal.classList.add('hidden');
                }
            });
            if (giphySearchInput) {
                let giphyTimeout;
                giphySearchInput.addEventListener('input', () => {
                    clearTimeout(giphyTimeout);
                    giphyTimeout = setTimeout(() => {
                        const query = giphySearchInput.value.trim();
                        fetchGifs(query);
                    }, 300);
                });
            }
        }

        function fetchGifs(query = '') {
            if (!giphyResults) {
                return;
            }
            const apiKey = data.giphy_api_key || '';
            if (!apiKey) {
                giphyResults.innerHTML = `<div class="col-span-full text-center p-6 text-gray-400">${wp.i18n.__('GIPHY is not configured.', 'gta6-mods')}</div>`;
                return;
            }
            const endpoint = query ?
                `https://api.giphy.com/v1/gifs/search?api_key=${apiKey}&q=${encodeURIComponent(query)}&limit=24&rating=g&lang=en` :
                `https://api.giphy.com/v1/gifs/trending?api_key=${apiKey}&limit=24&rating=g`;
            window.fetch(endpoint)
                .then((response) => response.json())
                .then((json) => {
                    if (!json || !json.data || !Array.isArray(json.data)) {
                        const noGifsText = (data.strings && data.strings.noGifsFound) ? data.strings.noGifsFound : 'No GIFs found.';
                        giphyResults.innerHTML = `<div class="col-span-full text-center p-6 text-gray-400">${noGifsText}</div>`;
                        return;
                    }
                    renderGifs(json.data);
                })
                .catch(() => {
                    const gifsError = (data.strings && data.strings.gifsError) ? data.strings.gifsError : 'Could not fetch GIFs.';
                    giphyResults.innerHTML = `<div class="col-span-full text-center p-6 text-red-500">${gifsError}</div>`;
                });
        }

        function renderGifs(gifs) {
            if (!giphyResults) {
                return;
            }
            if (!gifs.length) {
                const noGifsText = (data.strings && data.strings.noGifsFound) ? data.strings.noGifsFound : 'No GIFs found.';
                giphyResults.innerHTML = `<div class="col-span-full text-center p-6 text-gray-400">${noGifsText}</div>`;
                return;
            }
            giphyResults.innerHTML = gifs.map((gif) => {
                const images = gif.images || {};
                const thumb = (images.fixed_height_small && images.fixed_height_small.url)
                    || (images.preview_gif && images.preview_gif.url)
                    || (images.downsized_small && images.downsized_small.url)
                    || '';
                const full = (images.original && images.original.url) || thumb;
                return `<button type="button" class="rounded overflow-hidden relative group bg-gray-200 h-28" data-gif="${full}">
                    <img src="${thumb}" alt="" class="w-full h-full object-cover transition-transform group-hover:scale-105">
                </button>`;
            }).join('');
            giphyResults.querySelectorAll('button[data-gif]').forEach((button) => {
                button.addEventListener('click', () => {
                    const url = button.getAttribute('data-gif');
                    setGifPreview(activeGifContainer, url);
                    giphyModal.classList.add('hidden');
                });
            });
        }

        function setGifPreview(container, url) {
            if (!container) {
                return;
            }
            const previewContainer = container.querySelector('.gif-preview-container');
            const previewImage = previewContainer ? previewContainer.querySelector('img') : null;
            const hiddenField = container.querySelector('input[name="comment_gif_url"]');
            if (previewContainer && previewImage) {
                previewImage.src = url;
                previewContainer.classList.remove('hidden');
                container.classList.add('has-preview');
                const actions = container.querySelector('.comment-actions-bar');
                if (actions) {
                    actions.classList.remove('hidden');
                }
            }
            if (hiddenField) {
                hiddenField.value = url;
            }
        }

        function clearGifPreview(container) {
            const previewContainer = container.querySelector('.gif-preview-container');
            const previewImage = previewContainer ? previewContainer.querySelector('img') : null;
            const hiddenField = container.querySelector('input[name="comment_gif_url"]');
            if (hiddenField) {
                hiddenField.value = '';
            }
            if (previewContainer && previewImage) {
                previewImage.src = '';
                previewContainer.classList.add('hidden');
                const imagePreview = container.querySelector('.image-preview-container');
                const hasImagePreview = imagePreview && !imagePreview.classList.contains('hidden');
                if (hasImagePreview) {
                    return;
                }
                container.classList.remove('has-preview');
            }
        }

        function sendCommentRequest(formData, button) {
            if (!restEndpoints.comments) {
                return;
            }
            clearFeedback();
            if (button) {
                button.disabled = true;
            }

            window.fetch(restEndpoints.comments, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { ...baseHeaders },
                body: formData,
            })
                .then(async (response) => {
                    const payload = await response.json().catch(() => null);
                    if (!response.ok || !payload || typeof payload !== 'object') {
                        const message = (payload && payload.message)
                            ? payload.message
                            : (data.strings && data.strings.errorGeneric)
                                ? data.strings.errorGeneric
                                : 'Something went wrong. Please try again.';
                        throw new Error(message);
                    }
                    return payload;
                })
                .then((payload) => {
                    if (payload.html) {
                        insertCommentHtml(payload.html, payload.parent_id || 0);
                    }
                    if (payload.counts && typeof payload.counts.display === 'number') {
                        updateCommentCount(payload.counts.display);
                    }
                    if (noCommentsMessage) {
                        noCommentsMessage.classList.add('hidden');
                    }
                    const isPending = payload.status === '0';
                    const successMessage = payload.message
                        || (isPending
                            ? (data.strings && data.strings.commentPending) ? data.strings.commentPending : 'Your comment is awaiting moderation.'
                            : (data.strings && data.strings.commentPosted) ? data.strings.commentPosted : 'Your comment has been posted.');

                    if (Number(formData.get('comment_parent')) > 0) {
                        const replyContainer = document.getElementById(`reply-form-container-${formData.get('comment_parent')}`);
                        if (replyContainer) {
                            resetReplyForm(replyContainer);
                        }
                    } else {
                        resetCommentForm(commentForm);
                    }

                    showFeedback(successMessage, isPending ? 'info' : 'success');
                })
                .catch((error) => {
                    const message = error && error.message
                        ? error.message
                        : (data.strings && data.strings.errorGeneric)
                            ? data.strings.errorGeneric
                            : 'Something went wrong. Please try again.';
                    showFeedback(message, 'error');
                })
                .finally(() => {
                    if (button) {
                        button.disabled = false;
                    }
                });
        }

        function setupFormSubmit(form) {
            if (!form) {
                return;
            }
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const container = form.querySelector('.comment-box-container');
                if (container) {
                    updateHiddenFields(container);
                }
                const formData = new window.FormData(form);

                const submitButton = form.querySelector('.comment-actions-bar button[type="submit"]');
                sendCommentRequest(formData, submitButton);
            });
        }

        function submitReply(replyContainer, parentId, button) {
            const boxContainer = replyContainer.querySelector('.comment-box-container');
            if (!boxContainer) return;

            const editor = boxContainer.querySelector('.comment-box-textarea');
            const { html: replyHtml, text: replyPlain } = getEditorContent(editor);
            if (!replyPlain.trim()) {
                showFeedback('Please write a reply.', 'error');
                return;
            }

            const mainForm = document.getElementById('commentform');
            if (!mainForm) return;

            const formData = new FormData(mainForm);
            formData.set('comment', replyHtml);
            formData.set('comment_parent', parentId);

            const imageInput = boxContainer.querySelector('.comment-image-input');
            if (imageInput && imageInput.files.length > 0) {
                formData.set('comment_image_file', imageInput.files[0]);
            } else {
                formData.delete('comment_image_file');
            }

            const gifPreview = boxContainer.querySelector('.gif-preview-container:not(.hidden) img');
            if (gifPreview && gifPreview.src) {
                formData.set('comment_gif_url', gifPreview.src);
            } else {
                formData.delete('comment_gif_url');
            }
            
            const ids = Array.from(editor.querySelectorAll('.mention-tag[data-user-id]')).map((span) => span.dataset.userId);
            const uniqueIds = Array.from(new Set(ids.filter((value) => value && value !== '0')));
            formData.set('comment_mentioned_users', uniqueIds.join(','));

            sendCommentRequest(formData, button);
        }
        
        function insertCommentHtml(html, parentId) {
            if (!html) {
                return;
            }
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const newCommentElement = tempDiv.firstElementChild;
            if (!newCommentElement) return;

            initialiseCommentBoxes(newCommentElement);

            const targetParentId = parseInt(parentId, 10) || 0;

            if (targetParentId > 0) {
                const parentComment = root.querySelector(`#comment-${targetParentId}`);
                if (!parentComment) {
                    if (commentList) {
                        commentList.prepend(newCommentElement);
                    }
                    return;
                }

                let repliesContainer = parentComment.querySelector('.comment-replies');
                if (!repliesContainer) {
                    repliesContainer = document.createElement('div');
                    repliesContainer.className = 'comment-replies space-y-4 mt-4';
                    repliesContainer.dataset.parentId = String(targetParentId);

                    const body = parentComment.querySelector('.comment-body');
                    if (body) {
                        body.parentNode.insertBefore(repliesContainer, body.nextSibling);
                    } else {
                         parentComment.querySelector('.comment-main-content').appendChild(repliesContainer);
                    }
                } else if (!repliesContainer.dataset.parentId) {
                    repliesContainer.dataset.parentId = String(targetParentId);
                }
                repliesContainer.prepend(newCommentElement);
                ensureReplyToggleForContainer(repliesContainer, parentComment, newCommentElement, targetParentId);
                applyReplyCollapsing(parentComment || repliesContainer);
                if (currentPinnedCommentId !== null) {
                    setPinnedComment(currentPinnedCommentId);
                }
                return;
            }

            if (commentList) {
                commentList.prepend(newCommentElement);
                applyReplyCollapsing(newCommentElement);
                if (currentPinnedCommentId !== null) {
                    setPinnedComment(currentPinnedCommentId);
                }
            }
        }
        
        function resetReplyForm(container) {
            container.classList.add('hidden');
            const boxContainer = container.querySelector('.comment-box-container');
            if (boxContainer) {
                const editor = boxContainer.querySelector('.comment-box-textarea');
                if (editor) {
                    editor.innerHTML = '';
                    refreshEditorEmptyState(editor);
                }
                clearImagePreview(boxContainer);
                clearGifPreview(boxContainer);
                boxContainer.classList.remove('has-preview');
            }
        }

        function resetCommentForm(form) {
            form.reset();

            const container = form.querySelector('.comment-box-container');
            if (!container) {
                return;
            }

            const editor = container.querySelector('.comment-box-textarea');
            if (editor) {
                editor.textContent = '';
                refreshEditorEmptyState(editor);
            }
            const hiddenTextarea = container.querySelector('textarea[name="comment"]');
            if (hiddenTextarea) {
                hiddenTextarea.value = '';
            }
            const mentionsField = container.querySelector('input[name="comment_mentioned_users"]');
            if (mentionsField) {
                mentionsField.value = '';
            }
            clearImagePreview(container);
            clearGifPreview(container);
            container.classList.remove('has-preview');
            const actions = container.querySelector('.comment-actions-bar');
            if (actions) {
                actions.classList.add('hidden');
            }
            hideMentionSuggestions();
        }

        function updateCommentCount(count) {
            if (!commentCountHeading) return;

            const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;

            const singularTemplate = commentCountHeading.dataset.templateSingular;
            const pluralTemplate = commentCountHeading.dataset.templatePlural;
            
            let templateToUse = (safeCount === 1 && singularTemplate) ? singularTemplate : pluralTemplate;

            if (templateToUse && templateToUse.includes('%s')) {
                commentCountHeading.textContent = templateToUse.replace('%s', safeCount.toLocaleString());
            } else {
                // Fallback to a sensible default if templates are missing
                const label = safeCount === 1 ? 'Comment' : 'Comments';
                commentCountHeading.textContent = `${safeCount.toLocaleString()} ${label}`;
            }

            try {
                const event = new CustomEvent('gta6mods:comments:count-updated', {
                    detail: { count: safeCount },
                });
                window.dispatchEvent(event);
            } catch (error) {
                // Silently ignore environments where CustomEvent is not supported.
            }
        }

        function showFeedback(message, type = 'success') {
            if (!feedback) {
                return;
            }

            if (!message) {
                clearFeedback();
                return;
            }

            const dismissLabel = (strings && strings.dismissToast) ? strings.dismissToast : 'Dismiss';
            const variants = {
                success: {
                    container: 'bg-green-50 text-green-800 border-green-500',
                    iconColor: 'text-green-500',
                    icon: 'fa-solid fa-circle-check',
                    role: 'status',
                    timeout: 4500,
                },
                info: {
                    container: 'bg-amber-50 text-amber-800 border-amber-500',
                    iconColor: 'text-amber-500',
                    icon: 'fa-solid fa-circle-info',
                    role: 'status',
                    timeout: 5000,
                },
                error: {
                    container: 'bg-rose-50 text-rose-800 border-rose-500',
                    iconColor: 'text-rose-500',
                    icon: 'fa-solid fa-triangle-exclamation',
                    role: 'alert',
                    timeout: 7000,
                },
            };

            const variant = variants[type] || variants.success;

            const toast = document.createElement('div');
            toast.className = `gta6-comment-toast pointer-events-auto flex items-center gap-3 rounded-xl border-l-4 px-4 py-3 shadow-xl transition duration-200 ease-out transform translate-y-3 opacity-0 max-w-[400px] ${variant.container}`;
            toast.setAttribute('role', variant.role);

            const iconWrapper = document.createElement('span');
            iconWrapper.className = `text-lg ${variant.iconColor}`;

            const icon = document.createElement('i');
            icon.className = variant.icon;
            icon.setAttribute('aria-hidden', 'true');
            iconWrapper.appendChild(icon);

            const messageEl = document.createElement('div');
            messageEl.className = 'flex-1 text-sm font-medium';
            messageEl.textContent = message;

            const dismissButton = document.createElement('button');
            dismissButton.type = 'button';
            dismissButton.className = 'ml-auto text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 focus:outline-none';
            dismissButton.textContent = dismissLabel;

            dismissButton.addEventListener('click', (event) => {
                event.preventDefault();
                dismissToast(toast);
            });

            toast.appendChild(iconWrapper);
            toast.appendChild(messageEl);
            toast.appendChild(dismissButton);

            feedback.classList.remove('hidden');
            feedback.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
            });

            const timeout = window.setTimeout(() => {
                dismissToast(toast);
            }, variant.timeout);

            toast.dataset.dismissTimeout = String(timeout);
        }

        function dismissToast(toast) {
            if (!(toast instanceof HTMLElement)) {
                return;
            }

            const timeoutId = toast.dataset.dismissTimeout ? parseInt(toast.dataset.dismissTimeout, 10) : 0;
            if (timeoutId) {
                window.clearTimeout(timeoutId);
            }

            toast.classList.remove('translate-y-0', 'opacity-100');
            toast.classList.add('translate-y-3', 'opacity-0');

            window.setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
                if (feedback && feedback.childElementCount === 0) {
                    feedback.classList.add('hidden');
                }
            }, 180);
        }

        function clearFeedback() {
            if (!feedback) {
                return;
            }

            const toasts = feedback.querySelectorAll('.gta6-comment-toast');
            toasts.forEach((toast) => {
                if (toast instanceof HTMLElement) {
                    const timeoutId = toast.dataset.dismissTimeout ? parseInt(toast.dataset.dismissTimeout, 10) : 0;
                    if (timeoutId) {
                        window.clearTimeout(timeoutId);
                    }
                    toast.remove();
                }
            });

            feedback.classList.add('hidden');
        }

        function handleLikeButton(button) {
            if (!data.user || !data.user.logged_in) {
                const mustLogIn = (data.strings && data.strings.mustLogIn) ? data.strings.mustLogIn : 'You must be logged in to perform this action.';
                window.alert(mustLogIn);
                return;
            }
            const commentId = parseInt(button.dataset.commentId || '0', 10);
            if (!commentId) {
                return;
            }
            const likeBase = typeof restEndpoints.commentLike === 'string' ? restEndpoints.commentLike : '';
            const endpoint = likeBase ? `${likeBase}${commentId}/like` : '';
            if (!endpoint) {
                return;
            }

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { ...baseHeaders },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((json) => {
                    if (!json || typeof json.count === 'undefined') {
                        throw new Error('Invalid response');
                    }
                    const countElement = button.querySelector('.comment-like-count');
                    if (countElement) {
                        countElement.textContent = json.count;
                    }
                    button.setAttribute('aria-pressed', json.liked ? 'true' : 'false');
                })
                .catch(() => {});
        }

        if (!window.GTAModsComments) {
            window.GTAModsComments = {};
        }

        window.GTAModsComments.scrollToHash = (hash) => {
            if (typeof hash === 'string' && hash.indexOf('#comment-') === 0) {
                scrollToCommentFromHash(0, hash);
                return;
            }

            scrollToCommentFromHash();
        };
    };

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('gta6-comments');
        if (root) {
            initComments(root);
        }
    });

    if (!window.GTAModsComments) {
        window.GTAModsComments = {};
    }
    window.GTAModsComments.init = initComments;
})();

