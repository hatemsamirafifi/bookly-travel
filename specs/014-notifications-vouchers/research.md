# Research — Notifications and Vouchers (Spec 014)

**Branch**: `014-notifications-vouchers` | **Phase**: 0 (resolves all Technical Context unknowns)

> Grounded in a direct read of the current codebase. Each item states the Decision, Rationale, and Alternatives considered. No `[NEEDS CLARIFICATION]` markers remain after this file.

---

## R1. QR code generation in the voucher PDF

**Decision**: Add `simplesoftwareio/simple-qrcode` (Laravel `QrCode` facade) and render an embeddable QR image inside `resources/views/voucher/booking.blade.php`, replacing the current placeholder box.

**Evidence**: `voucher/booking.blade.php:71-77` is a **placeholder** — a bordered `<div>` showing the literal text `QR: {reference}` with a comment `requires a QR package like simplesoftwareio/simple-qrcode`. `VoucherService::generate()` builds a `$qrData` JSON payload and passes it to the view, but the view ignores `$qrData` and renders the placeholder. There is **no real QR code today**; FR-002 requires a scannable QR.

**Rationale**: `simplesoftwareio/simple-qrcode` is the de-facto Laravel QR integration, generates SVG or PNG that DomPDF can embed, and its API (`QrCode::encoding('UTF-8')->generate($url)`) is one line. It is MIT-licensed and compatible with PHP 8.4 / Laravel 11.

**Alternatives considered**:
- `endroid/qr-code` — equally capable, more low-level; rejected as it needs more boilerplate for the same one-call use case.
- Inline hand-rolled QR — rejected (QR encoding is non-trivial and error-prone).

---

## R2. QR payload — public verification URL, config-driven

**Decision**: The QR encodes `{public_base_url}/v/{booking_reference}` where `public_base_url` comes from a new config key `services.voucher.public_base_url` (default `https://bookly.travel`). The voucher view and `VoucherService` consume this config so the host is not hard-coded.

**Evidence**: `VoucherService::generate()` currently builds `$qrData = json_encode(['ref' => ..., 'date' => ..., 'pax' => ...])` (lines 24-28). The spec (FR-002, SC-009) mandates the public URL, explicitly **not** the bare reference and **not** a JSON payload.

**Rationale**: Config-driven host survives environment differences (local: `http://localhost:3000`, staging, prod `https://bookly.travel`). The footer of `voucher/booking.blade.php:80` currently says `bookly.com`; the spec says `bookly.travel` — reconcile to the config value in the same edit.

**Alternatives considered**:
- Hard-code `https://bookly.travel/v/{ref}` — rejected (breaks local/E2E scanning and staging).
- Encode only the reference — rejected (FR-002 explicitly forbids the bare reference).

---

## R3. Voucher freshness / staleness (FR-018, SC-008)

**Decision**: Add two columns to `bookings`: `voucher_generated_at` (timestamp, nullable) and `voucher_content_hash` (string, nullable). `VoucherService::getOrGenerate($booking)` recomputes the hash of the voucher-relevant fields (`tour_date`, `participant_count`, tour title for the booking locale, currency/total) and, if the stored hash differs OR the PDF file is missing OR `voucher_generated_at` is null, regenerates and updates both fields; otherwise it serves the cached file.

**Evidence**: `VoucherService::getOrGenerate()` (lines 61-70) only checks `file_exists($path)` — it **cannot** detect that `tour_date`/`participant_count` changed after confirmation, so a stale PDF would be served (FR-018 violation).

**Rationale**: A content hash is precise — it does **not** regenerate on status-only changes (e.g., `confirmed` → `completed`), which keeps the spec's "unchanged bookings reuse the existing voucher" requirement (SC-008). A pure `updated_at` comparison would over-regenerate because any booking update (cancellation, status change) bumps `updated_at` even when the voucher content is unchanged. The hash is computed in PHP (no DB function dependency) and stored cheaply.

**Alternatives considered**:
- Compare `voucher_generated_at < updated_at` — rejected (over-regenerates on status-only updates).
- Store nothing; always regenerate — rejected (FR-018 explicitly requires reuse for unchanged bookings; SC-008 forbids unnecessary regeneration).
- A separate `voucher_meta` table — rejected (over-engineering; the hash belongs on the booking).

**Migration**: `2026_07_04_100100_add_voucher_freshness_columns_to_bookings.php` (nullable columns, backfill NULL for existing rows — no data conversion needed). Add to the same domain migration numbering used by Spec 013.

---

## R4. Partner-email locale source (FR-006, FR-014)

**Decision**: Localize partner mailables to the **partner user's** `locale` (the `User` model has a fillable `locale` field, confirmed at `app/Models/User.php:37`), with `en` fallback. Add per-locale Blade views `resources/views/emails/partner/{approved,rejected,booking-cancelled,new-booking}/{en,es,it}.blade.php` mirroring the traveler pattern (`emails.booking.confirmed.{locale}`). Each mailable selects the view with the EN fallback exactly as `BookingConfirmedMail` does (lines 29-37, 50).

**Evidence**: `PartnerRejectedMail` (and the other partner mailables) currently use a single non-localized view `emails.partner.rejected` and a hard-coded English subject `'Your Partner Application Status — Bookly'` (line 24). The rejection/suspension **reason** is already passed and rendered (`'reason' => $this->reason`, line 35) — keep that. `BookingConfirmedMail` demonstrates the proven localized-view-with-fallback pattern to copy.

**Rationale**: The partner's `User->locale` is the natural per-partner locale (a Spanish-speaking partner gets the Spanish email). EN fallback satisfies FR-014 ("with EN as the fallback when a localized template is missing"). The reason is preserved verbatim regardless of locale (the reason text is operator-authored, not a template string).

**Alternatives considered**:
- Localize only the subject, keep a single body — rejected (FR-014 says "subject and body").
- Use the booking's locale for partner emails — rejected (partner emails are partner-scoped, not booking-scoped; the partner's own locale is correct).

---

## R5. Voucher PDF localization (FR-015)

**Decision**: Make `resources/views/voucher/booking.blade.php` render labels from the `$locale` passed by `VoucherService` (it already passes `'locale' => $locale`, line 37). Introduce a small translation map in the view (or a PHP array keyed by locale) for the fixed labels (`Booking Reference`, `Date`, `Participants`, `Total Paid`, `Meeting Point`, `Traveler`, `Status`, the status word, the footer strings). Also render the actual status word instead of the hard-coded `✓ Confirmed` (line 66).

**Evidence**: The voucher view is a single Blade file with hard-coded English labels (lines 35-80). `VoucherService` already selects the tour title in the booking's locale (lines 20-22) but the surrounding chrome is English-only.

**Rationale**: FR-015 requires the voucher's labels **and** content to follow the booking locale with EN fallback. A lightweight in-view translation map (3 locales × ~8 strings) avoids standing up a full lang-file system for Blade (the project's emails already use per-locale view files, but the voucher is one PDF; an in-view map is the minimal, consistent choice). EN is the source; `es`/`it` maps fall back to EN keys for any missing entry.

**Alternatives considered**:
- Per-locale voucher views (`voucher/booking.{en,es,it}.blade.php`) — rejected (triples a large HTML template for ~8 strings; the in-view map is far less duplication).
- Laravel lang files (`lang/en/voucher.php`) + `__('...')` — acceptable, slightly more wiring; the in-view map keeps the change local to the one file this spec already edits.

---

## R6. Public verification route + reference validation (FR-021, FR-024, SC-010, SC-011)

**Decision**: Register `GET /api/public/v/{reference}` in `routes/api/public.php`, **unauthenticated**, behind a dedicated `throttle:verify` limiter (60 req/min/IP) to deter reference enumeration. The controller resolves the booking by `reference` only (never by numeric ID) via `Booking::where('reference', $reference)->firstOrFail()` (404 for unknown). `firstOrFail` returns 404 uniformly, so no content/timing side-channel reveals whether a reference exists (SC-010, SC-011).

**Reference shape guard**: `Booking::REFERENCE_PREFIX` is `BKO-` and the alphabet is `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (length 6) — confirmed at `app/Domains/Booking/Models/Booking.php:39-43`. Add a route-model-binding or in-action regex guard `^BKO-[A-HJ-NP-TV-Z2-9]{6}$` so malformed references 404 without hitting the DB (cheap rejection, no enumeration signal). Valid-but-absent references also 404 (identical response).

**Rationale**: The opaque reference is the public lookup key (FR-024); numeric IDs are never accepted. A 60/min limiter is generous for legitimate scans (a ticket-taker scans one voucher at a time) but blunts brute-force. The endpoint writes nothing (FR-025, SC-011) — no audit log, no counter, no visitor tracking.

**Alternatives considered**:
- No rate limit — rejected (enumeration risk; the spec explicitly worries about attackers enumerating references, Edge Cases).
- `throttle:auth` (10/min) — rejected (too strict for a ticket-taker repeatedly scanning; not an auth endpoint).
- Authenticate the endpoint — rejected (FR-021 requires no authentication; the page must work for any scanner).

---

## R7. Verification status mapping (FR-023)

**Decision**: `VerificationAction` maps `Booking->status` → verification status as follows:

| Booking status | Verification status |
|---|---|
| `confirmed` | `VALID` |
| `cancellation_requested` | `VALID` (still a confirmed booking; cancellation only requested) |
| `completed` | `VALID` (spec: "confirmed or completed") |
| `cancelled` | `CANCELLED` |
| `pending_payment` | `PENDING` |
| `expired` | `EXPIRED` |
| `no_show` | `EXPIRED` (no longer valid for entry — the tour passed without attendance) |

The design leaves room for a future `USED` value (redeemed) without changing the QR format or URL scheme (FR-023): `USED` would be a distinct status set after `completed` once a redemption feature ships.

**Evidence**: `Booking` status constants are at `app/Domains/Booking/Models/Booking.php:25-37`. The spec (FR-023) mandates at minimum `{VALID, CANCELLED, PENDING, EXPIRED}` with future `USED`.

**Rationale**: `VALID = confirmed or completed` is verbatim from FR-023. `cancellation_requested` is a transient pre-cancelled state where the booking is still confirmed — `VALID` is honest (the traveler can still attend until it's actually cancelled). `no_show` mapped to `EXPIRED` reflects "no longer admissible" without inventing a new status; flagged here for reviewer sign-off since the spec does not name `no_show` explicitly.

**Alternatives considered**:
- `no_show` → `VALID` — rejected (a ticket-taker scanning a no-show's voucher should see it is not admissible).
- Add a `NO_SHOW`/`INVALID` verification status — rejected (the spec mandates a minimal set + future USED; adding statuses now is scope creep and breaks the "minimal" design).

---

## R8. Next.js verification page at `/v/{reference}` (FR-027)

**Decision**: Add `frontend/src/app/v/[reference]/page.tsx` **at the App Router root** (outside `[locale]/`) so the URL is exactly `/v/{reference}` with no locale prefix, matching the QR payload (FR-002). Configure the next-intl middleware to exclude `/v` from locale interception via a `matcher` that skips `/v(/.*)?` and `/api(/.*)?`. The page is a Server Component that calls the Laravel verification API (`GET /api/public/v/{reference}`) and renders a large status indicator + the allowed fields only. It includes `<meta name="robots" content="noindex,nofollow">` (R9). The page reads the `Accept-Language` header to pick a display locale from `{en, es, it}` (default `en`) for the status label and the handful of field labels; it does **not** use the full `next-intl` routing (no locale in the URL).

**Evidence**: `frontend/src/i18n/routing.ts` sets `localePrefix: 'always'`; without excluding `/v`, next-intl would redirect `/v/abc` to `/en/v/abc`, breaking the QR's exact URL. There is currently no source `middleware.ts` (only a built artifact in `.next/`), so a new `frontend/src/middleware.ts` will be added with the next-intl middleware + a custom matcher excluding `/v` and `/api`.

**Rationale**: The spec (FR-002, FR-027) is explicit that the QR encodes `https://bookly.travel/v/{booking_reference}` — no locale. A root-level page is the only way to honor that exact URL. Server Component rendering gives SSR HTML (constitution: Public Rendering Expectations) for the minimal content. `noindex` (R9) prevents voucher URLs polluting search. The page has no navigation to private surfaces (FR-027).

**Alternatives considered**:
- Put the page under `[locale]/v/[reference]` and encode `/en/v/{ref}` in the QR — rejected (FR-002 mandates `/v/{ref}`).
- Render the verification page server-side from Laravel (Filament-style) — rejected (API-First rule: the public surface MUST be Next.js + Laravel API; only the admin Filament panel is the server-rendered exception).
- Make it a pure client component — rejected (Public Rendering Expectations favor SSR for crawlable/validatable HTML, and the page is a thin server fetch).

---

## R9. noindex for the verification page

**Decision**: Add `<meta name="robots" content="noindex,nofollow">` (and no sitemap entry) to `/v/{reference}`. Voucher verification URLs must not be indexed: they are per-booking artifacts and indexing them would expose booking references in search results and pollute the sitemap (FR-028 "purely additive"; Public Experience & SEO Rules are about *discovery* surfaces, which the verification page intentionally is not).

**Rationale**: The page exists for a single scan-and-verify action, not for discovery. Indexing it serves no user and leaks references.

**Alternatives considered**:
- Allow indexing — rejected (no SEO value, reference leakage).

---

## R10. Voucher download eligibility (FR-007, FR-008)

**Decision**: Widen `VoucherController::download()`'s status guard from `where('status', STATUS_CONFIRMED)` to `whereIn('status', [STATUS_CONFIRMED, STATUS_COMPLETED])`. Keep the ownership check `where('traveler_id', $request->user()->id)` and `firstOrFail()` (404 for non-owners and for cancelled/other statuses — satisfies FR-008's "not-found / forbidden"). Guests (no account) have no `auth:sanctum` user, so the route's `auth:sanctum` middleware already 401s them (FR-009: dashboard path unavailable to guests).

**Evidence**: `VoucherController.php:23-26` currently restricts to `STATUS_CONFIRMED` only. The route lives under `Route::middleware(['auth:sanctum', 'throttle:traveler'])` (`routes/api/public.php:159-167`), so unauthenticated visitors are 401'd and non-owners 404'd.

**Rationale**: FR-007 explicitly extends download to `completed`; cancelled is excluded (firstOrFail on the status `whereIn` means cancelled → no row → 404). Guests are blocked at the middleware layer (FR-009).

**Alternatives considered**:
- Return 403 for cancelled — rejected (404 is the established pattern and avoids revealing whether a booking exists; the spec accepts "not-found / forbidden").
- A separate guest token-download route — rejected (spec Assumptions: token-based guest download is out of scope for Phase 1).

---

## R11. Partner in-app notification center completion (FR-016, FR-017, SC-007)

**Decision**: 
- **FR-016 (backend)**: confirmed already satisfied by `NotificationController` (`index` with `unread_only` filter + `unread_count` in meta, `markAsRead`, `markAllAsRead`, all scoped to `partner_id` from `$request->attributes->get('partner_id')`) and the `Notification` model (`scopeUnread`, `markAsRead`). No backend change; add/confirm tests.
- **FR-017 (frontend)**: wire the existing `frontend/src/components/partner/layout/NotificationBell.tsx` into `PartnerHeader.tsx` (which already accepts an `unreadCount?: number` prop and renders a `BellIcon`) so the unread count is fetched live (e.g., `useQuery` polling the `/api/partner/notifications` `unread_count` meta, or a dedicated unread-count endpoint) and rendered as a badge. `NotificationBell` currently has zero usages (grep confirmed) — it is built but not mounted.

**Evidence**: `NotificationController.php` has the full FR-016 surface. `PartnerHeader.tsx` accepts `unreadCount` but the partner dashboard `page.tsx` has no `unread` wiring (grep found none). `NotificationBell.tsx` exists (91 lines) but is unreferenced.

**Rationale**: Reuse the existing component and the existing controller meta; the gap is purely integration (mount + live fetch), not new logic. SC-007 ("dashboard unread indicator reflecting the true unread count, never a static zero") is satisfied by a live query, not a static prop.

**Alternatives considered**:
- Build a new unread-count endpoint — rejected (the `index` response already includes `meta.unread_count`; a separate endpoint is redundant).
- Poll every second — rejected (a 30-60s `staleTime` query + refetch-on-focus is sufficient and cheaper).

---

## R12. Backward compatibility (FR-028)

**Decision**: All changes are additive or in-place edits to existing classes with no contract removal:
- New route `/api/public/v/{reference}` (additive).
- New Next.js page `/v/{reference}` (additive, root).
- QR payload change is forward-compatible: no existing reader depended on the JSON-payload placeholder (there was no real QR before — R1), so no consumer breaks.
- Voucher download guard is widened (not narrowed): `confirmed` still allowed, `completed` added.
- Voucher freshness columns are nullable + backfilled NULL (no data conversion).
- Partner-email localization adds per-locale views with EN fallback; the existing single views become the EN views (rename/move, not delete).

**Rationale**: FR-028 forbids breaking booking, notifications, payments, partner dashboard, traveler dashboard, search, or admin moderation. No file owned by those specs is edited except `VoucherController` (Booking domain, this spec's scope), `VoucherService` (Booking domain), `Booking` model (additive columns), the partner mailables (this spec's scope), and `routes/api/public.php` (additive route).

---

## Summary of new dependencies / migrations

- **Composer**: `simplesoftwareio/simple-qrcode` (R1).
- **Migration**: `2026_07_04_100100_add_voucher_freshness_columns_to_bookings.php` — adds nullable `voucher_generated_at` (timestamp) and `voucher_content_hash` (string, 64) to `bookings` (R3).
- **npm**: none new (Next.js + next-intl + react-query already present).
- **Config**: `services.voucher.public_base_url` (default `https://bookly.travel`); `throttle:verify` limiter definition (60/min/IP).
- **Env**: `services.slack.admin_webhook_url` already used by `NotifyAdminOnEmailDeliveryFailure` — no new env required for it.

No new database tables. No new Filament resources. No new admin notification surface. All Constitution gates pass (see plan.md Constitution Check).