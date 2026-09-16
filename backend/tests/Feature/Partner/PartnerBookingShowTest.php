<?php

/*
 * Coverage for GET /api/partner/bookings/{reference}
 * (PartnerBookingController::show + PartnerBookingResource).
 *
 * Authorization contract: sanctum auth (401 when missing), PartnerRoleMiddleware
 * (intentional 404 for non-partner roles so travelers cannot enumerate partner
 * routes), and partner-scoped lookup (404 for foreign or unknown references).
 */

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'show-detail'], ['name' => 'Show Detail']);

    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $this->traveler = User::factory()->traveler()->create();

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'booking-show-tour-' . uniqid(),
        'location' => 'Venice, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 7500,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
    addTranslation($this->tour, 'en', 'Venice Show Tour');

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;

    $this->booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->addDays(3)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 7500,
        'total_price' => 15000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);
});

it('returns the own booking as a PartnerBookingResource for the authorized partner', function () {
    getJson('/api/partner/bookings/' . $this->booking->reference, [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertOk()
        ->assertJsonPath('data.reference', $this->booking->reference)
        ->assertJsonPath('data.status', Booking::STATUS_CONFIRMED)
        ->assertJsonPath('data.tour.title', 'Venice Show Tour')
        ->assertJsonPath('data.traveler.email', $this->traveler->email)
        ->assertJsonPath('data.total_participants', 2)
        ->assertJsonPath('data.total_amount', 150)
        ->assertJsonStructure([
            'data' => [
                'id', 'reference', 'status', 'tour', 'traveler',
                'booking_date', 'tour_date', 'participants',
                'total_participants', 'total_amount', 'currency',
                'payment_status', 'created_at', 'updated_at',
            ],
        ]);
});

it('returns 404 when the partner accesses another partner booking', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $otherToken = $otherPartnerUser->createToken('test', ['partner'])->plainTextToken;

    getJson('/api/partner/bookings/' . $this->booking->reference, [
        'Authorization' => 'Bearer ' . $otherToken,
    ])->assertNotFound();
});

it('rejects unauthenticated requests with 401', function () {
    getJson('/api/partner/bookings/' . $this->booking->reference)
        ->assertUnauthorized();
});

it('does not disclose the endpoint to non-partner roles with 404', function () {
    $travelerToken = $this->traveler->createToken('test')->plainTextToken;

    // PartnerRoleMiddleware intentionally returns 404 (not 403) for
    // non-partner roles so travelers cannot enumerate partner routes.
    getJson('/api/partner/bookings/' . $this->booking->reference, [
        'Authorization' => 'Bearer ' . $travelerToken,
    ])->assertNotFound();
});

it('returns 404 for a nonexistent booking reference', function () {
    getJson('/api/partner/bookings/BKO-ZZZZZZ', [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertNotFound();
});
