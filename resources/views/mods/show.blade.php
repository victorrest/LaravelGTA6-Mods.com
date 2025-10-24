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
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <i class="fa-solid fa-images text-pink-600"></i>
                                <span>Képgaléria</span>
                            </h2>
                            <span class="text-sm text-gray-500 font-medium">{{ count($galleryImages) }} kép</span>
                        </div>
                        <x-mod.gallery :images="$galleryImages" :modTitle="$mod->title" />
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

                {{-- Video Gallery --}}
                @if ($videos->isNotEmpty())
                    <div class="card p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <i class="fa-brands fa-youtube text-red-600"></i>
                                <span>Videógaléria</span>
                            </h2>
                            <span class="text-sm text-gray-500 font-medium">{{ $videos->count() }} videó</span>
                        </div>
                        <x-mod.video-gallery :videos="$videos" :modId="$mod->id" :modTitle="$mod->title" :canManage="$canManageMod" />
                    </div>
                @elseif(auth()->check())
                    <div class="card p-6 text-center">
                        <div class="py-8">
                            <i class="fa-brands fa-youtube text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Még nincsenek videók</h3>
                            <p class="text-gray-600 mb-4">Légy az első, aki videót oszt meg erről a modról!</p>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all"
                                data-modal-trigger="video-submit-modal"
                            >
                                <i class="fa-brands fa-youtube text-xl"></i>
                                <span>YouTube videó hozzáadása</span>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Version History / Changelog --}}
                @if($versions->isNotEmpty())
                    <div class="card p-6 space-y-4">
                        <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2 border-b pb-3">
                            <i class="fa-solid fa-clock-rotate-left text-pink-600"></i>
                            <span>Verziótörténet</span>
                        </h2>
                        <div class="space-y-3">
                            @foreach($versions as $version)
                                <div class="p-4 bg-gray-50 rounded-lg border {{ $version->is_current ? 'border-pink-500 bg-pink-50' : 'border-gray-200' }}">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="font-bold text-gray-900">
                                                Verzió {{ $version->version_number }}
                                                @if($version->is_current)
                                                    <span class="ml-2 px-2 py-1 bg-pink-600 text-white text-xs rounded-full">Aktuális</span>
                                                @endif
                                            </h3>
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ $version->created_at->format('Y. m. d.') }}
                                                @if($version->file_size_label)
                                                    · {{ $version->file_size_label }}
                                                @endif
                                            </p>
                                        </div>
                                        @if($version->download_url || $version->file_url)
                                            <a
                                                href="{{ $version->download_url ?: $version->file_url }}"
                                                class="px-4 py-2 bg-pink-600 text-white text-sm font-semibold rounded-lg hover:bg-pink-700 transition"
                                            >
                                                <i class="fa-solid fa-download mr-1"></i>Letöltés
                                            </a>
                                        @endif
                                    </div>
                                    @if($version->changelog)
                                        <div class="mt-3 text-sm text-gray-700">
                                            <strong>Változások:</strong>
                                            <p class="mt-1 whitespace-pre-line">{{ $version->changelog }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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

                    @if ($canManagePin)
                        <button
                            type="button"
                            id="pin-mod-btn"
                            data-pin-url="{{ route('profile.mod.pin', $mod) }}"
                            data-unpin-url="{{ route('profile.mod.unpin') }}"
                            data-is-pinned="{{ $isPinnedByOwner ? '1' : '0' }}"
                            class="w-full inline-flex items-center justify-center px-4 py-2 {{ $isPinnedByOwner ? 'bg-purple-600 hover:bg-purple-700' : 'bg-gray-600 hover:bg-gray-700' }} text-white text-sm font-semibold rounded-lg shadow transition"
                        >
                            <i class="fas fa-thumbtack mr-2 {{ $isPinnedByOwner ? '' : 'rotate-45' }}" data-pin-icon></i>
                            <span data-pin-text>{{ $isPinnedByOwner ? 'Unpin from Profile' : 'Pin to Profile' }}</span>
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

{{-- Video Submit Modal --}}
@auth
    <x-mod.video-submit-modal :modId="$mod->id" />
@endauth

@push('scripts')
<script>
// Rating stars interactivity
document.querySelectorAll('[data-rating-form]').forEach(form => {
    const stars = form.querySelectorAll('[data-rating-value]');
    const input = form.querySelector('[data-rating-input]');
    const submitBtn = form.querySelector('[data-rating-submit]');

    stars.forEach(star => {
        star.addEventListener('click', function() {
            const value = this.dataset.ratingValue;
            input.value = value;
            submitBtn.disabled = false;

            // Update star display
            stars.forEach((s, index) => {
                const icon = s.querySelector('i');
                if (index < value) {
                    icon.classList.remove('fa-regular', 'text-gray-300');
                    icon.classList.add('fa-solid', 'text-amber-400');
                } else {
                    icon.classList.remove('fa-solid', 'text-amber-400');
                    icon.classList.add('fa-regular', 'text-gray-300');
                }
            });
        });
    });
});
</script>
<!-- Pin toggle behavior handled by resources/js/modules/pin-mod.js -->
@endpush

