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
 * Spec 014 (FR-006, FR-010, FR-014, R4): localized to the partner user's
 * locale (en/es/it) with EN fallback for both subject and body view.
 * The rejection reason is operator-authored free text and is rendered
 * verbatim in every locale (not translated). Implements ShouldQueue so
 * Mail::to(...)->send(...) dispatches the mailable to the queue instead of
 * blocking the governance request (FR-010).
 */
class PartnerRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Partner $partner,
        public readonly string $reason,
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
        $view = "emails.partner.rejected.{$locale}";

        if (! view()->exists($view)) {
            $view = 'emails.partner.rejected.en';
        }

        return new Content(
            view: $view,
            with: [
                'partner' => $this->partner,
                'businessName' => $this->partner->profile?->company_name ?? 'Partner',
                'reason' => $this->reason,
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
            'es' => 'Estado de tu solicitud de partner — Bookly',
            'it' => 'Stato della tua richiesta di partner — Bookly',
            default => 'Your Partner Application Status — Bookly',
        };
    }
}