@extends('admin.layout')

@section('title', 'Mod frissítések')

@section('content')
    <div class="space-y-6">
        <header>
            <h1 class="text-2xl font-bold text-gray-900">Beküldött mod frissítések</h1>
            <p class="text-sm text-gray-500">Ellenőrizd és hagyd jóvá a közösségi frissítéseket.</p>
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
                        <th class="px-4 py-3">Mod</th>
                        <th class="px-4 py-3">Verzió</th>
                        <th class="px-4 py-3">Beküldő</th>
                        <th class="px-4 py-3">Állapot</th>
                        <th class="px-4 py-3">Dátum</th>
                        <th class="px-4 py-3 text-right">Műveletek</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($revisions as $revision)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $revision->mod->title }}</div>
                                <div class="text-xs text-gray-500">#{{ $revision->mod->id }}</div>
                            </td>
                            <td class="px-4 py-3">v{{ $revision->version }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-700">{{ $revision->author->name }}</div>
                                <div class="text-xs text-gray-400">{{ $revision->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $revision->status === \App\Models\ModRevision::STATUS_APPROVED ? 'bg-green-100 text-green-600' : ($revision->status === \App\Models\ModRevision::STATUS_REJECTED ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600') }}">
                                    {{ ucfirst($revision->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $revision->created_at->format('Y.m.d H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.mod-revisions.show', $revision) }}" class="inline-flex items-center gap-2 rounded-lg bg-pink-600 px-3 py-1 text-xs font-semibold text-white shadow hover:bg-pink-700">Megtekintés</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">Nincs frissítés.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $revisions->links() }}
    </div>
@endsection
