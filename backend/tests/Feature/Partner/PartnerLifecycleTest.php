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
use App\Mail\PartnerReinstatedMail;
use App\Mail\PartnerRejectedMail;
use App\Mail\PartnerSuspendedMail;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function createAdmin(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_partners' => true]]);

    return $admin->fresh('adminPermission');
}

function createPartnerUser(string $locale = 'en', string $status = 'pending', bool $active = false): Partner
{
    $user = User::factory()->partner()->create(['locale' => $locale]);
    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => $status,
        'is_active' => $active,
    ]);
    $partner->profile()->create([
        'company_name' => 'Acme Tours',
        'contact_email' => 'contact@acmetours.com',
        'payout_country' => 'US',
    ]);

    return $partner->fresh(['user', 'profile']);
}

it('approves a pending partner, sets active true, audits, and queues PartnerApprovedMail', function () {
    $admin = createAdmin();
    $partner = createPartnerUser('es', 'pending', false);

    $action = app(ApprovePartnerAction::class);
    $result = $action->execute($admin, $partner);

    expect($result->onboarding_status)->toBe(PartnerStatus::Approved->value)
        ->and($result->is_active)->toBeTrue();

    expect(GovernanceAuditLog::where('action', 'partner.approve')->where('target_id', $partner->id)->exists())->toBeTrue();

    Mail::assertQueued(PartnerApprovedMail::class, function ($mail) use ($partner) {
        return $mail->partner->id === $partner->id;
    });
});

it('rejects a pending partner with reason, sets active false, records reason in profile & audit, and queues PartnerRejectedMail', function () {
    $admin = createAdmin();
    $partner = createPartnerUser('it', 'pending', false);

    $action = app(RejectPartnerAction::class);
    $result = $action->execute($admin, $partner, ['rejection_reason' => 'Invalid commercial license.']);

    expect($result->onboarding_status)->toBe(PartnerStatus::Rejected->value)
        ->and($result->is_active)->toBeFalse()
        ->and($result->profile->rejection_reason)->toBe('Invalid commercial license.');

    $audit = GovernanceAuditLog::where('action', 'partner.reject')->where('target_id', $partner->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['reason'])->toBe('Invalid commercial license.');

    Mail::assertQueued(PartnerRejectedMail::class, function ($mail) use ($partner) {
        return $mail->partner->id === $partner->id && $mail->reason === 'Invalid commercial license.';
    });
});

it('suspends an approved partner with reason, unpublishes tours, and queues PartnerSuspendedMail', function () {
    $admin = createAdmin();
    $partner = createPartnerUser('en', 'approved', true);

    $category = Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure']);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => $category->id,
        'slug' => 'rome-sunset-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => TourStatus::Published->value,
    ]);

    $action = app(SuspendPartnerAction::class);
    $result = $action->execute($admin, $partner, ['reason' => 'Multiple safety complaints.']);

    expect($result->onboarding_status)->toBe(PartnerStatus::Suspended->value)
        ->and($result->is_active)->toBeFalse();

    expect($tour->fresh()->status)->toBe(TourStatus::Draft->value);

    $audit = GovernanceAuditLog::where('action', 'partner.suspend')->where('target_id', $partner->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['reason'])->toBe('Multiple safety complaints.');

    Mail::assertQueued(PartnerSuspendedMail::class, function ($mail) use ($partner) {
        return $mail->partner->id === $partner->id && $mail->reason === 'Multiple safety complaints.';
    });
});

it('reinstates a suspended partner, restores tours to draft, and queues PartnerReinstatedMail', function () {
    $admin = createAdmin();
    $partner = createPartnerUser('es', 'suspended', false);

    $action = app(ReinstatePartnerAction::class);
    $result = $action->execute($admin, $partner);

    expect($result->onboarding_status)->toBe(PartnerStatus::Approved->value)
        ->and($result->is_active)->toBeTrue();

    expect(GovernanceAuditLog::where('action', 'partner.reinstate')->where('target_id', $partner->id)->exists())->toBeTrue();

    Mail::assertQueued(PartnerReinstatedMail::class, function ($mail) use ($partner) {
        return $mail->partner->id === $partner->id;
    });
});

it('rejects invalid state transitions with 422', function () {
    $admin = createAdmin();
    $partner = createPartnerUser('en', 'rejected', false);

    $action = app(ApprovePartnerAction::class);

    expect(fn () => $action->execute($admin, $partner))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
