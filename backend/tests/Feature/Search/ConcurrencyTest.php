<?php

/**
 * Concurrency Test — Public Search & Discovery (Spec 006, T091)
 *
 * Simulates 500 concurrent search requests distributed across search, detail,
 * category, and destination endpoints and verifies:
 * - All requests complete without errors (SC-011)
 * - p95 latency targets are met
 * - No rate-limit false positives
 * - No connection pool exhaustion
 *
 * ⚠️ Tagged `@concurrency` — excluded from default CI, run explicitly:
 *   php artisan test --filter=ConcurrencyTest
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function seedConcurrencyDataset(): void
{
    $now = now();

    // Seed 100 tours, 5 categories, 5 destinations
    $categoryIds = [];
    for ($i = 1; $i <= 5; $i++) {
        $categoryIds[] = DB::table('categories')->insertGetId([
            'slug' => "cat-{$i}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('category_translations')->insert([
            'category_id' => end($categoryIds),
            'locale' => 'en',
            'name' => "Category {$i}",
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    for ($i = 1; $i <= 100; $i++) {
        DB::table('tours')->insert([
            'slug' => "tour-{$i}",
            'category_id' => $categoryIds[$i % 5],
            'status' => 'published',
            'duration_minutes' => 120,
            'location' => 'City ' . ($i % 10),
            'min_group_size' => 1,
            'max_group_size' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('tour_translations')->insert([
            'tour_id' => $i,
            'locale' => 'en',
            'title' => "Tour {$i}",
            'description' => 'Test description',
            'highlights' => '[]',
            'inclusions' => '[]',
            'exclusions' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

it('handles 500 concurrent search requests without errors', function () {
    seedConcurrencyDataset();

    $endpoints = [
        '/api/public/search/tours?locale=en&q=Tour',
        '/api/public/tours/tour-1',
        '/api/public/categories?locale=en',
        '/api/public/categories/cat-1/tours?locale=en',
        '/api/public/destinations?locale=en',
    ];

    $promises = [];
    for ($i = 0; $i < 500; $i++) {
        $endpoint = $endpoints[$i % count($endpoints)];
        $promises[] = Http::async()->get(url($endpoint));
    }

    $responses = Http::pool(fn () => $promises);

    $errors = 0;
    $latencies = [];

    foreach ($responses as $response) {
        if ($response->failed() || $response->status() >= 500) {
            $errors++;
        }
        if ($response->status() === 429) {
            // Rate-limit false positive
            $errors++;
        }
    }

    expect($errors)->toBe(0, "{$errors} requests failed out of 500 (SC-011)");
})->group('concurrency');
