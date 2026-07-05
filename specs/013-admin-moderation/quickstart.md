# Quickstart: Admin Moderation

## Prerequisites

- PHP 8.3 + Laravel 12 backend running at `http://localhost:8000`
- PostgreSQL + Redis (per project docker-compose)
- Composer dependencies installed (`backend/`)
- An admin user (see Setup below)

## Setup

```bash
# Backend
cd backend
composer install
cp .env.example .env          # if not already present
php artisan key:generate
php artisan migrate --seed    # runs migrations incl. governance_audit_logs,
                               # admin_permissions, static_pages + admin seeder

# Seed/prepare an admin user (role = admin) with permission flags
php artisan tinker
>>> $u = User::factory()->admin()->create(['email' => 'admin@bookly.test']);
>>> $u->adminPermission()->create(['flags' => [
...   'manage_tours' => true, 'manage_partners' => true, 'manage_bookings' => true,
...   'moderate_reviews' => true, 'view_all_analytics' => true, 'manage_settings' => true,
...   'manage_cms' => true, 'view_audit_log' => true, 'manage_users' => true,
... ]]);
```

## Access the admin panel

Open `http://localhost:8000/admin` and sign in with the admin user created above.

| Admin route | Screen | Stitch ID |
|---|---|---|
| `/admin` | Platform overview dashboard | ST-013-001…004 |
| `/admin/partners` | Partner approvals | ST-013-005 |
| `/admin/tours` | Tours moderation | ST-013-006 |
| `/admin/bookings` | Booking management | ST-013-007/008 |
| `/admin/reviews` | Reviews moderation | ST-013-009 |
| `/admin/availability` | Availability (read-only) | ST-013-010 |
| `/admin/settings` | Platform settings | ST-013-011 |
| `/admin/static-pages` | CMS / static pages | ST-013-012/013 |
| `/admin/governance-audit` | Governance audit log | (Monitoring) |

## Verifying the core moderation loop

1. As a partner, submit a tour (`pending_review`).
2. As admin, open **Tours**, approve it → status `published`, appears publicly;
   a `tour.publish` entry appears in **Governance audit**.
3. Try approving a tour whose partner is `pending` → blocked (FR-005).
4. Open **Partners**, suspend an approved partner → their tours leave public
   discovery; `partner.suspend` audited (FR-006).
5. Open **Reviews**, hide a review → it disappears from public tour detail and the
   aggregate rating recomputes; `review.hide` audited.
6. Open **Bookings**, transition a `confirmed` booking to `completed` →
   `booking.transition` audited; a refund-requiring transition delegates to
   Spec 008 and only logs the status (FR-009).
7. Confirm a non-admin user hitting `/admin` is denied (FR-001).

## Running tests

```bash
cd backend

# All admin feature tests
php artisan test -- tests/Feature/Admin

# Filament page/action tests
php artisan test -- tests/Feature/Admin/Filament

# Targeted flows
php artisan test tests/Feature/Admin/TourModerationTest.php
php artisan test tests/Feature/Admin/GovernanceAuditTest.php
php artisan test tests/Feature/Admin/AdminPermissionsTest.php
```

## Verification gates (from spec.md)

- Non-admin users cannot reach any admin screen or action (SC-001).
- No tour publishes without an admin approval + approved partner (SC-002).
- Every governance action produces an immutable audit entry (SC-003).
- Admin clears a pending-tour item in < 30s (SC-004).
- Hidden/reinstated reviews update public detail within seconds (SC-005).
- All 13 ST-013 screens have working Filament counterparts at 1280px+ (SC-006).
- Bulk actions report per-item results and audit each actual transition (SC-007).
- Suspending a partner removes their tours from discovery within seconds (SC-008).

## Notes

- The admin surface is Filament (server-rendered) — the ratified exception to
  the constitution's API-first principle for the internal admin surface only
  (constitution v1.1.0, API-First §Internal Admin Exception).
- Refunds/ledger writes are NOT performed here; financial side effects delegate
  to Spec 008.
- `GovernanceAuditService` is the single audit writer; `auth_audit_logs` remains
  separate for auth events.