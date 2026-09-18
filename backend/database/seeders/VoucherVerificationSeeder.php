<?php

namespace Database\Seeders;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Spec 014 (FR-022..FR-028) fixtures for the public voucher verification
 * surface (/v/{reference} + GET /api/public/v/{reference}).
 *
 * The verification action resolves references by the opaque shape defined in
 * Booking::REFERENCE_ALPHABET (BKO- + 6 chars from the unambiguous alphabet,
 * no I/L/O/0/1). The DatabaseSeeder's BKO-TESTxx fixtures intentionally use
 * out-of-alphabet characters for readable URLs, so they can NEVER be resolved
 * by the public verification endpoint. This seeder creates one valid-shape
 * booking per verification state so E2E (voucher-verification.spec.ts) and
 * manual QA can exercise the VALID/CANCELLED/PENDING/EXPIRED paths.
 */
class VoucherVerificationSeeder extends Seeder
{
    public function run(): void
    {
        $traveler = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('Password123!'), 'email_verified_at' => now()],
        );

        $tour = Tour::where('slug', 'hidden-gems-rome-walking-tour')->first();
        if (! $tour) {
            return;
        }

        $fixtures = [
            ['BKO-AUDVWX', Booking::STATUS_CONFIRMED],
            ['BKO-AUDCAN', Booking::STATUS_CANCELLED],
            ['BKO-AUDPEN', Booking::STATUS_PENDING_PAYMENT],
            ['BKO-AUDEXP', Booking::STATUS_EXPIRED],
        ];

        foreach ($fixtures as [$reference, $status]) {
            $paid = in_array($status, [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED], true);

            $booking = Booking::updateOrCreate(
                ['reference' => $reference],
                [
                    'traveler_id' => $traveler->id,
                    'tour_id' => $tour->id,
                    'tour_date' => now()->addWeeks(3)->toDateString(),
                    'participant_count' => 2,
                    'price_per_person' => 4500,
                    'total_price' => 9000,
                    'currency' => 'EUR',
                    'status' => $status,
                    'idempotency_key' => 'seed-' . $reference,
                    'cancellation_policy' => 'Free cancellation up to 24 hours before the tour.',
                    'cancellation_window_hours' => 24,
                    'stripe_payment_intent_id' => $paid ? 'pi_seed_' . $reference : null,
                    'payment_confirmed_at' => $paid ? now() : null,
                ]
            );

            if ($paid) {
                $payment = Payment::updateOrCreate(
                    ['stripe_payment_intent_id' => 'pi_seed_' . $reference],
                    [
                        'booking_id' => $booking->id,
                        'type' => 'charge',
                        'amount' => 9000,
                        'currency' => 'EUR',
                        'status' => 'succeeded',
                    ]
                );

                // Same financial/audit invariant as DatabaseSeeder fixtures:
                // a paid booking always carries its charge debit and the
                // payment_confirmed audit event (see ConfirmBookingOnPayment).
                FinancialLedgerEntry::firstOrCreate(
                    [
                        'payment_id' => $payment->id,
                        'entry_type' => 'debit',
                    ],
                    [
                        'booking_id' => $booking->id,
                        'amount' => 9000,
                        'currency' => 'EUR',
                        'actor' => 'system',
                        'description' => 'Payment captured for booking ' . $reference,
                    ]
                );

                BookingAuditLog::firstOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'action' => 'payment_confirmed',
                    ],
                    [
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'before_state' => Booking::STATUS_PENDING_PAYMENT,
                        'after_state' => $status,
                        'metadata' => ['payment_id' => $payment->id],
                    ]
                );
            }
        }
    }
}
