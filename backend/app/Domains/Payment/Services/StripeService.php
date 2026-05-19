<?php

namespace App\Domains\Payment\Services;

use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPaymentIntent(int $amount, string $currency, string $idempotencyKey): string
    {
        $intent = PaymentIntent::create([
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

        $refund = Refund::create($params);

        return $refund->id;
    }
}
