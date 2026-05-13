<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\LedgerService;
use App\Domains\Payment\Services\StripeService;

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

        $refundId = $this->stripe->refund($payment->stripe_payment_intent_id);

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
    }
}
