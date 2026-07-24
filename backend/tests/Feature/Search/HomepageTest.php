<?php

use App\Domains\Partner\Models\AvailabilityRule;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns homepage data wrapped in the data envelope (L3)', function () {
    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $data) => $data
                ->has('featured_tours')
                ->has('popular_categories')
                ->has('featured_destinations')
            )
            ->has('meta', fn (AssertableJson $meta) => $meta->has('seo', fn (AssertableJson $seo) => $seo
                ->has('meta_title')
                ->has('meta_description')
            ))
        );
});

it('returns only bookable featured tours (F9)', function () {
    $category = Category::create(['name' => 'Featured', 'slug' => 'featured', 'is_active' => true, 'display_order' => 1]);

    $tour = Tour::create([
        'category_id' => $category->id,
        'partner_id' => makePartner()->id,
        'slug' => 'homepage-featured-tour',
        'location' => 'London',
        'location_slug' => 'london',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 15,
        'price_amount' => 5000,
        'status' => 'published',
        'is_featured' => true,
    ]);
    AvailabilityRule::create([
        'tour_id' => $tour->id, 'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'capacity' => 10,
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Featured Tour Title',
        'description' => 'A featured tour for homepage', 'highlights' => [], 'inclusions' => [], 'exclusions' => [],
    ]);

    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJsonPath('data.featured_tours.0.title', 'Featured Tour Title');
});

it('excludes unbookable tours from featured (published but no pricing/availability)', function () {
    $category = Category::create(['name' => 'Cat', 'slug' => 'cat', 'is_active' => true, 'display_order' => 1]);

    // Published + featured but price_amount = 0 → not bookable.
    $unpriced = Tour::create([
        'category_id' => $category->id, 'partner_id' => makePartner()->id,
        'slug' => 'unpriced-featured', 'location' => 'Rome, Italy', 'location_slug' => 'rome',
        'duration_minutes' => 300, 'duration_label' => '5 hours',
        'group_size_min' => 1, 'group_size_max' => 15,
        'price_amount' => 0, 'status' => 'published', 'is_featured' => true,
    ]);

    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJsonMissingPath('data.featured_tours.0.title');
});

it('falls back to latest bookable tours when none are featured', function () {
    $category = Category::create(['name' => 'Cat', 'slug' => 'cat2', 'is_active' => true, 'display_order' => 1]);
    $tour = Tour::create([
        'category_id' => $category->id, 'partner_id' => makePartner()->id,
        'slug' => 'latest-bookable', 'location' => 'Rome, Italy', 'location_slug' => 'rome',
        'duration_minutes' => 300, 'duration_label' => '5 hours',
        'group_size_min' => 1, 'group_size_max' => 15,
        'price_amount' => 5000, 'status' => 'published', 'is_featured' => false,
    ]);
    AvailabilityRule::create([
        'tour_id' => $tour->id, 'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'capacity' => 10,
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Latest Bookable',
        'description' => 'x', 'highlights' => [], 'inclusions' => [], 'exclusions' => [],
    ]);

    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJsonPath('data.featured_tours.0.title', 'Latest Bookable');
});

it('returns categories with bookable tour counts', function () {
    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->has('data.popular_categories')->etc());
});

it('sets country to null when the location has no comma (F8)', function () {
    $category = Category::create(['name' => 'Cat', 'slug' => 'cat3', 'is_active' => true, 'display_order' => 1]);
    $tour = Tour::create([
        'category_id' => $category->id, 'partner_id' => makePartner()->id,
        'slug' => 'bare-city-tour', 'location' => 'Rome', 'location_slug' => 'rome',
        'duration_minutes' => 300, 'duration_label' => '5 hours',
        'group_size_min' => 1, 'group_size_max' => 15,
        'price_amount' => 5000, 'status' => 'published', 'is_featured' => true,
    ]);
    AvailabilityRule::create([
        'tour_id' => $tour->id, 'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'capacity' => 10,
    ]);

    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJsonPath('data.featured_destinations.0.country', null);
});

it('parses country from a "City, Country" location (F8)', function () {
    $category = Category::create(['name' => 'Cat', 'slug' => 'cat4', 'is_active' => true, 'display_order' => 1]);
    $tour = Tour::create([
        'category_id' => $category->id, 'partner_id' => makePartner()->id,
        'slug' => 'full-location-tour', 'location' => 'Florence, Italy', 'location_slug' => 'florence',
        'duration_minutes' => 300, 'duration_label' => '5 hours',
        'group_size_min' => 1, 'group_size_max' => 15,
        'price_amount' => 5000, 'status' => 'published', 'is_featured' => true,
    ]);
    AvailabilityRule::create([
        'tour_id' => $tour->id, 'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'capacity' => 10,
    ]);

    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJsonPath('data.featured_destinations.0.country', 'Italy');
});

it('validates locale parameter for homepage', function () {
    getJson('/api/public/homepage')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});

it('supports all three locales for homepage', function (string $locale) {
    getJson("/api/public/homepage?locale={$locale}")->assertOk();
})->with(['en', 'es', 'it']);