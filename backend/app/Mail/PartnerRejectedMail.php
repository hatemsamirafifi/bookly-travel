<?php

namespace App\Mail;

use App\Domains\Partner\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Partner $partner,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Partner Application Status — Bookly',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner.rejected',
            with: [
                'partner' => $this->partner,
                'businessName' => $this->partner->profile?->company_name ?? 'Partner',
                'reason' => $this->reason,
                'supportEmail' => config('mail.from.address', 'support@bookly.com'),
            ],
        );
    }
}
