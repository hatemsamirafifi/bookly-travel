<?php

use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('caches search results for identical queries', function () {
    $category = Category::firstOrCreate(['slug' => 'test-category'], ['name' => 'Test Category']);
    $partner = makePartner();

    Tour::factory()->create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'test-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    $response = postJson('/api/public/search/tours', [
        'query' => 'rome',
        'locale' => 'en',
        'per_page' => 10,
    ]);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);

    $cacheKey = 'search.' . md5(json_encode([
        'query' => 'rome',
        'locale' => 'en',
        'per_page' => 10,
    ]));

    // Second request should hit cache
    $response2 = postJson('/api/public/search/tours', [
        'query' => 'rome',
        'locale' => 'en',
        'per_page' => 10,
    ]);

    $response2->assertOk();
    expect($response2->json('data'))->toHaveCount(1);
});

it('caches category listing', function () {
    Category::factory()->count(5)->create(['is_active' => true]);

    $response = getJson('/api/public/categories');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);

    // Second request should hit cache
    $response2 = getJson('/api/public/categories');
    $response2->assertOk();
    expect($response2->json('data'))->toHaveCount(5);
});

it('caches destination listing', function () {
    $partner = makePartner();

    Tour::factory()->count(10)->create([
        'partner_id' => $partner->id,
        'location' => 'Rome, Italy',
        'status' => 'published',
    ]);

    Tour::factory()->count(5)->create([
        'partner_id' => $partner->id,
        'location' => 'Paris, France',
        'status' => 'published',
    ]);

    $response = getJson('/api/public/destinations');
    $response->assertOk();
    expect($response->json('data'))->toBeArray();
});

it('caches homepage data', function () {
    $category = Category::firstOrCreate(['slug' => 'home-cat'], ['name' => 'Home Category']);
    $partner = makePartner();

    Tour::factory()->count(5)->create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'home-tour-' . uniqid(),
        'status' => 'published',
    ]);

    $response = getJson('/api/public/homepage?locale=en');
    $response->assertOk();
    expect($response->json('data'))->toHaveKeys(['featured_tours', 'popular_categories', 'featured_destinations', 'meta']);
});

it('caches tour detail by slug and locale', function () {
    $category = Category::firstOrCreate(['slug' => 'detail-cat'], ['name' => 'Detail Category']);
    $partner = makePartner();
    $tour = Tour::factory()->create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'cached-tour-detail',
        'status' => 'published',
    ]);
    addTourTranslations($tour);

    $response = getJson('/api/public/tours/cached-tour-detail?locale=en');
    $response->assertOk();
    expect($response->json('data.slug'))->toBe('cached-tour-detail');

    // Second request should hit cache
    $response2 = getJson('/api/public/tours/cached-tour-detail?locale=en');
    $response2->assertOk();
    expect($response2->json('data.slug'))->toBe('cached-tour-detail');
});

it('invalidates cache on tour update', function () {
    $category = Category::firstOrCreate(['slug' => 'invalidate-cat'], ['name' => 'Invalidate Category']);
    $partner = makePartner();
    $tour = Tour::factory()->create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'invalidate-tour',
        'status' => 'published',
    ]);
    addTourTranslations($tour);

    // First request - populate cache
    getJson('/api/public/tours/invalidate-tour?locale=en')->assertOk();

    // Update tour
    $tour->update(['status' => 'archived']);

    // Second request - cache should be invalidated
    $response = getJson('/api/public/tours/invalidate-tour?locale=en');
    $response->assertStatus(404);
});
