# Seeders

<!-- EGM contract -->

## Overview

Creates development and browser fixtures, marketplace demo content, admin access, and canonical admin roles.

## Interface

- `DatabaseSeeder` groups the partner, blog browser, traveler booking and voucher verification fixtures for local/testing. [DatabaseSeeder.php](./DatabaseSeeder.php#L25)
- `AdminRbacSeeder` aliases `AdminRoleSeeder`, which calls `AdminRole::ensureSystemRoles()`. [AdminRbacSeeder.php](./AdminRbacSeeder.php#L10), [AdminRoleSeeder.php](./AdminRoleSeeder.php#L15)
- [AdminE2EFixtureSeeder.php](./AdminE2EFixtureSeeder.php#L29) declares `AdminE2EFixtureSeeder`, `AdminE2EFixtureSeeder::run()`.
- [AdminRbacSeeder.php](./AdminRbacSeeder.php#L10) declares `AdminRbacSeeder`.
- [AdminRoleSeeder.php](./AdminRoleSeeder.php#L8) declares `AdminRoleSeeder`, `AdminRoleSeeder::run()`.
- [AdminUserSeeder.php](./AdminUserSeeder.php#L10) declares `AdminUserSeeder`, `AdminUserSeeder::run()`.
- [BlogBrowserTestSeeder.php](./BlogBrowserTestSeeder.php#L12) declares `BlogBrowserTestSeeder`, `BlogBrowserTestSeeder::run()`.
- [BlogContentSeeder.php](./BlogContentSeeder.php#L13) declares `BlogContentSeeder`, `BlogContentSeeder::run()`.
- [DatabaseSeeder.php](./DatabaseSeeder.php#L18) declares `DatabaseSeeder`, `DatabaseSeeder::run()`.
- [DemoContentSeeder.php](./DemoContentSeeder.php#L17) declares `DemoContentSeeder`, `DemoContentSeeder::run()`.
- [PartnerSeeder.php](./PartnerSeeder.php#L22) declares `PartnerSeeder`, `PartnerSeeder::run()`.
- [VoucherVerificationSeeder.php](./VoucherVerificationSeeder.php#L25) declares `VoucherVerificationSeeder`, `VoucherVerificationSeeder::run()`.

## Invariants

- Outside local and testing, admin seeding requires an email outside `@bookly.test` and a password of at least 12 characters distinct from the weak local default. [AdminUserSeeder.php](./AdminUserSeeder.php#L20)

## Side Effects

- Seeders persist records for users, access flags, marketplace content and booking fixtures. [AdminUserSeeder.php](./AdminUserSeeder.php#L41), [DemoContentSeeder.php](./DemoContentSeeder.php#L351), [BlogContentSeeder.php](./BlogContentSeeder.php#L279), [VoucherVerificationSeeder.php](./VoucherVerificationSeeder.php#L27)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
