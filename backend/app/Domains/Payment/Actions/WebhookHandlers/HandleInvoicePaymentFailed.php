<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use Illuminate\Support\Facades\Log;

class HandleInvoicePaymentFailed
{
    public function execute(\Stripe\Event $event, string $eventId): array
    {
        $invoice = data_get($event, 'data.object');
        $bookingReference = data_get($invoice, 'metadata.booking_reference');

        if ($bookingReference) {
            $booking = \App\Domains\Booking\Models\Booking::where('reference', $bookingReference)->first();
            if ($booking) {
                $booking->update([
                    'stripe_invoice_id' => $invoice->id,
                    'stripe_invoice_status' => $invoice->status ?? 'open',
                ]);
                Log::warning('Invoice payment failed for booking', [
                    'reference' => $bookingReference,
                    'invoice_id' => $invoice->id,
                ]);
            }
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
