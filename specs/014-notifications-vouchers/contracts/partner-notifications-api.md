# Contract: Partner In-App Notifications API (confirmed existing)

**Spec**: 014-notifications-vouchers | **Endpoints**: under `/api/partner/notifications` | **Auth**: Sanctum + partner role

> This API already exists (`NotificationController` + `NotificationService` + `Notification` model). This contract documents it for completeness and to anchor FR-016/FR-017 tests. **No backend change** — the only work is wiring the existing `unread_count` into the partner dashboard header (FR-017).

## Endpoints

### `GET /api/partner/notifications`

Auth: `auth:sanctum` + partner role; `$partnerId` resolved from `$request->attributes->get('partner_id')`.

Query params:
- `per_page` (int, default 20)
- `unread_only` (bool, default false)

Response 200:
```json
{
  "data": [
    {
      "id": 42,
      "type": "new_booking",
      "title": "New booking on your tour",
      "body": "Booking BKO-AB23XY for 2 participants on 2026-08-15.",
      "data": { "booking_reference": "BKO-AB23XY", "tour_id": 17 },
      "read_at": null,
      "created_at": "2026-07-04T12:02:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "total": 1,
    "unread_count": 1
  }
}
```

Ownership scope: `WHERE partner_id = $partnerId`. A partner never sees another partner's notifications (FR-016).

### `POST /api/partner/notifications/{id}/read`

Marks a single notification read. 404 if the notification does not belong to the authenticated partner. Response 200 `{ "message": "Marked as read." }`.

### `POST /api/partner/notifications/read-all`

Marks all of the partner's unread notifications read. Response 200 `{ "message": "All notifications marked as read." }`.

## Dashboard unread indicator (FR-017, SC-007)

The partner dashboard header fetches `unread_count` (from the `meta` of `GET /api/partner/notifications?per_page=1` or a lightweight poll) and renders a live badge on `NotificationBell`. The count MUST reflect the true unread count within seconds of a new notification (react-query `staleTime` + refetch-on-focus + refetch-on-mutation). **Never a static zero.**

## Out of scope

- No traveler in-app notification center (spec Assumptions — travelers are email-only in Phase 1).
- No admin in-app notification surface (FR-012, US5 — admin alerts go through the ERROR log + Slack, not this API).
- No new endpoints; this contract only confirms the existing surface.