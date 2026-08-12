<?php

use App\Domains\Booking\Jobs\SendBookingConfirmationEmail;
use App\Domains\Booking\Models\Booking;
use App\Events\BookingEmailDeliveryFailed;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingVoucherMail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Spec 014 (US1 FR-006/FR-018/FR-019, US4 FR-020/FR-021): the queued
 * SendBookingConfirmationEmail job sends a localized confirmation email +
 * the voucher PDF to the traveler, is idempotent across retries, and on
 * terminal failure fires BookingEmailDeliveryFailed without touching booking
 * status. QUEUE_CONNECTION=sync runs the job inline in tests.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-04 00:00:00');
    $this->category = Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure', 'is_active' => true, 'display_order' => 1]);
    $this->traveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $this->category->id,
        'slug' => 'confirm-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    TourTranslation::create([
        'tour_id' => $this->tour->id,
        'locale' => 'en',
        'title' => 'Confirmation Test Tour',
        'description' => 'Desc',
        'highlights' => ['h'],
        'inclusions' => ['i'],
        'exclusions' => ['e'],
        'meeting_point' => 'Central station',
        'cancellation_policy' => 'Free 24h before',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    // Clean any voucher PDFs generated during the run.
    @array_map('unlink', glob(storage_path('app/vouchers/voucher-*.pdf')) ?: []);
});

function makeConfirmBooking(TestCase $scope, string $locale = 'en'): Booking
{
    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $scope->traveler->id,
        'tour_id' => $scope->tour->id,
        'tour_date' => '2026-08-15',
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Free 24h before',
        'cancellation_window_hours' => 24,
        'locale' => $locale,
    ]);
}

it('sends a localized confirmation email and the voucher PDF to the traveler', function () {
    Mail::fake();

    $booking = makeConfirmBooking($this, 'es');
    dispatch(new SendBookingConfirmationEmail($booking));

    // Localized confirmation email (ES subject).
    Mail::assertSent(BookingConfirmedMail::class, function ($mail) use ($booking) {
        return $mail->envelope()->subject === "Reserva confirmada — {$booking->reference}";
    });

    // Voucher email with the PDF attachment.
    Mail::assertSent(BookingVoucherMail::class, function ($mail) use ($booking) {
        $attachments = $mail->attachments();

        return $mail->envelope()->subject === "Tu voucher — {$booking->reference}"
            && count($attachments) === 1
            && $attachments[0]->as === "voucher-{$booking->reference}.pdf";
    });

    // The voucher PDF was generated on disk.
    expect(file_exists(storage_path("app/vouchers/voucher-{$booking->reference}.pdf")))->toBeTrue();

    // Idempotency marker written.
    expect($booking->fresh()->confirmation_email_sent_at)->not->toBeNull();
});

it('falls back to EN subject when the booking locale is unsupported', function () {
    Mail::fake();

    // The users CHECK constraint allows only en/es/it, but the booking locale
    // column accepts any string — set an unsupported one to exercise EN fallback.
    $booking = makeConfirmBooking($this, 'fr');
    dispatch(new SendBookingConfirmationEmail($booking));

    Mail::assertSent(BookingConfirmedMail::class, function ($mail) use ($booking) {
        return $mail->envelope()->subject === "Booking Confirmed — {$booking->reference}";
    });
});

it('is idempotent: dispatching twice sends each email exactly once (FR-020)', function () {
    Mail::fake();

    $booking = makeConfirmBooking($this);
    dispatch(new SendBookingConfirmationEmail($booking));
    dispatch(new SendBookingConfirmationEmail($booking)); // retry / duplicate dispatch

    Mail::assertSent(BookingConfirmedMail::class, 1);
    Mail::assertSent(BookingVoucherMail::class, 1);
});

it('fires BookingEmailDeliveryFailed on terminal failure without altering booking status (FR-021)', function () {
    Event::fake([BookingEmailDeliveryFailed::class]);

    $booking = makeConfirmBooking($this);
    $job = new SendBookingConfirmationEmail($booking);

    $job->failed(new Exception('SMTP server unreachable'));

    Event::assertDispatched(BookingEmailDeliveryFailed::class, function ($event) use ($booking) {
        return $event->booking->is($booking)
            && $event->errorMessage === 'SMTP server unreachable';
    });

    // Booking status is untouched by the failure path.
    expect($booking->fresh()->status)->toBe('confirmed');
});
