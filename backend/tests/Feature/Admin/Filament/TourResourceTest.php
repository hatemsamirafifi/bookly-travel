<?php

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Enums\TourStatus;
use App\Filament\Resources\TourResource;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function adminWithToursFlag(): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_tours' => true]]);

    return $admin->fresh('adminPermission');
}

function makeApprovedPartnerTour(string $status = 'pending_review'): Tour
{
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::create([
        'user_id' => $partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    return Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test'])->id,
        'slug' => 'filament-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => $status,
    ]);
}

beforeEach(function () {
    $this->admin = adminWithToursFlag();
    actingAs($this->admin);
});

it('lists tour records in the Filament table', function () {
    $tour = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->assertCanSeeTableRecords([$tour]);
});

it('publishes a tour via the Filament publish action and writes audit', function () {
    $tour = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableAction('publish', $tour)
        ->assertHasNoTableActionErrors();

    expect($tour->fresh()->status)->toBe(TourStatus::Published->value)
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->exists())->toBeTrue();
});

it('rejects a tour via the Filament reject action with a reason', function () {
    $tour = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableAction('reject', $tour, ['rejection_reason' => 'Bad photos'])
        ->assertHasNoTableActionErrors();

    expect($tour->fresh()->status)->toBe(TourStatus::Rejected->value);
    $log = GovernanceAuditLog::where('action', 'tour.reject')->where('target_id', $tour->id)->first();
    expect($log)->not->toBeNull()->and($log->metadata['reason'])->toBe('Bad photos');
});

it('bulk-publishes selected tours and writes an audit per item', function () {
    $tourA = makeApprovedPartnerTour('pending_review');
    $tourB = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableBulkAction('bulk_publish', [$tourA, $tourB])
        ->assertHasNoTableActionErrors();

    expect($tourA->fresh()->status)->toBe(TourStatus::Published->value)
        ->and($tourB->fresh()->status)->toBe(TourStatus::Published->value)
        ->and(GovernanceAuditLog::whereIn('target_id', [$tourA->id, $tourB->id])->where('action', 'tour.publish')->count())->toBe(2);
});

it('bulk-rejects selected tours with a shared reason and writes an audit per item', function () {
    $tourA = makeApprovedPartnerTour('pending_review');
    $tourB = makeApprovedPartnerTour('pending_review');

    Livewire::test(TourResource\Pages\ListTours::class)
        ->callTableBulkAction('bulk_reject', [$tourA, $tourB], ['rejection_reason' => 'Policy violation'])
        ->assertHasNoTableActionErrors();

    expect($tourA->fresh()->status)->toBe(TourStatus::Rejected->value)
        ->and($tourB->fresh()->status)->toBe(TourStatus::Rejected->value)
        ->and(GovernanceAuditLog::whereIn('target_id', [$tourA->id, $tourB->id])->where('action', 'tour.reject')->count())->toBe(2);
});
