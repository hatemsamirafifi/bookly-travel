<?php

namespace App\Domains\Partner\Jobs;

use App\Domains\Partner\Services\TourDepartureService;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Maintain the bounded 180-day explicit departure horizon for published tours (EGM-005 D3).
 *
 * Re-running is idempotent: preserves booked seats, never reduces capacity below
 * active reservations, and never resurrects cancelled departures.
 */
class MaintainDepartureHorizonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function handle(TourDepartureService $departureService): void
    {
        $startDate = now('UTC')->startOfDay();
        $endDate = $startDate->copy()->addDays(TourDepartureService::MAX_HORIZON_DAYS);
        $defaultTimezone = config('app.timezone', 'UTC');

        Tour::query()
            ->where('status', 'published')
            ->whereHas('options', fn ($query) => $query->where('status', TourOption::STATUS_ACTIVE))
            ->whereHas('availabilityRules')
            ->with(['partnerRecord'])
            ->chunkById(50, function ($tours) use ($departureService, $startDate, $endDate, $defaultTimezone): void {
                /** @var Tour $tour */
                foreach ($tours as $tour) {
                    if (! $tour->hasEligiblePublicOwner()) {
                        continue;
                    }

                    $timezone = TourDeparture::query()
                        ->where('tour_id', $tour->id)
                        ->orderByDesc('id')
                        ->value('timezone') ?: $defaultTimezone;

                    try {
                        $departureService->generateForTour(
                            $tour,
                            $startDate,
                            $endDate,
                            (string) $timezone
                        );
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to maintain departure horizon for tour', [
                            'tour_id' => $tour->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }
}
