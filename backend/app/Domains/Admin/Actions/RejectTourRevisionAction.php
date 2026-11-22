<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Partner\Services\TourRevisionService;
use App\Models\Tour;
use App\Models\TourContentRevision;
use App\Models\User;

/**
 * Reject the exact current proposed revision with a mandatory reason (design v3
 * §5). Linked requests are rejected with the same reason; new work requires an
 * explicit new candidate/request. The rejection refuses a stale modal: the
 * revision must still be live.
 */
class RejectTourRevisionAction
{
    public function __construct(
        private readonly TourRevisionService $revisions,
    ) {}

    public function execute(User $actor, Tour|int $tour, int $revisionId, string $reason): TourContentRevision
    {
        // The service owns authorization, locks and the outcome transaction.
        // An outer transaction would roll back committed supersession when
        // the service reports an obsolete-review conflict after reconciliation.
        return $this->revisions->rejectRevision($tour, $revisionId, $actor, $reason);
    }
}
