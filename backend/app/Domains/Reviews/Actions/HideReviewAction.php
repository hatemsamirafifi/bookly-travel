<?php

namespace App\Domains\Reviews\Actions;

use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;

class HideReviewAction
{
    public function execute(Review $review, int $adminId, string $reason): Review
    {
        $review->update(['status' => 'hidden']);

        ReviewAuditTrail::create([
            'review_id' => $review->id,
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'action' => 'hide',
            'reason' => $reason,
            'created_at' => now(),
        ]);

        event(new ReviewSubmitted($review));

        return $review;
    }
}
