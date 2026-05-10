<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\StripeService;

class CreatePaymentIntentAction
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    public function execute(Booking $booking): string
    {
        $clientSecret = $this->stripe->createPaymentIntent(
            amount: $booking->total_price,
            currency: $booking->currency,
            idempotencyKey: $booking->idempotency_key,
        );

        $intentId = $this->extractPaymentIntentId($clientSecret);

        Payment::create([
            'booking_id' => $booking->id,
            'stripe_payment_intent_id' => $intentId,
            'type' => 'charge',
            'amount' => $booking->total_price,
            'currency' => $booking->currency,
            'status' => 'pending',
        ]);

        return $clientSecret;
    }

    private function extractPaymentIntentId(string $clientSecret): string
    {
        // client_secret format: pi_xxx_secret_yyy
        return explode('_secret_', $clientSecret)[0];
    }
}
