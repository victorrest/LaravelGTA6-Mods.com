<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSocialLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProfileSettingsController extends Controller
{
    /**
     * Update profile settings
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'profile_title' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:160',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Upload avatar image
     */
    public function uploadAvatar(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:1024', // 1MB
        ]);

        // Delete old avatar if exists
        if ($user->avatar && $user->avatar_type === 'custom') {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar' => $path,
            'avatar_type' => 'custom',
            'avatar_preset_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => $user->getAvatarUrl(),
        ]);
    }

    /**
     * Select preset avatar
     */
    public function selectPresetAvatar(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'preset_id' => 'required|string',
        ]);

        // Delete custom avatar if exists
        if ($user->avatar && $user->avatar_type === 'custom') {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update([
            'avatar' => null,
            'avatar_type' => 'preset',
            'avatar_preset_id' => $request->preset_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preset avatar selected',
            'avatar_url' => $user->getAvatarUrl(),
        ]);
    }

    /**
     * Delete avatar
     */
    public function deleteAvatar()
    {
        $user = Auth::user();

        // Delete avatar file if exists
        if ($user->avatar && $user->avatar_type === 'custom') {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update([
            'avatar' => null,
            'avatar_type' => 'default',
            'avatar_preset_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar deleted successfully',
            'avatar_url' => $user->getAvatarUrl(),
        ]);
    }

    /**
     * Upload banner image
     */
    public function uploadBanner(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'banner' => 'required|image|mimes:jpeg,png,jpg|max:2048', // 2MB
        ]);

        // Delete old banner if exists
        if ($user->banner) {
            Storage::disk('public')->delete($user->banner);
        }

        // Store new banner
        $path = $request->file('banner')->store('banners', 'public');

        $user->update(['banner' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Banner uploaded successfully',
            'banner_url' => $user->getBannerUrl(),
        ]);
    }

    /**
     * Delete banner image
     */
    public function deleteBanner()
    {
        $user = Auth::user();

        if ($user->banner) {
            Storage::disk('public')->delete($user->banner);
        }

        $user->update(['banner' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully',
        ]);
    }

    /**
     * Update social links
     */
    public function updateSocialLinks(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'links' => 'required|array',
            'links.*.platform' => 'required|string',
            'links.*.url' => 'nullable|string|max:255',
        ]);

        // Delete all existing social links
        $user->socialLinks()->delete();

        // Create new social links
        foreach ($validated['links'] as $link) {
            if (!empty($link['url'])) {
                $user->socialLinks()->create([
                    'platform' => $link['platform'],
                    'url' => $this->normalizeUrl($link['platform'], $link['url']),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Social links updated successfully',
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()],
        ]);

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Normalize social media URL
     */
    private function normalizeUrl(string $platform, string $url): string
    {
        // Remove protocol and www
        $url = preg_replace('~^https?://(www\.)?~i', '', $url);

        // Get platform definitions
        $definitions = UserSocialLink::getPlatformDefinitions();

        if (isset($definitions[$platform]['base_url'])) {
            $baseUrl = $definitions[$platform]['base_url'];
            $prefix = $definitions[$platform]['prefix'] ?? '';

            // Remove prefix if present
            if ($prefix) {
                $url = preg_replace('~^' . preg_quote($prefix, '~') . '~i', '', $url);
            }

            return $baseUrl . $url;
        }

        // For website, ensure it has protocol
        if ($platform === 'website' && !preg_match('~^https?://~i', $url)) {
            return 'https://' . $url;
        }

        return $url;
    }
}
