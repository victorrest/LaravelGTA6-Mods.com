@extends('admin.layout', ['title' => 'Verzió moderáció'])

@section('admin-header')
    <h1 class="text-2xl font-bold text-gray-900">Verzió moderáció</h1>
    <p class="text-gray-600 text-sm">Felhasználók által beküldött mod verziók jóváhagyása és kezelése</p>
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
                @foreach (['all' => 'Összes', 'pending' => 'Függőben', 'approved' => 'Jóváhagyva', 'rejected' => 'Elutasítva'] as $statusKey => $statusLabel)
                    <a
                        href="{{ route('admin.versions.index', ['status' => $statusKey]) }}"
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

        @if ($versions->isEmpty())
            <div class="p-12 text-center text-gray-500">
                <i class="fa-solid fa-code-branch text-4xl mb-4 text-gray-300"></i>
                <p class="text-lg font-medium">Nincsenek verziók ebben a kategóriában</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Mod</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Verzió</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Beküldő</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Státusz</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Dátum</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700">Műveletek</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($versions as $version)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <div class="max-w-xs">
                                        <a
                                            href="{{ route('mods.show', [$version->mod->primary_category, $version->mod]) }}"
                                            target="_blank"
                                            class="font-medium text-gray-900 hover:text-pink-600 line-clamp-2"
                                        >
                                            {{ $version->mod->title }}
                                        </a>
                                        <p class="text-xs text-gray-500 mt-1">ID: {{ $version->mod->id }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>
                                        <span class="font-bold text-gray-900">v{{ $version->version_number }}</span>
                                        @if($version->is_current)
                                            <span class="ml-2 px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded-full">Aktuális</span>
                                        @endif
                                        @if($version->file_size_label)
                                            <p class="text-xs text-gray-500 mt-1">{{ $version->file_size_label }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $version->submitter->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $version->submitter->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Függőben',
                                            'approved' => 'Jóváhagyva',
                                            'rejected' => 'Elutasítva',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusColors[$version->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusLabels[$version->status] ?? $version->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $version->created_at->diffForHumans() }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($version->status === 'pending')
                                            <form method="POST" action="{{ route('admin.versions.approve', $version) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 transition"
                                                    title="Jóváhagyás"
                                                >
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.versions.reject', $version) }}">
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

                                        @if ($version->status === 'approved' && !$version->is_current)
                                            <form method="POST" action="{{ route('admin.versions.set-current', $version) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition"
                                                    title="Aktuálissá tétel"
                                                >
                                                    <i class="fa-solid fa-star"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.versions.destroy', $version) }}" onsubmit="return confirm('Biztosan törölni szeretnéd ezt a verziót?')">
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

                            @if ($version->changelog)
                                <tr class="bg-blue-50">
                                    <td colspan="6" class="px-4 py-2">
                                        <div class="text-sm">
                                            <strong class="text-blue-800"><i class="fa-solid fa-scroll mr-1"></i>Changelog:</strong>
                                            <p class="mt-1 text-gray-700 whitespace-pre-line">{{ $version->changelog }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-200">
                {{ $versions->links() }}
            </div>
        @endif
    </div>
@endsection
