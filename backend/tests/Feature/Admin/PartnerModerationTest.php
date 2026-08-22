<?php

use App\Domains\Admin\Actions\ApprovePartnerAction;
use App\Domains\Admin\Actions\ReinstatePartnerAction;
use App\Domains\Admin\Actions\RejectPartnerAction;
use App\Domains\Admin\Actions\SuspendPartnerAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Enums\TourStatus;
use App\Mail\PartnerApprovedMail;
use App\Mail\PartnerRejectedMail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function makePartnerWithUser(string $onboarding = 'pending', bool $active = false): Partner
{
    $user = User::factory()->partner()->create();

    return Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => $onboarding,
        'is_active' => $active,
    ]);
}

function admin(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_partners' => true]]);

    return $admin->fresh('adminPermission');
}

beforeEach(function () {
    $this->admin = admin();
});

it('approves a pending partner, sends mail, and writes audit', function () {
    Mail::fake();
    $partner = makePartnerWithUser('pending', false);

    $partner = app(ApprovePartnerAction::class)->execute($this->admin, $partner);

    expect($partner->fresh()->onboarding_status)->toBe(PartnerStatus::Approved->value)
        ->and($partner->fresh()->is_active)->toBeTrue();

    Mail::assertQueued(PartnerApprovedMail::class, fn ($m) => $m->partner->is($partner));

    $log = GovernanceAuditLog::where('action', 'partner.approve')->where('target_id', $partner->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->before_state)->toMatchArray(['onboarding_status' => 'pending', 'is_active' => false])
        ->and($log->after_state)->toMatchArray(['onboarding_status' => 'approved', 'is_active' => true]);
});

it('rejects a pending partner with a reason, sends mail, and writes audit', function () {
    Mail::fake();
    $partner = makePartnerWithUser('pending', false);

    $partner = app(RejectPartnerAction::class)->execute($this->admin, $partner, ['rejection_reason' => 'Incomplete profile']);

    expect($partner->fresh()->onboarding_status)->toBe(PartnerStatus::Rejected->value)
        ->and($partner->fresh()->is_active)->toBeFalse();

    Mail::assertQueued(PartnerRejectedMail::class, fn ($m) => $m->reason === 'Incomplete profile');

    $log = GovernanceAuditLog::where('action', 'partner.reject')->where('target_id', $partner->id)->first();
    expect($log)->not->toBeNull()->and($log->metadata['reason'])->toBe('Incomplete profile');
});

it('suspends an approved partner and removes its published tours from discovery (FR-006)', function () {
    $partner = makePartnerWithUser('approved', true);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'sus-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => TourStatus::Published->value,
    ]);

    $partner = app(SuspendPartnerAction::class)->execute($this->admin, $partner, ['reason' => 'Policy violation']);

    expect($partner->fresh()->onboarding_status)->toBe(PartnerStatus::Suspended->value)
        ->and($partner->fresh()->is_active)->toBeFalse()
        ->and($tour->fresh()->status)->toBe(TourStatus::Draft->value)
        ->and(GovernanceAuditLog::where('action', 'partner.suspend')->where('target_id', $partner->id)->exists())->toBeTrue();
});

it('reinstate reactivates a suspended partner without republishing tours', function () {
    $partner = makePartnerWithUser('suspended', false);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'reins-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => TourStatus::Draft->value,
    ]);

    $partner = app(ReinstatePartnerAction::class)->execute($this->admin, $partner);

    expect($partner->fresh()->onboarding_status)->toBe(PartnerStatus::Approved->value)
        ->and($partner->fresh()->is_active)->toBeTrue()
        ->and($tour->fresh()->status)->toBe(TourStatus::Draft->value) // NOT republished
        ->and(GovernanceAuditLog::where('action', 'partner.reinstate')->where('target_id', $partner->id)->exists())->toBeTrue();
});

it('blocks approving a partner that is not in a pending state', function () {
    $partner = makePartnerWithUser('approved', true);

    expect($partner->canTransitionTo(PartnerStatus::Approved))->toBeFalse()
        ->and(fn () => app(ApprovePartnerAction::class)->execute($this->admin, $partner))->toThrow(HttpException::class);
});

it('normalizes the legacy incomplete status to pending for governance', function () {
    $partner = makePartnerWithUser('incomplete', false);

    expect($partner->canTransitionTo(PartnerStatus::Approved))->toBeTrue();
});
