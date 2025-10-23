@extends('layouts.app')

@section('title', $author->name . ' - Profile')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sofia+Sans+Condensed:wght@800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    .gta6mods-author-profile {
        font-family: 'Inter', sans-serif;
    }

    .brand-font {
        font-family: 'Sofia Sans Condensed', sans-serif;
    }

    /* Override default header background for author profile */
    .gta6mods-author-profile .header-background {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        background-size: cover !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        @if($author->getBannerUrl())
            background-image: url('{{ $author->getBannerUrl() }}') !important;
        @endif
    }

    @media (min-width: 1280px) {
        .gta6mods-author-profile .header-background {
            @if($author->getBannerUrl())
                background-position: center -200px !important;
            @endif
        }
    }

    .verification-badge {
        color: #ec4899;
    }

    .profile-tab-btn {
        border-bottom: 3px solid transparent;
        transition: color 0.2s, border-color 0.2s;
    }

    .profile-tab-btn.active {
        color: #ec4899;
        border-bottom-color: #ec4899;
    }

    .settings-tab-btn {
        background-color: transparent;
        color: #4b5563;
        border-radius: 0.5rem;
        transition: all 0.2s;
    }

    .settings-tab-btn.active {
        background-color: #fce7f3;
        color: #be185d;
    }

    .btn-action {
        background-color: #ec4899;
        color: #ffffff;
        box-shadow: 0 4px 14px 0 rgba(236, 72, 153, 0.3);
        transition: all 0.2s;
    }

    .btn-action:hover {
        background-color: #db2777;
    }

    .form-input, .form-textarea, .form-select {
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        transition: all 0.2s;
    }

    .form-input:focus, .form-textarea:focus, .form-select:focus {
        border-color: #ec4899;
        box-shadow: 0 0 0 2px #fbcfe8;
        outline: none;
    }

    .gta6mods-banner-preview {
        height: 8rem;
        border: 1px dashed #d1d5db;
        border-radius: 0.75rem;
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .gta6mods-banner-preview.has-banner {
        border-style: solid;
        color: transparent;
    }

    .gta6mods-banner-remove {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(17, 24, 39, 0.75);
        border-radius: 9999px;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        transition: background 0.2s ease, transform 0.2s ease;
        cursor: pointer;
        border: none;
    }

    .gta6mods-banner-remove:hover {
        background: rgba(17, 24, 39, 0.9);
        transform: scale(1.1);
    }

    .preset-avatar {
        cursor: pointer;
        position: relative;
    }

    .preset-avatar.selected {
        border-color: #ec4899;
        box-shadow: 0 0 0 2px #fbcfe8;
    }

    .status-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .status-counter {
        font-size: 0.875rem;
        color: #6b7280;
    }

    [data-author-loading] {
        text-align: center;
        padding: 3rem 0;
        color: #9ca3af;
    }

    .activity-item {
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem 0;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .mod-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .mod-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
</style>
@endpush

@section('content')
<div class="gta6mods-author-profile">
    <!-- Header with Banner -->
    <div class="header-background h-48 relative">
        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-gray-100"></div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 mt-6 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left/Main Content Area -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Profile Tabs Navigation -->
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="border-b border-gray-200 px-6">
                        <nav class="flex -mb-px space-x-6 overflow-x-auto" role="tablist">
                            @foreach($tabs as $tabKey => $tab)
                                <button type="button"
                                        class="profile-tab-btn py-4 px-2 font-semibold text-sm whitespace-nowrap {{ $activeTab === $tabKey ? 'active' : 'text-gray-600' }}"
                                        data-tab="{{ $tabKey }}"
                                        onclick="switchTab('{{ $tabKey }}')">
                                    <i class="{{ $tab['icon'] }} mr-2"></i>
                                    <span>{{ $tab['label'] }}</span>
                                </button>
                            @endforeach
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        @include('author.tabs.overview')
                        @include('author.tabs.uploads')
                        @include('author.tabs.comments')
                        @include('author.tabs.followers')

                        @if($isOwner)
                            @include('author.tabs.notifications')
                            @include('author.tabs.bookmarks')
                            @include('author.tabs.settings')
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <aside class="lg:col-span-4">
                <div class="sticky top-6 space-y-6">
                    <!-- Author Summary Card -->
                    <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <!-- Avatar -->
                        <img src="{{ $author->getAvatarUrl(256) }}"
                             alt="{{ $author->name }}"
                             class="w-32 h-32 rounded-full mx-auto border-4 border-white shadow-lg object-cover"
                             id="author-avatar">

                        <!-- Name with Verification Badge -->
                        <h2 class="brand-font text-3xl font-bold mt-4 inline-flex items-center gap-2 tracking-wide">
                            {{ $author->name }}
                            <i class="fas fa-check-circle verification-badge text-xl"></i>
                        </h2>

                        @if($author->profile_title)
                            <p class="text-sm text-pink-600 font-semibold">{{ $author->profile_title }}</p>
                        @endif

                        <!-- Member Since -->
                        <p class="text-sm text-gray-500 mt-2">
                            {{ $author->created_at->format('F Y') }} member
                        </p>

                        <!-- Online Status -->
                        <p class="text-sm text-gray-500 mt-1 flex items-center justify-center gap-2">
                            @if($author->isOnline())
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500"></span>
                                <span>Now online</span>
                            @else
                                {{ $author->getLastActiveText() }}
                            @endif
                        </p>

                        <!-- Profile Views -->
                        <p class="text-sm text-gray-500 mt-1">
                            <span id="profile-views-count">{{ number_format($author->profile_views) }}</span> profile views
                        </p>

                        <!-- Bio -->
                        @if($author->bio)
                            <div class="mt-4 text-sm text-gray-600 prose prose-sm mx-auto">
                                {{ $author->bio }}
                            </div>
                        @endif

                        <!-- Social Links -->
                        @if($socialLinks->count() > 0)
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                @foreach($socialLinks as $link)
                                    @php
                                        $definitions = App\Models\UserSocialLink::getPlatformDefinitions();
                                        $definition = $definitions[$link->platform] ?? null;
                                    @endphp
                                    @if($definition)
                                        <a href="{{ $link->url }}"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-pink-100 hover:text-pink-600"
                                           target="_blank"
                                           rel="nofollow noopener"
                                           title="{{ $definition['label'] }}">
                                            <i class="{{ $definition['icon'] }}"></i>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="mt-6 flex items-center gap-2">
                            @if($isOwner)
                                <a href="{{ route('author.profile', $author->name) }}?tab=settings"
                                   class="flex-1 btn btn-action text-center py-2 px-4 rounded-lg font-semibold">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                            @else
                                <button id="follow-btn"
                                        class="flex-1 btn-action py-2 px-4 rounded-lg font-semibold"
                                        data-user-id="{{ $author->id }}"
                                        data-following="{{ auth()->check() && auth()->user()->isFollowing($author) ? 'true' : 'false' }}">
                                    <i class="fas fa-user-plus mr-2"></i>
                                    <span class="follow-label">{{ auth()->check() && auth()->user()->isFollowing($author) ? 'Following' : 'Follow' }}</span>
                                </button>
                            @endif
                            <button id="share-btn"
                                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded-lg font-semibold transition">
                                <i class="fas fa-share-alt"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Statistics Card -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="font-bold text-gray-800 mb-4">Statistics</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="text-center">
                                <p class="font-bold text-2xl text-pink-600">{{ number_format($stats['uploads']) }}</p>
                                <p class="text-gray-500">Uploads</p>
                            </div>
                            <div class="text-center">
                                <p class="font-bold text-2xl text-pink-600">{{ number_format($stats['downloads']) }}</p>
                                <p class="text-gray-500">Downloads</p>
                            </div>
                            <div class="text-center">
                                <p class="font-bold text-2xl text-pink-600">{{ number_format($stats['comments']) }}</p>
                                <p class="text-gray-500">Comments</p>
                            </div>
                            <div class="text-center">
                                <p class="font-bold text-2xl text-pink-600" id="followers-count">{{ number_format($stats['followers']) }}</p>
                                <p class="text-gray-500">Followers</p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const activeTab = '{{ $activeTab }}';
    const isOwner = {{ $isOwner ? 'true' : 'false' }};
    const authorId = {{ $author->id }};
    const csrfToken = '{{ csrf_token() }}';

    // Show active tab
    switchTab(activeTab);

    // Initialize profile functionality
    initProfileFunctionality();
});

function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('[data-tab-content]').forEach(el => {
        el.classList.add('hidden');
    });

    // Remove active class from all buttons
    document.querySelectorAll('.profile-tab-btn').forEach(btn => {
        btn.classList.remove('active', 'text-pink-600');
        btn.classList.add('text-gray-600');
    });

    // Show active tab content
    const tabContent = document.querySelector(`[data-tab-content="${tabName}"]`);
    if (tabContent) {
        tabContent.classList.remove('hidden');
    }

    // Add active class to button
    const tabBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (tabBtn) {
        tabBtn.classList.add('active', 'text-pink-600');
        tabBtn.classList.remove('text-gray-600');
    }

    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function initProfileFunctionality() {
    // Follow button
    const followBtn = document.getElementById('follow-btn');
    if (followBtn) {
        followBtn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            const following = this.dataset.following === 'true';

            try {
                const response = await fetch(`/api/follow/${userId}/toggle`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    this.dataset.following = data.following ? 'true' : 'false';
                    const label = this.querySelector('.follow-label');
                    label.textContent = data.following ? 'Following' : 'Follow';

                    // Update followers count
                    const followersCount = document.getElementById('followers-count');
                    if (followersCount) {
                        followersCount.textContent = data.followers_count.toLocaleString();
                    }
                }
            } catch (error) {
                console.error('Error toggling follow:', error);
            }
        });
    }

    // Share button
    const shareBtn = document.getElementById('share-btn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            if (navigator.share) {
                navigator.share({
                    title: document.title,
                    url: window.location.href
                });
            } else {
                navigator.clipboard.writeText(window.location.href);
                alert('Profile URL copied to clipboard!');
            }
        });
    }
}
</script>
@endpush
