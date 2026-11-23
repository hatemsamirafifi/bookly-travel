<?php

namespace App\Domains\Payment\Services;

use App\Domains\Payment\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripeService implements PaymentGateway
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
     *
     * Staging/production fail closed here before any client is built: the
     * PaymentConfigurationGuard rejects a missing or mode-mismatched
     * configuration (local/testing return early, keeping the tolerant
     * behavior fixtures rely on).
     */
    private function client(): StripeClient
    {
        if ($this->client === null) {
            app(PaymentConfigurationGuard::class)->assertValid();

            $this->client = new StripeClient(config('services.stripe.secret') ?? '');
        }

        return $this->client;
    }

    public function createPaymentIntent(
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $destinationAccountId = null,
        ?int $applicationFeeAmount = null,
        array $metadata = []
    ): string {
        $params = [
            'amount' => $amount,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => array_merge([
                'origin' => 'bookly-travel',
            ], $metadata),
        ];

        if ($destinationAccountId) {
            $params['transfer_data'] = [
                'destination' => $destinationAccountId,
            ];
            if ($applicationFeeAmount !== null && $applicationFeeAmount > 0) {
                $params['application_fee_amount'] = $applicationFeeAmount;
            }
        }

        // The idempotency key is a REQUEST OPTION, not a body param: the SDK
        // turns opts['idempotency_key'] into the Idempotency-Key HTTP header.
        // Passing it inside $params sent it as an unsupported body parameter
        // instead (stripe-php even warns when it finds option keys in params).
        $intent = $this->client()->paymentIntents->create(
            $params,
            ['idempotency_key' => $idempotencyKey],
        );

        return $intent->client_secret;
    }

    public function refund(
        string $paymentIntentId,
        ?string $idempotencyKey = null,
        bool $reverseTransfer = false,
        bool $refundApplicationFee = false
    ): string {
        $params = ['payment_intent' => $paymentIntentId];

        // Stripe Connect destination charges: reverse the transfer to the
        // connected account and return the platform's application fee.
        if ($reverseTransfer) {
            $params['reverse_transfer'] = true;
        }

        if ($refundApplicationFee) {
            $params['refund_application_fee'] = true;
        }

        // Idempotency key as a REQUEST OPTION (HTTP header), never a body param.
        $opts = $idempotencyKey !== null ? ['idempotency_key' => $idempotencyKey] : [];

        $refund = $this->client()->refunds->create($params, $opts);

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
