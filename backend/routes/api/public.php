<?php

use App\Http\Controllers\Public\Auth\RegisterController;
use App\Http\Controllers\Public\Auth\LoginController;
use App\Http\Controllers\Public\Auth\LogoutController;
use App\Http\Controllers\Public\Auth\EmailVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| Routes accessible without authentication (auth endpoints, public tours).
| Rate limited: 10 req/min for auth endpoints (per FR-014).
|
| Prefix: /api/public
*/

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    // Auth routes will be registered in Phase 3+ (per user story)
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class);

    // Named route required for verification link generation
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('auth.verify')
        ->middleware(['signed']);

    Route::post('logout', LogoutController::class)
        ->middleware('auth:sanctum');
});
