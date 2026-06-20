<?php

namespace App\Domains\Partner\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnerRoleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tokenCan('partner')) {
            abort(404);
        }

        // Load partner relationship for downstream ownership checks
        if (! $user->relationLoaded('partner')) {
            $user->load('partner');
        }

        if (! $user->partner || ! $user->partner->is_active) {
            abort(404);
        }

        // Inject partner_id into request attributes for convenience
        $request->attributes->set('partner_id', $user->partner->id);

        return $next($request);
    }
}
