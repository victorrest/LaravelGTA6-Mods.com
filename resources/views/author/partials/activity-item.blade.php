<div class="flex items-start gap-4" data-activity-item>
    @php
        $iconClass = 'fa-bolt';
        $iconBg = 'bg-yellow-100';
        $iconColor = 'text-yellow-600';
        $actionText = '';

        switch($activity->action_type) {
            case App\Models\UserActivity::TYPE_STATUS_UPDATE:
                $iconClass = 'fa-comment-dots';
                $iconBg = 'bg-purple-100';
                $iconColor = 'text-purple-600';
                $actionText = 'shared a new status update';
                break;
            case App\Models\UserActivity::TYPE_MOD_UPLOAD:
                $iconClass = 'fa-upload';
                $iconBg = 'bg-pink-100';
                $iconColor = 'text-pink-600';
                $actionText = 'published a new mod';
                break;
            case App\Models\UserActivity::TYPE_COMMENT:
                $iconClass = 'fa-comments';
                $iconBg = 'bg-green-100';
                $iconColor = 'text-green-600';
                $actionText = 'commented on';
                break;
            case App\Models\UserActivity::TYPE_FOLLOW:
                $iconClass = 'fa-user-check';
                $iconBg = 'bg-indigo-100';
                $iconColor = 'text-indigo-600';
                $actionText = 'followed';
                break;
            case App\Models\UserActivity::TYPE_BOOKMARK:
                $iconClass = 'fa-bookmark';
                $iconBg = 'bg-blue-100';
                $iconColor = 'text-blue-600';
                $actionText = 'bookmarked';
                break;
        }
    @endphp

    <!-- Activity Icon -->
    <div class="{{ $iconBg }} {{ $iconColor }} rounded-full h-9 w-9 flex-shrink-0 flex items-center justify-center">
        <i class="fas {{ $iconClass }}"></i>
    </div>

    <!-- Activity Content -->
    <div class="flex-1 min-w-0">
        @if($activity->action_type === App\Models\UserActivity::TYPE_STATUS_UPDATE)
            <!-- Status Update -->
            <p class="text-sm">
                <strong class="font-semibold">{{ $activity->user->name }}</strong> {{ $actionText }}
            </p>
            <div class="mt-2 p-3 bg-gray-100 text-sm text-gray-700 rounded-md">
                &ldquo;{{ $activity->content }}&rdquo;
            </div>
            <div class="flex items-center gap-3 mt-1.5 text-xs text-gray-500">
                <span>{{ $activity->created_at->diffForHumans() }}</span>
                @if(auth()->check() && auth()->id() === $activity->user_id)
                    <button class="font-semibold text-gray-500 hover:text-red-600 delete-status-btn" data-activity-id="{{ $activity->id }}">
                        Delete
                    </button>
                @endif
            </div>

        @elseif($activity->action_type === App\Models\UserActivity::TYPE_MOD_UPLOAD && $activity->subject)
            <!-- Mod Upload -->
            <p class="text-sm mb-2">
                <strong class="font-semibold">{{ $activity->user->name }}</strong> {{ $actionText }}
            </p>
            @php
                $mod = $activity->subject;
            @endphp
            <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="flex gap-4 p-3 rounded-lg bg-gray-50 hover:bg-gray-100 border border-gray-200 transition">
                @if($mod->thumbnail_url)
                    <img src="{{ $mod->thumbnail_url }}" class="w-24 h-14 object-cover rounded-md flex-shrink-0" alt="{{ $mod->title }}">
                @endif
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-gray-800 line-clamp-1">{{ $mod->title }}</h4>
                    <div class="flex items-center space-x-3 text-xs text-gray-500 mt-1">
                        <span><i class="fas fa-download mr-1"></i>{{ number_format($mod->downloads) }}</span>
                        @if($mod->average_rating)
                            <span><i class="fas fa-star mr-1 text-yellow-500"></i>{{ number_format($mod->average_rating, 1) }}</span>
                        @endif
                    </div>
                </div>
            </a>
            <p class="text-xs text-gray-500 mt-1.5">{{ $activity->created_at->diffForHumans() }}</p>

        @elseif($activity->action_type === App\Models\UserActivity::TYPE_COMMENT && $activity->subject)
            <!-- Comment Activity -->
            @php
                $comment = $activity->subject;
                $mod = $comment->mod ?? null;
            @endphp
            <p class="text-sm">
                <strong class="font-semibold">{{ $activity->user->name }}</strong> {{ $actionText }}
                @if($mod)
                    <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="font-semibold text-pink-600 hover:underline">{{ $mod->title }}</a>
                @else
                    a mod
                @endif
            </p>
            <div class="relative p-4 rounded-lg bg-gray-100 mt-2">
                <div class="text-gray-800 leading-relaxed text-sm">
                    &ldquo;{{ Str::words($comment->content, 45) }}&rdquo;
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-1.5">{{ $activity->created_at->diffForHumans() }}</p>

        @elseif($activity->action_type === App\Models\UserActivity::TYPE_FOLLOW && $activity->subject)
            <!-- Follow Activity -->
            <p class="text-sm">
                <strong class="font-semibold">{{ $activity->user->name }}</strong> {{ $actionText }}
                <a href="{{ route('author.profile', $activity->subject->name) }}" class="font-semibold text-pink-600 hover:underline">{{ $activity->subject->name }}</a>
            </p>
            <p class="text-xs text-gray-500 mt-1.5">{{ $activity->created_at->diffForHumans() }}</p>

        @elseif($activity->action_type === App\Models\UserActivity::TYPE_BOOKMARK && $activity->subject)
            <!-- Bookmark Activity -->
            @php
                $mod = $activity->subject;
            @endphp
            <p class="text-sm">
                <strong class="font-semibold">{{ $activity->user->name }}</strong> {{ $actionText }}
                <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="font-semibold text-pink-600 hover:underline">{{ $mod->title }}</a>
            </p>
            <p class="text-xs text-gray-500 mt-1.5">{{ $activity->created_at->diffForHumans() }}</p>

        @else
            <!-- Generic Activity -->
            <p class="text-sm">
                <strong class="font-semibold">{{ $activity->user->name }}</strong> {{ $activity->action_type }}
            </p>
            <p class="text-xs text-gray-500 mt-1.5">{{ $activity->created_at->diffForHumans() }}</p>
        @endif
    </div>
</div>

@once
@push('scripts')
<script>
// Delete status functionality
document.addEventListener('click', async function(e) {
    if (e.target.closest('.delete-status-btn')) {
        e.preventDefault();
        const btn = e.target.closest('.delete-status-btn');
        const activityId = btn.dataset.activityId;

        if (confirm('Are you sure you want to delete this status update?')) {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch(`/activity/status/${activityId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to delete status');
                }
            } catch (error) {
                console.error('Error deleting status:', error);
                alert('Failed to delete status');
            }
        }
    }
});
</script>
@endpush
@endonce
