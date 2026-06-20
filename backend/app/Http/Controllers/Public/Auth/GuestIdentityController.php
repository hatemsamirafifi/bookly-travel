<?php

namespace App\Http\Controllers\Public\Auth;

use App\Domains\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GuestIdentityRequest;
use Illuminate\Http\JsonResponse;

class GuestIdentityController extends Controller
{
    /**
     * Find or create a guest identity, or detect an existing account.
     */
    public function __invoke(GuestIdentityRequest $request, AuthService $service): JsonResponse
    {
        $data = $request->validated();
        $normalizedEmail = strtolower(trim($data['email']));

        if ($service->checkExistingAccount($normalizedEmail)) {
            return response()->json([
                'has_account' => true,
            ], 200);
        }

        $guest = $service->findOrCreateGuestIdentity(
            $normalizedEmail,
            $data['name'],
            $data['phone'] ?? null
        );

        $status = $guest->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'data' => $guest,
        ], $status);
    }
}
