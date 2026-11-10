# Listeners

<!-- EGM contract -->

## Overview

Operator alerting when a booking confirmation email exhausts delivery retries.

## Interface

- [NotifyAdminOnEmailDeliveryFailure.php](./NotifyAdminOnEmailDeliveryFailure.php#L19) declares `NotifyAdminOnEmailDeliveryFailure`, `NotifyAdminOnEmailDeliveryFailure::handle()`.

## Invariants

- `NotifyAdminOnEmailDeliveryFailure::handle` logs the booking reference and error. The optional webhook uses `services.slack.admin_webhook_url`. [NotifyAdminOnEmailDeliveryFailure.php](./NotifyAdminOnEmailDeliveryFailure.php#L21)

## Side Effects

- Writes an error log, posts the reference to the configured Slack webhook, and logs webhook failure as a warning. [NotifyAdminOnEmailDeliveryFailure.php](./NotifyAdminOnEmailDeliveryFailure.php#L26)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
