@extends('layouts.app', ['title' => 'Home', 'isHome' => true])

@section('content')
    @php
        $hasFeatured = $featuredMods->isNotEmpty();
        $primaryFeatured = $hasFeatured ? $featuredMods->first() : null;
        $hasPopular = $popularMods->isNotEmpty();
        $hasLatest = $latestMods->isNotEmpty();
        $hasNews = $latestNews->isNotEmpty();
        $newsPlaceholder = 'https://placehold.co/400x225/111827/f9fafb?text=GTA6+News';
    @endphp

    <section id="featured-section" class="space-y-6" aria-labelledby="featured-mods-heading">
        <div class="flex justify-between items-center mb-2">
            <h2 id="featured-mods-heading" class="text-xl md:text-2xl font-bold text-gray-900">Featured Mods</h2>
            <a href="{{ route('mods.index', ['filter' => 'featured']) }}" class="text-sm font-medium text-pink-600 hover:text-pink-700">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div class="lg:grid lg:grid-cols-12 lg:gap-6">
            <div class="lg:col-span-8">
                <div class="relative">
                    <div
                        id="featured-slider-container"
                        class="card relative overflow-hidden"
                        data-hydrated="{{ $hasFeatured ? 'true' : 'false' }}"
                        data-by-label="by"
                        data-featured-label="Featured"
                        data-loading-text="Kiemelt modok betöltése…"
                        data-empty-label="Jelenleg nincs kiemelt mod."
                        data-prev-label="Előző kiemelt mod"
                        data-next-label="Következő kiemelt mod"
                    >
                        @if ($hasFeatured && $primaryFeatured)
                            @php($primaryImage = $primaryFeatured->hero_image_url)
                            <a href="{{ route('mods.show', [$primaryFeatured->primary_category, $primaryFeatured]) }}" id="featured-main-display" class="block relative group rounded-lg overflow-hidden">
                                <div id="featured-image-container" class="relative w-full aspect-video bg-gray-800">
                                    <img id="featured-image-1" src="{{ $primaryImage }}" alt="{{ $primaryFeatured->title }}" class="absolute inset-0 w-full h-full object-cover" style="opacity: 1;">
                                    <img id="featured-image-2" src="" alt="" class="absolute inset-0 w-full h-full object-cover" style="opacity: 0;">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                                </div>
                                <div class="featured-badge">
                                    <i class="fas fa-star fa-xs mr-1.5" aria-hidden="true"></i>Featured
                                </div>
                                @if ($featuredMods->isNotEmpty())
                                    <div id="featured-nav-container">
                                        <div class="flex items-center gap-2">
                                            @foreach ($featuredMods as $index => $mod)
                                                <div class="featured-nav-segment{{ $index === 0 ? ' active' : '' }}" data-index="{{ $index }}">
                                                    <div class="progress-bar-inner" style="width: {{ $index === 0 ? '100' : '0' }}%;"></div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div id="featured-text-content" class="absolute bottom-0 left-0 p-4 md:p-6 text-white w-full">
                                    <h3 id="featured-title" class="text-lg sm:text-xl md:text-2xl font-bold leading-tight mb-1">{{ $primaryFeatured->title }}</h3>
                                    <p id="featured-author" class="text-sm text-gray-200">by <span class="font-semibold">{{ $primaryFeatured->author->name }}</span></p>
                                </div>
                                <button type="button" id="featured-prev" class="absolute left-3 top-1/2 -translate-y-1/2 bg-black/40 text-white text-[12px] sm:text-base rounded-full w-6 h-6 sm:w-10 sm:h-10 flex items-center justify-center opacity-100 group-hover:opacity-100 transform-gpu transition-all duration-300 hover:bg-black/60 hover:scale-110 focus:outline-none z-30" aria-label="Előző kiemelt mod">
                                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                                </button>
                                <button type="button" id="featured-next" class="absolute right-3 top-1/2 -translate-y-1/2 bg-black/40 text-white text-[12px] sm:text-base rounded-full w-6 h-6 sm:w-10 sm:h-10 flex items-center justify-center opacity-100 group-hover:opacity-100 transform-gpu transition-all duration-300 hover:bg-black/60 hover:scale-110 focus:outline-none z-30" aria-label="Következő kiemelt mod">
                                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                </button>
                            </a>
                        @else
                            <div class="p-8 text-center text-gray-400 flex items-center justify-center min-h-[300px]">Jelenleg nincs kiemelt mod.</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="hidden lg:flex lg:col-span-4 items-center justify-center mt-6 lg:mt-0">
                <div class="w-full h-full min-h-[300px] bg-gray-200 border-2 border-dashed border-gray-400 rounded-lg flex items-center justify-center text-gray-500">
                    Hirdetés (336x280)
                </div>
            </div>
        </div>
    </section>

    <section class="space-y-6" aria-labelledby="popular-mods-heading">
        <div class="flex justify-between items-center mb-2">
            <h2 id="popular-mods-heading" class="text-xl md:text-2xl font-bold text-gray-900">Popular Mods</h2>
            <a href="{{ route('mods.index', ['sort' => 'popular']) }}" class="text-sm font-medium text-pink-600 hover:text-pink-700">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div
            id="popular-mods-grid"
            class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6"
            data-hydrated="{{ $hasPopular ? 'true' : 'false' }}"
        >
            @if ($hasPopular)
                @foreach ($popularMods as $mod)
                    <div class="card hover:shadow-xl transition duration-300">
                        <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="block">
                            <div class="relative">
                                <img src="{{ $mod->hero_image_url }}" alt="{{ $mod->title }}" class="w-full h-auto object-cover rounded-t-xl">
                                <div class="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                                    <div class="flex justify-between items-center">
                                        @php($hasRating = ($mod->ratings_count ?? 0) > 0)
                                        <span class="flex items-center font-semibold text-yellow-400" title="{{ $hasRating ? $mod->ratings_count . ' értékelés' : 'Még nincs értékelés' }}">
                                            <i class="fa-solid fa-star mr-1"></i>{{ $hasRating ? number_format((float) $mod->rating, 1) : '—' }}
                                        </span>
                                        <div class="flex items-center space-x-3">
                                            <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1"></i>{{ number_format($mod->likes) }}</span>
                                            <span class="flex items-center"><i class="fa-solid fa-download mr-1"></i>{{ number_format($mod->downloads) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3">
                                <h3 class="font-semibold text-gray-900 text-sm truncate" title="{{ $mod->title }}">{{ $mod->title }}</h3>
                                <div class="flex justify-between items-center text-xs text-gray-500 mt-1">
                                    <span class="flex items-center"><i class="fa-solid fa-user mr-1"></i> {{ $mod->author->name }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            @else
                <div class="col-span-full text-center text-gray-400 py-10">Nincsenek még népszerű modok.</div>
            @endif
        </div>
    </section>

    <section class="space-y-6" aria-labelledby="latest-mods-heading">
        <div class="flex justify-between items-center mb-2">
            <h2 id="latest-mods-heading" class="text-xl md:text-2xl font-bold text-gray-900">Latest Mods <span class="text-sm font-normal text-pink-500">(24h)</span></h2>
            <a href="{{ route('mods.index', ['sort' => 'latest']) }}" class="text-sm font-medium text-pink-600 hover:text-pink-700">View All <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div
            id="latest-mods-grid"
            class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6"
            data-hydrated="{{ $hasLatest ? 'true' : 'false' }}"
        >
            @if ($hasLatest)
                @foreach ($latestMods as $mod)
                    <div class="card hover:shadow-xl transition duration-300">
                        <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="block">
                            <div class="relative">
                                <img src="{{ $mod->hero_image_url }}" alt="{{ $mod->title }}" class="w-full h-auto object-cover rounded-t-xl">
                                <div class="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                                    <div class="flex justify-between items-center">
                                        @php($hasRating = ($mod->ratings_count ?? 0) > 0)
                                        <span class="flex items-center font-semibold text-yellow-400" title="{{ $hasRating ? $mod->ratings_count . ' értékelés' : 'Még nincs értékelés' }}">
                                            <i class="fa-solid fa-star mr-1"></i>{{ $hasRating ? number_format((float) $mod->rating, 1) : '—' }}
                                        </span>
                                        <div class="flex items-center space-x-3">
                                            <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1"></i>{{ number_format($mod->likes) }}</span>
                                            <span class="flex items-center"><i class="fa-solid fa-download mr-1"></i>{{ number_format($mod->downloads) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3">
                                <h3 class="font-semibold text-gray-900 text-sm truncate" title="{{ $mod->title }}">{{ $mod->title }}</h3>
                                <div class="flex justify-between items-center text-xs text-gray-500 mt-1">
                                    <span class="flex items-center"><i class="fa-solid fa-user mr-1"></i> {{ $mod->author->name }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            @else
                <div class="col-span-full text-center text-gray-400 py-10">Még nincs friss mod feltöltés.</div>
            @endif
        </div>
    </section>

    <section class="space-y-6" aria-labelledby="latest-news-heading">
        <div class="flex justify-between items-center mb-2">
            <h2 id="latest-news-heading" class="text-xl md:text-2xl font-bold text-gray-900">Latest News</h2>
            <a href="{{ route('news.index') }}" class="text-sm font-medium text-pink-600 hover:text-pink-700">All News <i class="fa-solid fa-arrow-right ml-1"></i></a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div id="latest-news-list" class="lg:col-span-9 space-y-4" data-hydrated="{{ $hasNews ? 'true' : 'false' }}">
                @if ($hasNews)
                    @foreach ($latestNews as $news)
                        <div class="card p-4 md:p-5 hover:shadow-xl transition duration-300">
                            <a href="{{ route('news.show', $news) }}" class="flex flex-col md:flex-row gap-4 md:gap-5 items-start">
                                <div class="w-full h-32 md:w-48 md:h-28 flex-shrink-0 rounded-lg overflow-hidden">
                                    <img src="{{ $newsPlaceholder }}" alt="{{ $news->title }}" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-grow md:border-l md:border-gray-200 md:pl-5">
                                    <div class="flex flex-wrap items-center space-x-3 mb-2 text-xs">
                                        <span class="bg-pink-100 text-pink-800 font-semibold px-2 py-0.5 rounded-full shadow-sm">News</span>
                                        <span class="text-gray-500 mt-1 md:mt-0"><i class="fa-solid fa-calendar-days mr-1"></i>{{ optional($news->published_at)->format('M d, Y') }}</span>
                                    </div>
                                    <h3 class="font-bold text-lg text-gray-900 hover:text-pink-600 transition">{{ $news->title }}</h3>
                                    <p class="text-gray-600 mt-1 text-sm">{{ $news->excerpt }}</p>
                                    <span class="mt-3 inline-flex items-center text-xs font-semibold text-pink-600 hover:underline">Read More <i class="fa-solid fa-chevron-right ml-1 text-sm"></i></span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                @else
                    <div class="card p-6 text-center text-gray-500">Nincs elérhető hír.</div>
                @endif
            </div>
            <aside class="lg:col-span-3">
                <div class="card p-4 text-center border-2 border-dashed border-gray-300 bg-gray-100 sticky top-6">
                    <span class="text-xs font-semibold text-gray-400">LEBEGŐ HIRDETÉS (300x250)</span>
                    <div class="mt-3 w-full h-64 bg-white border border-gray-300 flex items-center justify-center rounded-lg shadow-inner">
                        <p class="text-gray-500 text-sm font-bold">STICKY AD BLOCK</p>
                    </div>
                    <span class="text-xl font-extrabold text-pink-600 logo-font mt-4">TÁMOGATÁS</span>
                    <p class="text-sm text-gray-500 mt-1">Támogasd az oldalt!</p>
                </div>
            </aside>
        </div>
    </section>

    <noscript>
        <div class="card p-4 text-center">
            <p class="text-sm text-gray-500">A tartalom teljes megjelenítéséhez engedélyezd a JavaScriptet.</p>
        </div>
    </noscript>
@endsection

@push('scripts')
    <script>
        window.GTAModsData = Object.assign({}, window.GTAModsData || {}, {!! json_encode($homeFeedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!});
    </script>
@endpush
