/**
 * Comment System JavaScript (WordPress Style)
 * Handles comment interactions: collapse, like, reply forms, etc.
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initCommentEditors();
        initCommentLikes();
        initReplyButtons();
        initCancelReplyButtons();
        initCollapseThreads();
        initCommentSubmission();
    }

    /**
     * Initialize comment editors (contenteditable to textarea sync)
     */
    function initCommentEditors() {
        // Main comment editor
        const mainEditor = document.getElementById('main-comment-editor');
        const mainTextarea = document.getElementById('main-comment-textarea');

        if (mainEditor && mainTextarea) {
            // Show/hide action bar on focus/blur
            mainEditor.addEventListener('focus', function() {
                const actionsBar = this.closest('.comment-box-container').querySelector('.comment-actions-bar');
                if (actionsBar) {
                    actionsBar.classList.remove('hidden');
                }
            });

            // Sync content to hidden textarea
            mainEditor.addEventListener('input', function() {
                const text = this.innerText.trim();
                mainTextarea.value = text;

                // Update empty class
                if (text.length === 0) {
                    this.classList.add('is-empty');
                } else {
                    this.classList.remove('is-empty');
                }
            });
        }

        // Reply editors (delegated)
        document.addEventListener('input', function(e) {
            const editor = e.target.closest('[data-comment-reply-editor]');
            if (!editor) return;

            const commentId = editor.dataset.commentReplyEditor;
            const textarea = document.querySelector(`[data-comment-reply-textarea="${commentId}"]`);

            if (textarea) {
                const text = editor.innerText.trim();
                textarea.value = text;

                // Update empty class
                if (text.length === 0) {
                    editor.classList.add('is-empty');
                } else {
                    editor.classList.remove('is-empty');
                }
            }
        });
    }

    /**
     * Initialize comment submission (AJAX - no page reload)
     */
    function initCommentSubmission() {
        // Main comment form
        const mainForm = document.getElementById('main-comment-form');
        if (mainForm) {
            mainForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const mainTextarea = document.getElementById('main-comment-textarea');
                const mainEditor = document.getElementById('main-comment-editor');
                const text = mainTextarea.value.trim();

                if (text.length < 5) {
                    alert('Comment must be at least 5 characters long.');
                    return;
                }

                const submitBtn = mainForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Posting...';

                try {
                    const formData = new FormData(mainForm);
                    const response = await fetch(mainForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        // Reload page to show new comment
                        window.location.reload();
                    } else {
                        const data = await response.json();
                        alert(data.message || 'Failed to post comment');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                } catch (error) {
                    console.error('Error posting comment:', error);
                    alert('Network error occurred');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }

        // Reply forms (delegated)
        document.addEventListener('submit', async function(e) {
            const form = e.target.closest('.reply-form-container form');
            if (!form) return;

            e.preventDefault();

            const textarea = form.querySelector('textarea[name="body"]');
            const text = textarea.value.trim();

            if (text.length < 5) {
                alert('Reply must be at least 5 characters long.');
                return;
            }

            const submitBtn = form.querySelector('.post-reply-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Posting...';

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    // Reload page to show new reply
                    window.location.reload();
                } else {
                    const data = await response.json();
                    alert(data.message || 'Failed to post reply');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error posting reply:', error);
                alert('Network error occurred');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    /**
     * Initialize comment like buttons
     */
    function initCommentLikes() {
        document.addEventListener('click', function(e) {
            const likeBtn = e.target.closest('.comment-like-btn');
            if (!likeBtn) return;

            e.preventDefault();
            handleCommentLike(likeBtn);
        });
    }

    async function handleCommentLike(button) {
        if (button.disabled) return;

        const commentId = button.dataset.commentId;
        if (!commentId) return;

        button.disabled = true;

        try {
            const response = await fetch(`/comments/${commentId}/like/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Update button state
                button.setAttribute('aria-pressed', data.liked ? 'true' : 'false');

                if (data.liked) {
                    button.classList.add('text-pink-600', 'font-semibold');
                } else {
                    button.classList.remove('text-pink-600', 'font-semibold');
                }

                // Update like count
                const countSpan = button.querySelector('.comment-like-count');
                if (countSpan) {
                    countSpan.textContent = data.likes_count;
                }

                // Update data attribute for potential caching
                const commentWrapper = button.closest('[data-comment-id]');
                if (commentWrapper) {
                    commentWrapper.dataset.likeCount = data.likes_count;
                }
            } else {
                if (response.status === 401) {
                    window.location.href = '/login';
                } else {
                    alert(data.message || 'Failed to like comment');
                }
            }
        } catch (error) {
            console.error('Error liking comment:', error);
            alert('Network error occurred');
        } finally {
            button.disabled = false;
        }
    }

    /**
     * Initialize reply buttons
     */
    function initReplyButtons() {
        document.addEventListener('click', function(e) {
            const replyBtn = e.target.closest('.reply-btn');
            if (!replyBtn) return;

            e.preventDefault();
            const commentId = replyBtn.dataset.commentId;
            toggleReplyForm(commentId);
        });
    }

    function toggleReplyForm(commentId) {
        const formContainer = document.getElementById(`reply-form-container-${commentId}`);
        if (!formContainer) return;

        const isHidden = formContainer.classList.contains('hidden');

        if (isHidden) {
            // Hide all other reply forms first
            document.querySelectorAll('.reply-form-container').forEach(form => {
                form.classList.add('hidden');
            });

            // Show this form
            formContainer.classList.remove('hidden');

            // Focus the editor
            const editor = formContainer.querySelector('[data-comment-reply-editor]');
            if (editor) {
                editor.focus();
            }
        } else {
            formContainer.classList.add('hidden');
        }
    }

    /**
     * Initialize cancel reply buttons
     */
    function initCancelReplyButtons() {
        document.addEventListener('click', function(e) {
            const cancelBtn = e.target.closest('.cancel-reply-btn');
            if (!cancelBtn) return;

            e.preventDefault();
            const commentId = cancelBtn.dataset.commentId;
            cancelReply(commentId);
        });
    }

    function cancelReply(commentId) {
        const formContainer = document.getElementById(`reply-form-container-${commentId}`);
        if (!formContainer) return;

        formContainer.classList.add('hidden');

        // Clear the editor
        const editor = formContainer.querySelector('[data-comment-reply-editor]');
        const textarea = formContainer.querySelector('[data-comment-reply-textarea]');

        if (editor) {
            editor.innerText = '';
            editor.classList.add('is-empty');
        }

        if (textarea) {
            textarea.value = '';
        }
    }

    /**
     * Initialize collapse/expand threads
     */
    function initCollapseThreads() {
        document.addEventListener('click', function(e) {
            const clickableArea = e.target.closest('.clickable-area');
            if (!clickableArea) return;

            const commentWrapper = clickableArea.closest('.comment-wrapper');
            if (!commentWrapper) return;

            toggleCollapseComment(commentWrapper);
        });

        // Also handle keyboard (Enter/Space)
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;

            const clickableArea = e.target.closest('.clickable-area');
            if (!clickableArea) return;

            e.preventDefault();
            const commentWrapper = clickableArea.closest('.comment-wrapper');
            if (commentWrapper) {
                toggleCollapseComment(commentWrapper);
            }
        });
    }

    function toggleCollapseComment(commentWrapper) {
        const isCollapsed = commentWrapper.classList.contains('is-collapsed');
        const clickableArea = commentWrapper.querySelector('.clickable-area');

        if (isCollapsed) {
            commentWrapper.classList.remove('is-collapsed');
            if (clickableArea) {
                clickableArea.setAttribute('aria-expanded', 'true');
            }
        } else {
            commentWrapper.classList.add('is-collapsed');
            if (clickableArea) {
                clickableArea.setAttribute('aria-expanded', 'false');
            }
        }
    }

})();
