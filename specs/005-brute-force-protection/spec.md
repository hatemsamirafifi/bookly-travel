# Feature Specification: Brute-Force Protection for Traveler Sign-In

**Feature Branch**: `005-brute-force-protection`
**Created**: 2026-04-28
**Status**: Draft
**Input**: User description: "phase 5 only specs\004-traveler-signin\tasks.md"
**Parent Feature**: `004-traveler-signin` (Phase 5 — User Story 3)

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Account Lockout After Consecutive Failures (Priority: P1)

A malicious actor attempts to guess a traveler's password by submitting incorrect credentials repeatedly. After 5 consecutive failed sign-in attempts for the same account, the system locks the account for 1 minute, preventing further attempts during that window. The attacker receives a generic lockout message with no indication of whether the credentials they tried were correct.

**Why this priority**: This is the core defense mechanism. Without it, accounts are vulnerable to unlimited credential-stuffing attacks. This is a constitution-mandated security requirement that must ship alongside sign-in.

**Independent Test**: Submit 5 incorrect passwords for the same account, verify the 6th attempt (even with correct credentials) is rejected with a 423 Locked status and the message "Too many failed attempts. Please try again later."

**Acceptance Scenarios**:

1. **Given** a traveler account exists with email "jane@example.com", **When** an attacker submits 5 consecutive incorrect passwords, **Then** on the 5th failure the system triggers an account lockout, sets `locked_until` to 1 minute in the future, dispatches an `AccountLockedOut` event, and returns a 423 status with the message "Too many failed attempts. Please try again later."
2. **Given** a traveler account is currently locked (`locked_until` is in the future), **When** anyone attempts to sign in — even with the correct password — **Then** the system rejects the attempt with a 423 status and the lockout message, without revealing whether the credentials were valid.
3. **Given** a traveler account is locked, **When** the lockout period expires (`locked_until` passes), **Then** sign-in attempts are processed normally again — the system checks `locked_until->isFuture()` on each attempt and proceeds to credential verification once it returns false.

---

### User Story 2 — Escalating Lockout Tiers (Priority: P1)

To deter persistent attackers while minimizing impact on legitimate travelers who forget their password, the system uses escalating lockout durations based on how many times the account has been locked out since the last successful sign-in.

**Why this priority**: Escalation balances security (longer lockouts for repeated attacks) with usability (short lockout for a first-time mistake). This must be correct at launch to avoid both over-penalizing travelers and under-protecting accounts.

**Independent Test**: Trigger a lockout, wait for expiry, trigger another, verify the 2nd lockout is 5 minutes. Trigger a 3rd, verify it is 30 minutes. All subsequent lockouts remain at 30 minutes until a successful sign-in resets the tier.

**Acceptance Scenarios**:

1. **Given** a traveler account has never been locked out before, **When** 5 consecutive failures trigger a lockout, **Then** the lockout duration is exactly 1 minute.
2. **Given** a traveler account was locked once (1-minute tier) and the lockout expired, **When** the attacker triggers another lockout with 5 more consecutive failures, **Then** the lockout duration is exactly 5 minutes.
3. **Given** a traveler account was locked twice (1-minute then 5-minute tiers) and the lockout expired, **When** the attacker triggers a third lockout, **Then** the lockout duration is 30 minutes. All subsequent lockouts — 4th, 5th, etc. — remain at 30 minutes.

---

### User Story 3 — Counter Reset on Successful Sign-In (Priority: P1)

A legitimate traveler who mistyped their password a few times eventually remembers it and signs in correctly before reaching the 5-failure threshold. The system resets the failed attempt counter to zero. If they had previously been locked out and the lockout expired, a successful sign-in also resets the lockout tier back to the beginning (next lockout would be 1 minute).

**Why this priority**: Resetting the counter prevents legitimate travelers from being penalized indefinitely for occasional typos. Without this, every traveler would eventually reach lockout, degrading the sign-in experience.

**Independent Test**: Fail 3 times, succeed on the 4th attempt, verify `failed_login_count` is 0. Trigger a full lockout cycle, wait for expiry, sign in successfully, then trigger another lockout — verify it starts at the 1-minute tier again.

**Acceptance Scenarios**:

1. **Given** a traveler has failed sign-in 3 times consecutively (`failed_login_count = 3`), **When** they sign in successfully on the 4th attempt, **Then** the system resets `failed_login_count` to 0 and `locked_until` to null, and signs them in normally.
2. **Given** a traveler was locked out at the 5-minute tier, the lockout expired, and they sign in successfully, **When** they later trigger another lockout with 5 consecutive failures, **Then** the lockout starts at the 1-minute tier (the tier resets after a successful sign-in).

---

### Edge Cases

- **What happens if two sign-in requests for the same account arrive simultaneously, both with incorrect passwords?** Both requests increment the `failed_login_count`. The worst case is a double-increment (e.g., count goes from 4 to 6 instead of 4 to 5), which is acceptable — the attacker reaches lockout slightly later (at 10 instead of 5), but the account is still protected. No data corruption occurs.
- **What happens to `failed_login_count` when a lockout is triggered?** The counter is NOT reset at lockout time. During the lockout window, no new increments occur because attempts are rejected before credential verification. However, every lockout-rejected attempt is still logged as a `login_failed` audit event with a `rejected_due_to_lockout` flag. After the lockout expires, the counter resumes from its current value, so the next lockout triggers at the next multiple of 5 (e.g., 10, 15).
- **What prevents a "slow-burn" attack where an attacker succeeds occasionally to reset the counter?** The counter only resets on a successful sign-in. If an attacker fails 4 times, guesses correctly on the 5th, and signs in, the counter resets to 0. However, if they fail 4 times and do not succeed, the counter persists indefinitely until a legitimate successful sign-in occurs. There is no time-based decay.
- **What happens to lockout state if the audit log history is purged?** Lockout tiers are determined by counting `account_lockout` events in `auth_audit_logs` since the most recent `login_success` event for that account. If audit logs are purged and no prior lockout history is found, the system defaults to the 1st lockout tier (1 minute). This prevents a log purge from causing incorrect tier escalation to 30 minutes.
- **What happens if a traveler has never signed in successfully (no `login_success` events exist)?** The system counts ALL `account_lockout` events for that account to determine the tier. Since there is no `login_success` to anchor the "since" point, every lockout event in history is counted.
- **What happens when the `locked_until` timestamp passes but the traveler hasn't attempted sign-in yet?** The account is not "auto-unlocked" in the database — `locked_until` remains set but is simply in the past. The next sign-in attempt checks `locked_until->isFuture()` and, finding it false, proceeds to credential verification. A successful sign-in at that point clears both `locked_until` and `failed_login_count`.
- **What happens when a legitimate traveler is locked out and contacts support?** There is no manual unlock mechanism in Phase 1. The traveler receives an email notification when their account is locked out, alerting them to the suspicious activity. The traveler must wait for the lockout to expire. This is acceptable because the maximum lockout is 30 minutes.
- **How does the lockout interact with IP-based rate limiting?** These are independent layers. The `throttle:auth` middleware (10 req/min/IP) provides network-level protection, while the account lockout provides per-account protection. An attacker from a single IP would hit both limits. A distributed attack from multiple IPs would bypass IP rate limiting but still be stopped by per-account lockout.

## Clarifications

### Session 2026-04-28

- **Q**: Should non-existent emails increment `failed_login_count` or be silently ignored with no account tracking? → **A**: Non-existent emails do not increment `failed_login_count`. They return the generic error with no account-specific tracking.
- **Q**: If `failed_login_count` double-increments (e.g., 4 → 6 due to a race condition), should lockout still trigger? → **A**: Lockout triggers on every multiple of 5 (`count > 0 && count % 5 == 0`). A 4 → 6 double-increment simply means lockout at 10 instead of 5.
- **Q**: Does `failed_login_count` reset to 0 when a lockout is triggered, or does it persist so the next lockout triggers at the next multiple of 5? → **A**: Counter persists (no reset at lockout). The next lockout triggers at the next multiple of 5 (e.g., 10, 15).
- **Q**: Should the system send an email notification to the traveler when their account is locked out? → **A**: Yes, send an email notification to the traveler on every `AccountLockedOut` event.
- **Q**: Should sign-in attempts rejected due to lockout be logged in the audit log? → **A**: Yes, log all lockout-rejected attempts as `login_failed` events with a `rejected_due_to_lockout` flag.
- **Q**: When should `failed_login_count` reset to 0? → **A**: Reset on Success Only. The counter resets to 0 exclusively after a successful `login_success` event. It does NOT reset on lockout expiry, session boundaries, or time decay. This prevents "slow-burn" brute-force attacks where an attacker mixes successes and failures across sessions.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST track consecutive failed sign-in attempts per traveler account using a `failed_login_count` counter stored in the `users` database table.
- **FR-002**: The system MUST increment `failed_login_count` by 1 on each failed credential verification where a user exists (wrong password or account with no password). Non-existent emails MUST NOT increment `failed_login_count` — they return the generic error message with no account-specific tracking.
- **FR-003**: The system MUST trigger an account lockout when `failed_login_count` reaches a multiple of 5 (`failed_login_count > 0 && failed_login_count % 5 == 0`). Lockout is enforced by setting `locked_until` on the `users` table to the current time plus the lockout duration.
- **FR-004**: The system MUST implement escalating lockout durations based on the number of prior lockouts since the last successful sign-in:
  - 1st lockout: 1 minute
  - 2nd lockout: 5 minutes
  - 3rd and all subsequent lockouts: 30 minutes
- **FR-005**: The lockout tier MUST be determined by counting `account_lockout` audit log entries since the traveler's most recent `login_success` entry. If no `login_success` entry exists, count all `account_lockout` entries. If no prior lockout entries are found, default to the 1st tier (1 minute).
- **FR-006**: The system MUST reject ALL sign-in attempts for a locked account (`locked_until->isFuture()`) with a 423 Locked HTTP status and the message "Too many failed attempts. Please try again later." — even if the submitted credentials are correct.
- **FR-007**: The system MUST NOT reveal whether credentials were correct or incorrect when rejecting a locked account. The same lockout message is returned regardless of credential validity.
- **FR-008**: The system MUST reset `failed_login_count` to 0 and `locked_until` to null exclusively upon successful sign-in. The counter MUST NOT reset on lockout expiry, session boundaries, or time decay. The lockout tier resets — the next lockout (if triggered) starts at the 1-minute tier.
- **FR-009**: The system MUST dispatch a `LoginFailed` event on every failed credential verification, including the email and user (or null if the user does not exist). Additionally, the system MUST dispatch a `LoginFailed` event for every sign-in attempt rejected due to an active lockout, including a `rejected_due_to_lockout` flag in the event payload.
- **FR-010**: The system MUST dispatch an `AccountLockedOut` event when a lockout is triggered (exactly when `failed_login_count` reaches 5 and the lockout tier is determined).
- **FR-011**: The system MUST store lockout state exclusively in the `users` database table (`failed_login_count` and `locked_until` columns). The lockout mechanism MUST NOT depend on cache — a cache flush must not affect lockout enforcement.
- **FR-012**: The frontend MUST display a user-facing lockout message when the server returns a 423 status. The message MUST be displayed in the traveler's selected language (English, Spanish, or Italian) using the `auth.errors.accountLocked` translation key.
- **FR-013**: The system MUST send an email notification to the traveler when their account is locked out. The notification MUST be triggered by the `AccountLockedOut` event and dispatched as a queued job.

### Key Entities

- **Traveler Account** (`users` table): Contains `failed_login_count` (integer, tracks consecutive failures) and `locked_until` (datetime/null, the lockout expiration timestamp). Both are reset on successful sign-in.
- **Auth Audit Log** (`auth_audit_logs` table): Append-only event log. Entries of type `account_lockout` are counted to determine the lockout tier. Entries of type `login_failed` record individual failures. Entries of type `login_success` serve as the tier-reset anchor point.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The system blocks the 6th consecutive sign-in attempt (the first attempt after lockout) with an "account locked" response — verified by an automated test that submits 5 wrong passwords and asserts the 6th attempt is rejected.
- **SC-002**: Lockout durations match the specified escalation: 1 minute, 5 minutes, and 30 minutes for 1st, 2nd, and 3rd+ lockouts respectively — verified by automated tests using time manipulation.
- **SC-003**: The failed login counter resets to zero after a successful sign-in, both for partial failures (e.g., 3 failures then success) and post-lockout recovery — verified by automated tests.
- **SC-004**: The lockout tier resets to the 1-minute level after a successful sign-in — verified by an automated test that triggers a 5-minute lockout, signs in successfully after expiry, triggers another lockout, and asserts the duration is 1 minute.
- **SC-005**: Lockout state persists reliably even during infrastructure disruptions — lockout enforcement reads directly from the account's stored state and is unaffected by temporary storage layers. Verified by a verification test.
- **SC-006**: Every failed sign-in attempt and every lockout event is recorded in the audit log with account identifier, timestamp, IP address, and user agent — verified by automated tests.
- **SC-007**: The frontend displays the lockout message in the traveler's selected language — verified by translation completeness checks across all three supported languages (English, Spanish, Italian).

## Assumptions

- The sign-in flow (`AuthenticateTravelerAction`) already exists from spec 004 and handles the normal credential verification path. This spec covers the brute-force protection logic embedded within that action.
- The `users` table already has `failed_login_count` (integer, default 0) and `locked_until` (datetime, nullable) columns from the foundational schema (002-foundational-implementation).
- The event infrastructure exists: `LoginFailed` and `AccountLockedOut` event classes, the `LogAuthEvent` listener, and the `auth_audit_logs` table.
- The frontend `AuthApiError` class supports a `code` field so the frontend can distinguish `account_locked` (423) from `invalid_credentials` (422) responses and display the correct translated message.
- The lockout tier is determined dynamically by counting prior lockout events rather than stored as a separate tier column. This avoids schema changes and keeps the tier calculation auditable.
- There is no manual account unlock mechanism for admins in Phase 1. Locked-out travelers must wait for the lockout to expire.
- The "Forgot Password" flow (spec 005, planned separately) does not interact with the lockout mechanism — requesting a password reset does not unlock an account.
