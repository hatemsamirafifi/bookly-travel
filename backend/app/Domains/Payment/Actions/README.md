# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Payment/Actions`.

## Interface

- [ConfirmDeterministicPaymentAction.php](./ConfirmDeterministicPaymentAction.php#L12) declares `ConfirmDeterministicPaymentAction`, `ConfirmDeterministicPaymentAction::execute()`.
- [CreatePaymentIntentAction.php](./CreatePaymentIntentAction.php#L12) declares `CreatePaymentIntentAction`, `CreatePaymentIntentAction::execute()`.
- [ProcessRefundAction.php](./ProcessRefundAction.php#L13) declares `ProcessRefundAction`, `ProcessRefundAction::execute()`.
- [ProcessStripeWebhookAction.php](./ProcessStripeWebhookAction.php#L20) declares `ProcessStripeWebhookAction`, `ProcessStripeWebhookAction::execute()`.
- [WebhookHandlers](./WebhookHandlers/README.md) — Public declarations include `HandleAccountUpdated`, `HandleDisputeClosed`, `HandleDisputeCreated`.

## Invariants

- `ProcessStripeWebhookAction::execute` verifies the Stripe signature before inserting the event. The insert and handler run in one transaction, and an existing Stripe event ID skips handler execution. [Signed and deduplicated dispatch](./ProcessStripeWebhookAction.php#L34).
- `ConfirmDeterministicPaymentAction::execute` looks up and locks the traveler's booking and its charge, validates the client secret, and rejects bookings that can no longer be paid. [Deterministic confirmation](./ConfirmDeterministicPaymentAction.php#L14).
- Payment initialization reuses a stored charge `client_secret`. If a prior charge has an intent ID and lacks a stored secret, the action calls gateway cancellation before requesting a fresh intent. Gateway creation runs before the local payment write transaction. [Retry branches](./CreatePaymentIntentAction.php#L33) · [Gateway call](./CreatePaymentIntentAction.php#L74).

## Side Effects

- `ProcessStripeWebhookAction::execute` persists the event ID, type, payload hash, and raw payload. A rolled-back transaction leaves the event eligible for redelivery. Once the transaction commits, redelivery skips the handler, even if a later domain-event dispatch failed. [Webhook persistence and dispatch](./ProcessStripeWebhookAction.php#L42).
- `CreatePaymentIntentAction::createFresh` stores the gateway intent on the booking and charge. If local persistence fails, it attempts to cancel the new remote intent before propagating the error. [Intent persistence and cleanup](./CreatePaymentIntentAction.php#L104).
- Webhook dispatch handles `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`, `charge.dispute.created`, `charge.dispute.closed`, `account.updated`, `invoice.paid`, `invoice.payment_failed`, and `invoice.finalized`. Unsupported types are logged and yield no domain events. [Dispatch map](./ProcessStripeWebhookAction.php#L68).
- The pending-booking sweep commits expiration and audit writes before attempting gateway cancellation. It logs cancellation failures and selects only pending bookings on later sweeps. The confirmation-email job uses a per-booking lock and a sent marker, with three attempts and a 900-second backoff. [Expiry job](../Jobs/ExpirePendingBookingsJob.php#L22) · [Email retries](../../Booking/Jobs/SendBookingConfirmationEmail.php#L33).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
