@extends('layouts.app', ['title' => 'Submit Update - ' . $mod->title])

@section('content')
<div class="container mx-auto px-4 py-6 lg:py-8">
    {{-- Page Header --}}
    <div class="mb-6">
        <nav class="text-sm text-gray-500 mb-3" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-pink-600">Home</a>
            <span class="mx-2">&raquo;</span>
            <a href="{{ route('mods.show', [$mod->primary_category ?? $mod->categories->first(), $mod]) }}" class="hover:text-pink-600">{{ $mod->title }}</a>
            <span class="mx-2">&raquo;</span>
            <span class="text-gray-700 font-semibold">Submit Update</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-900">Submit Update for: {{ $mod->title }}</h1>
        <p class="text-gray-600 mt-2">Upload a new version of your mod. Updates will be reviewed by moderators before becoming public.</p>
    </div>

    {{-- Info Notice --}}
    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-900 rounded-lg">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle mt-1 text-blue-500" aria-hidden="true"></i>
            <div>
                <p class="font-semibold">Important Notes:</p>
                <ul class="text-sm mt-2 space-y-1 list-disc list-inside">
                    <li>Your update will be reviewed by moderators before it becomes publicly visible</li>
                    <li>Make sure the version number is higher than the current version</li>
                    <li>Provide detailed changelog information to help users understand what's new</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Update Form --}}
    <div class="card p-6 md:p-8">
        <form method="POST" action="{{ route('mods.version.store', $mod) }}" enctype="multipart/form-data" id="version-submit-form">
            @csrf

            {{-- Version Number --}}
            <div class="mb-6">
                <label for="version_number" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-code-branch text-pink-600 mr-2"></i>
                    New Version Number <span class="text-red-500">*</span>
                </label>
                <input type="text" id="version_number" name="version_number" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition"
                       placeholder="e.g., 1.1, 2.0.1"
                       value="{{ old('version_number') }}">
                <p class="text-sm text-gray-500 mt-1">Version number must be unique and should be higher than the current version</p>
                @error('version_number')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- File Upload Method Toggle --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    <i class="fas fa-file-archive text-pink-600 mr-2"></i>
                    Mod File <span class="text-red-500">*</span>
                </label>

                <div class="flex items-center gap-4 mb-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="upload_method" value="file" checked class="mr-2" onchange="toggleUploadMethod('file')">
                        <span class="text-sm font-medium text-gray-700">Upload File</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="upload_method" value="url" class="mr-2" onchange="toggleUploadMethod('url')">
                        <span class="text-sm font-medium text-gray-700">External URL</span>
                    </label>
                </div>

                {{-- File Upload --}}
                <div id="file-upload-section">
                    <input type="file" id="mod_file" name="mod_file" accept=".zip,.rar,.7z"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                    <p class="text-sm text-gray-500 mt-2">Allowed: .zip, .rar, .7z (max. 100MB)</p>
                </div>

                {{-- URL Input --}}
                <div id="url-upload-section" class="hidden">
                    <input type="url" id="download_url" name="download_url"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition"
                           placeholder="https://example.com/your-mod-file.zip"
                           value="{{ old('download_url') }}">
                    <p class="text-sm text-gray-500 mt-2">Provide a direct download link to your mod file</p>
                </div>

                @error('mod_file')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
                @error('download_url')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Changelog --}}
            <div class="mb-6">
                <label for="changelog" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-list-ul text-pink-600 mr-2"></i>
                    Changelog <span class="text-gray-400 text-xs">(Optional)</span>
                </label>
                <textarea id="changelog" name="changelog" rows="6"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition resize-none"
                          placeholder="What's new in this version? e.g.:&#10;- Fixed handling issues&#10;- Added new paint jobs&#10;- Improved performance">{{ old('changelog') }}</textarea>
                <p class="text-sm text-gray-500 mt-1">Describe what changed in this version. Be specific to help users understand the updates.</p>
                @error('changelog')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-4 pt-4 border-t border-gray-200">
                <button type="submit" class="btn-download font-bold py-3 px-8 rounded-lg transition inline-flex items-center">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Submit Update
                </button>
                <a href="{{ route('mods.show', [$mod->primary_category ?? $mod->categories->first(), $mod]) }}"
                   class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    {{-- Help Section --}}
    <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
        <h3 class="font-semibold text-gray-900 mb-2">Need Help?</h3>
        <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
            <li>Version numbers should follow semantic versioning (e.g., 1.0, 1.1, 2.0)</li>
            <li>File size limit is 100MB. For larger files, use an external hosting service</li>
            <li>Updates are reviewed to ensure quality and safety</li>
            <li>You'll be notified once your update is approved or if changes are needed</li>
        </ul>
    </div>
</div>

@push('scripts')
<script>
function toggleUploadMethod(method) {
    const fileSection = document.getElementById('file-upload-section');
    const urlSection = document.getElementById('url-upload-section');
    const fileInput = document.getElementById('mod_file');
    const urlInput = document.getElementById('download_url');

    if (method === 'file') {
        fileSection.classList.remove('hidden');
        urlSection.classList.add('hidden');
        fileInput.required = true;
        urlInput.required = false;
        urlInput.value = '';
    } else {
        fileSection.classList.add('hidden');
        urlSection.classList.remove('hidden');
        fileInput.required = false;
        urlInput.required = true;
        fileInput.value = '';
    }
}

// Form validation
document.getElementById('version-submit-form').addEventListener('submit', function(e) {
    const versionInput = document.getElementById('version_number');
    const version = versionInput.value.trim();

    if (!version) {
        e.preventDefault();
        alert('Please enter a version number');
        versionInput.focus();
        return false;
    }

    // Basic version format validation
    if (!/^[\d.]+$/.test(version)) {
        e.preventDefault();
        alert('Version number should only contain numbers and dots (e.g., 1.0, 2.1.5)');
        versionInput.focus();
        return false;
    }

    // Check upload method
    const uploadMethod = document.querySelector('input[name="upload_method"]:checked').value;
    const fileInput = document.getElementById('mod_file');
    const urlInput = document.getElementById('download_url');

    if (uploadMethod === 'file' && !fileInput.files.length) {
        e.preventDefault();
        alert('Please select a file to upload');
        return false;
    }

    if (uploadMethod === 'url' && !urlInput.value.trim()) {
        e.preventDefault();
        alert('Please provide a download URL');
        urlInput.focus();
        return false;
    }

    return true;
});
</script>
@endpush
@endsection
