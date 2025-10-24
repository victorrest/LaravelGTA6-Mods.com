@props(['author'])

{{-- Author Card Component --}}
<div class="card">
    <div class="p-4">
        <div class="flex">
            {{-- Author Avatar --}}
            <img src="{{ $author->avatar_url ?? asset('images/default-avatar.png') }}"
                 class="rounded-full w-12 h-12 mr-4 object-cover"
                 alt="{{ $author->name }}'s avatar"
                 onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($author->name) }}&background=ec4899&color=fff'">

            {{-- Author Info --}}
            <div class="flex-grow">
                <div class="flex items-center space-x-2">
                    <p class="font-semibold text-gray-900">{{ $author->name }}</p>
                    @if($author->website_url)
                        <a href="{{ $author->website_url }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-pink-600 transition">
                            <i class="fas fa-home"></i>
                        </a>
                    @endif
                    @if($author->social_links['discord'] ?? null)
                        <a href="{{ $author->social_links['discord'] }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-pink-600 transition">
                            <i class="fab fa-discord"></i>
                        </a>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mb-2">
                    @if($author->is_admin)
                        Admin
                    @else
                        Member
                    @endif
                    · Joined {{ $author->created_at->format('M Y') }}
                </p>

                {{-- Author Actions --}}
                <div class="flex flex-col space-y-2 text-sm">
                    <a href="{{ route('author.profile', $author->username ?? $author->id) }}" class="flex items-center justify-center bg-gray-600 text-white px-3 py-1.5 rounded-md hover:bg-gray-700 transition">
                        <i class="fa-solid fa-user mr-2"></i>
                        <span>View Profile</span>
                    </a>
                    @if($author->social_links['youtube'] ?? null)
                        <a href="{{ $author->social_links['youtube'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center bg-red-600 text-white px-3 py-1.5 rounded-md hover:bg-red-700 transition">
                            <i class="fab fa-youtube mr-2"></i>
                            <span>YouTube</span>
                        </a>
                    @endif
                    @if($author->social_links['paypal'] ?? null)
                        <a href="{{ $author->social_links['paypal'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center bg-blue-800 text-white px-3 py-1.5 rounded-md hover:bg-blue-900 transition">
                            <i class="fab fa-paypal mr-2"></i>
                            <span>Donate with PayPal</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
