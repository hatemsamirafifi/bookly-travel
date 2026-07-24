<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Actions\CreatePaymentIntentAction;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\StripeService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_placeholder']);
    config(['services.stripe.key' => 'pk_test_placeholder']);
});

function makeDowntimeTour(string $slugSuffix, int $price = 6000): Tour
{
    $category = Category::firstOrCreate(['slug' => 'down-' . $slugSuffix], ['name' => 'Down ' . $slugSuffix]);
    $partner = makePartner();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'down-' . $slugSuffix . '-' . uniqid(),
        'location' => 'Turin, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => $price,
        'status' => 'published',
        'cover_image_url' => null,
    ]);
    addAvailabilityRule($tour);

    return $tour;
}

function makeBookingFor(Tour $tour): Booking
{
    $traveler = User::factory()->traveler()->create();

    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => $tour->price_amount,
        'total_price' => $tour->price_amount,
        'currency' => 'EUR',
        'status' => Booking::STATUS_PENDING_PAYMENT,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
        'pending_expires_at' => now()->addMinutes(15),
    ]);
}

it('returns 503 and marks the booking expired when PaymentIntent creation fails', function () {
    $tour = makeDowntimeTour('fail');
    $traveler = User::factory()->traveler()->create();

    // F8: Phase 2 (PaymentIntent creation) fails after Phase 1 commits the
    // booking. The booking is marked expired (releasing availability), the
    // failure is logged, and 503 is returned. No Payment row is persisted.
    $this->mock(StripeService::class)
        ->shouldReceive('createPaymentIntent')
        ->once()
        ->andThrow(new \RuntimeException('stripe unavailable'));

    Log::spy();

    $response = actingAs($traveler)
        ->withHeader('Idempotency-Key', Str::uuid()->toString())
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 2,
            'locale' => 'en',
            'page_load_price' => 6000,
        ]);

    $response->assertStatus(503);

    $booking = Booking::where('tour_id', $tour->id)->first();
    expect($booking)->not->toBeNull();
    expect($booking->status)->toBe(Booking::STATUS_EXPIRED);
    expect($booking->payments()->count())->toBe(0);

    Log::shouldHaveReceived('error')->withArgs(function (string $message, array $ctx) {
        return $message === 'PaymentIntent creation failed; marking booking expired'
            && ($ctx['error'] ?? null) === 'stripe unavailable';
    })->once();
});

it('idempotency retry returns existing booking with stored client_secret, no new PaymentIntent', function () {
    $tour = makeDowntimeTour('idem', 8000);
    $traveler = User::factory()->traveler()->create();

    $idempotencyKey = Str::uuid()->toString();

    // First request: create the PaymentIntent once and store its client_secret.
    $this->mock(StripeService::class)
        ->shouldReceive('createPaymentIntent')
        ->once()
        ->with(8000, 'EUR', Mockery::any())
        ->andReturn('pi_idem_123_secret_xyz');

    $first = actingAs($traveler)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 1,
            'locale' => 'en',
            'page_load_price' => 8000,
        ]);

    $first->assertStatus(201)
        ->assertJsonPath('payment.client_secret', 'pi_idem_123_secret_xyz');

    $bookingCount = Booking::where('idempotency_key', $idempotencyKey)->count();

    // Retry: same idempotency key. The stored client_secret is reused — Stripe
    // is NOT called again, and no duplicate booking or PaymentIntent is created.
    $second = actingAs($traveler)
        ->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/public/bookings', [
            'tour_slug' => $tour->slug,
            'tour_date' => now()->addDays(14)->toDateString(),
            'participant_count' => 1,
            'locale' => 'en',
            'page_load_price' => 8000,
        ]);

    $second->assertStatus(200)
        ->assertJsonPath('payment.client_secret', 'pi_idem_123_secret_xyz');
    expect($second->json('data.reference'))->toBe($first->json('data.reference'));
    expect(Booking::where('idempotency_key', $idempotencyKey)->count())->toBe($bookingCount);
    expect(Payment::where('type', 'charge')->count())->toBe(1);
});

it('compensates an orphan PaymentIntent when the local Payment write fails', function () {
    $tour = makeDowntimeTour('orphan', 7000);
    $booking = makeBookingFor($tour);

    // Pre-create a charge Payment for ANOTHER booking sharing the same intent id
    // so this booking's Payment::create hits the composite unique
    // (stripe_payment_intent_id, type) and throws — simulating a local write
    // failure after the intent was created in Stripe. execute() must cancel the
    // orphan intent and rethrow. (No global model hooks, so no test leakage.)
    $other = makeBookingFor($tour);
    Payment::create([
        'booking_id' => $other->id,
        'stripe_payment_intent_id' => 'pi_orphan_1',
        'type' => 'charge',
        'amount' => 7000,
        'currency' => 'EUR',
        'status' => 'pending',
    ]);

    $stripe = $this->mock(StripeService::class);
    $stripe->shouldReceive('createPaymentIntent')
        ->once()
        ->andReturn('pi_orphan_1_secret_abc');
    // parseIntentId is a pure static helper — the real implementation derives
    // 'pi_orphan_1' from 'pi_orphan_1_secret_abc', so it needs no mock.
    $stripe->shouldReceive('cancelPaymentIntent')
        ->once()
        ->with('pi_orphan_1');

    Log::spy();

    expect(fn () => app(CreatePaymentIntentAction::class)->execute($booking))
        ->toThrow(\Exception::class);

    // The orphan intent was canceled and no Payment was persisted for this booking.
    expect(Payment::where('booking_id', $booking->id)->count())->toBe(0);
    Log::shouldHaveReceived('error')->atLeast()->once();
});