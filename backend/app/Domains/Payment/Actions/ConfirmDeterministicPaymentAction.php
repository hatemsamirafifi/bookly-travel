<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Models\Payment;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConfirmDeterministicPaymentAction
{
    public function execute(string $reference, int $travelerId, string $clientSecret): Booking
    {
        [$booking, $payment, $dispatch] = DB::transaction(function () use ($reference, $travelerId, $clientSecret): array {
            $booking = Booking::where('reference', $reference)
                ->where('traveler_id', $travelerId)
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                throw new NotFoundHttpException('Booking not found.');
            }

            $payment = Payment::where('booking_id', $booking->id)
                ->where('type', 'charge')
                ->lockForUpdate()
                ->first();
            $storedSecret = $payment?->metadata['client_secret'] ?? null;

            if (! $payment || ! is_string($storedSecret) || ! hash_equals($storedSecret, $clientSecret)) {
                throw new NotFoundHttpException('Booking payment not found.');
            }

            if ($booking->status === Booking::STATUS_CONFIRMED && $payment->status === 'succeeded') {
                return [$booking, $payment, false];
            }

            if ($booking->status !== Booking::STATUS_PENDING_PAYMENT
                || ! in_array($payment->status, ['pending', 'succeeded'], true)) {
                throw new ConflictHttpException('This booking can no longer be paid.');
            }

            if ($payment->status === 'pending') {
                $payment->update([
                    'status' => 'succeeded',
                    'card_brand' => 'deterministic-test',
                    'card_last_four' => '4242',
                ]);
            }

            return [$booking, $payment->fresh(), true];
        });

        if ($dispatch) {
            event(new PaymentSucceeded($payment, $booking));
        }

        $booking->refresh();
        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            throw new ConflictHttpException('The booking could not be confirmed.');
        }

        return $booking;
    }
}
