<?php

namespace App\Domains\Admin\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Payment\Models\Payment;
use App\Domains\Translation\Models\TranslationFieldState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operational health rollup (Admin 2.0 Phase I, T-I02).
 *
 * One snapshot call aggregates the failure surfaces an operator needs at a
 * glance: failed translation fields, failed media-processing jobs, failed
 * payments, failed Stripe webhook events, expired bookings in the last 24h,
 * and the most recent failed queued jobs (from `failed_jobs`).
 */
class OperationalHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $webhookTable = DB::getTablePrefix() . 'stripe_webhook_events';
        $hasWebhookTable = Schema::hasTable($webhookTable);

        $failedWebhookEvents = $hasWebhookTable
            ? DB::table($webhookTable)->where('processing_status', 'failed')->count()
            : 0;

        return [
            'counts' => [
                'failed_translations' => TranslationFieldState::query()
                    ->where('status', TranslationFieldState::STATUS_FAILED)
                    ->count(),
                'failed_media_jobs' => MediaAsset::query()
                    ->where('processing_status', MediaAsset::STATUS_FAILED)
                    ->count(),
                'failed_payments' => Payment::query()
                    ->where('status', 'failed')
                    ->count(),
                'failed_webhook_events' => $failedWebhookEvents,
                'expired_bookings_24h' => Booking::query()
                    ->where('status', Booking::STATUS_EXPIRED)
                    ->where('updated_at', '>=', now()->subDay())
                    ->count(),
                'failed_queue_jobs' => Schema::hasTable('failed_jobs')
                    ? (int) DB::table('failed_jobs')->count()
                    : 0,
            ],
            'recent_failed_jobs' => $this->recentFailedJobs(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Last 10 failed queued jobs with a trimmed exception preview.
     *
     * @return list<array<string, mixed>>
     */
    public function recentFailedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (object $row): array {
                $queue = property_exists($row, 'queue') ? $row->queue : null;

                return [
                    'id' => (int) $row->id,
                    'queue' => $queue,
                    'failed_at' => $row->failed_at,
                    'exception' => str_contains((string) $row->exception, "\n")
                        ? substr((string) $row->exception, 0, (int) strpos((string) $row->exception, "\n"))
                        : (string) $row->exception,
                ];
            })
            ->all();
    }
}
