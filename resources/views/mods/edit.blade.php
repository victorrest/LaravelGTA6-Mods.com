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
                <input type="hidden" name="hero_image_token" id="hero_image_token" value="{{ old('hero_image_token') }}">
                <input type="hidden" name="mod_file_token" id="mod_file_token" value="{{ old('mod_file_token') }}">
                <div id="gallery-token-container">
                    @foreach (old('gallery_image_tokens', []) as $token)
                        <input type="hidden" name="gallery_image_tokens[]" value="{{ $token }}" data-token="{{ $token }}">
                    @endforeach
                </div>

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
                            <input id="file_size" name="file_size" type="number" step="0.1" min="0" value="{{ old('file_size', $mod->file_size) }}" class="form-input" placeholder="850">
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
            const form = document.getElementById('mod-update-form');
            const chunkEndpoint = @json(route('mods.uploads.chunk'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const chunkSize = 2 * 1024 * 1024;
            const MAX_GALLERY_ITEMS = 12;
            const galleryDropzone = document.getElementById('gallery-dropzone');
            const existingGalleryCount = Number(galleryDropzone.dataset.existingCount || 0);
            const hasExistingHostedFile = @json((bool) $mod->file_path);

            const heroTokenInput = document.getElementById('hero_image_token');
            const heroStatusLabel = document.getElementById('hero-upload-status');
            const modFileTokenInput = document.getElementById('mod_file_token');
            const galleryTokenContainer = document.getElementById('gallery-token-container');

            const previewHero = document.getElementById('preview-hero');
            const previewTitle = document.getElementById('preview-title');
            const previewVersion = document.getElementById('preview-version');
            const previewCategories = document.getElementById('preview-categories');
            const previewDownload = document.getElementById('preview-download');
            const previewGallery = document.getElementById('preview-gallery');
            const initialPreviewGalleryHtml = previewGallery.innerHTML;

            const titleInput = document.getElementById('title');
            const versionInput = document.getElementById('version');
            const categoriesSelect = document.getElementById('category_ids');
            const downloadInput = document.getElementById('download_url');
            const modFileInput = document.getElementById('mod_file');
            const modFileLabel = document.getElementById('mod-file-label');
            const fileSizeInput = document.getElementById('file_size');
            const galleryInput = document.getElementById('gallery_images');
            const galleryPreviewWrapper = document.getElementById('gallery-previews');
            const heroInput = document.getElementById('hero_image');
            const heroDropzone = document.getElementById('hero-dropzone');

            let galleryUploads = [];
            const originalHostedState = hasExistingHostedFile;
            let hasUploadedModFile = Boolean(modFileTokenInput.value) || hasExistingHostedFile;
            let activeUploads = 0;

            const beginUpload = () => {
                activeUploads += 1;
            };

            const finishUpload = () => {
                activeUploads = Math.max(0, activeUploads - 1);
            };

            form.addEventListener('submit', (event) => {
                if (activeUploads > 0) {
                    event.preventDefault();
                    alert('Please wait for all uploads to finish before saving changes.');
                }
            });

            const uploadFileInChunks = async (file, category, onProgress = () => {}) => {
                const totalChunks = Math.max(Math.ceil(file.size / chunkSize), 1);
                const uploadToken = crypto.randomUUID();
                let result = null;

                for (let index = 0; index < totalChunks; index++) {
                    const start = index * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);
                    const chunk = file.slice(start, end);
                    const formData = new FormData();
                    formData.append('chunk', chunk, file.name);
                    formData.append('upload_token', uploadToken);
                    formData.append('chunk_index', index);
                    formData.append('total_chunks', totalChunks);
                    formData.append('original_name', file.name);
                    formData.append('mime_type', file.type || 'application/octet-stream');
                    formData.append('upload_category', category);

                    const response = await fetch(chunkEndpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    if (!response.ok) {
                        const error = await response.text();
                        throw new Error(error || 'Upload failed.');
                    }

                    const payload = await response.json();
                    onProgress({ index: index + 1, total: totalChunks, payload });

                    if (payload.status === 'completed') {
                        result = payload;
                    }
                }

                if (!result) {
                    throw new Error('Upload did not complete.');
                }

                return result;
            };

            const addGalleryToken = (token) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'gallery_image_tokens[]';
                input.value = token;
                input.dataset.token = token;
                galleryTokenContainer.appendChild(input);
            };

            const removeGalleryToken = (token) => {
                const existing = galleryTokenContainer.querySelector(`[data-token="${token}"]`);
                if (existing) {
                    existing.remove();
                }
            };

            const renderGalleryPreviews = () => {
                galleryPreviewWrapper.innerHTML = '';

                galleryUploads.forEach((item, index) => {
                    const container = document.createElement('div');
                    container.className = 'relative h-24 overflow-hidden rounded-xl shadow-sm group';

                    if (item.preview) {
                        container.innerHTML = `
                            <img src="${item.preview}" alt="Screenshot preview" class="h-full w-full object-cover" />
                            <button type="button" class="absolute top-2 right-2 hidden rounded-full bg-black/70 p-1 text-white transition group-hover:flex" data-remove-index="${index}" aria-label="Remove screenshot" ${item.uploading ? 'disabled' : ''}>
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                            ${item.uploading ? '<div class="absolute inset-0 flex items-center justify-center bg-black/40 text-xs font-semibold text-white">Uploading…</div>' : ''}
                        `;
                    } else {
                        container.innerHTML = '<div class="flex h-full w-full items-center justify-center bg-gray-100 text-xs font-semibold text-gray-500">Preparing…</div>';
                    }

                    galleryPreviewWrapper.appendChild(container);
                });

                previewGallery.innerHTML = initialPreviewGalleryHtml;
                galleryUploads
                    .filter((item) => item.preview)
                    .forEach((item) => {
                        const thumb = document.createElement('div');
                        thumb.className = 'h-16 rounded-lg bg-cover bg-center';
                        thumb.style.backgroundImage = `url('${item.preview}')`;
                        previewGallery.appendChild(thumb);
                    });
            };

            galleryPreviewWrapper.addEventListener('click', (event) => {
                const button = event.target.closest('button[data-remove-index]');
                if (!button) {
                    return;
                }

                const index = Number(button.dataset.removeIndex);
                const [removed] = galleryUploads.splice(index, 1);
                if (removed?.token) {
                    removeGalleryToken(removed.token);
                }
                renderGalleryPreviews();
            });

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

                updateHeroPreview(file);
                heroStatusLabel.textContent = 'Uploading…';
                beginUpload();

                uploadFileInChunks(file, 'hero_image')
                    .then((result) => {
                        heroTokenInput.value = result.upload_token;
                        heroStatusLabel.textContent = 'Uploaded successfully.';
                    })
                    .catch((error) => {
                        heroTokenInput.value = '';
                        heroStatusLabel.textContent = 'Upload failed. Please try again.';
                        console.error(error);
                        alert('Hero image upload failed. Please try again.');
                    })
                    .finally(() => {
                        heroInput.value = '';
                        finishUpload();
                    });
            };

            const remainingGallerySlots = () => {
                const removed = document.querySelectorAll('input[name="remove_gallery_image_ids[]"]:checked').length;
                return MAX_GALLERY_ITEMS - (existingGalleryCount - removed) - galleryUploads.length;
            };

            const handleGalleryFile = (file) => {
                if (!file) {
                    return;
                }

                if (remainingGallerySlots() <= 0) {
                    alert('You have reached the 12 screenshot limit. Deselect existing images to free up space.');
                    return;
                }

                const item = { preview: null, token: null, uploading: true };
                galleryUploads.push(item);
                renderGalleryPreviews();
                beginUpload();

                const reader = new FileReader();
                reader.onload = (event) => {
                    item.preview = event.target.result;
                    renderGalleryPreviews();
                };
                reader.readAsDataURL(file);

                uploadFileInChunks(file, 'gallery_image')
                    .then((result) => {
                        item.token = result.upload_token;
                        item.uploading = false;
                        addGalleryToken(result.upload_token);
                        renderGalleryPreviews();
                    })
                    .catch((error) => {
                        galleryUploads = galleryUploads.filter((entry) => entry !== item);
                        renderGalleryPreviews();
                        console.error(error);
                        alert('Screenshot upload failed. Please try again.');
                    })
                    .finally(() => {
                        finishUpload();
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

                Array.from(files).forEach((file) => handleGalleryFile(file));
            });

            heroInput.addEventListener('change', (event) => {
                if (event.target.files && event.target.files[0]) {
                    setHeroFile(event.target.files[0]);
                }
            });

            galleryInput.addEventListener('change', (event) => {
                if (!event.target.files.length) {
                    return;
                }

                Array.from(event.target.files).forEach((file) => handleGalleryFile(file));
                galleryInput.value = '';
            });

            const updateDownloadState = () => {
                const hasUrl = !!downloadInput.value.trim();

                if (hasUploadedModFile) {
                    previewDownload.textContent = 'Direct download enabled';
                } else if (hasUrl) {
                    previewDownload.textContent = 'External link selected';
                } else {
                    previewDownload.textContent = originalHostedState ? 'Direct download enabled' : 'External link selected';
                }
            };

            downloadInput.addEventListener('input', () => {
                updateDownloadState();
            });

            const handleModFileSelection = (file) => {
                if (!file) {
                    return;
                }

                hasUploadedModFile = false;
                modFileLabel.textContent = 'Uploading…';
                beginUpload();

                uploadFileInChunks(file, 'mod_archive', ({ index, total }) => {
                    const percent = Math.round((index / total) * 100);
                    modFileLabel.textContent = `Uploading… ${percent}%`;
                })
                    .then((result) => {
                        hasUploadedModFile = true;
                        modFileTokenInput.value = result.upload_token;
                        modFileLabel.textContent = `${file.name} · ${result.size_mb.toFixed(2)} MB`;
                        if (fileSizeInput) {
                            fileSizeInput.value = result.size_mb.toFixed(2);
                        }
                        updateDownloadState();
                    })
                    .catch((error) => {
                        hasUploadedModFile = originalHostedState;
                        modFileTokenInput.value = '';
                        modFileLabel.textContent = 'Upload failed. Please try again.';
                        console.error(error);
                        alert('Mod archive upload failed. Please try again.');
                        updateDownloadState();
                    })
                    .finally(() => {
                        finishUpload();
                        modFileInput.value = '';
                    });
            };

            modFileInput.addEventListener('change', () => {
                if (modFileInput.files && modFileInput.files[0]) {
                    handleModFileSelection(modFileInput.files[0]);
                }
            });

            titleInput.addEventListener('input', () => {
                const title = titleInput.value.trim() || @json($mod->title);
                previewTitle.textContent = title;
            });

            versionInput.addEventListener('input', () => {
                const version = versionInput.value.trim() || @json($mod->version);
                previewVersion.textContent = version.startsWith('v') ? version : `v${version}`;
            });

            const updateCategories = () => {
                const selected = Array.from(categoriesSelect.selectedOptions).map((option) => option.textContent.trim());
                previewCategories.textContent = selected.length ? selected.join(', ') : @json($mod->category_names);
            };

            categoriesSelect.addEventListener('change', updateCategories);

            renderGalleryPreviews();
            updateCategories();
            updateDownloadState();
        });
    </script>

@endpush
