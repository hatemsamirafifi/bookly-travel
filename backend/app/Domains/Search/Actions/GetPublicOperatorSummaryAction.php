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
        $publicName = is_string($profile?->company_name) ? trim($profile->company_name) : '';
        if (! $profile || $publicName === '') {
            return null;
        }

        // Approximate scope is only a candidate prefilter. Final eligibility
        // uses the exact shared Tour::isPubliclyBookable() invariant (which
        // includes today) against eager-loaded availability collections, so
        // expired/fully-blocked/unpriced/unpublished tours never count and no
        // per-tour N+1 query is issued.
        $eligibleTourIds = Tour::bookable()
            ->where('partner_id', $partner->id)
            ->with(['availabilityRules', 'availabilityExceptions'])
            ->get()
            ->filter(fn (Tour $candidate) => $candidate->isPubliclyBookable())
            ->map(fn (Tour $candidate) => $candidate->id)
            ->values();

        if ($eligibleTourIds->isEmpty()) {
            return [
                'name' => $publicName,
                'description' => $profile->business_description,
                'logo_url' => $profile->logo_url,
                'verified' => true,
                'tour_count' => 0,
                'review_count' => 0,
                'average_rating' => null,
            ];
        }

        // Aggregate only reviews which can be shown on exact eligible tours.
        // No partner contact, tax, payout or account fields leave here.
        $stats = Review::query()
            ->whereIn('tour_id', $eligibleTourIds)
            ->whereIn('status', ['visible', 'flagged'])
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
            ->first();
        $reviewCount = (int) $stats?->getAttribute('review_count');

        return [
            'name' => $publicName,
            'description' => $profile->business_description,
            'logo_url' => $profile->logo_url,
            'verified' => true,
            'tour_count' => $eligibleTourIds->count(),
            'review_count' => $reviewCount,
            'average_rating' => $reviewCount > 0
                ? round((float) $stats->getAttribute('average_rating'), 2)
                : null,
        ];
    }
}
