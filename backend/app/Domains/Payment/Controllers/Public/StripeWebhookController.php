<?php

namespace App\Domains\Payment\Controllers\Public;

use App\Domains\Payment\Actions\ProcessStripeWebhookAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StripeWebhookController
{
    public function __invoke(Request $request, ProcessStripeWebhookAction $action): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            throw new BadRequestHttpException('Missing Stripe-Signature header.');
        }

        try {
            $action->execute($payload, $signature);
        } catch (\UnexpectedValueException $e) {
            throw new BadRequestHttpException('Invalid webhook payload.');
        } catch (SignatureVerificationException $e) {
            throw new BadRequestHttpException('Invalid webhook signature.');
        }

        return response()->json(['received' => true]);
    }
}
