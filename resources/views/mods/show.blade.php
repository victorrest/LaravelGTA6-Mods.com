@extends('layouts.app', ['title' => $mod->title])

@section('content')
    @php
        // Rating calculations
        $ratingDisplay = $ratingValue ? number_format($ratingValue, 1) : '—';
        $userLoggedIn = Auth::check();

        // Get current version data
        $currentVersionData = $currentVersion ?? null;
        $versionNumber = $currentVersionData ? $currentVersionData->version_number : ($mod->version ?? '1.0');
        $fileSize = $currentVersionData ? $currentVersionData->file_size_label : ($mod->file_size_label ?? '—');
        $downloadUrl = $currentVersionData ? ($currentVersionData->download_url ?: $currentVersionData->file_url) : ($mod->download_url ?? '#');
        $virusScanUrl = $currentVersionData->virus_scan_url ?? null;

        // Author data
        $authorUrl = route('author.profile', $mod->author->username ?? $mod->author->id);

        // Like and bookmark states
        $isLiked = false; // TODO: Implement user like checking
        $isBookmarked = false; // TODO: Implement user bookmark checking

        // Can edit/update mod
        $canEditMod = $canManageMod;
        $showUpdateButton = $canEditMod; // TODO: Add pending update check

        // Gallery preparation
        $galleryImages = $galleryImages ?? [];
    @endphp

    {{-- Success notices --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900 flex items-start gap-3">
            <i class="fa-solid fa-check-circle mt-1 text-green-500" aria-hidden="true"></i>
            <div>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(request()->get('update') === 'success')
        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sky-900 flex items-start gap-3">
            <i class="fa-solid fa-circle-info mt-1 text-sky-500" aria-hidden="true"></i>
            <div>
                <p class="font-semibold">Update submitted successfully</p>
                <p class="text-sm">Your update request was received. Once it passes moderation it will appear publicly on this mod page.</p>
            </div>
        </div>
    @endif

    {{-- Breadcrumbs --}}
    <nav class="text-sm text-gray-500 mb-2" aria-label="Breadcrumb">
        <ol class="breadcrumb-trail flex flex-wrap items-center gap-1" itemscope itemtype="https://schema.org/BreadcrumbList">
            @foreach ($breadcrumbs as $index => $crumb)
                @php($isLast = $loop->last)
                <li class="flex items-center gap-1" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    @if (!empty($crumb['url']) && !$isLast)
                        <a href="{{ $crumb['url'] }}" class="breadcrumb-link hover:text-pink-600" itemprop="item"
                           @if($crumb['is_home'] ?? false) rel="home" @endif
                           @if($isLast) aria-current="page" @endif>
                            <span itemprop="name">{{ $crumb['label'] }}</span>
                        </a>
                    @else
                        <span class="breadcrumb-current {{ $isLast ? 'text-gray-700 font-semibold' : '' }}" itemprop="name">{{ $crumb['label'] }}</span>
                    @endif
                    <meta itemprop="position" content="{{ $index + 1 }}">
                    @unless($isLast)
                        <span class="breadcrumb-separator text-gray-400" aria-hidden="true">&raquo;</span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- Title and Action Buttons --}}
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-3 lg:gap-4 mb-4">
        {{-- Left: Title and Meta --}}
        <div class="flex-grow min-w-0 max-w-[760px]">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight">
                @if ($mod->featured)
                    <i class="fa-solid fa-star text-yellow-400" aria-hidden="true"></i>
                @endif
                <span class="break-words">{{ $mod->title }}</span>
                @if ($versionNumber)
                    <span class="text-xl md:text-2xl font-semibold text-gray-400">{{ $versionNumber }}</span>
                @endif
            </h1>
            <div class="flex items-center flex-wrap gap-x-5 gap-y-2 text-gray-500 text-sm mt-2">
                <span class="flex items-center">
                    by <a href="{{ $authorUrl }}" class="font-semibold text-amber-600 hover:underline ml-1">{{ $mod->author->name }}</a>
                </span>
                <span class="flex items-center" aria-label="Total downloads">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-1.5 text-gray-500"><path d="M12 15V3"></path><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path></svg>
                    <span class="text-gray-500" data-download-count>{{ $downloadFormatted }}</span>
                </span>
                <span class="flex items-center" aria-label="Total likes">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-1.5 text-gray-500"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path></svg>
                    <span class="text-gray-500 mod-like-total">{{ $likesFormatted }}</span>
                </span>
            </div>
        </div>

        {{-- Right: Download and Action Buttons --}}
        <div class="flex flex-col items-center md:items-end">
            <div class="flex items-center space-x-2 w-full lg:w-auto" data-mod-id="{{ $mod->id }}">
                {{-- Download Button --}}
                <form method="POST" action="{{ route('mods.download', [$primaryCategory, $mod]) }}" class="w-full md:w-auto">
                    @csrf
                    <button type="submit" class="btn-download font-bold py-3 px-5 rounded-[12px] transition flex items-center justify-center w-full md:w-auto download-button" {{ $downloadUrl === '#' ? 'disabled' : '' }}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 max-[350px]:mr-0 mr-2"><path d="M12 15V3"></path><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path></svg>
                        <span class="download-text">Download</span>
                    </button>
                </form>

                {{-- Like Button --}}
                <button type="button" class="mod-hero-icon-button mod-like-button {{ $isLiked ? 'is-active' : 'is-inactive' }} w-11 h-11 hover:bg-gray-200 hover:text-pink-600 transition flex-shrink-0"
                        data-like-button="true" data-post-id="{{ $mod->id }}" aria-pressed="{{ $isLiked ? 'true' : 'false' }}"
                        @if(!$userLoggedIn) disabled title="Jelentkezz be a kedveléshez" @endif>
                    <span class="sr-only">Kedvelés</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"></path></svg>
                </button>

                {{-- Bookmark Button --}}
                <button type="button" class="mod-hero-icon-button mod-bookmark-button {{ $isBookmarked ? 'is-active' : 'is-inactive' }} w-11 h-11 hover:bg-gray-200 hover:text-pink-600 transition flex-shrink-0"
                        data-bookmark-button="true" data-post-id="{{ $mod->id }}" aria-pressed="{{ $isBookmarked ? 'true' : 'false' }}"
                        @if(!$userLoggedIn) disabled title="Jelentkezz be a mentéshez" @endif>
                    <span class="sr-only">{{ $isBookmarked ? 'Bookmarked' : 'Bookmark' }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                </button>

                {{-- More Options Dropdown --}}
                <div class="relative">
                    <button type="button" class="mod-hero-icon-button w-11 h-11 hover:bg-gray-200 hover:text-pink-600 transition flex-shrink-0" data-more-options-toggle aria-haspopup="true" aria-expanded="false">
                        <span class="sr-only">További műveletek</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                    </button>
                    <div class="more-options-dropdown hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl z-20 border border-gray-200" data-more-options-menu role="menu" aria-hidden="true">
                        @if($showUpdateButton)
                            <a href="{{ route('mods.version.create', $mod) }}" class="more-options-item flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem">
                                <i class="fa-solid fa-pen-to-square w-5 mr-2 text-gray-400"></i>
                                <span>Edit / Update</span>
                            </a>
                        @endif
                        @if($canManagePin)
                            <button type="button" class="w-full more-options-item flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem">
                                <i class="fa-solid fa-thumbtack w-5 mr-2 text-gray-400"></i>
                                <span>Pin</span>
                            </button>
                        @endif
                        <button type="button" class="w-full more-options-item flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem">
                            <i class="fa-solid fa-flag w-5 mr-2 text-gray-400"></i>
                            <span>Report</span>
                        </button>
                        @if($canEditMod)
                            <button type="button" class="w-full more-options-item flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700" role="menuitem">
                                <i class="fa-solid fa-trash w-5 mr-2"></i>
                                <span>Delete</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Virus Scan Link --}}
            @if($virusScanUrl)
                <a href="{{ $virusScanUrl }}" class="mt-3 lg:mt-2 inline-flex items-center text-xs font-medium text-green-700 hover:text-green-800" target="_blank" rel="noopener noreferrer" title="View the virus scan report">
                    <i class="fas fa-shield-halved mr-1.5" aria-hidden="true"></i>
                    <span class="text-gray-500 hover:text-gray-600">This file was virus-scanned and is safe to download.</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Main Grid Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Gallery (Images + Videos with PhotoSwipe) --}}
            <x-mod.gallery-photoswipe
                :images="$galleryImages"
                :videos="$videos"
                :modTitle="$mod->title"
                :modId="$mod->id"
                :canManageVideos="$canManageMod"
            />

            {{-- Stats Bar --}}
            <div class="card -ml-4 -mr-4 sm:ml-0 sm:mr-0 rounded-none sm:rounded-xl overflow-hidden">
                <div class="border-t border-b border-gray-200 py-3 px-4 md:px-6">
                    <div class="flex flex-row justify-between items-center text-gray-600 gap-4">
                        <div class="flex items-center flex-wrap gap-4 md:gap-6">
                            <div class="flex items-center space-x-2">
                                <i class="fa-solid fa-download text-3xl text-pink-500" aria-hidden="true"></i>
                                <div>
                                    <p class="font-bold text-base text-gray-800" data-download-count>{{ $downloadFormatted }}</p>
                                    <p class="text-xs uppercase text-gray-400">Letöltés</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <i class="fa-regular fa-thumbs-up text-3xl text-pink-500" aria-hidden="true"></i>
                                <div>
                                    <p class="font-bold text-base text-gray-800 mod-like-total">{{ $likesFormatted }}</p>
                                    <p class="text-xs uppercase text-gray-400">Kedvelés</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500 space-y-1 sm:text-right">
                            <p><span class="font-semibold text-gray-800">First Uploaded:</span> {{ $metaDetails['uploaded_at'] ?? '—' }}</p>
                            <p><span class="font-semibold text-gray-800">Last Updated:</span> {{ $metaDetails['updated_at'] ?? '—' }}</p>
                            @if($currentVersionData && $currentVersionData->created_at)
                                <p class="text-xs text-gray-400">Last Downloaded: {{ $currentVersionData->created_at->diffForHumans() }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabs: Description / Comments / Changelog --}}
            <div class="card">
                <div class="flex border-b border-gray-200">
                    <button data-tab="description" class="tab-btn px-6 py-3 font-semibold active text-pink-600 border-b-2 border-pink-600">Description</button>
                    <button data-tab="comments" class="tab-btn px-6 py-3 font-semibold text-gray-600 hover:text-pink-600 border-b-2 border-transparent">Comments ({{ $mod->comments_count }})</button>
                    <button data-tab="changelogs" class="tab-btn px-6 py-3 font-semibold text-gray-600 hover:text-pink-600 border-b-2 border-transparent">Changelogs ({{ $versions->count() + 1 }})</button>
                </div>

                {{-- Description Tab --}}
                <div id="tab-description" class="tab-content p-4 md:p-6 text-gray-700 leading-relaxed">
                    <h4 class="font-bold text-lg mb-2 text-gray-900">Description</h4>
                    <div class="editorjs-content">
                        {!! $mod->description_html !!}
                    </div>

                    {{-- Tags and Upload Info --}}
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="flex items-center space-x-2 mb-4">
                            @forelse ($mod->categories as $category)
                                <a href="{{ route('mods.index', ['category' => $category->slug]) }}" class="bg-gray-200 text-gray-700 text-xs font-semibold px-3 py-1 rounded-full hover:bg-gray-300 transition">{{ $category->name }}</a>
                            @empty
                                <span class="text-xs text-gray-400">No categories.</span>
                            @endforelse
                        </div>
                        <div class="text-sm text-gray-500 space-y-1">
                            <p><strong class="font-medium text-gray-700">First Uploaded:</strong> {{ $metaDetails['uploaded_at'] ?? '—' }}</p>
                            <p><strong class="font-medium text-gray-700">Last Updated:</strong> {{ $metaDetails['updated_at'] ?? '—' }}</p>
                            @if($currentVersionData && $currentVersionData->created_at)
                                <p><strong class="font-medium text-gray-700">Last Downloaded:</strong> {{ $currentVersionData->created_at->diffForHumans() }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Comments Tab --}}
                <div id="tab-comments" class="tab-content hidden p-4 md:p-6">
                    <x-mod.comments :mod="$mod" :comments="$comments" />
                </div>

                {{-- Changelogs Tab --}}
                <div id="tab-changelogs" class="tab-content hidden p-4 md:p-6">
                    <h4 class="font-bold text-lg mb-4 text-gray-900">Version History</h4>
                    <div class="space-y-3">
                        {{-- Initial Version (from mod table) --}}
                        <div class="p-4 bg-gray-50 rounded-lg border {{ $versions->isEmpty() ? 'border-pink-500 bg-pink-50' : 'border-gray-200' }}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-bold text-gray-900">
                                        Version {{ $mod->version ?? '1.0' }}
                                        @if($versions->isEmpty())
                                            <span class="ml-2 px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">Current</span>
                                        @endif
                                        <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">Initial Release</span>
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $mod->published_at ? $mod->published_at->format('F j, Y') : $mod->created_at->format('F j, Y') }}
                                        @if($mod->file_size_label)
                                            · {{ $mod->file_size_label }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 text-sm text-gray-700">
                                <strong>Changes:</strong>
                                <p class="mt-1">Initial release</p>
                            </div>
                        </div>

                        {{-- Additional Versions (from mod_versions table) --}}
                        @foreach($versions as $version)
                            <div class="p-4 bg-gray-50 rounded-lg border {{ $version->is_current ? 'border-pink-500 bg-pink-50' : 'border-gray-200' }}">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-bold text-gray-900">
                                            Version {{ $version->version_number }}
                                            @if($version->is_current)
                                                <span class="ml-2 px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">Current</span>
                                            @endif
                                        </h3>
                                        <p class="text-xs text-gray-500 mt-1">
                                            {{ $version->created_at->format('F j, Y') }}
                                            @if($version->file_size_label)
                                                · {{ $version->file_size_label }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                @if($version->changelog)
                                    <div class="mt-3 text-sm text-gray-700">
                                        <strong>Changes:</strong>
                                        <p class="mt-1 whitespace-pre-line">{{ $version->changelog }}</p>
                                    </div>
                                @else
                                    <div class="mt-3 text-sm text-gray-500 italic">
                                        No changelog provided
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Sidebar --}}
        <aside class="space-y-6">
            {{-- Download Card (continued...) --}}
            @include('mods.partials.sidebar-download', [
                'mod' => $mod,
                'currentVersion' => $currentVersionData,
                'versionNumber' => $versionNumber,
                'fileSize' => $fileSize,
                'downloadUrl' => $downloadUrl,
                'virusScanUrl' => $virusScanUrl,
                'metaDetails' => $metaDetails,
                'canManagePin' => $canManagePin,
                'isPinnedByOwner' => $isPinnedByOwner,
            ])

            {{-- Version History Sidebar --}}
            @if($versions->isNotEmpty())
                <div class="card">
                    <h3 class="text-lg font-bold text-gray-900 p-4 border-b">Version History</h3>
                    <div class="p-4 space-y-4">
                        @foreach($versions->take(3) as $version)
                            <div class="pb-3 border-b last:border-b-0">
                                <div class="flex justify-between items-center">
                                    <p class="font-semibold text-gray-800 flex items-center">
                                        Version {{ $version->version_number }}
                                        @if($version->is_current)
                                            <span class="ml-2 text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Current</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $version->created_at->format('Y.m.d.') }}</p>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                                        <span class="flex items-center" title="Download count">
                                            <i class="fa-solid fa-download w-4 text-center mr-1.5"></i> {{ $version->download_count ?? 0 }}
                                        </span>
                                        @if($version->file_size_label)
                                            <span class="flex items-center" title="File size">
                                                <i class="fa-solid fa-file-zipper w-4 text-center mr-1.5"></i> {{ $version->file_size_label }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        @if($version->virus_scan_url)
                                            <a href="{{ $version->virus_scan_url }}" target="_blank" rel="noopener noreferrer" class="text-green-600 hover:text-green-800 transition" title="VirusTotal Scan">
                                                <i class="fa-solid fa-shield-halved fa-lg"></i>
                                            </a>
                                        @endif
                                        @if($version->download_url || $version->file_url)
                                            <a href="{{ $version->download_url ?: $version->file_url }}" class="btn-download text-xs font-bold py-1.5 px-3 rounded-md hover:bg-pink-700 transition">
                                                Download
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Author Info Card --}}
            <x-mod.author-card :author="$mod->author" />

            {{-- More by Author --}}
            @if($relatedMods->isNotEmpty())
                <div class="card">
                    <h3 class="text-lg font-bold text-gray-900 p-4">More by {{ $mod->author->name }}</h3>
                    <div class="p-2 space-y-3">
                        @foreach($relatedMods->take(2) as $related)
                            <x-mod.related-card :mod="$related" />
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Related Mods --}}
            @if($relatedMods->count() > 2)
                <div class="card">
                    <h3 class="text-lg font-bold text-gray-900 p-4">Related Mods</h3>
                    <div class="p-2 space-y-3">
                        @foreach($relatedMods->slice(2)->take(2) as $related)
                            <x-mod.related-card :mod="$related" />
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </div>
@endsection

{{-- Share Modal --}}
<x-mod.share-modal :mod="$mod" :url="request()->url()" />

@push('scripts')
<script>
// Tab switching
document.querySelectorAll('[data-tab]').forEach(button => {
    button.addEventListener('click', function() {
        const targetTab = this.dataset.tab;

        // Update buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active', 'text-pink-600', 'border-pink-600');
            btn.classList.add('text-gray-600', 'border-transparent');
        });
        this.classList.add('active', 'text-pink-600', 'border-pink-600');
        this.classList.remove('text-gray-600', 'border-transparent');

        // Update content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.getElementById('tab-' + targetTab).classList.remove('hidden');
    });
});

// More options dropdown
document.querySelectorAll('[data-more-options-toggle]').forEach(button => {
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        const menu = this.parentElement.querySelector('[data-more-options-menu]');
        menu.classList.toggle('hidden');
    });
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('[data-more-options-toggle]')) {
        document.querySelectorAll('[data-more-options-menu]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }
});
</script>
@endpush
