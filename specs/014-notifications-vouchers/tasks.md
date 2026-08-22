---

description: "Task list for Spec 014 — Notifications and Vouchers"
---

# Tasks: Notifications and Vouchers

**Input**: Design documents from `/specs/014-notifications-vouchers/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/, quickstart.md — all present.

**Tests**: Included. The Bookly constitution (Testing & Quality Standards) mandates automated tests for critical business flows; the voucher-download and public-verification flows are critical. Tests run serially via `docker exec bookly-backend vendor/bin/pest` (per project memory: serial, pgsql, RefreshDatabase, never concurrent) and `npm run typecheck`/`npm run lint` on the frontend.

**Organization**: Tasks are grouped by user story (US1–US8 from spec.md) to enable independent implementation and testing. The backend notification/voucher infrastructure already substantially exists (see plan.md Summary) — most tasks are edits to existing classes plus one genuinely new additive surface (public voucher verification).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (e.g., US1, US8)
- Exact file paths are included in every task

## Path Conventions

- **Backend**: `backend/app/...`, `backend/database/...`, `backend/routes/...`, `backend/resources/...`, `backend/tests/...`
- **Frontend**: `frontend/src/...`, `frontend/messages/...`
- Pest feature tests live under `backend/tests/Feature/<Domain>/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Add the one new composer dependency, the config keys, the rate limiter, and the next-intl middleware exclusion that the later phases depend on.

- [X] T001 Install `simplesoftwareio/simple-qrcode` via `docker exec bookly-backend composer require simplesoftwareio/simple-qrcode` (registers `QrCode` facade; used by the voucher view in T007)
- [X] T002 [P] Add `voucher.public_base_url` config key (default `https://bookly.travel`) in backend/config/services.php under a `voucher` block — consumed by VoucherService (T006) for the QR URL host
- [X] T003 [P] Register a `throttle:verify` rate limiter (60 req/min/IP) in backend/app/Providers/AppServiceProvider.php `boot()` via `RateLimiter::for('verify', ...)` — used by the public verification route (T031) to deter reference enumeration
- [X] T004 [P] Edit frontend/src/proxy.ts (the project's authoritative next-intl middleware; do NOT create a second `frontend/src/middleware.ts` — that triggers next-intl's "Both files detected" error and breaks the dev server) so its `matcher` excludes `/v(/.*)?` and `/api(/.*)?` so the public verification page resolves at exactly `/v/{reference}` (no locale redirect) — per research R8

**Checkpoint**: Dependencies and config in place. No runtime behavior changed yet.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Voucher freshness columns + the `VoucherService` rewrite (QR URL + staleness) + the voucher Blade view (real QR, locale labels, dynamic status). These block US1, US2, US7, and US8 because they all depend on a correct, localized voucher PDF whose QR encodes the public URL.

**⚠️ CRITICAL**: No user story that touches the voucher PDF can begin until this phase is complete.

- [X] T005 Create migration backend/database/migrations/2026_07_04_100100_add_voucher_freshness_columns_to_bookings.php adding nullable `voucher_generated_at` (timestamp) and `voucher_content_hash` (string, length 64) to `bookings`; `down()` drops both columns (additive, reversible; backfills NULL — no data conversion). Add both to `Booking::$fillable` and `casts()` (`voucher_generated_at` → datetime) in backend/app/Domains/Booking/Models/Booking.php
- [X] T006 Rewrite VoucherService in backend/app/Domains/Booking/Services/VoucherService.php: (a) build the QR URL as `config('services.voucher.public_base_url') . '/v/' . $booking->reference` and pass it to the view as `qrUrl`; (b) `getOrGenerate($booking)` computes a content hash of `[tour_date, participant_count, locale-resolved tour_title, total_price, currency]`, and regenerates (updating `voucher_generated_at` + `voucher_content_hash`) when the file is missing, the stored hash is null, or the stored hash differs from the current hash — otherwise serves the cached file; (c) `generate($booking)` writes the new hash + timestamp. Remove the old JSON `$qrData` payload (FR-002, FR-018, R2, R3)
- [X] T007 [P] Update the voucher PDF view backend/resources/views/voucher/booking.blade.php: (a) replace the placeholder box with a real QR rendered via `QrCode::encoding('UTF-8')->size(120)->generate($qrUrl)` (embeddable SVG for DomPDF); (b) replace hard-coded English labels with an in-view locale map keyed by `$locale` (en/es/it, EN fallback) for Booking Reference / Date / Participants / Total Paid / Meeting Point / Traveler / Status / footer strings; (c) render the actual status word from `$booking->status` instead of the hard-coded `✓ Confirmed`; (d) reconcile footer host to `config('services.voucher.public_base_url')` (was `bookly.com`) (FR-002, FR-015, R1, R5)

**Checkpoint**: Voucher PDF now carries a real scannable QR encoding the public URL, renders in the booking's locale, and regenerates only when voucher-relevant fields change.

---

## Phase 3: User Story 1 — Booking Confirmation & Voucher Delivery (Priority: P1) 🎯 MVP

**Goal**: When a booking's payment is captured, the traveler receives one localized confirmation email and one localized voucher email (PDF attached), and the partner receives a new-booking notification.

**Independent Test**: A traveler completes a paid booking; `SendBookingConfirmationEmail` runs; the traveler receives exactly one localized confirmation email and one localized voucher email with a PDF (real QR, correct locale); the partner receives a new-booking notification. Re-running the job produces no duplicates.

> Note: `SendBookingConfirmationEmail` (queued, idempotent via `Cache::lock` + `confirmation_email_sent_at`, 3 tries, `failed()` → `BookingEmailDeliveryFailed`) already exists and is reused unchanged. The correctness of the voucher it attaches comes from Phase 2 (T006/T007).

- [X] T008 [P] [US1] Add/extend backend/tests/Feature/Booking/BookingConfirmationEmailTest.php: assert `Mail::fake()` queues exactly one `BookingConfirmedMail`, one `BookingVoucherMail`, and one `PartnerNewBookingMail` for a confirmed booking; assert `confirmation_email_sent_at` is set; re-dispatch the job and assert no second email trio (idempotency); assert voucher attachment content-type is `application/pdf` (FR-001, FR-003, FR-004, SC-001, US1 acceptance 1 & 4)
- [X] T009 [US1] Confirm backend/app/Domains/Booking/Jobs/SendBookingConfirmationEmail.php calls `VoucherService::generate($booking)` for the first-time confirmation flow (existing line 78 — keep `generate`, NOT `getOrGenerate`, since there is no cached file on first confirmation); the dashboard download path (US2, T011) uses `getOrGenerate` for freshness. No code change expected — verify and document in the test

**Checkpoint**: US1 fully functional and independently testable.

---

## Phase 4: User Story 2 — Voucher Download & Authorization (Priority: P1)

**Goal**: The booking owner can download their voucher for any post-payment, non-cancelled booking (`confirmed`, `completed`); non-owners, unauthenticated visitors, and cancelled bookings are refused; guests have no dashboard download path.

**Independent Test**: Owner download → 200 PDF; different authenticated traveler → 404; unauthenticated → 401; cancelled booking → 404; `completed` booking → 200; guest (no account) → no path (auth-gated).

- [X] T010 [P] [US2] Add backend/tests/Feature/Booking/VoucherDownloadTest.php: owner of a `confirmed` booking → 200 + `application/pdf`; owner of a `completed` booking → 200; a different authenticated traveler → 404; unauthenticated → 401; owner of a `cancelled` booking → 404; assert the served PDF is the current content (freshness from T006) (FR-007, FR-008, FR-009, SC-002, US2 acceptance 1–5)
- [X] T011 [US2] Widen the download guard in backend/app/Domains/Booking/Controllers/Public/VoucherController.php from `where('status', STATUS_CONFIRMED)` to `whereIn('status', [STATUS_CONFIRMED, STATUS_COMPLETED])`; switch the call from `generate` to `getOrGenerate` so staleness (T006) is honored on download; keep `where('traveler_id', $request->user()->id)` + `firstOrFail()` (404 for non-owner / cancelled / other). The route's `auth:sanctum` middleware already blocks unauthenticated and guests (FR-007, FR-008, FR-009, R10)

**Checkpoint**: US2 fully functional; US1 + US2 are independently testable.

---

## Phase 5: User Story 3 — Partner Lifecycle & Booking Notifications (Priority: P1)

**Goal**: Partners receive in-app + email notifications for new booking, cancellation, and governance decisions (approve/reject/suspend with reason). The partner dashboard shows a live unread indicator.

**Independent Test**: A booking is made on a partner's tour → partner's unread count rises, the notification appears in their list, an email is sent; marking read drops the count; a partner never sees another partner's notifications.

> Note: The partner `Notification` model + `NotificationController` (index/unread_count/markRead/markAllRead, partner-scoped) already exist (FR-016) and are reused. The gap is the live unread indicator (FR-017) — `NotificationBell` exists but is unmounted.

- [X] T012 [P] [US3] Add backend/tests/Feature/Partner/NotificationTest.php: new confirmed booking → partner has an unread `new_booking` notification and `meta.unread_count` reflects it; `unread_only=true` filters to unread; `POST /notifications/{id}/read` marks one read and drops the count; `POST /notifications/read-all` clears all; a different partner sees zero of this partner's notifications (ownership scope) (FR-016, US3 acceptance 1, 4, 5)
- [X] T013 [P] [US3] Add backend/tests/Feature/Partner/PartnerLifecycleEmailTest.php: approve → `PartnerApprovedMail` sent; reject with reason → `PartnerRejectedMail` sent with the reason in the body; suspend with reason → suspension email sent with reason; new booking → `PartnerNewBookingMail` sent; cancellation → `PartnerBookingCancelledMail` sent (FR-004, FR-005, FR-006, US3 acceptance 1–3)
- [X] T014 [US3] Wire the existing frontend/src/components/partner/layout/NotificationBell.tsx into frontend/src/components/partner/layout/PartnerHeader.tsx: add a `useQuery` (react-query) fetching `GET /api/partner/notifications?per_page=1` and reading `meta.unread_count`, with `staleTime: 60_000` + refetch-on-window-focus + refetch-on-mutation; render the `NotificationBell` with the live count badge (never a static zero). Reuse the existing `unreadCount` prop path in `PartnerHeader` (FR-017, SC-007, R11)
- [X] T015 [P] [US3] Add any new partner-notification UI strings (bell aria-label, empty state, mark-all-read) to frontend/messages/en.json, frontend/messages/es.json, frontend/messages/it.json under `partner.notifications` (parity across all three locales)

**Checkpoint**: US3 fully functional; the partner dashboard surfaces a live unread count and the notification list / mark-read flows work.

---

## Phase 6: User Story 4 — Delivery Resilience & Failure Handling (Priority: P2)

**Goal**: Notification delivery is queued, retry-safe, idempotent, and bounded; exhausted retries raise a failure event WITHOUT altering the booking status; voucher-generation failure does not block the confirmation email.

**Independent Test**: Force the confirmation email job to fail repeatedly; after the retry budget exhausts, `BookingEmailDeliveryFailed` fires and `failed()` logs; the booking stays `confirmed`; no duplicate emails were sent during retries; a voucher-generation failure still lets the confirmation email through.

> Note: All of this already exists in `SendBookingConfirmationEmail` (3 tries, 900s backoff, `Cache::lock` + `confirmation_email_sent_at` guard, `failed()` hook, voucher try/catch). These tasks add test coverage and a regression guard.

- [X] T016 [P] [US4] Add backend/tests/Feature/Booking/ConfirmationEmailIdempotencyTest.php: double-dispatch the job (force the lock to be re-acquirable) and assert only one email trio is sent; simulate a transient failure mid-flight and assert the retry does not duplicate emails; assert partial-success retry semantics (if email was already sent before failure, re-run creates in-app notification without duplicating the email) (FR-010, FR-011, SC-001, SC-003, US4 acceptance 1, 3)
- [X] T017 [P] [US4] Add backend/tests/Feature/Booking/ConfirmationEmailFailureTest.php: force all retries to exhaust → assert `BookingEmailDeliveryFailed` event is dispatched, the `failed()` hook logs at ERROR, and the booking's `status` is unchanged (still `confirmed`); separately simulate a voucher-generation failure and assert the confirmation email is still delivered + a warning is logged (FR-013, FR-019, SC-005, US4 acceptance 2, 4)

**Checkpoint**: US4 covered by tests; no production code change required unless a test reveals a gap (then fix the minimal gap and re-run serially).

---

## Phase 7: User Story 5 — Admin Visibility of Delivery Failures (Priority: P2)

**Goal**: An exhausted delivery failure raises an ERROR log entry (always) and a best-effort Slack webhook alert (when configured) with operational context; no in-app admin notification surface is introduced.

**Independent Test**: A delivery fails to exhaustion; an ERROR log entry references the booking + failure reason; if Slack is configured, an alert is posted; if Slack is not configured, only the log entry exists; no `admin_notifications` table / Filament resource / admin inbox is introduced.

> Note: `NotifyAdminOnEmailDeliveryFailure` already implements the log + Slack path. These tasks add tests + a regression guard against reintroducing an admin notification surface.

- [X] T018 [P] [US5] Add backend/tests/Feature/Booking/AdminDeliveryFailureAlertTest.php: with Slack webhook configured (`config('services.slack.admin_webhook_url')` set) → assert an HTTP POST is attempted (use `Http::fake()`) AND an ERROR log entry is written; with the webhook unset → assert only the ERROR log entry, no HTTP POST; assert the alert payload includes the booking reference and NEVER includes payment info or PII beyond locating the booking; assert Slack failure does not fail the listener (FR-012, FR-019, US5 acceptance 1–4)
- [X] T019 [US5] Add a regression-guard test in backend/tests/Feature/Booking/AdminDeliveryFailureAlertTest.php asserting: no `admin_notifications` table exists (`Schema::hasTable('admin_notifications')` is false), no admin `Notification` model class exists (`class_exists('App\\Domains\\Admin\\Models\\Notification')` is false), and no Filament `NotificationResource` exists — locking the FR-012 / US5 "no new admin surface" constraint (FR-012, US5 acceptance 5)

**Checkpoint**: US5 covered; the alerting path and the no-new-admin-surface constraint are both locked by tests.

---

## Phase 8: User Story 6 — Localization of Notifications (Priority: P2)

**Goal**: Traveler-facing emails (confirmation, voucher, cancellation) and partner-facing emails render in the booking's / partner's locale (en/es/it) with EN fallback; the voucher PDF labels follow the booking's locale.

**Independent Test**: A booking with `locale=it` → confirmation + voucher emails render in Italian, voucher PDF labels in Italian; a partner user with `locale=es` → governance emails in Spanish; a locale with a missing partner template → EN fallback (not an empty body, not a crash).

> Note: Traveler emails are already localized (`BookingConfirmedMail` / `BookingVoucherMail` / `BookingCancelledMail` select per-locale views with EN fallback). The voucher PDF labels are handled in Phase 2 (T007). The gap is the **partner** mailables, which currently use a single non-localized view each.

- [X] T020 [P] [US6] Localize backend/app/Mail/PartnerApprovedMail.php, backend/app/Mail/PartnerRejectedMail.php, backend/app/Mail/PartnerSuspendedMail.php, backend/app/Mail/PartnerBookingCancelledMail.php, and backend/app/Mail/PartnerNewBookingMail.php: select the view as `emails.partner.{event}.{$partnerLocale}` with EN fallback (copy the proven `BookingConfirmedMail` pattern — `view()->exists(...)` check); localize the subject via a `getLocalizedSubject()` match on locale; keep the rejection/suspension `reason` passed through verbatim; source locale from the partner `User->locale` (confirmed fillable) with `en` fallback (FR-006, FR-014, R4)
- [X] T021 [P] [US6] Add per-locale partner email views: backend/resources/views/emails/partner/approved/{en,es,it}.blade.php, backend/resources/views/emails/partner/rejected/{en,es,it}.blade.php, backend/resources/views/emails/partner/suspended/{en,es,it}.blade.php, backend/resources/views/emails/partner/booking-cancelled/{en,es,it}.blade.php, backend/resources/views/emails/partner/new-booking/{en,es,it}.blade.php — EN is the source; es/it translate subject + body; the rejection/suspension views render `{!! $reason !!}` (FR-006, FR-014, R4)
- [X] T022 [P] [US6] Add backend/tests/Feature/Booking/EmailLocalizationTest.php: booking `locale=es` → confirmation + voucher emails render Spanish subject + body (assert via `Mail::fake()->assertSent(BookingConfirmedMail::class, fn ($m) => $m->envelope()->subject === <Spanish subject>)` or rendered view content); booking `locale=it` → voucher PDF labels Italian (assert the `VoucherService`-generated view receives `locale=it`); partner user `locale=es` → `PartnerRejectedMail` and `PartnerSuspendedMail` render Spanish views; missing-locale variant → EN fallback (no empty body) (FR-014, FR-015, SC-006, US6 acceptance 1–4)

**Checkpoint**: US6 fully functional; all traveler + partner emails and the voucher PDF render in the correct locale with EN fallback.

---

## Phase 9: User Story 7 — Voucher Regeneration & Freshness (Priority: P3)

**Goal**: A confirmed booking whose date or participant count changes yields a fresh voucher on the next download; unchanged bookings reuse the cached PDF.

**Independent Test**: Generate a voucher; change `participant_count`; the next download returns a PDF reflecting the new count; change nothing; the next download reuses the cached file (no regeneration).

> Note: The freshness mechanism is implemented in Phase 2 (T005 migration + T006 `VoucherService::getOrGenerate` hash check). This phase adds test coverage.

- [X] T023 [P] [US7] Add backend/tests/Feature/Booking/VoucherFreshnessTest.php: generate a voucher (file exists, `voucher_generated_at` + `voucher_content_hash` set); download → serves cached file (assert no regeneration); update `participant_count` → download → serves a regenerated PDF with the new count (assert `voucher_content_hash` changed and `voucher_generated_at` advanced); update `tour_date` → same regeneration; update a non-voucher field (e.g., `status` `confirmed`→`completed`) → download → reuses cached file (hash unchanged, since status is not part of the hash) (FR-018, SC-008, US7 acceptance 1 & 2, R3)

**Checkpoint**: US7 covered; the freshness logic from Phase 2 is locked by tests.

---

## Phase 10: User Story 8 — Public Voucher Verification (Priority: P1)

**Goal**: Anyone holding a voucher can scan the QR, land on a public read-only page at `/v/{reference}` that shows VALID/CANCELLED/PENDING/EXPIRED + the allowed public fields, with no auth, no PII, no enumeration, no side effects, and no navigation to private surfaces.

**Independent Test**: A confirmed booking's QR resolves to `/v/{reference}` → page shows VALID with tour title + date + participants; a cancelled booking → CANCELLED; pending → PENDING; expired → EXPIRED; unknown → 404; no field exposes PII or internal IDs; the endpoint writes nothing.

> Note: This is the only genuinely NEW surface in the spec — everything else is an edit to an existing class. It is additive (FR-028).

### Tests for User Story 8

- [X] T024 [P] [US8] Add backend/tests/Feature/Booking/VerificationTest.php: `confirmed` → 200 `VALID`; `completed` → 200 `VALID`; `cancellation_requested` → 200 `VALID`; `cancelled` → 200 `CANCELLED`; `pending_payment` → 200 `PENDING`; `expired` → 200 `EXPIRED`; `no_show` → 200 `EXPIRED`; unknown reference → 404; malformed reference (not matching `^BKO-[A-HJ-NP-TV-Z2-9]{6}$`) → 404; assert the response body contains ONLY `{reference, status, tour_title, tour_date, participant_count}` and never `traveler_name/email/phone/payment_*/guest_identity/id/partner_*`; assert a repeated call writes no row to any audit/log table and increments no counter (FR-021..FR-025, FR-026, SC-010, SC-011, US8 acceptance 1–6, R6, R7)
- [X] T025 [P] [US8] Add a future-USED-compatibility assertion in backend/tests/Feature/Booking/VerificationTest.php: assert the `VerificationResult` status enum is open for extension (the `status` field is a string, not a closed enum that would block a future `USED`) and that the QR URL format `https://bookly.travel/v/{reference}` is independent of status (so adding `USED` later does not change existing QRs) (FR-023, US8 acceptance 7, R7)

### Implementation for User Story 8

- [X] T026 [P] [US8] Create backend/app/Domains/Booking/DTOs/VerificationResult.php (read-only DTO: `reference`, `status`, `tour_title`, `tour_date`, `participant_count`, optional `created_at` / `voucher_generated_at`) AND backend/app/Domains/Booking/Actions/VerificationAction.php with `execute(string $reference): ?VerificationResult` — validate the reference shape (`^BKO-[A-HJ-NP-TV-Z2-9]{6}$`, short-circuit to null → controller 404s), resolve `Booking::where('reference', $reference)->firstOrFail()`, map status per R7 (`confirmed`/`cancellation_requested`/`completed` → `VALID`, `cancelled` → `CANCELLED`, `pending_payment` → `PENDING`, `expired`/`no_show` → `EXPIRED`), and return the `VerificationResult` DTO. Single source of truth — no business logic in the controller or page (FR-022, FR-026, R7)
- [X] T027 [P] [US8] Create backend/app/Domains/Booking/Transformers/VerificationTransformer.php with `transform(Booking $booking): array` returning ONLY `{reference, status, tour_title, tour_date, participant_count}` (+ optional `created_at`, `voucher_generated_at` when present), field-by-field (never `(array)$booking`). Resolve `tour_title` in the booking's locale with EN fallback (same logic as `VoucherService`). PII guard enforced here (FR-022, SC-010)
- [X] T028 [P] [US8] Create backend/app/Domains/Booking/Controllers/Public/VerificationController.php — thin: inject `VerificationAction`, `show(string $reference)` calls `execute($reference)`, returns `response()->json(['data' => $transformer->transform(...)])` on hit and `abort(404, 'Not found.')` on miss. No auth, no business logic, no DB access in the controller (FR-021, FR-026, R6)
- [X] T029 [US8] Register `GET /api/public/v/{reference}` in backend/routes/api/public.php with `->middleware('throttle:verify')` (T003) and `Cache-Control: no-store` on the response (so a status change between a scan and a refresh is reflected) (FR-021, FR-025, SC-011, R6)
- [X] T030 [P] [US8] Add the verification-page strings (status labels VALID/CANCELLED/PENDING/EXPIRED, "Booking reference", "Tour", "Date", "Participants", "Not found", page title) to frontend/messages/en.json, frontend/messages/es.json, frontend/messages/it.json under a new `verification` namespace — parity across all three locales
- [X] T031 [US8] Create frontend/src/app/v/[reference]/page.tsx — a Next.js Server Component that calls `GET {API}/api/public/v/{reference}` (server-side fetch), renders a large prominent status indicator + the allowed fields only, includes `<meta name="robots" content="noindex,nofollow">`, has no navigation to private surfaces, renders a "not found" state on 404, and picks the display locale from the `Accept-Language` header (en/es/it, default en). No client auth, no dashboard chrome (FR-027, R8, R9)
- [X] T032 [P] [US8] Add a Playwright E2E test under frontend/tests/e2e/ (the project's E2E convention) verifying `/v/{reference}` renders the VALID status + allowed fields for a confirmed booking, renders CANCELLED for a cancelled booking, and renders the not-found state for an unknown reference — assert `noindex` meta is present and no PII appears in the rendered HTML (FR-027, SC-010, US8 acceptance 1, 2, 5)

**Checkpoint**: US8 fully functional — the public verification surface is live, read-only, PII-free, and enumeration-safe. All eight user stories are now independently testable.

---

## Phase 11: Polish & Cross-Cutting Concerns

**Purpose**: Whole-feature validation, parity checks, and backward-compatibility regression.

- [X] T033 [P] Run the backend test suites serially via `docker exec bookly-backend vendor/bin/pest tests/Feature/Booking` and `docker exec bookly-backend vendor/bin/pest tests/Feature/Partner` (serial, pgsql, never concurrent — per project memory) and confirm no new failures beyond documented pre-existing ones
- [X] T034 [P] Run `cd frontend && npm run typecheck && npm run lint` and confirm the edited/new frontend files compile cleanly
- [X] T035 [P] Run an i18n parity check: confirm every new key added under `partner.notifications` and `verification` exists in all three of frontend/messages/en.json, frontend/messages/es.json, frontend/messages/it.json
- [X] T036 [P] Backward-compatibility regression (FR-028): run `docker exec bookly-backend vendor/bin/pest tests/Feature/Admin tests/Feature/Search` serially and confirm the verification surface / QR change / voucher-guard widening / partner-email localization introduced no new failures in booking, payments, partner dashboard, traveler dashboard, search, or admin moderation
- [X] T037 [P] Run the end-to-end quickstart in specs/014-notifications-vouchers/quickstart.md (sections 2–6) against the running Docker stack and confirm every curl/browser check matches the expected result
- [X] T038 [P] Static guard: grep the diff to confirm no `admin_notifications` migration, no `App\Domains\Admin\Models\Notification` class, no Filament `NotificationResource`, no `vouchers` / `verifications` table was introduced (FR-012, FR-025, data-model §6), and no automated partner payout notification artifacts (no `PartnerPayout*Mail` / `*Payout*` mailable, job, or queued listener) were introduced (FR-020, Out-of-Scope §1)

**Checkpoint**: Feature complete, validated, backward-compatible, and constraint-compliant.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately. T001 must complete before T007 (QR facade). T002 before T006/T007 (config). T003 before T029 (limiter). T004 before T031 (middleware exclusion).
- **Foundational (Phase 2)**: Depends on Phase 1 (T001, T002). T005 before T006 (columns used by freshness). T006 before T007 (view consumes `qrUrl`). **BLOCKS US1, US2, US7, US8** (all touch the voucher PDF).
- **User Stories (Phases 3–10)**: All depend on Foundational where they touch the voucher (US1, US2, US7, US8). US3, US4, US5, US6 do NOT depend on Foundational and can start after Setup (they touch partner notifications / emails / alerting, not the voucher PDF).
- **Polish (Phase 11)**: Depends on all desired user stories being complete.

### User Story Dependencies

- **US1 (P1)**: depends on Phase 2 (voucher correctness). No dependency on other stories.
- **US2 (P1)**: depends on Phase 2 (T006 `getOrGenerate` freshness). No dependency on other stories.
- **US3 (P1)**: depends on Phase 1 only (frontend middleware not required for US3). No dependency on other stories.
- **US4 (P2)**: depends on Phase 1 only (tests the existing job). No dependency on other stories.
- **US5 (P2)**: depends on Phase 1 only (tests the existing listener). No dependency on other stories.
- **US6 (P2)**: depends on Phase 1 only. Voucher-PDF-locale is handled in Phase 2 (T007); partner-email localization (T020/T021) is independent. No dependency on other stories.
- **US7 (P3)**: depends on Phase 2 (T005/T006 freshness). No dependency on other stories.
- **US8 (P1)**: depends on Phase 2 (T006 QR URL) for the QR payload; the verification API + page themselves depend only on Phase 1 (T003 limiter, T004 middleware). No dependency on other stories.

### Within Each User Story

- Tests first (write, watch fail, then implement) where a task pair exists (e.g., T024/T025 tests before T026–T032 implementation for US8).
- DTO/Transformer before Action before Controller before Route (US8: T026 → T027 → T028 → T029).
- Backend endpoint before frontend page that consumes it (US8: T029 before T031).

### Parallel Opportunities

- **Phase 1**: T002, T003, T004 are [P] (different files) — run in parallel after T001.
- **Phase 2**: T007 is [P] relative to T005/T006 once T006 lands (the view only needs the `qrUrl` contract; T005 and T006 are sequential).
- **Phase 3–10 cross-story**: US3, US4, US5, US6 can all run in parallel (different files, no cross-dependency) after Phase 1. US1, US2, US7, US8 voucher-touching tasks run after Phase 2.
- **Within US8**: T026, T027, T028, T030, T032 are [P] (different files); T029 depends on T026–T028 + T003; T031 depends on T029 + T030 + T004.
- **Within US6**: T020, T021, T022 are [P] (mailables / views / tests are different files).

---

## Parallel Example: User Story 8 (after Phase 2)

```text
# Launch the parallel-safe US8 tasks together:
Task: "T024 VerificationTest (backend) in backend/tests/Feature/Booking/VerificationTest.php"
Task: "T025 Future-USED compatibility assertion in backend/tests/Feature/Booking/VerificationTest.php"   # same file — sequence after T024
Task: "T026 VerificationAction in backend/app/Domains/Booking/Actions/VerificationAction.php"
Task: "T027 VerificationTransformer in backend/app/Domains/Booking/Transformers/VerificationTransformer.php"
Task: "T028 VerificationController in backend/app/Domains/Booking/Controllers/Public/VerificationController.php"
Task: "T030 verification i18n keys in frontend/messages/{en,es,it}.json"

# Then sequence:
Task: "T029 Register route in backend/routes/api/public.php"           # after T026–T028 + T003
Task: "T031 Next.js page in frontend/src/app/v/[reference]/page.tsx"     # after T029 + T030 + T004
Task: "T032 Frontend verification test"                                  # after T031
```

---

## Implementation Strategy

### MVP First (User Story 1 + the verification surface, US8) — recommended

1. Complete Phase 1 (Setup) — T001–T004.
2. Complete Phase 2 (Foundational) — T005–T007. **CRITICAL — blocks US1/US2/US7/US8.**
3. Complete Phase 3 (US1) — T008–T009. **STOP and VALIDATE**: a confirmed booking produces one localized confirmation email, one localized voucher email with a real scannable QR, and a partner new-booking notification.
4. Complete Phase 10 (US8) — T024–T032. **STOP and VALIDATE**: scanning the QR opens `/v/{reference}` showing VALID with the public fields; unknown → 404; no PII.
5. Deploy/demo the MVP (the marketplace's core trust artifact + its public verification).

### Incremental Delivery

1. Setup + Foundational → foundation ready (correct voucher PDF with real QR + freshness).
2. + US1 → confirmation + voucher delivery works (MVP core).
3. + US8 → public verification works (MVP complete).
4. + US2 → owner-only download with `completed` eligibility.
5. + US3 → partner in-app notifications + live unread indicator.
6. + US4 + US5 → resilience + admin alerting (mostly already implemented; add tests + the no-admin-surface guard).
7. + US6 → partner-email localization.
8. + US7 → voucher freshness tests.
9. Polish (Phase 11) → whole-feature validation + backward-compatibility regression.

### Parallel Team Strategy

With multiple developers after Phase 2:

- Developer A: US1 + US2 + US7 (voucher thread — same files, sequence them).
- Developer B: US8 (verification surface — mostly independent new files).
- Developer C: US3 (partner notifications — frontend + existing backend).
- Developer D: US4 + US5 + US6 (resilience tests + partner-email localization — different files, parallel-safe).

---

## Notes

- **[P]** tasks touch different files and have no dependency on incomplete tasks.
- **[Story]** labels map each task to a user story for traceability.
- Each user story is independently completable and testable (run its test file in isolation).
- Verify tests fail before implementing (TDD where a test/impl pair exists).
- Commit after each task or logical group; run the affected Pest suite serially after backend changes (per project memory: never run Pest concurrently in the container).
- Stop at any checkpoint to validate a story independently.
- The spec reuses existing infrastructure — most tasks are edits, not greenfield. The only genuinely new surface is US8 (public verification).