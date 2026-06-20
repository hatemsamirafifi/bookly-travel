<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $perPage = $request->input('per_page', 20);
        $unreadOnly = $request->boolean('unread_only', false);

        $query = Notification::where('partner_id', $partnerId)
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($perPage);
        $unreadCount = Notification::where('partner_id', $partnerId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $notification = Notification::where('id', $id)
            ->where('partner_id', $partnerId)
            ->first();

        if (! $notification) {
            abort(404);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');

        Notification::where('partner_id', $partnerId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
