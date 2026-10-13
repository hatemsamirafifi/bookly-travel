<?php

use App\Domains\Partner\Contracts\TourContentTranslator;
use App\Domains\Partner\Jobs\GenerateTourTranslationJob;
use App\Domains\Partner\Services\TourService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Search\Actions\IndexTourAction;
use App\Models\Tour;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\CollectionEngine;
use Tests\Support\TourContentFixtures;

// CollectionEngine search reads SQL directly and cannot prove a stale persisted
// index was repaired. This adapter records documents written by real Scout sync,
// while keeping the actual IndexTourAction and toSearchableArray projection.
class Spec019RecordingProjectionEngine extends CollectionEngine
{
    public array $documents = [];

    public function update($models)
    {
        foreach ($models as $model) {
            $this->documents[$model->getScoutKey()] = $model->toSearchableArray();
        }
    }

    public function delete($models)
    {
        foreach ($models as $model) {
            unset($this->documents[$model->getScoutKey()]);
        }
    }
}

it('repairs an actually stale recorded search document through refresh-ready without generation or content rewrites', function () {
    Queue::fake();
    $engine = new Spec019RecordingProjectionEngine;
    app(EngineManager::class)->extend('spec019_recording', fn () => $engine);
    config(['scout.driver' => 'spec019_recording', 'scout.queue' => false]);

    $tour = Tour::withoutSyncingToSearch(function () {
        $tour = makeSearchableTour('published');
        $tour->translations()->create(['locale' => 'en'] + TourContentFixtures::englishAttributes());
        addAvailabilityRule($tour);
        foreach (['es', 'it'] as $locale) {
            TourContentFixtures::seedDerivative($tour, $locale, 'current');
        }

        return $tour->fresh();
    });
    expect($tour->shouldBeSearchable())->toBeTrue();
    (new IndexTourAction($tour->id))->handle();
    $oldDocument = $engine->documents[$tour->id];
    expect($oldDocument['title_en'])->toBe('Morning walking tour');
    expect($oldDocument['title_es'])->toBe('Title es');

    Tour::withoutSyncingToSearch(function () use ($tour) {
        app(TourService::class)->updateTour($tour, ['title' => 'Evening walking tour']);
        $fresh = $tour->fresh();
        $service = app(TourTranslationService::class);
        $hash = $service->sourceHash($fresh->translations()->where('locale', 'en')->firstOrFail());
        $translator = new class implements TourContentTranslator
        {
            public function translate(array $strings, string $locale): array
            {
                return array_map(fn (string $text): string => $locale . ': ' . $text, $strings);
            }
        };
        foreach (['es', 'it'] as $locale) {
            (new GenerateTourTranslationJob($tour->id, $locale, $hash))->handle($service, $translator);
        }
    });
    $service = app(TourTranslationService::class);
    expect($service->currentTranslation($tour->fresh(), 'es')?->title)->toBe('es: Evening walking tour');
    expect($service->currentTranslation($tour->fresh(), 'it')?->title)->toBe('it: Evening walking tour');
    // Both ready-dispatches were lost in the fake queue: the actual index
    // remains the old document even though the source and derivatives changed.
    expect($engine->documents[$tour->id])->toBe($oldDocument);
    $beforeRows = $tour->translations()->orderBy('locale')->get()->map(fn ($row) => $row->getRawOriginal())->all();
    $beforeStates = $tour->translationStates()->orderBy('locale')->get()->map(fn ($row) => $row->getRawOriginal())->all();
    Queue::fake();

    expect(Artisan::call('tours:queue-translations', [
        '--after-id' => (string) ($tour->id - 1), '--limit' => '1', '--refresh-ready' => true,
    ]))->toBe(0);
    Queue::assertNotPushed(GenerateTourTranslationJob::class);
    $refresh = null;
    Queue::assertPushed(IndexTourAction::class, function (IndexTourAction $job) use (&$refresh): bool {
        $refresh = $job;

        return true;
    });
    expect($refresh)->toBeInstanceOf(IndexTourAction::class);
    $refresh->handle();

    expect($engine->documents[$tour->id]['title_en'])->toBe('Evening walking tour');
    expect($engine->documents[$tour->id]['title_es'])->toBe('es: Evening walking tour');
    expect($engine->documents[$tour->id]['title_it'])->toBe('it: Evening walking tour');
    expect($tour->fresh()->translations()->orderBy('locale')->get()->map(fn ($row) => $row->getRawOriginal())->all())->toBe($beforeRows);
    expect($tour->fresh()->translationStates()->orderBy('locale')->get()->map(fn ($row) => $row->getRawOriginal())->all())->toBe($beforeStates);
});
