<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Actions\ResubmitPartnerApplicationAction;
use App\Domains\Partner\Requests\PartnerResubmitRequest;
use App\Domains\Partner\Services\PartnerOnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingStatusController extends Controller
{
    public function __construct(
        protected PartnerOnboardingService $onboardingService,
        protected ResubmitPartnerApplicationAction $resubmitAction
    ) {}

    public function show(Request $request): JsonResponse
    {
        $partner = $request->user()->partner()->first();

        if (! $partner) {
            return response()->json([
                'message' => 'Partner record not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->onboardingService->getStatus($partner),
        ]);
    }

    public function resubmit(PartnerResubmitRequest $request): JsonResponse
    {
        $user = $request->user();
        $partner = $user->partner()->first();

        if (! $partner) {
            return response()->json([
                'message' => 'Partner record not found.',
            ], 404);
        }

        $partner = $this->resubmitAction->execute($user, $partner, $request->validated());

        $data = $this->onboardingService->getStatus($partner);
        $data['message'] = 'Your application has been resubmitted for review.';

        return response()->json([
            'data' => $data,
        ]);
    }
}
