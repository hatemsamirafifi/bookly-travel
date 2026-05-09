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
});
