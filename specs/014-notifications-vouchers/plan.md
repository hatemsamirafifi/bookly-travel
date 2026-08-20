# Implementation Plan: Notifications and Vouchers

**Branch**: `014-notifications-vouchers` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/014-notifications-vouchers/spec.md`

## Summary

Formalize and complete the platform's transactional communication and proof-of-booking surface. The notification and voucher infrastructure already substantially exists (localized traveler confirmation/voucher/cancellation mailables, the queued idempotent `SendBookingConfirmationEmail` job, the `VoucherService` + auth-gated `VoucherController`, the partner in-app `Notification` model + controller, and the `BookingEmailDeliveryFailed` → `NotifyAdminOnEmailDeliveryFailure` log/Slack alerting) and is **reused, not redefined**. This plan closes the remaining gaps:

1. **Voucher QR payload migration** — change `VoucherService` so the QR encodes the public verification URL `https://bookly.travel/v/{reference}` instead of the current JSON payload (FR-002, SC-009).
2. **Public read-only verification surface (NEW, additive)** — a thin `VerificationController` delegating to one `VerificationAction` returning a `VerificationResult` DTO serialized by a `VerificationTransformer`, plus a minimal Next.js page at `/v/{reference}` (FR-021..FR-028, SC-010, SC-011). Unauthenticated, no PII, no enumeration, no side effects.
3. **Voucher download eligibility extension** — `VoucherController` guard widened from `STATUS_CONFIRMED` to `{confirmed, completed}` (FR-007, FR-008).
4. **Voucher freshness** — staleness detection so a changed booking (date/participants) regenerates the PDF and unchanged bookings reuse the cached file (FR-018, SC-008).
5. **Partner in-app notification completion** — wire the existing `NotificationBell` into the partner header/layout with a live unread count (FR-017, SC-007); confirm the unread filter / mark-read / ownership-scope endpoints (FR-016) are exercised.
6. **Partner governance-email localization** — localize `PartnerApprovedMail` / `PartnerRejectedMail` / `PartnerSuspendedMail` / `PartnerBookingCancelledMail` / `PartnerNewBookingMail` to the partner user's `locale` with EN fallback, keeping the rejection/suspension reason (FR-006, FR-014).
7. **Voucher PDF localization confirmation** — ensure `voucher/booking.blade.php` renders locale-appropriate labels from the booking's `locale` (FR-015).

Delivery-resilience (FR-010..FR-013, FR-019), traveler-email localization (FR-014 for traveler emails), and the no-new-admin-notification-surface constraint (FR-012, US5) are already satisfied by the existing job/listener and are validated by tests, not rebuilt.

## Technical Context

**Language/Version**: PHP 8.4 / Laravel 11 (API-only) backend; TypeScript / Next.js 16 (App Router) frontend; Blade for email + voucher views.
**Primary Dependencies**: `barryvdh/laravel-dompdf` (voucher PDF), `simplesoftwareio/simple-qrcode` (QR code generation in the voucher view), Laravel `Mail`/Mailables, Laravel Sanctum (auth), Redis (queue + cache locks), `next-intl` (frontend i18n), `@tanstack/react-query` (frontend data).
**Storage**: PostgreSQL (bookings, notifications, partner users); filesystem `storage/app/vouchers/voucher-{reference}.pdf` (regenerated PDFs); Redis (queue + idempotency locks).
**Testing**: Pest (backend integration/feature, serial, pgsql via `docker exec bookly-backend vendor/bin/pest`); Jest (frontend unit); Playwright (E2E). Per project memory: serial-only, RefreshDatabase, SCOUT_DRIVER forced to `collection` in tests.
**Target Platform**: Docker compose (nginx + bookly-backend + bookly-frontend + bookly-postgres + redis); Cloudflare in front of public surfaces.
**Project Type**: Web service (API-first Laravel backend + Next.js 16 SSR/SSG frontend; Filament admin panel is the sole server-rendered exception and is NOT touched by this spec).
**Performance Goals**: Confirmation email within 2 min, voucher email within 5 min of booking (SC-003 operational queue telemetry); verification endpoint must achieve low latency (target p95 < 50ms, single index lookup on reference) and be strictly read-only.
**Constraints**: Backward compatibility is hard — MUST NOT break booking, notifications, payments, partner dashboard, traveler dashboard, search, or admin moderation (FR-028). Verification surface is purely additive. No new tables for admin notifications (FR-012). No PII on the verification surface (FR-022, SC-010). No side effects on verification (FR-025, SC-011). No automated payouts (Out-of-Scope §1, FR-020).
**Scale/Scope**: 1 new public read route + 1 thin controller + 1 action + 1 DTO + 1 transformer; 1 new Next.js page; QR payload change in `VoucherService`; voucher-download guard widened; voucher staleness mechanism; partner-header unread wiring; partner-email localization. No migrations required for the verification surface (reuses Booking + opaque reference); one migration adds both nullable staleness columns on `bookings` — `voucher_generated_at` (timestamp) and `voucher_content_hash` (string) — decided in research.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| **V. Platform-Controlled Commerce** — voucher is the platform-controlled proof of booking; verification makes the QR trustworthy | Voucher QR encodes platform verification URL; verification confirms platform-mediated booking | ✅ PASS — design satisfies |
| **VI. Completed-Booking Review Integrity** | Not in scope here; reviews owned by Spec 009 | ✅ PASS — out of scope |
| **API-First** (with Internal Admin Exception) | Public verification page is Next.js consuming the Laravel verification API endpoint; no server-rendered HTML for the public surface | ✅ PASS — API-first split honored (FR-021 API + FR-027 Next.js page) |
| **Shared Backend, Separated Access Domains** | Verification endpoint is public/unauthenticated under `/api/public/v/{reference}`; partner notifications under existing `/api/partner/*` | ✅ PASS |
| **Modular Service Boundaries** | `VerificationAction` lives in the Booking domain (it reads Booking); thin controller delegates to the action; no cross-domain duplication | ✅ PASS |
| **Thin Controllers / No Direct DB Access from Controllers / Business Logic in Services/Actions** | `VerificationController` only resolves + authorizes (public, no auth) + returns transformer output; `VerificationAction` holds the status-mapping + PII-stripping logic | ✅ PASS (FR-026) |
| **Security-First / Mandatory Input Validation** | Verification reference validated as a non-empty opaque string matching the reference alphabet/prefix shape; unknown → 404; no numeric IDs accepted (FR-024) | ✅ PASS — gate in research |
| **Strict Authorization** | Voucher download keeps auth + ownership checks (FR-008); verification endpoint is intentionally unauthenticated but read-only and PII-stripped | ✅ PASS |
| **Secrets Handling** | No new secrets; Slack webhook already in `services.slack.admin_webhook_url` | ✅ PASS |
| **Idempotent Financial Flows** | Email delivery failures never alter booking status (FR-013); existing job idempotency preserved | ✅ PASS |
| **Queueing & Async Work / Retry-Safety** | All notification delivery remains queued + idempotent (existing job, FR-010/FR-011); no synchronous delivery introduced | ✅ PASS |
| **Audit Logging & Operational Governance** | Verification endpoint writes NO audit entry (FR-025, SC-011); delivery-failure alerting reuses the operational ERROR log + Slack, NOT the governance audit store (spec Assumptions) | ✅ PASS — explicit non-write is a design requirement, not a violation |
| **Public Experience & SEO Rules** | `/v/{reference}` is a minimal public page; metadata is intentionally minimal (the page is not a discovery surface, it is a verification artifact) — noindex is appropriate to avoid enumerating voucher URLs in search | ✅ PASS — research confirms noindex + canonical |
| **Out-of-Scope §1 (automated partner payouts)** | No payout notifications (FR-020) | ✅ PASS — explicit exclusion |
| **Backward compatibility (spec FR-028)** | Additive only; no existing route/contract changed except the QR payload (forward-compatible: old JSON-payload QRs are not "broken" because no reader relied on them — the verification surface is new) | ✅ PASS |

**Gate verdict**: No violations. No Complexity Tracking entries required. Proceed to Phase 0.

### Post-design re-check (after Phase 1 artifacts)

Re-evaluated against `research.md`, `data-model.md`, and `contracts/`:

- **API-First** — confirmed: `VerificationController` (Laravel) + `VerificationTransformer` + `VerificationAction` serve a JSON API; the public page at `/v/{reference}` is a Next.js Server Component consuming that API. No server-rendered public HTML. ✅
- **No new tables** — confirmed: only two nullable columns (`voucher_generated_at`, `voucher_content_hash`) on `bookings` (R3 migration). No `admin_notifications`, `vouchers`, or `verifications` tables (data-model §6). ✅
- **No admin notification surface / no new Filament resource** — confirmed: delivery-failure alerting reuses the existing ERROR log + optional Slack listener; nothing new introduced (FR-012, US5). ✅
- **Thin controller / single action** — confirmed: `VerificationController` delegates to one `VerificationAction` (FR-026); no duplicated booking-read logic (FR-028). ✅
- **Verification read-only / no PII / no enumeration** — confirmed by contract: `Cache-Control: no-store`, regex short-circuit + `firstOrFail` 404, field-by-field DTO construction (FR-022, FR-025, SC-010, SC-011). ✅
- **Out-of-scope §1 (payouts)** — confirmed excluded (FR-020). ✅
- **Backward compatibility** — confirmed: additive route + page; QR payload change is forward-compatible (no prior real QR existed — R1); voucher guard widened, not narrowed; freshness columns nullable + backfilled NULL (FR-028). ✅
- **New dependency** — `simplesoftwareio/simple-qrcode` is a utility composer package within the approved Laravel backend layer; not a stack deviation, no Complexity Tracking entry required. ✅

**Post-design gate verdict**: Still no violations. Plan is ready for `/speckit-tasks`.

## Project Structure

### Documentation (this feature)

```text
specs/014-notifications-vouchers/
├── plan.md              # This file
├── research.md          # Phase 0 — resolves QR library, voucher staleness, partner-locale source, noindex decision
├── data-model.md        # Phase 1 — Booking fields, Notification fields, VerificationResult DTO, status mapping
├── quickstart.md        # Phase 1 — how to run/verify the feature locally
├── contracts/
│   ├── verification-api.md   # Phase 1 — public verification API contract
│   └── partner-notifications-api.md  # Phase 1 — partner in-app notifications API (existing, confirmed)
└── tasks.md             # Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code (repository root)

```text
backend/
├── app/Domains/Booking/
│   ├── Actions/VerificationAction.php          # NEW — status mapping + PII-stripping, single source of truth
│   ├── Controllers/Public/
│   │   ├── VoucherController.php               # EDIT — widen status guard to {confirmed, completed}
│   │   └── VerificationController.php          # NEW — thin, delegates to VerificationAction
│   ├── DTOs/VerificationResult.php            # NEW — read-only payload returned by VerificationAction
│   ├── Services/VoucherService.php            # EDIT — QR encodes verification URL; staleness check
│   ├── Transformers/VerificationTransformer.php # NEW — Booking → VerificationResult payload
│   └── Models/Booking.php                       # EDIT (if needed) — voucher_generated_at accessor/cast
├── app/Mail/
│   ├── PartnerApprovedMail.php                # EDIT — localize to partner user's locale + EN fallback
│   ├── PartnerRejectedMail.php                 # EDIT — localize + keep reason
│   ├── PartnerSuspendedMail.php                # EDIT — localize + keep reason
│   ├── PartnerBookingCancelledMail.php        # EDIT — localize
│   └── PartnerNewBookingMail.php              # EDIT — localize
├── resources/views/emails/partner/            # NEW per-locale views (approved/rejected/suspended/booking-cancelled/new-booking × en/es/it) with EN as the source/fallback
├── resources/views/voucher/booking.blade.php  # EDIT (if needed) — locale-aware labels from booking.locale
├── database/migrations/                       # NEW — add bookings.voucher_generated_at (timestamp) + voucher_content_hash (string) for staleness; decided in research
├── routes/api/public.php                       # EDIT — add GET /api/public/v/{reference} (unauthenticated)
└── tests/Feature/
    ├── Booking/VoucherDownloadTest.php          # NEW/EDIT — owner/non-owner/cancelled/completed/guest
    └── Booking/VerificationTest.php            # NEW — VALID/CANCELLED/PENDING/EXPIRED/unknown-404 + PII-leak assertions + no-side-effect assertions

frontend/
├── src/app/v/[reference]/page.tsx             # NEW — minimal SSR public verification page (root, no locale prefix)
├── src/components/partner/layout/             # EDIT — wire NotificationBell into PartnerHeader with live unread count
└── messages/{en,es,it}.json                    # EDIT — verification page strings + any new partner-notification strings

backend/ (touched only for confirmation, no rebuild)
├── app/Domains/Partner/Controllers/NotificationController.php  # CONFIRMED existing — index/unread_count/markRead/markAllRead, partner-scoped
├── app/Domains/Partner/Models/Notification.php               # CONFIRMED existing
├── app/Domains/Booking/Jobs/SendBookingConfirmationEmail.php # CONFIRMED existing — idempotent, 3 tries, failed() → event
└── app/Listeners/NotifyAdminOnEmailDeliveryFailure.php        # CONFIRMED existing — ERROR log + best-effort Slack
```

**Structure Decision**: Single web-service project (existing `backend/` + `frontend/`). The verification surface is the only NEW public read route; everything else is an edit to an existing class. No new domain, no new table for admin notifications, no new Filament resource.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No Constitution Check violations. Table intentionally empty — no new architecture, no stack deviations, no out-of-scope items introduced.