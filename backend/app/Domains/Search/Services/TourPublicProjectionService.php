<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Transformers\TourCardTransformer;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The single coherent public read boundary (ADR 0024, design v3 §8).
 *
 * Detail reads take ONE Tour shared lock before any eager load and before any
 * cache hit, validate fresh lifecycle/visibility under that lock, read
 * `content_version`, then get/build `tour.{slug}.{locale}.v{version}`. The fill
 * completes before the lock is released, so one payload can never straddle a
 * promotion: approval and derivative writes serialize on the Tour row.
 *
 * Card and index hydration batch-locks the sorted Tour IDs and reloads the
 * relations the transformer reads once, so a collection never mixes approved
 * revisions and never issues a per-card query loop. It never materializes a
 * candidate; a collection may briefly show a PREVIOUSLY approved projection
 * (documented eventual consistency), but never a proposed one.
 */
class TourPublicProjectionService
{
    /** Relations the card transformer and bookable invariant read. */
    public const CARD_RELATIONS = [
        'translations',
        'translationStates',
        'category',
        'media',
        'availabilityRules',
        'availabilityExceptions',
        'options',
        'partnerRecord',
    ];

    public function __construct(
        private readonly TourPublicCacheService $cache,
        private readonly TourCardTransformer $transformer,
        private readonly TourPublicEligibilityService $eligibility,
    ) {}

    /**
     * Whether the projection service should serve the cached versioned payload.
     * Exposed for tests so the cache-hit visibility can be asserted without
     * reproducing the whole read.
     */
    public function cachedDetail(string $slug, string $locale, ?int $version = null): mixed
    {
        $version ??= $this->cache->contentVersionForSlug($slug);

        return Cache::get($this->cache->detailKey($slug, $locale, $version));
    }

    /**
     * @param  callable(Tour, string): array  $build  the payload builder
     */
    public function detail(string $slug, string $locale, callable $build): array
    {
        return DB::transaction(function () use ($slug, $locale, $build) {
            $tour = Tour::query()->where('slug', $slug)->sharedLock()->first();

            if ($tour === null) {
                throw new HttpResponseException(response()->json(['message' => 'Tour not found.'], 404));
            }

            if ($tour->status !== 'published') {
                $archived = $tour->status === 'archived' && $tour->published_at !== null;
                throw new HttpResponseException(response()->json([
                    'message' => $archived ? 'This tour is no longer available.' : 'Tour not found.',
                ], $archived ? 410 : 404));
            }

            // Check persisted eligibility before consulting any cached payload.
            $this->eligibility->assertEligibleOwner($tour);

            $contentVersion = (int) $tour->getAttribute('content_version');
            $key = $this->cache->detailKey($slug, $locale, $contentVersion);

            $cached = Cache::get($key);

            if (is_array($cached)) {
                return $this->eligibility->guardDetail($cached);
            }

            $result = $build($tour, $locale);

            Cache::put($key, $result, TourPublicCacheService::TTL);

            return $this->eligibility->guardDetail($result);
        });
    }

    /**
     * @template T
     *
     * @param  list<int>  $ids
     * @param  callable(EloquentCollection<int, Tour>, list<int>): T  $callback
     * @return T
     */
    public function withIndexProjections(array $ids, callable $callback): mixed
    {
        $ids = array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
        sort($ids, SORT_NUMERIC);

        return DB::transaction(function () use ($ids, $callback) {
            $tours = $ids === []
                ? new EloquentCollection
                : Tour::query()->whereIn('id', $ids)->orderBy('id')->sharedLock()->get();
            if ($tours->isNotEmpty()) {
                $tours->load(self::CARD_RELATIONS);
            }

            return $callback($tours, $ids);
        });
    }

    /**
     * Coherent indexing transaction (design v3 §8.1).
     *
     * Shared-locks the Tour row BEFORE eager-loading CARD_RELATIONS and running
     * the indexing callback. The callback receives the reloaded Tour (or null if missing)
     * and performs the actual Scout write (searchable / unsearchable) WHILE the lock is held.
     *
     * @template T
     *
     * @param  callable(?Tour): T  $callback
     * @return T
     */
    public function withIndexProjection(int $tourId, callable $callback): mixed
    {
        return $this->withIndexProjections([$tourId], fn (EloquentCollection $tours) => $callback($tours->first()));
    }

    /**
     * @param  Collection<int, Tour>  $tours
     * @return EloquentCollection<int, Tour>
     */
    public function hydrateCards(Collection $tours): EloquentCollection
    {
        return DB::transaction(fn (): EloquentCollection => $this->lockedCardRows($tours));
    }

    /**
     * @param  Collection<int, Tour>  $tours
     * @param  (callable(Tour, string): array<string, mixed>)|null  $transform
     * @return list<array<string, mixed>>
     */
    public function hydrateAndTransformCards(Collection $tours, string $locale, ?callable $transform = null): array
    {
        return $this->hydrateAndTransformWithPredicate($tours, $locale,
            fn (Tour $tour): bool => $tour->isPubliclyBookable(), $transform);
    }

    /**
     * Published content remains visible for eligible owners even when booking
     * is temporarily unavailable. Filtering precedes the editorial limit.
     *
     * @param  Collection<int, Tour>  $tours
     * @return list<array<string, mixed>>
     */
    public function hydrateAndTransformPublishedEligibleCards(Collection $tours, string $locale, int $limit = 6): array
    {
        return $this->hydrateAndTransformWithPredicate($tours, $locale,
            fn (Tour $tour): bool => $tour->status === 'published' && $tour->hasEligiblePublicOwner(), null, $limit);
    }

    /**
     * @param  Collection<int, Tour>  $tours
     * @param  callable(Tour): bool  $predicate
     * @param  (callable(Tour, string): array<string, mixed>)|null  $transform
     * @return list<array<string, mixed>>
     */
    private function hydrateAndTransformWithPredicate(Collection $tours, string $locale, callable $predicate, ?callable $transform = null, ?int $limit = null): array
    {
        return DB::transaction(function () use ($tours, $locale, $predicate, $transform, $limit): array {
            if ($limit !== null && $limit < 1) {
                return [];
            }
            $cards = [];
            foreach ($this->lockedCardRows($tours) as $tour) {
                if (! $predicate($tour)) {
                    continue;
                }
                $cards[] = $transform !== null
                    ? $transform($tour, $locale)
                    : $this->transformer->transform($tour, $locale);
                if ($limit !== null && count($cards) >= $limit) {
                    break;
                }
            }

            return $cards;
        });
    }

    /**
     * Caller owns the transaction through transformation. Batch-load fresh rows
     * after sorted shared locks, preserving only explicit genuine-review aliases
     * from candidate selection; no stale content attributes are copied.
     *
     * @param  Collection<int, Tour>  $tours
     * @return EloquentCollection<int, Tour>
     */
    private function lockedCardRows(Collection $tours): EloquentCollection
    {
        if ($tours->isEmpty()) {
            return new EloquentCollection;
        }
        $ids = $tours->map(fn (Tour $tour): int => (int) $tour->id)->unique()->sort()->values()->all();
        $freshRows = Tour::query()->whereIn('id', $ids)->orderBy('id')->sharedLock()
            ->with(self::CARD_RELATIONS)->get()->keyBy('id');
        $ordered = [];
        foreach ($tours as $candidate) {
            $fresh = $freshRows->get($candidate->id);
            if ($fresh === null) {
                continue;
            }
            $card = clone $fresh;
            if (array_key_exists('genuine_review_count', $candidate->getAttributes())) {
                $count = (int) $candidate->getAttribute('genuine_review_count');
                $average = $candidate->getAttribute('genuine_average_rating');
                $card->setAttribute('genuine_review_count', $count);
                $card->setAttribute('genuine_average_rating', $average);
                $card->setAttribute('review_count', $count);
                $card->setAttribute('average_rating', $count > 0 ? round((float) $average, 2) : 0.0);
            }
            $ordered[] = $card;
        }

        return new EloquentCollection($ordered);
    }

    public function hydrateCard(Tour $tour): Tour
    {
        // A disappeared row must not fall back to the stale original model.
        return $this->hydrateCards(new EloquentCollection([$tour]))->first() ?? new Tour;
    }
}
