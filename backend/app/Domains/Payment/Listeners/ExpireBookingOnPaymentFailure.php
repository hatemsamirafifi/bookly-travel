<?php

namespace App\Domains\Payment\Listeners;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\PaymentFailed;

class ExpireBookingOnPaymentFailure
{
    public function handle(PaymentFailed $event): void
    {
        $event->booking->update(['status' => Booking::STATUS_EXPIRED]);
    }
}
