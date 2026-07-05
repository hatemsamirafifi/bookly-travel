# Contract: Admin REST API (`/api/admin/*`)

The admin surface is primarily Filament (server-rendered). A small set of
admin REST endpoints already exists and is kept for API consumers (e.g. the
existing frontend review-moderation panel). All admin API routes are registered
in `backend/routes/api/admin.php` under middleware `['auth:sanctum',
'role:admin']` (the `role` alias maps to `RoleMiddleware`, aborting 403 unless
`user.role === 'admin'`), with `throttle:booking.get` on sub-groups.

Per `/speckit-clarify`, the canonical admin UX is Filament; no new admin API
endpoints are required for tour/partner moderation (those are Filament-only).
The endpoints below are existing and already tested; they are documented here
for completeness and to fix the review-moderation contract.

## Base

`POST/GET` against `http://<backend>/api/admin/*` with header
`Authorization: Bearer <sanctum-token>`. 403 for non-admin; 401 for unauthenticated.

## 1. Booking audit

### `GET /api/admin/audit/bookings`
List `booking_audit_logs` (legacy table; being migrated to route through
`GovernanceAuditService` → `governance_audit_logs`).

- **Query**: `booking_reference`, `actor_type`, `action`, `date_from`, `date_to`,
  `page` (paginated 50).
- **200**: `{ data: BookingAuditLog[], meta: { current_page, last_page, per_page, total } }`.

### `GET /api/admin/audit/bookings/{reference}`
Full audit trail for a booking + linked financial events from `payments`.

- **200**: `{ reference, booking, audit_trail: AuditEntry[], financial_events: FinancialEvent[] }`.
- **404**: unknown reference.

## 2. Financial ledger (read-only; owned by Spec 008 surface)

### `GET /api/admin/financial-ledger`
List `FinancialLedgerEntry`.

- **Query**: `booking_reference`, `entry_type`, `date_from`, `date_to`, `page`.
- **200**: `{ data: LedgerEntry[], meta: {...} }`.

> Note: Admin Moderation does NOT write ledger entries; this endpoint is a
> read-only view into Spec 008's financial domain (FR-009).

## 3. Review moderation

### `GET /api/admin/reviews`
List reviews for moderation.

- **Query**: `status`, `tour_id`, `date_from`, `date_to`, `flagged`, `per_page`.
- **200**: `{ data: AdminReviewResource[], meta: {...} }` where `AdminReviewResource`
  includes `id, tour, traveler_name, rating, text, status, submitted_at,
  booking_reference, response, audit_trails`.

### `POST /api/admin/reviews/{review}/hide`
Hide a review (visible → hidden); recomputes tour aggregate rating; writes
`review.hide` governance audit (routed through `GovernanceAuditService`).

- **Body**: `{ reason?: string }`
- **200**: `{ review: AdminReviewResource }`
- **403**: non-admin (via `ReviewPolicy::manage`).
- **409/422**: review not in a hideable state.

### `POST /api/admin/reviews/{review}/reinstate`
Reinstate a hidden review (hidden → visible); recomputes aggregate rating;
writes `review.reinstate` audit.

- **200**: `{ review: AdminReviewResource }`
- **403**: non-admin.

## 4. Audit shape (unified, after migration)

The Filament `GovernanceAuditResource` and any future audit API surface expose
the unified `governance_audit_logs` shape:

```json
{
  "id": 1234,
  "actor": { "type": "admin", "id": 7, "name": "Ops Admin" },
  "action": "tour.publish",
  "target": { "type": "tour", "id": 42, "label": "Rome Colosseum Walk" },
  "before_state": { "status": "pending_review" },
  "after_state": { "status": "published", "published_at": "2026-06-20T10:00:00Z" },
  "metadata": { "reason": null, "bulk_batch_id": null, "ip": "..." },
  "created_at": "2026-06-20T10:00:00Z"
}
```

Append-only: no `PUT`/`PATCH`/`DELETE` on audit entries (FR-012).

## Error contract

- **401**: missing/invalid token.
- **403**: authenticated but not `admin` role, or lacks the required permission
  flag.
- **404**: resource not found (booking reference, review).
- **422**: invalid transition / validation failure (e.g. hide a non-visible
  review, reject without reason).
- **429**: rate-limited (`throttle:booking.get`).