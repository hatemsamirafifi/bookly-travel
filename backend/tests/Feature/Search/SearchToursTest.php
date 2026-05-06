<?php

use Illuminate\Testing\Fluent\AssertableJson;
use function Pest\Laravel\getJson;

it('returns validation error when locale is missing', function () {
    getJson('/api/public/search/tours')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});

it('returns validation error for invalid locale', function () {
    getJson('/api/public/search/tours?locale=fr')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});

it('returns successful response with valid locale and expected JSON structure', function () {
    getJson('/api/public/search/tours?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->has('data')
                 ->has('meta', fn (AssertableJson $meta) =>
                    $meta->has('current_page')
                         ->has('last_page')
                         ->has('per_page')
                         ->has('total')
                 )
                 ->has('filters', fn (AssertableJson $filters) =>
                    $filters->has('categories')
                            ->has('locations')
                            ->has('price_range')
                            ->has('durations')
                 )
        );
});

it('accepts optional search query parameter', function () {
    getJson('/api/public/search/tours?locale=en&q=beach')
        ->assertOk();
});

it('strips whitespace and special chars from query safely', function () {
    getJson('/api/public/search/tours?locale=en&q=%20%20beach%20%20')
        ->assertOk();
});

it('accepts pagination parameter', function () {
    getJson('/api/public/search/tours?locale=en&page=1')
        ->assertOk();
});

it('rejects invalid page numbers', function () {
    getJson('/api/public/search/tours?locale=en&page=0')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['page']);

    getJson('/api/public/search/tours?locale=en&page=-1')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['page']);
});

it('rejects query exceeding max length', function () {
    getJson('/api/public/search/tours?locale=en&q=' . str_repeat('a', 256))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

it('supports all three supported locales', function (string $locale) {
    getJson("/api/public/search/tours?locale={$locale}")
        ->assertOk();
})->with(['en', 'es', 'it']);

it('handles empty query returning all available published tours', function () {
    getJson('/api/public/search/tours?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->has('data')
                 ->where('meta.current_page', 1)
                 ->etc()
        );
});

it('respects per_page default of 12', function () {
    getJson('/api/public/search/tours?locale=en')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 12);
});

// Filter & Sort tests (US2)

it('accepts category filter parameter', function () {
    getJson('/api/public/search/tours?locale=en&category=adventure')
        ->assertOk();
});

it('accepts location filter parameter', function () {
    getJson('/api/public/search/tours?locale=en&location=paris')
        ->assertOk();
});

it('accepts price range filter parameters', function () {
    getJson('/api/public/search/tours?locale=en&price_min=1000&price_max=5000')
        ->assertOk();
});

it('accepts duration filter parameter', function () {
    getJson('/api/public/search/tours?locale=en&duration=half-day')
        ->assertOk();

    getJson('/api/public/search/tours?locale=en&duration=full-day')
        ->assertOk();

    getJson('/api/public/search/tours?locale=en&duration=multi-day')
        ->assertOk();
});

it('rejects invalid duration values', function () {
    getJson('/api/public/search/tours?locale=en&duration=invalid')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['duration']);
});

it('accepts date filter parameter', function () {
    getJson('/api/public/search/tours?locale=en&date=2026-06-01')
        ->assertOk();
});

it('rejects invalid date format', function () {
    getJson('/api/public/search/tours?locale=en&date=01-06-2026')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date']);
});

it('accepts sort parameters', function (string $sort) {
    getJson("/api/public/search/tours?locale=en&sort={$sort}")
        ->assertOk();
})->with(['price_asc', 'price_desc', 'rating', 'newest', 'relevance']);

it('rejects invalid sort value', function () {
    getJson('/api/public/search/tours?locale=en&sort=invalid_sort')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);
});

it('applies combined filters successfully', function () {
    getJson('/api/public/search/tours?locale=en&category=adventure&location=paris&price_min=1000&price_max=10000&duration=full-day&sort=rating')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->has('data')
                 ->has('filters')
                 ->etc()
        );
});

it('returns filter metadata with categories in response', function () {
    getJson('/api/public/search/tours?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->has('filters.categories')
                 ->has('filters.locations')
                 ->has('filters.price_range', fn (AssertableJson $range) =>
                    $range->has('min')->has('max')
                 )
                 ->has('filters.durations')
                 ->etc()
        );
});
