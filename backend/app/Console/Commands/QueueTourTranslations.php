<?php

namespace App\Console\Commands;

use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Tour;
use Illuminate\Console\Command;

class QueueTourTranslations extends Command
{
    protected $signature = 'tours:queue-translations {--after-id=0 : Resume after this tour ID} {--limit=100 : Maximum tours to inspect in this run}';

    protected $description = 'Queue bounded ES/IT regeneration from English tour source without deleting legacy translations';

    public function handle(TourTranslationService $service): int
    {
        $afterId = max(0, (int) $this->option('after-id'));
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 500) {
            $this->error('Limit must be between 1 and 500.');

            return self::FAILURE;
        }

        $tours = Tour::where('id', '>', $afterId)
            ->whereHas('translations', fn ($query) => $query->where('locale', 'en'))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($tours as $tour) {
            $service->queueIfChanged($tour, null);
            $afterId = $tour->id;
        }

        $this->info("Inspected {$tours->count()} tours. Continue with --after-id={$afterId}.");

        return self::SUCCESS;
    }
}
