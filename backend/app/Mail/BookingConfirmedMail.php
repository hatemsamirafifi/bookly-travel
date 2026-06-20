<?php

namespace App\Mail;

use App\Domains\Booking\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable
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
        $locale = $this->booking->locale ?? 'en';
        $view = "emails.booking.confirmed.{$locale}";

        if (! view()->exists($view)) {
            $view = 'emails.booking.confirmed.en';
        }

        return new Content(
            view: $view,
            with: [
                'booking' => $this->booking,
                'tour' => $this->booking->tour,
                'traveler' => $this->booking->traveler,
                'formattedTotal' => Booking::formatPrice($this->booking->total_price, $this->booking->currency ?? 'EUR'),
                'tourDate' => $this->booking->tour_date->format('l, F j, Y'),
            ],
        );
    }

    private function getLocalizedSubject(): string
    {
        return match ($this->booking->locale ?? 'en') {
            'es' => "Reserva confirmada — {$this->booking->reference}",
            'it' => "Prenotazione confermata — {$this->booking->reference}",
            default => "Booking Confirmed — {$this->booking->reference}",
        };
    }
}
