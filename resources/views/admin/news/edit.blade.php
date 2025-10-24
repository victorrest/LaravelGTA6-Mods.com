@extends('admin.layout', ['title' => 'Hír szerkesztése: ' . $article->title])

@section('admin-header')
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $article->title }}</h1>
            <p class="text-sm text-gray-500">Utolsó módosítás: {{ $article->updated_at->diffForHumans() }}</p>
        </div>
        <a href="{{ route('news.show', $article) }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50" target="_blank">Megnyitás a nyilvános oldalon</a>
    </div>
@endsection

@section('admin-content')
    <form method="POST" action="{{ route('admin.news.update', $article) }}" class="card p-6 space-y-4">
        @csrf
        @method('PUT')
        @include('components.validation-errors')
        <div>
            <label class="form-label" for="title">Cím</label>
            <input id="title" name="title" type="text" value="{{ old('title', $article->title) }}" class="form-input" required>
        </div>
        <div>
            <label class="form-label" for="slug">Slug</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $article->slug) }}" class="form-input" required>
        </div>
        <div>
            <label class="form-label" for="excerpt">Kivonat</label>
            <textarea id="excerpt" name="excerpt" rows="3" class="form-textarea" required>{{ old('excerpt', $article->excerpt) }}</textarea>
        </div>
        <div>
            <label class="form-label" for="body">Tartalom</label>
            <textarea id="body" name="body" rows="10" class="form-textarea" required>{{ old('body', $article->body) }}</textarea>
        </div>
        <div>
            <label class="form-label" for="published_at">Publikálás ideje</label>
            <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', optional($article->published_at)->format('Y-m-d\TH:i')) }}" class="form-input">
        </div>
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.news.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Vissza</a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition"><i class="fa-solid fa-floppy-disk mr-2"></i>Mentés</button>
        </div>
    </form>
@endsection
