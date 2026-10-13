<?php

namespace App\Console\Commands;

use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Search\Actions\IndexTourAction;
use App\Models\Tour;
use Illuminate\Console\Command;

class QueueTourTranslations extends Command
{
    protected $signature = 'tours:queue-translations {--after-id=0 : Resume after this tour ID} {--limit=100 : Maximum tours to inspect in this run} {--refresh-ready : Repair lost search projections for tours with current derivatives}';

    protected $description = 'Queue bounded ES/IT regeneration from English tour source without deleting legacy translations';

    public function handle(TourTranslationService $service): int
    {
        $afterId = $this->parseNonNegativeInt($this->option('after-id'));
        if ($afterId === null) {
            $this->error('After ID must be a non-negative integer.');

            return self::FAILURE;
        }

        $limit = $this->parseNonNegativeInt($this->option('limit'));
        if ($limit === null || $limit < 1 || $limit > 500) {
            $this->error('Limit must be between 1 and 500.');

            return self::FAILURE;
        }

        $refreshReady = (bool) $this->option('refresh-ready');

        $tours = Tour::where('id', '>', $afterId)
            ->whereHas('translations', fn ($query) => $query->where('locale', 'en'))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $queued = 0;
        $refreshed = 0;
        foreach ($tours as $tour) {
            // Bounded recovery under the shared Tour-first lock: orphan
            // pending, stale, failed, and apparent-ready rows requeue even
            // when they already carry the current desired hash, while
            // genuinely current ready derivatives stay untouched.
            foreach ($service->reconcileTour($tour) as $plan) {
                GenerateTourTranslationJob::dispatch($plan['tour_id'], $plan['locale'], $plan['source_hash'])->afterCommit();
                $queued++;
            }

            // Lost index/cache projection repair: one fresh projection per
            // tour holding current derivatives, without provider calls or
            // derivative/source/state rewrites. Coexists with ordinary
            // pending recovery above.
            if ($refreshReady && $service->hasCurrentDerivatives($tour->fresh())) {
                IndexTourAction::dispatch($tour->id)->afterCommit();
                $refreshed++;
            }

            $afterId = $tour->id;
        }

        $this->info("Inspected {$tours->count()} tours. Continue with --after-id={$afterId}.");
        if ($queued > 0) {
            $this->info("Queued {$queued} translation jobs.");
        }
        if ($refreshReady) {
            $this->info("Refreshed {$refreshed} search projections.");
        }

        return self::SUCCESS;
    }

    /**
     * Strict non-negative integer parsing: digits only, so blank,
     * fractional, signed, and non-numeric cursors/limits fail instead of
     * being silently cast or clamped. Digit strings above the native
     * integer range are rejected BEFORE casting: a plain (int) cast would
     * silently saturate an overflowing value to PHP_INT_MAX, so
     * the leading-zero-stripped length plus a lexicographic comparison
     * against PHP_INT_MAX decides first. Native-range IDs (including large
     * values such as 999999999) still parse exactly as before.
     */
    private function parseNonNegativeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        $digits = ltrim($value, '0');
        if ($digits === '') {
            return 0;
        }

        $ceiling = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($ceiling)
            || (strlen($digits) === strlen($ceiling) && $digits > $ceiling)) {
            return null;
        }

        return (int) $digits;
    }
}
