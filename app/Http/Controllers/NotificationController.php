<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        \Log::info('Fetching notifications for user', [
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        \Log::info('Found notifications in database', [
            'count' => $notifications->count(),
            'notification_ids' => $notifications->pluck('id')->toArray(),
        ]);

        $mappedNotifications = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data ?? [],
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at?->toIso8601String() ?? $notification->created_at?->format('c') ?? now()->toIso8601String(),
                'updated_at' => $notification->updated_at?->toIso8601String() ?? $notification->updated_at?->format('c') ?? now()->toIso8601String(),
            ];
        });

        \Log::info('Returning notifications response', [
            'count' => $mappedNotifications->count(),
        ]);

        return response()->json([
            'message' => 'Notifications fetched successfully',
            'data' => $mappedNotifications
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => [
                'id' => $notification->id,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at?->toIso8601String() ?? $notification->read_at?->format('c'),
            ]
        ]);
    }

    /**
     * Mark all notifications as read for the authenticated user
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $updated = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'count' => $updated
        ]);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'message' => 'Unread count fetched successfully',
            'count' => $count
        ]);
    }
}
