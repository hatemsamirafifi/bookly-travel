<?php

namespace App\Domains\Payment\Listeners;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Events\PaymentFailed;
use Illuminate\Support\Facades\DB;

class ExpireBookingOnPaymentFailure
{
    public function __construct(
        private readonly \App\Domains\Booking\Services\AuditService $auditService
    ) {}

    public function handle(PaymentFailed $event): void
    {
        $booking = $event->booking;
        DB::transaction(function () use ($booking) {
            $beforeState = $booking->status;

            $user = auth()->user();
            $actorType = $user ? 'user' : 'system';
            $actorId = $user ? $user->id : null;

            $booking->update(['status' => Booking::STATUS_EXPIRED]);

            $this->auditService->log(
                $booking,
                $actorType,
                $actorId,
                'booking.status_changed',
                $beforeState,
                Booking::STATUS_EXPIRED,
                ['reason' => 'payment_failed']
            );
        });
    }
}
