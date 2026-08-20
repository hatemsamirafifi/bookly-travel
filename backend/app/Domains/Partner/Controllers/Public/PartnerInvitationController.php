<?php

namespace App\Domains\Partner\Controllers\Public;

use App\Domains\Partner\Actions\CompletePartnerInvitationAction;
use App\Domains\Partner\Models\PartnerInvitation;
use App\Domains\Partner\Requests\CompleteInvitationRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PartnerInvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = PartnerInvitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return response()->json([
                'valid' => false,
                'message' => 'The invitation token is invalid or has expired.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'email' => $invitation->email,
            'company_name' => $invitation->company_name,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    public function complete(CompleteInvitationRequest $request, string $token, CompletePartnerInvitationAction $action): JsonResponse
    {
        $result = $action->execute($token, $request->validated());

        return response()->json([
            'message' => 'Partner invitation completed successfully.',
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'role' => $result['user']->role,
            ],
            'partner' => [
                'id' => $result['partner']->id,
                'company_name' => $result['profile']->company_name,
                'status' => $result['partner']->onboarding_status,
            ],
            'token' => $result['token'],
        ], 201);
    }
}
