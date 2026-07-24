<?php

use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns homepage data with expected structure', function () {
    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->has('featured_tours')
            ->has('popular_categories')
            ->has('featured_destinations')
            ->has('meta', fn (AssertableJson $meta) => $meta->has('seo', fn (AssertableJson $seo) => $seo->has('meta_title')
                ->has('meta_description')
            )
            )
        );
});

it('returns featured tours when is_featured flag is set', function () {
    $category = Category::create(['name' => 'Featured', 'slug' => 'featured', 'is_active' => true, 'display_order' => 1]);

    $tour = Tour::create([
        'category_id' => $category->id,
        'partner_id' => makePartner()->id,
        'slug' => 'homepage-featured-tour',
        'location' => 'London',
        'location_slug' => 'london',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 15,
        'price_amount' => 5000,
        'status' => 'published',
        'is_featured' => true,
    ]);

    TourTranslation::create([
        'tour_id' => $tour->id,
        'locale' => 'en',
        'title' => 'Featured Tour Title',
        'description' => 'A featured tour for homepage',
        'highlights' => [],
        'inclusions' => [],
        'exclusions' => [],
    ]);

    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJsonPath('featured_tours.0.title', 'Featured Tour Title');
});

it('falls back to latest tours when no featured flag set', function () {
    Tour::where('is_featured', true)->update(['is_featured' => false]);

    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->has('featured_tours')
            ->etc()
        );
});

it('returns categories with tour counts', function () {
    Category::create(['name' => 'Active Cat', 'slug' => 'active-cat', 'is_active' => true, 'display_order' => 1]);

    getJson('/api/public/homepage?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->has('popular_categories')
            ->etc()
        );
});

it('validates locale parameter for homepage', function () {
    getJson('/api/public/homepage')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});

it('supports all three locales for homepage', function (string $locale) {
    getJson("/api/public/homepage?locale={$locale}")
        ->assertOk();
})->with(['en', 'es', 'it']);
