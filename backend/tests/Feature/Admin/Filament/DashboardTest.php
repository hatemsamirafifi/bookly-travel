<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Models\Review;
use App\Enums\TourStatus;
use App\Filament\Widgets\PlatformOverviewWidget;
use App\Filament\Widgets\QueueShortcutsWidget;
use App\Filament\Widgets\RecentBookingsWidget;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function dashboardAdmin(bool $withAnalytics = true): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create([
        'flags' => $withAnalytics ? ['view_all_analytics' => true] : [],
    ]);

    return $admin->fresh('adminPermission');
}

function dashboardApprovedPartner(): Partner
{
    $partnerUser = User::factory()->partner()->create();

    return Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);
}

it('reflects live pending counts from the database (FR-013)', function () {
    $partner = dashboardApprovedPartner();

    Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'dash-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => TourStatus::PendingReview->value,
    ]);

    Partner::create([
        'user_id' => User::factory()->partner()->create()->id,
        'role' => 'partner',
        'onboarding_status' => 'pending',
        'is_active' => false,
    ]);

    $booking = makeFlaggedReviewBooking();
    Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $booking->tour_id,
        'traveler_id' => $booking->traveler_id,
        'rating' => 1,
        'comment' => 'flagged',
        'status' => 'flagged',
        'locale' => 'en',
    ]);

    $counts = PlatformOverviewWidget::queueCounts();
    expect($counts['partners'])->toBe(1)
        ->and($counts['tours'])->toBe(1)
        ->and($counts['reviews'])->toBe(1)
        ->and($counts['bookings'])->toBeGreaterThanOrEqual(1);
});

it('renders queue-shortcut tiles that deep-link into filtered queues', function () {
    actingAs(dashboardAdmin(true));

    Livewire::test(QueueShortcutsWidget::class)
        ->assertSeeHtml('/admin/partners')
        ->assertSeeHtml('/admin/tours')
        ->assertSeeHtml('/admin/reviews')
        ->assertSeeHtml('/admin/bookings');
});

it('renders the recent-bookings widget for an analytics admin', function () {
    actingAs(dashboardAdmin(true));

    Livewire::test(RecentBookingsWidget::class)
        ->assertOk();
});

it('hides the dashboard widgets for an admin lacking view_all_analytics (FR-002)', function () {
    actingAs(dashboardAdmin(false));

    expect(QueueShortcutsWidget::canView())->toBeFalse()
        ->and(RecentBookingsWidget::canView())->toBeFalse();

    actingAs(dashboardAdmin(true));

    expect(QueueShortcutsWidget::canView())->toBeTrue()
        ->and(RecentBookingsWidget::canView())->toBeTrue();
});

it('renders the dashboard page for a permissioned admin', function () {
    actingAs(dashboardAdmin(true));

    $this->get('/admin')->assertSuccessful();
});

function makeFlaggedReviewBooking(): Booking
{
    $traveler = User::factory()->traveler()->create();
    $partner = dashboardApprovedPartner();
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'dash-rev-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    $booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $traveler->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->subDay()->toDateString(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_COMPLETED,
        'idempotency_key' => Str::uuid()->toString(),
        'locale' => 'en',
    ]);
    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
        'amount' => 5000,
        'currency' => 'EUR',
        'status' => 'succeeded',
        'type' => 'charge',
    ]);

    return $booking;
}
