<?php

namespace App\Http\Controllers\Public\Auth;

use App\Domains\Auth\Actions\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

class ResetPasswordController extends Controller
{
    /**
     * Handle password reset.
     */
    public function __invoke(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        if (! $result['success']) {
            return response()->json([
                'code' => 'invalid_token',
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
        ], 200);
    }
}
