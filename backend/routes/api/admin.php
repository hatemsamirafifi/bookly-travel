<?php

use App\Domains\Booking\Controllers\Admin\AuditController;
use App\Domains\Payment\Controllers\Admin\FinancialLedgerController;
use App\Domains\Reviews\Controllers\Admin\AdminReviewController;
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
        Route::get('/', [AuditController::class, 'index']);
        Route::get('{reference}', [AuditController::class, 'show']);
    });

    Route::get('financial-ledger', [FinancialLedgerController::class, 'index'])
        ->middleware('throttle:booking.get');

    Route::prefix('reviews')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [AdminReviewController::class, 'index']);
        Route::post('{review}/hide', [AdminReviewController::class, 'hide']);
        Route::post('{review}/reinstate', [AdminReviewController::class, 'reinstate']);
    });
});
