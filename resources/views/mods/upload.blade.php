@extends('layouts.app', ['title' => 'Upload Mod'])

@section('content')
    <section class="max-w-4xl mx-auto space-y-6">
        <header class="space-y-2 text-center">
            <h1 class="text-3xl font-bold text-gray-900">Upload a new mod</h1>
            <p class="text-sm text-gray-500">Share your GTA 6 creation with the community. Every submission is reviewed by our moderation team to keep the library spotless.</p>
        </header>

        <form method="POST" action="{{ route('mods.store') }}" enctype="multipart/form-data" class="card p-6 space-y-6">
            @include('components.validation-errors')
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label" for="title">Title</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label" for="version">Version</label>
                    <input id="version" name="version" type="text" value="{{ old('version', '1.0.0') }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label" for="category_ids">Categories</label>
                    <select id="category_ids" name="category_ids[]" multiple class="form-multiselect" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(collect(old('category_ids'))->contains($category->id))>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <p class="form-help">Hold Ctrl or Cmd to select multiple categories.</p>
                </div>
                <div>
                    <label class="form-label" for="download_url">Download URL</label>
                    <input id="download_url" name="download_url" type="url" value="{{ old('download_url') }}" class="form-input" required>
                </div>
            </div>

            <div>
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" rows="8" class="form-textarea" required>{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label" for="hero_image">Hero image</label>
                    <input id="hero_image" name="hero_image" type="file" accept="image/*" class="form-input">
                    <p class="form-help">Recommended size: 1600x900px.</p>
                </div>
                <div>
                    <label class="form-label" for="file_size">File size (MB)</label>
                    <input id="file_size" name="file_size" type="number" step="0.1" min="0" value="{{ old('file_size') }}" class="form-input">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('mods.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition">
                    <i class="fa-solid fa-paper-plane mr-2"></i>Submit for review
                </button>
            </div>
        </form>
    </section>
@endsection
