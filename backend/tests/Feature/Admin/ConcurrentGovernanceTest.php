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

if (! function_exists('bookingsAdmin')) {
    function bookingsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => ['manage_bookings' => true]]);

        return $admin->fresh('adminPermission');
    }
}

if (! function_exists('concurrentBooking')) {
    function concurrentBooking(string $status = Booking::STATUS_CONFIRMED): Booking
    {
        return makeBookingWithStatus($status);
    }
}

if (! function_exists('makeBookingWithStatus')) {
    // Reuse the canonical booking factory from the booking-transition suite.
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
            'slug' => 'conc-tour-' . uniqid(),
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
}

it('prevents a contradictory double transition when two admins act on a stale snapshot', function () {
    $adminA = bookingsAdmin();
    $adminB = bookingsAdmin();
    $booking = concurrentBooking(Booking::STATUS_CONFIRMED);

    // Admin A marks the booking completed first.
    app(TransitionBookingStatusAction::class)->execute($adminA, $booking, Booking::STATUS_COMPLETED);
    expect($booking->fresh()->status)->toBe(Booking::STATUS_COMPLETED);

    // Admin B still holds the stale "confirmed" snapshot and tries to cancel.
    // The row-locked re-check sees the committed `completed` status, which is
    // terminal, so the contradictory transition is rejected (edge case: no
    // contradictory double transition).
    $stale = Booking::find($booking->id);
    $stale->status = Booking::STATUS_CONFIRMED; // simulate a stale-loaded snapshot

    expect(fn () => app(TransitionBookingStatusAction::class)->execute($adminB, $stale, Booking::STATUS_CANCELLED))
        ->toThrow(HttpException::class);

    // The effective outcome is preserved and audited exactly once.
    expect($booking->fresh()->status)->toBe(Booking::STATUS_COMPLETED);
    expect(GovernanceAuditLog::where('action', 'booking.transition')->where('target_id', $booking->id)->count())->toBe(1);
});

it('records each successful transition and rejects a second terminal transition', function () {
    $admin = bookingsAdmin();
    $booking = concurrentBooking(Booking::STATUS_CONFIRMED);

    app(TransitionBookingStatusAction::class)->execute($admin, $booking, Booking::STATUS_NO_SHOW);
    expect($booking->fresh()->status)->toBe(Booking::STATUS_NO_SHOW);

    // no_show is terminal; a further transition must be rejected.
    expect(fn () => app(TransitionBookingStatusAction::class)->execute($admin, $booking->fresh(), Booking::STATUS_CANCELLED))
        ->toThrow(HttpException::class);

    expect(GovernanceAuditLog::where('action', 'booking.transition')->where('target_id', $booking->id)->count())->toBe(1);
});

it('keeps governance_audit_logs append-only under concurrent edits', function () {
    $admin = bookingsAdmin();
    $booking = concurrentBooking(Booking::STATUS_CONFIRMED);

    app(TransitionBookingStatusAction::class)->execute($admin, $booking, Booking::STATUS_COMPLETED);
    $log = GovernanceAuditLog::where('action', 'booking.transition')->where('target_id', $booking->id)->first();

    // Immutability: update and delete are blocked at the model layer.
    $log->action = 'booking.tampered';
    expect($log->save())->toBeFalse();
    expect($log->delete())->toBeFalse();

    $fresh = GovernanceAuditLog::find($log->id);
    expect($fresh->action)->toBe('booking.transition');
    expect(GovernanceAuditLog::where('target_id', $booking->id)->count())->toBe(1);
});
