<?php

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Actions\RejectTourAction;
use App\Domains\Admin\Actions\UnpublishTourAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Enums\TourStatus;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

if (! function_exists('adminWithFlag')) {
    function adminWithFlag(string $flag): User
    {
        $admin = User::factory()->admin()->create();

        $admin->adminPermission()->create(['flags' => [$flag => true]]);

        return $admin->fresh('adminPermission');
    }
}

if (! function_exists('makePartner')) {
    function makePartner(string $onboarding = 'approved'): Partner
    {
        $user = User::factory()->partner()->create();

        return Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => $onboarding,
            'is_active' => $onboarding === 'approved',
        ]);
    }
}

if (! function_exists('makeTour')) {
    function makeTour(Partner $partner, string $status = 'pending_review'): Tour
    {
        return Tour::create([
            'partner_id' => $partner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
            'slug' => 'mod-tour-' . uniqid(),
            'location' => 'Rome, Italy',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'price_amount' => 5000,
            'status' => $status,
        ]);
    }
}

beforeEach(function () {
    $this->admin = adminWithFlag('manage_tours');
});

it('publishes a pending tour for an approved partner and writes audit', function () {
    $partner = makePartner('approved');
    $tour = makeTour($partner, 'pending_review');

    $tour = app(ApproveTourAction::class)->execute($this->admin, $tour);

    expect($tour->fresh()->status)->toBe(TourStatus::Published->value)
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())->toBeTrue();

    $log = GovernanceAuditLog::where('action', 'tour.publish')->first();
    expect($log->actor_type)->toBe('admin')
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->before_state)->toBe(['status' => 'pending_review'])
        ->and($log->after_state)->toBe(['status' => 'published']);
});

it('blocks publishing when the owning partner is not approved (FR-005)', function () {
    $partner = makePartner('pending');
    $tour = makeTour($partner, 'pending_review');

    expect($tour->canTransitionTo(TourStatus::Published))->toBeFalse()
        ->and(fn () => app(ApproveTourAction::class)->execute($this->admin, $tour))->toThrow(HttpException::class)
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->exists())->toBeFalse();
});

it('rejects a pending tour with a reason and writes audit', function () {
    $partner = makePartner('approved');
    $tour = makeTour($partner, 'pending_review');

    $tour = app(RejectTourAction::class)->execute($this->admin, $tour, ['rejection_reason' => 'Needs better photos']);

    expect($tour->fresh()->status)->toBe(TourStatus::Rejected->value);

    $log = GovernanceAuditLog::where('action', 'tour.reject')->where('target_id', $tour->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['reason'])->toBe('Needs better photos')
        ->and($log->before_state)->toBe(['status' => 'pending_review'])
        ->and($log->after_state)->toBe(['status' => 'rejected']);
});

it('unpublishes a published tour and writes audit', function () {
    $partner = makePartner('approved');
    $tour = makeTour($partner, 'published');

    $tour = app(UnpublishTourAction::class)->execute($this->admin, $tour);

    expect($tour->fresh()->status)->toBe(TourStatus::Draft->value)
        ->and(GovernanceAuditLog::where('action', 'tour.unpublish')->where('target_id', $tour->id)->exists())->toBeTrue();
});

it('prevents rejecting a tour that is not in a rejectable state', function () {
    $partner = makePartner('approved');
    $tour = makeTour($partner, 'draft');

    expect($tour->canTransitionTo(TourStatus::Rejected))->toBeFalse()
        ->and(fn () => app(RejectTourAction::class)->execute($this->admin, $tour, ['rejection_reason' => 'x']))->toThrow(HttpException::class);
});
