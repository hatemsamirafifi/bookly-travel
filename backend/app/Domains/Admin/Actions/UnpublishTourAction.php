<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Unpublish a live tour (Spec 013, FR-004).
 *
 * Moves a published tour back to draft (removing it from public discovery) and
 * writes an immutable `tour.unpublish` governance audit entry.
 */
class UnpublishTourAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(User $actor, Tour $tour): Tour
    {
        return DB::transaction(function () use ($actor, $tour) {
            $locked = Tour::lockForUpdate()->find($tour->id) ?? $tour;

            abort_unless(
                $locked->canTransitionTo(TourStatus::Draft),
                422,
                'Tour cannot be unpublished from its current state.',
            );

            $before = ['status' => $locked->status];

            $locked->update(['status' => TourStatus::Draft->value]);
            $locked->refresh();

            $this->audit->log($actor, 'tour.unpublish', $locked, $before, ['status' => $locked->status]);

            return $locked;
        });
    }
}