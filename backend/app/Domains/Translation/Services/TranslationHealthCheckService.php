<?php

namespace App\Domains\Translation\Services;

use App\Domains\Translation\Jobs\TranslateContentFieldJob;
use App\Domains\Translation\Models\TranslationFieldState;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Translation health monitoring + recovery (Admin 2.0 Phase H, T-H06).
 *
 * Read-only rollups for the Translation Health dashboard plus the
 * retry-failed-fields recovery used by the "translate missing fields" /
 * "retranslate outdated fields" actions on blog & static-page edit pages.
 */
class TranslationHealthCheckService
{
    private const STATUS_ORDER = [
        TranslationFieldState::STATUS_COMPLETED,
        TranslationFieldState::STATUS_OUTDATED,
        TranslationFieldState::STATUS_NEEDS_REVIEW,
        TranslationFieldState::STATUS_MANUALLY_EDITED,
        TranslationFieldState::STATUS_FAILED,
        TranslationFieldState::STATUS_MISSING,
        TranslationFieldState::STATUS_QUEUED,
        TranslationFieldState::STATUS_TRANSLATING,
    ];

    /**
     * Rollup of every TranslationFieldState row belonging to one record.
     *
     * @return array{model_type: string, model_id: int, total_fields: int, counts: array<string, int>, has_failures: bool, fields: list<array<string, mixed>>}
     */
    public function evaluateModel(string $modelType, int $modelId): array
    {
        $rows = TranslationFieldState::query()
            ->where('translatable_type', $modelType)
            ->where('translatable_id', $modelId)
            ->get();

        $counts = array_fill_keys(self::STATUS_ORDER, 0);

        foreach ($rows as $row) {
            $counts[$row->status] = ($counts[$row->status] ?? 0) + 1;
        }

        return [
            'model_type' => $modelType,
            'model_id' => $modelId,
            'total_fields' => $rows->count(),
            'counts' => $counts,
            'has_failures' => ($counts[TranslationFieldState::STATUS_FAILED] ?? 0) > 0,
            'fields' => $rows
                ->map(fn (TranslationFieldState $row): array => $this->fieldRow($row))
                ->values()
                ->all(),
        ];
    }

    /**
     * Platform-wide rollup across every tracked record: totals per status,
     * the failure rate, and the per-model breakdown for the dashboard table.
     *
     * @return array{counts: array<string, int>, total_fields: int, failure_rate: float, by_model_type: list<array<string, mixed>>}
     */
    public function platformSummary(): array
    {
        $grouped = TranslationFieldState::query()
            ->select('translatable_type', 'status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('translatable_type', 'status')
            ->get();

        $counts = array_fill_keys(self::STATUS_ORDER, 0);

        /** @var array<string, array<string, int>> $perModel */
        $perModel = [];

        foreach ($grouped as $row) {
            $type = (string) $row->translatable_type;
            $status = (string) $row->status;
            $count = (int) $row->aggregate;

            $counts[$status] = ($counts[$status] ?? 0) + $count;
            $perModel[$type] ??= array_fill_keys(self::STATUS_ORDER, 0);
            $perModel[$type][$status] = ($perModel[$type][$status] ?? 0) + $count;
        }

        $total = array_sum($counts);
        $failures = $counts[TranslationFieldState::STATUS_FAILED] ?? 0;

        $aliases = Relation::morphMap();

        $breakdown = collect($perModel)
            ->map(fn (array $modelCounts, string $type): array => [
                'model_type' => $aliases[$type] ?? $type,
                'total_fields' => array_sum($modelCounts),
                'counts' => $modelCounts,
                'has_failures' => ($modelCounts[TranslationFieldState::STATUS_FAILED] ?? 0) > 0,
            ])
            ->values()
            ->all();

        return [
            'counts' => $counts,
            'total_fields' => $total,
            'failure_rate' => $total > 0 ? round($failures / $total, 4) : 0.0,
            'by_model_type' => $breakdown,
        ];
    }

    /**
     * Re-dispatch every FAILED translation field of one record: resets the
     * state to queued and re-enqueues the worker. Returns how many fields
     * were queued.
     *
     * @return array{queued: int}
     */
    public function retryFailed(string $modelType, int $modelId): array
    {
        $failed = TranslationFieldState::query()
            ->where('translatable_type', $modelType)
            ->where('translatable_id', $modelId)
            ->where('status', TranslationFieldState::STATUS_FAILED)
            ->get();

        foreach ($failed as $state) {
            $state->status = TranslationFieldState::STATUS_QUEUED;
            $state->last_error_code = null;
            $state->last_error_message = null;
            $state->save();

            TranslateContentFieldJob::dispatch($state->translatable_type, $state->translatable_id, $state->locale, $state->field_name);
        }

        return ['queued' => $failed->count()];
    }

    /**
     * Re-dispatch every FAILED translation field across the platform, up to
     * $limit distinct records. Used by the Operational Monitoring page.
     *
     * @return array{queued: int}
     */
    public function retryAllFailures(int $limit = 50): array
    {
        $pairs = TranslationFieldState::query()
            ->select('translatable_type', 'translatable_id')
            ->where('status', TranslationFieldState::STATUS_FAILED)
            ->groupBy('translatable_type', 'translatable_id')
            ->orderBy('translatable_type')
            ->orderBy('translatable_id')
            ->limit($limit)
            ->get();

        $queued = 0;

        foreach ($pairs as $pair) {
            $result = $this->retryFailed((string) $pair->translatable_type, (int) $pair->translatable_id);
            $queued += $result['queued'];
        }

        return ['queued' => $queued];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldRow(TranslationFieldState $row): array
    {
        return [
            'id' => $row->id,
            'locale' => $row->locale,
            'field_name' => $row->field_name,
            'status' => $row->status,
            'origin' => $row->origin,
            'provider' => $row->provider,
            'model' => $row->model,
            'attempt_count' => $row->attempt_count,
            'last_error_code' => $row->last_error_code,
            'last_error_message' => $row->last_error_message,
            'last_translated_at' => $row->last_translated_at?->toIso8601String(),
        ];
    }
}
