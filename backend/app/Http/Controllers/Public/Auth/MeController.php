<?php

namespace App\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Return the currently authenticated user.
 *
 * Allows the SPA to restore its session across navigations using the
 * persisted bearer token (Sanctum) instead of relying on httpOnly cookies
 * that are unreachable behind the nginx /api proxy.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($request->user()),
            ],
        ], 200);
    }
}
