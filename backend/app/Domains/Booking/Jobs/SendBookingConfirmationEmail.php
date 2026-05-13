<?php

namespace App\Domains\Booking\Jobs;

use App\Domains\Booking\Models\Booking;
use App\Events\BookingEmailDeliveryFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued job: send localized booking confirmation email to the traveler.
 *
 * Idempotency guard: if confirmation_email_sent_at is already set, the job
 * exits immediately so retries never produce duplicate emails.
 *
 * Failure handling: when all retry attempts are exhausted, the failed() hook
 * logs at ERROR severity and dispatches BookingEmailDeliveryFailed so an
 * admin listener can surface the failure for manual resolution (FR-028).
 * The booking status is NEVER affected by email delivery failures.
 */
class SendBookingConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum delivery attempts before giving up (3 attempts, ~15 min apart). */
    public int $tries = 3;

    /** Seconds between retry attempts. */
    public int $backoff = 900;

    public function __construct(
        private readonly Booking $booking,
    ) {
        $this->queue = 'booking_emails';
    }

    public function handle(): void
    {
        // Idempotency guard — skip if email was already delivered
        if ($this->booking->confirmation_email_sent_at !== null) {
            return;
        }

        // Re-fresh booking in case it was modified between dispatch and execution
        $booking = $this->booking->fresh();
        if (! $booking || $booking->confirmation_email_sent_at !== null) {
            return;
        }

        // TODO: swap the Log::info stub below for a real Mailable once the
        //       Mail template (BookingConfirmationMail) is implemented.
        //       Example: Mail::to($booking->traveler->email)->send(new BookingConfirmationMail($booking));
        Log::info('Booking confirmation email dispatched', [
            'booking_reference' => $booking->reference,
            'traveler_id'       => $booking->traveler_id,
            'locale'            => $booking->locale,
        ]);

        $booking->update(['confirmation_email_sent_at' => now()]);
    }

    /**
     * Called by Laravel after all retry attempts are exhausted.
     * Logs the failure at ERROR severity and fires an event for admin notification.
     * Per FR-028: this MUST NOT alter the booking status.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Booking confirmation email delivery failed — all retries exhausted', [
            'booking_reference' => $this->booking->reference,
            'traveler_id'       => $this->booking->traveler_id,
            'error'             => $exception->getMessage(),
        ]);

        event(new BookingEmailDeliveryFailed($this->booking, $exception->getMessage()));
    }
}
