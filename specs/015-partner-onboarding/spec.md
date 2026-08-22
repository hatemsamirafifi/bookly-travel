# Feature Specification: Partner Onboarding

**Feature Branch**: `015-partner-onboarding`  
**Created**: 2026-08-18  
**Status**: Draft  
**Input**: User description: "Spec 015 — Partner Onboarding"  
**Plan Reference**: Frontend Implementation Plan (Phase 6 / Remaining Specs), PRD §10.3, Specification Strategy §6.2  
**Constitution**: Bookly Constitution v1.1.0 (Principles I, IV, V; API-First, Strict Authorization, Mandatory Input Validation, Operational Governance)

## Clarifications

### Session 2026-08-18

Informed decisions recorded as defaults based on the Bookly Constitution and PRD specifications (no `[NEEDS CLARIFICATION]` markers remain):

- **Q**: Organization and staff structure for partner accounts in Phase 1? → **A**: **One account per partner organization.** Phase 1 prohibits multi-staff accounts, sub-user roles, or complex permission trees within a partner entity. One partner organization maps to one primary authenticated partner user account.
- **Q**: What are the onboarding registration entry paths? → **A**: **Two entry paths:** 1) Self-service public registration (`/auth/partner-register`) where a new applicant submits credentials and company/business profile; 2) Admin-initiated direct invitation (from Filament Admin) sending an email with a secure registration link that pre-fills company details.
- **Q**: What are the partner lifecycle states and their transitions? → **A**: Partner states are strictly governed: `pending` (initial application/invitation accepted), `approved` (admin approved, full tour creation and dashboard access unlocked), `rejected` (admin rejected application with mandatory reason), and `suspended` (admin suspended account; all active tours immediately hidden from public search and tour creation blocked).
- **Q**: Approval status for admin-invited partners upon registration completion? → **A**: **Auto-approved.** Completing registration via a valid admin invitation link immediately sets the partner account to `approved` status, granting immediate access to the partner dashboard and tour creation capabilities without requiring a second manual admin approval.
- **Q**: Can a rejected partner re-apply? → **A**: **Yes.** A rejected partner can view the rejection reason on their dashboard/status page, update their submitted profile/company details, and resubmit their application back into the `pending` state for admin review.
- **Q**: Can an unapproved partner create or publish tours? → **A**: **No.** An unapproved or non-approved partner (`pending`, `rejected`, or `suspended`) is hard-blocked from creating new tours, submitting tours for review, or having existing tours published to public discovery.

## User Scenarios & Testing *(mandatory)*

This feature covers **partner registration, profile verification, lifecycle status management, and dashboard onboarding** for the Bookly marketplace. Primary actors are **Prospective / Registered Partners** (applying, updating business profile, managing company settings) and **Admins** (inviting, reviewing, approving, rejecting, suspending, and reinstating partner accounts).

### User Story 1 - Partner Self-Registration & Application Submission (Priority: P1)

A tour operator or local guide visits the Bookly website, navigates to the partner registration page, and submits their company profile, contact details, business description, and operating credentials. Upon successful submission, a partner account is created in `pending` status, the applicant is authenticated into an onboarding status view informing them their application is under review, and platform admins receive an in-app operational notification (via the existing Notification model mechanism) informing them a new application requires review.

**Why this priority**: Self-service onboarding is the primary mechanism for marketplace supply acquisition (Constitution Principle I: Marketplace-First). Without registration, new operators cannot join Bookly.

**Independent Test**: An operator visits the registration flow, submits valid business information, gets redirected to the pending onboarding screen, and verifies that the backend partner record exists with status `pending`.

**Acceptance Scenarios**:

1. **Given** an unauthenticated visitor on the partner registration page, **When** they submit valid user credentials (name, email, password) and partner business details (company name, business email, phone, website URL, operational country/city, and company description), **Then** their account is created with partner role, a partner profile is created with `pending` status, and they are logged in and redirected to their pending onboarding page.
2. **Given** an applicant attempting to register with an already registered email address, **When** they submit the form, **Then** the submission is rejected with a clear validation error without creating duplicate records.
3. **Given** an applicant submitting incomplete required fields (e.g. missing company name or invalid phone format), **When** they submit, **Then** localized client and server-side validation errors are displayed.
4. **Given** a successful partner self-registration, **When** the application is submitted, **Then** all platform admins with `manage_partners` permission receive an in-app operational notification (via the Notification model) containing the applicant's company name and contact email, distinct from the partner-facing transactional lifecycle emails dispatched under FR-011.

---

### User Story 2 - Admin Review, Approval & Gating Enforcement (Priority: P1)

Platform administrators review pending partner applications via internal admin tooling. The admin inspects business details, verified contact information, and operating regions. The admin can approve the application, which transitions the partner to `approved` status, sends a congratulatory onboarding email, and unlocks full tour management capabilities in the partner dashboard. Alternatively, the admin can reject the application with a mandatory written reason.

**Why this priority**: Constitution Principle IV (Admin-Governed Publishing) requires that all supply is vetted before listing or publishing. Gating prevents unverified entities from publishing inventory.

**Independent Test**: An admin approves a pending partner; the partner logs in and gains full access to create and manage tours; an unapproved partner attempting to create tours receives an authorization error.

**Acceptance Scenarios**:

1. **Given** a partner in `pending` status, **When** an admin approves their application, **Then** the partner status transitions to `approved`, an approval email is dispatched, and the partner's dashboard unlocks tour creation.
2. **Given** a partner in `pending` status, **When** an admin rejects their application providing a rejection reason, **Then** the partner status transitions to `rejected`, the reason is recorded, an email notification with the explanation is dispatched to the partner, and tour creation remains blocked.
3. **Given** a partner in `pending` or `rejected` status, **When** they attempt to access tour creation or submit a tour, **Then** the system denies the request with an explicit access restriction message.

---

### User Story 3 - Partner Profile Management & Re-Application (Priority: P2)

An approved partner can update their business profile, public company description, contact details, logo/avatar, and payout business settings from their partner dashboard account settings. A rejected partner can view the administrator's feedback, edit their application details to address the feedback, and submit their application for re-evaluation.

**Why this priority**: Keeps partner directory information accurate and provides a fair, transparent pathway for rejected applicants to correct deficiencies and gain approval.

**Independent Test**: A rejected partner logs in, views the rejection feedback, updates their company description and contact details, and resubmits; their status returns to `pending` and appears in the admin review queue.

**Acceptance Scenarios**:

1. **Given** an approved partner on their account settings page, **When** they update their company description, contact numbers, and business address, **Then** the updated information persists and immediately reflects on their profile.
2. **Given** a rejected partner viewing their status dashboard, **When** they view the rejection notes, adjust their company details, and click "Resubmit Application", **Then** their status transitions to `pending` and an audit entry is created.

---

### User Story 4 - Admin Partner Invitation Flow (Priority: P2)

An administrator can proactively invite high-quality tour operators by entering their business name, contact person, email, and target destination from the admin panel. The system generates a unique, secure invitation token and sends an invitation email. The recipient clicks the link, which opens a dedicated onboarding screen pre-filled with their details, allows them to set their password and complete their profile, and immediately enrolls them.

**Why this priority**: Enables proactive supply curation and targeted acquisition of premier tour operators by platform operators.

**Independent Test**: An admin creates an invitation for an operator; the operator receives an email, opens the secure registration link, sets a password and submits details, and is successfully activated.

**Acceptance Scenarios**:

1. **Given** an administrator in the admin panel, **When** they submit a partner invitation with partner email and company name, **Then** a cryptographically secure invitation token is generated and an invitation email is sent.
2. **Given** an invited partner clicking a valid invitation link, **When** they complete their password and confirm profile information, **Then** their partner account is activated directly in `approved` status, granting immediate access to the partner dashboard and tour creation tools.
3. **Given** an invited partner attempting to use an expired or already used invitation link, **When** they access the link, **Then** they are presented with an explanatory message and redirected to the standard registration form.

---

### User Story 5 - Partner Suspension & Account State Governance (Priority: P3)

When a partner violates marketplace quality or safety guidelines, an administrator can suspend the partner account with a recorded justification. When suspended, the partner is immediately blocked from logging into private operational tools or creating tours, and all of the partner's existing tours are hidden from public search and booking. If the issue is resolved, the administrator can reinstate the partner to `approved` status, restoring listing visibility.

**Why this priority**: Essential operational defense and quality control to protect travelers and platform integrity.

**Independent Test**: An admin suspends an approved partner; all associated tours disappear from search results and the partner's write actions are blocked; reinstating restores normal operations.

**Acceptance Scenarios**:

1. **Given** an approved partner with published tours, **When** an admin suspends the partner with a reason, **Then** partner status becomes `suspended`, all their published tours are immediately delisted from public search, and the action is recorded in the operational audit log.
2. **Given** a suspended partner, **When** an admin reinstates them, **Then** partner status returns to `approved`, eligible tours are restored, and the partner is notified.

---

### Edge Cases

- **Concurrent Registration Attempts**: Duplicate submissions using identical email or tax identifier simultaneously are caught by uniqueness constraints and return structured 422 responses.
- **Session Expiration During Application**: Incomplete registration data entered in multi-step onboarding is preserved in client state so users do not lose their input on validation or network hiccup.
- **Account State Tampering**: Direct API requests attempting to mutate `onboarding_status` from non-admin authenticated sessions are rejected with strict 403 Forbidden responses.
- **Deleted / Inactive Users**: If a partner user account is disabled or suspended, all associated partner endpoints and dashboard accesses immediately terminate active sessions. An already-issued Sanctum Bearer token remaining in the client's possession after suspension MUST be rejected on the next request to any protected partner endpoint, enforcing the authorization boundary via current user/partner status checks rather than physical token deletion.
- **Rejection Reason Sanitization**: Administrator rejection and suspension reasons are stored as their original text for audit and history integrity. When rendered in email Blade views or any frontend surface, these values MUST be output-escaped (using Blade `{{ $reason }}` syntax, not raw `{!! $reason !!}`) to prevent script injection while preserving clear formatting for the partner applicant.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a public partner registration interface allowing prospective operators to submit user credentials and business entity information.
- **FR-002**: System MUST validate all partner registration fields server-side, enforcing unique user emails, valid phone number formats, and complete business addresses.
- **FR-003**: System MUST initialize new self-registered partners in `pending` onboarding status.
- **FR-004**: System MUST restrict partners in `pending`, `rejected`, or `suspended` status from creating, editing, submitting, or publishing tours.
- **FR-005**: System MUST provide administrative actions to approve, reject (with mandatory reason), suspend (with mandatory reason), and reinstate partner accounts.
- **FR-006**: System MUST record immutable audit log entries for all partner lifecycle transitions (`partner.approve`, `partner.reject`, `partner.suspend`, `partner.reinstate`, `partner.resubmit`).
- **FR-007**: System MUST automatically hide all published tours belonging to a partner from public search and booking if that partner's status changes to `suspended` or `rejected`.
- **FR-008**: System MUST allow rejected partners to view administrator rejection notes and submit an updated application that transitions their status back to `pending`.
- **FR-009**: System MUST allow administrators to issue time-limited, single-use partner registration invitations via email, and successfully completed invitation registrations MUST activate directly in `approved` status.
- **FR-010**: System MUST enforce strict ownership boundaries so authenticated partners can only view and modify their own organization profile, settings, and tour data.
- **FR-011**: System MUST dispatch localized transactional email notifications for key partner lifecycle milestones (Application Received, Application Approved, Application Rejected, Invitation Received, Account Suspended, and Account Reinstated).
- **FR-012**: System MUST enforce a single-account model per partner organization in Phase 1 without multi-user role assignments within the partner organization.
- **FR-013**: System MUST deliver an in-app operational notification to all platform admins with `manage_partners` permission when a new partner self-registration application is submitted, via the existing Notification model mechanism. This admin-facing in-app notification is distinct from the partner-facing transactional lifecycle emails governed by FR-011 and does not dispatch an email to admins.

### Key Entities *(include if feature involves data)*

- **Partner**: Represents the registered tour operator or supplier entity. Attributes include company name, legal/business name, description, contact email, phone, address, country, city, website URL (nullable; social media links are out of scope for Spec 015 / Phase 1 and may be added in a future enhancement), logo/avatar URL, `onboarding_status` (`pending`, `approved`, `rejected`, `suspended`), rejection reason, and lifecycle timestamps. Partner lifecycle timestamps (approval, rejection, suspension, reinstatement) are derived from the immutable GovernanceAuditLog rather than stored as duplicate timestamp columns on the partner record — the audit log is the authoritative lifecycle history.
- **PartnerInvitation**: Represents an administrative invitation extended to a prospective partner. Attributes include recipient email, company name, contact person (optional), secure token, expiration timestamp, consumed timestamp, status (`pending`, `consumed`, `expired`), partner reference (`partner_id`, set on consumption), and issuing admin reference. (The Key Entities list is descriptive and does not enumerate every persistence-level column; see data-model.md §2.1 for the full schema.)
- **PartnerAuditLog**: Morph-mapped operational governance log recording state changes, issuing actor (admin/partner), previous state, new state, transition reason, and timestamp.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Prospective partners can complete and submit the full onboarding registration form in under 3 minutes. *(Note: SC-001 is a UX/product target measured through the simplicity of the registration flow — single-page form, client-side validation, and streamlined fields. It is not a deterministic backend performance SLA and does not require an automated timing assertion. An automated Playwright "under 3 minutes" timing test is out of scope for Spec 015.)*
- **SC-002**: 100% of unapproved partners (`pending`, `rejected`, `suspended`) are successfully blocked from creating tours or publishing inventory.
- **SC-003**: 100% of partner status changes generate immutable audit log entries and appropriate transactional email notifications within 5 seconds of the transition.
- **SC-004**: When an admin suspends a partner, 100% of that partner's active tours are removed from public discovery instantly (zero-latency cache invalidation/indexing update).
- **SC-005**: Rejected partners can review rejection feedback and resubmit their corrected application in a single unified workflow without requiring administrative database intervention.

## Assumptions

- **Single-Staff Scope**: Phase 1 adheres strictly to one account per partner organization. Multi-user teams, granular partner roles, and staff permissions are deferred to future milestones.
- **Automated Payouts Deferred**: Partner financial payout configuration and automated Stripe Connect onboarding are handled in dedicated finance milestones; onboarding collects standard business and tax identifiers without blocking on live banking verification in Phase 1.
- **Localized UI**: Partner registration and dashboard interfaces support platform locales (EN, ES, IT) with English as the fallback.
- **Admin Tooling Surface**: In accordance with the Bookly Constitution Internal Admin Exception, admin partner moderation is managed via Laravel Filament, while traveler and partner surfaces are API-First Next.js applications.
- **Website Only (No Social Links)**: Phase 1 captures a single website URL per partner profile. Social media links (Facebook, Instagram, X, etc.) are out of scope for Spec 015 and may be added in a future enhancement. No `social_links` column or related UI is introduced.
