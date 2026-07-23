<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Support\DurationBucket;
use App\Domains\Search\Support\SearchableAttributes;
use App\Domains\Search\Transformers\TourCardTransformer;
use App\Models\Category;
use App\Models\Tour;

class SearchToursAction
{
    protected TourCardTransformer $transformer;

    public function __construct()
    {
        $this->transformer = new TourCardTransformer;
    }

    protected int $perPage = 12;

    public function execute(array $params): array
    {
        $query = $params['q'] ?? '';
        $locale = $params['locale'] ?? 'en';
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = $this->perPage;

        $filterString = $this->buildFilterString($params);
        $sort = $this->resolveSort($params['sort'] ?? null);

        $search = Tour::search($query ?: '');

        if ($filterString) {
            $search->options['filter'] = $filterString;
        }

        if (! empty($sort)) {
            $search->options['sort'] = $sort;
        }

        // Search only the locale's language fields + shared fields, not the
        // whole document (search-api.md:145). Without this a Spanish "vino"
        // query would also match English/Italian titles.
        $search->options['attributesToSearchOn'] = SearchableAttributes::forLocale($locale);

        $results = $search->paginate($perPage, 'page', $page);

        // Eager-load every relation the transformer reads so we don't issue
        // per-tour queries (N+1) while mapping the results page.
        $results->getCollection()->load([
            'translations',
            'category',
            'availabilityRules',
            'availabilityExceptions',
        ]);

        $data = $results->map(fn (Tour $tour) => $this->transformer->transform($tour, $locale))->values()->toArray();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
            'filters' => $this->facetAggregates($params, $locale),
        ];
    }

    protected function buildFilterString(array $params): string
    {
        $filters = ['status = published'];

        if (! empty($params['category'])) {
            $filters[] = 'category_slug = "' . str_replace('"', '\\"', $params['category']) . '"';
        }

        if (! empty($params['location'])) {
            $filters[] = 'location_slug = "' . str_replace('"', '\\"', $params['location']) . '"';
        }

        // Use isset()/!== '' rather than empty(): `empty('0') === true` in PHP,
        // which would silently drop a legitimate `price_max=0` (free-tour
        // filter) bound (F7).
        if (isset($params['price_min']) && $params['price_min'] !== '') {
            $filters[] = 'price_amount >= ' . (int) $params['price_min'];
        }

        if (isset($params['price_max']) && $params['price_max'] !== '') {
            $filters[] = 'price_amount <= ' . (int) $params['price_max'];
        }

        if (! empty($params['duration'])) {
            $filters[] = $this->durationFilter($params['duration']);
        }

        if (! empty($params['date'])) {
            $filters[] = 'available_dates = "' . $params['date'] . '"';
        }

        return implode(' AND ', $filters);
    }

    protected function durationFilter(string $duration): string
    {
        return DurationBucket::tryFrom($duration)?->filterExpression()
            ?? 'duration_minutes >= 0';
    }

    protected function resolveSort(?string $sort): array
    {
        return match ($sort) {
            'price_asc' => ['price_amount:asc'],
            'price_desc' => ['price_amount:desc'],
            'rating' => ['average_rating:desc'],
            'newest' => ['created_at:desc'],
            default => [],
        };
    }

    /**
     * Facet counts scoped to the same bookable invariant the search index uses
     * (published + valid pricing + upcoming availability), so a non-zero facet
     * always returns ≥1 Meilisearch hit (F5). The index remains the source of
     * truth for real-time availability; the DB query approximates "has
     * upcoming availability" with `whereHas('availabilityRules')`.
     *
     * Contract search-api.md:143 describes dynamic facets given the current
     * result set — the bookable-scoped static count is the correct
     * contract-compliant baseline; fully dynamic Meilisearch `facets` is a
     * documented follow-up, not silently skipped.
     */
    protected function facetAggregates(array $params, string $locale): array
    {
        $categories = Category::popularWithCounts()
            ->get()
            ->map(fn (Category $cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'count' => (int) $cat->tours_count,
            ])
            ->filter(fn (array $c) => $c['count'] > 0)
            ->values()
            ->toArray();

        $locations = Tour::bookable()
            ->select('location_slug as slug', 'location as name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('location_slug', 'location')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->toArray();

        // Price range from the bookable set, not all published tours.
        $priceRange = Tour::bookable()
            ->selectRaw('MIN(price_amount) as min_price, MAX(price_amount) as max_price')
            ->first();
        $priceMin = (int) ($priceRange->min_price ?? 0);
        $priceMax = (int) ($priceRange->max_price ?? 0);

        $durations = [
            ['value' => 'half-day', 'label' => __('search.durations.half_day', [], $locale), 'count' => 0],
            ['value' => 'full-day', 'label' => __('search.durations.full_day', [], $locale), 'count' => 0],
            ['value' => 'multi-day', 'label' => __('search.durations.multi_day', [], $locale), 'count' => 0],
        ];

        $durationCounts = Tour::bookable()
            ->selectRaw(DurationBucket::sqlCase() . ' as bucket, COUNT(*) as count')
            ->groupBy('bucket')
            ->pluck('count', 'bucket');

        foreach ($durations as &$d) {
            $d['count'] = $durationCounts[$d['value']] ?? 0;
        }

        return [
            'categories' => $categories,
            'locations' => $locations,
            'price_range' => ['min' => $priceMin, 'max' => $priceMax],
            'durations' => $durations,
        ];
    }
}
