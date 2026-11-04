<?php

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Enums\TourStatus;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function seedSecurityAdmin(bool $canManageTours = true): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_tours' => $canManageTours]]);

    return $admin->fresh('adminPermission');
}

function makeTourWithPartnerStatus(string $partnerOnboardingStatus = 'approved', string $tourStatus = 'pending_review'): Tour
{
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => $partnerOnboardingStatus,
        'is_active' => ($partnerOnboardingStatus === 'approved'),
    ]);

    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'adventure'], ['name' => 'Adventure'])->id,
        'slug' => 'security-tour-' . uniqid(),
        'location' => 'Paris, France',
        'duration_minutes' => 180,
        'duration_label' => '3 hours',
        'group_size_min' => 1,
        'group_size_max' => 15,
        'price_amount' => 7500,
        'status' => $tourStatus,
    ]);

    $tour->translations()->create([
        'locale' => 'en',
        'title' => 'English Louvre Discovery Tour',
        'description' => 'A comprehensive Louvre museum experience in English.',
    ]);

    return $tour;
}

it('authorizes tour creation strictly to admins with manage_tours flag', function () {
    $authorizedAdmin = seedSecurityAdmin(canManageTours: true);
    $unauthorizedAdmin = seedSecurityAdmin(canManageTours: false);

    expect($authorizedAdmin->can('create', Tour::class))->toBeTrue()
        ->and($unauthorizedAdmin->can('create', Tour::class))->toBeFalse();
});

it('strictly blocks publishing a tour owned by an unapproved partner, rolls back transaction, and records no audit log', function () {
    $admin = seedSecurityAdmin(canManageTours: true);
    $tour = makeTourWithPartnerStatus(partnerOnboardingStatus: 'pending', tourStatus: 'pending_review');

    $action = app(ApproveTourAction::class);

    try {
        $action->execute($admin, $tour);
        test()->fail('Expected HttpException 422 was not thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toContain('owning partner is not approved');
    }

    // 1. Status remains unchanged
    expect($tour->fresh()->status)->toBe('pending_review');

    // 2. CRITICAL AUDIT CHECK: Failed publish does NOT write a governance audit log
    $auditLogExists = GovernanceAuditLog::where('action', 'tour.publish')
        ->where('target_id', $tour->id)
        ->exists();
    expect($auditLogExists)->toBeFalse();

    // 3. Tour is not exposed as published
    expect(Tour::where('id', $tour->id)->where('status', TourStatus::Published->value)->exists())->toBeFalse();
});

it('strictly blocks publishing a tour owned by a rejected partner and records no audit log', function () {
    $admin = seedSecurityAdmin(canManageTours: true);
    $tour = makeTourWithPartnerStatus(partnerOnboardingStatus: 'rejected', tourStatus: 'pending_review');

    $action = app(ApproveTourAction::class);

    try {
        $action->execute($admin, $tour);
        test()->fail('Expected HttpException 422 was not thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }

    expect($tour->fresh()->status)->toBe('pending_review');

    $auditLogExists = GovernanceAuditLog::where('action', 'tour.publish')
        ->where('target_id', $tour->id)
        ->exists();
    expect($auditLogExists)->toBeFalse();
});

it('publishes tour for approved partner and records immutable governance audit log', function () {
    $admin = seedSecurityAdmin(canManageTours: true);
    $tour = makeTourWithPartnerStatus(partnerOnboardingStatus: 'approved', tourStatus: 'pending_review');

    $action = app(ApproveTourAction::class);
    $publishedTour = $action->execute($admin, $tour);

    expect($publishedTour->status)->toBe(TourStatus::Published->value);

    // Immutable audit log is recorded on successful publication
    $auditLog = GovernanceAuditLog::where('action', 'tour.publish')
        ->where('target_id', $tour->id)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->actor_id)->toBe($admin->id)
        ->and($auditLog->after_state['status'])->toBe(TourStatus::Published->value);
});
