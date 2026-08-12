<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Mail\PartnerApprovedMail;
use App\Mail\PartnerBookingCancelledMail;
use App\Mail\PartnerNewBookingMail;
use App\Mail\PartnerRejectedMail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
 * Spec 014 (FR-006, FR-014, R4, T013/T022): the four partner lifecycle
 * mailables are localized to the partner user's locale (en/es/it) with EN
 * fallback for both subject and body view. The rejection reason is rendered
 * verbatim (operator-authored, not translated).
 *
 * Mailables are constructed + rendered directly so the assertion is on the
 * localization logic itself, independent of where each mailable is dispatched
 * (approval/rejection from admin actions, new-booking/cancelled from the
 * booking flow — all covered by the same per-locale view selection here).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-04 00:00:00');
    $this->category = Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure', 'is_active' => true, 'display_order' => 1]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeLocalizedPartner(string $locale): array
{
    $user = User::factory()->partner()->create(['locale' => $locale]);
    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    PartnerProfile::create([
        'partner_id' => $partner->id,
        'company_name' => 'Test Adventures Co.',
        'contact_email' => $user->email,
    ]);

    return [$partner, $user];
}

function makeTourForPartner(Partner $partner): Tour
{
    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure'])->id,
        'slug' => 'lifecycle-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
}

function makeBookingForTour(Tour $tour, User $traveler): Booking
{
    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => '2026-08-15',
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'cancellation_policy' => 'Free 24h before',
        'cancellation_window_hours' => 24,
        'locale' => 'en',
    ]);
}

it('localizes PartnerApprovedMail subject and body to the partner locale (en/es/it)', function () {
    $subjects = [
        'en' => 'Your Partner Application Has Been Approved — Bookly',
        'es' => 'Tu solicitud de partner ha sido aprobada — Bookly',
        'it' => 'La tua richiesta di partner è stata approvata — Bookly',
    ];
    $bodyMarkers = [
        'en' => 'Congratulations',
        'es' => 'Felicidades',
        'it' => 'Congratulazioni',
    ];

    foreach (['en', 'es', 'it'] as $locale) {
        [$partner] = makeLocalizedPartner($locale);
        $mail = new PartnerApprovedMail($partner);

        expect($mail->envelope()->subject)->toBe($subjects[$locale]);
        expect($mail->render())->toContain($bodyMarkers[$locale]);
    }
});

it('falls back to EN for PartnerApprovedMail when the partner locale is unsupported', function () {
    // The users table CHECK constraint only allows en/es/it, so simulate an
    // unsupported locale by setting it in-memory and pinning the relation.
    [$partner, $user] = makeLocalizedPartner('en');
    $user->locale = 'fr';
    $partner->setRelation('user', $user);

    $mail = new PartnerApprovedMail($partner);

    expect($mail->envelope()->subject)->toBe('Your Partner Application Has Been Approved — Bookly')
        ->and($mail->render())->toContain('Congratulations');
});

it('localizes PartnerRejectedMail subject and renders the reason verbatim', function () {
    [$partner] = makeLocalizedPartner('es');
    $reason = 'Documentación incompleta — por favor reenvía.';

    $mail = new PartnerRejectedMail($partner, $reason);

    expect($mail->envelope()->subject)->toBe('Estado de tu solicitud de partner — Bookly')
        ->and($mail->render())->toContain('Motivo') // ES body marker
        ->and($mail->render())->toContain($reason); // reason rendered verbatim
});

it('falls back to EN for PartnerRejectedMail when the partner locale is unsupported', function () {
    [$partner, $user] = makeLocalizedPartner('en');
    $user->locale = 'de';
    $partner->setRelation('user', $user);
    $mail = new PartnerRejectedMail($partner, 'Incomplete docs.');

    expect($mail->envelope()->subject)->toBe('Your Partner Application Status — Bookly')
        ->and($mail->render())->toContain('Reason'); // EN body marker
});

it('localizes PartnerNewBookingMail subject to the owning partner locale', function () {
    $subjects = [
        'en' => 'New Booking — ',
        'es' => 'Nueva reserva — ',
        'it' => 'Nuova prenotazione — ',
    ];

    foreach (['en', 'es', 'it'] as $locale) {
        [$partner, $partnerUser] = makeLocalizedPartner($locale);
        $tour = makeTourForPartner($partner);
        $traveler = User::factory()->traveler()->create();
        $booking = makeBookingForTour($tour, $traveler);

        $mail = new PartnerNewBookingMail($booking);

        expect($mail->envelope()->subject)->toStartWith($subjects[$locale]);
    }
});

it('falls back to EN for PartnerNewBookingMail when the owning partner locale is unsupported', function () {
    [$partner] = makeLocalizedPartner('en');
    $tour = makeTourForPartner($partner);
    $traveler = User::factory()->traveler()->create();
    $booking = makeBookingForTour($tour, $traveler);

    // The users CHECK constraint only allows en/es/it, so simulate an
    // unsupported locale on the loaded relation chain the mailable reads.
    $booking->load('tour.partnerRecord.user');
    $booking->tour->partnerRecord->user->locale = 'xx';

    $mail = new PartnerNewBookingMail($booking);

    expect($mail->envelope()->subject)->toStartWith('New Booking — ');
});

it('localizes PartnerBookingCancelledMail subject to the owning partner locale', function () {
    $subjects = [
        'en' => 'Booking Cancelled — ',
        'es' => 'Reserva cancelada — ',
        'it' => 'Prenotazione cancellata — ',
    ];

    foreach (['en', 'es', 'it'] as $locale) {
        [$partner] = makeLocalizedPartner($locale);
        $tour = makeTourForPartner($partner);
        $traveler = User::factory()->traveler()->create();
        $booking = makeBookingForTour($tour, $traveler);
        $booking->status = 'cancelled';
        $booking->save();

        $mail = new PartnerBookingCancelledMail($booking);

        expect($mail->envelope()->subject)->toStartWith($subjects[$locale]);
    }
});

it('does not leak traveler PII into the rendered partner emails', function () {
    [$partner] = makeLocalizedPartner('en');
    $tour = makeTourForPartner($partner);
    $traveler = User::factory()->traveler()->create([
        'name' => 'Secret Traveler Name',
        'email' => 'secret-traveler@example.com',
    ]);
    $booking = makeBookingForTour($tour, $traveler);

    $newBookingHtml = (new PartnerNewBookingMail($booking))->render();
    $cancelledHtml = (new PartnerBookingCancelledMail($booking))->render();

    expect($newBookingHtml)->not->toContain('secret-traveler@example.com');
    // Traveler first name IS shown in the new-booking email (FR allows it), but
    // the email address must never appear.
    expect($cancelledHtml)->not->toContain('secret-traveler@example.com')
        ->and($cancelledHtml)->not->toContain('Secret Traveler Name');
});
