<?php

namespace App\Domains\Payment\Services;

use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;

class LedgerService
{
    public function recordCharge(Payment $payment): void
    {
        FinancialLedgerEntry::create([
            'booking_id' => $payment->booking_id,
            'payment_id' => $payment->id,
            'entry_type' => 'debit',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'actor' => 'system',
            'description' => 'Payment captured for booking ' . $payment->booking->reference,
        ]);
    }

    public function recordRefund(Payment $payment): void
    {
        FinancialLedgerEntry::create([
            'booking_id' => $payment->booking_id,
            'payment_id' => $payment->id,
            'entry_type' => 'credit',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'actor' => 'system',
            'description' => 'Refund issued for booking ' . $payment->booking->reference,
        ]);
    }
}
