<?php

use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Services\PricingTierService;
use App\Domains\Partner\Services\TourService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourOption;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $category = Category::firstOrCreate(['slug' => 'pricing-cat'], ['name' => 'Category']);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $category->id,
        'slug' => 'tiered-tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 1000, // 1000 cents = €10
        'status' => 'published',
    ]);

    $this->option = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'English Guided Tour',
        'price_amount' => 1000,
        'group_size_max' => 10,
        'start_time' => '09:00:00',
        'weekdays' => [1, 2, 3, 4, 5],
        'status' => 'active',
    ]);

    $this->pricingService = app(PricingTierService::class);
});

test('it validates contiguous and complete pricing tiers', function () {
    // Valid tiers covering 1..10
    $validTiers = [
        ['min_participants' => 1, 'max_participants' => 1, 'price_amount' => 1000],
        ['min_participants' => 2, 'max_participants' => 2, 'price_amount' => 900],
        ['min_participants' => 3, 'max_participants' => 4, 'price_amount' => 800],
        ['min_participants' => 5, 'max_participants' => null, 'price_amount' => 700],
    ];

    expect(fn () => $this->pricingService->validateTiers($validTiers, 10))->not->toThrow(ValidationException::class);

    // Invalid: first tier does not start at 1
    $invalidFirst = [
        ['min_participants' => 2, 'max_participants' => 4, 'price_amount' => 1000],
    ];
    expect(fn () => $this->pricingService->validateTiers($invalidFirst, 10))->toThrow(ValidationException::class);

    // Invalid: gap between tiers (1..2, then 4..5)
    $gapTiers = [
        ['min_participants' => 1, 'max_participants' => 2, 'price_amount' => 1000],
        ['min_participants' => 4, 'max_participants' => 10, 'price_amount' => 800],
    ];
    expect(fn () => $this->pricingService->validateTiers($gapTiers, 10))->toThrow(ValidationException::class);

    // Invalid: overlap between tiers (1..3, then 3..5)
    $overlapTiers = [
        ['min_participants' => 1, 'max_participants' => 3, 'price_amount' => 1000],
        ['min_participants' => 3, 'max_participants' => 10, 'price_amount' => 800],
    ];
    expect(fn () => $this->pricingService->validateTiers($overlapTiers, 10))->toThrow(ValidationException::class);

    // Invalid: open-ended tier is not the last one
    $midOpenTiers = [
        ['min_participants' => 1, 'max_participants' => null, 'price_amount' => 1000],
        ['min_participants' => 5, 'max_participants' => 10, 'price_amount' => 800],
    ];
    expect(fn () => $this->pricingService->validateTiers($midOpenTiers, 10))->toThrow(ValidationException::class);

    // Invalid: bounded tiers do not cover up to option capacity (e.g. stops at 6 when capacity is 10)
    $shortTiers = [
        ['min_participants' => 1, 'max_participants' => 6, 'price_amount' => 1000],
    ];
    expect(fn () => $this->pricingService->validateTiers($shortTiers, 10))->toThrow(ValidationException::class);
});

test('it accurately calculates D5 table verification prices', function () {
    // Synchronize D5 specification test tiers:
    // 1 participant: €10 (1000 cents)
    // 2 participants: €9 (900 cents)
    // 3-4 participants: €8 (800 cents)
    // 5+ participants: €7 (700 cents)
    $tiers = [
        ['name' => 'Solo', 'min_participants' => 1, 'max_participants' => 1, 'price_amount' => 1000],
        ['name' => 'Duo', 'min_participants' => 2, 'max_participants' => 2, 'price_amount' => 900],
        ['name' => 'Small Group', 'min_participants' => 3, 'max_participants' => 4, 'price_amount' => 800],
        ['name' => 'Large Group', 'min_participants' => 5, 'max_participants' => null, 'price_amount' => 700],
    ];

    $this->pricingService->syncOptionTiers($this->option, $tiers);

    // D5 Verification Table Checks:
    // 1 participant: €10.00 (1000c)
    $p1 = $this->pricingService->calculatePrice($this->option, 1, $this->tour);
    expect($p1['unit_price_cents'])->toBe(1000)
        ->and($p1['total_price_cents'])->toBe(1000);

    // 2 participants: €9.00/person (900c), total €18.00 (1800c)
    $p2 = $this->pricingService->calculatePrice($this->option, 2, $this->tour);
    expect($p2['unit_price_cents'])->toBe(900)
        ->and($p2['total_price_cents'])->toBe(1800);

    // 3 participants: €8.00/person (800c), total €24.00 (2400c)
    $p3 = $this->pricingService->calculatePrice($this->option, 3, $this->tour);
    expect($p3['unit_price_cents'])->toBe(800)
        ->and($p3['total_price_cents'])->toBe(2400);

    // 4 participants: €8.00/person (800c), total €32.00 (3200c)
    $p4 = $this->pricingService->calculatePrice($this->option, 4, $this->tour);
    expect($p4['unit_price_cents'])->toBe(800)
        ->and($p4['total_price_cents'])->toBe(3200);

    // 5 participants: €7.00/person (700c), total €35.00 (3500c)
    $p5 = $this->pricingService->calculatePrice($this->option, 5, $this->tour);
    expect($p5['unit_price_cents'])->toBe(700)
        ->and($p5['total_price_cents'])->toBe(3500);

    // 10 participants: €7.00/person (700c), total €70.00 (7000c)
    $p10 = $this->pricingService->calculatePrice($this->option, 10, $this->tour);
    expect($p10['unit_price_cents'])->toBe(700)
        ->and($p10['total_price_cents'])->toBe(7000);
});

test('partner pricing controller supports syncing option tiers', function () {
    $tiers = [
        ['name' => 'Solo', 'min_participants' => 1, 'max_participants' => 1, 'price_amount' => 1200],
        ['name' => 'Group', 'min_participants' => 2, 'max_participants' => null, 'price_amount' => 950],
    ];

    $response = $this->actingAs($this->partnerUser)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson("/api/partner/tours/{$this->tour->id}/pricing/sync", [
            'tour_option_id' => $this->option->id,
            'tiers' => $tiers,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Pricing tiers synchronized successfully.');

    expect(PricingTier::where('tour_option_id', $this->option->id)->count())->toBe(2);

    $indexResponse = $this->actingAs($this->partnerUser)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson("/api/partner/tours/{$this->tour->id}/pricing?tour_option_id={$this->option->id}");

    $indexResponse->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('it rejects max_participants equal to zero instead of treating it as open-ended', function () {
    expect(fn () => $this->pricingService->validateTiers([
        ['min_participants' => 1, 'max_participants' => 0, 'price_amount' => 1000],
    ], 10))->toThrow(ValidationException::class);
});

test('updating tour legacy pricing_tiers preserves option-scoped D5 tiers and calculatePrice includes tier_name', function () {
    $this->tour->update(['status' => 'draft']);

    $tiers = [
        ['name' => 'Solo', 'min_participants' => 1, 'max_participants' => 1, 'price_amount' => 1200],
        ['name' => 'Group Rate', 'min_participants' => 2, 'max_participants' => null, 'price_amount' => 900],
    ];
    $this->pricingService->syncOptionTiers($this->option, $tiers);

    $calc = $this->pricingService->calculatePrice($this->option, 3, $this->tour);
    expect($calc['tier_name'])->toBe('Group Rate')
        ->and($calc['unit_price_cents'])->toBe(900);

    app(TourService::class)->updateTour($this->tour, [
        'pricing_tiers' => [
            ['name' => 'Legacy Tier', 'price' => 50, 'min_participants' => 1, 'max_participants' => 10],
        ],
    ]);

    expect(PricingTier::where('tour_option_id', $this->option->id)->count())->toBe(2)
        ->and(PricingTier::where('tour_id', $this->tour->id)->whereNull('tour_option_id')->count())->toBe(1);
});

test('index exposes per_person mode and active option capacities in meta', function () {
    $response = $this->actingAs($this->partnerUser)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson("/api/partner/tours/{$this->tour->id}/pricing");

    $response->assertOk()
        ->assertJsonPath('meta.mode', 'per_person')
        ->assertJsonPath('meta.options.0.id', $this->option->id)
        ->assertJsonPath('meta.options.0.effective_capacity', 10);
});

test('partner pricing endpoints enforce strict per_person mode, option ownership, non-overlap, and cache eviction', function () {
    // Reject per_group on sync & store
    $this->actingAs($this->partnerUser)
        ->postJson("/api/partner/tours/{$this->tour->id}/pricing/sync", [
            'tour_option_id' => $this->option->id,
            'pricing_mode' => 'per_group',
            'tiers' => [
                ['name' => 'Flat Group', 'min_participants' => 1, 'max_participants' => 10, 'price_amount' => 15000],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pricing_mode']);

    $this->actingAs($this->partnerUser)
        ->postJson("/api/partner/tours/{$this->tour->id}/pricing", [
            'name' => 'Group Flat',
            'price_amount' => 15000,
            'min_participants' => 1,
            'max_participants' => 4,
            'pricing_mode' => 'per_group',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pricing_mode']);

    // Reject max_participants = 0 on single store
    $this->actingAs($this->partnerUser)
        ->postJson("/api/partner/tours/{$this->tour->id}/pricing", [
            'name' => 'Zero Max',
            'price_amount' => 5000,
            'min_participants' => 1,
            'max_participants' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['max_participants']);

    // Create base tier 1..4 on option, then reject overlapping sibling 3..6
    $t1 = $this->actingAs($this->partnerUser)
        ->postJson("/api/partner/tours/{$this->tour->id}/pricing", [
            'tour_option_id' => $this->option->id,
            'name' => 'Small',
            'price_amount' => 9000,
            'min_participants' => 1,
            'max_participants' => 4,
        ])
        ->assertStatus(201);

    $this->actingAs($this->partnerUser)
        ->postJson("/api/partner/tours/{$this->tour->id}/pricing", [
            'tour_option_id' => $this->option->id,
            'name' => 'Overlap',
            'price_amount' => 8000,
            'min_participants' => 3,
            'max_participants' => 6,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['min_participants']);

    // Reject retired option on store & sync
    $retiredOption = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Retired Slot',
        'price_amount' => 4000,
        'group_size_max' => 6,
        'start_time' => '15:00:00',
        'weekdays' => [1, 2, 3],
        'status' => TourOption::STATUS_RETIRED,
    ]);

    $this->actingAs($this->partnerUser)
        ->postJson("/api/partner/tours/{$this->tour->id}/pricing/sync", [
            'tour_option_id' => $retiredOption->id,
            'pricing_mode' => 'per_person',
            'tiers' => [
                ['name' => 'All', 'min_participants' => 1, 'max_participants' => 6, 'price_amount' => 4000],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tour_option_id']);

    // Cross-partner access denied (404) & non-numeric tierId -> 404 (not 500)
    $otherPartnerUser = User::factory()->partner()->create();
    Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $this->actingAs($otherPartnerUser)
        ->getJson("/api/partner/tours/{$this->tour->id}/pricing")
        ->assertStatus(404);

    $this->actingAs($this->partnerUser)
        ->putJson("/api/partner/tours/{$this->tour->id}/pricing/not-an-id", ['name' => 'Nope'])
        ->assertStatus(404);

    // Warm public detail cache, then sync updated tiers and confirm cache eviction
    TourTranslation::create([
        'tour_id' => $this->tour->id,
        'locale' => 'en',
        'title' => 'Cached Pricing Tour',
        'description' => str_repeat('Detailed description for cache eviction test. ', 4),
    ]);
    AvailabilityRule::create([
        'tour_id' => $this->tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'capacity' => 10,
    ]);

    $this->getJson("/api/public/tours/{$this->tour->slug}?locale=en")
        ->assertOk()
        ->assertJsonPath('data.options.0.pricing.tiered_pricing.tiers.0.price_amount', 9000);

    $this->actingAs($this->partnerUser)
        ->postJson("/api/partner/tours/{$this->tour->id}/pricing/sync", [
            'tour_option_id' => $this->option->id,
            'pricing_mode' => 'per_person',
            'tiers' => [
                ['name' => 'Solo', 'min_participants' => 1, 'max_participants' => 2, 'price_amount' => 7500],
                ['name' => 'Group', 'min_participants' => 3, 'max_participants' => 10, 'price_amount' => 6200],
            ],
        ])
        ->assertOk();

    $this->getJson("/api/public/tours/{$this->tour->slug}?locale=en")
        ->assertOk()
        ->assertJsonPath('data.options.0.pricing.tiered_pricing.tiers.0.price_amount', 7500)
        ->assertJsonPath('data.options.0.pricing.tiered_pricing.tiers.1.price_amount', 6200);

    expect($t1->json('data.id'))->toBeInt();
});

test('calculatePrice does not bleed tour-level tiers over an option base price when the option has no option-level tiers', function () {
    $this->tour->pricingTiers()->create([
        'tour_option_id' => null,
        'name' => 'Tour Standard',
        'min_participants' => 1,
        'max_participants' => null,
        'price_amount' => 8900,
        'price' => '89.00',
        'currency' => 'EUR',
    ]);

    $optionWithoutTiers = TourOption::create([
        'tour_id' => $this->tour->id,
        'title' => 'Budget Slot',
        'price_amount' => 5900,
        'group_size_max' => 8,
        'start_time' => '11:00:00',
        'weekdays' => [0, 1, 2, 3, 4, 5, 6],
        'status' => TourOption::STATUS_ACTIVE,
    ]);

    $calculated = $this->pricingService->calculatePrice($optionWithoutTiers, 2, $this->tour);

    expect($calculated['mode'])->toBe('base_price')
        ->and($calculated['tier_id'])->toBeNull()
        ->and($calculated['unit_price_cents'])->toBe(5900)
        ->and($calculated['total_price_cents'])->toBe(11800);
});
