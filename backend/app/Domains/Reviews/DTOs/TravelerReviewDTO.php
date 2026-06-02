<?php

namespace App\Domains\Reviews\DTOs;

use App\Domains\Reviews\Models\Review;

class TravelerReviewDTO
{
    public static function fromReview(Review $review): array
    {
        $tour = $review->tour;
        $translation = null;

        if ($tour->relationLoaded('translations')) {
            $translation = $tour->translations->firstWhere('locale', $review->locale ?? 'en')
                ?? $tour->translations->firstWhere('locale', 'en');
        }

        return [
            'id' => $review->id,
            'tour' => [
                'id' => $tour->id,
                'name' => $translation?->title ?? $tour->slug,
                'slug' => $tour->slug,
            ],
            'rating' => $review->rating,
            'text' => $review->comment,
            'submitted_at' => $review->created_at?->toIso8601String(),
            'can_edit' => $review->canEdit(),
        ];
    }
}
