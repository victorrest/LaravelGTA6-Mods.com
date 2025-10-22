@extends('layouts.app', ['title' => 'Mods'])

@section('content')
    <section class="space-y-6">
        <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Browse Mods</h1>
                <p class="text-sm text-gray-500">Find the perfect GTA 6 mod by filtering categories, popularity and release date.</p>
            </div>
            <form method="GET" action="{{ route('mods.index') }}" class="flex flex-col md:flex-row gap-3">
                <div>
                    <label for="category" class="sr-only">Category</label>
                    <select id="category" name="category" class="px-3 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sort" class="sr-only">Sort</label>
                    <select id="sort" name="sort" class="px-3 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500">
                        <option value="latest" @selected(request('sort', 'latest') === 'latest')>Latest</option>
                        <option value="popular" @selected(request('sort') === 'popular')>Most popular</option>
                        <option value="rating" @selected(request('sort') === 'rating')>Highest rated</option>
                    </select>
                </div>
                <div class="md:self-end">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-pink-600 text-white text-sm font-medium rounded-lg shadow hover:bg-pink-700 transition">Apply</button>
                </div>
            </form>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-5">
            @forelse ($mods as $mod)
                @include('mods.partials.card', ['mod' => $mod])
            @empty
                <p class="text-sm text-gray-500 col-span-full">No mods match your filters.</p>
            @endforelse
        </div>

        <div>
            {{ $mods->withQueryString()->links() }}
        </div>
    </section>
@endsection
