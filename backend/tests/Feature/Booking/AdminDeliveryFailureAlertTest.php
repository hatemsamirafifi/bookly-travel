<?php

use App\Domains\Admin\Models\Notification;
use App\Domains\Booking\Models\Booking;
use App\Events\BookingEmailDeliveryFailed;
use App\Filament\Resources\AdminNotificationResource;
use App\Filament\Resources\NotificationResource;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/*
 * Spec 014 (US5 FR-016/FR-017, FR-012): when a booking confirmation email
 * terminally fails, NotifyAdminOnEmailDeliveryFailure logs an ERROR always
 * and posts to a Slack webhook when configured. Per FR-012, admin alerting
 * MUST NOT create an admin notifications table, admin Notification model, or
 * Filament admin inbox — this file includes a static regression guard.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-04 00:00:00');
    $this->category = Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure', 'is_active' => true, 'display_order' => 1]);
    $this->traveler = User::factory()->traveler()->create();
    $this->tour = Tour::create([
        'partner_id' => makePartner()->id,
        'category_id' => $this->category->id,
        'slug' => 'alert-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 240,
        'duration_label' => '4 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'published',
    ]);
    $this->booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => $this->traveler->id,
        'tour_id' => $this->tour->id,
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
});

afterEach(function () {
    Carbon::setTestNow();
    config(['services.slack.admin_webhook_url' => null]);
});

it('logs an ERROR when the delivery-failure event fires', function () {
    Log::spy();

    event(new BookingEmailDeliveryFailed($this->booking, 'SMTP down'));

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'ADMIN ALERT')
                && ($context['booking_reference'] ?? null) === $this->booking->reference
                && ($context['error'] ?? null) === 'SMTP down';
        })
        ->atLeast()
        ->once();
});

it('posts to the Slack webhook when one is configured', function () {
    Http::fake();
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/services/test/webhook']);

    event(new BookingEmailDeliveryFailed($this->booking, 'SMTP down'));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://hooks.slack.com/services/test/webhook'
            && str_contains((string) $request->body(), $this->booking->reference);
    });
});

it('does not post to Slack when no webhook is configured', function () {
    Http::fake();
    config(['services.slack.admin_webhook_url' => null]);

    event(new BookingEmailDeliveryFailed($this->booking, 'SMTP down'));

    Http::assertNothingSent();
});

/*
 * FR-012: Slack is best-effort only and MUST NOT fail the original
 * listener/job if Slack itself returns a 5xx or throws an exception.
 */
it('survives a Slack webhook failure without crashing the listener', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/bad']);
    Log::spy();

    // Should not throw even though Slack throws a ConnectionException
    event(new BookingEmailDeliveryFailed($this->booking, 'SMTP down'));

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'ADMIN ALERT')
                && ($context['booking_reference'] ?? null) === $this->booking->reference
                && ($context['error'] ?? null) === 'SMTP down';
        })
        ->atLeast()
        ->once();
    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'Slack admin alert failed')
                && ($context['booking_reference'] ?? null) === $this->booking->reference
                && ($context['slack_error'] ?? null) === 'Connection refused';
        })
        ->atLeast()
        ->once();
});

it('logs a warning when the Slack webhook returns HTTP 500', function () {
    Http::fake([
        'https://hooks.slack.com/*' => Http::response('Server error', 500),
    ]);
    config(['services.slack.admin_webhook_url' => 'https://hooks.slack.com/bad']);
    Log::spy();

    event(new BookingEmailDeliveryFailed($this->booking, 'SMTP down'));

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context) {
            return str_contains($message, 'Slack admin alert failed')
                && ($context['booking_reference'] ?? null) === $this->booking->reference
                && str_contains($context['slack_error'] ?? '', '500');
        })
        ->atLeast()
        ->once();
});

it('does not alter the booking status on a delivery failure (FR-028)', function () {
    event(new BookingEmailDeliveryFailed($this->booking, 'SMTP down'));

    expect($this->booking->fresh()->status)->toBe('confirmed');
});

/*
 * FR-012 regression guard: admin alerting MUST NOT introduce an admin
 * notifications table, admin Notification model, Filament admin inbox, or
 * Filament NotificationResource. These assertions fail loudly if a future
 * change reintroduces any of those surfaces.
 */
it('does not introduce a forbidden admin-notification surface (FR-012)', function () {
    expect(class_exists(Notification::class))->toBeFalse()
        ->and(class_exists(NotificationResource::class))->toBeFalse()
        ->and(class_exists(AdminNotificationResource::class))->toBeFalse();

    // No migration creates an admin_notifications table.
    $migrations = glob(database_path('migrations/*.php')) ?: [];
    $createsAdminNotifications = false;
    foreach ($migrations as $migration) {
        $contents = (string) file_get_contents($migration);
        if (preg_match("/Schema::create\(['\"]admin_notifications['\"]/", $contents)) {
            $createsAdminNotifications = true;
            break;
        }
    }
    expect($createsAdminNotifications)->toBeFalse();
});
