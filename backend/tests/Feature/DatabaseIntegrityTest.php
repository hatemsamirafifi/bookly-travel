<?php

/*
 * Data-integrity gate: 18 explicit PostgreSQL checks mirroring the production
 * audit (§23 of the final audit report). Each test executes exact SQL against
 * the database and asserts zero violating rows.
 *
 * The beforeEach seeds a complete, valid fixture graph (tour + confirmed
 * booking with succeeded payment, charge debit, and payment_confirmed audit;
 * completed booking with review + owning-partner response; published blog
 * post) so the checks run against real rows instead of an empty schema — the
 * opening test proves the graph exists.
 */

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Partner\Models\ReviewResponse;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Models\Review;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'integrity'], ['name' => 'Integrity']);
    $this->partner = makePartner();
    $this->traveler = User::factory()->traveler()->create();

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'integrity-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
    addTranslation($this->tour, 'en', 'Integrity Tour');
    addAvailabilityRule($this->tour);

    $this->booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $this->payment = Payment::create([
        'stripe_payment_intent_id' => testPaymentIntentId(),
        'booking_id' => $this->booking->id,
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    FinancialLedgerEntry::create([
        'booking_id' => $this->booking->id,
        'payment_id' => $this->payment->id,
        'entry_type' => 'debit',
        'amount' => 10000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Payment captured for booking ' . $this->booking->reference,
    ]);

    BookingAuditLog::create([
        'booking_id' => $this->booking->id,
        'actor_type' => 'system',
        'actor_id' => null,
        'action' => 'payment_confirmed',
        'before_state' => Booking::STATUS_PENDING_PAYMENT,
        'after_state' => Booking::STATUS_CONFIRMED,
        'metadata' => ['payment_id' => $this->payment->id],
    ]);

    $this->completedBooking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->subDays(7)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);

    $this->review = Review::create([
        'booking_id' => $this->completedBooking->id,
        'tour_id' => $this->tour->id,
        'traveler_id' => $this->traveler->id,
        'rating' => 5,
        'comment' => 'Wonderful tour.',
        'status' => 'visible',
        'locale' => 'en',
    ]);

    ReviewResponse::create([
        'review_id' => $this->review->id,
        'partner_id' => $this->partner->id,
        'response_text' => 'Thank you!',
    ]);

    $this->blogPost = makeBlogPost(['slug' => 'integrity-post-' . uniqid()]);
});

it('seeds a complete fixture graph so the checks run against real rows', function () {
    expect(Tour::count())->toBeGreaterThanOrEqual(1)
        ->and(Booking::count())->toBeGreaterThanOrEqual(2)
        ->and(Payment::count())->toBeGreaterThanOrEqual(1)
        ->and(FinancialLedgerEntry::count())->toBeGreaterThanOrEqual(1)
        ->and(BookingAuditLog::count())->toBeGreaterThanOrEqual(1)
        ->and(Review::count())->toBeGreaterThanOrEqual(1)
        ->and(ReviewResponse::count())->toBeGreaterThanOrEqual(1)
        ->and(BlogPost::count())->toBeGreaterThanOrEqual(1);
});

it('check 1: reports 0 bookings without a traveler or guest identity', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM bookings WHERE traveler_id IS NULL AND guest_identity_id IS NULL'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 2: reports 0 bookings without a valid tour', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM bookings b LEFT JOIN tours t ON t.id = b.tour_id WHERE t.id IS NULL'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 3: reports 0 duplicate payment intents', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM (SELECT stripe_payment_intent_id FROM payments GROUP BY stripe_payment_intent_id HAVING COUNT(*) > 1) AS d'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 4: reports 0 duplicate charge rows per booking', function () {
    // Refunds legitimately add rows, so the invariant is scoped to charges:
    // exactly one charge per booking.
    $violations = DB::selectOne(
        "SELECT COUNT(*) AS c FROM (SELECT booking_id FROM payments WHERE type = 'charge' GROUP BY booking_id HAVING COUNT(*) > 1) AS d"
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 5: reports 0 duplicate idempotency keys', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM (SELECT idempotency_key FROM bookings WHERE idempotency_key IS NOT NULL GROUP BY idempotency_key HAVING COUNT(*) > 1) AS d'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 6: reports 0 duplicate ledger entries per booking and entry type', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM (SELECT booking_id, entry_type FROM financial_ledger_entries GROUP BY booking_id, entry_type HAVING COUNT(*) > 1) AS d'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 7: reports 0 bookings with an invalid status', function () {
    $violations = DB::selectOne(
        "SELECT COUNT(*) AS c FROM bookings WHERE status NOT IN ('pending_payment', 'confirmed', 'completed', 'cancelled', 'no_show', 'expired', 'cancellation_requested')"
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 8: reports 0 payments with an invalid status', function () {
    $violations = DB::selectOne(
        "SELECT COUNT(*) AS c FROM payments WHERE status NOT IN ('pending', 'succeeded', 'refunded', 'failed', 'disputed')"
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 9: reports 0 reviews on non-completed bookings', function () {
    $violations = DB::selectOne(
        "SELECT COUNT(*) AS c FROM reviews r JOIN bookings b ON b.id = r.booking_id WHERE b.status <> 'completed'"
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 10: reports 0 duplicate reviews per traveler and tour', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM (SELECT traveler_id, tour_id FROM reviews GROUP BY traveler_id, tour_id HAVING COUNT(*) > 1) AS d'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 11: reports 0 review responses from the wrong partner', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM review_responses rr JOIN reviews r ON r.id = rr.review_id JOIN tours t ON t.id = r.tour_id WHERE rr.partner_id <> t.partner_id'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 12: reports 0 tours without an owning partner', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM tours t LEFT JOIN partners p ON p.id = t.partner_id WHERE p.id IS NULL'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 13: reports 0 confirmed bookings missing the payment_confirmed audit log', function () {
    $violations = DB::selectOne(
        "SELECT COUNT(*) AS c FROM bookings b WHERE b.status = 'confirmed' AND NOT EXISTS (SELECT 1 FROM booking_audit_logs l WHERE l.booking_id = b.id AND l.action = 'payment_confirmed')"
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 14: reports 0 paid bookings missing a ledger debit', function () {
    $violations = DB::selectOne(
        "SELECT COUNT(*) AS c FROM bookings b WHERE EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.id AND p.status = 'succeeded') AND NOT EXISTS (SELECT 1 FROM financial_ledger_entries e WHERE e.booking_id = b.id AND e.entry_type = 'debit')"
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 15: reports 0 published tours without availability rules', function () {
    $violations = DB::selectOne(
        "SELECT COUNT(*) AS c FROM tours t WHERE t.status = 'published' AND NOT EXISTS (SELECT 1 FROM availability_rules r WHERE r.tour_id = t.id)"
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 16: reports 0 duplicate blog slugs', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM (SELECT slug FROM blog_posts GROUP BY slug HAVING COUNT(*) > 1) AS d'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 17: reports 0 orphan tour translations', function () {
    $violations = DB::selectOne(
        'SELECT COUNT(*) AS c FROM tour_translations tt LEFT JOIN tours t ON t.id = tt.tour_id WHERE t.id IS NULL'
    )->c;

    expect((int) $violations)->toBe(0);
});

it('check 18: reports 0 published tours without a category', function () {
    $violations = DB::selectOne(
        "SELECT COUNT(*) AS c FROM tours WHERE status = 'published' AND category_id IS NULL"
    )->c;

    expect((int) $violations)->toBe(0);
});
