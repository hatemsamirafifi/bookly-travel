<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\PaymentFailed;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

            $inserted = StripeWebhookEvent::insertOrIgnore([
                'stripe_event_id' => $event->id,
                'event_type' => $event->type,
                'processing_status' => 'received',
                'payload_hash' => $hash,
                'raw_payload' => $payload,
                'created_at' => now(),
            ]);

            if (! $inserted) {
                Log::info('Duplicate webhook event skipped', ['stripe_event_id' => $event->id]);

                return;
            }

            $this->processEvent($event, $event->id);
        });
    }

    private function processEvent(Event $event, string $eventId): void
    {
        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event, $eventId),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event, $eventId),
            'charge.refunded' => $this->handleRefunded($event, $eventId),
            'charge.dispute.created' => $this->handleDisputeCreated($event, $eventId),
            'charge.dispute.closed' => $this->handleDisputeClosed($event, $eventId),
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

        $updated = Payment::where('id', $payment->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'succeeded',
                'card_last_four' => $intent->charges->data[0]->payment_method_details->card->last4 ?? null,
                'card_brand' => $intent->charges->data[0]->payment_method_details->card->brand ?? null,
            ]);

        if (! $updated) {
            Log::info('Payment not in pending state — skipping succeeded handler', [
                'payment_id' => $payment->id,
                'intent_id' => $intentId,
            ]);

            return;
        }

        $payment->refresh();

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

        $updated = Payment::where('id', $payment->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        if (! $updated) {
            Log::info('Payment not in pending state — skipping failed handler', [
                'payment_id' => $payment->id,
                'intent_id' => $intentId,
            ]);

            return;
        }

        $payment->refresh();

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

        $updated = Payment::where('id', $payment->id)
            ->where('status', 'succeeded')
            ->update(['status' => 'refunded']);

        if (! $updated) {
            Log::info('Payment not in succeeded state — skipping refunded handler', [
                'payment_id' => $payment->id,
                'intent_id' => $intentId,
            ]);

            return;
        }

        $payment->refresh();

        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }

    private function handleDisputeCreated(Event $event, string $eventId): void
    {
        $dispute = $event->data->object;
        $intentId = $dispute->payment_intent;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for dispute', ['intent_id' => $intentId]);

            return;
        }

        $updated = Payment::where('id', $payment->id)
            ->where('status', 'succeeded')
            ->update(['status' => 'disputed']);

        if (! $updated) {
            Log::info('Payment not in succeeded state — skipping dispute handler', [
                'payment_id' => $payment->id,
                'intent_id' => $intentId,
            ]);

            return;
        }

        $payment->refresh();
        $payment->booking->update(['status' => Booking::STATUS_CONFIRMED]);

        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);

        Log::error('ADMIN ALERT: Dispute created for payment — admin review required', [
            'booking_reference' => $payment->booking->reference,
            'payment_intent_id' => $intentId,
            'dispute_id' => $dispute->id,
            'dispute_reason' => $dispute->reason,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]);

        $webhookUrl = config('services.slack.admin_webhook_url');
        if ($webhookUrl) {
            Http::post($webhookUrl, [
                'text' => sprintf(
                    ':warning: *Bookly Alert* — Dispute created for booking `%s`.'
                    . ' Admin must review.'
                    . "\n*Amount*: %d %s  |  *Reason*: %s",
                    $payment->booking->reference,
                    $payment->amount,
                    $payment->currency,
                    $dispute->reason,
                ),
            ]);
        }
    }

    private function handleDisputeClosed(Event $event, string $eventId): void
    {
        $dispute = $event->data->object;
        $intentId = $dispute->payment_intent;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for dispute closure', ['intent_id' => $intentId]);

            return;
        }

        if ($dispute->status === 'won') {
            $updated = Payment::where('id', $payment->id)
                ->where('status', 'disputed')
                ->update(['status' => 'succeeded']);
        } else {
            $updated = Payment::where('id', $payment->id)
                ->where('status', 'disputed')
                ->update(['status' => 'refunded']);
        }

        if (! $updated) {
            Log::info('Payment not in disputed state — skipping dispute-closed handler', [
                'payment_id' => $payment->id,
                'intent_id' => $intentId,
            ]);

            return;
        }

        $payment->refresh();

        if ($dispute->status === 'won') {
            $payment->booking->update(['status' => Booking::STATUS_CONFIRMED]);
        } else {
            $payment->booking->update(['status' => Booking::STATUS_CANCELLED]);
        }

        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
