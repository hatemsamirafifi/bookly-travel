<?php

namespace App\Domains\Payment\Listeners;

use App\Domains\Payment\Events\PaymentFailed;
use App\Jobs\SendAdminSlackAlertJob;
use Illuminate\Support\Facades\Log;

class NotifyAdminOnPaymentFailure
{
    public function handle(PaymentFailed $event): void
    {
        $booking = $event->booking;
        $payment = $event->payment;

        Log::error('ADMIN ALERT: Payment failed for booking — manual review may be needed', [
            'booking_reference' => $booking->reference,
            'traveler_id' => $booking->traveler_id,
            'tour_id' => $booking->tour_id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]);

        // Slack is best-effort (FR-012): the queued job owns the webhook URL
        // (it is read from config at run time, never serialized with the
        // job) and is dispatched after commit, so the external HTTP call
        // never runs inside — and can never roll back — the caller's DB
        // transaction.
        SendAdminSlackAlertJob::dispatch(
            sprintf(
                ':warning: *Bookly Alert* — Payment failed for booking `%s`.'
                . ' Booking has been expired.'
                . "\n*Amount*: %d %s  |  Check application logs for details.",
                $booking->reference,
                $payment->amount,
                $payment->currency,
            ),
            [
                'booking_reference' => $booking->reference,
                'traveler_id' => $booking->traveler_id,
                'tour_id' => $booking->tour_id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ],
        )->afterCommit();
    }
}
