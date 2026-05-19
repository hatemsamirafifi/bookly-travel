<?php

namespace App\Domains\Reviews\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Reviews\Events\ReviewFlagged;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Domains\Reviews\Services\ProfanityFilterService;
use App\Domains\Reviews\Services\ReviewValidationService;
use App\Models\User;

class SubmitReviewAction
{
    public function __construct(
        private readonly ReviewValidationService $validationService,
        private readonly ProfanityFilterService $profanityFilter,
    ) {}

    public function execute(string $bookingReference, int $rating, ?string $comment, string $locale, User $traveler): Review
    {
        $booking = Booking::where('reference', $bookingReference)->firstOrFail();

        $this->validationService->validate($booking, $traveler);

        $matchedKeywords = $this->profanityFilter->scan($comment);

        $review = Review::create([
            'booking_id' => $booking->id,
            'tour_id' => $booking->tour_id,
            'traveler_id' => $traveler->id,
            'rating' => $rating,
            'comment' => $comment,
            'status' => ! empty($matchedKeywords) ? 'flagged' : 'visible',
            'locale' => $locale,
        ]);

        ReviewAuditTrail::create([
            'review_id' => $review->id,
            'actor_type' => 'traveler',
            'actor_id' => $traveler->id,
            'action' => 'submit',
            'new_rating' => $rating,
            'new_comment' => $comment,
        ]);

        ReviewSubmitted::dispatch($review);

        if (! empty($matchedKeywords)) {
            ReviewFlagged::dispatch($review, $matchedKeywords);
        }

        return $review;
    }
}
