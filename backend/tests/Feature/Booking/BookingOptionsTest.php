<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Services\StripeService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

if (! function_exists('makeBookingOptionFixture')) {
    /**
     * Create a real tour option scoped to $tour. Defaults describe a 09:00
     * departure with Arabic-capable guiding; pass overrides for price, time,
     * capacity, weekday allow-list or lifecycle status.
     */
    function makeBookingOptionFixture(Tour $tour, array $overrides = []): TourOption
    {
        return TourOption::create(array_merge([
            'tour_id' => $tour->id,
            'title' => 'Standard departure',
            'pickup_instructions' => 'Meet the guide at the fountain 15 minutes early.',
            'guide_languages' => ['en'],
            'price_amount' => 5900,
            'group_size_max' => 6,
            'start_time' => '09:00:00',
            'weekdays' => null,
            'status' => TourOption::STATUS_ACTIVE,
        ], $overrides));
    }
}

if (! function_exists('postBookingRequest')) {
    function postBookingRequest(Tour $tour, User $traveler, array $payload = [], ?string $key = null): TestResponse
    {
        return postJson('/api/public/bookings', array_merge([
            'tour_slug' => $tour->slug,
            'tour_date' => '2026-06-15',
            'participant_count' => 2,
            'locale' => 'en',
        ], $payload), [
            'Authorization' => 'Bearer ' . $traveler->createToken('test')->plainTextToken,
            'Idempotency-Key' => $key ?? Str::uuid()->toString(),
        ]);
    }
}

beforeEach(function () {
    Carbon::setTestNow('2026-06-01 00:00:00');

    $this->category = Category::firstOrCreate(['slug' => 'wine-food'], ['name' => 'Wine & Food']);
    $this->traveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $this->category->id,
        'slug' => 'options-test-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 300,
        'duration_label' => '5 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 8900,
        'status' => 'published',
        'cover_image_url' => 'https://cdn.bookly.com/tours/42/cover.jpg',
    ]);
    addAvailabilityRule($this->tour); // operates every day, 09:00 start
    addTranslation($this->tour, 'en', 'Tuscany Wine Tasting');

    // Booking creation calls Stripe; fake it and hand out a unique intent per
    // call so several bookings can be created inside one test.
    $this->mock(StripeService::class)
        ->shouldReceive('createPaymentIntent')
        ->andReturnUsing(fn () => 'pi_' . Str::random(12) . '_secret_' . Str::random(12));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('books each option with its own price, start time and Arabic guide', function () {
    $morning = makeBookingOptionFixture($this->tour, [
        'title' => 'Morning small group',
        'pickup_instructions' => 'Pickup at Piazza della Signoria at 08:40.',
        'guide_languages' => ['ar', 'en'],
        'price_amount' => 5900,
        'start_time' => '09:00:00',
        'group_size_max' => 6,
    ]);

    $sunset = makeBookingOptionFixture($this->tour, [
        'title' => 'Sunset departure',
        'pickup_instructions' => 'Pickup at the Duomo steps at 13:45.',
        'guide_languages' => ['it'],
        'price_amount' => 7900,
        'start_time' => '14:00:00',
        'group_size_max' => 4,
    ]);

    postBookingRequest($this->tour, $this->traveler, [
        'tour_option_id' => $morning->id,
        'participant_count' => 2,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.pricing.price_per_person.amount', 5900)
        ->assertJsonPath('data.pricing.total.amount', 11800);

    postBookingRequest($this->tour, $this->traveler, [
        'tour_option_id' => $sunset->id,
        'participant_count' => 1,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.pricing.price_per_person.amount', 7900)
        ->assertJsonPath('data.pricing.total.amount', 7900);

    assertDatabaseHas('bookings', [
        'tour_id' => $this->tour->id,
        'tour_option_id' => $morning->id,
        'start_time' => '09:00:00',
        'price_per_person' => 5900,
        'total_price' => 11800,
        'tour_option_title' => 'Morning small group',
        'tour_option_pickup_instructions' => 'Pickup at Piazza della Signoria at 08:40.',
    ]);

    assertDatabaseHas('bookings', [
        'tour_id' => $this->tour->id,
        'tour_option_id' => $sunset->id,
        'start_time' => '14:00:00',
        'price_per_person' => 7900,
        'total_price' => 7900,
        'tour_option_title' => 'Sunset departure',
    ]);

    expect($morning->fresh()->guideLanguages())->toBe(['ar', 'en']);
});

it('keeps the booked snapshot after the option is renamed and retired', function () {
    $option = makeBookingOptionFixture($this->tour, [
        'title' => 'Original title',
        'pickup_instructions' => 'Original pickup point',
    ]);

    postBookingRequest($this->tour, $this->traveler, ['tour_option_id' => $option->id])->assertStatus(201);

    $option->update([
        'title' => 'Renamed title',
        'pickup_instructions' => 'Renamed pickup point',
        'status' => TourOption::STATUS_RETIRED,
        'retired_at' => now(),
    ]);

    $booking = Booking::where('tour_option_id', $option->id)->firstOrFail();

    expect($booking->tour_option_title)->toBe('Original title')
        ->and($booking->tour_option_pickup_instructions)->toBe('Original pickup point')
        ->and($booking->tourOption?->id)->toBe($option->id);

    // A retired option can no longer be selected for a new booking.
    postBookingRequest($this->tour, $this->traveler, ['tour_option_id' => $option->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tour_option_id']);

    expect(Booking::count())->toBe(1);
});

it('requires an option once the tour exposes active options', function () {
    makeBookingOptionFixture($this->tour);

    postBookingRequest($this->tour, $this->traveler)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tour_option_id']);

    expect(Booking::count())->toBe(0);
});

it('rejects forged and foreign option ids', function () {
    makeBookingOptionFixture($this->tour);

    $otherTour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $this->category->id,
        'slug' => 'options-test-other-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 4900,
        'status' => 'published',
    ]);
    addAvailabilityRule($otherTour);
    $foreign = makeBookingOptionFixture($otherTour);

    // Forged: no such option.
    postBookingRequest($this->tour, $this->traveler, ['tour_option_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tour_option_id']);

    // Foreign: a real option, but it belongs to another tour.
    postBookingRequest($this->tour, $this->traveler, ['tour_option_id' => $foreign->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tour_option_id']);

    expect(Booking::count())->toBe(0);
});

it('intersects the option weekdays with the tour operating dates', function () {
    // Tour operates every day (beforeEach rule); the option runs Mondays only.
    $mondayOnly = makeBookingOptionFixture($this->tour, ['weekdays' => [1]]);

    // 2026-06-15 is a Monday → the intersection allows it.
    postBookingRequest($this->tour, $this->traveler, [
        'tour_option_id' => $mondayOnly->id,
        'tour_date' => '2026-06-15',
    ])->assertStatus(201);

    // 2026-06-16 is a Tuesday the tour operates on, but the option does not.
    postBookingRequest($this->tour, $this->traveler, [
        'tour_option_id' => $mondayOnly->id,
        'tour_date' => '2026-06-16',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tour_option_id']);

    expect(Booking::count())->toBe(1);
});

it('counts capacity per option so one departure pool never blocks another', function () {
    $small = makeBookingOptionFixture($this->tour, ['title' => 'Small group', 'group_size_max' => 2]);
    $other = makeBookingOptionFixture($this->tour, ['title' => 'Second departure', 'group_size_max' => 2]);

    postBookingRequest($this->tour, $this->traveler, [
        'tour_option_id' => $small->id,
        'participant_count' => 2,
    ])->assertStatus(201);

    // Same option, same date → full.
    postBookingRequest($this->tour, $this->traveler, [
        'tour_option_id' => $small->id,
        'participant_count' => 1,
    ])->assertStatus(409);

    // The other option on the same tour/date keeps its own seats.
    postBookingRequest($this->tour, $this->traveler, [
        'tour_option_id' => $other->id,
        'participant_count' => 1,
    ])->assertStatus(201);

    // Above the option capacity is a validation error, not a late 409.
    postBookingRequest($this->tour, $this->traveler, [
        'tour_option_id' => $other->id,
        'participant_count' => 3,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['participant_count']);

    expect(Booking::count())->toBe(2);
});

it('keeps option-less booking behavior for tours without options', function () {
    postBookingRequest($this->tour, $this->traveler, ['participant_count' => 2])
        ->assertStatus(201)
        ->assertJsonPath('data.pricing.price_per_person.amount', 8900);

    assertDatabaseHas('bookings', [
        'tour_id' => $this->tour->id,
        'tour_option_id' => null,
        'tour_option_title' => null,
        'tour_option_pickup_instructions' => null,
        'start_time' => '09:00:00',
        'price_per_person' => 8900,
    ]);
});

it('replays the same booking for the same traveler and option', function () {
    $option = makeBookingOptionFixture($this->tour);
    $key = Str::uuid()->toString();

    $first = postBookingRequest($this->tour, $this->traveler, ['tour_option_id' => $option->id], $key)
        ->assertStatus(201);
    $second = postBookingRequest($this->tour, $this->traveler, ['tour_option_id' => $option->id], $key)
        ->assertStatus(200);

    $second->assertJsonPath('data.reference', $first->json('data.reference'));

    expect(Booking::count())->toBe(1);
});

it('never discloses another traveler booking on a replayed idempotency key', function () {
    $option = makeBookingOptionFixture($this->tour);
    $key = Str::uuid()->toString();

    postBookingRequest($this->tour, $this->traveler, ['tour_option_id' => $option->id], $key)
        ->assertStatus(201);

    $intruder = User::factory()->traveler()->create();
    app('auth')->forgetGuards();
    $response = postBookingRequest($this->tour, $intruder, ['tour_option_id' => $option->id], $key);

    $response->assertStatus(409);

    expect($response->json('data'))->toBeNull()
        ->and($response->json('payment'))->toBeNull()
        ->and((string) $response->json('message'))->not->toContain('BKO-');

    expect(Booking::count())->toBe(1);
});
