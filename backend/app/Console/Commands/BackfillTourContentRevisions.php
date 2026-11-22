<?php

namespace App\Console\Commands;

use App\Domains\Partner\Services\TourRevisionService;
use Illuminate\Console\Command;

/**
 * Bounded, idempotent legacy-baseline capture for the EGM-003 activation
 * runbook (design v3 §10).
 *
 * Batches are Tour-locked, faithful (no normalization, no invented approved
 * content) and pointer-if-null CAS. The command never deploys, never migrates
 * and never touches an accepted revision: it only captures provenance for
 * published tours that still have no approved snapshot.
 */
class BackfillTourContentRevisions extends Command
{
    protected $signature = 'tours:backfill-content-revisions
        {--limit=100 : Maximum tours to capture in this run}
        {--after-id=0 : Resume after this tour id}';

    protected $description = 'Capture faithful content-revision baselines for published tours in bounded locked batches';

    public function handle(TourRevisionService $revisions): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1 || $limit > 500) {
            $this->error('Limit must be between 1 and 500.');

            return self::FAILURE;
        }

        $afterId = (int) $this->option('after-id');

        if ($afterId < 0) {
            $this->error('After ID must be a non-negative integer.');

            return self::FAILURE;
        }

        $result = $revisions->captureBaselineBatch($limit, $afterId);

        $this->info(sprintf(
            'Captured %d baseline(s), skipped %d tour(s). Continue with --after-id=%d.',
            $result['captured'],
            $result['skipped'],
            $result['after_id'],
        ));

        return self::SUCCESS;
    }
}
