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
 * Spec 015 (FR-011, contracts/api.md §5.5): localized to the partner user's
 * locale (en/es/it) with EN fallback for both subject and body view.
 * Notifies partner of reinstatement and informs them tours must be resubmitted.
 */
class PartnerReinstatedMail extends Mailable implements ShouldQueue
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
        $view = "emails.partner.reinstated.{$locale}";

        if (! view()->exists($view)) {
            $view = 'emails.partner.reinstated.en';
        }

        return new Content(
            view: $view,
            with: [
                'partner' => $this->partner,
                'businessName' => $this->partner->profile?->company_name ?? 'Partner',
                'dashboardUrl' => config('app.url') . '/partner',
                'supportEmail' => config('mail.from.address', 'support@bookly.com'),
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
            'es' => 'Tu cuenta de partner de Bookly ha sido restablecida — Bookly',
            'it' => 'Il tuo account partner Bookly è stato ripristinato — Bookly',
            default => 'Your Bookly Partner Account Has Been Reinstated — Bookly',
        };
    }
}
