<?php

namespace App\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Domains\Auth\Actions\AuthenticateTravelerAction;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request, AuthenticateTravelerAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        if (!$result['success']) {
            if ($result['locked'] ?? false) {
                return response()->json([
                    'code' => 'account_locked',
                    'message' => $result['message']
                ], 423);
            }

            return response()->json([
                'code' => 'invalid_credentials',
                'message' => $result['message']
            ], 422);
        }

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token']
            ]
        ], 200);
    }
}
