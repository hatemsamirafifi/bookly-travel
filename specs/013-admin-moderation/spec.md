# Feature Specification: Admin Moderation

**Feature Branch**: `013-admin-moderation`
**Created**: 2026-06-20
**Status**: Draft
**Input**: User description: "phase 5 docs\frontend-implementation-plan.md"
**Plan Reference**: Frontend Implementation Plan, Phase 5 — Admin Moderation (Spec `013`)
**Constitution**: Bookly Constitution v1.1.0 (Principles IV, V, VI; Sections 3, 7; API-First §Internal Admin Exception)

## Clarifications

### Session 2026-06-20

- Q: Admin surface delivery (Filament vs. API-first Next.js admin) → A: Laravel Filament (server-rendered) confirmed as the approved implementation for the admin surface; a documented exception to the constitution's API-first principle applies to the internal admin surface only — the public, traveler, and partner surfaces remain API-first Next.js.
- Q: Financial authority scope (refunds/payouts) → A: Refunds and all financial execution/orchestration stay entirely within Spec 008 (Payments & Finance); Admin Moderation is limited to governance, oversight, and state visibility, and performs no financial execution.
- Q: Admin role granularity → A: A single "admin" role backed by granular per-action permission flags (least-privilege per admin, no named sub-roles for Phase 1).
- Q: Availability oversight scope → A: Read-only oversight; admins view partner availability/slots for support and dispute resolution but cannot create, edit, or delete them (availability is partner-owned).
- Q: Bulk action scope → A: Bulk governance actions apply to tours (bulk approve/reject) and reviews (bulk hide/reinstate) only; bulk actions MUST NOT apply to bookings (state/financial safety) or partners.

## User Scenarios & Testing *(mandatory)*

The admin surface is an internal platform-operations tool used by Bookly staff
to govern the marketplace: approve partners and tours, oversee bookings and
reviews, and operate site content. Actors are **Admins** (platform operators);
secondary actors are **Partners** (whose listings are governed) and
**Travelers** (whose reviews and bookings may be moderated).

### User Story 1 - Admin Access Control (Priority: P1)

An admin signs into a dedicated administration panel. Only users holding the
admin role can reach it; every other visitor (traveler, partner, or anonymous
guest) is blocked. Within the panel, an admin can only perform actions their
assigned permissions allow.

**Why this priority**: Without enforced access control, none of the other
moderation workflows are safe to expose. Authorization is the foundation of
every other story.

**Independent Test**: A non-admin user attempts to open any admin URL and is
denied; an admin user signs in and sees the administration home.

**Acceptance Scenarios**:

1. **Given** an anonymous visitor, **When** they open any administration URL,
   **Then** they are refused access and never see protected data.
2. **Given** a logged-in traveler or partner, **When** they open any
   administration URL, **Then** they are refused access.
3. **Given** a user holding the admin role, **When** they sign in to the
   administration panel, **Then** they reach the administration home and can
   navigate to the resources their permissions grant.
4. **Given** an admin without a specific permission (e.g. approve partners),
   **When** they view the relevant screen, **Then** the disallowed action is
   unavailable to them.

---

### User Story 2 - Tour Moderation & Publishing Approval (Priority: P1)

A partner submits a tour for review. An admin evaluates it and either approves
it (so it becomes visible to the public), rejects it with a reason (sent back
to the partner for revision), or unpublishes a previously published tour. Tour
status moves through `draft`, `pending_review`, `published`, `rejected`, and
`archived`, and only `published` tours appear in public discovery.

**Why this priority**: This is the core marketplace-governance loop and a
constitution mandate (Principle IV — Admin-Governed Publishing). No tour reaches
travelers without admin approval.

**Independent Test**: A partner submits a tour; an admin approves it; the tour
then appears in public search, and the approval action is recorded in the audit
log.

**Acceptance Scenarios**:

1. **Given** a tour in `pending_review`, **When** an admin approves it,
   **Then** its status becomes `published` and it appears in public discovery.
2. **Given** a tour in `pending_review`, **When** an admin rejects it with a
   reason, **Then** its status becomes `rejected`, the reason is recorded, and
   the partner can see the reason and edit for resubmission.
3. **Given** a `published` tour, **When** an admin unpublishes it,
   **Then** it is removed from public discovery and its status change is
   audited.
4. **Given** a tour that does not belong to an approved partner, **When** an
   admin views the moderation queue, **Then** the tour is still reviewable but
   cannot become `published` until its partner is approved.
5. **Given** multiple pending tours, **When** an admin selects several and
   applies a bulk approve action, **Then** each transitions correctly and each
   transition is audited individually.

---

### User Story 3 - Partner Approvals & Lifecycle Management (Priority: P1)

Admins review partner applications and decide whether a partner may list on
Bookly. Admins can approve a partner, reject a partner (with reason), suspend a
partner, or reinstate one. A partner's approval status governs whether their
tours can be published.

**Why this priority**: Partners are the supply side of the marketplace; gating
them is as critical as gating tours and is required for financial/commerce
governance (Principle V).

**Independent Test**: A new partner application is submitted; an admin approves
it; the partner can then have tours published; the approval is audited.

**Acceptance Scenarios**:

1. **Given** a partner application awaiting review, **When** an admin approves
   it, **Then** the partner is marked approved and notified, and their tours
   become eligible for publishing.
2. **Given** an approved partner, **When** an admin suspends them with a
   reason, **Then** their tours stop being publishable and the suspension is
   audited.
3. **Given** a suspended partner, **When** an admin reinstates them,
   **Then** the partner returns to approved status and the change is audited.
4. **Given** a partner application, **When** an admin rejects it with a reason,
   **Then** the partner is marked rejected, the reason is recorded, and the
   action is audited.

---

### User Story 4 - Booking Oversight & Status Management (Priority: P2)

Admins can view all bookings across the marketplace, filter and search them,
inspect booking detail, and transition booking status where policy allows
(e.g. mark completed, cancel). All status transitions and any financial action
produce immutable audit entries.

**Why this priority**: Bookings are the commercial core; oversight protects
travelers, partners, and platform revenue integrity (Principle V — financial
auditability).

**Independent Test**: An admin opens the bookings list, filters by status and
date, opens a booking, transitions its status, and the transition is recorded
in the audit log.

**Acceptance Scenarios**:

1. **Given** the bookings list, **When** an admin filters by status, partner,
   date range, or reference, **Then** only matching bookings are shown.
2. **Given** a booking detail, **When** an admin transitions its status to an
   allowed next state, **Then** the status changes and an audit entry captures
   actor, action, target, and before/after status.
3. **Given** a booking, **When** an admin attempts a disallowed transition
   (e.g. reviving a long-cancelled booking), **Then** the action is blocked.
4. **Given** an admin-initiated booking transition that implies a financial
   side effect (e.g. a cancellation requiring a refund), **When** the
   transition is made, **Then** financial execution is delegated to Spec 008
   and Admin Moderation records only the governance/status transition in its
   audit log.

---

### User Story 5 - Review Moderation (Priority: P2)

Admins moderate traveler reviews: they can hide a review that violates policy
and reinstate a hidden review. Hidden reviews disappear from public tour
detail; reinstated reviews reappear. Partner responses to reviews remain
visible alongside the review.

**Why this priority**: Review integrity protects traveler trust and partner
reputation (Principle VI — partners may respond, admin may moderate).

**Independent Test**: A reported review is hidden by an admin, disappears from
the public tour page, and the moderation action is audited; reinstating it
restores visibility.

**Acceptance Scenarios**:

1. **Given** a published review, **When** an admin hides it, **Then** it no
   longer appears on public tour detail and the aggregate rating recomputes.
2. **Given** a hidden review, **When** an admin reinstates it, **Then** it
   reappears on public tour detail and the aggregate rating recomputes.
3. **Given** a moderation action, **When** it is performed, **Then** an audit
   entry records who hid/reinstated which review and when.
4. **Given** a review queue, **When** an admin filters by status or tour,
   **Then** only matching reviews are shown.

---

### User Story 6 - Audit Trail of All Admin Actions (Priority: P2)

Every administrative action — approval, rejection, suspension, status change,
review moderation, financial change — is captured in an immutable audit log
recording the actor, the action, the target resource, a timestamp, and the
before/after state where applicable. Admins can review this trail.

**Why this priority**: Operational governance and accountability are mandated by
the constitution (Section 7 — Audit Logging & Operational Governance).

**Independent Test**: An admin performs any governed action and an audit entry
appears identifying them, the action, the target, and the state change.

**Acceptance Scenarios**:

1. **Given** any admin governance action, **When** it completes, **Then** an
   audit entry is written with actor, action, target, timestamp, and
   before/after where applicable.
2. **Given** the audit log, **When** an admin filters by actor, action type,
   or target, **Then** only matching entries are shown.
3. **Given** an existing audit entry, **When** anyone attempts to alter it,
   **Then** the entry remains unchanged (append-only).

---

### User Story 7 - Platform Overview Dashboard (Priority: P3)

Admins land on a dashboard summarizing marketplace health: counts of pending
partner approvals, pending tour reviews, recent bookings, revenue, pending
review moderation, and notable alerts. The dashboard links directly into the
relevant queues.

**Why this priority**: Gives admins a single starting point and surfaces
backlogs, but moderation can operate without it.

**Independent Test**: An admin opens the panel and sees current pending counts
and shortcuts into each moderation queue.

**Acceptance Scenarios**:

1. **Given** pending partners and tours, **When** an admin opens the
   dashboard, **Then** the counts reflect the true pending totals.
2. **Given** a dashboard metric tile, **When** an admin selects it,
   **Then** they are taken to the corresponding filtered queue.

---

### User Story 8 - Availability/Slots Oversight (Priority: P3)

Admins can view partner availability and slots marketplace-wide for support and
dispute resolution, in read-only mode; availability remains partner-owned and
admins do not modify slots.

**Why this priority**: Supports operational investigations, but is not a
gating workflow.

**Independent Test**: An admin opens the availability view for a tour and sees
its slots.

**Acceptance Scenarios**:

1. **Given** a tour with availability slots, **When** an admin opens its
   availability, **Then** the slots and their booking state are visible.
2. **Given** availability for a tour, **When** an admin views it,
   **Then** empty, full, and partially-booked states are distinguishable.

---

### User Story 9 - Settings, CMS & Static Pages (Priority: P3)

Admins manage platform settings and static site content (e.g. privacy policy,
terms of service, about, and other marketing/legal pages), including
localized content where applicable.

**Why this priority**: Keeps the public site's legal and marketing content
maintained, but is not part of the core moderation loop.

**Independent Test**: An admin edits a static page and the updated content
appears on the public site.

**Acceptance Scenarios**:

1. **Given** a static page, **When** an admin edits and publishes it,
   **Then** the public site shows the updated content.
2. **Given** platform settings, **When** an admin updates a setting,
   **Then** the new value takes effect for the affected surface and the
   change is audited.

---

### Edge Cases

- An admin approves a tour whose partner is not yet approved — publishing must
  be blocked until the partner is approved.
- A partner is suspended while their tours are `published` — those tours must
  be removed from public discovery.
- Concurrent admins act on the same tour — the system must not allow a
  contradictory double transition and must audit the effective outcome.
- A bulk action partially succeeds (some items already in the target state) —
  the system must report per-item results and audit only actual transitions.
- An admin attempts to unpublish or moderate a resource they lack permission for
  — the action is unavailable, not merely server-rejected after a click.
- A rejected tour is resubmitted after edits — it returns to `pending_review`
  and re-enters the queue.
- An audit entry cannot be edited or deleted (append-only integrity).
- A booking status transition that implies a financial side effect (refund or
  charge) never executes financially from the admin surface; execution is
  delegated to Spec 008, which enforces idempotency and writes the immutable
  ledger. Admin Moderation audits only the status transition.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST restrict the entire administration surface to
  users holding the admin role; all other users MUST be denied.
- **FR-002**: The system MUST enforce a single "admin" role backed by granular
  per-action permission flags, so an admin can only perform the specific
  administrative actions their assigned permissions grant (least-privilege).
- **FR-003**: The system MUST let an admin review tours submitted by partners
  and approve, reject (with reason), or unpublish them.
- **FR-004**: Tour status MUST span `draft`, `pending_review`, `published`,
  `rejected`, and `archived`; only `published` tours MUST appear in public
  discovery.
- **FR-005**: The system MUST prevent a tour from reaching `published` while
  its partner is not approved.
- **FR-006**: The system MUST let an admin approve, reject (with reason),
  suspend, and reinstate partners; suspension MUST remove the partner's tours
  from public discovery.
- **FR-007**: The system MUST notify partners of approval, rejection, and
  suspension decisions (notification delivery is detailed in Spec `014`).
- **FR-008**: The system MUST let an admin list, filter, search, and inspect all
  bookings and transition booking status only to allowed next states.
- **FR-009**: The system MUST NOT execute refunds, charges, payouts, or ledger
  writes from the admin surface; admin-initiated booking transitions that imply
  a financial side effect MUST delegate financial execution to Spec 008
  (Payments & Finance), which owns idempotency and the immutable ledger. Admin
  Moderation audits only the governance/status transition.
- **FR-010**: The system MUST let an admin hide and reinstate reviews; hidden
  reviews MUST disappear from public tour detail and recomputed aggregate
  ratings; reinstated reviews MUST reappear.
- **FR-011**: The system MUST write an immutable audit entry for every
  governance action capturing actor, action, target resource, timestamp, and
  before/after state where applicable.
- **FR-012**: The audit log MUST be append-only; no user may edit or delete
  audit entries.
- **FR-013**: The system MUST provide a platform overview dashboard showing
  pending approvals, pending tour reviews, recent bookings, revenue, and
  pending review moderation, with shortcuts into each queue.
- **FR-014**: The system MUST let an admin view partner availability and slots
  across the marketplace in read-only mode, distinguishing empty, full, and
  partially-booked states. Admins MUST NOT create, edit, or delete partner
  availability slots (availability is partner-owned).
- **FR-015**: The system MUST let an admin manage static site pages (privacy,
  terms, about, and marketing/legal content) and platform settings, with
  localized content where applicable.
- **FR-016**: The system MUST support bulk governance actions for tours (bulk
  approve/reject) and reviews (bulk hide/reinstate), with per-item result
  reporting and per-item audit entries. Bulk actions MUST NOT apply to bookings
  (state/financial safety) or partners.
- **FR-017**: The system MUST provide an administration experience usable on
  desktop (1280px+) covering all admin screens.
- **FR-018**: Each administration screen MUST have a corresponding backing
  resource covering the Stitch admin reference screens ST-013-001 through
  ST-013-013.

### Key Entities *(include if feature involves data)*

- **AdminUser**: A platform operator holding the single "admin" role and a set
  of granular per-action permission flags governing which administrative actions
  they may perform.
- **Partner**: A marketplace seller with an approval/lifecycle status
  (`pending`, `approved`, `rejected`, `suspended`) whose status governs
  whether their tours can be published.
- **Tour**: A listing with a moderation status (`draft`, `pending_review`,
  `published`, `rejected`, `archived`) owned by a partner; only `published`
  tours appear publicly.
- **Booking**: A traveler's reservation with a status lifecycle; subject to
  admin oversight and status transitions, with financial auditability.
- **Review**: A traveler's review of a completed booking, with a moderation
  state (visible/hidden) and an optional partner response.
- **AuditLog**: An append-only record of a governance action capturing actor,
  action, target, timestamp, and before/after state.
- **AvailabilitySlot**: A bookable slot for a tour with a booking state (empty,
  full, partially booked), viewable marketplace-wide by admins.
- **StaticPage / CMSContent**: Editable site content (legal and marketing pages)
  and platform settings, with localized variants where applicable.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Zero non-admin users can reach any administration screen or
  perform any administrative action (100% access denial for unauthorized
  roles).
- **SC-002**: 100% of tour publishing transitions require admin approval; no
  tour reaches public discovery without an approved admin action and an
  approved partner.
- **SC-003**: Every governance action (tour/partner decision, booking
  transition, review moderation, settings change) produces an immutable audit
  entry capturing actor, action, target, and before/after. Financial ledger
  entries for refunds/charges are owned by Spec 008, not Admin Moderation.
- **SC-004**: Admins can clear a typical pending-review backlog (approve or
  reject a tour) in under 30 seconds per item from the queue.
- **SC-005**: Hidden reviews disappear from public tour detail and aggregate
  ratings within seconds; reinstated reviews reappear within seconds.
- **SC-006**: All 13 admin reference screens (ST-013-001…ST-013-013) have
  working administrative counterparts usable on desktop at 1280px+.
- **SC-007**: Bulk governance actions report per-item success/failure and
  audit each actual transition, with zero silent partial failures.
- **SC-008**: Suspension of a partner removes their tours from public discovery
  within seconds and is fully auditable.

## Assumptions

- The administration surface is delivered via Laravel Filament server-rendered
  resources/widgets, per the ratified plan decision D4 ("Keep Laravel Filament;
  use Stitch admin screens as design reference only") and confirmed during
  clarification as the approved implementation. This is the sole ratified
  exception to the constitution's API-first/no-server-rendered-HTML principle
  for the **internal admin surface only**; the public, traveler, and partner
  surfaces remain API-first Next.js. The exception is formally codified in the
  constitution v1.1.0, API-First §Internal Admin Exception — no follow-up note
  is pending.
- Refunds, payouts, charges, and immutable ledger writes are explicitly OUT OF
  SCOPE for Admin Moderation and are owned by Spec `008` (Payments & Finance).
  Admin Moderation is limited to governance, oversight, and state visibility, and
  delegates any financial side effect to the payment domain.
- Partner lifecycle statuses are `pending`, `approved`, `rejected`, and
  `suspended` (in line with the partner approval status UI already
  implemented in Phase 4).
- Notification delivery (partner approved/rejected/suspended emails) is owned
  by Spec `014` (Notifications and Vouchers); this spec depends on that
  capability but does not redefine it.
- Review submission, editing, and the 48-hour edit window are owned by Spec
  `009`; this spec covers only the admin moderation (hide/reinstate) of
  reviews.
- Static/CMS pages managed here are legal and marketing pages (privacy,
  terms, about, etc.); the editorial blog is owned by Spec `016` and is out of
  scope here.
- Audit log storage is append-only; retention period follows the
  platform-wide data-retention policy and is not specified further here.
- Admin authentication reuses the existing Laravel Sanctum-based auth and
  role/permission infrastructure; no new auth mechanism is introduced.
- All admin actions remain subject to the constitution's strict-authorization
  (authentication → role → permission → ownership) and idempotent-financial-
  flows requirements.