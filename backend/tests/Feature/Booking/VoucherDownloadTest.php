<?php

use App\Domains\Booking\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

use function Pest\Laravel\getJson;

/*
 * Spec 014 (FR-007, FR-008, R10): the voucher download endpoint serves any
 * post-payment, non-cancelled booking — i.e. `confirmed` OR `completed`.
 * Non-owners, cancelled/pending/expired statuses, and other non-eligible
 * statuses all 404 (no enumeration signal). Unauthenticated → 401.
 *
 * GET /api/public/traveler/bookings/{reference}/voucher (auth:sanctum)
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    \Illuminate\Support\Carbon::setTestNow('2026-07-04 00:00:00');
    $this->category = Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure', 'is_active' => true, 'display_order' => 1]);
    $this->traveler = User::factory()->traveler()->create();
    $this->otherTraveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $this->category->id,
        'slug' => 'download-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    \App\Models\TourTranslation::create([
        'tour_id' => $this->tour->id,
        'locale' => 'en',
        'title' => 'Download Test Tour',
        'description' => 'Desc',
        'highlights' => ['h'],
        'inclusions' => ['i'],
        'exclusions' => ['e'],
        'meeting_point' => 'Central station',
        'cancellation_policy' => 'Free 24h before',
    ]);
});

afterEach(function () {
    \Illuminate\Support\Carbon::setTestNow();
});

function makeDownloadBooking(TestCase $scope, string $status = 'confirmed'): Booking
{
    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $scope->traveler->id,
        'tour_id' => $scope->tour->id,
        'tour_date' => '2026-08-15',
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => $status,
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Free 24h before',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);
}

function voucherPath(Booking $booking): string
{
    return storage_path("app/vouchers/voucher-{$booking->reference}.pdf");
}

it('downloads a PDF for a confirmed booking owned by the traveler', function () {
    $booking = makeDownloadBooking($this, 'confirmed');
    $token = $this->traveler->createToken('test')->plainTextToken;

    $response = getJson(
        "/api/public/traveler/bookings/{$booking->reference}/voucher",
        ['Authorization' => 'Bearer ' . $token]
    );

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/pdf');

    @unlink(voucherPath($booking));
});

it('downloads a PDF for a completed booking (FR-008 widened guard)', function () {
    $booking = makeDownloadBooking($this, 'completed');
    $token = $this->traveler->createToken('test')->plainTextToken;

    $response = getJson(
        "/api/public/traveler/bookings/{$booking->reference}/voucher",
        ['Authorization' => 'Bearer ' . $token]
    );

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/pdf');

    @unlink(voucherPath($booking));
});

it('returns 404 for a cancelled booking (no enumeration signal)', function () {
    $booking = makeDownloadBooking($this, 'cancelled');
    $token = $this->traveler->createToken('test')->plainTextToken;

    getJson(
        "/api/public/traveler/bookings/{$booking->reference}/voucher",
        ['Authorization' => 'Bearer ' . $token]
    )->assertStatus(404);

    expect(file_exists(voucherPath($booking)))->toBeFalse(); // never generated
});

it('returns 404 for pending_payment, expired, and no_show bookings', function () {
    $token = $this->traveler->createToken('test')->plainTextToken;

    foreach (['pending_payment', 'expired', 'no_show', 'cancellation_requested'] as $status) {
        $booking = makeDownloadBooking($this, $status);

        getJson(
            "/api/public/traveler/bookings/{$booking->reference}/voucher",
            ['Authorization' => 'Bearer ' . $token]
        )->assertStatus(404);

        @unlink(voucherPath($booking));
    }
});

it('returns 404 when the booking belongs to a different traveler (ownership guard)', function () {
    $booking = makeDownloadBooking($this, 'confirmed');
    $otherToken = $this->otherTraveler->createToken('test')->plainTextToken;

    getJson(
        "/api/public/traveler/bookings/{$booking->reference}/voucher",
        ['Authorization' => 'Bearer ' . $otherToken]
    )->assertStatus(404); // not 403 — no enumeration signal

    @unlink(voucherPath($booking));
});

it('returns 401 when unauthenticated', function () {
    $booking = makeDownloadBooking($this, 'confirmed');

    getJson("/api/public/traveler/bookings/{$booking->reference}/voucher")
        ->assertStatus(401);

    @unlink(voucherPath($booking));
});

it('returns 404 for an unknown reference', function () {
    $token = $this->traveler->createToken('test')->plainTextToken;

    getJson(
        '/api/public/traveler/bookings/BKO-ZZ99ZZ/voucher',
        ['Authorization' => 'Bearer ' . $token]
    )->assertStatus(404);
});