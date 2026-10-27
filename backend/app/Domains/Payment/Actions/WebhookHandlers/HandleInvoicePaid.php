<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use App\Domains\Payment\Models\StripeWebhookEvent;
use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\LedgerService;
use Illuminate\Support\Facades\Log;

class HandleInvoicePaid
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function execute(\Stripe\Event $event, string $eventId): array
    {
        $invoice = data_get($event, 'data.object');
        $bookingReference = data_get($invoice, 'metadata.booking_reference');

        if (! $bookingReference) {
            $this->markProcessed($eventId);
            return [];
        }

        $booking = Booking::where('reference', $bookingReference)->first();
        if (! $booking) {
            Log::warning('Booking not found for invoice.paid webhook', ['reference' => $bookingReference]);
            $this->markProcessed($eventId);
            return [];
        }

        $paymentIntentId = data_get($invoice, 'payment_intent');
        $paymentId = $paymentIntentId ?? ('in_' . $invoice->id);

        $payment = Payment::firstOrCreate(
            ['stripe_payment_intent_id' => $paymentId],
            [
                'booking_id' => $booking->id,
                'type' => 'charge',
                'amount' => (int) $invoice->amount_paid,
                'currency' => strtoupper($invoice->currency),
                'status' => 'succeeded',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'invoice_pdf' => $invoice->invoice_pdf,
                ],
            ]
        );

        if ($payment->status !== 'succeeded') {
            $payment->update(['status' => 'succeeded']);
        }

        $booking->update([
            'status' => Booking::STATUS_CONFIRMED,
            'stripe_payment_intent_id' => $paymentId,
            'stripe_invoice_id' => $invoice->id,
            'stripe_invoice_url' => $invoice->hosted_invoice_url,
            'stripe_invoice_pdf' => $invoice->invoice_pdf,
            'stripe_invoice_status' => $invoice->status ?? 'paid',
        ]);

        $this->ledger->recordCharge($payment);
        $this->markProcessed($eventId);

        return [new PaymentSucceeded($payment, $booking)];
    }

    private function markProcessed(string $eventId): void
    {
        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
