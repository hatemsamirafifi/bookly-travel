<?php

namespace App\Mail;

use App\Domains\Partner\Models\PartnerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PartnerInvitation $invitation,
        public string $adminName,
        string $mailLocale = 'en'
    ) {
        $this->locale = $mailLocale;
    }

    public function envelope(): Envelope
    {
        $subjects = [
            'en' => 'You are invited to join Bookly as a Partner',
            'es' => 'Estás invitado a unirte a Bookly como Socio',
            'it' => 'Sei invitato a unirti a Bookly come Partner',
        ];

        return new Envelope(
            subject: $subjects[$this->locale] ?? $subjects['en'],
        );
    }

    public function content(): Content
    {
        $locale = in_array($this->locale, ['en', 'es', 'it']) ? $this->locale : 'en';

        return new Content(
            view: "emails.partner.invitation.{$locale}",
            with: [
                'companyName' => $this->invitation->company_name,
                'adminName' => $this->adminName,
                'inviteUrl' => config('app.frontend_url', 'http://localhost:3000') . "/partner-invite?token={$this->invitation->token}",
                'expiresAt' => $this->invitation->expires_at->toFormattedDateString(),
            ],
        );
    }
}
