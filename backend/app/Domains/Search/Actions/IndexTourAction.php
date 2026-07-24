<?php

namespace App\Domains\Search\Actions;

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
        $tour = Tour::with(['translations', 'category', 'availabilityRules', 'availabilityExceptions'])
            ->find($this->tourId);

        if (! $tour || ! $tour->shouldBeSearchable()) {
            return;
        }

        $tour->searchable();
    }
}
