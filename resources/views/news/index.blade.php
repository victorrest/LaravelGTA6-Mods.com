@extends('layouts.app', ['title' => 'News'])

@section('content')
    <section class="space-y-6 max-w-4xl mx-auto">
        <header class="text-center space-y-2">
            <h1 class="text-3xl font-bold text-gray-900">Latest news</h1>
            <p class="text-sm text-gray-500">Stay informed about GTA 6 modding scene updates and platform announcements.</p>
        </header>

        <div class="space-y-4">
            @forelse ($articles as $article)
                <article class="card p-6 space-y-3">
                    <h2 class="text-xl font-semibold text-gray-900">
                        <a href="{{ route('news.show', $article) }}" class="hover:text-pink-600">{{ $article->title }}</a>
                    </h2>
                    <p class="text-sm text-gray-500">
                        <span class="mr-3"><i class="fa-solid fa-user mr-1"></i>{{ $article->author->name }}</span>
                        <span><i class="fa-solid fa-clock mr-1"></i>{{ $article->published_at->format('M d, Y') }}</span>
                    </p>
                    <p class="text-sm text-gray-600">{{ $article->excerpt }}</p>
                </article>
            @empty
                <p class="text-sm text-gray-500">No news articles have been published yet.</p>
            @endforelse
        </div>

        <div>
            {{ $articles->links() }}
        </div>
    </section>
@endsection
