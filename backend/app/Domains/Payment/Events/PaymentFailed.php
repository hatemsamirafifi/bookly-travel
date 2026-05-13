<?php

namespace App\Domains\Payment\Events;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly Booking $booking,
    ) {}
}
