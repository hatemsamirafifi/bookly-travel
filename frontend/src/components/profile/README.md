# Profile

<!-- EGM contract -->

## Overview

Composes traveler identity, preference and password forms with profile API hooks.

## Interface

- [PasswordChangeForm.tsx](./PasswordChangeForm.tsx#L12) declares `default PasswordChangeForm`.
- [PreferencesForm.tsx](./PreferencesForm.tsx#L11) declares `default PreferencesForm`.
- [ProfileForm.tsx](./ProfileForm.tsx#L18) declares `default ProfileForm`.
- [ProfileSettings.tsx](./ProfileSettings.tsx#L27) declares `default ProfileSettings`.

## Invariants

- Profile submission trims the phone, represents an empty phone as null, and validates profile fields before sending the update. [ProfileSettings.tsx](./ProfileSettings.tsx#L50)

## Side Effects

- Loads and updates the profile through `useProfile` and changes the password through `useChangePassword`. A successful language change replaces the current route's locale. [ProfileSettings.tsx](./ProfileSettings.tsx#L32) [ProfileSettings.tsx](./ProfileSettings.tsx#L86) [ProfileSettings.tsx](./ProfileSettings.tsx#L104)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
