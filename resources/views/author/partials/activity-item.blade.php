<div class="activity-item">
    <div class="flex items-start gap-4">
        <img src="{{ $activity->user->getAvatarUrl(48) }}" alt="{{ $activity->user->name }}" class="w-12 h-12 rounded-full object-cover flex-shrink-0">

        <div class="flex-1 min-w-0">
            @if($activity->action_type === App\Models\UserActivity::TYPE_STATUS_UPDATE)
                <!-- Status Update -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="prose prose-sm max-w-none text-gray-700">
                        {{ $activity->content }}
                    </div>
                    <div class="flex items-center justify-between mt-3 text-xs text-gray-500">
                        <span>{{ $activity->created_at->diffForHumans() }}</span>
                        @if(auth()->check() && auth()->id() === $activity->user_id)
                            <button class="text-red-600 hover:text-red-700 delete-status-btn" data-activity-id="{{ $activity->id }}">
                                <i class="fas fa-trash-alt mr-1"></i>Delete
                            </button>
                        @endif
                    </div>
                </div>

            @elseif($activity->action_type === App\Models\UserActivity::TYPE_MOD_UPLOAD && $activity->subject)
                <!-- Mod Upload -->
                <div>
                    <p class="text-gray-700 mb-2">
                        <span class="font-semibold">{{ $activity->user->name }}</span> uploaded a new mod
                    </p>
                    <a href="{{ route('mods.show', [$activity->subject->primary_category, $activity->subject]) }}" class="block bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition">
                        <div class="flex items-center gap-3">
                            @if($activity->subject->thumbnail_url)
                                <img src="{{ $activity->subject->thumbnail_url }}" alt="{{ $activity->subject->title }}" class="w-16 h-16 rounded object-cover">
                            @endif
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-800 truncate">{{ $activity->subject->title }}</h4>
                                <p class="text-sm text-gray-600 truncate">{{ $activity->subject->description }}</p>
                            </div>
                        </div>
                    </a>
                    <p class="text-xs text-gray-500 mt-2">{{ $activity->created_at->diffForHumans() }}</p>
                </div>

            @elseif($activity->action_type === App\Models\UserActivity::TYPE_COMMENT && $activity->subject)
                <!-- Comment -->
                <div>
                    <p class="text-gray-700 mb-2">
                        <span class="font-semibold">{{ $activity->user->name }}</span> commented on
                        @if($activity->subject->mod)
                            <a href="{{ route('mods.show', [$activity->subject->mod->primary_category, $activity->subject->mod]) }}" class="text-pink-600 hover:underline">
                                {{ $activity->subject->mod->title }}
                            </a>
                        @else
                            a mod
                        @endif
                    </p>
                    <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                        {{ Str::limit($activity->subject->content, 150) }}
                    </div>
                    <p class="text-xs text-gray-500 mt-2">{{ $activity->created_at->diffForHumans() }}</p>
                </div>

            @elseif($activity->action_type === App\Models\UserActivity::TYPE_FOLLOW && $activity->subject)
                <!-- Follow -->
                <div>
                    <p class="text-gray-700">
                        <span class="font-semibold">{{ $activity->user->name }}</span> started following
                        <a href="{{ route('author.profile', $activity->subject->name) }}" class="text-pink-600 hover:underline font-semibold">
                            {{ $activity->subject->name }}
                        </a>
                    </p>
                    <p class="text-xs text-gray-500 mt-2">{{ $activity->created_at->diffForHumans() }}</p>
                </div>

            @else
                <!-- Generic Activity -->
                <div>
                    <p class="text-gray-700">
                        <span class="font-semibold">{{ $activity->user->name }}</span> {{ $activity->action_type }}
                    </p>
                    <p class="text-xs text-gray-500 mt-2">{{ $activity->created_at->diffForHumans() }}</p>
                </div>
            @endif
        </div>
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
                const response = await fetch(`/activity/status/${activityId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
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
