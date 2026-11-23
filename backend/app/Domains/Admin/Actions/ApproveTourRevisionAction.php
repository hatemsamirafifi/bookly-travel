<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Partner\Services\TourRevisionService;
use App\Models\Tour;
use App\Models\TourContentRevision;
use App\Models\User;

/**
 * Approve the EXACT proposed revision captured in the review state (design v3
 * §5). The approving actor and permissions are re-read from persistence; the
 * revision must still be `proposed`, the tour currently `published`, and the
 * candidate must still sit on the current base pointer. The owner-first lock
 * order of EGM-002 is reused (current/proposed partner owners before the Tour),
 * and nothing here publishes a first publication, resets `published_at`, or
 * emits a second first-publication effect.
 */
class ApproveTourRevisionAction
{
    public function __construct(
        private readonly TourRevisionService $revisions,
    ) {}

    public function execute(User $actor, Tour|int $tour, int $revisionId): TourContentRevision
    {
        // The service owns authorization, locks and the outcome transaction.
        // An outer transaction would roll back committed supersession when
        // the service reports an obsolete-review conflict after reconciliation.
        return $this->revisions->approveRevision($tour, $revisionId, $actor);
    }
}
