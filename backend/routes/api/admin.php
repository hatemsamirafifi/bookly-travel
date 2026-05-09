<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
| Routes for authenticated admin users.
| Prefix: /api/admin
*/

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::prefix('audit/bookings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Booking\Controllers\Admin\AuditController::class, 'index']);
        Route::get('{reference}', [\App\Domains\Booking\Controllers\Admin\AuditController::class, 'show']);
    });
});
