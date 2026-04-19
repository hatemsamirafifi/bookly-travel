<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected User $user,
        protected string $verificationUrl
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getLocalizedSubject(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verify',
            with: [
                'userName' => $this->user->name,
                'verificationUrl' => $this->verificationUrl,
                'platformName' => config('app.name', 'Bookly'),
            ],
        );
    }

    /**
     * Get the localized subject line based on user's locale.
     */
    protected function getLocalizedSubject(): string
    {
        $locale = $this->user->locale ?? 'en';

        return match ($locale) {
            'es' => 'Verifica tu correo electrónico',
            'it' => 'Verifica il tuo indirizzo email',
            default => 'Verify your email address',
        };
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}