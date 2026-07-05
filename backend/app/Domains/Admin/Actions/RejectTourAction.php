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
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    /**
     * @param  array{rejection_reason?: string}  $data
     */
    public function execute(User $actor, Tour $tour, array $data): Tour
    {
        abort_unless(
            $tour->canTransitionTo(TourStatus::Rejected),
            422,
            'Tour cannot be rejected from its current state.',
        );

        $before = ['status' => $tour->status];
        $reason = (string) ($data['rejection_reason'] ?? '');

        return DB::transaction(function () use ($actor, $tour, $before, $reason) {
            $tour->update(['status' => TourStatus::Rejected->value]);
            $tour->refresh();

            $this->audit->log(
                $actor,
                'tour.reject',
                $tour,
                $before,
                ['status' => $tour->status],
                ['reason' => $reason],
            );

            return $tour;
        });
    }
}