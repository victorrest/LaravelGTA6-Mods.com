<div id="comments-tab" data-tab-content="comments" class="tab-content hidden">
    <div class="text-center py-12" data-loading>
        <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
        <p class="text-gray-500">Loading comments...</p>
    </div>

    <div id="comments-content" class="hidden">
        <div id="comments-list" class="space-y-4"></div>

        <div id="comments-empty" class="hidden text-center py-12 text-gray-500">
            <i class="fas fa-comments text-4xl mb-3 text-gray-300"></i>
            <p>No comments yet</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function loadComments() {
    const commentsContent = document.getElementById('comments-content');
    const commentsList = document.getElementById('comments-list');
    const commentsEmpty = document.getElementById('comments-empty');
    const loadingDiv = document.querySelector('#comments-tab [data-loading]');

    try {
        const response = await fetch(`/api/author/{{ $author->id }}/comments`);
        const data = await response.json();

        loadingDiv.classList.add('hidden');
        commentsContent.classList.remove('hidden');

        if (data.comments && data.comments.length > 0) {
            commentsList.innerHTML = data.comments.map(comment => `
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-1">
                            <div class="text-sm text-gray-600 mb-2">${comment.content}</div>
                            <div class="text-xs text-gray-500">
                                On <a href="${comment.mod_url}" class="text-pink-600 hover:underline">${comment.mod_title}</a>
                                · ${comment.time_ago}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            commentsEmpty.classList.add('hidden');
        } else {
            commentsList.innerHTML = '';
            commentsEmpty.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        loadingDiv.innerHTML = '<p class="text-red-500">Failed to load comments</p>';
    }
}

// Load when tab is opened
document.addEventListener('DOMContentLoaded', function() {
    const commentsTab = document.querySelector('[data-tab="comments"]');
    if (commentsTab) {
        commentsTab.addEventListener('click', function() {
            const commentsContent = document.getElementById('comments-content');
            if (commentsContent.classList.contains('hidden')) {
                loadComments();
            }
        });
    }

    if ('{{ $activeTab }}' === 'comments') {
        loadComments();
    }
});
</script>
@endpush
