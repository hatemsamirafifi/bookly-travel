# Quickstart: Tour Booking

**Feature**: 007-tour-booking
**Date**: 2026-05-09

## Prerequisites

- Docker and Docker Compose running
- Laravel backend and Next.js frontend set up per project README
- Specs 001–006 completed (auth, tours, pricing, availability, search)
- Meilisearch running (from spec 006)
- Redis running (cache + queue)

## Setup

### 1. Database Migrations

```bash
cd backend
php artisan migrate
```

This creates `bookings` and `booking_audit_logs` tables.

### 2. Environment Configuration

No new env vars required. Existing `REDIS_*` and `DB_*` settings are reused. For rate limiting, Redis is the cache store (already configured).

### 3. Queue Worker

```bash
php artisan queue:work redis --queue=booking_emails
```

Processes booking confirmation email jobs asynchronously.

## Verification

### 1. Create a Booking (API)

```bash
curl -X POST http://localhost/api/public/bookings \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
    "tour_slug": "tuscany-wine-tasting",
    "tour_date": "2026-06-15",
    "participant_count": 2,
    "locale": "en"
  }'
```

Expected: 201 Created with booking reference `BKO-XXXXXX`.

### 2. Idempotency Test

Repeat the same curl with the **same** `Idempotency-Key`. Expected: 200 OK with the same booking data (no duplicate created).

### 3. View My Bookings

```bash
curl http://localhost/api/public/my-bookings \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}"
```

Expected: 200 OK with the booking in the response.

### 4. Cancel a Booking

```bash
curl -X POST http://localhost/api/public/my-bookings/BKO-A3XK9M/cancel \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}"
```

Expected: 200 OK, status `cancelled`.

### 5. Partner View

```bash
curl http://localhost/api/partner/bookings \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {partner_token}"
```

Expected: 200 OK with bookings for the partner's tours only.

### 6. Partner Status Transition

```bash
# Set tour date to today or past for testing
curl -X PATCH http://localhost/api/partner/bookings/BKO-A3XK9M/status \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {partner_token}" \
  -H "Content-Type: application/json" \
  -d '{"status": "completed"}'
```

Expected: 200 OK (if tour date is past) or 409 Conflict (if tour date is future).

### 7. Audit Trail (Admin)

```bash
curl http://localhost/api/admin/audit/bookings/BKO-A3XK9M \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {admin_token}"
```

Expected: 200 OK with full chronological audit trail.

## Frontend Dev

```bash
cd frontend
npm run dev
```

- Booking form: `http://localhost:3000/en/booking?tour=tuscany-wine-tasting&date=2026-06-15`
- My Bookings: `http://localhost:3000/en/my-bookings`
- Booking detail: `http://localhost:3000/en/my-bookings/BKO-A3XK9M`

## Running Tests

```bash
cd backend
php artisan test --testsuite=Feature tests/Feature/Booking

cd frontend
npx playwright test tests/e2e/booking.spec.ts
```

## Common Issues

| Problem | Solution |
|---------|----------|
| 429 on repeated booking requests | Rate limit is 10/min; wait or use a different test user |
| 409 "spots remaining" | Seed availability or adjust participant count |
| 403 on partner endpoint | Verify partner owns the tour |
| Email not received | Check `queue:work` is running; check `storage/logs/laravel.log` |
| `Idempotency-Key` missing | Client must generate and send UUID v4 in header |
