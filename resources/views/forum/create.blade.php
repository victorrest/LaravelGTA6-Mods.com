@extends('layouts.app', ['title' => 'Create thread'])

@section('content')
    <section class="max-w-3xl mx-auto space-y-6">
        <header class="space-y-2 text-center">
            <h1 class="text-3xl font-bold text-gray-900">Start a new discussion</h1>
            <p class="text-sm text-gray-500">Share discoveries, troubleshoot issues or propose new mod ideas.</p>
        </header>

        <form method="POST" action="{{ route('forum.store') }}" class="card p-6 space-y-5">
            @include('components.validation-errors')
            @csrf
            <div>
                <label class="form-label" for="title">Title</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="flair">Flair</label>
                <select id="flair" name="flair" class="form-input">
                    <option value="">No flair</option>
                    @foreach ($flairs as $flair)
                        <option value="{{ $flair }}" @selected(old('flair') === $flair)> {{ ucfirst($flair) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="body">Body</label>
                <x-editorjs
                    name="body"
                    id="body"
                    :value="old('body')"
                    :plain-text="\App\Support\EditorJs::toPlainText(old('body'))"
                    placeholder="Share every detail, add bullet lists, embeds or code snippets"
                    required
                />
            </div>
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('forum.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition">
                    <i class="fa-solid fa-paper-plane mr-2"></i>Publish thread
                </button>
            </div>
        </form>
    </section>
@endsection
