<?php

use App\Domains\Admin\Services\AvailabilitySlotService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Models\Payment;
use App\Filament\Resources\AvailabilityResource;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function availabilityAdmin(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_bookings' => true]]);

    return $admin->fresh('adminPermission');
}

function availabilityTour(int $capacity = 5): Tour
{
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'avail-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => $capacity,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
}

function bookOnDate(Tour $tour, Carbon $date, int $participants): void
{
    $traveler = User::factory()->traveler()->create();
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => $date->toDateString(),
        'participant_count' => $participants,
        'price_per_person' => 5000,
        'total_price' => 5000 * $participants,
        'currency' => 'EUR',
        'status' => 'confirmed',
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);
    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
        'amount' => 5000 * $participants,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);
}

it('lets a manage_bookings admin list availability (FR-014)', function () {
    actingAs(availabilityAdmin());
    $tour = availabilityTour();

    Livewire::test(AvailabilityResource\Pages\ListAvailability::class)
        ->assertCanSeeTableRecords([$tour]);
});

it('renders the read-only slots table with booking states on the view page', function () {
    actingAs(availabilityAdmin());
    $tour = availabilityTour(5);
    $today = Carbon::today();
    bookOnDate($tour, $today, 2); // partially booked (2/5)

    Livewire::test(AvailabilityResource\Pages\ViewAvailability::class, ['record' => $tour->getRouteKey()])
        ->assertSeeHtml('data-availability-slots')
        ->assertSeeHtml('data-slot-state="empty"')
        ->assertSeeHtml('data-slot-state="partially_booked"');
});

it('computes an empty slot when no bookings exist', function () {
    $tour = availabilityTour(5);
    $date = Carbon::today()->addDays(3);

    $slots = app(AvailabilitySlotService::class)->slotsForRange($tour, $date, 1);

    expect($slots[0]['state'])->toBe('empty')
        ->and($slots[0]['booked'])->toBe(0)
        ->and($slots[0]['remaining'])->toBe(5);
});

it('computes a partially-booked slot when bookings are below capacity', function () {
    $tour = availabilityTour(5);
    $date = Carbon::today()->addDays(4);
    bookOnDate($tour, $date, 3);

    $slots = app(AvailabilitySlotService::class)->slotsForRange($tour, $date, 1);

    expect($slots[0]['state'])->toBe('partially_booked')
        ->and($slots[0]['booked'])->toBe(3)
        ->and($slots[0]['remaining'])->toBe(2);
});

it('computes a full slot when bookings meet capacity', function () {
    $tour = availabilityTour(4);
    $date = Carbon::today()->addDays(5);
    bookOnDate($tour, $date, 4);

    $slots = app(AvailabilitySlotService::class)->slotsForRange($tour, $date, 1);

    expect($slots[0]['state'])->toBe('full')
        ->and($slots[0]['remaining'])->toBe(0);
});

it('computes an unavailable slot when the date is blocked by an exception', function () {
    $tour = availabilityTour(5);
    $date = Carbon::today()->addDays(6);
    AvailabilityException::create([
        'tour_id' => $tour->id,
        'exception_type' => 'block',
        'date' => $date->toDateString(),
    ]);

    $slots = app(AvailabilitySlotService::class)->slotsForRange($tour, $date, 1);

    expect($slots[0]['state'])->toBe('unavailable')
        ->and($slots[0]['capacity'])->toBe(5)
        ->and($slots[0]['remaining'])->toBe(0);
});

it('exposes no mutation actions on the availability resource (read-only)', function () {
    actingAs(availabilityAdmin());
    $tour = availabilityTour();

    expect(AvailabilityResource::canCreate())->toBeFalse()
        ->and(AvailabilityResource::canEdit($tour))->toBeFalse()
        ->and(AvailabilityResource::canDelete($tour))->toBeFalse()
        ->and(AvailabilityResource::canDeleteAny())->toBeFalse();

    // Only a ViewAction is registered (no edit/delete/create row actions).
    Livewire::test(AvailabilityResource\Pages\ListAvailability::class)
        ->assertTableActionExists('view', null, $tour);
});

it('denies availability oversight to an admin lacking manage_bookings (FR-002)', function () {
    $adminNoFlag = User::factory()->admin()->create();
    $adminNoFlag->adminPermission()->create(['flags' => []]);
    actingAs($adminNoFlag);

    expect(AvailabilityResource::canViewAny())->toBeFalse();

    actingAs(availabilityAdmin());

    expect(AvailabilityResource::canViewAny())->toBeTrue();
});
