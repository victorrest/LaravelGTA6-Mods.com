@extends('admin.layout', ['title' => 'Felhasználók kezelése'])

@section('admin-header')
    <h1 class="text-3xl font-bold text-gray-900">Felhasználók</h1>
    <p class="text-sm text-gray-500">Moderátor jogosultságok kiosztása és közösségi tagok áttekintése.</p>
@endsection

@section('admin-content')
    <form method="GET" class="card p-4 grid gap-4 md:grid-cols-[2fr_1fr]">
        <div>
            <label for="search" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Keresés</label>
            <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-input mt-1" placeholder="Név vagy e-mail">
        </div>
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition"><i class="fa-solid fa-magnifying-glass mr-2"></i>Keresés</button>
        </div>
    </form>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Felhasználó</th>
                    <th class="px-4 py-3 text-left">Csatlakozott</th>
                    <th class="px-4 py-3 text-left">Modok</th>
                    <th class="px-4 py-3 text-left">Kommentek</th>
                    <th class="px-4 py-3 text-left">Jogosultság</th>
                    <th class="px-4 py-3 text-right">Műveletek</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-4">
                            <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                            <p class="text-xs text-gray-500">{{ $user->email }}</p>
                        </td>
                        <td class="px-4 py-4 text-gray-600">{{ $user->created_at->format('Y.m.d') }}</td>
                        <td class="px-4 py-4 text-gray-600">{{ $user->mods_count }}</td>
                        <td class="px-4 py-4 text-gray-600">{{ $user->mod_comments_count }}</td>
                        <td class="px-4 py-4">
                            @if ($user->isAdmin())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-semibold"><i class="fa-solid fa-shield"></i>Admin</span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-200 text-gray-700 text-xs font-semibold">Tag</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right">
                            <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-pink-600 text-xs font-semibold text-pink-600 hover:bg-pink-50">Szerkesztés</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Nincs találat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
