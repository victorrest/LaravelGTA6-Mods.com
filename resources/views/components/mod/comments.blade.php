@props(['mod', 'comments'])

{{-- Comments Component (WordPress Style) --}}
<div class="space-y-6">
    <h4 class="font-bold text-lg mb-4 text-gray-900">Comments ({{ $mod->comments_count }})</h4>

    {{-- New Comment Form (Auth Users) --}}
    @auth
        <div class="mb-6">
            <form method="POST" action="{{ route('mods.comment', [$mod->primary_category ?? $mod->categories->first(), $mod]) }}" class="space-y-3">
                @csrf
                <div class="flex items-start space-x-3">
                    <img src="{{ auth()->user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=ec4899&color=fff' }}"
                         class="rounded-full w-10 h-10 object-cover flex-shrink-0"
                         alt="{{ auth()->user()->name }}'s avatar">
                    <div class="flex-1">
                        <textarea name="body" rows="3" required minlength="5" maxlength="1500"
                                  class="w-full p-3 bg-white border border-gray-300 rounded-md focus:ring-2 focus:ring-pink-500 focus:border-pink-500 text-gray-700 text-sm resize-none"
                                  placeholder="Write a comment..."></textarea>
                        @error('body')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="mt-2 btn-download font-semibold py-2 px-5 rounded-lg text-sm transition inline-flex items-center">
                            <i class="fa-solid fa-paper-plane mr-2"></i>
                            Post Comment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @else
        <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg text-center">
            <p class="text-sm text-gray-600">
                Please <a href="{{ route('login') }}" class="text-pink-600 hover:text-pink-700 font-semibold">log in</a> to post a comment.
            </p>
        </div>
    @endauth

    {{-- Comments List --}}
    <div class="space-y-4">
        @forelse ($comments as $comment)
            <div class="flex items-start space-x-3 p-4 bg-gray-50 rounded-lg border border-gray-200 hover:border-gray-300 transition">
                {{-- Avatar --}}
                <img src="{{ $comment->author->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($comment->author->name) . '&background=e5e7eb&color=374151' }}"
                     class="rounded-full w-10 h-10 object-cover flex-shrink-0"
                     alt="{{ $comment->author->name }}'s avatar">

                {{-- Comment Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center space-x-2">
                            <p class="font-semibold text-gray-900 text-sm">{{ $comment->author->name }}</p>

                            {{-- Creator Badge --}}
                            @if($comment->user_id === $mod->user_id)
                                <span class="text-xs font-semibold bg-pink-500 text-white px-2 py-0.5 rounded-full">Creator</span>
                            @endif

                            {{-- Admin Badge --}}
                            @if($comment->author->is_admin)
                                <span class="text-xs font-semibold bg-purple-500 text-white px-2 py-0.5 rounded-full">Admin</span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500 flex-shrink-0">{{ $comment->created_at->diffForHumans() }}</span>
                    </div>

                    {{-- Comment Body --}}
                    <p class="text-sm text-gray-700 break-words">{{ $comment->body }}</p>

                    {{-- Comment Actions --}}
                    <div class="flex items-center space-x-4 text-xs text-gray-500 mt-2">
                        @auth
                            <button type="button" class="hover:text-pink-600 font-semibold transition" data-comment-reply="{{ $comment->id }}">
                                <i class="fa-solid fa-reply mr-1"></i>Reply
                            </button>
                        @endauth

                        {{-- Like Button (Placeholder) --}}
                        <button type="button" class="hover:text-pink-600 flex items-center transition group" data-comment-like="{{ $comment->id }}">
                            <i class="fa-regular fa-thumbs-up mr-1 group-hover:fa-solid"></i>
                            <span>{{ $comment->likes_count ?? 0 }}</span>
                        </button>

                        {{-- Delete Button (Own Comments or Admin) --}}
                        @if(auth()->check() && (auth()->id() === $comment->user_id || auth()->user()->is_admin))
                            <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="hover:text-red-600 font-semibold transition">
                                    <i class="fa-solid fa-trash mr-1"></i>Delete
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Reply Form (Hidden by default) --}}
                    @auth
                        <div class="mt-3 hidden" data-comment-reply-form="{{ $comment->id }}">
                            <form method="POST" action="{{ route('mods.comment', [$mod->primary_category ?? $mod->categories->first(), $mod]) }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                <textarea name="body" rows="2" required minlength="5" maxlength="1500"
                                          class="w-full p-2 bg-white border border-gray-300 rounded-md focus:ring-2 focus:ring-pink-500 text-gray-700 text-sm resize-none"
                                          placeholder="Write a reply..."></textarea>
                                <div class="flex items-center space-x-2">
                                    <button type="submit" class="text-xs bg-pink-600 text-white font-semibold px-3 py-1.5 rounded hover:bg-pink-700 transition">
                                        Post Reply
                                    </button>
                                    <button type="button" class="text-xs text-gray-600 font-semibold px-3 py-1.5 hover:text-gray-800 transition" data-cancel-reply="{{ $comment->id }}">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endauth

                    {{-- Nested Replies (if any) --}}
                    @if($comment->replies && $comment->replies->count() > 0)
                        <div class="ml-6 mt-3 space-y-3 border-l-2 border-gray-200 pl-3">
                            @foreach($comment->replies as $reply)
                                <div class="flex items-start space-x-2">
                                    <img src="{{ $reply->author->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($reply->author->name) . '&background=e5e7eb&color=374151' }}"
                                         class="rounded-full w-8 h-8 object-cover flex-shrink-0"
                                         alt="{{ $reply->author->name }}'s avatar">
                                    <div class="flex-1 bg-white p-3 rounded-lg border border-gray-200">
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="flex items-center space-x-2">
                                                <p class="font-semibold text-gray-900 text-xs">{{ $reply->author->name }}</p>
                                                @if($reply->user_id === $mod->user_id)
                                                    <span class="text-xs font-semibold bg-pink-500 text-white px-1.5 py-0.5 rounded-full">Creator</span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-gray-500">{{ $reply->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-xs text-gray-700">{{ $reply->body }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-8 text-center bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                <i class="fa-regular fa-comments text-4xl text-gray-300 mb-2"></i>
                <p class="text-sm text-gray-500">No comments yet. Be the first to comment!</p>
            </div>
        @endforelse
    </div>

    {{-- Load More Button (if needed) --}}
    @if($comments->count() >= 20)
        <div class="text-center mt-6">
            <button type="button" class="px-6 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition" data-load-more-comments>
                <i class="fa-solid fa-chevron-down mr-2"></i>Load More Comments
            </button>
        </div>
    @endif
</div>

@push('scripts')
<script>
// Comment reply toggle
document.querySelectorAll('[data-comment-reply]').forEach(button => {
    button.addEventListener('click', function() {
        const commentId = this.dataset.commentReply;
        const form = document.querySelector(`[data-comment-reply-form="${commentId}"]`);
        if (form) {
            form.classList.toggle('hidden');
            if (!form.classList.contains('hidden')) {
                form.querySelector('textarea').focus();
            }
        }
    });
});

// Cancel reply
document.querySelectorAll('[data-cancel-reply]').forEach(button => {
    button.addEventListener('click', function() {
        const commentId = this.dataset.cancelReply;
        const form = document.querySelector(`[data-comment-reply-form="${commentId}"]`);
        if (form) {
            form.classList.add('hidden');
            form.querySelector('textarea').value = '';
        }
    });
});
</script>
@endpush
