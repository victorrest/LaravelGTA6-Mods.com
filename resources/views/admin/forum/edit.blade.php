@extends('admin.layout', ['title' => 'Fórum téma: ' . $thread->title])

@section('admin-header')
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $thread->title }}</h1>
            <p class="text-sm text-gray-500">{{ $thread->author?->name ?? 'Ismeretlen' }} • {{ $thread->created_at->diffForHumans() }}</p>
        </div>
        <a href="{{ route('forum.show', $thread) }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50" target="_blank">Megnyitás a nyilvános oldalon</a>
    </div>
@endsection

@section('admin-content')
    <form method="POST" action="{{ route('admin.forum.update', $thread) }}" class="card p-6 space-y-4">
        @csrf
        @method('PUT')
        @include('components.validation-errors')
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="form-label" for="title">Cím</label>
                <input id="title" name="title" type="text" value="{{ old('title', $thread->title) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="flair">Flair</label>
                <input id="flair" name="flair" type="text" value="{{ old('flair', $thread->flair) }}" class="form-input" placeholder="Pl. Közlemény, Kérdés">
            </div>
        </div>
        <div class="flex flex-wrap gap-4">
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                <input type="checkbox" name="pinned" value="1" class="rounded border-gray-300 text-pink-600 focus:ring-pink-500" @checked(old('pinned', $thread->pinned))>
                Téma kiemelése (a lista tetején jelenik meg)
            </label>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                <input type="checkbox" name="locked" value="1" class="rounded border-gray-300 text-pink-600 focus:ring-pink-500" @checked(old('locked', $thread->locked))>
                Új válaszok letiltása (zárolt állapot)
            </label>
        </div>
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.forum.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Vissza</a>
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition"><i class="fa-solid fa-floppy-disk mr-2"></i>Mentés</button>
        </div>
    </form>

    <section class="card mt-6">
        <header class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Hozzászólások</h2>
            <span class="text-sm text-gray-500">Összesen {{ $posts->total() }}</span>
        </header>
        <div class="divide-y divide-gray-100">
            @forelse ($posts as $post)
                <article class="p-6 space-y-2">
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span><strong class="text-gray-900">{{ $post->author?->name ?? 'Ismeretlen' }}</strong> • {{ $post->created_at->diffForHumans() }}</span>
                        <form method="POST" action="{{ route('admin.forum.posts.destroy', [$thread, $post]) }}" onsubmit="return confirm('Biztosan törlöd ezt a hozzászólást?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-500">
                                <i class="fa-solid fa-trash"></i>Törlés
                            </button>
                        </form>
                    </div>
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 text-sm text-gray-700 leading-relaxed editorjs-content">
                        {!! $post->body_html !!}
                    </div>
                </article>
            @empty
                <p class="p-6 text-sm text-gray-500">Nincsenek hozzászólások.</p>
            @endforelse
        </div>
        <div class="px-6 py-4 border-t border-gray-100">{{ $posts->links() }}</div>
    </section>
@endsection
