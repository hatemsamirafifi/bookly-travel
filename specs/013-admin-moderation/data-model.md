# Data Model: Admin Moderation

## Overview

This document defines the backend entities, tables, enums, and relationships
introduced or formalized by the Admin Moderation feature (Spec 013). The admin
surface is Laravel Filament; entities live under `backend/app/Domains/Admin/`
and `backend/app/Models/`. Existing entities (Tour, Partner, Booking, Review)
are referenced, not redefined — only their status enums/transition guards are
formalized here.

---

## 1. GovernanceAuditLog (NEW — canonical append-only audit)

Unified audit record for every admin governance action (FR-011/FR-012).

```php
// backend/app/Domains/Admin/Models/GovernanceAuditLog.php
class GovernanceAuditLog extends Model
{
    public const UPDATED_AT = null; // append-only: no updates, no deletes
    protected $guarded = ['id'];
}
```

### Table: `governance_audit_logs`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK, auto-increment |
| `actor_type` | string(20) | morph map alias (e.g. `admin`) |
| `actor_id` | bigint, nullable | FK→users.id (admin) |
| `action` | string(40) | e.g. `tour.publish`, `partner.suspend`, `review.hide`, `booking.transition`, `settings.update`, `cms.update` |
| `target_type` | string(60) | morph map alias (e.g. `tour`, `partner`, `booking`, `review`, `static_page`, `setting`) |
| `target_id` | bigint, nullable | FK to target, nullable for settings/global |
| `before_state` | jsonb, nullable | prior state snapshot (status/fields) |
| `after_state` | jsonb, nullable | new state snapshot |
| `metadata` | jsonb, nullable | reason, bulk_batch_id, ip, user_agent |
| `created_at` | timestamp | immutable; no `updated_at` |

Indexes: `(actor_type, actor_id, created_at)`, `(target_type, target_id, created_at)`,
`(action, created_at)`. Booted `deleting`/`updating` handlers return false to
enforce immutability.

### Service contract

```php
GovernanceAuditService::log(
    User $actor,
    string $action,            // 'tour.publish'
    Model $target = null,      // the governed record
    ?array $before = null,
    ?array $after = null,
    array $metadata = []
): GovernanceAuditLog
```

### Action vocabulary

| Action | Target | Triggered by |
|--------|--------|--------------|
| `tour.publish` / `tour.reject` / `tour.unpublish` | Tour | TourResource actions |
| `partner.approve` / `partner.reject` / `partner.suspend` / `partner.reinstate` | Partner | PartnerResource actions |
| `review.hide` / `review.reinstate` | Review | ReviewResource / `HideReviewAction` / `ReinstateReviewAction` |
| `booking.transition` | Booking | `TransitionBookingStatusAction` (non-financial only) |
| `settings.update` | Setting | Settings page |
| `cms.update` / `cms.publish` | StaticPage | StaticPageResource |

---

## 2. AdminPermission (NEW — per-admin permission flags)

Single `admin` role (on `users.role`) + granular per-action flags (clarify Option C).

```php
// backend/app/Domains/Admin/Models/AdminPermission.php
// One row per admin user; boolean flags cast as array/booleans.
```

### Table: `admin_permissions`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `user_id` | bigint | unique FK→users.id (role=admin) |
| `flags` | jsonb | map of permission→bool (see inventory below) |
| `created_at` / `updated_at` | timestamp | |

### Permission flag inventory (seed)

`manage_tours`, `manage_partners`, `manage_bookings`, `moderate_reviews`,
`view_all_analytics`, `manage_users` *(reserved — no resource uses this flag in
Phase 1; placeholder for future user management)*, `manage_settings`,
`manage_cms`, `view_audit_log`.

### Authorization resolution

```php
AdminAuthorizationService::can(User $user, string $permission): bool
// returns true only if $user->role === 'admin' && ($user->permissionFlags[$permission] ?? false)
```

`UserPolicy::hasPermission()` is extended to delegate to this service. Filament
resources register Policies whose methods (`viewAny`, `approve`, `reject`,
`suspend`, `hide`, `transition`, `update`) map to permission flags.

---

## 3. StaticPage / CmsContent (NEW — ST-013-012/013)

Static site pages (privacy, terms, about, marketing/legal) + CMS content, with
localized variants.

```php
// backend/app/Domains/Admin/Models/StaticPage.php
```

### Table: `static_pages`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `slug` | string(120) | unique, e.g. `privacy`, `terms`, `about` |
| `status` | string(20) | `draft` / `published` |
| `title` | jsonb | localized {en, es, it} |
| `body` | jsonb | localized content (markdown/html) |
| `meta_description` | jsonb, nullable | localized SEO meta |
| `updated_by` | bigint, nullable | FK→users.id (admin) |
| `created_at` / `updated_at` / `published_at` | timestamp | |

Localized fields are JSONB keyed by locale (`en`/`es`/`it`), matching the
platform i18n. Updates write a `cms.update`/`cms.publish` governance audit entry
(actor = `updated_by`).

---

## 4. Platform Settings (spatie/laravel-settings)

Platform-wide settings (site name, SEO defaults, contact details, booking
rules) are persisted by **`spatie/laravel-settings`** — the official settings
storage mechanism for the admin surface (US9, FR-015). No custom key/value
table is introduced; the package owns its own `settings` table.

### Storage table: `settings` (owned by the package)

Published by `spatie/laravel-settings` via `php artisan vendor:publish
--provider="Spatie\LaravelSettings\SettingsServiceProvider"`. Its schema is
owned by the package and MUST NOT be modified.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint unsigned | PK, auto-increment |
| `group` | string | settings group: `general` / `seo` / `contact` / `booking` |
| `name` | string | property name, e.g. `site_name` |
| `locked` | boolean | default `false`; locked properties cannot be overwritten |
| `payload` | json | stored value (cast per declared property type) |
| `created_at` / `updated_at` | timestamp, nullable | |

Unique index on `(group, name)` — one row per property per group. The
package resolves, casts, and caches values; repositories may be `database`
(default) or `redis` via `config/settings.php`.

### Settings classes

Classes extend `Spatie\LaravelSettings\Settings`, declare public properties
(with default values), and implement `public static function group(): string`.
They live in `backend/app/Domains/Admin/Settings/` and are registered in
`config/settings.php` under `'settings' => [...]` (explicit registration keeps
them inside the Admin domain rather than relying on the `app/Settings`
auto-discovery path).

#### GeneralSettings — group `general`

| Property | Type | Default | Purpose |
|----------|------|---------|---------|
| `site_name` | string | `'Bookly'` | Marketplace brand name |
| `site_tagline` | ?string | `null` | Optional brand tagline |
| `support_email` | string | `'support@bookly.test'` | Customer support address |
| `default_currency` | string | `'USD'` | ISO 4217 display currency |
| `timezone` | string | `'UTC'` | Platform default timezone |
| `maintenance_mode` | bool | `false` | Toggles public-site maintenance banner |

#### SeoSettings — group `seo`

| Property | Type | Default | Purpose |
|----------|------|---------|---------|
| `default_meta_title` | string | `'Bookly — Tours Marketplace'` | Fallback `<title>` |
| `default_meta_description` | ?string | `null` | Fallback meta description |
| `default_og_image` | ?string | `null` | Default Open Graph image (R2 URL) |
| `twitter_handle` | ?string | `null` | Twitter `@handle` |
| `default_canonical_base` | ?string | `null` | Canonical URL base |
| `sitemap_enabled` | bool | `true` | Enable XML sitemap generation |

#### ContactSettings — group `contact`

| Property | Type | Default | Purpose |
|----------|------|---------|---------|
| `contact_email` | string | `'hello@bookly.test'` | Public contact email |
| `contact_phone` | ?string | `null` | Public contact phone |
| `contact_address` | ?string | `null` | Postal / business address |
| `business_hours` | ?string | `null` | Human-readable hours |
| `social_links` | array | `[]` | `['facebook' => url, 'instagram' => url, …]` |

#### BookingSettings — group `booking`

| Property | Type | Default | Purpose |
|----------|------|---------|---------|
| `allow_guest_checkout` | bool | `true` | Allow booking without an account |
| `min_advance_booking_hours` | int | `0` | Minimum lead time before booking a tour |
| `default_booking_window_days` | int | `365` | Maximum days ahead a tour may be booked |
| `max_guests_per_booking` | int | `20` | Hard cap on guests per booking |
| `cancellation_cutoff_hours` | int | `48` | Hours before tour date cancellation is allowed |
| `auto_complete_after_days` | int | `2` | Days after tour date bookings auto-complete (Spec 008 job) |

### Settings migrations (seeding defaults)

spatie requires each property to exist in the repository before `save()`,
otherwise a `MissingSettings` exception is thrown. Defaults declared on the
properties above allow reads before migration; persisting defaults uses
**settings migrations** in `backend/database/settings/` (executed by the
standard `php artisan migrate`, which runs settings migrations alongside
DB migrations):

```php
// backend/database/settings/2026_06_20_100000_create_general_settings.php
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void {
        $this->migrator->add('general.site_name', 'Bookly');
        $this->migrator->add('general.site_tagline', null);
        $this->migrator->add('general.support_email', 'support@bookly.test');
        $this->migrator->add('general.default_currency', 'USD');
        $this->migrator->add('general.timezone', 'UTC');
        $this->migrator->add('general.maintenance_mode', false);
    }
};
```

Equivalent migrations are added for `seo` (`…100001…`), `contact`
(`…100002…`), and `booking` (`…100003…`) — one file per group with sequential
timestamps for deterministic ordering. Scaffold with
`php artisan make:settings-migration Create<Name>Settings`. Migrator helpers:
`add()`, `rename()`, `update()`, `delete()`/`deleteIfExists()`, `addEncrypted()`
for sensitive values.

### Filament binding (FR-015 / ST-013-011)

- `Pages\Settings` (task T057) resolves the four settings classes from the
  container and renders one Filament section per class, so every admin
  settings form field maps directly to a typed settings-class property
  (`GeneralSettings`, `SeoSettings`, `ContactSettings`, `BookingSettings`).
- `UpdateSettingsAction` + `UpdateSettingsRequest` (task T055) validates the
  submitted group, fills the matching settings class, calls `save()`, and
  writes a `settings.update` governance audit entry via
  `GovernanceAuditService::log()` with `target_type = 'setting'`,
  `target_id = null`, and `metadata.group = <group>` + `metadata.properties`
  listing changed keys with before/after snapshots.
- Platform settings are **not** Eloquent models and are not governance-audit
  morph targets; the `setting` target uses a plain `target_type` string with a
  null `target_id` (see §1).

---

## 5. Status enums + transition guards (formalize existing fields)

No schema change — these formalize existing bare-string columns with PHP enums
and guard methods. Filament governance actions assert the guard before mutating.

### TourStatus (`tours.status`, existing)

```php
enum TourStatus: string {
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
```

Transitions:
- `draft` → `pending_review` (partner submits)
- `pending_review` → `published` (admin approve) — **blocked if partner not approved** (FR-005)
- `pending_review` → `rejected` (admin reject, reason required)
- `rejected` → `pending_review` (partner resubmits after edits)
- `published` → `archived` (partner archives) / `draft` (admin unpublish)

`Tour::canTransitionTo(TourStatus $to): bool` — guards the above; publishing also
checks `$this->partner->onboarding_status === 'approved'`.

### PartnerStatus (`partners.onboarding_status`, existing)

```php
enum PartnerStatus: string {
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
```

Transitions:
- `pending` → `approved` / `rejected`
- `approved` → `suspended` (suspend) — **removes partner's tours from public discovery** (FR-006)
- `suspended` → `approved` (reinstate)
- `rejected` → `pending` — **deferred / out of scope for Phase 1**; if a
  rejected partner wishes to re-apply, the current product expectation is a
  manual admin action. This transition is NOT implemented in
  `Partner::canTransitionTo()` and will be evaluated in a future phase.

`Partner::canTransitionTo()` guards transitions. `is_active` boolean mirrors
lifecycle for fast filtering.

### ReviewStatus (`reviews.status`, existing)

```php
enum ReviewStatus: string {
    case Visible = 'visible';
    case Hidden = 'hidden';
    case Flagged = 'flagged';
}
```

Transitions (admin):
- `visible` → `hidden` (hide; recomputes tour aggregate rating)
- `hidden` → `visible` (reinstate; recomputes aggregate rating)
- `visible` ↔ `flagged` — **reserved for future use**; the `Flagged` case is
  declared in the enum for forward compatibility (e.g. user-reported reviews)
  but no Filament action, user story, or acceptance scenario exercises
  flag/unflag in Phase 1. The filter UI may surface existing flagged reviews
  if any are seeded, but no transition path is enforced.

`Review::canTransitionTo()` guards these.

### BookingStatus (`bookings.status`, existing constants)

Already has constants; add a `Booking::canTransitionTo(string $to): bool` guard for
**non-financial** admin transitions only:

| From | Allowed admin `to` | Financial side effect? |
|------|--------------------|------------------------|
| `confirmed` | `completed`, `no_show` | none (completion may trigger Spec 008 payout-side orchestration, but execution stays in 008) |
| `confirmed` | `cancellation_requested` / `cancelled` | **yes → delegate refund to Spec 008**; admin logs status only |
| `pending_payment` | `expired` | none |

Financial side effects are NEVER executed by `TransitionBookingStatusAction`; it
dispatches a request to the payment domain (Spec 008) and writes only the
`booking.transition` audit entry (FR-009).

---

## 6. Existing entities referenced (no schema change)

- **User** (`backend/app/Models/User.php`): `role` enum incl. `admin`;
  `canAccessPanel()` ⇒ `role === 'admin'`. Gains relation `hasOne(AdminPermission)`.
- **Tour**, **Partner**, **Booking**, **Review**: as above; status fields only.
- **AuthAuditLog** (`auth_audit_logs`): unchanged; auth events remain separate
  from governance audit.
- Existing `booking_audit_logs` / `review_audit_trails`: migrated to route
  through `GovernanceAuditService` (writes to `governance_audit_logs`); old
  tables deprecated after backfill (see research.md §2).

---

## 7. Relationships

```text
User (admin) 1—1 AdminPermission
User (admin) 1—* GovernanceAuditLog (as actor)
Tour / Partner / Booking / Review / StaticPage 1—* GovernanceAuditLog (as target)
StaticPage 1—* GovernanceAuditLog (cms.update/publish, actor = updated_by)
```

`GovernanceAuditLog` uses Laravel morph maps (`admin`→User, `tour`→Tour,
`partner`→Partner, `booking`→Booking, `review`→Review, `static_page`→StaticPage)
for `actor` and `target`, fixing the plain-string `actor_type` mismatch in the
legacy `review_audit_trails`.