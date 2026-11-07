<?php

namespace App\Domains\Reviews\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Flag a review for moderator attention (Admin 2.0, T-B03).
 *
 * Marks a visible review as `flagged` so it drops out of the public rating
 * aggregate while the moderation team investigates, without deleting it.
 * Writes a `flagged` ReviewAuditTrail row and an immutable `review.flag`
 * governance audit entry.
 */
class FlagReviewAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(Review $review, int $adminId, string $reason): Review
    {
        abort_unless($review->status === 'visible', 422, 'Only visible reviews can be flagged.');

        $maxLength = 500;
        $reasonText = mb_substr(trim($reason), 0, $maxLength);
        abort_if($reasonText === '', 422, 'A flag reason is required.');

        $actor = User::find($adminId);
        abort_unless($actor !== null && $actor->role === 'admin', 404, 'Admin actor not found.');

        $before = ['status' => $review->status];

        DB::transaction(function () use ($review, $adminId, $reasonText, $actor, $before) {
            $review->update(['status' => 'flagged']);

            ReviewAuditTrail::create([
                'review_id' => $review->id,
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'flag',
                'reason' => $reasonText,
                'created_at' => now(),
            ]);

            $this->audit->log(
                $actor,
                'review.flag',
                $review,
                $before,
                ['status' => 'flagged'],
                ['reason' => $reasonText],
            );
        });

        return $review;
    }
}
