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

        // Fresh partner check so in-memory user->partner reflects database state
        $partner = $user->partner()->first();
        if (! $partner) {
            abort(404);
        }

        // Inject partner_id into request attributes for convenience
        $request->attributes->set('partner_id', $partner->id);

        $status = $partner->onboarding_status;
        if ($status === 'incomplete') {
            $status = 'pending';
        }

        // Legacy 'complete' status is normalized to 'approved' (older rows used
        // 'complete' before the enum was finalized; kept for backward compat).
        if (($status === 'approved' || $status === 'complete') && $partner->is_active) {
            return $next($request);
        }

        // Gating for non-approved or inactive partners: only allow profile, settings, onboarding, notifications, and read-only tours
        $isAllowedEndpoint = $request->is(
            'api/partner/profile*',
            'api/partner/settings*',
            'api/partner/onboarding*',
            'api/partner/onboarding-status*',
            'api/partner/notifications*',
            'partner/profile*',
            'partner/settings*',
            'partner/onboarding*',
            'partner/onboarding-status*',
            'partner/notifications*'
        ) || (($request->is('api/partner/tours*') || $request->is('partner/tours*')) && $request->isMethod('GET'));

        if ($isAllowedEndpoint) {
            return $next($request);
        }

        return response()->json([
            'message' => match ($status) {
                'pending' => 'Your account is pending approval. Tour creation is only available for approved partners.',
                'rejected' => 'Your application was rejected. Please review feedback and resubmit.',
                'suspended' => 'Your account is suspended. Access to partner operations is restricted.',
                default => 'Your account is not approved for this action.',
            },
            'error_code' => 'ONBOARDING_STATUS_BLOCKED',
            'onboarding_status' => $status,
        ], 403);
    }
}
