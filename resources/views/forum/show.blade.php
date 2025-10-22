@extends('layouts.app', ['title' => $thread->title])

@section('content')
    <article class="space-y-6 max-w-5xl mx-auto">
        <header class="card p-6 space-y-3">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">{{ $thread->title }}</h1>
                @if ($thread->flair)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-pink-600/10 text-pink-600 font-semibold">{{ strtoupper($thread->flair) }}</span>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                <span class="flex items-center"><i class="fa-solid fa-user mr-2"></i>{{ $thread->author->name }}</span>
                <span class="flex items-center"><i class="fa-solid fa-clock mr-2"></i>Created {{ $thread->created_at->format('M d, Y H:i') }}</span>
                <span class="flex items-center"><i class="fa-solid fa-comments mr-2"></i>{{ $thread->replies_count }} replies</span>
            </div>
            <div class="prose max-w-none text-gray-700">
                {!! $thread->body_html !!}
            </div>
        </header>

        <section class="card p-6 space-y-4">
            <h2 class="text-xl font-semibold text-gray-900">Replies</h2>
            <div class="space-y-5">
                @forelse ($posts as $post)
                    <div class="bg-gray-50 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-gray-900">{{ $post->author->name }}</p>
                            <span class="text-xs text-gray-500">{{ $post->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-sm text-gray-700 mt-2 prose max-w-none">
                            {!! $post->body_html !!}
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">There are no replies yet.</p>
                @endforelse
            </div>
            @auth
                <form method="POST" action="{{ route('forum.reply', $thread) }}" class="space-y-3">
                    @csrf
                    <textarea name="body" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-500" placeholder="Write a reply" required></textarea>
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-pink-600 text-white text-sm font-medium rounded-lg shadow hover:bg-pink-700 transition">Post reply</button>
                </form>
            @else
                <p class="text-sm text-gray-500">Please <a href="{{ route('login') }}" class="text-pink-600">log in</a> to participate.</p>
            @endauth
        </section>
    </article>
@endsection
