# Contract: Partner Stripe Connect API

**Base path**: `/api/partner/stripe`

**Middleware**: `auth:sanctum`, `partner`

**Role concealment**: The partner middleware returns `404` for a missing user,
missing partner token ability, or missing Partner record. Sanctum handles an
unauthenticated request before the partner middleware.

## GET `/status`

Refresh the authenticated partner's Stripe account state and return it.

### Connected response — `200`

```json
{
  "data": {
    "connected": true,
    "stripe_account_id": "acct_example",
    "charges_enabled": true,
    "payouts_enabled": true,
    "details_submitted": true,
    "onboarding_completed": true
  }
}
```

### No connected account — `200`

```json
{
  "data": {
    "connected": false,
    "charges_enabled": false,
    "payouts_enabled": false,
    "details_submitted": false,
    "onboarding_completed": false
  }
}
```

## POST `/onboard`

Create a Stripe Express account when none exists and return a hosted onboarding
account link.

### Optional request body

```json
{
  "return_url": "https://bookly.travel/partner/settings/payouts?status=success",
  "refresh_url": "https://bookly.travel/partner/settings/payouts?status=refresh",
  "country": "US"
}
```

Validation:

- `return_url`: nullable URL
- `refresh_url`: nullable URL
- `country`: nullable two-character string; defaults to `US`

When URLs are omitted, the controller uses `config('app.frontend_url')` and the
paths `/partner/settings/payouts?status=success` and
`/partner/settings/payouts?status=refresh`.

### Success response — `200`

```json
{
  "url": "https://connect.stripe.com/setup/s/example"
}
```

Invalid input uses Laravel's standard validation response with status `422`.

## GET `/dashboard`

Create a single-use Stripe Express Dashboard login link.

### Success response — `200`

```json
{
  "url": "https://connect.stripe.com/express/example"
}
```

### No account — `422`

```json
{
  "message": "Partner does not have a connected Stripe account yet."
}
```

### Link cannot be generated — `422`

```json
{
  "message": "Could not generate dashboard link. Please complete onboarding first."
}
```

## Throttling

- `GET /status` and `GET /dashboard`: `throttle:booking.get`
- `POST /onboard`: `throttle:booking.create`

## Contract boundary

These endpoints do not expose settlement history, payout batches, Bookly-owned
earnings, reconciliation results, or invoice creation. Those are not part of
the merged Spec `017` API.
