<?php

namespace App\Domains\CMS\Services;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\CMS\Models\ContentRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only content version history (Admin 2.0 Phase G, T-G03).
 *
 * Every audited content mutation saves a full attribute snapshot as the next
 * `version_number` for its `revisionable` target. Snapshots never contain
 * volatile bookkeeping columns (`created_at`/`updated_at`/`lock_version`), so
 * a restore reproduces the exact editorial state.
 */
class ContentRevisionService
{
    /**
     * Attributes excluded from snapshots — timestamps and optimistic-locking
     * counters, not editorial content.
     */
    private const SNAPSHOT_EXCLUDED_KEYS = ['created_at', 'updated_at', 'lock_version'];

    public function __construct(private readonly GovernanceAuditService $audit) {}

    /**
     * Persist the record's current attributes as the next immutable revision.
     */
    public function recordRevision(Model $record, ?User $actor, string $changeSummary): ContentRevision
    {
        $connectionName = $record->getConnectionName() ?? $record->getConnection()->getName();

        $latest = ContentRevision::query()
            ->where('revisionable_type', $record->getMorphClass())
            ->where('revisionable_id', $record->getKey())
            ->orderByDesc('version_number')
            ->lockForUpdate()
            ->first();

        $snapshot = collect($record->attributesToArray())
            ->except(self::SNAPSHOT_EXCLUDED_KEYS)
            ->all();

        $revision = ContentRevision::create([
            'revisionable_type' => $record->getMorphClass(),
            'revisionable_id' => $record->getKey(),
            'version_number' => ($latest?->version_number ?? 0) + 1,
            'snapshot' => $snapshot,
            'diff' => $this->diff($latest?->snapshot ?? [], $snapshot),
            'change_summary' => $changeSummary,
            'actor_id' => $actor?->id,
        ]);

        if ($this->hasLockVersionColumn($connectionName, $record->getTable())) {
            $next = (int) ($record->lock_version ?? 0) + 1;
            $record->lock_version = $next;

            $record->getConnection()->table($record->getTable())
                ->where($record->getKeyName(), $record->getKey())
                ->update(['lock_version' => $next]);
        }

        return $revision;
    }

    /**
     * Field-level diff entries between two consecutive snapshots.
     *
     * @param  array<string, mixed>  $older
     * @param  array<string, mixed>  $newer
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    public function diff(array $older, array $newer): array
    {
        $entries = [];

        foreach ($newer as $field => $after) {
            $before = $older[$field] ?? null;

            if ($before === $after) {
                continue;
            }

            $entries[] = [
                'field' => $field,
                'before' => $before,
                'after' => $after,
            ];
        }

        return $entries;
    }

    /**
     * Restore a record to a revision's snapshot and record the restore itself
     * as another revision, so history remains monotonic and auditable.
     */
    public function restore(ContentRevision $revision, ?User $actor): Model
    {
        $record = $revision->revisionable;

        if (! $record instanceof Model) {
            throw new \RuntimeException('The revision target no longer exists.');
        }

        $record->fill($revision->snapshot ?? []);
        $record->save();

        $restored = $this->recordRevision(
            $record,
            $actor,
            sprintf(
                'Restored revision #%d of %s #%d (%s)',
                $revision->version_number,
                $revision->revisionable_type,
                $revision->revisionable_id,
                $revision->change_summary ?? 'no summary'
            ),
        );

        if ($actor instanceof User) {
            $this->audit->log(
                actor: $actor,
                action: 'cms.revision_restore',
                target: $revision,
                before: null,
                after: [
                    'revisionable_type' => $revision->revisionable_type,
                    'revisionable_id' => $revision->revisionable_id,
                    'restored_from_version' => $revision->version_number,
                ],
                metadata: ['restored_revision_id' => $restored->id],
                targetType: 'content_revision',
            );
        }

        return $record;
    }

    private function hasLockVersionColumn(string $connectionName, string $table): bool
    {
        return in_array('lock_version', Schema::connection($connectionName)->getColumnListing($table), true);
    }
}
