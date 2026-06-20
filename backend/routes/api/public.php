<?php

use App\Domains\Booking\Controllers\Public\BookingController;
use App\Domains\Booking\Controllers\Public\TravelerBookingController;
use App\Domains\Booking\Controllers\Public\VoucherController;
use App\Domains\Partner\Controllers\Public\PartnerRegistrationController;
use App\Domains\Payment\Controllers\Public\StripeWebhookController;
use App\Domains\Reviews\Controllers\Public\ReviewController;
use App\Domains\Search\Controllers\Public\CategoryController;
use App\Domains\Search\Controllers\Public\DestinationController;
use App\Domains\Search\Controllers\Public\HomepageController;
use App\Domains\Search\Controllers\Public\SearchController;
use App\Domains\Search\Controllers\Public\SitemapController;
use App\Domains\Search\Controllers\Public\TourDetailController;
use App\Domains\Traveler\Controllers\Public\ProfileController;
use App\Domains\Traveler\Controllers\Public\WishlistController;
use App\Http\Controllers\Public\Account\ChangePasswordController;
use App\Http\Controllers\Public\Account\SessionController;
use App\Http\Controllers\Public\Auth\EmailVerificationController;
use App\Http\Controllers\Public\Auth\ForgotPasswordController;
use App\Http\Controllers\Public\Auth\GuestConversionController;
use App\Http\Controllers\Public\Auth\GuestIdentityController;
use App\Http\Controllers\Public\Auth\LoginController;
use App\Http\Controllers\Public\Auth\LogoutController;
use App\Http\Controllers\Public\Auth\MeController;
use App\Http\Controllers\Public\Auth\RegisterController;
use App\Http\Controllers\Public\Auth\ResendVerificationController;
use App\Http\Controllers\Public\Auth\ResetPasswordController;
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
    Route::post('register', RegisterController::class);
    Route::post('partners/register', PartnerRegistrationController::class);
    Route::post('login', LoginController::class);

    Route::post('guest/identity', GuestIdentityController::class);
    Route::post('guest/convert', GuestConversionController::class);
    Route::post('forgot-password', ForgotPasswordController::class);
    Route::post('reset-password', ResetPasswordController::class);

    // Named route required for verification link generation
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('auth.verify');

    Route::post('resend-verification', ResendVerificationController::class)
        ->middleware('auth:sanctum');

    Route::put('change-password', ChangePasswordController::class)
        ->middleware('auth:sanctum');

    Route::post('logout', LogoutController::class)
        ->middleware('auth:sanctum');
});

// Restore the authenticated user from a persisted bearer token (SPA session
// restore). This is an authenticated READ (auth:sanctum already rejects
// invalid/missing tokens), not a login attempt, so it must NOT sit under the
// 10/min `throttle:auth` brute-force limiter — that limiter is shared by IP
// and a normal SPA restores the session on every page load, so any concurrent
// usage (or the E2E suite) blows past 10/min and gets 429'd, which the client
// treats as an expired session. Use the 120/min `throttle:traveler` limiter
// (per authenticated user) that governs other authenticated traveler reads.
Route::get('auth/me', MeController::class)
    ->middleware(['auth:sanctum', 'throttle:traveler']);

Route::middleware(['auth:sanctum', 'throttle:traveler'])->prefix('account')->group(function () {
    Route::get('sessions', [SessionController::class, 'index']);
    Route::post('change-password', ChangePasswordController::class);
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
    Route::get('tours', [SearchController::class, 'search']);
});

Route::prefix('tours')->middleware('throttle:detail')->group(function () {
    Route::get('{slug}', [TourDetailController::class, 'show']);
});

Route::prefix('categories')->middleware('throttle:listing')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('{slug}/tours', [CategoryController::class, 'tours']);
});

Route::prefix('destinations')->middleware('throttle:listing')->group(function () {
    Route::get('/', [DestinationController::class, 'index']);
    Route::get('{slug}/tours', [DestinationController::class, 'tours']);
});

Route::prefix('homepage')->middleware('throttle:homepage')->group(function () {
    Route::get('/', [HomepageController::class, 'index']);
});

Route::prefix('sitemap')->middleware('throttle:sitemap')->group(function () {
    Route::get('.xml', [SitemapController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Booking API Routes (Feature: 007-tour-booking)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('bookings')->middleware('throttle:booking.create')->group(function () {
        Route::post('/', [BookingController::class, 'store']);
    });
});

/*
|--------------------------------------------------------------------------
| Stripe Webhook Route (Feature: 008-payment-processing)
|--------------------------------------------------------------------------
| Unauthenticated — validated via Stripe webhook signature.
*/
Route::post('webhooks/stripe', StripeWebhookController::class);

/*
|--------------------------------------------------------------------------
| Reviews API Routes (Feature: 009-reviews-ratings)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('reviews')->middleware('throttle:reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('{review}', [ReviewController::class, 'update']);
    });
});

Route::get('tours/{slug}/reviews', [ReviewController::class, 'index'])
    ->middleware('throttle:detail');

/*
|--------------------------------------------------------------------------
| Traveler API Routes (Feature: 011-tour-management)
|--------------------------------------------------------------------------
| Authenticated traveler endpoints for profile, wishlist, and reviews.
| Rate limit: 120 req/min per user.
*/
Route::middleware(['auth:sanctum', 'throttle:traveler'])->prefix('traveler')->group(function () {
    Route::prefix('bookings')->middleware('throttle:booking.get')->group(function () {
        Route::get('/', [TravelerBookingController::class, 'index']);
        Route::get('summary', [TravelerBookingController::class, 'summary']);
        Route::get('{reference}', [TravelerBookingController::class, 'show']);
        Route::get('{reference}/voucher', [VoucherController::class, 'download']);
        Route::post('{reference}/cancel', [TravelerBookingController::class, 'cancel'])
            ->middleware('throttle:booking.create');
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('change-password', [ProfileController::class, 'changePassword']);
    });

    Route::prefix('sessions')->group(function () {
        Route::get('/', [SessionController::class, 'index']);
        Route::delete('{tokenId}', [SessionController::class, 'destroy']);
    });

    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::delete('{tour_id}', [WishlistController::class, 'destroy']);
        Route::get('status', [WishlistController::class, 'status']);
    });

    Route::get('reviews', [App\Domains\Traveler\Controllers\Public\ReviewController::class, 'index']);
});
