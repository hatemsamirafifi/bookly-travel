# Models

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Admin/Models`.

## Interface

- [AdminPermission.php](./AdminPermission.php#L17) declares `AdminPermission`, `AdminPermission::user()`.
- [AdminRole.php](./AdminRole.php#L17) declares `AdminRole`, `AdminRole::users()`, `AdminRole::systemRoleTemplates()`, `AdminRole::ensureSystemRoles()`, `AdminRole::grants()`.
- [CmsContent.php](./CmsContent.php#L12) declares `CmsContent`, `CmsContent::hasBody()`.
- [GovernanceAuditLog.php](./GovernanceAuditLog.php#L22) declares `GovernanceAuditLog`, `GovernanceAuditLog::actor()`, `GovernanceAuditLog::target()`.
- [StaticPage.php](./StaticPage.php#L24) declares `StaticPage`, `StaticPage::updatedBy()`, `StaticPage::ogImage()`, `StaticPage::revisions()`, `StaticPage::translationFieldStates()`, `StaticPage::governanceAuditLogs()`, `StaticPage::scopePublished()`, `StaticPage::rawContentFor()`.
- [StaticPage.php](./StaticPage.php#L125) also exposes `StaticPage::hasCompleteLocale()`, `StaticPage::isLocaleEligible()`, `StaticPage::isPublished()`, `StaticPage::contentFor()`.

## Invariants

- `GovernanceAuditLog::booted` rejects updates and deletes through the Eloquent model hooks. Its actor and target are polymorphic relations. [Append-only model hooks](./GovernanceAuditLog.php#L38).

- Lifecycle constants in [StaticPage.php](./StaticPage.php#L26) include `STATUS_DRAFT` → `draft`, `STATUS_PUBLISHED` → `published`.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
