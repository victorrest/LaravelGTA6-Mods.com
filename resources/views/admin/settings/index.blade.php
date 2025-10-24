@extends('admin.layout', ['title' => 'Beállítások'])

@section('admin-header')
    <h1 class="text-2xl font-bold text-gray-900">Rendszer beállítások</h1>
    <p class="text-gray-600 text-sm">Általános rendszerbeállítások és API kulcsok kezelése</p>
@endsection

@section('admin-content')
    <div class="card p-6">
        @if (session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                <i class="fa-solid fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf

            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 border-b pb-2">
                    <i class="fa-brands fa-youtube text-red-600 mr-2"></i>YouTube API beállítások
                </h2>

                <div>
                    <label for="youtube_api_key" class="block text-sm font-medium text-gray-700 mb-2">
                        YouTube Data API v3 kulcs
                    </label>
                    <input
                        type="text"
                        name="youtube_api_key"
                        id="youtube_api_key"
                        value="{{ old('youtube_api_key', $settings['youtube_api_key']) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500"
                        placeholder="AIzaSy..."
                    >
                    <p class="mt-2 text-sm text-gray-500">
                        <i class="fa-solid fa-info-circle mr-1"></i>
                        Ez a kulcs szükséges a YouTube videó metaadatok (cím, leírás, időtartam) lekéréséhez.
                        Szerezz be egyet a
                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-pink-600 hover:underline">Google Cloud Console</a>-on.
                    </p>
                    @error('youtube_api_key')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t">
                <p class="text-sm text-gray-500">
                    <i class="fa-solid fa-shield-halved mr-1"></i>
                    A beállítások azonnal mentésre kerülnek
                </p>
                <button
                    type="submit"
                    class="inline-flex items-center px-6 py-3 bg-pink-600 text-white font-semibold rounded-lg shadow hover:bg-pink-700 transition"
                >
                    <i class="fa-solid fa-save mr-2"></i>
                    Beállítások mentése
                </button>
            </div>
        </form>
    </div>

    <div class="card p-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fa-solid fa-book mr-2 text-blue-600"></i>YouTube API használat
        </h2>
        <div class="prose max-w-none text-sm text-gray-600">
            <p><strong>A YouTube Data API v3 kulcs beszerzése:</strong></p>
            <ol class="list-decimal list-inside space-y-2 mt-3">
                <li>Látogasd meg a <a href="https://console.cloud.google.com/" target="_blank" class="text-pink-600 hover:underline">Google Cloud Console</a>-t</li>
                <li>Hozz létre egy új projektet, vagy válassz egy meglévőt</li>
                <li>Engedélyezd a "YouTube Data API v3"-at a projektedhez</li>
                <li>Hozz létre egy API kulcsot a Credentials oldalon</li>
                <li>Másold be a kulcsot a fenti mezőbe</li>
            </ol>
            <p class="mt-4">
                <strong>Fontos:</strong> Az API kulcs korlátozott napi kvótával rendelkezik. A Google Cloud Console-ban
                követheted a használatot és szükség esetén emelheted a kvótát.
            </p>
        </div>
    </div>
@endsection
