<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds default admin user with all flags in testing environment', function () {
    $seeder = new AdminUserSeeder;
    $seeder->run();

    $admin = User::where('email', 'admin@bookly.test')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe('admin')
        ->and(Hash::check('password', $admin->password))->toBeTrue()
        ->and($admin->adminPermission)->not->toBeNull()
        ->and($admin->adminPermission->flags['manage_tours'])->toBeTrue()
        ->and($admin->adminPermission->flags['view_audit_log'])->toBeTrue();
});

it('refuses to seed in non-local environment if password is default or missing', function () {
    app()['env'] = 'staging';

    putenv('ADMIN_EMAIL=security-admin@bookly.com');
    putenv('ADMIN_PASSWORD=password'); // known default

    expect(fn () => (new AdminUserSeeder)->run())
        ->toThrow(RuntimeException::class, 'without a secure ADMIN_PASSWORD');
})->after(function () {
    app()['env'] = 'testing';
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
});

it('refuses to seed in non-local environment if email uses test domain', function () {
    app()['env'] = 'production';

    putenv('ADMIN_EMAIL=admin@bookly.test');
    putenv('ADMIN_PASSWORD=SuperSecureP@ssw0rd2026!');

    expect(fn () => (new AdminUserSeeder)->run())
        ->toThrow(RuntimeException::class, 'with a test domain (@bookly.test)');
})->after(function () {
    app()['env'] = 'testing';
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
});

it('successfully seeds in non-local environment with valid email and strong password', function () {
    app()['env'] = 'staging';

    putenv('ADMIN_EMAIL=ops-admin@bookly.travel');
    putenv('ADMIN_PASSWORD=StrongStagingP@ssw0rd2026!');

    $seeder = new AdminUserSeeder;
    $seeder->run();

    $admin = User::where('email', 'ops-admin@bookly.travel')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe('admin')
        ->and(Hash::check('StrongStagingP@ssw0rd2026!', $admin->password))->toBeTrue()
        ->and($admin->adminPermission->flags['manage_tours'])->toBeTrue();
})->after(function () {
    app()['env'] = 'testing';
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
});
