<?php

namespace App\Domains\Reviews\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HideReviewAction
{
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    public function execute(Review $review, int $adminId, string $reason): Review
    {
        abort_unless($review->canTransitionTo('hidden'), 422, 'Review cannot be hidden from its current state.');

        $actor = User::find($adminId);
        abort_unless($actor !== null, 404, 'Admin actor not found.');

        $before = ['status' => $review->status];

        DB::transaction(function () use ($review, $adminId, $reason, $actor, $before) {
            $review->update(['status' => 'hidden']);

            ReviewAuditTrail::create([
                'review_id' => $review->id,
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'hide',
                'reason' => $reason,
                'created_at' => now(),
            ]);

            // Unified governance audit (Spec 013): actor resolves to the User morph
            // map (admin => User), fixing the legacy actor_type='admin' string.
            $this->audit->log(
                $actor,
                'review.hide',
                $review,
                $before,
                ['status' => 'hidden'],
                ['reason' => $reason],
            );
        });

        event(new ReviewSubmitted($review));

        return $review;
    }
}