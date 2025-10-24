@extends('admin.layout')

@section('title', 'Videó beküldések')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Videó beküldések</h1>
                <p class="text-sm text-gray-500">Moderáld a felhasználók által beküldött videókat.</p>
            </div>
            <div class="flex gap-2">
                @foreach ([
                    \App\Models\ModVideo::STATUS_PENDING => 'Függőben',
                    \App\Models\ModVideo::STATUS_APPROVED => 'Jóváhagyva',
                    \App\Models\ModVideo::STATUS_REJECTED => 'Elutasítva',
                ] as $status => $label)
                    <a href="{{ route('admin.mod-videos.index', ['status' => $status]) }}" class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium {{ $activeStatus === $status ? 'border-pink-500 bg-pink-50 text-pink-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Videó</th>
                        <th class="px-4 py-3">Mod</th>
                        <th class="px-4 py-3">Beküldte</th>
                        <th class="px-4 py-3">Állapot</th>
                        <th class="px-4 py-3 text-right">Műveletek</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($videos as $video)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($video->thumbnailUrl())
                                        <img src="{{ $video->thumbnailUrl() }}" alt="{{ $video->title }} thumbnail" class="h-14 w-24 rounded-lg object-cover">
                                    @else
                                        <div class="h-14 w-24 rounded-lg bg-gray-200"></div>
                                    @endif
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $video->title }}</p>
                                        <p class="text-xs text-gray-500">{{ $video->channel_title }}</p>
                                        <a href="https://youtu.be/{{ $video->external_id }}" target="_blank" class="text-xs text-pink-600 hover:text-pink-700">Megnyitás YouTube-on</a>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('mods.show', [$video->mod->primary_category, $video->mod]) }}" class="text-sm font-semibold text-pink-600 hover:text-pink-700">{{ $video->mod->title }}</a>
                                <p class="text-xs text-gray-500">#{{ $video->mod->id }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-700">{{ $video->author->name }}</div>
                                <div class="text-xs text-gray-400">{{ $video->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $video->status === \App\Models\ModVideo::STATUS_APPROVED ? 'bg-green-100 text-green-600' : ($video->status === \App\Models\ModVideo::STATUS_REJECTED ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600') }}">
                                    {{ ucfirst($video->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.mod-videos.approve', $video) }}">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1 text-xs font-semibold text-white shadow hover:bg-green-700">Jóváhagyás</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.mod-videos.reject', $video) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="reason" placeholder="Elutasítás oka" class="rounded-lg border-gray-300 text-xs focus:border-pink-500 focus:ring-pink-500" />
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold text-white shadow hover:bg-red-700">Elutasítás</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-500">Nincs megjeleníthető videó.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $videos->links() }}
    </div>
@endsection
