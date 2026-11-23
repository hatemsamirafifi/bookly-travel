<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\StripeWebhookEvent;
use App\Jobs\SendAdminSlackAlertJob;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

class HandleDisputeCreated
{
    public function execute(Event $event, string $eventId): array
    {
        $dispute = data_get($event, 'data.object');
        $intentId = $dispute->payment_intent;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)
            ->where('type', 'charge')
            ->first();
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
        $payment->booking->update(['status' => 'confirmed']);
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

    /**
     * Slack is best-effort (FR-012): the queued job owns the webhook URL and
     * is dispatched after commit, so the external HTTP call never runs inside
     * the webhook-processing DB transaction (where it would hold row locks on
     * Slack's latency) and a Slack outage can never roll the dispute state
     * back.
     */
    private function notifyAdminOfDispute(Payment $payment, object $dispute): void
    {
        SendAdminSlackAlertJob::dispatch(
            sprintf(
                ':warning: *Bookly Alert* — Dispute created for booking `%s`.'
                . ' Admin must review.'
                . "\n*Amount*: %d %s  |  *Reason*: %s",
                $payment->booking->reference,
                $payment->amount,
                $payment->currency,
                $dispute->reason,
            ),
            [
                'stripe_event_id' => $dispute->id,
                'booking_reference' => $payment->booking->reference,
            ],
        )->afterCommit();
    }

    private function markProcessed(string $eventId): void
    {
        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
