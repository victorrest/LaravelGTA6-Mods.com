<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthorProfileController extends Controller
{
    /**
     * Display the author's profile page
     */
    public function show(Request $request, $username)
    {
        $author = User::where('name', $username)
            ->with(['pinnedMod.categories'])
            ->firstOrFail();
        $isOwner = Auth::check() && Auth::id() === $author->id;

        // Available tabs
        $tabs = $this->getProfileTabs($isOwner);

        // Get requested tab
        $requestedTab = $request->query('tab', 'overview');
        $activeTab = $this->getValidTab($requestedTab, $isOwner);

        // Increment profile views if not owner
        if (!$isOwner) {
            $cookieName = 'profile_viewed_' . $author->id;

            if (!$request->cookie($cookieName)) {
                $author->incrementProfileViews();
                Cookie::queue($cookieName, '1', 60); // 60 minutes
            }
        }

        // Get author statistics
        $stats = $author->getStatistics();

        // Get recent activities for overview tab
        $recentActivities = $author->activities()
            ->with('subject')
            ->limit(10)
            ->get();

        // Get popular mods
        $popularMods = $author->mods()
            ->orderBy('downloads', 'desc')
            ->limit(6)
            ->get();

        // Get social links
        $socialLinks = $author->socialLinks()
            ->get()
            ->keyBy('platform');

        // Get preset avatars
        $presetAvatars = $this->getPresetAvatars();

        // Get pinned mod
        $pinnedMod = $author->pinnedMod;

        if ($pinnedMod) {
            $pinnedMod->setAttribute('thumbnail_url', $pinnedMod->hero_image_url);
            $pinnedMod->setAttribute('average_rating', $pinnedMod->rating);
        }

        return view('author.profile', compact(
            'author',
            'isOwner',
            'tabs',
            'activeTab',
            'stats',
            'recentActivities',
            'popularMods',
            'socialLinks',
            'presetAvatars',
            'pinnedMod'
        ));
    }

    /**
     * Get profile tabs based on ownership
     */
    private function getProfileTabs(bool $isOwner): array
    {
        $tabs = [
            'overview' => ['label' => 'Overview', 'icon' => 'fas fa-stream'],
            'uploads' => ['label' => 'Uploads', 'icon' => 'fas fa-upload'],
            'comments' => ['label' => 'Comments', 'icon' => 'fas fa-comments'],
            'followers' => ['label' => 'Followers', 'icon' => 'fas fa-users'],
        ];

        if ($isOwner) {
            $tabs['notifications'] = ['label' => 'Notifications', 'icon' => 'fas fa-bell'];
            $tabs['bookmarks'] = ['label' => 'Bookmarks', 'icon' => 'fas fa-bookmark'];
            $tabs['settings'] = ['label' => 'Settings', 'icon' => 'fas fa-cog'];
        }

        return $tabs;
    }

    /**
     * Get valid tab name
     */
    private function getValidTab(string $tab, bool $isOwner): string
    {
        $validTabs = array_keys($this->getProfileTabs($isOwner));

        return in_array($tab, $validTabs) ? $tab : 'overview';
    }

    /**
     * Get preset avatar definitions
     */
    private function getPresetAvatars(): array
    {
        $avatars = [];

        for ($i = 1; $i <= 30; $i++) {
            $avatars[] = [
                'id' => 'preset-' . $i,
                'url' => asset('images/avatars/preset-' . $i . '.png'),
            ];
        }

        return $avatars;
    }
}
