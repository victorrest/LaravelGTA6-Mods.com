@foreach ($comments as $comment)
    <article class="card border border-gray-200 p-4 md:p-5" data-comment-id="{{ $comment->id }}">
        <div class="flex items-start gap-3">
            <img
                src="{{ $comment->author->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($comment->author->name) . '&background=e5e7eb&color=374151' }}"
                alt="{{ $comment->author->name }} avatar"
                class="h-11 w-11 rounded-full object-cover"
            >
            <div class="flex-1">
                <header class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">{{ $comment->author->name }}</span>
                    @if ($comment->user_id === $mod->user_id)
                        <span class="rounded-full bg-pink-100 px-2 py-0.5 text-xs font-semibold text-pink-600">Creator</span>
                    @endif
                    @if ($comment->author->is_admin)
                        <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-600">Admin</span>
                    @endif
                    <time datetime="{{ $comment->created_at->toIso8601String() }}" class="ml-auto text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</time>
                </header>
                <div class="prose prose-sm mt-3 max-w-none text-gray-700">
                    <p>{{ $comment->body }}</p>
                </div>
                <footer class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                    @auth
                        <button type="button" class="flex items-center gap-2 font-semibold hover:text-pink-600" data-comment-reply="{{ $comment->id }}">
                            <i class="fa-solid fa-reply"></i>
                            Reply
                        </button>
                    @endauth
                    <span class="flex items-center gap-1">
                        <i class="fa-regular fa-thumbs-up"></i>
                        <span>{{ number_format($comment->likes_count ?? 0) }}</span>
                    </span>
                    @if(auth()->check() && (auth()->id() === $comment->user_id || auth()->user()->is_admin))
                        <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}" class="inline" onsubmit="return confirm('Delete this comment?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex items-center gap-2 font-semibold text-red-600 hover:text-red-700">
                                <i class="fa-solid fa-trash"></i>
                                Delete
                            </button>
                        </form>
                    @endif
                </footer>

                @auth
                    <div class="mt-3 hidden" data-comment-reply-form="{{ $comment->id }}">
                        <form method="POST" action="{{ route('mods.comment', [$mod->primary_category ?? $mod->categories->first(), $mod]) }}" class="space-y-2">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                            <textarea
                                name="body"
                                rows="3"
                                minlength="5"
                                maxlength="1500"
                                required
                                class="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-pink-500 focus:outline-none focus:ring-2 focus:ring-pink-200"
                                placeholder="Write a reply..."
                            ></textarea>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-pink-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-pink-700">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    Reply
                                </button>
                                <button type="button" data-cancel-reply="{{ $comment->id }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Cancel</button>
                            </div>
                        </form>
                    </div>
                @endauth

                @if ($comment->replies && $comment->replies->isNotEmpty())
                    <div class="mt-5 space-y-3 border-l border-gray-200 pl-5">
                        @foreach ($comment->replies as $reply)
                            <div class="flex items-start gap-3" data-comment-id="{{ $reply->id }}">
                                <img
                                    src="{{ $reply->author->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($reply->author->name) . '&background=e5e7eb&color=374151' }}"
                                    alt="{{ $reply->author->name }} avatar"
                                    class="h-9 w-9 rounded-full object-cover"
                                >
                                <div class="flex-1 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-900">{{ $reply->author->name }}</span>
                                        @if ($reply->user_id === $mod->user_id)
                                            <span class="rounded-full bg-pink-100 px-2 py-0.5 text-[11px] font-semibold text-pink-600">Creator</span>
                                        @endif
                                        <time datetime="{{ $reply->created_at->toIso8601String() }}" class="ml-auto text-[11px] text-gray-500">{{ $reply->created_at->diffForHumans() }}</time>
                                    </div>
                                    <p class="mt-2 leading-relaxed">{{ $reply->body }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </article>
@endforeach

@if($comments->isEmpty())
    <div class="card border border-dashed border-gray-200 bg-gray-50 py-10 text-center">
        <i class="fa-regular fa-comments text-4xl text-gray-300"></i>
        <p class="mt-3 text-sm text-gray-500">No comments yet. Be the first to share your thoughts!</p>
    </div>
@endif
