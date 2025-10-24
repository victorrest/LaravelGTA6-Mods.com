@extends('admin.layout', ['title' => 'Videó moderáció'])

@section('admin-header')
    <h1 class="text-2xl font-bold text-gray-900">Videó moderáció</h1>
    <p class="text-gray-600 text-sm">Felhasználók által beküldött YouTube videók jóváhagyása és kezelése</p>
@endsection

@section('admin-content')
    @if (session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <i class="fa-solid fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 p-4">
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['all' => 'Összes', 'pending' => 'Függőben', 'approved' => 'Jóváhagyva', 'rejected' => 'Elutasítva', 'reported' => 'Jelentve'] as $statusKey => $statusLabel)
                    <a
                        href="{{ route('admin.videos.index', ['status' => $statusKey]) }}"
                        @class([
                            'px-4 py-2 rounded-lg text-sm font-medium transition',
                            'bg-pink-600 text-white shadow' => $status === $statusKey,
                            'bg-white text-gray-700 hover:bg-gray-100' => $status !== $statusKey,
                        ])
                    >
                        {{ $statusLabel }}
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs {{ $status === $statusKey ? 'bg-white/20' : 'bg-gray-200' }}">
                            {{ $statusCounts[$statusKey] ?? 0 }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        @if ($videos->isEmpty())
            <div class="p-12 text-center text-gray-500">
                <i class="fa-solid fa-video text-4xl mb-4 text-gray-300"></i>
                <p class="text-lg font-medium">Nincsenek videók ebben a kategóriában</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Videó</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Mod</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Beküldő</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Státusz</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Dátum</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700">Műveletek</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($videos as $video)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            src="{{ $video->thumbnail_small_url }}"
                                            alt="{{ $video->video_title }}"
                                            class="w-20 h-12 object-cover rounded"
                                        >
                                        <div class="max-w-xs">
                                            <a
                                                href="https://www.youtube.com/watch?v={{ $video->youtube_id }}"
                                                target="_blank"
                                                class="font-medium text-gray-900 hover:text-pink-600 line-clamp-1"
                                            >
                                                {{ $video->video_title ?: 'Nincs cím' }}
                                            </a>
                                            <p class="text-xs text-gray-500">ID: {{ $video->youtube_id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <a
                                        href="{{ route('mods.show', [$video->mod->primary_category, $video->mod]) }}"
                                        target="_blank"
                                        class="text-gray-900 hover:text-pink-600"
                                    >
                                        {{ Str::limit($video->mod->title, 30) }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $video->submitter->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $video->submitter->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'reported' => 'bg-orange-100 text-orange-800',
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Függőben',
                                            'approved' => 'Jóváhagyva',
                                            'rejected' => 'Elutasítva',
                                            'reported' => 'Jelentve',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusColors[$video->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusLabels[$video->status] ?? $video->status }}
                                    </span>
                                    @if ($video->report_count > 0)
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fa-solid fa-flag mr-1"></i>{{ $video->report_count }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $video->created_at->diffForHumans() }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($video->status === 'pending' || $video->status === 'reported')
                                            <form method="POST" action="{{ route('admin.videos.approve', $video) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 transition"
                                                    title="Jóváhagyás"
                                                >
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($video->status !== 'rejected')
                                            <form method="POST" action="{{ route('admin.videos.reject', $video) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 bg-yellow-600 text-white text-xs font-medium rounded hover:bg-yellow-700 transition"
                                                    title="Elutasítás"
                                                >
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($video->report_count > 0)
                                            <form method="POST" action="{{ route('admin.videos.clear-reports', $video) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition"
                                                    title="Jelentések törlése"
                                                >
                                                    <i class="fa-solid fa-flag-checkered"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.videos.destroy', $video) }}" onsubmit="return confirm('Biztosan törölni szeretnéd ezt a videót?')">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700 transition"
                                                title="Törlés"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            @if ($video->reports->isNotEmpty())
                                <tr class="bg-red-50">
                                    <td colspan="6" class="px-4 py-2">
                                        <div class="text-sm">
                                            <strong class="text-red-800"><i class="fa-solid fa-exclamation-triangle mr-1"></i>Jelentések ({{ $video->reports->count() }}):</strong>
                                            <div class="mt-1 space-y-1">
                                                @foreach ($video->reports as $report)
                                                    <div class="text-gray-700">
                                                        <strong>{{ $report->user->name }}</strong>
                                                        <span class="text-gray-500">- {{ $report->reported_at->diffForHumans() }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-200">
                {{ $videos->links() }}
            </div>
        @endif
    </div>
@endsection
