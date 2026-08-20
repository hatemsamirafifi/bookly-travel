<?php

namespace App\Mail;

use App\Domains\Partner\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Spec 015 (FR-011, contracts/api.md §5.1): localized to the partner user's
 * locale (en/es/it) with EN fallback for both subject and body view.
 * Sent when a partner submits self-registration.
 */
class PartnerApplicationReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Partner $partner,
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
        $view = "emails.partner.application-received.{$locale}";

        if (! view()->exists($view)) {
            $view = 'emails.partner.application-received.en';
        }

        return new Content(
            view: $view,
            with: [
                'partner' => $this->partner,
                'businessName' => $this->partner->profile?->company_name ?? 'Partner',
                'contactPerson' => $this->partner->user?->name ?? 'Partner',
                'onboardingUrl' => config('app.url') . '/partner/onboarding',
            ],
        );
    }

    private function resolveLocale(): string
    {
        $locale = $this->partner->user?->locale ?? 'en';

        return in_array($locale, ['en', 'es', 'it'], true) ? $locale : 'en';
    }

    private function getLocalizedSubject(): string
    {
        return match ($this->resolveLocale()) {
            'es' => 'Tu solicitud de partner en Bookly ha sido recibida',
            'it' => 'La tua richiesta di partner su Bookly è stata ricevuta',
            default => 'Your Bookly Partner Application Has Been Received',
        };
    }
}
