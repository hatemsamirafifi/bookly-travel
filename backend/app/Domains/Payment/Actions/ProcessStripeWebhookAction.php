<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\PaymentFailed;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Webhook;

class ProcessStripeWebhookAction
{
    public function execute(string $payload, string $signature): void
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret'),
        );

        DB::transaction(function () use ($event, $payload) {
            $hash = hash('sha256', $payload);

            $existing = StripeWebhookEvent::where('stripe_event_id', $event->id)->first();
            if ($existing) {
                Log::info('Duplicate webhook event skipped', ['stripe_event_id' => $event->id]);
                return;
            }

            StripeWebhookEvent::create([
                'stripe_event_id' => $event->id,
                'event_type' => $event->type,
                'processing_status' => 'received',
                'payload_hash' => $hash,
                'raw_payload' => $payload,
            ]);

            $this->processEvent($event, $event->id);
        });
    }

    private function processEvent(Event $event, string $eventId): void
    {
        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event, $eventId),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event, $eventId),
            'charge.refunded' => $this->handleRefunded($event, $eventId),
            default => Log::info('Unhandled webhook event type', ['type' => $event->type]),
        };
    }

    private function handlePaymentSucceeded(Event $event, string $eventId): void
    {
        $intent = $event->data->object;
        $intentId = $intent->id;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for intent', ['intent_id' => $intentId]);
            return;
        }

        $payment->update([
            'status' => 'succeeded',
            'card_last_four' => $intent->charges->data[0]->payment_method_details->card->last4 ?? null,
            'card_brand' => $intent->charges->data[0]->payment_method_details->card->brand ?? null,
        ]);

        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);

        event(new PaymentSucceeded($payment, $payment->booking));
    }

    private function handlePaymentFailed(Event $event, string $eventId): void
    {
        $intent = $event->data->object;
        $intentId = $intent->id;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for failed intent', ['intent_id' => $intentId]);
            return;
        }

        $payment->update(['status' => 'failed']);

        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);

        event(new PaymentFailed($payment, $payment->booking));
    }

    private function handleRefunded(Event $event, string $eventId): void
    {
        $charge = $event->data->object;
        $intentId = $charge->payment_intent;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for refund', ['intent_id' => $intentId]);
            return;
        }

        $payment->update(['status' => 'refunded']);

        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
