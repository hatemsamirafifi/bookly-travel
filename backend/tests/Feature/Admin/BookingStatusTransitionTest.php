<?php

use App\Domains\Admin\Actions\TransitionBookingStatusAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function adminWithBookingsFlag(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_bookings' => true]]);

    return $admin->fresh('adminPermission');
}

function makeBookingWithStatus(string $status): Booking
{
    $traveler = User::factory()->traveler()->create();
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
    $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'bkg-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);

    return Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(7)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'EUR',
        'status' => $status,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);
}

beforeEach(function () {
    $this->admin = adminWithBookingsFlag();
});

it('transitions a confirmed booking to completed and writes audit (non-financial)', function () {
    $booking = makeBookingWithStatus(Booking::STATUS_CONFIRMED);

    $booking = app(TransitionBookingStatusAction::class)->execute($this->admin, $booking, Booking::STATUS_COMPLETED);

    expect($booking->fresh()->status)->toBe(Booking::STATUS_COMPLETED);

    $log = GovernanceAuditLog::where('action', 'booking.transition')->where('target_id', $booking->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->before_state)->toMatchArray(['status' => 'confirmed'])
        ->and($log->after_state)->toMatchArray(['status' => 'completed'])
        ->and($log->metadata['to'])->toBe('completed')
        ->and($log->metadata['financial'])->toBeFalse()
        ->and($log->actor_id)->toBe($this->admin->id);
});

it('transitions a confirmed booking to no_show', function () {
    $booking = makeBookingWithStatus(Booking::STATUS_CONFIRMED);

    $booking = app(TransitionBookingStatusAction::class)->execute($this->admin, $booking, Booking::STATUS_NO_SHOW);

    expect($booking->fresh()->status)->toBe(Booking::STATUS_NO_SHOW)
        ->and(GovernanceAuditLog::where('action', 'booking.transition')->where('target_id', $booking->id)->exists())->toBeTrue();
});

it('transitions a pending_payment booking to expired', function () {
    $booking = makeBookingWithStatus(Booking::STATUS_PENDING_PAYMENT);

    $booking = app(TransitionBookingStatusAction::class)->execute($this->admin, $booking, Booking::STATUS_EXPIRED);

    expect($booking->fresh()->status)->toBe(Booking::STATUS_EXPIRED);
});

it('logs a cancellation request as financial without executing the refund (FR-009)', function () {
    $booking = makeBookingWithStatus(Booking::STATUS_CONFIRMED);

    $booking = app(TransitionBookingStatusAction::class)->execute($this->admin, $booking, Booking::STATUS_CANCELLATION_REQUESTED);

    // Status persisted + audit flagged financial; refund is delegated to Spec 008,
    // not executed here, so payment fields are untouched.
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLATION_REQUESTED)
        ->and($booking->fresh()->payment_confirmed_at)->toBeNull();

    $log = GovernanceAuditLog::where('action', 'booking.transition')->where('target_id', $booking->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['financial'])->toBeTrue();
});

it('sets cancelled_at when cancelling a confirmed booking (financial, status-only)', function () {
    $booking = makeBookingWithStatus(Booking::STATUS_CONFIRMED);

    $booking = app(TransitionBookingStatusAction::class)->execute($this->admin, $booking, Booking::STATUS_CANCELLED);

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($booking->fresh()->cancelled_at)->not->toBeNull();

    $log = GovernanceAuditLog::where('action', 'booking.transition')->where('target_id', $booking->id)->first();
    expect($log->metadata['financial'])->toBeTrue();
});

it('blocks disallowed transitions', function () {
    $booking = makeBookingWithStatus(Booking::STATUS_CONFIRMED);

    // confirmed -> expired is not an allowed admin transition.
    expect($booking->canTransitionTo(Booking::STATUS_EXPIRED))->toBeFalse()
        ->and(fn () => app(TransitionBookingStatusAction::class)->execute($this->admin, $booking, Booking::STATUS_EXPIRED))
        ->toThrow(HttpException::class);
});

it('blocks transitioning from a terminal status (completed)', function () {
    $booking = makeBookingWithStatus(Booking::STATUS_COMPLETED);

    expect($booking->canTransitionTo(Booking::STATUS_CANCELLED))->toBeFalse()
        ->and(fn () => app(TransitionBookingStatusAction::class)->execute($this->admin, $booking, Booking::STATUS_CANCELLED))
        ->toThrow(HttpException::class);
});

it('denies the transition gate for non-admin users and grants it for a manage_bookings admin (FR-002)', function () {
    $booking = makeBookingWithStatus(Booking::STATUS_CONFIRMED);
    $traveler = User::factory()->traveler()->create();

    expect($traveler->can('transition', $booking))->toBeFalse()
        ->and($this->admin->can('transition', $booking))->toBeTrue();
});
