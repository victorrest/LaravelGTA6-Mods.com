@props(['modId'])

<div
    id="video-submit-modal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4"
    data-modal="video-submit-modal"
>
    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl transform transition-all" data-modal-content>
        {{-- Header --}}
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center">
                    <i class="fa-brands fa-youtube text-white text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">YouTube videó hozzáadása</h3>
                    <p class="text-sm text-gray-500">Oszd meg a moddal kapcsolatos videódat</p>
                </div>
            </div>
            <button
                type="button"
                data-modal-close
                class="text-gray-400 hover:text-gray-600 transition"
            >
                <i class="fa-solid fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Body --}}
        <form id="video-submit-form" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="mod_id" value="{{ $modId }}">

            <div>
                <label for="youtube_url" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fa-brands fa-youtube text-red-600 mr-2"></i>YouTube videó URL
                </label>
                <input
                    type="url"
                    id="youtube_url"
                    name="youtube_url"
                    placeholder="https://www.youtube.com/watch?v=..."
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition"
                    required
                >
                <p class="mt-2 text-xs text-gray-500">
                    <i class="fa-solid fa-info-circle mr-1"></i>
                    Csak publikus YouTube videók linkjét add meg. A videó automatikusan moderálásra kerül.
                </p>
            </div>

            {{-- Preview area (hidden by default) --}}
            <div id="video-preview" class="hidden">
                <div class="aspect-video rounded-lg overflow-hidden bg-gray-900">
                    <img id="video-thumbnail" src="" alt="Videó előnézet" class="w-full h-full object-cover">
                </div>
                <div id="video-info" class="mt-3 p-4 bg-gray-50 rounded-lg">
                    <h4 id="video-title" class="font-semibold text-gray-900"></h4>
                    <p id="video-description" class="text-sm text-gray-600 mt-1 line-clamp-2"></p>
                </div>
            </div>

            {{-- Info boxes --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <i class="fa-solid fa-circle-info text-blue-600 text-xl"></i>
                    </div>
                    <div class="text-sm text-blue-900">
                        <p class="font-semibold mb-1">Moderáció</p>
                        <p>A beküldött videó csak akkor jelenik meg nyilvánosan, miután egy moderátor jóváhagyja. Ez általában 24 órán belül megtörténik.</p>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-yellow-600 text-xl"></i>
                    </div>
                    <div class="text-sm text-yellow-900">
                        <p class="font-semibold mb-1">Szabályok</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Csak a moddal kapcsolatos tartalom</li>
                            <li>Napi maximum 3 videó beküldése</li>
                            <li>Nem megfelelő tartalom törlésre kerül</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Error message --}}
            <div id="video-submit-error" class="hidden bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <i class="fa-solid fa-circle-exclamation text-red-600 text-xl"></i>
                    </div>
                    <div class="text-sm text-red-900">
                        <p id="error-message" class="font-semibold"></p>
                    </div>
                </div>
            </div>

            {{-- Footer actions --}}
            <div class="flex items-center justify-end gap-3 pt-4 border-t">
                <button
                    type="button"
                    data-modal-close
                    class="px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition"
                >
                    Mégse
                </button>
                <button
                    type="submit"
                    id="submit-video-btn"
                    class="px-6 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fa-solid fa-paper-plane mr-2"></i>
                    <span>Videó beküldése</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('video-submit-modal');
    const form = document.getElementById('video-submit-form');
    const urlInput = document.getElementById('youtube_url');
    const submitBtn = document.getElementById('submit-video-btn');
    const errorDiv = document.getElementById('video-submit-error');
    const errorMessage = document.getElementById('error-message');

    // Modal triggers
    document.querySelectorAll('[data-modal-trigger="video-submit-modal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        });
    });

    // Modal close
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
            form.reset();
            errorDiv.classList.add('hidden');
        });
    });

    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }
    });

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i><span>Beküldés...</span>';
        errorDiv.classList.add('hidden');

        try {
            const response = await fetch('/api/videos/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok) {
                alert(result.message);
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
                form.reset();
                location.reload(); // Reload to show the pending video
            } else {
                errorMessage.textContent = result.message || 'Hiba történt a videó beküldésekor.';
                errorDiv.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error:', error);
            errorMessage.textContent = 'Hálózati hiba történt. Próbáld újra később.';
            errorDiv.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i><span>Videó beküldése</span>';
        }
    });

    // Optional: YouTube URL preview
    urlInput.addEventListener('blur', function() {
        const url = this.value.trim();
        if (url && (url.includes('youtube.com') || url.includes('youtu.be'))) {
            // Extract video ID and show thumbnail preview
            const videoId = extractYouTubeId(url);
            if (videoId) {
                const preview = document.getElementById('video-preview');
                const thumbnail = document.getElementById('video-thumbnail');
                thumbnail.src = `https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`;
                preview.classList.remove('hidden');
            }
        }
    });
});

function extractYouTubeId(url) {
    const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
    const match = url.match(regExp);
    return (match && match[7].length === 11) ? match[7] : null;
}
</script>
@endpush
