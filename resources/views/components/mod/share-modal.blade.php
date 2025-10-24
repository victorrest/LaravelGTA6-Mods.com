@props(['mod', 'url'])

@php
    $shareUrl = $url ?? request()->url();
    $shareTitle = "Check out this awesome GTA 6 mod: {$mod->title}";
    $shareText = $shareTitle;
@endphp

{{-- Share Modal --}}
<div id="share-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 transition-opacity duration-300" data-modal="share-modal">
    <div class="relative w-full max-w-sm bg-white rounded-xl shadow-2xl transform transition-all" data-modal-content>
        {{-- Close Button --}}
        <button type="button" data-modal-close class="absolute top-3 right-3 text-gray-400 hover:text-gray-800 transition z-10">
            <i class="fas fa-times fa-lg"></i>
        </button>

        {{-- Header --}}
        <div class="p-6 text-center border-b border-gray-200">
            <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-share-nodes text-white text-xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Share this mod!</h3>
            <p class="text-gray-500 text-sm">Choose where you'd like to share</p>
        </div>

        {{-- Social Media Buttons --}}
        <div class="p-6">
            <div class="grid grid-cols-2 gap-3 mb-4">
                {{-- Facebook --}}
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="social-share-btn bg-[#1877F2] hover:bg-[#166FE5] text-white flex items-center justify-center p-3 rounded-lg font-semibold transition-all transform hover:scale-105">
                    <i class="fab fa-facebook-f mr-2"></i> Facebook
                </a>

                {{-- X / Twitter --}}
                <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($shareText) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="social-share-btn bg-black hover:bg-gray-800 text-white flex items-center justify-center p-3 rounded-lg font-semibold transition-all transform hover:scale-105">
                    <i class="fab fa-twitter mr-2"></i> X / Twitter
                </a>

                {{-- VK --}}
                <a href="https://vk.com/share.php?url={{ urlencode($shareUrl) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="social-share-btn bg-[#4680C2] hover:bg-[#3D73AF] text-white flex items-center justify-center p-3 rounded-lg font-semibold transition-all transform hover:scale-105">
                    <i class="fab fa-vk mr-2"></i> VK
                </a>

                {{-- Reddit --}}
                <a href="https://www.reddit.com/submit?url={{ urlencode($shareUrl) }}&title={{ urlencode($shareTitle) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="social-share-btn bg-[#FF4500] hover:bg-[#E63E00] text-white flex items-center justify-center p-3 rounded-lg font-semibold transition-all transform hover:scale-105">
                    <i class="fab fa-reddit-alien mr-2"></i> Reddit
                </a>

                {{-- WhatsApp --}}
                <a href="https://api.whatsapp.com/send?text={{ urlencode($shareText . ' ' . $shareUrl) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="social-share-btn bg-[#25D366] hover:bg-[#20BD5A] text-white flex items-center justify-center p-3 rounded-lg font-semibold transition-all transform hover:scale-105">
                    <i class="fab fa-whatsapp mr-2"></i> WhatsApp
                </a>

                {{-- Bluesky --}}
                <a href="https://bsky.app/intent/compose?text={{ urlencode($shareText . ' ' . $shareUrl) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="social-share-btn bg-[#0285FF] hover:bg-[#0275E6] text-white flex items-center justify-center p-3 rounded-lg font-semibold transition-all transform hover:scale-105">
                    <i class="fa-solid fa-square-poll-vertical mr-2"></i> Bluesky
                </a>
            </div>

            {{-- Copy Link Button --}}
            <button type="button" id="copy-link-btn" data-url="{{ $shareUrl }}"
                    class="w-full p-3 rounded-lg font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 transition flex items-center justify-center">
                <i class="fas fa-copy mr-2"></i>
                <span id="copy-link-text">Copy Link</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const shareModal = document.getElementById('share-modal');
    const copyLinkBtn = document.getElementById('copy-link-btn');
    const copyLinkText = document.getElementById('copy-link-text');

    // Open share modal
    document.querySelectorAll('[data-share-button]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            shareModal.classList.remove('hidden');
            shareModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close share modal
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            shareModal.classList.add('hidden');
            shareModal.classList.remove('flex');
            document.body.style.overflow = '';
        });
    });

    // Close on outside click
    shareModal.addEventListener('click', (e) => {
        if (e.target === shareModal) {
            shareModal.classList.add('hidden');
            shareModal.classList.remove('flex');
            document.body.style.overflow = '';
        }
    });

    // Copy link functionality
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', async function() {
            const url = this.dataset.url;

            try {
                // Try modern clipboard API first
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(url);
                } else {
                    // Fallback for older browsers
                    const tempInput = document.createElement('input');
                    tempInput.style.position = 'absolute';
                    tempInput.style.left = '-9999px';
                    tempInput.value = url;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                }

                // Update button text
                const originalText = copyLinkText.textContent;
                copyLinkText.textContent = 'Link Copied!';
                copyLinkBtn.classList.remove('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
                copyLinkBtn.classList.add('bg-green-200', 'text-green-800');

                setTimeout(() => {
                    copyLinkText.textContent = originalText;
                    copyLinkBtn.classList.remove('bg-green-200', 'text-green-800');
                    copyLinkBtn.classList.add('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy link:', err);
                alert('Failed to copy link. Please copy manually: ' + url);
            }
        });
    }
});
</script>
@endpush
