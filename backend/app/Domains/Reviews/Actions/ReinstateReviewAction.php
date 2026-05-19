<?php

namespace App\Domains\Reviews\Actions;

use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;

class ReinstateReviewAction
{
    public function execute(Review $review, int $adminId, string $reason): Review
    {
        $review->update(['status' => 'visible']);

        ReviewAuditTrail::create([
            'review_id' => $review->id,
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'action' => 'reinstate',
            'reason' => $reason,
            'created_at' => now(),
        ]);

        event(new ReviewSubmitted($review));

        return $review;
    }
}
