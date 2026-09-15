<?php

namespace App\Http\Resources\Concerns;

use App\Domains\Reviews\Models\Review;

/**
 * Shared first-name-only formatter for review author names (FR-004).
 *
 * Used by both the public ReviewResource and the PartnerReviewResource so the
 * two endpoints expose the same anonymization-safe name shape ("Anonymous
 * Traveler" when the traveler relation is missing).
 */
trait FormatsReviewerName
{
    protected function reviewerName(): string
    {
        $review = $this->resource;
        if (! $review instanceof Review || ! $review->traveler) {
            return 'Anonymous Traveler';
        }

        $name = $review->traveler->name ?? '';

        // First name only (FR-004). strtok returns the first token of the name
        // or false on an empty/whitespace string; fall back to the full name,
        // then to the anonymization placeholder.
        return strtok($name, ' ') ?: $name ?: 'Anonymous Traveler';
    }
}
