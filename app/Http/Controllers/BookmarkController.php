<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Mod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    /**
     * Get user's bookmarked mods
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $bookmarks = $user->bookmarkedMods()
            ->with(['user', 'categories'])
            ->paginate(12);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'bookmarks' => $bookmarks->map(function ($mod) {
                    return [
                        'id' => $mod->id,
                        'title' => $mod->title,
                        'slug' => $mod->slug,
                        'description' => $mod->description,
                        'thumbnail_url' => $mod->thumbnail_url,
                        'downloads' => $mod->downloads,
                        'rating' => $mod->average_rating,
                        'author' => [
                            'name' => $mod->user->name,
                            'avatar' => $mod->user->getAvatarUrl(64),
                        ],
                        'bookmarked_at' => $mod->pivot->created_at->diffForHumans(),
                    ];
                }),
                'pagination' => [
                    'current_page' => $bookmarks->currentPage(),
                    'last_page' => $bookmarks->lastPage(),
                    'per_page' => $bookmarks->perPage(),
                    'total' => $bookmarks->total(),
                ],
            ]);
        }

        return view('author.bookmarks', compact('bookmarks'));
    }

    /**
     * Toggle bookmark for a mod
     */
    public function toggle(Request $request, $modId)
    {
        $mod = Mod::findOrFail($modId);
        $user = Auth::user();

        $bookmark = Bookmark::where('user_id', $user->id)
            ->where('mod_id', $mod->id)
            ->first();

        if ($bookmark) {
            // Remove bookmark
            $bookmark->delete();

            return response()->json([
                'success' => true,
                'bookmarked' => false,
                'message' => 'Bookmark removed',
            ]);
        } else {
            // Add bookmark
            Bookmark::create([
                'user_id' => $user->id,
                'mod_id' => $mod->id,
            ]);

            // Log activity
            ActivityController::logActivity(
                $user->id,
                'bookmark',
                $mod
            );

            return response()->json([
                'success' => true,
                'bookmarked' => true,
                'message' => 'Mod bookmarked',
            ]);
        }
    }

    /**
     * Check if mod is bookmarked
     */
    public function check($modId)
    {
        $bookmarked = Auth::check() && Auth::user()->hasBookmarked(Mod::findOrFail($modId));

        return response()->json([
            'success' => true,
            'bookmarked' => $bookmarked,
        ]);
    }
}
