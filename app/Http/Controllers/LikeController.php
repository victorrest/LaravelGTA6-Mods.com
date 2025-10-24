<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use App\Models\ModLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    /**
     * Toggle like for a mod
     */
    public function toggle(Request $request, $modId)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Jelentkezz be a kedveléshez.',
            ], 401);
        }

        $mod = Mod::findOrFail($modId);
        $user = Auth::user();

        $like = ModLike::where('user_id', $user->id)
            ->where('mod_id', $mod->id)
            ->first();

        if ($like) {
            // Remove like
            $like->delete();
            $mod->decrement('likes');

            return response()->json([
                'success' => true,
                'liked' => false,
                'likes_count' => $mod->fresh()->likes,
                'message' => 'Like removed',
            ]);
        } else {
            // Add like
            ModLike::create([
                'user_id' => $user->id,
                'mod_id' => $mod->id,
            ]);

            $mod->increment('likes');

            // Log activity
            ActivityController::logActivity(
                $user->id,
                'like',
                $mod
            );

            return response()->json([
                'success' => true,
                'liked' => true,
                'likes_count' => $mod->fresh()->likes,
                'message' => 'Mod liked',
            ]);
        }
    }

    /**
     * Check if mod is liked
     */
    public function check($modId)
    {
        $liked = false;

        if (Auth::check()) {
            $liked = ModLike::where('user_id', Auth::id())
                ->where('mod_id', $modId)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
        ]);
    }
}
