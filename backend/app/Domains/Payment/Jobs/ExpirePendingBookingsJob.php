<?php

namespace App\Domains\Payment\Jobs;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePendingBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AuditService $auditService, StripeService $stripe): void
    {
        // Phase 1 — expire pending bookings inside a DB transaction. Collect
        // their PaymentIntent ids so we can cancel them AFTER the commit (no
        // Stripe I/O inside the txn). A stale intent that later succeeds then
        // hits the ConfirmBookingOnPayment guard (status is expired) and is
        // rejected — closing the late-webhook-resurrects-expired race.
        $intentsToCancel = DB::transaction(function () use ($auditService) {
            $bookings = Booking::where('status', Booking::STATUS_PENDING_PAYMENT)
                ->where('pending_expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            $intentIds = [];
            foreach ($bookings as $booking) {
                $beforeState = $booking->status;
                $booking->update(['status' => Booking::STATUS_EXPIRED]);

                $auditService->log(
                    $booking,
                    'system',
                    null,
                    'booking.expired',
                    $beforeState,
                    Booking::STATUS_EXPIRED,
                    ['reason' => 'pending_payment_timeout']
                );

                // The charge Payment row holds the intent id to cancel.
                $intentId = Payment::where('booking_id', $booking->id)
                    ->where('type', 'charge')
                    ->value('stripe_payment_intent_id');
                if ($intentId) {
                    $intentIds[] = $intentId;
                }
            }

            return $intentIds;
        });

        // Phase 2 — best-effort cancel the Stripe PaymentIntents now that the
        // expiry has committed. Failures are logged and swallowed: expiry must
        // remain durable even if Stripe is unreachable.
        foreach ($intentsToCancel as $intentId) {
            try {
                $stripe->cancelPaymentIntent($intentId);
            } catch (\Throwable $e) {
                Log::warning('Failed to cancel Stripe PaymentIntent on expiry', [
                    'intent_id' => $intentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (count($intentsToCancel) > 0) {
            Log::info('Expired pending payment bookings', ['count' => count($intentsToCancel)]);
        }
    }
}