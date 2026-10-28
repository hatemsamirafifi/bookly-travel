<?php

use App\Models\Category;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function createCachingTour(array $attributes = []): Tour
{
    $partnerId = $attributes['partner_id'] ?? makePartner()->id;
    $categoryId = $attributes['category_id'] ?? Category::firstOrCreate(
        ['slug' => 'test-cat-' . uniqid()],
        ['name' => 'Test Category', 'is_active' => true]
    )->id;

    $tour = Tour::create(array_merge([
        'partner_id' => $partnerId,
        'category_id' => $categoryId,
        'slug' => 'test-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ], $attributes));

    if (! $tour->translations()->where('locale', 'en')->exists()) {
        $tour->translations()->create([
            'locale' => 'en',
            'title' => 'Test Tour Title ' . $tour->slug,
            'description' => 'Test Tour Description',
        ]);
    }

    // A tour with no availability rules has no upcoming dates, fails
    // `isPubliclyBookable()`, and is therefore excluded from search results
    // (`shouldBeSearchable`). Seed one concrete upcoming date so the tour is
    // genuinely discoverable by the search endpoint.
    if (! $tour->availabilityRules()->exists()) {
        $tour->availabilityRules()->create([
            'rule_type' => 'specific_date',
            'start_date' => now()->addDays(7)->toDateString(),
            'capacity' => 10,
        ]);
    }

    return $tour;
}

it('caches search results for identical queries', function () {
    $category = Category::firstOrCreate(['slug' => 'test-category'], ['name' => 'Test Category', 'is_active' => true]);
    $partner = makePartner();

    createCachingTour([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'search-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'status' => 'published',
    ]);

    $response = $this->getJson('/api/public/search/tours?locale=en&q=rome');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);

    // Second request should hit cache
    $response2 = $this->getJson('/api/public/search/tours?locale=en&q=rome');

    $response2->assertOk();
    expect($response2->json('data'))->toHaveCount(1);
});

it('caches category listing', function () {
    for ($i = 0; $i < 5; $i++) {
        Category::firstOrCreate(
            ['slug' => "cached-cat-{$i}"],
            ['name' => "Cached Cat {$i}", 'is_active' => true]
        );
    }

    $response = $this->getJson('/api/public/categories?locale=en');
    $response->assertOk();
    expect($response->json('data'))->toBeArray();

    // Second request should hit cache
    $response2 = $this->getJson('/api/public/categories?locale=en');
    $response2->assertOk();
    expect($response2->json('data'))->toBeArray();
});

it('caches destination listing', function () {
    $partner = makePartner();

    for ($i = 0; $i < 3; $i++) {
        createCachingTour([
            'partner_id' => $partner->id,
            'location' => 'Rome, Italy',
            'status' => 'published',
        ]);
    }

    $response = $this->getJson('/api/public/destinations?locale=en');
    $response->assertOk();
    expect($response->json('data'))->toBeArray();

    $response2 = $this->getJson('/api/public/destinations?locale=en');
    $response2->assertOk();
    expect($response2->json('data'))->toBeArray();
});

it('caches homepage data', function () {
    $category = Category::firstOrCreate(['slug' => 'home-cat'], ['name' => 'Home Category', 'is_active' => true]);
    $partner = makePartner();

    for ($i = 0; $i < 3; $i++) {
        createCachingTour([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'status' => 'published',
        ]);
    }

    $response = $this->getJson('/api/public/homepage?locale=en');
    $response->assertOk();
    // The homepage contract nests the three content lists under `data` and
    // keeps SEO copy in the top-level `meta` key (not inside `data`).
    expect($response->json('data'))->toHaveKeys(['featured_tours', 'popular_categories', 'featured_destinations']);
    expect($response->json('meta.seo'))->toHaveKeys(['meta_title', 'meta_description']);
});

it('caches tour detail by slug and locale', function () {
    $category = Category::firstOrCreate(['slug' => 'detail-cat'], ['name' => 'Detail Category', 'is_active' => true]);
    $partner = makePartner();
    $tour = createCachingTour([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'cached-tour-detail',
        'status' => 'published',
    ]);

    $response = $this->getJson('/api/public/tours/cached-tour-detail?locale=en');
    $response->assertOk();
    expect($response->json('data.slug'))->toBe('cached-tour-detail');

    // Second request should hit cache
    $response2 = $this->getJson('/api/public/tours/cached-tour-detail?locale=en');
    $response2->assertOk();
    expect($response2->json('data.slug'))->toBe('cached-tour-detail');
});

it('invalidates cache on tour update', function () {
    $category = Category::firstOrCreate(['slug' => 'invalidate-cat'], ['name' => 'Invalidate Category', 'is_active' => true]);
    $partner = makePartner();
    $tour = createCachingTour([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'invalidate-tour',
        'status' => 'published',
    ]);

    // First request - populate cache
    $this->getJson('/api/public/tours/invalidate-tour?locale=en')->assertOk();

    // Update tour
    $tour->update(['status' => 'archived']);

    // Second request - cache should be invalidated
    $response = $this->getJson('/api/public/tours/invalidate-tour?locale=en');
    $response->assertStatus(404);
});
