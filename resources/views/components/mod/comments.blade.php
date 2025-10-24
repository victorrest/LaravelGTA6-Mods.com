@props(['mod', 'comments'])

@php
    $primaryCategory = $mod->primary_category ?? $mod->categories->first();
    $totalComments = $mod->comments_count;
    $hasMoreComments = $totalComments > $comments->count();
@endphp

<section
    id="mod-comments"
    class="space-y-6"
    data-comments-section
    data-mod-id="{{ $mod->id }}"
    data-total-comments="{{ $totalComments }}"
>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fa-regular fa-comments text-pink-500"></i>
                <span>Comments</span>
                <span class="text-sm text-gray-500" data-comment-count>({{ number_format($totalComments) }})</span>
            </h2>
            <p class="text-sm text-gray-500">Join the conversation, ask questions and share your feedback.</p>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <label for="comment-sort" class="text-gray-500">Sort by</label>
            <select id="comment-sort" name="orderby" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-pink-500 focus:ring-pink-500" data-comment-sort>
                <option value="best" selected>Top</option>
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
            </select>
        </div>
    </div>

    @auth
        <div class="card p-4 md:p-5" data-comment-form>
            <form method="POST" action="{{ route('mods.comment', [$primaryCategory, $mod]) }}" class="space-y-3">
                @csrf
                <div class="flex items-start gap-3">
                    <img
                        src="{{ auth()->user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=ec4899&color=fff' }}"
                        alt="{{ auth()->user()->name }} avatar"
                        class="h-10 w-10 rounded-full object-cover"
                    >
                    <div class="flex-1 space-y-3">
                        <textarea
                            name="body"
                            rows="4"
                            minlength="5"
                            maxlength="1500"
                            required
                            class="w-full resize-none rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm focus:border-pink-500 focus:outline-none focus:ring-2 focus:ring-pink-200"
                            placeholder="Share your thoughts about this mod..."
                        ></textarea>
                        @error('body')
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400">1500 characters maximum</span>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-pink-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-pink-700">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span>Post comment</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @else
        <div class="card border border-dashed border-pink-200 bg-pink-50/50 p-5 text-center text-sm text-pink-700">
            <p>
                <strong>Sign in to join the discussion.</strong>
                <a href="{{ route('login') }}" class="ml-1 font-semibold text-pink-600 underline-offset-4 hover:underline">Log in</a>
                or
                <a href="{{ route('register') }}" class="font-semibold text-pink-600 underline-offset-4 hover:underline">create an account</a>.
            </p>
        </div>
    @endauth

    <div id="comment-thread" class="space-y-4" data-comment-thread>
        @include('mods.partials.comment-thread', ['mod' => $mod, 'comments' => $comments])
    </div>

    @if ($hasMoreComments)
        <div class="flex justify-center">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full border-2 border-pink-500 px-6 py-2 text-sm font-semibold text-pink-600 transition hover:bg-pink-50"
                data-load-more-comments
            >
                <i class="fa-solid fa-rotate"></i>
                Load more comments
            </button>
        </div>
    @endif
</section>

@push('scripts')
<script>
    document.querySelectorAll('[data-comment-reply]').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.commentReply;
            const form = document.querySelector(`[data-comment-reply-form="${targetId}"]`);
            if (!form) return;
            form.classList.toggle('hidden');
            if (!form.classList.contains('hidden')) {
                const textarea = form.querySelector('textarea');
                if (textarea) textarea.focus();
            }
        });
    });

    document.querySelectorAll('[data-cancel-reply]').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.cancelReply;
            const form = document.querySelector(`[data-comment-reply-form="${targetId}"]`);
            if (!form) return;
            form.classList.add('hidden');
            const textarea = form.querySelector('textarea');
            if (textarea) textarea.value = '';
        });
    });

    const commentsSection = document.querySelector('[data-comments-section]');
    if (commentsSection) {
        const modId = commentsSection.dataset.modId;
        const thread = commentsSection.querySelector('[data-comment-thread]');
        const loadMoreBtn = commentsSection.querySelector('[data-load-more-comments]');
        const sortSelect = commentsSection.querySelector('[data-comment-sort]');
        const totalLabel = commentsSection.querySelector('[data-comment-count]');

        let currentPage = 1;
        const perPage = 15;
        let totalComments = Number(commentsSection.dataset.totalComments || 0);
        let lastPage = Math.max(1, Math.ceil(totalComments / perPage));
        let currentOrder = sortSelect ? sortSelect.value : 'best';
        const loaderText = loadMoreBtn ? loadMoreBtn.innerHTML : '';

        const updateLoadMoreVisibility = () => {
            if (!loadMoreBtn) return;
            loadMoreBtn.classList.toggle('hidden', currentPage >= lastPage);
        };

        const fetchComments = async (page = 1, append = false) => {
            try {
                const params = new URLSearchParams({ page: String(page), order: currentOrder });
                const response = await fetch(`/api/mods/${modId}/comments?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) {
                    throw new Error('Failed to load comments');
                }

                const data = await response.json();
                if (!data || !data.success) {
                    throw new Error('Invalid response');
                }

                if (append) {
                    thread.insertAdjacentHTML('beforeend', data.html);
                } else {
                    thread.innerHTML = data.html;
                }

                currentPage = data.page;
                lastPage = data.last_page;

                if (typeof data.total !== 'undefined') {
                    totalComments = Number(data.total);
                    if (totalLabel) {
                        totalLabel.textContent = `(${totalComments.toLocaleString()})`;
                    }
                }

                lastPage = data.last_page ?? lastPage;
                updateLoadMoreVisibility();
            } catch (error) {
                console.error('Error loading comments:', error);
                if (loadMoreBtn) {
                    loadMoreBtn.innerHTML = 'Failed to load comments';
                }
            }
        };

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', async () => {
                if (loadMoreBtn.disabled) return;
                loadMoreBtn.disabled = true;
                loadMoreBtn.innerHTML = '<i class="fa-solid fa-rotate fa-spin"></i> Loading...';

                await fetchComments(currentPage + 1, true);

                loadMoreBtn.disabled = false;
                loadMoreBtn.innerHTML = loaderText;
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', async () => {
                currentOrder = sortSelect.value || 'best';
                currentPage = 1;
                await fetchComments(1, false);
            });
        }

        updateLoadMoreVisibility();
    }
</script>
@endpush
