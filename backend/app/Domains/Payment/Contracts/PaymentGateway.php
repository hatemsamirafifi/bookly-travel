<?php

namespace App\Domains\Payment\Contracts;

interface PaymentGateway
{
    public function createPaymentIntent(
        int $amount,
        string $currency,
        string $idempotencyKey,
        ?string $destinationAccountId = null,
        ?int $applicationFeeAmount = null,
        array $metadata = []
    ): string;

    public function refund(
        string $paymentIntentId,
        ?string $idempotencyKey = null,
        bool $reverseTransfer = false,
        bool $refundApplicationFee = false
    ): string;

    public function cancelPaymentIntent(string $intentId): void;
}
