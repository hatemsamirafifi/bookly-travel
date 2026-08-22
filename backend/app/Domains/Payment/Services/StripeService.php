<?php

namespace App\Domains\Payment\Services;

use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripeService
{
    private ?StripeClient $client = null;

    /**
     * Build the per-instance StripeClient lazily. Constructing it eagerly in
     * the constructor crashed container resolution in any environment where
     * `services.stripe.secret` is unset (the stripe-php client rejects null
     * AND empty-string keys at construction) — breaking routes that merely
     * inject the service without calling Stripe. Lazy init matches the old
     * global `Stripe::setApiKey(null)` tolerance: a missing secret only
     * surfaces when an actual API call is attempted, which is the correct
     * failure point (and callers like ProcessRefundAction catch it).
     */
    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(config('services.stripe.secret') ?? '');
    }

    public function createPaymentIntent(int $amount, string $currency, string $idempotencyKey): string
    {
        $intent = $this->client()->paymentIntents->create([
            'amount' => $amount,
            'currency' => strtolower($currency),
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'origin' => 'bookly-travel',
            ],
        ]);

        return $intent->client_secret;
    }

    public function refund(string $paymentIntentId, ?string $idempotencyKey = null): string
    {
        $params = ['payment_intent' => $paymentIntentId];

        if ($idempotencyKey !== null) {
            $params['idempotency_key'] = $idempotencyKey;
        }

        $refund = $this->client()->refunds->create($params);

        return $refund->id;
    }

    /**
     * Cancel a PaymentIntent best-effort. Tolerates intents that are already
     * canceled or in a non-cancelable state (e.g. succeeded) — those are not
     * actionable, so we log and return rather than surface a Stripe error.
     */
    public function cancelPaymentIntent(string $intentId): void
    {
        try {
            $this->client()->paymentIntents->cancel($intentId, []);
        } catch (InvalidRequestException $e) {
            Log::warning('Stripe PaymentIntent cancel tolerated non-actionable state', [
                'intent_id' => $intentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse the PaymentIntent id from a client_secret (`pi_xxx_secret_yyy`).
     * Centralized so CreateBookingAction and CreatePaymentIntentAction share
     * a single parser. Static + pure so it stays out of the instance's mock
     * surface — tests that mock StripeService for createPaymentIntent/refund
     * don't need to stub the parser too.
     */
    public static function parseIntentId(string $clientSecret): string
    {
        return explode('_secret_', $clientSecret)[0];
    }
}
