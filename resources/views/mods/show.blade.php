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
                <form method="POST" action="{{ route('mods.download', $mod) }}" class="w-full md:w-auto">
                    @csrf
                    <button type="submit" class="btn-download font-bold py-3 px-5 rounded-[12px] transition flex items-center justify-center w-full md:w-auto bg-pink-600 text-white hover:bg-pink-700 shadow">
                        <i class="fa-solid fa-download mr-2"></i>
                        <span>Download</span>
                    </button>
                </form>
                <div class="text-sm text-gray-500 md:text-right">
                    <div class="flex items-center justify-start md:justify-end gap-1 text-xl font-bold text-gray-900">
                        <span>{{ $ratingDisplay }}</span>
                        <span class="text-base font-normal text-gray-500">/ 5</span>
                    </div>
                    <div class="flex justify-start md:justify-end gap-1 mt-1 text-lg">
                        @for ($i = 1; $i <= 5; $i++)
                            @php($isHalf = $ratingHasHalf && $i === $ratingFullStars + 1)
                            <i class="fa-solid {{ $i <= $ratingFullStars ? 'fa-star text-yellow-400' : ($isHalf ? 'fa-star-half-stroke text-yellow-400' : 'fa-star text-gray-300') }}"></i>
                        @endfor
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Közösségi értékelés</p>
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

                <div class="card overflow-hidden">
                    <div class="flex border-b border-gray-200 bg-gray-50">
                        @foreach (['description', 'comments', 'changelog'] as $tabKey)
                            @php
                                $isActive = $activeTab === $tabKey;
                                $activeClass = 'text-pink-600 border-pink-500 bg-white';
                                $inactiveClass = 'text-gray-600 border-transparent hover:text-pink-600';
                            @endphp
                            <a
                                href="{{ $tabUrl }}"
                                data-tab-target="tab-{{ $tabKey }}"
                                data-tab-active-class="{{ $activeClass }}"
                                data-tab-inactive-class="{{ $inactiveClass }}"
                                class="tab-trigger flex-1 sm:flex-none px-4 sm:px-6 py-3 text-sm font-semibold border-b-2 transition-colors {{ $isActive ? $activeClass : $inactiveClass }}"
                                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                                role="tab"
                            >
                                @switch($tabKey)
                                    @case('comments')
                                        Kommentek ({{ $mod->comments_count }})
                                        @break
                                    @case('changelog')
                                        Changelog
                                        @break
                                    @default
                                        Leírás
                                @endswitch
                            </a>
                        @endforeach
                    </div>
                    <div class="p-4 md:p-6 text-gray-700 leading-relaxed space-y-6">
                        <div id="tab-description" data-tab-section class="space-y-6 {{ $activeTab === 'description' ? '' : 'hidden' }}">
                            <div class="prose max-w-none text-gray-700">
                                {!! nl2br(e($mod->description)) !!}
                            </div>
                            <div class="pt-6 border-t border-gray-200 space-y-3">
                                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Címkék</h3>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($mod->categories as $category)
                                        <a href="{{ route('mods.index', ['category' => $category->slug]) }}" class="bg-gray-200 text-gray-700 text-xs font-semibold px-3 py-1 rounded-full hover:bg-gray-300 transition">{{ $category->name }}</a>
                                    @empty
                                        <span class="text-xs text-gray-400">Nincsenek kategóriák megadva.</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div id="tab-comments" data-tab-section class="space-y-5 {{ $activeTab === 'comments' ? '' : 'hidden' }}">
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-lg text-gray-900">Kommentek</h3>
                                <span class="text-sm text-gray-500">{{ $mod->comments_count }} összesen</span>
                            </div>
                            <div class="space-y-4">
                                @forelse ($comments as $comment)
                                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <p class="font-semibold text-gray-900">{{ $comment->author->name }}</p>
                                            <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-sm text-gray-700 mt-2">{{ $comment->body }}</p>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">Még nincsenek hozzászólások.</p>
                                @endforelse
                            </div>
                            @auth
                                <form method="POST" action="{{ route('mods.comment', $mod) }}" class="space-y-3">
                                    @csrf
                                    <textarea name="body" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-500" placeholder="Írd meg a véleményed"></textarea>
                                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-pink-600 text-white text-sm font-medium rounded-lg shadow hover:bg-pink-700 transition">Hozzászólás küldése</button>
                                </form>
                            @else
                                <p class="text-sm text-gray-500">A hozzászóláshoz kérjük <a href="{{ route('login') }}" class="text-pink-600">jelentkezz be</a>.</p>
                            @endauth
                        </div>

                        <div id="tab-changelog" data-tab-section class="space-y-3 {{ $activeTab === 'changelog' ? '' : 'hidden' }}">
                            <h3 class="font-bold text-lg text-gray-900">Verziótörténet (Changelog)</h3>
                            <p class="text-sm text-gray-500">A verziótörténet hamarosan elérhető lesz.</p>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Letöltés</h2>
                    <p class="text-sm text-gray-600 leading-relaxed">Ez a mod a közösség által lett feltöltve és folyamatosan karbantartva.</p>
                    <form method="POST" action="{{ route('mods.download', $mod) }}">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-pink-600 text-white font-semibold rounded-xl shadow hover:bg-pink-700 transition">
                            <i class="fa-solid fa-download mr-2"></i>Letöltés most
                        </button>
                    </form>
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
                                <a href="{{ route('mods.show', $related) }}" class="font-semibold text-gray-900 hover:text-pink-600">{{ $related->title }}</a>
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
        document.addEventListener('DOMContentLoaded', () => {
            const tabTriggers = document.querySelectorAll('[data-tab-target]');
            const tabSections = document.querySelectorAll('[data-tab-section]');

            if (!tabTriggers.length || !tabSections.length) {
                return;
            }

            const applyClassList = (element, classes, action) => {
                if (!classes) {
                    return;
                }

                classes.split(/\s+/).forEach((cls) => {
                    if (!cls) {
                        return;
                    }

                    if (action === 'add') {
                        element.classList.add(cls);
                    } else {
                        element.classList.remove(cls);
                    }
                });
            };

            const activateTab = (targetId) => {
                tabSections.forEach((section) => {
                    if (section.id === targetId) {
                        section.classList.remove('hidden');
                    } else {
                        section.classList.add('hidden');
                    }
                });

                tabTriggers.forEach((trigger) => {
                    const isActive = trigger.getAttribute('data-tab-target') === targetId;
                    const activeClasses = trigger.getAttribute('data-tab-active-class');
                    const inactiveClasses = trigger.getAttribute('data-tab-inactive-class');

                    if (isActive) {
                        applyClassList(trigger, inactiveClasses, 'remove');
                        applyClassList(trigger, activeClasses, 'add');
                        trigger.setAttribute('aria-selected', 'true');
                    } else {
                        applyClassList(trigger, activeClasses, 'remove');
                        applyClassList(trigger, inactiveClasses, 'add');
                        trigger.setAttribute('aria-selected', 'false');
                    }
                });
            };

            tabTriggers.forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    const targetId = trigger.getAttribute('data-tab-target');
                    if (targetId) {
                        activateTab(targetId);
                    }
                });
            });
        });
    </script>
@endpush
