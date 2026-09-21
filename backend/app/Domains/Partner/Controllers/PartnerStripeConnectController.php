<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Payment\Services\StripeConnectService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerStripeConnectController extends Controller
{
    public function __construct(
        protected StripeConnectService $connectService
    ) {}

    public function status(Request $request): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json([
                'message' => 'Partner record not found.',
            ], 404);
        }

        $status = $this->connectService->syncAccountStatus($partner);

        return response()->json([
            'data' => $status,
        ]);
    }

    public function onboard(Request $request): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json([
                'message' => 'Partner record not found.',
            ], 404);
        }

        $validated = $request->validate([
            'return_url' => ['nullable', 'url'],
            'refresh_url' => ['nullable', 'url'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $defaultBase = config('app.frontend_url', 'http://localhost:3000');
        $returnUrl = $validated['return_url'] ?? "{$defaultBase}/partner/settings/payouts?status=success";
        $refreshUrl = $validated['refresh_url'] ?? "{$defaultBase}/partner/settings/payouts?status=refresh";

        if (empty($partner->stripe_account_id)) {
            $this->connectService->createExpressAccount(
                $partner,
                country: $validated['country'] ?? 'US',
            );
        }

        $url = $this->connectService->createAccountLink($partner, $returnUrl, $refreshUrl);

        return response()->json([
            'url' => $url,
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json([
                'message' => 'Partner record not found.',
            ], 404);
        }

        if (empty($partner->stripe_account_id)) {
            return response()->json([
                'message' => 'Partner does not have a connected Stripe account yet.',
            ], 422);
        }

        try {
            $url = $this->connectService->createLoginLink($partner);

            return response()->json([
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Could not generate dashboard link. Please complete onboarding first.',
            ], 422);
        }
    }
}
