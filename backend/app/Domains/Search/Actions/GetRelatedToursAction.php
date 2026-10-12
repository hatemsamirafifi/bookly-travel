<?php

namespace App\Domains\Search\Actions;

use App\Domains\Reviews\Models\Review;
use App\Domains\Search\Transformers\TourCardTransformer;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetRelatedToursAction
{
    private const DEFAULT_LIMIT = 4;

    private const ABSOLUTE_CEILING = 8;

    private const CANDIDATE_PAGE_SIZE = 32;

    public function __construct(private readonly TourCardTransformer $transformer) {}

    public function execute(Tour $tour, string $locale, int $limit = self::DEFAULT_LIMIT): array
    {
        // Internal clamp only: four by default, never beyond eight. There is
        // no public limit parameter; callers pass nothing or an internal
        // value which is still bounded by the absolute ceiling.
        $limit = max(1, min($limit, self::ABSOLUTE_CEILING));

        if (! $tour->location_slug && ! $tour->category_id) {
            return [];
        }

        $eligible = collect();
        $offset = 0;

        // Scan bounded candidate pages in genuine-stats SQL order until the
        // requested eligible cards are found or candidates are exhausted. A
        // fixed 32-row cap would hide valid tours behind ineligible ones.
        do {
            $page = $this->candidatePage($tour, $offset, self::CANDIDATE_PAGE_SIZE);

            foreach ($page as $candidate) {
                if ($candidate->isPubliclyBookable()) {
                    // Project the same genuine facts used for ordering without
                    // changing stored caches or the shared card transformer.
                    $reviewCount = (int) $candidate->getAttribute('genuine_review_count');
                    $candidate->setAttribute('review_count', $reviewCount);
                    $candidate->setAttribute('average_rating', $reviewCount > 0
                        ? round((float) $candidate->getAttribute('genuine_average_rating'), 2)
                        : 0.0);
                    $eligible->push($candidate);

                    if ($eligible->count() >= $limit) {
                        break 2;
                    }
                }
            }

            $offset += self::CANDIDATE_PAGE_SIZE;
        } while ($page->count() === self::CANDIDATE_PAGE_SIZE);

        return $eligible
            ->map(fn (Tour $candidate) => $this->transformer->transform($candidate, $locale))
            ->values()
            ->all();
    }

    /**
     * One deterministically ordered candidate page. Ordering applies genuine
     * visible/flagged review aggregates (per-query aliases, no cached-column
     * ranking, no persistent writes) BEFORE paging: destination matches,
     * then category matches, then higher genuine average, then higher
     * genuine review count, then stable ID.
     *
     * @return Collection<int, Tour>
     */
    private function candidatePage(Tour $tour, int $offset, int $pageSize): Collection
    {
        $genuineStats = Review::query()
            ->whereIn('status', ['visible', 'flagged'])
            ->select('tour_id')
            ->selectRaw('COUNT(*) as genuine_review_count')
            ->selectRaw('AVG(rating) as genuine_average_rating')
            ->groupBy('tour_id');

        return Tour::bookable()
            ->select(['tours.*', 'genuine_stats.genuine_review_count', 'genuine_stats.genuine_average_rating'])
            ->whereKeyNot($tour->id)
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', 'en'))
            ->where(function (Builder $query) use ($tour): void {
                if ($tour->location_slug) {
                    $query->where('tours.location_slug', $tour->location_slug);
                }
                if ($tour->category_id) {
                    $tour->location_slug
                        ? $query->orWhere('tours.category_id', $tour->category_id)
                        : $query->where('tours.category_id', $tour->category_id);
                }
            })
            ->leftJoinSub($genuineStats, 'genuine_stats', 'tours.id', '=', 'genuine_stats.tour_id')
            ->with(['translations', 'translationStates', 'category', 'media', 'availabilityRules', 'availabilityExceptions'])
            ->orderByRaw('CASE WHEN tours.location_slug = ? THEN 0 ELSE 1 END', [$tour->location_slug])
            ->orderByRaw('CASE WHEN tours.category_id = ? THEN 0 ELSE 1 END', [$tour->category_id])
            ->orderByRaw('CASE WHEN genuine_stats.genuine_review_count > 0 THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN genuine_stats.genuine_average_rating IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('genuine_stats.genuine_average_rating')
            ->orderByRaw('COALESCE(genuine_stats.genuine_review_count, 0) DESC')
            ->orderBy('tours.id')
            ->offset($offset)
            ->limit($pageSize)
            ->get();
    }
}
