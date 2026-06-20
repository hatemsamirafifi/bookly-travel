# API Contract: Partner Profile, Settings, Analytics & Notifications

**Base URL**: `/api/partner` (prefix set in route group)
**Auth**: Bearer Token + `partner` middleware (PartnerRoleMiddleware)
**Middleware**: `auth:sanctum`, `partner` (sets `partner_id` on request, returns 404 for cross-partner)

---

## Profile

### 1. Get Profile

**Endpoint**: `GET /api/partner/profile`
**Throttle**: `booking.get`

Returns both the partner profile and settings in a single response.

**Response (200 OK)**:
```json
{
  "profile": {
    "id": 1,
    "partner_id": 1,
    "company_name": "Rome Tours LLC",
    "business_description": "Premier tour operator in Rome...",
    "logo_url": "https://cdn.bookly.test/logos/rome-tours.jpg",
    "contact_email": "info@rometours.com",
    "contact_phone": "+39 06 1234 5678",
    "website": "https://rometours.com",
    "business_address": {
      "street": "Via del Corso 1",
      "city": "Rome",
      "state": "Lazio",
      "postal_code": "00100",
      "country": "IT"
    },
    "tax_id": "IT12345678901",
    "payout_holder_name": "Rome Tours LLC",
    "payout_bank_name": "Intesa Sanpaolo",
    "payout_account_number": "****5678",
    "payout_iban": "****************5678",
    "payout_swift_bic": "BCIT***",
    "payout_country": "IT",
    "created_at": "2026-05-01T10:00:00Z",
    "updated_at": "2026-06-03T15:00:00Z"
  },
  "settings": {
    "id": 1,
    "partner_id": 1,
    "notify_new_booking": true,
    "notify_cancellation": true,
    "notify_daily_summary": true,
    "notify_review_received": true,
    "notify_tour_status_change": true,
    "locale": "en",
    "created_at": "2026-05-01T10:00:00Z",
    "updated_at": "2026-06-03T15:00:00Z"
  }
}
```

**Note**: Payout fields (`payout_account_number`, `payout_iban`, `payout_swift_bic`) are encrypted at rest and masked on read. Raw values are only accessible via `getRawPayout*()` methods for processing.

---

### 2. Update Profile

**Endpoint**: `PUT /api/partner/profile`
**Throttle**: `booking.get`

**Payload**:
```json
{
  "company_name": "Rome Tours LLC",
  "business_description": "Premier tour operator...",
  "logo_url": "https://cdn.bookly.test/logos/rome-tours.jpg",
  "contact_email": "info@rometours.com",
  "contact_phone": "+39 06 1234 5678",
  "website": "https://rometours.com",
  "business_address": {
    "street": "Via del Corso 1",
    "city": "Rome",
    "state": "Lazio",
    "postal_code": "00100",
    "country": "IT"
  },
  "tax_id": "IT12345678901",
  "payout_holder_name": "Rome Tours LLC",
  "payout_bank_name": "Intesa Sanpaolo",
  "payout_account_number": "IT60X0542811101000000123456",
  "payout_iban": "IT60X0542811101000000123456",
  "payout_swift_bic": "BCITITMM",
  "payout_country": "IT"
}
```

**Validation Rules**:
- `company_name`: required, string, max 255
- `business_description`: nullable, string, max 5000
- `logo_url`: nullable, URL, max 2048
- `contact_email`: required, email, max 255
- `contact_phone`: nullable, string, max 50
- `website`: nullable, URL, max 2048
- `business_address`: nullable, array
- `tax_id`: nullable, string, max 100
- `payout_holder_name`: nullable, string, max 255
- `payout_bank_name`: nullable, string, max 255
- `payout_account_number`: nullable, string, max 100
- `payout_iban`: nullable, string, max 34 (validated via `ValidIban` rule with MOD 97 checksum)
- `payout_swift_bic`: nullable, string, max 11
- `payout_country`: nullable, string, size 2

**Response (200 OK)**: Updated `PartnerProfile` object (payout fields masked on read).

---

## Settings

### 3. Get Settings

**Endpoint**: `GET /api/partner/settings`
**Throttle**: `booking.get`

**Response (200 OK)**:
```json
{
  "id": 1,
  "partner_id": 1,
  "notify_new_booking": true,
  "notify_cancellation": true,
  "notify_daily_summary": true,
  "notify_review_received": true,
  "notify_tour_status_change": true,
  "locale": "en",
  "created_at": "2026-05-01T10:00:00Z",
  "updated_at": "2026-06-03T15:00:00Z"
}
```

---

### 4. Update Settings

**Endpoint**: `PUT /api/partner/settings`
**Throttle**: `booking.get`

**Payload**:
```json
{
  "notify_new_booking": true,
  "notify_cancellation": false,
  "notify_daily_summary": true,
  "notify_review_received": true,
  "notify_tour_status_change": true,
  "locale": "en"
}
```

**Validation Rules**:
- `notify_new_booking`: sometimes, boolean
- `notify_cancellation`: sometimes, boolean
- `notify_daily_summary`: sometimes, boolean
- `notify_review_received`: sometimes, boolean
- `notify_tour_status_change`: sometimes, boolean
- `locale`: sometimes, string, size 2

**Response (200 OK)**: Updated `PartnerSettings` object.

---

## Analytics

### 5. Get Analytics

**Endpoint**: `GET /api/partner/analytics`
**Throttle**: `booking.get`

**Query Parameters**:
- `from` (optional): date, format `Y-m-d`
- `to` (optional): date, format `Y-m-d`
- `tour_id` (optional): integer — filters to a specific tour (must belong to partner, returns 404 if not)

**Response (200 OK)**:
```json
{
  "summary": {
    "total_bookings": 145,
    "total_revenue": {
      "amount": 1450000,
      "currency": "EUR",
      "formatted": "€14,500.00"
    },
    "average_rating": 4.8,
    "review_count": 89,
    "conversion_rate": 4.5,
    "upcoming_bookings": 12
  },
  "bookings_over_time": [
    {
      "date": "2026-06-01",
      "count": 5,
      "revenue": 45000
    },
    {
      "date": "2026-06-02",
      "count": 8,
      "revenue": 72000
    }
  ]
}
```

**Error (404)**: `tour_id` does not belong to the authenticated partner.

---

## Notifications

### 6. List Notifications

**Endpoint**: `GET /api/partner/notifications`
**Throttle**: `booking.get`

**Query Parameters**:
- `per_page` (optional): integer (default 20)
- `unread_only` (optional): boolean (default false)

**Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 1,
      "partner_id": 1,
      "type": "booking",
      "priority": "high",
      "title": "New Booking",
      "message": "You have a new booking for Colosseum Tour",
      "data": {
        "booking_reference": "BOK-ABC123",
        "tour_id": 5
      },
      "read_at": null,
      "created_at": "2026-06-03T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "total": 4,
    "unread_count": 3
  }
}
```

---

### 7. Mark Notification as Read

**Endpoint**: `POST /api/partner/notifications/{id}/read`
**Throttle**: `booking.get`
**Partner scoping**: Returns 404 if notification does not belong to authenticated partner.

**Response (200 OK)**:
```json
{
  "message": "Marked as read."
}
```

---

### 8. Mark All Notifications as Read

**Endpoint**: `POST /api/partner/notifications/read-all`
**Throttle**: `booking.get`

**Response (200 OK)**:
```json
{
  "message": "All notifications marked as read."
}
```