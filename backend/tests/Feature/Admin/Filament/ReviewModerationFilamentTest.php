<?php

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Models\Payment;
use App\Domains\Reviews\Models\Review;
use App\Enums\ReviewStatus;
use App\Filament\Resources\ReviewResource;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function adminWithReviewsFlag(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['moderate_reviews' => true]]);

    return $admin->fresh('adminPermission');
}

function makeReview(string $status = 'visible'): Review
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
        'slug' => 'rev-tour-' . uniqid(),
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

    return Review::create([
        'booking_id' => $booking->id,
        'tour_id' => $tour->id,
        'traveler_id' => $traveler->id,
        'rating' => 4,
        'comment' => 'A review for moderation',
        'status' => $status,
        'locale' => 'en',
    ]);
}

beforeEach(function () {
    $this->admin = adminWithReviewsFlag();
    actingAs($this->admin);
});

it('lists review records in the Filament table', function () {
    $review = makeReview('visible');

    Livewire::test(ReviewResource\Pages\ListReviews::class)
        ->assertCanSeeTableRecords([$review]);
});

it('hides a review via the Filament hide action and writes governance audit', function () {
    $review = makeReview('visible');

    Livewire::test(ReviewResource\Pages\ListReviews::class)
        ->callTableAction('hide', $review, ['reason' => 'Spam'])
        ->assertHasNoTableActionErrors();

    expect($review->fresh()->status)->toBe(ReviewStatus::Hidden->value);
    $log = GovernanceAuditLog::where('action', 'review.hide')->where('target_id', $review->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->metadata['reason'])->toBe('Spam');
});

it('reinstates a hidden review via the Filament reinstate action and writes governance audit', function () {
    $review = makeReview('hidden');

    Livewire::test(ReviewResource\Pages\ListReviews::class)
        ->callTableAction('reinstate', $review, ['reason' => 'Hidden in error'])
        ->assertHasNoTableActionErrors();

    expect($review->fresh()->status)->toBe(ReviewStatus::Visible->value);
    $log = GovernanceAuditLog::where('action', 'review.reinstate')->where('target_id', $review->id)->first();
    expect($log)->not->toBeNull()->and($log->actor_id)->toBe($this->admin->id);
});

it('hiding a review recomputes the tour aggregate rating', function () {
    $review = makeReview('visible');

    Livewire::test(ReviewResource\Pages\ListReviews::class)
        ->callTableAction('hide', $review, ['reason' => 'Spam'])
        ->assertHasNoTableActionErrors();

    expect($review->tour->fresh()->review_count)->toBe(0)
        ->and($review->tour->fresh()->average_rating)->toBeNull();
});

it('bulk-hides selected reviews with a shared reason', function () {
    $reviewA = makeReview('visible');
    $reviewB = makeReview('visible');

    Livewire::test(ReviewResource\Pages\ListReviews::class)
        ->callTableBulkAction('bulk_hide', [$reviewA, $reviewB], ['reason' => 'Policy violation'])
        ->assertHasNoTableActionErrors();

    expect($reviewA->fresh()->status)->toBe(ReviewStatus::Hidden->value)
        ->and($reviewB->fresh()->status)->toBe(ReviewStatus::Hidden->value)
        ->and(GovernanceAuditLog::whereIn('target_id', [$reviewA->id, $reviewB->id])->where('action', 'review.hide')->count())->toBe(2);
});

it('bulk-reinstates selected hidden reviews', function () {
    $reviewA = makeReview('hidden');
    $reviewB = makeReview('hidden');

    Livewire::test(ReviewResource\Pages\ListReviews::class)
        ->callTableBulkAction('bulk_reinstate', [$reviewA, $reviewB], ['reason' => 'Cleared on review'])
        ->assertHasNoTableActionErrors();

    expect($reviewA->fresh()->status)->toBe(ReviewStatus::Visible->value)
        ->and($reviewB->fresh()->status)->toBe(ReviewStatus::Visible->value)
        ->and(GovernanceAuditLog::whereIn('target_id', [$reviewA->id, $reviewB->id])->where('action', 'review.reinstate')->count())->toBe(2);
});

it('reinstates a flagged review (flagged → visible)', function () {
    $review = makeReview('flagged');

    Livewire::test(ReviewResource\Pages\ListReviews::class)
        ->callTableAction('reinstate', $review, ['reason' => 'Cleared after review'])
        ->assertHasNoTableActionErrors();

    expect($review->fresh()->status)->toBe(ReviewStatus::Visible->value);
});

it('denies the moderation gate for an admin lacking the moderate_reviews flag (FR-002)', function () {
    $adminNoFlag = User::factory()->admin()->create();
    $review = makeReview('visible');

    // The per-action gate that controls action visibility + viewAny.
    expect($adminNoFlag->can('manage', $review))->toBeFalse()
        ->and($adminNoFlag->can('viewAny', Review::class))->toBeFalse()
        ->and($this->admin->can('manage', $review))->toBeTrue();
});
