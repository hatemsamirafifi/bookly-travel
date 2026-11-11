# Services

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Admin/Services`.

## Interface

- [AdminAuthorizationService.php](./AdminAuthorizationService.php#L17) declares `AdminAuthorizationService`, `AdminAuthorizationService::can()`, `AdminAuthorizationService::effectivePermissions()`, `AdminAuthorizationService::isSuperAdmin()`.
- [AdminUserService.php](./AdminUserService.php#L21) declares `AdminUserService`, `AdminUserService::createAdmin()`, `AdminUserService::updateAdmin()`, `AdminUserService::suspendAdmin()`, `AdminUserService::reactivateAdmin()`, `AdminUserService::assertNoPrivilegeEscalation()`, `AdminUserService::assertNotLastActiveSuperAdmin()`.
- [AvailabilitySlotService.php](./AvailabilitySlotService.php#L19) declares `AvailabilitySlotService`, `AvailabilitySlotService::slotsForRange()`, `AvailabilitySlotService::renderSlotsTable()`.
- [FinancialOverviewService.php](./FinancialOverviewService.php#L23) declares `FinancialOverviewService`, `FinancialOverviewService::canView()`, `FinancialOverviewService::summary()`, `FinancialOverviewService::reconciliationReport()`, `FinancialOverviewService::exportLedgerCsv()`, `FinancialOverviewService::downloadLedgerCsv()`, `FinancialOverviewService::canExport()`.
- [GovernanceAuditService.php](./GovernanceAuditService.php#L17) declares `GovernanceAuditService`, `GovernanceAuditService::log()`, `GovernanceAuditService::logActor()`, `GovernanceAuditService::logSystem()`.
- [OperationalHealthService.php](./OperationalHealthService.php#L20) declares `OperationalHealthService`, `OperationalHealthService::snapshot()`, `OperationalHealthService::recentFailedJobs()`.
- [PlatformAnalyticsService.php](./PlatformAnalyticsService.php#L24) declares `PlatformAnalyticsService`, `PlatformAnalyticsService::resolveDateRange()`, `PlatformAnalyticsService::kpis()`, `PlatformAnalyticsService::bookingAndRevenueTrends()`, `PlatformAnalyticsService::topTours()`, `PlatformAnalyticsService::topPartners()`, `PlatformAnalyticsService::topDestinations()`.
- [TravelerModerationService.php](./TravelerModerationService.php#L19) declares `TravelerModerationService`, `TravelerModerationService::suspend()`, `TravelerModerationService::reactivate()`, `TravelerModerationService::isAuthorized()`.

## Invariants

- `AdminAuthorizationService::can` requires an admin account with active status, treating a missing status as active. Effective permissions are calculated across the known flags from direct assignments and role grants. The `super_admin` role enables all known flags. [Account guard](./AdminAuthorizationService.php#L42) and [permission resolution](./AdminAuthorizationService.php#L56).

## Side Effects

- `GovernanceAuditService` persists a governance audit entry for either an identified actor or the system. [System entry](./GovernanceAuditService.php#L69) and [persistence](./GovernanceAuditService.php#L90).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
