@extends('admin.layout', ['title' => 'Felhasználó szerkesztése: ' . $user->name])

@section('admin-header')
    <h1 class="text-3xl font-bold text-gray-900">{{ $user->name }}</h1>
    <p class="text-sm text-gray-500">Regisztráció: {{ $user->created_at->format('Y.m.d H:i') }} • Modok: {{ $user->mods()->count() }}</p>
@endsection

@section('admin-content')
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card p-6 space-y-4">
        @csrf
        @method('PUT')
        @include('components.validation-errors')
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label" for="name">Név</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="form-input" required>
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label" for="password">Új jelszó (opcionális)</label>
                <input id="password" name="password" type="password" class="form-input" autocomplete="new-password">
            </div>
            <div>
                <label class="form-label" for="password_confirmation">Jelszó megerősítése</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="form-input" autocomplete="new-password">
            </div>
        </div>
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
            <input type="checkbox" name="is_admin" value="1" class="rounded border-gray-300 text-pink-600 focus:ring-pink-500" @checked(old('is_admin', $user->isAdmin()))>
            Rendszergazdai jogosultság (hozzáférés az admin felülethez)
        </label>
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Mégse</a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition"><i class="fa-solid fa-floppy-disk mr-2"></i>Mentés</button>
        </div>
    </form>
@endsection
