<?php

namespace App\Domains\Search\Transformers;

use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Tour;

class TourCardTransformer
{
    public function transform(Tour $tour, string $locale): array
    {
        $tour->loadMissing('translations');
        $translation = app(TourTranslationService::class)->currentTranslation($tour, $locale)
            ?? $tour->sourceTranslation();
        $priceAmount = $tour->lowestPriceAmount();

        return [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'title' => optional($translation)->title ?? '',
            'location' => $tour->location,
            'category' => optional($tour->category)->name ?? '',
            'duration_label' => $tour->duration_label,
            'price' => [
                'amount' => $priceAmount,
                'currency' => $tour->currency(),
                'formatted' => Tour::formatPrice($priceAmount, $tour->currency()),
            ],
            'rating' => [
                'average' => $tour->averageRating(),
                'count' => $tour->reviewCount(),
            ],
            'cover_image_url' => self::formatImageUrl($tour->cover_image_url),
            'group_size' => [
                'min' => $tour->group_size_min,
                'max' => $tour->group_size_max,
            ],
            'next_available_date' => $this->resolveNextAvailableDate($tour),
        ];
    }

    protected function resolveNextAvailableDate(Tour $tour): ?string
    {
        $dates = $tour->upcomingAvailableDates();

        // The UI renders this as "Next: {date}" and the booking form uses it
        // as the deep-link date. CreateBookingAction rejects today/past dates,
        // so only advertise a strictly-future date as "next available".
        $tomorrow = now()->addDay()->toDateString();

        foreach ($dates as $date) {
            if ($date >= $tomorrow) {
                return $date;
            }
        }

        return null;
    }

    public static function formatImageUrl(?string $url): string
    {
        if (blank($url)) {
            return '';
        }

        if (preg_match('#^https?://[^/]+/storage/(.*)$#i', $url, $matches)) {
            return '/storage/' . ltrim($matches[1], '/');
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) {
            return $url;
        }

        return '/storage/' . ltrim($url, '/');
    }
}
