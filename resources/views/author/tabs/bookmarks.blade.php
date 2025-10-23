@if($isOwner)
<div id="bookmarks-tab" data-tab-content="bookmarks" class="tab-content hidden">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Bookmarked Mods</h3>

    <div class="text-center py-12" data-loading>
        <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
        <p class="text-gray-500">Loading bookmarks...</p>
    </div>

    <div id="bookmarks-content" class="hidden">
        <div id="bookmarks-list" class="grid grid-cols-1 sm:grid-cols-2 gap-6"></div>

        <div id="bookmarks-empty" class="hidden text-center py-12 text-gray-500">
            <i class="fas fa-bookmark text-4xl mb-3 text-gray-300"></i>
            <p>No bookmarked mods yet</p>
            <p class="text-sm mt-2">Bookmark your favorite mods to save them here!</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function loadBookmarks() {
    const bookmarksContent = document.getElementById('bookmarks-content');
    const bookmarksList = document.getElementById('bookmarks-list');
    const bookmarksEmpty = document.getElementById('bookmarks-empty');
    const loadingDiv = document.querySelector('#bookmarks-tab [data-loading]');

    try {
        const response = await fetch('/api/bookmarks');
        const data = await response.json();

        loadingDiv.classList.add('hidden');
        bookmarksContent.classList.remove('hidden');

        if (data.bookmarks && data.bookmarks.length > 0) {
            bookmarksList.innerHTML = data.bookmarks.map(mod => `
                <div class="mod-card bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition">
                    ${mod.thumbnail_url ? `
                        <a href="${mod.url}">
                            <img src="${mod.thumbnail_url}" alt="${mod.title}" class="w-full h-48 object-cover">
                        </a>
                    ` : ''}
                    <div class="p-4">
                        <h4 class="font-bold text-gray-800 mb-2">
                            <a href="${mod.url}" class="hover:text-pink-600">${mod.title}</a>
                        </h4>
                        <p class="text-sm text-gray-600 mb-3 line-clamp-2">${mod.description || ''}</p>
                        <div class="flex items-center justify-between text-sm">
                            <div class="text-gray-500">
                                <span><i class="fas fa-download mr-1"></i> ${mod.downloads.toLocaleString()}</span>
                                <span class="ml-3"><i class="fas fa-star mr-1 text-yellow-500"></i> ${mod.rating || 'N/A'}</span>
                            </div>
                            <button class="text-pink-600 hover:text-pink-700 unbookmark-btn" data-mod-id="${mod.id}">
                                <i class="fas fa-bookmark"></i>
                            </button>
                        </div>
                        <div class="text-xs text-gray-400 mt-2">
                            Bookmarked ${mod.bookmarked_at}
                        </div>
                    </div>
                </div>
            `).join('');

            // Add unbookmark listeners
            document.querySelectorAll('.unbookmark-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const modId = this.dataset.modId;
                    await toggleBookmark(modId);
                });
            });

            bookmarksEmpty.classList.add('hidden');
        } else {
            bookmarksList.innerHTML = '';
            bookmarksEmpty.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading bookmarks:', error);
        loadingDiv.innerHTML = '<p class="text-red-500">Failed to load bookmarks</p>';
    }
}

async function toggleBookmark(modId) {
    try {
        const response = await fetch(`/api/bookmarks/${modId}/toggle`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const data = await response.json();
        if (data.success) {
            loadBookmarks(); // Reload list
        }
    } catch (error) {
        console.error('Error toggling bookmark:', error);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const bookmarksTab = document.querySelector('[data-tab="bookmarks"]');
    if (bookmarksTab) {
        bookmarksTab.addEventListener('click', function() {
            const bookmarksContent = document.getElementById('bookmarks-content');
            if (bookmarksContent.classList.contains('hidden')) {
                loadBookmarks();
            }
        });
    }

    if ('{{ $activeTab }}' === 'bookmarks') {
        loadBookmarks();
    }
});
</script>
@endpush
@endif
