<?php

namespace App\Mail;

use App\Domains\Booking\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Spec 014 (FR-006, FR-014, R4): localized to the owning partner user's locale
 * (en/es/it) with EN fallback for both subject and body view. Locale is
 * resolved via the booking's tour -> partnerRecord -> user (the authoritative
 * Partner owner, see Tour::partnerRecord()).
 *
 * Spec 014 R5: implements ShouldQueue per FR-010 for defense-in-depth,
 * even though the wrapping SendBookingConfirmationEmail job currently
 * provides queueing.
 */
class PartnerNewBookingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getLocalizedSubject(),
        );
    }

    public function content(): Content
    {
        $locale = $this->resolveLocale();
        $view = "emails.partner.new-booking.{$locale}";

        if (! view()->exists($view)) {
            $view = 'emails.partner.new-booking.en';
        }

        return new Content(
            view: $view,
            with: [
                'booking' => $this->booking,
                'tour' => $this->booking->tour,
                'traveler' => $this->booking->traveler,
                'formattedTotal' => Booking::formatPrice($this->booking->total_price, $this->booking->currency ?? 'EUR'),
                'tourDate' => $this->booking->tour_date->format('l, F j, Y'),
                'participantCount' => $this->booking->participant_count,
            ],
        );
    }

    private function resolveLocale(): string
    {
        $locale = $this->booking->tour?->partnerRecord?->user?->locale ?? 'en';
        return in_array($locale, ['en', 'es', 'it'], true) ? $locale : 'en';
    }

    private function getLocalizedSubject(): string
    {
        $reference = $this->booking->reference;

        return match ($this->resolveLocale()) {
            'es' => "Nueva reserva — {$reference}",
            'it' => "Nuova prenotazione — {$reference}",
            default => "New Booking — {$reference}",
        };
    }
}