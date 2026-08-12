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
 * Implements ShouldQueue so Mail::to(...)->send(...) dispatches the mailable
 * to the queue instead of blocking the governance request (FR-010).
 */
class PartnerApprovedMail extends Mailable implements ShouldQueue
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
        $view = "emails.partner.approved.{$locale}";

        if (! view()->exists($view)) {
            $view = 'emails.partner.approved.en';
        }

        return new Content(
            view: $view,
            with: [
                'partner' => $this->partner,
                'businessName' => $this->partner->profile?->company_name ?? 'Partner',
                'dashboardUrl' => config('app.url') . '/partner',
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
            'es' => 'Tu solicitud de partner ha sido aprobada — Bookly',
            'it' => 'La tua richiesta di partner è stata approvata — Bookly',
            default => 'Your Partner Application Has Been Approved — Bookly',
        };
    }
}
