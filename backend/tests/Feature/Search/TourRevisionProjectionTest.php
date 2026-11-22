<?php

use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourRevisionService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Search\Services\TourPublicProjectionService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TourRevisionFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'General Tours',
        'slug' => 'general-tours',
        'is_active' => true,
        'display_order' => 1,
    ]);

    $this->user = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->user->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
});

function egm003BookableProjectionTour(array $attributes = [], array $sourceOverrides = []): Tour
{
    $tour = Tour::create(array_merge([
        'category_id' => test()->category->id,
        'partner_id' => test()->partner->id,
        'price_amount' => 5000,
        'slug' => 'tour-' . uniqid(),
        'source_locale' => 'en',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ], $attributes));

    $tour->translations()->create(array_merge([
        'locale' => $tour->source_locale ?? 'en',
        'title' => 'Approved Live Title',
        'description' => 'Approved live description.',
        'meeting_point' => 'Colosseum entrance',
    ], $sourceOverrides));

    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'specific_date',
        'start_date' => now()->addDays(5)->toDateString(),
        'capacity' => 10,
    ]);

    return $tour->fresh() ?? $tour;
}

it('hydrates and transforms a support collection without type error', function () {
    $tour = egm003BookableProjectionTour();
    $supportCollection = collect([$tour]);

    $cards = app(TourPublicProjectionService::class)->hydrateAndTransformCards($supportCollection, 'en');

    expect($cards)->toBeArray()
        ->and($cards)->toHaveCount(1)
        ->and($cards[0]['title'])->toBe('Approved Live Title');
});

it('allows GetRelatedToursAction to project related tours from support collection', function () {
    $targetTour = egm003BookableProjectionTour(['slug' => 'main-target-tour', 'location_slug' => 'rome']);
    $relatedTour = egm003BookableProjectionTour(['slug' => 'related-destination-tour', 'location_slug' => 'rome']);

    $this->getJson("/api/public/tours/{$targetTour->slug}?locale=en")
        ->assertOk()
        ->assertJsonCount(1, 'data.related_tours')
        ->assertJsonPath('data.related_tours.0.slug', $relatedTour->slug);
});

it('never projects proposed revisions in public cards while an active candidate exists', function () {
    $tour = egm003BookableProjectionTour(['slug' => 'governed-tour'], ['title' => 'Live Approved Walking Tour']);

    $baseline = TourRevisionFixtures::seedBaseline($tour);
    $tour->update(['published_revision_id' => $baseline->id]);
    $service = app(TourRevisionService::class);
    $candidate = $service->createCandidate($tour->id, $this->user->id, $baseline->id, [
        'source' => ['title' => 'Leaked Proposed Walking Tour'],
    ]);
    $service->submitCandidate($tour->id, $candidate->id, $this->user->id);

    $cards = app(TourPublicProjectionService::class)->hydrateAndTransformCards(collect([$tour]), 'en');

    expect($cards[0]['title'])->toBe('Live Approved Walking Tour')
        ->and($cards[0]['title'])->not->toBe('Leaked Proposed Walking Tour');
});

it('preserves the original candidate collection ranking order regardless of database id sort', function () {
    $t1 = egm003BookableProjectionTour(['slug' => 'rank-first']);
    $t2 = egm003BookableProjectionTour(['slug' => 'rank-second']);
    $t3 = egm003BookableProjectionTour(['slug' => 'rank-third']);

    $inputOrder = collect([$t3, $t1, $t2]);
    $cards = app(TourPublicProjectionService::class)->hydrateAndTransformCards($inputOrder, 'en');

    expect($cards)->toHaveCount(3)
        ->and($cards[0]['slug'])->toBe('rank-third')
        ->and($cards[1]['slug'])->toBe('rank-first')
        ->and($cards[2]['slug'])->toBe('rank-second');
});

it('preserves genuine rating aggregate aliases on related cards without leaking stale content attributes', function () {
    $tour = egm003BookableProjectionTour(['location' => 'Rome, Italy', 'review_count' => 0, 'average_rating' => 0.0]);

    $candidate = clone $tour;
    $candidate->setAttribute('genuine_review_count', 42);
    $candidate->setAttribute('genuine_average_rating', 4.85);
    $candidate->setAttribute('review_count', 42);
    $candidate->setAttribute('average_rating', 4.85);
    $candidate->setAttribute('location', 'Stale Hallucinated City');

    $cards = app(TourPublicProjectionService::class)->hydrateAndTransformCards(collect([$candidate]), 'en');

    expect($cards[0]['rating']['count'])->toBe(42)
        ->and($cards[0]['rating']['average'])->toBe(4.85)
        ->and($cards[0]['location'])->toBe('Rome, Italy')
        ->and($cards[0]['location'])->not->toBe('Stale Hallucinated City');
});

it('excludes tours that became unbookable or unpublished from projected cards', function () {
    $bookable = egm003BookableProjectionTour();
    $unbookable = egm003BookableProjectionTour(['slug' => 'unbookable-tour']);
    $unbookable->availabilityRules()->delete();
    $draft = egm003BookableProjectionTour(['status' => 'draft']);

    $cards = app(TourPublicProjectionService::class)->hydrateAndTransformCards(collect([$bookable, $unbookable, $draft]), 'en');

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['slug'])->toBe($bookable->slug);
});

it('selects a current derivative from the loaded coherent snapshot without new queries', function () {
    $tour = egm003BookableProjectionTour();
    $translations = app(TourTranslationService::class);
    $hash = $translations->sourceHash($tour->sourceTranslation(), $tour);
    $tour->translations()->create(['locale' => 'es', 'title' => 'Título generado', 'description' => 'Descripción generada.']);
    TourTranslationState::create([
        'tour_id' => $tour->id, 'locale' => 'es', 'status' => 'ready',
        'source_hash' => $hash, 'translated_hash' => $hash,
        'generation_epoch' => $tour->generationEpoch(),
    ]);
    $tour->refresh()->load(TourPublicProjectionService::CARD_RELATIONS);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $translation = $translations->currentTranslation($tour, 'es');
        expect($translation?->title)->toBe('Título generado')
            ->and(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

it('batch loads card relations with bounded query growth', function () {
    $tours = collect();
    for ($index = 0; $index < 8; $index++) {
        $tours->push(egm003BookableProjectionTour());
    }
    $projection = app(TourPublicProjectionService::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $small = $projection->hydrateAndTransformCards($tours->take(2), 'en');
        $smallQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $large = $projection->hydrateAndTransformCards($tours, 'en');
        expect($small)->toHaveCount(2)
            ->and($large)->toHaveCount(8)
            ->and(count(DB::getQueryLog()))->toBeLessThanOrEqual($smallQueries + 2);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

it('falls back to the selected canonical source in projected cards', function (string $sourceLocale, string $requestedLocale, string $title) {
    $tour = egm003BookableProjectionTour(['source_locale' => $sourceLocale], ['title' => $title]);

    $cards = app(TourPublicProjectionService::class)->hydrateAndTransformCards(collect([$tour]), $requestedLocale);

    expect($cards)->toHaveCount(1)->and($cards[0]['title'])->toBe($title);
})->with([
    ['en', 'es', 'Approved English source'],
    ['es', 'it', 'Fuente aprobada en español'],
    ['it', 'en', 'Fonte italiana approvata'],
]);
