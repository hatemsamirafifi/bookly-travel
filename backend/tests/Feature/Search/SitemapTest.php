<?php

use App\Domains\Partner\Models\Partner;
use App\Domains\Search\Services\TourPublicCacheService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

it('bounds cold and hot sitemap queries for ten thousand published tours', function () {
    $partner = makePartner();
    $category = Category::create(['name' => 'Sitemap scale', 'slug' => 'sitemap-scale', 'is_active' => true]);
    $batch = [];
    for ($i = 1; $i <= 10000; $i++) {
        $batch[] = [
            'partner_id' => $partner->id, 'category_id' => $category->id,
            'slug' => 'sitemap-scale-' . $i, 'status' => 'published', 'price_amount' => 5000,
            'location' => 'City ' . ($i % 20), 'location_slug' => 'city-' . ($i % 20),
            'duration_minutes' => 120, 'duration_label' => '2 hours',
            'group_size_min' => 1, 'group_size_max' => 10,
        ];
        if (count($batch) === 500) {
            DB::table('tours')->insert($batch);
            $batch = [];
        }
    }
    foreach (['cold', 'hot'] as $mode) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = microtime(true);
        try {
            $response = get('/api/public/sitemap.xml')->assertOk();
            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $milliseconds = (microtime(true) - $start) * 1000;
        $xml = $response->getContent();
        expect($xml)->toContain('/tours/sitemap-scale-1<', '/tours/sitemap-scale-10000<');
        // 500-row build chunks plus batched dependency probes; no per-tour query.
        expect($queries)->toBeLessThanOrEqual($mode === 'cold' ? 50 : 25);
        fwrite(STDOUT, sprintf("\nSitemap %s: 10000 tours, %d SQL queries, %.3fms, %d peak bytes, %d XML bytes\n",
            $mode, $queries, $milliseconds, memory_get_peak_usage(true), strlen($xml)));
    }
});

it('rejects legacy XML and stale generations while preserving structured hot cache reuse', function () {
    $tour = makeSearchableTour('published', 'sitemap-envelope-tour');
    $hidden = makeSearchableTour('published', 'unrelated-ineligible-sitemap');
    $cache = app(TourPublicCacheService::class);
    Cache::put('bookly:sitemap:xml', '<legacy-private-content/>', 3600);
    get('/api/public/sitemap.xml')->assertOk()->assertDontSee('legacy-private-content');
    $envelope = Cache::get('bookly:sitemap:xml');
    expect($envelope['payload']['tours'])->toHaveKey($tour->id)
        ->and($envelope['payload'])->not->toHaveKey('xml');

    // Bypass invalidation so a current envelope and inconsistent row coexist.
    DB::table('partners')->where('id', $hidden->partner_id)->update(['is_active' => false]);
    $builds = 0;
    $parts = $cache->rememberSitemapParts('bookly:sitemap:xml', 3600, function () use (&$builds) {
        $builds++;
        throw new LogicException('Hot structured sitemap must not rebuild.');
    });
    expect($builds)->toBe(0)->and($parts['tours'])->toHaveKey($tour->id);

    $cache->rotateCatalogGeneration();
    $fresh = $cache->rememberSitemapParts('bookly:sitemap:xml', 3600, fn () => ['prefix' => 'fresh']);
    expect($fresh['prefix'])->toBe('fresh');
    Cache::forget('bookly:sitemap:xml');
    $evicted = $cache->rememberSitemapParts('bookly:sitemap:xml', 3600, fn () => ['prefix' => 'rebuilt']);
    expect($evicted['prefix'])->toBe('rebuilt');
});

it('retains a shared sitemap destination supported by another eligible owner', function () {
    $hidden = makeSearchableTour('published', 'hidden-shared-destination');
    $visible = makeSearchableTour('published', 'visible-shared-destination');
    get('/api/public/sitemap.xml')->assertOk()->assertSee('/destinations/rome', false);
    DB::table('partners')->where('id', $hidden->partner_id)->update(['is_active' => false]);
    get('/api/public/sitemap.xml')->assertOk()
        ->assertDontSee('/tours/' . $hidden->slug, false)
        ->assertSee('/tours/' . $visible->slug, false)
        ->assertSee('/destinations/rome', false);
});
