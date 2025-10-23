<div id="overview-tab" data-tab-content="overview" class="tab-content {{ $activeTab !== 'overview' ? 'hidden' : '' }}">
    <!-- Status Update Form (Owner Only) -->
    @if($isOwner)
        <div class="mb-8">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="bg-gray-100 text-gray-500 rounded-full h-12 w-12 flex items-center justify-center">
                        <i class="fas fa-comment-dots text-xl"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="border border-gray-200 rounded-lg overflow-hidden focus-within:border-pink-500 focus-within:ring-2 focus-within:ring-pink-100">
                        <div id="status-update-textarea"
                             class="w-full p-4 bg-transparent outline-none min-h-[80px]"
                             contenteditable="true"
                             data-placeholder="Share an update, {{ $author->name }}..."
                             data-maxlength="5000"></div>
                        <div id="status-update-actions" class="hidden bg-gray-50 px-4 py-3 flex justify-between items-center">
                            <span id="status-update-counter" class="text-sm text-gray-500">0/5000</span>
                            <button id="status-update-submit"
                                    type="button"
                                    class="btn-action py-2 px-6 rounded-lg font-semibold text-sm">
                                Publish
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Pinned Mod Section -->
    @if($pinnedMod)
        <div class="mb-8">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-thumbtack text-pink-600"></i>
                    Pinned Mod
                </h3>
                @if($isOwner)
                    <button
                        class="text-sm text-gray-500 hover:text-pink-600 transition"
                        data-unpin-url="{{ route('profile.mod.unpin') }}"
                        onclick="unpinMod(this)"
                    >
                        <i class="fas fa-times mr-1"></i>Unpin
                    </button>
                @endif
            </div>

            <a href="{{ route('mods.show', [$pinnedMod->primary_category, $pinnedMod]) }}" class="block bg-gradient-to-r from-pink-50 to-purple-50 rounded-lg p-4 border-2 border-pink-200 hover:border-pink-300 transition group">
                <div class="flex gap-4">
                    @if($pinnedMod->thumbnail_url)
                        <img src="{{ $pinnedMod->thumbnail_url }}" alt="{{ $pinnedMod->title }}" class="w-32 h-20 object-cover rounded-md flex-shrink-0 group-hover:shadow-md transition">
                    @endif
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-gray-900 text-lg mb-1 group-hover:text-pink-600 transition">{{ $pinnedMod->title }}</h4>
                        <p class="text-sm text-gray-600 line-clamp-2 mb-2">{{ $pinnedMod->description }}</p>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="text-gray-600">
                                <i class="fas fa-download mr-1 text-pink-600"></i>
                                <strong>{{ number_format($pinnedMod->downloads) }}</strong> downloads
                            </span>
                            @if($pinnedMod->average_rating)
                                <span class="text-gray-600">
                                    <i class="fas fa-star mr-1 text-yellow-500"></i>
                                    <strong>{{ number_format($pinnedMod->average_rating, 1) }}</strong> rating
                                </span>
                            @endif
                            <span class="text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                {{ $pinnedMod->updated_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endif

    <!-- Recent Activity Header -->
    <h3 class="text-lg font-bold text-gray-800 mb-4 pt-4 border-t">Recent activity</h3>

    <!-- Activity Feed -->
    <div id="activity-feed" class="space-y-4">
        @foreach($recentActivities as $activity)
            @include('author.partials.activity-item', ['activity' => $activity])
        @endforeach

        @if($recentActivities->isEmpty())
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                <p>No recent activity yet</p>
            </div>
        @endif
    </div>

    <!-- Most Popular Mods -->
    @if($popularMods->count() > 0)
        <h3 class="text-lg font-bold text-gray-800 mb-4 pt-6 border-t mt-6">Most popular mods</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            @foreach($popularMods as $mod)
                @include('author.partials.mod-card', ['mod' => $mod])
            @endforeach
        </div>
    @endif
</div>

@push('scripts')
<script>
// Unpin mod function
async function unpinMod(button) {
    if (!confirm('Are you sure you want to unpin this mod from your profile?')) {
        return;
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const targetUrl = button?.dataset?.unpinUrl;

        if (!targetUrl) {
            throw new Error('Unpin endpoint is not available.');
        }

        const response = await fetch(targetUrl, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to unpin mod');
        }

        location.reload();
    } catch (error) {
        console.error('Error unpinning mod:', error);
        alert(error.message || 'Failed to unpin mod. Please try again.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const statusTextarea = document.getElementById('status-update-textarea');
    const statusActions = document.getElementById('status-update-actions');
    const statusCounter = document.getElementById('status-update-counter');
    const statusSubmit = document.getElementById('status-update-submit');

    if (statusTextarea) {
        // Set placeholder style
        if (statusTextarea.textContent.trim() === '') {
            statusTextarea.classList.add('empty');
        }

        statusTextarea.addEventListener('input', function() {
            const text = this.textContent;
            const length = text.length;

            // Update counter
            statusCounter.textContent = `${length}/5000`;

            // Show/hide actions
            if (length > 0) {
                statusActions.classList.remove('hidden');
                this.classList.remove('empty');
            } else {
                statusActions.classList.add('hidden');
                this.classList.add('empty');
            }

            // Prevent exceeding max length
            if (length > 5000) {
                this.textContent = text.substring(0, 5000);

                // Place cursor at end
                const range = document.createRange();
                const sel = window.getSelection();
                range.selectNodeContents(this);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        });

        statusTextarea.addEventListener('focus', function() {
            this.classList.remove('empty');
        });

        statusTextarea.addEventListener('blur', function() {
            if (this.textContent.trim() === '') {
                this.classList.add('empty');
            }
        });

        // Submit status
        if (statusSubmit) {
            statusSubmit.addEventListener('click', async function() {
                const content = statusTextarea.textContent.trim();

                if (!content) {
                    alert('Status update cannot be empty');
                    return;
                }

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                    const response = await fetch('/activity/status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ content })
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // Clear textarea
                        statusTextarea.textContent = '';
                        statusTextarea.classList.add('empty');
                        statusActions.classList.add('hidden');

                        // Reload activity feed
                        location.reload();
                    } else {
                        const errorMsg = data.message || data.errors?.content?.[0] || 'Failed to post status update';
                        alert(errorMsg);
                        console.error('Server error:', data);
                    }
                } catch (error) {
                    console.error('Error posting status:', error);
                    alert('Network error: Failed to post status update. Please check your connection.');
                }
            });
        }
    }
});

// Add CSS for placeholder
const style = document.createElement('style');
style.textContent = `
    #status-update-textarea.empty:before {
        content: attr(data-placeholder);
        color: #9ca3af;
        pointer-events: none;
    }
    #status-update-textarea.empty:focus:before {
        content: '';
    }
`;
document.head.appendChild(style);
</script>
@endpush
