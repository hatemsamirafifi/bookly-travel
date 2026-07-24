<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Requests\RejectTourRequest;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reject a submitted tour (Spec 013, FR-003).
 *
 * A reason is required (validated by RejectTourRequest) and recorded in the
 * audit metadata. Writes an immutable `tour.reject` governance audit entry.
 */
class RejectTourAction
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    /**
     * @param  array{rejection_reason?: string}  $data
     */
    public function execute(User $actor, Tour $tour, array $data): Tour
    {
        $reason = (string) ($data['rejection_reason'] ?? '');
        abort_if(trim($reason) === '', 422, 'A rejection reason is required.');

        return DB::transaction(function () use ($actor, $tour, $reason) {
            $locked = Tour::lockForUpdate()->find($tour->id) ?? $tour;

            abort_unless(
                $locked->canTransitionTo(TourStatus::Rejected),
                422,
                'Tour cannot be rejected from its current state.',
            );

            $before = ['status' => $locked->status];

            $locked->update(['status' => TourStatus::Rejected->value]);
            $locked->refresh();

            $this->audit->log(
                $actor,
                'tour.reject',
                $locked,
                $before,
                ['status' => $locked->status],
                ['reason' => $reason],
            );

            return $locked;
        });
    }
}