# Implementation Plan: Admin Moderation

**Branch**: `013-admin-moderation` | **Date**: 2026-06-20 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/013-admin-moderation/spec.md`

## Summary

Build the Bookly admin moderation surface as a Laravel Filament panel that lets
platform operators govern the marketplace: approve/reject/suspend partners,
approve/reject/unpublish tours, oversee bookings (non-financial status
transitions only), moderate reviews (hide/reinstate), view availability
read-only, manage settings and static/CMS pages, and operate a platform
overview dashboard. A single append-only **governance audit log** records every
governance action with actor/action/target/before-after. Authorization uses
one `admin` role backed by granular per-action permission flags enforced on
every Filament resource and action. Filament scaffolding already exists for
tours, partners, bookings, an auth-audit resource, and an overview widget; this
plan fills the gaps: audit wiring for tour/partner actions, per-action
permissions, status enums + transition guards, missing resources (reviews,
availability, settings, CMS/pages), booking status-transition actions, bulk
actions for tours + reviews, and a custom dashboard page.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12, Filament 3
**Primary Dependencies**: filament/filament 3 (admin panel), laravel/sanctum 4
(admin API already in use), spatie/laravel-settings (platform settings storage,
US9/FR-015 — owns its `settings` table; no custom key/value table), Laravel
Eloquent, PostgreSQL 16, Redis (queue)
**Storage**: PostgreSQL (governance audit log, permissions, static pages,
settings via spatie/laravel-settings `settings` table)
**Auth**: Session-based Filament `Authenticate` + `User::canAccessPanel()` (role
=== 'admin'); admin API uses `auth:sanctum` + `role:admin` middleware alias
**Permissions**: Single `admin` role + granular per-action permission flags (no
spatie/permission package; extend the existing `UserPolicy::hasPermission()` map
to a persisted per-admin permission set consumed by Filament gates/policies)
**Testing**: Pest PHP (feature tests) + Filament testing helpers
(`Livewire\Filament` test helpers for resource actions/bulk actions)
**Target Platform**: Web admin panel, desktop-first at 1280px+
**Project Type**: web-service (server-rendered admin panel)
**Performance Goals**: admin page render < 2s; governance action < 30s per item
(SC-004); audit write synchronous with the governed action
**Constraints**: append-only audit (no update/delete); no financial execution
(refunds/ledger owned by Spec 008); availability read-only; bulk actions only
tours + reviews; desktop 1280px+
**Scale/Scope**: 1 Filament panel, ~7 resources (Tour, Partner, Booking, Review,
Availability, GovernanceAudit, StaticPage/CMS), 2-3 custom pages (Dashboard,
Settings, CMS), ~12 governance actions, 4 bulk actions, 1 unified audit table +
service, 1 permissions model

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Evaluation | Status |
|---|---|---|
| I. Marketplace-First | Admin governs partners and tours across the marketplace. | PASS |
| II. Tours-Only Discipline | All admin resources are tour-vertical. | PASS |
| III. Direct Booking Only | Admin oversees booking lifecycle; no inquiry/request flows. | N/A |
| IV. Admin-Governed Publishing | Tour approval/rejection/unpublish + partner lifecycle are the core of this feature. | PASS |
| V. Platform-Controlled Commerce | No refunds/ledger writes here; financial side effects delegate to Spec 008. | PASS |
| VI. Completed-Booking Review Integrity | Admin hides/reinstates reviews; submission integrity owned by Spec 009. | PASS |
| API-First | Admin surface is server-rendered Filament, not an API-consumed frontend. | EXCEPTION (ratified — constitution v1.1.0, API-First §Internal Admin Exception; see Complexity Tracking) |
| SEO-First | Admin panel is internal/behind auth; not public. | N/A |
| Security-First | `role:admin` panel gate + per-action permission flags + append-only audit + ownership boundaries. | PASS |
| Thin Controllers / No Direct DB Access | Governance logic MUST move into services/actions; Filament actions MUST NOT mutate models directly. | PASS (by design — current direct-update gaps are fixed in this plan) |

**Re-evaluation after Phase 1**: No additional violations. The API-first
exception is ratified in constitution v1.1.0 (§Internal Admin Exception)
and tracked below.

## Project Structure

### Documentation (this feature)

```text
specs/013-admin-moderation/
├── plan.md                       # This file
├── spec.md                       # Feature specification
├── research.md                   # Phase 0 output (decisions + rationale)
├── data-model.md                 # Backend entity/table definitions
├── quickstart.md                 # Local admin setup + test commands
├── checklists/requirements.md    # Spec quality checklist
└── contracts/
    ├── admin-filament-surface.contract.md  # ST-013 -> Filament resource/page/widget mapping
    └── admin-api.contract.md               # Existing /api/admin/* REST endpoints
```

### Source Code (repository root)

#### Backend — Filament admin surface (new + extended)

```text
backend/app/Filament/
├── Resources/
│   ├── TourResource.php          # EXTEND: bulk reject, audit wiring, permission gates, transition guard
│   ├── PartnerResource.php       # EXTEND: audit wiring, permission gates, transition guard, publish-block-when-unapproved
│   ├── BookingResource.php       # EXTEND: non-financial status-transition actions, fix actor_description, audit, gates
│   ├── ReviewResource.php        # NEW (ST-013-009): list/hide/reinstate + bulk hide/reinstate, audit, gate
│   ├── AvailabilityResource.php  # NEW (ST-013-010): read-only view of partner slots
│   ├── GovernanceAuditResource.php  # NEW (Monitoring): unified append-only governance log viewer
│   └── StaticPageResource.php   # NEW (ST-013-012/013): CMS + static pages (privacy, terms, about)
├── Pages/
│   ├── Dashboard.php             # NEW/extend custom dashboard (ST-013-001..004) hosting overview + queue widgets
│   └── Settings.php              # NEW (ST-013-011): platform settings page
└── Widgets/
    └── PlatformOverviewWidget.php  # EXTEND: pending approvals/reviews/revenue + queue shortcuts

backend/app/Domains/Admin/                 # NEW domain (governance + audit + permissions + CMS)
├── Actions/
│   ├── ApproveTourAction.php / RejectTourAction.php / UnpublishTourAction.php
│   ├── ApprovePartnerAction.php / RejectPartnerAction.php / SuspendPartnerAction.php / ReinstatePartnerAction.php
│   ├── TransitionBookingStatusAction.php  # non-financial only; delegates financial side effects to Spec 008
│   └── UpdateStaticPageAction.php / UpdateSettingsAction.php
# Note: HideReviewAction / ReinstateReviewAction remain in Domains/Reviews/Actions/
# (enhanced in-place with GovernanceAuditService injection, not wrapped here)
├── Services/
│   ├── GovernanceAuditService.php         # canonical append-only audit writer (FR-011/FR-012)
│   └── AdminAuthorizationService.php       # resolves per-admin permission flags for gates/policies
├── Models/
│   ├── GovernanceAuditLog.php
│   ├── AdminPermission.php                 # per-admin permission flags (linked to User)
│   ├── StaticPage.php / CmsContent.php
│   └── (references Tour, Partner, Booking, Review)
├── Settings/                                # spatie/laravel-settings classes (see data-model.md §4)
│   ├── GeneralSettings.php / SeoSettings.php
│   └── ContactSettings.php / BookingSettings.php
├── Policies/
│   ├── TourPolicy.php / PartnerPolicy.php / BookingPolicy.php
│   ├── ReviewPolicy.php (extend) / AvailabilityPolicy.php / StaticPagePolicy.php
│   └── GovernanceAuditPolicy.php
└── Requests/
    ├── RejectTourRequest.php / RejectPartnerRequest.php   # rejection_reason validation
    ├── TransitionBookingStatusRequest.php
    └── UpdateStaticPageRequest.php / UpdateSettingsRequest.php

backend/app/Policies/UserPolicy.php         # EXTEND hasPermission() to read persisted per-admin flags

backend/database/migrations/
├── 2026_06_20_100001_create_governance_audit_logs_table.php
├── 2026_06_20_100002_create_admin_permissions_table.php
├── 2026_06_20_100003_create_static_pages_table.php
└── (spatie) create_settings_table.php        # published by spatie/laravel-settings

backend/database/settings/                     # spatie settings migrations (seed defaults)
├── 2026_06_20_100000_create_general_settings.php
├── 2026_06_20_100001_create_seo_settings.php
├── 2026_06_20_100002_create_contact_settings.php
└── 2026_06_20_100003_create_booking_settings.php

backend/config/settings.php                     # register the four settings classes

backend/routes/api/admin.php               # EXTEND only if new admin API endpoints needed (reviews/audit/ledger already exist)

backend/tests/Feature/Admin/               # NEW Pest feature tests
├── TourModerationTest.php / PartnerModerationTest.php
├── BookingStatusTransitionTest.php / ReviewModerationFilamentTest.php
├── GovernanceAuditTest.php / AdminPermissionsTest.php
├── StaticPageTest.php / AvailabilityReadonlyTest.php
└── Filament/                              # Filament page/action tests
    ├── TourResourceTest.php / PartnerResourceTest.php
    └── DashboardTest.php
```

**Structure Decision**: Admin is a Filament panel (server-rendered) at `/admin`,
governed by `AdminPanelProvider`. Governance business logic lives in a new
`app/Domains/Admin/` domain following the existing DDD pattern (thin Filament
actions delegate to Actions/Services). Filament resources are the UI surface.
No frontend changes are required for this feature. Authorization is enforced via
per-resource Policies + a per-action permission service. The unified
`GovernanceAuditService` is the single audit writer called by every governance
action.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| API-First (server-rendered Filament admin) | Internal admin tooling; Filament already scaffolded for tours/partners/bookings/audit; ratified plan decision D4 and clarify confirmed Filament as the approved admin surface; exception formally ratified in constitution v1.1.0 (API-First §Internal Admin Exception). | Building a Next.js admin consuming new `/api/admin/*` endpoints is large scope, high rework, and duplicates working Filament CRUD. The ratified, scope-limited Filament exception for the internal admin surface only is the pragmatic path. |
| Governance logic in services/actions (not Filament actions mutating models) | Constitution Thin Controllers / business-logic-in-services; needed to centralize audit, transition guards, and permission checks. | Direct `$record->update([...])` in Filament actions (current state for tour/partner) bypasses audit and guards and scatters logic across resources. |
| Unified `governance_audit_logs` table | FR-011/FR-012 require a single append-only audit capturing actor/action/target/before-after for every governance action; today audit is split across 3 uncoordinated tables with no coverage for tour/partner actions. | Extending the three existing per-domain audit tables leaves tour/partner/settings actions un-audited and gives no single governance trail view. A unified table + service is the clean target state; existing booking/review audit writes are migrated to route through `GovernanceAuditService`. |