<?php

/**
 * Scale Test — Public Search & Discovery (Spec 006, T090)
 *
 * Seeds 10,000 published tours across 50 categories and verifies:
 * - Search p95 latency < 2s (SC-002)
 * - Filter/sort p95 < 1.5s (SC-008)
 * - Pagination stays performant at deep offsets (SC-010)
 *
 * ⚠️ This test seeds a large dataset and is tagged `@scale` so it can be
 * excluded from the default CI run and executed explicitly:
 *   php artisan test --filter=ScaleTest
 *
 * It uses a separate database transaction that rolls back after the run.
 */

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\EngineManager;
use Meilisearch\Client as MeilisearchClient;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['scout.driver' => 'meilisearch']);
    app(EngineManager::class)->forgetEngines();

    $client = app(MeilisearchClient::class);
    $indexName = (new Tour)->searchableAs();

    try {
        $task = $client->deleteIndex($indexName);
        $client->waitForTask($task['taskUid'], 30_000);
    } catch (Throwable) {
        // The first test run has no index to delete.
    }

    $index = $client->index($indexName);
    $tasks = [
        $index->updateSearchableAttributes([
            'title_en', 'title_es', 'title_it',
            'description_en', 'description_es', 'description_it',
            'highlights_en', 'highlights_es', 'highlights_it',
            'location', 'category_name',
        ]),
        $index->updateFilterableAttributes(['status', 'category_slug', 'location_slug', 'price_amount', 'duration_minutes', 'available_dates']),
        $index->updateSortableAttributes(['price_amount', 'average_rating', 'created_at']),
    ];
    $client->waitForTasks(array_column($tasks, 'taskUid'), 30_000);
});

afterEach(function () {
    $client = app(MeilisearchClient::class);
    $task = $client->deleteIndex((new Tour)->searchableAs());
    $client->waitForTask($task['taskUid'], 30_000);
});

/**
 * Seed 10,000 published tours across 50 categories.
 */
function seedScaleDataset(): void
{
    $partner = makePartner();

    // Create 50 categories
    $categoryIds = [];
    for ($i = 1; $i <= 50; $i++) {
        $categoryIds[] = DB::table('categories')->insertGetId([
            'name' => "Category {$i}",
            'slug' => "category-{$i}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Create 10,000 tours (200 per category)
    $batch = [];
    $now = now();
    for ($i = 1; $i <= 10_000; $i++) {
        $categoryId = $categoryIds[($i - 1) % 50];
        $batch[] = [
            'partner_id' => $partner->id,
            'slug' => "scale-tour-{$i}",
            'category_id' => $categoryId,
            'status' => 'published',
            'duration_minutes' => 60 + ($i % 240),
            'duration_label' => '1-5 hours',
            'location' => 'City ' . ($i % 100),
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000 + $i,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($batch) === 500) {
            DB::table('tours')->insert($batch);
            $batch = [];
        }
    }
    if (! empty($batch)) {
        DB::table('tours')->insert($batch);
    }

    $tourIds = DB::table('tours')
        ->where('slug', 'like', 'scale-tour-%')
        ->pluck('id', 'slug');

    // Seed tour translations (title only for search)
    $batch = [];
    for ($i = 1; $i <= 10_000; $i++) {
        $batch[] = [
            'tour_id' => $tourIds["scale-tour-{$i}"],
            'locale' => 'en',
            'title' => "Tour {$i} - Amazing Experience",
            'description' => str_repeat('Lorem ipsum ', 20),
            'highlights' => '[]',
            'inclusions' => '[]',
            'exclusions' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($batch) === 500) {
            DB::table('tour_translations')->insert($batch);
            $batch = [];
        }
    }
    if (! empty($batch)) {
        DB::table('tour_translations')->insert($batch);
    }

    $documents = DB::table('tours')
        ->join('tour_translations', 'tour_translations.tour_id', '=', 'tours.id')
        ->join('categories', 'categories.id', '=', 'tours.category_id')
        ->where('tours.slug', 'like', 'scale-tour-%')
        ->where('tour_translations.locale', 'en')
        ->select([
            'tours.id',
            'tours.slug',
            'tours.status',
            'tours.location',
            'tours.location_slug',
            'tours.price_amount',
            'tours.duration_minutes',
            'tours.created_at',
            'tour_translations.title as title_en',
            'tour_translations.description as description_en',
            'categories.slug as category_slug',
            'categories.name as category_name',
        ])
        ->get()
        ->map(fn ($tour) => array_merge((array) $tour, [
            'average_rating' => 0,
            'available_dates' => [],
            'title_es' => '',
            'title_it' => '',
            'description_es' => '',
            'description_it' => '',
            'highlights_en' => '[]',
            'highlights_es' => '[]',
            'highlights_it' => '[]',
        ]));

    $client = app(MeilisearchClient::class);
    $index = $client->index((new Tour)->searchableAs());
    $taskIds = [];
    foreach ($documents->chunk(1000) as $chunk) {
        $task = $index->addDocuments($chunk->values()->all());
        $taskIds[] = $task['taskUid'];
    }
    $client->waitForTasks($taskIds, 60_000);
}

it('searches 10,000 tours with p95 latency under 2 seconds', function () {
    seedScaleDataset();

    $latencies = [];
    for ($i = 0; $i < 20; $i++) {
        $start = microtime(true);
        getJson('/api/public/search/tours?locale=en&q=Tour')
            ->assertOk();
        $latencies[] = (microtime(true) - $start) * 1000;
    }

    sort($latencies);
    $p95Index = (int) ceil(0.95 * count($latencies)) - 1;
    $p95 = $latencies[$p95Index];

    expect($p95)->toBeLessThan(2000, "Search p95 latency {$p95}ms exceeds 2s target (SC-002)");
})->group('scale');

it('filters and sorts 10,000 tours with p95 latency under 1.5 seconds', function () {
    seedScaleDataset();

    $latencies = [];
    for ($i = 0; $i < 20; $i++) {
        $start = microtime(true);
        getJson('/api/public/search/tours?locale=en&category=category-1&sort=price_asc')
            ->assertOk();
        $latencies[] = (microtime(true) - $start) * 1000;
    }

    sort($latencies);
    $p95Index = (int) ceil(0.95 * count($latencies)) - 1;
    $p95 = $latencies[$p95Index];

    expect($p95)->toBeLessThan(1500, "Filter/sort p95 latency {$p95}ms exceeds 1.5s target (SC-008)");
})->group('scale');

it('paginates deep offsets without performance degradation', function () {
    seedScaleDataset();

    // Deep offset — page 100 with 10 per_page = offset 990
    $start = microtime(true);
    getJson('/api/public/search/tours?locale=en&page=100&per_page=10')
        ->assertOk();
    $latency = (microtime(true) - $start) * 1000;

    expect($latency)->toBeLessThan(2000, "Deep pagination latency {$latency}ms exceeds 2s target (SC-010)");
})->group('scale');
