<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    /**
     * Get user activities for overview tab
     */
    public function getUserActivities(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $activities = $user->activities()
            ->with(['subject'])
            ->paginate(20);

        return response()->json([
            'success' => true,
            'activities' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action_type' => $activity->action_type,
                    'content' => $activity->content,
                    'time_ago' => $activity->getTimeAgoAttribute(),
                    'created_at' => $activity->created_at,
                    'subject' => $this->formatSubject($activity),
                ];
            }),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Create a status update
     */
    public function createStatus(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $activity = UserActivity::create([
            'user_id' => Auth::id(),
            'action_type' => UserActivity::TYPE_STATUS_UPDATE,
            'subject_type' => null,
            'subject_id' => null,
            'content' => $request->content,
        ]);

        // Update last activity
        Auth::user()->updateLastActivity();

        return response()->json([
            'success' => true,
            'message' => 'Status posted successfully',
            'activity' => [
                'id' => $activity->id,
                'content' => $activity->content,
                'time_ago' => $activity->getTimeAgoAttribute(),
            ],
        ]);
    }

    /**
     * Delete a status update
     */
    public function deleteStatus($id)
    {
        $activity = UserActivity::where('id', $id)
            ->where('user_id', Auth::id())
            ->where('action_type', UserActivity::TYPE_STATUS_UPDATE)
            ->firstOrFail();

        $activity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Status deleted successfully',
        ]);
    }

    /**
     * Log an activity (called from other parts of the app)
     */
    public static function logActivity(int $userId, string $actionType, $subject = null, ?string $content = null, ?array $metadata = null)
    {
        $data = [
            'user_id' => $userId,
            'action_type' => $actionType,
            'content' => $content,
            'metadata' => $metadata,
        ];

        if ($subject) {
            $data['subject_type'] = get_class($subject);
            $data['subject_id'] = $subject->id;
        }

        UserActivity::create($data);

        // Update user's last activity timestamp
        User::where('id', $userId)->update(['last_activity_at' => now()]);
    }

    /**
     * Format activity subject for display
     */
    private function formatSubject($activity): ?array
    {
        if (!$activity->subject) {
            return null;
        }

        $subject = $activity->subject;

        switch (get_class($subject)) {
            case 'App\Models\Mod':
                return [
                    'type' => 'mod',
                    'id' => $subject->id,
                    'title' => $subject->title,
                    'slug' => $subject->slug,
                    'image' => $subject->thumbnail_url ?? null,
                ];

            case 'App\Models\ModComment':
                return [
                    'type' => 'comment',
                    'id' => $subject->id,
                    'content' => substr($subject->content, 0, 100),
                    'mod_title' => $subject->mod->title ?? null,
                    'mod_slug' => $subject->mod->slug ?? null,
                ];

            case 'App\Models\User':
                return [
                    'type' => 'user',
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'avatar' => $subject->getAvatarUrl(64),
                ];

            default:
                return null;
        }
    }
}
