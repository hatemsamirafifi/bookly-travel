<?php

namespace App\Domains\Payment\Listeners;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Payment\Events\PaymentFailed;
use Illuminate\Support\Facades\DB;

class ExpireBookingOnPaymentFailure
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function handle(PaymentFailed $event): void
    {
        $booking = $event->booking;

        DB::transaction(function () use ($booking): void {
            $locked = Booking::lockForUpdate()->find($booking->id);

            // Guard: only a pending_payment booking expires on payment failure.
            // A late `payment_failed` for a booking that has since been
            // confirmed/cancelled/expired must NOT overwrite its status. This is
            // a system-initiated transition (a listener, not an HTTP request),
            // so the actor is `system`, never `auth()->user()`.
            if (! $locked || $locked->status !== Booking::STATUS_PENDING_PAYMENT) {
                return;
            }

            $beforeState = $locked->status;
            $locked->update(['status' => Booking::STATUS_EXPIRED]);

            $this->auditService->log(
                $locked,
                'system',
                null,
                'booking.status_changed',
                $beforeState,
                Booking::STATUS_EXPIRED,
                ['reason' => 'payment_failed']
            );
        });
    }
}