<?php

namespace App\Domains\Partner\Services;

use App\Domains\Admin\Actions\RejectTourAction;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Admin\Services\TourPublicationGuardService;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Requests\TourContentRules;
use App\Domains\Search\Services\TourPublicCacheService;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\TourContentRevision;
use App\Models\TourRevisionRequest;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The locked tour-content revision service (ADR 0024, design v3 §4-§5).
 *
 * Every candidate/request operation identifies the tour AND the revision and
 * scopes ownership before validation; foreign/unknown ids always stay 404.
 * Immutable replacement inserts a NEW row and supersedes the old one: a
 * payload is never mutated under a reviewed ID. Stale base or nonpublished
 * Tour states commit their supersession RESULT (plus request effects and
 * audit) and only then report a conflict, so the persisted effect and the
 * thrown error can never disagree.
 *
 * Lock order (design v3 §4): publication and correction/moderation mutations
 * lock the current/proposed Partner owners in stable ID order BEFORE the Tour,
 * then the source, derivative states and revision/request rows in stable
 * order. Pure partner proposal/draft/translation operations lock the Tour
 * first and never later acquire a Partner row lock. Public reads take Tour
 * shared locks and never Partner locks. Permissions and account eligibility
 * are always re-read, never trusted from a stale model.
 */
class TourRevisionService
{
    private const OPEN_REQUEST_SUMMARY_LIMIT = 30;

    public function __construct(
        private readonly TourRevisionPayloadService $payloads,
        private readonly GovernanceAuditService $audit,
        private readonly TourPublicationGuardService $publicationGuard,
        private readonly TourTranslationService $translations,
        private readonly AdminAuthorizationService $adminAuth,
        private readonly RejectTourAction $rejectTour,
    ) {}

    // --------------------------------------------------------------------
    // Reads and lazy/batch baseline capture
    // --------------------------------------------------------------------

    /** The currently approved public snapshot, or null before adoption. */
    public function currentApproved(Tour $tour): ?TourContentRevision
    {
        if ($tour->published_revision_id === null) {
            return null;
        }

        $approved = TourContentRevision::query()->forTour((int) $tour->id)
            ->whereKey($tour->published_revision_id)
            ->where('state', TourContentRevision::STATE_APPROVED)->first();

        if ($approved === null) {
            throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT);
        }

        return $approved;
    }

    /**
     * The one live candidate (draft/proposed), guaranteed unique per tour by
     * the PostgreSQL partial unique index.
     */
    public function activeCandidate(Tour $tour): ?TourContentRevision
    {
        return TourContentRevision::query()
            ->forTour((int) $tour->id)
            ->whereIn('state', [TourContentRevision::STATE_DRAFT, TourContentRevision::STATE_PROPOSED])
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Whether a live candidate exists without taking a row lock (cheap guard).
     */
    public function hasActiveCandidate(int $tourId): bool
    {
        return TourContentRevision::query()
            ->forTour($tourId)
            ->whereIn('state', [TourContentRevision::STATE_DRAFT, TourContentRevision::STATE_PROPOSED])
            ->exists();
    }

    /**
     * Lazily capture the faithful legacy baseline of a published tour that has
     * no approved snapshot yet. Idempotent under the Tour lock with a
     * pointer-if-null CAS, so batch backfill and first concurrent use cannot
     * establish two pointers.
     *
     * Capture records existing public provenance: it never validates, never
     * repairs and never changes visibility or emits `tour.publish`.
     */
    public function captureBaseline(Tour|int $tour, bool $lock = true): ?TourContentRevision
    {
        return $this->runTransaction(function () use ($tour, $lock): ?TourContentRevision {
            $locked = $lock ? $this->lockTour($this->tourId($tour)) : $this->tourModel($tour);

            if (! $locked->exists || $locked->status !== 'published') {
                return null;
            }

            return $this->captureBaselineLocked($locked);
        });
    }

    /**
     * Baseline for a tour that is already locked by the caller.
     */
    public function captureBaselineLocked(Tour $locked): ?TourContentRevision
    {
        $existing = $this->currentApproved($locked);

        if ($existing !== null) {
            return $existing;
        }

        // Idempotence on historical rows that adoption may have captured
        // before the pointer landed.
        $adopted = TourContentRevision::query()
            ->forTour((int) $locked->id)
            ->where('kind', TourContentRevision::KIND_LEGACY_BASELINE)
            ->whereIn('state', [TourContentRevision::STATE_APPROVED])
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($adopted === null) {
            $adopted = $this->insertRevision(
                $locked,
                kind: TourContentRevision::KIND_LEGACY_BASELINE,
                state: TourContentRevision::STATE_APPROVED,
                payload: $this->payloads->captureSnapshot($locked),
                createdBy: null,
            );
        }

        // Pointer-if-null CAS: a concurrent publication already advancing the
        // pointer always wins.
        Tour::query()->whereKey($locked->id)->whereNull('published_revision_id')->update([
            'published_revision_id' => $adopted->id,
        ]);

        $locked->refresh()->loadMissing('publishedRevision');
        $locked->publishedRevision?->refresh();

        return $locked->publishedRevision;
    }

    /**
     * Bounded, idempotent baseline capture for the activation runbook. Tours
     * already holding a pointer are skipped; the return value reports the
     * counts and the resume cursor without claiming anything else.
     *
     * @return array{captured: int, skipped: int, after_id: int}
     */
    public function captureBaselineBatch(int $limit = 100, int $afterId = 0): array
    {
        $captured = 0;
        $skipped = 0;
        $lastId = $afterId;

        Tour::query()->where('id', '>', $afterId)->where('status', 'published')->orderBy('id')->limit($limit)
            ->get()
            ->each(function (Tour $tour) use (&$captured, &$skipped, &$lastId): void {
                $this->runTransaction(function () use ($tour, &$captured, &$skipped, &$lastId): void {
                    $fresh = $this->lockTour($tour->id);

                    if ($fresh->status !== 'published') {
                        $skipped++;

                        return;
                    }

                    $pointer = $fresh->published_revision_id;

                    $this->captureBaselineLocked($fresh);

                    $fresh->refresh();

                    if ($pointer !== $fresh->published_revision_id) {
                        $captured++;
                    } else {
                        $skipped++;
                    }

                    $lastId = $fresh->id;
                });
            });

        return ['captured' => $captured, 'skipped' => $skipped, 'after_id' => $lastId];
    }

    // --------------------------------------------------------------------
    // Candidate lifecycle
    // --------------------------------------------------------------------

    /**
     * Create the first candidate of a published tour.
     *
     * @param  array<string, mixed>  $patch  source/presentation/options/media tiers
     */
    public function createCandidate(Tour|int $tour, int $actorId, ?int $observedBaseId, array $patch, ?string $changeSummary = null): TourContentRevision
    {
        $outcome = $this->runTransaction(function () use ($tour, $actorId, $observedBaseId, $patch, $changeSummary): array {
            $locked = $this->lockTourForPartnerMutation($tour, $actorId);

            if ($locked->status !== 'published') {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'tour_id' => (int) $locked->id,
                    'required_state' => 'published',
                ])];
            }

            $base = $this->captureBaselineLocked($locked);
            $baseId = (int) ($base->id ?? 0);

            if ($observedBaseId === null || $observedBaseId !== $baseId) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'tour_id' => (int) $locked->id,
                    'expected_base_revision_id' => $baseId,
                ])];
            }

            if ($this->hasActiveCandidate((int) $locked->id)) {
                $existing = $this->activeCandidate($locked);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_ALREADY_OPEN, [
                    'tour_id' => (int) $locked->id,
                    'conflicting_revision_id' => $existing?->id,
                    'conflicting_revision_number' => $existing?->revision_number,
                    'conflicting_revision_state' => $existing?->state,
                ])];
            }

            $payload = $this->payloads->mergePatch($patch, $base?->payload, $locked);

            $revision = $this->insertRevision(
                $locked,
                kind: TourContentRevision::KIND_PARTNER_EDIT,
                state: TourContentRevision::STATE_DRAFT,
                payload: $payload,
                createdBy: $actorId,
                baseRevisionId: $baseId,
                contentHash: $this->payloads->canonicalHashOfPayload($payload),
                changeSummary: $changeSummary,
            );

            $this->auditActor(
                $this->resolveActor($actorId),
                'tour.revision.created',
                $locked,
                null,
                ['revision_id' => $revision->id, 'revision_number' => $revision->revision_number, 'state' => $revision->state],
                ['base_revision_id' => $baseId === 0 ? null : $baseId],
            );

            return ['revision' => $revision->refresh()];
        });

        $this->throwRefusal($outcome['refusal'] ?? null);

        return $outcome['revision'];
    }

    /**
     * Immutable replacement: merge onto the stored candidate, insert a NEW
     * draft row and supersede the reviewed one.
     *
     * @param  array<string, mixed>  $patch
     */
    public function replaceCandidate(
        Tour|int $tour,
        int $revisionId,
        int $actorId,
        ?int $observedBaseId,
        ?string $observedPayloadHash,
        array $patch,
        ?string $changeSummary = null,
        bool $changeSummaryProvided = false,
    ): TourContentRevision {
        $outcome = $this->runTransaction(function () use (
            $tour,
            $revisionId,
            $actorId,
            $observedBaseId,
            $observedPayloadHash,
            $patch,
            $changeSummary,
            $changeSummaryProvided,
        ): array {
            $locked = $this->lockTourForPartnerMutation($tour, $actorId);

            $candidate = $this->lockRevision($revisionId, (int) $locked->id);

            if ($candidate === null) {
                throw new NotFoundHttpException('Revision not found.');
            }

            if ($candidate->tour_id !== $locked->id) {
                throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT, null, [], [
                    'revision_id' => $revisionId,
                ]);
            }

            if (! $candidate->isLive()) {
                return ['refusal' => $this->refusal($this->terminalCode($candidate->state), [
                    'revision_id' => $candidate->id,
                    'revision_state' => $candidate->state,
                ])];
            }

            if ($locked->status !== 'published') {
                // The supersession RESULT commits with the transaction; the
                // conflict is only reported afterwards.
                $this->reconcileLifecycleLocked($locked);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'tour_id' => (int) $locked->id,
                    'required_state' => 'published',
                    'superseded_revision_id' => $candidate->id,
                ])];
            }

            $base = $this->captureBaselineLocked($locked);
            $baseId = (int) ($base->id ?? 0);

            if ((int) $candidate->base_revision_id !== $baseId) {
                $this->supersedeCandidate($candidate, $locked, TourRevisionException::REVISION_CONFLICT, null);
                $this->supersedeLinkedRequests($candidate);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_SUPERSEDED, [
                    'tour_id' => (int) $locked->id,
                    'expected_base_revision_id' => $baseId,
                    'superseded_revision_id' => $candidate->id,
                ])];
            }

            if ($observedBaseId === null || $observedBaseId !== $baseId) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'tour_id' => (int) $locked->id,
                    'expected_base_revision_id' => $baseId,
                ])];
            }

            if ($observedPayloadHash === null || $observedPayloadHash !== $candidate->payload_hash) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'revision_id' => $candidate->id,
                    'expected_payload_hash' => $candidate->payload_hash,
                ])];
            }

            $payload = $this->payloads->mergePatch($patch, $candidate->payload, $locked);

            // Supersede the old candidate BEFORE inserting the new draft row
            // to satisfy the immediate partial unique index on (tour_id WHERE state IN ('draft', 'proposed')).
            $this->supersedeCandidate($candidate, $locked, TourRevisionException::REVISION_SUPERSEDED, null);
            $this->supersedeLinkedRequests($candidate);

            $nextSummary = ($changeSummaryProvided || $changeSummary !== null)
                ? $changeSummary
                : $candidate->change_summary;

            $replacement = $this->insertRevision(
                $locked,
                kind: $candidate->kind,
                state: TourContentRevision::STATE_DRAFT,
                payload: $payload,
                createdBy: $actorId,
                baseRevisionId: $baseId,
                contentHash: $this->payloads->canonicalHashOfPayload($payload),
                changeSummary: $nextSummary,
            );

            $this->auditActor(
                $this->resolveActor($actorId),
                'tour.revision.replaced',
                $locked,
                ['revision_id' => $candidate->id, 'revision_state' => $candidate->state],
                ['revision_id' => $replacement->id, 'revision_number' => $replacement->revision_number],
                ['replaced_revision_id' => $candidate->id],
            );

            return ['revision' => $replacement->refresh()];
        });

        $this->throwRefusal($outcome['refusal'] ?? null);

        return $outcome['revision'];
    }

    /** Submit the owned current draft: proposed, stamped, audited once. */
    public function submitCandidate(Tour|int $tour, int $revisionId, int $actorId): TourContentRevision
    {
        $outcome = $this->runTransaction(function () use ($tour, $revisionId, $actorId): array {
            $locked = $this->lockTourForPartnerMutation($tour, $actorId);
            $candidate = $this->lockRevision($revisionId, (int) $locked->id);

            if ($candidate === null) {
                throw new NotFoundHttpException('Revision not found.');
            }

            if ($candidate->tour_id !== $locked->id) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'revision_id' => $revisionId,
                ])];
            }

            if (! $candidate->isLive()) {
                return ['refusal' => $this->refusal($this->terminalCode($candidate->state), [
                    'revision_id' => $candidate->id,
                    'revision_state' => $candidate->state,
                ])];
            }

            if ($locked->status !== 'published') {
                $this->supersedeCandidate($candidate, $locked, TourRevisionException::REVISION_CONFLICT, null);
                $this->supersedeLinkedRequests($candidate);
                $this->auditReconciliation($locked);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'tour_id' => (int) $locked->id,
                    'required_state' => 'published',
                    'superseded_revision_id' => $candidate->id,
                ])];
            }

            $base = $this->captureBaselineLocked($locked);
            $baseId = (int) ($base->id ?? 0);

            if ((int) $candidate->base_revision_id !== $baseId) {
                $this->supersedeCandidate($candidate, $locked, TourRevisionException::REVISION_CONFLICT, null);
                $this->supersedeLinkedRequests($candidate);
                $this->auditReconciliation($locked);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'expected_base_revision_id' => $baseId,
                    'superseded_revision_id' => $candidate->id,
                ])];
            }

            if ($candidate->state === TourContentRevision::STATE_PROPOSED) {
                // Replaying an exact in-review submission must never duplicate
                // the submission stamp or its audit.
                return ['revision' => $candidate];
            }

            $paths = $this->payloads->validateFullSnapshot($candidate->payload, $locked);

            if ($paths !== []) {
                return ['refusal' => $this->refusal(
                    TourRevisionException::REVISION_CONTENT_INVALID,
                    ['revision_id' => $candidate->id],
                    $paths,
                    TourRevisionException::STATUS_INVALID,
                )];
            }

            $candidate->update([
                'state' => TourContentRevision::STATE_PROPOSED,
                'submitted_at' => now(),
            ]);

            $this->auditActor(
                $this->resolveActor($actorId),
                'tour.revision.submitted',
                $locked,
                ['revision_state' => TourContentRevision::STATE_DRAFT],
                ['revision_state' => TourContentRevision::STATE_PROPOSED],
                ['revision_id' => $candidate->id],
            );

            return ['revision' => $candidate->refresh()];
        });

        $this->throwRefusal($outcome['refusal'] ?? null);

        return $outcome['revision'];
    }

    /** Owner withdrawal of a partner-authored draft/proposed. */
    public function withdrawCandidate(Tour|int $tour, int $revisionId, int $actorId): TourContentRevision
    {
        $outcome = $this->runTransaction(function () use ($tour, $revisionId, $actorId): array {
            $locked = $this->lockTourForPartnerMutation($tour, $actorId);
            $candidate = $this->lockRevision($revisionId, (int) $locked->id);

            if ($candidate === null) {
                throw new NotFoundHttpException('Revision not found.');
            }

            if ($candidate->tour_id !== $locked->id) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'revision_id' => $revisionId,
                ])];
            }

            if (! $candidate->isLive()) {
                return ['refusal' => $this->refusal($this->terminalCode($candidate->state), [
                    'revision_id' => $candidate->id,
                    'revision_state' => $candidate->state,
                ])];
            }

            if ($candidate->kind === TourContentRevision::KIND_ADMIN_CORRECTION) {
                // An owner cannot withdraw an administrator's proposed
                // correction; the reviewer rejects it or the owner replaces it.
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'revision_id' => $candidate->id,
                    'revision_kind' => $candidate->kind,
                ])];
            }

            $candidate->update(['state' => TourContentRevision::STATE_WITHDRAWN, 'decided_by' => $actorId, 'decided_at' => now()]);

            $this->supersedeLinkedRequests($candidate);

            $this->auditActor(
                $this->resolveActor($actorId),
                'tour.revision.withdrawn',
                $locked,
                ['revision_state' => $candidate->getOriginal('state')],
                ['revision_state' => TourContentRevision::STATE_WITHDRAWN],
                ['revision_id' => $candidate->id],
            );

            return ['revision' => $candidate->refresh()];
        });

        $this->throwRefusal($outcome['refusal'] ?? null);

        return $outcome['revision'];
    }

    // --------------------------------------------------------------------
    // Moderation: requests, corrections, review
    // --------------------------------------------------------------------

    /**
     * Re-read the actor from persistence and assert the given administrator permission.
     */
    private function authorizeAdmin(User $actor, string $permission, string $message): User
    {
        $fresh = $actor->fresh();

        if ($fresh === null || ! $this->adminAuth->can($fresh, $permission)) {
            throw new AuthorizationException($message);
        }

        return $fresh;
    }

    /**
     * Administrator change request on a tour (initial/published/correction).
     *
     * @param  list<array{path: string, note?: string}>|list<mixed>  $fieldRefs
     */
    public function requestChanges(Tour|int $tour, User $actor, string $context, string $reason, array $fieldRefs, ?int $baseRevisionId = null, ?string $correctionPatchPayloadHash = null): TourRevisionRequest
    {
        return $this->runTransaction(function () use ($tour, $actor, $context, $reason, $fieldRefs, $baseRevisionId): TourRevisionRequest {
            $locked = $this->lockTourForModeration($tour);
            $freshActor = $this->authorizeAdmin($actor, 'manage_tours', 'You are not authorized to request tour changes.');
            $actorId = (int) $freshActor->id;
            $trimmedReason = trim($reason);

            if ($trimmedReason === '' || mb_strlen($trimmedReason) > 2000) {
                throw TourRevisionException::invalid(TourRevisionException::UNKNOWN_FIELD_REFERENCE, [
                    'reason' => 'A reason of 1-2000 characters is required.',
                ]);
            }

            if (! in_array($context, TourRevisionRequest::CONTEXTS, true)) {
                throw TourRevisionException::invalid(TourRevisionException::UNKNOWN_FIELD_REFERENCE, [
                    'context' => 'Unsupported request context.',
                ]);
            }

            if ($context === TourRevisionRequest::CONTEXT_INITIAL_REVIEW) {
                if (! in_array($locked->status, ['draft', 'pending_review', 'rejected'], true)
                    || $locked->published_at !== null || $locked->published_revision_id !== null) {
                    throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT, null, [], [
                        'tour_id' => (int) $locked->id,
                        'tour_status' => $locked->status,
                        'context' => $context,
                    ]);
                }
            } else {
                if ($locked->status !== 'published') {
                    throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT, null, [], [
                        'tour_id' => (int) $locked->id,
                        'required_state' => 'published',
                    ]);
                }

                if (! $locked->hasEligiblePublicOwner()) {
                    throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT, null, [], [
                        'tour_id' => (int) $locked->id,
                        'required_owner_status' => 'approved',
                    ]);
                }
            }

            $baseline = $context === TourRevisionRequest::CONTEXT_INITIAL_REVIEW
                ? null
                : $this->captureBaselineLocked($locked);

            if ($context !== TourRevisionRequest::CONTEXT_INITIAL_REVIEW && $baseRevisionId !== null) {
                $baseId = (int) ($baseline->id ?? 0);
                if ($baseId !== $baseRevisionId) {
                    throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT, null, [], [
                        'expected_base_revision_id' => $baseId,
                        'observed_base_revision_id' => $baseRevisionId,
                    ]);
                }
            }

            $contextPayload = $baseline->payload ?? $this->payloads->captureSnapshot($locked);
            $resolvedRefs = $this->payloads->resolveFieldReferences($fieldRefs, $contextPayload);

            // Initial-review requests capture the canonical source identity AND
            // the observed epoch in one 64-character immutable value: the
            // partner response must observe that exact epoch.
            $contentHash = $context === TourRevisionRequest::CONTEXT_INITIAL_REVIEW
                ? hash(
                    'sha256',
                    ($this->payloads->contentHash($locked) ?? '') . '|epoch:' . $locked->generationEpoch(),
                )
                : (string) ($baseline->content_hash ?? '');

            $request = TourRevisionRequest::create([
                'tour_id' => (int) $locked->id,
                'context' => $context,
                'base_revision_id' => $baseline?->id,
                'revision_id' => null,
                'state' => TourRevisionRequest::STATE_OPEN,
                'reason' => $trimmedReason,
                'field_refs' => $resolvedRefs,
                'requested_content_hash' => $contentHash,
                'requested_by' => $actorId,
            ]);

            if ($context === TourRevisionRequest::CONTEXT_INITIAL_REVIEW && $locked->status === 'pending_review') {
                $this->rejectTour->execute($freshActor, $locked, ['rejection_reason' => $trimmedReason]);
            }

            $this->audit->log(
                $freshActor->fresh() ?? $freshActor,
                $context === TourRevisionRequest::CONTEXT_CORRECTION ? 'tour.correction.requested' : 'tour.changes.requested',
                $locked,
                null,
                ['request_state' => $request->state, 'request_context' => $request->context],
                ['request_id' => $request->id, 'reason' => $trimmedReason, 'field_refs' => array_column($resolvedRefs, 'path')],
            );

            return $request->refresh();
        });
    }

    /**
     * Partner response to a change request. Published context links ONLY the
     * current owned submitted candidate with the captured base; initial context
     * requires the partner's observed generation epoch to equal the current one.
     *
     * @param  array<string, mixed>  $input
     */
    public function respondToRequest(Tour|int $tour, int $requestId, int $partnerId, int $actorId, ?string $note, array $input): TourRevisionRequest
    {
        $outcome = $this->runTransaction(function () use ($tour, $requestId, $actorId, $note, $input, $partnerId): array {
            $locked = $this->lockTourForPartnerMutation($tour, $actorId);

            if ((int) $locked->partner_id !== $partnerId) {
                throw new NotFoundHttpException('Tour not found.');
            }

            try {
                $request = TourRevisionRequest::query()
                    ->forTour((int) $locked->id)
                    ->whereKey($requestId)
                    ->lockForUpdate()
                    ->first();

                if ($request === null) {
                    throw new NotFoundHttpException('Change request not found.');
                }

                if ($request->state !== TourRevisionRequest::STATE_OPEN) {
                    throw TourRevisionException::conflict(TourRevisionException::REQUEST_NOT_OPEN, null, [], [
                        'request_id' => $request->id,
                        'request_state' => $request->state,
                    ]);
                }

                $trimmedNote = $note === null ? null : trim($note);

                if ($trimmedNote === '') {
                    $trimmedNote = null;
                }

                if ($trimmedNote !== null && mb_strlen($trimmedNote) > 2000) {
                    throw TourRevisionException::invalid(TourRevisionException::UNKNOWN_FIELD_REFERENCE, [
                        'response_note' => 'The response note must not exceed 2000 characters.',
                    ]);
                }

                if ($request->context !== TourRevisionRequest::CONTEXT_INITIAL_REVIEW) {
                    $approved = $this->currentApproved($locked);
                    if ($request->base_revision_id === null || (int) $request->base_revision_id !== (int) $approved?->id) {
                        $request->update([
                            'state' => TourRevisionRequest::STATE_SUPERSEDED,
                            'decision_reason' => 'The approved baseline shifted; the review request was superseded.',
                            'decided_at' => now(),
                        ]);
                        $this->auditActor($this->resolveActor($actorId), 'tour.changes.superseded', $locked,
                            ['request_state' => TourRevisionRequest::STATE_OPEN],
                            ['request_state' => TourRevisionRequest::STATE_SUPERSEDED],
                            ['request_id' => $request->id]);
                        throw TourRevisionException::conflict(TourRevisionException::REQUEST_NOT_OPEN, null, [], [
                            'request_id' => $request->id, 'request_state' => TourRevisionRequest::STATE_SUPERSEDED,
                        ]);
                    }

                    $submission = is_int($input['revision_id'] ?? null) ? (int) $input['revision_id'] : null;

                    if ($submission === null) {
                        throw TourRevisionException::invalid(TourRevisionException::REVISION_CONFLICT, [
                            'revision_id' => 'A submitted revision id is required.',
                        ]);
                    }

                    $candidate = $this->lockRevision($submission, (int) $locked->id);

                    if ($candidate === null || $candidate->tour_id !== $locked->id) {
                        throw TourRevisionException::invalid(TourRevisionException::REVISION_CONFLICT, [
                            'revision_id' => 'The submitted revision does not belong to this tour.',
                        ], null, ['request_id' => $request->id]);
                    }

                    if ($candidate->state !== TourContentRevision::STATE_PROPOSED) {
                        throw TourRevisionException::conflict($this->terminalCode($candidate->state), null, [], [
                            'revision_id' => $candidate->id,
                            'revision_state' => $candidate->state,
                            'request_id' => $request->id,
                        ]);
                    }

                    if ($this->hasActiveCandidate((int) $locked->id) && $candidate->revision_number !== $this->activeCandidate($locked)->revision_number) {
                        throw TourRevisionException::conflict(TourRevisionException::REVISION_SUPERSEDED, null, [], [
                            'revision_id' => $candidate->id,
                            'conflicting_revision_id' => $this->activeCandidate($locked)?->id,
                        ]);
                    }

                    if ((int) $candidate->base_revision_id !== (int) $request->base_revision_id) {
                        throw TourRevisionException::invalid(TourRevisionException::REVISION_CONFLICT, [
                            'revision_id' => 'The submitted revision is built on a different base than the request.',
                        ], null, ['request_id' => $request->id, 'expected_base_revision_id' => (int) $request->base_revision_id]);
                    }

                    $request->update([
                        'revision_id' => $candidate->id,
                        'state' => TourRevisionRequest::STATE_ANSWERED,
                        'response_note' => $trimmedNote,
                        'responded_by' => $actorId,
                        'responded_at' => now(),
                    ]);
                } else {
                    $epoch = is_int($input['generation_epoch'] ?? null) ? (int) $input['generation_epoch'] : null;

                    if ($epoch === null) {
                        throw TourRevisionException::invalid(TourRevisionException::REVISION_CONFLICT, [
                            'generation_epoch' => 'The observed source generation epoch is required.',
                        ]);
                    }

                    if ($epoch !== $locked->generationEpoch()) {
                        throw TourRevisionException::conflict(TourRevisionException::REQUEST_NOT_OPEN, null, [], [
                            'request_id' => $request->id, 'expected_generation_epoch' => $locked->generationEpoch(),
                        ]);
                    }

                    $request->update([
                        'state' => TourRevisionRequest::STATE_ANSWERED,
                        'response_note' => $trimmedNote,
                        'response_generation_epoch' => $epoch,
                        'responded_by' => $actorId,
                        'responded_at' => now(),
                    ]);
                }

                $this->auditActor(
                    $this->resolveActor($actorId),
                    'tour.changes.responded',
                    $locked,
                    ['request_state' => TourRevisionRequest::STATE_OPEN],
                    ['request_state' => TourRevisionRequest::STATE_ANSWERED],
                    ['request_id' => $request->id, 'revision_id' => $request->revision_id],
                );

                return ['request' => $request->refresh()];
            } catch (TourRevisionException $exception) {
                return ['refusal' => $this->refusal(
                    $exception->errorCode, $exception->identity, $exception->paths,
                    $exception->status, $exception->getMessage(),
                )];
            }
        });

        $this->throwRefusal($outcome['refusal'] ?? null);

        return $outcome['request'];
    }

    /**
     * Exceptional administrator correction: explicit permission, mandatory
     * reason, declared fields, validated canonical patch, immutable proposed
     * revision and audit. Never writes live source and never publishes.
     *
     * @param  list<mixed>  $declaredRefs
     * @param  array<string, mixed>  $patch
     */
    public function proposeCorrection(Tour|int $tour, User $actor, string $reason, array $declaredRefs, array $patch): TourContentRevision
    {
        return $this->runTransaction(function () use ($tour, $actor, $reason, $declaredRefs, $patch): TourContentRevision {
            $locked = $this->lockTourForModeration($tour);
            $freshActor = $this->authorizeAdmin($actor, 'correct_tour_content', 'You are not authorized to propose tour corrections.');
            $actorId = (int) $freshActor->id;
            $trimmedReason = trim($reason);

            if ($trimmedReason === '' || mb_strlen($trimmedReason) > 2000) {
                throw TourRevisionException::invalid(TourRevisionException::UNKNOWN_FIELD_REFERENCE, [
                    'reason' => 'A reason of 1-2000 characters is required.',
                ]);
            }

            if ($locked->status !== 'published') {
                throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT, null, [], [
                    'tour_id' => (int) $locked->id,
                    'required_state' => 'published',
                ]);
            }

            if (! $locked->hasEligiblePublicOwner()) {
                throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT, null, [], [
                    'tour_id' => (int) $locked->id,
                    'required_owner_status' => 'approved',
                ]);
            }

            $baseline = $this->captureBaselineLocked($locked);
            $contextPayload = $baseline->payload ?? $this->payloads->captureSnapshot($locked);
            $declaredPaths = array_column($this->payloads->resolveFieldReferences($declaredRefs, $contextPayload), 'path');

            if ($this->hasActiveCandidate((int) $locked->id)) {
                $existing = $this->activeCandidate($locked);

                throw TourRevisionException::conflict(TourRevisionException::REVISION_ALREADY_OPEN, null, [], [
                    'conflicting_revision_id' => $existing?->id,
                ]);
            }

            // The correction patch is canonical-source-only in this slice: only
            // `source.*` paths may be declared and only source fields may change.
            foreach ($declaredPaths as $path) {
                if (! str_starts_with((string) $path, 'source.')) {
                    throw TourRevisionException::invalid(TourRevisionException::UNDECLARED_FIELD_CHANGE, [
                        'field_refs' => 'Corrections in this slice support canonical source fields only.',
                    ]);
                }
            }

            $payload = $this->payloads->mergePatch($patch, $contextPayload, $locked);

            $changedPaths = $this->payloads->changedPaths($contextPayload, $payload);

            if ($changedPaths === []) {
                throw TourRevisionException::invalid(TourRevisionException::UNDECLARED_FIELD_CHANGE, [
                    'field_refs' => 'A correction must change at least one declared field.',
                ]);
            }

            foreach ($changedPaths as $changed) {
                if (! in_array((string) $changed, $declaredPaths, true)) {
                    throw TourRevisionException::invalid(TourRevisionException::UNDECLARED_FIELD_CHANGE, [
                        (string) $changed => 'The changed field was not declared in the correction.',
                    ]);
                }
            }

            $revision = $this->insertRevision(
                $locked,
                kind: TourContentRevision::KIND_ADMIN_CORRECTION,
                state: TourContentRevision::STATE_PROPOSED,
                payload: $payload,
                createdBy: $actorId,
                baseRevisionId: (int) ($baseline->id ?? 0),
                contentHash: $this->payloads->canonicalHashOfPayload($payload),
            );

            $revision->update(['submitted_at' => now()]);

            TourRevisionRequest::create([
                'tour_id' => (int) $locked->id,
                'context' => TourRevisionRequest::CONTEXT_CORRECTION,
                'base_revision_id' => $baseline?->id,
                'revision_id' => $revision->id,
                'state' => TourRevisionRequest::STATE_OPEN,
                'reason' => $trimmedReason,
                'field_refs' => array_map(fn ($path) => ['path' => $path], $declaredPaths),
                'requested_content_hash' => (string) ($baseline->content_hash ?? ''),
                'requested_by' => $actorId,
            ]);

            $this->audit->log(
                $freshActor->fresh() ?? $freshActor,
                'tour.correction.proposed',
                $locked,
                null,
                ['revision_id' => $revision->id, 'revision_state' => $revision->state],
                ['reason' => $trimmedReason, 'field_refs' => $declaredPaths, 'actor_admin_id' => $actorId],
            );

            return $revision->refresh();
        });
    }

    /**
     * Approve the exact proposed revision and promote it atomically over the
     * published tour.
     */
    public function approveRevision(Tour|int $tour, int $revisionId, User $actor): TourContentRevision
    {
        $outcome = $this->runTransaction(function () use ($tour, $revisionId, $actor): array {
            $locked = $this->lockTourForModeration($tour);
            $actor = $this->publicationGuard->resolveAuthorizedPublisher($actor, $locked);
            $actorId = (int) $actor->id;
            $candidate = $this->lockRevision($revisionId, (int) $locked->id);

            if ($candidate === null) {
                throw new NotFoundHttpException('Revision not found.');
            }

            if ($candidate->tour_id !== $locked->id) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'revision_id' => $revisionId,
                ])];
            }

            if ($candidate->state === TourContentRevision::STATE_SUPERSEDED) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_SUPERSEDED, [
                    'revision_id' => $candidate->id,
                ])];
            }

            if ($candidate->state !== TourContentRevision::STATE_PROPOSED) {
                return ['refusal' => $this->refusal($this->terminalCode($candidate->state), [
                    'revision_id' => $candidate->id,
                    'revision_state' => $candidate->state,
                ])];
            }

            // EGM-002 eligibility is reused, NOT the lifecycle matrix: revision
            // promotion is not a published→published transition. The tour must
            // still be published and its owner must still be approved for
            // publication (fresh, after the owner-first lock).
            if ($locked->status !== 'published') {
                $this->supersedeCandidate($candidate, $locked, TourRevisionException::REVISION_SUPERSEDED, null);
                $this->supersedeLinkedRequests($candidate);
                $this->auditReconciliation($locked);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_SUPERSEDED, [
                    'tour_id' => (int) $locked->id,
                    'required_state' => 'published',
                    'superseded_revision_id' => $candidate->id,
                ])];
            }

            if (! $locked->hasEligiblePublicOwner()) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'tour_id' => (int) $locked->id,
                    'required_owner_status' => 'approved',
                ])];
            }

            $base = $this->captureBaselineLocked($locked);
            $baseId = (int) ($base->id ?? 0);

            if ((int) $candidate->base_revision_id !== $baseId) {
                $this->supersedeCandidate($candidate, $locked, TourRevisionException::REVISION_CONFLICT, null);
                $this->supersedeLinkedRequests($candidate);
                $this->auditReconciliation($locked);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'expected_base_revision_id' => $baseId,
                    'superseded_revision_id' => $candidate->id,
                ])];
            }

            $paths = $this->payloads->validateFullSnapshot($candidate->payload, $locked);

            if ($paths !== []) {
                return ['refusal' => $this->refusal(
                    TourRevisionException::REVISION_CONTENT_INVALID,
                    ['revision_id' => $candidate->id],
                    $paths,
                    TourRevisionException::STATUS_INVALID,
                )];
            }

            $beforePayload = $this->payloads->captureSnapshot($locked);
            $beforeContentVersion = $locked->contentVersion();
            $beforeSlug = (string) $locked->slug;

            // Promote atomically: write source/presentation/media/option text
            // and advance the pointer/version in ONE transaction.
            $this->payloads->materialize($locked, $candidate->payload);

            $after = $this->payloads->captureSnapshot($locked->refresh());

            // Media/presentation-only approval never advances the canonical
            // generation epoch: in-flight work for identical canonical content
            // stays valid.
            $previousHash = $this->payloads->canonicalHashOfPayload($beforePayload);
            $canonChanged = $previousHash !== $this->payloads->canonicalHashOfPayload($after);
            $planned = $canonChanged ? $this->translations->planSourceDispatches($locked, $previousHash) : [];

            $candidate->update([
                'state' => TourContentRevision::STATE_APPROVED,
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);

            $this->supersedeOtherCandidates($locked, $candidate);

            // The shared planner owns the epoch and desired derivative states;
            // never advance a second identity that queued jobs cannot match.
            $nextEpoch = $locked->fresh()->generationEpoch();

            Tour::query()->whereKey($locked->id)->update([
                'published_revision_id' => $candidate->id,
                'content_version' => $beforeContentVersion + 1,
                'generation_epoch' => $nextEpoch,
            ]);

            // Linked answered requests are approved; other open requests for
            // the same base close with an explicit approval decision reason
            // (without claiming a partner response).
            $this->closeRequestsOnApproval($locked, $candidate, $actorId);

            $this->audit->log(
                $actor->fresh() ?? $actor,
                'tour.revision.approved',
                $locked,
                ['base_revision_id' => $baseId === 0 ? null : $baseId, 'content_version' => $beforeContentVersion],
                ['base_revision_id' => $candidate->id, 'content_version' => $beforeContentVersion + 1, 'generation_epoch' => $nextEpoch],
                ['revision_id' => $candidate->id, 'canonical_changed' => $canonChanged],
            );

            $this->translations->dispatchPlannedTranslations($planned);

            app(TourPublicCacheService::class)->invalidateOwnerTargetsAfterCommit([[
                'id' => (int) $locked->id,
                'slug' => $beforeSlug,
                'content_version' => $beforeContentVersion,
            ]]);

            return ['revision' => $candidate->refresh()];
        });

        $this->throwRefusal($outcome['refusal'] ?? null);

        return $outcome['revision'];
    }

    /**
     * Seal an approved publication revision during first- or re-publication in
     * ApproveTourAction (ADR 0024, design v3 §4).
     *
     * Preconditions (caller-enforced):
     *  - caller holds an encompassing database transaction;
     *  - caller acquired owner-first locks and authorized the publisher;
     *  - tour is already in published status and source content is valid.
     *
     * Commits a new approved `publication` revision snapshot, advances
     * published_revision_id and content_version, and reconciles older candidates
     * and review requests.
     */
    public function sealPublication(Tour $lockedTour, User $actor): TourContentRevision
    {
        abort_unless(DB::transactionLevel() > 0, 500, 'Publication sealing requires an active database transaction.');
        abort_unless($lockedTour->status === TourStatus::Published->value, 422, 'Tour must be in published status to seal a publication revision.');
        $actor = $this->publicationGuard->resolveAuthorizedPublisher($actor, $lockedTour);

        $payload = $this->payloads->captureSnapshot($lockedTour);

        $revision = $this->insertRevision(
            $lockedTour,
            kind: TourContentRevision::KIND_PUBLICATION,
            state: TourContentRevision::STATE_APPROVED,
            payload: $payload,
            createdBy: (int) $actor->id,
            baseRevisionId: null,
            contentHash: $this->payloads->canonicalHashOfPayload($payload),
        );

        $revision->update([
            'decided_by' => (int) $actor->id,
            'decided_at' => now(),
        ]);

        $this->supersedeOtherCandidates($lockedTour, $revision);

        TourRevisionRequest::query()->forTour((int) $lockedTour->id)
            ->where('context', TourRevisionRequest::CONTEXT_INITIAL_REVIEW)
            ->whereIn('state', [TourRevisionRequest::STATE_OPEN, TourRevisionRequest::STATE_ANSWERED])
            ->orderBy('id')->get()->each(function (TourRevisionRequest $request) use ($lockedTour, $actor, $revision): void {
                $beforeState = $request->state;
                $request->update([
                    'state' => TourRevisionRequest::STATE_APPROVED,
                    'decision_reason' => 'Initial publication approved by the reviewer.',
                    'decided_by' => (int) $actor->id,
                    'decided_at' => now(),
                ]);
                $this->audit->log($actor, 'tour.changes.approved', $lockedTour,
                    ['request_state' => $beforeState], ['request_state' => TourRevisionRequest::STATE_APPROVED],
                    ['request_id' => $request->id, 'revision_id' => $revision->id]);
            });

        TourRevisionRequest::query()
            ->forTour((int) $lockedTour->id)
            ->where('state', TourRevisionRequest::STATE_OPEN)
            ->where('context', TourRevisionRequest::CONTEXT_PUBLISHED_REVIEW)
            ->each(function (TourRevisionRequest $req): void {
                $req->update([
                    'state' => TourRevisionRequest::STATE_SUPERSEDED,
                    'decision_reason' => 'The tour was re-published; prior review requests were superseded.',
                    'decided_at' => now(),
                ]);
            });

        Tour::query()->whereKey($lockedTour->id)->update([
            'published_revision_id' => $revision->id,
            'content_version' => DB::raw('content_version + 1'),
        ]);

        $lockedTour->refresh();

        return $revision->refresh();
    }

    /** Reject the exact current proposed revision with a mandatory reason. */
    public function rejectRevision(Tour|int $tour, int $revisionId, User $actor, string $reason): TourContentRevision
    {
        $outcome = $this->runTransaction(function () use ($tour, $revisionId, $actor, $reason): array {
            $freshActor = $this->authorizeAdmin($actor, 'manage_tours', 'You are not authorized to reject tour content revisions.');
            $actorId = (int) $freshActor->id;
            $trimmed = trim($reason);

            if ($trimmed === '' || mb_strlen($trimmed) > 2000) {
                return ['refusal' => $this->refusal(
                    TourRevisionException::UNKNOWN_FIELD_REFERENCE,
                    [],
                    ['reason' => 'A rejection reason of 1-2000 characters is required.'],
                    TourRevisionException::STATUS_INVALID,
                )];
            }

            $locked = $this->lockTourForModeration($tour);
            $freshActor = $this->authorizeAdmin($actor, 'manage_tours', 'You are not authorized to reject tour content revisions.');
            $candidate = $this->lockRevision($revisionId, (int) $locked->id);

            if ($candidate === null) {
                throw new NotFoundHttpException('Revision not found.');
            }

            if ((int) $candidate->tour_id !== (int) $locked->id) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'revision_id' => $revisionId,
                ])];
            }

            if ($candidate->state === TourContentRevision::STATE_SUPERSEDED) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_SUPERSEDED, [
                    'revision_id' => $candidate->id,
                ])];
            }

            if ($candidate->state !== TourContentRevision::STATE_PROPOSED) {
                return ['refusal' => $this->refusal($this->terminalCode($candidate->state), [
                    'revision_id' => $candidate->id,
                    'revision_state' => $candidate->state,
                ])];
            }

            if ($locked->status !== 'published') {
                $this->supersedeCandidate($candidate, $locked, TourRevisionException::REVISION_SUPERSEDED, null);
                $this->supersedeLinkedRequests($candidate);
                $this->auditReconciliation($locked);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_SUPERSEDED, [
                    'tour_id' => (int) $locked->id,
                    'required_state' => 'published',
                    'superseded_revision_id' => $candidate->id,
                ])];
            }

            if (! $locked->hasEligiblePublicOwner()) {
                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'tour_id' => (int) $locked->id,
                    'required_owner_status' => 'approved',
                ])];
            }

            $base = $this->captureBaselineLocked($locked);
            $baseId = (int) ($base->id ?? 0);

            if ((int) $candidate->base_revision_id !== $baseId) {
                $this->supersedeCandidate($candidate, $locked, TourRevisionException::REVISION_CONFLICT, null);
                $this->supersedeLinkedRequests($candidate);
                $this->auditReconciliation($locked);

                return ['refusal' => $this->refusal(TourRevisionException::REVISION_CONFLICT, [
                    'expected_base_revision_id' => $baseId,
                    'superseded_revision_id' => $candidate->id,
                ])];
            }

            $beforeState = $candidate->state;

            $candidate->update([
                'state' => TourContentRevision::STATE_REJECTED,
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);

            TourRevisionRequest::query()
                ->forTour((int) $locked->id)
                ->where('revision_id', $candidate->id)
                ->whereIn('state', [TourRevisionRequest::STATE_OPEN, TourRevisionRequest::STATE_ANSWERED])
                ->update([
                    'state' => TourRevisionRequest::STATE_REJECTED,
                    'decision_reason' => $trimmed,
                    'decided_by' => $actorId,
                    'decided_at' => now(),
                ]);

            $this->audit->log(
                $freshActor->fresh() ?? $freshActor,
                'tour.revision.rejected',
                $locked,
                ['revision_state' => $beforeState],
                ['revision_state' => TourContentRevision::STATE_REJECTED],
                ['revision_id' => $candidate->id, 'reason' => $trimmed],
            );

            return ['revision' => $candidate->refresh()];
        });

        $this->throwRefusal($outcome['refusal'] ?? null);

        return $outcome['revision'];
    }

    // --------------------------------------------------------------------
    // Lifecycle reconciliation
    // --------------------------------------------------------------------

    /**
     * Every exit from `published` makes an approval impossible and marks the
     * active candidate and open published-context requests superseded. Also
     * callable at the known lifecycle service boundaries; security never
     * depends on eager invalidation because every governance read/mutation
     * reconciles first.
     */
    public function reconcileLifecycle(Tour|int $tour): void
    {
        $this->runTransaction(function () use ($tour): void {
            $locked = $this->lockTour($tour instanceof Tour ? $tour->id : $tour);

            $this->reconcileLifecycleLocked($locked);
        });
    }

    /**
     * Reconcile an already-locked tour. Returns true when a supersession
     * happened so the caller can audit once.
     */
    public function reconcileLifecycleLocked(Tour $locked): bool
    {
        if ($locked->status === 'published') {
            return false;
        }

        $changed = false;

        $candidates = TourContentRevision::query()
            ->forTour((int) $locked->id)
            ->whereIn('state', [TourContentRevision::STATE_DRAFT, TourContentRevision::STATE_PROPOSED])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $candidate) {
            $candidate->update(['state' => TourContentRevision::STATE_SUPERSEDED, 'decided_at' => now()]);
            $this->supersedeLinkedRequests($candidate, TourRevisionRequest::CONTEXT_PUBLISHED_REVIEW);
            $changed = true;
        }

        $openPublished = TourRevisionRequest::query()
            ->forTour((int) $locked->id)
            ->where('context', TourRevisionRequest::CONTEXT_PUBLISHED_REVIEW)
            ->where('state', TourRevisionRequest::STATE_OPEN)
            ->orderBy('id')
            ->get();

        foreach ($openPublished as $request) {
            $request->update([
                'state' => TourRevisionRequest::STATE_SUPERSEDED,
                'decision_reason' => 'The tour is no longer published; the request was superseded.',
                'decided_at' => now(),
            ]);
        }

        $openCorrections = TourRevisionRequest::query()
            ->forTour((int) $locked->id)
            ->where('context', TourRevisionRequest::CONTEXT_CORRECTION)
            ->where('state', TourRevisionRequest::STATE_OPEN)
            ->orderBy('id')
            ->get();

        foreach ($openCorrections as $request) {
            $request->update([
                'state' => TourRevisionRequest::STATE_SUPERSEDED,
                'decision_reason' => 'The tour is no longer published; the correction was superseded.',
                'decided_at' => now(),
            ]);
        }

        return $changed || $openPublished->isNotEmpty() || $openCorrections->isNotEmpty();
    }

    /** System audit for a supersession without an actor. */
    public function auditReconciliation(Tour $locked): void
    {
        $this->audit->logSystem('tour.governance.reconciled', $locked, null, [
            'tour_status' => $locked->status,
        ]);
    }

    // --------------------------------------------------------------------
    // Published operational save guard
    // --------------------------------------------------------------------

    /**
     * The partner published-PUT fence (design v3 §4): under a fresh Tour lock,
     * compare normalized source aliases, all governed presentation fields,
     * media and option text against the CURRENT live approved values. Any
     * changed governed value refuses the whole atomic save with a 422
     * `content_requires_revision` and no operational sub-write. Unchanged
     * round-tripped content may accompany a legitimate operational save.
     *
     * @param  array<string, mixed>  $data  validated operational request data
     * @return array<string, mixed> the operational-only payload safe to persist
     */
    public function governedDiffForOperationalSave(Tour $lockedTour, array $data): array
    {
        $sourceLocale = $lockedTour->sourceLocale();

        $sourcePatch = TourContentRules::normalizeSourcePatch($data, $sourceLocale);

        $presentationPatch = $this->governedPresentationPatch($data);

        $optionsPatch = $this->governedOptionsPatch($lockedTour, $data);

        $mediaPatch = null;
        if (array_key_exists('media', $data) && is_array($data['media'])) {
            $mediaPatch = $data['media'];
        }

        $base = $this->captureBaselineLocked($lockedTour);
        $basePayload = $base->payload ?? $this->payloads->captureSnapshot($lockedTour);

        $patch = [];

        if ($sourcePatch !== []) {
            $patch['source'] = $sourcePatch;
        }
        if ($presentationPatch !== []) {
            $patch['presentation'] = $presentationPatch;
        }
        if ($optionsPatch !== null) {
            $patch['options'] = $optionsPatch;
        }
        if ($mediaPatch !== null) {
            $patch['media'] = $mediaPatch;
        }

        $requested = $this->payloads->mergePatch($patch, $basePayload, $lockedTour);

        $paths = [];

        foreach ($this->payloads->diff($basePayload, $requested) as $path => $change) {
            $paths[$path] = sprintf('%s changed (from "%s" to "%s").', $path, $this->summarize($change['before']), $this->summarize($change['after']));
        }

        $this->assertOptionStructureUnchanged($basePayload, $lockedTour, $data);

        if ($paths !== []) {
            throw TourRevisionException::invalid(
                TourRevisionException::CONTENT_REQUIRES_REVISION,
                $paths,
                null,
                ['tour_id' => (int) $lockedTour->id, 'base_revision_id' => $base?->id],
            );
        }

        $governedKeys = [
            'translations',
            'title',
            'description',
            'highlights',
            'inclusions',
            'exclusions',
            'meeting_point',
            'cancellation_policy',
            'important_information',
            'itinerary',
            'category_id',
            'category',
            'slug',
            'location',
            'destination',
            'location_slug',
            'duration_minutes',
            'duration_label',
            'duration_value',
            'duration_unit',
            'group_size_min',
            'group_size_max',
            'difficulty_level',
            'guide_languages',
            'languages',
            'cover_image_url',
            'media',
            'source',
            'presentation',
        ];

        return array_diff_key($data, array_flip($governedKeys));
    }

    /**
     * Published option structure fails closed (no A3): ids, identity, order,
     * additions, reactivations, retirements and reordering are refused. Only
     * unchanged text on the exact active IDs in order round-trips.
     *
     * @param  array<string, mixed>  $basePayload
     */
    private function assertOptionStructureUnchanged(array $basePayload, Tour $lockedTour, array $data): void
    {
        if (! array_key_exists('options', $data) || ! is_array($data['options'])) {
            return;
        }

        // The required set is the CURRENT live active option set in display
        // order (design v3 §1/§4): additions, reactivations, retirements and
        // reordering are all refused, exactly like the approved snapshot.
        $baseIds = array_map('strval', array_keys($this->payloads->optionsProjected($lockedTour)));

        $requested = TourContentRules::normalizeOptions(array_values($data['options']));

        $requestedIds = [];

        foreach ($requested as $item) {
            $requestedIds[] = isset($item['id']) ? (string) (int) $item['id'] : 'new';
        }

        if ($baseIds !== $requestedIds) {
            throw TourRevisionException::invalid(TourRevisionException::OPTION_STRUCTURE_NOT_SUPPORTED, [
                'options' => sprintf(
                    'Published option structure changes are refused: expected exactly %s in order.',
                    $baseIds === [] ? '(none)' : implode(', ', $baseIds),
                ),
            ], null, ['tour_id' => (int) $lockedTour->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function governedPresentationPatch(array $data): array
    {
        $patch = [];

        $map = [
            'category_id' => ['category_id'],
            'slug' => ['slug'],
            'location' => ['location', 'destination'],
            'location_slug' => ['location_slug'],
            'duration_minutes' => ['duration_minutes', 'duration_value', 'duration_unit'],
            'duration_label' => ['duration_label'],
            'group_size_min' => ['group_size_min'],
            'group_size_max' => ['group_size_max'],
            'difficulty_level' => ['difficulty_level'],
            'guide_languages' => ['guide_languages', 'languages'],
            'cover_image_url' => ['cover_image_url'],
        ];

        if (array_key_exists('duration_minutes', $data) || array_key_exists('duration_value', $data) || array_key_exists('duration_unit', $data)) {
            $minutes = $data['duration_minutes'] ?? (
                is_numeric($data['duration_value'] ?? null)
                    ? (($data['duration_unit'] ?? 'hour') === 'day' ? (int) $data['duration_value'] * 1440 : (int) $data['duration_value'] * 60)
                    : null
            );
            $patch['duration_minutes'] = $minutes;

            if (array_key_exists('duration_label', $data)) {
                $patch['duration_label'] = $data['duration_label'];
            }
        }

        foreach ($map as $field => $aliases) {
            if ($field === 'duration_minutes') {
                continue;
            }

            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $data)) {
                    $patch[$field] = $data[$alias];

                    break;
                }
            }
        }

        if (array_key_exists('category', $data) && ! array_key_exists('category_id', $patch)) {
            $patch['category'] = $data['category'];
        }

        return $patch;
    }

    /**
     * The option-text tier the partner PUT supplies. Null when `options` is
     * absent (nothing governed changed); structured commercial values are
     * ignored here and handled by the operational path.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function governedOptionsPatch(Tour $lockedTour, array $data): ?array
    {
        if (! array_key_exists('options', $data) || ! is_array($data['options'])) {
            return null;
        }

        $base = $this->captureBaselineLocked($lockedTour);
        $baseOptions = is_array($base?->payload['options'] ?? null) ? $base->payload['options'] : [];

        $requested = TourContentRules::normalizeOptions(array_values($data['options']));

        $patch = [];

        foreach ($requested as $item) {
            $optionId = isset($item['id']) ? (string) (int) $item['id'] : null;

            if ($optionId === null) {
                continue;
            }

            $patch[$optionId] = [
                'title' => $item['title'],
                'pickup_instructions' => $item['pickup_instructions'],
            ];
        }

        // Preserve untouched base entries so the merged patch equals the base
        // when the partner round-trips its displayed list.
        foreach ($baseOptions as $optionId => $entry) {
            $patch[$optionId] ??= [
                'title' => $entry['title'] ?? null,
                'pickup_instructions' => $entry['pickup_instructions'] ?? null,
            ];
        }

        return $patch;
    }

    private function summarize(mixed $value): string
    {
        if (is_string($value)) {
            return Str::limit($value, 60, '…');
        }

        if (is_array($value)) {
            return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'array', 60, '…');
        }

        return Str::limit(var_export($value, true), 60, '…');
    }

    // --------------------------------------------------------------------
    // Owner-facing projections
    // --------------------------------------------------------------------

    /**
     * The owned `content_governance` projection (design v3 §6): real epoch,
     * nullable approved ID, nullable active candidate metadata and request
     * summaries. Provider, canonical/content hashes and revision internals
     * never leave the boundary.
     *
     * @return array<string, mixed>
     */
    public function governancePayload(Tour $tour): array
    {
        $base = $tour->published_revision_id !== null ? $this->currentApproved($tour) : null;

        $candidate = $this->activeCandidate($tour);
        $latest = TourContentRevision::query()->forTour((int) $tour->id)->orderByDesc('revision_number')->first();

        return [
            'content_governance' => [
                'source_locale' => $tour->sourceLocale(),
                'generation_epoch' => $tour->generationEpoch(),
                'content_version' => $tour->contentVersion(),
                'approved_revision' => $base === null ? null : [
                    'id' => $base->id,
                    'revision_number' => $base->revision_number,
                    'kind' => $base->kind,
                    'state' => 'approved',
                    'payload_hash' => $base->payload_hash,
                    'created_at' => $base->created_at?->toIso8601String(),
                ],
                'active_candidate' => $candidate === null ? null : [
                    'id' => $candidate->id,
                    'revision_number' => $candidate->revision_number,
                    'kind' => $candidate->kind,
                    'state' => $candidate->state,
                    'payload_hash' => $candidate->payload_hash,
                    'base_revision_id' => $candidate->base_revision_id,
                    'change_summary' => $candidate->change_summary,
                    'submitted_at' => $candidate->submitted_at?->toIso8601String(),
                    'created_at' => $candidate->created_at?->toIso8601String(),
                ],
                'latest_revision' => $latest === null ? null : $this->revisionSummary($latest),
                'open_requests' => $this->openRequestsSummary($tour),
            ],
        ];
    }

    /**
     * The full revision payload a partner/admin view reloads: payload, hash,
     * kind/state, base id, timestamps, reasons/fields and the normalized diff
     * versus the base. Only the payload hash is a replacement token; canonical
     * source/content hashes and provider diagnostics stay internal.
     *
     * @return array<string, mixed>
     */
    public function revisionPayload(Tour $tour, TourContentRevision $revision): array
    {
        if ((int) $revision->tour_id !== (int) $tour->id) {
            throw new NotFoundHttpException('Revision not found.');
        }

        $base = $revision->base_revision_id === null
            ? null
            : TourContentRevision::query()->forTour((int) $tour->id)->find($revision->base_revision_id);

        if ($revision->base_revision_id !== null && $base === null) {
            throw TourRevisionException::conflict(TourRevisionException::REVISION_CONFLICT);
        }

        $requests = TourRevisionRequest::query()->forTour((int) $tour->id)
            ->where('revision_id', $revision->id)->orderBy('id')
            ->limit(self::OPEN_REQUEST_SUMMARY_LIMIT)->get()
            ->map(fn (TourRevisionRequest $request): array => $this->requestPayload($tour, $request))->all();

        $diff = $base === null
            ? []
            : $this->payloads->diff($base->payload ?? [], $revision->payload ?? []);

        $wirePayload = $revision->payload;
        if (isset($wirePayload['options']) && is_array($wirePayload['options'])) {
            $wireOptions = [];
            foreach ($wirePayload['options'] as $optionId => $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $id = isset($entry['id']) && (is_int($entry['id']) || ctype_digit((string) $entry['id']))
                    ? (int) $entry['id']
                    : (int) $optionId;
                $wireOptions[] = [
                    'id' => $id,
                    'title' => $entry['title'] ?? null,
                    'pickup_instructions' => $entry['pickup_instructions'] ?? null,
                ];
            }
            $wirePayload['options'] = $wireOptions;
        }

        return [
            'id' => $revision->id,
            'tour_id' => $revision->tour_id,
            'revision_number' => $revision->revision_number,
            'kind' => $revision->kind,
            'state' => $revision->state,
            'source_locale' => $revision->source_locale,
            'change_summary' => $revision->change_summary,
            'payload' => $wirePayload,
            'payload_hash' => $revision->payload_hash,
            'base_revision_id' => $revision->base_revision_id,
            'created_by' => $revision->created_by,
            'submitted_at' => $revision->submitted_at?->toIso8601String(),
            'decided_by' => $revision->decided_by,
            'decided_at' => $revision->decided_at?->toIso8601String(),
            'created_at' => $revision->created_at?->toIso8601String(),
            'diff' => $diff,
            'requests' => $requests,
            'generation_epoch' => $tour->generationEpoch(),
        ];
    }

    public function ownedRevisionPayload(int $tourId, int $partnerId, int $revisionId): array
    {
        return $this->withOwnedTour($tourId, $partnerId, function (Tour $tour) use ($revisionId): array {
            $revision = $this->findRevision($tour, $revisionId);
            if ($revision === null) {
                throw new NotFoundHttpException('Revision not found.');
            }

            return $this->revisionPayload($tour, $revision);
        });
    }

    public function ownedRequestPayload(int $tourId, int $partnerId, int $requestId): array
    {
        return $this->withOwnedTour($tourId, $partnerId, function (Tour $tour) use ($requestId): array {
            $request = TourRevisionRequest::query()->forTour($tour->id)->whereKey($requestId)->firstOrFail();

            return $this->requestPayload($tour, $request);
        });
    }

    public function ownedRevisionHistory(int $tourId, int $partnerId, int $perPage): array
    {
        return $this->withOwnedTour($tourId, $partnerId, fn (Tour $tour): array => $this->paginationPayload(
            $this->paginateRevisions($tour, $perPage),
            fn (TourContentRevision $revision): array => $this->revisionSummary($revision),
        ));
    }

    public function ownedRequestHistory(int $tourId, int $partnerId, int $perPage): array
    {
        return $this->withOwnedTour($tourId, $partnerId, fn (Tour $tour): array => $this->paginationPayload(
            $this->paginateRequests($tour, $perPage),
            fn (TourRevisionRequest $request): array => $this->requestPayload($tour, $request),
        ));
    }

    private function paginationPayload(LengthAwarePaginator $paginator, callable $projection): array
    {
        return [
            'data' => array_map($projection, $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            ],
        ];
    }

    public function paginateRevisions(Tour $tour, int $perPage = 20): LengthAwarePaginator
    {
        return TourContentRevision::query()
            ->where('tour_id', $tour->id)
            ->orderByDesc('revision_number')
            ->paginate($perPage);
    }

    public function findRevision(Tour $tour, int $revisionId): ?TourContentRevision
    {
        return TourContentRevision::query()
            ->where('tour_id', $tour->id)
            ->whereKey($revisionId)
            ->first();
    }

    public function paginateRequests(Tour $tour, int $perPage = 20): LengthAwarePaginator
    {
        return TourRevisionRequest::query()
            ->where('tour_id', $tour->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function revisionSummary(TourContentRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'tour_id' => $revision->tour_id,
            'revision_number' => $revision->revision_number,
            'kind' => $revision->kind,
            'state' => $revision->state,
            'payload_hash' => $revision->payload_hash,
            'base_revision_id' => $revision->base_revision_id,
            'change_summary' => $revision->change_summary,
            'submitted_at' => $revision->submitted_at?->toIso8601String(),
            'decided_at' => $revision->decided_at?->toIso8601String(),
            'created_at' => $revision->created_at?->toIso8601String(),
        ];
    }

    public function openRequestsSummary(Tour $tour): array
    {
        return TourRevisionRequest::query()
            ->forTour((int) $tour->id)
            ->where('state', TourRevisionRequest::STATE_OPEN)
            ->orderByDesc('id')
            ->limit(self::OPEN_REQUEST_SUMMARY_LIMIT)
            ->get()
            ->map(fn (TourRevisionRequest $req) => $this->requestPayload($tour, $req))
            ->all();
    }

    private function assertPartnerMutationAccess(Tour $locked, int $actorId): void
    {
        if ($actorId <= 0) {
            throw new NotFoundHttpException('Tour not found.');
        }

        $partner = Partner::query()->where('user_id', $actorId)->first();

        if ($partner === null || (int) $partner->id !== (int) $locked->partner_id) {
            throw new NotFoundHttpException('Tour not found.');
        }

        $actor = User::query()->find($actorId);

        if ($actor === null || $actor->role !== 'partner' || ($actor->status ?? 'active') !== 'active') {
            throw new AccessDeniedHttpException('Partner account is not eligible for content mutations.');
        }

        $status = $partner->onboarding_status;
        if ($status === 'incomplete') {
            $status = 'pending';
        }

        if (! (($status === 'approved' || $status === 'complete') && (bool) $partner->is_active)) {
            throw new AccessDeniedHttpException('Partner account is not approved for content mutations.');
        }
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, string>  $paths
     * @return array{errorCode: string, identity: array<string, mixed>, paths: array<string, string>, status: int, message: ?string}
     */
    private function refusal(
        string $errorCode,
        array $identity = [],
        array $paths = [],
        int $status = TourRevisionException::STATUS_CONFLICT,
        ?string $message = null,
    ): array {
        return [
            'errorCode' => $errorCode,
            'identity' => $identity,
            'paths' => $paths,
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * @param  array{errorCode: string, identity?: array<string, mixed>, paths?: array<string, string>, status?: int, message?: ?string}|null  $refusal
     */
    private function throwRefusal(?array $refusal): void
    {
        if ($refusal === null) {
            return;
        }

        $status = $refusal['status'] ?? TourRevisionException::STATUS_CONFLICT;
        $errorCode = $refusal['errorCode'];
        $paths = $refusal['paths'] ?? [];
        $identity = $refusal['identity'] ?? [];
        $message = $refusal['message'] ?? null;

        if ($status === TourRevisionException::STATUS_INVALID) {
            throw TourRevisionException::invalid($errorCode, $paths, $message, $identity);
        }

        throw TourRevisionException::conflict($errorCode, $message, $paths, $identity);
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPayload(Tour $tour, TourRevisionRequest $request): array
    {
        return [
            'id' => $request->id,
            'tour_id' => $request->tour_id,
            'context' => $request->context,
            'base_revision_id' => $request->base_revision_id,
            'revision_id' => $request->revision_id,
            'state' => $request->state,
            'reason' => $request->reason,
            'field_refs' => $request->field_refs,
            'response_note' => $request->response_note,
            'response_generation_epoch' => $request->response_generation_epoch,
            'requested_by' => $request->requested_by,
            'responded_by' => $request->responded_by,
            'responded_at' => $request->responded_at?->toIso8601String(),
            'decided_by' => $request->decided_by,
            'decided_at' => $request->decided_at?->toIso8601String(),
            'decision_reason' => $request->decision_reason,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    // --------------------------------------------------------------------
    // Locks and transaction helper
    // --------------------------------------------------------------------

    /**
     * Tour-first content lock (never a partner lock) for partner proposal,
     * draft and other non-publication content operations.
     */
    private function lockTourForPartnerMutation(Tour|int $tour, int $actorId): Tour
    {
        $locked = $this->lockTour($this->tourId($tour));
        $this->assertPartnerMutationAccess($locked, $actorId);
        $this->reconcileOnGovernanceAccess($locked);
        $this->lockGovernanceRows($locked);

        return $locked;
    }

    /**
     * @template T
     *
     * @param  callable(Tour): T  $projection
     * @return T
     */
    public function withOwnedTour(int $tourId, int $partnerId, callable $projection): mixed
    {
        return $this->runTransaction(function () use ($tourId, $partnerId, $projection) {
            $locked = $this->lockTour($tourId);
            if ((int) $locked->partner_id !== $partnerId) {
                throw new NotFoundHttpException('Tour not found.');
            }
            $this->reconcileOnGovernanceAccess($locked);
            $this->lockGovernanceRows($locked);
            if ($locked->status === 'published') {
                $this->captureBaselineLocked($locked);
            }

            return $projection($locked);
        });
    }

    public function lockTourForPartnerContent(Tour|int $tour): Tour
    {
        $locked = $this->lockTour($tour instanceof Tour ? $tour->id : $tour);
        $this->reconcileOnGovernanceAccess($locked);
        $this->lockGovernanceRows($locked);

        return $locked;
    }

    /**
     * Correction/moderation boundary: partners are locked in stable ID order
     * BEFORE the Tour (current owner first, then any proposed-owner actor),
     * then the Tour, then the governance rows.
     */
    public function lockTourForModeration(Tour|int $tour): Tour
    {
        $snapshot = $tour instanceof Tour ? $tour : Tour::query()->findOrFail($tour);
        // Reuse the publication guard's owner-first lock and post-lock owner
        // comparison: a transfer while waiting must fail closed with 409.
        $locked = $this->publicationGuard->lockTourForMutation($snapshot);
        $this->reconcileOnGovernanceAccess($locked);
        $this->lockGovernanceRows($locked);

        return $locked;
    }

    /**
     * Reconciliation on governance access (design v3 §4): an exit from
     * `published` makes a later approval impossible, so the very next
     * governance read/mutation supersedes the live candidate and its open
     * published-context requests. Security never depends on this eager call —
     * every lifecycle operation also rechecks the fresh status under its own
     * lock — the reconciliation just stops stale candidates from lingering.
     */
    private function reconcileOnGovernanceAccess(Tour $locked): void
    {
        if ($locked->status === 'published') {
            return;
        }

        $changed = $this->reconcileLifecycleLocked($locked);

        if ($changed) {
            $this->auditReconciliation($locked);
        }
    }

    /**
     * Reconcile a tour in one locked step and run the caller's mutation under
     * the same locks (used by lifecycle exits owned by this service).
     *
     * @template T
     *
     * @param  callable(Tour): T  $mutation
     * @return T
     */
    public function runReconciliationUnderLock(Tour|int $tour, callable $mutation): mixed
    {
        return $this->runTransaction(function () use ($tour, $mutation) {
            $locked = $this->lockTourForModeration($this->tourId($tour));

            return $mutation($locked);
        });
    }

    /**
     * The tour id of a raw tour value.
     */
    private function tourId(Tour|int $tour): int
    {
        return $tour instanceof Tour ? (int) $tour->id : (int) $tour;
    }

    /**
     * A fresh, unlocked tour model (used when no lock is required).
     */
    private function tourModel(Tour|int $tour): Tour
    {
        return $tour instanceof Tour ? $tour : Tour::query()->findOrFail($this->tourId($tour));
    }

    /**
     * Re-read the persisted actor so a revoke or suspension applies
     * immediately; a deleted actor is recorded as an attributed id only.
     */
    private function resolveActor(int $actorId): ?User
    {
        return User::query()->find($actorId);
    }

    /**
     * Audit once with a re-read actor; a persisted actor id whose user row no
     * longer matches records the event as an automated action instead of
     * failing the governed mutation.
     */
    private function auditActor(?User $actor, string $action, Model $target, ?array $before, ?array $after, array $metadata = []): void
    {
        if ($actor !== null) {
            $this->audit->log($actor, $action, $target, $before, $after, $metadata);

            return;
        }

        $this->audit->logSystem($action, $target, $before, $after, $metadata);
    }

    /**
     * Tour row plus the authored source and the derivative states in stable
     * platform order — the shared content lock, without opening a transaction
     * so a caller that already holds an outer transaction keeps its ordering.
     */
    private function lockTour(int $tourId): Tour
    {
        $tour = Tour::query()->whereKey($tourId)->lockForUpdate()->firstOrFail();

        $sourceLocale = $tour->sourceLocale();

        TourTranslation::query()->where('tour_id', $tourId)->where('locale', $sourceLocale)->lockForUpdate()->first();

        foreach (Tour::derivativeLocalesFor($sourceLocale) as $locale) {
            TourTranslationState::query()->where('tour_id', $tourId)->where('locale', $locale)->lockForUpdate()->first();
        }

        return $tour;
    }

    private function lockGovernanceRows(Tour $locked): void
    {
        // Locked in stable id order so two actors never deadlock on the same
        // tour's revision/request rows.
        TourContentRevision::query()
            ->forTour((int) $locked->id)
            ->whereIn('state', [
                TourContentRevision::STATE_DRAFT,
                TourContentRevision::STATE_PROPOSED,
                TourContentRevision::STATE_APPROVED,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        TourRevisionRequest::query()
            ->forTour((int) $locked->id)
            ->whereNotIn('state', [TourRevisionRequest::STATE_CLOSED])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function lockRevision(int $revisionId, int $tourId): ?TourContentRevision
    {
        return TourContentRevision::query()->forTour($tourId)->whereKey($revisionId)->lockForUpdate()->first();
    }

    /**
     * Own a transaction or nested savepoint. A SQL failure must roll back this
     * mutation even when its caller already owns an outer transaction.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function runTransaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /**
     * Run inside the governance transaction boundary of THIS service, so an
     * owner-facing read (GET) and its lazy baseline capture observe one locked
     * snapshot. Public because service-owning entry points delegate here.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runGovernanceTransaction(callable $callback): mixed
    {
        return $this->runTransaction($callback);
    }

    // --------------------------------------------------------------------
    // Row helpers
    // --------------------------------------------------------------------

    private function insertRevision(
        Tour $tour,
        string $kind,
        string $state,
        array $payload,
        ?int $createdBy,
        ?int $baseRevisionId = null,
        ?string $contentHash = null,
        ?string $changeSummary = null,
    ): TourContentRevision {
        $next = TourContentRevision::query()
            ->forTour((int) $tour->id)
            ->max('revision_number');

        // The stored content hash is the canonical identity OF THIS SNAPSHOT, so
        // a candidate whose source text differs from the live rows hashes as
        // itself and never borrows the live identity.
        $contentHash ??= $this->payloads->canonicalHashOfPayload($payload);
        $payloadHash = $this->payloads->hashPayload($payload);
        $normalizedSummary = $changeSummary !== null && trim($changeSummary) !== ''
            ? trim($changeSummary)
            : null;

        try {
            $revision = TourContentRevision::create([
                'tour_id' => (int) $tour->id,
                'revision_number' => ((int) ($next ?? 0)) + 1,
                'kind' => $kind,
                'state' => $state,
                'source_locale' => $tour->sourceLocale(),
                'payload' => $payload,
                'payload_hash' => $payloadHash,
                'content_hash' => $contentHash,
                'change_summary' => $normalizedSummary,
                'base_revision_id' => $baseRevisionId && $baseRevisionId > 0 ? $baseRevisionId : null,
                'created_by' => $createdBy,
            ]);
        } catch (QueryException $exception) {
            // Duplicate live candidate (23505 on the partial unique index):
            // another actor opened one first.
            if (($exception->errorInfo[0] ?? null) === '23505') {
                throw TourRevisionException::conflict(TourRevisionException::REVISION_ALREADY_OPEN);
            }

            throw $exception;
        }

        return $revision;
    }

    /**
     * Mark a candidate superseded with its terminal reason. The supersession
     * RESULT commits before the caller reports 409.
     */
    private function supersedeCandidate(TourContentRevision $candidate, Tour $tour, string $errorCode, ?string $decisionReason): void
    {
        if (! $candidate->isLive()) {
            return;
        }

        $candidate->update([
            'state' => TourContentRevision::STATE_SUPERSEDED,
            'decided_by' => $candidate->decided_by,
            'decided_at' => now(),
        ]);

        $this->audit->logSystem('tour.revision.superseded', $tour, ['revision_state' => $candidate->getOriginal('state')], [
            'revision_id' => $candidate->id,
            'reason_code' => $errorCode,
            'decision_reason' => $decisionReason,
        ]);
    }

    private function supersedeOtherCandidates(Tour $tour, TourContentRevision $approved): void
    {
        TourContentRevision::query()
            ->forTour((int) $tour->id)
            ->whereKeyNot($approved->id)
            ->whereIn('state', [TourContentRevision::STATE_DRAFT, TourContentRevision::STATE_PROPOSED])
            ->orderBy('id')
            ->each(function (TourContentRevision $candidate) use ($tour): void {
                $candidate->update(['state' => TourContentRevision::STATE_SUPERSEDED]);
                $this->supersedeLinkedRequests($candidate);
                $this->audit->logSystem('tour.revision.superseded', $tour, ['revision_state' => $candidate->getOriginal('state')], [
                    'revision_id' => $candidate->id,
                    'reason_code' => TourRevisionException::REVISION_SUPERSEDED,
                ]);
            });
    }

    /**
     * Linked answered/correction requests of a candidate become superseded
     * history. Unlinked open requests remain open.
     */
    private function supersedeLinkedRequests(TourContentRevision $candidate, ?string $context = null): void
    {
        $query = TourRevisionRequest::query()
            ->forTour((int) $candidate->tour_id)
            ->where(function ($q) use ($candidate): void {
                $q->where('revision_id', $candidate->id)
                    ->orWhere('base_revision_id', $candidate->id);
            })
            ->orderBy('id');

        if ($context !== null) {
            $query->where('context', $context);
        }

        $query->each(function (TourRevisionRequest $request): void {
            if (! in_array($request->state, [TourRevisionRequest::STATE_OPEN, TourRevisionRequest::STATE_ANSWERED], true)) {
                return;
            }

            $request->update([
                'state' => TourRevisionRequest::STATE_SUPERSEDED,
                'decision_reason' => 'The linked revision was superseded.',
                'decided_at' => now(),
            ]);
        });
    }

    private function closeRequestsOnApproval(Tour $tour, TourContentRevision $approved, int $actorId): void
    {
        // Linked answered requests are approved.
        TourRevisionRequest::query()
            ->forTour((int) $tour->id)
            ->where('revision_id', $approved->id)
            ->where('state', TourRevisionRequest::STATE_ANSWERED)
            ->orderBy('id')
            ->update([
                'state' => TourRevisionRequest::STATE_APPROVED,
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);

        // Linked open correction requests on the approved revision are approved.
        TourRevisionRequest::query()
            ->forTour((int) $tour->id)
            ->where('revision_id', $approved->id)
            ->where('state', TourRevisionRequest::STATE_OPEN)
            ->orderBy('id')
            ->update([
                'state' => TourRevisionRequest::STATE_APPROVED,
                'decision_reason' => sprintf('Correction revision %d was approved.', $approved->id),
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);

        // Other open requests on the same base close with an explicit approval
        // decision reason without claiming a partner response. Requests linked
        // to another candidate were already superseded with it.
        TourRevisionRequest::query()
            ->forTour((int) $tour->id)
            ->where('base_revision_id', $approved->base_revision_id)
            ->where('state', TourRevisionRequest::STATE_OPEN)
            ->where(fn ($q) => $q->whereNull('revision_id')->orWhereNot('revision_id', $approved->id))
            ->orderBy('id')
            ->update([
                'state' => TourRevisionRequest::STATE_CLOSED,
                'decision_reason' => sprintf('Revision %d was approved.', $approved->id),
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);
    }

    private function terminalCode(string $state): string
    {
        return $state === TourContentRevision::STATE_SUPERSEDED
            ? TourRevisionException::REVISION_SUPERSEDED
            : TourRevisionException::REVISION_TERMINAL;
    }
}
