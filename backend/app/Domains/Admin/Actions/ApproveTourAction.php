<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Publish (approve) a tour (Spec 013, FR-003/FR-005).
 *
 * Asserts the Tour::canTransitionTo() guard — publishing is blocked unless the
 * owning partner is approved (FR-005) and the tour is in a submittable state.
 * Writes an immutable `tour.publish` governance audit entry.
 */
class ApproveTourAction
{
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    public function execute(User $actor, Tour $tour): Tour
    {
        abort_unless(
            $tour->canTransitionTo(TourStatus::Published),
            422,
            'Tour cannot be published from its current state, or the owning partner is not approved.',
        );

        $before = ['status' => $tour->status];

        return DB::transaction(function () use ($actor, $tour, $before) {
            $tour->update(['status' => TourStatus::Published->value]);
            $tour->refresh();

            $this->audit->log($actor, 'tour.publish', $tour, $before, ['status' => $tour->status]);

            return $tour;
        });
    }
}