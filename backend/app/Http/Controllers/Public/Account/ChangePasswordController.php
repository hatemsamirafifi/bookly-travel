<?php

namespace App\Http\Controllers\Public\Account;

use App\Domains\Auth\Actions\ChangePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;

class ChangePasswordController extends Controller
{
    /**
     * Change the authenticated traveler's password.
     */
    public function __invoke(ChangePasswordRequest $request, ChangePasswordAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->user(),
            $request->input('current_password'),
            $request->input('password')
        );

        if (! $result['success']) {
            return response()->json([
                'code' => 'invalid_credentials',
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
        ], 200);
    }
}
