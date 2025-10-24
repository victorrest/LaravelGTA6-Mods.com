<div class="mod-card bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-lg transition-all duration-200">
    @if($mod->thumbnail_url)
        <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="block relative overflow-hidden group">
            <img src="{{ $mod->thumbnail_url }}" alt="{{ $mod->title }}" class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-200">

            <!-- Overlay stats -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                <div class="absolute bottom-0 left-0 right-0 p-4 flex items-center justify-between text-white text-sm">
                    <span><i class="fas fa-download mr-1"></i> {{ number_format($mod->downloads) }}</span>
                    <span><i class="fas fa-star mr-1"></i> {{ $mod->average_rating ? number_format($mod->average_rating, 1) : 'N/A' }}</span>
                </div>
            </div>

            @if($mod->is_featured)
                <div class="absolute top-3 left-3 bg-pink-600 text-white text-xs font-bold px-2 py-1 rounded">
                    FEATURED
                </div>
            @endif
        </a>
    @endif

    <div class="p-4">
        <h4 class="font-bold text-gray-800 mb-2 line-clamp-2">
            <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="hover:text-pink-600 transition-colors">
                {{ $mod->title }}
            </a>
        </h4>

        @if($mod->description)
            <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                {{ Str::limit($mod->description, 100) }}
            </p>
        @endif

        <div class="flex items-center justify-between text-sm">
            <div class="text-gray-500">
                <span><i class="fas fa-download mr-1"></i> {{ number_format($mod->downloads) }}</span>
            </div>

            <div class="flex items-center gap-2">
                @if($mod->average_rating)
                    <span class="text-yellow-500">
                        <i class="fas fa-star mr-1"></i>
                        {{ number_format($mod->average_rating, 1) }}
                    </span>
                @endif

                @auth
                    <button class="bookmark-toggle-btn text-gray-400 hover:text-pink-600 transition-colors"
                            data-mod-id="{{ $mod->id }}"
                            data-bookmarked="{{ auth()->user()->hasBookmarked($mod) ? 'true' : 'false' }}"
                            title="{{ auth()->user()->hasBookmarked($mod) ? 'Remove bookmark' : 'Bookmark' }}">
                        <i class="fas fa-bookmark {{ auth()->user()->hasBookmarked($mod) ? 'text-pink-600' : '' }}"></i>
                    </button>
                @endauth
            </div>
        </div>

        <!-- Categories -->
        @if($mod->categories->count() > 0)
            <div class="mt-3 flex flex-wrap gap-1">
                @foreach($mod->categories->take(2) as $category)
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                        {{ $category->name }}
                    </span>
                @endforeach
                @if($mod->categories->count() > 2)
                    <span class="text-xs text-gray-500">+{{ $mod->categories->count() - 2 }}</span>
                @endif
            </div>
        @endif

        <div class="mt-3 text-xs text-gray-400">
            Updated {{ $mod->updated_at->diffForHumans() }}
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
// Bookmark toggle functionality
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.bookmark-toggle-btn');
    if (!btn) return;

    e.preventDefault();

    const modId = btn.dataset.modId;
    const bookmarked = btn.dataset.bookmarked === 'true';

    try {
        const response = await fetch(`/api/bookmarks/${modId}/toggle`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const data = await response.json();

        if (data.success) {
            btn.dataset.bookmarked = data.bookmarked ? 'true' : 'false';
            const icon = btn.querySelector('i');

            if (data.bookmarked) {
                icon.classList.add('text-pink-600');
                btn.title = 'Remove bookmark';
            } else {
                icon.classList.remove('text-pink-600');
                btn.title = 'Bookmark';
            }
        }
    } catch (error) {
        console.error('Error toggling bookmark:', error);
    }
});
</script>
@endpush
@endonce
