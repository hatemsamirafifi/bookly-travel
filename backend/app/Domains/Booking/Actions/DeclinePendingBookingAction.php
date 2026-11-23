<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Payment\Contracts\PaymentGateway;
use App\Domains\Payment\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Decline an unpaid `pending_payment` booking (e.g. after FR-027 price-drift
 * disclosure) without calling the paid-booking refund cancellation flow.
 *
 * Atomically transitions the booking from `pending_payment` to `expired` under
 * row lock so held departure seats are released immediately, then best-effort
 * cancels any pending PaymentIntent outside the database transaction.
 */
class DeclinePendingBookingAction
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $reference, int $travelerId, ?string $reason = null): array
    {
        $resolvedReason = is_string($reason) && trim($reason) !== ''
            ? trim($reason)
            : 'Traveler declined updated price';

        [$dto, $intentIds] = DB::transaction(function () use ($reference, $travelerId, $resolvedReason): array {
            $booking = Booking::where('reference', $reference)->lockForUpdate()->first();

            if (! $booking) {
                throw new NotFoundHttpException('Booking not found.');
            }

            if ((int) $booking->traveler_id !== $travelerId) {
                throw new AccessDeniedHttpException('You do not have access to this booking.');
            }

            if ($booking->status !== Booking::STATUS_PENDING_PAYMENT) {
                throw new UnprocessableEntityHttpException('Only pending payment bookings can be declined.');
            }

            $beforeState = $booking->status;
            $booking->update([
                'status' => Booking::STATUS_EXPIRED,
                'pending_expires_at' => now(),
                'cancellation_reason' => $resolvedReason,
            ]);

            $intentIds = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('type', 'charge')
                ->pluck('stripe_payment_intent_id')
                ->filter()
                ->values()
                ->all();

            Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);

            $this->audit->log(
                $booking,
                'traveler',
                $booking->traveler_id,
                'declined_price_change',
                $beforeState,
                Booking::STATUS_EXPIRED,
                ['reason' => $resolvedReason],
            );

            $booking->refresh()->load(['tour.translations', 'departure']);

            return [BookingResponseDTO::fromBooking($booking), $intentIds];
        });

        foreach ($intentIds as $intentId) {
            try {
                $this->gateway->cancelPaymentIntent((string) $intentId);
            } catch (\Throwable $e) {
                Log::warning('Best-effort PaymentIntent cancel failed after pending booking decline', [
                    'reference' => $reference,
                    'payment_intent_id' => $intentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dto;
    }
}
