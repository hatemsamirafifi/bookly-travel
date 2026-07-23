<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Reviews\Models\Review;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'is_active' => true,
        'display_order' => 1,
    ]);
});

it('returns tour detail for a valid slug and locale', function () {
    $category = Category::create([
        'name' => 'Adventure',
        'slug' => 'adventure',
        'is_active' => true,
        'display_order' => 1,
    ]);

    $tour = Tour::create([
        'category_id' => $category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
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
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
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
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
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

it('returns 410 for an archived tour that was previously published', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'archived-tour',
        'location' => 'Madrid',
        'location_slug' => 'madrid',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 2,
        'group_size_max' => 15,
        'status' => 'archived',
        'published_at' => now()->subWeek(),
    ]);

    getJson('/api/public/tours/archived-tour?locale=en')
        ->assertStatus(410)
        ->assertJson(['message' => 'This tour is no longer available.']);
});

it('returns 404 for an archived tour that was never published', function () {
    // Contract tour-detail-api.md:107-114 — archived + published_at null (never
    // publicly accessible) is 404, not 410, so stale links don't claim the
    // resource once existed.
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'never-published-archived',
        'location' => 'Lisbon',
        'location_slug' => 'lisbon',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 2,
        'group_size_max' => 15,
        'status' => 'archived',
        'published_at' => null,
    ]);

    getJson('/api/public/tours/never-published-archived?locale=en')
        ->assertStatus(404)
        ->assertJson(['message' => 'Tour not found.']);
});

it('marks a published tour with no valid pricing as unavailable (F2)', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 0,
        'slug' => 'mispriced-tour',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Mispriced',
        'description' => 'x', 'highlights' => [], 'inclusions' => [], 'exclusions' => [],
    ]);

    getJson('/api/public/tours/mispriced-tour?locale=en')
        ->assertOk()
        ->assertJsonPath('data.availability.is_unavailable', true);
});

it('marks a published tour with no upcoming availability as unavailable (F2)', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'expired-availability-tour',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Expired',
        'description' => 'x', 'highlights' => [], 'inclusions' => [], 'exclusions' => [],
    ]);

    // No availability rules → no upcoming dates → not bookable, but still 200
    // per the contract's "Currently Unavailable" state.
    getJson('/api/public/tours/expired-availability-tour?locale=en')
        ->assertOk()
        ->assertJsonPath('data.availability.is_unavailable', true);
});

it('serves a fully bookable published tour as available (F2)', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'bookable-tour',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Bookable',
        'description' => 'x', 'highlights' => [], 'inclusions' => [], 'exclusions' => [],
    ]);
    \App\Domains\Partner\Models\AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
        'capacity' => 10,
    ]);

    getJson('/api/public/tours/bookable-tour?locale=en')
        ->assertOk()
        ->assertJsonPath('data.availability.is_unavailable', false);
});

it('returns content in the requested locale', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
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
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
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
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
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

it('computes the review distribution from visible reviews (L4)', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'reviewed-tour',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
        'average_rating' => 4.5,
        'review_count' => 4,
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Reviewed',
        'description' => 'x', 'highlights' => [], 'inclusions' => [], 'exclusions' => [],
    ]);

    $traveler = User::factory()->create();
    foreach ([5, 5, 4, 3] as $rating) {
        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $traveler->id,
            'tour_id' => $tour->id,
            'tour_date' => now()->subDay(),
            'participant_count' => 1,
            'price_per_person' => 5000,
            'total_price' => 5000,
            'currency' => 'EUR',
            'status' => Booking::STATUS_COMPLETED,
        ]);
        Review::create([
            'booking_id' => $booking->id,
            'tour_id' => $tour->id,
            'traveler_id' => $traveler->id,
            'rating' => $rating,
            'comment' => 'ok',
            'status' => 'visible',
            'locale' => 'en',
        ]);
    }

    getJson('/api/public/tours/reviewed-tour?locale=en')
        ->assertOk()
        ->assertJsonPath('data.reviews.distribution', [
            '5' => 2, '4' => 1, '3' => 1, '2' => 0, '1' => 0,
        ]);
});

it('excludes hidden reviews from the distribution (L4)', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'hidden-review-tour',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Hidden Reviews',
        'description' => 'x', 'highlights' => [], 'inclusions' => [], 'exclusions' => [],
    ]);

    $traveler = User::factory()->create();
    foreach ([5, 1] as $rating) {
        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'traveler_id' => $traveler->id,
            'tour_id' => $tour->id,
            'tour_date' => now()->subDay(),
            'participant_count' => 1,
            'price_per_person' => 5000,
            'total_price' => 5000,
            'currency' => 'EUR',
            'status' => Booking::STATUS_COMPLETED,
        ]);
        Review::create([
            'booking_id' => $booking->id,
            'tour_id' => $tour->id,
            'traveler_id' => $traveler->id,
            'rating' => $rating,
            'comment' => 'ok',
            'status' => $rating === 1 ? 'hidden' : 'visible',
            'locale' => 'en',
        ]);
    }

    // The hidden 1-star review must not appear in the public distribution.
    getJson('/api/public/tours/hidden-review-tour?locale=en')
        ->assertOk()
        ->assertJsonPath('data.reviews.distribution', [
            '5' => 1, '4' => 0, '3' => 0, '2' => 0, '1' => 0,
        ]);
});
