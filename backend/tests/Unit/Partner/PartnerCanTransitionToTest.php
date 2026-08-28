<?php

declare(strict_types=1);

use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;

it('allows valid onboarding status transitions', function () {
    expect(Partner::canTransition('pending', 'approved'))->toBeTrue();
    expect(Partner::canTransition('pending', 'rejected'))->toBeTrue();
    expect(Partner::canTransition('approved', 'suspended'))->toBeTrue();
    expect(Partner::canTransition('suspended', 'approved'))->toBeTrue();
    expect(Partner::canTransition('rejected', 'pending'))->toBeTrue();
});

it('rejects invalid onboarding status transitions', function () {
    expect(Partner::canTransition('pending', 'pending'))->toBeFalse();
    expect(Partner::canTransition('approved', 'approved'))->toBeFalse();
    expect(Partner::canTransition('suspended', 'pending'))->toBeFalse();
    expect(Partner::canTransition('rejected', 'approved'))->toBeFalse();
    expect(Partner::canTransition('approved', 'rejected'))->toBeFalse();
});

it('normalizes legacy incomplete status to pending', function () {
    expect(Partner::canTransition('incomplete', 'approved'))->toBeTrue();
    expect(Partner::canTransition('incomplete', 'rejected'))->toBeTrue();
    expect(Partner::canTransition('incomplete', 'suspended'))->toBeFalse();
});

it('instance canTransitionTo delegates properly', function () {
    $partner = new Partner(['onboarding_status' => 'pending']);
    expect($partner->canTransitionTo(PartnerStatus::Approved))->toBeTrue();
    expect($partner->canTransitionTo(PartnerStatus::Rejected))->toBeTrue();
    expect($partner->canTransitionTo(PartnerStatus::Suspended))->toBeFalse();
});
