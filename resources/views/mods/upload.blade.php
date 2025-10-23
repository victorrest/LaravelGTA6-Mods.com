@extends('layouts.app', ['title' => 'Upload Mod'])

@php($previewPlaceholder = 'https://placehold.co/800x450/0f172a/ffffff?text=GTA6+Mods')

@section('content')
    <section id="mod-upload-root" class="space-y-8">
        <h2 id="main-title" class="text-3xl font-bold text-gray-900">Upload a new file</h2>

        <div id="preview-mode-banner" class="hidden bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md" role="alert">
            <p class="font-bold">Preview Mode</p>
            <p>This is how your mod page will look. Review the details below before submitting.</p>
        </div>

        <div id="form-card" class="card p-6 md:p-8">
            <form id="mod-upload-form" method="POST" action="{{ route('mods.store') }}" enctype="multipart/form-data" class="space-y-10">
                @csrf
                @include('components.validation-errors')

                <input type="hidden" name="hero_image_token" id="hero_image_token" value="{{ old('hero_image_token') }}">
                <input type="hidden" name="mod_file_token" id="mod_file_token" value="{{ old('mod_file_token') }}">

                <div id="gallery-token-container">
                    @foreach (old('gallery_image_tokens', []) as $token)
                        <input type="hidden" name="gallery_image_tokens[]" value="{{ $token }}" data-token="{{ $token }}">
                    @endforeach
                </div>

                <section id="form-step-1" data-step="1" class="space-y-8">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div>
                                <label class="form-label" for="title">File Name</label>
                                <input id="title" name="title" type="text" value="{{ old('title') }}" class="form-input" required>
                            </div>

                            <div>
                                <span class="form-label">Categories</span>
                                <p class="text-xs text-gray-500 mb-3">Select every category that applies to your upload.</p>
                                <div id="category-grid" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                                    @foreach ($categories as $category)
                                        <label class="group rounded-xl border border-gray-200 hover:border-pink-400 transition cursor-pointer bg-white/70 category-tile" data-category-id="{{ $category->id }}">
                                            <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" class="sr-only category-checkbox" @checked(collect(old('category_ids'))->contains($category->id))>
                                            <div class="flex flex-col items-center justify-center gap-2 py-4 px-3">
                                                <i class="{{ $category->icon ?? 'fa-solid fa-star' }} text-2xl text-gray-500 group-[.active]:text-pink-500 transition"></i>
                                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-600 text-center group-[.active]:text-gray-900">{{ $category->name }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="form-label" for="description">Description</label>
                                <x-editorjs
                                    name="description"
                                    id="description"
                                    :value="old('description')"
                                    :plain-text="\App\Support\EditorJs::toPlainText(old('description'))"
                                    placeholder="Provide information and installation instructions..."
                                    required
                                />
                                <p class="text-xs text-gray-500 mt-1">Include features, issues, installation steps and credits. Minimum 20 characters.</p>
                            </div>
                        </div>

                        <div class="lg:col-span-1 space-y-6">
                            <div>
                                <label class="form-label">Add screenshots</label>
                                <div class="flex items-center justify-center w-full">
                                    <label id="screenshot-dropzone" class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6 text-center px-3">
                                            <i class="fa-solid fa-cloud-arrow-up text-4xl text-gray-400"></i>
                                            <p class="mt-2 text-sm text-gray-600"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs text-gray-500">JPG, PNG, WebP (max 10 MB each)</p>
                                        </div>
                                        <input type="file" id="screenshot-input" class="hidden" multiple accept="image/*">
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Mark one screenshot as featured to become the hero image. Drag to reorder.</p>
                                <div id="screenshot-previews" class="mt-4 flex flex-wrap gap-4"></div>
                                <p id="screenshot-helper" class="text-xs text-red-600 hidden">Upload at least one screenshot.</p>
                            </div>

                            <div>
                                <h3 class="form-label">File Settings</h3>
                                <div class="info-box space-y-3 text-sm">
                                    <p class="font-semibold text-gray-800">Download source</p>
                                    <p class="text-gray-600">You can upload the archive directly or provide a trusted external link in the next step.</p>
                                </div>
                            </div>

                            <div class="info-box text-xs space-y-2">
                                <p class="font-semibold text-gray-800">Quality checklist</p>
                                <ul class="list-disc list-inside text-gray-600 space-y-1">
                                    <li>Use an in-game or representative hero screenshot.</li>
                                    <li>Summarise features, bugs and credits in the description.</li>
                                    <li>Include clear installation steps for users.</li>
                                </ul>
                            </div>

                            <div class="flex items-center justify-end gap-3">
                                <button type="button" class="btn-secondary font-bold py-2 px-6 rounded-lg transition" id="cancel-step-1">Cancel</button>
                                <button type="button" id="continue-btn" class="btn-action font-bold py-2 px-6 rounded-lg transition">Continue</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="form-step-2" data-step="2" class="hidden space-y-8">
                    <div id="step-2-errors"></div>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="form-label mb-0">Mod file</label>
                                    <button type="button" id="switch-to-url" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition">Or provide a download link</button>
                                </div>
                                <div id="mod-file-wrapper" class="block">
                                    <label id="mod-dropzone" class="flex flex-col items-center justify-center w-full h-52 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition text-center">
                                        <div id="mod-dropzone-content" class="px-6">
                                            <i class="fa-solid fa-file-zipper text-4xl text-gray-400"></i>
                                            <p class="mt-3 text-gray-600"><span class="font-semibold">Click to upload</span> or drag archive here</p>
                                            <p class="text-xs text-gray-500">ZIP, RAR, 7Z, OIV · Max 200 MB</p>
                                            <p id="mod-file-label" class="text-xs text-pink-600 mt-2"></p>
                                        </div>
                                        <input type="file" id="mod_file" name="mod_file" class="hidden" accept=".zip,.rar,.7z,.oiv">
                                    </label>
                                </div>
                                <div id="mod-url-wrapper" class="hidden space-y-4">
                                    <div>
                                        <label class="form-label" for="download_url">Download URL</label>
                                        <input id="download_url" name="download_url" type="url" value="{{ old('download_url') }}" class="form-input" placeholder="https://">
                                        <p class="text-xs text-gray-500 mt-1">Provide a direct, trusted download link if you are not uploading the archive.</p>
                                    </div>
                                    <div>
                                        <label class="form-label" for="file_size">File size</label>
                                        <input id="file_size" name="file_size" type="number" step="0.01" min="0" value="{{ old('file_size') }}" class="form-input" placeholder="850">
                                        <p class="text-xs text-gray-500 mt-1">Show users how large the download is. Automatically filled when uploading.</p>
                                    </div>
                                    <button type="button" id="switch-to-file" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition">Or upload a file instead</button>
                                </div>
                            </div>
                            <div class="space-y-5">
                                <div>
                                    <label class="form-label" for="version">Version</label>
                                    <input id="version" name="version" type="text" value="{{ old('version', '1.0.0') }}" class="form-input" required>
                                </div>
                                <div class="info-box text-sm text-gray-600 space-y-2">
                                    <p class="font-semibold text-gray-800">Submission tips</p>
                                    <p>Ensure your archive mirrors the GTA 6 directory layout and includes a README or instructions.</p>
                                    <p>External downloads should be reliable mirrors such as Google Drive, Dropbox or Mega.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="button" id="back-btn" class="btn-secondary font-bold py-2 px-6 rounded-lg transition">Back</button>
                        <div class="flex items-center gap-3">
                            <button type="button" id="preview-btn" class="btn-action font-bold py-2 px-6 rounded-lg transition">Preview</button>
                        </div>
                    </div>
                </section>

                <section id="form-step-3" data-step="3" class="hidden space-y-8">
                    <div class="space-y-2" id="preview-header">
                        <div class="flex flex-wrap items-baseline gap-3">
                            <h1 class="text-2xl md:text-4xl font-bold text-gray-900" id="preview-title">Your GTA 6 mod title</h1>
                            <span class="text-xl font-semibold text-gray-400" id="preview-version">v1.0.0</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-500 gap-2">
                            <span>by</span>
                            <span class="font-semibold text-pink-600" id="preview-author">{{ auth()->user()->name }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div>
                                <div id="preview-gallery-container" class="pswp-gallery">
                                    <div id="preview-featured" class="aspect-video bg-gray-200 rounded-lg overflow-hidden flex items-center justify-center">
                                        <img src="{{ $previewPlaceholder }}" alt="Preview placeholder" class="w-full h-full object-cover hidden" id="preview-featured-image">
                                        <p class="text-sm text-gray-500" id="preview-featured-empty">Upload screenshots to populate this gallery.</p>
                                    </div>
                                    <div id="preview-thumbnails" class="grid grid-cols-5 gap-2 mt-3"></div>
                                </div>
                                <div id="preview-load-more" class="mt-4"></div>
                            </div>

                            <div class="info-box">
                                <h3 class="text-lg font-bold text-gray-900 mb-3 border-b border-gray-200 pb-3">Description</h3>
                                <div id="preview-description" class="prose prose-sm max-w-none text-gray-800"></div>
                            </div>
                        </div>

                        <div class="lg:col-span-1 space-y-6 self-start">
                            <div class="info-box p-4 space-y-4">
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <span class="preview-label">Author:</span>
                                    <span class="preview-value font-semibold text-right truncate" id="preview-sidebar-author">{{ auth()->user()->name }}</span>

                                    <span class="preview-label">Version:</span>
                                    <span class="preview-value font-semibold text-right" id="preview-sidebar-version">1.0.0</span>

                                    <span class="preview-label">Last Updated:</span>
                                    <span class="preview-value text-right" id="preview-sidebar-updated">—</span>

                                    <span class="preview-label">Categories:</span>
                                    <span class="preview-value text-right" id="preview-sidebar-categories">—</span>
                                </div>
                                <div class="border-t pt-4">
                                    <span class="preview-label">Download:</span>
                                    <div id="preview-sidebar-download" class="preview-value text-sm text-right mt-2"></div>
                                </div>
                            </div>

                            <div class="info-box p-4">
                                <div class="grid grid-cols-3 gap-2 text-sm text-center">
                                    <div>
                                        <span class="font-bold text-lg text-gray-800">0</span>
                                        <span class="text-xs text-gray-500 block">Likes</span>
                                    </div>
                                    <div>
                                        <span class="font-bold text-lg text-gray-800">0</span>
                                        <span class="text-xs text-gray-500 block">Views</span>
                                    </div>
                                    <div>
                                        <span class="font-bold text-lg text-gray-800">0</span>
                                        <span class="text-xs text-gray-500 block">Downloads</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <button type="submit" class="w-full btn-action font-bold py-3 px-6 rounded-lg transition text-lg flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-check-circle"></i>
                                    Submit file
                                </button>
                                <button type="button" id="back-to-step-2" class="w-full btn-secondary font-bold py-2 px-6 rounded-lg transition">Back to edit</button>
                            </div>
                        </div>
                    </div>
                </section>
            </form>
        </div>

        <div id="upload-rules" class="card">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">Upload rules</h3>
            </div>
            <div class="p-6 text-sm text-gray-600 space-y-3">
                <p class="font-semibold">Do not upload any of the following items – breaking these rules will cause your file to be deleted without notice:</p>
                <ul class="list-disc list-inside space-y-2">
                    <li>Files other than .zip, .rar, .7z or .oiv archives.</li>
                    <li>Archives without an actual mod or that bundle content from other authors without permission.</li>
                    <li>Original GTA game files or any files usable for cheating online.</li>
                    <li>Files containing malware, cracks or copyrighted media.</li>
                    <li>Content that is pornographic, hateful or politically inflammatory.</li>
                    <li>Uploads missing clear installation instructions (tools excluded).</li>
                </ul>
                <p>Review the full <a href="#" class="text-pink-600 hover:underline font-semibold">rules and regulations</a> before submitting.</p>
            </div>
        </div>
    </section>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5/dist/photoswipe.css">
@endpush

@push('scripts')
    <script type="module">
        import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5/dist/photoswipe-lightbox.esm.js';

        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('mod-upload-form');
            const step1 = document.getElementById('form-step-1');
            const step2 = document.getElementById('form-step-2');
            const step3 = document.getElementById('form-step-3');
            const previewBanner = document.getElementById('preview-mode-banner');
            const formCard = document.getElementById('form-card');
            const mainTitle = document.getElementById('main-title');
            const uploadRules = document.getElementById('upload-rules');

            const continueBtn = document.getElementById('continue-btn');
            const backBtn = document.getElementById('back-btn');
            const previewBtn = document.getElementById('preview-btn');
            const backToStep2Btn = document.getElementById('back-to-step-2');
            const cancelStep1Btn = document.getElementById('cancel-step-1');

            const step2Errors = document.getElementById('step-2-errors');
            const screenshotHelper = document.getElementById('screenshot-helper');

            const categoryTiles = document.querySelectorAll('.category-tile');
            const titleInput = document.getElementById('title');
            const versionInput = document.getElementById('version');
            const downloadInput = document.getElementById('download_url');
            const fileSizeInput = document.getElementById('file_size');
            const descriptionInput = document.getElementById('description');
            let descriptionPlainText = descriptionInput?.dataset.initialPlain || '';

            const screenshotDropzone = document.getElementById('screenshot-dropzone');
            const screenshotInput = document.getElementById('screenshot-input');
            const screenshotPreviews = document.getElementById('screenshot-previews');

            const heroTokenInput = document.getElementById('hero_image_token');
            const modFileTokenInput = document.getElementById('mod_file_token');
            const galleryTokenContainer = document.getElementById('gallery-token-container');

            const modFileInput = document.getElementById('mod_file');
            const modDropzone = document.getElementById('mod-dropzone');
            const modDropzoneContent = document.getElementById('mod-dropzone-content');
            const modFileLabel = document.getElementById('mod-file-label');
            const modFileWrapper = document.getElementById('mod-file-wrapper');
            const modUrlWrapper = document.getElementById('mod-url-wrapper');
            const switchToUrlBtn = document.getElementById('switch-to-url');
            const switchToFileBtn = document.getElementById('switch-to-file');

            const previewTitle = document.getElementById('preview-title');
            const previewVersion = document.getElementById('preview-version');
            const previewAuthor = document.getElementById('preview-author');
            const previewDescription = document.getElementById('preview-description');
            const previewSidebarAuthor = document.getElementById('preview-sidebar-author');
            const previewSidebarVersion = document.getElementById('preview-sidebar-version');
            const previewSidebarUpdated = document.getElementById('preview-sidebar-updated');
            const previewSidebarCategories = document.getElementById('preview-sidebar-categories');
            const previewSidebarDownload = document.getElementById('preview-sidebar-download');
            const previewFeatured = document.getElementById('preview-featured');
            const previewFeaturedImage = document.getElementById('preview-featured-image');
            const previewFeaturedEmpty = document.getElementById('preview-featured-empty');
            const previewThumbnails = document.getElementById('preview-thumbnails');
            const previewLoadMore = document.getElementById('preview-load-more');

            const chunkEndpoint = @json(route('mods.uploads.chunk'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const chunkSize = 512 * 1024;
            const MAX_GALLERY_ITEMS = 12;

            let galleryUploads = [];
            let pswpInstance = null;
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
                    alert('Please wait for all uploads to finish before submitting.');
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
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
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

            const syncCategoryTiles = () => {
                const selectedTiles = [];
                categoryTiles.forEach((tile) => {
                    const checkbox = tile.querySelector('.category-checkbox');
                    if (checkbox.checked) {
                        tile.classList.add('category-active');
                        tile.classList.remove('opacity-50');
                        tile.classList.add('border-pink-400', 'shadow-sm', 'bg-white');
                        tile.classList.add('active');
                        selectedTiles.push(tile.dataset.categoryId);
                    } else {
                        tile.classList.remove('category-active', 'border-pink-400', 'shadow-sm', 'bg-white', 'active');
                        tile.classList.add('opacity-50');
                    }
                });
                return selectedTiles;
            };

            categoryTiles.forEach((tile) => {
                tile.addEventListener('click', () => {
                    const checkbox = tile.querySelector('.category-checkbox');
                    checkbox.checked = !checkbox.checked;
                    syncCategoryTiles();
                });
            });

            syncCategoryTiles();

            const renderGallery = () => {
                screenshotPreviews.innerHTML = '';

                if (!galleryUploads.length) {
                    screenshotHelper.classList.remove('hidden');
                    previewFeaturedImage.classList.add('hidden');
                    previewFeaturedEmpty.classList.remove('hidden');
                    previewThumbnails.innerHTML = '';
                    previewLoadMore.innerHTML = '';
                    return;
                }

                screenshotHelper.classList.add('hidden');

                galleryUploads.forEach((item, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'relative group aspect-video w-[calc(50%-0.5rem)] min-w-[140px] max-w-[240px] flex-1 border rounded-lg overflow-hidden bg-gray-100 shadow-sm';
                    wrapper.draggable = true;
                    wrapper.dataset.index = index;

                    if (item.preview) {
                        const img = document.createElement('img');
                        img.src = item.preview;
                        img.alt = 'Screenshot preview';
                        img.className = 'w-full h-full object-cover pointer-events-none';
                        wrapper.appendChild(img);
                    } else {
                        wrapper.innerHTML = '<div class="flex items-center justify-center w-full h-full text-xs text-gray-500">Processing…</div>';
                    }

                    const numberBadge = document.createElement('span');
                    numberBadge.className = 'absolute top-2 left-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white text-xs font-bold';
                    numberBadge.textContent = index + 1;
                    wrapper.appendChild(numberBadge);

                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'absolute top-2 right-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white hover:bg-red-500 transition';
                    deleteBtn.innerHTML = '<i class="fa-solid fa-xmark text-sm"></i>';
                    deleteBtn.addEventListener('click', (event) => {
                        event.preventDefault();
                        const [removed] = galleryUploads.splice(index, 1);
                        if (removed?.token) {
                            removeGalleryToken(removed.token);
                        }
                        if (removed?.heroToken && heroTokenInput.value === removed.heroToken) {
                            heroTokenInput.value = '';
                        }
                        renderGallery();
                    });
                    wrapper.appendChild(deleteBtn);

                    const featuredLabel = document.createElement('button');
                    featuredLabel.type = 'button';
                    featuredLabel.className = 'absolute bottom-2 right-2 flex items-center gap-2 bg-black/60 text-white text-xs rounded-full px-3 py-1 transition hover:bg-black/80';
                    featuredLabel.innerHTML = `<span class="inline-flex h-3 w-3 rounded-full border border-white ${item.isHero ? 'bg-pink-500 border-pink-500' : ''}"></span> Featured`;
                    featuredLabel.addEventListener('click', (event) => {
                        event.preventDefault();
                        setHeroFromGallery(index);
                    });
                    wrapper.appendChild(featuredLabel);

                    if (item.isHero) {
                        wrapper.classList.add('ring-2', 'ring-pink-500');
                    }

                    screenshotPreviews.appendChild(wrapper);
                });

                refreshPreviewGallery();
            };

            const handleDragSorting = () => {
                let draggedIndex = null;

                screenshotPreviews.addEventListener('dragstart', (event) => {
                    const target = event.target.closest('[draggable="true"]');
                    if (!target) return;
                    draggedIndex = Number(target.dataset.index);
                    target.classList.add('opacity-70');
                });

                screenshotPreviews.addEventListener('dragend', (event) => {
                    const target = event.target.closest('[draggable="true"]');
                    if (!target) return;
                    target.classList.remove('opacity-70');
                });

                screenshotPreviews.addEventListener('dragover', (event) => {
                    event.preventDefault();
                    const target = event.target.closest('[draggable="true"]');
                    if (!target || draggedIndex === null) return;
                    const targetIndex = Number(target.dataset.index);
                    if (targetIndex === draggedIndex) return;
                    const [removed] = galleryUploads.splice(draggedIndex, 1);
                    galleryUploads.splice(targetIndex, 0, removed);
                    draggedIndex = targetIndex;
                    renderGallery();
                });
            };

            handleDragSorting();

            const addScreenshotFiles = (files) => {
                const images = Array.from(files).filter((file) => file.type.startsWith('image/'));
                if (!images.length) return;

                for (const file of images) {
                    if (galleryUploads.length >= MAX_GALLERY_ITEMS) {
                        alert('You can upload up to 12 screenshots.');
                        break;
                    }

                    const item = { file, preview: null, token: null, heroToken: null, uploading: true, isHero: false };
                    galleryUploads.push(item);
                    beginUpload();

                    const reader = new FileReader();
                    reader.onload = (event) => {
                        item.preview = event.target?.result;
                        renderGallery();
                    };
                    reader.readAsDataURL(file);

                    uploadFileInChunks(file, 'gallery_image')
                        .then((result) => {
                            item.token = result.upload_token;
                            item.uploading = false;
                            addGalleryToken(result.upload_token);
                            renderGallery();
                            if (!heroTokenInput.value) {
                                setHeroFromGallery(galleryUploads.indexOf(item));
                            }
                        })
                        .catch((error) => {
                            console.error(error);
                            galleryUploads = galleryUploads.filter((entry) => entry !== item);
                            renderGallery();
                            alert('Screenshot upload failed. Please try again.');
                        })
                        .finally(() => finishUpload());
                }
            };

            screenshotInput.addEventListener('change', (event) => {
                addScreenshotFiles(event.target.files);
                screenshotInput.value = '';
            });

            const handleDrop = (zone, callback) => {
                zone.addEventListener('dragover', (event) => {
                    event.preventDefault();
                    zone.classList.add('border-pink-400', 'bg-pink-50');
                });

                zone.addEventListener('dragleave', () => {
                    zone.classList.remove('border-pink-400', 'bg-pink-50');
                });

                zone.addEventListener('drop', (event) => {
                    event.preventDefault();
                    zone.classList.remove('border-pink-400', 'bg-pink-50');
                    callback(event.dataTransfer.files);
                });
            };

            handleDrop(screenshotDropzone, addScreenshotFiles);

            const setHeroFromGallery = (index) => {
                const item = galleryUploads[index];
                if (!item) return;

                galleryUploads.forEach((entry) => (entry.isHero = false));
                item.isHero = true;
                renderGallery();

                const applyHeroToken = (token) => {
                    heroTokenInput.value = token;
                    previewFeaturedImage.src = item.preview || previewFeaturedImage.src;
                    previewFeaturedImage.classList.remove('hidden');
                    previewFeaturedEmpty.classList.add('hidden');
                    refreshPreviewGallery();
                };

                if (item.heroToken) {
                    applyHeroToken(item.heroToken);
                    return;
                }

                if (!item.file) {
                    alert('Please re-upload this screenshot to set it as featured.');
                    return;
                }

                beginUpload();
                uploadFileInChunks(item.file, 'hero_image')
                    .then((result) => {
                        item.heroToken = result.upload_token;
                        applyHeroToken(result.upload_token);
                    })
                    .catch((error) => {
                        console.error(error);
                        alert('Failed to set hero image. Please try again.');
                    })
                    .finally(() => finishUpload());
            };

            const refreshPreviewGallery = () => {
                previewThumbnails.innerHTML = '';
                previewLoadMore.innerHTML = '';

                const ready = galleryUploads.filter((item) => item.preview);
                if (!ready.length) {
                    previewFeaturedImage.classList.add('hidden');
                    previewFeaturedEmpty.classList.remove('hidden');
                    destroyLightbox();
                    return;
                }

                const heroItem = galleryUploads.find((item) => item.isHero) || ready[0];
                if (heroItem) {
                    previewFeaturedImage.src = heroItem.preview;
                    previewFeaturedImage.classList.remove('hidden');
                    previewFeaturedEmpty.classList.add('hidden');
                }

                const others = ready.filter((item) => item !== heroItem);

                others.forEach((item, index) => {
                    const link = document.createElement('a');
                    link.href = item.preview;
                    link.dataset.pswpWidth = 1600;
                    link.dataset.pswpHeight = 900;
                    link.className = `gallery-item block aspect-video overflow-hidden rounded-lg ${index >= 4 ? 'hidden extra-thumbnail' : ''}`;

                    const img = document.createElement('img');
                    img.src = item.preview;
                    img.alt = 'Screenshot thumbnail';
                    img.className = 'w-full h-full object-cover';
                    link.appendChild(img);
                    previewThumbnails.appendChild(link);
                });

                if (others.length > 4) {
                    const remaining = others.length - 4;
                    const loadMoreBtn = document.createElement('button');
                    loadMoreBtn.type = 'button';
                    loadMoreBtn.className = 'w-full py-2 px-4 rounded-lg border-2 border-pink-500 text-pink-600 font-semibold hover:bg-pink-50 transition';
                    loadMoreBtn.innerHTML = `<i class="fa-solid fa-images mr-2"></i>Load more images (${remaining} more)`;
                    loadMoreBtn.addEventListener('click', () => {
                        previewThumbnails.querySelectorAll('.extra-thumbnail').forEach((el) => el.classList.remove('hidden'));
                        loadMoreBtn.remove();
                    });
                    previewLoadMore.appendChild(loadMoreBtn);
                }

                initLightbox();
            };

            const initLightbox = () => {
                destroyLightbox();
                pswpInstance = new PhotoSwipeLightbox({
                    gallery: '#preview-gallery-container',
                    children: 'a.gallery-item',
                    pswpModule: () => import('https://unpkg.com/photoswipe@5/dist/photoswipe.esm.js'),
                });
                pswpInstance.init();
            };

            const destroyLightbox = () => {
                if (pswpInstance) {
                    pswpInstance.destroy();
                    pswpInstance = null;
                }
            };

            const setUrlMode = (enabled) => {
                if (enabled) {
                    modFileWrapper.classList.add('hidden');
                    modUrlWrapper.classList.remove('hidden');
                    modFileInput.value = '';
                    modFileLabel.textContent = '';
                    modDropzoneContent.classList.remove('hidden');
                    modFileTokenInput.value = '';
                } else {
                    modFileWrapper.classList.remove('hidden');
                    modUrlWrapper.classList.add('hidden');
                    downloadInput.value = '';
                    fileSizeInput.value = '';
                }
                updateDownloadPreview();
            };

            switchToUrlBtn.addEventListener('click', () => setUrlMode(true));
            switchToFileBtn.addEventListener('click', () => setUrlMode(false));

            const handleModFileSelection = (file) => {
                if (!file) return;

                beginUpload();
                modDropzoneContent.classList.add('hidden');
                modFileLabel.textContent = 'Uploading…';

                uploadFileInChunks(file, 'mod_file', ({ index, total }) => {
                    const percent = Math.round((index / total) * 100);
                    modFileLabel.textContent = `Uploading… ${percent}%`;
                })
                    .then((result) => {
                        modFileTokenInput.value = result.upload_token;
                        modFileLabel.textContent = `${file.name} · ${result.size_mb.toFixed(2)} MB`;
                        if (!fileSizeInput.value) {
                            fileSizeInput.value = result.size_mb.toFixed(2);
                        }
                        updateDownloadPreview();
                    })
                    .catch((error) => {
                        console.error(error);
                        modFileTokenInput.value = '';
                        modFileLabel.textContent = 'Upload failed. Please try again.';
                        alert('Mod archive upload failed. Please try again.');
                        updateDownloadPreview();
                    })
                    .finally(() => {
                        finishUpload();
                        modFileInput.value = '';
                    });
            };

            modFileInput.addEventListener('change', () => {
                if (modFileInput.files?.[0]) {
                    handleModFileSelection(modFileInput.files[0]);
                }
            });

            handleDrop(modDropzone, (files) => {
                if (files?.[0]) {
                    handleModFileSelection(files[0]);
                }
            });

            const updatePreviewBasics = () => {
                const title = titleInput.value.trim() || 'Your GTA 6 mod title';
                previewTitle.textContent = title;
                previewSidebarUpdated.textContent = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                const version = versionInput.value.trim() || '1.0.0';
                previewVersion.textContent = version.startsWith('v') ? version : `v${version}`;
                previewSidebarVersion.textContent = version;

                const selectedCategories = Array.from(document.querySelectorAll('.category-checkbox:checked'))
                    .map((input) => input.closest('.category-tile')?.querySelector('span')?.textContent?.trim())
                    .filter(Boolean);
                previewSidebarCategories.textContent = selectedCategories.length ? selectedCategories.join(', ') : '—';
            };

            titleInput.addEventListener('input', updatePreviewBasics);
            versionInput.addEventListener('input', updatePreviewBasics);
            categoryTiles.forEach((tile) => tile.addEventListener('click', updatePreviewBasics));

            const updateDescriptionPreview = (plainText = '') => {
                descriptionPlainText = plainText || '';
                if (plainText) {
                    previewDescription.innerHTML = plainText.replace(/\n/g, '<br>');
                } else {
                    previewDescription.textContent = 'Add description content to populate this area.';
                }
            };

            if (descriptionInput) {
                updateDescriptionPreview(descriptionInput.dataset.initialPlain || '');
                descriptionInput.addEventListener('editorjs:change', (event) => {
                    updateDescriptionPreview(event.detail?.plainText || '');
                });
            }

            const updateDownloadPreview = () => {
                if (modUrlWrapper.classList.contains('hidden')) {
                    if (modFileTokenInput.value) {
                        previewSidebarDownload.textContent = 'Direct download hosted on GTA6-Mods.com';
                    } else {
                        previewSidebarDownload.textContent = 'Upload an archive or provide a link';
                    }
                } else {
                    const url = downloadInput.value.trim();
                    previewSidebarDownload.textContent = url ? url : 'Provide a direct download URL.';
                }
            };

            downloadInput.addEventListener('input', updateDownloadPreview);
            updateDownloadPreview();
            updatePreviewBasics();

            const validateStepOne = () => {
                const errors = [];
                if (!titleInput.value.trim()) {
                    errors.push('Please provide a file name.');
                }
                const selectedCategories = syncCategoryTiles();
                if (!selectedCategories.length) {
                    errors.push('Select at least one category.');
                }
                if (!galleryUploads.length) {
                    errors.push('Upload at least one screenshot.');
                }
                if (!descriptionPlainText.trim()) {
                    errors.push('Please add a description.');
                }

                if (errors.length) {
                    alert(errors.join('\n'));
                    return false;
                }
                return true;
            };

            const validateStepTwo = () => {
                step2Errors.innerHTML = '';
                const errors = [];

                const usingFileMode = !modFileWrapper.classList.contains('hidden');
                if (usingFileMode) {
                    if (!modFileTokenInput.value) {
                        errors.push('Upload a mod archive or switch to an external URL.');
                    }
                } else {
                    if (!downloadInput.value.trim()) {
                        errors.push('Provide a valid download URL.');
                    }
                    if (!fileSizeInput.value || Number(fileSizeInput.value) <= 0) {
                        errors.push('Specify the file size for the external download.');
                    }
                }

                if (!versionInput.value.trim()) {
                    errors.push('Enter a version number.');
                }

                if (errors.length) {
                    const list = document.createElement('ul');
                    list.className = 'list-disc list-inside text-sm text-red-600 bg-red-100 p-4 rounded-lg';
                    errors.forEach((error) => {
                        const item = document.createElement('li');
                        item.textContent = error;
                        list.appendChild(item);
                    });
                    step2Errors.appendChild(list);
                    return false;
                }

                return true;
            };

            const showStep = (stepNumber) => {
                [step1, step2, step3].forEach((section, index) => {
                    if (!section) return;
                    section.classList.toggle('hidden', index !== stepNumber - 1);
                });
            };

            continueBtn.addEventListener('click', () => {
                if (!validateStepOne()) {
                    return;
                }
                showStep(2);
            });

            backBtn.addEventListener('click', () => showStep(1));

            previewBtn.addEventListener('click', () => {
                if (!validateStepTwo()) {
                    return;
                }
                previewSidebarUpdated.textContent = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                formCard.classList.remove('card', 'p-6', 'md:p-8');
                previewBanner.classList.remove('hidden');
                mainTitle.classList.add('hidden');
                uploadRules.classList.add('hidden');
                showStep(3);
            });

            backToStep2Btn.addEventListener('click', () => {
                previewBanner.classList.add('hidden');
                mainTitle.classList.remove('hidden');
                uploadRules.classList.remove('hidden');
                formCard.classList.add('card', 'p-6', 'md:p-8');
                showStep(2);
            });

            cancelStep1Btn.addEventListener('click', () => {
                window.location.href = '{{ route('mods.index') }}';
            });

            showStep(1);
            refreshPreviewGallery();

            if (downloadInput.value.trim()) {
                setUrlMode(true);
            }

            updateDescriptionPreview(descriptionPlainText);

            if (modFileTokenInput.value && modFileWrapper.classList.contains('hidden') === false) {
                modDropzoneContent.classList.add('hidden');
                modFileLabel.textContent = 'Archive ready for submission.';
            }
        });
    </script>
@endpush
