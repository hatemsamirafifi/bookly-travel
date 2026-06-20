<?php

namespace App\Mail;

use App\Domains\Booking\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerNewBookingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Booking — {$this->booking->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner.new-booking',
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
}
