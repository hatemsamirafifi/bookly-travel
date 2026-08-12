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
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(User $actor, Tour $tour): Tour
    {
        return DB::transaction(function () use ($actor, $tour) {
            $locked = Tour::lockForUpdate()->find($tour->id) ?? $tour;

            abort_unless(
                $locked->canTransitionTo(TourStatus::Published),
                422,
                'Tour cannot be published from its current state, or the owning partner is not approved.',
            );

            $before = ['status' => $locked->status];

            // Stamp the first-publication timestamp the first time a tour is
            // published. The tour-detail endpoint uses a non-null `published_at`
            // to decide 410 (was once public) vs 404 (never was) for archived
            // tours (tour-detail-api.md:107-114). Preserve an existing stamp on
            // re-publish after archive.
            $locked->update([
                'status' => TourStatus::Published->value,
                'published_at' => $locked->published_at ?? now(),
            ]);
            $locked->refresh();

            $this->audit->log($actor, 'tour.publish', $locked, $before, ['status' => $locked->status]);

            return $locked;
        });
    }
}
