<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Transformers\TourCardTransformer;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;

class GetRelatedToursAction
{
    public function __construct(private readonly TourCardTransformer $transformer) {}

    public function execute(Tour $tour, string $locale): array
    {
        if (! $tour->location_slug && ! $tour->category_id) {
            return [];
        }

        $candidates = Tour::bookable()
            ->whereKeyNot($tour->id)
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', 'en'))
            ->where(function (Builder $query) use ($tour): void {
                if ($tour->location_slug) {
                    $query->where('location_slug', $tour->location_slug);
                }
                if ($tour->category_id) {
                    $tour->location_slug
                        ? $query->orWhere('category_id', $tour->category_id)
                        : $query->where('category_id', $tour->category_id);
                }
            })
            ->with(['translations', 'translationStates', 'category', 'media', 'availabilityRules', 'availabilityExceptions'])
            ->orderByRaw('CASE WHEN location_slug = ? THEN 0 ELSE 1 END', [$tour->location_slug])
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$tour->category_id])
            ->orderByRaw('CASE WHEN review_count > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('average_rating')
            ->orderByDesc('review_count')
            ->orderBy('id')
            ->limit(32)
            ->get();

        return $candidates
            ->filter(fn (Tour $candidate) => $candidate->isPubliclyBookable())
            ->take(4)
            ->map(fn (Tour $candidate) => $this->transformer->transform($candidate, $locale))
            ->values()
            ->all();
    }
}
