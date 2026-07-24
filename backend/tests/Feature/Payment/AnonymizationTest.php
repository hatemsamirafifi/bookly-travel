<?php

use App\Domains\Booking\Jobs\AnonymizeExpiredBookingData;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Booking\Services\AuditService;
use App\Models\Category;
use App\Models\GuestIdentity;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeAnonymizeTour(): Tour
{
    $category = Category::firstOrCreate(['slug' => 'anon'], ['name' => 'Anonymize']);
    $partner = makePartner();

    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'anon-tour-' . uniqid(),
        'location' => 'Turin, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
}

function makeStaleBooking(Tour $tour): Booking
{
    $traveler = User::factory()->traveler()->create();
    $guestIdentity = GuestIdentity::factory()->create();

    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'guest_identity_id' => $guestIdentity->id,
        'tour_id' => $tour->id,
        // More than 90 days in the past so the job picks it up.
        'tour_date' => now()->subDays(120)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_reason' => 'Traveler requested cancellation with PII in the note.',
        'locale' => 'en',
    ]);
}

it('anonymizes a stale booking: severs traveler/guest links, redacts reason, marks anonymized_at, writes audit', function () {
    $tour = makeAnonymizeTour();
    $booking = makeStaleBooking($tour);
    $originalTravelerId = $booking->traveler_id;
    $originalGuestId = $booking->guest_identity_id;

    expect($originalTravelerId)->not->toBeNull();
    expect($originalGuestId)->not->toBeNull();

    (new AnonymizeExpiredBookingData)->handle(app(AuditService::class));

    $booking->refresh();

    expect($booking->traveler_id)->toBeNull();
    expect($booking->guest_identity_id)->toBeNull();
    expect($booking->anonymized_at)->not->toBeNull();
    // Reason is redacted behind a stable pseudonym token.
    expect($booking->cancellation_reason)->toContain('reason redacted');
    expect($booking->cancellation_reason)->toContain('ANON-');

    $audit = BookingAuditLog::where('booking_id', $booking->id)->where('action', 'data_anonymized')->first();
    expect($audit)->not->toBeNull();
    expect($audit->actor_type)->toBe('system');
    expect($audit->metadata['anonymization_token'])->toContain('ANON-');
});

it('skips already-anonymized bookings on re-run (idempotent — no second audit)', function () {
    $tour = makeAnonymizeTour();
    $booking = makeStaleBooking($tour);

    (new AnonymizeExpiredBookingData)->handle(app(AuditService::class));
    (new AnonymizeExpiredBookingData)->handle(app(AuditService::class));

    expect(BookingAuditLog::where('booking_id', $booking->id)->where('action', 'data_anonymized')->count())->toBe(1);
});