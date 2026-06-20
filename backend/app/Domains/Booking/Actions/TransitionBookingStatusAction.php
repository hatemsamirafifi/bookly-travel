<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        if ($booking->tour->partner_id !== $partner->id) {
            // Return 404 instead of 403 to prevent information leakage about other partners' bookings
            throw new NotFoundHttpException('Booking not found.');
        }

        if (! in_array($targetStatus, [Booking::STATUS_COMPLETED, Booking::STATUS_NO_SHOW], true)) {
            throw new UnprocessableEntityHttpException(
                "Invalid status. Partners can only transition to 'completed' or 'no_show'."
            );
        }

        if ($booking->tour_date->isFuture()) {
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
