# Feature Specification: Traveler Registration

**Feature Branch**: `003-traveler-registration`
**Created**: 2026-04-18
**Status**: In Review
**Input**: User description: "Implement Phase 3 (User Story 1) from the Traveler Authentication feature — traveler registration with email verification, guest booking linkage, and multi-language support"
**Parent Feature**: `001-traveler-auth` (Phase 3)

## Clarifications

### Session 2026-04-18

- Q: Should the registration form include a password confirmation field? → A: No. The registration form collects only three fields (name, email, password) with no confirmation field. Password strength rules are sufficient. The API contract is updated to remove `password_confirmation` from the register endpoint.

### Session 2026-04-25

- Q: What restrictions should apply to unverified accounts? → A: No restrictions. Verification is advisory in this feature; feature-gating (e.g., blocking unverified users from booking or reviewing) is deferred to each downstream feature spec.
- Q: What should happen when a traveler clicks an expired verification link? → A: Redirect to a dedicated page explaining the link has expired, with a one-click "Resend verification email" button.
- Q: Should the spec status be updated from Draft? → A: Yes, updated to "In Review" — implementation is complete but end-to-end verification (T015) is still pending.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New Visitor Registration (Priority: P1) 🎯 MVP

A new visitor to Bookly wants to create a personal account so they can manage future bookings, access vouchers, and leave reviews. They navigate to the registration page and provide their full name, email address, and a password. The system validates their input, verifies the email is not already in use, and creates the account. The traveler is immediately signed in and redirected to their starting point. A non-blocking verification email is sent in the background.

**Why this priority**: Account creation is the foundational identity action for the platform. Without registration, travelers cannot build a persistent relationship with Bookly, manage bookings, or access personalized features. This is the minimum viable deliverable for the authentication system.

**Independent Test**: Visit the registration page, submit valid credentials (name, email, password), and confirm the user is signed in and redirected. Verify a verification email is queued.

**Acceptance Scenarios**:

1. **Given** a visitor is on the registration page, **When** they submit a valid name, email, and password, **Then** the account is created, the user is signed in, and they are redirected to the page they were previously viewing (or the homepage).
2. **Given** a visitor submits a registration form, **When** the email is already associated with an existing account, **Then** the system displays a clear error message indicating the email is taken and suggests signing in instead.
3. **Given** a visitor submits the registration form, **When** the password does not meet strength requirements (minimum 8 characters, at least one uppercase, one lowercase, and one number), **Then** the system displays specific feedback about what the password is missing.
4. **Given** a visitor submits the registration form, **When** any required field is missing or invalid, **Then** the system highlights the specific fields with validation errors and does not create the account.
5. **Given** a traveler has just registered, **When** the account is created, **Then** a verification email is sent to the provided address in the background, and the traveler can continue using the platform immediately without waiting for verification.

---

### User Story 2 - Guest Booking Linkage on Registration (Priority: P1)

When a traveler registers with an email that was previously used for guest checkout bookings, the system automatically links all those guest bookings to the newly created account. The traveler sees their booking history immediately after registration without any manual intervention.

**Why this priority**: Seamless guest-to-registered transition is critical for the platform's conversion funnel. Travelers who previously booked as guests must not lose their booking history when they create an account.

**Independent Test**: Create one or more guest bookings with a specific email, then register a new account with that same email, and verify all guest bookings are linked to the new account.

**Acceptance Scenarios**:

1. **Given** a guest has made previous bookings using email "jane@example.com", **When** a new visitor registers with the same email, **Then** all guest bookings associated with that email are automatically linked to the new account.
2. **Given** no guest bookings exist for a given email, **When** a visitor registers with that email, **Then** the registration completes successfully with no booking linkage errors.
3. **Given** multiple guest bookings exist under the same email, **When** the visitor registers, **Then** all bookings are linked — not just the most recent one.

---

### User Story 3 - Multi-Language Registration (Priority: P2)

The registration page is available in all three supported languages (English, Spanish, Italian). All field labels, validation errors, and success messages are displayed in the traveler's selected language. The traveler's preferred language is stored with their account.

**Why this priority**: Localization supports the platform's core audience across multiple markets. However, the registration flow works correctly in English even without localization, so this is a polish concern rather than a blocker.

**Independent Test**: Switch to each of the three supported languages and complete the registration flow, verifying that all text is correctly translated.

**Acceptance Scenarios**:

1. **Given** a visitor is browsing in Spanish (`/es/`), **When** they navigate to the registration page, **Then** all labels, placeholders, and error messages are displayed in Spanish.
2. **Given** a visitor registers while browsing in Italian, **When** their account is created, **Then** the account's preferred locale is set to Italian.
3. **Given** a visitor submits invalid registration data while browsing in English, **When** validation errors are returned, **Then** the error messages are displayed in English, not in a system default language.

---

### Edge Cases

- What happens when the registration form is submitted with leading/trailing whitespace in the email? → The system trims whitespace and normalizes the email to lowercase before validation and storage.
- What happens when the verification email cannot be delivered? → The traveler can request a new verification email. Delivery failures are logged but do not prevent account usage.
- What happens if two visitors try to register with the same email simultaneously? → The unique email constraint ensures only one account is created; the second attempt receives a "email taken" error.
- What happens when a guest identity exists for the email but the bookings table is not yet created (spec 007)? → The guest booking linkage gracefully handles the case where no bookings table exists, without errors.
- What happens if the registration request is submitted multiple times rapidly? → Rate limiting (10 requests per minute per IP) prevents abuse. Duplicate submissions after the first successful registration return an "email taken" error.
- What happens when a traveler clicks a verification link that has expired? → The traveler is redirected to a dedicated expiry page explaining the link is no longer valid, with a one-click "Resend verification email" button to generate a fresh link.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow visitors to create a traveler account by providing exactly three fields: full name, email address, and password. No password confirmation field is required.
- **FR-002**: The system MUST validate that email addresses are properly formatted and unique across all accounts.
- **FR-003**: The system MUST enforce password strength requirements: minimum 8 characters, at least one uppercase letter, one lowercase letter, and one number.
- **FR-004**: The system MUST immediately sign in the traveler upon successful registration, issuing a session token.
- **FR-005**: The system MUST redirect the traveler to their original destination page after successful registration (return-to-URL behavior). If no return URL is present, the traveler is redirected to the homepage.
- **FR-006**: The system MUST send a non-blocking verification email to the traveler upon account creation. The email is queued and sent in the background; the traveler is not blocked waiting for delivery. Unverified accounts have no feature restrictions in this spec; verification gating is deferred to downstream feature specs (e.g., booking, reviews).
- **FR-007**: The system MUST automatically link all guest bookings made with the registered email to the newly created account. This linkage occurs during the registration process.
- **FR-008**: The system MUST display specific, user-friendly validation errors for each invalid field (name, email, password).
- **FR-009**: The system MUST display a clear error when the provided email is already associated with an existing account, and suggest signing in instead.
- **FR-010**: The system MUST validate registration input on both the client side (immediate feedback) and the server side (authoritative validation).
- **FR-011**: The system MUST support an optional locale parameter during registration. If provided, it sets the account's preferred language. If omitted, it defaults to English.
- **FR-012**: The system MUST display all registration page content (labels, placeholders, error messages, success messages) in the traveler's selected language (English, Spanish, or Italian).
- **FR-013**: The system MUST log the registration event for security auditing, capturing the account identifier, timestamp, client IP address, and user agent string.
- **FR-014**: The system MUST rate-limit registration requests to prevent abuse (maximum 10 requests per minute per IP address).

### Key Entities

- **Traveler Account**: Represents a registered user. Key attributes: full name, email address (unique), hashed password, preferred language, email verification status, creation date, last sign-in timestamp. In this feature, accounts are created via the registration form.
- **Guest Identity**: A temporary identity created during guest checkout (name, email, phone). Not modified by this feature, but queried during registration to identify bookings eligible for linkage.
- **Verification Email**: A transactional email sent upon registration containing a time-limited signed link for the traveler to confirm ownership of their email address. Verification is advisory in this feature — no account restrictions are imposed for unverified status. Expired links redirect the traveler to a page with a "Resend verification email" button.
- **Audit Log Entry**: An append-only record of the registration event, including user identity, timestamp, IP address, and user agent.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Travelers can complete registration (from landing on the registration page to being signed in) in under 60 seconds.
- **SC-002**: 100% of valid registration submissions result in an account being created and the traveler being signed in within 3 seconds.
- **SC-003**: All guest bookings associated with the registered email are linked to the new account with a 100% linkage rate.
- **SC-004**: Verification emails are queued within 1 second of account creation under normal operating conditions.
- **SC-005**: 100% of registration pages render correctly in all three supported languages (English, Spanish, Italian).
- **SC-006**: The system prioritizes user experience by providing an explicit "email taken" error for duplicate registrations (guiding the user to log in), accepting that this allows email enumeration as a known and intentional tradeoff. **Security Sign-off**: Approved by Security Team Lead (Alex Chen) on 2026-04-18. **Compensating Controls**: To mitigate enumeration risks, the API enforces strict rate-limiting thresholds (max 10 requests per minute per IP via `throttle:auth`), employs user/IP anomaly detection for rapid registration attempts, and triggers automated monitoring/alerting rules on abnormal failure spikes. These controls are enforced at the Laravel middleware layer and monitored via the application's logging infrastructure.
- **SC-007**: Client-side validation catches 100% of common input errors (empty fields, malformed email, weak password) before a server request is made.

## Assumptions

- The foundational infrastructure from Phase 2 (001-traveler-auth) is complete: database schema (users, guest_identities, auth_audit_logs, personal_access_tokens tables), User model with Sanctum token support, event infrastructure, API route scaffolding, frontend validators, API client, and auth hooks.
- The verification email content and template will be created as part of this feature. The email delivery infrastructure (SMTP/mailpit) is already configured.
- Guest bookings may not exist yet (the bookings table is defined in spec 007). The guest booking linkage action must handle the case where the bookings table does not exist without throwing errors.
- The registration endpoint is a public API — no prior authentication is required.
- Phone number is NOT collected during registration (it is only collected during guest checkout).
- Social login and third-party OAuth are explicitly out of scope.
- Account deletion and deactivation flows are out of scope.
