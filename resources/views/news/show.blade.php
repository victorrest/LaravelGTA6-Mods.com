@extends('layouts.app', ['title' => $article->title])

@section('content')
    <article class="max-w-3xl mx-auto space-y-6">
        <header class="space-y-3 text-center">
            <h1 class="text-4xl font-bold text-gray-900">{{ $article->title }}</h1>
            <p class="text-sm text-gray-500">
                <span class="mr-3"><i class="fa-solid fa-user mr-1"></i>{{ $article->author->name }}</span>
                <span><i class="fa-solid fa-clock mr-1"></i>{{ $article->published_at->format('M d, Y H:i') }}</span>
            </p>
        </header>
        <div class="card p-6 prose max-w-none text-gray-700">
            {!! nl2br(e($article->body)) !!}
        </div>
        <footer class="text-center text-sm text-gray-500">
            <a href="{{ route('news.index') }}" class="text-pink-600 hover:text-pink-700">Back to news</a>
        </footer>
    </article>
@endsection
