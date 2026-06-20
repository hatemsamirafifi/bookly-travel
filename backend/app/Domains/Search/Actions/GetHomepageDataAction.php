<?php

namespace App\Domains\Search\Actions;

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
        $featuredTours = Tour::where('status', 'published')
            ->where('is_featured', true)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->orWhere('locale', 'en'))
            ->with(['translations', 'category'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Tour $tour) => $this->transformer->transform($tour, $locale))
            ->values()
            ->toArray();

        // Fallback to latest published if no featured tours
        if (empty($featuredTours)) {
            $featuredTours = Tour::where('status', 'published')
                ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->orWhere('locale', 'en'))
                ->with(['translations', 'category'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Tour $tour) => $this->transformer->transform($tour, $locale))
                ->values()
                ->toArray();
        }

        $popularCategories = Category::where('is_active', true)
            ->orderBy('display_order')
            ->withCount(['tours' => fn ($q) => $q->where('status', 'published')])
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

        $featuredDestinations = Tour::where('status', 'published')
            ->select('location_slug as slug', 'location as name')
            ->selectRaw('COUNT(*) as tour_count')
            ->groupBy('location_slug', 'location')
            ->orderByDesc('tour_count')
            ->limit(6)
            ->get()
            ->map(fn ($d) => [
                'slug' => $d->slug,
                'name' => $d->name,
                'country' => trim((string) str($d->name)->afterLast(',')),
                'image_url' => null,
                'tour_count' => (int) $d->tour_count,
                'is_featured' => true,
            ])
            ->values()
            ->toArray();

        return [
            'featured_tours' => $featuredTours,
            'popular_categories' => $popularCategories,
            'featured_destinations' => $featuredDestinations,
            'meta' => [
                'seo' => [
                    'meta_title' => __('seo.homepage.title', [], $locale) ?: 'Bookly — Discover & Book Amazing Tours',
                    'meta_description' => __('seo.homepage.description', [], $locale) ?: 'Discover and instantly book the best tours worldwide. Browse thousands of experiences across top destinations.',
                ],
            ],
        ];
    }
}
