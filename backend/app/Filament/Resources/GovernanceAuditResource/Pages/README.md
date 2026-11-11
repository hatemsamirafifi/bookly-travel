# Pages

<!-- EGM contract -->

## Overview

Lists governance audits and displays details for the selected entry.

## Interface

- [ListGovernanceAudits.php](./ListGovernanceAudits.php#L8) declares `ListGovernanceAudits`.
- [ViewGovernanceAudit.php](./ViewGovernanceAudit.php#L8) declares `ViewGovernanceAudit`.

## Invariants

None recorded in this contract.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
