<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Support\DestinationAggregator;
use App\Domains\Search\Transformers\TourCardTransformer;
use App\Models\Category;
use App\Models\Tour;

class GetHomepageDataAction
{
    protected TourCardTransformer $transformer;

    public function __construct()
    {
        $this->transformer = new TourCardTransformer;
    }

    public function execute(string $locale): array
    {
        $featuredTours = Tour::bookable()
            ->where('is_featured', true)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->orWhere('locale', 'en'))
            ->with(['translations', 'category', 'availabilityRules', 'availabilityExceptions'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Tour $tour) => $this->transformer->transform($tour, $locale))
            ->values()
            ->toArray();

        // Fallback to latest bookable tours if none are flagged featured.
        if (empty($featuredTours)) {
            $featuredTours = Tour::bookable()
                ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->orWhere('locale', 'en'))
                ->with(['translations', 'category', 'availabilityRules', 'availabilityExceptions'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Tour $tour) => $this->transformer->transform($tour, $locale))
                ->values()
                ->toArray();
        }

        $popularCategories = Category::popularWithCounts()
            ->get()
            ->map(fn (Category $cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'description' => $cat->description,
                'image_url' => $cat->image_url,
                'tour_count' => (int) $cat->tours_count,
            ])
            ->values()
            ->toArray();

        $featuredDestinations = DestinationAggregator::popular(6);

        return [
            'data' => [
                'featured_tours' => $featuredTours,
                'popular_categories' => $popularCategories,
                'featured_destinations' => $featuredDestinations,
            ],
            'meta' => [
                'seo' => [
                    'meta_title' => __('seo.homepage.title', [], $locale) ?: 'Bookly — Discover & Book Amazing Tours',
                    'meta_description' => __('seo.homepage.description', [], $locale) ?: 'Discover and instantly book the best tours worldwide. Browse thousands of experiences across top destinations.',
                ],
            ],
        ];
    }
}
