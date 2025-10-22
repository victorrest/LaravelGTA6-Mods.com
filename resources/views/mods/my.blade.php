@extends('layouts.app', ['title' => 'My uploads'])

@php($pendingStatus = App\Models\Mod::STATUS_PENDING)
@php($publishedStatus = App\Models\Mod::STATUS_PUBLISHED)

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
                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold"
                                    @class([
                                        'bg-amber-100 text-amber-700' => $mod->status === $pendingStatus,
                                        'bg-emerald-100 text-emerald-700' => $mod->status === $publishedStatus,
                                        'bg-gray-200 text-gray-700' => ! in_array($mod->status, [$pendingStatus, $publishedStatus]),
                                    ])>
                                    <i class="fa-solid fa-circle" aria-hidden="true"></i> {{ $mod->statusLabel() }}
                                </span>
                                @if ($mod->published_at)
                                    <span>Published {{ $mod->published_at->diffForHumans() }}</span>
                                @else
                                    <span>Uploaded {{ $mod->created_at->diffForHumans() }}</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-1">{{ $mod->downloads }} downloads • {{ $mod->likes }} likes</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($mod->status === $publishedStatus)
                            <a href="{{ route('mods.show', $mod) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50">View</a>
                        @endif
                        <a href="{{ route('mods.edit', $mod) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-pink-600 text-xs font-semibold text-pink-600 hover:bg-pink-50">Edit</a>
                    </div>
                </article>
            @empty
                <p class="p-5 text-sm text-gray-500">You have not uploaded any mods yet.</p>
            @endforelse
        </div>
    </section>
@endsection
