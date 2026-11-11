# Auth

<!-- EGM contract -->

## Overview

Provides localized authentication forms and client-side guards for traveler and partner views.

## Interface

- [AuthGuard.tsx](./AuthGuard.tsx#L14) declares `AuthGuard`.
- [AuthPageShell.tsx](./AuthPageShell.tsx#L10) declares `AuthPageShell`.
- [ForgotPasswordForm.tsx](./ForgotPasswordForm.tsx#L16) declares `ForgotPasswordForm`.
- [GuestConversionPrompt.tsx](./GuestConversionPrompt.tsx#L21) declares `GuestConversionPrompt`.
- [LoginForm.tsx](./LoginForm.tsx#L24) declares `LoginForm`.
- [PartnerAuthGuard.tsx](./PartnerAuthGuard.tsx#L12) declares `PartnerAuthGuard`.
- [PartnerRegisterForm.tsx](./PartnerRegisterForm.tsx#L21) declares `PartnerRegisterForm`.
- [RegisterForm.tsx](./RegisterForm.tsx#L24) declares `RegisterForm`.
- [ResetPasswordForm.tsx](./ResetPasswordForm.tsx#L22) declares `ResetPasswordForm`.
- [VerifyEmailClient.tsx](./VerifyEmailClient.tsx#L12) declares `VerifyEmailClient`.

## Invariants

- Guards wait for session restoration before redirecting or exposing protected children. The partner guard requires `user.role === 'partner'`. [AuthGuard.tsx](./AuthGuard.tsx#L20) [PartnerAuthGuard.tsx](./PartnerAuthGuard.tsx#L18)
- Login sanitizes same-locale return URLs for both successful login and the registration cross-link, preserving a valid checkout query. [LoginForm.tsx](./LoginForm.tsx#L34) [LoginForm.tsx](./LoginForm.tsx#L58)

## Side Effects

- The general guard consumes the `sessionExpired` session-storage flag when redirecting to login. Signed-in visitors to guest-only views navigate through the safe return helper. [AuthGuard.tsx](./AuthGuard.tsx#L20)
- Forms call the auth provider or API for login, registration, password reset and verification resend. [LoginForm.tsx](./LoginForm.tsx#L58) [RegisterForm.tsx](./RegisterForm.tsx#L62) [ResetPasswordForm.tsx](./ResetPasswordForm.tsx#L52) [VerifyEmailClient.tsx](./VerifyEmailClient.tsx#L25)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
