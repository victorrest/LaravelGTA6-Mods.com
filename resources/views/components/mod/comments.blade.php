@props(['mod', 'comments'])

@php
    // Recursive function to render comments with proper depth
    function renderComment($comment, $mod, $depth = 1, $maxDepth = 6) {
        $userLoggedIn = auth()->check();
        $isLiked = $userLoggedIn && $comment->isLikedBy(auth()->user());
        $isCreator = $comment->user_id === $mod->user_id;
        $canDelete = $userLoggedIn && (auth()->id() === $comment->user_id || auth()->user()->is_admin);
        $canReply = $depth < $maxDepth;

        // Depth classes for responsive threading
        $depthClass = 'depth-' . $depth;
        $wrapperClasses = "comment-wrapper {$depthClass}";

        $authorName = $comment->author->name ?? 'Unknown User';
        $authorAvatar = $comment->author->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($authorName) . '&background=e5e7eb&color=374151';
        $timeLabel = $comment->created_at->diffForHumans();

        echo '<div id="comment-' . $comment->id . '" class="' . $wrapperClasses . '" data-comment-id="' . $comment->id . '" data-comment-depth="' . $depth . '">';
        echo '<div class="comment-instance flex space-x-3">';

        // Left column with avatar and thread line
        echo '<div class="clickable-area flex-shrink-0 flex flex-col items-center" role="button" tabindex="0" aria-expanded="true">';
        echo '<img src="' . e($authorAvatar) . '" class="avatar rounded-full w-10 h-10 object-cover" alt="' . e($authorName) . '">';
        echo '<div class="collapsed-icon hidden w-10 h-10 rounded-full bg-gray-200 items-center justify-center text-gray-500 hover:bg-gray-300">';
        echo '<i class="fas fa-plus"></i>';
        echo '</div>';
        echo '<div class="comment-thread-line w-0.5 mt-2 bg-gray-200 hover:bg-pink-500 flex-grow"></div>';
        echo '</div>';

        // Main content
        echo '<div class="comment-main-content flex-1">';

        // Author line
        echo '<p class="font-semibold text-gray-900 text-sm">';
        echo '<a href="' . route('author.profile', $comment->user_id) . '" class="comment-author-link hover:text-pink-600">' . e($authorName) . '</a>';

        if ($isCreator) {
            echo '<span class="text-xs text-white font-semibold bg-pink-500 px-1.5 py-0.5 rounded-full ml-1">Creator</span>';
        }

        if ($comment->author && $comment->author->is_admin) {
            echo '<span class="text-xs text-white font-semibold bg-purple-500 px-1.5 py-0.5 rounded-full ml-1">Admin</span>';
        }

        echo '<time datetime="' . $comment->created_at->toIso8601String() . '">';
        echo '<span class="text-xs text-gray-500 font-normal whitespace-nowrap ml-1">' . e($timeLabel) . '</span>';
        echo '</time>';
        echo '<span class="collapsed-text hidden text-xs font-normal text-gray-500">[+]</span>';
        echo '</p>';

        // Comment body
        echo '<div class="comment-body">';
        echo '<div class="mt-1 text-gray-800 leading-relaxed comment-content text-sm">' . nl2br(e($comment->body)) . '</div>';

        // Actions
        echo '<div class="flex items-center space-x-4 text-xs text-gray-500 mt-2">';

        if ($canReply && $userLoggedIn) {
            echo '<button type="button" class="reply-btn hover:text-pink-600 font-semibold" data-comment-id="' . $comment->id . '" data-comment-author="' . e($authorName) . '">Reply</button>';
        }

        // Like button
        $likeCount = $comment->likes_count ?? 0;
        $likePressed = $isLiked ? 'true' : 'false';
        $likeClass = $isLiked ? 'comment-like-btn hover:text-pink-600 flex items-center font-semibold text-pink-600' : 'comment-like-btn hover:text-pink-600 flex items-center';

        if ($userLoggedIn) {
            echo '<button type="button" class="' . $likeClass . '" data-comment-id="' . $comment->id . '" aria-pressed="' . $likePressed . '">';
            echo '<i class="fas fa-thumbs-up mr-1"></i> <span class="comment-like-count">' . $likeCount . '</span>';
            echo '</button>';
        } else {
            echo '<span class="flex items-center text-gray-400">';
            echo '<i class="fas fa-thumbs-up mr-1"></i> <span>' . $likeCount . '</span>';
            echo '</span>';
        }

        // Delete button
        if ($canDelete) {
            echo '<form method="POST" action="' . route('admin.comments.destroy', $comment) . '" class="inline" onsubmit="return confirm(\'Are you sure you want to delete this comment?\')">';
            echo csrf_field();
            echo method_field('DELETE');
            echo '<button type="submit" class="hover:text-red-600 font-semibold"><i class="fa-solid fa-trash mr-1"></i>Delete</button>';
            echo '</form>';
        }

        echo '</div>'; // End actions

        // Reply form
        if ($canReply && $userLoggedIn) {
            $primaryCategory = $mod->primary_category ?? $mod->categories->first();
            echo '<div id="reply-form-container-' . $comment->id . '" class="reply-form-container hidden mt-4">';
            echo '<form method="POST" action="' . route('mods.comment', [$primaryCategory, $mod]) . '" class="comment-box-container border border-gray-300 rounded-lg focus-within:ring-2 focus-within:ring-pink-500 overflow-hidden transition-all">';
            echo csrf_field();
            echo '<input type="hidden" name="parent_id" value="' . $comment->id . '">';
            echo '<div class="comment-rich-editor">';
            echo '<div class="comment-box-textarea w-full p-3 text-sm bg-transparent border-0 focus:ring-0 is-empty" contenteditable="true" data-placeholder="Replying to @' . e($authorName) . '..." data-comment-reply-editor="' . $comment->id . '"></div>';
            echo '</div>';
            echo '<textarea name="body" class="hidden" required minlength="5" maxlength="1500" data-comment-reply-textarea="' . $comment->id . '"></textarea>';
            echo '<div class="comment-actions-bar px-3 pt-1.5 pb-3 flex justify-end items-center gap-x-2">';
            echo '<button type="button" class="cancel-reply-btn text-gray-500 font-semibold py-1.5 px-3 rounded-md text-xs transition hover:bg-gray-100" data-comment-id="' . $comment->id . '">Cancel</button>';
            echo '<button type="submit" class="post-reply-btn bg-pink-500 hover:bg-pink-600 text-white font-semibold py-1.5 px-3 rounded-lg text-xs transition" data-comment-id="' . $comment->id . '">Reply</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }

        echo '</div>'; // End comment-body
        echo '</div>'; // End comment-main-content
        echo '</div>'; // End comment-instance

        // Render replies
        if ($comment->replies && $comment->replies->count() > 0) {
            echo '<div class="comment-replies">';
            foreach ($comment->replies as $reply) {
                renderComment($reply, $mod, $depth + 1, $maxDepth);
            }
            echo '</div>';
        }

        echo '</div>'; // End comment-wrapper
    }
@endphp

{{-- Comments Component (WordPress Style - Exact Match) --}}
<div id="gta6-comments" class="space-y-6">
    <h4 class="font-bold text-lg mb-4 text-gray-900">Comments ({{ $mod->comments_count }})</h4>

    {{-- New Comment Form (Auth Users) --}}
    @auth
        <div class="mb-6">
            <form method="POST" action="{{ route('mods.comment', [$mod->primary_category ?? $mod->categories->first(), $mod]) }}" id="main-comment-form">
                @csrf
                <div class="flex items-start space-x-3">
                    <img src="{{ auth()->user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=ec4899&color=fff' }}"
                         class="rounded-full w-10 h-10 object-cover flex-shrink-0"
                         alt="{{ auth()->user()->name }}'s avatar">
                    <div class="flex-1">
                        <div class="comment-box-container border border-gray-300 rounded-lg focus-within:ring-2 focus-within:ring-pink-500 overflow-hidden transition-all">
                            <div class="comment-rich-editor">
                                <div class="comment-box-textarea w-full p-3 text-sm bg-transparent border-0 focus:ring-0 is-empty"
                                     contenteditable="true"
                                     data-placeholder="Write a comment..."
                                     id="main-comment-editor"></div>
                            </div>
                            <textarea name="body" class="hidden" required minlength="5" maxlength="1500" id="main-comment-textarea"></textarea>
                            <div class="comment-actions-bar hidden px-3 pt-1.5 pb-3 flex justify-end items-center">
                                <button type="submit" class="bg-pink-500 hover:bg-pink-600 text-white font-semibold py-1.5 px-3 rounded-lg text-xs transition">
                                    <i class="fa-solid fa-paper-plane mr-1"></i>Post Comment
                                </button>
                            </div>
                        </div>
                        @error('body')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
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
    <div class="comments-list space-y-4">
        @forelse ($comments as $comment)
            {!! renderComment($comment, $mod) !!}
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

{{-- Include WordPress comment styles --}}
@push('styles')
<style>
{!! file_get_contents(base_path('GTA6ModsWP/assets/css/comments.css')) !!}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/comments.js') }}"></script>
@endpush
