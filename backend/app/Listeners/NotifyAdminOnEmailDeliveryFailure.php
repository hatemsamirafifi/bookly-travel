<?php

namespace App\Listeners;

use App\Events\BookingEmailDeliveryFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Listens to BookingEmailDeliveryFailed and notifies admin operators so they
 * can arrange manual email delivery for the affected traveler.
 *
 * Channels:
 *   1. ERROR-level log entry (always).
 *   2. Slack webhook (only when ADMIN_SLACK_WEBHOOK_URL env var is set).
 *
 * Per FR-028: this listener MUST NOT alter the booking status or block normal flow.
 */
class NotifyAdminOnEmailDeliveryFailure
{
    public function handle(BookingEmailDeliveryFailed $event): void
    {
        $booking = $event->booking;

        // 1. Log — always present so ops can grep the log
        Log::error('ADMIN ALERT: Booking confirmation email delivery failed — manual intervention required', [
            'booking_reference' => $booking->reference,
            'traveler_id' => $booking->traveler_id,
            'tour_id' => $booking->tour_id,
            'locale' => $booking->locale,
            'error' => $event->errorMessage,
        ]);

        // 2. Slack webhook — only if configured
        $webhookUrl = config('services.slack.admin_webhook_url');
        if (! $webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'text' => sprintf(
                ':warning: *Bookly Alert* — Booking confirmation email delivery failed (all retries exhausted).'
                . "\n*Booking*: `%s`  |  Check application logs for details.",
                $booking->reference,
            ),
        ]);
    }
}
