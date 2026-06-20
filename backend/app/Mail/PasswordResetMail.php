<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected User $user,
        protected string $resetUrl
    ) {}

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
            view: 'emails.auth.password-reset',
            with: [
                'userName' => $this->user->name,
                'resetUrl' => $this->resetUrl,
                'platformName' => config('app.name', 'Bookly'),
                'expirationMinutes' => 60,
                'locale' => $this->user->locale ?? 'en',
            ],
        );
    }

    /**
     * Get the localized subject line based on user's locale.
     */
    protected function getLocalizedSubject(): string
    {
        $locale = $this->user->locale ?? 'en';

        return __('emails.password_reset.subject', [], $locale);
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
