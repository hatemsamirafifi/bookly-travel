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
        $featuredTours = $this->fetchBookableTours($locale, featuredOnly: true);

        // Fallback to latest bookable tours if none are flagged featured.
        if (empty($featuredTours)) {
            $featuredTours = $this->fetchBookableTours($locale, featuredOnly: false);
        }

        $popularCategories = Category::popularWithCounts()
            ->get()
            ->map(fn (Category $cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
                'description' => $cat->description,
                'image_url' => TourCardTransformer::formatImageUrl($cat->image_url),
                'tour_count' => (int) $cat->getAttribute('tours_count'),
            ])
            ->values()
            ->toArray();

        $featuredDestinations = DestinationAggregator::popular(6);
        $curatedRails = $this->getCuratedRails($locale);

        return [
            'data' => [
                'featured_tours' => $featuredTours,
                'popular_categories' => $popularCategories,
                'featured_destinations' => $featuredDestinations,
                'curated_rails' => $curatedRails,
            ],
            'meta' => [
                'seo' => [
                    'meta_title' => __('seo.homepage.title', [], $locale) ?: 'Bookly — Discover & Book Amazing Tours',
                    'meta_description' => __('seo.homepage.description', [], $locale) ?: 'Discover and instantly book the best tours worldwide. Browse thousands of experiences across top destinations.',
                ],
            ],
        ];
    }

    /**
     * Fetch bookable tours transformed for the given locale.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchBookableTours(string $locale, bool $featuredOnly = false, int $limit = 8): array
    {
        $query = Tour::bookable()
            ->whereHas('translations', fn ($q) => $q
                ->where('locale', $locale)
                ->orWhereRaw('tour_translations.locale = COALESCE(tours.source_locale, ?)', [Tour::DEFAULT_SOURCE_LOCALE]))
            ->with(['translations', 'translationStates', 'category', 'availabilityRules', 'availabilityExceptions', 'options'])
            ->latest()
            ->limit($limit);

        if ($featuredOnly) {
            $query->where('is_featured', true);
        }

        return $query->get()
            ->map(fn (Tour $tour) => $this->transformer->transform($tour, $locale))
            ->values()
            ->toArray();
    }

    /**
     * Aggregate themed and seasonal curated rails (FR-001, FR-007).
     * Omit any rail that has zero bookable tours.
     *
     * @return array<int, array{id: string, slug: string, title: string, tours: array}>
     */
    protected function getCuratedRails(string $locale): array
    {
        $themeDefinitions = [
            [
                'id' => 'outdoor-adventures',
                'slug' => 'outdoor-adventures',
                'title' => 'Adventures & Outdoor Activities',
                'category_slugs' => ['beach-water', 'nature-wildlife', 'outdoor-adventures', 'adventure'],
            ],
            [
                'id' => 'culture-heritage',
                'slug' => 'culture-heritage',
                'title' => 'Culture & Historic Highlights',
                'category_slugs' => ['museums-art', 'culture', 'historical'],
            ],
            [
                'id' => 'food-drink',
                'slug' => 'food-drink',
                'title' => 'Culinary & Tasting Experiences',
                'category_slugs' => ['food', 'food-and-drink'],
            ],
        ];

        $rails = [];

        foreach ($themeDefinitions as $theme) {
            $tours = Tour::bookable()
                ->whereHas('category', fn ($q) => $q->whereIn('slug', $theme['category_slugs']))
                ->whereHas('translations', fn ($q) => $q
                    ->where('locale', $locale)
                    ->orWhereRaw('tour_translations.locale = COALESCE(tours.source_locale, ?)', [Tour::DEFAULT_SOURCE_LOCALE]))
                ->with(['translations', 'translationStates', 'category', 'availabilityRules', 'availabilityExceptions', 'options'])
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Tour $tour) => $this->transformer->transform($tour, $locale))
                ->values()
                ->toArray();

            if (! empty($tours)) {
                $translationKey = "homepage.rails.{$theme['id']}";
                $translatedTitle = __($translationKey, [], $locale);
                $rails[] = [
                    'id' => $theme['id'],
                    'slug' => $theme['slug'],
                    'title' => $translatedTitle !== $translationKey ? $translatedTitle : $theme['title'],
                    'tours' => $tours,
                ];
            }
        }

        return $rails;
    }
}
