<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill (Spec 013, US6): port existing rows from the legacy
 * `booking_audit_logs` and `review_audit_trails` tables into the unified
 * append-only `governance_audit_logs` trail.
 *
 * Idempotent: each ported row is tagged with metadata.backfilled_from +
 * metadata.source_id, and a row is skipped if a governance entry with the
 * same source already exists. Safe to re-run.
 *
 * Inserts bypass the GovernanceAuditLog model (its append-only hooks live at
 * the Eloquent layer) using the DB facade, which is appropriate for a one-off
 * historical import.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfillBookingAuditLogs();
        $this->backfillReviewAuditTrails();
    }

    public function down(): void
    {
        // Remove only the rows this migration ported; leave native governance
        // entries untouched.
        DB::table('governance_audit_logs')
            ->whereNotNull(DB::raw("metadata->>'backfilled_from'"))
            ->delete();
    }

    private function backfillBookingAuditLogs(): void
    {
        foreach (DB::table('booking_audit_logs')->orderBy('id')->get() as $row) {
            if ($this->alreadyPorted('booking_audit_logs', $row->id)) {
                continue;
            }

            $before = $row->before_state !== null ? ['status' => $row->before_state] : null;
            $after = $row->after_state !== null ? ['status' => $row->after_state] : null;
            $metadata = array_merge(
                (array) json_decode((string) $row->metadata, true),
                ['backfilled_from' => 'booking_audit_logs', 'source_id' => $row->id],
            );

            DB::table('governance_audit_logs')->insert([
                'actor_type' => $row->actor_type,
                'actor_id' => $row->actor_id !== null ? (int) $row->actor_id : null,
                'action' => 'booking.' . $row->action,
                'target_type' => 'booking',
                'target_id' => (int) $row->booking_id,
                'before_state' => $before !== null ? json_encode($before) : null,
                'after_state' => $after !== null ? json_encode($after) : null,
                'metadata' => json_encode($metadata),
                'created_at' => $row->created_at,
            ]);
        }
    }

    private function backfillReviewAuditTrails(): void
    {
        foreach (DB::table('review_audit_trails')->orderBy('id')->get() as $row) {
            if ($this->alreadyPorted('review_audit_trails', $row->id)) {
                continue;
            }

            $before = array_filter([
                'rating' => $row->old_rating,
                'comment' => $row->old_comment,
            ], fn ($v) => $v !== null) ?: null;
            $after = array_filter([
                'rating' => $row->new_rating,
                'comment' => $row->new_comment,
            ], fn ($v) => $v !== null) ?: null;

            $metadata = [
                'reason' => $row->reason,
                'backfilled_from' => 'review_audit_trails',
                'source_id' => $row->id,
            ];

            DB::table('governance_audit_logs')->insert([
                'actor_type' => $row->actor_type,
                'actor_id' => $row->actor_id !== null ? (int) $row->actor_id : null,
                'action' => 'review.' . $row->action,
                'target_type' => 'review',
                'target_id' => (int) $row->review_id,
                'before_state' => $before !== null ? json_encode($before) : null,
                'after_state' => $after !== null ? json_encode($after) : null,
                'metadata' => json_encode($metadata),
                'created_at' => $row->created_at,
            ]);
        }
    }

    private function alreadyPorted(string $sourceTable, int $sourceId): bool
    {
        return DB::table('governance_audit_logs')
            ->whereRaw("metadata->>'backfilled_from' = ?", [$sourceTable])
            ->whereRaw("metadata->>'source_id' = ?", [(string) $sourceId])
            ->exists();
    }
};