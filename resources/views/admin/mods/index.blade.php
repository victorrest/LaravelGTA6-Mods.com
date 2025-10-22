@extends('admin.layout', ['title' => 'Modok moderálása'])

@php($statuses = App\Models\Mod::STATUS_LABELS)

@section('admin-header')
    <h1 class="text-3xl font-bold text-gray-900">Modok kezelése</h1>
    <p class="text-sm text-gray-500">Szűrés státusz szerint, jóváhagyás és kiemelés a WordPress sablon logikája szerint.</p>
@endsection

@section('admin-content')
    <form method="GET" class="card p-4 grid gap-4 md:grid-cols-3">
        <div>
            <label for="search" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Keresés</label>
            <input id="search" type="text" name="search" value="{{ request('search') }}" placeholder="Mod vagy készítő neve" class="form-input mt-1">
        </div>
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Státusz</label>
            <select id="status" name="status" class="form-input mt-1">
                <option value="">Összes státusz</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-pink-600 text-white text-sm font-semibold rounded-lg shadow hover:bg-pink-700 transition"><i class="fa-solid fa-filter mr-2"></i>Szűrés</button>
        </div>
    </form>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Mod</th>
                    <th class="px-4 py-3 text-left">Készítő</th>
                    <th class="px-4 py-3 text-left">Státusz</th>
                    <th class="px-4 py-3 text-right">Interakciók</th>
                    <th class="px-4 py-3 text-right">Műveletek</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($mods as $mod)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-4">
                            <div class="font-semibold text-gray-900 flex items-center gap-3">
                                <img src="{{ $mod->hero_image_url }}" alt="{{ $mod->title }}" class="w-12 h-12 rounded-lg object-cover hidden md:block">
                                <div>
                                    <a href="{{ route('admin.mods.edit', $mod) }}" class="hover:text-pink-600">{{ $mod->title }}</a>
                                    <p class="text-xs text-gray-500 mt-1">Létrehozva {{ $mod->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-gray-600">{{ $mod->author?->name ?? 'Ismeretlen' }}</td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold"
                                @class([
                                    'bg-emerald-100 text-emerald-700' => $mod->status === App\Models\Mod::STATUS_PUBLISHED,
                                    'bg-amber-100 text-amber-700' => $mod->status === App\Models\Mod::STATUS_PENDING,
                                    'bg-gray-200 text-gray-700' => ! in_array($mod->status, [App\Models\Mod::STATUS_PENDING, App\Models\Mod::STATUS_PUBLISHED]),
                                ])>
                                <i class="fa-solid fa-circle"></i>{{ $mod->statusLabel() }}
                            </span>
                            @if ($mod->featured)
                                <span class="mt-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-pink-100 text-pink-700 text-xs font-semibold"><i class="fa-solid fa-star"></i>Kiemelt</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right text-gray-600">
                            <div>{{ number_format($mod->downloads) }} letöltés</div>
                            <div>{{ number_format($mod->likes) }} kedvelés</div>
                            <div>{{ $mod->comments_count }} hozzászólás</div>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.mods.edit', $mod) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-pink-600 text-xs font-semibold text-pink-600 hover:bg-pink-50">Szerkesztés</a>
                                <form method="POST" action="{{ route('admin.mods.destroy', $mod) }}" onsubmit="return confirm('Biztosan törlöd a modot?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-rose-500 text-xs font-semibold text-rose-600 hover:bg-rose-50">Törlés</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Nincs találat a megadott feltételekkel.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $mods->links() }}
@endsection
