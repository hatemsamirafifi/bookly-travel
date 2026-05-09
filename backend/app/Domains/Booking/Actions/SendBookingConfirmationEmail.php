<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Booking $booking,
    ) {
        $this->queue = 'booking_emails';
    }

    public function handle(): void
    {
        if ($this->booking->confirmation_email_sent_at !== null) {
            return;
        }

        try {
            // Email sending will be wired up with Mailable class in a future iteration.
            // For now, log the confirmation event so the queue infrastructure is in place.
            Log::info('Booking confirmation email would be sent', [
                'booking_reference' => $this->booking->reference,
                'traveler_id' => $this->booking->traveler_id,
                'locale' => $this->booking->locale,
            ]);

            $this->booking->update(['confirmation_email_sent_at' => now()]);
        } catch (\Exception $e) {
            Log::error('Failed to send booking confirmation email', [
                'booking_reference' => $this->booking->reference,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
