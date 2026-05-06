<?php

namespace App\Domains\Search\Actions;

use App\Models\Tour;
use App\Models\Category;

class SearchToursAction
{
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

        $results = $search->paginate($perPage, 'page', $page);

        $data = $results->map(fn (Tour $tour) => $this->toTourCard($tour, $locale))->values()->toArray();

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

    protected function toTourCard(Tour $tour, string $locale): array
    {
        $translation = $tour->translations()->where('locale', $locale)->first()
            ?? $tour->translations()->where('locale', 'en')->first();

        return [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'title' => $translation?->title ?? '',
            'location' => $tour->location,
            'category' => $tour->category?->name ?? '',
            'duration_label' => $tour->duration_label,
            'price' => [
                'amount' => $tour->lowestPriceAmount(),
                'currency' => $tour->currency(),
                'formatted' => $this->formatPrice($tour->lowestPriceAmount(), $tour->currency()),
            ],
            'rating' => [
                'average' => $tour->averageRating(),
                'count' => $tour->reviewCount(),
            ],
            'cover_image_url' => $tour->cover_image_url ?? '',
            'group_size' => [
                'min' => $tour->group_size_min,
                'max' => $tour->group_size_max,
            ],
            'next_available_date' => $this->nextAvailableDate($tour),
        ];
    }

    protected function nextAvailableDate(Tour $tour): ?string
    {
        $dates = $tour->upcomingAvailableDates();
        return $dates[0] ?? null;
    }

    protected function formatPrice(int $amount, string $currency): string
    {
        return match ($currency) {
            'EUR' => '€' . number_format($amount / 100, 2),
            'USD' => '$' . number_format($amount / 100, 2),
            default => number_format($amount / 100, 2) . ' ' . $currency,
        };
    }

    protected function buildFilterString(array $params): string
    {
        $filters = ['status = published'];

        if (! empty($params['category'])) {
            $filters[] = 'category_slug = ' . $params['category'];
        }

        if (! empty($params['location'])) {
            $filters[] = 'location_slug = ' . $params['location'];
        }

        if (! empty($params['price_min'])) {
            $filters[] = 'price_amount >= ' . (int) $params['price_min'];
        }

        if (! empty($params['price_max'])) {
            $filters[] = 'price_amount <= ' . (int) $params['price_max'];
        }

        if (! empty($params['duration'])) {
            $filters[] = $this->durationFilter($params['duration']);
        }

        if (! empty($params['date'])) {
            $filters[] = 'available_dates EXISTS ' . $params['date'];
        }

        return implode(' AND ', $filters);
    }

    protected function durationFilter(string $duration): string
    {
        return match ($duration) {
            'half-day' => 'duration_minutes <= 240',
            'full-day' => 'duration_minutes 241 TO 480',
            'multi-day' => 'duration_minutes > 480',
            default => 'duration_minutes >= 0',
        };
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

    protected function facetAggregates(array $params, string $locale): array
    {
        $publishedQuery = Tour::where('status', 'published');

        $categories = Category::where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Category $cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'count' => $cat->publishedTourCount(),
            ])
            ->filter(fn (array $c) => $c['count'] > 0)
            ->values()
            ->toArray();

        $locations = Tour::where('status', 'published')
            ->select('location_slug as slug', 'location as name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('location_slug', 'location')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->toArray();

        // Calculate price range from published tours
        $priceStats = Tour::where('status', 'published')->get();
        $priceMin = 0;
        $priceMax = 0;
        if ($priceStats->isNotEmpty()) {
            $amounts = $priceStats->map(fn (Tour $t) => $t->lowestPriceAmount())->filter(fn (int $a) => $a > 0);
            $priceMin = $amounts->isEmpty() ? 0 : $amounts->min();
            $priceMax = $amounts->isEmpty() ? 0 : $amounts->max();
        }

        $durations = [
            ['value' => 'half-day', 'label' => __('search.durations.half_day', [], $locale), 'count' => 0],
            ['value' => 'full-day', 'label' => __('search.durations.full_day', [], $locale), 'count' => 0],
            ['value' => 'multi-day', 'label' => __('search.durations.multi_day', [], $locale), 'count' => 0],
        ];

        // Populate duration counts from the published query snapshot
        $durationCounts = Tour::where('status', 'published')
            ->selectRaw("
                CASE
                    WHEN duration_minutes <= 240 THEN 'half-day'
                    WHEN duration_minutes <= 480 THEN 'full-day'
                    ELSE 'multi-day'
                END as bucket,
                COUNT(*) as count
            ")
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
