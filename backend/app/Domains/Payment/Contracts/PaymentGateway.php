<?php

namespace App\Domains\Payment\Contracts;

interface PaymentGateway
{
    public function createPaymentIntent(int $amount, string $currency, string $idempotencyKey): string;

    public function refund(string $paymentIntentId, ?string $idempotencyKey = null): string;

    public function cancelPaymentIntent(string $intentId): void;
}
