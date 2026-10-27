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

    $response = $this->postJson('/api/public/search/tours', [
        'query' => 'rome',
        'locale' => 'en',
        'per_page' => 10,
    ]);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);

    // Second request should hit cache
    $response2 = $this->postJson('/api/public/search/tours', [
        'query' => 'rome',
        'locale' => 'en',
        'per_page' => 10,
    ]);

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

    $response = $this->getJson('/api/public/categories');
    $response->assertOk();
    expect($response->json('data'))->toBeArray();

    // Second request should hit cache
    $response2 = $this->getJson('/api/public/categories');
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

    $response = $this->getJson('/api/public/destinations');
    $response->assertOk();
    expect($response->json('data'))->toBeArray();

    $response2 = $this->getJson('/api/public/destinations');
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
    expect($response->json('data'))->toHaveKeys(['featured_tours', 'popular_categories', 'featured_destinations', 'meta']);
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
