# Quickstart — Notifications and Vouchers (Spec 014)

**Branch**: `014-notifications-vouchers`

> Assumes the Bookly Docker compose stack is running (`bookly-backend`, `bookly-frontend`, `bookly-postgres`, `redis`). All commands run from the repo root unless noted.

## 1. Backend setup

```bash
# Add the QR-code dependency (R1)
docker exec bookly-backend composer require simplesoftwareio/simple-qrcode

# Run the additive migration (R3): voucher freshness columns
docker exec bookly-backend php artisan migrate
```

Optional env (defaults are safe for local):
```env
# services.voucher.public_base_url — default https://bookly.travel (set to http://localhost:3000 for local scanning)
SERVICES_VOUCHER_PUBLIC_BASE_URL=http://localhost:3000
# Slack admin alert (already used by NotifyAdminOnEmailDeliveryFailure; leave unset to test log-only path)
SERVICES_SLACK_ADMIN_WEBHOOK_URL=
```

## 2. Verify the verification surface (FR-021..FR-028)

```bash
# Confirm a known booking reference (replace with a real reference from your DB)
docker exec bookly-backend php artisan tinker --execute="echo App\\Domains\\Booking\\Models\\Booking::whereNotNull('reference')->value('reference');"
# → e.g. BKO-AB23XY

curl -s http://localhost:8000/api/public/v/BKO-AB23XY | jq
# Expect: { "data": { "reference": "...", "status": "VALID", "tour_title": "...", "tour_date": "...", "participant_count": ... } }

# Unknown reference → 404, no PII
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8000/api/public/v/BKO-ZZ99ZZ
# Expect: 404

# Malformed reference → 404 (regex short-circuit, no DB hit)
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8000/api/public/v/not-a-ref
# Expect: 404
```

## 3. Verify the voucher QR + download

```bash
# As the booking owner, download the voucher (auth required)
TOKEN="<owner sanctum token>"
curl -s -o /tmp/voucher.pdf -w 'content-type=%{content_type}\n' \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/public/traveler/bookings/BKO-AB23XY/voucher
# Expect: content-type=application/pdf  (open /tmp/voucher.pdf — the QR must now be a real scannable code encoding http://localhost:3000/v/BKO-AB23XY)

# Non-owner → 404 (no voucher served)
OTHER_TOKEN="<different traveler token>"
curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer $OTHER_TOKEN" \
  http://localhost:8000/api/public/traveler/bookings/BKO-AB23XY/voucher
# Expect: 404

# Cancelled booking → 404 (download closed)
# (cancel the booking first via the existing cancel endpoint, then retry the owner download → 404)

# Completed booking → 200 (FR-007 extension)
# (set booking status to 'completed' in tinker, then owner download → 200)
```

## 4. Verify the frontend verification page

```bash
# Frontend dev server
cd frontend && npm run dev   # http://localhost:3000

# Open the public verification page (no locale prefix, no auth)
# Browser: http://localhost:3000/v/BKO-AB23XY
# Expect: large VALID indicator + tour title + date + participants; no PII; no navigation to dashboard.
# View page source: <meta name="robots" content="noindex,nofollow">

# Unknown reference
# Browser: http://localhost:3000/v/BKO-ZZ99ZZ → "not found" state
```

## 5. Verify partner in-app notifications + live unread (FR-016, FR-017)

```bash
PARTNER_TOKEN="<partner sanctum token>"

# List + unread count
curl -s -H "Authorization: Bearer $PARTNER_TOKEN" \
  http://localhost:8000/api/partner/notifications?per_page=5 | jq '.meta.unread_count'

# Mark all read
curl -s -X POST -H "Authorization: Bearer $PARTNER_TOKEN" \
  http://localhost:8000/api/partner/notifications/read-all

# In the browser: open the partner dashboard and confirm the bell badge shows the live
# unread count (drops to 0 after mark-all-read; rises after a new booking is confirmed).
```

## 6. Verify delivery resilience + admin alerting (FR-010..FR-013, FR-019)

```bash
# Confirm a booking (triggers the queued idempotent SendBookingConfirmationEmail job)
docker exec bookly-backend php artisan tinker --execute="
  App\\Domains\\Booking\\Models\\Booking::where('confirmation_email_sent_at', null)->first()->reference;
"
# The job: 3 tries, 900s backoff, Cache lock + confirmation_email_sent_at guard.
# Failed-delivery path: on exhaustion, failed() logs ERROR + fires BookingEmailDeliveryFailed
# → NotifyAdminOnEmailDeliveryFailure writes ERROR log + best-effort Slack (when configured).
# Booking status is NEVER changed by email failures (FR-013).

# Tail the log to confirm the ERROR alert on forced failure (point Mail::to at an invalid host to force exhaustion in a test).
docker exec bookly-backend tail -f storage/logs/laravel.log | grep -i 'ADMIN ALERT'
```

## 7. Run the affected tests (serial — per project memory)

```bash
# Backend (serial, pgsql, never concurrent)
docker exec bookly-backend vendor/bin/pest tests/Feature/Booking
docker exec bookly-backend vendor/bin/pest tests/Feature/Partner

# Frontend
cd frontend && npm run typecheck && npm run lint
```

## 8. Localization spot-check (FR-014, FR-015)

- Set a booking's `locale` to `es` (or `it`) and re-trigger confirmation → traveler confirmation + voucher emails render in Spanish/Italian; voucher PDF labels render in Spanish/Italian.
- Set a partner user's `locale` to `it` and re-trigger a governance email (approve/reject/suspend) → partner email renders in Italian with EN fallback if an `it` view is missing.

---

## Files touched (cheat sheet)

**Backend (new):** `VerificationAction`, `VerificationController`, `VerificationTransformer`, migration `2026_07_04_100100_add_voucher_freshness_columns_to_bookings.php`, partner per-locale email views, `frontend/src/middleware.ts`.

**Backend (edit):** `VoucherService` (QR URL + freshness), `VoucherController` (status guard), `voucher/booking.blade.php` (locale labels + real QR), `routes/api/public.php` (verification route + `throttle:verify`), partner mailables (locale selection), `config/services.php` (`voucher.public_base_url`), `app/Http/Kernel.php` or `bootstrap/app.php` (`throttle:verify` limiter definition).

**Frontend (new):** `src/app/v/[reference]/page.tsx`, `src/middleware.ts`.

**Frontend (edit):** `src/components/partner/layout/PartnerHeader.tsx` (mount `NotificationBell` with live `unread_count`), `messages/{en,es,it}.json` (verification page strings).

**No new tables, no new Filament resources, no admin notification surface.**