@extends('admin.layout', ['title' => 'Fórum moderálása'])

@section('admin-header')
    <h1 class="text-3xl font-bold text-gray-900">Fórum témák</h1>
    <p class="text-sm text-gray-500">Beszélgetések kezelése, kiemelés és zárolás – a WordPress sablon logikájával megegyezően.</p>
@endsection

@section('admin-content')
    <form method="GET" class="card p-4 grid gap-4 md:grid-cols-[2fr_1fr]">
        <div>
            <label for="search" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Keresés</label>
            <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-input mt-1" placeholder="Téma vagy szerző">
        </div>
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition"><i class="fa-solid fa-magnifying-glass mr-2"></i>Keresés</button>
        </div>
    </form>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Téma</th>
                    <th class="px-4 py-3 text-left">Szerző</th>
                    <th class="px-4 py-3 text-left">Válaszok</th>
                    <th class="px-4 py-3 text-left">Állapot</th>
                    <th class="px-4 py-3 text-right">Műveletek</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($threads as $thread)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-4">
                            <a href="{{ route('admin.forum.edit', $thread) }}" class="font-semibold text-gray-900 hover:text-pink-600">{{ $thread->title }}</a>
                            <p class="text-xs text-gray-500">Utolsó aktivitás: {{ optional($thread->last_posted_at)->diffForHumans() ?? $thread->created_at->diffForHumans() }}</p>
                        </td>
                        <td class="px-4 py-4 text-gray-600">{{ $thread->author?->name ?? 'Ismeretlen' }}</td>
                        <td class="px-4 py-4 text-gray-600">{{ $thread->posts_count }}</td>
                        <td class="px-4 py-4">
                            <div class="flex flex-wrap gap-2">
                                @if ($thread->pinned)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold"><i class="fa-solid fa-thumbtack"></i>Kiemelt</span>
                                @endif
                                @if ($thread->locked)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs font-semibold"><i class="fa-solid fa-lock"></i>Zárolt</span>
                                @endif
                                @if (! $thread->pinned && ! $thread->locked)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-200 text-gray-700 text-xs font-semibold">Aktív</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.forum.edit', $thread) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-pink-600 text-xs font-semibold text-pink-600 hover:bg-pink-50">Szerkesztés</a>
                                <form method="POST" action="{{ route('admin.forum.destroy', $thread) }}" onsubmit="return confirm('Biztosan törlöd a témát?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-rose-500 text-xs font-semibold text-rose-600 hover:bg-rose-50">Törlés</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Nincs találat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $threads->links() }}
@endsection
