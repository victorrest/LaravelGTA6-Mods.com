@extends('layouts.app', ['title' => 'My uploads'])

@section('content')
    <section class="space-y-6">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">My uploads</h1>
                <p class="text-sm text-gray-500">Manage and monitor the performance of your published mods.</p>
            </div>
            <a href="{{ route('mods.upload') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition">
                <i class="fa-solid fa-upload mr-2"></i>Upload new
            </a>
        </header>

        <div class="card divide-y divide-gray-100">
            @forelse ($mods as $mod)
                <article class="p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <img src="{{ $mod->hero_image_url }}" alt="{{ $mod->title }}" class="w-20 h-20 object-cover rounded-xl">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $mod->title }}</h2>
                            <p class="text-xs text-gray-500">Published {{ $mod->published_at->diffForHumans() }}</p>
                            <p class="text-xs text-gray-500">{{ $mod->downloads }} downloads • {{ $mod->likes }} likes</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('mods.show', $mod) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50">View</a>
                        <a href="{{ route('mods.edit', $mod) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-pink-600 text-xs font-semibold text-pink-600 hover:bg-pink-50">Edit</a>
                    </div>
                </article>
            @empty
                <p class="p-5 text-sm text-gray-500">You have not uploaded any mods yet.</p>
            @endforelse
        </div>
    </section>
@endsection
