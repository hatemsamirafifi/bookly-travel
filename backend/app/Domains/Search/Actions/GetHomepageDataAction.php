<?php

namespace App\Domains\Search\Actions;

use App\Models\Tour;
use App\Models\Category;

class GetHomepageDataAction
{
    public function execute(string $locale): array
    {
        $featuredTours = Tour::where('status', 'published')
            ->where('is_featured', true)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->orWhere('locale', 'en'))
            ->with(['translations', 'category'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Tour $tour) => $this->toTourCard($tour, $locale))
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
                ->map(fn (Tour $tour) => $this->toTourCard($tour, $locale))
                ->values()
                ->toArray();
        }

        $popularCategories = Category::where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Category $cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'description' => $cat->description,
                'image_url' => $cat->image_url,
                'tour_count' => $cat->publishedTourCount(),
            ])
            ->values()
            ->toArray();

        $featuredDestinations = Tour::where('status', 'published')
            ->select('location_slug as slug', 'location as name')
            ->selectRaw("
                SPLIT_PART(location, ',', -1) as country,
                COUNT(*) as tour_count
            ")
            ->groupBy('location_slug', 'location')
            ->orderByDesc('tour_count')
            ->limit(6)
            ->get()
            ->map(fn ($d) => [
                'slug' => $d->slug,
                'name' => $d->name,
                'country' => trim($d->country),
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
            'next_available_date' => null,
        ];
    }

    protected function formatPrice(int $amount, string $currency): string
    {
        return match ($currency) {
            'EUR' => '€' . number_format($amount / 100, 2),
            'USD' => '$' . number_format($amount / 100, 2),
            default => number_format($amount / 100, 2) . ' ' . $currency,
        };
    }
}
