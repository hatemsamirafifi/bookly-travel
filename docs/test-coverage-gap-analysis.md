# Test Coverage Gap Analysis — Bookly Travel

**Date:** 2026-05-16
**Branch:** 009-reviews-ratings

---

## Executive Summary

| Metric | Backend | Frontend |
|--------|---------|----------|
| Source files | 107 | 71 |
| Test files | 30 (28 Feature) | 11 (6 unit, 5 e2e) |
| Direct coverage | ~53% (57/107) | ~8.5% (6/71) |
| **Completely untested** | **50 files** | **65 files** |
| Testing frameworks | Pest 2.x | Jest + RTL, Playwright |

**Overall verdict**: Backend has reasonable feature-test coverage but lacks unit tests for individual classes. Frontend has critically low unit test coverage (only 6 of 71 source files tested). E2E tests cover major flows but leave the entire API layer, hooks, validators, and middleware untested in isolation.

---

## 1. BACKEND GAPS

### 1.1 Completely Untested Source Files (50 files)

**By risk level:**

**HIGH RISK — Core business logic with no tests:**

| File | Risk | Why |
|------|------|-----|
| `Payment/Listeners/ConfirmBookingOnPayment.php` | HIGH | Booking confirmation + ledger + email dispatch chain |
| `Payment/Listeners/ExpireBookingOnPaymentFailure.php` | HIGH | Transactional status change + audit trail |
| `Payment/Listeners/NotifyAdminOnPaymentFailure.php` | HIGH | Slack webhook + error logging |
| `Reviews/Listeners/UpdateTourAggregateRating.php` | HIGH | Aggregate rating recalculation on review events |
| `Reviews/Services/ReviewValidationService.php` | HIGH | 5 validation gates for review submission |
| `Booking/Jobs/SendBookingConfirmationEmail.php` | HIGH | Distributed lock, idempotency, failure event |
| `Booking/Jobs/CompleteBookingJob.php` | MEDIUM | Batch status transition for completed tours |
| `Http/Middleware/RoleMiddleware.php` | MEDIUM | AuthN/AuthZ gate |
| `Http/Middleware/SetLocaleFromRequest.php` | MEDIUM | Locale resolution from query/header/fallback |

**MEDIUM RISK — Events/Resources with no tests:**

| File | Risk |
|------|------|
| `Payment/Events/PaymentSucceeded.php` | MEDIUM |
| `Payment/Events/PaymentFailed.php` | MEDIUM |
| `Payment/Events/RefundCompleted.php` | MEDIUM |
| `Reviews/Events/ReviewSubmitted.php` | MEDIUM |
| `Reviews/Events/ReviewFlagged.php` | MEDIUM |
| `Auth/Events/TravelerRegistered.php` | MEDIUM |
| `Auth/Events/TravelerLoggedIn.php` | MEDIUM |
| `Auth/Events/PasswordReset.php` | MEDIUM |
| `Auth/Events/PasswordChanged.php` | MEDIUM |
| `Auth/Events/EmailVerified.php` | MEDIUM |
| `Auth/Events/GuestConvertedToAccount.php` | MEDIUM |
| `Auth/Actions/SendVerificationEmailAction.php` | MEDIUM |
| `Http/Resources/UserResource.php` | LOW |
| `Http/Resources/ReviewResource.php` | LOW |
| `Payment/Controllers/Admin/FinancialLedgerController.php` | MEDIUM |
| `Http/Controllers/Auth/EmailVerificationController.php` | MEDIUM |
| `Domains/Search/Actions/ConfigureSearchIndexAction.php` | LOW |
| `Domains/Search/Actions/GetDestinationToursAction.php` | MEDIUM |
| `Domains/Search/Controllers/Public/DestinationController.php` | MEDIUM |
| `Domains/Search/Actions/IndexTourAction.php` | LOW |
| `Domains/Search/Actions/RemoveFromIndexAction.php` | LOW |
| `Domains/Search/Transformers/TourCardTransformer.php` | LOW |
| `Booking/DTOs/BookingResponseDTO.php` | LOW |
| `Booking/Jobs/AnonymizeExpiredBookingData.php` | LOW |
| `Events/BookingEmailDeliveryFailed.php` | LOW |
| `Listeners/NotifyAdminOnEmailDeliveryFailure.php` | LOW |
| `Mail/VerificationMail.php` | LOW |
| `Mail/AccountLockedOutMail.php` | LOW |
| `Jobs/SendVerificationEmail.php` | LOW |

**LOW RISK — Providers/config:**

All 4 Providers + base Controller — framework scaffolding, minimal risk.

### 1.2 Edge Case Gaps in Existing Backend Tests

**PaymentCaptureTest** — Missing edge cases:
- Zero/negative amount payment (should it even be possible?)
- Payment with `payment_method` = empty string
- Very large participant count (boundary of `group_size_max`)
- Booking with a tour whose price changed between page load and submit

**SubmitReviewTest** — Missing edge cases:
- Rating outside 1-5 range (only 422 tested, what about 0 or 6?)
- Comment exactly at 2000 chars (boundary)
- Comment with Unicode/emoji only
- Booking whose `tour_date` is exactly 30 days ago (boundary of review window)
- Booking whose `tour_date` is yesterday (within window)

**RefundTest** — Missing edge cases:
- Refund for a booking with no payment record
- Refund amount mismatch (partial refund scenarios)
- Stripe refund API failure (network error, not just logic error)
- Refund of already-refunded booking

### 1.3 Missing Error Handling Tests (Backend)

| Scenario | No test exists |
|----------|---------------|
| StripeService throws on `createPaymentIntent` → booking not orphaned | Covered by StripeDowntimeTest |
| StripeService throws on `confirmPayment` → proper error response | **MISSING** |
| StripeService throws on `refund` → proper error response | **MISSING** |
| LedgerService DB write fails → payment still succeeds gracefully | **MISSING** |
| Slack webhook timeout (5s) in NotifyAdminOnPaymentFailure | **MISSING** |
| Cache lock acquisition failure in SendBookingConfirmationEmail | **MISSING** |
| `$booking->fresh()` returns null in SendBookingConfirmationEmail | **MISSING** |

### 1.4 Integration Test Gaps (Backend)

| Gap | Description |
|-----|-------------|
| Payment → Booking → Email pipeline | Full happy-path: PaymentSucceeded → ConfirmBookingOnPayment → ledger record → SendBookingConfirmationEmail → confirmation_email_sent_at set |
| Payment failure → cleanup pipeline | PaymentFailed → ExpireBookingOnPaymentFailure (audit) + NotifyAdminOnPaymentFailure (Slack+log) |
| Review → Aggregate update pipeline | ReviewSubmitted → UpdateTourAggregateRating → Tour.average_rating recalculated |
| Review moderation → Aggregate pipeline | HideReviewAction → aggregate rating excludes hidden review |
| Auth → Guest booking linking | RegisterTravelerAction → LinkGuestBookingsAction → guest bookings transferred |

---

## 2. FRONTEND GAPS

### 2.1 Completely Untested Components (27 files)

**HIGH RISK:**

| Component | Risk | Why |
|-----------|------|-----|
| `middleware.ts` | HIGH | Next.js middleware — i18n routing |
| `lib/api/client.ts` | HIGH | HTTP client with error classes — all API calls depend on it |
| `lib/hooks/useAuth.tsx` | HIGH | Auth context, session restore, login/register/logout flows |
| `lib/hooks/useFilters.ts` | HIGH | URL-based filter state management |
| `lib/validators/auth.ts` | MEDIUM | Zod schemas for login/register/reset/change password |
| `components/auth/LoginForm.tsx` | HIGH | Login form with field errors, server errors, redirect |
| `components/auth/RegisterForm.tsx` | HIGH | Register form with success state, redirect |

**MEDIUM RISK:**

| Components | Count |
|------------|-------|
| `booking/*` (BookingForm, BookingConfirmation, DateConfirmation, ParticipantSelector, PriceBreakdown, PriceChangeModal) | 6 |
| `search/*` (FilterPanel, Pagination, SearchBar, SearchResults, SearchUnavailable, SortDropdown, TourCard) | 7 |
| `my-bookings/*` (BookingCard, BookingList, CancelBookingButton) | 3 |
| `tour/*` (AvailabilityCalendar, BookingCTA, ImageGallery, ReviewList, TourDetail) | 5 |
| `home/*` (CategoryGrid, DestinationShowcase, FeaturedTours, HeroSection) | 4 |

### 2.2 Edge Case Gaps in Existing Frontend Tests

**AggregateRating.test.tsx** — Missing:
- Rating of exactly 5.0 (max boundary)
- Rating of exactly 1.0 (min boundary)
- Large review counts (1,234 → display formatting)
- `average_rating` is `null` (tour has only hidden reviews)
- `review_count` is `0` with non-null `average_rating` (inconsistent state)

**ReviewForm.test.tsx** — Missing:
- Comment at exactly 2000 chars (boundary)
- Rating change: select 3 stars, then change to 5
- Submitting with only whitespace in comment
- Network error during submission
- Double-submit prevention while loading

**ReviewList.test.tsx** — Missing:
- Reviews with mixed ratings in correct order
- Review from "Anonymous Traveler" (deleted user)
- API returning 5xx error
- Pagination / load-more behavior

**StarRating.test.tsx** — Missing:
- Keyboard navigation (Arrow keys)
- Half-star display mode
- Custom `maxRating` prop
- Screen reader announcement of current value

### 2.3 Missing Error Handling Tests (Frontend)

| Component | Missing Error Test |
|-----------|-------------------|
| `LoginForm` | `AuthApiError` with `code: 'account_locked'` → locked message displayed |
| `LoginForm` | `AuthApiError` with field errors → per-field error messages |
| `LoginForm` | Generic `Error` thrown → fallback error message |
| `RegisterForm` | Duplicate email → field error on email |
| `useAuth` | `restoreSession` 401 → redirect to login with `sessionExpired=1` |
| `useAuth` | `restoreSession` network error → silent failure, `isLoading = false` |
| `useAuth` | `logout` API call fails → still clears user+token, redirects |
| `apiClient` | 500 response → generic `ApiError` |
| `apiClient` | Network error (fetch throws) → should propagate properly |
| `apiClient` | Malformed JSON response → JSON parse error |
| `apiClient` | 429 with missing `retry_after` → defaults to 60 |

### 2.4 Integration Test Gaps (Frontend)

| Gap | Description |
|-----|-------------|
| Auth + API token flow | Login → token stored → subsequent API calls include Bearer token |
| Auth + protected routes | AuthGuard redirects to login when no token |
| Auth + session restore | Page refresh → session restored from /api/auth/session |
| Search → filter URL sync | useFilters → URLSearchParams → search results API call |
| Booking flow API integration | BookingForm → POST /api/bookings → confirmation page with BKO- reference |
| i18n + routing | Middleware correctly routes all 3 locales, handles non-supported locale |

---

## 3. TEST SKELETONS

### 3.1 Backend Test Skeletons

#### ConfirmBookingOnPaymentTest

```php
<?php

use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Listeners\ConfirmBookingOnPayment;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Jobs\SendBookingConfirmationEmail;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\LedgerService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->ledger = mock(LedgerService::class);
    $this->listener = new ConfirmBookingOnPayment($this->ledger);
});

test('confirms booking and dispatches confirmation email on successful payment', function () {
    $booking = Booking::factory()->create(['status' => Booking::STATUS_PENDING]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id]);

    $this->ledger->shouldReceive('hasChargeRecord')->andReturn(false);
    $this->ledger->shouldReceive('recordCharge')->andReturn(true);

    $this->listener->handle(new PaymentSucceeded($booking, $payment));

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
    expect($booking->payment_confirmed_at)->not->toBeNull();
    Queue::assertPushed(SendBookingConfirmationEmail::class);
});

test('is idempotent when booking is already confirmed', function () {
    $booking = Booking::factory()->create(['status' => Booking::STATUS_CONFIRMED]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id]);

    $this->ledger->shouldReceive('hasChargeRecord')->never();

    $this->listener->handle(new PaymentSucceeded($booking, $payment));

    Queue::assertNothingPushed();
});

test('skips charge recording when ledger already has entry', function () {
    $booking = Booking::factory()->create(['status' => Booking::STATUS_PENDING]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id]);

    $this->ledger->shouldReceive('hasChargeRecord')->andReturn(true);

    $this->listener->handle(new PaymentSucceeded($booking, $payment));

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
    Queue::assertNothingPushed();
});
```

#### ExpireBookingOnPaymentFailureTest

```php
<?php

use App\Domains\Payment\Events\PaymentFailed;
use App\Domains\Payment\Listeners\ExpireBookingOnPaymentFailure;
use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Booking\Services\AuditService;

beforeEach(function () {
    $this->auditService = mock(AuditService::class);
    $this->listener = new ExpireBookingOnPaymentFailure($this->auditService);
});

test('expires booking and writes audit trail on payment failure', function () {
    $booking = Booking::factory()->create(['status' => Booking::STATUS_PENDING]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id]);

    $this->auditService->shouldReceive('log')->once()->withArgs(
        function ($b, $actorType, $actorId, $action, $before, $after, $meta) use ($booking) {
            return $b->id === $booking->id
                && $action === 'booking.status_changed'
                && $before === Booking::STATUS_PENDING
                && $after === Booking::STATUS_EXPIRED
                && $meta === ['reason' => 'payment_failed'];
        }
    );

    $this->listener->handle(new PaymentFailed($booking, $payment));

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_EXPIRED);
});

test('uses system actor type when user is not authenticated', function () {
    $booking = Booking::factory()->create(['status' => Booking::STATUS_PENDING]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id]);

    $this->auditService->shouldReceive('log')->once()->withArgs(
        function ($b, $actorType, $actorId) {
            return $actorType === 'system' && $actorId === null;
        }
    );

    $this->listener->handle(new PaymentFailed($booking, $payment));
});

test('rolls back on audit service failure', function () {
    $booking = Booking::factory()->create(['status' => Booking::STATUS_PENDING]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id]);

    $this->auditService->shouldReceive('log')->andThrow(new \RuntimeException('DB error'));

    expect(fn () => $this->listener->handle(new PaymentFailed($booking, $payment)))
        ->toThrow(\RuntimeException::class);

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_PENDING);
});
```

#### ReviewValidationServiceTest

```php
<?php

use App\Domains\Reviews\Services\ReviewValidationService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Reviews\Models\Review;
use App\Domains\Payment\Models\Payment;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->service = new ReviewValidationService();
});

test('passes validation for completed booking with payment', function () {
    $traveler = User::factory()->create();
    $booking = Booking::factory()->create([
        'traveler_id' => $traveler->id,
        'status' => Booking::STATUS_COMPLETED,
        'tour_date' => now()->subDays(5),
    ]);
    Payment::factory()->create(['booking_id' => $booking->id, 'status' => 'succeeded']);

    $this->service->validate($booking, $traveler);
    expect(true)->toBeTrue();
});

test('throws 403 when booking is not completed', function () {
    $traveler = User::factory()->create();
    $booking = Booking::factory()->create([
        'traveler_id' => $traveler->id,
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    expect(fn () => $this->service->validate($booking, $traveler))
        ->toThrow(HttpException::class, 'Reviews can only be submitted for completed bookings.');
});

test('throws 403 when booking belongs to different traveler', function () {
    $traveler = User::factory()->create();
    $otherTraveler = User::factory()->create();
    $booking = Booking::factory()->create([
        'traveler_id' => $otherTraveler->id,
        'status' => Booking::STATUS_COMPLETED,
    ]);

    expect(fn () => $this->service->validate($booking, $traveler))
        ->toThrow(HttpException::class, 'You can only review your own bookings.');
});

test('throws 403 when tour_date is more than 30 days ago', function () {
    $traveler = User::factory()->create();
    $booking = Booking::factory()->create([
        'traveler_id' => $traveler->id,
        'status' => Booking::STATUS_COMPLETED,
        'tour_date' => now()->subDays(31),
    ]);

    expect(fn () => $this->service->validate($booking, $traveler))
        ->toThrow(HttpException::class);
});

test('boundary: tour_date exactly 30 days ago still within window', function () {
    $traveler = User::factory()->create();
    $booking = Booking::factory()->create([
        'traveler_id' => $traveler->id,
        'status' => Booking::STATUS_COMPLETED,
        'tour_date' => now()->subDays(30),
    ]);
    Payment::factory()->create(['booking_id' => $booking->id, 'status' => 'succeeded']);

    $this->service->validate($booking, $traveler);
    expect(true)->toBeTrue();
});

test('throws 403 when tour_date is null', function () {
    $traveler = User::factory()->create();
    $booking = Booking::factory()->create([
        'traveler_id' => $traveler->id,
        'status' => Booking::STATUS_COMPLETED,
        'tour_date' => null,
    ]);

    expect(fn () => $this->service->validate($booking, $traveler))
        ->toThrow(HttpException::class);
});

test('throws 403 when review already exists for booking', function () {
    $traveler = User::factory()->create();
    $booking = Booking::factory()->create([
        'traveler_id' => $traveler->id,
        'status' => Booking::STATUS_COMPLETED,
        'tour_date' => now()->subDays(5),
    ]);
    Review::factory()->create(['booking_id' => $booking->id]);

    expect(fn () => $this->service->validate($booking, $traveler))
        ->toThrow(HttpException::class, 'You have already submitted a review for this booking.');
});

test('throws 403 when no successful payment record exists', function () {
    $traveler = User::factory()->create();
    $booking = Booking::factory()->create([
        'traveler_id' => $traveler->id,
        'status' => Booking::STATUS_COMPLETED,
        'tour_date' => now()->subDays(5),
    ]);

    expect(fn () => $this->service->validate($booking, $traveler))
        ->toThrow(HttpException::class, 'A successful payment record is required to submit a review.');
});
```

#### UpdateTourAggregateRatingTest

```php
<?php

use App\Domains\Reviews\Listeners\UpdateTourAggregateRating;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Models\Tour;

beforeEach(function () {
    $this->listener = new UpdateTourAggregateRating();
});

test('calculates correct average from multiple visible reviews', function () {
    $tour = Tour::factory()->create(['average_rating' => null, 'review_count' => 0]);
    Review::factory()->create(['tour_id' => $tour->id, 'rating' => 4, 'status' => 'visible']);
    Review::factory()->create(['tour_id' => $tour->id, 'rating' => 5, 'status' => 'visible']);
    $newReview = Review::factory()->create(['tour_id' => $tour->id, 'rating' => 3, 'status' => 'visible']);

    $this->listener->handle(new ReviewSubmitted($newReview));

    $tour->refresh();
    expect($tour->average_rating)->toBe(4.0);  // (4+5+3)/3
    expect($tour->review_count)->toBe(3);
});

test('sets average_rating to null when no visible reviews remain', function () {
    $tour = Tour::factory()->create(['average_rating' => 4.5, 'review_count' => 2]);
    Review::factory()->create(['tour_id' => $tour->id, 'rating' => 5, 'status' => 'hidden']);
    Review::factory()->create(['tour_id' => $tour->id, 'rating' => 4, 'status' => 'hidden']);
    $flaggedReview = Review::factory()->create(['tour_id' => $tour->id, 'rating' => 3, 'status' => 'hidden']);

    $this->listener->handle(new ReviewSubmitted($flaggedReview));

    $tour->refresh();
    expect($tour->average_rating)->toBeNull();
    expect($tour->review_count)->toBe(0);
});

test('includes flagged reviews in aggregate calculation', function () {
    $tour = Tour::factory()->create(['average_rating' => null, 'review_count' => 0]);
    Review::factory()->create(['tour_id' => $tour->id, 'rating' => 5, 'status' => 'flagged']);
    $newReview = Review::factory()->create(['tour_id' => $tour->id, 'rating' => 4, 'status' => 'visible']);

    $this->listener->handle(new ReviewSubmitted($newReview));

    $tour->refresh();
    expect($tour->average_rating)->toBe(4.5);
    expect($tour->review_count)->toBe(2);
});

test('excludes hidden reviews from aggregate', function () {
    $tour = Tour::factory()->create(['average_rating' => null, 'review_count' => 0]);
    Review::factory()->create(['tour_id' => $tour->id, 'rating' => 1, 'status' => 'hidden']);
    $newReview = Review::factory()->create(['tour_id' => $tour->id, 'rating' => 4, 'status' => 'visible']);

    $this->listener->handle(new ReviewSubmitted($newReview));

    $tour->refresh();
    expect($tour->average_rating)->toBe(4.0);
    expect($tour->review_count)->toBe(1);
});
```

#### SetLocaleFromRequestTest

```php
<?php

use App\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Http\Request;

test('sets locale from query parameter', function () {
    $request = Request::create('/api/search?locale=es', 'GET');
    $middleware = new SetLocaleFromRequest();

    $middleware->handle($request, fn ($req) => null);

    expect(app()->getLocale())->toBe('es');
});

test('falls back to Accept-Language header when no query param', function () {
    $request = Request::create('/api/search', 'GET');
    $request->headers->set('Accept-Language', 'it-IT,it;q=0.9');
    $middleware = new SetLocaleFromRequest();

    $middleware->handle($request, fn ($req) => null);

    expect(app()->getLocale())->toBe('it');
});

test('defaults to en for unsupported locale', function () {
    $request = Request::create('/api/search?locale=fr', 'GET');
    $middleware = new SetLocaleFromRequest();

    $middleware->handle($request, fn ($req) => null);

    expect(app()->getLocale())->toBe('en');
});

test('defaults to en when no locale information provided', function () {
    $request = Request::create('/api/search', 'GET');
    $middleware = new SetLocaleFromRequest();

    $middleware->handle($request, fn ($req) => null);

    expect(app()->getLocale())->toBe('en');
});

test('extracts first 2 chars from multi-part Accept-Language', function () {
    $request = Request::create('/api/search', 'GET');
    $request->headers->set('Accept-Language', 'es-ES,en;q=0.9');
    $middleware = new SetLocaleFromRequest();

    $middleware->handle($request, fn ($req) => null);

    expect(app()->getLocale())->toBe('es');
});
```

#### RoleMiddlewareTest

```php
<?php

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Http\Request;

test('allows user with matching role', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $request = Request::create('/admin/bookings', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new RoleMiddleware();
    $called = false;

    $response = $middleware->handle($request, function () use (&$called) {
        $called = true;
        return response('OK');
    }, 'admin');

    expect($called)->toBeTrue();
});

test('returns 403 when role does not match', function () {
    $user = User::factory()->create(['role' => 'traveler']);
    $request = Request::create('/admin/bookings', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new RoleMiddleware();

    $response = $middleware->handle($request, fn () => null, 'admin');

    expect($response->getStatusCode())->toBe(403);
});

test('returns 403 when user is not authenticated', function () {
    $request = Request::create('/admin/bookings', 'GET');
    $middleware = new RoleMiddleware();

    $response = $middleware->handle($request, fn () => null, 'admin');

    expect($response->getStatusCode())->toBe(403);
});
```

---

### 3.2 Frontend Test Skeletons

#### apiClient.test.ts

```typescript
import { apiClient, ApiError, NotFoundError, ConflictError, ValidationError, GoneError, RateLimitError } from '@/lib/api/client';

beforeEach(() => {
  jest.restoreAllMocks();
});

describe('apiClient', () => {
  it('returns parsed JSON on successful response', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: true,
      json: async () => ({ data: 'test' }),
    } as Response);

    const result = await apiClient<{ data: string }>('/test');
    expect(result).toEqual({ data: 'test' });
  });

  it('appends Accept-Language header when locale provided', async () => {
    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: true,
      json: async () => ({}),
    } as Response);

    await apiClient('/test', { locale: 'es' });

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({ 'Accept-Language': 'es' }),
      })
    );
  });

  it('throws NotFoundError on 404', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: false,
      status: 404,
    } as Response);

    await expect(apiClient('/non-existent')).rejects.toThrow(NotFoundError);
  });

  it('throws ConflictError on 409 with message from body', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: false,
      status: 409,
      json: async () => ({ message: 'Booking conflict' }),
    } as Response);

    await expect(apiClient('/booking')).rejects.toThrow(ConflictError);
    await expect(apiClient('/booking')).rejects.toThrow('Booking conflict');
  });

  it('throws ValidationError with field errors on 422', async () => {
    const fieldErrors = { email: ['Already taken'], password: ['Too weak'] };
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => ({ message: 'Validation failed', errors: fieldErrors }),
    } as Response);

    try {
      await apiClient('/register', { method: 'POST' });
      fail('Should have thrown');
    } catch (e: unknown) {
      expect(e).toBeInstanceOf(ValidationError);
      expect((e as ValidationError).errors).toEqual(fieldErrors);
    }
  });

  it('throws GoneError on 410', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: false,
      status: 410,
      json: async () => ({ message: 'Tour archived' }),
    } as Response);

    await expect(apiClient('/tour')).rejects.toThrow(GoneError);
  });

  it('throws RateLimitError with retry_after on 429', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: false,
      status: 429,
      json: async () => ({ message: 'Too many requests', retry_after: 30 }),
    } as Response);

    try {
      await apiClient('/search');
      fail('Should have thrown');
    } catch (e: unknown) {
      expect(e).toBeInstanceOf(RateLimitError);
      expect((e as RateLimitError).retryAfter).toBe(30);
    }
  });

  it('defaults retryAfter to 60 when missing from 429 body', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: false,
      status: 429,
      json: async () => ({}),
    } as Response);

    try {
      await apiClient('/search');
      fail('Should have thrown');
    } catch (e: unknown) {
      expect((e as RateLimitError).retryAfter).toBe(60);
    }
  });

  it('handles non-JSON error body gracefully on 422', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => { throw new Error('Invalid JSON'); },
    } as Response);

    await expect(apiClient('/test')).rejects.toThrow(ValidationError);
  });

  it('falls back to generic ApiError for unhandled status codes', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: false,
      status: 500,
    } as Response);

    await expect(apiClient('/test')).rejects.toThrow(ApiError);
    await expect(apiClient('/test')).rejects.toThrow('API request failed with status 500');
  });

  it('propagates network errors', async () => {
    jest.spyOn(global, 'fetch').mockRejectedValueOnce(new TypeError('Failed to fetch'));

    await expect(apiClient('/test')).rejects.toThrow('Failed to fetch');
  });
});
```

#### validators/auth.test.ts

```typescript
import { loginSchema, registerSchema, passwordSchema, resetPasswordSchema, changePasswordSchema, forgotPasswordSchema } from '@/lib/validators/auth';

describe('passwordSchema', () => {
  it('rejects password shorter than 8 chars', () => {
    const result = passwordSchema.safeParse('Ab1');
    expect(result.success).toBe(false);
  });

  it('rejects password without uppercase', () => {
    const result = passwordSchema.safeParse('abcdefg1');
    expect(result.success).toBe(false);
  });

  it('rejects password without lowercase', () => {
    const result = passwordSchema.safeParse('ABCDEFG1');
    expect(result.success).toBe(false);
  });

  it('rejects password without digit', () => {
    const result = passwordSchema.safeParse('Abcdefgh');
    expect(result.success).toBe(false);
  });

  it('accepts valid password', () => {
    const result = passwordSchema.safeParse('MyPassword1');
    expect(result.success).toBe(true);
  });
});

describe('loginSchema', () => {
  it('accepts valid email and password', () => {
    const result = loginSchema.safeParse({ email: 'test@example.com', password: 'secret' });
    expect(result.success).toBe(true);
  });

  it('rejects invalid email', () => {
    const result = loginSchema.safeParse({ email: 'not-email', password: 'secret' });
    expect(result.success).toBe(false);
  });

  it('rejects empty password', () => {
    const result = loginSchema.safeParse({ email: 'test@example.com', password: '' });
    expect(result.success).toBe(false);
  });
});

describe('registerSchema', () => {
  it('accepts valid registration data with locale', () => {
    const result = registerSchema.safeParse({
      name: 'John',
      email: 'john@example.com',
      password: 'MyPassword1',
      locale: 'es',
    });
    expect(result.success).toBe(true);
  });

  it('accepts registration without locale (optional)', () => {
    const result = registerSchema.safeParse({
      name: 'John',
      email: 'john@example.com',
      password: 'MyPassword1',
    });
    expect(result.success).toBe(true);
  });

  it('rejects invalid locale', () => {
    const result = registerSchema.safeParse({
      name: 'John',
      email: 'john@example.com',
      password: 'MyPassword1',
      locale: 'fr',
    });
    expect(result.success).toBe(false);
  });

  it('rejects empty name', () => {
    const result = registerSchema.safeParse({
      name: '',
      email: 'john@example.com',
      password: 'MyPassword1',
    });
    expect(result.success).toBe(false);
  });

  it('rejects name longer than 255 chars', () => {
    const result = registerSchema.safeParse({
      name: 'a'.repeat(256),
      email: 'john@example.com',
      password: 'MyPassword1',
    });
    expect(result.success).toBe(false);
  });
});

describe('resetPasswordSchema', () => {
  it('rejects mismatched password confirmation', () => {
    const result = resetPasswordSchema.safeParse({
      email: 'test@example.com',
      token: 'abc123',
      password: 'MyPassword1',
      password_confirmation: 'DifferentPassword1',
    });
    expect(result.success).toBe(false);
  });

  it('accepts valid reset data', () => {
    const result = resetPasswordSchema.safeParse({
      email: 'test@example.com',
      token: 'abc123',
      password: 'MyPassword1',
      password_confirmation: 'MyPassword1',
    });
    expect(result.success).toBe(true);
  });

  it('rejects empty token', () => {
    const result = resetPasswordSchema.safeParse({
      email: 'test@example.com',
      token: '',
      password: 'MyPassword1',
      password_confirmation: 'MyPassword1',
    });
    expect(result.success).toBe(false);
  });
});
```

#### useAuth.test.tsx

```typescript
import { renderHook, act, waitFor } from '@testing-library/react';
import { AuthProvider, useAuth } from '@/lib/hooks/useAuth';
import { authApi } from '@/lib/api/auth';

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));
jest.mock('next-intl', () => ({
  useLocale: () => 'en',
}));

const wrapper = ({ children }: { children: React.ReactNode }) => (
  <AuthProvider>{children}</AuthProvider>
);

describe('useAuth', () => {
  beforeEach(() => {
    jest.spyOn(global, 'fetch').mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({}),
    } as Response);
  });

  it('throws when used outside AuthProvider', () => {
    expect(() => renderHook(() => useAuth())).toThrow('useAuth must be used within an AuthProvider');
  });

  it('starts with isLoading=true and null user', () => {
    const { result } = renderHook(() => useAuth(), { wrapper });
    expect(result.current.user).toBeNull();
    expect(result.current.isLoading).toBe(true);
  });

  it('sets isLoading to false after session check completes', async () => {
    const { result } = renderHook(() => useAuth(), { wrapper });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });
  });

  it('restores session on success', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: true,
      json: async () => ({ user: { id: 1, name: 'Test' }, token: 'abc123' }),
    } as Response);

    const { result } = renderHook(() => useAuth(), { wrapper });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
      expect(result.current.user).toEqual({ id: 1, name: 'Test' });
      expect(result.current.token).toBe('abc123');
    });
  });

  it('handles session restore network error gracefully', async () => {
    jest.spyOn(global, 'fetch').mockRejectedValueOnce(new Error('Network error'));

    const { result } = renderHook(() => useAuth(), { wrapper });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
      expect(result.current.user).toBeNull();
    });
  });

  it('logout clears user and token even when API call fails', async () => {
    jest.spyOn(global, 'fetch').mockResolvedValueOnce({
      ok: true,
      json: async () => ({ user: { id: 1, name: 'Test' }, token: 'abc123' }),
    } as Response);

    const { result } = renderHook(() => useAuth(), { wrapper });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    jest.spyOn(authApi, 'logout').mockRejectedValueOnce(new Error('Network error'));

    await act(async () => {
      await result.current.logout();
    });

    expect(result.current.user).toBeNull();
    expect(result.current.token).toBeNull();
  });
});
```

#### useFilters.test.ts

```typescript
import { renderHook, act } from '@testing-library/react';
import { useFilters } from '@/lib/hooks/useFilters';

const mockPush = jest.fn();

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush }),
  useSearchParams: () => new URLSearchParams('q=beach&category=adventure&page=2'),
  useParams: () => ({ locale: 'en' }),
}));

describe('useFilters', () => {
  beforeEach(() => {
    mockPush.mockClear();
  });

  it('parses current filters from URL search params', () => {
    const { result } = renderHook(() => useFilters());

    expect(result.current.filters).toEqual({
      q: 'beach',
      category: 'adventure',
      page: '2',
    });
  });

  it('counts active filters correctly', () => {
    const { result } = renderHook(() => useFilters());
    expect(result.current.activeFilterCount).toBe(2);
  });

  it('setFilter adds a new filter and resets page', () => {
    const { result } = renderHook(() => useFilters());

    act(() => {
      result.current.setFilter('location', 'rome');
    });

    expect(mockPush).toHaveBeenCalledWith(
      '/en/search?q=beach&category=adventure&page=2&location=rome',
      { scroll: false }
    );
  });

  it('setFilter removes a filter when value is null', () => {
    const { result } = renderHook(() => useFilters());

    act(() => {
      result.current.setFilter('category', null);
    });

    expect(mockPush).toHaveBeenCalledWith(
      '/en/search?q=beach&page=2',
      { scroll: false }
    );
  });

  it('setMultipleFilters applies batch updates and resets page', () => {
    const { result } = renderHook(() => useFilters());

    act(() => {
      result.current.setMultipleFilters({ price_min: '50', price_max: '200' });
    });

    expect(mockPush).toHaveBeenCalledWith(
      '/en/search?q=beach&category=adventure&page=2&price_min=50&price_max=200',
      { scroll: false }
    );
  });

  it('clearAll navigates to bare search path', () => {
    const { result } = renderHook(() => useFilters());

    act(() => {
      result.current.clearAll();
    });

    expect(mockPush).toHaveBeenCalledWith('/en/search', { scroll: false });
  });
});
```

#### LoginForm.test.tsx

```typescript
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LoginForm } from '@/components/auth/LoginForm';
import { useAuth } from '@/lib/hooks/useAuth';
import { AuthApiError } from '@/lib/api/auth';

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => {
    const map: Record<string, string> = {
      'signin.title': 'Sign In',
      'signin.emailLabel': 'Email',
      'signin.passwordLabel': 'Password',
      'signin.submitButton': 'Sign In',
      'signin.forgotPasswordLink': 'Forgot password?',
      'errors.invalidCredentials': 'Invalid credentials',
      'errors.accountLocked': 'Account locked',
      'errors.sessionExpired': 'Session expired',
    };
    return map[key] || key;
  },
  useLocale: () => 'en',
}));
jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));
jest.mock('next/link', () => ({ children, href }: { children: React.ReactNode; href: string }) => (
  <a href={href}>{children}</a>
));
jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: jest.fn(),
}));

describe('LoginForm', () => {
  const mockLogin = jest.fn();

  beforeEach(() => {
    (useAuth as jest.Mock).mockReturnValue({ login: mockLogin });
    mockLogin.mockClear();
  });

  it('renders email, password fields and submit button', () => {
    render(<LoginForm />);
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
  });

  it('shows session expired banner when sessionExpired prop is true', () => {
    render(<LoginForm sessionExpired />);
    expect(screen.getByText('Session expired')).toBeInTheDocument();
  });

  it('calls login with form data on submit', async () => {
    mockLogin.mockResolvedValueOnce(undefined);
    render(<LoginForm />);

    await userEvent.type(screen.getByLabelText('Email'), 'test@example.com');
    await userEvent.type(screen.getByLabelText('Password'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith({
        email: 'test@example.com',
        password: 'MyPassword1',
      });
    });
  });

  it('shows server error on invalid credentials', async () => {
    mockLogin.mockRejectedValueOnce(
      new AuthApiError('Invalid credentials', 401, 'invalid_credentials')
    );
    render(<LoginForm />);

    await userEvent.type(screen.getByLabelText('Email'), 'test@example.com');
    await userEvent.type(screen.getByLabelText('Password'), 'wrong');
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByText('Invalid credentials')).toBeInTheDocument();
    });
  });

  it('shows account locked error', async () => {
    mockLogin.mockRejectedValueOnce(
      new AuthApiError('Account locked', 423, 'account_locked')
    );
    render(<LoginForm />);

    await userEvent.type(screen.getByLabelText('Email'), 'locked@example.com');
    await userEvent.type(screen.getByLabelText('Password'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByText('Account locked')).toBeInTheDocument();
    });
  });

  it('shows field-level errors from API response', async () => {
    mockLogin.mockRejectedValueOnce(
      new AuthApiError('Validation failed', 422, undefined, { email: ['Invalid email format'] })
    );
    render(<LoginForm />);

    await userEvent.type(screen.getByLabelText('Email'), 'bad');
    await userEvent.type(screen.getByLabelText('Password'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByText('Invalid email format')).toBeInTheDocument();
    });
  });

  it('shows generic error for non-AuthApiError exceptions', async () => {
    mockLogin.mockRejectedValueOnce(new Error('Network error'));
    render(<LoginForm />);

    await userEvent.type(screen.getByLabelText('Email'), 'test@example.com');
    await userEvent.type(screen.getByLabelText('Password'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByText('Invalid credentials')).toBeInTheDocument();
    });
  });

  it('disables submit button while submitting', async () => {
    mockLogin.mockImplementationOnce(() => new Promise(() => {}));
    render(<LoginForm />);

    await userEvent.type(screen.getByLabelText('Email'), 'test@example.com');
    await userEvent.type(screen.getByLabelText('Password'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }));

    expect(screen.getByRole('button', { name: /sign in/i })).toBeDisabled();
  });

  it('shows validation errors for empty fields on submit', async () => {
    render(<LoginForm />);
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByText('auth.errors.invalidEmail')).toBeInTheDocument();
      expect(screen.getByText('auth.errors.passwordRequired')).toBeInTheDocument();
    });
  });
});
```

#### RegisterForm.test.tsx

```typescript
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RegisterForm } from '@/components/auth/RegisterForm';
import { useAuth } from '@/lib/hooks/useAuth';
import { authApi, AuthApiError } from '@/lib/api/auth';

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => key,
  useLocale: () => 'en',
}));
jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));
jest.mock('next/link', () => ({ children, href }: { children: React.ReactNode; href: string }) => (
  <a href={href}>{children}</a>
));
jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: jest.fn(),
}));
jest.mock('@/lib/api/auth', () => ({
  authApi: { register: jest.fn() },
  AuthApiError: class extends Error {
    errors: Record<string, string[]> | undefined;
    constructor(message: string, status: number, code?: string, errors?: Record<string, string[]>) {
      super(message);
      this.errors = errors;
    }
  },
}));

describe('RegisterForm', () => {
  const mockSetAuth = jest.fn();

  beforeEach(() => {
    (useAuth as jest.Mock).mockReturnValue({ setAuth: mockSetAuth });
    jest.clearAllMocks();
  });

  it('renders name, email, password fields and submit button', () => {
    render(<RegisterForm />);
    expect(screen.getByLabelText('register.nameLabel')).toBeInTheDocument();
    expect(screen.getByLabelText('register.emailLabel')).toBeInTheDocument();
    expect(screen.getByLabelText('register.passwordLabel')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'register.submitButton' })).toBeInTheDocument();
  });

  it('shows success message after registration', async () => {
    (authApi.register as jest.Mock).mockResolvedValueOnce({
      data: { id: 1, name: 'Test', email: 'test@example.com' },
      token: 'abc123',
    });
    render(<RegisterForm />);

    await userEvent.type(screen.getByLabelText('register.nameLabel'), 'Test');
    await userEvent.type(screen.getByLabelText('register.emailLabel'), 'test@example.com');
    await userEvent.type(screen.getByLabelText('register.passwordLabel'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: 'register.submitButton' }));

    await waitFor(() => {
      expect(screen.getByText('register.successMessage')).toBeInTheDocument();
    });
  });

  it('shows field error for duplicate email', async () => {
    (authApi.register as jest.Mock).mockRejectedValueOnce(
      new AuthApiError('Duplicate', 422, undefined, { email: ['Already taken'] })
    );
    render(<RegisterForm />);

    await userEvent.type(screen.getByLabelText('register.nameLabel'), 'Test');
    await userEvent.type(screen.getByLabelText('register.emailLabel'), 'taken@example.com');
    await userEvent.type(screen.getByLabelText('register.passwordLabel'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: 'register.submitButton' }));

    await waitFor(() => {
      expect(screen.getByText('Already taken')).toBeInTheDocument();
    });
  });

  it('shows server error for generic failure', async () => {
    (authApi.register as jest.Mock).mockRejectedValueOnce(new Error('Server error'));
    render(<RegisterForm />);

    await userEvent.type(screen.getByLabelText('register.nameLabel'), 'Test');
    await userEvent.type(screen.getByLabelText('register.emailLabel'), 'test@example.com');
    await userEvent.type(screen.getByLabelText('register.passwordLabel'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: 'register.submitButton' }));

    await waitFor(() => {
      expect(screen.getByText('auth.errors.invalidCredentials')).toBeInTheDocument();
    });
  });

  it('disables submit button while submitting', async () => {
    (authApi.register as jest.Mock).mockImplementationOnce(() => new Promise(() => {}));
    render(<RegisterForm />);

    await userEvent.type(screen.getByLabelText('register.nameLabel'), 'Test');
    await userEvent.type(screen.getByLabelText('register.emailLabel'), 'test@example.com');
    await userEvent.type(screen.getByLabelText('register.passwordLabel'), 'MyPassword1');
    await userEvent.click(screen.getByRole('button', { name: 'register.submitButton' }));

    expect(screen.getByRole('button', { name: 'register.submitButton' })).toBeDisabled();
  });
});
```

---

## 4. PRIORITIZED RECOMMENDATIONS

### Priority 1 — Critical (implement immediately)

| # | Test | Type | Effort | Impact |
|---|------|------|--------|--------|
| 1 | `apiClient.test.ts` | Unit | Small | All API calls depend on this — bugs here affect every feature |
| 2 | `validators/auth.test.ts` | Unit | Small | Zod schemas — validation errors silently pass bad data |
| 3 | `ReviewValidationServiceTest` | Unit | Small | 5 validation gates, no tests, critical for review integrity |
| 4 | `ConfirmBookingOnPaymentTest` | Unit | Medium | Payment → booking state transition chain |
| 5 | `ExpireBookingOnPaymentFailureTest` | Unit | Small | Transactional state change, must not leave orphans |

### Priority 2 — High (next sprint)

| # | Test | Type |
|---|------|------|
| 6 | `useAuth.test.tsx` | Unit |
| 7 | `useFilters.test.ts` | Unit |
| 8 | `LoginForm.test.tsx` | Unit |
| 9 | `RegisterForm.test.tsx` | Unit |
| 10 | `UpdateTourAggregateRatingTest` | Unit |
| 11 | `RoleMiddlewareTest` | Unit |
| 12 | `SetLocaleFromRequestTest` | Unit |
| 13 | `SendBookingConfirmationEmailTest` | Unit |

### Priority 3 — Medium (this milestone)

| # | Test | Type |
|---|------|------|
| 14 | `NotifyAdminOnPaymentFailureTest` | Unit |
| 15 | `CompleteBookingJobTest` | Unit |
| 16 | Payment → Booking → Email integration test | Integration |
| 17 | Payment failure → cleanup integration test | Integration |
| 18 | Review → Aggregate update integration test | Integration |

### Priority 4 — Lower (next milestone)

| # | Test | Type |
|---|------|------|
| 19 | `BookingForm.test.tsx` | Unit |
| 20 | `PriceBreakdown.test.tsx` | Unit |
| 21 | `FilterPanel.test.tsx` | Unit |
| 22 | `Pagination.test.tsx` | Unit |
| 23 | `TourCard.test.tsx` | Unit |
| 24 | `AvailabilityCalendar.test.tsx` | Unit |
| 25 | Remaining Event class tests | Unit |
| 26 | Resource class tests | Unit |

---

**Summary stats**: 26 recommended new tests, ~50 test cases total, covering the most critical untested logic in both frontend and backend. The highest ROI tests are `apiClient` (protects all frontend API calls), `ReviewValidationService` (5 validation rules), and `ConfirmBookingOnPayment` (payment→booking state machine).
