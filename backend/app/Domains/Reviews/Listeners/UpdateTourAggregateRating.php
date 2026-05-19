<?php

namespace App\Domains\Reviews\Listeners;

use App\Domains\Reviews\Events\ReviewFlagged;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Models\Tour;

class UpdateTourAggregateRating
{
    public function handle(ReviewSubmitted|ReviewFlagged $event): void
    {
        $tourId = $event->review->tour_id;

        $stats = Review::where('tour_id', $tourId)
            ->whereIn('status', ['visible', 'flagged'])
            ->selectRaw('COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as count')
            ->first();

        Tour::where('id', $tourId)->update([
            'average_rating' => $stats->count > 0 ? round($stats->avg_rating, 2) : null,
            'review_count' => $stats->count,
        ]);
    }
}
