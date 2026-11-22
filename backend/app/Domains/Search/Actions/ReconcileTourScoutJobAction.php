<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\Services\TourPublicProjectionService;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;

class ReconcileTourScoutJobAction
{
    public function __construct(
        private readonly TourPublicProjectionService $projectionService
    ) {}

    /**
     * Reconcile current state for models carried by mapped Scout queue commands.
     *
     * Non-Tour models delegate directly to the command's native handle() method.
     * Tour models are reconciled against current database state under sorted
     * shared locks via TourPublicProjectionService::withIndexProjections,
     * ensuring out-of-order queue completions never overwrite current state.
     */
    public function handle(MakeSearchable|RemoveFromSearch $command): void
    {
        if ($command->models->isEmpty()) {
            return;
        }

        $others = $command->models->filter(fn ($model): bool => ! $model instanceof Tour)->values();
        $tourModels = $command->models->filter(fn ($model): bool => $model instanceof Tour);
        if ($tourModels->isEmpty()) {
            $command->handle();

            return;
        }

        if ($others->isNotEmpty()) {
            $native = clone $command;
            $native->models = $others;
            $native->handle();
        }

        $ids = [];
        foreach ($tourModels as $model) {
            $id = (int) $model->getScoutKey();
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return;
        }

        foreach (array_chunk($ids, 500) as $chunkIds) {
            $this->projectionService->withIndexProjections(
                $chunkIds,
                function (EloquentCollection $tours, array $sortedIds): void {
                    $foundById = $tours->keyBy(fn (Tour $tour): int => (int) $tour->id);

                    /** @var EloquentCollection<int, Tour> $searchable */
                    $searchable = new EloquentCollection;

                    /** @var EloquentCollection<int, Tour> $removable */
                    $removable = new EloquentCollection;

                    foreach ($sortedIds as $id) {
                        /** @var Tour|null $tour */
                        $tour = $foundById->get($id);

                        if ($tour !== null && $tour->shouldBeSearchable()) {
                            $searchable->push($tour);
                        } else {
                            $synthetic = new Tour;
                            $synthetic->setAttribute('id', $id);
                            $removable->push($synthetic);
                        }
                    }

                    if ($searchable->isNotEmpty()) {
                        $searchable->firstOrFail()->syncMakeSearchable($searchable);
                    }

                    if ($removable->isNotEmpty()) {
                        $removable->firstOrFail()->syncRemoveFromSearch($removable);
                    }
                }
            );
        }
    }
}
