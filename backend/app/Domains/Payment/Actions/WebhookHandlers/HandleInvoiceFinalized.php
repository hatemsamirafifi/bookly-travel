<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use Illuminate\Support\Facades\Log;

class HandleInvoiceFinalized
{
    public function execute(\Stripe\Event $event, string $eventId): array
    {
        $invoice = data_get($event, 'data.object');
        $bookingReference = data_get($invoice, 'metadata.booking_reference');

        if ($bookingReference) {
            \App\Domains\Booking\Models\Booking::where('reference', $bookingReference)->update([
                'stripe_invoice_id' => $invoice->id,
                'stripe_invoice_url' => $invoice->hosted_invoice_url,
                'stripe_invoice_pdf' => $invoice->invoice_pdf,
                'stripe_invoice_status' => $invoice->status ?? 'open',
            ]);
            Log::info('Invoice finalized for booking', [
                'reference' => $bookingReference,
                'invoice_id' => $invoice->id,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
            ]);
        }

        $this->markProcessed($eventId);
        return [];
    }

    private function markProcessed(string $eventId): void
    {
        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
