<?php

namespace App\Domains\Search\Actions;

use App\Domains\Reviews\Models\Review;
use App\Models\Tour;

class GetPublicOperatorSummaryAction
{
    public function execute(Tour $tour): ?array
    {
        $partner = $tour->partnerRecord;
        if (! $partner || ! $partner->is_active || $partner->onboarding_status !== 'approved') {
            return null;
        }

        $profile = $partner->profile;
        if (! $profile || ! $profile->company_name) {
            return null;
        }

        $publicTourIds = Tour::bookable()
            ->where('partner_id', $partner->id)
            ->pluck('id');

        // Aggregate only reviews which can be shown on publicly bookable
        // tours. No partner contact, tax, payout or account fields leave here.
        $stats = Review::query()
            ->whereIn('tour_id', $publicTourIds)
            ->whereIn('status', ['visible', 'flagged'])
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
            ->first();
        $reviewCount = (int) $stats?->getAttribute('review_count');

        return [
            'name' => $profile->company_name,
            'description' => $profile->business_description,
            'logo_url' => $profile->logo_url,
            'tour_count' => $publicTourIds->count(),
            'review_count' => $reviewCount,
            'average_rating' => $reviewCount > 0
                ? round((float) $stats->getAttribute('average_rating'), 2)
                : null,
        ];
    }
}
