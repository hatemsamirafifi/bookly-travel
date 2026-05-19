<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\StripeService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_placeholder']);
    config(['services.stripe.key' => 'pk_test_placeholder']);
});

function createPaidBooking(User $traveler, Tour $tour): Booking
{
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(14)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Free cancellation up to 24 hours before the tour start time.',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
        'payment_confirmed_at' => now(),
    ]);

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
        'type' => 'charge',
        'amount' => 10000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'card_last_four' => '4242',
        'card_brand' => 'visa',
    ]);

    FinancialLedgerEntry::create([
        'booking_id' => $booking->id,
        'payment_id' => $payment->id,
        'entry_type' => 'debit',
        'amount' => 10000,
        'currency' => 'EUR',
        'actor' => 'system',
        'description' => 'Payment captured for booking ' . $booking->reference,
    ]);

    return $booking->fresh();
}

it('can cancel a booking within cancellation window and trigger refund', function () {
    $category = Category::firstOrCreate(['slug' => 'refund'], ['name' => 'Refund Test']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'refund-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = createPaidBooking($traveler, $tour);

    $mock = $this->mock(StripeService::class);
    $mock->shouldReceive('refund')
        ->once()
        ->with($booking->payment->stripe_payment_intent_id)
        ->andReturn('re_test_123');

    $response = actingAs($traveler)
        ->postJson("/api/public/my-bookings/{$booking->reference}/cancel");

    $response->assertStatus(200);

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_CANCELLED);

    $refundPayment = Payment::where('booking_id', $booking->id)
        ->where('type', 'refund')
        ->first();
    expect($refundPayment)->not->toBeNull();
    expect($refundPayment->amount)->toBe(10000);

    $creditEntry = FinancialLedgerEntry::where('booking_id', $booking->id)
        ->where('entry_type', 'credit')
        ->first();
    expect($creditEntry)->not->toBeNull();
    expect($creditEntry->amount)->toBe(10000);
});

it('double cancellation is idempotent', function () {
    $category = Category::firstOrCreate(['slug' => 'dbl-cancel'], ['name' => 'Double Cancel']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'dbl-cancel-tour-' . uniqid(),
        'location' => 'Florence, Italy',
        'duration_minutes' => 90,
        'duration_label' => '1.5 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 4000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = createPaidBooking($traveler, $tour);

    $mock = $this->mock(StripeService::class);
    $mock->shouldReceive('refund')
        ->once()
        ->andReturn('re_dbl_123');

    // First cancellation
    actingAs($traveler)
        ->postJson("/api/public/my-bookings/{$booking->reference}/cancel")
        ->assertStatus(200);

    $refundCountBefore = Payment::where('booking_id', $booking->id)->where('type', 'refund')->count();
    $creditCountBefore = FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'credit')->count();

    // Second cancellation — should return existing result without calling Stripe again
    actingAs($traveler)
        ->postJson("/api/public/my-bookings/{$booking->reference}/cancel")
        ->assertStatus(200);

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect(Payment::where('booking_id', $booking->id)->where('type', 'refund')->count())->toBe($refundCountBefore);
    expect(FinancialLedgerEntry::where('booking_id', $booking->id)->where('entry_type', 'credit')->count())->toBe($creditCountBefore);
});

it('does not auto-refund when cancelling outside cancellation window', function () {
    $category = Category::firstOrCreate(['slug' => 'no-refund'], ['name' => 'No Refund']);
    $partner = User::factory()->partner()->create();
    $traveler = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'no-refund-tour-' . uniqid(),
        'location' => 'Pisa, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 8,
        'price_amount' => 6000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addHours(12)->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 6000,
        'total_price' => 6000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Free cancellation up to 24 hours before the tour start time.',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
        'payment_confirmed_at' => now(),
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
        'type' => 'charge',
        'amount' => 6000,
        'currency' => 'EUR',
        'status' => 'succeeded',
    ]);

    // StripeService mock should NEVER be called since cancellation should be blocked
    $response = actingAs($traveler)
        ->postJson("/api/public/my-bookings/{$booking->reference}/cancel");

    $response->assertStatus(409);

    $refund = Payment::where('booking_id', $booking->id)->where('type', 'refund')->first();
    expect($refund)->toBeNull();
});
