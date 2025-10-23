<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->with(['actor', 'notifiable'])
            ->paginate(20);

        return response()->json([
            'success' => true,
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'message' => $notification->message,
                    'read' => !$notification->isUnread(),
                    'created_at' => $notification->created_at->diffForHumans(),
                    'actor' => $notification->actor ? [
                        'id' => $notification->actor->id,
                        'name' => $notification->actor->name,
                        'avatar' => $notification->actor->getAvatarUrl(64),
                    ] : null,
                    'notifiable' => $this->formatNotifiable($notification),
                ];
            }),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get unread count
     */
    public function unreadCount()
    {
        $count = Auth::user()->notifications()->unread()->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = UserNotification::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        Auth::user()->notifications()->unread()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Format notifiable for display
     */
    private function formatNotifiable($notification): ?array
    {
        if (!$notification->notifiable) {
            return null;
        }

        $notifiable = $notification->notifiable;

        switch (get_class($notifiable)) {
            case 'App\Models\Mod':
                return [
                    'type' => 'mod',
                    'id' => $notifiable->id,
                    'title' => $notifiable->title,
                    'slug' => $notifiable->slug,
                ];

            case 'App\Models\User':
                return [
                    'type' => 'user',
                    'id' => $notifiable->id,
                    'name' => $notifiable->name,
                ];

            default:
                return null;
        }
    }
}
