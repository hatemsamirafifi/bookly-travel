# Layout

<!-- EGM contract -->

## Overview

Renders localized public navigation and footer, the account menu, mobile navigation and locale switching.

## Interface

- [Footer.tsx](./Footer.tsx#L11) declares `default Footer`.
- [Header.tsx](./Header.tsx#L18) declares `default Header`.
- [LocaleSwitcher.tsx](./LocaleSwitcher.tsx#L13) declares `default LocaleSwitcher`.
- [MobileNavPanel.tsx](./MobileNavPanel.tsx#L20) declares `default MobileNavPanel`.
- [UserMenuDropdown.tsx](./UserMenuDropdown.tsx#L16) declares `default UserMenuDropdown`.

## Invariants

- Desktop and mobile account navigation distinguish signed-out visitors from partner and traveler accounts. The locale switch replaces the current locale prefix and preserves the query string. [Header.tsx](./Header.tsx#L87) [MobileNavPanel.tsx](./MobileNavPanel.tsx#L32) [LocaleSwitcher.tsx](./LocaleSwitcher.tsx#L20)

## Side Effects

- Locale changes assign `window.location.href`. Header search opening focuses the input, and the account menu handles outside interaction and keyboard focus. [LocaleSwitcher.tsx](./LocaleSwitcher.tsx#L20) [Header.tsx](./Header.tsx#L32) [UserMenuDropdown.tsx](./UserMenuDropdown.tsx#L28)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
