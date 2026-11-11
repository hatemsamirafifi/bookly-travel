<?php

namespace App\Domains\Admin\Services;

use App\Domains\Partner\Models\Partner;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Shared fail-closed publication-gate helpers for EGM-002 (Supplier Portal
 * Goldfish Review A4 blocker; Constitution v3.0.0 §II and §IV).
 *
 * Publication happens ONLY through ApproveTourAction. The general Filament
 * create/edit form can no longer change lifecycle state: attempted writes are
 * refused against the freshly locked persisted row instead of being silently
 * sanitized into a successful partial save.
 */
class TourPublicationGuardService
{
    /**
     * Caller holds a transaction. Lock owners before the tour, matching partner
     * suspension. Include a proposed owner so reassignment cannot race suspension.
     */
    public function lockTourForMutation(Tour $tour, mixed $requestedOwnerId = null): Tour
    {
        $snapshot = Tour::findOrFail($tour->getKey());
        $ownerIds = [$snapshot->partner_id];
        $requestedOwner = filter_var($requestedOwnerId, FILTER_VALIDATE_INT);
        if ($requestedOwner !== false && $requestedOwner > 0) {
            $ownerIds[] = $requestedOwner;
        }
        Partner::query()->whereIn('id', $ownerIds)->orderBy('id')->lockForUpdate()->get();
        $locked = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
        abort_unless($locked->partner_id === $snapshot->partner_id, 409, 'Tour ownership changed. Reload before trying again.');

        return $locked;
    }

    /**
     * Authorize the PASSED actor explicitly against the registered `publish`
     * policy (AppServiceProvider: Gate::policy(Tour::class, TourPolicy::class)),
     * re-resolving the actor from persisted permission flags and account
     * status so a revoke or suspension applies immediately instead of
     * trusting a stale in-memory model instance.
     *
     * @return User the freshly resolved, authorized actor
     */
    public function resolveAuthorizedPublisher(User $actor, Tour $tour): User
    {
        $freshActor = $actor->fresh();

        if ($freshActor === null || ! $freshActor->can('publish', $tour)) {
            throw new AuthorizationException('You are not authorized to publish tours.');
        }

        return $freshActor;
    }

    /**
     * General create form: only ever materializes a draft. Any submitted
     * lifecycle value other than `draft` is an attempted write and is refused.
     */
    public function assertGeneralCreateRequiresDraft(?string $submittedStatus): void
    {
        if ($submittedStatus !== null && $submittedStatus !== TourStatus::Draft->value) {
            throw ValidationException::withMessages([
                'data.status' => 'Tours are created as drafts. Publishing is only available through the approval workflow.',
            ]);
        }
    }

    /**
     * General edit form: the submitted raw lifecycle status must equal the
     * freshly locked persisted status. This prevents a stale published form
     * from republishing a concurrently-unpublished record and a stale pending
     * form from silently retracting a published one; a valid existing
     * published metadata save stays published with its stamp and audit
     * unchanged.
     */
    public function assertGeneralSaveKeepsCurrentStatus(?string $submittedStatus, Tour $lockedTour): void
    {
        if ($submittedStatus !== null && $submittedStatus !== $lockedTour->status) {
            throw ValidationException::withMessages([
                'data.status' => sprintf(
                    'This tour is currently "%s". Lifecycle changes through this form are rejected; publish and unpublish remain governed actions with their own audit trail.',
                    $lockedTour->status,
                ),
            ]);
        }
    }

    /**
     * TEMPORARY fail-closed EGM-003 intersection guard (owner decision
     * outstanding on admin content editing). After the main record and
     * relationship rows are saved, a still-published tour must keep a nonblank
     * canonical source title and description in its immutable source locale
     * and an approved owner. Destructive source deletes/renames/blanks and
     * invalid owner changes roll the whole save back. Existing canonical and
     * derivative admin editing behavior is otherwise preserved; derivatives
     * never participate in this check.
     */
    public function assertPublishedTourEditIntegrity(Tour $savedTour): void
    {
        if ($savedTour->status !== TourStatus::Published->value) {
            return;
        }

        $source = $savedTour->sourceTranslation();

        if ($source === null || ! filled($source->title) || ! filled($source->description)) {
            throw ValidationException::withMessages([
                'data.translations' => sprintf(
                    'A published tour requires a title and description in its source language (%s).',
                    $savedTour->sourceLocale(),
                ),
            ]);
        }

        $owner = $savedTour->partnerRecord;

        if ($owner === null || $owner->onboarding_status !== 'approved') {
            throw ValidationException::withMessages([
                'data.partner_id' => 'A published tour must remain owned by an approved partner.',
            ]);
        }
    }
}
