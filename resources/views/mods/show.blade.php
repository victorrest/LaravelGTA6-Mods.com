@extends('layouts.app', ['title' => $mod->title])

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5/dist/photoswipe.css">
@endpush

@section('content')
    @php
        $mediaImages = collect($galleryImages);
        $primaryImage = $mediaImages->first();
        $secondaryImages = $mediaImages->slice(1);
        $videoItems = $videos->map(function ($video) {
            return [
                'type' => 'video',
                'title' => $video->title,
                'thumbnail' => $video->thumbnailUrl() ?? 'https://placehold.co/640x360/111827/ffffff?text=Video',
                'source' => 'https://www.youtube.com/embed/' . $video->external_id . '?rel=0&autoplay=1',
                'channel' => $video->channel_title,
                'duration' => $video->duration,
                'submitted_by' => $video->author->name,
            ];
        });

        $gridMedia = $videoItems->merge($secondaryImages->map(fn ($image) => [
            'type' => 'image',
            'title' => $mod->title,
            'thumbnail' => $image['src'],
            'source' => $image['src'],
            'alt' => $image['alt'],
        ]));
    @endphp

    <div class="space-y-10">
        <nav class="text-xs uppercase tracking-wide text-gray-400" aria-label="Breadcrumb">
            <ol class="flex items-center gap-2">
                @foreach ($breadcrumbs as $crumb)
                    <li class="flex items-center gap-2">
                        @if (!empty($crumb['url']))
                            <a href="{{ $crumb['url'] }}" class="text-gray-500 hover:text-pink-500">{{ $crumb['label'] }}</a>
                        @else
                            <span class="text-gray-700">{{ $crumb['label'] }}</span>
                        @endif
                        @unless($loop->last)
                            <span class="text-gray-400">/</span>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </nav>

        <article class="overflow-hidden rounded-3xl bg-gray-900 text-white shadow-xl">
            <div class="relative">
                <img src="{{ $primaryImage['src'] ?? $mod->hero_image_url }}" alt="{{ $primaryImage['alt'] ?? $mod->title }}" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/60 to-black/40"></div>
                <div class="relative z-10 flex flex-col gap-8 p-6 md:p-10 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl space-y-4">
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-pink-200">
                            {{ $primaryCategory?->name ?? 'Mod' }}
                        </div>
                        <h1 class="text-3xl font-bold leading-tight md:text-4xl">{{ $mod->title }}
                            @if (!empty($metaDetails['version']))
                                <span class="ml-2 text-lg text-pink-200">v{{ $metaDetails['version'] }}</span>
                            @endif
                        </h1>
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-200">
                            <span class="inline-flex items-center gap-2">
                                <i class="fa-solid fa-user"></i>
                                <a href="{{ route('author.profile', $mod->author->name) }}" class="hover:text-pink-300">{{ $mod->author->name }}</a>
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <i class="fa-solid fa-calendar"></i>
                                {{ $metaDetails['uploaded_at'] ?? '—' }}
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <i class="fa-solid fa-rotate"></i>
                                {{ $metaDetails['updated_at'] ?? '—' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col items-stretch gap-4 lg:items-end">
                        <form method="POST" action="{{ route('mods.download', [$primaryCategory, $mod]) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-3 rounded-full bg-pink-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-pink-600/30 transition hover:bg-pink-500">
                                <i class="fa-solid fa-download text-lg"></i>
                                Letöltés
                            </button>
                        </form>
                        <div class="grid grid-cols-3 gap-4 text-center text-sm">
                            <div class="rounded-2xl bg-white/10 px-4 py-3">
                                <div class="text-xs uppercase text-pink-100">Letöltések</div>
                                <div class="text-lg font-semibold">{{ $downloadFormatted }}</div>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-4 py-3">
                                <div class="text-xs uppercase text-pink-100">Kedvelések</div>
                                <div class="text-lg font-semibold">{{ $likesFormatted }}</div>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-4 py-3">
                                <div class="text-xs uppercase text-pink-100">Értékelés</div>
                                <div class="text-lg font-semibold">{{ $ratingValue ? number_format($ratingValue, 1) : '—' }}/5</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <div class="grid gap-8 lg:grid-cols-[2fr,1fr]">
            <div class="space-y-8">
                <section class="rounded-3xl border border-gray-200 bg-white/80 p-6 shadow-sm backdrop-blur" id="mod-media">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Galéria &amp; videók</h2>
                            <p class="text-sm text-gray-500">Lapozz a közösség által beküldött képek és videók között.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500">{{ $mediaImages->count() + $videoCount }} média</span>
                            @auth
                                <button type="button" class="inline-flex items-center gap-2 rounded-full bg-gray-900 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-gray-700" data-open-video-modal>
                                    <i class="fa-solid fa-video"></i> Videó beküldése
                                </button>
                            @endauth
                        </div>
                    </div>

                    <div id="mod-media-gallery" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @if ($primaryImage)
                            <a href="{{ $primaryImage['src'] }}" data-pswp-item data-pswp-type="image" data-caption="{{ $mod->title }}" data-pswp-width="1600" data-pswp-height="900" class="group relative overflow-hidden rounded-2xl border border-gray-200">
                                <img src="{{ $primaryImage['src'] }}" alt="{{ $primaryImage['alt'] }}" class="h-48 w-full object-cover transition duration-300 group-hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 transition group-hover:opacity-100"></div>
                                <div class="absolute bottom-3 left-3 text-xs font-semibold uppercase tracking-wide text-white">Fő kép</div>
                            </a>
                        @endif

                        @foreach ($gridMedia as $index => $item)
                            <a href="{{ $item['source'] }}"
                               data-pswp-item
                               data-pswp-type="{{ $item['type'] === 'video' ? 'iframe' : 'image' }}"
                               data-caption="{{ $item['type'] === 'video' ? $item['title'] . ' • ' . ($item['channel'] ?? 'YouTube') . ' • Beküldte: ' . ($item['submitted_by'] ?? 'Közösség') : $item['title'] }}"
                               data-pswp-width="{{ $item['type'] === 'video' ? 1280 : 1600 }}"
                               data-pswp-height="{{ $item['type'] === 'video' ? 720 : 900 }}"
                               class="media-card group relative hidden overflow-hidden rounded-2xl border border-gray-200"
                               data-index="{{ $index }}">
                                <img src="{{ $item['thumbnail'] }}" alt="{{ $item['title'] }}" class="h-48 w-full object-cover transition duration-300 group-hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                                <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between text-xs text-white">
                                    <span class="font-semibold">{{ $item['title'] }}</span>
                                    @if ($item['type'] === 'video')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-white/20 px-2 py-1 text-[10px] uppercase"><i class="fa-solid fa-play"></i> Videó</span>
                                    @endif
                                </div>
                                @if ($item['type'] === 'video')
                                    <div class="absolute top-3 left-3 rounded-full bg-black/60 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">{{ $item['channel'] }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    @if ($gridMedia->count() > 6)
                        <div class="mt-4 flex justify-center">
                            <button type="button" class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-600 transition hover:border-pink-400 hover:text-pink-600" data-gallery-load-more>
                                <i class="fa-solid fa-images"></i>
                                További média betöltése
                            </button>
                        </div>
                    @endif
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white/80 p-6 shadow-sm backdrop-blur" id="mod-description">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900">Leírás</h2>
                        <span class="text-xs uppercase tracking-wide text-gray-400">{{ number_format($ratingCount) }} értékelés</span>
                    </div>
                    <div class="mt-4 space-y-6">
                        <div class="editorjs-content">{!! $mod->description_html !!}</div>
                        <div class="border-t border-gray-200 pt-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Címkék</h3>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($mod->categories as $category)
                                    <a href="{{ route('mods.index', ['category' => $category->slug]) }}" class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-pink-100 hover:text-pink-600">#{{ $category->name }}</a>
                                @empty
                                    <span class="text-xs text-gray-400">Nincsenek címkék megadva.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white/80 p-6 shadow-sm backdrop-blur" id="mod-comments">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900">Hozzászólások</h2>
                        <span class="text-sm text-gray-500">{{ $mod->comments_count }} hozzászólás</span>
                    </div>
                    <div class="mt-4 space-y-4">
                        @forelse ($comments as $comment)
                            <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                                <header class="flex items-center justify-between text-sm text-gray-500">
                                    <div class="font-semibold text-gray-800">{{ $comment->author->name }}</div>
                                    <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->diffForHumans() }}</time>
                                </header>
                                <p class="mt-3 text-sm text-gray-700">{{ $comment->body }}</p>
                            </article>
                        @empty
                            <p class="text-sm text-gray-500">Még nincsenek hozzászólások. Légy te az első!</p>
                        @endforelse
                    </div>
                    <div class="mt-6">
                        @auth
                            <form method="POST" action="{{ route('mods.comment', [$primaryCategory, $mod]) }}" class="space-y-3">
                                @csrf
                                <textarea name="body" rows="4" class="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-pink-400 focus:outline-none focus:ring-2 focus:ring-pink-100" placeholder="Írd meg a véleményed..."></textarea>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-pink-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-pink-500">
                                    <i class="fa-solid fa-paper-plane"></i> Hozzászólás küldése
                                </button>
                            </form>
                        @else
                            <p class="text-sm text-gray-500">A hozzászóláshoz kérjük <a href="{{ route('login') }}" class="text-pink-600 hover:text-pink-500">jelentkezz be</a>.</p>
                        @endauth
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-gray-200 bg-white/80 p-6 shadow-sm backdrop-blur">
                    <h2 class="text-lg font-semibold text-gray-900">Értékeld a modot</h2>
                    <p class="text-sm text-gray-500">Az értékelés segít a többi játékosnak.</p>
                    @auth
                        <form method="POST" action="{{ route('mods.rate', [$primaryCategory, $mod]) }}" class="mt-4 space-y-3" data-rating-form data-rating-initial="{{ $userRating ?? 0 }}">
                            @csrf
                            <input type="hidden" name="rating" value="{{ $userRating ?? '' }}" data-rating-input>
                            <div class="flex gap-1 text-2xl text-yellow-400" data-rating-stars>
                                @for ($i = 1; $i <= 5; $i++)
                                    <button type="button" class="rating-star rounded-full p-1 transition hover:scale-110" data-rating-value="{{ $i }}">
                                        <i class="fa-star {{ $userRating && $i <= $userRating ? 'fa-solid text-yellow-400' : 'fa-regular text-gray-300' }}"></i>
                                    </button>
                                @endfor
                            </div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-gray-900 px-4 py-2 text-xs font-semibold text-white shadow hover:bg-gray-700" data-rating-submit disabled>
                                <i class="fa-solid fa-star"></i> Értékelés mentése
                            </button>
                            <p class="text-xs text-gray-500" data-rating-feedback>{{ $userRating ? 'Jelenlegi értékelésed: ' . $userRating . '/5' : 'Kattints egy csillagra az értékelés leadásához.' }}</p>
                        </form>
                    @else
                        <p class="mt-4 text-sm text-gray-500">Értékeléshez <a href="{{ route('login') }}" class="text-pink-600 hover:text-pink-500">jelentkezz be</a>.</p>
                    @endauth
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white/80 p-6 shadow-sm backdrop-blur">
                    <h2 class="text-lg font-semibold text-gray-900">Mod információ</h2>
                    <dl class="mt-4 space-y-3 text-sm text-gray-600">
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">Verzió</dt>
                            <dd class="font-semibold text-gray-900">{{ $metaDetails['version'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">Fájlméret</dt>
                            <dd class="font-semibold text-gray-900">{{ $metaDetails['file_size'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">Első feltöltés</dt>
                            <dd class="font-semibold text-gray-900">{{ $metaDetails['uploaded_at'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">Utolsó frissítés</dt>
                            <dd class="font-semibold text-gray-900">{{ $metaDetails['updated_at'] ?? '—' }}</dd>
                        </div>
                    </dl>
                    @auth
                        <div class="mt-4 flex items-center justify-between gap-3 text-sm text-gray-500">
                            <a href="{{ route('mods.edit', [$primaryCategory, $mod]) }}" class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:border-pink-400 hover:text-pink-600">
                                <i class="fa-solid fa-pen"></i> Mod szerkesztése
                            </a>
                            @if ($canManagePin)
                                <button type="button"
                                        id="pin-mod-btn"
                                        data-pin-url="{{ route('profile.mod.pin', $mod) }}"
                                        data-unpin-url="{{ route('profile.mod.unpin') }}"
                                        data-is-pinned="{{ $isPinnedByOwner ? '1' : '0' }}"
                                        class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:border-purple-400 hover:text-purple-600">
                                    <i class="fas fa-thumbtack {{ $isPinnedByOwner ? '' : 'rotate-45' }}" data-pin-icon></i>
                                    <span data-pin-text>{{ $isPinnedByOwner ? 'Levétel a profilról' : 'Kitűzés a profilra' }}</span>
                                </button>
                            @endif
                        </div>
                    @endauth
                </section>

                <section class="rounded-3xl border border-gray-200 bg-white/80 p-6 shadow-sm backdrop-blur">
                    <h2 class="text-lg font-semibold text-gray-900">Kapcsolódó modok</h2>
                    <ul class="mt-4 space-y-3 text-sm text-gray-600">
                        @forelse ($relatedMods as $related)
                            <li class="rounded-2xl border border-gray-200 p-3 transition hover:border-pink-400 hover:bg-pink-50">
                                <a href="{{ route('mods.show', [$related->primary_category, $related]) }}" class="font-semibold text-gray-900 hover:text-pink-600">{{ $related->title }}</a>
                                <div class="text-xs text-gray-400">{{ $related->category_names }}</div>
                            </li>
                        @empty
                            <li class="text-xs text-gray-400">Nem találtunk kapcsolódó modokat.</li>
                        @endforelse
                    </ul>
                </section>
            </aside>
        </div>
    </div>

    @auth
        <div id="video-submit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-6" role="dialog" aria-modal="true" aria-labelledby="video-modal-title">
            <div class="relative w-full max-w-xl rounded-3xl bg-white p-6 shadow-xl">
                <button type="button" class="absolute right-5 top-5 text-gray-400 hover:text-gray-600" data-close-video-modal>
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
                <div class="space-y-4">
                    <header class="space-y-2">
                        <h2 id="video-modal-title" class="text-xl font-semibold text-gray-900">YouTube videó beküldése</h2>
                        <p class="text-sm text-gray-500">Másold be a YouTube linket. A rendszer automatikusan letölti az előnézeti képet és a meta adatokat.</p>
                    </header>
                    <form method="POST" action="{{ route('mods.videos.store', [$primaryCategory, $mod]) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="video_url" class="block text-sm font-medium text-gray-700">YouTube URL</label>
                            <input type="url" id="video_url" name="video_url" required placeholder="https://www.youtube.com/watch?v=..." class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-pink-400 focus:outline-none focus:ring-2 focus:ring-pink-100">
                        </div>
                        <div>
                            <label for="video_note" class="block text-sm font-medium text-gray-700">Megjegyzés (opcionális)</label>
                            <textarea id="video_note" name="note" rows="3" class="mt-1 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm shadow-sm focus:border-pink-400 focus:outline-none focus:ring-2 focus:ring-pink-100" placeholder="Milyen részletet mutat be a videó?"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:border-gray-400" data-close-video-modal>Bezárás</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-pink-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-pink-500">
                                <i class="fa-solid fa-paper-plane"></i> Beküldés moderációra
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endauth
@endsection

@push('scripts')
    <script type="module">
        import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5/dist/photoswipe-lightbox.esm.js';
        const lightbox = new PhotoSwipeLightbox({
            gallery: '#mod-media-gallery',
            children: 'a[data-pswp-item]',
            pswpModule: () => import('https://unpkg.com/photoswipe@5/dist/photoswipe.esm.js'),
        });

        lightbox.on('contentLoad', (e) => {
            const { content, data } = e;
            if (data.type === 'iframe') {
                const iframe = document.createElement('iframe');
                iframe.src = data.src;
                iframe.width = data.width;
                iframe.height = data.height;
                iframe.allow = 'autoplay; fullscreen';
                iframe.className = 'rounded-xl';
                content.element = iframe;
            }
        });

        lightbox.init();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mediaCards = Array.from(document.querySelectorAll('#mod-media-gallery .media-card'));
            const loadMoreBtn = document.querySelector('[data-gallery-load-more]');
            const initialVisible = 6;

            mediaCards.forEach((card, index) => {
                if (index < initialVisible) {
                    card.classList.remove('hidden');
                }
            });

            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', () => {
                    mediaCards.forEach((card) => card.classList.remove('hidden'));
                    loadMoreBtn.remove();
                });
            }

            const openButtons = document.querySelectorAll('[data-open-video-modal]');
            const modal = document.getElementById('video-submit-modal');
            const closeButtons = document.querySelectorAll('[data-close-video-modal]');

            openButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    modal?.classList.remove('hidden');
                    modal?.classList.add('flex');
                });
            });

            closeButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    modal?.classList.add('hidden');
                    modal?.classList.remove('flex');
                });
            });

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            });
        });
    </script>
    <!-- Pin toggle behavior handled by resources/js/modules/pin-mod.js -->
@endpush
