<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Partner API Routes
|--------------------------------------------------------------------------
| Routes for authenticated partner users.
| Prefix: /api/partner
*/

Route::middleware(['auth:sanctum', 'role:partner'])->group(function () {
    Route::prefix('bookings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Booking\Controllers\Partner\PartnerBookingController::class, 'index']);
        Route::patch('{reference}/status', [\App\Domains\Booking\Controllers\Partner\PartnerBookingController::class, 'updateStatus']);
    });

    Route::get('financial-summary', [\App\Domains\Payment\Controllers\Partner\FinancialSummaryController::class, 'index'])
        ->middleware('throttle:booking.get');

    Route::get('reviews', [\App\Domains\Reviews\Controllers\Partner\PartnerReviewController::class, 'index'])
        ->middleware('throttle:booking.get');
});
