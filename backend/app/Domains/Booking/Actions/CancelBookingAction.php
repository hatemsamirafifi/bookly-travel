<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CancelBookingAction
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function execute(string $reference, int $travelerId, ?string $reason = null): array
    {
        $booking = Booking::where('reference', $reference)->first();

        if (! $booking) {
            throw new NotFoundHttpException('Booking not found.');
        }

        if ($booking->traveler_id !== $travelerId) {
            throw new NotFoundHttpException('Booking not found.');
        }

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            throw new UnprocessableEntityHttpException('Only confirmed bookings can be cancelled.');
        }

        if (! $booking->canCancel()) {
            throw new ConflictHttpException(
                'This booking cannot be cancelled — the cancellation window has passed.'
            );
        }

        DB::transaction(function () use ($booking, $reason) {
            $beforeState = $booking->status;

            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->audit->log(
                $booking,
                'traveler',
                $booking->traveler_id,
                'cancelled',
                $beforeState,
                Booking::STATUS_CANCELLED,
                $reason ? ['cancellation_reason' => $reason] : null,
            );
        });

        $booking->refresh()->load('tour');

        return BookingResponseDTO::fromBooking($booking);
    }
}
