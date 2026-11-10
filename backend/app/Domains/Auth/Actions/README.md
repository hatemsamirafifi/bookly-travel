# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Auth/Actions`.

## Interface

- [AuthenticateTravelerAction.php](./AuthenticateTravelerAction.php#L13) declares `AuthenticateTravelerAction`, `AuthenticateTravelerAction::execute()`.
- [ChangePasswordAction.php](./ChangePasswordAction.php#L10) declares `ChangePasswordAction`, `ChangePasswordAction::execute()`.
- [ConvertGuestToAccountAction.php](./ConvertGuestToAccountAction.php#L10) declares `ConvertGuestToAccountAction`, `ConvertGuestToAccountAction::execute()`.
- [LinkGuestBookingsAction.php](./LinkGuestBookingsAction.php#L11) declares `LinkGuestBookingsAction`, `LinkGuestBookingsAction::execute()`.
- [LogoutTravelerAction.php](./LogoutTravelerAction.php#L7) declares `LogoutTravelerAction`, `LogoutTravelerAction::execute()`.
- [RegisterTravelerAction.php](./RegisterTravelerAction.php#L9) declares `RegisterTravelerAction`, `RegisterTravelerAction::execute()`.
- [ResetPasswordAction.php](./ResetPasswordAction.php#L11) declares `ResetPasswordAction`, `ResetPasswordAction::execute()`.
- [SendVerificationEmailAction.php](./SendVerificationEmailAction.php#L8) declares `SendVerificationEmailAction`, `SendVerificationEmailAction::execute()`.
- [VerifyEmailAction.php](./VerifyEmailAction.php#L8) declares `VerifyEmailAction`, `VerifyEmailAction::execute()`.

## Invariants

- `AuthenticateTravelerAction::execute` locks the existing user's row before checking lockout or updating login failure counters. The success and existing-user failure events are scheduled with `DB::afterCommit`. [Login transaction](./AuthenticateTravelerAction.php#L33), [failure events](./AuthenticateTravelerAction.php#L86), and [success event](./AuthenticateTravelerAction.php#L106).
- `LinkGuestBookingsAction::execute` selects unconverted guest identities for the user's email and links only bookings with no traveler ID. [Guest and booking selection](./LinkGuestBookingsAction.php#L19).

## Side Effects

- `LinkGuestBookingsAction::execute` marks the identities converted, assigns unowned matching bookings to the traveler when the bookings table exists, and writes a `guest_bookings_linked` audit entry. [Guest linkage effects](./LinkGuestBookingsAction.php#L31).
- `ChangePasswordAction::execute` checks the current password, hashes the replacement, clears the user's password-reset tokens, and emits `PasswordChanged`. [Password change](./ChangePasswordAction.php#L17).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
