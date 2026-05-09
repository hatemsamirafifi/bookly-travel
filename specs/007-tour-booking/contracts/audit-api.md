# Admin Audit Trail API Contract

**Feature**: 007-tour-booking
**Base Path**: `/api/admin/audit/bookings`

## List Booking Audit Entries

**Endpoint**: `GET /api/admin/audit/bookings`

Returns a filterable, paginated view of booking audit log entries.

### Headers

| Header | Value |
|--------|-------|
| `Accept` | `application/json` |
| `Authorization` | `Bearer {token}` |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `booking_reference` | string | no | — | Filter to a specific booking |
| `actor_type` | string | no | — | traveler, partner, admin, system |
| `action` | string | no | — | created, confirmed, completed, cancelled, no_show, anonymized |
| `date_from` | datetime | no | — | ISO 8601, earliest entry |
| `date_to` | datetime | no | — | ISO 8601, latest entry |
| `page` | integer | no | 1 | Page number |

### Response

#### 200 OK

```json
{
  "data": [
    {
      "id": 542,
      "booking_reference": "BKO-A3XK9M",
      "actor_type": "system",
      "actor_id": null,
      "action": "created",
      "before_state": null,
      "after_state": "confirmed",
      "metadata": {
        "idempotency_key": "a1b2c3d4-..."
      },
      "created_at": "2026-05-09T14:30:00Z"
    },
    {
      "id": 612,
      "booking_reference": "BKO-A3XK9M",
      "actor_type": "partner",
      "actor_id": 7,
      "action": "completed",
      "before_state": "confirmed",
      "after_state": "completed",
      "metadata": null,
      "created_at": "2026-06-15T18:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 50,
    "total": 124
  }
}
```

#### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

#### 403 Forbidden

```json
{
  "message": "Admin access required."
}
```

---

## Get Booking Audit Trail

**Endpoint**: `GET /api/admin/audit/bookings/{reference}`

Returns the complete chronological audit trail for a single booking.

### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `reference` | string | Booking reference |

### Response

#### 200 OK

```json
{
  "data": {
    "booking_reference": "BKO-A3XK9M",
    "entries": [
      {
        "id": 542,
        "actor_type": "system",
        "action": "created",
        "before_state": null,
        "after_state": "confirmed",
        "metadata": {"idempotency_key": "a1b2c3d4-..."},
        "created_at": "2026-05-09T14:30:00Z"
      },
      {
        "id": 612,
        "actor_type": "partner",
        "actor_id": 7,
        "action": "completed",
        "before_state": "confirmed",
        "after_state": "completed",
        "metadata": null,
        "created_at": "2026-06-15T18:00:00Z"
      }
    ],
    "linked_financial_events": [
      {
        "payment_id": 298,
        "type": "charge",
        "amount": {"amount": 17800, "currency": "EUR", "formatted": "€178.00"},
        "status": "succeeded",
        "created_at": "2026-05-09T14:30:01Z"
      }
    ]
  }
}
```

#### 404 Not Found

```json
{
  "message": "Booking not found."
}
```

## Behavior Notes

- Audit entries are immutable — the API is read-only.
- `linked_financial_events` are read from the payments domain (spec 008) by joining on `booking_id`. This data is included as a convenience; the financial records remain owned by spec 008.
- Entries are always returned in chronological order (oldest first).
- The `metadata` field is a JSON object whose schema varies by action. Common metadata includes `idempotency_key` (on creation) and `cancellation_reason` (on cancellation).
- System actors have `actor_id: null`; all other actor types have a valid user ID.
