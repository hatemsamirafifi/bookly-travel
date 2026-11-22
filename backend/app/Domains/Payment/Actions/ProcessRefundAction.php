<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Contracts\PaymentGateway;
use App\Domains\Payment\Events\RefundCompleted;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\LedgerService;
use App\Jobs\SendAdminSlackAlertJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refund a booking's succeeded charge with retry-safe, idempotent
 * coordination across three phases:
 *
 *   Phase 1 — short DB transaction: locks the booking, the succeeded charge
 *   payment, and any existing refund row, then claims exclusive refund
 *   ownership by creating a `pending` refund Payment (or re-claiming a
 *   `failed`/stale-`pending` one). Already-refunded rows and fresh in-flight
 *   claims return without touching Stripe, so concurrent or repeated calls
 *   cannot double-refund.
 *
 *   Phase 2 — OUTSIDE any DB transaction: the external Stripe refund call
 *   with the deterministic idempotency key "refund_{booking reference}". A
 *   Stripe outage here can never roll back the committed cancellation and
 *   never holds row locks across network latency.
 *
 *   Phase 3 — short DB transaction: records the Stripe refund id and the
 *   `refunded` status, writes the idempotent ledger credit, and dispatches
 *   RefundCompleted after commit.
 *
 * Connect destination charges (Spec 017) reverse the transfer and return the
 * platform's application fee; plain platform charges keep the two-argument
 * call shape.
 */
class ProcessRefundAction
{
    public function __construct(
        private readonly PaymentGateway $stripe,
        private readonly LedgerService $ledger,
    ) {}

    public function execute(Booking $booking): void
    {
        $claim = DB::transaction(function () use ($booking): ?array {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->first();

            if (! $lockedBooking) {
                return null;
            }

            $payment = Payment::where('booking_id', $lockedBooking->id)
                ->where('type', 'charge')
                ->where('status', 'succeeded')
                ->first();

            if (! $payment) {
                return null;
            }

            $existingRefund = Payment::where('booking_id', $lockedBooking->id)
                ->where('type', 'refund')
                ->lockForUpdate()
                ->first();

            if ($existingRefund && $existingRefund->status === 'refunded') {
                // Already refunded — zero further Stripe calls.
                return null;
            }

            if ($existingRefund
                && $existingRefund->status === 'pending'
                && $existingRefund->updated_at?->gt(now()->subMinutes(5))) {
                // A concurrent worker owns a fresh in-flight claim — do not
                // race it into a second Stripe call.
                return null;
            }

            if ($existingRefund) {
                // Re-claim a failed (or stale pending) refund row for this retry.
                // forceFill guarantees updated_at advances even when re-claiming
                // a stale pending row whose status attribute is unchanged.
                $existingRefund->forceFill([
                    'status' => 'pending',
                    'updated_at' => now(),
                ])->save();

                $refundPayment = $existingRefund;
            } else {
                $refundPayment = Payment::create([
                    'booking_id' => $lockedBooking->id,
                    // F2: store the REAL intent id (composite unique
                    // (stripe_payment_intent_id, type) allows one charge + one
                    // refund per intent; preserves webhook lookup-by-intent).
                    'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                    'stripe_refund_id' => null,
                    'type' => 'refund',
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => 'pending',
                ]);
            }

            return ['booking' => $lockedBooking, 'payment' => $payment, 'refund' => $refundPayment];
        });

        if ($claim === null) {
            return;
        }

        $booking = $claim['booking'];
        $payment = $claim['payment'];
        $refundPayment = $claim['refund'];

        // Deterministic idempotency key: a Stripe-side replay of this key
        // returns the original refund instead of issuing a second one.
        $idempotencyKey = "refund_{$booking->reference}";

        $isConnectCharge = ! empty($payment->metadata['destination_account_id']);

        try {
            $refundId = $isConnectCharge
                ? $this->stripe->refund(
                    $payment->stripe_payment_intent_id,
                    $idempotencyKey,
                    true,
                    ((int) ($payment->metadata['application_fee_amount'] ?? 0)) > 0,
                )
                : $this->stripe->refund($payment->stripe_payment_intent_id, $idempotencyKey);
        } catch (\Throwable $e) {
            $this->handleRefundFailure($booking, $payment, $refundPayment, $e);

            return;
        }

        DB::transaction(function () use ($booking, $refundPayment, $refundId): void {
            $refundPayment = Payment::where('id', $refundPayment->id)->lockForUpdate()->firstOrFail();
            $wasAlreadyRefunded = $refundPayment->status === 'refunded';

            $refundPayment->update([
                'stripe_refund_id' => $refundPayment->stripe_refund_id ?? $refundId,
                'status' => 'refunded',
            ]);

            // Idempotent (firstOrCreate): a webhook reconciliation, retry, or
            // replay can never double-write the credit.
            $this->ledger->recordRefund($refundPayment);

            if (! $wasAlreadyRefunded) {
                DB::afterCommit(function () use ($refundPayment, $booking): void {
                    event(new RefundCompleted($refundPayment, $booking));
                });
            }
        });
    }

    /**
     * Best-effort failure handling: the cancellation stays committed, the
     * claimed refund row is marked failed for a later retry, and ops are
     * alerted through the (sanitized) log and the queued Slack job.
     */
    private function handleRefundFailure(Booking $booking, Payment $payment, Payment $refundPayment, \Throwable $e): void
    {
        $sanitizedError = SendAdminSlackAlertJob::redactWebhookUrls(
            $e->getMessage(),
            (string) config('services.slack.admin_webhook_url'),
        );

        DB::transaction(function () use ($refundPayment, $e): void {
            $claimed = Payment::where('id', $refundPayment->id)->lockForUpdate()->first();

            // Only a still-pending claim is failed here; a webhook may have
            // already reconciled the row to refunded in the meantime.
            if ($claimed && $claimed->status === 'pending') {
                $claimed->update([
                    'status' => 'failed',
                    'metadata' => array_merge($claimed->metadata ?? [], [
                        'last_error_class' => $e::class,
                        'failed_at' => now()->toIso8601String(),
                    ]),
                ]);
            }
        });

        Log::error('ADMIN ALERT: Refund failed for booking — manual intervention required', [
            'booking_reference' => $booking->reference,
            'payment_intent_id' => $payment->stripe_payment_intent_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'error' => $sanitizedError,
        ]);

        SendAdminSlackAlertJob::dispatch(
            sprintf(
                ':warning: *Bookly Alert* — Refund failed for booking `%s`.'
                . ' Manual resolution required.'
                . "\n*Amount*: %d %s  |  *Error*: %s",
                $booking->reference,
                $payment->amount,
                $payment->currency,
                $sanitizedError,
            ),
            [
                'booking_reference' => $booking->reference,
                'payment_intent_id' => $payment->stripe_payment_intent_id,
            ],
        )->afterCommit();
    }
}
