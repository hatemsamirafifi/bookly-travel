<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Archive a published tour (Admin 2.0, T-B03).
 *
 * Moves a published tour into the terminal `archived` state (delisted from
 * public search/discovery, hidden from new bookings) and writes an immutable
 * `tour.archive` governance audit entry with the reason supplied by the admin.
 */
class ArchiveTourAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    public function execute(User $actor, Tour $tour, string $reason): Tour
    {
        $reasonText = trim($reason);
        abort_if($reasonText === '', 422, 'An archive reason is required.');

        return DB::transaction(function () use ($actor, $tour, $reasonText) {
            $locked = Tour::lockForUpdate()->find($tour->id) ?? $tour;

            abort_unless(
                $locked->canTransitionTo(TourStatus::Archived),
                422,
                'Tour cannot be archived from its current state.',
            );

            $before = ['status' => (string) $locked->status];

            $locked->update(['status' => TourStatus::Archived->value]);
            $locked->refresh();

            $this->audit->log($actor, 'tour.archive', $locked, $before, [
                'status' => TourStatus::Archived->value,
            ], ['reason' => $reasonText]);

            return $locked;
        });
    }
}
