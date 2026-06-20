<?php

namespace App\Mail;

use App\Domains\Booking\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingVoucherMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $voucherPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->booking->locale ?? 'en') {
                'es' => "Tu voucher — {$this->booking->reference}",
                'it' => "Il tuo voucher — {$this->booking->reference}",
                default => "Your Voucher — {$this->booking->reference}",
            },
        );
    }

    public function content(): Content
    {
        $locale = $this->booking->locale ?? 'en';
        $view = "emails.booking.voucher.{$locale}";

        if (! view()->exists($view)) {
            $view = 'emails.booking.voucher.en';
        }

        return new Content(
            view: $view,
            with: [
                'booking' => $this->booking,
                'tour' => $this->booking->tour,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->voucherPath)
                ->as("voucher-{$this->booking->reference}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
