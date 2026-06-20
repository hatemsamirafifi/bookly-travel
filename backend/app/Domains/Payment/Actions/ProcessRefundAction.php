<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\RefundCompleted;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\LedgerService;
use App\Domains\Payment\Services\StripeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessRefundAction
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly LedgerService $ledger,
    ) {}

    public function execute(Booking $booking): void
    {
        $payment = $booking->payment;

        if (! $payment || $payment->status !== 'succeeded') {
            return;
        }

        $existingRefund = Payment::where('booking_id', $booking->id)
            ->where('type', 'refund')
            ->where('status', 'refunded')
            ->first();

        if ($existingRefund) {
            return;
        }

        $idempotencyKey = "refund_{$booking->reference}";

        try {
            $refundId = $this->stripe->refund($payment->stripe_payment_intent_id, $idempotencyKey);

            $refundPayment = Payment::create([
                'booking_id' => $booking->id,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'stripe_refund_id' => $refundId,
                'type' => 'refund',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => 'refunded',
            ]);

            $this->ledger->recordRefund($refundPayment);

            event(new RefundCompleted($refundPayment, $booking));
        } catch (\Throwable $e) {
            Log::error('ADMIN ALERT: Refund failed for booking — manual intervention required', [
                'booking_reference' => $booking->reference,
                'payment_intent_id' => $payment->stripe_payment_intent_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'error' => $e->getMessage(),
            ]);

            $webhookUrl = config('services.slack.admin_webhook_url');
            if ($webhookUrl) {
                Http::post($webhookUrl, [
                    'text' => sprintf(
                        ':warning: *Bookly Alert* — Refund failed for booking `%s`.'
                        . ' Manual resolution required.'
                        . "\n*Amount*: %d %s  |  *Error*: %s",
                        $booking->reference,
                        $payment->amount,
                        $payment->currency,
                        $e->getMessage(),
                    ),
                ]);
            }
        }
    }
}
