<?php

namespace App\Domains\Booking\Jobs;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\VoucherService;
use App\Events\BookingEmailDeliveryFailed;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingVoucherMail;
use App\Mail\PartnerNewBookingMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Queued job: send localized booking confirmation email to the traveler,
 * notify the partner, and send the voucher PDF.
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
        $lock = Cache::lock("booking:{$this->booking->id}:confirmation_email", 30);

        if (! $lock->get()) {
            return;
        }

        try {
            // Re-fresh in case the booking was modified between dispatch and execution
            $booking = $this->booking->fresh(['tour', 'tour.translations', 'traveler', 'tour.partnerRecord.user']);
            if (! $booking || $booking->confirmation_email_sent_at !== null) {
                return;
            }

            // 1. Send confirmation email to traveler
            Mail::to($booking->traveler->email)
                ->send(new BookingConfirmedMail($booking));

            // 2. Notify the partner about the new booking. `tours.partner_id`
            //    references `partners.id` (repinned by the
            //    fix_tours_partner_id_to_partners_table migration), so the
            //    partner user is reached via `partnerRecord`, NOT the legacy
            //    `partner` relation (which still points at users.id).
            $partnerEmail = $booking->tour->partnerRecord?->user?->email;
            if ($partnerEmail) {
                Mail::to($partnerEmail)
                    ->send(new PartnerNewBookingMail($booking));
            }

            // 3. Generate and send voucher
            try {
                $voucherService = app(VoucherService::class);
                $voucherPath = $voucherService->generate($booking);

                Mail::to($booking->traveler->email)
                    ->send(new BookingVoucherMail($booking, $voucherPath));
            } catch (Throwable $e) {
                // Voucher generation failure should not prevent confirmation
                Log::warning('Voucher generation failed, confirmation email still sent', [
                    'booking_reference' => $booking->reference,
                    'error' => $e->getMessage(),
                ]);
            }

            $booking->update(['confirmation_email_sent_at' => now()]);

            Log::info('Booking confirmation emails dispatched', [
                'booking_reference' => $booking->reference,
                'traveler_id' => $booking->traveler_id,
                'locale' => $booking->locale,
            ]);
        } finally {
            $lock->release();
        }
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
            'traveler_id' => $this->booking->traveler_id,
            'error' => $exception->getMessage(),
        ]);

        event(new BookingEmailDeliveryFailed($this->booking, $exception->getMessage()));
    }
}
