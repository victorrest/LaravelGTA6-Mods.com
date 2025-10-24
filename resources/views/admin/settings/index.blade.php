@extends('admin.layout')

@section('title', 'Beállítások')

@section('content')
    <div class="space-y-6">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Globális beállítások</h1>
                <p class="text-sm text-gray-500">Itt adhatod meg a YouTube API kulcsot a videók meta adatainak feldolgozásához.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="card p-6 space-y-5">
            <form method="POST" action="{{ route('admin.settings.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="youtube_api_key" class="block text-sm font-medium text-gray-700">YouTube API kulcs</label>
                    <input id="youtube_api_key" name="youtube_api_key" type="text" value="{{ old('youtube_api_key', optional($settings['youtube.api_key'] ?? null)->value) }}" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500" placeholder="AIza...">
                    <p class="mt-1 text-xs text-gray-500">A kulcs a YouTube Data API v3 hívásaihoz szükséges.</p>
                    @error('youtube_api_key')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-pink-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-pink-700">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Mentés</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
