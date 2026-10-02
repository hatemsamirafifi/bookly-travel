<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\TourMedia;
use App\Domains\Partner\Services\TourTranslationService;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Listeners\UpdateTourAggregateRating;
use App\Domains\Reviews\Models\Review;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

function markDerivedTourTranslationReady(Tour $tour, string $locale): void
{
    $english = $tour->translations()->where('locale', 'en')->firstOrFail();
    $hash = app(TourTranslationService::class)->sourceHash($english);
    TourTranslationState::create([
        'tour_id' => $tour->id,
        'locale' => $locale,
        'source_hash' => $hash,
        'translated_hash' => $hash,
        'status' => 'ready',
    ]);
}

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
        ->assertJsonStructure(['data' => [
            'slug', 'title', 'description', 'content_locale', 'translation_status',
            'itinerary', 'itinerary_locale', 'languages', 'guide_languages',
            'highlights', 'inclusions', 'exclusions', 'important_information',
            'images', 'pricing', 'availability', 'rating', 'reviews', 'seo',
        ]])
        ->assertJsonPath('data.content_locale', 'en')
        ->assertJsonPath('data.translation_status', 'source')
        ->assertJsonMissingPath('data.source_hash')
        ->assertJsonMissingPath('data.last_error_code')
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

it('returns the selected cover before ordered gallery media and exposes genuine difficulty', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'ordered-gallery',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'status' => 'published',
        'cover_image_url' => 'https://images.example.com/cover.jpg',
        'difficulty_level' => 'moderate',
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id,
        'locale' => 'en',
        'title' => 'Ordered Gallery',
        'description' => 'A guided walk through Rome.',
    ]);
    TourMedia::create(['tour_id' => $tour->id, 'type' => 'image', 'url' => 'https://images.example.com/side-b.jpg', 'sort_order' => 2]);
    TourMedia::create(['tour_id' => $tour->id, 'type' => 'image', 'url' => 'https://images.example.com/side-a.jpg', 'sort_order' => 1]);
    TourMedia::create(['tour_id' => $tour->id, 'type' => 'image', 'url' => 'https://images.example.com/cover.jpg', 'sort_order' => 3]);

    getJson('/api/public/tours/ordered-gallery?locale=en')->assertOk()
        ->assertJsonPath('data.difficulty_level', 'moderate')
        ->assertJsonPath('data.images.0.url', 'https://images.example.com/cover.jpg')
        ->assertJsonPath('data.images.0.is_cover', true)
        ->assertJsonPath('data.images.1.url', 'https://images.example.com/side-a.jpg')
        ->assertJsonPath('data.images.2.url', 'https://images.example.com/side-b.jpg');
});

it('keeps the legacy cover as the only image when no gallery media exists', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'legacy-cover',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'status' => 'published',
        'cover_image_url' => 'https://images.example.com/legacy.jpg',
    ]);
    TourTranslation::create(['tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Legacy Cover', 'description' => 'A classic visit.']);

    getJson('/api/public/tours/legacy-cover?locale=en')->assertOk()
        ->assertJsonCount(1, 'data.images')
        ->assertJsonPath('data.images.0.url', 'https://images.example.com/legacy.jpg')
        ->assertJsonPath('data.images.0.is_cover', true)
        ->assertJsonPath('data.difficulty_level', null);
});

it('returns only approved public operator fields and visible-review aggregates', function () {
    $partner = makePartner();
    $partner->profile()->create([
        'company_name' => 'Rome Walks',
        'business_description' => 'Locally operated walks.',
        'logo_url' => 'https://images.example.com/operator.jpg',
        'contact_email' => 'private@example.com',
        'contact_phone' => '+390000000',
        'tax_id' => 'SECRET-TAX',
        'payout_iban' => 'IT60X0542811101000000123456',
    ]);
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => $partner->id,
        'price_amount' => 5000,
        'slug' => 'operator-tour',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'status' => 'published',
    ]);
    TourTranslation::create(['tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Operator Tour', 'description' => 'A guided visit.']);
    AvailabilityRule::create([
        'tour_id' => $tour->id, 'rule_type' => 'specific_date',
        'start_date' => now()->addWeek()->toDateString(), 'capacity' => 8,
    ]);
    $traveler = User::factory()->traveler()->create();
    foreach (['visible' => 5, 'hidden' => 1] as $status => $rating) {
        $booking = Booking::create([
            'reference' => Booking::generateReference(), 'traveler_id' => $traveler->id,
            'tour_id' => $tour->id, 'tour_date' => now()->subDay(),
            'participant_count' => 1, 'price_per_person' => 5000,
            'total_price' => 5000, 'currency' => 'EUR', 'status' => 'completed',
        ]);
        $review = Review::create([
            'booking_id' => $booking->id, 'tour_id' => $tour->id,
            'traveler_id' => $traveler->id, 'rating' => $rating,
            'status' => $status, 'locale' => 'en',
        ]);
        app(UpdateTourAggregateRating::class)->handle(new ReviewSubmitted($review));
    }

    $response = getJson('/api/public/tours/operator-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator.name', 'Rome Walks')
        ->assertJsonPath('data.operator.description', 'Locally operated walks.')
        ->assertJsonPath('data.operator.tour_count', 1)
        ->assertJsonPath('data.operator.review_count', 1)
        ->assertJsonPath('data.operator.average_rating', 5);
    $operator = $response->json('data.operator');
    expect(array_keys($operator))->toBe(['name', 'description', 'logo_url', 'tour_count', 'review_count', 'average_rating']);
    expect(json_encode($operator))->not->toContain('private@example.com', 'SECRET-TAX', 'IT60');
});

it('omits operator identity when its partner is not approved', function () {
    $partner = makePartner('pending');
    $partner->profile()->create(['company_name' => 'Unapproved Operator', 'contact_email' => 'private@example.com']);
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => $partner->id,
        'price_amount' => 5000,
        'slug' => 'unapproved-operator-tour',
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'status' => 'published',
    ]);
    TourTranslation::create(['tour_id' => $tour->id, 'locale' => 'en', 'title' => 'Tour', 'description' => 'A guided visit.']);

    getJson('/api/public/tours/unapproved-operator-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator', null)
        ->assertDontSee('Unapproved Operator')
        ->assertDontSee('private@example.com');
});

it('ranks only published bookable related tours by destination then category then review quality', function () {
    $partner = makePartner();
    $otherCategory = Category::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
    $makeTour = function (string $slug, string $location, int $categoryId, string $status = 'published', bool $available = true) use ($partner): Tour {
        $tour = Tour::create([
            'category_id' => $categoryId, 'partner_id' => $partner->id,
            'price_amount' => 5000, 'slug' => $slug,
            'location' => $location, 'location_slug' => str_contains($location, 'Rome') ? 'rome' : 'florence',
            'duration_minutes' => 180, 'duration_label' => '3 hours',
            'group_size_min' => 1, 'group_size_max' => 8, 'status' => $status,
        ]);
        TourTranslation::create(['tour_id' => $tour->id, 'locale' => 'en', 'title' => $slug, 'description' => 'A guided visit.']);
        if ($available) {
            AvailabilityRule::create([
                'tour_id' => $tour->id, 'rule_type' => 'specific_date',
                'start_date' => now()->addWeek()->toDateString(), 'capacity' => 8,
            ]);
        }

        return $tour;
    };
    $makeTour('current-tour', 'Rome, Italy', $this->category->id);
    $rated = $makeTour('same-destination', 'Rome, Italy', $otherCategory->id);
    $makeTour('same-destination-unrated', 'Rome, Italy', $otherCategory->id);
    $makeTour('same-category', 'Florence, Italy', $this->category->id);
    $makeTour('unrelated', 'Florence, Italy', $otherCategory->id);
    $makeTour('draft-related', 'Rome, Italy', $this->category->id, 'draft');
    $makeTour('unbookable-related', 'Rome, Italy', $this->category->id, 'published', false);
    $traveler = User::factory()->traveler()->create();
    $booking = Booking::create([
        'reference' => Booking::generateReference(), 'traveler_id' => $traveler->id,
        'tour_id' => $rated->id, 'tour_date' => now()->subDay(),
        'participant_count' => 1, 'price_per_person' => 5000,
        'total_price' => 5000, 'currency' => 'EUR', 'status' => 'completed',
    ]);
    $review = Review::create([
        'booking_id' => $booking->id, 'tour_id' => $rated->id,
        'traveler_id' => $traveler->id, 'rating' => 5,
        'status' => 'visible', 'locale' => 'en',
    ]);
    app(UpdateTourAggregateRating::class)->handle(new ReviewSubmitted($review));

    getJson('/api/public/tours/current-tour?locale=en')->assertOk()
        ->assertJsonCount(3, 'data.related_tours')
        ->assertJsonPath('data.related_tours.0.slug', 'same-destination')
        ->assertJsonPath('data.related_tours.1.slug', 'same-destination-unrated')
        ->assertJsonPath('data.related_tours.2.slug', 'same-category');
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
    AvailabilityRule::create([
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
    markDerivedTourTranslationReady($tour, 'es');

    getJson('/api/public/tours/localized-tour?locale=es')
        ->assertOk()
        ->assertJsonPath('data.title', 'Título en Español')
        ->assertJsonPath('data.description', 'Descripción en español');
});

it('keeps guide languages separate from localized content and itinerary', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'multilingual-guide-tour',
        'location' => 'Rome',
        'location_slug' => 'rome',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
        'guide_languages' => ['de', 'en', 'es'],
    ]);

    foreach (['en' => 'English', 'es' => 'Español', 'it' => 'Italiano'] as $locale => $language) {
        TourTranslation::create([
            'tour_id' => $tour->id,
            'locale' => $locale,
            'title' => "{$language} title",
            'description' => "{$language} description",
            'itinerary' => [['day' => 1, 'title' => "{$language} itinerary", 'stops' => []]],
        ]);

        if ($locale !== 'en') {
            markDerivedTourTranslationReady($tour, $locale);
        }

        expect($tour->load('translations')->toSearchableArray()['languages'])->toBe(['de', 'en', 'es']);

        getJson("/api/public/tours/multilingual-guide-tour?locale={$locale}")
            ->assertOk()
            ->assertJsonPath('data.title', "{$language} title")
            ->assertJsonPath('data.content_locale', $locale)
            ->assertJsonPath('data.description', "{$language} description")
            ->assertJsonPath('data.itinerary.0.title', "{$language} itinerary")
            ->assertJsonPath('data.itinerary_locale', $locale)
            ->assertJsonPath('data.guide_languages', ['de', 'en', 'es'])
            ->assertJsonPath('data.languages', ['de', 'en', 'es'])
            ->assertJsonMissingPath('data.translation_warning');
    }
});

it('marks an English itinerary fallback when only that field lacks localization', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'partial-itinerary-tour',
        'location' => 'Rome',
        'location_slug' => 'rome',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
        'guide_languages' => ['de'],
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'English title',
        'description' => 'English description',
        'itinerary' => [['day' => 1, 'title' => 'English itinerary', 'stops' => []]],
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'es', 'title' => 'Título español',
        'description' => 'Descripción española',
    ]);
    markDerivedTourTranslationReady($tour, 'es');

    getJson('/api/public/tours/partial-itinerary-tour?locale=es')
        ->assertOk()
        ->assertJsonPath('data.title', 'Título español')
        ->assertJsonPath('data.itinerary.0.title', 'English itinerary')
        ->assertJsonPath('data.content_locale', 'es')
        ->assertJsonPath('data.itinerary_locale', 'en')
        ->assertJsonPath('data.translation_warning', 'partial_translation')
        ->assertJsonPath('data.guide_languages', ['de']);
});

it('backfills a valid legacy itinerary into English without overwriting legacy data', function () {
    $tour = Tour::create([
        'category_id' => $this->category->id,
        'partner_id' => makePartner()->id,
        'price_amount' => 5000,
        'slug' => 'legacy-itinerary-tour',
        'location' => 'Rome',
        'location_slug' => 'rome',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'status' => 'published',
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'en', 'title' => 'English title',
        'description' => 'English description',
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id, 'locale' => 'es', 'title' => 'Título español',
        'description' => 'Descripción española',
    ]);
    markDerivedTourTranslationReady($tour, 'es');
    $legacy = json_encode(['Arrival', 'City tour'], JSON_THROW_ON_ERROR);
    DB::table('tours')->where('id', $tour->id)->update(['itinerary' => $legacy]);

    $migration = require database_path('migrations/2026_09_25_000001_add_guide_languages_and_localized_itinerary.php');
    $migration->backfillLegacyItineraries();

    expect(DB::table('tours')->where('id', $tour->id)->value('itinerary'))->not->toBeNull();
    getJson('/api/public/tours/legacy-itinerary-tour?locale=es')
        ->assertOk()
        ->assertJsonPath('data.title', 'English title')
        ->assertJsonPath('data.content_locale', 'en')
        ->assertJsonPath('data.translation_status', 'stale')
        ->assertJsonPath('data.itinerary.0.title', 'Arrival')
        ->assertJsonPath('data.itinerary.1.title', 'City tour')
        ->assertJsonPath('data.itinerary_locale', 'en')
        ->assertJsonPath('data.translation_warning', 'partial_translation');
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
        ->assertJsonPath('data.content_locale', 'en')
        ->assertJsonPath('data.translation_warning', 'partial_translation')
        ->assertJsonPath('data.guide_languages', [])
        ->assertJsonPath('data.languages', []);
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
