# Auth

<!-- EGM contract -->

## Overview

Validates traveler login, registration, password operations, and guest identity and conversion input.

## Interface

- [ChangePasswordRequest.php](./ChangePasswordRequest.php#L7) declares `ChangePasswordRequest`, `ChangePasswordRequest::authorize()`, `ChangePasswordRequest::rules()`, `ChangePasswordRequest::messages()`.
- [ConvertGuestRequest.php](./ConvertGuestRequest.php#L7) declares `ConvertGuestRequest`, `ConvertGuestRequest::authorize()`, `ConvertGuestRequest::rules()`, `ConvertGuestRequest::messages()`.
- [ForgotPasswordRequest.php](./ForgotPasswordRequest.php#L7) declares `ForgotPasswordRequest`, `ForgotPasswordRequest::authorize()`, `ForgotPasswordRequest::rules()`, `ForgotPasswordRequest::messages()`.
- [GuestIdentityRequest.php](./GuestIdentityRequest.php#L7) declares `GuestIdentityRequest`, `GuestIdentityRequest::authorize()`, `GuestIdentityRequest::rules()`, `GuestIdentityRequest::messages()`.
- [LoginRequest.php](./LoginRequest.php#L8) declares `LoginRequest`, `LoginRequest::authorize()`, `LoginRequest::rules()`, `LoginRequest::messages()`.
- [RegisterRequest.php](./RegisterRequest.php#L7) declares `RegisterRequest`, `RegisterRequest::authorize()`, `RegisterRequest::rules()`, `RegisterRequest::messages()`.
- [ResetPasswordRequest.php](./ResetPasswordRequest.php#L7) declares `ResetPasswordRequest`, `ResetPasswordRequest::authorize()`, `ResetPasswordRequest::rules()`, `ResetPasswordRequest::messages()`.

## Invariants

- `ConvertGuestRequest::rules` requires a confirmed password with at least eight characters and uppercase, lowercase, and numeric characters. `prepareForValidation` lowercases and trims string emails. [ConvertGuestRequest.php](./ConvertGuestRequest.php#L20) [ConvertGuestRequest.php](./ConvertGuestRequest.php#L41)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
