<?php

namespace App\Domains\Search\Actions;

use App\Models\Tour;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetTourDetailAction
{
    public function execute(string $slug, string $locale): array
    {
        $tour = Tour::where('slug', $slug)->first();

        if (! $tour) {
            throw new HttpResponseException(
                response()->json(['message' => 'Tour not found.'], 404)
            );
        }

        if (in_array($tour->status, ['draft', 'pending_review', 'rejected', 'archived'])) {
            $code = $tour->status === 'archived' ? 410 : 404;
            $message = $tour->status === 'archived'
                ? 'This tour is no longer available.'
                : 'Tour not found.';

            throw new HttpResponseException(
                response()->json(['message' => $message], $code)
            );
        }

        $translation = $tour->translations()->where('locale', $locale)->first();
        $fallback = $translation ? null : $tour->translations()->where('locale', 'en')->first();
        $t = $translation ?? $fallback;

        $images = $tour->allImageUrls();
        $imageObjects = [];
        foreach ($images as $i => $url) {
            $imageObjects[] = [
                'url' => $url,
                'is_cover' => $i === 0,
                'alt' => $t?->title ?? '',
            ];
        }

        $availableDates = $tour->upcomingAvailableDates();

        $data = [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'title' => $t?->title ?? '',
            'description' => $t?->description ?? '',
            'highlights' => $t?->highlights ?? [],
            'inclusions' => $t?->inclusions ?? [],
            'exclusions' => $t?->exclusions ?? [],
            'location' => $tour->location,
            'meeting_point' => $t?->meeting_point ?? '',
            'category' => [
                'slug' => $tour->category?->slug ?? '',
                'name' => $tour->category?->name ?? '',
            ],
            'duration' => [
                'minutes' => $tour->duration_minutes,
                'label' => $tour->duration_label,
            ],
            'languages' => $tour->availableLanguages(),
            'group_size' => [
                'min' => $tour->group_size_min,
                'max' => $tour->group_size_max,
            ],
            'cancellation_policy' => $t?->cancellation_policy ?? '',
            'images' => $imageObjects,
            'pricing' => [
                'base_price' => [
                    'amount' => $tour->lowestPriceAmount(),
                    'currency' => $tour->currency(),
                    'formatted' => Tour::formatPrice($tour->lowestPriceAmount(), $tour->currency()),
                ],
                'tiered_pricing' => null,
            ],
            'availability' => [
                'next_available_date' => $availableDates[0] ?? null,
                'available_dates' => $availableDates,
            ],
            'reviews' => [
                'average_rating' => $tour->averageRating(),
                'count' => $tour->reviewCount(),
                'distribution' => ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0],
            ],
            'seo' => $this->buildSeoMetadata($tour, $t, $locale),
        ];

        if (! $translation && $fallback) {
            $data['translation_warning'] = 'partial_translation';
        }

        return ['data' => $data];
    }

    protected function buildSeoMetadata(Tour $tour, $translation, string $locale): array
    {
        $title = $translation?->title ?? '';
        $desc = $translation?->description ?? '';
        $baseUrl = config('app.url', 'https://bookly.com');
        $canonical = "{$baseUrl}/{$locale}/tours/{$tour->slug}";

        $hreflang = [];
        foreach (['en', 'es', 'it'] as $lang) {
            $hreflang[$lang] = "{$baseUrl}/{$lang}/tours/{$tour->slug}";
        }

        $metaTitle = $title ? "{$title} | Bookly" : 'Tour Details | Bookly';
        $metaDesc = $desc ? substr($desc, 0, 160) : 'View tour details and book your next adventure with Bookly.';

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDesc,
            'canonical_url' => $canonical,
            'hreflang' => $hreflang,
        ];
    }
}
