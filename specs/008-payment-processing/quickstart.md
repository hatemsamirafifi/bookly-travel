# Quickstart: Payment Processing (008)

## Prerequisites

- Spec 007 (Tour Booking) fully deployed
- Stripe account (test mode) with API keys
- Docker stack running (backend, frontend, postgres, redis)

## Environment Variables

Add to `backend/.env`:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Add to `frontend/.env.local`:

```env
NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=pk_test_...
```

## Backend Setup

```bash
# Install Stripe PHP SDK
docker compose exec backend composer require stripe/stripe-php

# Run migrations (3 new tables + 3 columns on bookings)
docker compose exec backend php artisan migrate

# Verify Stripe connectivity
docker compose exec backend php artisan tinker --execute="\Stripe\Stripe::setApiKey(config('services.stripe.secret')); echo \Stripe\Balance::retrieve()->available[0]->amount;"
```

## Frontend Setup

```bash
# Install Stripe React packages
docker compose exec frontend npm install @stripe/stripe-js @stripe/react-stripe-js
```

## Stripe CLI (Webhook Testing)

```bash
# Install Stripe CLI (if not present)
# Forward webhooks to local backend
stripe listen --forward-to localhost:8000/api/webhooks/stripe

# Trigger test events
stripe trigger payment_intent.succeeded
stripe trigger charge.refunded
```

## Verification Checklist

1. **Create a booking** → verify response includes `payment.client_secret`
2. **Confirm payment** via Stripe Elements → verify booking status transitions to `confirmed`
3. **Cancel booking** → verify refund created and ledger entry added
4. **Send test webhook** → verify idempotent processing (no duplicates)
5. **Wait 15 min with unpaid booking** → verify `pending_payment` → `expired` transition
