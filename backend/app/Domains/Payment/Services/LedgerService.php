<?php

namespace App\Domains\Payment\Services;

use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;

class LedgerService
{
    public function recordCharge(Payment $payment): bool
    {
        $entry = FinancialLedgerEntry::firstOrCreate(
            [
                'payment_id' => $payment->id,
                'entry_type' => 'debit',
            ],
            [
                'booking_id' => $payment->booking_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'actor' => 'system',
                'description' => 'Payment captured for booking ' . $payment->booking->reference,
            ]
        );

        return $entry->wasRecentlyCreated;
    }

    public function hasChargeRecord(Payment $payment): bool
    {
        return FinancialLedgerEntry::where('payment_id', $payment->id)
            ->where('entry_type', 'debit')
            ->exists();
    }

    public function recordRefund(Payment $payment): bool
    {
        $entry = FinancialLedgerEntry::firstOrCreate(
            [
                'payment_id' => $payment->id,
                'entry_type' => 'credit',
            ],
            [
                'booking_id' => $payment->booking_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'actor' => 'system',
                'description' => 'Refund issued for booking ' . $payment->booking->reference,
            ]
        );

        return $entry->wasRecentlyCreated;
    }
}
