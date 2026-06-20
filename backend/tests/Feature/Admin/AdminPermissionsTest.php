<?php

use App\Domains\Admin\Models\AdminPermission;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'approved',
        'is_active' => true,
    ]);

    $this->tour = Tour::create([
        'partner_id' => $this->partner->id,
        'category_id' => $this->category->id,
        'slug' => 'perms-tour-' . uniqid(),
        'location' => 'Rome, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 10,
        'price_amount' => 5000,
        'status' => 'pending_review',
    ]);

    $this->booking = Booking::create([
        'reference' => Booking::generateReference(),
        'traveler_id' => User::factory()->traveler()->create()->id,
        'tour_id' => $this->tour->id,
        'tour_date' => now()->addDay(),
        'participant_count' => 1,
        'price_per_person' => 5000,
        'total_price' => 5000,
        'currency' => 'EUR',
        'status' => Booking::STATUS_CONFIRMED,
    ]);
});

function adminWithFlags(array $flags): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => $flags]);

    return $admin->fresh('adminPermission');
}

it('denies non-admin users access to the Filament admin panel', function () {
    $traveler = User::factory()->traveler()->create();

    actingAs($traveler)->get('/admin')->assertForbidden();
});

it('lets a permissioned admin reach the Filament admin panel', function () {
    $admin = adminWithFlags(array_fill_keys(AdminAuthorizationService::FLAGS, true));

    actingAs($admin)->get('/admin')->assertSuccessful();
});

it('denies non-admin users access to admin API routes (role:admin guard)', function () {
    $traveler = User::factory()->traveler()->create();

    actingAs($traveler, 'sanctum')->getJson('/api/admin/reviews')->assertForbidden();
});

it('denies unauthenticated access to admin API routes', function () {
    getJson('/api/admin/reviews')->assertUnauthorized();
});

it('hides governance actions for an admin lacking the required flag', function () {
    $adminNoFlags = User::factory()->admin()->create(); // no AdminPermission row

    expect($adminNoFlags->can('publish', $this->tour))->toBeFalse()
        ->and($adminNoFlags->can('reject', $this->tour))->toBeFalse()
        ->and($adminNoFlags->can('unpublish', $this->tour))->toBeFalse()
        ->and($adminNoFlags->can('approve', $this->partner))->toBeFalse()
        ->and($adminNoFlags->can('suspend', $this->partner))->toBeFalse()
        ->and($adminNoFlags->can('transition', $this->booking))->toBeFalse();
});

it('grants governance actions for an admin with the required flags', function () {
    $admin = adminWithFlags(array_fill_keys(AdminAuthorizationService::FLAGS, true));

    expect($admin->can('publish', $this->tour))->toBeTrue()
        ->and($admin->can('reject', $this->tour))->toBeTrue()
        ->and($admin->can('approve', $this->partner))->toBeTrue()
        ->and($admin->can('suspend', $this->partner))->toBeTrue()
        ->and($admin->can('transition', $this->booking))->toBeTrue();
});

it('resolves per-action flags independently', function () {
    $toursOnly = adminWithFlags(['manage_tours' => true]);

    expect($toursOnly->can('publish', $this->tour))->toBeTrue()
        ->and($toursOnly->can('approve', $this->partner))->toBeFalse()
        ->and($toursOnly->can('transition', $this->booking))->toBeFalse();
});