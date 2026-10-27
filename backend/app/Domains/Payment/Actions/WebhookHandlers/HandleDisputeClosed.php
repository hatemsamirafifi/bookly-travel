<?php

namespace App\Domains\Payment\Actions\WebhookHandlers;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use Illuminate\Support\Facades\Log;

class HandleDisputeClosed
{
    public function execute(\Stripe\Event $event, string $eventId): array
    {
        $dispute = data_get($event, 'data.object');
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
