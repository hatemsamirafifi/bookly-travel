# Data Model — Notifications and Vouchers (Spec 014)

**Branch**: `014-notifications-vouchers` | **Phase**: 1

> This spec reuses the existing `Booking` and partner `Notification` models (no new tables). The only schema change is two nullable columns on `bookings` for voucher freshness (R3). The new `VerificationResult` is a DTO, not a persisted entity.

---

## 1. Entity: `Booking` (existing — additive columns only)

**Table**: `bookings` (owned by the Booking domain; shared with Specs 007/008/013)

### Fields used by this spec

| Field | Type | Source | Notes |
|---|---|---|---|
| `reference` | string, unique | existing | Opaque public lookup key (`BKO-` + 6 chars from `ABCDEFGHJKMNPQRSTUVWXYZ23456789`). Used as the verification path param (FR-024). Never numeric ID. |
| `traveler_id` | FK → users | existing | Owner check for voucher download (FR-008). Null for guest bookings → dashboard download blocked by `auth:sanctum` (FR-009). |
| `tour_id` | FK → tours | existing | Drives tour title / meeting point on voucher and verification payload. |
| `tour_date` | date | existing | Voucher content + verification field; part of voucher content hash (R3). |
| `participant_count` | integer | existing | Voucher content + verification field; part of voucher content hash (R3). |
| `total_price` / `currency` | integer / string | existing | Voucher content (total paid). Not exposed on the verification surface (FR-022). |
| `status` | string | existing | Drives verification status mapping (R7) and voucher-download eligibility (R10). |
| `locale` | string (en/es/it) | existing | Drives traveler email + voucher PDF localization (FR-014/FR-015). |
| `confirmation_email_sent_at` | timestamp, nullable | existing | Idempotency guard in `SendBookingConfirmationEmail` (FR-011). |
| **`voucher_generated_at`** | timestamp, nullable | **NEW (R3)** | Set when a voucher PDF is (re)generated. |
| **`voucher_content_hash`** | string(64), nullable | **NEW (R3)** | Hash of voucher-relevant fields; mismatch triggers regeneration (FR-018). |

### Validation rules (this spec)

- Voucher download: `reference` resolved by `where('reference', $ref)->where('traveler_id', $user->id)->whereIn('status', [confirmed, completed])->firstOrFail()` — non-owner / cancelled / other → 404 (FR-007, FR-008).
- Verification lookup: `reference` matches `^BKO-[A-HJ-NP-TV-Z2-9]{6}$`; resolved by `where('reference', $ref)->firstOrFail()` — unknown/malformed → 404 (FR-024, SC-010).

### State transitions relevant to this spec

The spec does not introduce new booking transitions. Verification **observes** the existing status lifecycle:
- `pending_payment` → `confirmed` (payment captured) → triggers `SendBookingConfirmationEmail` (existing).
- `confirmed` → `completed` / `no_show` (admin/partner, Spec 013) → voucher still downloadable (`completed`).
- `confirmed` → `cancelled` (Spec 008 cancellation) → voucher download closed.
- `pending_payment` → `expired` (unpaid, existing) → verification shows `EXPIRED`.

Voucher freshness state (R3): `clean` (hash matches, file exists) → serve cached; `stale` (hash differs or file missing) → regenerate and update `voucher_generated_at` + `voucher_content_hash`.

---

## 2. Entity: `Notification` (existing — partner in-app, no schema change)

**Table**: `partner_notifications` (owned by Partner domain; confirm exact table name in tasks phase)

### Fields used by this spec

| Field | Type | Notes |
|---|---|---|
| `partner_id` | FK → partners | Ownership scope (FR-016 — partners see only their own). |
| `type` | string | Notification type (new_booking, booking_cancelled, partner_approved, etc.). |
| `title` / `body` | string / text | Locale-appropriate copy (FR-016). |
| `data` | json (nullable) | Structured payload (booking reference, etc.). |
| `read_at` | timestamp, nullable | Null = unread. `scopeUnread` + `markAsRead()` exist. |

### API surface (FR-016, FR-017) — confirmed existing

- `GET /api/partner/notifications?per_page=&unread_only=` → `{ data: [...], meta: { current_page, last_page, total, unread_count } }`
- `POST /api/partner/notifications/{id}/read` → mark one read
- `POST /api/partner/notifications/read-all` → mark all read
- All scoped to `$request->attributes->get('partner_id')` (partner ownership, never another partner's).

---

## 3. DTO: `VerificationResult` (new — not persisted)

A read-only transfer object produced by `VerificationAction` and serialized by `VerificationTransformer`. Never written to the DB.

### Fields (FR-022)

| Field | Type | Always present | Notes |
|---|---|---|---|
| `reference` | string | yes | The booking reference echoed back. |
| `status` | enum string | yes | One of `VALID`, `CANCELLED`, `PENDING`, `EXPIRED` (future `USED`). Mapping per R7. |
| `tour_title` | string | yes | Tour title in the booking's locale (EN fallback). |
| `tour_date` | ISO date string | yes | `Y-m-d`. |
| `participant_count` | integer | yes | |
| `created_at` | ISO datetime string | optional | MAY be included (FR-022 "may additionally include"). |
| `voucher_generated_at` | ISO datetime string | optional | MAY be included when a voucher exists. |

### PII guard (FR-022, SC-010)

`VerificationResult` MUST NOT carry: traveler name, email, phone, payment info, guest identity, internal DB IDs, partner notes. Enforced by the transformer constructing the DTO field-by-field from the `Booking` (never by `(array)$booking` or `toArray()`).

### Status mapping (R7)

```
confirmed            → VALID
cancellation_requested → VALID
completed            → VALID
cancelled            → CANCELLED
pending_payment      → PENDING
expired              → EXPIRED
no_show              → EXPIRED
```

---

## 4. DTO: `DeliveryFailure` alert payload (existing — operational, not persisted)

Surfaced by the existing `NotifyAdminOnEmailDeliveryFailure` listener (ERROR log + best-effort Slack). Not a model. Fields carried (FR-019, US5):
- `booking_reference` (always)
- `mail_class` (the mailable FQCN, where available)
- `exception` (message)
- `traveler_id`, `tour_id`, `locale` (operational context)
- Queue/job info where available

PII guard: never includes payment info or PII beyond what locates the booking. **No schema**, **no table**, **no Filament resource** (FR-012, US5).

---

## 5. Index / integrity notes

- `bookings.reference` is already `unique` (existing) — the verification lookup is an index hit; no new index needed.
- The new `voucher_generated_at` / `voucher_content_hash` columns are nullable and not indexed (read/write happens on the single-booking download path, not in list queries).
- No foreign keys added. No new constraints. The migration is additive and reversible (down() drops the two columns).

---

## 6. Out-of-scope data (explicitly NOT introduced)

- `admin_notifications` table — forbidden (FR-012, US5).
- `vouchers` table — forbidden (vouchers are files in `storage/app/vouchers/`, keyed by reference; freshness tracked on `bookings`).
- `verifications` / `verification_log` table — forbidden (FR-025, SC-011: the verification surface writes nothing).
- `redemptions` / `USED` status — future (FR-023 leaves room; not implemented this spec).
- Guest token-download table — out of scope (spec Assumptions).
- Payout/ledger entries — out of scope (Out-of-Scope §1, FR-020).