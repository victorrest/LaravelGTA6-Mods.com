<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    /**
     * Toggle follow for a user
     */
    public function toggle(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $currentUser = Auth::user();

        // Prevent self-follow
        if ($currentUser->id === $targetUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot follow yourself',
            ], 422);
        }

        $follow = UserFollow::where('follower_id', $currentUser->id)
            ->where('following_id', $targetUser->id)
            ->first();

        if ($follow) {
            // Unfollow
            $follow->delete();

            return response()->json([
                'success' => true,
                'following' => false,
                'message' => 'Unfollowed',
                'followers_count' => $targetUser->followers()->count(),
            ]);
        } else {
            // Follow
            UserFollow::create([
                'follower_id' => $currentUser->id,
                'following_id' => $targetUser->id,
            ]);

            // Create notification
            UserNotification::create([
                'user_id' => $targetUser->id,
                'actor_id' => $currentUser->id,
                'type' => UserNotification::TYPE_FOLLOW,
                'notifiable_type' => User::class,
                'notifiable_id' => $currentUser->id,
                'message' => 'started following you',
            ]);

            // Log activity
            ActivityController::logActivity(
                $currentUser->id,
                'follow',
                $targetUser
            );

            return response()->json([
                'success' => true,
                'following' => true,
                'message' => 'Following',
                'followers_count' => $targetUser->followers()->count(),
            ]);
        }
    }

    /**
     * Get user's followers
     */
    public function followers(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $followers = $user->followers()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'followers' => $followers->map(function ($follower) {
                return [
                    'id' => $follower->id,
                    'name' => $follower->name,
                    'avatar' => $follower->getAvatarUrl(64),
                    'profile_title' => $follower->profile_title,
                    'is_online' => $follower->isOnline(),
                    'followers_count' => $follower->followers()->count(),
                ];
            }),
            'pagination' => [
                'current_page' => $followers->currentPage(),
                'last_page' => $followers->lastPage(),
                'total' => $followers->total(),
            ],
        ]);
    }

    /**
     * Get user's following
     */
    public function following(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $following = $user->following()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'following' => $following->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->getAvatarUrl(64),
                    'profile_title' => $user->profile_title,
                    'is_online' => $user->isOnline(),
                    'followers_count' => $user->followers()->count(),
                ];
            }),
            'pagination' => [
                'current_page' => $following->currentPage(),
                'last_page' => $following->lastPage(),
                'total' => $following->total(),
            ],
        ]);
    }
}
