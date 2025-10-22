@extends('admin.layout', ['title' => 'Mod kategóriák'])

@section('admin-header')
    <h1 class="text-3xl font-bold text-gray-900">Kategóriák</h1>
    <p class="text-sm text-gray-500">A WordPress sablonban alkalmazott kategória struktúra kezelése.</p>
@endsection

@section('admin-content')
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.categories.store') }}" class="card p-5 space-y-4">
            @csrf
            <h2 class="text-lg font-semibold text-gray-900">Új kategória</h2>
            @include('components.validation-errors')
            <div>
                <label class="form-label" for="name">Név</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="slug">Slug</label>
                <input id="slug" name="slug" type="text" value="{{ old('slug') }}" class="form-input" required>
                <p class="form-help">Pl. vehicles, scripts – megegyezik a WordPress sablonnal.</p>
            </div>
            <div>
                <label class="form-label" for="icon">Ikon</label>
                <input id="icon" name="icon" type="text" value="{{ old('icon', 'fa-solid fa-star') }}" class="form-input">
                <p class="form-help">Font Awesome osztály, pl. <code>fa-solid fa-car</code>.</p>
            </div>
            <div>
                <label class="form-label" for="description">Leírás</label>
                <textarea id="description" name="description" rows="3" class="form-textarea">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition">
                <i class="fa-solid fa-plus mr-2"></i>Kategória létrehozása
            </button>
        </form>

        <div class="card divide-y divide-gray-100">
            <header class="p-5">
                <h2 class="text-lg font-semibold text-gray-900">Meglévő kategóriák</h2>
                <p class="text-xs text-gray-500">Szerkesztés vagy törlés azonnal frissíti a frontend navigációt.</p>
            </header>
            @forelse ($categories as $category)
                <div class="p-5 space-y-3">
                    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 text-gray-700">
                                <i class="fa-solid {{ $category->icon ?? 'fa-star' }} text-pink-500"></i>
                                <h3 class="font-semibold text-gray-900">{{ $category->name }}</h3>
                            </div>
                            <span class="text-xs text-gray-400">Slug: {{ $category->slug }}</span>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="form-label" for="name-{{ $category->id }}">Név</label>
                                <input id="name-{{ $category->id }}" type="text" name="name" value="{{ old('name', $category->name) }}" class="form-input" required>
                            </div>
                            <div>
                                <label class="form-label" for="slug-{{ $category->id }}">Slug</label>
                                <input id="slug-{{ $category->id }}" type="text" name="slug" value="{{ old('slug', $category->slug) }}" class="form-input" required>
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="form-label" for="icon-{{ $category->id }}">Ikon</label>
                                <input id="icon-{{ $category->id }}" type="text" name="icon" value="{{ old('icon', $category->icon) }}" class="form-input">
                            </div>
                            <div>
                                <label class="form-label" for="description-{{ $category->id }}">Leírás</label>
                                <textarea id="description-{{ $category->id }}" name="description" rows="2" class="form-textarea">{{ old('description', $category->description) }}</textarea>
                            </div>
                        </div>
                        <div class="flex items-center justify-end">
                            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 transition">Mentés</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Biztosan törlöd a kategóriát?');" class="text-right">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-rose-600 text-white text-xs font-semibold hover:bg-rose-500 transition">Törlés</button>
                    </form>
                </div>
            @empty
                <p class="p-5 text-sm text-gray-500">Még nincsenek kategóriák.</p>
            @endforelse
        </div>
    </div>
@endsection
