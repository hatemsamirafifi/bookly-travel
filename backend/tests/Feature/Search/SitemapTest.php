<?php

use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

// SitemapController caches the rendered XML for 1h under a fixed key
// ('bookly:sitemap:xml'). RefreshDatabase rolls back the DB between tests but
// does NOT clear the cache, so without a flush each test would see a prior
// test's (or a prior run's) cached sitemap instead of one rendered from the
// current DB state. Production caching behaviour is untouched.
beforeEach(function () {
    Cache::flush();
});

it('returns valid XML sitemap', function () {
    $response = get('/api/public/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');

    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
});

it('includes hreflang alternates for URLs', function () {
    $response = get('/api/public/sitemap.xml');

    $content = $response->getContent();
    expect($content)->toContain('xhtml:link');
    expect($content)->toContain('hreflang="en"');
    expect($content)->toContain('hreflang="es"');
    expect($content)->toContain('hreflang="it"');
});

it('includes homepage URL with highest priority', function () {
    $response = get('/api/public/sitemap.xml');

    $content = $response->getContent();
    expect($content)->toContain('<priority>1.0</priority>');
});

it('has valid cache control headers', function () {
    $response = get('/api/public/sitemap.xml');

    $response->assertHeader('Cache-Control');
});

it('only includes published tours in sitemap', function () {
    // SitemapController::renderXml() walks the published-tour catalog, so seed
    // both a published and a draft tour and assert only the published one
    // appears. Without seeding, no tour URLs exist and the original loop-based
    // assertion ran zero times (risky / no assertions).
    $category = Category::create([
        'name' => 'Sitemap Category',
        'slug' => 'sitemap-category',
        'is_active' => true,
        'display_order' => 1,
    ]);

    $partner = Partner::create([
        'user_id' => User::factory()->partner()->create()->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $tourFields = [
        'category_id' => $category->id,
        'partner_id' => $partner->id,
        'price_amount' => 5000,
        'location' => 'Paris, France',
        'location_slug' => 'paris',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 2,
        'group_size_max' => 10,
    ];

    $published = Tour::create(array_merge($tourFields, [
        'slug' => 'published-sitemap-tour',
        'status' => 'published',
    ]));

    Tour::create(array_merge($tourFields, [
        'slug' => 'draft-sitemap-tour',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'status' => 'draft',
    ]));

    $content = get('/api/public/sitemap.xml')->getContent();

    // Published tour URL is present, draft tour URL is excluded.
    expect($content)->toContain("/tours/{$published->slug}")
        ->and($content)->not->toContain('/tours/draft-sitemap-tour');
});