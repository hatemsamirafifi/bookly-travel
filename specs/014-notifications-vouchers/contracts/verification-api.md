# Contract: Public Voucher Verification API

**Spec**: 014-notifications-vouchers | **Endpoint**: `GET /api/public/v/{reference}` | **Auth**: none

## Purpose

Read-only, unauthenticated lookup that resolves an opaque booking reference to a `VerificationResult` payload for the public `/v/{booking_reference}` Next.js page. The voucher QR encodes `https://bookly.travel/v/{booking_reference}`, which renders this page, which consumes this endpoint.

## Request

```
GET /api/public/v/{reference}
Host: api.bookly.travel
Accept: application/json
```

- **Path param**: `reference` — opaque booking reference, must match `^BKO-[A-HJ-NP-TV-Z2-9]{6}$`. Malformed → 404. Unknown → 404. **No query params, no body.**
- **Auth**: none. The endpoint MUST NOT require a token, cookie, or session.
- **Rate limit**: `throttle:verify` — 60 req/min/IP (deters enumeration).
- **Idempotency**: pure read; identical requests return identical results for the same booking state. Not cached long enough to show a stale status for a booking whose state just changed (Edge Cases) — `Cache-Control: no-store`.

## Responses

### 200 OK — known reference

```json
{
  "data": {
    "reference": "BKO-AB23XY",
    "status": "VALID",
    "tour_title": "Majestic Roman Colosseum Tour",
    "tour_date": "2026-08-15",
    "participant_count": 2,
    "created_at": "2026-07-04T12:00:00Z",
    "voucher_generated_at": "2026-07-04T12:01:30Z"
  }
}
```

`status` ∈ `{ "VALID", "CANCELLED", "PENDING", "EXPIRED" }` (future `USED`). Mapping: `confirmed`/`cancellation_requested`/`completed` → `VALID`; `cancelled` → `CANCELLED`; `pending_payment` → `PENDING`; `expired`/`no_show` → `EXPIRED`.

`created_at` and `voucher_generated_at` are optional (MAY be omitted; `voucher_generated_at` omitted when no voucher exists yet).

### 404 Not Found — unknown / malformed reference

```json
{
  "message": "Not found."
}
```

Identical response for malformed and valid-but-absent references (no enumeration side-channel — SC-010, SC-011). No timing difference engineered beyond the regex pre-check (malformed short-circuits before the DB hit; both then 404 with the same body).

### 429 Too Many Requests — rate limit exceeded

Standard Laravel throttle response.

## PII guarantee (FR-022, SC-010)

The `data` object contains **only** the fields listed above. It MUST NEVER contain: `traveler_name`, `traveler_email`, `phone`, `payment_*`, `guest_identity`, `id` (numeric), `partner_id`, `partner_notes`, `total_price`, `currency`. The transformer constructs the DTO field-by-field from the `Booking` model; it never serializes the whole model.

## Side-effect guarantee (FR-025, SC-011)

The endpoint MUST NOT: increment any counter, write any audit/log entry keyed to the visitor, log visitor identity, mutate booking state, or trigger any job. (Governance audit logging of admin actions is owned by Spec 013; the public verification surface writes nothing.)

## Thin-controller / single-action guarantee (FR-026)

`VerificationController` only: validates the reference shape, calls `VerificationAction::execute($reference)`, returns `VerificationTransformer::transform($result)`. All status-mapping and PII-stripping logic lives in `VerificationAction` (single source of truth — no duplication in the controller or page).

## Next.js page contract (FR-027)

`GET /v/{reference}` (root, no locale) — Server Component fetches `GET {API}/api/public/v/{reference}`:
- 200 → render a large status indicator (`VALID` / `CANCELLED` / etc.) + the allowed fields. `noindex,nofollow`. No navigation to private surfaces.
- 404 → render a "not found" state (same visual whether the booking never existed or was malformed — no enumeration signal to the human viewer either).
- The page consumes only the fields in this contract.