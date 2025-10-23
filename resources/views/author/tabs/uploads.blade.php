<div id="uploads-tab" data-tab-content="uploads" class="tab-content hidden">
    <div class="text-center py-12" data-loading>
        <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
        <p class="text-gray-500">Loading uploads...</p>
    </div>

    <div id="uploads-content" class="hidden">
        <div id="uploads-list" class="grid grid-cols-1 sm:grid-cols-2 gap-6"></div>

        <div id="uploads-pagination" class="mt-6 flex justify-center"></div>

        <div id="uploads-empty" class="hidden text-center py-12 text-gray-500">
            <i class="fas fa-upload text-4xl mb-3 text-gray-300"></i>
            <p>No uploads yet</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function loadUploads() {
    const uploadsContent = document.getElementById('uploads-content');
    const uploadsList = document.getElementById('uploads-list');
    const uploadsEmpty = document.getElementById('uploads-empty');
    const loadingDiv = document.querySelector('[data-loading]');

    try {
        const response = await fetch(`/api/author/{{ $author->id }}/uploads`);
        const data = await response.json();

        loadingDiv.classList.add('hidden');
        uploadsContent.classList.remove('hidden');

        if (data.uploads && data.uploads.length > 0) {
            uploadsList.innerHTML = data.uploads.map(mod => `
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
                        <div class="flex items-center justify-between text-sm text-gray-500">
                            <span><i class="fas fa-download mr-1"></i> ${mod.downloads.toLocaleString()}</span>
                            <span><i class="fas fa-star mr-1 text-yellow-500"></i> ${mod.rating || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `).join('');

            uploadsEmpty.classList.add('hidden');
        } else {
            uploadsList.innerHTML = '';
            uploadsEmpty.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading uploads:', error);
        loadingDiv.innerHTML = '<p class="text-red-500">Failed to load uploads</p>';
    }
}

// Load when tab is opened
document.addEventListener('DOMContentLoaded', function() {
    const uploadsTab = document.querySelector('[data-tab="uploads"]');
    if (uploadsTab) {
        uploadsTab.addEventListener('click', function() {
            const uploadsContent = document.getElementById('uploads-content');
            if (uploadsContent.classList.contains('hidden')) {
                loadUploads();
            }
        });
    }

    // Load if this is the active tab
    if ('{{ $activeTab }}' === 'uploads') {
        loadUploads();
    }
});
</script>
@endpush
