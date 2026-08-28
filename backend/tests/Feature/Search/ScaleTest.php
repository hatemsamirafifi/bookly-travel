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

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

/**
 * Seed 10,000 published tours across 50 categories.
 */
function seedScaleDataset(): void
{
    // Create 50 categories
    $categoryIds = [];
    for ($i = 1; $i <= 50; $i++) {
        $categoryIds[] = DB::table('categories')->insertGetId([
            'slug' => "category-{$i}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('category_translations')->insert([
            'category_id' => end($categoryIds),
            'locale' => 'en',
            'name' => "Category {$i}",
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
            'slug' => "tour-{$i}",
            'category_id' => $categoryId,
            'status' => 'published',
            'duration_minutes' => 60 + ($i % 240),
            'location' => 'City ' . ($i % 100),
            'min_group_size' => 1,
            'max_group_size' => 10,
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

    // Seed tour translations (title only for search)
    $batch = [];
    for ($i = 1; $i <= 10_000; $i++) {
        $batch[] = [
            'tour_id' => $i,
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
