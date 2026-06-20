<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            $traveler = User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => bcrypt('Password123!'),
                    'email_verified_at' => now(),
                ],
            );

            $this->call([
                PartnerSeeder::class,
            ]);

            $this->seedTravelerBookings($traveler);
        }
    }

    /**
     * Seed the bookings/review the traveler E2E suites depend on. All bookings
     * hang off the single published tour created by PartnerSeeder
     * (hidden-gems-rome-walking-tour, €45.00, group 1-20) and are owned by the
     * seeded test@example.com traveler. References are fixed so specs can target
     * them by URL (e.g. /en/my-bookings/BKO-TEST01). updateOrCreate keeps the
     * rows restorable — e.g. after a spec cancels BKO-TEST01, re-seeding resets
     * it to confirmed. WithoutModelEvents suppresses the Booking/Payment
     * creating hooks, so idempotency_key and stripe_payment_intent_id are set
     * explicitly here.
     */
    private function seedTravelerBookings(User $traveler): void
    {
        $tour = Tour::where('slug', 'hidden-gems-rome-walking-tour')->first();
        if (! $tour) {
            return;
        }

        $price = (int) $tour->price_amount; // 4500 cents (€45.00)

        // BKO-TEST01: confirmed and eligible for cancellation (tour date >24h
        // out) — drives cancel-booking + booking-detail.
        $this->createBooking('BKO-TEST01', $traveler->id, $tour->id, now()->addWeeks(2)->toDateString(), 2, $price, Booking::STATUS_CONFIRMED);

        // BKO-TEST02: completed within the 30-day review window, no review yet
        // — drives review-submission (completed booking shows the review form).
        $this->createBooking('BKO-TEST02', $traveler->id, $tour->id, now()->subDays(5)->toDateString(), 2, $price, Booking::STATUS_COMPLETED);

        // BKO-TEST03: completed with a visible review — drives the My Reviews
        // card + edit/cancel-edit flow (created now so the 48h edit window is open).
        $booking3 = $this->createBooking('BKO-TEST03', $traveler->id, $tour->id, now()->subDays(3)->toDateString(), 1, $price, Booking::STATUS_COMPLETED);
        Review::firstOrCreate(
            ['booking_id' => $booking3->id],
            [
                'tour_id' => $tour->id,
                'traveler_id' => $traveler->id,
                'rating' => 5,
                'comment' => 'A wonderful hidden-gems walk through Rome — highly recommended!',
                'status' => 'visible',
                'locale' => 'en',
            ]
        );

        // BKO-PAST01: confirmed but past the 24h cancellation window — the
        // cancel button renders disabled (cancel-booking error-handling case).
        $this->createBooking('BKO-PAST01', $traveler->id, $tour->id, now()->subDays(2)->toDateString(), 1, $price, Booking::STATUS_CONFIRMED);

        // BKO-SOLD0: fills hidden-gems-rome-walking-tour to its group_size_max
        // (20) on 2026-12-01. A new booking attempt for that date gets HTTP 409
        // ("sold out") — fired before the Stripe PaymentIntent step, so the
        // sold-out E2E test needs no Stripe credentials.
        $this->createBooking('BKO-SOLD0', $traveler->id, $tour->id, '2026-12-01', 20, $price, Booking::STATUS_CONFIRMED);
    }

    private function createBooking(string $reference, int $travelerId, int $tourId, string $tourDate, int $participants, int $pricePerPerson, string $status): Booking
    {
        $paid = in_array($status, [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED], true);
        $total = $pricePerPerson * $participants;

        $booking = Booking::updateOrCreate(
            ['reference' => $reference],
            [
                'traveler_id' => $travelerId,
                'tour_id' => $tourId,
                'tour_date' => $tourDate,
                'participant_count' => $participants,
                'price_per_person' => $pricePerPerson,
                'total_price' => $total,
                'currency' => 'EUR',
                'status' => $status,
                'idempotency_key' => Str::uuid()->toString(),
                'cancellation_policy' => 'Free cancellation up to 24 hours before the tour.',
                'cancellation_window_hours' => 24,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'locale' => 'en',
                'stripe_payment_intent_id' => $paid ? 'pi_seed_'.$reference : null,
                'payment_confirmed_at' => $paid ? now() : null,
            ]
        );

        // Confirmed/completed bookings need a succeeded payment so the receipt
        // renders and ReviewValidationService's payment check passes.
        if ($paid) {
            Payment::updateOrCreate(
                ['stripe_payment_intent_id' => 'pi_seed_'.$reference],
                [
                    'booking_id' => $booking->id,
                    'type' => 'charge',
                    'amount' => $total,
                    'currency' => 'EUR',
                    'status' => 'succeeded',
                ]
            );
        }

        return $booking;
    }
}
