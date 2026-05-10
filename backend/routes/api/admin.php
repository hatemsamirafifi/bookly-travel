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

    Route::get('financial-ledger', [\App\Domains\Payment\Controllers\Admin\FinancialLedgerController::class, 'index'])
        ->middleware('throttle:booking.get');
});
