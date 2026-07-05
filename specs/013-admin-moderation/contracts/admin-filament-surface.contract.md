# Contract: Admin Filament Surface (ST-013 → Filament mapping)

The admin surface is a Laravel Filament 3 panel at `/admin`
(`AdminPanelProvider`). This contract maps each Stitch admin reference screen
(ST-013-001…013) to its Filament counterpart and specifies the actions, filters,
bulk actions, authorization gate, and audit wiring each must provide. Every
governance action writes a `governance_audit_logs` entry via
`GovernanceAuditService` and is gated by the listed permission flag (see
data-model.md §2).

## Panel

- **Panel id**: `admin` — **Path**: `/admin`
- **Auth**: session `Authenticate` + `User::canAccessPanel()` ⇒ `role === 'admin'`
- **Nav groups**: Platform, Operations, Monitoring, Content
- **Responsive target**: desktop 1280px+ (SC-006)

## Screen → Filament mapping

| Stitch ID | Screen | Filament counterpart | Type | Permission flag |
|-----------|--------|----------------------|------|-----------------|
| ST-013-001 | Platform Overview Dashboard A | `Pages\Dashboard` + `PlatformOverviewWidget` | Custom page + widget | `view_all_analytics` |
| ST-013-002 | Platform Overview Dashboard B | (data-state variant of above) | widget view variant | `view_all_analytics` |
| ST-013-003 | Marketplace Admin Home A | `Pages\Dashboard` (Filament default) | dashboard | `view_all_analytics` |
| ST-013-004 | Marketplace Admin Home B | (stats/navigation variant) | dashboard variant | `view_all_analytics` |
| ST-013-005 | Partner Approvals Admin | `PartnerResource` | resource | `manage_partners` |
| ST-013-006 | Tours Moderation Admin | `TourResource` | resource | `manage_tours` |
| ST-013-007 | Booking Management Admin A | `BookingResource` | resource | `manage_bookings` |
| ST-013-008 | Booking Management Admin B | (table variant of above) | resource variant | `manage_bookings` |
| ST-013-009 | Reviews Moderation Admin | `ReviewResource` (NEW) | resource | `moderate_reviews` |
| ST-013-010 | Availability and Slots Admin | `AvailabilityResource` (NEW, read-only) | resource | `manage_bookings` (view) |
| ST-013-011 | Admin Settings Dashboard | `Pages\Settings` (NEW) | custom page | `manage_settings` |
| ST-013-012 | Content Management Admin | `StaticPageResource` (NEW) | resource | `manage_cms` |
| ST-013-013 | Site Pages CMS Admin | `StaticPageResource` (static pages) | resource | `manage_cms` |

## Per-resource contract

### TourResource (ST-013-006) — EXTEND
- **Pages**: `ListTours`, `ViewTour` (no Create/Edit on admin).
- **Filters**: `status` (draft/pending_review/published/rejected/archived),
  `category`, partner.
- **Row actions** (each writes `tour.*` audit, asserts `Tour::canTransitionTo`):
  - `publish` — pending_review → published; **blocked if partner not approved** (FR-005).
  - `reject` — modal with `rejection_reason` → rejected.
  - `unpublish` — published → **draft** (returns to partner for further editing;
    distinct from partner-initiated `archive` which moves to `archived`).
- **Bulk actions**: `bulk_publish`, `bulk_reject` (per-item results + per-item
  audit; FR-016).
- **Nav badge**: count of `pending_review` tours → shortcut to filtered queue.
- **Gate**: `manage_tours` per action (publish/reject) via `TourPolicy`.

### PartnerResource (ST-013-005) — EXTEND
- **Pages**: `ListPartners`, `ViewPartner`.
- **Filters**: `onboarding_status`, `is_active`.
- **Row actions** (write `partner.*` audit, assert `Partner::canTransitionTo`,
  mail via Spec 014):
  - `approve`, `reject` (reason modal), `suspend`, `reinstate`.
  - `suspend`/`reinstate` toggle tours' public visibility (FR-006).
- **Bulk actions**: none (FR-016 — bulk actions are limited to tours and reviews only).
- **Nav badge**: count of pending partners.
- **Gate**: `manage_partners` via `PartnerPolicy`.

### BookingResource (ST-013-007/008) — EXTEND
- **Pages**: `ListBookings`, `ViewBooking`.
- **Filters**: `status`, `tour_date` range, `reference`, partner.
- **Row actions** (write `booking.transition` audit, non-financial only —
  FR-009):
  - Allowed transitions via `TransitionBookingStatusAction` (e.g. confirmed→
    completed, confirmed→no_show). Financial transitions (refund/cancel-with-
    refund) dispatch to Spec 008; admin logs status only.
- **No Create**; no bulk actions (clarify §7).
- **Fix**: add `actor_description` accessor / route audit through
  `GovernanceAuditService`.
- **Gate**: `manage_bookings` via `BookingPolicy`.

### ReviewResource (ST-013-009) — NEW
- **Pages**: `ListReviews`, `ViewReview`.
- **Filters**: `status` (visible/hidden/flagged), `tour`, date range, flagged.
- **Row actions** (write `review.hide`/`review.reinstate` audit, recompute tour
  aggregate rating):
  - `hide`, `reinstate`.
- **Bulk actions**: `bulk_hide`, `bulk_reinstate` (FR-016).
- **Gate**: `moderate_reviews` via `ReviewPolicy` (extends existing
  `ReviewPolicy::manage`).
- **Note**: existing `/api/admin/reviews` API kept (see admin-api contract).

### AvailabilityResource (ST-013-010) — NEW, READ-ONLY
- **Pages**: `ListAvailability`, `ViewAvailability`.
- **Read-only**: `canCreate()/canEdit() = false`; no row/bulk actions (FR-014,
  clarify §6).
- **Filters**: tour, date, booking state (empty/full/partially-booked).
- **Gate**: `manage_bookings` (view only) via `AvailabilityPolicy`.

### GovernanceAuditResource (Monitoring) — NEW
- **Pages**: `ListAuditLogs` (read-only; no create/edit/delete).
- **Filters**: `actor`, `action`, `target_type`, `target_id`, date range.
- **Append-only**: enforce no update/delete (FR-012).
- **Gate**: `view_audit_log`.

### StaticPageResource (ST-013-012/013) — NEW
- **Pages**: `ListStaticPages`, `EditStaticPage`, `ViewStaticPage`.
- **Fields**: slug, status (draft/published), localized title/body/meta
  (JSONB en/es/it), `updated_by`.
- **Actions**: save (draft), publish, unpublish — each writes `cms.*` audit
  (actor = `updated_by`).
- **Gate**: `manage_cms` via `StaticPagePolicy`.

### Pages\Dashboard (ST-013-001..004) — NEW/extend
- Hosts `PlatformOverviewWidget` (pending partners, pending tours, pending
  reviews, recent bookings, monthly revenue, 7-day sparkline).
- Queue-shortcut widgets/tiles link into each filtered moderation queue.
- **Gate**: `view_all_analytics`.

### Pages\Settings (ST-013-011) — NEW
- One Filament section per spatie settings class (`GeneralSettings`,
  `SeoSettings`, `ContactSettings`, `BookingSettings` — see data-model.md §4);
  each form field maps directly to a typed settings-class property. Saving
  fills + `save()`s the relevant class via `UpdateSettingsAction` and writes a
  `settings.update` audit (`target_type`=`setting`, `target_id`=null,
  `metadata.group` = the group).
- **Gate**: `manage_settings`.

## Authorization model (all resources)

- Entry: `User::canAccessPanel()` ⇒ `role === 'admin'`.
- Per action: `AdminAuthorizationService::can($user, $flag)` via the resource's
  Policy methods.
- Disallowed actions are hidden/unavailable (not merely server-rejected) —
  FR-002 acceptance scenario 4.

## Audit wiring (all governance actions)

Every action calls `GovernanceAuditService::log($admin, $action, $target,
$before, $after, $metadata)` synchronously within the same transaction as the
state change. Bulk actions log one entry per actual transition with a shared
`metadata.bulk_batch_id` (FR-016).

## Bulk action partial-failure UX

When a bulk action partially succeeds (e.g. some items already in the target
state), Filament displays a **notification toast** summarizing the outcome:
`"X of Y items transitioned successfully; Z skipped (already in target state)"`.
Each actually-transitioned item produces its own `governance_audit_logs` entry
with the shared `bulk_batch_id`. Skipped items produce no audit entry. No
partial failure causes a rollback — each item is processed independently
(FR-016, spec edge case §4).