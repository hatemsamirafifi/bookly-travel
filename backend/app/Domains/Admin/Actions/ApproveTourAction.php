<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Admin\Services\TourPublicationGuardService;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Publish an eligible reviewed tour and record its governance event atomically.
 * Owner and tour locks serialize publication with suspension and content saves.
 * Authorization is checked after locking against the persisted actor and record.
 * Derivative readiness never participates in publication validation.
 */
class ApproveTourAction
{
    public function __construct(
        private readonly GovernanceAuditService $audit,
        private readonly TourPublicationGuardService $publicationGuard,
    ) {}

    public function execute(User $actor, Tour $tour): Tour
    {
        return DB::transaction(function () use ($actor, $tour) {
            $locked = $this->publicationGuard->lockTourForMutation($tour);
            $authorizedActor = $this->publicationGuard->resolveAuthorizedPublisher($actor, $locked);
            $sourceLocale = $locked->sourceLocale();
            $source = TourTranslation::query()
                ->where('tour_id', $locked->id)
                ->where('locale', $sourceLocale)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $locked->canTransitionTo(TourStatus::Published),
                422,
                'Tour cannot be published from its current state, or the owning partner is not approved.',
            );

            abort_unless(
                $source && filled($source->title) && filled($source->description),
                422,
                $sourceLocale === Tour::DEFAULT_SOURCE_LOCALE
                    ? 'Tour requires an English title and description before publication.'
                    : 'Tour requires a title and description in its source language before publication.',
            );

            $before = ['status' => $locked->status];

            // Stamp the first-publication timestamp the first time a tour is
            // published. The tour-detail endpoint uses a non-null `published_at`
            // to decide 410 (was once public) vs 404 (never was) for archived
            // tours (tour-detail-api.md:107-114). Preserve an existing stamp on
            // publication after resubmission.
            $locked->update([
                'status' => TourStatus::Published->value,
                'published_at' => $locked->published_at ?? now(),
            ]);
            $locked->refresh();

            $this->audit->log($authorizedActor, 'tour.publish', $locked, $before, ['status' => $locked->status]);

            return $locked;
        });
    }
}
