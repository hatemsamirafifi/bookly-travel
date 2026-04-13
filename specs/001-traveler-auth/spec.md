# Feature Specification: Traveler Authentication

**Feature Branch**: `001-traveler-auth`
**Created**: 2026-04-13
**Status**: Draft
**Input**: User description: "Traveler account and authentication system for Bookly marketplace — registration, login, session management, guest checkout identity, password reset, and automatic account creation from guest bookings"

## Clarifications

### Session 2026-04-13

- Q: What are the specific brute-force lockout durations and reset behavior? → A: Escalating lockout of 1 minute → 5 minutes → 30 minutes after 5 consecutive failed attempts. Lockout resets after a successful sign-in.
- Q: Should email verification be required at registration? → A: Non-blocking verification. A verification email is sent upon registration, but the traveler can use the platform immediately. Unverified accounts are flagged. Password reset is restricted to verified emails only.
- Q: Which authentication events should be logged for security auditing? → A: Standard set — sign-ins (success and failure), password resets, account lockouts, account creation, and email verification events.
- Q: How long should unconverted guest identity records be retained? → A: 24 months after the last booking. After that, guest identity data is anonymized but booking records are retained with anonymized contact information.
- Q: Can signed-in travelers change their password without using the email reset flow? → A: Yes. Travelers can change their password from account settings by providing their current password for confirmation. This is separate from the forgot-password reset flow.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Traveler Registration (Priority: P1)

A new visitor to Bookly wants to create a personal account so they can manage future bookings, access vouchers, and leave reviews. They navigate to the registration page and provide their full name, email address, and a password. The system validates their input, verifies the email is not already in use, and creates the account. The traveler is immediately signed in and redirected to their starting point.

**Why this priority**: Account creation is the foundational identity action for the platform. Without registration, travelers cannot build a persistent relationship with Bookly, manage bookings, or access personalized features.

**Independent Test**: Can be fully tested by visiting the registration page, submitting valid credentials, and confirming the user is signed in with access to account features.

**Acceptance Scenarios**:

1. **Given** a visitor is on the registration page, **When** they submit a valid name, email, and password, **Then** the account is created, the user is signed in, and they are redirected to the page they were previously viewing (or the homepage).
2. **Given** a visitor submits a registration form, **When** the email is already associated with an existing account, **Then** the system displays a clear error message indicating the email is taken and suggests signing in instead.
3. **Given** a visitor submits the registration form, **When** the password does not meet strength requirements, **Then** the system displays specific feedback about what the password is missing.
4. **Given** a visitor submits the registration form, **When** any required field is missing or invalid, **Then** the system highlights the specific fields with validation errors and does not create the account.
5. **Given** a traveler has just registered, **When** the account is created, **Then** a verification email is sent to the provided address, and the traveler can continue using the platform immediately without waiting for verification.

---

### User Story 2 - Traveler Sign-In and Sign-Out (Priority: P1)

A returning traveler wants to sign into their existing account to view upcoming bookings, download vouchers, or leave reviews. They enter their email and password on the sign-in page. Upon successful authentication, they are returned to where they were browsing. When finished, they can sign out to end their session securely.

**Why this priority**: Sign-in/sign-out is equally critical to registration — it is the gateway to all authenticated features and must work reliably for every returning user.

**Independent Test**: Can be fully tested by signing in with valid credentials, verifying access to authenticated pages, signing out, and confirming authenticated pages are no longer accessible.

**Acceptance Scenarios**:

1. **Given** a traveler has a registered account, **When** they enter their correct email and password, **Then** they are signed in and redirected to their previous page or the homepage.
2. **Given** a traveler enters incorrect credentials, **When** they submit the sign-in form, **Then** the system displays a generic error (e.g., "Invalid email or password") without revealing whether the email or password was wrong.
3. **Given** a traveler is signed in, **When** they click sign out, **Then** their session is terminated and they are redirected to the homepage.
4. **Given** a traveler's session has expired due to inactivity, **When** they attempt to access an authenticated page, **Then** they are redirected to the sign-in page with a message indicating their session has expired.
5. **Given** a traveler has failed to sign in 5 times consecutively, **When** they attempt another sign-in, **Then** the system blocks further attempts for 1 minute (escalating to 5 minutes, then 30 minutes on continued failures) and displays a message advising to wait or reset their password. The lockout resets upon a successful sign-in.

---

### User Story 3 - Guest Checkout Identity (Priority: P1)

A traveler wants to book a tour without creating an account. During checkout, they provide their name, email, and phone number. The system captures this information to associate with the booking. The traveler does not need to set a password or go through a registration flow to complete their purchase.

**Why this priority**: Guest checkout is a binding Phase 1 decision. Removing friction from the purchase flow directly impacts conversion rates. This must work seamlessly alongside the registered account system.

**Independent Test**: Can be fully tested by completing a checkout as a guest, verifying the booking is created and associated with the provided email, and confirming no password or account activation was required.

**Acceptance Scenarios**:

1. **Given** a visitor is not signed in, **When** they proceed to checkout, **Then** they are prompted for name, email, and phone — but NOT required to create an account or set a password.
2. **Given** a guest provides an email during checkout, **When** the email is not associated with any existing account, **Then** the booking is created and linked to the guest's email for future reference.
3. **Given** a guest provides an email during checkout, **When** the email is already associated with a registered account, **Then** the system prompts the traveler to sign in to link the booking to their existing account, but still allows proceeding as a guest.
4. **Given** a guest has completed a booking, **When** multiple bookings use the same guest email, **Then** all bookings are associated with that email and will be linked to an account if one is later created.

---

### User Story 4 - Automatic Account Creation After Guest Booking (Priority: P2)

After completing a booking as a guest, the traveler is offered the opportunity to create an account using the information they already provided. They only need to set a password. This converts a one-time guest into a returning user with full account capabilities.

**Why this priority**: While guest checkout must work without account creation, converting guests into registered users increases retention and lifetime value. This is a natural extension of the guest flow but not required for the core booking to succeed.

**Independent Test**: Can be tested by completing a guest booking, accepting the account creation offer, setting a password, and verifying the new account has the guest's bookings linked.

**Acceptance Scenarios**:

1. **Given** a guest has just completed a booking, **When** they are shown the confirmation page, **Then** they see a prominent offer to "Create an account" with their name and email pre-filled.
2. **Given** a guest accepts the account creation offer, **When** they set a password that meets requirements, **Then** an account is created, all their previous guest bookings (by email) are linked to it, and they are signed in.
3. **Given** a guest declines the account creation offer, **When** they dismiss or navigate away, **Then** no account is created, and the booking remains accessible via the booking reference and guest email.
4. **Given** a guest tries to create an account, **When** the email is already registered, **Then** the system informs them that an account already exists and offers to sign in instead — their guest booking will be linked upon sign-in.

---

### User Story 5 - Password Reset (Priority: P2)

A traveler has forgotten their password and needs to regain access to their account. They request a password reset by providing their email address. The system sends a time-limited reset link. The traveler clicks the link, sets a new password, and can sign in with their new credentials.

**Why this priority**: Password reset is essential for account recovery but is a secondary flow that supports the primary sign-in journey.

**Independent Test**: Can be tested by requesting a reset, receiving the email, clicking the link, setting a new password, and verifying sign-in works with the new password.

**Acceptance Scenarios**:

1. **Given** a traveler is on the sign-in page, **When** they click "Forgot password" and enter their registered email, **Then** the system sends a password reset email and displays a confirmation message.
2. **Given** a traveler receives a reset email, **When** they click the reset link within the validity period, **Then** they are taken to a form where they can set a new password.
3. **Given** a traveler has a reset link, **When** they click it after the link has expired (e.g., after 60 minutes), **Then** the system displays a message that the link has expired and offers to request a new one.
4. **Given** a traveler requests a password reset, **When** the email is not associated with any account, **Then** the system displays the same confirmation message (to prevent email enumeration) but does not send an email.
5. **Given** a traveler successfully resets their password, **When** they try to use any previously issued reset links, **Then** those links are invalidated and cannot be used.

---

### User Story 6 - Session Management (Priority: P3)

A signed-in traveler expects their session to persist for a reasonable duration so they don't need to sign in repeatedly. However, for security, sessions should expire after prolonged inactivity. The traveler should be informed when their session expires and guided to sign in again.

**Why this priority**: Session management is a background concern that affects user experience but does not represent a standalone user journey. It supports the sign-in flow and is primarily a security and convenience feature.

**Independent Test**: Can be tested by signing in, remaining idle beyond the session timeout, and verifying the system requires re-authentication.

**Acceptance Scenarios**:

1. **Given** a traveler is signed in, **When** they actively use the platform within the session validity period, **Then** their session remains active and is automatically extended.
2. **Given** a traveler is signed in, **When** they are inactive beyond the session timeout threshold, **Then** their session expires and they must sign in again upon their next action.
3. **Given** a traveler has an active session, **When** they sign out on one device, **Then** sessions on other devices remain unaffected (each session is independent).
4. **Given** a signed-in traveler navigates to account settings, **When** they enter their current password and a valid new password, **Then** their password is updated and they remain signed in. All other active sessions for that account are preserved.

---

### Edge Cases

- What happens when a traveler tries to register with an email that was used for guest checkout but never converted to an account? → The account is created and all bookings associated with that guest email are automatically linked to the new account.
- How does the system handle concurrent sign-in attempts from multiple devices? → Each device receives its own independent session. Multiple concurrent sessions are allowed.
- What happens if the password reset email fails to deliver? → The traveler can request another reset email. Delivery failures are logged but the system does not reveal whether delivery succeeded to prevent enumeration.
- What happens when a traveler changes their email address on their profile? → The old email is released and the new email must not be in use by another account. A verification step confirms ownership of the new email before the change takes effect.
- What happens if a guest books with email A, then later registers with email B? → The guest bookings under email A are not linked to the email B account. If the traveler later claims email A (via profile update), bookings can be linked.
- How is the sign-in form protected against automated brute-force attacks? → After 5 consecutive failed sign-in attempts for a given account, further attempts are temporarily blocked (progressive delay of 1 min → 5 min → 30 min). Rate limiting is applied per IP address.
- What authentication events are logged? → The system logs successful sign-ins, failed sign-in attempts, password resets, account lockouts, account creation, and email verification events for security auditing and operational monitoring.
- What happens to unconverted guest data after a long period? → Guest identity records are anonymized 24 months after the last booking. Booking records are retained with anonymized contact fields. Guests with future bookings are never anonymized.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow visitors to create a traveler account by providing a full name, email address, and password.
- **FR-002**: The system MUST validate that email addresses are properly formatted and unique across all accounts.
- **FR-003**: The system MUST enforce password strength requirements: minimum 8 characters, at least one uppercase letter, one lowercase letter, and one number.
- **FR-004**: The system MUST allow registered travelers to sign in using their email and password.
- **FR-005**: The system MUST allow signed-in travelers to sign out, terminating their current session.
- **FR-006**: The system MUST support guest checkout — travelers MUST be able to proceed through booking without creating an account, providing only name, email, and phone number.
- **FR-007**: The system MUST offer automatic account creation after a guest completes a booking, requiring only a password to be set (name and email are pre-filled from the booking).
- **FR-008**: When a guest creates an account (via FR-007 or standard registration), the system MUST automatically link all previous bookings made with that email to the new account.
- **FR-009**: The system MUST provide a password reset flow: the traveler enters their email, receives a time-limited reset link (valid for 60 minutes), and can set a new password via that link. Password reset emails MUST only be sent to verified email addresses.
- **FR-010**: The system MUST NOT reveal whether an email address is registered during the password reset process (to prevent email enumeration).
- **FR-011**: The system MUST invalidate all outstanding password reset links when a password is successfully changed.
- **FR-012**: The system MUST maintain active sessions for signed-in travelers, extending the session with each user interaction and expiring the session after a configurable period of inactivity (default: 7 days).
- **FR-013**: The system MUST allow multiple concurrent sessions per traveler (e.g., signed in on phone and laptop simultaneously).
- **FR-014**: The system MUST implement progressive sign-in attempt blocking: after 5 consecutive failed attempts for a given account, block further attempts with escalating delays of 1 minute, then 5 minutes, then 30 minutes on continued failures. The lockout counter MUST reset to zero after a successful sign-in.
- **FR-015**: The system MUST display localized, clear error messages for all authentication failures (invalid credentials, expired session, blocked account, validation errors). All error messages MUST be mapped from API error codes to translation keys defined in the i18n locale files.
- **FR-016**: The system MUST support the platform's three languages (English, Spanish, Italian) for all authentication pages and error messages.
- **FR-017**: The system MUST redirect travelers to their original destination after successful sign-in or registration (return-to-URL behavior).
- **FR-018**: The system MUST use email as the unique account identifier. No two accounts may share the same email address.
- **FR-019**: The system MUST NOT support social login, OAuth, or third-party authentication providers in this phase.
- **FR-020**: The system MUST ensure that sign-in error messages are generic (e.g., "Invalid email or password") to prevent revealing whether an email is registered.
- **FR-021**: The system MUST send a verification email upon account creation (both standard registration and guest-to-account conversion). The traveler MAY use the platform immediately without verifying.
- **FR-022**: The system MUST mark accounts as "unverified" until the traveler confirms their email via the verification link.
- **FR-023**: The system MUST restrict password reset functionality to verified email addresses only. Unverified accounts requesting a password reset MUST receive a verification email instead.
- **FR-024**: The system MUST allow travelers to request a new verification email if the original was not received or has expired.
- **FR-025**: The system MUST log the following authentication events for security auditing: successful sign-ins, failed sign-in attempts, password reset requests, password changes, account lockouts (brute-force protection triggers), account creation (both standard and guest conversion), and email verification completions. Each log entry MUST include the associated account identifier, timestamp, and event outcome.
- **FR-026**: The system MUST anonymize unconverted guest identity records 24 months after the guest's last booking. Anonymization MUST remove personally identifiable information (name, email, phone) from the guest identity while retaining the associated booking records with anonymized contact fields. Guest identities linked to bookings with future tour dates MUST NOT be anonymized regardless of age.
- **FR-027**: The system MUST allow signed-in travelers to change their password from their account settings by providing their current password and a new password that meets strength requirements. This is distinct from the forgot-password reset flow (FR-009) and does not require an email link.

### Key Entities

- **Traveler Account**: Represents a registered user on the platform. Key attributes include: full name, email address (unique identifier), hashed password, preferred language, email verification status (verified/unverified), account creation date, and last sign-in timestamp. An account may originate from standard registration or from guest-to-account conversion.
- **Guest Identity**: A temporary identity created during guest checkout, consisting of name, email, and phone number. Not a full account — no password, no sign-in capability. Exists solely to associate bookings with a contact. Converts to a Traveler Account when the guest sets a password. Retention lifecycle: retained for 24 months after the last associated booking, then anonymized (PII removed, booking records preserved).
- **Session**: Represents an active authenticated connection between a traveler and the platform. Key attributes include: associated account, creation time, last activity time, and expiration rules. A traveler may have multiple concurrent sessions.
- **Password Reset Token**: A single-use, time-limited token that authorizes a password change. Key attributes: associated account email, creation time, expiration time (60 minutes), and usage status. Invalidated upon use or when a new token is issued.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Travelers can complete registration (from landing on the registration page to being signed in) in under 60 seconds.
- **SC-002**: Travelers can sign in to an existing account in under 15 seconds.
- **SC-003**: 95% of guest-to-account conversions complete successfully when the traveler accepts the offer and sets a valid password.
- **SC-004**: Password reset emails are received by the traveler within 2 minutes of request (under normal operating conditions).
- **SC-005**: The password reset flow (request to successful password change) can be completed in under 3 minutes.
- **SC-006**: 100% of authentication pages render correctly in all three supported languages (English, Spanish, Italian).
- **SC-007**: Zero instances of email enumeration are possible through any authentication endpoint (sign-in, registration error messages, password reset).
- **SC-008**: Brute-force protection activates after exactly 5 consecutive failed sign-in attempts and prevents further attempts for the configured lockout duration.
- **SC-009**: All guest bookings made with a given email are correctly linked to the account when a traveler registers or converts from guest, with a 100% linkage rate.
- **SC-010**: Session expiration occurs correctly after the configured inactivity period, with the traveler prompted to sign in again upon their next interaction.

## Assumptions

- Travelers have access to a valid, deliverable email address for registration and password recovery.
- Travelers use modern web browsers that support current web standards (session storage, secure cookies).
- The platform has an operational email delivery system capable of sending transactional emails (confirmation, password reset).
- Guest checkout is always available — there is no option to require account creation before booking.
- A non-blocking email verification flow is included in Phase 1. Travelers receive a verification email upon registration but can use the platform immediately. Password reset is restricted to verified emails to prevent abuse.
- Social login (Google, Facebook, Apple, etc.) and third-party authentication are explicitly out of scope for Phase 1 and will not be implemented.
- The password reset link validity period of 60 minutes is a reasonable default for user convenience and security balance.
- Session inactivity timeout of 7 days is a reasonable default for a consumer-facing travel platform where users may browse intermittently.
- Phone number is collected during guest checkout (for booking contact purposes) but is NOT required for account registration.
- Account deletion/deactivation flows are out of scope for Phase 1.
- Email address changes (updating the email on an existing account) are out of scope for Phase 1. The edge case is documented for awareness but will be implemented in a future feature.
