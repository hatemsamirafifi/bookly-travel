<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Actions\IndexTourAction;
use App\Models\Tour;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The single home for public tour-detail cache keys and their invalidation
 * (ADR 0024, design v3 §8).
 *
 * Keys are content-versioned: `tour.{slug}.{locale}.v{content_version}`. The
 * version is read under the same Tour lock that builds/fills the payload, so
 * an old fill can never be written under the version a later promotion uses.
 * The legacy unversioned key `tour.{slug}.{locale}` is no longer written but
 * is still evicted by every existing invalidation event, so an old deploy or
 * a warmed pre-cutover key can never serve stale content.
 *
 * Every eviction runs after commit: a pre-commit eviction could be silently
 * refilled by a concurrent reader while the governing transaction is still
 * open. Callers that currently fire model hooks inside a transaction keep their
 * hook; only the timing of the eviction moves.
 */
class TourPublicCacheService
{
    public const TTL = 900;

    public const LOCALES = ['en', 'es', 'it'];

    public const CATALOG_GENERATION_KEY = 'catalog.generation';

    public const CATALOG_GENERATION_TTL = 86400;

    public function __construct(private readonly TourPublicEligibilityService $eligibility) {}

    public function catalogGeneration(): string
    {
        $marker = Cache::get(self::CATALOG_GENERATION_KEY);
        if (is_string($marker) && $marker !== '') {
            return $marker;
        }

        Cache::add(self::CATALOG_GENERATION_KEY, (string) Str::uuid(), self::CATALOG_GENERATION_TTL);
        $persisted = Cache::get(self::CATALOG_GENERATION_KEY);
        if (! is_string($persisted) || $persisted === '') {
            throw new RuntimeException('Public catalog cache generation is unavailable.');
        }

        return $persisted;
    }

    public function rotateCatalogGeneration(): void
    {
        if (! Cache::put(self::CATALOG_GENERATION_KEY, (string) Str::uuid(), self::CATALOG_GENERATION_TTL)) {
            throw new RuntimeException('Public catalog cache generation could not be invalidated.');
        }
    }

    /**
     * Internal cache-envelope primitive shared across public catalog representations.
     * Enforces generation captured before building, rejects unwrapped/stale entries,
     * and puts the generation envelope into cache on miss.
     *
     * @template T
     *
     * @param  callable(): T  $build
     * @param  list<string>  $tags
     * @return T
     */
    private function rememberEnvelope(string $logicalKey, int $ttl, callable $build, array $tags = []): mixed
    {
        $generation = $this->catalogGeneration();
        $store = $tags !== [] && Cache::supportsTags() ? Cache::tags($tags) : Cache::store();
        $cached = $store->get($logicalKey);

        if (is_array($cached)
            && ($cached['generation'] ?? null) === $generation
            && array_key_exists('payload', $cached)) {
            return $cached['payload'];
        }

        $payload = $build();
        $store->put($logicalKey, ['generation' => $generation, 'payload' => $payload], $ttl);

        return $payload;
    }

    /**
     * Existing logical keys and TTLs retain their invalidation semantics. Store
     * the generation captured BEFORE building, fencing late fills after rotation.
     * Fresh owner checks remain required even when an envelope is current.
     *
     * @template T
     *
     * @param  callable(): T  $build
     * @param  callable(T): T  $guard
     * @param  list<string>  $tags
     * @return T
     */
    public function rememberCatalog(string $logicalKey, int $ttl, callable $build, callable $guard, array $tags = []): mixed
    {
        $this->catalogGeneration();
        if ($this->eligibility->hasIneligiblePublishedOwners()) {
            return $guard($build());
        }

        $payload = $this->rememberEnvelope($logicalKey, $ttl, $build, $tags);

        return $guard($payload);
    }

    /**
     * Cache structured sitemap parts using the catalog generation envelope,
     * hardwired through TourPublicEligibilityService::guardSitemapParts.
     * Unlike catalog aggregates, sitemap parts gate dependencies dynamically
     * on every delivery without globally bypassing cache when an ineligible owner exists.
     *
     * @param  callable(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function rememberSitemapParts(string $logicalKey, int $ttl, callable $build): array
    {
        /** @var array<string, mixed> $parts */
        $parts = $this->rememberEnvelope($logicalKey, $ttl, $build);

        return $this->eligibility->guardSitemapParts($parts);
    }

    /**
     * Capture scalar tour targets for a partner inside the current transaction.
     *
     * @return list<array{id: int, slug: string, content_version: int}>
     */
    public function captureOwnerTargets(int $partnerId): array
    {
        return Tour::query()
            ->where('partner_id', $partnerId)
            ->select(['id', 'slug', 'content_version'])
            ->get()
            ->map(fn (Tour $t): array => [
                'id' => (int) $t->id,
                'slug' => (string) $t->slug,
                'content_version' => (int) ($t->content_version ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Invalidate catalog, homepage, and detail keys for captured owner targets after commit,
     * and enqueue current-state search index reconciliation.
     *
     * @param  list<array{id: int, slug: string, content_version: int}>  $targets
     */
    public function invalidateOwnerTargetsAfterCommit(array $targets): void
    {
        if ($targets === []) {
            return;
        }

        DB::afterCommit(function () use ($targets): void {
            $this->rotateCatalogGeneration();
            $this->flushHomepageTag();

            $this->forgetCapturedDetails($targets);

            foreach ($targets as $target) {
                $tourId = $target['id'];
                try {
                    $pending = IndexTourAction::dispatch($tourId);
                    unset($pending);
                } catch (\Throwable) {
                    Log::warning('tour_projection.dispatch_failed', ['tour_id' => $tourId]);
                }
            }
        });
    }

    /**
     * Evict captured keys even after deletion, plus current ID-matched keys
     * after a rename/promotion. Batch current facts rather than querying per tour.
     *
     * @param  list<array{id: int, slug: string, content_version: int}>  $targets
     */
    public function forgetCapturedDetails(array $targets): void
    {
        $current = [];
        $ids = array_values(array_unique(array_column($targets, 'id')));
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (Tour::query()->whereIn('id', $chunk)->get(['id', 'slug', 'content_version']) as $row) {
                $current[(int) $row->id] = ['slug' => (string) $row->slug, 'version' => (int) $row->content_version];
            }
        }
        foreach ($targets as $target) {
            $keys = [];
            $fresh = $current[$target['id']] ?? null;
            foreach (self::LOCALES as $locale) {
                $keys[] = $this->legacyDetailKey($target['slug'], $locale);
                $keys[] = $this->detailKey($target['slug'], $locale, $target['content_version']);
                if ($fresh !== null) {
                    $keys[] = $this->legacyDetailKey($fresh['slug'], $locale);
                    $keys[] = $this->detailKey($fresh['slug'], $locale, $fresh['version']);
                }
            }
            $this->forgetRaw(...array_unique($keys));
        }
    }

    /** The documented unversioned detail key (legacy readers). */
    public function legacyDetailKey(string $slug, string $locale): string
    {
        return "tour.{$slug}.{$locale}";
    }

    /** The current content-versioned detail key. */
    public function detailKey(string $slug, string $locale, ?int $contentVersion = null): string
    {
        $version = $contentVersion ?? $this->contentVersionForSlug($slug);

        return "tour.{$slug}.{$locale}.v{$version}";
    }

    public function contentVersionForSlug(string $slug): int
    {
        $version = DB::table('tours')->where('slug', $slug)->value('content_version');

        return (int) ($version ?? 0);
    }

    public function forgetRaw(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * One coherent detail eviction for one (possibly renamed) tour.
     *
     * Evicts the legacy key and the current versioned key for each locale, so
     * operational changes that never bump `content_version` (price, capacity,
     * media, availability, failed→ready derivative transitions) still drop the
     * freshly served payload. Safe to call repeatedly and before any tour row
     * exists.
     *
     * @param  list<string|string|null>|string|null  $slugs  exact keys to evict
     */
    public function forgetDetail(string|array|null $slugs, ?string $previousSlug = null): void
    {
        $targets = collect([is_string($slugs) ? [$slugs] : ($slugs ?? []), $previousSlug !== null ? [$previousSlug] : []])
            ->flatten()
            ->filter(fn ($slug) => is_string($slug) && $slug !== '')
            ->unique()
            ->values();

        foreach ($targets as $slug) {
            $keys = [];
            $version = (int) $this->contentVersionForSlug($slug);

            foreach (self::LOCALES as $locale) {
                $keys[] = $this->legacyDetailKey($slug, $locale);
                $keys[] = $this->detailKey($slug, $locale, $version);
            }

            $this->forgetRaw(...$keys);
        }
    }

    /**
     * The after-commit boundary used by every existing tour/translation/option/
     * media/pricing/availability invalidation event. Outside a transaction the
     * eviction runs immediately; inside one it always runs after commit, so the
     * commit-time state decides and a rolled-back save evicts nothing it wrote.
     */
    public function forgetDetailAfterCommit(string|array|null $slugs, ?string $previousSlug = null): void
    {
        DB::afterCommit(function () use ($slugs, $previousSlug): void {
            $this->forgetDetail($slugs, $previousSlug);
        });
    }

    /** Homepage/list tag invalidation shared with the existing listeners. */
    public function flushHomepageTag(): void
    {
        try {
            Cache::tags(['homepage'])->flush();
        } catch (\Throwable) {
            // Store does not support tags — nothing safe to fall back to for a
            // tag flush. Plain Cache::flush() is never called.
        }
    }

    public function flushHomepageTagAfterCommit(): void
    {
        DB::afterCommit(function (): void {
            $this->flushHomepageTag();
        });
    }

    /**
     * The tours whose detail keys a saved model row invalidates. Kept on the
     * service so every model hook resolves slugs through one code path.
     *
     * @param  int|string  ...$tourIds  raw tour_id columns of the saved row
     * @return array<int, string|null>
     */
    public function slugsForTourIds(int|string ...$tourIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $tourIds), fn ($id) => $id > 0));

        if ($ids === []) {
            return [];
        }

        return Tour::query()->whereIn('id', $ids)->pluck('slug')->values()->all();
    }
}
