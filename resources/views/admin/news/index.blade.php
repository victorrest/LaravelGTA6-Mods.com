@extends('admin.layout', ['title' => 'Hírek kezelése'])

@section('admin-header')
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Hírcikkek</h1>
            <p class="text-sm text-gray-500">A WordPress sablon hírblokkjának tartalma innen szerkeszthető.</p>
        </div>
        <a href="{{ route('admin.news.create') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition"><i class="fa-solid fa-plus mr-2"></i>Új cikk</a>
    </div>
@endsection

@section('admin-content')
    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Cím</th>
                    <th class="px-4 py-3 text-left">Szerző</th>
                    <th class="px-4 py-3 text-left">Publikálva</th>
                    <th class="px-4 py-3 text-right">Műveletek</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($articles as $article)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-4">
                            <a href="{{ route('admin.news.edit', $article) }}" class="font-semibold text-gray-900 hover:text-pink-600">{{ $article->title }}</a>
                            <p class="text-xs text-gray-500 mt-1">Slug: {{ $article->slug }}</p>
                        </td>
                        <td class="px-4 py-4 text-gray-600">{{ $article->author?->name ?? 'Ismeretlen' }}</td>
                        <td class="px-4 py-4 text-gray-600">{{ optional($article->published_at)->format('Y.m.d H:i') ?? 'Nincs' }}</td>
                        <td class="px-4 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.news.edit', $article) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-pink-600 text-xs font-semibold text-pink-600 hover:bg-pink-50">Szerkesztés</a>
                                <form method="POST" action="{{ route('admin.news.destroy', $article) }}" onsubmit="return confirm('Biztosan törlöd a cikket?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-rose-500 text-xs font-semibold text-rose-600 hover:bg-rose-50">Törlés</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Még nincs hír publikálva.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $articles->links() }}
@endsection
