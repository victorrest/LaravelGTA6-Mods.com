@extends('layouts.app', ['title' => 'Upload Mod'])

@php($previewPlaceholder = 'https://placehold.co/800x400/111827/EC4899?text=GTA6+Mod')

@section('content')
    <section class="max-w-6xl mx-auto space-y-8" id="mod-upload-root">
        <header class="text-center space-y-3">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Share your GTA 6 masterpiece</h1>
            <p class="text-sm md:text-base text-gray-500 max-w-2xl mx-auto">
                Our refreshed uploader mirrors the GTA6ModsWP workflow: organise details, showcase screenshots and bundle your
                files in one streamlined, multi-step flow. Submit once and let the community explore your creation.
            </p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">
            <form id="mod-upload-form" method="POST" action="{{ route('mods.store') }}" enctype="multipart/form-data" class="card overflow-hidden">
                <div class="bg-gray-50/60 border-b border-gray-100 px-6 py-4">
                    <nav class="flex items-center justify-between text-sm font-semibold text-gray-500" aria-label="Upload steps">
                        <button type="button" class="step-tab active" data-step="1">1. Details</button>
                        <button type="button" class="step-tab" data-step="2">2. Media &amp; files</button>
                        <button type="button" class="step-tab" data-step="3">3. Review</button>
                    </nav>
                </div>

                <div class="p-6 md:p-8 space-y-10">
                    @include('components.validation-errors')
                    @csrf

                    <section class="upload-step space-y-6" data-step="1">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="form-label" for="title">Mod title</label>
                                <input id="title" name="title" type="text" value="{{ old('title') }}" class="form-input" required>
                                <p class="form-help">Use a short, descriptive name (max 150 characters).</p>
                            </div>
                            <div>
                                <label class="form-label" for="version">Version</label>
                                <input id="version" name="version" type="text" value="{{ old('version', '1.0.0') }}" class="form-input" required>
                                <p class="form-help">Keep the version in sync with your downloadable build.</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label" for="category_ids">Categories</label>
                                <select id="category_ids" name="category_ids[]" multiple class="form-multiselect" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(collect(old('category_ids'))->contains($category->id))>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <p class="form-help">Hold Ctrl or Cmd to select every category that applies.</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label" for="download_url">Download URL</label>
                                <input id="download_url" name="download_url" type="url" value="{{ old('download_url') }}" class="form-input" placeholder="https://">
                                <p class="form-help">Optional if you upload the archive directly. External links must include the full protocol.</p>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="9" class="form-textarea" placeholder="Describe features, installation steps and credits" required>{{ old('description') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <span class="text-sm text-gray-500">Step 1 of 3</span>
                            <button type="button" class="inline-flex items-center gap-2 rounded-full bg-pink-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-pink-700 transition step-next">
                                Continue
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </section>

                    <section class="upload-step space-y-8 hidden" data-step="2">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <div>
                                <label class="form-label">Hero image</label>
                                <div id="hero-dropzone" class="relative flex h-48 w-full cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-pink-300 bg-white text-center transition hover:border-pink-500 hover:bg-pink-50">
                                    <input id="hero_image" name="hero_image" type="file" accept="image/*" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                                    <div class="space-y-2 px-6">
                                        <i class="fa-regular fa-image text-2xl text-pink-500"></i>
                                        <p class="text-sm font-semibold text-gray-700">Drop or click to upload</p>
                                        <p class="text-xs text-gray-500">Recommended 1600×900 JPG/PNG.</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label" for="file_size">File size (MB)</label>
                                <input id="file_size" name="file_size" type="number" step="0.1" min="0" value="{{ old('file_size') }}" class="form-input" placeholder="850">
                                <p class="form-help">We will auto-fill this when possible using your uploaded archive.</p>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Gallery screenshots</label>
                            <div id="gallery-dropzone" class="relative flex min-h-[220px] w-full cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-pink-300 bg-white text-center transition hover:border-pink-500 hover:bg-pink-50">
                                <input id="gallery_images" name="gallery_images[]" type="file" accept="image/*" multiple class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                                <div class="space-y-2 px-6">
                                    <i class="fa-solid fa-images text-2xl text-pink-500"></i>
                                    <p class="text-sm font-semibold text-gray-700">Bulk upload screenshots</p>
                                    <p class="text-xs text-gray-500">Add up to 12 JPG/PNG/WebP images to showcase your mod.</p>
                                </div>
                            </div>
                            <div id="gallery-previews" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <div>
                                <label class="form-label" for="mod_file">Upload mod archive</label>
                                <div class="relative flex h-48 cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-indigo-300 bg-indigo-50/60 text-center transition hover:border-indigo-400 hover:bg-indigo-100">
                                    <input id="mod_file" name="mod_file" type="file" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                                    <div class="space-y-2 px-6">
                                        <i class="fa-solid fa-file-zipper text-2xl text-indigo-500"></i>
                                        <p class="text-sm font-semibold text-gray-700">Drop ZIP/RAR/7Z here</p>
                                        <p class="text-xs text-gray-500">Max 200 MB. We'll deliver it directly from our CDN.</p>
                                        <p id="mod-file-label" class="text-xs text-indigo-500"></p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="info-box text-sm leading-relaxed text-gray-600">
                                    <h3 class="mb-2 text-base font-semibold text-gray-800">Pro tips</h3>
                                    <ul class="list-inside list-disc space-y-1">
                                        <li>Include installation instructions in the description.</li>
                                        <li>Use high quality screenshots with matching aspect ratios.</li>
                                        <li>Archive structure should mirror the GTA 6 directory layout.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="button" class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 step-previous">
                                <i class="fa-solid fa-arrow-left"></i>
                                Back
                            </button>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500">Step 2 of 3</span>
                                <button type="button" class="inline-flex items-center gap-2 rounded-full bg-pink-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-pink-700 transition step-next">
                                    Continue
                                    <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="upload-step space-y-6 hidden" data-step="3">
                        <div class="rounded-2xl border border-pink-100 bg-pink-50/60 p-5 text-sm text-pink-900">
                            <h3 class="text-base font-semibold">Almost there!</h3>
                            <p class="mt-1">Review your information and screenshots. The live preview on the right updates in real-time so you can double check everything before submission.</p>
                        </div>

                        <div class="grid gap-4 text-sm text-gray-700">
                            <div class="flex justify-between rounded-xl border border-gray-100 bg-white px-4 py-3">
                                <span class="font-medium text-gray-500">Title</span>
                                <span id="review-title" class="font-semibold text-gray-900">—</span>
                            </div>
                            <div class="flex justify-between rounded-xl border border-gray-100 bg-white px-4 py-3">
                                <span class="font-medium text-gray-500">Version</span>
                                <span id="review-version" class="font-semibold text-gray-900">—</span>
                            </div>
                            <div class="flex justify-between rounded-xl border border-gray-100 bg-white px-4 py-3">
                                <span class="font-medium text-gray-500">Categories</span>
                                <span id="review-categories" class="font-semibold text-gray-900 text-right">—</span>
                            </div>
                            <div class="flex justify-between rounded-xl border border-gray-100 bg-white px-4 py-3">
                                <span class="font-medium text-gray-500">Download source</span>
                                <span id="review-download" class="font-semibold text-gray-900 text-right">External link</span>
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-white px-4 py-3">
                                <span class="font-medium text-gray-500">Description</span>
                                <p id="review-description" class="mt-1 max-h-32 overflow-y-auto text-gray-700">—</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="button" class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 step-previous">
                                <i class="fa-solid fa-arrow-left"></i>
                                Back
                            </button>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500">Step 3 of 3</span>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-green-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-green-700 transition">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    Submit for review
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </form>

            <aside class="card p-6 md:p-7 space-y-6 lg:sticky lg:top-24" aria-live="polite">
                <div class="rounded-2xl overflow-hidden shadow-inner bg-gray-900 text-white">
                    <div id="preview-hero" class="relative h-48 bg-cover bg-center" style="background-image: url('{{ $previewPlaceholder }}');">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-gray-900/40 to-transparent"></div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <div class="flex items-center justify-between text-xs uppercase tracking-widest text-pink-200/80">
                                <span>Preview card</span>
                                <span id="preview-version" class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold">v1.0.0</span>
                            </div>
                            <h2 id="preview-title" class="mt-2 text-2xl font-bold leading-tight">Your GTA 6 mod title</h2>
                        </div>
                    </div>
                    <div class="space-y-4 bg-gray-900 px-5 py-6 text-sm">
                        <div>
                            <p class="text-gray-400 text-xs uppercase">Categories</p>
                            <p id="preview-categories" class="text-gray-100 font-medium">Choose at least one</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase">Download</p>
                            <p id="preview-download" class="text-gray-100 font-medium">External link required or upload file</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs uppercase">Screenshots</p>
                            <div id="preview-gallery" class="mt-2 grid grid-cols-3 gap-2">
                                <div class="h-16 rounded-lg bg-white/5"></div>
                                <div class="h-16 rounded-lg bg-white/5"></div>
                                <div class="h-16 rounded-lg bg-white/5"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-box text-sm leading-relaxed text-gray-600">
                    <h3 class="text-base font-semibold text-gray-800">Need a reminder?</h3>
                    <p class="mt-1">Your submission enters a moderation queue. Keep an eye on your dashboard for approval status and publish updates through the same flow.</p>
                </div>
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('mod-upload-form');
            const steps = Array.from(form.querySelectorAll('.upload-step'));
            const stepTabs = Array.from(form.querySelectorAll('.step-tab'));
            const nextButtons = form.querySelectorAll('.step-next');
            const previousButtons = form.querySelectorAll('.step-previous');
            let currentStep = 1;

            const reviewTitle = document.getElementById('review-title');
            const reviewVersion = document.getElementById('review-version');
            const reviewCategories = document.getElementById('review-categories');
            const reviewDownload = document.getElementById('review-download');
            const reviewDescription = document.getElementById('review-description');

            const previewTitle = document.getElementById('preview-title');
            const previewVersion = document.getElementById('preview-version');
            const previewCategories = document.getElementById('preview-categories');
            const previewDownload = document.getElementById('preview-download');
            const previewHero = document.getElementById('preview-hero');
            const previewGallery = document.getElementById('preview-gallery');

            const titleInput = document.getElementById('title');
            const versionInput = document.getElementById('version');
            const descriptionInput = document.getElementById('description');
            const downloadInput = document.getElementById('download_url');
            const categoriesSelect = document.getElementById('category_ids');
            const fileSizeInput = document.getElementById('file_size');
            const modFileInput = document.getElementById('mod_file');
            const modFileLabel = document.getElementById('mod-file-label');

            const heroInput = document.getElementById('hero_image');
            const heroDropzone = document.getElementById('hero-dropzone');

            const galleryInput = document.getElementById('gallery_images');
            const galleryDropzone = document.getElementById('gallery-dropzone');
            const galleryPreviewWrapper = document.getElementById('gallery-previews');

            let galleryFiles = [];

            const syncStepTabs = () => {
                stepTabs.forEach((tab) => {
                    const targetStep = Number(tab.dataset.step);
                    tab.classList.toggle('text-pink-600', targetStep === currentStep);
                    tab.classList.toggle('active', targetStep === currentStep);
                });
            };

            const showStep = (step) => {
                currentStep = step;
                steps.forEach((section) => {
                    const target = Number(section.dataset.step);
                    section.classList.toggle('hidden', target !== currentStep);
                });
                syncStepTabs();
            };

            const renderGalleryPreviews = () => {
                galleryPreviewWrapper.innerHTML = '';

                if (!galleryFiles.length) {
                    previewGallery.innerHTML = '<div class="col-span-3 text-xs text-gray-400">Add screenshots to populate this area.</div>';
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

                previewGallery.innerHTML = '';
                galleryFiles.slice(0, 3).forEach((file) => {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const thumb = document.createElement('div');
                        thumb.className = 'h-16 rounded-lg bg-cover bg-center';
                        thumb.style.backgroundImage = `url('${event.target.result}')`;
                        previewGallery.appendChild(thumb);
                    };
                    reader.readAsDataURL(file);
                });
            };

            galleryPreviewWrapper.addEventListener('click', (event) => {
                const button = event.target.closest('button[data-remove-index]');
                if (!button) {
                    return;
                }

                const index = Number(button.dataset.removeIndex);
                galleryFiles.splice(index, 1);
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

            const setHeroFile = (file) => {
                if (!file) {
                    return;
                }

                const supportsDataTransfer = typeof DataTransfer !== 'undefined';
                if (supportsDataTransfer) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    heroInput.files = dt.files;
                }
                updateHeroPreview(file);
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

            heroInput.addEventListener('change', (event) => {
                if (event.target.files && event.target.files[0]) {
                    updateHeroPreview(event.target.files[0]);
                }
            });

            const updateHeroPreview = (file) => {
                const reader = new FileReader();
                reader.onload = (event) => {
                    previewHero.style.backgroundImage = `url('${event.target.result}')`;
                };
                reader.readAsDataURL(file);
            };

            galleryInput.addEventListener('change', (event) => {
                if (!event.target.files.length) {
                    return;
                }
                galleryFiles = galleryFiles.concat(Array.from(event.target.files));
                renderGalleryPreviews();
            });

            titleInput.addEventListener('input', () => {
                const title = titleInput.value.trim() || 'Your GTA 6 mod title';
                previewTitle.textContent = title;
                reviewTitle.textContent = title;
            });

            versionInput.addEventListener('input', () => {
                const version = versionInput.value.trim() || 'v1.0.0';
                previewVersion.textContent = version.startsWith('v') ? version : `v${version}`;
                reviewVersion.textContent = version;
            });

            const updateCategories = () => {
                const selected = Array.from(categoriesSelect.selectedOptions).map((option) => option.textContent.trim());
                const label = selected.length ? selected.join(', ') : 'Choose at least one';
                previewCategories.textContent = label;
                reviewCategories.textContent = selected.length ? selected.join(', ') : '—';
            };

            categoriesSelect.addEventListener('change', updateCategories);

            descriptionInput.addEventListener('input', () => {
                const text = descriptionInput.value.trim();
                reviewDescription.textContent = text ? text : '—';
            });

            downloadInput.addEventListener('input', () => {
                const hasUrl = !!downloadInput.value.trim();
                reviewDownload.textContent = hasUrl ? 'External link' : (modFileInput.files.length ? 'Hosted on GTA6-Mods' : 'Not provided');
                previewDownload.textContent = hasUrl ? 'External link selected' : (modFileInput.files.length ? 'Direct download enabled' : 'External link required or upload file');
            });

            modFileInput.addEventListener('change', () => {
                if (!modFileInput.files.length) {
                    modFileLabel.textContent = '';
                    if (!downloadInput.value.trim()) {
                        previewDownload.textContent = 'External link required or upload file';
                        reviewDownload.textContent = 'External link';
                    }
                    return;
                }

                const file = modFileInput.files[0];
                const sizeMb = file.size / 1048576;
                modFileLabel.textContent = `${file.name} · ${sizeMb.toFixed(2)} MB`;
                previewDownload.textContent = 'Direct download enabled';
                reviewDownload.textContent = 'Hosted on GTA6-Mods';

                if (!fileSizeInput.value) {
                    fileSizeInput.value = sizeMb.toFixed(2);
                }

                if (!downloadInput.value.trim()) {
                    reviewDownload.textContent = 'Hosted on GTA6-Mods';
                }
            });

            nextButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const target = Math.min(currentStep + 1, steps.length);
                    showStep(target);
                });
            });

            previousButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const target = Math.max(currentStep - 1, 1);
                    showStep(target);
                });
            });

            stepTabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const target = Number(tab.dataset.step);
                    showStep(target);
                });
            });

            showStep(currentStep);
            updateCategories();

            if (titleInput.value.trim()) {
                titleInput.dispatchEvent(new Event('input'));
            }

            if (versionInput.value.trim()) {
                versionInput.dispatchEvent(new Event('input'));
            }

            if (descriptionInput.value.trim()) {
                reviewDescription.textContent = descriptionInput.value.trim();
            }

            if (downloadInput.value.trim()) {
                downloadInput.dispatchEvent(new Event('input'));
            }
        });
    </script>
@endpush
