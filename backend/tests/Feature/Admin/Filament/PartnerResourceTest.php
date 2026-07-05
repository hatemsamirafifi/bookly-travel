<?php

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Enums\PartnerStatus;
use App\Filament\Resources\PartnerResource;
use App\Filament\Resources\PartnerResource\Pages\ListPartners;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Livewire\Livewire;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (!function_exists('partnersAdmin')) {
    function partnersAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => ['manage_partners' => true]]);

        return $admin->fresh('adminPermission');
    }
}

if (!function_exists('makePartner')) {
    function makePartner(string $onboarding = 'pending', bool $active = false): Partner
    {
        $user = User::factory()->partner()->create();

        return Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => $onboarding,
            'is_active' => $active,
        ]);
    }
}

it('lists partners for a manage_partners admin', function () {
    actingAs(partnersAdmin());
    $partner = makePartner('pending');

    Livewire::test(ListPartners::class)
        ->assertCanSeeTableRecords([$partner]);
});

it('approves a pending partner via the Filament table action and audits it', function () {
    Mail::fake();
    actingAs(partnersAdmin());
    $partner = makePartner('pending');

    Livewire::test(ListPartners::class)
        ->callTableAction('approve', $partner)
        ->assertHasNoTableActionErrors();

    expect($partner->fresh()->onboarding_status)->toBe(PartnerStatus::Approved->value)
        ->and($partner->fresh()->is_active)->toBeTrue();

    expect(GovernanceAuditLog::where('action', 'partner.approve')->where('target_id', $partner->id)->exists())->toBeTrue();
});

it('rejects a pending partner with a reason via the Filament table action', function () {
    Mail::fake();
    actingAs(partnersAdmin());
    $partner = makePartner('pending');

    Livewire::test(ListPartners::class)
        ->callTableAction('reject', $partner, ['rejection_reason' => 'Incomplete documents.'])
        ->assertHasNoTableActionErrors();

    expect($partner->fresh()->onboarding_status)->toBe(PartnerStatus::Rejected->value);
    expect(GovernanceAuditLog::where('action', 'partner.reject')->where('target_id', $partner->id)->exists())->toBeTrue();
});

it('suspends an approved active partner via the Filament table action', function () {
    actingAs(partnersAdmin());
    $partner = makePartner('approved', true);

    Livewire::test(ListPartners::class)
        ->callTableAction('suspend', $partner)
        ->assertHasNoTableActionErrors();

    expect($partner->fresh()->onboarding_status)->toBe(PartnerStatus::Suspended->value)
        ->and($partner->fresh()->is_active)->toBeFalse();
    expect(GovernanceAuditLog::where('action', 'partner.suspend')->where('target_id', $partner->id)->exists())->toBeTrue();
});

it('reactivates a suspended partner via the Filament table action', function () {
    actingAs(partnersAdmin());
    $partner = makePartner('suspended', false);

    Livewire::test(ListPartners::class)
        ->callTableAction('unsuspend', $partner)
        ->assertHasNoTableActionErrors();

    expect($partner->fresh()->onboarding_status)->toBe(PartnerStatus::Approved->value);
    expect(GovernanceAuditLog::where('action', 'partner.reinstate')->where('target_id', $partner->id)->exists())->toBeTrue();
});

it('registers no bulk actions on the partner resource (FR-016)', function () {
    actingAs(partnersAdmin());

    $page = Livewire::test(ListPartners::class);

    expect(count($page->instance()->getTable()->getBulkActions()))->toBe(0);
});

it('denies partner governance to an admin lacking manage_partners', function () {
    $adminNoFlag = User::factory()->admin()->create();
    $adminNoFlag->adminPermission()->create(['flags' => []]);
    $partner = makePartner('pending');

    expect($adminNoFlag->can('approve', $partner))->toBeFalse()
        ->and($adminNoFlag->can('viewAny', Partner::class))->toBeFalse();

    actingAs(partnersAdmin());
    expect(auth()->user()->can('approve', $partner))->toBeTrue()
        ->and(auth()->user()->can('viewAny', Partner::class))->toBeTrue();
});