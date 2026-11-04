<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Model\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSessionNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $unreadOnly = filter_var($request->query('unread_only'), FILTER_VALIDATE_BOOLEAN);

        $query = UserNotification::query()
            ->where('user_id', $userId)
            ->latest();

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->limit(50)->get();

        return response()->json([
            'notifications' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'link' => $n->link,
                'mentor_booking_id' => $n->mentor_booking_id,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'unread_count' => UserNotification::query()
                ->where('user_id', $userId)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$notification) {
            return response()->json(['errors' => [['message' => 'Notification not found']]], 404);
        }

        if (!$notification->read_at) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
