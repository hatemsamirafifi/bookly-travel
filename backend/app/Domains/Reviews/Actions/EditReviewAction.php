<?php

namespace App\Domains\Reviews\Actions;

use App\Domains\Reviews\Events\ReviewFlagged;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Domains\Reviews\Services\ProfanityFilterService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EditReviewAction
{
    public function __construct(
        private readonly ProfanityFilterService $profanityFilter,
    ) {}

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

        // FR-014: edited content is subject to the same profanity moderation as
        // submissions. The status is derived from the scanned comment, mirroring
        // SubmitReviewAction (the profanity filter is an automated flag, not an
        // admin moderation transition, so it bypasses canTransitionTo).
        // A `hidden` review is admin-suppressed and must NOT be un-hidden by a
        // traveler edit, so its status is preserved as-is.
        $matchedKeywords = $this->profanityFilter->scan($comment);

        $newStatus = $review->status === 'hidden'
            ? 'hidden'
            : (! empty($matchedKeywords) ? 'flagged' : 'visible');

        DB::transaction(function () use ($review, $rating, $comment, $traveler, $oldRating, $oldComment, $newStatus) {
            $review->update([
                'rating' => $rating,
                'comment' => $comment,
                'status' => $newStatus,
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
        });

        // Always recompute the tour aggregate (rating may have changed).
        ReviewSubmitted::dispatch($review);

        if ($newStatus === 'flagged') {
            ReviewFlagged::dispatch($review, $matchedKeywords);
        }

        return $review;
    }
}
