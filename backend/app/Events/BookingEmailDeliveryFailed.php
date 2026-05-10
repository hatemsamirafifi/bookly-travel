<?php

namespace App\Events;

use App\Domains\Booking\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by SendBookingConfirmationEmail::failed() when all retry attempts are
 * exhausted. Consumed by NotifyAdminOnEmailDeliveryFailure listener (FR-028).
 *
 * The booking remains confirmed — this event is purely for operator alerting.
 */
class BookingEmailDeliveryFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $errorMessage,
    ) {}
}
