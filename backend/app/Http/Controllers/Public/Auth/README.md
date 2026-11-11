# Auth

<!-- EGM contract -->

## Overview

JSON adapters for traveler registration, login, logout, guest identity and conversion, password reset, and verification.

## Interface

- [EmailVerificationController.php](./EmailVerificationController.php#L17) declares `EmailVerificationController`, `EmailVerificationController::verify()`.
- [ForgotPasswordController.php](./ForgotPasswordController.php#L12) declares `ForgotPasswordController`, `ForgotPasswordController::__invoke()`.
- [GuestConversionController.php](./GuestConversionController.php#L11) declares `GuestConversionController`, `GuestConversionController::__invoke()`.
- [GuestIdentityController.php](./GuestIdentityController.php#L10) declares `GuestIdentityController`, `GuestIdentityController::__invoke()`.
- [LoginController.php](./LoginController.php#L11) declares `LoginController`, `LoginController::__invoke()`.
- [LogoutController.php](./LogoutController.php#L9) declares `LogoutController`, `LogoutController::__invoke()`.
- [MeController.php](./MeController.php#L17) declares `MeController`, `MeController::__invoke()`.
- [RegisterController.php](./RegisterController.php#L11) declares `RegisterController`, `RegisterController::__invoke()`.
- [ResendVerificationController.php](./ResendVerificationController.php#L11) declares `ResendVerificationController`, `ResendVerificationController::__invoke()`.
- [ResetPasswordController.php](./ResetPasswordController.php#L10) declares `ResetPasswordController`, `ResetPasswordController::__invoke()`.

## Invariants

- `LoginController::__invoke` returns 423 with `account_locked`, 422 with `invalid_credentials`, or 200 with the serialized user and bearer token. [LoginController.php](./LoginController.php#L16)
- `EmailVerificationController::verify` checks both the URL signature and email hash. A previously verified email returns 200. [EmailVerificationController.php](./EmailVerificationController.php#L32)
- `ForgotPasswordController::__invoke` returns the same generic 200 response regardless of whether the email has an account. [ForgotPasswordController.php](./ForgotPasswordController.php#L18)
- `GuestIdentityController::__invoke` returns `has_account` when the normalized email belongs to an existing account and returns 201 only for a newly created guest. [GuestIdentityController.php](./GuestIdentityController.php#L15)

## Side Effects

- `ForgotPasswordController::__invoke` dispatches a reset email job for a verified account or a verification email job for an unverified account. [ForgotPasswordController.php](./ForgotPasswordController.php#L23)
- `ResendVerificationController::__invoke` records up to three attempts per hour by user ID before calling `SendVerificationEmailAction`. [ResendVerificationController.php](./ResendVerificationController.php#L26)
- `EmailVerificationController::verify` persists verification and dispatches Laravel's `Verified` event when `markEmailAsVerified` succeeds. [EmailVerificationController.php](./EmailVerificationController.php#L50)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
