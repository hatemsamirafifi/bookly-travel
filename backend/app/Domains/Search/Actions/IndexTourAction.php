<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Services\TourPublicProjectionService;
use App\Models\Tour;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexTourAction implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $tourId
    ) {}

    public function handle(): void
    {
        // Reconcile CURRENT Tour row under the shared Tour lock inside withIndexProjection.
        // Scout operations run WHILE the lock is held (design v3 §8.1). Out-of-order A-B-A
        // jobs decide by the locked row, never by the job name.
        app(TourPublicProjectionService::class)->withIndexProjection(
            $this->tourId,
            function (?Tour $tour): void {
                if ($tour === null) {
                    $synthetic = new Tour;
                    $synthetic->setAttribute('id', $this->tourId);
                    $synthetic->unsearchableSync();

                    return;
                }

                if ($tour->shouldBeSearchable()) {
                    $tour->searchableSync();
                } else {
                    $tour->unsearchableSync();
                }
            }
        );
    }
}
