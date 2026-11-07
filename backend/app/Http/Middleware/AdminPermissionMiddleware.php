<?php

namespace App\Http\Middleware;

use App\Domains\Admin\Services\AdminAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPermissionMiddleware
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $this->authz->can($user, $permission)) {
            abort(403, 'Forbidden. Insufficient permissions.');
        }

        return $next($request);
    }
}
