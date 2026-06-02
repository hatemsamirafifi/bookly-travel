<?php

namespace App\Domains\Wishlist\DTOs;

use App\Domains\Wishlist\Models\Wishlist;

class WishlistItemDTO
{
    public static function fromWishlist(Wishlist $wishlist, string $locale = 'en'): array
    {
        $tour = $wishlist->tour;
        $translation = null;

        if ($tour->relationLoaded('translations')) {
            $translation = $tour->translations->firstWhere('locale', $locale)
                ?? $tour->translations->firstWhere('locale', 'en');
        }

        return [
            'id' => $wishlist->id,
            'tour' => [
                'id' => $tour->id,
                'name' => $translation?->title ?? $tour->slug,
                'cover_image' => $tour->cover_image_url,
                'slug' => $tour->slug,
                'price' => $tour->lowestPriceAmount(),
                'rating' => $tour->averageRating(),
                'review_count' => $tour->reviewCount(),
                'location' => $tour->location,
                'duration' => $tour->duration_label,
                'is_available' => $tour->status === 'published' && $tour->hasUpcomingAvailability(),
            ],
            'added_at' => $wishlist->created_at?->toIso8601String(),
        ];
    }
}
