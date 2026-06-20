<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Partner API Routes
|--------------------------------------------------------------------------
| Routes for authenticated partner users.
| Prefix: /api/partner
*/

Route::middleware(['auth:sanctum', 'partner'])->group(function () {

    // Uploads (signed URLs for R2 direct upload)
    Route::prefix('uploads')->group(function () {
        Route::post('signed-url', [\App\Domains\Partner\Controllers\UploadController::class, 'signedUrl'])
            ->middleware('throttle:booking.create');
    });

    // Tours
    Route::prefix('tours')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Partner\Controllers\TourController::class, 'index']);
        Route::post('/', [\App\Domains\Partner\Controllers\TourController::class, 'store']);
        Route::get('{id}', [\App\Domains\Partner\Controllers\TourController::class, 'show']);
        Route::put('{id}', [\App\Domains\Partner\Controllers\TourController::class, 'update']);
        Route::delete('{id}', [\App\Domains\Partner\Controllers\TourController::class, 'destroy']);
        Route::post('{id}/drafts/save', [\App\Domains\Partner\Controllers\TourController::class, 'saveDraft']);
        Route::get('{id}/drafts/latest', [\App\Domains\Partner\Controllers\TourController::class, 'latestDraft']);
    });

    // Bookings (existing routes preserved)
    Route::prefix('bookings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Booking\Controllers\Partner\PartnerBookingController::class, 'index']);
        Route::get('{reference}', [\App\Domains\Booking\Controllers\Partner\PartnerBookingController::class, 'show']);
        Route::patch('{reference}/status', [\App\Domains\Booking\Controllers\Partner\PartnerBookingController::class, 'updateStatus']);
        Route::post('{reference}/cancellation-request', [\App\Domains\Partner\Controllers\BookingController::class, 'requestCancellation']);
    });

    // Reviews
    Route::prefix('reviews')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Partner\Controllers\ReviewController::class, 'index']);
        Route::post('{id}/responses', [\App\Domains\Partner\Controllers\ReviewController::class, 'storeResponse']);
        Route::put('{id}/responses', [\App\Domains\Partner\Controllers\ReviewController::class, 'updateResponse']);
    });

    // Analytics
    Route::prefix('analytics')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Partner\Controllers\AnalyticsController::class, 'index']);
    });

    // Profile & Settings
    Route::prefix('profile')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Partner\Controllers\ProfileController::class, 'show']);
        Route::put('/', [\App\Domains\Partner\Controllers\ProfileController::class, 'update']);
    });

    Route::prefix('settings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Partner\Controllers\ProfileController::class, 'settings']);
        Route::put('/', [\App\Domains\Partner\Controllers\ProfileController::class, 'updateSettings']);
    });

    // Notifications
    Route::prefix('notifications')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Partner\Controllers\NotificationController::class, 'index']);
        Route::post('{id}/read', [\App\Domains\Partner\Controllers\NotificationController::class, 'markAsRead']);
        Route::post('read-all', [\App\Domains\Partner\Controllers\NotificationController::class, 'markAllAsRead']);
    });

    // Financial summary (existing route preserved)
    Route::get('financial-summary', [\App\Domains\Payment\Controllers\Partner\FinancialSummaryController::class, 'index'])
        ->middleware('throttle:booking.get');
});
