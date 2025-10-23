@extends('layouts.app', ['title' => 'Upload Mod'])

@php
    $navigationItems = collect(config('gta6.navigation'));
    $categoriesBySlug = $categories->keyBy('slug');
    $selectedCategoryId = collect(old('category_ids', []))->first();
    $initialTags = collect(old('tag_list', []))->implode(', ');
    $initialAuthors = collect(old('authors', auth()->user() ? [auth()->user()->name] : []))->filter()->values()->all();
    $plainDescription = \App\Support\EditorJs::toPlainText(old('description'));
    $rulesUrl = \Illuminate\Support\Facades\Route::has('docs.upload-rules') ? route('docs.upload-rules') : '#';
@endphp

@push('styles')
    <style>
        body {
            background-color: #f3f4f6;
        }

        .logo-font {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            color: #ffffff;
            font-size: 2.2rem;
            line-height: 1;
        }

        .header-background {
            background-image: url('https://topiku.hu/wp-content/themes/backgroundkep.webp');
            background-size: cover;
            background-position: center -120px;
        }

        .header-top-bar {
            background-color: #ec4899e0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-nav-bar {
            background-color: rgba(39, 17, 28, 0.59);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }

        .btn-action {
            background-color: #ec4899;
            color: #ffffff;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px 0 rgba(236, 72, 153, 0.3);
        }

        .btn-action:hover {
            background-color: #db2777;
        }

        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            transition: background-color 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #d1d5db;
        }

        .card {
            background-color: white;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            overflow: hidden;
        }

        .form-input,
        .form-select,
        .form-textarea,
        .form-url {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease-in-out;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus,
        .form-url:focus {
            outline: none;
            border-color: #ec4899;
            box-shadow: 0 0 0 2px #fbcfe8;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .info-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .dragging {
            opacity: 0.5;
        }

        .drag-over {
            border: 2px dashed #ec4899 !important;
        }

        .category-active {
            opacity: 1 !important;
            transform: scale(1.05);
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.7);
        }

        .category-dimmed {
            opacity: 0.45 !important;
        }

        .file-input-wrapper {
            background-color: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
        }

        .preview-label {
            font-weight: 600;
            color: #4b5563;
        }

        .preview-value {
            color: #1f2937;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.6);
            border-radius: 9999px;
        }
    </style>
@endpush

@section('content')
    <section id="mod-upload-app" class="space-y-6">
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
                <input type="hidden" name="description" id="description" value="{{ old('description') }}">
                <input type="hidden" name="category_ids[]" id="selected-category" value="{{ $selectedCategoryId }}">

                <div id="gallery-token-container">
                    @foreach (old('gallery_image_tokens', []) as $token)
                        <input type="hidden" name="gallery_image_tokens[]" value="{{ $token }}" data-token="{{ $token }}">
                    @endforeach
                </div>

                <div id="form-step-1">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div>
                                <label for="file-name" class="form-label">File Name</label>
                                <input type="text" id="file-name" name="title" class="form-input" value="{{ old('title') }}" required>
                            </div>

                            <div>
                                <label class="form-label">Author(s)</label>
                                <div id="authors-container" class="space-y-2" data-initial="@json($initialAuthors)"></div>
                                <button type="button" id="add-author-btn" class="mt-2 text-sm font-semibold text-pink-600 hover:text-pink-800 transition">+ Add Author</button>
                            </div>

                            <div>
                                <label for="category" class="form-label">Category</label>
                                <select id="category" class="form-select">
                                    <option value="">Select a category...</option>
                                    @foreach ($navigationItems as $item)
                                        @php($category = $categoriesBySlug->get($item['slug']))
                                        @continue(! $category)
                                        <option value="{{ $category->id }}" data-slug="{{ $item['slug'] }}" @selected($selectedCategoryId === $category->id)>{{ $item['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="tags" class="form-label">Tags</label>
                                <input type="text" id="tags" name="tags" class="form-input" value="{{ $initialTags }}" placeholder="e.g. car, addon, tuning, classic">
                            </div>

                            <div>
                                <label for="description_plain" class="form-label">Description</label>
                                <textarea id="description_plain" name="description_plain" rows="10" class="form-textarea resize-none overflow-hidden" placeholder="Provide information and installation instructions...">{{ $plainDescription }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Allowed HTML tags: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;ol&gt;, &lt;br&gt;</p>
                            </div>
                        </div>

                        <div class="lg:col-span-1 space-y-6">
                            <div>
                                <label class="form-label">Add screenshots</label>
                                <div class="flex items-center justify-center w-full">
                                    <label id="dropzone-label" for="screenshot-upload" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                                            <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                            <p class="text-xs text-gray-500">JPG, PNG (MAX. 10MB)</p>
                                        </div>
                                        <input id="screenshot-upload" type="file" class="hidden" multiple accept="image/png, image/jpeg">
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">The first image is the default featured image. You can drag to reorder.</p>
                                <div id="image-preview-container" class="mt-4 flex flex-wrap gap-4"></div>
                            </div>

                            <div>
                                <h3 class="form-label">File Settings</h3>
                                <div class="info-box space-y-3">
                                    <h4 class="font-semibold text-gray-800">Video Upload Permissions</h4>
                                    @php($selectedPermission = old('video_permission', 'self_moderate'))
                                    <div class="flex items-center">
                                        <input id="video-deny" name="video_permission" type="radio" value="deny" class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" @checked($selectedPermission === 'deny')>
                                        <label for="video-deny" class="ml-3 block text-sm font-medium text-gray-700">Deny</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="video-moderate" name="video_permission" type="radio" value="self_moderate" class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" @checked($selectedPermission === 'self_moderate')>
                                        <label for="video-moderate" class="ml-3 block text-sm font-medium text-gray-700">Self Moderate</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="video-allow" name="video_permission" type="radio" value="allow" class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" @checked($selectedPermission === 'allow')>
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
                                    <li>Credits and permission notices</li>
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

                <div id="form-step-2" class="hidden">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Upload Your File</h3>
                    <div id="step-2-errors"></div>
                    <div class="space-y-6">
                        <div>
                            <div id="file-upload-view" @class(['hidden' => old('download_url')])>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="form-label !mb-0">Mod File</label>
                                    <button type="button" id="show-url-view-btn" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition">Or provide a download link</button>
                                </div>
                                <div id="mod-file-upload-container">
                                    <div class="flex items-center justify-center w-full">
                                        <label id="mod-dropzone-label" for="mod-file-input" class="flex flex-col items-center justify-center w-full h-48 lg:h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                            <div id="mod-dropzone-content" class="flex flex-col items-center justify-center pt-5 pb-6 text-center">
                                                <i class="fas fa-file-archive text-5xl text-gray-400"></i>
                                                <p class="my-2 text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                                <p class="text-xs text-gray-500">Allowed: .zip, .rar, .7z, .oiv (MAX. 400MB)</p>
                                            </div>
                                            <div id="mod-file-preview" class="hidden items-center justify-center text-center p-4"></div>
                                            <input id="mod-file-input" type="file" class="hidden" accept=".zip,.rar,.7z,.oiv">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div id="url-view" @class(['hidden' => !old('download_url')])>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="form-label !mb-0" for="mod-url-input">Download URL</label>
                                    <button type="button" id="show-file-view-btn" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition">Or upload a file</button>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <input type="url" id="mod-url-input" name="download_url" class="form-url" value="{{ old('download_url') }}" placeholder="e.g. https://drive.google.com/file/d/...">
                                        <p class="text-xs text-gray-500 mt-2">Provide a direct download link from a trusted source (e.g., Google Drive, Mega, Dropbox).</p>
                                    </div>
                                    <div>
                                        <label for="file-size-input" class="form-label text-sm">File Size</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number" min="0" id="file-size-input" class="form-input" name="file_size" value="{{ old('file_size') }}" placeholder="e.g. 850">
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
                            <input type="text" id="version" name="version" class="form-input" value="{{ old('version', '1.0.0') }}" placeholder="e.g. 1.0.0">
                        </div>

                        <div class="pt-2 flex items-center justify-between">
                            <button type="button" id="back-btn" class="btn-secondary font-bold py-2 px-6 rounded-lg transition">Back</button>
                            <button type="button" id="preview-btn" class="btn-action font-bold py-2 px-6 rounded-lg transition">Preview</button>
                        </div>
                        <p class="text-xs text-gray-500">Please follow the <a href="{{ $rulesUrl }}" class="text-pink-600 hover:underline">upload rules</a> or your file will be removed.</p>
                    </div>
                </div>

                <div id="form-step-3" class="hidden">
                    <div id="preview-header" class="mb-6"></div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div>
                                <div id="preview-gallery-container" class="pswp-gallery">
                                    <div class="aspect-video bg-gray-200 rounded-lg overflow-hidden mb-4 shadow-inner"></div>
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
                                    <a href="#" id="preview-sidebar-category" class="preview-value text-pink-600 hover:underline text-right"></a>
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
                                <div id="preview-sidebar-source-info" class="mt-1"></div>
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
            <div class="p-6 text-sm text-gray-600 space-y-2">
                <p class="font-semibold">DO NOT upload any of the following items - breaking these rules will cause your file to be deleted without notice:</p>
                <ul class="list-disc list-inside space-y-2">
                    <li>Any files besides .zip, .rar, .7z and .oiv archives.</li>
                    <li>Archives that do not contain a mod, or are part of other mods or mod packs.</li>
                    <li>Any archive containing only original game files.</li>
                    <li>Any file that can be used for cheating online.</li>
                    <li>Any file containing or giving access to pirated or otherwise copyrighted content.</li>
                    <li>Files containing malware or any .EXE file with a positive anti-virus result.</li>
                    <li>Any file containing nude or semi-nude pornographic images.</li>
                    <li>Any file containing a political or ideology theme that may cause debates.</li>
                    <li>Files that do not contain simple installation instructions, with an exception for tools.</li>
                </ul>
                <p>For a full list of our rules and regulations please see: <a href="{{ $rulesUrl }}" class="text-pink-600 hover:underline font-semibold">Rules and Regulations</a>.</p>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script type="module">
        import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5/dist/photoswipe-lightbox.esm.js';

        document.addEventListener('DOMContentLoaded', () => {
            const categorySelect = document.getElementById('category');
            const selectedCategoryInput = document.getElementById('selected-category');
            const headerNavLinks = Array.from(document.querySelectorAll('.header-nav-bar nav a'));

            headerNavLinks.forEach((link) => {
                if (link.dataset.slug) {
                    return;
                }

                try {
                    const parsed = new URL(link.href, window.location.origin);
                    const slugParam = parsed.searchParams.get('category');
                    if (slugParam) {
                        link.dataset.slug = slugParam;
                        return;
                    }
                } catch (error) {
                    // ignore parsing failures for relative links
                }

                link.dataset.slug = link.textContent.trim().toLowerCase();
            });

            const authorsContainer = document.getElementById('authors-container');
            const addAuthorBtn = document.getElementById('add-author-btn');
            const initialAuthors = JSON.parse(authorsContainer.dataset.initial || '[]');

            const descriptionPlain = document.getElementById('description_plain');
            const descriptionInput = document.getElementById('description');

            const screenshotInput = document.getElementById('screenshot-upload');
            const dropzoneLabel = document.getElementById('dropzone-label');
            const imagePreviewContainer = document.getElementById('image-preview-container');
            const heroTokenInput = document.getElementById('hero_image_token');

            const galleryTokenContainer = document.getElementById('gallery-token-container');

            const continueBtn = document.getElementById('continue-btn');
            const backBtn = document.getElementById('back-btn');
            const previewBtn = document.getElementById('preview-btn');
            const backToStep2Btn = document.getElementById('back-to-step-2-btn');

            const formStep1 = document.getElementById('form-step-1');
            const formStep2 = document.getElementById('form-step-2');
            const formStep3 = document.getElementById('form-step-3');
            const step1ErrorsContainer = document.getElementById('step-1-errors');
            const step2ErrorsContainer = document.getElementById('step-2-errors');
            const previewBanner = document.getElementById('preview-mode-banner');
            const formCard = document.getElementById('form-card');
            const mainTitle = document.getElementById('main-title');
            const uploadRules = document.getElementById('upload-rules');

            const fileUploadView = document.getElementById('file-upload-view');
            const urlView = document.getElementById('url-view');
            const showUrlViewBtn = document.getElementById('show-url-view-btn');
            const showFileViewBtn = document.getElementById('show-file-view-btn');
            const modFileInput = document.getElementById('mod-file-input');
            const modDropzoneLabel = document.getElementById('mod-dropzone-label');
            const modDropzoneContent = document.getElementById('mod-dropzone-content');
            const modFilePreview = document.getElementById('mod-file-preview');
            const modFileTokenInput = document.getElementById('mod_file_token');
            const modUrlInput = document.getElementById('mod-url-input');
            const fileSizeInput = document.getElementById('file-size-input');
            const fileSizeUnit = document.getElementById('file-size-unit');
            const versionInput = document.getElementById('version');
            const fileNameInput = document.getElementById('file-name');
            const tagsInput = document.getElementById('tags');

            const previewHeader = document.getElementById('preview-header');
            const previewDescriptionContent = document.getElementById('preview-description-content');
            const previewSidebarAuthors = document.getElementById('preview-sidebar-authors');
            const previewSidebarVersion = document.getElementById('preview-sidebar-version');
            const previewSidebarUpdated = document.getElementById('preview-sidebar-updated');
            const previewSidebarCategory = document.getElementById('preview-sidebar-category');
            const previewSidebarTags = document.getElementById('preview-sidebar-tags');
            const previewSidebarSourceInfo = document.getElementById('preview-sidebar-source-info');
            const previewGalleryContainer = document.getElementById('preview-gallery-container');
            const previewGalleryFeatured = previewGalleryContainer.querySelector('.aspect-video');
            const previewGalleryThumbnails = document.getElementById('preview-gallery-thumbnails');
            const previewLoadMoreContainer = document.getElementById('preview-load-more-container');

            let pswpLightbox = null;

            const chunkEndpoint = @json(route('mods.uploads.chunk'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const chunkSize = 512 * 1024;
            const MAX_GALLERY_ITEMS = 12;

            let galleryItems = [];
            let activeUploads = 0;

            const beginUpload = () => {
                activeUploads += 1;
            };

            const finishUpload = () => {
                activeUploads = Math.max(0, activeUploads - 1);
            };

            const parseFileSize = () => {
                const rawValue = parseFloat(fileSizeInput.value);
                if (Number.isNaN(rawValue) || rawValue <= 0) {
                    return null;
                }

                const unit = fileSizeUnit.value;
                const sizeInMb = unit === 'GB' ? rawValue * 1024 : rawValue;

                return {
                    raw: rawValue,
                    unit,
                    mb: sizeInMb,
                };
            };

            const ensureFileSizeConverted = () => {
                const parsed = parseFileSize();
                if (!parsed) {
                    return;
                }

                fileSizeInput.value = parsed.mb.toFixed(2);
                fileSizeUnit.value = 'MB';
            };

            const ensureDescriptionSync = () => {
                const value = descriptionPlain.value || '';
                const trimmed = value.trim();

                if (!trimmed) {
                    descriptionInput.value = '';
                    return;
                }

                const paragraphs = trimmed
                    .replace(/\r/g, '')
                    .split(/\n{2,}/)
                    .map((paragraph) => {
                        const sanitized = paragraph
                            .replace(/<(?!\/?(b|i|u|ul|ol|li|br)\b)[^>]*>/gi, '')
                            .replace(/\n/g, '<br>')
                            .trim();

                        if (!sanitized) {
                            return null;
                        }

                        return {
                            type: 'paragraph',
                            data: {
                                text: sanitized,
                            },
                        };
                    })
                    .filter(Boolean);

                const payload = {
                    time: Date.now(),
                    blocks: paragraphs,
                    version: '2.28.2',
                };

                descriptionInput.value = JSON.stringify(payload);
            };

            descriptionPlain.addEventListener('input', () => {
                descriptionPlain.style.height = 'auto';
                descriptionPlain.style.height = `${descriptionPlain.scrollHeight}px`;
                ensureDescriptionSync();
            });
            descriptionPlain.dispatchEvent(new Event('input'));

            const createAuthorInput = (value = '') => {
                const wrapper = document.createElement('div');
                wrapper.className = 'flex items-center space-x-2';

                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'authors[]';
                input.className = 'form-input';
                input.placeholder = 'Enter author name';
                input.value = value;

                wrapper.appendChild(input);

                if (authorsContainer.children.length > 0 || value) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'text-gray-400 hover:text-red-500 transition-colors';
                    removeBtn.innerHTML = '<i class="fas fa-times-circle"></i>';
                    removeBtn.addEventListener('click', () => {
                        wrapper.remove();
                    });
                    wrapper.appendChild(removeBtn);
                }

                authorsContainer.appendChild(wrapper);
            };

            if (!initialAuthors.length) {
                createAuthorInput('');
            } else {
                initialAuthors.forEach((name, index) => {
                    createAuthorInput(name);
                    if (index === 0 && !name) {
                        authorsContainer.firstElementChild?.querySelector('input')?.focus();
                    }
                });
            }

            addAuthorBtn.addEventListener('click', () => createAuthorInput(''));

            const updateCategorySelection = (categoryId) => {
                selectedCategoryInput.value = categoryId || '';

                headerNavLinks.forEach((link) => {
                    const slug = link.dataset.category || link.dataset.slug;
                    const isActive = slug && categorySelect.selectedOptions[0]?.dataset.slug === slug;
                    link.classList.toggle('category-active', isActive);
                    link.classList.toggle('category-dimmed', Boolean(categoryId) && !isActive);
                });
            };

            categorySelect.addEventListener('change', () => {
                const selectedOption = categorySelect.selectedOptions[0];
                const value = selectedOption ? selectedOption.value : '';
                updateCategorySelection(value);
            });

            headerNavLinks.forEach((link) => {
                const slug = link.dataset.category || link.dataset.slug;
                if (!slug) {
                    return;
                }

                link.addEventListener('click', (event) => {
                    event.preventDefault();

                    const option = Array.from(categorySelect.options).find((opt) => opt.dataset.slug === slug);
                    if (option) {
                        categorySelect.value = option.value;
                        updateCategorySelection(option.value);
                    }
                });
            });

            updateCategorySelection(categorySelect.value);

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

            const syncGalleryTokenOrder = () => {
                const inputs = new Map(
                    Array.from(galleryTokenContainer.querySelectorAll('input[name="gallery_image_tokens[]"]')).map((input) => [
                        input.dataset.token,
                        input,
                    ]),
                );

                galleryItems.forEach((item) => {
                    if (!item.galleryToken) {
                        return;
                    }

                    const existing = inputs.get(item.galleryToken);
                    if (existing) {
                        galleryTokenContainer.appendChild(existing);
                    }
                });
            };

            const uploadFileInChunks = async (file, category, onProgress = () => {}) => {
                const totalChunks = Math.max(Math.ceil(file.size / chunkSize), 1);
                const uploadToken = crypto.randomUUID();
                let result = null;

                for (let index = 0; index < totalChunks; index += 1) {
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
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(errorText || 'Upload failed');
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

            const renderScreenshotPreviews = () => {
                imagePreviewContainer.innerHTML = '';

                galleryItems.forEach((item, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'relative group aspect-video rounded-lg cursor-grab w-[calc(50%-0.5rem)]';
                    wrapper.draggable = true;
                    wrapper.dataset.index = index;

                    const img = document.createElement('img');
                    img.src = item.previewUrl;
                    img.alt = item.file.name;
                    img.className = 'w-full h-full object-cover rounded-md pointer-events-none';
                    wrapper.appendChild(img);

                    const numberBadge = document.createElement('span');
                    numberBadge.className = 'absolute top-2 left-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white text-xs font-bold z-10 pointer-events-none';
                    numberBadge.textContent = index + 1;
                    wrapper.appendChild(numberBadge);

                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'absolute top-2 right-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white hover:bg-red-500 transition-colors z-10';
                    deleteBtn.innerHTML = '<i class="fas fa-times text-sm"></i>';
                    deleteBtn.addEventListener('click', (event) => {
                        event.stopPropagation();
                        const [removed] = galleryItems.splice(index, 1);
                        if (removed?.galleryToken) {
                            removeGalleryToken(removed.galleryToken);
                        }
                        if (heroTokenInput.value === removed?.heroToken) {
                            heroTokenInput.value = '';
                        }
                        URL.revokeObjectURL(removed.previewUrl);
                        if (removed?.isFeatured && galleryItems.length) {
                            galleryItems.forEach((entry, entryIndex) => {
                                entry.isFeatured = entryIndex === 0;
                            });

                            const nextFeatured = galleryItems[0];
                            if (nextFeatured.heroToken) {
                                heroTokenInput.value = nextFeatured.heroToken;
                            } else {
                                setFeaturedHero(nextFeatured).catch((error) => {
                                    console.error(error);
                                    alert('Unable to set featured image.');
                                    renderScreenshotPreviews();
                                });
                            }
                        }

                        renderScreenshotPreviews();
                    });
                    wrapper.appendChild(deleteBtn);

                    const radioLabel = document.createElement('label');
                    radioLabel.className = 'absolute bottom-2 right-2 flex items-center p-1.5 bg-black/60 rounded-full cursor-pointer text-white text-xs backdrop-blur-sm transition-all';

                    const radioInput = document.createElement('input');
                    radioInput.type = 'radio';
                    radioInput.name = 'featured_image';
                    radioInput.className = 'hidden peer';
                    radioInput.checked = item.isFeatured;
                    radioInput.addEventListener('change', () => {
                        galleryItems.forEach((entry) => {
                            entry.isFeatured = entry === item;
                        });
                        setFeaturedHero(item).catch((error) => {
                            console.error(error);
                            alert('Unable to set featured image.');
                        });
                        renderScreenshotPreviews();
                    });

                    const customRadio = document.createElement('span');
                    customRadio.className = 'w-4 h-4 rounded-full border-2 border-white flex-shrink-0 mr-1.5 peer-checked:bg-pink-500 peer-checked:border-pink-500 transition-colors duration-200';

                    radioLabel.appendChild(radioInput);
                    radioLabel.appendChild(customRadio);
                    radioLabel.appendChild(document.createTextNode('Featured'));
                    wrapper.appendChild(radioLabel);

                    if (item.isFeatured) {
                        wrapper.classList.add('ring-2', 'ring-pink-500', 'ring-offset-2', 'ring-offset-white');
                    }

                    imagePreviewContainer.appendChild(wrapper);
                });

                syncGalleryTokenOrder();
            };

            let draggedIndex = null;

            imagePreviewContainer.addEventListener('dragstart', (event) => {
                const target = event.target.closest('[draggable="true"]');
                if (!target) {
                    return;
                }
                draggedIndex = Number(target.dataset.index);
                setTimeout(() => target.classList.add('dragging'), 0);
            });

            imagePreviewContainer.addEventListener('dragend', (event) => {
                const target = event.target.closest('[draggable="true"]');
                if (target) {
                    target.classList.remove('dragging');
                }
                document.querySelectorAll('.drag-over').forEach((element) => element.classList.remove('drag-over'));
                draggedIndex = null;
            });

            imagePreviewContainer.addEventListener('dragover', (event) => {
                event.preventDefault();
                const target = event.target.closest('[draggable="true"]');
                document.querySelectorAll('.drag-over').forEach((element) => element.classList.remove('drag-over'));
                if (target) {
                    target.classList.add('drag-over');
                }
            });

            imagePreviewContainer.addEventListener('drop', (event) => {
                event.preventDefault();
                document.querySelectorAll('.drag-over').forEach((element) => element.classList.remove('drag-over'));
                const target = event.target.closest('[draggable="true"]');
                if (!target || draggedIndex === null) {
                    return;
                }

                const targetIndex = Number(target.dataset.index);
                const [moved] = galleryItems.splice(draggedIndex, 1);
                galleryItems.splice(targetIndex, 0, moved);
                renderScreenshotPreviews();
            });

            const setFeaturedHero = async (item) => {
                if (item.heroToken) {
                    heroTokenInput.value = item.heroToken;
                    return;
                }

                beginUpload();
                try {
                    const result = await uploadFileInChunks(item.file, 'hero_image');
                    item.heroToken = result.upload_token;
                    heroTokenInput.value = result.upload_token;
                } catch (error) {
                    heroTokenInput.value = '';
                    item.isFeatured = false;
                    throw error;
                } finally {
                    finishUpload();
                }
            };

            const handleScreenshotFiles = (files) => {
                const imageFiles = Array.from(files).filter((file) => file.type.startsWith('image/'));
                if (!imageFiles.length) {
                    return;
                }

                const slotsAvailable = MAX_GALLERY_ITEMS - galleryItems.length;
                const filesToAdd = imageFiles.slice(0, Math.max(slotsAvailable, 0));

                filesToAdd.forEach((file) => {
                    const previewUrl = URL.createObjectURL(file);
                    const item = {
                        file,
                        previewUrl,
                        galleryToken: null,
                        heroToken: null,
                        isFeatured: galleryItems.length === 0,
                    };
                    galleryItems.push(item);
                    beginUpload();

                    uploadFileInChunks(file, 'gallery_image')
                        .then((result) => {
                            item.galleryToken = result.upload_token;
                            addGalleryToken(result.upload_token);
                            if (item.isFeatured && !heroTokenInput.value) {
                                return setFeaturedHero(item);
                            }
                            return null;
                        })
                        .catch((error) => {
                            console.error(error);
                            alert('Screenshot upload failed. Please try again.');
                            const index = galleryItems.indexOf(item);
                            if (index !== -1) {
                                galleryItems.splice(index, 1);
                            }
                            if (item.galleryToken) {
                                removeGalleryToken(item.galleryToken);
                            }
                            URL.revokeObjectURL(previewUrl);
                        })
                        .finally(() => {
                            finishUpload();
                            renderScreenshotPreviews();
                        });
                });

                renderScreenshotPreviews();
            };

            screenshotInput.addEventListener('change', (event) => {
                handleScreenshotFiles(event.target.files);
                screenshotInput.value = '';
            });

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evt) => {
                dropzoneLabel.addEventListener(evt, (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                });
            });

            ['dragenter', 'dragover'].forEach((evt) => {
                dropzoneLabel.addEventListener(evt, () => dropzoneLabel.classList.add('bg-pink-50', 'border-pink-400'));
            });

            ['dragleave', 'drop'].forEach((evt) => {
                dropzoneLabel.addEventListener(evt, () => dropzoneLabel.classList.remove('bg-pink-50', 'border-pink-400'));
            });

            dropzoneLabel.addEventListener('drop', (event) => {
                handleScreenshotFiles(event.dataTransfer.files);
            });

            const showStep = (stepNumber) => {
                formStep1.classList.toggle('hidden', stepNumber !== 1);
                formStep2.classList.toggle('hidden', stepNumber !== 2);
                formStep3.classList.toggle('hidden', stepNumber !== 3);
            };

            continueBtn.addEventListener('click', () => {
                step1ErrorsContainer.innerHTML = '';
                const errors = [];

                if (!fileNameInput.value.trim()) {
                    errors.push('The "File Name" field is required.');
                }

                if (!selectedCategoryInput.value) {
                    errors.push('Please select a category.');
                }

                ensureDescriptionSync();
                if (!descriptionInput.value) {
                    errors.push('The description cannot be empty.');
                }

                if (!galleryItems.length) {
                    errors.push('Please upload at least one screenshot.');
                }

                if (errors.length) {
                    const list = document.createElement('ul');
                    list.className = 'list-disc list-inside text-sm text-red-600 bg-red-100 p-4 rounded-lg';
                    errors.forEach((message) => {
                        const item = document.createElement('li');
                        item.textContent = message;
                        list.appendChild(item);
                    });
                    step1ErrorsContainer.appendChild(list);
                    return;
                }

                showStep(2);
            });

            backBtn.addEventListener('click', () => showStep(1));

            const formatFileSize = (bytes) => {
                if (!bytes) {
                    return '0 MB';
                }
                const mb = bytes / (1024 * 1024);
                if (mb >= 1024) {
                    return `${(mb / 1024).toFixed(2)} GB`;
                }
                return `${mb.toFixed(2)} MB`;
            };

            const handleModFileSelection = (file) => {
                if (!file) {
                    return;
                }

                modDropzoneContent.classList.add('hidden');
                modFilePreview.classList.remove('hidden');
                modFilePreview.classList.add('flex');

                modFilePreview.innerHTML = `
                    <div class="flex flex-col items-center">
                        <i class="fas fa-check-circle text-green-500 text-4xl"></i>
                        <p class="font-semibold mt-2 break-all">${file.name}</p>
                        <p class="text-sm text-gray-500">${formatFileSize(file.size)}</p>
                        <button type="button" id="remove-mod-file-btn" class="mt-2 text-sm font-semibold text-red-600 hover:text-red-800 transition">Remove</button>
                    </div>
                `;

                const removeBtn = modFilePreview.querySelector('#remove-mod-file-btn');
                removeBtn.addEventListener('click', () => {
                    modFileInput.value = '';
                    modFilePreview.classList.add('hidden');
                    modFilePreview.classList.remove('flex');
                    modDropzoneContent.classList.remove('hidden');
                    modFileTokenInput.value = '';
                });

                beginUpload();
                uploadFileInChunks(file, 'mod_archive', ({ index, total }) => {
                    const percent = Math.round((index / total) * 100);
                    modFilePreview.querySelector('p.text-sm').textContent = `${formatFileSize(file.size)} · ${percent}%`;
                })
                    .then((result) => {
                        modFileTokenInput.value = result.upload_token;
                        fileSizeInput.value = result.size_mb.toFixed(2);
                        fileSizeUnit.value = 'MB';
                    })
                    .catch((error) => {
                        console.error(error);
                        alert('Mod archive upload failed. Please try again.');
                        modFileTokenInput.value = '';
                        modFilePreview.classList.add('hidden');
                        modFilePreview.classList.remove('flex');
                        modDropzoneContent.classList.remove('hidden');
                    })
                    .finally(() => {
                        finishUpload();
                        modFileInput.value = '';
                    });
            };

            modFileInput.addEventListener('change', (event) => {
                const [file] = event.target.files;
                if (file) {
                    handleModFileSelection(file);
                }
            });

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evt) => {
                modDropzoneLabel.addEventListener(evt, (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                });
            });

            ['dragenter', 'dragover'].forEach((evt) => {
                modDropzoneLabel.addEventListener(evt, () => modDropzoneLabel.classList.add('bg-pink-50', 'border-pink-400'));
            });

            ['dragleave', 'drop'].forEach((evt) => {
                modDropzoneLabel.addEventListener(evt, () => modDropzoneLabel.classList.remove('bg-pink-50', 'border-pink-400'));
            });

            modDropzoneLabel.addEventListener('drop', (event) => {
                handleModFileSelection(event.dataTransfer.files[0]);
            });

            showUrlViewBtn.addEventListener('click', () => {
                fileUploadView.classList.add('hidden');
                urlView.classList.remove('hidden');
                modFileTokenInput.value = '';
            });

            showFileViewBtn.addEventListener('click', () => {
                urlView.classList.add('hidden');
                fileUploadView.classList.remove('hidden');
                modUrlInput.value = '';
                fileSizeInput.value = '';
            });

            const sanitizeHtml = (html) => {
                const div = document.createElement('div');
                div.textContent = html;
                return div.innerHTML;
            };

            const populatePreview = () => {
                ensureDescriptionSync();

                const title = fileNameInput.value.trim();
                const version = versionInput.value.trim();
                const authors = Array.from(authorsContainer.querySelectorAll('input[name="authors[]"]'))
                    .map((input) => input.value.trim())
                    .filter(Boolean);
                const tags = tagsInput.value
                    .split(',')
                    .map((tag) => tag.trim())
                    .filter(Boolean);

                const selectedOption = categorySelect.selectedOptions[0];
                const categoryLabel = selectedOption ? selectedOption.textContent.trim() : 'Uncategorised';

                previewHeader.innerHTML = `
                    <div class="flex items-center flex-wrap gap-3">
                        <h1 class="text-2xl md:text-4xl font-bold text-gray-900">${sanitizeHtml(title || 'Untitled mod')}</h1>
                        <span class="text-xl md:text-2xl font-semibold text-gray-400">${sanitizeHtml(version || '1.0.0')}</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-500 mt-1">
                        <span>by</span>
                        <span class="font-semibold text-pink-600 ml-1">${sanitizeHtml(authors.join(', ') || 'Unknown author')}</span>
                    </div>
                `;

                const descriptionValue = descriptionInput.value ? JSON.parse(descriptionInput.value) : null;
                if (descriptionValue?.blocks?.length) {
                    const content = descriptionValue.blocks
                        .map((block) => block.data?.text || '')
                        .join('<br><br>');
                    previewDescriptionContent.innerHTML = content;
                } else {
                    previewDescriptionContent.innerHTML = '<p class="text-gray-500">No description provided.</p>';
                }

                previewSidebarAuthors.textContent = authors.join(', ') || 'Unknown author';
                previewSidebarVersion.textContent = version || '1.0.0';
                previewSidebarUpdated.textContent = new Date().toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                });
                previewSidebarCategory.textContent = categoryLabel;

                previewSidebarTags.innerHTML = '';
                if (tags.length) {
                    tags.forEach((tag) => {
                        const element = document.createElement('span');
                        element.className = 'bg-gray-200 text-gray-700 text-xs font-semibold px-2.5 py-1 rounded-full';
                        element.textContent = tag;
                        previewSidebarTags.appendChild(element);
                    });
                } else {
                    previewSidebarTags.textContent = 'No tags provided.';
                }

                previewSidebarSourceInfo.innerHTML = '';
                if (!urlView.classList.contains('hidden')) {
                    const url = modUrlInput.value.trim();
                    const sizeInfo = parseFileSize();
                    const displayLabel = sizeInfo ? `${sizeInfo.raw} ${sizeInfo.unit}` : 'Unknown';
                    previewSidebarSourceInfo.innerHTML = `
                        <p class="preview-value font-mono text-sm bg-gray-200 p-2 rounded break-words">${sanitizeHtml(url)}</p>
                        <p class="text-right text-sm text-gray-600 mt-1">Size: <strong>${sanitizeHtml(displayLabel)}</strong></p>
                    `;
                } else if (modFileTokenInput.value) {
                    const sizeInfo = parseFileSize();
                    const displayLabel = sizeInfo ? `${sizeInfo.mb.toFixed(2)} MB` : (fileSizeInput.value ? `${fileSizeInput.value} MB` : 'Unknown');
                    previewSidebarSourceInfo.innerHTML = `
                        <p class="preview-value font-mono text-sm bg-gray-200 p-2 rounded break-words">Uploaded archive</p>
                        <p class="text-right text-sm text-gray-600 mt-1">Size: <strong>${sanitizeHtml(displayLabel)}</strong></p>
                    `;
                } else {
                    previewSidebarSourceInfo.innerHTML = '<p class="text-sm text-gray-500">No download source provided yet.</p>';
                }

                if (pswpLightbox) {
                    pswpLightbox.destroy();
                    pswpLightbox = null;
                }

                previewGalleryFeatured.innerHTML = '';
                previewGalleryThumbnails.innerHTML = '';
                previewLoadMoreContainer.innerHTML = '';

                if (!galleryItems.length) {
                    previewGalleryFeatured.innerHTML = '<div class="w-full h-full flex items-center justify-center text-gray-500">No screenshots available</div>';
                    return;
                }

                const loadImageDimensions = (item) => new Promise((resolve, reject) => {
                    const image = new Image();
                    image.onload = () => resolve({
                        width: image.naturalWidth,
                        height: image.naturalHeight,
                        url: item.previewUrl,
                        item,
                    });
                    image.onerror = reject;
                    image.src = item.previewUrl;
                });

                const sortedItems = [...galleryItems];
                const featuredItem = sortedItems.find((entry) => entry.isFeatured) || sortedItems[0];
                const otherItems = sortedItems.filter((entry) => entry !== featuredItem);

                Promise.all(sortedItems.map(loadImageDimensions))
                    .then((details) => {
                        const featuredDetails = details.find((detail) => detail.item === featuredItem);
                        if (featuredDetails) {
                            const link = document.createElement('a');
                            link.href = featuredDetails.url;
                            link.dataset.pswpWidth = featuredDetails.width;
                            link.dataset.pswpHeight = featuredDetails.height;
                            link.className = 'gallery-item block w-full h-full';

                            const img = document.createElement('img');
                            img.src = featuredDetails.url;
                            img.alt = 'Featured screenshot';
                            img.className = 'w-full h-full object-cover';
                            link.appendChild(img);
                            previewGalleryFeatured.appendChild(link);
                        }

                        otherItems.forEach((entry, index) => {
                            const detail = details.find((item) => item.item === entry);
                            if (!detail) {
                                return;
                            }

                            const thumbLink = document.createElement('a');
                            thumbLink.href = detail.url;
                            thumbLink.dataset.pswpWidth = detail.width;
                            thumbLink.dataset.pswpHeight = detail.height;
                            thumbLink.className = 'gallery-item relative aspect-video block';

                            if (index >= 4) {
                                thumbLink.classList.add('hidden', 'extra-thumbnail');
                            }

                            const thumbImage = document.createElement('img');
                            thumbImage.src = detail.url;
                            thumbImage.className = 'w-full h-full object-cover rounded-lg';
                            thumbLink.appendChild(thumbImage);
                            previewGalleryThumbnails.appendChild(thumbLink);
                        });

                        const hiddenThumbs = previewGalleryThumbnails.querySelectorAll('.extra-thumbnail');
                        if (hiddenThumbs.length) {
                            const loadMoreBtn = document.createElement('button');
                            loadMoreBtn.type = 'button';
                            loadMoreBtn.className = 'w-full py-2 px-4 rounded-lg border-2 border-pink-500 text-pink-600 font-semibold hover:bg-pink-50 transition duration-300 ease-in-out';
                            loadMoreBtn.innerHTML = `<i class="fas fa-images mr-2"></i>Load more images (${hiddenThumbs.length} more)`;
                            loadMoreBtn.addEventListener('click', () => {
                                hiddenThumbs.forEach((element) => element.classList.remove('hidden'));
                                loadMoreBtn.remove();
                            });
                            previewLoadMoreContainer.appendChild(loadMoreBtn);
                        }

                        pswpLightbox = new PhotoSwipeLightbox({
                            gallery: '#preview-gallery-container',
                            children: 'a.gallery-item',
                            pswpModule: () => import('https://unpkg.com/photoswipe@5/dist/photoswipe.esm.js'),
                        });
                        pswpLightbox.init();
                    })
                    .catch((error) => {
                        console.error('Error preparing gallery preview', error);
                    });
            };

            previewBtn.addEventListener('click', () => {
                step2ErrorsContainer.innerHTML = '';
                const errors = [];

                const urlMode = !urlView.classList.contains('hidden');

                if (urlMode) {
                    if (!modUrlInput.value.trim()) {
                        errors.push('Please provide a valid download URL.');
                    }

                    if (!parseFileSize()) {
                        errors.push('Please enter a valid file size.');
                    }
                } else if (!modFileTokenInput.value) {
                    errors.push('Please upload a mod archive.');
                }

                if (!versionInput.value.trim()) {
                    errors.push('The "Version" field is required.');
                }

                if (errors.length) {
                    const list = document.createElement('ul');
                    list.className = 'list-disc list-inside text-sm text-red-600 bg-red-100 p-4 rounded-lg';
                    errors.forEach((message) => {
                        const item = document.createElement('li');
                        item.textContent = message;
                        list.appendChild(item);
                    });
                    step2ErrorsContainer.appendChild(list);
                    return;
                }

                populatePreview();
                showStep(3);
                previewBanner.classList.remove('hidden');
                formCard.classList.remove('card', 'p-6', 'md:p-8');
                mainTitle.classList.add('hidden');
                uploadRules.classList.add('hidden');
            });

            backToStep2Btn.addEventListener('click', () => {
                showStep(2);
                previewBanner.classList.add('hidden');
                formCard.classList.add('card', 'p-6', 'md:p-8');
                mainTitle.classList.remove('hidden');
                uploadRules.classList.remove('hidden');
            });

            const form = document.getElementById('mod-upload-form');
            form.addEventListener('submit', (event) => {
                if (activeUploads > 0) {
                    event.preventDefault();
                    alert('Please wait for all uploads to finish before submitting.');
                    return;
                }

                if (!heroTokenInput.value && galleryItems.length) {
                    event.preventDefault();
                    alert('Please select a featured screenshot.');
                    return;
                }

                ensureFileSizeConverted();
            });

            showStep(1);
        });
    </script>
@endpush
