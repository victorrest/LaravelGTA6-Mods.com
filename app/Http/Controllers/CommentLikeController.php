<?php

namespace App\Http\Controllers;

use App\Models\ModComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentLikeController extends Controller
{
    /**
     * Toggle like status for a comment
     */
    public function toggle(Request $request, $commentId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to like a comment'
            ], 401);
        }

        $comment = ModComment::find($commentId);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found'
            ], 404);
        }

        // Check if user already liked the comment
        $existingLike = $comment->likes()->where('user_id', $user->id)->exists();

        if ($existingLike) {
            // Unlike
            $comment->likes()->detach($user->id);
            $liked = false;
        } else {
            // Like
            $comment->likes()->attach($user->id);
            $liked = true;
        }

        // Get updated likes count
        $likesCount = $comment->likes()->count();

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $likesCount,
        ]);
    }

    /**
     * Check if user has liked a comment
     */
    public function check(Request $request, $commentId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => true,
                'liked' => false
            ]);
        }

        $comment = ModComment::find($commentId);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found'
            ], 404);
        }

        $liked = $comment->likes()->where('user_id', $user->id)->exists();

        return response()->json([
            'success' => true,
            'liked' => $liked
        ]);
    }
}
