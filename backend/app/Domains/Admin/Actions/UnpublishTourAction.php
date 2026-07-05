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
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    public function execute(User $actor, Tour $tour): Tour
    {
        abort_unless(
            $tour->canTransitionTo(TourStatus::Draft),
            422,
            'Tour cannot be unpublished from its current state.',
        );

        $before = ['status' => $tour->status];

        return DB::transaction(function () use ($actor, $tour, $before) {
            $tour->update(['status' => TourStatus::Draft->value]);
            $tour->refresh();

            $this->audit->log($actor, 'tour.unpublish', $tour, $before, ['status' => $tour->status]);

            return $tour;
        });
    }
}