<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
// kept for other uses
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TransitionBookingStatusAction
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function execute(string $reference, User $partner, string $targetStatus): array
    {
        $booking = Booking::with('tour')->where('reference', $reference)->first();

        if (! $booking) {
            throw new NotFoundHttpException('Booking not found.');
        }

        $partner->loadMissing('partner');
        if ($partner->partner === null || $booking->tour->partner_id !== $partner->partner->id) {
            // partner-booking-api.md: 403 "You do not own this tour."
            throw new AccessDeniedHttpException('You do not own this tour.');
        }

        if (! in_array($targetStatus, [Booking::STATUS_COMPLETED, Booking::STATUS_NO_SHOW], true)) {
            throw new UnprocessableEntityHttpException(
                "Invalid status. Partners can only transition to 'completed' or 'no_show'."
            );
        }

        // F6: transitions only after the tour date has fully passed. The prior
        // `tour_date->isFuture()` guard was false on the day-of (midnight is
        // past), letting partners mark no_show before the tour even started.
        if (! ($booking->tour_date < Carbon::today())) {
            throw new ConflictHttpException(
                'Status can only be updated after the tour date has passed.'
            );
        }

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            throw new ConflictHttpException(
                "Booking is already {$booking->status}."
            );
        }

        DB::transaction(function () use ($booking, $partner, $targetStatus) {
            $beforeState = $booking->status;

            $booking->update(['status' => $targetStatus]);

            $this->audit->log(
                $booking,
                'partner',
                $partner->id,
                $targetStatus,
                $beforeState,
                $targetStatus,
            );
        });

        $booking->refresh()->load('tour');

        return [
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
                'previous_status' => Booking::STATUS_CONFIRMED,
                'transitioned_at' => now()->toIso8601String(),
            ],
        ];
    }
}
