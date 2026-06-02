<?php

namespace App\Domains\Partner\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Models\Tour;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * List bookings for a partner's tours with optional filters.
     *
     * @param int $partnerId The authenticated partner's ID
     * @param array{tour_id?: int, status?: string, date_from?: string, date_to?: string, search?: string, per_page?: int, page?: int} $filters
     * @return LengthAwarePaginator
     */
    public function listForPartner(int $partnerId, array $filters = []): LengthAwarePaginator
    {
        $query = Booking::with(['tour', 'traveler'])
            ->whereHas('tour', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            })
            ->orderByDesc('tour_date');

        if (! empty($filters['tour_id'])) {
            $query->where('tour_id', $filters['tour_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('tour_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('tour_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('traveler', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
    }

    /**
     * Get a single booking's details, scoped to the partner's tours.
     *
     * Returns null if the booking does not belong to one of the partner's tours.
     *
     * @param string $reference The booking reference
     * @param int $partnerId The authenticated partner's ID
     * @return Booking|null
     */
    public function getForPartner(string $reference, int $partnerId): ?Booking
    {
        return Booking::with(['tour', 'traveler', 'auditLogs'])
            ->where('reference', $reference)
            ->whereHas('tour', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            })
            ->first();
    }

    /**
     * Mark a confirmed booking as completed.
     *
     * Only allowed when the booking's tour_date is today or in the past,
     * and the booking is currently in 'confirmed' status.
     *
     * @param string $reference The booking reference
     * @param int $partnerId The authenticated partner's ID
     * @return Booking The updated booking
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException If booking not found or not owned by partner
     * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException If tour_date is in the future or booking is not confirmed
     */
    public function markAsCompleted(string $reference, int $partnerId): Booking
    {
        $booking = $this->getForPartnerOrFail($reference, $partnerId);

        if ($booking->tour_date->isFuture()) {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                'Booking can only be marked as completed after the tour date has passed.'
            );
        }

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                "Booking status is '{$booking->status}', can only transition from 'confirmed'."
            );
        }

        DB::transaction(function () use ($booking, $partnerId) {
            $beforeState = $booking->status;
            $booking->update([
                'status' => Booking::STATUS_COMPLETED,
            ]);

            $this->audit->log(
                $booking,
                'partner',
                $partnerId,
                'mark_completed',
                $beforeState,
                Booking::STATUS_COMPLETED,
            );
        });

        return $booking->fresh();
    }

    /**
     * Request cancellation of a confirmed booking.
     *
     * Sets the booking status to 'cancellation_requested' and stores the reason
     * and optional evidence. Only allowed for confirmed bookings owned by the partner.
     *
     * @param string $reference The booking reference
     * @param int $partnerId The authenticated partner's ID
     * @param string $reason The cancellation reason (required)
     * @param array<string> $evidenceUrls Optional evidence URLs
     * @return Booking The updated booking
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException If booking not found or not owned by partner
     * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException If booking is not in a cancellable state
     */
    public function requestCancellation(string $reference, int $partnerId, string $reason, array $evidenceUrls = []): Booking
    {
        $booking = $this->getForPartnerOrFail($reference, $partnerId);

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                "Booking status is '{$booking->status}', can only request cancellation for 'confirmed' bookings."
            );
        }

        DB::transaction(function () use ($booking, $partnerId, $reason, $evidenceUrls) {
            $beforeState = $booking->status;

            $booking->update([
                'status' => 'cancellation_requested',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $this->audit->log(
                $booking,
                'partner',
                $partnerId,
                'request_cancellation',
                $beforeState,
                'cancellation_requested',
                ['reason' => $reason, 'evidence_urls' => $evidenceUrls],
            );
        });

        return $booking->fresh();
    }

    /**
     * Get booking summary counts for a partner.
     *
     * @param int $partnerId The authenticated partner's ID
     * @param array{date_from?: string, date_to?: string, tour_id?: int} $filters
     * @return array<string, mixed>
     */
    public function getBookingSummary(int $partnerId, array $filters = []): array
    {
        $baseQuery = Booking::whereHas('tour', function ($q) use ($partnerId) {
            $q->where('partner_id', $partnerId);
        });

        if (! empty($filters['tour_id'])) {
            $baseQuery->where('tour_id', $filters['tour_id']);
        }

        if (! empty($filters['date_from'])) {
            $baseQuery->where('tour_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $baseQuery->where('tour_date', '<=', $filters['date_to']);
        }

        $totalBookings = (clone $baseQuery)->count();
        $totalRevenue = (clone $baseQuery)->sum('total_price');

        $upcomingCount = (clone $baseQuery)
            ->where('tour_date', '>=', now()->toDateString())
            ->where('status', Booking::STATUS_CONFIRMED)
            ->count();

        return [
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalRevenue,
            'upcoming_count' => $upcomingCount,
        ];
    }

    /**
     * Get a booking for the partner or fail with 404.
     *
     * @param string $reference The booking reference
     * @param int $partnerId The authenticated partner's ID
     * @return Booking
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function getForPartnerOrFail(string $reference, int $partnerId): Booking
    {
        $booking = $this->getForPartner($reference, $partnerId);

        if (! $booking) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
                'Booking not found.'
            );
        }

        return $booking;
    }
}