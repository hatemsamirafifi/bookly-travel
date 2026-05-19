<?php

namespace App\Domains\Reviews\Actions;

use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EditReviewAction
{
    public function execute(Review $review, int $rating, ?string $comment, User $traveler): Review
    {
        if ($review->traveler_id !== $traveler->id) {
            throw new HttpException(403, 'You can only edit your own reviews.');
        }

        if (! $review->canEdit()) {
            throw new HttpException(403, 'The 48-hour edit window has closed.');
        }

        $oldRating = $review->rating;
        $oldComment = $review->comment;

        $review->update([
            'rating' => $rating,
            'comment' => $comment,
            'edited_at' => now(),
        ]);

        ReviewAuditTrail::create([
            'review_id' => $review->id,
            'actor_type' => 'traveler',
            'actor_id' => $traveler->id,
            'action' => 'edit',
            'old_rating' => $oldRating,
            'new_rating' => $rating,
            'old_comment' => $oldComment,
            'new_comment' => $comment,
        ]);

        ReviewSubmitted::dispatch($review);

        return $review;
    }
}
