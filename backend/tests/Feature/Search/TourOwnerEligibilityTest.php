<?php

use App\Domains\Admin\Actions\SuspendPartnerAction;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Search\Actions\IndexTourAction;
use App\Domains\Search\Actions\RemoveFromIndexAction;
use App\Domains\Search\Services\TourPublicCacheService;
use App\Domains\Search\Services\TourPublicEligibilityService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;
use Tests\Support\TourRevisionFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::create(['name' => 'Owner visibility', 'slug' => 'owner-visibility', 'is_active' => true]);
    $this->partner = Partner::create([
        'user_id' => User::factory()->partner()->create()->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
});

function ownerVisibilityTour(Partner $partner, string $sourceLocale = 'en'): Tour
{
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => test()->category->id,
        'slug' => 'owner-visibility-' . bin2hex(random_bytes(6)),
        'source_locale' => $sourceLocale,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
    ]);
    $tour->translations()->create([
        'locale' => $sourceLocale,
        'title' => 'Owner sensitive tour',
        'description' => 'Owner sensitive description.',
    ]);
    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'specific_date',
        'start_date' => now()->addDays(5)->toDateString(),
        'capacity' => 10,
    ]);

    return $tour->fresh();
}

it('denies ineligible owners before serving cold or warm public content', function (string $locale, string $status, bool $active, bool $warm) {
    $tour = ownerVisibilityTour($this->partner, $locale);
    $url = "/api/public/tours/{$tour->slug}?locale={$locale}";
    if ($warm) {
        $this->getJson($url)->assertOk()->assertJsonPath('data.title', 'Owner sensitive tour');
        expect(Cache::has(app(TourPublicCacheService::class)->detailKey($tour->slug, $locale)))->toBeTrue();
    }

    // Deliberately bypass model hooks: stale cache and inconsistent published
    // state must be safe even when ordinary suspension callbacks did not run.
    DB::table('partners')->where('id', $this->partner->id)->update([
        'onboarding_status' => $status,
        'is_active' => $active,
    ]);
    expect($tour->fresh()->status)->toBe('published');

    $this->getJson($url)->assertNotFound()
        ->assertJsonMissingPath('data')
        ->assertDontSee('Owner sensitive', false);
})->with(['en', 'es', 'it'])->with([
    'pending' => ['pending', true],
    'rejected' => ['rejected', true],
    'suspended' => ['suspended', false],
    'inactive approved' => ['approved', false],
    'legacy complete is not approved' => ['complete', true],
])->with([false, true]);

it('keeps eligible-owner temporary unavailability as HTTP 200', function (string $locale) {
    $tour = ownerVisibilityTour($this->partner, $locale);
    $tour->availabilityRules()->delete();

    $this->getJson("/api/public/tours/{$tour->slug}?locale={$locale}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Owner sensitive tour')
        ->assertJsonPath('data.availability.is_unavailable', true);
})->with(['en', 'es', 'it']);

it('checks fresh lifecycle before a warm cache hit', function (string $status, int $code) {
    $tour = ownerVisibilityTour($this->partner);
    $url = "/api/public/tours/{$tour->slug}?locale=en";
    $this->getJson($url)->assertOk();
    DB::table('tours')->where('id', $tour->id)->update(['status' => $status]);

    $this->getJson($url)->assertStatus($code)->assertDontSee('Owner sensitive', false);
})->with(['draft' => ['draft', 404], 'previously published archive' => ['archived', 410]]);

it('removes an ineligible-owner related card from another eligible tours cached detail', function () {
    $main = ownerVisibilityTour($this->partner);
    $otherPartner = Partner::create([
        'user_id' => User::factory()->partner()->create()->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $related = ownerVisibilityTour($otherPartner);
    $url = "/api/public/tours/{$main->slug}?locale=en";
    $this->getJson($url)->assertOk()->assertJsonPath('data.related_tours.0.slug', $related->slug);
    DB::table('partners')->where('id', $otherPartner->id)->update(['is_active' => false]);

    $this->getJson($url)->assertOk()->assertJsonCount(0, 'data.related_tours');
});

it('does not expose public review aggregates after the tour owner becomes ineligible', function () {
    $tour = ownerVisibilityTour($this->partner);
    $url = "/api/public/tours/{$tour->slug}/reviews";
    $this->getJson($url)->assertOk()->assertJsonPath('meta.review_count', 0);

    DB::table('partners')->where('id', $this->partner->id)->update(['is_active' => false]);

    $this->getJson($url)->assertNotFound()->assertJsonMissingPath('data')->assertJsonMissingPath('meta');
});

it('rechecks owner eligibility before cached discovery cards and counts', function (string $endpoint, string $path, bool $counts, string $locale) {
    $hidden = ownerVisibilityTour($this->partner, $locale);
    $visibleOwner = Partner::create([
        'user_id' => User::factory()->partner()->create()->id,
        'role' => 'partner', 'onboarding_status' => 'approved', 'is_active' => true,
    ]);
    $visible = ownerVisibilityTour($visibleOwner, $locale);
    $url = $endpoint . '?locale=' . $locale;
    $warm = $this->getJson($url)->assertOk();
    if ($counts) {
        expect($warm->json($path))->toBe(2);
    } else {
        expect(array_column($warm->json($path), 'slug'))->toContain($hidden->slug, $visible->slug);
    }
    DB::table('partners')->where('id', $this->partner->id)->update(['is_active' => false]);

    $response = $this->getJson($url)->assertOk();
    if ($counts) {
        expect($response->json($path))->toBe(1);
    } else {
        expect(array_column($response->json($path), 'slug'))->toContain($visible->slug)->not->toContain($hidden->slug);
    }
})->with([
    'search cards' => ['/api/public/search/tours', 'data', false],
    'homepage cards' => ['/api/public/homepage', 'data.featured_tours', false],
    'category cards' => ['/api/public/categories/owner-visibility/tours', 'data', false],
    'destination cards' => ['/api/public/destinations/rome/tours', 'data', false],
    'category counts' => ['/api/public/categories', 'data.0.tour_count', true],
    'destination counts' => ['/api/public/destinations', 'data.0.tour_count', true],
])->with(['en', 'es', 'it']);

it('does not recognize a destination solely from an ineligible owners published tour', function () {
    ownerVisibilityTour($this->partner);
    $url = '/api/public/destinations/rome/tours?locale=en';
    $this->getJson($url)->assertOk();
    DB::table('partners')->where('id', $this->partner->id)->update(['is_active' => false]);

    $this->getJson($url)->assertNotFound()->assertJsonMissingPath('data');
});

it('rejects a late catalog fill carrying the generation captured before invalidation', function () {
    $cache = app(TourPublicCacheService::class);
    $key = 'owner.late-fill.' . uniqid();
    $calls = 0;
    $build = function () use (&$calls, $cache): array {
        $calls++;
        if ($calls === 1) {
            $cache->rotateCatalogGeneration();
        }

        return ['build' => $calls];
    };
    expect($cache->rememberCatalog($key, 300, $build, fn ($payload) => $payload))->toBe(['build' => 1]);
    expect($cache->rememberCatalog($key, 300, $build, fn ($payload) => $payload))->toBe(['build' => 2]);
    expect($cache->rememberCatalog($key, 300, $build, fn ($payload) => $payload))->toBe(['build' => 2]);
    expect($calls)->toBe(2);
});

it('misses old raw catalog payloads and preserves logical-key eviction', function () {
    $cache = app(TourPublicCacheService::class);
    $key = 'owner.raw-entry.' . uniqid();
    Cache::put($key, ['build' => 'legacy'], 300);
    $calls = 0;
    $build = function () use (&$calls): array {
        return ['build' => ++$calls];
    };
    expect($cache->rememberCatalog($key, 300, $build, fn ($payload) => $payload))->toBe(['build' => 1]);
    expect($cache->rememberCatalog($key, 300, $build, fn ($payload) => $payload))->toBe(['build' => 1]);
    expect(Cache::get($key))->toHaveKeys(['generation', 'payload']);
    Cache::forget($key);
    expect($cache->rememberCatalog($key, 300, $build, fn ($payload) => $payload))->toBe(['build' => 2]);
});

it('preserves homepage tag eviction while keeping unrelated catalog entries', function () {
    $cache = app(TourPublicCacheService::class);
    expect(Cache::supportsTags())->toBeTrue();
    $key = 'owner.tagged.' . uniqid();
    $other = 'owner.plain.' . uniqid();
    $calls = 0;
    $build = function () use (&$calls): array {
        return ['build' => ++$calls];
    };
    expect($cache->rememberCatalog($key, 600, $build, fn ($p) => $p, ['homepage']))->toBe(['build' => 1]);
    expect($cache->rememberCatalog($other, 300, $build, fn ($p) => $p))->toBe(['build' => 2]);
    Cache::tags(['homepage'])->flush();
    expect($cache->rememberCatalog($key, 600, $build, fn ($p) => $p, ['homepage']))->toBe(['build' => 3]);
    expect($cache->rememberCatalog($other, 300, $build, fn ($p) => $p))->toBe(['build' => 2]);
});

it('retains a persisted generation beyond payload lifetimes and renews it after expiration', function () {
    $cache = app(TourPublicCacheService::class);
    $beforeClock = Carbon::getTestNow();
    $start = now();
    try {
        $first = $cache->catalogGeneration();
        expect(Str::isUuid($first))->toBeTrue()
            ->and($cache->catalogGeneration())->toBe($first);
        Carbon::setTestNow($start->copy()->addSeconds(3601));
        expect($cache->catalogGeneration())->toBe($first);
        Carbon::setTestNow($start->copy()->addSeconds(86401));
        expect($cache->catalogGeneration())->not->toBe($first);
    } finally {
        Carbon::setTestNow($beforeClock);
    }
});

it('uses distinct generations across A to B to A content and preserves unrelated cache values', function () {
    $cache = app(TourPublicCacheService::class);
    $key = 'owner.aba.' . uniqid();
    $sentinel = 'owner.sentinel.' . uniqid();
    Cache::put($sentinel, 'unrelated', 600);
    $generations = [];
    foreach (['A', 'B', 'A'] as $content) {
        $cache->rotateCatalogGeneration();
        $generations[] = $cache->catalogGeneration();
        expect($cache->rememberCatalog($key, 300, fn () => ['content' => $content], fn ($p) => $p))
            ->toBe(['content' => $content]);
    }
    expect(array_unique($generations))->toHaveCount(3)
        ->and(Cache::get($sentinel))->toBe('unrelated');
});

it('supports a real non-taggable file store without changing logical eviction', function () {
    $directory = sys_get_temp_dir() . '/egm003-file-cache-' . uniqid();
    mkdir($directory, 0700);
    $original = config('cache.default');
    $store = 'egm003_owner_file';
    config()->set('cache.stores.' . $store, ['driver' => 'file', 'path' => $directory]);
    Cache::setDefaultDriver($store);
    try {
        expect(Cache::supportsTags())->toBeFalse();
        $cache = app(TourPublicCacheService::class);
        $calls = 0;
        $build = function () use (&$calls): array {
            return ['build' => ++$calls];
        };
        $key = 'owner.file.homepage';
        expect($cache->rememberCatalog($key, 600, $build, fn ($p) => $p, ['homepage']))->toBe(['build' => 1]);
        expect($cache->rememberCatalog($key, 600, $build, fn ($p) => $p, ['homepage']))->toBe(['build' => 1]);
        expect(Cache::get($key))->toHaveKeys(['generation', 'payload']);
        Cache::forget($key);
        expect($cache->rememberCatalog($key, 600, $build, fn ($p) => $p, ['homepage']))->toBe(['build' => 2]);
    } finally {
        Cache::purge($store);
        Cache::setDefaultDriver($original);
        if (realpath(dirname($directory)) !== realpath(sys_get_temp_dir())
            || ! str_starts_with(basename($directory), 'egm003-file-cache-')) {
            throw new RuntimeException('Refusing cache cleanup outside the owned test directory.');
        }
        (new Filesystem)->deleteDirectory($directory);
    }
});

it('fails closed before building when the cache cannot retain its generation marker', function () {
    $original = config('cache.default');
    $store = 'egm003_marker_fault';
    // Infrastructure fault only: retain the real repository and domain service.
    Cache::extend($store, fn () => new Repository(new class extends ArrayStore
    {
        public function get($key)
        {
            return $key === TourPublicCacheService::CATALOG_GENERATION_KEY ? null : parent::get($key);
        }
    }));
    config()->set('cache.stores.' . $store, ['driver' => $store]);
    Cache::setDefaultDriver($store);
    $built = false;
    try {
        $build = function () use (&$built): array {
            $built = true;

            return ['data' => []];
        };
        expect(fn () => app(TourPublicCacheService::class)->rememberCatalog('owner.marker-fault', 300, $build, fn ($p) => $p))
            ->toThrow(RuntimeException::class, 'Public catalog cache generation is unavailable.');
        expect($built)->toBeFalse();
    } finally {
        Cache::purge($store);
        Cache::setDefaultDriver($original);
    }
});

it('keeps warm catalog eligibility queries constant as card count grows', function () {
    $cache = app(TourPublicCacheService::class);
    $eligibility = app(TourPublicEligibilityService::class);
    foreach ([2, 8] as $size) {
        $cards = [];
        for ($i = 0; $i < $size; $i++) {
            $cards[] = ['id' => ownerVisibilityTour($this->partner)->id];
        }
        $key = 'owner.measured.' . uniqid();
        $build = fn (): array => ['data' => $cards];
        $guard = fn (array $payload): array => $eligibility->guardTourList($payload);
        $cache->rememberCatalog($key, 300, $build, $guard);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $start = microtime(true);
            $result = $cache->rememberCatalog($key, 300, $build, $guard);
            $elapsed = (microtime(true) - $start) * 1000;
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        expect($result['data'])->toHaveCount($size)->and($queries)->toHaveCount(2);
        fwrite(STDOUT, sprintf("\nCatalog warm read: %d cards, %d SQL queries, %.3fms\n", $size, count($queries), $elapsed));
    }
});

it('invalidates owner representations and queues reconciliation only after commit', function () {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f): void {
        $cache = app(TourPublicCacheService::class);
        $tour = $f['tour']->refresh();
        $keys = [];
        foreach (['en', 'es', 'it'] as $locale) {
            $payload = test()->getJson("/api/public/tours/{$tour->slug}?locale={$locale}")->assertOk()->json();
            $keys[] = $cache->detailKey($tour->slug, $locale);
            $legacy = $cache->legacyDetailKey($tour->slug, $locale);
            Cache::put($legacy, $payload, 900);
            $keys[] = $legacy;
        }
        $generation = $cache->catalogGeneration();
        Queue::fake();
        DB::beginTransaction();
        $f['partner']->update(['is_active' => false]);
        foreach ($keys as $key) {
            expect(Cache::has($key))->toBeTrue();
        }
        expect($cache->catalogGeneration())->toBe($generation);
        Queue::assertNothingPushed();
        DB::commit();
        foreach ($keys as $key) {
            expect(Cache::has($key))->toBeFalse();
        }
        expect($cache->catalogGeneration())->not->toBe($generation);
        Queue::assertPushed(IndexTourAction::class);
    });
});

it('preserves cache and queues no projection when an owner change rolls back', function () {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f): void {
        $cache = app(TourPublicCacheService::class);
        $generation = $cache->catalogGeneration();
        $key = $cache->detailKey($f['tour']->slug, 'en');
        Cache::put($key, ['owned' => true], 900);
        Queue::fake();
        DB::beginTransaction();
        $f['partner']->update(['is_active' => false]);
        DB::rollBack();
        expect($f['partner']->fresh()->is_active)->toBeTrue()
            ->and($cache->catalogGeneration())->toBe($generation)
            ->and(Cache::has($key))->toBeTrue();
        Queue::assertNothingPushed();
    });
});

it('defers tour ownership cache invalidation until commit and preserves rollback', function (bool $commit) {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f) use ($commit): void {
        $cache = app(TourPublicCacheService::class);
        $generation = $cache->catalogGeneration();
        $key = 'owner.transfer.tag.' . uniqid();
        Cache::tags(['homepage'])->put($key, ['owned' => true], 600);
        Queue::fake();
        DB::beginTransaction();
        $f['tour']->update(['partner_id' => $f['otherPartner']->id]);
        expect(Cache::tags(['homepage'])->has($key))->toBeTrue()
            ->and($cache->catalogGeneration())->toBe($generation);
        Queue::assertNothingPushed();
        if ($commit) {
            DB::commit();
            expect(Cache::tags(['homepage'])->has($key))->toBeFalse()
                ->and($cache->catalogGeneration())->not->toBe($generation);
        } else {
            DB::rollBack();
            expect(Cache::tags(['homepage'])->has($key))->toBeTrue()
                ->and($cache->catalogGeneration())->toBe($generation);
            Queue::assertNothingPushed();
        }
    });
})->with([false, true]);

it('does not invalidate owner content for unrelated partner field changes', function () {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f): void {
        $cache = app(TourPublicCacheService::class);
        $generation = $cache->catalogGeneration();
        Queue::fake();
        $f['partner']->update(['invited_by_admin' => true]);
        expect($cache->catalogGeneration())->toBe($generation);
        Queue::assertNothingPushed();
    });
});

it('does not repeat owner invalidation for an unchanged save', function () {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f): void {
        $f['partner']->update(['is_active' => false]);
        $cache = app(TourPublicCacheService::class);
        $generation = $cache->catalogGeneration();
        Queue::fake();
        $f['partner']->save();
        expect($cache->catalogGeneration())->toBe($generation);
        Queue::assertNothingPushed();
    });
});

it('batches current-version lookups when invalidating many owner tours', function () {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f): void {
        $created = [];
        $counts = [];
        $cache = app(TourPublicCacheService::class);
        try {
            foreach ([2, 8] as $size) {
                while (count($created) + 1 < $size) {
                    $created[] = Tour::create([
                        'partner_id' => $f['partner']->id, 'category_id' => $f['category']->id,
                        'slug' => 'owner-batch-' . uniqid(), 'location' => 'Rome', 'price_amount' => 5000,
                        'duration_minutes' => 120, 'duration_label' => '2 hours',
                        'group_size_min' => 1, 'group_size_max' => 10, 'status' => 'draft',
                    ]);
                }
                $targets = $cache->captureOwnerTargets($f['partner']->id);
                DB::flushQueryLog();
                DB::enableQueryLog();
                try {
                    $cache->invalidateOwnerTargetsAfterCommit($targets);
                    $counts[$size] = count(DB::getQueryLog());
                } finally {
                    DB::disableQueryLog();
                }
            }
            expect($counts[8])->toBe($counts[2]);
        } finally {
            foreach ($created as $tour) {
                $tour->delete();
            }
        }
    });
});

it('does not invalidate or queue when owner deletion is rejected by the real foreign key', function () {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f): void {
        $cache = app(TourPublicCacheService::class);
        $generation = $cache->catalogGeneration();
        $key = $cache->detailKey($f['tour']->slug, 'en');
        Cache::put($key, ['owned' => true], 900);
        Queue::fake();
        expect(fn () => $f['partner']->delete())->toThrow(QueryException::class);
        expect($f['partner']->fresh())->not->toBeNull()
            ->and($cache->catalogGeneration())->toBe($generation)
            ->and(Cache::has($key))->toBeTrue();
        Queue::assertNothingPushed();
    });
});

it('preserves normal suspension to draft and invalidates after its commit', function () {
    Queue::fake();
    Mail::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f): void {
        $cache = app(TourPublicCacheService::class);
        $generation = $cache->catalogGeneration();
        app(SuspendPartnerAction::class)->execute($f['admin'], $f['partner'], 'Ownership acceptance test.');
        expect($f['tour']->fresh()->status)->toBe('draft')
            ->and($f['partner']->fresh()->is_active)->toBeFalse()
            ->and($cache->catalogGeneration())->not->toBe($generation);
    });
});

it('evicts a deleted tours captured content version rather than only version zero', function () {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f): void {
        $cache = app(TourPublicCacheService::class);
        $tour = $f['tour']->refresh();
        $tour->forceFill(['content_version' => 37])->save();
        $key = $cache->detailKey($tour->slug, 'en', 37);
        Cache::put($key, ['owned' => true], 900);
        DB::transaction(fn () => $tour->delete());
        expect(Cache::has($key))->toBeFalse();
    });
});

class Egm003OwnerRecordingEngine extends CollectionEngine
{
    public array $documents = [];

    public array $writeTransactionLevels = [];

    public array $writeBatchSizes = [];

    public bool $retainDocuments = true;

    public function update($models)
    {
        $this->writeTransactionLevels[] = DB::transactionLevel();
        $this->writeBatchSizes[] = count($models);
        foreach ($models as $model) {
            $document = $model->toSearchableArray();
            $this->documents[$model->getScoutKey()] = $this->retainDocuments ? $document : true;
        }
    }

    public function delete($models)
    {
        $this->writeTransactionLevels[] = DB::transactionLevel();
        $this->writeBatchSizes[] = count($models);
        foreach ($models as $model) {
            unset($this->documents[$model->getScoutKey()]);
        }
    }
}

it('reconciles stale index and removal jobs against current committed owner state', function (string $operation, bool $queued) {
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f) use ($operation, $queued): void {
        $engine = new Egm003OwnerRecordingEngine;
        app(EngineManager::class)->extend('egm003_owner_recording', fn () => $engine);
        config()->set('scout.driver', 'egm003_owner_recording');
        config()->set('scout.queue', $queued ? ['connection' => 'sync', 'queue' => 'scout'] : false);
        Queue::fake();
        $id = $f['tour']->id;
        (new IndexTourAction($id))->handle();
        expect($engine->documents)->toHaveKey($id);
        if ($operation === 'late remove after A-B-A') {
            DB::table('tours')->where('id', $id)->update(['status' => 'draft']);
            DB::table('tours')->where('id', $id)->update(['status' => 'published']);
            (new RemoveFromIndexAction($id))->handle();
            expect($engine->documents)->toHaveKey($id);
        } elseif ($operation === 'late index after inactivation') {
            DB::table('partners')->where('id', $f['partner']->id)->update(['is_active' => false]);
            (new IndexTourAction($id))->handle();
            expect($engine->documents)->not->toHaveKey($id);
        } else {
            Tour::withoutSyncingToSearch(fn () => $f['tour']->delete());
            (new RemoveFromIndexAction($id))->handle();
            expect($engine->documents)->not->toHaveKey($id);
        }
        expect(min($engine->writeTransactionLevels))->toBeGreaterThan(0);
        Queue::assertNotPushed(MakeSearchable::class);
        Queue::assertNotPushed(RemoveFromSearch::class);
    });
})->with(['late remove after A-B-A', 'late index after inactivation', 'deleted tour'])->with([false, true]);

it('reconciles serialized stock Scout jobs through the actual synchronous queue', function (bool $removal) {
    $realQueue = app('queue');
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f) use ($realQueue, $removal): void {
        $engine = new Egm003OwnerRecordingEngine;
        app(EngineManager::class)->extend('egm003_legacy_recording', fn () => $engine);
        config()->set('scout.driver', 'egm003_legacy_recording');
        config()->set('scout.queue', ['connection' => 'sync', 'queue' => 'scout']);
        $id = $f['tour']->id;
        (new IndexTourAction($id))->handle();
        $models = $f['tour']->newCollection([$f['tour']->refresh()]);
        $serialized = serialize($removal
            ? new RemoveFromSearch($models)
            : new MakeSearchable($models));
        if ($removal) {
            DB::table('tours')->where('id', $id)->update(['status' => 'draft']);
            DB::table('tours')->where('id', $id)->update(['status' => 'published']);
        } else {
            DB::table('partners')->where('id', $f['partner']->id)->update(['is_active' => false]);
        }
        $fakeQueue = Queue::getFacadeRoot();
        Queue::swap($realQueue);
        try {
            // SyncQueue runs CallQueuedHandler after real model restoration;
            // this exercises the installed Bus handler-resolution boundary.
            Bus::dispatchSync(unserialize($serialized));
        } finally {
            Queue::swap($fakeQueue);
        }
        if ($removal) {
            expect($engine->documents)->toHaveKey($id);
        } else {
            expect($engine->documents)->not->toHaveKey($id);
        }
        expect(min($engine->writeTransactionLevels))->toBeGreaterThan(0);
    });
})->with(['late Make' => [false], 'late Remove after A-B-A' => [true]]);

it('retains the real Scout observer while reconciling its queued work under the lock', function () {
    $realQueue = app('queue');
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f) use ($realQueue): void {
        $engine = new Egm003OwnerRecordingEngine;
        app(EngineManager::class)->extend('egm003_observer_recording', fn () => $engine);
        config()->set('scout.driver', 'egm003_observer_recording');
        config()->set('scout.queue', ['connection' => 'sync', 'queue' => 'scout']);
        Queue::fake();
        // Use a real named-connection transaction so the stock observer's
        // after-commit callback belongs to this commit, not Pest's outer one.
        DB::transaction(fn () => $f['tour']->refresh()->update(['price_amount' => 5100]));
        Queue::assertPushed(MakeSearchable::class);
        $pending = Queue::pushed(MakeSearchable::class)->first();
        $serialized = serialize($pending);
        $fakeQueue = Queue::getFacadeRoot();
        Queue::swap($realQueue);
        try {
            Bus::dispatchSync(unserialize($serialized));
        } finally {
            Queue::swap($fakeQueue);
        }
        expect($engine->documents[$f['tour']->id]['price_amount'])->toBe(5100)
            ->and(min($engine->writeTransactionLevels))->toBeGreaterThan(0);
    });
});

it('honors real legacy Make restoration limits and retained deletion keys', function (bool $allDeleted) {
    $realQueue = app('queue');
    Queue::fake();
    TourRevisionFixtures::withCommittedPublicTour(function (array $f) use ($realQueue, $allDeleted): void {
        $engine = new Egm003OwnerRecordingEngine;
        app(EngineManager::class)->extend('egm003_restore_recording', fn () => $engine);
        config()->set('scout.driver', 'egm003_restore_recording');
        config()->set('scout.queue', ['connection' => 'sync', 'queue' => 'scout']);
        $attributes = $f['tour']->refresh()->getAttributes();
        unset($attributes['id']);
        $attributes['slug'] = 'legacy-deleted-' . uniqid();
        $extra = Tour::create($attributes);
        try {
            $extra->translations()->create(['locale' => 'en', 'title' => 'Legacy deletion source', 'description' => 'Legacy source description.']);
            AvailabilityRule::create([
                'tour_id' => $extra->id, 'rule_type' => 'recurring',
                'days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'capacity' => 10,
            ]);
            $models = $extra->newCollection($allDeleted ? [$extra->refresh()] : [$f['tour']->refresh(), $extra->refresh()]);
            foreach ($models as $tour) {
                (new IndexTourAction($tour->id))->handle();
            }
            $makePayload = serialize(new MakeSearchable($models));
            $removePayload = serialize(new RemoveFromSearch($extra->newCollection([$extra])));
            Tour::withoutSyncingToSearch(fn () => $extra->delete());
            DB::table('partners')->where('id', $f['partner']->id)->update(['is_active' => false]);
            $make = unserialize($makePayload);
            expect($make->models)->toHaveCount($allDeleted ? 0 : 1);
            $writes = count($engine->writeTransactionLevels);
            $fakeQueue = Queue::getFacadeRoot();
            Queue::swap($realQueue);
            try {
                Bus::dispatchSync($make);
                if ($allDeleted) {
                    expect(count($engine->writeTransactionLevels))->toBe($writes);
                } else {
                    expect($engine->documents)->not->toHaveKey($f['tour']->id);
                }
                // Make cannot reconstruct the missing ID. The real restored
                // Remove payload retains it and owns this deletion cleanup.
                expect($engine->documents)->toHaveKey($extra->id);
                Bus::dispatchSync(unserialize($removePayload));
            } finally {
                Queue::swap($fakeQueue);
            }
            expect($engine->documents)->toBeEmpty()
                ->and(min($engine->writeTransactionLevels))->toBeGreaterThan(0);
        } finally {
            $extra->delete();
        }
    });
})->with(['mixed surviving/deleted Make' => [false], 'all-deleted Make' => [true]]);

it('bounds Scout bulk reconciliation queries and engine writes', function (int $size) {
    Queue::fake();
    $prefix = 'scout-bulk-' . uniqid() . '-';
    $batch = [];
    for ($i = 0; $i < $size; $i++) {
        $batch[] = [
            'partner_id' => $this->partner->id, 'category_id' => $this->category->id,
            'slug' => $prefix . $i, 'location' => 'Rome', 'price_amount' => 5000,
            'duration_minutes' => 120, 'duration_label' => '2 hours',
            'group_size_min' => 1, 'group_size_max' => 10, 'status' => 'published',
        ];
        if (count($batch) === 500 || $i === $size - 1) {
            DB::table('tours')->insert($batch);
            $batch = [];
        }
    }
    $models = Tour::where('slug', 'like', $prefix . '%')->get();
    $translations = $rules = [];
    foreach ($models as $index => $tour) {
        $translations[] = ['tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Bulk approved source', 'description' => 'Bulk source description.'];
        $rules[] = ['tour_id' => $tour->id, 'rule_type' => 'recurring', 'days_of_week' => json_encode([0, 1, 2, 3, 4, 5, 6]), 'capacity' => 10];
        if (count($translations) === 500 || $index === $size - 1) {
            DB::table('tour_translations')->insert($translations);
            DB::table('availability_rules')->insert($rules);
            $translations = $rules = [];
        }
    }
    $engine = new Egm003OwnerRecordingEngine;
    $engine->retainDocuments = false;
    app(EngineManager::class)->extend('egm003_bulk_recording', fn () => $engine);
    config()->set('scout.driver', 'egm003_bulk_recording');
    config()->set('scout.queue', ['connection' => 'sync', 'queue' => 'scout']);
    $payload = serialize(new MakeSearchable($models));
    // A real worker receives the serialized command, not this producer's
    // original 10000-model collection as an additional live fixture.
    unset($models);
    gc_collect_cycles();
    $outerLevel = DB::transactionLevel();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $start = microtime(true);
    try {
        // Actual serialized stock command and installed Bus handler mapping;
        // queue processing itself is exercised independently through SyncQueue.
        Bus::dispatchNow(unserialize($payload));
        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
    }
    $milliseconds = (microtime(true) - $start) * 1000;
    expect($engine->documents)->toHaveCount($size)
        ->and(max($engine->writeBatchSizes))->toBeLessThanOrEqual(500)
        ->and(count($engine->writeBatchSizes))->toBe((int) ceil($size / 500))
        ->and(min($engine->writeTransactionLevels))->toBeGreaterThan($outerLevel)
        ->and($queryCount)->toBeLessThanOrEqual((int) ceil($size / 500) * 10 + 2);
    fwrite(STDOUT, sprintf("\nScout batch: %d IDs, %d SQL queries, %d writes, %.3fms, %d peak bytes\n",
        $size, $queryCount, count($engine->writeBatchSizes), $milliseconds, memory_get_peak_usage(true)));
})->with(['two IDs' => [2], 'eight IDs' => [8], 'ten thousand IDs' => [10000]]);

it('filters sitemap tour and destination dependencies after owner withdrawal even with stale cache', function (bool $warm, bool $draft) {
    Cache::forget('bookly:sitemap:xml');
    $tour = ownerVisibilityTour($this->partner);
    $tour->update(['location_slug' => 'withdrawn-destination']);
    if ($warm) {
        $before = $this->get('/api/public/sitemap.xml')->assertOk()->getContent();
        expect($before)->toContain('/tours/' . $tour->slug, '/destinations/withdrawn-destination');
    }
    DB::table('partners')->where('id', $this->partner->id)->update(['is_active' => false]);
    if ($draft) {
        DB::table('tours')->where('id', $tour->id)->update(['status' => 'draft']);
    }

    $response = $this->get('/api/public/sitemap.xml')->assertOk();
    expect($response->getContent())->not->toContain('/tours/' . $tour->slug, '/destinations/withdrawn-destination')
        ->and($response->getContent())->toContain('/blog', '/categories/owner-visibility');
    expect(simplexml_load_string($response->getContent()))->not->toBeFalse();
})->with([false, true])->with([false, true]);

it('keeps eligible-owner unavailable tours in sitemap while disabling outer XML caching', function () {
    Cache::forget('bookly:sitemap:xml');
    $tour = ownerVisibilityTour($this->partner);
    $tour->availabilityRules()->delete();

    $response = $this->get('/api/public/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
    expect($response->getContent())->toContain('/tours/' . $tour->slug, '/destinations/rome')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->not->toContain('max-age=');
});
