<?php

namespace App\Domains\Payment\Listeners;

use App\Domains\Payment\Events\PaymentFailed;
use Illuminate\Support\Facades\Http;
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

        $webhookUrl = config('services.slack.admin_webhook_url');
        if (! $webhookUrl) {
            return;
        }

        try {
            $response = Http::timeout(5)->post($webhookUrl, [
                'text' => sprintf(
                    ':warning: *Bookly Alert* — Payment failed for booking `%s`.'
                    . ' Booking has been expired.'
                    . "\n*Amount*: %d %s  |  Check application logs for details.",
                    $booking->reference,
                    $payment->amount,
                    $payment->currency,
                ),
            ]);

            if (! $response->successful()) {
                Log::error('Slack webhook returned non-success status', [
                    'webhook_url' => $webhookUrl,
                    'booking_reference' => $booking->reference,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Slack webhook call failed', [
                'webhook_url' => $webhookUrl,
                'booking_reference' => $booking->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
