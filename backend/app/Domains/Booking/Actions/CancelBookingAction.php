<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Payment\Actions\ProcessRefundAction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CancelBookingAction
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ProcessRefundAction $processRefund,
    ) {}

    public function execute(string $reference, int $travelerId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($reference, $travelerId, $reason) {
            $booking = Booking::where('reference', $reference)->lockForUpdate()->first();

            if (! $booking) {
                throw new NotFoundHttpException('Booking not found.');
            }

            if ($booking->traveler_id !== $travelerId) {
                throw new AccessDeniedHttpException('You do not have access to this booking.');
            }

            if ($booking->status === Booking::STATUS_CANCELLED) {
                $booking->load('tour');

                return BookingResponseDTO::fromBooking($booking);
            }

            if ($booking->status !== Booking::STATUS_CONFIRMED) {
                throw new UnprocessableEntityHttpException('Only confirmed bookings can be cancelled.');
            }

            if (! $booking->canCancel()) {
                throw new ConflictHttpException(
                    'This booking cannot be cancelled — the cancellation window has passed.'
                );
            }

            $beforeState = $booking->status;

            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->processRefund->execute($booking);

            $this->audit->log(
                $booking,
                'traveler',
                $booking->traveler_id,
                'cancelled',
                $beforeState,
                Booking::STATUS_CANCELLED,
                $reason ? ['cancellation_reason' => $reason] : null,
            );

            $booking->refresh()->load('tour');

            return BookingResponseDTO::fromBooking($booking);
        });
    }
}
