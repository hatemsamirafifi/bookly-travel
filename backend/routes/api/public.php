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

/*
|--------------------------------------------------------------------------
| Public Search & Discovery Routes
|--------------------------------------------------------------------------
| Routes for the public search, tour detail, category, and discovery APIs.
| Rate limits are per-endpoint: 60/min search, 120/min detail/listings.
|
| Feature: 006-public-search-discovery
*/
Route::prefix('search')->middleware('throttle:search')->group(function () {
    Route::get('tours', [\App\Domains\Search\Controllers\Public\SearchController::class, 'search']);
});

Route::prefix('tours')->middleware('throttle:detail')->group(function () {
    Route::get('{slug}', [\App\Domains\Search\Controllers\Public\TourDetailController::class, 'show']);
});

Route::prefix('categories')->middleware('throttle:listing')->group(function () {
    Route::get('/', [\App\Domains\Search\Controllers\Public\CategoryController::class, 'index']);
    Route::get('{slug}/tours', [\App\Domains\Search\Controllers\Public\CategoryController::class, 'tours']);
});

Route::prefix('destinations')->middleware('throttle:listing')->group(function () {
    Route::get('/', [\App\Domains\Search\Controllers\Public\DestinationController::class, 'index']);
    Route::get('{slug}/tours', [\App\Domains\Search\Controllers\Public\DestinationController::class, 'tours']);
});

Route::prefix('homepage')->middleware('throttle:homepage')->group(function () {
    Route::get('/', [\App\Domains\Search\Controllers\Public\HomepageController::class, 'index']);
});

Route::prefix('sitemap')->middleware('throttle:sitemap')->group(function () {
    Route::get('.xml', [\App\Domains\Search\Controllers\Public\SitemapController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Booking API Routes (Feature: 007-tour-booking)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('bookings')->middleware('throttle:booking.create')->group(function () {
        Route::post('/', [\App\Domains\Booking\Controllers\Public\BookingController::class, 'store']);
    });

    Route::prefix('my-bookings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Booking\Controllers\Public\TravelerBookingController::class, 'index']);
        Route::get('summary', [\App\Domains\Booking\Controllers\Public\TravelerBookingController::class, 'summary']);
        Route::get('{reference}', [\App\Domains\Booking\Controllers\Public\TravelerBookingController::class, 'show']);
        Route::post('{reference}/cancel', [\App\Domains\Booking\Controllers\Public\TravelerBookingController::class, 'cancel'])
            ->middleware('throttle:booking.create');
    });
});

/*
|--------------------------------------------------------------------------
| Stripe Webhook Route (Feature: 008-payment-processing)
|--------------------------------------------------------------------------
| Unauthenticated — validated via Stripe webhook signature.
*/
Route::post('webhooks/stripe', \App\Domains\Payment\Controllers\Public\StripeWebhookController::class);

/*
|--------------------------------------------------------------------------
| Reviews API Routes (Feature: 009-reviews-ratings)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('reviews')->middleware('throttle:reviews')->group(function () {
        Route::post('/', [\App\Domains\Reviews\Controllers\Public\ReviewController::class, 'store']);
        Route::put('{review}', [\App\Domains\Reviews\Controllers\Public\ReviewController::class, 'update']);
    });
});

Route::get('tours/{slug}/reviews', [\App\Domains\Reviews\Controllers\Public\ReviewController::class, 'index'])
    ->middleware('throttle:detail');

/*
|--------------------------------------------------------------------------
| Traveler API Routes (Feature: 011-tour-management)
|--------------------------------------------------------------------------
| Authenticated traveler endpoints for profile, wishlist, and reviews.
| Rate limit: 120 req/min per user.
*/
Route::middleware(['auth:sanctum'])->prefix('traveler')->middleware('throttle:traveler')->group(function () {
    Route::prefix('bookings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [\App\Domains\Booking\Controllers\Public\TravelerBookingController::class, 'index']);
        Route::get('{reference}', [\App\Domains\Booking\Controllers\Public\TravelerBookingController::class, 'show']);
        Route::post('{reference}/cancel', [\App\Domains\Booking\Controllers\Public\TravelerBookingController::class, 'cancel'])
            ->middleware('throttle:booking.create');
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [\App\Domains\Traveler\Controllers\Public\ProfileController::class, 'show']);
        Route::put('/', [\App\Domains\Traveler\Controllers\Public\ProfileController::class, 'update']);
        Route::post('change-password', [\App\Domains\Traveler\Controllers\Public\ProfileController::class, 'changePassword']);
    });

    Route::prefix('wishlist')->group(function () {
        Route::get('/', [\App\Domains\Traveler\Controllers\Public\WishlistController::class, 'index']);
        Route::post('/', [\App\Domains\Traveler\Controllers\Public\WishlistController::class, 'store']);
        Route::delete('{tour_id}', [\App\Domains\Traveler\Controllers\Public\WishlistController::class, 'destroy']);
        Route::get('status', [\App\Domains\Traveler\Controllers\Public\WishlistController::class, 'status']);
    });

    Route::get('reviews', [\App\Domains\Traveler\Controllers\Public\ReviewController::class, 'index']);
});
