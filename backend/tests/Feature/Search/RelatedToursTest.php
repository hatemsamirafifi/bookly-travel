<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Listeners\UpdateTourAggregateRating;
use App\Domains\Reviews\Models\Review;
use App\Domains\Search\Actions\GetRelatedToursAction;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('clamps an internally requested recommendation count to eight', function () {
    $partner = makePartner();
    $current = makeRelatedTour($this->category, $partner, 'related-ceiling-current');
    addFutureRule($current);
    for ($index = 0; $index < 12; $index++) {
        $candidate = makeRelatedTour($this->category, $partner, "related-ceiling-{$index}");
        addFutureRule($candidate);
    }
    $cards = app(GetRelatedToursAction::class)->execute($current, 'en', 100);
    expect($cards)->toHaveCount(8)
        ->and(array_unique(array_column($cards, 'id')))->toHaveCount(8)
        ->and(array_column($cards, 'id'))->not->toContain($current->id);
});

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'is_active' => true,
        'display_order' => 1,
    ]);
    $this->otherCategory = Category::create([
        'name' => 'Other Category',
        'slug' => 'other-category',
        'is_active' => true,
        'display_order' => 2,
    ]);
});

/**
 * Create a published tour with an English translation for related tests.
 */
function makeRelatedTour(Category $category, Partner $partner, string $slug, string $locationSlug = 'rome', string $status = 'published'): Tour
{
    $tour = Tour::create([
        'category_id' => $category->id,
        'partner_id' => $partner->id,
        'price_amount' => 5000,
        'slug' => $slug,
        'location' => $locationSlug === 'rome' ? 'Rome, Italy' : 'Florence, Italy',
        'location_slug' => $locationSlug,
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'status' => $status,
    ]);
    TourTranslation::create([
        'tour_id' => $tour->id,
        'locale' => 'en',
        'title' => $slug,
        'description' => 'A guided visit.',
    ]);

    return $tour;
}

/**
 * Attach a future specific-date availability rule so the tour satisfies
 * the exact shared public-bookability invariant.
 */
function addFutureRule(Tour $tour, int $days = 7): string
{
    $date = now()->addDays($days)->toDateString();
    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'specific_date',
        'start_date' => $date,
        'capacity' => 8,
    ]);

    return $date;
}

/**
 * Create a genuine visible review with a backing completed booking and sync
 * the denormalized aggregate columns through the review service listener.
 */
function addGenuineReview(Tour $tour, User $traveler, int $rating): void
{
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => 'completed',
    ]);
    $review = Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => $rating,
        'status' => 'visible',
        'locale' => 'en',
    ]);
    app(UpdateTourAggregateRating::class)->handle(new ReviewSubmitted($review));
}

it('excludes the current, unpublished and unbookable tours while ranking destination before category', function () {
    $partner = makePartner();

    $current = makeRelatedTour($this->category, $partner, 'related-current');
    addFutureRule($current);

    // A higher-rated category-only match must still lose to a destination
    // match: destination precedes category precedes genuine rating.
    $destinationMatch = makeRelatedTour($this->otherCategory, $partner, 'related-destination');
    addFutureRule($destinationMatch);

    $categoryMatch = makeRelatedTour($this->category, $partner, 'related-category', 'florence');
    addFutureRule($categoryMatch);
    $traveler = User::factory()->traveler()->create();
    addGenuineReview($categoryMatch, $traveler, 5);

    // Ineligible: unpublished draft, published without rules, expired rules.
    makeRelatedTour($this->category, $partner, 'related-draft', 'rome', 'draft');
    makeRelatedTour($this->category, $partner, 'related-norules');
    $expired = makeRelatedTour($this->category, $partner, 'related-expired');
    AvailabilityRule::create([
        'tour_id' => $expired->id,
        'rule_type' => 'specific_date',
        'start_date' => now()->subDay()->toDateString(),
        'capacity' => 8,
    ]);

    // Matches neither destination nor category: never a candidate.
    makeRelatedTour($this->otherCategory, $partner, 'related-unrelated', 'florence');

    getJson('/api/public/tours/related-current?locale=en')->assertOk()
        ->assertJsonCount(2, 'data.related_tours')
        ->assertJsonPath('data.related_tours.0.slug', 'related-destination')
        ->assertJsonPath('data.related_tours.1.slug', 'related-category');
});

it('finds valid recommendations beyond more than 32 leading ineligible candidates', function () {
    $partner = makePartner();

    // Published tours with expired rules pass the approximate SQL scope
    // but fail exact availability. An unconditional 32-row cap hides the
    // four valid tours behind them.
    for ($i = 1; $i <= 33; $i++) {
        $expired = makeRelatedTour($this->category, $partner, "related-filler-{$i}");
        AvailabilityRule::create([
            'tour_id' => $expired->id,
            'rule_type' => 'specific_date',
            'start_date' => now()->subDay()->toDateString(),
            'capacity' => 8,
        ]);
        expect($expired->isPubliclyBookable())->toBeFalse();
    }

    $current = makeRelatedTour($this->category, $partner, 'related-paged-current');
    addFutureRule($current);

    // Equal genuine stats: deterministic stable-ID order across the page
    // boundary, with no duplicates and no padding.
    $expected = [];
    for ($i = 1; $i <= 4; $i++) {
        $tour = makeRelatedTour($this->category, $partner, "related-paged-valid-{$i}");
        addFutureRule($tour, 7 + $i);
        $expected[] = "related-paged-valid-{$i}";
    }

    $response = getJson('/api/public/tours/related-paged-current?locale=en')->assertOk()
        ->assertJsonCount(4, 'data.related_tours');

    expect($response->json('data.related_tours.*.slug'))->toBe($expected);
});

it('ranks genuine review stats ahead of stale cached aggregates', function () {
    $partner = makePartner();
    $traveler = User::factory()->traveler()->create();

    $current = makeRelatedTour($this->category, $partner, 'related-stale-current');
    addFutureRule($current);

    // Hand-filled cached numbers with zero real reviews: an invented
    // aggregate that must not outrank genuine eligible review stats.
    $stale = makeRelatedTour($this->category, $partner, 'related-stale-cached');
    addFutureRule($stale);
    DB::table('tours')->where('id', $stale->id)->update(['average_rating' => 5.0, 'review_count' => 25]);
    expect($stale->fresh()->averageRating())->toBe(5.0)
        ->and($stale->fresh()->reviewCount())->toBe(25);

    // Genuine stats synced from real visible rows: (5 + 4 + 4) / 3 = 4.33.
    $genuine = makeRelatedTour($this->category, $partner, 'related-genuine');
    addFutureRule($genuine);
    addGenuineReview($genuine, $traveler, 5);
    addGenuineReview($genuine, $traveler, 4);
    addGenuineReview($genuine, $traveler, 4);

    getJson('/api/public/tours/related-stale-current?locale=en')->assertOk()
        ->assertJsonCount(2, 'data.related_tours')
        ->assertJsonPath('data.related_tours.0.slug', 'related-genuine')
        ->assertJsonPath('data.related_tours.0.rating.average', 4.33)
        ->assertJsonPath('data.related_tours.0.rating.count', 3)
        ->assertJsonPath('data.related_tours.1.slug', 'related-stale-cached')
        ->assertJsonPath('data.related_tours.1.rating.average', 0)
        ->assertJsonPath('data.related_tours.1.rating.count', 0);
    expect($stale->fresh()->averageRating())->toBe(5.0)
        ->and($stale->fresh()->reviewCount())->toBe(25);
});

it('breaks rating ties deterministically by review count then stable id', function () {
    $partner = makePartner();
    $traveler = User::factory()->traveler()->create();

    $current = makeRelatedTour($this->category, $partner, 'related-tie-current');
    addFutureRule($current);

    // Same destination, same category, same genuine average: higher genuine
    // review count wins; a full tie falls back to stable ID order.
    $moreReviews = makeRelatedTour($this->category, $partner, 'related-tie-more');
    addFutureRule($moreReviews);
    addGenuineReview($moreReviews, $traveler, 4);
    addGenuineReview($moreReviews, $traveler, 4);

    $fewerReviews = makeRelatedTour($this->category, $partner, 'related-tie-fewer');
    addFutureRule($fewerReviews);
    addGenuineReview($fewerReviews, $traveler, 4);

    $untiedA = makeRelatedTour($this->category, $partner, 'related-tie-a');
    addFutureRule($untiedA);
    $untiedB = makeRelatedTour($this->category, $partner, 'related-tie-b');
    addFutureRule($untiedB);

    getJson('/api/public/tours/related-tie-current?locale=en')->assertOk()
        ->assertJsonCount(4, 'data.related_tours')
        ->assertJsonPath('data.related_tours.0.slug', 'related-tie-more')
        ->assertJsonPath('data.related_tours.1.slug', 'related-tie-fewer')
        ->assertJsonPath('data.related_tours.2.slug', 'related-tie-a')
        ->assertJsonPath('data.related_tours.3.slug', 'related-tie-b');
});

it('returns the default four cards and fewer real cards without padding', function () {
    $partner = makePartner();

    $current = makeRelatedTour($this->category, $partner, 'related-limit-current');
    addFutureRule($current);

    for ($i = 1; $i <= 6; $i++) {
        $tour = makeRelatedTour($this->category, $partner, "related-limit-{$i}");
        addFutureRule($tour, 7 + $i);
    }

    // Default is four even when more tours are eligible.
    getJson('/api/public/tours/related-limit-current?locale=en')->assertOk()
        ->assertJsonCount(4, 'data.related_tours')
        ->assertJsonPath('data.related_tours.0.slug', 'related-limit-1')
        ->assertJsonPath('data.related_tours.3.slug', 'related-limit-4');

    // Fewer eligible tours yield fewer real cards, never padding.
    $sparse = makeRelatedTour($this->otherCategory, $partner, 'related-sparse-current', 'florence');
    addFutureRule($sparse);
    $onlyA = makeRelatedTour($this->otherCategory, $partner, 'related-sparse-a', 'florence');
    addFutureRule($onlyA);
    $onlyB = makeRelatedTour($this->otherCategory, $partner, 'related-sparse-b', 'florence');
    addFutureRule($onlyB);

    getJson('/api/public/tours/related-sparse-current?locale=en')->assertOk()
        ->assertJsonCount(2, 'data.related_tours')
        ->assertJsonPath('data.related_tours.0.slug', 'related-sparse-a')
        ->assertJsonPath('data.related_tours.1.slug', 'related-sparse-b');
});

it('exposes no public limit parameter for related selection', function () {
    $partner = makePartner();

    $current = makeRelatedTour($this->category, $partner, 'related-nolimit-current');
    addFutureRule($current);

    for ($i = 1; $i <= 6; $i++) {
        $tour = makeRelatedTour($this->category, $partner, "related-nolimit-{$i}");
        addFutureRule($tour, 7 + $i);
    }

    $plain = getJson('/api/public/tours/related-nolimit-current?locale=en')->assertOk()->json('data.related_tours.*.slug');
    $withLimit = getJson('/api/public/tours/related-nolimit-current?locale=en&limit=50')->assertOk()->json('data.related_tours.*.slug');

    // Unknown limit inputs change nothing: the default-four / internal
    // absolute-eight clamp stays server-side with no query-surface change.
    expect($withLimit)->toBe($plain)->and($plain)->toHaveCount(4);
});

it('preserves the TourCard contract with server minor-unit pricing and localized titles', function () {
    $partner = makePartner();

    $current = makeRelatedTour($this->category, $partner, 'related-card-current');
    addFutureRule($current);

    $candidate = makeRelatedTour($this->category, $partner, 'related-card-candidate');
    $ruleDate = addFutureRule($candidate);

    $response = getJson('/api/public/tours/related-card-current?locale=en')->assertOk()
        ->assertJsonCount(1, 'data.related_tours');

    $card = $response->json('data.related_tours.0');

    // Existing TourCard field shapes are preserved exactly.
    expect(array_keys($card))->toBe([
        'id', 'slug', 'title', 'location', 'category', 'duration_label',
        'price', 'rating', 'cover_image_url', 'group_size', 'next_available_date',
    ]);

    // Localized current-hash title, server-authoritative minor-unit price
    // and genuine aggregate stats flow through unchanged.
    expect($card['title'])->toBe('related-card-candidate')
        ->and($card['price']['amount'])->toBe(5000)
        ->and($card['price']['currency'])->toBe('EUR')
        ->and($card['price']['formatted'])->toBe('€50.00')
        ->and($card['rating'])->toBe(['average' => 0, 'count' => 0])
        ->and($card['next_available_date'])->toBe($ruleDate);
});
