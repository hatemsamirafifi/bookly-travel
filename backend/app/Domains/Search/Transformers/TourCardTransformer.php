<?php

namespace App\Domains\Search\Transformers;

use App\Models\Tour;

class TourCardTransformer
{
    public function transform(Tour $tour, string $locale): array
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
                'formatted' => Tour::formatPrice($tour->lowestPriceAmount(), $tour->currency()),
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
            'next_available_date' => $this->resolveNextAvailableDate($tour),
        ];
    }

    protected function resolveNextAvailableDate(Tour $tour): ?string
    {
        $dates = $tour->upcomingAvailableDates();

        return $dates[0] ?? null;
    }
}
