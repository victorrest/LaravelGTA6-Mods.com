<div id="followers-tab" data-tab-content="followers" class="tab-content hidden">
    <div class="text-center py-12" data-loading>
        <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
        <p class="text-gray-500">Loading followers...</p>
    </div>

    <div id="followers-content" class="hidden">
        <div id="followers-list" class="space-y-3"></div>

        <div id="followers-empty" class="hidden text-center py-12 text-gray-500">
            <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
            <p>No followers yet</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function loadFollowers() {
    const followersContent = document.getElementById('followers-content');
    const followersList = document.getElementById('followers-list');
    const followersEmpty = document.getElementById('followers-empty');
    const loadingDiv = document.querySelector('#followers-tab [data-loading]');

    try {
        const response = await fetch(`/api/author/{{ $author->id }}/followers`);
        const data = await response.json();

        loadingDiv.classList.add('hidden');
        followersContent.classList.remove('hidden');

        if (data.followers && data.followers.length > 0) {
            followersList.innerHTML = data.followers.map(follower => `
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <a href="/author/${follower.name}">
                        <img src="${follower.avatar}" alt="${follower.name}" class="w-12 h-12 rounded-full object-cover">
                    </a>
                    <div class="flex-1">
                        <a href="/author/${follower.name}" class="font-semibold text-gray-800 hover:text-pink-600">
                            ${follower.name}
                        </a>
                        ${follower.profile_title ? `<p class="text-sm text-gray-500">${follower.profile_title}</p>` : ''}
                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
                            ${follower.is_online ? '<span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-green-500"></span> Online</span>' : ''}
                            <span>${follower.followers_count} followers</span>
                        </div>
                    </div>
                </div>
            `).join('');

            followersEmpty.classList.add('hidden');
        } else {
            followersList.innerHTML = '';
            followersEmpty.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading followers:', error);
        loadingDiv.innerHTML = '<p class="text-red-500">Failed to load followers</p>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const followersTab = document.querySelector('[data-tab="followers"]');
    if (followersTab) {
        followersTab.addEventListener('click', function() {
            const followersContent = document.getElementById('followers-content');
            if (followersContent.classList.contains('hidden')) {
                loadFollowers();
            }
        });
    }

    if ('{{ $activeTab }}' === 'followers') {
        loadFollowers();
    }
});
</script>
@endpush
