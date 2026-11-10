# Rules

<!-- EGM contract -->

## Overview

Local source in `ValidIban.php`.

## Interface

- [ValidIban.php](./ValidIban.php#L18) declares `ValidIban`, `ValidIban::validate()`.

## Invariants

- `ValidIban::validate` accepts null and empty values. Other values must pass the normalized structural check and a MOD 97 checksum with remainder 1. [IBAN validation](./ValidIban.php#L23) and [checksum](./ValidIban.php#L53).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
