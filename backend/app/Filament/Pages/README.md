# Pages

<!-- EGM contract -->

## Overview

Standalone admin views for the dashboard, settings, file management, storage, demo content, translation health, operational monitoring, and finance.

## Interface

- [Dashboard.php](./Dashboard.php#L20) declares `Dashboard`, `Dashboard::getWidgets()`.
- [DemoContentManager.php](./DemoContentManager.php#L19) declares `DemoContentManager`, `DemoContentManager::canAccess()`, `DemoContentManager::getViewData()`.
- [FileManagerPage.php](./FileManagerPage.php#L27) declares `FileManagerPage`, `FileManagerPage::canAccess()`, `FileManagerPage::mount()`, `FileManagerPage::updatedFolderId()`, `FileManagerPage::currentFolder()`, `FileManagerPage::breadcrumbs()`, `FileManagerPage::folders()`, `FileManagerPage::assets()`.
- [FileManagerPage.php](./FileManagerPage.php#L110) also exposes `FileManagerPage::trashedAssets()`, `FileManagerPage::kpis()`, `FileManagerPage::assetUsageLabel()`.
- [FinancialOverviewPage.php](./FinancialOverviewPage.php#L18) declares `FinancialOverviewPage`, `FinancialOverviewPage::canAccess()`, `FinancialOverviewPage::mount()`, `FinancialOverviewPage::issueLabels()`, `FinancialOverviewPage::issueLabel()`, `FinancialOverviewPage::loadData()`.
- [OperationalMonitoringPage.php](./OperationalMonitoringPage.php#L22) declares `OperationalMonitoringPage`, `OperationalMonitoringPage::mount()`, `OperationalMonitoringPage::getHeading()`, `OperationalMonitoringPage::canAccess()`, `OperationalMonitoringPage::loadSnapshot()`, `OperationalMonitoringPage::retryFailedTranslations()`.
- [Settings.php](./Settings.php#L28) declares `Settings`, `Settings::canAccess()`, `Settings::mount()`, `Settings::form()`, `Settings::save()`.
- [StorageAnalyticsPage.php](./StorageAnalyticsPage.php#L18) declares `StorageAnalyticsPage`, `StorageAnalyticsPage::canAccess()`, `StorageAnalyticsPage::mount()`, `StorageAnalyticsPage::loadOverview()`, `StorageAnalyticsPage::humanSize()`.
- [TranslationHealthDashboard.php](./TranslationHealthDashboard.php#L18) declares `TranslationHealthDashboard`, `TranslationHealthDashboard::canAccess()`, `TranslationHealthDashboard::mount()`, `TranslationHealthDashboard::loadSummary()`, `TranslationHealthDashboard::statusesVisibleInTables()`.

## Invariants

- `Settings::canAccess` requires `manage_settings`. `FileManagerPage::canAccess` accepts `manage_files` or `manage_media`. [Settings.php](./Settings.php#L45) [FileManagerPage.php](./FileManagerPage.php#L47)
- `TranslationHealthDashboard::canAccess` accepts translation, blog, or CMS management permission. `FinancialOverviewPage::canAccess` accepts finance or broad analytics permission. [TranslationHealthDashboard.php](./TranslationHealthDashboard.php#L35) [FinancialOverviewPage.php](./FinancialOverviewPage.php#L38)

## Side Effects

- `Settings::save` sends each populated settings group to `UpdateSettingsAction`. `FileManagerPage::getHeaderActions` delegates folder creation, movement, and deletion to `FileManagerService`. [Settings.php](./Settings.php#L108) [FileManagerPage.php](./FileManagerPage.php#L130)
- `OperationalMonitoringPage::retryFailedTranslations` invokes `TranslationHealthCheckService::retryAllFailures`. `DemoContentManager::getHeaderActions` runs seeders and flushes the cache during demo generation and offers a separate cache flush. [OperationalMonitoringPage.php](./OperationalMonitoringPage.php#L86) [DemoContentManager.php](./DemoContentManager.php#L40)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
