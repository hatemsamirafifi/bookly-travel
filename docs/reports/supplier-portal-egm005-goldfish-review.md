# Bookly Travel — EGM-005 Independent Goldfish Review Record (Waves 2, 3A–3E & Final 3-Perspective Audit)

**Date:** 2026-11-22
**Workspace:** `/home/hatem/projects/bookly-travel` (`ext4`)
**Branch:** `codex/supplier-portal-hardening` (`HEAD: 324cb71cd6b46e36be27af799c7123d820a55f63`)
**Review Method:** Fresh-context independent Goldfish reviews gated every wave from Wave 2 (`NO-GO`) through Waves 3A–3E (`PASS`), concluding with a full-stack Phase 3 regression sweep (`Pint` `800` files, `PHPStan` `0` errors, `Pest PGSQL` `1,712 passed / 9,391 assertions`, `Jest` `92 suites / 1,024 tests`, EN/ES/IT `1,511` keys parity, desktop + mobile Playwright E2E + Axe accessibility) audited by three parallel independent Goldfish reviewers.

---

## 1. Final Release Readiness Summary

| Review Perspective / Scope | Wave 2 Verdict | Final Phase 3 Verdict | Final Summary |
|---|---|---|---|
| **D6 (EGM-003 Content Governance) — Full Stack** | **GO** | **`PASS` / `GO`** | Backend governance, multi-process PGSQL concurrency, Filament admin review/publish actions, published-tour draft fence (`422`), and Playwright desktop+mobile E2E (`tour-revision.spec.ts` `12/12`) + `partner-a11y` (`23/23` / `25/25`) all pass. |
| **Perspective 1: Backend Architecture, DB Invariants, Concurrency & Security** | `BLOCKED / FAIL` | **`PASS`** | Wave 3A hardening migration (`2026_11_21_000001`), transactional/savepoint D2 idempotency (`TourFirstSaveConcurrencyTest` `3/3` 2-worker PGSQL), partner departure REST API + daily `01:30` horizon job, canonical lock DAG (`Partner sharedLock -> Tour lockForUpdate -> TourDeparture lockForUpdate`), per-person tiered pricing isolation, `DeclinePendingBookingAction`, voucher freshness/7-key non-PII verification, and `1,712` Pest tests verified. |
| **Perspective 2: Frontend Behavior, UX, A11y, Localization & Responsive** | `BLOCKED / FAIL` | **`PASS`** | `TourWizard` + `tourWizard.ts` server draft sync & `STALE_DRAFT_CONFLICT` 409 modal, `Client-Creation-Id`, localized `/availability` & `/pricing` pages (`per_person` only, Rule 10 inline errors, booked-cancel gate), live `/departures` checkout + `PriceChangeModal` `/decline`, confirmation/detail/partner/voucher `/v/[ref]` views, 1,511-key EN/ES/IT parity, Jest `92/92`, and Axe `23/23` verified. |
| **Perspective 3: E2E Marketplace Journeys, Contracts & Release Readiness** | `NO-GO` | **`PASS` / `GO`** | All 4 EGM-005 real-stack desktop (`7/7`) & mobile (`7/7`) Playwright journeys plus core regression suites pass cleanly (`unarchived_slug_egm005=0`, `seeded_tour_1_2_active_revisions=0`); `throttle:booking.create` `10/min` matches `spec-007`; deterministic adapter gated to `local/testing`; ready for staging Stripe test-mode smoke & release. |

**Overall EGM-005 Phase A Release Verdict: `GO`**

---

## 2. Wave-by-Wave Independent Goldfish Audit Progression (Waves 3A–3E)

Following Wave 2 (which fixed Phase 2 blockers, core locking, expiry fences, DST sync, and D6 invariants while documenting five open release blockers in backend migrations, partner HTTP APIs, frontend wiring, vouchers, and E2E tests), Waves 3A–3E systematically closed and reviewed every blocker:

| Wave | Scope | Reviewer Conversation ID | Initial Verdict | Remediations Applied Prior to Final Gate | Final Wave Verdict | Evidence Log |
|---|---|---|---|---|---|---|
| **Wave 3A** | DB invariants & D2 concurrent first-save idempotency (`2026_11_21_000001_harden_egm005_database_invariants.php`, `TourService::createTour()`, `TourFirstSaveConcurrencyTest`) | `abb68a9c-8c64-4b03-af3c-54aa139e1547` | **PASS** | N/A (passed on first review after disposable PGSQL down->up->down->up rehearsal, savepoint `23505` handling, partial unique index, `CHECK (capacity >= 1)`, and `bookings` index) | **`PASS`** | `scratch/wave3a_verify.log`, `wave3a_fix_verify.log` |
| **Wave 3B** | D1 server draft recovery, pre-language `source_locale: null`, 409 `STALE_DRAFT_CONFLICT` modal, D2 `creation_id` wizard integration (`egm005-draft-recovery.spec.ts`) | `abf92583-3df7-4b7c-939e-18840ed1b6ab` | **PASS** | N/A (passed on first review; verified `client.ts` `server_draft` retention, UUID generation/reset, desktop + mobile E2E) | **`PASS`** | `scratch/wave3b_stage_a.log`, `wave3b_verify.log` |
| **Wave 3C** | Partner departure CRUD/generation REST API (`partner/TourDepartureController`), daily `01:30` `MaintainDepartureHorizonJob`, localized `/availability` UI (`egm005-departures.spec.ts`) | Initial: `356708e1-90b7-458c-87cf-828ed7155fe9` <br> Re-review: `3d22d659-f702-44d4-a047-3a12a9d0e581` | **FAIL** *(initial)* | Fixed accessibility contrast/focus-loop defects and departure cancellation / horizon edge findings surfaced in initial review `356708e1`; re-verified Pest `60/60`, `partner-a11y` `24/24`, desktop + mobile `egm005-departures` | **`PASS`** | `scratch/wave3c_verify.log` |
| **Wave 3D** | Strict `per_person` tiered pricing, `GetTourDetailAction` option tiers, localized `/pricing` UI, checkout `/departures` selector, real `PriceChangeModal` + `POST .../decline` (`egm005-tiered-pricing-booking.spec.ts`) | Initial: `9a81a0b2-f111-46bb-8f2f-7beaa329393b` <br> Re-review: `e4176832-a9e3-456b-8ccf-f995881e05d1` | **FAIL** *(initial)* | Resolved initial Option capacity isolation, option `group_size` checkout sync (`BookingForm`), and drift-decline edge findings surfaced in `9a81a0b2`; re-verified Pest `112/112`, `Jest` `10/10` (`219` tests), `partner-a11y` `25/25`, desktop + mobile `egm005-tiered-pricing-booking` | **`PASS`** | `scratch/wave3d_verify.log`, `wave3d_reverify.log` |
| **Wave 3E** | Voucher PDF (`VoucherService` hash & `booking.blade.php` EN/ES/IT labels), `PartnerBookingResource` tier serialization, `BookingConfirmation`, `BookingDetailView`, `Partner/BookingDetail`, zero-PII `/v/{ref}` preservation (`egm005-booking-voucher-lifecycle.spec.ts`) | `5bb9737d-3e82-4ef7-bfc7-b5c40cc8e5f4` | **PASS** | N/A (passed on first review; verified SC-008 status-only voucher cache reuse, `%PDF-` binary output, zero PII across `/api/public/v/{ref}` & `/v/{ref}`, cancellation seat restoration `6 -> 8`) | **`PASS`** | `scratch/wave3e_verify.log` |

---

## 3. Final Independent Three-Perspective Goldfish Review (Phase 3 Post-Regression)

After completing the full Phase 3 regression suite across backend, frontend, Playwright (desktop + mobile), and Axe accessibility (`scratch/final_regression.log`, `scratch/pest_full.log`), three fresh-context `research` subagents audited the complete codebase independently:

### Perspective 1 — Backend, Database, Concurrency & Security (`7527f9ec-760a-41c9-a48f-225fbc7db46a`) — Verdict: **`PASS`**
- **Verified Invariants:**
  - Historical migrations `2026_11_17_000001..000003` remain unmodified (`git diff` length `0`).
  - `2026_11_21_000001_harden_egm005_database_invariants.php` enforces pre-flight duplicate/capacity guards, adds `tour_drafts.creation_payload_hash`, creates partial unique index `tour_drafts_partner_id_creation_id_unique`, enforces PostgreSQL `CHECK (capacity >= 1)`, adds `bookings_departure_status_expiry_idx`, and rolls back cleanly in strict reverse order.
  - D1/D2: Atomic `DB::transaction()` + `lockForUpdate()` + savepoint SQLSTATE `23505` recovery in `TourService::createTour()` and `saveGeneralDraft()`, UUID parity & `hash_equals()` token checks, whitespace exemption in `bootstrap/app.php:64-68`, published-tour draft fence (`422`), and 2-worker PostgreSQL concurrency proof (`TourFirstSaveConcurrencyTest` `3/3`).
  - D3/D4: Canonical lock DAG (`Partner sharedLock -> Tour lockForUpdate -> TourDeparture lockForUpdate`), independent capacity scoped strictly by `tour_departure_id`, daily `01:30` `MaintainDepartureHorizonJob` w/ `withoutOverlapping()`, expired-hold late-webhook protection (`ConfirmBookingOnPayment`), `DeclinePendingBookingAction`, and D6 `404` owner eligibility.
  - D5 & Verification: Strictly `per_person` (`422` on `per_group`), integer cents, contiguous tier validation, option-scoped tier lookup (preventing legacy tour-tier leakage), status-excluded `VoucherService::contentHash()`, escaped `booking.blade.php` rendering, and immutable 7-key zero-PII `/api/public/v/{reference}`.
  - Backend quality: Pint (`800 files`), PHPStan (`0 errors`), Pest PGSQL (`1,712 passed / 9,391 assertions`).
- **Non-Blocking Hygiene Notes:**
  1. `Public/TourDepartureController.php:91-93` issues `$departure->reservedCapacity()` and `$departure->isBookable()` per departure row (covered by `bookings_departure_status_expiry_idx`; partner listing already uses batched lookup and public path can be batched optionally later).
  2. Legacy `POST /api/partner/tours/{id}/drafts/save` (`TourService::saveDraft` `:1279-1294`) writes only opaque JSONB without mutating canonical tour tables, though unlike `saveGeneralDraft()` it does not block requests on published tours with `422`.
  3. `storage/logs/laravel.log` (`~86 MB`, `www-data:www-data`) and `138` non-EGM005 E2E test tours (`id > 20`) remain in the local dev environment (`unarchived_slug_egm005=0`).

### Perspective 2 — Frontend Behavior, UX, Accessibility & Localization (`25a8ef95-b282-4fdb-8839-ac307969e94b`) — Verdict: **`PASS`**
- **Verified Invariants:**
  - `tourWizard.ts` (`frontend/src/lib/stores/tourWizard.ts`) & `TourWizard.tsx` preserve `source_locale: null` before language choice, synchronize debounced server drafts via `syncPromiseRef`, handle `409 STALE_DRAFT_CONFLICT` with focus-managed accessible `draft-conflict-panel` + `serverDraft` recovery (`client.ts`), transmit UUID `Client-Creation-Id` (`partner.ts`), and leave EGM-003 published editor governance intact (`edit/page.tsx`, `TourRevisionPanel.tsx`, `PartnerRichTextEditor.tsx` with `mobile: { toolbar_mode: 'sliding' }`).
  - `availability/page.tsx` supports recurring rules, exceptions, bulk/single departures, Rule 10 inline error display, and inline accessible booked-departure cancellation confirmation.
  - `pricing/page.tsx` enforces strict `per_person` brackets (contiguous helpers, open-ended top bracket, cent conversion, live traveler preview, `tablist`/`tab`/`tabpanel`, zero `per_group`).
  - `BookingForm.tsx`, `booking-selection.ts`, `TourBookingController.tsx`, `BookingCard.tsx`, & `PriceChangeModal.tsx` consume live `/departures`, reflect dynamic option tiers, prevent Wave 2 H1 tour vs option `group_size` mismatches, trap dialog focus properly, and call `POST .../decline` on cancel.
  - `BookingConfirmation.tsx`, `BookingDetailView.tsx`, `partner/bookings/BookingDetail.tsx`, & `app/v/[reference]/page.tsx` render departure time/tz, option, and rate tier cleanly with zero PII on `/v/{ref}`.
  - EN/ES/IT exact 1:1 parity across `1,511` keys (`0` diff), TypeScript `0` errors, ESLint `0` errors, Jest `92/92` (`1,024` tests), `partner-a11y.spec.ts` `23/23` (`0` Axe violations).
- **Non-Blocking UX/A11y/i18n Polish Notes:**
  1. Legacy raw inputs/selects in `availability/page.tsx` (`L426, 440, 455, 466`) and `edit/page.tsx` (`L1978`) retain `outline-none` without explicit `focus:ring-*` utilities (UI primitives and `pricing/page.tsx` retain visible focus rings).
  2. Legacy TourWizard step 4 `components/partner/tours/PricingTierForm.tsx` still clamps cleared `max_participants` to `1` (`L117`), whereas EGM-005 open-ended tiered pricing brackets are configured via `/partner/tours/[id]/pricing`.
  3. Minor pre-existing literal English strings remain in legacy views (`TourWizard.tsx:1208-1212` `" defined"`, `partner/bookings/BookingDetail.tsx:132,146` & `window.prompt` `:47`, `my-bookings/[reference]/client.tsx:120-130`, `edit/page.tsx:1772,1779` `"Manage Pricing"` / `"Manage Availability"`), and `BookingDetailView.tsx:26` formats via `Intl.NumberFormat('en', ...)`.

### Perspective 3 — E2E Marketplace Journeys, Contracts & Release Readiness (`0bceba97-d9a7-4dbb-896c-3bbb80ae5a49`) — Verdict: **`PASS`** | **`GO`**
- **Verified Invariants:**
  - All 4 EGM-005 Playwright specs (`egm005-draft-recovery`, `egm005-departures`, `egm005-tiered-pricing-booking`, `egm005-booking-voucher-lifecycle`) execute real Nginx -> Next.js -> Laravel -> PostgreSQL journeys (with real Filament admin publish & review) across desktop (`7/7`) and Pixel 7 mobile (`7/7`), leaving `unarchived_slug_egm005=0` and `seeded_tour_1_2_active_revisions=0`.
  - Audited why combining all 4 EGM-005 specs in a single browser burst hit `429` at `egm005-tiered-pricing-booking.spec.ts:411`: `RateLimiter::for('booking.create')` enforces `10/min` per user (`AppServiceProvider.php:166-181`, compliant with `specs/007-tour-booking/contracts/booking-api.md:116-125`), and the combined run fired 11+ shared-user mutations across `POST /bookings`, `/deterministic-payment/confirm`, `/cancel`, and `/decline` within 36–48 seconds. Splitting with `redis-cli FLUSHALL` passed `7/7` Desktop & `7/7` Mobile cleanly.
  - Confirmed `DeterministicPaymentGateway` and `/deterministic-payment/confirm` are strictly gated behind `app()->environment(['local', 'testing'])`.
- **Non-Blocking Test-Harness & Staging Pre-Cutover Notes:**
  1. `egm005-booking-voucher-lifecycle.spec.ts:192` opens its secondary `travelerContext` tab without copying Pixel 7 mobile viewport settings when running under `mobile-partner` (partner pages run in Pixel 7; secondary traveler tab uses default desktop viewport).
  2. Staging pre-cutover checks before production launch: verify `APP_ENV!=local|testing` and `PAYMENT_GATEWAY=stripe` (`/deterministic-payment/confirm` returns `404`), run a test-mode Stripe Elements + signed `payment_intent.succeeded` webhook + refund smoke test, confirm `SERVICES_VOUCHER_PUBLIC_BASE_URL`, and verify production scheduler (`01:30` horizon job) + queue workers.

---

## 4. Wave 2 Exit Criteria Sign-Off

All five Wave 2 release exit criteria in Section 5 of the previous report are now **closed and verified**:

1. **D1–D5 End-to-End Wiring & Browser E2E Coverage:** Complete (`TourWizard`, `tourWizard.ts`, `client.ts`, `/availability`, `/pricing`, `BookingForm`, `BookingConfirmation`, `BookingDetailView`, `BookingDetail`, `DeclinePendingBookingAction`, and all 4 desktop + mobile EGM-005 specs).
2. **Partner Departure HTTP API:** Complete (`routes/api/partner.php:93-97`, `Partner/TourDepartureController.php`, daily `MaintainDepartureHorizonJob`).
3. **Database Unique Constraints & Atomic Locking:** Complete (`2026_11_21_000001_harden_egm005_database_invariants.php`, UP/DOWN/UP rehearsal, `TourFirstSaveConcurrencyTest` 2-worker PGSQL pass).
4. **Voucher PDF & `/v/{reference}` Consistency:** Complete (`VoucherService`, `booking.blade.php`, `PartnerBookingResource`, `VoucherFreshnessTest` `8/8`, `VerificationTest` `7/7`, `voucher-verification.spec.ts` `16/16`).
5. **Accessibility, Focus/Localization & Regression Matrix:** Complete (`1,511` EN/ES/IT keys `0` diff, Jest `92/92`, `partner-a11y` `23/23` with `0` Axe violations, Pest `1,712/1,712`).
