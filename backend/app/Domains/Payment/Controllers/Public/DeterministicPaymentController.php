<?php

namespace App\Domains\Payment\Controllers\Public;

use App\Domains\Payment\Actions\ConfirmDeterministicPaymentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeterministicPaymentController
{
    public function __invoke(Request $request, string $reference, ConfirmDeterministicPaymentAction $action): JsonResponse
    {
        abort_unless(
            app()->environment(['local', 'testing'])
                && config('services.payment.gateway') === 'deterministic',
            404,
        );

        $validated = $request->validate([
            'client_secret' => ['required', 'string', 'max:255'],
        ]);

        $booking = $action->execute(
            $reference,
            (int) $request->user()->getAuthIdentifier(),
            $validated['client_secret'],
        );

        return response()->json([
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
            ],
        ]);
    }
}
