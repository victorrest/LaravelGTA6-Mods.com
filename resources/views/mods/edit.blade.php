@extends('layouts.app', ['title' => 'Update Mod'])

@php($previewPlaceholder = $mod->hero_image_url)

@section('content')
    <section class="max-w-6xl mx-auto space-y-8" id="mod-edit-root">
        <header class="text-center space-y-3">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Update {{ $mod->title }}</h1>
            <p class="text-sm md:text-base text-gray-500 max-w-2xl mx-auto">
                Küldd be a mód frissítését új verziószámmal, képekkel és fájlokkal. A jelenlegi verzió addig marad elérhető, amíg a moderátorok jóvá nem hagyják az új kiadást.
            </p>
        </header>

        @if ($pendingRevision)
            <div class="rounded-2xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 flex items-center justify-between">
                <div>
                    <strong class="font-semibold">Folyamatban lévő frissítés:</strong>
                    v{{ $pendingRevision->version }} – beküldve {{ $pendingRevision->created_at->diffForHumans() }}
                </div>
                <div class="text-xs text-yellow-700">A módosítás addig nem publikálódik, amíg jóvá nem hagyjuk.</div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">
            <form id="mod-update-form" method="POST" action="{{ route('mods.update', $mod) }}" enctype="multipart/form-data" class="space-y-8">
                @include('components.validation-errors')
                @csrf
                @method('PUT')

                <div class="card p-6 md:p-8 space-y-6">
                    <h2 class="text-lg font-semibold text-gray-900">General details</h2>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="form-label" for="title">Mod title</label>
                            <input id="title" name="title" type="text" value="{{ old('title', $mod->title) }}" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label" for="version">Version</label>
                            <input id="version" name="version" type="text" value="{{ old('version', $mod->version) }}" class="form-input" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="category_ids">Categories</label>
                            <select id="category_ids" name="category_ids[]" multiple class="form-multiselect" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($mod->categories->pluck('id')->contains($category->id) || collect(old('category_ids'))->contains($category->id))>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <p class="form-help">Hold Ctrl or Cmd to select every category that applies.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="download_url">Download URL</label>
                            <input id="download_url" name="download_url" type="url" value="{{ old('download_url', $mod->download_url) }}" class="form-input" placeholder="https://">
                            <p class="form-help">Leave empty if you rely on the uploaded archive below.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="changelog">Changelog</label>
                            <textarea id="changelog" name="changelog" rows="4" class="form-textarea" placeholder="Írd le röviden, mi változott.">{{ old('changelog') }}</textarea>
                            <p class="form-help">A moderátorok ez alapján döntik el, hogy milyen gyorsan kerül jóváhagyásra a frissítés.</p>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="description">Description</label>
                        <x-editorjs
                            name="description"
                            id="description"
                            :value="old('description', $mod->description_raw)"
                            :plain-text="\App\Support\EditorJs::toPlainText(old('description', $mod->description_raw))"
                            placeholder="Describe features, installation steps and credits"
                            required
                        />
                    </div>
                </div>

                <div class="card p-6 md:p-8 space-y-6">
                    <h2 class="text-lg font-semibold text-gray-900">Visual assets</h2>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <label class="form-label">Hero image</label>
                            <div id="hero-dropzone" class="relative flex h-48 w-full cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-pink-300 bg-white text-center transition hover:border-pink-500 hover:bg-pink-50">
                                <input id="hero_image" name="hero_image" type="file" accept="image/*" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                                <div class="space-y-2 px-6">
                                    <i class="fa-regular fa-image text-2xl text-pink-500"></i>
                                    <p class="text-sm font-semibold text-gray-700">Drop or click to upload</p>
                                    <p class="text-xs text-gray-500">Current image will remain unless you replace it.</p>
                                </div>
                                <p id="hero-upload-status" class="pointer-events-none mt-3 text-xs font-semibold text-pink-600"></p>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Current hero image</label>
                            <div class="h-48 overflow-hidden rounded-2xl border border-gray-200 bg-gray-100">
                                <img src="{{ $mod->hero_image_url }}" alt="{{ $mod->title }} hero" class="h-full w-full object-cover">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Existing gallery</label>
                        @if ($mod->galleryImages->isEmpty())
                            <p class="text-sm text-gray-500">No additional screenshots yet. Add some below!</p>
                        @else
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($mod->galleryImages as $image)
                                    <label class="relative block overflow-hidden rounded-2xl border border-gray-200 shadow-sm">
                                        <img src="{{ $image->url }}" alt="Gallery image" class="h-40 w-full object-cover">
                                        <input type="checkbox" name="remove_gallery_image_ids[]" value="{{ $image->id }}" class="absolute top-3 right-3 h-4 w-4 rounded border-gray-300 text-pink-500 focus:ring-pink-400">
                                        <span class="absolute bottom-3 left-3 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">Remove</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="form-label">Upload new screenshots</label>
                        <div id="gallery-dropzone" data-existing-count="{{ $mod->galleryImages->count() }}" class="relative flex min-h-[220px] w-full cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-pink-300 bg-white text-center transition hover:border-pink-500 hover:bg-pink-50">
                            <input id="gallery_images" name="gallery_images[]" type="file" accept="image/*" multiple class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                            <div class="space-y-2 px-6">
                                <i class="fa-solid fa-images text-2xl text-pink-500"></i>
                                <p class="text-sm font-semibold text-gray-700">Bulk upload additional screenshots</p>
                                <p class="text-xs text-gray-500">Add up to 12 JPG/PNG/WebP images to showcase your mod.</p>
                            </div>
                        </div>
                        <div id="gallery-previews" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
                    </div>
                </div>

                <div class="card p-6 md:p-8 space-y-6">
                    <h2 class="text-lg font-semibold text-gray-900">Files</h2>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <label class="form-label" for="file_size">File size (MB)</label>
                            <input id="file_size" name="file_size" type="number" step="0.01" min="0" value="{{ old('file_size', $mod->file_size) }}" class="form-input" placeholder="850">
                            <p class="form-help">We will auto-fill this when possible using your uploaded archive.</p>
                        </div>
                        <div>
                            <label class="form-label" for="mod_file">Upload mod archive</label>
                            <div class="relative flex h-48 cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-indigo-300 bg-indigo-50/60 text-center transition hover:border-indigo-400 hover:bg-indigo-100">
                                <input id="mod_file" name="mod_file" type="file" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                                <div class="space-y-2 px-6">
                                    <i class="fa-solid fa-file-zipper text-2xl text-indigo-500"></i>
                                    <p class="text-sm font-semibold text-gray-700">Drop ZIP/RAR/7Z here</p>
                                    <p class="text-xs text-gray-500">Max 200 MB. Existing archive remains unless replaced.</p>
                                    <p id="mod-file-label" class="text-xs text-indigo-500">{{ $mod->file_path ? basename($mod->file_path) : '' }}</p>
                                </div>
                            </div>
                            @if ($mod->file_path)
                                <p class="mt-2 text-xs text-gray-500">Current archive: <span class="font-semibold">{{ \Illuminate\Support\Str::limit($mod->file_path, 48) }}</span></p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('mods.show', [$category, $mod]) }}" class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-pink-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-pink-700 transition">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save changes
                        </button>
                    </div>
                </div>
            </form>

            <aside class="card p-6 md:p-7 space-y-6 lg:sticky lg:top-24" aria-live="polite">
                <div class="rounded-2xl overflow-hidden shadow-inner bg-gray-900 text-white">
                    <div id="preview-hero" class="relative h-48 bg-cover bg-center" style="background-image: url('{{ $previewPlaceholder }}');">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-gray-900/40 to-transparent"></div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <div class="flex items-center justify-between text-xs uppercase tracking-widest text-pink-200/80">
                                <span>Preview card</span>
                                <span id="preview-version" class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold">v{{ $mod->version }}</span>
                            </div>
                            <h2 id="preview-title" class="mt-2 text-2xl font-bold leading-tight">{{ $mod->title }}</h2>
                        </div>
                    </div>
                    <div class="space-y-4 bg-gray-900 px-5 py-6 text-sm">
                        <div>
                            <p class="text-gray-400 text-xs uppercase">Categories</p>
                            <p id="preview-categories" class="text-gray-100 font-medium">{{ $mod->category_names }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase">Download</p>
                            <p id="preview-download" class="text-gray-100 font-medium">{{ $mod->file_path ? 'Direct download enabled' : 'External link selected' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase">Screenshots</p>
                            <div id="preview-gallery" class="mt-2 grid grid-cols-3 gap-2">
                                @forelse ($mod->galleryImages->take(3) as $image)
                                    <div class="h-16 rounded-lg bg-cover bg-center" style="background-image: url('{{ $image->url }}');"></div>
                                @empty
                                    <div class="col-span-3 text-xs text-gray-400">No gallery images yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-box text-sm leading-relaxed text-gray-600">
                    <h3 class="text-base font-semibold text-gray-800">Moderation note</h3>
                    <p class="mt-1">Updates may require re-approval depending on their scope. Keep your changelog handy for quicker reviews.</p>
                </div>
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('mod-update-form');
            const titleInput = document.getElementById('title');
            const versionInput = document.getElementById('version');
            const categoriesSelect = document.getElementById('category_ids');
            const downloadUrlInput = document.getElementById('download_url');
            const fileSizeInput = document.getElementById('file_size');

            const heroInput = document.getElementById('hero_image');
            const heroStatusLabel = document.getElementById('hero-upload-status');
            const galleryInput = document.getElementById('gallery_images');
            const galleryPreviews = document.getElementById('gallery-previews');
            const modFileInput = document.getElementById('mod_file');
            const modFileLabel = document.getElementById('mod-file-label');

            const previewHero = document.getElementById('preview-hero');
            const previewTitle = document.getElementById('preview-title');
            const previewVersion = document.getElementById('preview-version');
            const previewCategories = document.getElementById('preview-categories');
            const previewDownload = document.getElementById('preview-download');
            let galleryUrls = [];

            const updatePreviewCategories = () => {
                const selected = Array.from(categoriesSelect.selectedOptions).map((option) => option.textContent.trim());
                previewCategories.textContent = selected.length ? selected.join(', ') : 'None selected';
            };

            const updatePreviewDownload = () => {
                if (downloadUrlInput.value.trim()) {
                    previewDownload.textContent = 'External link provided';
                } else if (modFileInput.files.length) {
                    previewDownload.textContent = 'Direct download enabled';
                } else {
                    previewDownload.textContent = 'No download source selected yet';
                }
            };

            const updatePreviewHero = (file) => {
                if (file) {
                    const url = URL.createObjectURL(file);
                    previewHero.style.backgroundImage = `url('${url}')`;
                    previewHero.dataset.tempUrl && URL.revokeObjectURL(previewHero.dataset.tempUrl);
                    previewHero.dataset.tempUrl = url;
                }
            };

            const renderGalleryPreviews = (files) => {
                galleryPreviews.innerHTML = '';
                galleryUrls.forEach((url) => URL.revokeObjectURL(url));
                galleryUrls = [];

                Array.from(files).slice(0, 12).forEach((file) => {
                    const url = URL.createObjectURL(file);
                    galleryUrls.push(url);
                    const item = document.createElement('div');
                    item.className = 'h-20 rounded-xl bg-cover bg-center';
                    item.style.backgroundImage = `url('${url}')`;
                    galleryPreviews.appendChild(item);
                });

                if (!files.length) {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'col-span-3 text-sm text-gray-500';
                    placeholder.textContent = 'No new screenshots selected.';
                    galleryPreviews.appendChild(placeholder);
                }
            };

            titleInput.addEventListener('input', () => {
                previewTitle.textContent = titleInput.value.trim() || previewTitle.dataset.fallback;
            });

            versionInput.addEventListener('input', () => {
                previewVersion.textContent = `v${versionInput.value.trim() || previewVersion.dataset.fallback}`;
            });

            categoriesSelect.addEventListener('change', updatePreviewCategories);
            downloadUrlInput.addEventListener('input', updatePreviewDownload);

            heroInput.addEventListener('change', () => {
                if (heroInput.files.length) {
                    const file = heroInput.files[0];
                    heroStatusLabel.textContent = file.name;
                    updatePreviewHero(file);
                } else {
                    heroStatusLabel.textContent = '';
                }
            });

            galleryInput.addEventListener('change', () => {
                renderGalleryPreviews(galleryInput.files);
            });

            modFileInput.addEventListener('change', () => {
                if (modFileInput.files.length) {
                    const file = modFileInput.files[0];
                    const sizeMb = (file.size / 1024 / 1024).toFixed(2);
                    modFileLabel.textContent = `${file.name} (${sizeMb} MB)`;
                    fileSizeInput.value = sizeMb;
                } else {
                    modFileLabel.textContent = '';
                }
                updatePreviewDownload();
            });

            form.addEventListener('submit', () => {
                galleryUrls.forEach((url) => URL.revokeObjectURL(url));
                if (previewHero.dataset.tempUrl) {
                    URL.revokeObjectURL(previewHero.dataset.tempUrl);
                }
            });

            // initial state
            previewTitle.dataset.fallback = previewTitle.textContent;
            previewVersion.dataset.fallback = previewVersion.textContent.replace(/^v/, '');
            updatePreviewCategories();
            updatePreviewDownload();
            renderGalleryPreviews(galleryInput.files || []);
        });
    </script>
@endpush
