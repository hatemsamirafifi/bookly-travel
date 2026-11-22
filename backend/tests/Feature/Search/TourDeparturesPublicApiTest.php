<?php

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $category = Category::firstOrCreate(['slug' => 'search-cat'], ['name' => 'Search Cat']);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $category->id,
        'slug' => 'florence-food-walk',
        'source_locale' => 'en',
        'location' => 'Florence, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 12,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    $this->option1 = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Morning Food Walk',
        'price_amount' => 5000,
        'group_size_max' => 10,
        'start_time' => '09:00:00',
        'status' => 'active',
    ]);

    $this->option2 = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Evening Food Walk',
        'price_amount' => 6000,
        'group_size_max' => 8,
        'start_time' => '17:00:00',
        'status' => 'active',
    ]);

    // Create pricing tiers for option 1:
    // 1-2 participants: €50 (5000c)
    // 3-5 participants: €45 (4500c)
    // 6+ participants: €40 (4000c)
    PricingTier::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option1->id,
        'name' => 'Small',
        'min_participants' => 1,
        'max_participants' => 2,
        'price_amount' => 5000,
        'price' => '50.00',
        'currency' => 'EUR',
    ]);

    PricingTier::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option1->id,
        'name' => 'Medium',
        'min_participants' => 3,
        'max_participants' => 5,
        'price_amount' => 4500,
        'price' => '45.00',
        'currency' => 'EUR',
    ]);

    PricingTier::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option1->id,
        'name' => 'Large',
        'min_participants' => 6,
        'max_participants' => null,
        'price_amount' => 4000,
        'price' => '40.00',
        'currency' => 'EUR',
    ]);

    // Departures for 2026-12-01
    $this->dep1 = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option1->id,
        'starts_at' => Carbon::parse('2026-12-01 08:00:00', 'UTC'),
        'timezone' => 'Europe/Rome',
        'local_date' => '2026-12-01',
        'local_time' => '09:00:00',
        'capacity' => 10,
        'booking_cutoff_hours' => 2,
        'status' => 'active',
    ]);

    $this->dep2 = TourDeparture::create([
        'tour_id' => $this->tour->id,
        'tour_option_id' => $this->option2->id,
        'starts_at' => Carbon::parse('2026-12-01 16:00:00', 'UTC'),
        'timezone' => 'Europe/Rome',
        'local_date' => '2026-12-01',
        'local_time' => '17:00:00',
        'capacity' => 8,
        'booking_cutoff_hours' => 2,
        'status' => 'active',
    ]);
});

test('it returns departures with independent availability and tiered pricing', function () {
    // Query 4 participants for option1
    $response = $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from=2026-12-01&date_to=2026-12-01&option_id={$this->option1->id}&participants=4");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->dep1->id)
        ->assertJsonPath('data.0.capacity', 10)
        ->assertJsonPath('data.0.available_spots', 10)
        ->assertJsonPath('data.0.pricing.mode', 'tiered_per_person')
        ->assertJsonPath('data.0.pricing.unit_price_cents', 4500)
        ->assertJsonPath('data.0.pricing.total_price_cents', 18000)
        ->assertJsonPath('data.0.pricing.currency', 'EUR');
});

test('it filters departures by date and option', function () {
    // Without option_id, returns both departures on 2026-12-01
    $responseAll = $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from=2026-12-01&date_to=2026-12-01");
    $responseAll->assertStatus(200)->assertJsonCount(2, 'data');

    // With option2 id, returns only dep2
    $responseOpt2 = $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from=2026-12-01&date_to=2026-12-01&option_id={$this->option2->id}");
    $responseOpt2->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->dep2->id);
});

test('it validates date range query maximum of 60 days', function () {
    $response = $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from=2026-12-01&date_to=2027-02-15");
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['date_to']);
});

test('it returns 404 when partner owner is ineligible', function () {
    // Ineligible partner (onboarding_status = pending)
    $this->partner->update(['onboarding_status' => 'pending']);

    $response = $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from=2026-12-01&date_to=2026-12-01");
    $response->assertStatus(404);
});

test('it excludes departures belonging to retired tour options', function () {
    $this->option2->update(['status' => 'retired']);

    $response = $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from=2026-12-01&date_to=2026-12-01");
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tour_option_id', $this->option1->id);

    $opt2Response = $this->getJson("/api/public/tours/{$this->tour->slug}/departures?date_from=2026-12-01&date_to=2026-12-01&option_id={$this->option2->id}");
    $opt2Response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});
