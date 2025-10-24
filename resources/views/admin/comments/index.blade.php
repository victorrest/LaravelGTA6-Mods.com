@extends('admin.layout', ['title' => 'Mod hozzászólások moderálása'])

@section('admin-header')
    <h1 class="text-3xl font-bold text-gray-900">Mod hozzászólások</h1>
    <p class="text-sm text-gray-500">Spam és szabálysértő bejegyzések eltávolítása a WordPress sablonhoz igazodó stílusban.</p>
@endsection

@section('admin-content')
    <form method="GET" class="card p-4 grid gap-4 md:grid-cols-[2fr_1fr]">
        <div>
            <label for="search" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Keresés</label>
            <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-input mt-1" placeholder="Kulcsszó, felhasználó vagy mod">
        </div>
        <div class="flex items-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-pink-600 text-white text-sm font-semibold shadow hover:bg-pink-700 transition"><i class="fa-solid fa-magnifying-glass mr-2"></i>Keresés</button>
        </div>
    </form>

    <div class="card divide-y divide-gray-100">
        @forelse ($comments as $comment)
            <article class="p-5 space-y-3">
                <header class="flex items-center justify-between text-sm text-gray-500">
                    <div>
                        <strong class="text-gray-900">{{ $comment->author?->name ?? 'Ismeretlen' }}</strong>
                        <span> • {{ $comment->created_at->diffForHumans() }}</span>
                        <span> • <a href="{{ route('mods.show', $comment->mod) }}" class="text-pink-600 hover:text-pink-500">{{ $comment->mod?->title }}</a></span>
                    </div>
                    <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}" onsubmit="return confirm('Biztosan törlöd ezt a hozzászólást?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-500">
                            <i class="fa-solid fa-trash"></i>Törlés
                        </button>
                    </form>
                </header>
                <p class="text-sm text-gray-700 leading-relaxed">{!! nl2br(e($comment->body)) !!}</p>
            </article>
        @empty
            <p class="p-6 text-sm text-gray-500">Nincs moderálni való hozzászólás.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $comments->links() }}</div>
@endsection
