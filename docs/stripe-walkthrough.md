# Stripe Payments, Connect & Invoicing Walkthrough — Bookly Travel

We have implemented the full Stripe modernization roadmap for Bookly Travel based on the Stripe Implementation Planner recommendations for **Payments**, **Connect**, and **Invoicing**.

---

## 1. Summary of Changes

### 💳 Stripe Payments Modernization
- **Dynamic Payment Methods (APM)**: Enabled automatic payment methods in [`StripeService.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Services/StripeService.php) to accept Apple Pay, Google Pay, Klarna, iDEAL, and Bancontact automatically without frontend code changes.
- **Frontend Stripe Elements**: Configured [`StripePaymentForm.tsx`](file:///f:/Travel%20Website/bookly%20travel/frontend/src/components/booking/StripePaymentForm.tsx) with `options={{ layout: 'tabs' }}` for multi-payment-method selection and updated `.env.local` with the active Stripe test publishable key.
- **Security & Scope Isolation**: Hardened [`CreateBookingAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Booking/Actions/CreateBookingAction.php) so that `stripe_publishable_key` is only returned when the active gateway is Stripe.

---

### 🤝 Stripe Connect (Tour Operator Marketplace & Payouts)
- **Database Schema**: Added migration [`2026_09_19_100001_add_stripe_connect_columns_to_partners_table.php`](file:///f:/Travel%20Website/bookly%20travel/backend/database/migrations/2026_09_19_100001_add_stripe_connect_columns_to_partners_table.php) with:
  - `stripe_account_id` (string, unique)
  - `stripe_charges_enabled` (boolean)
  - `stripe_payouts_enabled` (boolean)
  - `stripe_onboarding_completed` (boolean)
  - `stripe_details_submitted` (boolean)
- **Partner Model**: Updated [`Partner.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Models/Partner.php) with casts, fillable fields, and `isPayoutReady()`.
- **StripeConnectService**: Created [`StripeConnectService.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Services/StripeConnectService.php):
  - `createExpressAccount`: Creates a Stripe Express connected account.
  - `createAccountLink`: Generates Stripe-hosted KYC onboarding link.
  - `createLoginLink`: Generates SSO link to the partner's Express dashboard.
  - `syncAccountStatus`: Live syncs verification and payout status.
- **Marketplace Destination Charges**: Updated [`CreatePaymentIntentAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Actions/CreatePaymentIntentAction.php):
  - If a tour operator has an active connected account, funds route directly via `transfer_data[destination]`.
  - The platform retains its commission (`application_fee_amount`) based on `services.stripe.platform_commission_percent` (default 15%).
- **Partner Connect API**:
  - `GET /api/partner/stripe/status`: Check payout readiness and verification status.
  - `POST /api/partner/stripe/onboard`: Request onboarding URL.
  - `GET /api/partner/stripe/dashboard`: Request single sign-on Express dashboard URL.
  - Updated [`PartnerRoleMiddleware.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Middleware/PartnerRoleMiddleware.php) to permit Stripe onboarding endpoints during account setup.
- **Webhook Integration**: Added `account.updated` handler in [`ProcessStripeWebhookAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Actions/ProcessStripeWebhookAction.php) to auto-update partner payout status when Stripe verification changes.

---

### 📄 Stripe Invoicing (B2B Corporate & Group Bookings)
- **StripeInvoiceService**: Created [`StripeInvoiceService.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Services/StripeInvoiceService.php):
  - Automatically gets or creates a Stripe Customer from traveler/guest data.
  - Generates line items (`invoiceItems.create`) with booking and tour details.
  - Creates and finalizes invoice (`collection_method: 'send_invoice'`, `auto_advance: true`).
  - Returns `hosted_invoice_url` and `invoice_pdf`.
- **Invoicing Webhooks**: Added `invoice.paid`, `invoice.payment_failed`, and `invoice.finalized` handlers in [`ProcessStripeWebhookAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Actions/ProcessStripeWebhookAction.php):
  - `invoice.paid`: Automatically transitions booking to `confirmed`, creates the `Payment` charge record, and logs a debit in the financial ledger.

---

## 2. Verification Results

### Backend Automated Tests
All 42 payment and connect tests pass:
```
   PASS  Tests\Feature\Payment\StripeConnectAndInvoicingTest
  ✓ it routes destination charge and retains platform commission when partner has active Stripe account
  ✓ it updates partner status via account.updated webhook
  ✓ it confirms booking and creates ledger charge on invoice.paid webhook
  ✓ it provides partner Stripe status via API
  ✓ it generates an onboarding link for a partner via API
  ✓ it generates a dashboard login link for a partner with connected account

   PASS  Tests\Feature\Payment\PaymentCaptureTest (4 passed)
   PASS  Tests\Feature\Payment\DeterministicPaymentGatewayTest (4 passed)
   PASS  Tests\Feature\Payment\ConfirmBookingOnPaymentTest (3 passed)
   PASS  Tests\Feature\Payment\ExpireBookingOnPaymentFailureTest (3 passed)
   PASS  Tests\Feature\Payment\LedgerImmutabilityTest (4 passed)
   PASS  Tests\Feature\Payment\PartnerFinancialSummaryTest (4 passed)
   PASS  Tests\Feature\Payment\PendingExpiryTest (4 passed)
   PASS  Tests\Feature\Payment\RefundTest (3 passed)
   PASS  Tests\Feature\Payment\StripeDowntimeTest (3 passed)
   PASS  Tests\Feature\Payment\WebhookTest (2 passed)
   PASS  Tests\Feature\Payment\AnonymizationTest (2 passed)

Total: 42 passed (182 assertions)
```

### Frontend Automated Tests
```
PASS src/components/booking/__tests__/StripePaymentForm.test.tsx
  StripePaymentForm
    ✓ renders the PaymentElement
    ✓ renders a submit button with "Pay Now" text
    ✓ disables the submit button when stripe is not loaded
    ✓ disables the submit button and shows "Processing..." during processing
    ✓ calls stripe.confirmPayment with elements, redirect: if_required, and return_url pointing at the confirmation page
    ✓ calls onSuccess when confirmPayment resolves without error
    ✓ calls onError with message when confirmPayment returns an error
    ✓ shows a fallback error message when error has no message
    ✓ resets the processing flag and calls onError when confirmPayment rejects

Total: 9 passed, 9 total
```

---

## 3. Ready-To-Use API Endpoints

| Method | Endpoint | Description | Auth Required |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/partner/stripe/status` | Current payout readiness and Stripe account status | Partner Token |
| `POST` | `/api/partner/stripe/onboard` | Generate Express onboarding link | Partner Token |
| `GET` | `/api/partner/stripe/dashboard` | Generate Express Dashboard SSO link | Partner Token |
| `POST` | `/api/public/webhooks/stripe` | Processes `payment_intent.*`, `charge.*`, `account.updated`, `invoice.*` | Stripe Signature |
