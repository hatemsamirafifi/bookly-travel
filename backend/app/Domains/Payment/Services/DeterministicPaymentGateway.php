<?php

namespace App\Domains\Payment\Services;

use App\Domains\Payment\Contracts\PaymentGateway;
use LogicException;

/**
 * Local/test payment adapter for deterministic browser and API verification.
 * The service provider refuses to resolve this adapter outside local/testing.
 */
class DeterministicPaymentGateway implements PaymentGateway
{
    public function __construct()
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('The deterministic payment gateway is restricted to local and testing environments.');
        }
    }

    public function createPaymentIntent(
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $destinationAccountId = null,
        ?int $applicationFeeAmount = null,
        array $metadata = []
    ): string {
        $digest = hash_hmac(
            'sha256',
            implode('|', [$amount, strtoupper($currency), $idempotencyKey]),
            (string) config('app.key'),
        );

        return 'pi_test_' . substr($digest, 0, 24) . '_secret_' . substr($digest, 24);
    }

    public function refund(
        string $paymentIntentId,
        ?string $idempotencyKey = null,
        bool $reverseTransfer = false,
        bool $refundApplicationFee = false
    ): string {
        // The deterministic adapter has no external resource, so the Connect
        // refund flags (transfer reversal, application fee return) are no-ops
        // here; they only shape the real StripeService call.
        return 're_test_' . substr(hash_hmac(
            'sha256',
            $paymentIntentId . '|' . ($idempotencyKey ?? ''),
            (string) config('app.key'),
        ), 0, 32);
    }

    public function cancelPaymentIntent(string $intentId): void
    {
        // There is no external resource to release in the deterministic adapter.
    }
}
