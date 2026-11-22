<?php

namespace App\Domains\Payment\Listeners;

use App\Domains\Booking\Jobs\SendBookingConfirmationEmail;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Services\LedgerService;
use App\Models\Tour;
use App\Models\TourDeparture;
use Illuminate\Support\Facades\DB;

class ConfirmBookingOnPayment
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditService $audit,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        $booking = $event->booking;
        $payment = $event->payment;

        DB::transaction(function () use ($booking, $payment): void {
            $departure = null;
            if ($booking->tour_departure_id !== null) {
                Tour::query()->lockForUpdate()->find($booking->tour_id);
                $departure = TourDeparture::query()->lockForUpdate()->find($booking->tour_departure_id);
            }
            $locked = Booking::lockForUpdate()->find($booking->id);

            // Guard: only a pending_payment booking transitions to confirmed.
            // A late succeeded webhook for a booking that has since expired or
            // been cancelled must NOT resurrect it (overbooking / inventory
            // corruption). Already-confirmed bookings are a no-op.
            if (! $locked || $locked->status !== Booking::STATUS_PENDING_PAYMENT) {
                return;
            }

            if (
                $departure !== null
                && $locked->pending_expires_at !== null
                && $locked->pending_expires_at->isPast()
                && $departure->remainingCapacity() < $locked->participant_count
            ) {
                $locked->update([
                    'status' => Booking::STATUS_EXPIRED,
                ]);

                $this->audit->log(
                    $locked,
                    'system',
                    null,
                    'expired_after_late_payment',
                    Booking::STATUS_PENDING_PAYMENT,
                    Booking::STATUS_EXPIRED,
                    ['payment_id' => $payment->id],
                );

                return;
            }

            // Idempotency: a charge ledger entry may already exist (duplicate
            // webhook). Still record/return so the email is not re-sent.
            $recorded = $this->ledger->recordCharge($payment);
            if (! $recorded) {
                return;
            }

            $beforeState = $locked->status;
            $locked->update([
                'status' => Booking::STATUS_CONFIRMED,
                'payment_confirmed_at' => now(),
            ]);

            $this->audit->log(
                $locked,
                'system',
                null,
                'payment_confirmed',
                $beforeState,
                Booking::STATUS_CONFIRMED,
                ['payment_id' => $payment->id],
            );

            SendBookingConfirmationEmail::dispatch($locked);
        });
    }
}
