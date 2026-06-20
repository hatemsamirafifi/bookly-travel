<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Models\Notification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * List notifications for a partner with optional filters.
     *
     * @param int $partnerId The authenticated partner's ID
     * @param array{unread_only?: bool, type?: string, per_page?: int, page?: int} $filters
     * @return LengthAwarePaginator
     */
    public function listForPartner(int $partnerId, array $filters = []): LengthAwarePaginator
    {
        $query = Notification::where('partner_id', $partnerId)
            ->orderByDesc('created_at');

        if (! empty($filters['unread_only'])) {
            $query->whereNull('read_at');
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
    }

    /**
     * Get the count of unread notifications for a partner.
     *
     * @param int $partnerId The authenticated partner's ID
     * @return int
     */
    public function getUnreadCount(int $partnerId): int
    {
        return Notification::where('partner_id', $partnerId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Mark a single notification as read.
     *
     * @param int $notificationId The notification ID
     * @param int $partnerId The authenticated partner's ID
     * @return Notification The updated notification
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException If notification not found or not owned by partner
     */
    public function markAsRead(int $notificationId, int $partnerId): Notification
    {
        $notification = Notification::where('id', $notificationId)
            ->where('partner_id', $partnerId)
            ->first();

        if (! $notification) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
                'Notification not found.'
            );
        }

        $notification->markAsRead();

        return $notification;
    }

    /**
     * Mark all unread notifications as read for a partner.
     *
     * @param int $partnerId The authenticated partner's ID
     * @return int The number of notifications marked as read
     */
    public function markAllAsRead(int $partnerId): int
    {
        return Notification::where('partner_id', $partnerId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Create a new notification for a partner.
     *
     * @param int $partnerId The partner ID to notify
     * @param string $type The notification type (e.g., 'new_booking', 'cancellation')
     * @param string $title The notification title
     * @param string $body The notification body
     * @param array|null $data Optional structured data payload
     * @return Notification
     */
    public function create(int $partnerId, string $type, string $title, string $body, ?array $data = null): Notification
    {
        return Notification::create([
            'partner_id' => $partnerId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}