@extends('layouts.app', ['title' => $mod->title])

@section('content')
    <article class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="card overflow-hidden">
                <img src="{{ $mod->hero_image_url }}" alt="{{ $mod->title }}" class="w-full h-80 object-cover">
                <div class="p-6 space-y-4">
                    <h1 class="text-3xl font-bold text-gray-900">{{ $mod->title }}</h1>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        <span class="flex items-center"><i class="fa-solid fa-user mr-2"></i>{{ $mod->author->name }}</span>
                        <span class="flex items-center"><i class="fa-solid fa-calendar mr-2"></i>Published {{ $mod->published_at->format('M d, Y') }}</span>
                        <span class="flex items-center"><i class="fa-solid fa-download mr-2"></i>{{ number_format($mod->downloads) }} downloads</span>
                        <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-2"></i>{{ number_format($mod->likes) }} likes</span>
                    </div>
                    <div class="prose prose-invert max-w-none text-gray-200 bg-gray-900/90 rounded-2xl p-6">
                        {!! nl2br(e($mod->description)) !!}
                    </div>
                </div>
            </div>

            <section class="card p-6 space-y-4">
                <header class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Comments</h2>
                    <span class="text-sm text-gray-500">{{ $mod->comments_count }} total</span>
                </header>
                <div class="space-y-5">
                    @forelse ($comments as $comment)
                        <div class="bg-gray-50 rounded-xl p-4">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-gray-900">{{ $comment->author->name }}</p>
                                <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-gray-700 mt-2">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">There are no comments yet.</p>
                    @endforelse
                </div>
                @auth
                    <form method="POST" action="{{ route('mods.comment', $mod) }}" class="space-y-3">
                        @csrf
                        <textarea name="body" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-500" placeholder="Share your thoughts"></textarea>
                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-pink-600 text-white text-sm font-medium rounded-lg shadow hover:bg-pink-700 transition">Post comment</button>
                    </form>
                @else
                    <p class="text-sm text-gray-500">Please <a href="{{ route('login') }}" class="text-pink-600">log in</a> to comment.</p>
                @endauth
            </section>
        </div>

        <aside class="space-y-6">
            <div class="card p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900">Download</h2>
                <p class="text-sm text-gray-600 leading-relaxed">This mod is hosted on our global CDN for ultra-fast download speeds.</p>
                <form method="POST" action="{{ route('mods.download', $mod) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-pink-600 text-white font-semibold rounded-xl shadow hover:bg-pink-700 transition">
                        <i class="fa-solid fa-download mr-2"></i>Download now
                    </button>
                </form>
                <div class="text-sm text-gray-500 space-y-1">
                    <p><strong>Version:</strong> {{ $mod->version }}</p>
                    <p><strong>File size:</strong> {{ $mod->file_size_label }}</p>
                    <p><strong>Uploaded:</strong> {{ $mod->published_at->format('M d, Y') }}</p>
                </div>
            </div>

            <div class="card p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900">Related Mods</h2>
                <ul class="space-y-3 text-sm text-gray-600">
                    @foreach ($relatedMods as $related)
                        <li>
                            <a href="{{ route('mods.show', $related) }}" class="hover:text-pink-600">{{ $related->title }}</a>
                            <span class="block text-xs text-gray-400">{{ $related->category_names }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </article>
@endsection
