@extends('admin.layout', ['title' => 'Új hír publikálása'])

@section('admin-header')
    <h1 class="text-3xl font-bold text-gray-900">Új hír</h1>
    <p class="text-sm text-gray-500">Publikálj közösségi frissítést a WordPress mintához igazodva.</p>
@endsection

@section('admin-content')
    <form method="POST" action="{{ route('admin.news.store') }}" class="card p-6 space-y-4">
        @csrf
        @include('components.validation-errors')
        <div>
            <label class="form-label" for="title">Cím</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" class="form-input" required>
        </div>
        <div>
            <label class="form-label" for="slug">Slug (opcionális)</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug') }}" class="form-input" placeholder="automatikus, ha üresen hagyod">
        </div>
        <div>
            <label class="form-label" for="excerpt">Kivonat</label>
            <textarea id="excerpt" name="excerpt" rows="3" class="form-textarea" required>{{ old('excerpt') }}</textarea>
        </div>
        <div>
            <label class="form-label" for="body">Tartalom</label>
            <textarea id="body" name="body" rows="10" class="form-textarea" required>{{ old('body') }}</textarea>
        </div>
        <div>
            <label class="form-label" for="published_at">Publikálás ideje</label>
            <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at') }}" class="form-input">
        </div>
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.news.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Mégse</a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition"><i class="fa-solid fa-paper-plane mr-2"></i>Publikálás</button>
        </div>
    </form>
@endsection
