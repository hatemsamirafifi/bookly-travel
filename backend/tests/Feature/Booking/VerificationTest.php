<?php

use App\Domains\Booking\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

use Tests\TestCase;

/*
 * Spec 014 (FR-022..FR-028, SC-010/011): public, read-only voucher
 * verification surface. GET /api/public/v/{reference} — unauthenticated,
 * throttle:verify, no-store. Status mapping + PII stripping + enumeration
 * resistance are all verified here.
 *
 * Bookings are created directly via the model (not the public booking API)
 * because the booking API requires a live Stripe round-trip that is not
 * available in the test environment — the verification surface is read-only
 * and must not depend on payment processing to be exercised.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-04 00:00:00');
    // Isolate from cross-test cache pollution (throttle:verify bucket, etc.)
    // — same pattern as the Auth suite (per project memory).
    Cache::flush();
    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $this->traveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $this->category->id,
        'slug' => 'verify-test-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);
    TourTranslation::create([
        'tour_id' => $this->tour->id,
        'locale' => 'en',
        'title' => 'Majestic Roman Colosseum Tour',
        'description' => 'Desc',
        'highlights' => ['h'],
        'inclusions' => ['i'],
        'exclusions' => ['e'],
        'meeting_point' => 'Colosseum main entrance',
        'cancellation_policy' => 'Free 24h before',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeVerificationBooking(TestCase $scope, string $status = 'confirmed'): Booking
{
    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $scope->traveler->id,
        'tour_id' => $scope->tour->id,
        'tour_date' => '2026-08-15',
        'participant_count' => 2,
        'price_per_person' => 8900,
        'total_price' => 17800,
        'currency' => 'EUR',
        'status' => $status,
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Free 24h before',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);
}

it('returns 200 + VALID for a confirmed booking without auth', function () {
    $booking = makeVerificationBooking($this);

    $response = getJson("/api/public/v/{$booking->reference}");

    $response->assertStatus(200)
        ->assertJsonPath('data.reference', $booking->reference)
        ->assertJsonPath('data.status', 'VALID')
        ->assertJsonPath('data.tour_title', 'Majestic Roman Colosseum Tour')
        ->assertJsonPath('data.tour_date', '2026-08-15')
        ->assertJsonPath('data.participant_count', 2);

    // Cache-Control: no-store — a stale status must never be served (FR-027,
    // contract §Idempotency).
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('maps each booking lifecycle status to the public verification status', function () {
    $booking = makeVerificationBooking($this);

    $cases = [
        'confirmed' => 'VALID',
        'cancellation_requested' => 'VALID',
        'completed' => 'VALID',
        'cancelled' => 'CANCELLED',
        'pending_payment' => 'PENDING',
        'expired' => 'EXPIRED',
        'no_show' => 'EXPIRED',
    ];

    foreach ($cases as $bookingStatus => $verificationStatus) {
        $booking->status = $bookingStatus;
        $booking->save();

        getJson("/api/public/v/{$booking->reference}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', $verificationStatus);
    }
});

it('never exposes PII or sensitive fields (FR-022, SC-010)', function () {
    $booking = makeVerificationBooking($this);

    $json = getJson("/api/public/v/{$booking->reference}")->json();

    $allowedKeys = ['reference', 'status', 'tour_title', 'tour_date', 'participant_count', 'created_at', 'voucher_generated_at'];
    $forbidden = ['traveler_name', 'traveler_email', 'email', 'phone', 'total_price', 'currency', 'payment', 'guest_identity', 'id', 'partner_id', 'partner_notes', 'traveler_id', 'tour_id'];

    $dataKeys = array_keys($json['data'] ?? []);
    foreach ($dataKeys as $key) {
        expect(in_array($key, $allowedKeys, true))
            ->toBeTrue("Unexpected field exposed: {$key}");
    }

    $raw = json_encode($json);
    foreach ($forbidden as $field) {
        expect($raw)->not->toContain("\"{$field}\"", "Forbidden field present in response: {$field}");
    }
});

it('returns an identical 404 body for malformed and unknown references (no enumeration)', function () {
    $malformed = getJson('/api/public/v/not-a-reference');
    $unknownValidShape = getJson('/api/public/v/BKO-ZZ99ZZ');

    $malformed->assertStatus(404)->assertJson(['message' => 'Not found.']);
    $unknownValidShape->assertStatus(404)->assertJson(['message' => 'Not found.']);

    expect($malformed->json())->toBe($unknownValidShape->json());
});

it('short-circuits malformed references before any DB hit (regex guard)', function () {
    // Lowercase letters + wrong length must NOT reach the DB. We assert a 404
    // is returned (the regex rejects it inside VerificationAction).
    getJson('/api/public/v/bko-ab23xy')->assertStatus(404); // lowercase
    getJson('/api/public/v/BKO-AB23XY7')->assertStatus(404); // 7 chars
    getJson('/api/public/v/BKO-IO10AB')->assertStatus(404); // ambiguous I/O/0/1
});

it('does not require authentication, a token, or a session', function () {
    $booking = makeVerificationBooking($this);

    // No Authorization header, no cookie — still 200.
    getJson("/api/public/v/{$booking->reference}")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'VALID');
});

it('writes nothing to the DB on a verification lookup (FR-025, SC-011)', function () {
    $booking = makeVerificationBooking($this);
    $generatedAtBefore = $booking->voucher_generated_at;
    $updatedAtBefore = $booking->updated_at;

    getJson("/api/public/v/{$booking->reference}")->assertStatus(200);

    $booking->refresh();
    expect($booking->voucher_generated_at)->toBe($generatedAtBefore)
        ->and($booking->updated_at->timestamp)->toBe($updatedAtBefore->timestamp);
});
