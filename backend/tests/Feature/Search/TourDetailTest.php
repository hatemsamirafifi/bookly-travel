<?php

use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\getJson;

it('returns tour detail for a valid slug and locale', function () {
    $category = Category::create([
        'name' => 'Adventure',
        'slug' => 'adventure',
        'is_active' => true,
        'display_order' => 1,
    ]);

    $tour = Tour::create([
        'category_id' => $category->id,
        'slug' => 'test-adventure',
        'location' => 'Paris, France',
        'location_slug' => 'paris',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 2,
        'group_size_max' => 10,
        'status' => 'published',
    ]);

    TourTranslation::create([
        'tour_id' => $tour->id,
        'locale' => 'en',
        'title' => 'Test Adventure Tour',
        'description' => 'An amazing adventure in Paris',
        'highlights' => ['Eiffel Tower', 'Louvre'],
        'inclusions' => ['Guide', 'Transport'],
        'exclusions' => ['Meals'],
        'meeting_point' => 'Eiffel Tower entrance',
        'cancellation_policy' => 'Free cancellation 24h before',
    ]);

    getJson('/api/public/tours/test-adventure?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->has('data', fn (AssertableJson $data) => $data->where('slug', 'test-adventure')
            ->where('title', 'Test Adventure Tour')
            ->where('location', 'Paris, France')
            ->has('category')
            ->has('duration')
            ->has('pricing')
            ->has('availability')
            ->has('reviews')
            ->has('seo')
            ->etc()
        )
        );
});

it('returns 404 for non-existent slug', function () {
    getJson('/api/public/tours/non-existent-tour?locale=en')
        ->assertStatus(404)
        ->assertJson(['message' => 'Tour not found.']);
});

it('returns 404 for draft tour', function () {
    $tour = Tour::create([
        'category_id' => null,
        'slug' => 'draft-tour',
        'location' => 'Rome',
        'location_slug' => 'rome',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 5,
        'status' => 'draft',
    ]);

    getJson('/api/public/tours/draft-tour?locale=en')
        ->assertStatus(404);
});

it('returns 404 for rejected tour', function () {
    $tour = Tour::create([
        'category_id' => null,
        'slug' => 'rejected-tour',
        'location' => 'Berlin',
        'location_slug' => 'berlin',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'status' => 'rejected',
    ]);

    getJson('/api/public/tours/rejected-tour?locale=en')
        ->assertStatus(404);
});

it('returns 410 for archived tour', function () {
    $tour = Tour::create([
        'category_id' => null,
        'slug' => 'archived-tour',
        'location' => 'Madrid',
        'location_slug' => 'madrid',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 2,
        'group_size_max' => 15,
        'status' => 'archived',
    ]);

    getJson('/api/public/tours/archived-tour?locale=en')
        ->assertStatus(410)
        ->assertJson(['message' => 'This tour is no longer available.']);
});

it('returns content in the requested locale', function () {
    $tour = Tour::create([
        'category_id' => null,
        'slug' => 'localized-tour',
        'location' => 'Barcelona',
        'location_slug' => 'barcelona',
        'duration_minutes' => 360,
        'duration_label' => '6 hours',
        'group_size_min' => 1,
        'group_size_max' => 20,
        'status' => 'published',
    ]);

    TourTranslation::create([
        'tour_id' => $tour->id,
        'locale' => 'en',
        'title' => 'English Title',
        'description' => 'English description',
        'highlights' => [],
        'inclusions' => [],
        'exclusions' => [],
    ]);

    TourTranslation::create([
        'tour_id' => $tour->id,
        'locale' => 'es',
        'title' => 'Título en Español',
        'description' => 'Descripción en español',
        'highlights' => [],
        'inclusions' => [],
        'exclusions' => [],
    ]);

    getJson('/api/public/tours/localized-tour?locale=es')
        ->assertOk()
        ->assertJsonPath('data.title', 'Título en Español')
        ->assertJsonPath('data.description', 'Descripción en español');
});

it('falls back to English when requested locale is unavailable', function () {
    $tour = Tour::create([
        'category_id' => null,
        'slug' => 'fallback-tour',
        'location' => 'Milan',
        'location_slug' => 'milan',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
    ]);

    TourTranslation::create([
        'tour_id' => $tour->id,
        'locale' => 'en',
        'title' => 'English Fallback Title',
        'description' => 'English fallback description',
        'highlights' => [],
        'inclusions' => [],
        'exclusions' => [],
    ]);

    getJson('/api/public/tours/fallback-tour?locale=it')
        ->assertOk()
        ->assertJsonPath('data.title', 'English Fallback Title')
        ->assertJsonPath('data.translation_warning', 'partial_translation');
});

it('validates locale parameter is required', function () {
    getJson('/api/public/tours/any-tour')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});

it('includes SEO metadata in response', function () {
    $tour = Tour::create([
        'category_id' => null,
        'slug' => 'seo-tour',
        'location' => 'Tokyo',
        'location_slug' => 'tokyo',
        'duration_minutes' => 480,
        'duration_label' => '8 hours',
        'group_size_min' => 2,
        'group_size_max' => 6,
        'status' => 'published',
    ]);

    TourTranslation::create([
        'tour_id' => $tour->id,
        'locale' => 'en',
        'title' => 'SEO Tour',
        'description' => 'A tour with proper SEO metadata',
        'highlights' => [],
        'inclusions' => [],
        'exclusions' => [],
    ]);

    getJson('/api/public/tours/seo-tour?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->has('data.seo', fn (AssertableJson $seo) => $seo->has('meta_title')
            ->has('meta_description')
            ->has('canonical_url')
            ->has('hreflang', fn (AssertableJson $hreflang) => $hreflang->has('en')->has('es')->has('it')
            )
        )
        );
});
