<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use App\Domains\Payment\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HandleDisputeCreated
{
    public function execute(\Stripe\Event $event, string $eventId): array
    {
        $dispute = data_get($event, 'data.object');
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

    private function markProcessed(string $eventId): void
    {
        StripeWebhookEvent::where('stripe_event_id', $eventId)
            ->update(['processing_status' => 'processed', 'processed_at' => now()]);
    }
}
