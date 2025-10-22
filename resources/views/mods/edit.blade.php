@extends('layouts.app', ['title' => 'Update Mod'])

@php($previewPlaceholder = $mod->hero_image_url)

@section('content')
    <section class="max-w-6xl mx-auto space-y-8" id="mod-edit-root">
        <header class="text-center space-y-3">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Update {{ $mod->title }}</h1>
            <p class="text-sm md:text-base text-gray-500 max-w-2xl mx-auto">
                Refresh your listing with new screenshots, files or version information. Changes are saved as soon as they pass
                moderation.
            </p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">
            <form id="mod-update-form" method="POST" action="{{ route('mods.update', $mod) }}" enctype="multipart/form-data" class="space-y-8">
                @include('components.validation-errors')
                @csrf
                @method('PUT')
                <input type="hidden" name="mod_file_token" id="mod_file_token" value="{{ old('mod_file_token') }}">

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
                    </div>
                    <div>
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" rows="9" class="form-textarea" required>{{ old('description', $mod->description) }}</textarea>
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
                        <div id="gallery-dropzone" class="relative flex min-h-[220px] w-full cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-pink-300 bg-white text-center transition hover:border-pink-500 hover:bg-pink-50">
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
                            <input id="file_size" name="file_size" type="number" step="0.1" min="0" value="{{ old('file_size', $mod->file_size) }}" class="form-input" placeholder="850">
                            <p class="form-help">We will auto-fill this when possible using your uploaded archive.</p>
                        </div>
                        <div>
                            <label class="form-label" for="mod_file">Upload mod archive</label>
                            <div id="mod-file-dropzone" class="relative flex h-48 cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-indigo-300 bg-indigo-50/60 text-center transition hover:border-indigo-400 hover:bg-indigo-100">
                                <input id="mod_file" name="mod_file" type="file" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                                <div class="space-y-2 px-6">
                                    <i class="fa-solid fa-file-zipper text-2xl text-indigo-500"></i>
                                    <p class="text-sm font-semibold text-gray-700">Drop ZIP/RAR/7Z here</p>
                                    <p class="text-xs text-gray-500">Max 200 MB. Existing archive remains unless replaced.</p>
                                    <p id="mod-file-label" class="text-xs text-indigo-500">{{ $mod->file_path ? basename($mod->file_path) : '' }}</p>
                                </div>
                            </div>
                            <div id="mod-upload-progress" class="mt-3 hidden">
                                <div class="h-2 rounded-full bg-indigo-100 overflow-hidden">
                                    <div id="mod-upload-progress-bar" class="h-full w-0 bg-indigo-500 transition-all duration-300"></div>
                                </div>
                                <p id="mod-upload-progress-label" class="mt-2 text-xs font-semibold text-indigo-500"></p>
                            </div>
                            @if ($mod->file_path)
                                <p class="mt-2 text-xs text-gray-500">Current archive: <span class="font-semibold">{{ \Illuminate\Support\Str::limit($mod->file_path, 48) }}</span></p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('mods.show', $mod) }}" class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
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
            const previewHero = document.getElementById('preview-hero');
            const previewTitle = document.getElementById('preview-title');
            const previewVersion = document.getElementById('preview-version');
            const previewCategories = document.getElementById('preview-categories');
            const previewDownload = document.getElementById('preview-download');
            const previewGallery = document.getElementById('preview-gallery');

            const titleInput = document.getElementById('title');
            const versionInput = document.getElementById('version');
            const categoriesSelect = document.getElementById('category_ids');
            const downloadInput = document.getElementById('download_url');
            const modFileInput = document.getElementById('mod_file');
            const modFileLabel = document.getElementById('mod-file-label');
            const fileSizeInput = document.getElementById('file_size');
            const modFileTokenInput = document.getElementById('mod_file_token');
            const modUploadProgress = document.getElementById('mod-upload-progress');
            const modUploadProgressBar = document.getElementById('mod-upload-progress-bar');
            const modUploadProgressLabel = document.getElementById('mod-upload-progress-label');
            const modFileDropzone = document.getElementById('mod-file-dropzone');
            const chunkUploadEndpoint = @json(route('uploads.chunks'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const heroInput = document.getElementById('hero_image');
            const heroDropzone = document.getElementById('hero-dropzone');

            const galleryInput = document.getElementById('gallery_images');
            const galleryDropzone = document.getElementById('gallery-dropzone');
            const galleryPreviewWrapper = document.getElementById('gallery-previews');

            const defaultTitle = @json($mod->title);
            const defaultVersion = @json($mod->version);
            const defaultCategories = @json($mod->category_names);
            const fallbackDownloadState = @json($mod->file_path ? 'Direct download enabled' : 'External link selected');

            let galleryFiles = [];
            let modUploadActive = false;
            let hostedArchiveAvailable = @json((bool) $mod->file_path);
            if (modFileTokenInput.value) {
                hostedArchiveAvailable = true;
            }

            const updateHeroPreview = (file) => {
                const reader = new FileReader();
                reader.onload = (event) => {
                    previewHero.style.backgroundImage = `url('${event.target.result}')`;
                };
                reader.readAsDataURL(file);
            };

            const setHeroFile = (file) => {
                if (!file) {
                    return;
                }
                if (typeof DataTransfer !== 'undefined') {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    heroInput.files = dt.files;
                }
                updateHeroPreview(file);
            };

            const renderGalleryPreviews = () => {
                galleryPreviewWrapper.innerHTML = '';

                if (!galleryFiles.length) {
                    return;
                }

                const supportsDataTransfer = typeof DataTransfer !== 'undefined';
                const dt = supportsDataTransfer ? new DataTransfer() : null;

                galleryFiles.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const container = document.createElement('div');
                        container.className = 'relative h-24 overflow-hidden rounded-xl shadow-sm group';
                        container.innerHTML = `
                            <img src="${event.target.result}" alt="Screenshot preview" class="h-full w-full object-cover" />
                            <button type="button" class="absolute top-2 right-2 hidden rounded-full bg-black/70 p-1 text-white transition group-hover:flex" data-remove-index="${index}" aria-label="Remove screenshot">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        `;
                        galleryPreviewWrapper.appendChild(container);
                    };
                    reader.readAsDataURL(file);

                    if (dt) {
                        dt.items.add(file);
                    }
                });

                if (dt) {
                    galleryInput.files = dt.files;
                }

                const thumbs = document.createDocumentFragment();
                galleryFiles.slice(0, 3).forEach((file) => {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const thumb = document.createElement('div');
                        thumb.className = 'h-16 rounded-lg bg-cover bg-center';
                        thumb.style.backgroundImage = `url('${event.target.result}')`;
                        thumbs.appendChild(thumb);
                    };
                    reader.readAsDataURL(file);
                });

                if (thumbs.childNodes.length) {
                    previewGallery.innerHTML = '';
                    previewGallery.appendChild(thumbs);
                }
            };

            const hasUploadedArchive = () => hostedArchiveAvailable || !!modFileTokenInput.value;

            const uploadFileInChunks = async (file) => {
                const chunkSize = 5 * 1024 * 1024;
                const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
                const identifier = `${Date.now()}-${Math.random().toString(16).slice(2)}`;

                for (let index = 0; index < totalChunks; index++) {
                    const start = index * chunkSize;
                    const chunk = file.slice(start, Math.min(start + chunkSize, file.size));

                    const formData = new FormData();
                    formData.append('identifier', identifier);
                    formData.append('filename', file.name);
                    formData.append('mime_type', file.type || 'application/octet-stream');
                    formData.append('chunk_index', index);
                    formData.append('total_chunks', totalChunks);
                    formData.append('upload_type', 'mod_file');
                    formData.append('chunk', new File([chunk], file.name));

                    const response = await fetch(chunkUploadEndpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    if (!response.ok) {
                        throw new Error('Upload failed. Please try again.');
                    }

                    const payload = await response.json();
                    const progress = Math.min(100, Math.round(((index + 1) / totalChunks) * 100));
                    modUploadProgressBar.style.width = `${progress}%`;
                    modUploadProgressLabel.textContent = `Uploading… ${progress}%`;
                    modUploadProgressLabel.classList.remove('text-red-600');
                    modUploadProgressLabel.classList.add('text-indigo-500');

                    if (payload.status === 'completed') {
                        return payload;
                    }
                }

                throw new Error('Upload did not complete.');
            };

            const processModArchive = async (file) => {
                if (!file || modUploadActive) {
                    return;
                }

                modUploadActive = true;
                modUploadProgress.classList.remove('hidden');
                modUploadProgressBar.style.width = '0%';
                modUploadProgressLabel.textContent = 'Preparing upload…';
                modUploadProgressLabel.classList.remove('text-red-600');
                modUploadProgressLabel.classList.add('text-indigo-500');
                modFileDropzone.classList.add('opacity-60', 'pointer-events-none');
                modFileInput.value = '';

                try {
                    const result = await uploadFileInChunks(file);
                    modFileTokenInput.value = result.token;
                    modFileLabel.textContent = `${result.name} · ${result.size.toFixed(2)} MB`;

                    if (!fileSizeInput.value) {
                        fileSizeInput.value = result.size.toFixed(2);
                    }

                    hostedArchiveAvailable = true;
                    previewDownload.textContent = 'Direct download enabled';
                    modUploadProgressLabel.textContent = 'Upload complete. Ready to save.';
                    modUploadProgressBar.style.width = '100%';
                    updateDownloadSource();
                } catch (error) {
                    console.error(error);
                    modFileTokenInput.value = '';
                    modFileLabel.textContent = '';
                    modUploadProgressLabel.textContent = error.message || 'Upload failed. Please try again.';
                    modUploadProgressLabel.classList.remove('text-indigo-500');
                    modUploadProgressLabel.classList.add('text-red-600');
                    modUploadProgressBar.style.width = '0%';
                    updateDownloadSource();
                } finally {
                    modUploadActive = false;
                    modFileDropzone.classList.remove('opacity-60', 'pointer-events-none');
                }
            };

            const handleDrop = (zone, callback) => {
                zone.addEventListener('dragover', (event) => {
                    event.preventDefault();
                    zone.classList.add('ring-2', 'ring-pink-400');
                });

                zone.addEventListener('dragleave', () => {
                    zone.classList.remove('ring-2', 'ring-pink-400');
                });

                zone.addEventListener('drop', (event) => {
                    event.preventDefault();
                    zone.classList.remove('ring-2', 'ring-pink-400');
                    callback(event.dataTransfer.files);
                });
            };

            handleDrop(heroDropzone, (files) => {
                if (!files.length) {
                    return;
                }
                setHeroFile(files[0]);
            });

            handleDrop(galleryDropzone, (files) => {
                if (!files.length) {
                    return;
                }
                galleryFiles = galleryFiles.concat(Array.from(files));
                renderGalleryPreviews();
            });

            handleDrop(modFileDropzone, (files) => {
                if (!files.length) {
                    return;
                }

                processModArchive(files[0]);
            });

            heroInput.addEventListener('change', (event) => {
                if (event.target.files && event.target.files[0]) {
                    updateHeroPreview(event.target.files[0]);
                }
            });

            galleryInput.addEventListener('change', (event) => {
                if (!event.target.files.length) {
                    return;
                }
                galleryFiles = galleryFiles.concat(Array.from(event.target.files));
                renderGalleryPreviews();
            });

            galleryPreviewWrapper.addEventListener('click', (event) => {
                const button = event.target.closest('button[data-remove-index]');
                if (!button) {
                    return;
                }

                const index = Number(button.dataset.removeIndex);
                galleryFiles.splice(index, 1);
                renderGalleryPreviews();
            });

            titleInput.addEventListener('input', () => {
                const title = titleInput.value.trim() || defaultTitle;
                previewTitle.textContent = title;
            });

            versionInput.addEventListener('input', () => {
                const version = versionInput.value.trim() || defaultVersion;
                previewVersion.textContent = version.startsWith('v') ? version : `v${version}`;
            });

            const updateCategories = () => {
                const selected = Array.from(categoriesSelect.selectedOptions).map((option) => option.textContent.trim());
                previewCategories.textContent = selected.length ? selected.join(', ') : defaultCategories;
            };

            categoriesSelect.addEventListener('change', updateCategories);

            const updateDownloadSource = () => {
                const hasUrl = !!downloadInput.value.trim();
                const hasArchive = hasUploadedArchive();
                previewDownload.textContent = hasUrl ? 'External link selected' : (hasArchive ? 'Direct download enabled' : fallbackDownloadState);
            };

            downloadInput.addEventListener('input', updateDownloadSource);

            modFileInput.addEventListener('change', (event) => {
                if (!event.target.files.length) {
                    modFileTokenInput.value = '';
                    modFileLabel.textContent = '';
                    updateDownloadSource();
                    return;
                }

                processModArchive(event.target.files[0]);
            });

            updateCategories();
            updateDownloadSource();
        });
    </script>
@endpush
