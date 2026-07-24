<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreatePaymentIntentAction
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    /**
     * Ensure a Stripe PaymentIntent exists for the booking and return its
     * client_secret. Idempotent across retries:
     *  - existing charge Payment with a stored client_secret → reuse it (never
     *    recreate, never call Stripe again);
     *  - existing charge Payment whose stored secret is missing (inconsistent)
     *    → cancel the orphaned intent and create a fresh one;
     *  - no charge Payment yet (first attempt, or Phase 2 never ran) → create
     *    the PaymentIntent and persist the Payment row.
     *
     * Stripe network I/O happens OUTSIDE any DB transaction. If the intent is
     * created but the local Payment write then fails, the orphan intent is
     * canceled best-effort before rethrowing.
     */
    public function execute(Booking $booking): string
    {
        $existing = Payment::where('booking_id', $booking->id)
            ->where('type', 'charge')
            ->first();

        if ($existing && ($existing->metadata['client_secret'] ?? null)) {
            return $existing->metadata['client_secret'];
        }

        if ($existing && $existing->stripe_payment_intent_id) {
            // Inconsistent state: a Payment row exists with an intent id but no
            // stored client_secret (e.g. an older code path, or a partial write).
            // Cancel the orphaned intent before creating a fresh one.
            Log::warning('Payment row missing stored client_secret — canceling orphaned PaymentIntent', [
                'booking_id' => $booking->id,
                'payment_id' => $existing->id,
                'intent_id' => $existing->stripe_payment_intent_id,
            ]);
            $this->stripe->cancelPaymentIntent($existing->stripe_payment_intent_id);
        }

        return $this->createFresh($booking, $existing);
    }

    private function createFresh(Booking $booking, ?Payment $existing): string
    {
        // Stripe I/O outside the DB transaction.
        $clientSecret = $this->stripe->createPaymentIntent(
            amount: $booking->total_price,
            currency: $booking->currency,
            idempotencyKey: $booking->idempotency_key,
        );

        $intentId = StripeService::parseIntentId($clientSecret);

        try {
            DB::transaction(function () use ($existing, $booking, $intentId, $clientSecret): void {
                if ($existing) {
                    $existing->update([
                        'stripe_payment_intent_id' => $intentId,
                        'status' => 'pending',
                        'metadata' => ['client_secret' => $clientSecret],
                    ]);
                } else {
                    Payment::create([
                        'booking_id' => $booking->id,
                        'stripe_payment_intent_id' => $intentId,
                        'type' => 'charge',
                        'amount' => $booking->total_price,
                        'currency' => $booking->currency,
                        'status' => 'pending',
                        'metadata' => ['client_secret' => $clientSecret],
                    ]);
                }

                // Keep the legacy booking.stripe_payment_intent_id column in
                // sync (displayed in the admin Filament resource) with the
                // canonical Payment row.
                $booking->update(['stripe_payment_intent_id' => $intentId]);
            });
        } catch (\Throwable $e) {
            // Orphan compensation: the intent was created in Stripe but we
            // failed to persist the link locally. Cancel it so no orphan
            // PaymentIntent lingers, log both, and rethrow.
            Log::error('Failed to persist Payment after PaymentIntent creation — canceling orphan', [
                'booking_id' => $booking->id,
                'intent_id' => $intentId,
                'error' => $e->getMessage(),
            ]);
            $this->stripe->cancelPaymentIntent($intentId);

            throw $e;
        }

        return $clientSecret;
    }
}