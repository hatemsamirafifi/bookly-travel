<?php

namespace App\Console\Commands;

use App\Domains\Partner\Services\LegacyTourItineraryNormalizer;
use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Tour;
use App\Models\TourTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdoptLegacyTourItineraries extends Command
{
    protected $signature = 'tours:adopt-itineraries {--dry-run : Preview adoption without writing} {--after-id=0 : Resume after this tour ID} {--limit=100 : Maximum tours to inspect in this run}';

    protected $description = 'Adopt legacy tour itineraries into English source without overwriting populated content';

    public function handle(
        LegacyTourItineraryNormalizer $normalizer,
        TourTranslationService $service
    ): int {
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

        $dryRun = (bool) $this->option('dry-run');

        // ID-ordered bounded page. Tours without an English row are included
        // so they can be reported for governed repair, never fabricated.
        $tours = Tour::where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $adopted = 0;
        $skipped = 0;
        $invalid = 0;
        $missing = 0;

        foreach ($tours as $tour) {
            // Each tour adopts through the shared Tour-first lock. The fresh
            // Tour and English rows are reread INSIDE the lock and English
            // eligibility (strictly null itinerary) is rechecked there, so a
            // concurrently populated source always wins over stale legacy.
            $planned = $service->withContentLock($tour->id, function () use (
                $tour,
                $normalizer,
                $service,
                $dryRun,
                &$adopted,
                &$skipped,
                &$invalid,
                &$missing
            ) {
                $fresh = Tour::find($tour->id);
                if ($fresh === null) {
                    $skipped++;

                    return [];
                }

                $english = TourTranslation::where('tour_id', $tour->id)
                    ->where('locale', 'en')
                    ->first();

                if ($english === null) {
                    $missing++;

                    return [];
                }

                // Populated English (including an intentional []) is never
                // overwritten.
                if ($english->itinerary !== null) {
                    $skipped++;

                    return [];
                }

                $outcome = $normalizer->normalize($this->decodeLegacy(
                    DB::table('tours')->where('id', $tour->id)->value('itinerary')
                ));

                if ($dryRun) {
                    if ($outcome['valid']) {
                        $adopted++;
                    } else {
                        $invalid++;
                    }

                    return [];
                }

                $previousHash = $service->sourceHash($english);

                // Only the accepted English itinerary is persisted; the raw
                // legacy column, derivatives, media, and settings are
                // untouched.
                $english->itinerary = $outcome['itinerary'];
                $english->save();

                $planned = $service->planSourceDispatches($fresh, $previousHash);

                if ($outcome['valid']) {
                    $adopted++;
                } else {
                    $invalid++;
                }

                return $planned;
            });

            // Queue pushes run only after the source transaction committed,
            // via the accepted service boundary, so a lost push can never
            // fail the committed source write. Dry-run plans nothing.
            if ($planned !== []) {
                $service->dispatchPlannedTranslations($planned);
            }

            $afterId = $tour->id;
        }

        if ($dryRun) {
            $this->info('Dry-run mode: no changes were written.');
        }

        $this->info("Inspected {$tours->count()} tours. Adopted {$adopted}, skipped {$skipped}, invalid {$invalid}, missing {$missing}. Continue with --after-id={$afterId}.");

        return self::SUCCESS;
    }

    /**
     * Decode the raw legacy column for normalization: JSON text becomes its
     * stored value, NULL stays absent (a valid empty source), and
     * non-JSON text or scalar JSON passes through so the normalizer reports
     * it invalid instead of this layer silently clearing it.
     */
    private function decodeLegacy(mixed $value): mixed
    {
        if ($value === null || is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
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
