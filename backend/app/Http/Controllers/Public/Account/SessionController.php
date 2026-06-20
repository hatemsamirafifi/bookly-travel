<?php

namespace App\Http\Controllers\Public\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * List active sessions for the authenticated traveler.
     * Returns metadata only — no token exposure.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tokens = $user->tokens()
            ->select(['id', 'name', 'created_at', 'last_used_at'])
            ->orderByDesc('last_used_at')
            ->get()
            ->map(function ($token) use ($request) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'device' => $this->parseDeviceName($token->name),
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                    'is_current' => $this->isCurrentToken($request, $token->id),
                ];
            });

        return response()->json([
            'data' => $tokens->toArray(),
        ]);
    }

    /**
     * Revoke a specific session token.
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();

        $token = $user->tokens()->find($tokenId);

        if (! $token) {
            return response()->json([
                'message' => 'Session not found.',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Session revoked successfully.',
        ], 200);
    }

    /**
     * Parse a device name from the token name.
     */
    protected function parseDeviceName(?string $name): array
    {
        if (! $name) {
            return [
                'type' => 'unknown',
                'browser' => 'Unknown',
                'os' => 'Unknown',
            ];
        }

        $parts = explode(' - ', $name);
        $browser = $parts[0] ?? 'Unknown';
        $os = $parts[1] ?? 'Unknown';

        return [
            'type' => $this->detectDeviceType($browser),
            'browser' => $browser,
            'os' => $os,
        ];
    }

    /**
     * Detect device type from browser string.
     */
    protected function detectDeviceType(string $browser): string
    {
        $browser = strtolower($browser);

        if (str_contains($browser, 'mobile') || str_contains($browser, 'android') || str_contains($browser, 'iphone')) {
            return 'mobile';
        }

        if (str_contains($browser, 'tablet') || str_contains($browser, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Check if the given token ID is the current session.
     */
    protected function isCurrentToken(Request $request, int $tokenId): bool
    {
        $currentToken = $request->user()->currentAccessToken();

        return $currentToken && $currentToken->id === $tokenId;
    }
}
