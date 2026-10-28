<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use App\Domains\Payment\Events\PaymentFailed;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

class HandlePaymentFailed
{
    public function execute(Event $event, string $eventId): array
    {
        $intent = data_get($event, 'data.object');
        $intentId = $intent->id;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for failed intent', ['intent_id' => $intentId]);

            return [];
        }

        $updated = Payment::where('id', $payment->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        if (! $updated) {
            Log::info('Payment not in pending state — skipping failed handler', [
                'payment_id' => $payment->id,
                'intent_id' => $intentId,
            ]);

            return [];
        }

        $payment->refresh();
        $this->markProcessed($eventId);

        return [new PaymentFailed($payment, $payment->booking)];
    }

    private function markProcessed(string $eventId): void
    {
        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
