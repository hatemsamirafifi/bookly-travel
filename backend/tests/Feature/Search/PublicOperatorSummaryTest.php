<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Listeners\UpdateTourAggregateRating;
use App\Domains\Reviews\Models\Review;
use App\Domains\Search\Actions\GetPublicOperatorSummaryAction;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

/**
 * Create a published tour for the given partner with an English translation.
 */
function makeOperatorTour(Category $category, Partner $partner, string $slug, int $price = 5000, string $status = 'published'): Tour
{
    $tour = Tour::create([
        'category_id' => $category->id,
        'partner_id' => $partner->id,
        'price_amount' => $price,
        'slug' => $slug,
        'location' => 'Rome, Italy',
        'location_slug' => 'rome',
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
 * Attach a single specific-date availability rule on the given date string.
 */
function addSpecificDateRule(Tour $tour, string $date): void
{
    AvailabilityRule::create([
        'tour_id' => $tour->id,
        'rule_type' => 'specific_date',
        'start_date' => $date,
        'capacity' => 8,
    ]);
}

/**
 * Create a review with a backing completed booking and sync the tour's
 * denormalized aggregate columns through the review service listener, so
 * cached average/review_count stay consistent with the genuine source rows.
 */
function addOperatorReview(Tour $tour, User $traveler, int $rating, string $status): void
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
        'status' => $status,
        'locale' => 'en',
    ]);
    app(UpdateTourAggregateRating::class)->handle(new ReviewSubmitted($review));
}

it('returns the exact allowlisted summary with verified true for an approved active profile', function () {
    $partner = makePartner();
    $partner->profile()->create([
        'company_name' => 'Rome Walks',
        'business_description' => 'Locally operated walks.',
        'logo_url' => 'https://images.example.com/operator.jpg',
        'contact_email' => 'private@example.com',
        'contact_phone' => '+390000000',
        'business_address' => ['street' => '1 Private Lane', 'city' => 'Rome'],
        'tax_id' => 'SECRET-TAX',
        'payout_iban' => 'IT60X0542811101000000123456',
        'payout_holder_name' => 'Private Holder',
    ]);
    $partner->update(['stripe_account_id' => 'acct_secret123']);
    $tour = makeOperatorTour($this->category, $partner, 'allowlisted-operator-tour');
    addSpecificDateRule($tour, now()->addWeek()->toDateString());

    $response = getJson('/api/public/tours/allowlisted-operator-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator.name', 'Rome Walks')
        ->assertJsonPath('data.operator.description', 'Locally operated walks.')
        ->assertJsonPath('data.operator.logo_url', 'https://images.example.com/operator.jpg')
        ->assertJsonPath('data.operator.verified', true)
        ->assertJsonPath('data.operator.tour_count', 1)
        ->assertJsonPath('data.operator.review_count', 0)
        ->assertJsonPath('data.operator.average_rating', null);

    // Exact public allowlist: no contact/address/tax/payout/Stripe/user/
    // state/provider/private fields may leave the projection, recursively.
    expect(array_keys($response->json('data.operator')))
        ->toBe(['name', 'description', 'logo_url', 'verified', 'tour_count', 'review_count', 'average_rating']);

    $raw = $response->getContent();
    foreach ([
        'private@example.com', '+390000000', 'Private Lane', 'SECRET-TAX',
        'IT60', 'Private Holder', 'acct_secret123', 'onboarding_status',
        'source_hash', 'translated_hash', 'last_error_code',
    ] as $leak) {
        expect($raw)->not->toContain($leak);
    }
});

it('returns nullable description and logo facts without new mandatory gates', function () {
    $partner = makePartner();
    $partner->profile()->create(['company_name' => 'Minimal Operator', 'contact_email' => 'private@example.test']);
    $tour = makeOperatorTour($this->category, $partner, 'minimal-operator-tour');
    addSpecificDateRule($tour, now()->addWeek()->toDateString());

    // Description/logo are nullable returned facts, not eligibility gates.
    getJson('/api/public/tours/minimal-operator-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator.name', 'Minimal Operator')
        ->assertJsonPath('data.operator.description', null)
        ->assertJsonPath('data.operator.logo_url', null)
        ->assertJsonPath('data.operator.verified', true)
        ->assertJsonPath('data.operator.tour_count', 1);
});

it('returns null summary for an inactive partner', function () {
    $partner = makePartner();
    $partner->update(['is_active' => false]);
    $partner->profile()->create([
        'company_name' => 'Suspended Operator',
        'contact_email' => 'private@example.com',
    ]);
    $tour = makeOperatorTour($this->category, $partner, 'inactive-operator-tour');
    addSpecificDateRule($tour, now()->addWeek()->toDateString());

    getJson('/api/public/tours/inactive-operator-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator', null)
        ->assertDontSee('Suspended Operator')
        ->assertDontSee('private@example.com');
});

it('returns null summary for an unapproved partner', function () {
    $partner = makePartner('pending');
    $partner->profile()->create([
        'company_name' => 'Pending Operator',
        'contact_email' => 'private@example.com',
    ]);
    $tour = makeOperatorTour($this->category, $partner, 'pending-operator-tour');
    addSpecificDateRule($tour, now()->addWeek()->toDateString());

    getJson('/api/public/tours/pending-operator-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator', null)
        ->assertDontSee('Pending Operator')
        ->assertDontSee('private@example.com');
});

it('treats a whitespace-only public name as a missing name prerequisite', function () {
    $partner = makePartner();
    $partner->profile()->create(['company_name' => '   ', 'contact_email' => 'private@example.test']);
    $tour = makeOperatorTour($this->category, $partner, 'blank-name-operator-tour');
    addSpecificDateRule($tour, now()->addWeek()->toDateString());

    // The summary prerequisite is a trimmed public name; whitespace-only
    // must not produce a summary or a verification claim.
    getJson('/api/public/tours/blank-name-operator-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator', null);
});

it('returns null summary when the partner has no profile', function () {
    $partner = makePartner();
    $tour = makeOperatorTour($this->category, $partner, 'profileless-operator-tour');
    addSpecificDateRule($tour, now()->addWeek()->toDateString());

    getJson('/api/public/tours/profileless-operator-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator', null);
});

it('matches every eligibility fixture against the shared exact invariant', function () {
    $partner = makePartner();
    $partner->profile()->create(['company_name' => 'Invariant Operator', 'contact_email' => 'private@example.test']);

    $future = makeOperatorTour($this->category, $partner, 'invariant-future');
    addSpecificDateRule($future, now()->addWeek()->toDateString());

    // Today counts as upcoming under the shared invariant: a today-only
    // tour is eligible (no invented tomorrow-only rule).
    $todayOnly = makeOperatorTour($this->category, $partner, 'invariant-today');
    addSpecificDateRule($todayOnly, now()->toDateString());

    $expired = makeOperatorTour($this->category, $partner, 'invariant-expired');
    addSpecificDateRule($expired, now()->subDay()->toDateString());

    // Rules exist but every upcoming date is blocked: fully unbookable.
    $blocked = makeOperatorTour($this->category, $partner, 'invariant-blocked');
    addSpecificDateRule($blocked, now()->toDateString());
    AvailabilityException::create([
        'tour_id' => $blocked->id,
        'exception_type' => 'block',
        'date' => now()->toDateString(),
    ]);

    $unpriced = makeOperatorTour($this->category, $partner, 'invariant-unpriced', 0);
    addSpecificDateRule($unpriced, now()->addWeek()->toDateString());

    $draft = makeOperatorTour($this->category, $partner, 'invariant-draft', 5000, 'draft');
    addSpecificDateRule($draft, now()->addWeek()->toDateString());

    foreach ([$future, $todayOnly, $expired, $blocked, $unpriced, $draft] as $tour) {
        $tour->load(['availabilityRules', 'availabilityExceptions']);
    }

    expect($future->isPubliclyBookable())->toBeTrue()
        ->and($todayOnly->isPubliclyBookable())->toBeTrue()
        ->and($expired->isPubliclyBookable())->toBeFalse()
        ->and($blocked->isPubliclyBookable())->toBeFalse()
        ->and($unpriced->isPubliclyBookable())->toBeFalse()
        ->and($draft->isPubliclyBookable())->toBeFalse();
});

it('counts only exact eligible tours and their visible/flagged reviews', function () {
    $partner = makePartner();
    $partner->profile()->create(['company_name' => 'Aggregate Operator', 'contact_email' => 'private@example.test']);

    $future = makeOperatorTour($this->category, $partner, 'aggregate-future');
    addSpecificDateRule($future, now()->addWeek()->toDateString());

    $todayOnly = makeOperatorTour($this->category, $partner, 'aggregate-today');
    addSpecificDateRule($todayOnly, now()->toDateString());

    // Approximate bookable scope would include these, but the exact shared
    // invariant excludes them: expired rules, fully blocked dates, invalid
    // pricing and unpublished status.
    $expired = makeOperatorTour($this->category, $partner, 'aggregate-expired');
    addSpecificDateRule($expired, now()->subDay()->toDateString());

    $blocked = makeOperatorTour($this->category, $partner, 'aggregate-blocked');
    addSpecificDateRule($blocked, now()->toDateString());
    AvailabilityException::create([
        'tour_id' => $blocked->id,
        'exception_type' => 'block',
        'date' => now()->toDateString(),
    ]);

    $unpriced = makeOperatorTour($this->category, $partner, 'aggregate-unpriced', 0);
    addSpecificDateRule($unpriced, now()->addWeek()->toDateString());

    $draft = makeOperatorTour($this->category, $partner, 'aggregate-draft', 5000, 'draft');
    addSpecificDateRule($draft, now()->addWeek()->toDateString());

    $traveler = User::factory()->traveler()->create();

    // Genuine eligible stats: visible + flagged count; hidden does not.
    addOperatorReview($future, $traveler, 5, 'visible');
    addOperatorReview($future, $traveler, 4, 'visible');
    addOperatorReview($future, $traveler, 4, 'flagged');
    addOperatorReview($future, $traveler, 1, 'hidden');

    // Reviews on ineligible tours must not leak into the aggregate.
    addOperatorReview($expired, $traveler, 5, 'visible');
    addOperatorReview($draft, $traveler, 5, 'visible');

    // Exact eligible IDs are future + today-only: (5 + 4 + 4) / 3 = 4.33.
    getJson('/api/public/tours/aggregate-future?locale=en')->assertOk()
        ->assertJsonPath('data.operator.verified', true)
        ->assertJsonPath('data.operator.tour_count', 2)
        ->assertJsonPath('data.operator.review_count', 3)
        ->assertJsonPath('data.operator.average_rating', 4.33);

    getJson('/api/public/tours/aggregate-today?locale=en')->assertOk()
        ->assertJsonPath('data.operator.tour_count', 2)
        ->assertJsonPath('data.operator.review_count', 3);
});

it('scopes ownership to the partner record id, not the user id', function () {
    $decoy = User::factory()->traveler()->create();
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $partner->profile()->create(['company_name' => 'Record Scoped Operator', 'contact_email' => 'private@example.test']);

    // Partner-record IDs must differ from user IDs so the test proves the
    // lookup uses the record scope rather than coinciding identifiers.
    expect($partner->id)->not->toBe($partner->user_id)
        ->and($partner->id)->not->toBe($decoy->id);

    $tour = makeOperatorTour($this->category, $partner, 'record-scoped-tour');
    expect($tour->partner_id)->toBe($partner->id);
    addSpecificDateRule($tour, now()->addWeek()->toDateString());

    getJson('/api/public/tours/record-scoped-tour?locale=en')->assertOk()
        ->assertJsonPath('data.operator.name', 'Record Scoped Operator')
        ->assertJsonPath('data.operator.verified', true)
        ->assertJsonPath('data.operator.tour_count', 1);
});

it('issues a bounded query count with eager-loaded availability collections', function () {
    $partner = makePartner();
    $partner->profile()->create(['company_name' => 'Query Operator', 'contact_email' => 'private@example.test']);

    for ($i = 1; $i <= 6; $i++) {
        $tour = makeOperatorTour($this->category, $partner, "query-eligible-{$i}");
        addSpecificDateRule($tour, now()->addDays($i)->toDateString());
    }

    $tour = Tour::with([
        'partnerRecord', 'partnerRecord.profile',
        'availabilityRules', 'availabilityExceptions',
    ])->where('slug', 'query-eligible-1')->firstOrFail();

    DB::enableQueryLog();
    $summary = app(GetPublicOperatorSummaryAction::class)->execute($tour);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Six eligible tours must not produce per-tour queries: the candidate
    // check reuses eager-loaded collections instead of N+1 availability or
    // review lookups behind mocked internals.
    expect($summary['tour_count'])->toBe(6)
        ->and(count($queries))->toBeLessThanOrEqual(5);
});
