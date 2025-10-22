@extends('layouts.app', ['title' => 'Community Forum'])

@section('content')
    <section class="space-y-6">
        <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Community Forum</h1>
                <p class="text-sm text-gray-500">Discuss modding tips, share showcases and get help from fellow creators.</p>
            </div>
            @auth
                <a href="{{ route('forum.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition">
                    <i class="fa-solid fa-pen-to-square mr-2"></i>New thread
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-pink-600 text-pink-600 text-sm font-semibold hover:bg-pink-50 transition">
                    Sign in to post
                </a>
            @endauth
        </header>

        <div class="card divide-y divide-gray-100">
            @forelse ($threads as $thread)
                <article class="p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="space-y-2">
                        <h2 class="text-lg font-semibold text-gray-900">
                            <a href="{{ route('forum.show', $thread) }}" class="hover:text-pink-600">{{ $thread->title }}</a>
                        </h2>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                            <span class="flex items-center"><i class="fa-solid fa-user mr-1"></i>{{ $thread->author->name }}</span>
                            <span class="flex items-center"><i class="fa-solid fa-comments mr-1"></i>{{ $thread->replies_count }} replies</span>
                            <span class="flex items-center"><i class="fa-solid fa-clock mr-1"></i>Updated {{ $thread->last_posted_at?->diffForHumans() }}</span>
                            @if ($thread->flair)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-pink-600/10 text-pink-600 font-medium">{{ strtoupper($thread->flair) }}</span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('forum.show', $thread) }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">View thread</a>
                </article>
            @empty
                <p class="p-5 text-sm text-gray-500">No threads have been created yet.</p>
            @endforelse
        </div>

        <div>
            {{ $threads->links() }}
        </div>
    </section>
@endsection
