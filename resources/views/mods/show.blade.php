@extends('layouts.app', ['title' => $mod->title])

@section('content')
    @php
        $ratingDisplay = $ratingValue ? number_format($ratingValue, 1) : '—';
    @endphp

    <div class="space-y-6">
        <nav class="text-sm text-gray-500" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1">
                @foreach ($breadcrumbs as $index => $crumb)
                    @php($isLast = $loop->last)
                    <li class="flex items-center gap-1">
                        @if (!empty($crumb['url']) && !$isLast)
                            <a href="{{ $crumb['url'] }}" class="hover:text-pink-600">{{ $crumb['label'] }}</a>
                        @else
                            <span class="{{ $isLast ? 'text-gray-700 font-semibold' : '' }}">{{ $crumb['label'] }}</span>
                        @endif
                        @unless($loop->last)
                            <span class="text-gray-400">&raquo;</span>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
            <div class="flex-grow min-w-0">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight flex flex-wrap items-center gap-2">
                    @if ($mod->featured)
                        <i class="fa-solid fa-star text-yellow-400" aria-hidden="true"></i>
                    @endif
                    <span class="break-words">{{ $mod->title }}</span>
                    @if (!empty($metaDetails['version']))
                        <span class="text-xl md:text-2xl font-semibold text-gray-400">{{ $metaDetails['version'] }}</span>
                    @endif
                </h1>
                <div class="flex items-center flex-wrap gap-x-5 gap-y-2 text-gray-500 text-sm mt-2">
                    <span class="flex items-center">
                        by <span class="font-semibold text-amber-600 ml-1">{{ $mod->author->name }}</span>
                    </span>
                    <span class="flex items-center" aria-label="Total downloads">
                        <i class="fa-solid fa-download mr-1.5 text-gray-400"></i>{{ $downloadFormatted }}
                    </span>
                    <span class="flex items-center" aria-label="Total likes">
                        <i class="fa-solid fa-thumbs-up mr-1.5 text-gray-400"></i>{{ $likesFormatted }}
                    </span>
                </div>
            </div>
            <div class="flex flex-col items-stretch md:items-end gap-3 w-full md:w-auto">
                <form method="POST" action="{{ route('mods.download', [$primaryCategory, $mod]) }}" class="w-full md:w-auto">
                    @csrf
                    <button type="submit" class="btn-download font-bold py-3 px-5 rounded-[12px] transition flex items-center justify-center w-full md:w-auto bg-pink-600 text-white hover:bg-pink-700 shadow">
                        <i class="fa-solid fa-download mr-2"></i>
                        <span>Download</span>
                    </button>
                </form>
                <div class="text-sm text-gray-500 md:text-right space-y-2">
                    <div class="flex items-center justify-start md:justify-end gap-1 text-xl font-bold text-gray-900">
                        <span>{{ $ratingDisplay }}</span>
                        <span class="text-base font-normal text-gray-500">/ 5</span>
                    </div>
                    <div class="flex justify-start md:justify-end gap-1 text-lg text-yellow-400" aria-label="Átlagos értékelés">
                        @for ($i = 1; $i <= 5; $i++)
                            @php($isHalf = $ratingHasHalf && $i === $ratingFullStars + 1)
                            <i class="fa-solid {{ $i <= $ratingFullStars ? 'fa-star text-yellow-400' : ($isHalf ? 'fa-star-half-stroke text-yellow-400' : 'fa-star text-gray-300') }}"></i>
                        @endfor
                    </div>
                    <p class="text-xs text-gray-400">
                        Közösségi értékelés · {{ number_format($ratingCount) }} értékelés
                    </p>

                    @auth
                        <form method="POST" action="{{ route('mods.rate', [$primaryCategory, $mod]) }}" class="space-y-2" data-rating-form data-rating-initial="{{ $userRating ?? 0 }}">
                            @csrf
                            <input type="hidden" name="rating" value="{{ $userRating ?? '' }}" data-rating-input>
                            <div class="flex justify-start md:justify-end gap-1 text-2xl" data-rating-stars aria-label="Add le az értékelésed">
                                @for ($i = 1; $i <= 5; $i++)
                                    <button type="button" class="rating-star bg-transparent p-1 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500 transition-transform" data-rating-value="{{ $i }}" aria-label="{{ $i }} csillag">
                                        <i class="fa-star {{ $userRating && $i <= $userRating ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300' }}"></i>
                                    </button>
                                @endfor
                            </div>
                            <div class="flex items-center justify-start md:justify-end gap-2">
                                <button type="submit" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-pink-600 text-white text-xs font-semibold shadow transition disabled:opacity-60 disabled:cursor-not-allowed" data-rating-submit disabled>
                                    <i class="fa-solid fa-paper-plane text-xs"></i>
                                    <span>Értékelés mentése</span>
                                </button>
                                <span class="text-xs text-gray-400" data-rating-feedback>{{ $userRating ? 'Jelenlegi értékelésed: ' . $userRating . '/5' : 'Kattints egy csillagra a saját értékelésedhez.' }}</span>
                            </div>
                        </form>
                    @else
                        <p class="text-xs text-gray-400">A saját értékelésed leadásához <a href="{{ route('login') }}" class="text-pink-600 hover:text-pink-700 font-medium">jelentkezz be</a>.</p>
                    @endauth
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="card overflow-hidden">
                    <div class="relative w-full aspect-video bg-gray-900">
                        <img src="{{ $galleryImages[0]['src'] }}" alt="{{ $galleryImages[0]['alt'] }}" class="absolute inset-0 w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent pointer-events-none"></div>
                    </div>
                </div>

                @if (count($galleryImages) > 1)
                    <div class="card p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Galéria</h2>
                            <span class="text-xs text-gray-500">{{ count($galleryImages) }} kép</span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach (array_slice($galleryImages, 1) as $image)
                                <div class="group relative overflow-hidden rounded-xl border border-gray-200">
                                    <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" class="h-40 w-full object-cover transition-transform duration-300 group-hover:scale-105">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card border-t border-b border-gray-200 py-4 px-4 md:px-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center flex-wrap gap-4 md:gap-6 text-gray-600">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-download text-3xl text-pink-500" aria-hidden="true"></i>
                            <div>
                                <p class="font-bold text-base text-gray-800">{{ $downloadFormatted }}</p>
                                <p class="text-xs uppercase text-gray-400">Letöltés</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fa-regular fa-thumbs-up text-3xl text-pink-500" aria-hidden="true"></i>
                            <div>
                                <p class="font-bold text-base text-gray-800">{{ $likesFormatted }}</p>
                                <p class="text-xs uppercase text-gray-400">Kedvelés</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500 space-y-1 sm:text-right">
                        <p><span class="font-semibold text-gray-800">Első feltöltés:</span> {{ $metaDetails['uploaded_at'] ?? '—' }}</p>
                        <p><span class="font-semibold text-gray-800">Utolsó frissítés:</span> {{ $metaDetails['updated_at'] ?? '—' }}</p>
                    </div>
                </div>

                <div class="card p-6 space-y-6">
                    <section id="mod-description" class="space-y-4">
                        <h2 class="text-xl font-semibold text-gray-900">Leírás</h2>
                        <div class="editorjs-content">
                            {!! $mod->description_html !!}
                        </div>
                    </section>

                    <section aria-labelledby="mod-tags" class="pt-4 border-t border-gray-200 space-y-3">
                        <h3 id="mod-tags" class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Címkék</h3>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($mod->categories as $category)
                                <a href="{{ route('mods.index', ['category' => $category->slug]) }}" class="bg-gray-200 text-gray-700 text-xs font-semibold px-3 py-1 rounded-full hover:bg-gray-300 transition">{{ $category->name }}</a>
                            @empty
                                <span class="text-xs text-gray-400">Nincsenek kategóriák megadva.</span>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="card p-6 space-y-6">
                    <section id="mod-comments" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-900">Kommentek</h2>
                            <span class="text-sm text-gray-500">{{ $mod->comments_count }} összesen</span>
                        </div>
                        <div class="space-y-4">
                            @forelse ($comments as $comment)
                                <article class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                                    <header class="flex items-center justify-between">
                                        <p class="font-semibold text-gray-900">{{ $comment->author->name }}</p>
                                        <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                    </header>
                                    <p class="text-sm text-gray-700 mt-2">{{ $comment->body }}</p>
                                </article>
                            @empty
                                <p class="text-sm text-gray-500">Még nincsenek hozzászólások.</p>
                            @endforelse
                        </div>
                        @auth
                            <form method="POST" action="{{ route('mods.comment', [$primaryCategory, $mod]) }}" class="space-y-3">
                                @csrf
                                <textarea name="body" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-500" placeholder="Írd meg a véleményed"></textarea>
                                <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-pink-600 text-white text-sm font-medium rounded-lg shadow hover:bg-pink-700 transition">Hozzászólás küldése</button>
                            </form>
                        @else
                            <p class="text-sm text-gray-500">A hozzászóláshoz kérjük <a href="{{ route('login') }}" class="text-pink-600">jelentkezz be</a>.</p>
                        @endauth
                    </section>
                </div>

                <div class="card p-6 space-y-4">
                    <section id="mod-changelog" class="space-y-3">
                        <h2 class="text-xl font-semibold text-gray-900">Verziótörténet (Changelog)</h2>
                        <p class="text-sm text-gray-500">A verziótörténet hamarosan elérhető lesz.</p>
                    </section>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Letöltés</h2>
                    <p class="text-sm text-gray-600 leading-relaxed">Ez a mod a közösség által lett feltöltve és folyamatosan karbantartva.</p>
                    <form method="POST" action="{{ route('mods.download', [$primaryCategory, $mod]) }}">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-pink-600 text-white font-semibold rounded-xl shadow hover:bg-pink-700 transition">
                            <i class="fa-solid fa-download mr-2"></i>Letöltés most
                        </button>
                    </form>

                    @php
                        $canManageMod = auth()->check() && auth()->id() === $mod->user_id;
                        $isPinned = $canManageMod ? auth()->user()->pinned_mod_id === $mod->id : false;
                    @endphp

                    @if ($canManageMod)
                        <button onclick="togglePinMod({{ $mod->id }}, {{ json_encode($isPinned) }})" id="pin-mod-btn" class="w-full inline-flex items-center justify-center px-4 py-2 {{ $isPinned ? 'bg-purple-600 hover:bg-purple-700' : 'bg-gray-600 hover:bg-gray-700' }} text-white text-sm font-semibold rounded-lg shadow transition">
                            <i class="fas fa-thumbtack mr-2 {{ $isPinned ? '' : 'rotate-45' }}"></i>
                            <span id="pin-mod-text">{{ $isPinned ? 'Unpin from Profile' : 'Pin to Profile' }}</span>
                        </button>
                    @endif

                    <div class="text-sm text-gray-500 space-y-1">
                        <p><strong>Verzió:</strong> {{ $metaDetails['version'] ?? '—' }}</p>
                        <p><strong>Fájlméret:</strong> {{ $metaDetails['file_size'] ?? '—' }}</p>
                        <p><strong>Feltöltve:</strong> {{ $metaDetails['uploaded_at'] ?? '—' }}</p>
                        <p><strong>Frissítve:</strong> {{ $metaDetails['updated_at'] ?? '—' }}</p>
                    </div>
                </div>

                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Kapcsolódó modok</h2>
                    <ul class="space-y-3 text-sm text-gray-600">
                        @forelse ($relatedMods as $related)
                            <li class="border-b border-gray-200 pb-3 last:border-0 last:pb-0">
                                <a href="{{ route('mods.show', [$related->primary_category, $related]) }}" class="font-semibold text-gray-900 hover:text-pink-600">{{ $related->title }}</a>
                                <span class="block text-xs text-gray-400 mt-1">{{ $related->category_names }}</span>
                            </li>
                        @empty
                            <li class="text-xs text-gray-400">Nem találtunk kapcsolódó modokat.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
<script>
async function togglePinMod(modId, isPinned) {
    const btn = document.getElementById('pin-mod-btn');
    const text = document.getElementById('pin-mod-text');
    const icon = btn.querySelector('i');

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        let url, method;
        if (isPinned) {
            // Unpin
            url = '/profile/pin-mod';
            method = 'DELETE';
        } else {
            // Pin
            url = `/profile/pin-mod/${modId}`;
            method = 'POST';
        }

        const response = await fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            // Update button state
            if (isPinned) {
                // Was pinned, now unpinned
                btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                btn.classList.add('bg-gray-600', 'hover:bg-gray-700');
                icon.classList.add('rotate-45');
                text.textContent = 'Pin to Profile';
                btn.setAttribute('onclick', `togglePinMod(${modId}, false)`);
            } else {
                // Was unpinned, now pinned
                btn.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
                icon.classList.remove('rotate-45');
                text.textContent = 'Unpin from Profile';
                btn.setAttribute('onclick', `togglePinMod(${modId}, true)`);
            }

            // Show success message
            const message = document.createElement('div');
            message.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            message.textContent = data.message;
            document.body.appendChild(message);
            setTimeout(() => message.remove(), 3000);
        } else {
            alert(data.message || 'Failed to update pinned mod');
        }
    } catch (error) {
        console.error('Error toggling pin:', error);
        alert('Failed to update pinned mod. Please try again.');
    }
}
</script>
@endpush

