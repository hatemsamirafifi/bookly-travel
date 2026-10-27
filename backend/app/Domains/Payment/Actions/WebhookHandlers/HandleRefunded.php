<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use App\Domains\Payment\Models\StripeWebhookEvent;
use App\Domains\Payment\Events\PaymentRefunded;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\LedgerService;
use Illuminate\Support\Facades\Log;

class HandleRefunded
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function execute(\Stripe\Event $event, string $eventId): array
    {
        $charge = data_get($event, 'data.object');
        $intentId = $charge->payment_intent;

        $refundPayment = Payment::where('stripe_payment_intent_id', $intentId)
            ->where('type', 'refund')
            ->first();

        $chargePayment = Payment::where('stripe_payment_intent_id', $intentId)
            ->where('type', 'charge')
            ->first();

        if (! $chargePayment) {
            Log::warning('Charge payment not found for refund', ['intent_id' => $intentId]);
            return [];
        }

        if (! $refundPayment) {
            $refundPayment = $this->createExternalRefundPayment($charge, $chargePayment);
        }

        $this->ledger->recordRefund($refundPayment);

        $updated = Payment::where('id', $chargePayment->id)
            ->where('status', 'succeeded')
            ->update(['status' => 'refunded']);

        if ($updated) {
            $this->markProcessed($eventId);
        } else {
            Log::info('Charge payment not in succeeded state — refund recorded but status unchanged', [
                'payment_id' => $chargePayment->id,
                'intent_id' => $intentId,
            ]);
        }

        $refundPayment->refresh();
        $chargePayment->refresh();

        return [new PaymentRefunded($refundPayment, $chargePayment->booking)];
    }

    private function createExternalRefundPayment(object $charge, Payment $chargePayment): Payment
    {
        $amount = data_get($charge, 'refunds.data.0.amount') ?? $chargePayment->amount;
        $currency = strtoupper(data_get($charge, 'currency') ?? $chargePayment->currency);
        $refundId = data_get($charge, 'refunds.data.0.id');

        return Payment::create([
            'booking_id' => $chargePayment->booking_id,
            'stripe_payment_intent_id' => $chargePayment->stripe_payment_intent_id,
            'stripe_refund_id' => $refundId,
            'type' => 'refund',
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'refunded',
        ]);
    }

    private function markProcessed(string $eventId): void
    {
        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
