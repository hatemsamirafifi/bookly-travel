<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\Log;

class HandlePaymentSucceeded
{
    public function execute(\Stripe\Event $event, string $eventId): array
    {
        $intent = data_get($event, 'data.object');
        $intentId = $intent->id;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for intent', ['intent_id' => $intentId]);
            return [];
        }

        [$last4, $brand] = $this->extractCardDetails($intent);

        $updated = Payment::where('id', $payment->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'succeeded',
                'card_last_four' => $last4,
                'card_brand' => $brand,
            ]);

        if (! $updated) {
            Log::info('Payment not in pending state — skipping succeeded handler', [
                'payment_id' => $payment->id,
                'intent_id' => $intentId,
            ]);
            return [];
        }

        $payment->refresh();
        $this->markProcessed($eventId);

        return [new PaymentSucceeded($payment, $payment->booking)];
    }

    private function extractCardDetails(object $intent): array
    {
        $charges = data_get($intent, 'charges.data') ?? [];
        $firstCharge = $charges[0] ?? null;
        $card = data_get($firstCharge, 'payment_method_details.card');

        return [
            $card->last4 ?? null,
            $card->brand ?? null,
        ];
    }

    private function markProcessed(string $eventId): void
    {
        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
