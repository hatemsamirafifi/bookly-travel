# Stripe MCP Setup — Bookly Travel

## Completed Steps

### ✅ Step 1: Stripe Skills Installed
10 skills installed to [`.agents/skills/`](file:///f:/Travel%20Website/bookly%20travel/.agents/skills/):

| Skill | Purpose |
|-------|---------|
| `stripe-best-practices` | Integration routing, API version, SDK guidance |
| `stripe-docs` | Read/search Stripe documentation via CLI |
| `connect-recommend` | Connect platform type recommendations |
| `connect-required-verification-information` | Connected account verification requirements |
| `stripe-apps` | Stripe Apps development |
| `stripe-directory` | Find/resolve external providers |
| `stripe-pay` | Send funds to Stripe businesses |
| `stripe-projects` | Provision infrastructure via Stripe Projects |
| `metronome` | Usage-based billing guidance |
| `upgrade-stripe` | API version & SDK upgrade guide |

### ✅ Step 2: Stripe MCP Server Configured
Added to global config at [`mcp_config.json`](file:///C:/Users/HaTeM/.gemini/config/mcp_config.json):
```json
"stripe": {
  "command": "npx",
  "args": ["-y", "mcp-remote", "https://mcp.stripe.com"]
}
```

---

## ⚠️ Action Required: Reload & Authenticate

The Stripe MCP server is configured but **not yet active**. To activate it:

1. **Reload your tools** — use the MCP Servers panel (Additional Options (...) → MCP Servers), or **start a new chat session**
2. **Authenticate with Stripe** — when `mcp-remote` starts, it will open a browser window for OAuth. Log in with your Stripe account and authorize the connection
3. **Verify** — confirm that `stripe_implementation_planner` appears in the available tools list

---

## Step 3: Generate Integration Plan (After Reload)

Once `stripe_implementation_planner` is available, I'll call it with your Bookly Travel business context:

**Your Stripe products:**
- **Payments** — Already integrated (Phase 1: Payment Intents, webhooks, financial ledger)
- **Connect** — Needed for Phase 2 Spec 017 (Partner Payouts & Commission Ledger via Stripe Connect Express)
- **Invoicing** — New requirement

**Business context for the planner:**
> Bookly is a multi-partner tours marketplace (B2C/B2B) built with Laravel 11 + Next.js 16. Phase 1 already has Stripe Payment Intents for tour booking payments with webhook-driven confirmation. Phase 2 needs: (1) Stripe Connect Express for partner payouts with configurable commission rates and automated disbursements, (2) automated refunds with cancellation policies, and (3) invoicing for partner commission statements and traveler booking receipts.

---

## Existing Stripe Integration (Phase 1 — for review)

| Component | File |
|-----------|------|
| Stripe Service | [`StripeService.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Services/StripeService.php) |
| Webhook Controller | [`StripeWebhookController.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Controllers/Public/StripeWebhookController.php) |
| Create Payment Intent | [`CreatePaymentIntentAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Actions/CreatePaymentIntentAction.php) |
| Process Refund | [`ProcessRefundAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Actions/ProcessRefundAction.php) |
| Process Webhook | [`ProcessStripeWebhookAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Actions/ProcessStripeWebhookAction.php) |
| Payment Model | [`Payment.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Models/Payment.php) |
| Webhook Events | [`StripeWebhookEvent.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Models/StripeWebhookEvent.php) |
| Config | [`services.php`](file:///f:/Travel%20Website/bookly%20travel/backend/config/services.php) |
