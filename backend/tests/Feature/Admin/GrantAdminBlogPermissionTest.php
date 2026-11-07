<?php

use App\Domains\Admin\Models\AdminPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects execution when neither --email nor --id is provided', function () {
    $this->artisan('admin:grant-blog-permission')
        ->expectsOutputToContain('Operation rejected: You must designate at least one administrator')
        ->assertExitCode(1);
});

it('grants manage_blog to the intended administrator by email and preserves existing permissions', function () {
    $targetAdmin = User::factory()->admin()->create(['email' => 'target.admin@bookly.test']);
    $targetAdmin->adminPermission()->create([
        'flags' => [
            'manage_tours' => true,
            'manage_partners' => false,
            'view_all_analytics' => true,
        ],
    ]);

    $nonTargetAdmin = User::factory()->admin()->create(['email' => 'other.admin@bookly.test']);
    $nonTargetAdmin->adminPermission()->create([
        'flags' => [
            'manage_tours' => false,
        ],
    ]);

    $this->artisan('admin:grant-blog-permission', ['--email' => ['target.admin@bookly.test']])
        ->expectsOutputToContain('1 account(s) updated')
        ->assertExitCode(0);

    $targetFlags = $targetAdmin->fresh()->adminPermission->flags;
    expect($targetFlags['manage_blog'])->toBeTrue()
        ->and($targetFlags['manage_tours'])->toBeTrue()
        ->and($targetFlags['manage_partners'])->toBeFalse()
        ->and($targetFlags['view_all_analytics'])->toBeTrue();

    $nonTargetFlags = $nonTargetAdmin->fresh()->adminPermission->flags;
    expect($nonTargetFlags)->not->toHaveKey('manage_blog')
        ->and($nonTargetFlags['manage_tours'])->toBeFalse();
});

it('grants manage_blog to the intended administrator by user ID', function () {
    $admin = User::factory()->admin()->create(['email' => 'staff@bookly.test']);
    $admin->adminPermission()->create(['flags' => ['manage_bookings' => true]]);

    $this->artisan('admin:grant-blog-permission', ['--id' => [(string) $admin->id]])
        ->assertExitCode(0);

    $flags = $admin->fresh()->adminPermission->flags;
    expect($flags['manage_blog'])->toBeTrue()
        ->and($flags['manage_bookings'])->toBeTrue();
});

it('creates an admin_permissions row if the administrator has none', function () {
    $admin = User::factory()->admin()->create(['email' => 'newadmin@bookly.test']);
    expect($admin->adminPermission)->toBeNull();

    $this->artisan('admin:grant-blog-permission', ['--email' => ['newadmin@bookly.test']])
        ->assertExitCode(0);

    $flags = $admin->fresh()->adminPermission->flags;
    expect($flags)->toMatchArray(['manage_blog' => true]);
});

it('does not modify records in dry-run mode', function () {
    $admin = User::factory()->admin()->create(['email' => 'dryrun@bookly.test']);
    $admin->adminPermission()->create(['flags' => ['manage_cms' => true]]);

    $this->artisan('admin:grant-blog-permission', [
        '--email' => ['dryrun@bookly.test'],
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('[DRY RUN]')
        ->assertExitCode(0);

    $flags = $admin->fresh()->adminPermission->flags;
    expect($flags)->not->toHaveKey('manage_blog')
        ->and($flags['manage_cms'])->toBeTrue();
});

it('is idempotent and skips accounts that already have manage_blog', function () {
    $admin = User::factory()->admin()->create(['email' => 'existing@bookly.test']);
    $admin->adminPermission()->create([
        'flags' => [
            'manage_blog' => true,
            'manage_tours' => true,
        ],
    ]);

    $this->artisan('admin:grant-blog-permission', ['--email' => ['existing@bookly.test']])
        ->expectsOutputToContain('already has manage_blog permission (no-op)')
        ->expectsOutputToContain('0 account(s) updated, 1 account(s) already up to date')
        ->assertExitCode(0);

    $flags = $admin->fresh()->adminPermission->flags;
    expect($flags['manage_blog'])->toBeTrue()
        ->and($flags['manage_tours'])->toBeTrue();
});

it('does not grant permission to non-admin accounts even if specified', function () {
    $traveler = User::factory()->traveler()->create(['email' => 'traveler@bookly.test']);

    $this->artisan('admin:grant-blog-permission', ['--email' => ['traveler@bookly.test']])
        ->expectsOutputToContain('No matching administrator accounts found')
        ->assertExitCode(0);

    expect(AdminPermission::where('user_id', $traveler->id)->exists())->toBeFalse();
});
