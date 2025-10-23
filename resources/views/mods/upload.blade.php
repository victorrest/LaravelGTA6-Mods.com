@extends('layouts.app', ['title' => 'Upload Mod'])

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5/dist/photoswipe.css">
    <style>
        body.mod-upload-page {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            color: #374151;
        }

        #mod-upload-root .card {
            background-color: white;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        #mod-upload-root .form-input,
        #mod-upload-root .form-select,
        #mod-upload-root .form-textarea,
        #mod-upload-root .form-url {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease-in-out;
        }

        #mod-upload-root .form-input:focus,
        #mod-upload-root .form-select:focus,
        #mod-upload-root .form-textarea:focus,
        #mod-upload-root .form-url:focus {
            outline: none;
            border-color: #ec4899;
            box-shadow: 0 0 0 2px #fbcfe8;
        }

        #mod-upload-root .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        #mod-upload-root .btn-action {
            background-color: #ec4899;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px 0 rgba(236, 72, 153, 0.3);
        }

        #mod-upload-root .btn-action:hover {
            background-color: #db2777;
        }

        #mod-upload-root .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            transition: background-color 0.3s ease;
        }

        #mod-upload-root .btn-secondary:hover {
            background-color: #d1d5db;
        }

        #mod-upload-root .info-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        #mod-upload-root .dragging {
            opacity: 0.5;
        }

        #mod-upload-root .drag-over {
            border: 2px dashed #ec4899 !important;
        }

        #mod-upload-root .file-input-wrapper {
            background-color: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
        }

        #mod-upload-root .preview-label {
            font-weight: 600;
            color: #4b5563;
        }

        #mod-upload-root .preview-value {
            color: #1f2937;
        }
    </style>
@endpush

@php
    use Illuminate\Support\Facades\Auth;

    $currentUser = Auth::user();
    $defaultAuthorName = $currentUser?->name ?? 'ViceDriver';
    $previousAuthors = array_values(array_filter(old('authors', [])));
    if (empty($previousAuthors)) {
        $previousAuthors = [$defaultAuthorName];
    }

    $plainDescription = '';
    if (old('description')) {
        try {
            $plainDescription = \App\Support\EditorJs::toPlainText(old('description'));
        } catch (\Throwable $exception) {
            $plainDescription = old('description_plain', '');
        }
    } else {
        $plainDescription = old('description_plain', '');
    }
@endphp

@section('content')
    <div id="mod-upload-root" class="space-y-6">
        <h2 id="main-title" class="text-3xl font-bold text-gray-900">Upload a new file</h2>

        <div id="preview-mode-banner" class="hidden bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md" role="alert">
            <p class="font-bold">Preview Mode</p>
            <p>This is how your mod page will look. Review the details below before submitting.</p>
        </div>

        <div id="form-card" class="card p-6 md:p-8">
            <form id="mod-upload-form" action="{{ route('mods.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf
                @include('components.validation-errors')

                <input type="hidden" name="description" id="description" value="{{ old('description') }}">
                <input type="hidden" name="file_size" id="file_size" value="{{ old('file_size') }}">
                <input type="file" name="hero_image" id="hero_image_input" class="hidden" accept="image/png,image/jpeg,image/webp">
                <input type="file" name="gallery_images[]" id="gallery_images_input" class="hidden" accept="image/png,image/jpeg,image/webp" multiple>

                <div id="form-step-1">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div>
                                <label for="title" class="form-label">File Name</label>
                                <input type="text" id="title" name="title" value="{{ old('title') }}" class="form-input" required>
                            </div>

                            <div>
                                <label class="form-label">Author(s)</label>
                                <div id="authors-container" class="space-y-2">
                                    @foreach ($previousAuthors as $index => $author)
                                        <div class="flex items-center space-x-2" data-initial-author="{{ $author === $defaultAuthorName && $index === 0 ? 'true' : 'false' }}">
                                            <input type="text" name="authors[]" value="{{ $author }}" class="form-input" placeholder="Enter author name" {{ $author === $defaultAuthorName && $index === 0 ? 'disabled' : '' }}>
                                            @if (!($author === $defaultAuthorName && $index === 0))
                                                <button type="button" class="text-gray-400 hover:text-red-500 transition-colors" aria-label="Remove author">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" id="add-author-btn" class="mt-2 text-sm font-semibold text-pink-600 hover:text-pink-800 transition">+ Add Author</button>
                            </div>

                            <div>
                                <label for="category" class="form-label">Category</label>
                                <select id="category" name="category_ids[]" class="form-select">
                                    <option value="">Select a category...</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(collect(old('category_ids'))->contains($category->id))>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="tags" class="form-label">Tags</label>
                                <input type="text" id="tags" name="tags_raw" value="{{ old('tags_raw') }}" class="form-input" placeholder="e.g. car, addon, tuning, classic">
                            </div>

                            <div>
                                <label for="description-textarea" class="form-label">Description</label>
                                <textarea id="description-textarea" class="form-textarea resize-none overflow-hidden" rows="10" placeholder="Provide information and installation instructions...">{{ $plainDescription }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Allowed HTML tags: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;ol&gt;, &lt;br&gt;</p>
                            </div>
                        </div>

                        <div class="lg:col-span-1 space-y-6">
                            <div>
                                <label class="form-label">Add screenshots</label>
                                <div class="flex items-center justify-center w-full">
                                    <label id="dropzone-label" for="screenshot-upload" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6 pointer-events-none">
                                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                                            <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs text-gray-500">JPG, PNG (MAX. 10MB)</p>
                                        </div>
                                        <input id="screenshot-upload" type="file" class="hidden" multiple accept="image/png, image/jpeg, image/webp">
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">The first image is the default featured image. You can drag to reorder.</p>
                                <div id="image-preview-container" class="mt-4 flex flex-wrap gap-4"></div>
                                <p id="hero-upload-status" class="text-xs text-gray-500"></p>
                            </div>

                            <div>
                                <h3 class="form-label">File Settings</h3>
                                <div class="info-box space-y-3">
                                    <h4 class="font-semibold text-gray-800">Video Upload Permissions</h4>
                                    <div class="flex items-center">
                                        <input id="video-deny" name="video_permissions" type="radio" class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" value="deny">
                                        <label for="video-deny" class="ml-3 block text-sm font-medium text-gray-700">Deny</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="video-moderate" name="video_permissions" type="radio" class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" value="moderate" checked>
                                        <label for="video-moderate" class="ml-3 block text-sm font-medium text-gray-700">Self Moderate</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="video-allow" name="video_permissions" type="radio" class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" value="allow">
                                        <label for="video-allow" class="ml-3 block text-sm font-medium text-gray-700">Allow</label>
                                    </div>
                                </div>
                            </div>

                            <div class="info-box text-sm">
                                <p class="font-semibold mb-2 text-gray-800">Please ensure you upload an in-game image or representative art of your mod.</p>
                                <p class="mb-2">The description must include:</p>
                                <ul class="list-disc list-inside space-y-1 text-xs text-gray-600">
                                    <li>Mod description</li>
                                    <li>Bugs and features</li>
                                    <li>Summary of installation instructions</li>
                                    <li>Credits and, if applicable, notices of permission for content re-use</li>
                                </ul>
                                <p class="mt-2">Failing to provide the necessary details will result in your mod being rejected.</p>
                            </div>

                            <div id="step-1-errors"></div>

                            <div class="mt-6 flex items-center justify-end space-x-3">
                                <a href="{{ route('mods.my') }}" class="btn-secondary font-bold py-2 px-6 rounded-lg transition text-center">Cancel</a>
                                <button type="button" id="continue-btn" class="btn-action font-bold py-2 px-6 rounded-lg transition">Continue</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="form-step-2" class="hidden space-y-6">
                    <h3 class="text-2xl font-bold text-gray-800">Upload Your File</h3>
                    <div id="step-2-errors"></div>

                    <div class="space-y-6">
                        <div>
                            <div id="file-upload-view">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="form-label !mb-0" for="mod_file_input">Mod File</label>
                                    <button type="button" id="show-url-view-btn" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition">Or provide a download link</button>
                                </div>
                                <div class="flex items-center justify-center w-full">
                                    <label id="mod-dropzone-label" for="mod_file_input" class="flex flex-col items-center justify-center w-full h-48 lg:h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                        <div id="mod-dropzone-content" class="flex flex-col items-center justify-center pt-5 pb-6 text-center">
                                            <i class="fas fa-file-archive text-5xl text-gray-400"></i>
                                            <p class="my-2 text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs text-gray-500">Allowed: .zip, .rar, .7z, .oiv (MAX. 400MB)</p>
                                        </div>
                                        <div id="mod-file-preview" class="hidden items-center justify-center text-center p-4"></div>
                                            <input id="mod_file_input" name="mod_file" type="file" class="hidden" accept=".zip,.rar,.7z,.oiv">
                                    </label>
                                </div>
                            </div>

                            <div id="url-view" class="hidden">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="form-label !mb-0" for="download_url">Download URL</label>
                                    <button type="button" id="show-file-view-btn" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition">Or upload a file</button>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <input type="url" id="download_url" name="download_url" value="{{ old('download_url') }}" class="form-url" placeholder="e.g. https://drive.google.com/file/d/...">
                                        <p class="text-xs text-gray-500 mt-2">Provide a direct download link from a trusted source (e.g., Google Drive, Mega, Dropbox).</p>
                                    </div>
                                    <div>
                                        <label for="file-size-input" class="form-label text-sm">File Size</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number" min="0" step="0.01" id="file-size-input" class="form-input" placeholder="e.g. 850">
                                            <select id="file-size-unit" class="form-select !w-auto">
                                                <option value="MB" selected>MB</option>
                                                <option value="GB">GB</option>
                                            </select>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Please specify the file size to inform users about the download.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="version" class="form-label">Version</label>
                            <input type="text" id="version" name="version" value="{{ old('version', '1.0.0') }}" class="form-input" placeholder="e.g. 1.0.0" required>
                        </div>

                        <div class="pt-2 flex items-center justify-between">
                            <button type="button" id="back-btn" class="btn-secondary font-bold py-2 px-6 rounded-lg transition">Back</button>
                            <button type="button" id="preview-btn" class="btn-action font-bold py-2 px-6 rounded-lg transition">Preview</button>
                        </div>
                        <p class="text-xs text-gray-500">Please follow the <a href="#upload-rules" class="text-pink-600 hover:underline">upload rules</a> or your file will be removed.</p>
                    </div>
                </div>

                <div id="form-step-3" class="hidden space-y-6">
                    <div id="preview-header" class="mb-6"></div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div>
                                <div id="preview-gallery-container" class="pswp-gallery">
                                    <div class="aspect-video bg-gray-200 rounded-lg overflow-hidden mb-4 shadow-inner flex items-center justify-center text-gray-500">
                                        <span>No featured image selected.</span>
                                    </div>
                                    <div id="preview-gallery-thumbnails" class="grid grid-cols-5 gap-2"></div>
                                </div>
                                <div id="preview-load-more-container" class="mt-4"></div>
                            </div>

                            <div class="info-box">
                                <h3 class="text-lg font-bold text-gray-900 mb-3 border-b pb-3">Description</h3>
                                <div id="preview-description-content" class="prose prose-sm max-w-none text-gray-800"></div>
                            </div>
                        </div>

                        <div class="lg:col-span-1 space-y-6 self-start lg:sticky lg:top-6">
                            <div class="info-box p-4 space-y-4">
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <span class="preview-label">Author:</span>
                                    <span id="preview-sidebar-authors" class="preview-value font-semibold text-right truncate"></span>

                                    <span class="preview-label">Version:</span>
                                    <span id="preview-sidebar-version" class="preview-value font-semibold text-right"></span>

                                    <span class="preview-label">Last Updated:</span>
                                    <span id="preview-sidebar-updated" class="preview-value text-right"></span>

                                    <span class="preview-label">Category:</span>
                                    <span id="preview-sidebar-category" class="preview-value text-right"></span>
                                </div>
                                <div class="border-t pt-4">
                                    <span class="preview-label">Tags:</span>
                                    <div id="preview-sidebar-tags" class="flex flex-wrap gap-2 mt-2"></div>
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
                            <div class="info-box p-4">
                                <p class="preview-label">Mod Source:</p>
                                <div id="preview-sidebar-source-info" class="mt-1 text-sm text-gray-700"></div>
                            </div>
                            <div class="space-y-3">
                                <button type="submit" class="w-full btn-action font-bold py-3 px-6 rounded-lg transition text-lg flex items-center justify-center gap-2">
                                    <i class="fas fa-check-circle"></i> Submit File
                                </button>
                                <button type="button" id="back-to-step-2-btn" class="w-full btn-secondary font-bold py-2 px-6 rounded-lg transition">Back to Edit</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div id="upload-rules" class="card">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">Upload Rules</h3>
            </div>
            <div class="p-6 text-sm text-gray-600 space-y-4">
                <p class="font-semibold">DO NOT upload any of the following items - breaking these rules will cause your file to be deleted without notice:</p>
                <ul class="list-disc list-inside space-y-2">
                    <li>Any files besides .zip, .rar, .7z and .oiv archives.</li>
                    <li>Archives that do not contain a mod, or are part of other mods or mod packs.</li>
                    <li>Any archive containing only original game files.</li>
                    <li>Any file that can be used for cheating online.</li>
                    <li>Any file containing or giving access to pirated or otherwise copyrighted content including game cracks, movies, television shows and music.</li>
                    <li>Files containing malware or any .EXE file with a positive anti-virus result.</li>
                    <li>Any file containing nude or semi-nude pornographic images.</li>
                    <li>Any file containing a political or ideology theme. At the complete discretion of the administrator, deemed to be something that will cause unnecessary debates in the comments section.</li>
                    <li>Files that do not contain a simple installation instruction, with an exception for tools.</li>
                </ul>
                <p>For a full list of our rules and regulations please see: <a href="#" class="text-pink-600 hover:underline font-semibold">Rules and Regulations</a>.</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5/dist/photoswipe-lightbox.esm.js';

        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('mod-upload-page');

            const MAX_SCREENSHOTS = 12;

            const form = document.getElementById('mod-upload-form');
            const formSteps = [
                document.getElementById('form-step-1'),
                document.getElementById('form-step-2'),
                document.getElementById('form-step-3'),
            ];

            const mainTitle = document.getElementById('main-title');
            const formCard = document.getElementById('form-card');
            const uploadRules = document.getElementById('upload-rules');
            const previewBanner = document.getElementById('preview-mode-banner');

            const continueBtn = document.getElementById('continue-btn');
            const backBtn = document.getElementById('back-btn');
            const previewBtn = document.getElementById('preview-btn');
            const backToStep2Btn = document.getElementById('back-to-step-2-btn');

            const step1ErrorsContainer = document.getElementById('step-1-errors');
            const step2ErrorsContainer = document.getElementById('step-2-errors');

            const titleInput = document.getElementById('title');
            const versionInput = document.getElementById('version');
            const categorySelect = document.getElementById('category');
            const tagsInput = document.getElementById('tags');
            const descriptionTextarea = document.getElementById('description-textarea');
            const descriptionInput = document.getElementById('description');

            const screenshotInput = document.getElementById('screenshot-upload');
            const galleryInput = document.getElementById('gallery_images_input');
            const heroInput = document.getElementById('hero_image_input');
            const dropzoneLabel = document.getElementById('dropzone-label');
            const imagePreviewContainer = document.getElementById('image-preview-container');
            const heroStatusLabel = document.getElementById('hero-upload-status');

            const authorsContainer = document.getElementById('authors-container');
            const addAuthorBtn = document.getElementById('add-author-btn');

            const modFileInput = document.getElementById('mod_file_input');
            const modDropzoneLabel = document.getElementById('mod-dropzone-label');
            const modDropzoneContent = document.getElementById('mod-dropzone-content');
            const modFilePreview = document.getElementById('mod-file-preview');

            const fileUploadView = document.getElementById('file-upload-view');
            const urlView = document.getElementById('url-view');
            const showUrlViewBtn = document.getElementById('show-url-view-btn');
            const showFileViewBtn = document.getElementById('show-file-view-btn');
            const downloadUrlInput = document.getElementById('download_url');
            const fileSizeHiddenInput = document.getElementById('file_size');
            const fileSizeInput = document.getElementById('file-size-input');
            const fileSizeUnit = document.getElementById('file-size-unit');

            const previewHeader = document.getElementById('preview-header');
            const previewGalleryContainer = document.getElementById('preview-gallery-container');
            const previewGalleryFeatured = previewGalleryContainer.querySelector('.aspect-video');
            const previewGalleryThumbnails = document.getElementById('preview-gallery-thumbnails');
            const previewLoadMoreContainer = document.getElementById('preview-load-more-container');
            const previewDescriptionContent = document.getElementById('preview-description-content');
            const previewSidebarAuthors = document.getElementById('preview-sidebar-authors');
            const previewSidebarVersion = document.getElementById('preview-sidebar-version');
            const previewSidebarUpdated = document.getElementById('preview-sidebar-updated');
            const previewSidebarCategory = document.getElementById('preview-sidebar-category');
            const previewSidebarTags = document.getElementById('preview-sidebar-tags');
            const previewSidebarSourceInfo = document.getElementById('preview-sidebar-source-info');

            let usingUrlMode = Boolean(downloadUrlInput.value);
            let pswpLightbox = null;
            let screenshotItems = [];
            let currentStep = 0;

            const autoResizeTextarea = () => {
                descriptionTextarea.style.height = 'auto';
                descriptionTextarea.style.height = `${descriptionTextarea.scrollHeight}px`;
            };
            descriptionTextarea.addEventListener('input', autoResizeTextarea);
            autoResizeTextarea();

            const destroyLightbox = () => {
                if (pswpLightbox) {
                    pswpLightbox.destroy();
                    pswpLightbox = null;
                }
            };

            const initializeLightbox = () => {
                destroyLightbox();
                pswpLightbox = new PhotoSwipeLightbox({
                    gallery: '#preview-gallery-container',
                    children: 'a.gallery-item',
                    pswpModule: () => import('https://unpkg.com/photoswipe@5/dist/photoswipe.esm.js'),
                });
                pswpLightbox.init();
            };

            const syncFileInputs = () => {
                if (!window.DataTransfer) {
                    return;
                }

                const galleryTransfer = new DataTransfer();
                screenshotItems.forEach((item) => galleryTransfer.items.add(item.file));
                galleryInput.files = galleryTransfer.files;

                const featured = screenshotItems.find((item) => item.isFeatured);
                if (featured) {
                    const heroTransfer = new DataTransfer();
                    heroTransfer.items.add(featured.file);
                    heroInput.files = heroTransfer.files;
                    heroStatusLabel.textContent = 'Featured image ready.';
                } else {
                    heroInput.value = '';
                    heroStatusLabel.textContent = '';
                }
            };

            const setStep = (stepIndex) => {
                currentStep = stepIndex;
                formSteps.forEach((stepEl, index) => {
                    if (index === stepIndex) {
                        stepEl.classList.remove('hidden');
                    } else {
                        stepEl.classList.add('hidden');
                    }
                });
            };

            const sanitizeAllowedHtml = (input) => {
                if (!input) {
                    return '';
                }

                const allowedTags = ['b', 'i', 'u', 'ul', 'ol', 'li', 'br'];
                const tempElement = document.createElement('div');
                tempElement.innerHTML = input;

                const traverse = (node) => {
                    [...node.children].forEach((child) => {
                        if (!allowedTags.includes(child.tagName.toLowerCase())) {
                            child.replaceWith(child.textContent || '');
                        } else {
                            traverse(child);
                        }
                    });
                };

                traverse(tempElement);

                return tempElement.innerHTML;
            };

            const buildDescriptionPayload = () => {
                const rawValue = descriptionTextarea.value;
                const sanitizedHtml = sanitizeAllowedHtml(rawValue.replace(/\n/g, '<br>'));
                const plainText = rawValue.trim();

                return {
                    html: sanitizedHtml,
                    plain: plainText,
                    json: JSON.stringify({
                        time: Date.now(),
                        version: '2.27.0',
                        blocks: [
                            {
                                type: 'paragraph',
                                data: { text: sanitizedHtml || ' ' },
                            },
                        ],
                    }),
                };
            };

            const getAuthorsList = () => Array.from(authorsContainer.querySelectorAll('input[name="authors[]"]'))
                .map((input) => input.value.trim())
                .filter(Boolean);

            const ensureFeaturedExists = () => {
                if (!screenshotItems.length) {
                    heroStatusLabel.textContent = '';
                    return;
                }

                if (!screenshotItems.some((item) => item.isFeatured)) {
                    screenshotItems[0].isFeatured = true;
                }
            };

            const renderScreenshotPreviews = () => {
                ensureFeaturedExists();
                imagePreviewContainer.innerHTML = '';

                screenshotItems.forEach((item, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'relative group aspect-video rounded-lg cursor-grab w-[calc(50%-0.5rem)] bg-gray-100 overflow-hidden';
                    wrapper.dataset.index = index;
                    wrapper.draggable = true;

                    const img = document.createElement('img');
                    img.src = item.previewUrl;
                    img.alt = `Screenshot ${index + 1}`;
                    img.className = 'w-full h-full object-cover pointer-events-none';

                    const badge = document.createElement('span');
                    badge.className = 'absolute top-2 left-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white text-xs font-bold z-10 pointer-events-none';
                    badge.textContent = index + 1;

                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'absolute top-2 right-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white hover:bg-red-500 transition-colors z-10';
                    deleteBtn.innerHTML = '<i class="fas fa-times text-sm"></i>';
                    deleteBtn.addEventListener('click', (event) => {
                        event.preventDefault();
                        const [removed] = screenshotItems.splice(index, 1);
                        if (removed) {
                            URL.revokeObjectURL(removed.previewUrl);
                        }
                        renderScreenshotPreviews();
                        syncFileInputs();
                    });

                    const radioLabel = document.createElement('label');
                    radioLabel.className = 'absolute bottom-2 right-2 flex items-center p-1.5 bg-black/60 rounded-full cursor-pointer text-white text-xs backdrop-blur-sm transition-all';

                    const radioInput = document.createElement('input');
                    radioInput.type = 'radio';
                    radioInput.name = 'featured_screenshot';
                    radioInput.className = 'hidden peer';
                    radioInput.checked = item.isFeatured;
                    radioInput.addEventListener('change', () => {
                        screenshotItems.forEach((entry, entryIndex) => {
                            entry.isFeatured = entryIndex === index;
                        });
                        renderScreenshotPreviews();
                        syncFileInputs();
                    });

                    const radioIndicator = document.createElement('span');
                    radioIndicator.className = 'w-4 h-4 rounded-full border-2 border-white flex-shrink-0 mr-1.5 peer-checked:bg-pink-500 peer-checked:border-pink-500 transition-colors duration-200';

                    radioLabel.appendChild(radioInput);
                    radioLabel.appendChild(radioIndicator);
                    radioLabel.appendChild(document.createTextNode('Featured'));

                    if (item.isFeatured) {
                        wrapper.classList.add('ring-2', 'ring-pink-500', 'ring-offset-2', 'ring-offset-white');
                    }

                    wrapper.appendChild(img);
                    wrapper.appendChild(badge);
                    wrapper.appendChild(deleteBtn);
                    wrapper.appendChild(radioLabel);

                    imagePreviewContainer.appendChild(wrapper);
                });

                syncFileInputs();
            };

            let draggedIndex = null;

            imagePreviewContainer.addEventListener('dragstart', (event) => {
                const target = event.target.closest('[draggable="true"]');
                if (!target) {
                    return;
                }
                draggedIndex = Number(target.dataset.index);
                target.classList.add('opacity-50');
                event.dataTransfer.effectAllowed = 'move';
            });

            imagePreviewContainer.addEventListener('dragend', (event) => {
                const target = event.target.closest('[draggable="true"]');
                if (target) {
                    target.classList.remove('opacity-50');
                }
                draggedIndex = null;
            });

            imagePreviewContainer.addEventListener('dragover', (event) => {
                event.preventDefault();
                const target = event.target.closest('[draggable="true"]');
                imagePreviewContainer.querySelectorAll('.drag-over').forEach((element) => element.classList.remove('drag-over'));
                if (target && Number(target.dataset.index) !== draggedIndex) {
                    target.classList.add('drag-over');
                }
            });

            imagePreviewContainer.addEventListener('dragleave', (event) => {
                const target = event.target.closest('[draggable="true"]');
                if (target) {
                    target.classList.remove('drag-over');
                }
            });

            imagePreviewContainer.addEventListener('drop', (event) => {
                event.preventDefault();
                const target = event.target.closest('[draggable="true"]');
                imagePreviewContainer.querySelectorAll('.drag-over').forEach((element) => element.classList.remove('drag-over'));
                if (!target || draggedIndex === null) {
                    return;
                }
                const targetIndex = Number(target.dataset.index);
                if (targetIndex === draggedIndex) {
                    return;
                }
                const [draggedItem] = screenshotItems.splice(draggedIndex, 1);
                screenshotItems.splice(targetIndex, 0, draggedItem);
                renderScreenshotPreviews();
            });

            const handleScreenshotFiles = (files) => {
                const newFiles = Array.from(files).filter((file) => file.type.startsWith('image/'));
                if (!newFiles.length) {
                    return;
                }

                newFiles.forEach((file) => {
                    if (screenshotItems.length >= MAX_SCREENSHOTS) {
                        alert('You can upload up to 12 screenshots.');
                        return;
                    }

                    const previewUrl = URL.createObjectURL(file);
                    screenshotItems.push({
                        file,
                        previewUrl,
                        isFeatured: screenshotItems.length === 0,
                    });
                });

                renderScreenshotPreviews();
            };

            screenshotInput.addEventListener('change', (event) => {
                handleScreenshotFiles(event.target.files);
                screenshotInput.value = '';
            });

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
                dropzoneLabel.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                });
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzoneLabel.addEventListener(eventName, () => {
                    dropzoneLabel.classList.add('bg-pink-50', 'border-pink-400');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzoneLabel.addEventListener(eventName, () => {
                    dropzoneLabel.classList.remove('bg-pink-50', 'border-pink-400');
                });
            });

            dropzoneLabel.addEventListener('drop', (event) => {
                handleScreenshotFiles(event.dataTransfer.files);
            });

            const handleModFile = (files) => {
                if (!files || !files.length) {
                    return;
                }

                const file = files[0];
                const sizeMb = (file.size / 1024 / 1024).toFixed(2);

                modDropzoneContent.classList.add('hidden');
                modFilePreview.innerHTML = `
                    <div class="flex flex-col items-center text-gray-700">
                        <i class="fas fa-check-circle text-green-500 text-4xl"></i>
                        <p class="font-semibold mt-2 break-all">${file.name}</p>
                        <p class="text-sm text-gray-500">${sizeMb} MB</p>
                        <button type="button" class="mt-2 text-sm font-semibold text-red-600 hover:text-red-800 transition" id="remove-mod-file-btn">Remove</button>
                    </div>
                `;
                modFilePreview.classList.remove('hidden');
                modFilePreview.classList.add('flex');

                fileSizeHiddenInput.value = sizeMb;

                const removeBtn = modFilePreview.querySelector('#remove-mod-file-btn');
                removeBtn.addEventListener('click', () => {
                    modFileInput.value = '';
                    fileSizeHiddenInput.value = '';
                    modDropzoneContent.classList.remove('hidden');
                    modFilePreview.classList.add('hidden');
                    modFilePreview.classList.remove('flex');
                });
            };

            modFileInput.addEventListener('change', () => handleModFile(modFileInput.files));

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
                modDropzoneLabel.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                });
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                modDropzoneLabel.addEventListener(eventName, () => {
                    modDropzoneLabel.classList.add('bg-pink-50', 'border-pink-400');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                modDropzoneLabel.addEventListener(eventName, () => {
                    modDropzoneLabel.classList.remove('bg-pink-50', 'border-pink-400');
                });
            });

            modDropzoneLabel.addEventListener('drop', (event) => {
                handleModFile(event.dataTransfer.files);
            });

            const toggleUrlMode = (useUrl) => {
                usingUrlMode = useUrl;
                if (useUrl) {
                    fileUploadView.classList.add('hidden');
                    urlView.classList.remove('hidden');
                    modFileInput.value = '';
                    fileSizeHiddenInput.value = '';
                    modDropzoneContent.classList.remove('hidden');
                    modFilePreview.classList.add('hidden');
                    modFilePreview.classList.remove('flex');
                } else {
                    urlView.classList.add('hidden');
                    fileUploadView.classList.remove('hidden');
                    downloadUrlInput.value = '';
                    fileSizeInput.value = '';
                }
            };

            showUrlViewBtn.addEventListener('click', () => toggleUrlMode(true));
            showFileViewBtn.addEventListener('click', () => toggleUrlMode(false));

            if (usingUrlMode) {
                toggleUrlMode(true);
                if (fileSizeHiddenInput.value) {
                    fileSizeInput.value = Number(fileSizeHiddenInput.value) || '';
                    fileSizeUnit.value = 'MB';
                }
            }

            const syncManualFileSize = () => {
                if (!usingUrlMode) {
                    return;
                }

                let sizeValue = Number(fileSizeInput.value);
                if (!sizeValue || sizeValue <= 0) {
                    fileSizeHiddenInput.value = '';
                    return;
                }

                if (fileSizeUnit.value === 'GB') {
                    sizeValue *= 1024;
                }

                fileSizeHiddenInput.value = sizeValue.toFixed(2);
            };

            fileSizeInput.addEventListener('input', syncManualFileSize);
            fileSizeUnit.addEventListener('change', syncManualFileSize);

            const validateStep1 = () => {
                const errors = [];
                const descriptionPayload = buildDescriptionPayload();

                if (!titleInput.value.trim()) {
                    errors.push('The "File Name" field is required.');
                }

                if (!categorySelect.value) {
                    errors.push('Please select a category.');
                }

                if (!descriptionPayload.plain) {
                    errors.push('The description cannot be empty.');
                }

                if (!screenshotItems.length) {
                    errors.push('Please upload at least one screenshot.');
                }

                step1ErrorsContainer.innerHTML = '';
                if (errors.length) {
                    const list = document.createElement('ul');
                    list.className = 'list-disc list-inside text-sm text-red-600 bg-red-100 p-4 rounded-lg';
                    errors.forEach((message) => {
                        const li = document.createElement('li');
                        li.textContent = message;
                        list.appendChild(li);
                    });
                    step1ErrorsContainer.appendChild(list);
                    return false;
                }

                descriptionInput.value = descriptionPayload.json;

                return true;
            };

            const validateStep2 = () => {
                const errors = [];

                if (!versionInput.value.trim()) {
                    errors.push('The "Version" field is required.');
                }

                if (usingUrlMode) {
                    if (!downloadUrlInput.value.trim()) {
                        errors.push('Please provide a valid download URL.');
                    }

                    const sizeValue = Number(fileSizeInput.value);
                    if (!sizeValue || sizeValue <= 0) {
                        errors.push('Please enter a valid file size.');
                    }
                } else if (!modFileInput.files.length && !downloadUrlInput.value.trim()) {
                    errors.push('Please upload a mod file or provide a download URL.');
                }

                step2ErrorsContainer.innerHTML = '';
                if (errors.length) {
                    const list = document.createElement('ul');
                    list.className = 'list-disc list-inside text-sm text-red-600 bg-red-100 p-4 rounded-lg';
                    errors.forEach((message) => {
                        const li = document.createElement('li');
                        li.textContent = message;
                        list.appendChild(li);
                    });
                    step2ErrorsContainer.appendChild(list);
                    return false;
                }

                return true;
            };

            const populatePreview = () => {
                const authors = getAuthorsList();
                const tags = tagsInput.value
                    .split(',')
                    .map((tag) => tag.trim())
                    .filter(Boolean);
                const descriptionPayload = buildDescriptionPayload();

                previewHeader.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <h1 class="text-2xl md:text-4xl font-bold text-gray-900">${titleInput.value.trim()}</h1>
                        <span class="text-xl md:text-2xl font-semibold text-gray-400">${versionInput.value.trim()}</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-500 mt-1">
                        <span>by</span>
                        <span class="font-semibold text-pink-600 ml-1">${authors.join(', ') || 'Unknown author'}</span>
                    </div>
                `;

                previewDescriptionContent.innerHTML = descriptionPayload.html || '<p>No description provided.</p>';

                previewSidebarAuthors.textContent = authors.join(', ') || 'Unknown author';
                previewSidebarVersion.textContent = versionInput.value.trim() || '1.0';
                previewSidebarUpdated.textContent = new Date().toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                });
                previewSidebarCategory.textContent = categorySelect.options[categorySelect.selectedIndex]?.textContent || 'Uncategorised';

                previewSidebarTags.innerHTML = '';
                if (tags.length) {
                    tags.forEach((tag) => {
                        const span = document.createElement('span');
                        span.className = 'bg-gray-200 text-gray-700 text-xs font-semibold px-2.5 py-1 rounded-full';
                        span.textContent = tag;
                        previewSidebarTags.appendChild(span);
                    });
                } else {
                    previewSidebarTags.textContent = 'No tags provided.';
                }

                previewSidebarSourceInfo.innerHTML = '';
                if (usingUrlMode && downloadUrlInput.value.trim()) {
                    const sizeValue = Number(fileSizeInput.value);
                    const unit = fileSizeUnit.value || 'MB';
                    previewSidebarSourceInfo.innerHTML = `
                        <p class="preview-value font-mono text-sm bg-gray-200 p-2 rounded break-words">${downloadUrlInput.value.trim()}</p>
                        <p class="text-right text-sm text-gray-600 mt-1">Size: <strong>${sizeValue ? `${sizeValue} ${unit}` : 'Unknown'}</strong></p>
                    `;
                } else if (modFileInput.files.length) {
                    const file = modFileInput.files[0];
                    previewSidebarSourceInfo.innerHTML = `
                        <p class="preview-value font-mono text-sm bg-gray-200 p-2 rounded break-words">${file.name}</p>
                        <p class="text-right text-sm text-gray-600 mt-1">Size: <strong>${(file.size / 1024 / 1024).toFixed(2)} MB</strong></p>
                    `;
                } else {
                    previewSidebarSourceInfo.textContent = 'No file selected yet.';
                }

                previewGalleryFeatured.innerHTML = '';
                previewGalleryThumbnails.innerHTML = '';
                previewLoadMoreContainer.innerHTML = '';

                if (!screenshotItems.length) {
                    destroyLightbox();
                    return;
                }

                const featuredItem = screenshotItems.find((item) => item.isFeatured) || screenshotItems[0];
                const otherItems = screenshotItems.filter((item) => item !== featuredItem);

                const featuredLink = document.createElement('a');
                featuredLink.href = featuredItem.previewUrl;
                featuredLink.dataset.pswpWidth = 1600;
                featuredLink.dataset.pswpHeight = 900;
                featuredLink.className = 'gallery-item block w-full h-full';

                const featuredImg = document.createElement('img');
                featuredImg.src = featuredItem.previewUrl;
                featuredImg.alt = 'Featured Screenshot';
                featuredImg.className = 'w-full h-full object-cover';
                featuredLink.appendChild(featuredImg);
                previewGalleryFeatured.appendChild(featuredLink);

                otherItems.forEach((item, index) => {
                    const thumbLink = document.createElement('a');
                    thumbLink.href = item.previewUrl;
                    thumbLink.dataset.pswpWidth = 1600;
                    thumbLink.dataset.pswpHeight = 900;
                    thumbLink.className = 'gallery-item relative aspect-video block';

                    if (index >= 4) {
                        thumbLink.classList.add('hidden', 'extra-thumbnail');
                    }

                    const thumbImg = document.createElement('img');
                    thumbImg.src = item.previewUrl;
                    thumbImg.className = 'w-full h-full object-cover rounded-lg';
                    thumbLink.appendChild(thumbImg);
                    previewGalleryThumbnails.appendChild(thumbLink);
                });

                const hiddenThumbs = previewGalleryThumbnails.querySelectorAll('.extra-thumbnail');
                if (hiddenThumbs.length) {
                    const loadMoreBtn = document.createElement('button');
                    loadMoreBtn.type = 'button';
                    loadMoreBtn.className = 'w-full py-2 px-4 rounded-lg border-2 border-pink-500 text-pink-600 font-semibold hover:bg-pink-50 transition duration-300 ease-in-out';
                    loadMoreBtn.innerHTML = `<i class="fas fa-images mr-2"></i>Load more images (${hiddenThumbs.length} more)`;
                    loadMoreBtn.addEventListener('click', () => {
                        hiddenThumbs.forEach((thumb) => thumb.classList.remove('hidden'));
                        loadMoreBtn.remove();
                    });
                    previewLoadMoreContainer.appendChild(loadMoreBtn);
                }

                if (previewGalleryContainer.querySelectorAll('a.gallery-item').length) {
                    initializeLightbox();
                } else {
                    destroyLightbox();
                }
            };

            const handleFormSubmit = (event) => {
                if (!descriptionInput.value) {
                    descriptionInput.value = buildDescriptionPayload().json;
                }

                syncManualFileSize();
                syncFileInputs();
            };

            const addAuthorField = () => {
                const wrapper = document.createElement('div');
                wrapper.className = 'flex items-center space-x-2';
                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'authors[]';
                input.className = 'form-input';
                input.placeholder = 'Enter author name';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'text-gray-400 hover:text-red-500 transition-colors';
                removeBtn.innerHTML = '<i class="fas fa-times-circle"></i>';
                removeBtn.addEventListener('click', () => {
                    wrapper.remove();
                });

                wrapper.appendChild(input);
                wrapper.appendChild(removeBtn);
                authorsContainer.appendChild(wrapper);
            };

            addAuthorBtn.addEventListener('click', addAuthorField);

            authorsContainer.addEventListener('click', (event) => {
                const button = event.target.closest('button');
                if (!button) {
                    return;
                }
                const row = button.closest('div');
                if (row && row.dataset.initialAuthor !== 'true') {
                    row.remove();
                }
            });

            continueBtn.addEventListener('click', () => {
                if (validateStep1()) {
                    setStep(1);
                }
            });

            backBtn.addEventListener('click', () => {
                setStep(0);
            });

            previewBtn.addEventListener('click', () => {
                if (validateStep2()) {
                    descriptionInput.value = buildDescriptionPayload().json;
                    mainTitle.classList.add('hidden');
                    previewBanner.classList.remove('hidden');
                    formCard.classList.remove('card', 'p-6', 'md:p-8');
                    uploadRules.classList.add('hidden');
                    setStep(2);
                    populatePreview();
                }
            });

            backToStep2Btn.addEventListener('click', () => {
                setStep(1);
                mainTitle.classList.remove('hidden');
                previewBanner.classList.add('hidden');
                formCard.classList.add('card', 'p-6', 'md:p-8');
                uploadRules.classList.remove('hidden');
                destroyLightbox();
            });

            form.addEventListener('submit', handleFormSubmit);

            setStep(0);
        });
    </script>
@endpush
