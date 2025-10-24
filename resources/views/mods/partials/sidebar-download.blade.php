{{-- Sidebar Download Card --}}
<div class="card">
    <div class="p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">Mod Info</h2>

        {{-- Download Button --}}
        <form method="POST" action="{{ route('mods.download', [$mod->primary_category ?? $mod->categories->first(), $mod]) }}" class="w-full">
            @csrf
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-pink-600 text-white font-semibold rounded-xl shadow hover:bg-pink-700 transition" {{ $downloadUrl === '#' ? 'disabled' : '' }}>
                <i class="fa-solid fa-download mr-2"></i>Download Now
            </button>
        </form>

        {{-- Pin Button (if owner) --}}
        @if($canManagePin ?? false)
            <button
                type="button"
                id="pin-mod-btn"
                data-pin-url="{{ route('profile.mod.pin', $mod) }}"
                data-unpin-url="{{ route('profile.mod.unpin') }}"
                data-is-pinned="{{ ($isPinnedByOwner ?? false) ? '1' : '0' }}"
                class="w-full inline-flex items-center justify-center px-4 py-2 {{ ($isPinnedByOwner ?? false) ? 'bg-purple-600 hover:bg-purple-700' : 'bg-gray-600 hover:bg-gray-700' }} text-white text-sm font-semibold rounded-lg shadow transition"
            >
                <i class="fas fa-thumbtack mr-2 {{ ($isPinnedByOwner ?? false) ? '' : 'rotate-45' }}" data-pin-icon></i>
                <span data-pin-text>{{ ($isPinnedByOwner ?? false) ? 'Unpin from Profile' : 'Pin to Profile' }}</span>
            </button>
        @endif

        {{-- Mod Info Details --}}
        <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between">
                <span class="flex items-center text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2 text-gray-400"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M6 21V9a9 9 0 0 0 9 9"/></svg>
                    Version:
                </span>
                <span class="font-semibold text-gray-800">{{ $versionNumber ?? '—' }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="flex items-center text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2 text-gray-400"><path d="M10 12v-1"/><path d="M10 18v-2"/><path d="M10 7V6"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M15.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v16a2 2 0 0 0 .274 1.01"/><circle cx="10" cy="20" r="2"/></svg>
                    Size:
                </span>
                <span class="font-semibold text-gray-800">{{ $fileSize ?? '—' }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="flex items-center text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2 text-gray-400"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                    Uploaded:
                </span>
                <span class="font-semibold text-gray-800">{{ $metaDetails['uploaded_at'] ?? '—' }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="flex items-center text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2 text-gray-400"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                    Views:
                </span>
                <span class="font-semibold text-gray-800">{{ number_format($mod->views ?? 0) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="flex items-center text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2 text-gray-400"><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></svg>
                    Downloads:
                </span>
                <span class="font-semibold text-gray-800">{{ number_format($mod->downloads ?? 0) }}</span>
            </div>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="border-t border-gray-200"></div>
    <div class="p-4 space-y-4">
        <div class="grid grid-cols-3 gap-2">
            <button type="button" class="action-btn flex flex-col items-center justify-center py-2 rounded-md transition text-sm border border-gray-200 hover:bg-gray-100"
                    data-share-button title="Share this mod">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mb-1"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/></svg>
                <span>Share</span>
            </button>
            @auth
                <button type="button" class="action-btn flex flex-col items-center justify-center py-2 rounded-md transition text-sm border border-gray-200 hover:bg-gray-100"
                        data-modal-trigger="video-submit-modal" title="Add YouTube video">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mb-1"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                    <span>Add Video</span>
                </button>
            @else
                <button type="button" class="action-btn flex flex-col items-center justify-center py-2 rounded-md transition text-sm border border-gray-200 opacity-50 cursor-not-allowed" disabled title="Log in to add videos">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mb-1"><path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>
                    <span>Add Video</span>
                </button>
            @endauth
            <a href="#tab-comments" class="action-btn flex flex-col items-center justify-center py-2 rounded-md transition text-sm border border-gray-200 hover:bg-gray-100" title="View comments">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mb-1"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/></svg>
                <span>Comment</span>
            </a>
        </div>
    </div>
</div>
