@extends('layouts.app', ['title' => $mod->title])

@section('content')
    @php
        $currentVersionData = $currentVersion;
        $versionNumber = $currentVersionData?->version_number ?? ($mod->version ?? '1.0');
        $fileSizeLabel = $currentVersionData?->file_size_label ?? ($mod->file_size_label ?? '—');
        $downloadActionUrl = route('mods.download', [$primaryCategory, $mod]);
        $authorUrl = route('author.profile', $mod->author->username ?? $mod->author->id);
        $ratingDisplay = $ratingValue ? number_format($ratingValue, 1) : '—';
        $likeActiveClasses = 'is-active bg-pink-600 text-white';
        $likeInactiveClasses = 'is-inactive bg-gray-200 text-gray-700 hover:bg-gray-300';
        $bookmarkActiveClasses = 'is-active';
        $bookmarkInactiveClasses = 'is-inactive';
        $metaUploaded = $metaDetails['uploaded_at'] ?? '—';
        $metaUpdated = $metaDetails['updated_at'] ?? '—';
        $metaUploadedAgo = $metaDetails['uploaded_ago'] ?? null;
    @endphp

    <main class="container mx-auto space-y-6 p-4 lg:p-6">
        <div data-mod-id="{{ $mod->id }}" hidden></div>

        @if (request()->get('update') === 'success')
            <div class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-900">
                <i class="fa-solid fa-circle-info mt-1 text-sky-500" aria-hidden="true"></i>
                <div>
                    <p class="font-semibold">Update submitted successfully</p>
                    <p class="text-sm text-sky-800">Your update request was received. Once moderators approve it, the new version will appear on this page.</p>
                </div>
            </div>
        @endif

        @if ($showPendingNotice)
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                <i class="fa-solid fa-clock mt-1 text-amber-500" aria-hidden="true"></i>
                <div>
                    <p class="font-semibold">An update is currently awaiting moderation</p>
                    <p class="text-sm">A new version has been submitted for this mod and is waiting for moderator review. Please wait until it is approved or rejected before submitting another update.</p>
                </div>
            </div>
        @endif

        @if ($showAuthorPendingNotice)
            <div class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-900">
                <i class="fa-solid fa-circle-info mt-1 text-sky-500" aria-hidden="true"></i>
                <div>
                    <p class="font-semibold">Your mod is waiting for approval</p>
                    <p class="text-sm">Thanks for sharing your work! Our moderators are double-checking the files and will publish your mod for everyone as soon as it passes review.</p>
                </div>
            </div>
        @endif

        <nav class="text-sm text-gray-500" aria-label="Breadcrumb">
            <ol class="breadcrumb-trail flex flex-wrap items-center gap-1" itemscope itemtype="https://schema.org/BreadcrumbList">
                @foreach ($breadcrumbs as $index => $crumb)
                    @php($isLast = $loop->last)
                    <li class="flex items-center gap-1" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                        @if (!empty($crumb['url']) && !$isLast)
                            <a href="{{ $crumb['url'] }}" class="breadcrumb-link hover:text-pink-600" itemprop="item" @if($crumb['is_home'] ?? false) rel="home" @endif>
                                <span itemprop="name">{{ $crumb['label'] }}</span>
                            </a>
                        @else
                            <span class="breadcrumb-current font-semibold text-gray-700" itemprop="name">{{ $crumb['label'] }}</span>
                        @endif
                        <meta itemprop="position" content="{{ $index + 1 }}">
                        @unless($isLast)
                            <span class="breadcrumb-separator text-gray-300" aria-hidden="true">&raquo;</span>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </nav>

        <section class="card space-y-6 p-6 md:p-8">
            <header class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex-1 space-y-3">
                    <h1 class="flex flex-wrap items-center gap-2 text-3xl font-bold text-gray-900 md:text-4xl">
                        @if ($mod->featured)
                            <i class="fa-solid fa-star text-yellow-400" aria-hidden="true"></i>
                        @endif
                        <span class="break-words leading-tight">{{ $mod->title }}</span>
                        <span class="text-2xl font-semibold text-gray-400">v{{ $versionNumber }}</span>
                    </h1>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-500">
                        <span class="flex items-center">
                            by <a href="{{ $authorUrl }}" class="ml-1 font-semibold text-amber-600 hover:underline">{{ $mod->author->name }}</a>
                        </span>
                        <span class="flex items-center" aria-label="Total downloads">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5 text-gray-500"><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></svg>
                            <span data-download-count>{{ $downloadFormatted }}</span>
                        </span>
                        <span class="flex items-center" aria-label="Total likes">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5 text-gray-500"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/></svg>
                            <span class="mod-like-total" data-post-id="{{ $mod->id }}">{{ $likesFormatted }}</span>
                        </span>
                        <span class="flex items-center gap-2 text-sm text-gray-600">
                            <span class="flex items-center gap-1 text-yellow-500" title="Average rating">
                                @for ($star = 1; $star <= 5; $star++)
                                    @if ($star <= $ratingFullStars)
                                        <i class="fa-solid fa-star"></i>
                                    @elseif ($ratingHasHalf && $star === $ratingFullStars + 1)
                                        <i class="fa-solid fa-star-half-stroke"></i>
                                    @else
                                        <i class="fa-regular fa-star text-gray-300"></i>
                                    @endif
                                @endfor
                            </span>
                            <span class="text-xs font-semibold text-gray-500">{{ $ratingDisplay }}/5 ({{ number_format($ratingCount) }})</span>
                        </span>
                    </div>
                </div>

                <div class="flex w-full flex-col items-stretch gap-3 lg:w-72">
                    <form method="POST" action="{{ $downloadActionUrl }}" class="w-full">
                        @csrf
                        <button type="submit" class="download-button btn-download flex w-full items-center justify-center gap-2 rounded-2xl py-3 text-lg font-bold">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></svg>
                            <span>Download</span>
                            <span class="text-xs font-normal text-white/80">{{ $fileSizeLabel }}</span>
                        </button>
                    </form>

                    <div class="grid grid-cols-3 gap-2 text-sm font-semibold">
                        <button
                            type="button"
                            class="mod-like-button flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm transition {{ $isLiked ? $likeActiveClasses : $likeInactiveClasses }}"
                            data-like-button="true"
                            data-like-active-class="{{ $likeActiveClasses }}"
                            data-like-inactive-class="{{ $likeInactiveClasses }}"
                            aria-pressed="{{ $isLiked ? 'true' : 'false' }}"
                            @if(!Auth::check()) disabled title="Sign in to like this mod" @endif
                        >
                            <i class="fa-solid fa-thumbs-up"></i>
                            <span>Like</span>
                        </button>
                        <button
                            type="button"
                            class="mod-bookmark-button flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm transition {{ $isBookmarked ? 'bg-pink-100 text-pink-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                            data-bookmark-button="true"
                            data-bookmark-active-class="bg-pink-100 text-pink-600"
                            data-bookmark-inactive-class="bg-gray-200 text-gray-700 hover:bg-gray-300"
                            aria-pressed="{{ $isBookmarked ? 'true' : 'false' }}"
                            @if(!Auth::check()) disabled title="Sign in to bookmark this mod" @endif
                        >
                            <i class="fa-solid fa-bookmark"></i>
                            <span data-bookmark-label>{{ $isBookmarked ? 'Bookmarked' : 'Bookmark' }}</span>
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-xl bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-200"
                            data-share-modal-target
                        >
                            <i class="fa-solid fa-share-nodes"></i>
                            <span>Share</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <button type="button" class="mod-secondary-action flex flex-col items-center justify-center gap-1 rounded-lg bg-gray-100 py-2 text-gray-600 hover:bg-gray-200" data-video-submit-modal="true" data-mod-id="{{ $mod->id }}" data-requires-login="{{ Auth::check() ? 'false' : 'true' }}">
                            <i class="fa-solid fa-video"></i>
                            <span>Add video</span>
                        </button>
                        <a href="#mod-comments" class="mod-secondary-action flex flex-col items-center justify-center gap-1 rounded-lg bg-gray-100 py-2 text-gray-600 hover:bg-gray-200" data-scroll-to-comments>
                            <i class="fa-solid fa-comments"></i>
                            <span>Comments</span>
                        </a>
                        @if ($showUpdateButton)
                            <a href="{{ route('mods.version.create', $mod) }}" class="mod-secondary-action flex flex-col items-center justify-center gap-1 rounded-lg bg-pink-100 py-2 text-pink-600 hover:bg-pink-200">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>Submit update</span>
                            </a>
                        @else
                            <span class="mod-secondary-action flex flex-col items-center justify-center gap-1 rounded-lg bg-gray-100 py-2 text-gray-400">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>Update locked</span>
                            </span>
                        @endif
                    </div>
                </div>
            </header>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-mod.gallery-photoswipe
                    :items="$galleryItems"
                    :gallery-json="$galleryJson"
                    :default-image="$defaultGalleryImage"
                    :mod="$mod"
                    :can-manage-videos="$canManageMod"
                />

                <div class="card overflow-hidden">
                    <div class="grid gap-6 bg-white px-6 py-5 md:grid-cols-3">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-download text-3xl text-pink-500" aria-hidden="true"></i>
                            <div>
                                <p class="text-xl font-bold text-gray-900" data-download-count>{{ $downloadFormatted }}</p>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Downloads</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-heart text-3xl text-pink-500" aria-hidden="true"></i>
                            <div>
                                <p class="text-xl font-bold text-gray-900 mod-like-total">{{ $likesFormatted }}</p>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Likes</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-gauge-high text-3xl text-pink-500" aria-hidden="true"></i>
                            <div>
                                <p class="text-xl font-bold text-gray-900">{{ $ratingDisplay }}/5</p>
                                <p class="text-xs uppercase tracking-wide text-gray-400">Community rating</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="flex flex-wrap border-b border-gray-200 text-sm font-semibold">
                        <button data-tab="description" class="tab-btn active border-b-2 border-pink-600 px-5 py-3 text-pink-600">Description</button>
                        <button data-tab="comments" class="tab-btn border-b-2 border-transparent px-5 py-3 text-gray-600 hover:text-pink-600">Comments ({{ number_format($mod->comments_count) }})</button>
                        @if($versions->count() > 1)
                            <button data-tab="changelog" class="tab-btn border-b-2 border-transparent px-5 py-3 text-gray-600 hover:text-pink-600">Changelog</button>
                        @endif
                    </div>

                    <div id="tab-description" class="tab-content p-6 space-y-6">
                        <article class="prose max-w-none text-gray-700">
                            {!! $mod->description_html !!}
                        </article>
                        <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600">
                            <div class="flex flex-wrap items-center gap-2">
                                @forelse ($mod->categories as $category)
                                    <a href="{{ route('mods.index', ['category' => $category->slug]) }}" class="rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-300">{{ $category->name }}</a>
                                @empty
                                    <span>No categories assigned.</span>
                                @endforelse
                            </div>
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div class="flex items-center justify-between">
                                    <dt class="text-gray-500">First uploaded</dt>
                                    <dd class="font-semibold text-gray-800">{{ $metaUploaded }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-gray-500">Last updated</dt>
                                    <dd class="font-semibold text-gray-800">{{ $metaUpdated }}</dd>
                                </div>
                                @if ($metaUploadedAgo)
                                    <div class="flex items-center justify-between">
                                        <dt class="text-gray-500">Published</dt>
                                        <dd class="font-semibold text-gray-800">{{ $metaUploadedAgo }}</dd>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between">
                                    <dt class="text-gray-500">File size</dt>
                                    <dd class="font-semibold text-gray-800">{{ $fileSizeLabel }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div id="tab-comments" class="tab-content hidden p-6">
                        <x-mod.comments :mod="$mod" :comments="$comments" />
                    </div>

                    @if($versions->count() > 1)
                        <div id="tab-changelog" class="tab-content hidden space-y-4 p-6">
                            @foreach($versions as $version)
                                <article class="rounded-lg border {{ $version->is_current ? 'border-pink-500 bg-pink-50' : 'border-gray-200 bg-white' }} p-4">
                                    <header class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-lg font-semibold text-gray-900">Version {{ $version->version_number }}</h3>
                                        <div class="flex items-center gap-3 text-xs text-gray-500">
                                            <span>{{ $version->created_at->format('F j, Y') }}</span>
                                            @if ($version->file_size_label)
                                                <span>{{ $version->file_size_label }}</span>
                                            @endif
                                            @if ($version->is_current)
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Current</span>
                                            @endif
                                        </div>
                                    </header>
                                    @if ($version->changelog)
                                        <div class="mt-3 text-sm text-gray-700">
                                            <p class="font-semibold text-gray-900">Changes:</p>
                                            <p class="mt-2 whitespace-pre-line">{{ $version->changelog }}</p>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <aside class="space-y-6">
                <div class="card space-y-4 p-6">
                    <h2 class="text-lg font-bold text-gray-900">Mod details</h2>
                    <dl class="grid gap-3 text-sm text-gray-600">
                        <div class="flex items-center justify-between">
                            <dt>Latest version</dt>
                            <dd class="font-semibold text-gray-900">{{ $versionNumber }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt>File size</dt>
                            <dd class="font-semibold text-gray-900">{{ $fileSizeLabel }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt>Uploaded</dt>
                            <dd class="font-semibold text-gray-900">{{ $metaUploaded }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt>Updated</dt>
                            <dd class="font-semibold text-gray-900">{{ $metaUpdated }}</dd>
                        </div>
                    </dl>
                    @if ($currentVersionData && $currentVersionData->virus_scan_url)
                        <a href="{{ $currentVersionData->virus_scan_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 hover:text-emerald-700">
                            <i class="fa-solid fa-shield-halved"></i>
                            View VirusTotal report
                        </a>
                    @endif
                </div>

                @if ($versions->isNotEmpty())
                    <div class="card p-6">
                        <h2 class="text-lg font-bold text-gray-900">Version history</h2>
                        <div class="mt-4 space-y-4">
                            @foreach ($versions->take(3) as $version)
                                <div class="rounded-lg border border-gray-200 p-3">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-semibold text-gray-800">v{{ $version->version_number }}</span>
                                        <span class="text-xs text-gray-500">{{ $version->created_at->format('Y.m.d.') }}</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                        <span class="flex items-center gap-1"><i class="fa-solid fa-download"></i> {{ $version->download_count ?? 0 }}</span>
                                        @if ($version->file_size_label)
                                            <span class="flex items-center gap-1"><i class="fa-solid fa-file-zipper"></i> {{ $version->file_size_label }}</span>
                                        @endif
                                        @if ($version->virus_scan_url)
                                            <a href="{{ $version->virus_scan_url }}" target="_blank" rel="noopener" class="flex items-center gap-1 text-emerald-600 hover:text-emerald-700">
                                                <i class="fa-solid fa-shield-halved"></i> Scan
                                            </a>
                                        @endif
                                        @if ($version->download_url || $version->file_url)
                                            <a href="{{ $version->download_url ?: $version->file_url }}" class="flex items-center gap-1 text-pink-600 hover:text-pink-700">
                                                <i class="fa-solid fa-arrow-down"></i> Download
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <x-mod.author-card :author="$mod->author" />

                @if($relatedMods->isNotEmpty())
                    <div class="card p-6">
                        <h2 class="text-lg font-bold text-gray-900">More from {{ $mod->author->name }}</h2>
                        <div class="mt-4 space-y-3">
                            @foreach($relatedMods->take(2) as $related)
                                <x-mod.related-card :mod="$related" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($relatedMods->count() > 2)
                    <div class="card p-6">
                        <h2 class="text-lg font-bold text-gray-900">Related mods</h2>
                        <div class="mt-4 space-y-3">
                            @foreach($relatedMods->slice(2)->take(2) as $related)
                                <x-mod.related-card :mod="$related" />
                            @endforeach
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </main>
@endsection

<x-mod.share-modal :mod="$mod" :url="request()->url()" />

@push('scripts')
<script>
    document.querySelectorAll('[data-tab]').forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.dataset.tab;

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active', 'text-pink-600', 'border-pink-600');
                btn.classList.add('text-gray-600', 'border-transparent');
            });
            this.classList.add('active', 'text-pink-600', 'border-pink-600');
            this.classList.remove('text-gray-600', 'border-transparent');

            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            document.getElementById('tab-' + targetTab).classList.remove('hidden');
        });
    });
</script>
@endpush
