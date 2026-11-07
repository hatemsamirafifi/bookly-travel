<?php

use App\Domains\Admin\Models\AdminRole;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\AdminUserService;
use App\Filament\Resources\AdminRoleResource;
use App\Filament\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AdminUserService::class);
    $this->auth = app(AdminAuthorizationService::class);
    AdminRole::ensureSystemRoles();
});

function superAdmin(): User
{
    $user = User::factory()->admin()->create();
    $user->adminRoles()->attach(AdminRole::where('slug', 'super_admin')->value('id'));

    return $user->fresh('adminRoles');
}

function prefixAllFlags(bool $value): array
{
    return array_fill_keys(AdminAuthorizationService::FLAGS, $value);
}

// ---- T-A01: system role templates seed & role-based permission grants ----

it('seeds all 7 canonical system role templates idempotently', function () {
    AdminRole::ensureSystemRoles();

    expect(AdminRole::where('is_system', true)->count())->toBe(7)
        ->and(AdminRole::where('is_system', true)->orderBy('slug')->pluck('slug')->values()->all())
        ->toEqual(collect(array_keys(AdminRole::systemRoleTemplates()))->sort()->values()->all());

    AdminRole::where('slug', 'media_manager')->update(['name' => 'Corrupted', 'is_system' => false]);
    AdminRole::ensureSystemRoles();

    $mediaManager = AdminRole::where('slug', 'media_manager')->first();

    expect($mediaManager->name)->toBe('Media Manager')
        ->and($mediaManager->is_system)->toBeTrue()
        ->and(count(AdminRole::all()))->toBe(7);
});

it('grants a permission flag through an assigned role', function () {
    $actor = superAdmin();

    $financeViewer = User::factory()->admin()->create();
    $financeViewer->adminRoles()->attach(AdminRole::where('slug', 'finance_viewer')->value('id'));
    $financeViewer = $financeViewer->fresh('adminRoles');

    expect($this->auth->can($financeViewer, 'view_finances'))->toBeTrue()
        ->and($this->auth->can($financeViewer, 'export_financial_reports'))->toBeTrue()
        ->and($this->auth->can($financeViewer, 'view_all_analytics'))->toBeTrue()
        ->and($this->auth->can($financeViewer, 'manage_tours'))->toBeFalse()
        ->and($this->auth->can($financeViewer, 'manage_users'))->toBeFalse();

    $created = $this->service->createAdmin($actor, [
        'name' => 'Support Agent',
        'email' => 'support@example.test',
        'password' => 'secret-password',
        'roles' => [AdminRole::where('slug', 'support_agent')->value('id')],
    ]);

    expect($this->auth->can($created, 'manage_bookings'))->toBeTrue()
        ->and($this->auth->can($created, 'manage_travelers'))->toBeTrue()
        ->and($this->auth->can($created, 'manage_tours'))->toBeFalse();
});

// ---- T-A02: User::canAccessPanel gates by role + status ----

it('denies suspended admins panel access and grants it to active admins', function () {
    $actor = superAdmin();
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_users' => true]]);
    $admin = $admin->fresh('adminPermission');

    expect($admin->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();

    $this->service->suspendAdmin($actor, $admin, 'policy breach');

    $admin = $admin->fresh('adminPermission');

    expect($admin->status)->toBe('suspended')
        ->and($admin->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

// ---- T-A02: anti-escalation ----

it('blocks a limited admin from granting flags they do not hold', function () {
    $actor = adminFlagsLimitedTo('manage_users');
    $target = User::factory()->admin()->create();

    expect(fn () => $this->service->updateAdmin($actor, $target, [
        'flags' => ['manage_users' => true, 'manage_roles' => true],
    ]))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->updateAdmin($actor, $target, [
            'flags' => ['view_all_analytics' => true],
        ]))->toThrow(ValidationException::class)
        ->and($target->adminPermission()->exists())->toBeFalse();
});

it('blocks a limited admin from assigning roles containing flags they do not hold', function () {
    $actor = adminFlagsLimitedTo('manage_users');
    $contentManager = AdminRole::where('slug', 'content_manager')->first();
    $target = User::factory()->admin()->create();

    expect(fn () => $this->service->updateAdmin($actor, $target, [
        'roles' => [$contentManager->id],
    ]))->toThrow(ValidationException::class)
        ->and($target->adminRoles()->count())->toBe(0);
});

it('lets a super admin grant any flag or role', function () {
    $actor = superAdmin();

    $created = $this->service->createAdmin($actor, [
        'name' => 'Operator',
        'email' => 'operator@example.test',
        'password' => 'secret-password',
        'flags' => ['manage_roles' => true, 'view_all_analytics' => true],
        'roles' => [AdminRole::where('slug', 'operations_admin')->value('id')],
    ]);

    expect($this->auth->can($created, 'manage_roles'))->toBeTrue()
        ->and($this->auth->can($created, 'view_all_analytics'))->toBeTrue()
        ->and($this->auth->can($created, 'manage_tours'))->toBeTrue();
});

function adminFlagsLimitedTo(string ...$held): User
{
    $flags = prefixAllFlags(false);

    foreach ($held as $flag) {
        $flags[$flag] = true;
    }

    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => $flags]);

    return $admin->fresh(['adminPermission', 'adminRoles']);
}

// ---- T-A02: last active Super Admin protection ----

it('protects the sole active Super Admin from suspension', function () {
    $sole = superAdmin();
    $ghost = superAdmin();

    // Suspend the second Super Admin so $sole becomes the last active one.
    $this->service->suspendAdmin($sole, $ghost, 'reduce to one');

    expect(fn () => $this->service->suspendAdmin($ghost, $sole, 'audit'))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->service->assertNotLastActiveSuperAdmin($sole))
        ->toThrow(ValidationException::class)
        ->and($sole->fresh()->status)->toBe('active');
});

it('allows suspending a Super Admin when another active Super Admin remains', function () {
    $first = superAdmin();
    $second = superAdmin();

    $result = $this->service->suspendAdmin($first, $second, 'vacation');

    expect($result->status)->toBe('suspended')
        ->and($first->fresh()->status)->toBe('active');

    // A third Super Admin keeps the platform guarded while $first leaves.
    $third = superAdmin();

    expect($this->service->suspendAdmin($third, $first->fresh(), 'offboarding')->status)->toBe('suspended');
});

// ---- T-A02: suspension revokes tokens + sessions, and audits ----

it('suspends an admin, revokes their Sanctum tokens, and writes an audit entry', function () {
    $actor = superAdmin();
    $target = User::factory()->admin()->create();
    $target->adminPermission()->create(['flags' => ['manage_users' => true]]);
    $target->createToken('api');

    expect($target->tokens()->count())->toBe(1);

    actingAs($target)->get('/admin')->assertSuccessful();
    actingAs($actor);

    $result = $this->service->suspendAdmin($actor, $target, 'investigation');

    $target = $target->fresh(['adminPermission', 'adminRoles']);

    expect($result->status)->toBe('suspended')
        ->and($result->suspended_at)->not->toBeNull()
        ->and($result->suspension_reason)->toBe('investigation')
        ->and($target->tokens()->count())->toBe(0)
        ->and($target->canAccessPanel(filament()->getPanel('admin')))->toBeFalse()
        ->and(GovernanceAuditLog::where('action', 'admin_user.suspend')
            ->where('target_id', $target->id)
            ->exists())->toBeTrue();
});

it('reactivates a suspended admin, restoring panel access, and writes an audit entry', function () {
    $actor = superAdmin();
    $target = User::factory()->admin()->create();

    $this->service->suspendAdmin($actor, $target, 'temporary');

    $result = $this->service->reactivateAdmin($actor, $target->fresh(['adminPermission', 'adminRoles']));

    expect($result->status)->toBe('active')
        ->and($result->suspended_at)->toBeNull()
        ->and($result->suspension_reason)->toBeNull()
        ->and($result->canAccessPanel(filament()->getPanel('admin')))->toBeTrue()
        ->and(GovernanceAuditLog::where('action', 'admin_user.reactivate')
            ->where('target_id', $target->id)
            ->exists())->toBeTrue();
});

// ---- T-A03: Filament resources gate on manage_users / manage_roles ----

it('gates the Admin User resource behind manage_users', function () {
    $limited = User::factory()->admin()->create();
    $limited->adminPermission()->create(['flags' => ['view_audit_log' => true]]);
    actingAs($limited);

    get(AdminUserResource::getUrl('index'))->assertForbidden();

    $allowed = User::factory()->admin()->create();
    $allowed->adminPermission()->create(['flags' => ['manage_users' => true]]);
    actingAs($allowed);

    get(AdminUserResource::getUrl('index'))->assertSuccessful();
});

it('gates the Admin Role resource behind manage_roles or manage_users', function () {
    $limited = User::factory()->admin()->create();
    $limited->adminPermission()->create(['flags' => ['manage_blog' => true]]);
    actingAs($limited);

    get(AdminRoleResource::getUrl('index'))->assertForbidden();

    $manager = User::factory()->admin()->create();
    $manager->adminPermission()->create(['flags' => ['manage_roles' => true]]);
    actingAs($manager);

    get(AdminRoleResource::getUrl('index'))->assertSuccessful();
});

it('prevents deleting system roles and allows deleting custom roles', function () {
    actingAs(superAdmin());

    $systemRole = AdminRole::where('slug', 'super_admin')->first();
    $customRole = AdminRole::create([
        'name' => 'Custom',
        'slug' => 'custom',
        'permissions' => ['manage_tours' => true],
        'is_system' => false,
    ]);

    expect(AdminRoleResource::canDelete($systemRole))->toBeFalse()
        ->and(AdminRoleResource::canDelete($customRole))->toBeTrue();
});
