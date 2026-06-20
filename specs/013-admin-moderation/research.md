# Research: Admin Moderation

## Phase 0 — Research Output

**Feature**: Admin Moderation (Spec 013)
**Date**: 2026-06-20
**Branch**: `013-admin-moderation`

---

## 0. Current-State Summary (from codebase exploration)

The admin surface already has substantial Filament scaffolding. This plan
builds on it rather than replacing it.

**Implemented (Filament)**:
- `AdminPanelProvider` (`/admin`, `canAccessPanel()` ⇒ `role === 'admin'`),
  three nav groups (Platform, Operations, Monitoring).
- `TourResource` — list/view, `publish`/`reject`/`unpublish` actions,
  `bulk_publish`, status + category filters, pending_review nav badge.
- `PartnerResource` — list/view, `approve`/`reject`/`suspend`/`reinstate`,
  status + `is_active` filters, pending nav badge; mails
  `PartnerApprovedMail` / `PartnerRejectedMail`.
- `BookingResource` — list/view, status + tour_date filters, audit-trail
  infolist (references a missing `actor_description` accessor).
- `AuditLogResource` — read-only viewer for `auth_audit_logs`.
- `PlatformOverviewWidget` — bookings today/week/month, monthly revenue,
  pending partners, pending tours, 7-day sparkline.

**Implemented (admin API, `/api/admin` under `auth:sanctum` + `role:admin`)**:
- `GET /api/admin/audit/bookings[/{reference}]` — `AuditController`.
- `GET /api/admin/financial-ledger` — `FinancialLedgerController`.
- `GET /api/admin/reviews`, `POST /api/admin/reviews/{r}/hide|reinstate` —
  `AdminReviewController` (gated by `ReviewPolicy::manage`).

**Audit today**: three uncoordinated tables — `auth_audit_logs` (auth events
via `LogAuthEvent` listener), `booking_audit_logs` (`AuditService`), and
`review_audit_trails` (`HideReviewAction`/`ReinstateReviewAction`). **No audit
is written for tour or partner governance actions** — Filament actions call
`$record->update([...])` directly.

**Auth/roles**: `users.role` enum (`traveler`/`partner`/`admin`); no
spatie/permission package; permissions are a hardcoded map in
`UserPolicy::hasPermission()` (`manage_users`, `manage_tours`, `manage_bookings`,
`manage_partners`, `moderate_reviews`, `view_all_analytics`). Filament resources
do **not** attach policies; only `canAccessPanel()` gates entry.

**Status fields**: Tour/Partner/Review statuses are bare strings with no PHP
enums and no transition-guard methods; Booking has status constants and
`canCancel()` only.

---

## 1. Decision: Admin surface = Laravel Filament (server-rendered)

- **Decision**: Deliver admin moderation via the existing Filament panel.
- **Rationale**: Filament is already scaffolded for tours/partners/bookings/
  audit; plan decision D4 ratified it; `/speckit-clarify` confirmed it as the
  approved admin surface. Rebuilding as a Next.js + `/api/admin` frontend is
  large scope with high rework and duplicates working CRUD.
- **Alternatives considered**:
  - Next.js admin consuming `/api/admin/*` — constitution-compliant (API-first)
    but huge scope; rejected.
  - Hybrid (Filament now, migrate later) — deferred; adds long-term debt without
    near-term benefit.
- **Constitution impact**: Ratified exception to API-first for the internal
  admin surface only (public/traveler/partner stay API-first). Recorded in the
  Complexity Tracking table and formally codified in constitution v1.1.0
  (API-First §Internal Admin Exception).

## 2. Decision: Unified `governance_audit_logs` + `GovernanceAuditService`

- **Decision**: Introduce one append-only `governance_audit_logs` table and a
  `GovernanceAuditService::log(actor, action, target, before, after, metadata)`
  called by every governance action (tour, partner, review, booking-status,
  settings, CMS). Migrate existing booking/review audit writes to route through
  this service. Keep `auth_audit_logs` separate (auth events are not governance).
- **Rationale**: FR-011/FR-012 require a single immutable audit capturing
  actor/action/target/before-after for every governance action. The current
  split leaves tour/partner/settings actions entirely un-audited and offers no
  unified trail. A single service gives one enforcement point and one viewer
  (`GovernanceAuditResource`).
- **Alternatives considered**:
  - Extend the three per-domain tables — leaves gaps and no unified view;
    rejected.
  - Use spatie/laravel-activitylog — adds a dependency and a generic shape that
    doesn't match the constitution's actor/target/before-after mandate; rejected
    in favor of a small purpose-built table.
- **Migration note**: Backfill `governance_audit_logs` from existing
  `booking_audit_logs` and `review_audit_trails` via a one-off data migration;
  keep old tables read-only during transition, then deprecate.

## 3. Decision: Single `admin` role + persisted per-action permission flags

- **Decision**: Keep the `users.role` enum (one `admin` role); add a persisted
  per-admin permission set (`admin_permissions` table, one row per admin with
  boolean flags, or a JSON cast on `User`). Extend `UserPolicy::hasPermission()`
  to read the persisted flags. Define Filament gates/policies per resource and
  per action that call `AdminAuthorizationService::can($user, $permission)`.
- **Rationale**: `/speckit-clarify` chose Option C — single admin role with
  granular per-action permissions (least-privilege, no named sub-roles for
  Phase 1). The existing hardcoded map is a good seed for the flag list.
- **Alternatives considered**:
  - spatie/permission (roles + permissions package) — heavier than needed for a
    single admin role; rejected for Phase 1.
  - Single all-powerful admin role — violates FR-002 and least-privilege;
    rejected.
- **Permission flag inventory (seed, to finalize in tasks)**: `manage_tours`,
  `manage_partners`, `manage_bookings`, `moderate_reviews`, `view_all_analytics`,
  `manage_users`, `manage_settings`, `manage_cms`, `view_audit_log`.

## 4. Decision: PHP status enums + transition guards

- **Decision**: Introduce enum classes (`TourStatus`, `PartnerStatus`,
  `ReviewStatus`) and transition-guard methods on the models
  (`Tour::canTransitionTo()`, `Partner::canTransitionTo()`, etc.). Filament
  actions call dedicated Actions that assert the guard before mutating.
- **Rationale**: FR-004/FR-005 require a bounded lifecycle and blocking
  publishing while a partner is unapproved; ad-hoc string mutation cannot
  enforce these. Centralizing transitions prevents contradictory double
  transitions (edge case in spec).
- **Alternatives considered**:
  - Leave bare strings + ad-hoc checks — already proven insufficient; rejected.
  - A state-machine package (e.g. laravel-state) — overkill for these small
    lifecycles; rejected.

## 5. Decision: Booking status transitions are non-financial; financial effects delegate to Spec 008

- **Decision**: `TransitionBookingStatusAction` only performs non-financial
  transitions (e.g. `confirmed`→`completed`, `confirmed`→`no_show`). Any
  transition implying a financial side effect (refund/charge) does NOT execute
  financially from the admin surface — it dispatches/requests the payment domain
  (Spec 008), which enforces idempotency and writes the immutable ledger. Admin
  Moderation audits only the status transition.
- **Rationale**: `/speckit-clarify` confirmed refunds/financial execution are
  entirely in Spec 008; this feature is governance/oversight/state-visibility
  only (constitution Principle V + domain boundaries).
- **Alternatives considered**: Admins execute refunds here — duplicates
  financial logic and risks idempotency bugs; rejected.

## 6. Decision: Availability oversight is read-only

- **Decision**: `AvailabilityResource` is a read-only Filament resource
  (`canCreate()/canEdit() = false`, no row/bulk actions) over partner slots. It
  distinguishes empty/full/partially-booked states.
- **Rationale**: `/speckit-clarify` chose read-only oversight; availability is
  partner-owned (constitution ownership boundaries).
- **Alternatives considered**: Admins manage/disable partner slots — violates
  ownership and broadens scope; rejected.

## 7. Decision: Bulk actions for tours + reviews only

- **Decision**: `bulk_publish` + `bulk_reject` on `TourResource`; `bulk_hide` +
  `bulk_reinstate` on `ReviewResource`. No bulk actions on bookings or partners.
- **Rationale**: `/speckit-clarify` scoped bulk to the two high-volume queues
  (tours, reviews); bulk booking transitions carry state/financial risk;
  partners are low-volume.
- **Alternatives considered**: Bulk across all entities — rejected on safety
  and need.

## 8. Decision: Reviews moderation gets a Filament `ReviewResource` (ST-013-009)

- **Decision**: Add a Filament `ReviewResource` (list + hide/reinstate actions +
  bulk hide/reinstate + audit) for ST-013-009, alongside the existing
  `/api/admin/reviews` API (already implemented and tested, kept for any API
  consumers). The Filament resource is the canonical admin UX per D4.
- **Rationale**: D4 mandates Filament for all admin screens; currently review
  moderation is API-only with no Filament surface.
- **Alternatives considered**: Drop the API and go Filament-only — the API is
  already tested and may serve other consumers; keep both, Filament as primary.

## 9. Decision: New Filament pages/resources for the remaining ST-013 screens

- **Decision**:
  - ST-013-001/002 (Platform Overview A/B) + ST-013-003/004 (Marketplace Admin
    Home A/B) → custom `Dashboard` page hosting `PlatformOverviewWidget` (data
    states A/B are filter/view variants, not separate resources).
  - ST-013-005 Partner Approvals → existing `PartnerResource`.
  - ST-013-006 Tours Moderation → existing `TourResource`.
  - ST-013-007/008 Booking Management A/B → existing `BookingResource` (A/B =
    table variants).
  - ST-013-009 Reviews Moderation → new `ReviewResource`.
  - ST-013-010 Availability/Slots → new read-only `AvailabilityResource`.
  - ST-013-011 Admin Settings → new `Settings` page.
  - ST-013-012 Content Management → new `StaticPageResource` (CMS content).
  - ST-013-013 Site Pages CMS → `StaticPageResource` (static pages: privacy,
    terms, about).
- **Rationale**: Maps all 13 reference screens to Filament counterparts (SC-006)
  while reusing existing resources where they already fit.

## 10. Known bugs to fix during implementation

- `BookingAuditLog` lacks the `actor_description` accessor and `actor` relation
  that `BookingResource` infolist references — add the accessor/relation or
  route booking audit through `GovernanceAuditService`.
- `ReviewAuditTrail` writes `actor_type='admin'` as a plain string while the
  model defines a `morphTo actor()` — fix the morph map or standardize on the
  unified governance audit shape.

## 11. Deferred: Audit-Log Retention Policy (T061)

- **Policy**: `governance_audit_logs` must be retained indefinitely due to legal and compliance requirements for governance actions (bans, suspensions, financial status overrides). Unlike `auth_audit_logs` which may be pruned after 90 days, governance logs are a permanent ledger.
- **Implementation Note**: The table is heavily indexed by `target_type`/`target_id` and `created_at`. If volume grows to >10M rows, we will consider partitioning by year rather than deletion. For now, it is an immutable, append-only permanent store.

---

## Phase 0 Output

All design unknowns are resolved. No `NEEDS CLARIFICATION` remains in the spec
or plan. Proceeding to Phase 1 (data model + contracts + quickstart).