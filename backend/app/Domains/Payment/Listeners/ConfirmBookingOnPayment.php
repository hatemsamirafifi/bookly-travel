<?php

namespace App\Domains\Payment\Listeners;

use App\Domains\Booking\Jobs\SendBookingConfirmationEmail;
use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Services\LedgerService;

class ConfirmBookingOnPayment
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        $booking = $event->booking;

        $booking->update([
            'status' => Booking::STATUS_CONFIRMED,
            'payment_confirmed_at' => now(),
        ]);

        $this->ledger->recordCharge($event->payment);

        SendBookingConfirmationEmail::dispatch($booking);
    }
}
