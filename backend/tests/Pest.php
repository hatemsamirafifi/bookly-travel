<?php

use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific
| PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
| Of course, you may need to change it using the "pest()" function to bind a
| different classes or traits to your test case.
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain
| conditions. The "expect()" function gives you access to a set of "expectations"
| methods that you can use to assert different things. Of course, you may extend
| the Expectation API at any time.
|
*/

//

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code
| specific to your project that you don't want to repeat in every file. Here
| you can also expose helpers as global functions to help you to reduce the
| number of lines of code in your test files.
|
*/

// Shared search-domain test factory helpers. Declared guarded so any single
// test file can run standalone and the full suite never redeclares them.
if (! function_exists('makePartner')) {
    /**
     * Create a Partner domain record backed by a partner-role User.
     *
     * The tours.partner_id FK references partners.id (not users.id — see
     * migration 2026_06_06_231248_fix_tours_partner_id_to_partners_table),
     * so any test that creates a Tour must supply a Partner's id here. Use
     * makePartner()->id for tours.partner_id and makePartner()->user when a
     * test needs to authenticate as the partner (createToken / actingAs).
     */
    function makePartner(string $onboardingStatus = 'approved'): Partner
    {
        $partnerUser = User::factory()->partner()->create();

        return Partner::create([
            'user_id' => $partnerUser->id,
            'role' => 'partner',
            'onboarding_status' => $onboardingStatus,
            'is_active' => true,
        ]);
    }
}

if (! function_exists('makeSearchableTour')) {
    function makeSearchableTour(string $status = 'published', ?string $slug = null): Tour
    {
        $partner = makePartner();

        $category = Category::firstOrCreate(
            ['slug' => 'adventure'],
            ['name' => 'Adventure', 'is_active' => true, 'display_order' => 1],
        );

        return Tour::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'slug' => $slug ?? ('tour-' . uniqid()),
            'location' => 'Rome, Italy',
            'location_slug' => 'rome',
            'duration_minutes' => 240,
            'duration_label' => '4 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000,
            'status' => $status,
        ]);
    }
}

if (! function_exists('addTranslation')) {
    function addTranslation(Tour $tour, string $locale = 'en', string $title = 'Test Tour'): void
    {
        TourTranslation::create([
            'tour_id' => $tour->id,
            'locale' => $locale,
            'title' => $title,
            'description' => 'Description for ' . $title,
            'highlights' => ['Highlight one'],
            'inclusions' => ['Guide'],
            'exclusions' => ['Meals'],
            'meeting_point' => 'Central station',
            'cancellation_policy' => 'Free cancellation 24h before',
        ]);
    }
}

if (! function_exists('addAvailabilityRule')) {
    /**
     * Attach an availability rule to a tour so it has an operating schedule
     * (required by the F9 booking gate, which rejects dates the tour does not
     * operate on). Defaults to a recurring rule covering every day with a
     * 09:00 start — pass overrides for specific_date / day-of-week / start_time.
     */
    function addAvailabilityRule(Tour $tour, array $overrides = []): AvailabilityRule
    {
        return AvailabilityRule::create(array_merge([
            'tour_id' => $tour->id,
            'rule_type' => 'recurring',
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'start_time' => '09:00:00',
            'start_date' => null,
            'end_date' => null,
            'capacity' => $tour->group_size_max ?? 10,
        ], $overrides));
    }
}
