<?php

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes append-only governance audit entries (Spec 013, FR-011/FR-012).
 *
 * All Filament governance actions and the Settings/CMS pages route state
 * mutations through `log()` so the single `governance_audit_logs` table is the
 * canonical trail. Actor/target types are stored as morph-map aliases.
 */
class GovernanceAuditService
{
    public function __construct(private readonly Request $request) {}

    /**
     * Append one immutable governance audit entry.
     *
     * @param  Model|null  $target  The governed record (Tour/Partner/Booking/
     *                              Review/StaticPage). Null for global actions
     *                              like settings.update.
     * @param  array|null  $before  Prior state snapshot.
     * @param  array|null  $after  New state snapshot.
     * @param  array  $metadata  Extra context: reason, bulk_batch_id, group, etc.
     * @param  string|null  $targetType  Override target_type for non-model
     *                                   targets (e.g. `setting`); ignored when a
     *                                   model target is supplied.
     */
    public function log(
        User $actor,
        string $action,
        ?Model $target = null,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
        ?string $targetType = null,
    ): GovernanceAuditLog {
        return $this->logActor($actor, $action, $target, $before, $after, $metadata, $targetType);
    }

    /** Append an entry for any morph-mapped authenticated domain actor. */
    public function logActor(
        Model $actor,
        string $action,
        ?Model $target = null,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
        ?string $targetType = null,
    ): GovernanceAuditLog {
        return $this->persist(
            actorType: $actor->getMorphClass(),
            actorId: (int) $actor->getKey(),
            action: $action,
            target: $target,
            before: $before,
            after: $after,
            metadata: $metadata,
            targetType: $targetType,
        );
    }

    /** Append an audit entry for a trusted automated process such as a webhook. */
    public function logSystem(
        string $action,
        ?Model $target = null,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
        ?string $targetType = null,
    ): GovernanceAuditLog {
        return $this->persist('system', null, $action, $target, $before, $after, $metadata, $targetType);
    }

    private function persist(
        string $actorType,
        ?int $actorId,
        string $action,
        ?Model $target,
        ?array $before,
        ?array $after,
        array $metadata,
        ?string $targetType,
    ): GovernanceAuditLog {
        return GovernanceAuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $target?->getMorphClass() ?? $targetType,
            'target_id' => $target?->getKey(),
            'before_state' => $before,
            'after_state' => $after,
            'metadata' => array_merge($metadata, [
                'ip' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ]),
        ]);
    }
}
