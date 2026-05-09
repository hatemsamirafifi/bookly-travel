<?php

namespace App\Domains\Search\Actions;

use App\Models\Tour;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RemoveFromIndexAction implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $tourId
    ) {}

    public function handle(): void
    {
        $tour = Tour::find($this->tourId);

        if (! $tour) {
            return;
        }

        $tour->unsearchable();
    }
}
