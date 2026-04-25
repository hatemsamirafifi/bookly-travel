# Feature Specification: Traveler Sign-In and Sign-Out

**Feature Branch**: `004-traveler-signin`
**Created**: 2026-04-25
**Status**: Draft
**Input**: User description: "Implement Phase 4 (User Story 2) from the Traveler Authentication feature — traveler sign-in with email/password, sign-out with token revocation, brute-force protection with escalating lockout, and multi-language support"
**Parent Feature**: `001-traveler-auth` (Phase 4)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Traveler Sign-In (Priority: P1) 🎯 MVP

A returning traveler wants to sign into their existing account to view upcoming bookings, download vouchers, or leave reviews. They navigate to the sign-in page and enter their email and password. The system verifies their credentials, signs them in, and redirects them to the page they were previously browsing (or the homepage). The system updates the traveler's last sign-in timestamp for security auditing.

**Why this priority**: Sign-in is the gateway to all authenticated features. Without it, registered travelers cannot access their bookings, reviews, or account settings. This is the core user journey for returning users.

**Independent Test**: Navigate to the sign-in page, submit valid email and password, confirm the traveler is signed in and redirected. Verify that `last_login_at` is updated and an audit log entry is created.

**Acceptance Scenarios**:

1. **Given** a traveler has a registered account with email "jane@example.com" and password "MyPassword1", **When** they enter those credentials on the sign-in page, **Then** the system authenticates them, issues a session token, updates their `last_login_at` timestamp, and redirects them to the page they were previously viewing (or the homepage if no return URL is present).
2. **Given** a traveler enters an email that does not exist in the system, **When** they submit the sign-in form, **Then** the system displays the generic error message "Invalid email or password" — the same message regardless of whether the email or password was wrong (to prevent email enumeration).
3. **Given** a traveler enters a valid email but incorrect password, **When** they submit the sign-in form, **Then** the system displays the same generic error message "Invalid email or password" and increments the failed login counter for that account.
4. **Given** a traveler submits the sign-in form with an empty email or empty password, **When** the form is submitted, **Then** client-side validation prevents the request from being sent and highlights the specific empty fields.
5. **Given** a traveler has just signed in, **When** the system processes the successful authentication, **Then** a `TravelerLoggedIn` event is dispatched, which triggers the `LogAuthEvent` listener to write a `login_success` entry to `auth_audit_logs` with the traveler's ID, timestamp, IP address, and user agent.

---

### User Story 2 - Traveler Sign-Out (Priority: P1)

A signed-in traveler wants to securely end their session. They click the sign-out button. The system revokes their current session token (and only their current token — other sessions on other devices remain active). The traveler is redirected to the homepage.

**Why this priority**: Sign-out is the counterpart to sign-in and is essential for session security, especially on shared devices. Without it, travelers cannot securely terminate their sessions.

**Independent Test**: Sign in, verify access to authenticated content, sign out, verify the token is revoked and authenticated content is no longer accessible. Verify other sessions (if any) remain active.

**Acceptance Scenarios**:

1. **Given** a traveler is currently signed in with a valid session token, **When** they click the sign-out button, **Then** the system revokes ONLY their current session token (not all tokens), clears the client-side authentication state, and redirects the traveler to the homepage.
2. **Given** a traveler is signed in on two devices (phone and laptop), **When** they sign out on the phone, **Then** their laptop session remains active and unaffected.
3. **Given** a traveler has signed out, **When** they attempt to access an authenticated page using the revoked token, **Then** the system returns a 401 Unauthorized response and the frontend redirects them to the sign-in page.

---

### User Story 3 - Brute-Force Protection (Priority: P1)

The system protects traveler accounts from brute-force attacks. After 5 consecutive failed sign-in attempts for the same account, the system temporarily locks the account with escalating lockout durations: 1 minute for the first lockout, 5 minutes for the second, and 30 minutes for subsequent lockouts. The lockout counter resets to zero after a successful sign-in.

**Why this priority**: Brute-force protection is a security requirement from the constitution (Security-First mandate). Without it, accounts are vulnerable to credential-stuffing attacks. This must ship alongside sign-in.

**Independent Test**: Attempt to sign in with incorrect passwords 5 times for the same account. Verify the 6th attempt is blocked with a lockout message. Wait for the lockout period to expire, sign in successfully, and verify the counter has reset.

**Acceptance Scenarios**:

1. **Given** a traveler has failed to sign in 4 times consecutively, **When** they submit a 5th incorrect password, **Then** the system records the 5th failure and locks the account for 1 minute. The system displays: "Too many failed attempts. Please try again later." A `LoginFailed` event and an `AccountLockedOut` event are dispatched.
2. **Given** a traveler's account is currently locked, **When** they attempt to sign in (even with correct credentials), **Then** the system rejects the attempt and displays the lockout message with no indication of whether the credentials were correct.
3. **Given** a traveler's account was locked for 1 minute and the lockout has expired, **When** they attempt to sign in with incorrect credentials again and accumulate 5 more failures, **Then** the system locks the account for 5 minutes (the second lockout tier).
4. **Given** a traveler's account was locked at the 5-minute tier, **When** the lockout expires and they accumulate 5 more failures, **Then** the system locks the account for 30 minutes (the maximum lockout tier). All subsequent lockouts remain at 30 minutes.
5. **Given** a traveler's account has been locked and the lockout has expired, **When** they sign in with the correct credentials, **Then** the system resets the failed login counter to zero, clears the lockout state, and signs them in normally. The next lockout (if triggered) would start again at the 1-minute tier.
6. **Given** a traveler has failed 3 times, **When** they sign in successfully on the 4th attempt, **Then** the failed login counter resets to zero. The previous 3 failures are cleared.

---

### User Story 4 - Multi-Language Sign-In (Priority: P2)

The sign-in page is available in all three supported languages (English, Spanish, Italian). All field labels, validation errors, lockout messages, and error messages are displayed in the traveler's selected language.

**Why this priority**: Localization supports the platform's core audience across multiple markets. However, the sign-in flow works correctly in English even without localization, so this is a polish concern rather than a blocker.

**Independent Test**: Switch to each of the three supported languages and attempt the sign-in flow (including triggering validation errors), verifying that all text is correctly translated.

**Acceptance Scenarios**:

1. **Given** a visitor is browsing in Spanish (`/es/`), **When** they navigate to the sign-in page, **Then** all labels ("Correo electrónico", "Contraseña"), placeholders, button text, and error messages are displayed in Spanish.
2. **Given** a visitor submits invalid sign-in credentials while browsing in Italian, **When** the error message is returned, **Then** the error message is displayed in Italian, not in English or a system default.
3. **Given** a visitor triggers account lockout while browsing in Spanish, **When** the lockout message is shown, **Then** the message "Demasiados intentos fallidos. Inténtalo más tarde." is displayed in Spanish.

---

### Edge Cases

- What happens when a traveler submits the sign-in form with leading/trailing whitespace in the email? → The system trims whitespace and normalizes the email to lowercase before credential verification (matching the registration normalization behavior).
- What happens when a traveler who is already signed in navigates to the sign-in page? → The system redirects them away from the sign-in page (guest-only page). They are sent to the locale-prefixed homepage (e.g., `/en/`). The `returnUrl` query parameter is ignored in this scenario.
- What happens when a traveler signs in and their session token is stored but the backend token has been revoked (e.g., by admin)? → The next API call with the revoked token returns 401. The frontend clears the stored authentication state and redirects to the sign-in page with a "session expired" message.
- What happens when the login API endpoint receives a request with no body? → The server returns a 422 Unprocessable Entity with per-field validation errors for both email and password.
- What happens when a traveler is locked out and the Redis cache that stores lockout state is flushed? → Lockout state is stored in the `users` database table (columns: `failed_login_count`, `locked_until`), NOT in Redis cache, so a cache flush does not affect lockout enforcement.
- What happens if two sign-in requests for the same account arrive simultaneously, both with incorrect passwords? → Both increment the failed login counter. Race conditions are acceptable here — worst case is the counter increments twice, which is still correct behavior (the traveler just reaches lockout faster).
- What happens when a traveler's account exists but has no password (created via future guest conversion with incomplete setup)? → The sign-in attempt fails with the same generic "Invalid email or password" error. No distinction is made between missing password and wrong password.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow registered travelers to sign in by providing their email address and password. The sign-in form collects exactly two fields: email and password.
- **FR-002**: Upon successful sign-in, the system MUST issue a Sanctum personal access token named `auth-token` and return it alongside the authenticated user data in the response.
- **FR-003**: Upon successful sign-in, the system MUST update the traveler's `last_login_at` timestamp in the database to the current time.
- **FR-004**: Upon successful sign-in, the system MUST dispatch a `TravelerLoggedIn` event. The existing `LogAuthEvent` listener will handle writing the audit log entry.
- **FR-005**: The system MUST display a generic error message "Invalid email or password" for ALL credential failures — whether the email does not exist, the password is wrong, or the account has no password. This prevents email enumeration.
- **FR-006**: The system MUST validate sign-in input on both the client side (immediate feedback via Zod schema) and the server side (authoritative validation via Form Request). Client-side validation prevents submission of empty fields. Server-side validation is the security authority.
- **FR-007**: The system MUST allow signed-in travelers to sign out by sending a POST request to the logout endpoint with their Bearer token. Signing out revokes ONLY the current session token — other active tokens for the same account remain valid.
- **FR-008**: After successful sign-out, the system MUST clear the client-side authentication state (user and token) and redirect the traveler to the locale-prefixed homepage (e.g., `/en/`).
- **FR-009**: The system MUST implement brute-force protection with the following exact behavior:
  - Track failed consecutive sign-in attempts per account using the `failed_login_count` column on the `users` table.
  - After exactly 5 consecutive failures, lock the account by setting `locked_until` to the current time plus the lockout duration.
  - Lockout durations escalate across lockout tiers: 1st lockout = 1 minute, 2nd lockout = 5 minutes, 3rd and all subsequent lockouts = 30 minutes.
  - While an account is locked (`locked_until` is in the future), ALL sign-in attempts are rejected with the message "Too many failed attempts. Please try again later." — even if the credentials are correct.
  - A successful sign-in after a lockout expires MUST reset `failed_login_count` to 0 and `locked_until` to null. The next lockout (if triggered) starts at the 1-minute tier.
  - Each failed sign-in attempt MUST dispatch a `LoginFailed` event.
  - When a lockout is triggered, the system MUST dispatch an `AccountLockedOut` event.
- **FR-010**: The system MUST redirect the traveler to their original destination page after successful sign-in (return-to-URL behavior). The return URL is passed as a `returnUrl` query parameter. If no return URL is present, the traveler is redirected to the locale-prefixed homepage.
- **FR-011**: The system MUST display all sign-in page content (labels, placeholders, error messages, lockout messages) in the traveler's selected language (English, Spanish, or Italian).
- **FR-012**: The system MUST log all sign-in related events for security auditing: successful sign-ins (`login_success`), failed sign-in attempts (`login_failed`), and account lockouts (`account_locked_out`). Each log entry includes: account identifier (or email for failed attempts), timestamp, IP address, user agent, and event outcome.
- **FR-013**: The system MUST normalize the submitted email before credential verification: trim leading/trailing whitespace and convert to lowercase. This matches the registration normalization behavior.
- **FR-014**: The system MUST apply rate limiting to the sign-in endpoint (maximum 10 requests per minute per IP address) using the existing `throttle:auth` middleware on the auth route group.
- **FR-015**: The sign-in page MUST be a guest-only page. If an already-authenticated traveler navigates the sign-in page, they MUST be redirected to the locale-prefixed homepage (e.g., `/en/`). The `returnUrl` query parameter is ignored in this scenario.

### Key Entities

- **Traveler Account** (existing — no schema changes): The `users` table already contains all columns needed for sign-in: `email`, `password` (hashed), `last_login_at`, `failed_login_count`, `locked_until`, `locale`. No new columns or migrations are required for this feature.
- **Session Token** (existing — Sanctum `personal_access_tokens` table): Represents an active authenticated session. A new token is created on each successful sign-in via `$user->createToken('auth-token')`. Sign-out revokes the specific token used for the request via `$request->user()->currentAccessToken()->delete()`.
- **Audit Log Entry** (existing — `auth_audit_logs` table): Append-only record of sign-in events. The existing `LogAuthEvent` listener handles writing entries when `TravelerLoggedIn`, `LoginFailed`, and `AccountLockedOut` events are dispatched.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Travelers can complete the sign-in flow (from landing on the sign-in page to being signed in and redirected) in under 15 seconds.
- **SC-002**: 100% of valid sign-in submissions result in the traveler being authenticated and receiving a session token within 3 seconds.
- **SC-003**: 100% of failed sign-in attempts display the generic "Invalid email or password" error — never revealing whether the email exists.
- **SC-004**: Brute-force protection activates after exactly 5 consecutive failed sign-in attempts, with lockout durations matching the 1min → 5min → 30min escalation tiers.
- **SC-005**: Sign-out revokes only the current session token. Other active sessions for the same traveler remain functional with a 100% independence rate.
- **SC-006**: 100% of sign-in pages render correctly in all three supported languages (English, Spanish, Italian) with all text (labels, errors, lockout messages) properly translated.
- **SC-007**: All sign-in events (success, failure, lockout) are recorded in the audit log with account identifier, timestamp, IP address, and user agent.
- **SC-008**: Client-side validation catches 100% of common input errors (empty email, empty password) before a server request is made.

## Assumptions

- The foundational infrastructure from Phase 2 (002-foundational-implementation) is complete: database schema (`users` table with `failed_login_count`, `locked_until`, `last_login_at` columns), User model with Sanctum token support, event infrastructure (`TravelerLoggedIn`, `LoginFailed`, `AccountLockedOut` events), `LogAuthEvent` listener, API route scaffolding with `throttle:auth` middleware, frontend `loginSchema` validator, `authApi.login()`/`authApi.logout()` API client methods, and `useAuth` hook with `login`/`logout` methods.
- The `users` table schema already includes all required columns. No new database migrations are needed for this feature.
- All three event classes (`TravelerLoggedIn`, `LoginFailed`, `AccountLockedOut`) already exist in `backend/app/Domains/Auth/Events/`. They do NOT need to be created — they need to be dispatched from the new action classes.
- The `LogAuthEvent` listener already handles all three event types and writes to `auth_audit_logs`. No listener changes are needed.
- The existing `authApi.login()` and `authApi.logout()` methods in `frontend/src/lib/api/auth.ts` are already implemented and functional. The frontend work is creating the LoginForm component and login page that USE these existing methods.
- The existing `useAuth` hook in `frontend/src/lib/hooks/useAuth.tsx` already has `login()` and `logout()` methods. The frontend components should call these methods, not implement their own API calls.
- The existing `loginSchema` in `frontend/src/lib/validators/auth.ts` validates email and password fields. The LoginForm component should use this schema with react-hook-form and zodResolver.
- The registration flow (003-traveler-registration) is complete and serves as a reference implementation pattern for this feature.
- Social login and third-party OAuth are explicitly out of scope.
- "Forgot password?" link appears on the sign-in page but is non-functional — it links to a placeholder URL that will be implemented in spec 005 (Password Reset).
