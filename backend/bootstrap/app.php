<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {

            RateLimiter::for('api', function (Request $request) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            });

            RateLimiter::for('auth', function (Request $request) {
                return Limit::perMinute(10)->by($request->ip());
            });

            RateLimiter::for('search', function (Request $request) {
                return Limit::perMinute(60)->by($request->ip());
            });

            RateLimiter::for('detail', function (Request $request) {
                return Limit::perMinute(120)->by($request->ip());
            });

            RateLimiter::for('listing', function (Request $request) {
                return Limit::perMinute(120)->by($request->ip());
            });

            RateLimiter::for('homepage', function (Request $request) {
                return Limit::perMinute(120)->by($request->ip());
            });

            RateLimiter::for('sitemap', function (Request $request) {
                return Limit::perMinute(10)->by($request->ip());
            });

            RateLimiter::for('reviews', function (Request $request) {
                return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
            });

            RateLimiter::for('booking.create', function (Request $request) {
                return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
            });

            RateLimiter::for('booking.get', function (Request $request) {
                return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
            });

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
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
