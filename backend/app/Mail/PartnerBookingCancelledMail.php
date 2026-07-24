<?php

namespace App\Mail;

use App\Domains\Booking\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Spec 014 (FR-006, FR-014, R4): localized to the owning partner user's locale
 * (en/es/it) with EN fallback for both subject and body view. Locale is
 * resolved via the booking's tour -> partnerRecord -> user (the authoritative
 * Partner owner, see Tour::partnerRecord()).
 */
class PartnerBookingCancelledMail extends Mailable
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
        $view = "emails.partner.booking-cancelled.{$locale}";

        if (! view()->exists($view)) {
            $view = 'emails.partner.booking-cancelled.en';
        }

        return new Content(
            view: $view,
            with: [
                'booking' => $this->booking,
                'tour' => $this->booking->tour,
                'formattedTotal' => Booking::formatPrice($this->booking->total_price, $this->booking->currency ?? 'EUR'),
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
            'es' => "Reserva cancelada — {$reference}",
            'it' => "Prenotazione cancellata — {$reference}",
            default => "Booking Cancelled — {$reference}",
        };
    }
}