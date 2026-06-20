<?php

use App\Domains\Booking\Controllers\Partner\PartnerBookingController;
use App\Domains\Partner\Controllers\AnalyticsController;
use App\Domains\Partner\Controllers\AvailabilityController;
use App\Domains\Partner\Controllers\BookingController;
use App\Domains\Partner\Controllers\NotificationController;
use App\Domains\Partner\Controllers\PricingController;
use App\Domains\Partner\Controllers\ProfileController;
use App\Domains\Partner\Controllers\ReviewController;
use App\Domains\Partner\Controllers\TourController;
use App\Domains\Partner\Controllers\UploadController;
use App\Domains\Payment\Controllers\Partner\FinancialSummaryController;
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
        Route::post('signed-url', [UploadController::class, 'signedUrl'])
            ->middleware('throttle:booking.create');
    });

    // Tours
    Route::prefix('tours')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [TourController::class, 'index']);
        Route::post('/', [TourController::class, 'store']);
        Route::get('{id}', [TourController::class, 'show']);
        Route::put('{id}', [TourController::class, 'update']);
        Route::delete('{id}', [TourController::class, 'destroy']);
        Route::post('{id}/drafts/save', [TourController::class, 'saveDraft']);
        Route::get('{id}/drafts/latest', [TourController::class, 'latestDraft']);
        Route::post('{id}/submit', [TourController::class, 'submitForReview']);
        Route::post('{id}/archive', [TourController::class, 'archive']);

        // Pricing and Availability CRUD
        Route::prefix('{tourId}')->group(function () {
            // Pricing Resource
            Route::get('pricing', [PricingController::class, 'index']);
            Route::post('pricing', [PricingController::class, 'store']);
            Route::put('pricing/{tierId}', [PricingController::class, 'update']);
            Route::delete('pricing/{tierId}', [PricingController::class, 'destroy']);

            // Availability Resource
            Route::get('availability', [AvailabilityController::class, 'index']);
            Route::post('availability/rules', [AvailabilityController::class, 'storeRule']);
            Route::put('availability/rules/{ruleId}', [AvailabilityController::class, 'updateRule']);
            Route::delete('availability/rules/{ruleId}', [AvailabilityController::class, 'destroyRule']);
            Route::post('availability/exceptions', [AvailabilityController::class, 'storeException']);
            Route::put('availability/exceptions/{exceptionId}', [AvailabilityController::class, 'updateException']);
            Route::delete('availability/exceptions/{exceptionId}', [AvailabilityController::class, 'destroyException']);
        });
    });

    // Bookings (existing routes preserved)
    Route::prefix('bookings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [PartnerBookingController::class, 'index']);
        Route::get('{reference}', [PartnerBookingController::class, 'show']);
        Route::patch('{reference}/status', [PartnerBookingController::class, 'updateStatus']);
        Route::post('{reference}/cancellation-request', [BookingController::class, 'requestCancellation']);
    });

    // Reviews
    Route::prefix('reviews')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [ReviewController::class, 'index']);
        Route::post('{id}/responses', [ReviewController::class, 'storeResponse']);
        Route::put('{id}/responses', [ReviewController::class, 'updateResponse']);
    });

    // Analytics
    Route::prefix('analytics')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index']);
    });

    // Profile & Settings
    Route::prefix('profile')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
    });

    Route::prefix('settings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [ProfileController::class, 'settings']);
        Route::put('/', [ProfileController::class, 'updateSettings']);
    });

    // Notifications
    Route::prefix('notifications')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Financial summary (existing route preserved)
    Route::get('financial-summary', [FinancialSummaryController::class, 'index'])
        ->middleware('throttle:booking.get');
});
