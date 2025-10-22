@extends('layouts.app', ['title' => 'Home', 'isHome' => true])

@section('content')
    <section class="space-y-6" aria-labelledby="featured-mods-heading">
        <div class="flex items-center justify-between">
            <h2 id="featured-mods-heading" class="text-xl md:text-2xl font-bold text-gray-900">Featured Mods</h2>
            <a href="{{ route('mods.index', ['filter' => 'featured']) }}" class="text-sm font-semibold text-pink-600 hover:text-pink-700">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div id="featured-slider-container" class="relative card overflow-hidden" data-by-label="by" data-featured-label="Featured" data-loading-text="Loading featured mods…" data-empty-label="No featured mods available." data-prev-label="Previous featured mod" data-next-label="Next featured mod">
            <div class="p-8 text-center text-gray-400 flex items-center justify-center min-h-[300px]">Loading featured mods…</div>
        </div>
        <noscript>
            @if ($featuredMods->isNotEmpty())
                @php($primaryFeatured = $featuredMods->first())
                <div class="relative card overflow-hidden mt-4">
                    <a href="{{ route('mods.show', $primaryFeatured) }}" class="block relative group rounded-lg overflow-hidden">
                        <div class="relative w-full aspect-video bg-gray-800">
                            <img src="{{ $primaryFeatured->hero_image_url }}" alt="{{ $primaryFeatured->title }}" class="absolute inset-0 w-full h-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                        </div>
                        <div class="featured-badge"><i class="fas fa-star fa-xs mr-1.5" aria-hidden="true"></i>Featured</div>
                        <div class="absolute bottom-0 left-0 p-4 md:p-6 text-white w-full">
                            <h3 class="text-lg sm:text-xl md:text-2xl font-bold leading-tight mb-1">{{ $primaryFeatured->title }}</h3>
                            <p class="text-sm text-gray-200">by <span class="font-semibold">{{ $primaryFeatured->author->name }}</span></p>
                        </div>
                    </a>
                </div>
            @else
                <div class="p-8 text-center text-gray-400">There are no featured mods yet.</div>
            @endif
        </noscript>
    </section>

    <section class="space-y-6" aria-labelledby="popular-mods-heading">
        <div class="flex items-center justify-between">
            <h2 id="popular-mods-heading" class="text-xl md:text-2xl font-bold text-gray-900">Popular Mods</h2>
            <a href="{{ route('mods.index', ['sort' => 'popular']) }}" class="text-sm font-semibold text-pink-600 hover:text-pink-700">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div id="popular-mods-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            <div class="col-span-full text-center text-gray-400 py-10" role="status">Loading popular mods…</div>
        </div>
        <noscript>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6 mt-4">
                @forelse ($popularMods as $mod)
                    @include('mods.partials.card', ['mod' => $mod])
                @empty
                    <p class="text-sm text-gray-500 col-span-full">No popular mods yet.</p>
                @endforelse
            </div>
        </noscript>
    </section>

    <section class="space-y-6" aria-labelledby="latest-mods-heading">
        <div class="flex items-center justify-between">
            <h2 id="latest-mods-heading" class="text-xl md:text-2xl font-bold text-gray-900">Latest Mods <span class="text-sm font-normal text-pink-500">(24h)</span></h2>
            <a href="{{ route('mods.index', ['sort' => 'latest']) }}" class="text-sm font-semibold text-pink-600 hover:text-pink-700">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div id="latest-mods-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            <div class="col-span-full text-center text-gray-400 py-10" role="status">Loading latest mods…</div>
        </div>
        <noscript>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6 mt-4">
                @forelse ($latestMods as $mod)
                    @include('mods.partials.card', ['mod' => $mod])
                @empty
                    <p class="text-sm text-gray-500 col-span-full">No mods have been published yet.</p>
                @endforelse
            </div>
        </noscript>
    </section>

    <section class="space-y-6" aria-labelledby="latest-news-heading">
        <div class="flex items-center justify-between">
            <h2 id="latest-news-heading" class="text-xl md:text-2xl font-bold text-gray-900">Latest News</h2>
            <a href="{{ route('news.index') }}" class="text-sm font-semibold text-pink-600 hover:text-pink-700">All News <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-9 space-y-4">
                <div id="latest-news-list" class="space-y-4">
                    <div class="card p-6 text-center text-gray-400" role="status">Loading news…</div>
                </div>
                <noscript>
                    @forelse ($latestNews as $news)
                        <article class="card p-4 md:p-5 hover:shadow-xl transition duration-300">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                <a href="{{ route('news.show', $news) }}" class="hover:text-pink-600">{{ $news->title }}</a>
                            </h3>
                            <p class="text-sm text-gray-500 mb-3">
                                <span class="mr-3"><i class="fa-solid fa-user mr-1"></i>{{ $news->author->name }}</span>
                                <span><i class="fa-solid fa-clock mr-1"></i>{{ $news->published_at->diffForHumans() }}</span>
                            </p>
                            <p class="text-sm text-gray-600 leading-relaxed">{{ $news->excerpt }}</p>
                        </article>
                    @empty
                        <p class="text-sm text-gray-500">No news has been published yet.</p>
                    @endforelse
                </noscript>
            </div>
            <aside class="lg:col-span-3">
                <div class="card p-4 space-y-3">
                    <h3 class="text-lg font-semibold text-gray-900">Top Discussions</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        @foreach ($topThreads as $thread)
                            <li>
                                <a href="{{ route('forum.show', $thread) }}" class="hover:text-pink-600">{{ $thread->title }}</a>
                                <span class="block text-xs text-gray-400">{{ $thread->replies_count }} replies · Last active {{ $thread->last_posted_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        window.GTAModsData = Object.assign({}, window.GTAModsData || {}, {!! json_encode($homeFeedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!});
    </script>
@endpush
