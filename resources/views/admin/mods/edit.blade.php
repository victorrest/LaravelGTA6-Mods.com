@extends('admin.layout', ['title' => 'Mod szerkesztése: ' . $mod->title])

@php($statuses = App\Models\Mod::STATUS_LABELS)

@section('admin-header')
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $mod->title }}</h1>
            <p class="text-sm text-gray-500">Készítette: {{ $mod->author?->name ?? 'Ismeretlen' }} • {{ $mod->statusLabel() }}</p>
        </div>
        <a href="{{ route('mods.show', $mod) }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50" target="_blank">Megnyitás a nyilvános oldalon</a>
    </div>
@endsection

@section('admin-content')
    <form method="POST" action="{{ route('admin.mods.update', $mod) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="card p-6 space-y-5">
            <h2 class="text-lg font-semibold text-gray-900">Alapadatok</h2>
            @include('components.validation-errors')
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="form-label" for="title">Cím</label>
                    <input type="text" id="title" name="title" value="{{ old('title', $mod->title) }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label" for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug', $mod->slug) }}" class="form-input" required>
                    <p class="form-help">SEO-barát URL, mint a WordPress sablonban.</p>
                </div>
                <div>
                    <label class="form-label" for="version">Verzió</label>
                    <input type="text" id="version" name="version" value="{{ old('version', $mod->version) }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label" for="download_url">Letöltési URL</label>
                    <input type="url" id="download_url" name="download_url" value="{{ old('download_url', $mod->download_url) }}" class="form-input" required>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="form-label" for="status">Státusz</label>
                    <select id="status" name="status" class="form-input" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $mod->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="file_size">Fájlméret (MB)</label>
                    <input type="number" step="0.01" min="0" id="file_size" name="file_size" value="{{ old('file_size', $mod->file_size) }}" class="form-input">
                </div>
            </div>
            <div>
                <label class="form-label" for="excerpt">Kivonat</label>
                <textarea id="excerpt" name="excerpt" rows="3" class="form-textarea">{{ old('excerpt', $mod->excerpt) }}</textarea>
                <p class="form-help">Rövid leírás a listanézetekhez, maximum 255 karakter.</p>
            </div>
        <div>
            <label class="form-label" for="description">Leírás</label>
            <x-editorjs
                name="description"
                id="description"
                :value="old('description', $mod->description_raw)"
                :plain-text="\App\Support\EditorJs::toPlainText(old('description', $mod->description_raw))"
                placeholder="Fogalmazd meg a mod részletes leírását"
                required
            />
        </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="form-label" for="category_ids">Kategóriák</label>
                    <select id="category_ids" name="category_ids[]" multiple class="form-multiselect" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(collect(old('category_ids', $mod->categories->pluck('id')->all()))->contains($category->id))>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <p class="form-help">Több kijelölés támogatott, ahogy a WordPress sablonban.</p>
                </div>
                <div class="space-y-2">
                    <label class="form-label" for="hero_image">Borítókép</label>
                    <input type="file" id="hero_image" name="hero_image" class="form-input" accept="image/*">
                    <p class="form-help">Új feltöltés esetén lecseréli a jelenlegi képet.</p>
                    <img src="{{ $mod->hero_image_url }}" alt="Aktuális kép" class="rounded-xl border border-gray-200">
                </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                <input type="checkbox" name="featured" value="1" class="rounded border-gray-300 text-pink-600 focus:ring-pink-500" @checked(old('featured', $mod->featured))>
                Kiemelt modként jelenjen meg a főoldalon
            </label>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.mods.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Mégse</a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition">
                <i class="fa-solid fa-floppy-disk mr-2"></i>Mentés
            </button>
        </div>
    </form>
@endsection
