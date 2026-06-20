<?php

namespace App\Mail;

use App\Domains\Partner\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Partner $partner,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Partner Application Has Been Approved — Bookly',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner.approved',
            with: [
                'partner' => $this->partner,
                'businessName' => $this->partner->profile?->company_name ?? 'Partner',
                'dashboardUrl' => config('app.url') . '/partner',
            ],
        );
    }
}
