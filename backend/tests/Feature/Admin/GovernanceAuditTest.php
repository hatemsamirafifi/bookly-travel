<?php

use App\Domains\Admin\Actions\ApprovePartnerAction;
use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Actions\TransitionBookingStatusAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Actions\HideReviewAction;
use App\Domains\Reviews\Models\Review;
use App\Domains\Reviews\Models\ReviewAuditTrail;
use App\Enums\TourStatus;
use App\Filament\Resources\GovernanceAuditResource;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Livewire\Livewire;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function auditAdmin(array $flags): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => $flags]);

    return $admin->fresh('adminPermission');
}

function auditPartner(): Partner
{
    $partnerUser = User::factory()->partner()->create();

    return Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'pending',
        'is_active' => false,
    ]);
}

function auditTour(): Tour
{
    $partner = auditPartner();
    // Approve the partner so the tour can be published (FR-005).
    $partner->update(['onboarding_status' => 'approved', 'is_active' => true]);

    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'audit-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => TourStatus::PendingReview->value,
    ]);
}

it('writes a governance audit entry when a tour is published', function () {
    $admin = auditAdmin(['manage_tours' => true]);
    $tour = auditTour();

    app(ApproveTourAction::class)->execute($admin, $tour);

    $log = GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_type)->toBe('admin')
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->target_type)->toBe('tour')
        ->and($log->before_state)->toMatchArray(['status' => TourStatus::PendingReview->value])
        ->and($log->after_state)->toMatchArray(['status' => TourStatus::Published->value]);
});

it('writes a governance audit entry when a partner is approved', function () {
    $admin = auditAdmin(['manage_partners' => true]);
    $partner = auditPartner();

    app(ApprovePartnerAction::class)->execute($admin, $partner);

    $log = GovernanceAuditLog::where('action', 'partner.approve')->where('target_id', $partner->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->target_type)->toBe('partner')
        ->and($log->actor_id)->toBe($admin->id);
});

it('writes a governance audit entry when a review is hidden', function () {
    $admin = auditAdmin(['moderate_reviews' => true]);
    $review = makeReviewForAudit('visible');

    app(HideReviewAction::class)->execute($review, $admin->id, 'Spam content');

    $log = GovernanceAuditLog::where('action', 'review.hide')->where('target_id', $review->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->target_type)->toBe('review')
        ->and($log->metadata['reason'])->toBe('Spam content');
});

it('writes a governance audit entry when a booking transitions', function () {
    $admin = auditAdmin(['manage_bookings' => true]);
    $booking = makeBookingForAudit(Booking::STATUS_CONFIRMED);

    app(TransitionBookingStatusAction::class)->execute($admin, $booking, Booking::STATUS_COMPLETED);

    $log = GovernanceAuditLog::where('action', 'booking.transition')->where('target_id', $booking->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->target_type)->toBe('booking')
        ->and($log->metadata['to'])->toBe('completed');
});

it('is append-only: updates are blocked (FR-012)', function () {
    $admin = auditAdmin(['manage_tours' => true]);
    $tour = auditTour();
    app(ApproveTourAction::class)->execute($admin, $tour);

    $log = GovernanceAuditLog::where('action', 'tour.publish')->first();
    $originalAction = $log->action;
    $log->action = 'tampered';

    expect($log->save())->toBeFalse();
    expect(GovernanceAuditLog::find($log->id)->action)->toBe($originalAction);
});

it('is append-only: deletes are blocked (FR-012)', function () {
    $admin = auditAdmin(['manage_tours' => true]);
    $tour = auditTour();
    app(ApproveTourAction::class)->execute($admin, $tour);

    $log = GovernanceAuditLog::where('action', 'tour.publish')->first();
    $id = $log->id;

    expect($log->delete())->toBeFalse();
    expect(GovernanceAuditLog::find($id))->not->toBeNull();
});

it('filters governance entries by action in the Filament table', function () {
    $admin = auditAdmin(['manage_tours' => true, 'manage_partners' => true, 'view_audit_log' => true]);
    actingAs($admin);

    $tour = auditTour();
    app(ApproveTourAction::class)->execute($admin, $tour);
    $partner = auditPartner();
    app(ApprovePartnerAction::class)->execute($admin, $partner);

    $tourLog = GovernanceAuditLog::where('action', 'tour.publish')->first();
    $partnerLog = GovernanceAuditLog::where('action', 'partner.approve')->first();

    // Apply the action filter and assert only the matching entry is visible.
    Livewire::test(GovernanceAuditResource\Pages\ListGovernanceAudits::class)
        ->filterTable('action', 'tour.publish')
        ->assertCanSeeTableRecords([$tourLog])
        ->assertCanNotSeeTableRecords([$partnerLog]);
});

it('backfills legacy booking_audit_logs and review_audit_trails into the unified trail (idempotent)', function () {
    $booking = makeBookingForAudit(Booking::STATUS_CONFIRMED);
    $review = makeReviewForAudit('visible');

    DB::table('booking_audit_logs')->insert([
        'booking_id' => $booking->id,
        'actor_type' => 'admin',
        'actor_id' => 1,
        'action' => 'completed',
        'before_state' => 'confirmed',
        'after_state' => 'completed',
        'metadata' => json_encode(['note' => 'legacy']),
        'created_at' => now(),
    ]);

    DB::table('review_audit_trails')->insert([
        'review_id' => $review->id,
        'actor_type' => 'admin',
        'actor_id' => 1,
        'action' => 'hide',
        'old_rating' => 4,
        'new_rating' => null,
        'old_comment' => 'old',
        'new_comment' => null,
        'reason' => 'legacy hide',
        'created_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_06_20_100200_backfill_governance_audit_logs_from_legacy_trails.php');
    $migration->up();

    expect(GovernanceAuditLog::where('action', 'booking.completed')->where('target_id', $booking->id)->exists())->toBeTrue()
        ->and(GovernanceAuditLog::where('action', 'review.hide')->where('target_id', $review->id)->exists())->toBeTrue();

    $portedBooking = GovernanceAuditLog::where('action', 'booking.completed')->first();
    expect($portedBooking->metadata['backfilled_from'])->toBe('booking_audit_logs')
        ->and($portedBooking->target_type)->toBe('booking');

    // Re-running is idempotent (no duplicate rows).
    $countBefore = GovernanceAuditLog::whereNotNull(DB::raw("metadata->>'backfilled_from'"))->count();
    $migration->up();
    $countAfter = GovernanceAuditLog::whereNotNull(DB::raw("metadata->>'backfilled_from'"))->count();
    expect($countAfter)->toBe($countBefore);

    // down() removes only the ported rows.
    $migration->down();
    expect(GovernanceAuditLog::whereNotNull(DB::raw("metadata->>'backfilled_from'"))->count())->toBe(0);
});

// ----- helpers shared with this file -----

function makeBookingForAudit(string $status): Booking
{
    $traveler = User::factory()->traveler()->create();
    $partner = auditPartner();
    $partner->update(['onboarding_status' => 'approved', 'is_active' => true]);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'audit-bkg-tour-' . uniqid(),
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
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => $status,
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
        'locale' => 'en',
    ]);
}

function makeReviewForAudit(string $status): Review
{
    $traveler = User::factory()->traveler()->create();
    $partner = auditPartner();
    $partner->update(['onboarding_status' => 'approved', 'is_active' => true]);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'audit-rev-tour-' . uniqid(),
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
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
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

    return Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 4,
        'comment' => 'A review for audit',
        'status' => $status,
        'locale' => 'en',
    ]);
}