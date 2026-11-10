# Pages

<!-- EGM contract -->

## Overview

Lists ledger entries, exposes a CSV export, and displays entry details.

## Interface

- [ListFinancialLedgerEntries.php](./ListFinancialLedgerEntries.php#L22) declares `ListFinancialLedgerEntries`.
- [ViewFinancialLedgerEntry.php](./ViewFinancialLedgerEntry.php#L14) declares `ViewFinancialLedgerEntry`, `ViewFinancialLedgerEntry::getTitle()`, `ViewFinancialLedgerEntry::infolist()`.

## Invariants

- `ListFinancialLedgerEntries::getHeaderActions` exposes the export only when `FinancialOverviewService::canExport` accepts the actor. [ListFinancialLedgerEntries.php](./ListFinancialLedgerEntries.php#L49)

## Side Effects

- The export action delegates to `FinancialOverviewService::downloadLedgerCsv` with entry type and date filters. [ListFinancialLedgerEntries.php](./ListFinancialLedgerEntries.php#L51)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
