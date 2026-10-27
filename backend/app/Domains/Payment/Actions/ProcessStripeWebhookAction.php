<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\Actions\WebhookHandlers\HandleAccountUpdated;
use App\Domains\Payment\Actions\WebhookHandlers\HandleDisputeClosed;
use App\Domains\Payment\Actions\WebhookHandlers\HandleDisputeCreated;
use App\Domains\Payment\Actions\WebhookHandlers\HandleInvoiceFinalized;
use App\Domains\Payment\Actions\WebhookHandlers\HandleInvoicePaid;
use App\Domains\Payment\Actions\WebhookHandlers\HandleInvoicePaymentFailed;
use App\Domains\Payment\Actions\WebhookHandlers\HandlePaymentFailed;
use App\Domains\Payment\Actions\WebhookHandlers\HandlePaymentSucceeded;
use App\Domains\Payment\Actions\WebhookHandlers\HandleRefunded;
use App\Domains\Payment\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Webhook;

class ProcessStripeWebhookAction
{
    public function __construct(
        private readonly HandlePaymentSucceeded $handlePaymentSucceeded,
        private readonly HandlePaymentFailed $handlePaymentFailed,
        private readonly HandleRefunded $handleRefunded,
        private readonly HandleDisputeCreated $handleDisputeCreated,
        private readonly HandleDisputeClosed $handleDisputeClosed,
        private readonly HandleAccountUpdated $handleAccountUpdated,
        private readonly HandleInvoicePaid $handleInvoicePaid,
        private readonly HandleInvoicePaymentFailed $handleInvoicePaymentFailed,
        private readonly HandleInvoiceFinalized $handleInvoiceFinalized,
    ) {}

    public function execute(string $payload, string $signature): void
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret'),
        );

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

            return $this->handleEvent($event, $event->id);
        });

        foreach ($toDispatch as $domainEvent) {
            event($domainEvent);
        }
    }

    private function handleEvent(Event $event, string $eventId): array
    {
        return match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded->execute($event, $eventId),
            'payment_intent.payment_failed' => $this->handlePaymentFailed->execute($event, $eventId),
            'charge.refunded' => $this->handleRefunded->execute($event, $eventId),
            'charge.dispute.created' => $this->handleDisputeCreated->execute($event, $eventId),
            'charge.dispute.closed' => $this->handleDisputeClosed->execute($event, $eventId),
            'account.updated' => $this->handleAccountUpdated->execute($event, $eventId),
            'invoice.paid' => $this->handleInvoicePaid->execute($event, $eventId),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed->execute($event, $eventId),
            'invoice.finalized' => $this->handleInvoiceFinalized->execute($event, $eventId),
            default => $this->handleUnsupportedEvent($event),
        };
    }

    /** @return array{} */
    private function handleUnsupportedEvent(Event $event): array
    {
        Log::info('Unhandled webhook event type', ['type' => $event->type]);

        return [];
    }
}
