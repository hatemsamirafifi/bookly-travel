<?php

use App\Domains\Partner\Middleware\PartnerRoleMiddleware;
use App\Http\Middleware\RateLimitSearchMiddleware;
use App\Http\Middleware\RefreshTokenExpiry;
use App\Http\Middleware\RoleMiddleware;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        AdminPanelProvider::class,
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/public')
                ->group(base_path('routes/api/public.php'));

            Route::middleware('api')
                ->prefix('api/partner')
                ->group(base_path('routes/api/partner.php'));

            Route::middleware('api')
                ->prefix('api/admin')
                ->group(base_path('routes/api/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'partner' => PartnerRoleMiddleware::class,
            // Spec 006 — emits the contract 429 body + X-RateLimit-* headers +
            // localized message for the public search & discovery surface only.
            'rate.limit' => RateLimitSearchMiddleware::class,
            // Spec 001 FR-012 — sliding-window session expiry (extend on use).
            'refresh.token' => RefreshTokenExpiry::class,
        ]);

        // H-01 — guests hitting api/* must never be redirected to /login:
        // returning null hands the request to Sanctum's AuthenticationException
        // path, which withExceptions() below renders as a JSON 401. Web guests
        // keep the /login redirect.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');

        // Spec 019 T011 — opaque draft snapshots must round-trip exactly as
        // authored (padded/empty strings preserved). Skip BOTH global string
        // normalizers ONLY for the authenticated draft-save endpoint; route
        // auth and owner policy are unchanged, and every canonical
        // create/update/source/publish request keeps trim/null normalization.
        $draftSnapshotRequest = fn (Request $request) => $request->isMethod('POST')
            && $request->is('api/partner/tours/*/drafts/save');
        $middleware->trimStrings(except: [$draftSnapshotRequest]);
        $middleware->convertEmptyStringsToNull(except: [$draftSnapshotRequest]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return;
            }

            // H-01 — unauthenticated API requests consistently receive a JSON
            // 401 (regardless of the Accept header), never Laravel's default
            // redirect-to-login which yields a 301 text/html response.
            if ($exception instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Validation and HttpResponseException retain Laravel's structured contracts.
            if ($exception instanceof ValidationException
                || $exception instanceof HttpResponseException) {
                return;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode() : 500;
            $headers = $exception instanceof HttpExceptionInterface
                ? $exception->getHeaders() : [];
            $message = $status >= 500
                ? 'An unexpected error occurred. Please try again later.'
                : ($status === 404 ? 'Not found.' : $exception->getMessage());

            return response()->json(['message' => $message], $status, $headers);
        });
    })->create();
