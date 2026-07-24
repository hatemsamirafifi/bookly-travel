<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\PaymentFailed;
use App\Domains\Payment\Events\PaymentRefunded;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use App\Domains\Payment\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Webhook;

class ProcessStripeWebhookAction
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function execute(string $payload, string $signature): void
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret'),
        );

        // Dedup + state mutation run inside the txn; domain events are collected
        // and dispatched AFTER the commit so listeners (queued email, ledger)
        // only fire on committed state.
        $toDispatch = DB::transaction(function () use ($event, $payload) {
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

                return [];
            }

            return $this->processEvent($event, $event->id);
        });

        foreach ($toDispatch as $domainEvent) {
            event($domainEvent);
        }
    }

    private function processEvent(Event $event, string $eventId): array
    {
        return match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event, $eventId),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event, $eventId),
            'charge.refunded' => $this->handleRefunded($event, $eventId),
            'charge.dispute.created' => $this->handleDisputeCreated($event, $eventId),
            'charge.dispute.closed' => $this->handleDisputeClosed($event, $eventId),
            default => Log::info('Unhandled webhook event type', ['type' => $event->type]) ?? [],
        };
    }

    private function handlePaymentSucceeded(Event $event, string $eventId): array
    {
        $intent = $event->data->object;
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

    private function handlePaymentFailed(Event $event, string $eventId): array
    {
        $intent = $event->data->object;
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

    private function handleRefunded(Event $event, string $eventId): array
    {
        $charge = $event->data->object;
        $intentId = $charge->payment_intent;

        // Look for the local refund Payment row first (action-led refund). If a
        // refund was issued from the Stripe dashboard, no local refund row
        // exists — create one so the ledger reflects the external credit.
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

        // Idempotent per F2: recordRefund no-ops on a duplicate credit entry.
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

    /**
     * Null-safe extraction of the card last4 + brand from the first charge on
     * the PaymentIntent. Guards empty charge lists and non-card payment
     * methods (e.g. wallet/redirect) so a malformed payload never throws.
     */
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

    private function handleDisputeCreated(Event $event, string $eventId): array
    {
        $dispute = $event->data->object;
        $intentId = $dispute->payment_intent;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for dispute', ['intent_id' => $intentId]);

            return [];
        }

        $updated = Payment::where('id', $payment->id)
            ->where('status', 'succeeded')
            ->update(['status' => 'disputed']);

        if (! $updated) {
            Log::info('Payment not in succeeded state — skipping dispute handler', [
                'payment_id' => $payment->id,
                'intent_id' => $intentId,
            ]);

            return [];
        }

        $payment->refresh();
        $payment->booking->update(['status' => Booking::STATUS_CONFIRMED]);

        $this->markProcessed($eventId);

        Log::error('ADMIN ALERT: Dispute created for payment — admin review required', [
            'booking_reference' => $payment->booking->reference,
            'payment_intent_id' => $intentId,
            'dispute_id' => $dispute->id,
            'dispute_reason' => $dispute->reason,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]);

        $this->notifyAdminOfDispute($payment, $dispute);

        return [];
    }

    private function notifyAdminOfDispute(Payment $payment, object $dispute): void
    {
        $webhookUrl = config('services.slack.admin_webhook_url');
        if (! $webhookUrl) {
            return;
        }

        try {
            $response = Http::timeout(5)->post($webhookUrl, [
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

            if (! $response->successful()) {
                Log::error('Slack webhook returned non-success status', [
                    'stripe_event_id' => $dispute->id,
                    'booking_reference' => $payment->booking->reference,
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Slack webhook call failed', [
                'stripe_event_id' => $dispute->id,
                'booking_reference' => $payment->booking->reference,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function handleDisputeClosed(Event $event, string $eventId): array
    {
        $dispute = $event->data->object;
        $intentId = $dispute->payment_intent;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->first();
        if (! $payment) {
            Log::warning('Payment not found for dispute closure', ['intent_id' => $intentId]);

            return [];
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

            return [];
        }

        $payment->refresh();

        if ($dispute->status === 'won') {
            $payment->booking->update(['status' => Booking::STATUS_CONFIRMED]);
        } else {
            $payment->booking->update(['status' => Booking::STATUS_CANCELLED]);
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