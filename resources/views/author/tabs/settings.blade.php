@if($isOwner)
<div id="settings-tab" data-tab-content="settings" class="tab-content hidden">
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Settings Navigation -->
        <nav class="flex md:flex-col md:w-1/4 space-x-2 md:space-x-0 md:space-y-2 overflow-x-auto md:overflow-x-visible">
            <button type="button" class="settings-tab-btn active font-semibold text-left p-3 flex items-center gap-3 whitespace-nowrap" data-settings-tab="profile">
                <i class="fas fa-user-circle fa-fw w-5"></i><span>Profile</span>
            </button>
            <button type="button" class="settings-tab-btn font-semibold text-left p-3 flex items-center gap-3 whitespace-nowrap" data-settings-tab="accounts">
                <i class="fas fa-link fa-fw w-5"></i><span>Accounts</span>
            </button>
            <button type="button" class="settings-tab-btn font-semibold text-left p-3 flex items-center gap-3 whitespace-nowrap" data-settings-tab="security">
                <i class="fas fa-shield-alt fa-fw w-5"></i><span>Security</span>
            </button>
        </nav>

        <!-- Settings Content -->
        <div class="flex-1">
            <!-- Profile Settings -->
            <div id="settings-profile" class="settings-tab-content space-y-8">
                <!-- General Settings -->
                <div>
                    <h3 class="brand-font text-2xl font-bold text-gray-800 mb-1 tracking-wide">General settings</h3>
                    <p class="text-gray-500 mb-6">Update your basic profile information and branding.</p>

                    <div class="space-y-4">
                        <!-- Username (Disabled) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" value="{{ $author->name }}" class="w-full p-2 rounded-md form-input bg-gray-100 cursor-not-allowed" disabled>
                            <p class="text-xs text-gray-400 mt-1">Username cannot be changed</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="settings-email" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                            <input type="email" id="settings-email" value="{{ $author->email }}" class="w-full p-2 rounded-md form-input">
                        </div>

                        <!-- Profile Title -->
                        <div>
                            <label for="settings-profile-title" class="block text-sm font-medium text-gray-700 mb-1">Profile title</label>
                            <input type="text" id="settings-profile-title" value="{{ $author->profile_title }}" class="w-full p-2 rounded-md form-input" maxlength="100" placeholder="e.g., Modder, Content Creator">
                        </div>

                        <!-- Bio -->
                        <div>
                            <label for="settings-bio" class="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                            <textarea id="settings-bio" rows="3" maxlength="160" class="w-full p-2 rounded-md form-textarea" placeholder="Tell the community about yourself...">{{ $author->bio }}</textarea>
                            <div class="flex justify-end">
                                <span id="bio-counter" class="text-xs text-gray-500">{{ strlen($author->bio ?? '') }}/160</span>
                            </div>
                        </div>

                        <!-- Banner Image -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Banner image</label>
                            <div id="banner-preview" class="gta6mods-banner-preview {{ $author->getBannerUrl() ? 'has-banner' : '' }}"
                                 style="{{ $author->getBannerUrl() ? 'background-image: url(' . $author->getBannerUrl() . ');' : '' }}">
                                <button type="button" id="remove-banner-btn" class="gta6mods-banner-remove {{ $author->getBannerUrl() ? '' : 'hidden' }}">
                                    <i class="fas fa-times"></i>
                                </button>
                                <span class="gta6mods-banner-empty {{ $author->getBannerUrl() ? 'hidden' : '' }}">No banner uploaded yet.</span>
                            </div>
                            <input type="file" id="settings-banner" accept="image/*" class="mt-3 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                            <p class="text-xs text-gray-400 mt-1">Upload a JPG or PNG up to 2 MB. The image appears at the top of your author page.</p>
                        </div>
                    </div>
                </div>

                <!-- Avatar Settings -->
                <div>
                    <h3 class="brand-font text-2xl font-bold text-gray-800 mb-1 tracking-wide">Profile avatar</h3>
                    <p class="text-gray-500 mb-6">Upload a custom avatar or choose one of the presets below.</p>

                    <div class="space-y-4">
                        <!-- Avatar Upload -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload avatar</label>
                            <div class="flex items-center gap-4">
                                <div class="relative w-24 h-24">
                                    <img id="avatar-preview" src="{{ $author->getAvatarUrl(96) }}" alt="Avatar preview" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
                                </div>
                                <div class="flex-1">
                                    <input type="file" id="settings-avatar" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                                    <p class="text-xs text-gray-400 mt-1">Upload a JPG or PNG up to 1 MB.</p>
                                    @if($author->avatar_type === 'custom' && $author->avatar)
                                        <button type="button" id="delete-avatar-btn" class="mt-3 text-sm font-semibold text-red-600 hover:text-red-700">
                                            <i class="fas fa-trash-can mr-1"></i>Delete image
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Preset Avatars -->
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Preset avatars</p>
                            <div id="avatar-selection-grid" class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-3">
                                @foreach($presetAvatars as $preset)
                                    <button type="button"
                                            class="preset-avatar rounded-full overflow-hidden aspect-square border-2 border-transparent transition hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 {{ $author->avatar_preset_id === $preset['id'] ? 'selected' : '' }}"
                                            data-avatar-id="{{ $preset['id'] }}"
                                            data-avatar-url="{{ $preset['url'] }}">
                                        <img src="{{ $preset['url'] }}" alt="" class="w-full h-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="pt-6 border-t">
                    <button id="save-profile-btn" class="btn-action font-semibold py-3 px-8 rounded-lg text-base">
                        Save changes
                    </button>
                </div>
            </div>

            <!-- Social Accounts Settings -->
            <div id="settings-accounts" class="settings-tab-content hidden space-y-8">
                <div>
                    <h3 class="brand-font text-2xl font-bold text-gray-800 mb-1 tracking-wide">Social accounts</h3>
                    <p class="text-gray-500 mb-6">Share the places where people can follow your work.</p>

                    <div class="space-y-4 max-w-xl" id="social-links-form">
                        @php
                            $definitions = App\Models\UserSocialLink::getPlatformDefinitions();
                        @endphp

                        @foreach($definitions as $key => $definition)
                            @php
                                $link = $socialLinks->get($key);
                                $value = $link ? $link->url : '';
                            @endphp
                            <div class="flex items-center gap-2">
                                <span class="flex items-center text-gray-600 min-w-[140px]">
                                    <i class="{{ $definition['icon'] }} fa-fw w-5 mr-2"></i>
                                    <span class="text-sm font-medium">{{ $definition['label'] }}</span>
                                </span>
                                <div class="flex-1">
                                    @if(isset($definition['prefix']) && $definition['prefix'])
                                        <div class="flex items-center border border-gray-300 rounded-md overflow-hidden focus-within:border-pink-500 focus-within:ring-2 focus-within:ring-pink-100">
                                            <span class="px-3 py-2 bg-gray-50 text-sm text-gray-500 whitespace-nowrap">{{ $definition['prefix'] }}</span>
                                            <input type="text" class="flex-1 p-2 border-0 focus:ring-0" data-link-key="{{ $key }}" value="{{ $value }}" placeholder="username">
                                        </div>
                                    @else
                                        <input type="text" class="w-full p-2 form-input" data-link-key="{{ $key }}" value="{{ $value }}" placeholder="https://">
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="pt-6 border-t">
                    <button id="save-social-btn" class="btn-action font-semibold py-3 px-8 rounded-lg text-base">
                        Save links
                    </button>
                </div>
            </div>

            <!-- Security Settings -->
            <div id="settings-security" class="settings-tab-content hidden space-y-8">
                <div>
                    <h3 class="brand-font text-2xl font-bold text-gray-800 mb-1 tracking-wide">Change password</h3>
                    <p class="text-gray-500 mb-6">Make sure your account is protected with a strong password.</p>

                    <form id="password-form" class="space-y-4 max-w-md">
                        <!-- Current Password -->
                        <div>
                            <label for="current-password" class="block text-sm font-medium text-gray-700 mb-1">Current password</label>
                            <input type="password" id="current-password" class="w-full p-2 rounded-md form-input" autocomplete="current-password">
                        </div>

                        <!-- New Password -->
                        <div>
                            <label for="new-password" class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                            <input type="password" id="new-password" class="w-full p-2 rounded-md form-input" autocomplete="new-password">
                            <p class="text-xs text-gray-500 mt-2">Use at least 12 characters including uppercase, lowercase, numbers and symbols.</p>

                            <!-- Password Strength Indicator -->
                            <div class="mt-3">
                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div id="password-strength-bar" class="h-2 w-0 bg-red-500 transition-all duration-300"></div>
                                </div>
                                <p id="password-strength-text" class="text-xs text-gray-500 mt-2"></p>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-1">Confirm new password</label>
                            <input type="password" id="confirm-password" class="w-full p-2 rounded-md form-input" autocomplete="new-password">
                        </div>

                        <div class="pt-2">
                            <button type="submit" id="save-password-btn" class="btn-action font-semibold py-3 px-8 rounded-lg text-base">
                                Save password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/author-settings.js') }}"></script>
@endpush
@endif
