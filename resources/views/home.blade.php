@extends('layouts.app', ['title' => 'Home', 'isHome' => true])

@section('content')
    <section id="featured-section" class="space-y-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900">Featured Mods</h2>
            <a href="{{ route('mods.index', ['filter' => 'featured']) }}" class="text-sm font-medium text-pink-600 hover:text-pink-700">
                View All <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="lg:grid lg:grid-cols-12 lg:gap-6">
            <div class="lg:col-span-8">
                <div class="relative card overflow-hidden">
                    @if ($featuredMods->isNotEmpty())
                        @php($primaryFeatured = $featuredMods->first())
                        <a href="{{ route('mods.show', $primaryFeatured) }}" class="block relative group rounded-lg overflow-hidden">
                            <div class="relative w-full aspect-video bg-gray-800">
                                <img src="{{ $primaryFeatured->hero_image_url }}" alt="{{ $primaryFeatured->title }}" class="absolute inset-0 w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                            </div>
                            <div class="featured-badge">
                                <i class="fas fa-star fa-xs mr-1.5" aria-hidden="true"></i>Featured
                            </div>
                            <div class="absolute bottom-0 left-0 p-4 md:p-6 text-white w-full">
                                <h3 class="text-lg sm:text-xl md:text-2xl font-bold leading-tight mb-1">{{ $primaryFeatured->title }}</h3>
                                <p class="text-sm text-gray-200">by <span class="font-semibold">{{ $primaryFeatured->author->name }}</span></p>
                            </div>
                        </a>
                    @else
                        <div class="p-8 text-center text-gray-400 flex items-center justify-center min-h-[300px]">
                            There are no featured mods yet.
                        </div>
                    @endif
                </div>
            </div>
            <div class="hidden lg:flex lg:col-span-4 items-center justify-center mt-6 lg:mt-0">
                <div class="w-full h-full min-h-[300px] bg-gray-200 border-2 border-dashed border-gray-400 rounded-lg flex items-center justify-center text-gray-500">
                    Advertisement (336x280)
                </div>
            </div>
        </div>
    </section>

    <section class="space-y-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900">Popular Mods</h2>
            <a href="{{ route('mods.index', ['sort' => 'popular']) }}" class="text-sm font-medium text-pink-600 hover:text-pink-700">
                View All <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            @forelse ($popularMods as $mod)
                @include('mods.partials.card', ['mod' => $mod])
            @empty
                <p class="text-sm text-gray-500 col-span-full">No popular mods yet.</p>
            @endforelse
        </div>
    </section>

    <section class="space-y-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900">Latest Mods <span class="text-sm font-normal text-pink-500">(24h)</span></h2>
            <a href="{{ route('mods.index', ['sort' => 'latest']) }}" class="text-sm font-medium text-pink-600 hover:text-pink-700">
                View All <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            @forelse ($latestMods as $mod)
                @include('mods.partials.card', ['mod' => $mod])
            @empty
                <p class="text-sm text-gray-500 col-span-full">No mods have been published yet.</p>
            @endforelse
        </div>
    </section>

    <section class="space-y-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900">Latest News</h2>
            <a href="{{ route('news.index') }}" class="text-sm font-medium text-pink-600 hover:text-pink-700">
                All News <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-9 space-y-4">
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
