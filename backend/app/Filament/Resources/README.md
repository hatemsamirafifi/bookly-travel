# Resources

<!-- EGM contract -->

## Overview

Filament form, table, detail, and action definitions for administrative catalog, marketplace, content, media, finance, and governance workflows.

## Interface

- [AdminRoleResource.php](./AdminRoleResource.php#L16) declares `AdminRoleResource`, `AdminRoleResource::canViewAny()`, `AdminRoleResource::canCreate()`, `AdminRoleResource::canEdit()`, `AdminRoleResource::canDelete()`, `AdminRoleResource::getEloquentQuery()`, `AdminRoleResource::form()`, `AdminRoleResource::table()`.
- [AdminRoleResource.php](./AdminRoleResource.php#L147) also exposes `AdminRoleResource::getPages()`.
- [AdminUserResource.php](./AdminUserResource.php#L17) declares `AdminUserResource`, `AdminUserResource::canViewAny()`, `AdminUserResource::canGloballySearch()`, `AdminUserResource::getGloballySearchableAttributes()`, `AdminUserResource::getGlobalSearchResultTitle()`, `AdminUserResource::getGlobalSearchResultDetails()`, `AdminUserResource::canCreate()`, `AdminUserResource::canEdit()`.
- [AdminUserResource.php](./AdminUserResource.php#L74) also exposes `AdminUserResource::canDelete()`, `AdminUserResource::getEloquentQuery()`, `AdminUserResource::flagOptions()`, `AdminUserResource::flagLabel()`, `AdminUserResource::form()`, `AdminUserResource::table()`, `AdminUserResource::getPages()`.
- [AuditLogResource.php](./AuditLogResource.php#L14) declares `AuditLogResource`, `AuditLogResource::canViewAny()`, `AuditLogResource::table()`, `AuditLogResource::canCreate()`, `AuditLogResource::getPages()`.
- [AuthorProfileResource.php](./AuthorProfileResource.php#L23) declares `AuthorProfileResource`, `AuthorProfileResource::form()`, `AuthorProfileResource::table()`, `AuthorProfileResource::mergeLocalizedFields()`, `AuthorProfileResource::userOptions()`, `AuthorProfileResource::getPages()`.
- [AvailabilityResource.php](./AvailabilityResource.php#L26) declares `AvailabilityResource`, `AvailabilityResource::canViewAny()`, `AvailabilityResource::canView()`, `AvailabilityResource::canCreate()`, `AvailabilityResource::canEdit()`, `AvailabilityResource::canDelete()`, `AvailabilityResource::canDeleteAny()`, `AvailabilityResource::table()`.
- [AvailabilityResource.php](./AvailabilityResource.php#L121) also exposes `AvailabilityResource::infolist()`, `AvailabilityResource::getRelations()`, `AvailabilityResource::getPages()`.
- [BlogCategoryResource.php](./BlogCategoryResource.php#L19) declares `BlogCategoryResource`, `BlogCategoryResource::form()`, `BlogCategoryResource::table()`, `BlogCategoryResource::getPages()`.
- [BlogPostResource.php](./BlogPostResource.php#L32) declares `BlogPostResource`, `BlogPostResource::canGloballySearch()`, `BlogPostResource::getGloballySearchableAttributes()`, `BlogPostResource::getGlobalSearchResultTitle()`, `BlogPostResource::getGlobalSearchResultDetails()`, `BlogPostResource::form()`, `BlogPostResource::table()`, `BlogPostResource::getPages()`.
- [BlogTagResource.php](./BlogTagResource.php#L21) declares `BlogTagResource`, `BlogTagResource::form()`, `BlogTagResource::tagNameSchema()`, `BlogTagResource::table()`, `BlogTagResource::mergeLocalizedNames()`, `BlogTagResource::getPages()`.
- [BookingResource.php](./BookingResource.php#L20) declares `BookingResource`, `BookingResource::canGloballySearch()`, `BookingResource::getGloballySearchableAttributes()`, `BookingResource::getGlobalSearchResultTitle()`, `BookingResource::getGlobalSearchResultDetails()`, `BookingResource::table()`, `BookingResource::infolist()`, `BookingResource::canCreate()`.
- [BookingResource.php](./BookingResource.php#L326) also exposes `BookingResource::getRelations()`, `BookingResource::getPages()`.
- [CategoryResource.php](./CategoryResource.php#L19) declares `CategoryResource`, `CategoryResource::form()`, `CategoryResource::table()`, `CategoryResource::getPages()`.
- [ContentRevisionResource.php](./ContentRevisionResource.php#L24) declares `ContentRevisionResource`, `ContentRevisionResource::form()`, `ContentRevisionResource::table()`, `ContentRevisionResource::canAccess()`, `ContentRevisionResource::canCreate()`, `ContentRevisionResource::getPages()`.
- [DestinationResource.php](./DestinationResource.php#L23) declares `DestinationResource`, `DestinationResource::form()`, `DestinationResource::table()`, `DestinationResource::getRelations()`, `DestinationResource::canViewAny()`, `DestinationResource::isAuthorized()`, `DestinationResource::getPages()`.
- [FinancialLedgerResource.php](./FinancialLedgerResource.php#L27) declares `FinancialLedgerResource`, `FinancialLedgerResource::canViewAny()`, `FinancialLedgerResource::canViewFinancials()`, `FinancialLedgerResource::canView()`, `FinancialLedgerResource::canCreate()`, `FinancialLedgerResource::canEdit()`, `FinancialLedgerResource::canDelete()`, `FinancialLedgerResource::canDeleteAny()`.
- [FinancialLedgerResource.php](./FinancialLedgerResource.php#L91) also exposes `FinancialLedgerResource::getEloquentQuery()`, `FinancialLedgerResource::table()`, `FinancialLedgerResource::infolist()`, `FinancialLedgerResource::getRelations()`, `FinancialLedgerResource::getPages()`.
- [GovernanceAuditResource.php](./GovernanceAuditResource.php#L18) declares `GovernanceAuditResource`, `GovernanceAuditResource::table()`, `GovernanceAuditResource::infolist()`, `GovernanceAuditResource::canCreate()`, `GovernanceAuditResource::canEdit()`, `GovernanceAuditResource::canDelete()`, `GovernanceAuditResource::canDeleteAny()`, `GovernanceAuditResource::getRelations()`.
- [GovernanceAuditResource.php](./GovernanceAuditResource.php#L184) also exposes `GovernanceAuditResource::getPages()`.
- [MediaAssetResource.php](./MediaAssetResource.php#L24) declares `MediaAssetResource`, `MediaAssetResource::getPluralLabel()`, `MediaAssetResource::form()`, `MediaAssetResource::table()`, `MediaAssetResource::humanSize()`, `MediaAssetResource::canViewAny()`, `MediaAssetResource::canGloballySearch()`, `MediaAssetResource::getGloballySearchableAttributes()`.
- [MediaAssetResource.php](./MediaAssetResource.php#L222) also exposes `MediaAssetResource::getGlobalSearchResultTitle()`, `MediaAssetResource::getGlobalSearchResultDetails()`, `MediaAssetResource::getPages()`.
- [PartnerResource.php](./PartnerResource.php#L25) declares `PartnerResource`, `PartnerResource::getRecordTitle()`, `PartnerResource::getNavigationBadge()`, `PartnerResource::getNavigationBadgeColor()`, `PartnerResource::canGloballySearch()`, `PartnerResource::getGloballySearchableAttributes()`, `PartnerResource::getGlobalSearchResultTitle()`, `PartnerResource::getGlobalSearchResultDetails()`.
- [PartnerResource.php](./PartnerResource.php#L82) also exposes `PartnerResource::table()`, `PartnerResource::infolist()`, `PartnerResource::getRelations()`, `PartnerResource::getPages()`.
- [PaymentResource.php](./PaymentResource.php#L25) declares `PaymentResource`, `PaymentResource::canViewAny()`, `PaymentResource::canGloballySearch()`, `PaymentResource::getGloballySearchableAttributes()`, `PaymentResource::getGlobalSearchResultTitle()`, `PaymentResource::getGlobalSearchResultDetails()`, `PaymentResource::canViewFinancials()`, `PaymentResource::canView()`.
- [PaymentResource.php](./PaymentResource.php#L92) also exposes `PaymentResource::canCreate()`, `PaymentResource::canEdit()`, `PaymentResource::canDelete()`, `PaymentResource::canDeleteAny()`, `PaymentResource::getEloquentQuery()`, `PaymentResource::table()`, `PaymentResource::infolist()`, `PaymentResource::getRelations()`, `PaymentResource::getPages()`.
- [ReviewResource.php](./ReviewResource.php#L22) declares `ReviewResource`, `ReviewResource::getNavigationBadge()`, `ReviewResource::getNavigationBadgeColor()`, `ReviewResource::canGloballySearch()`, `ReviewResource::getGloballySearchableAttributes()`, `ReviewResource::getGlobalSearchResultTitle()`, `ReviewResource::getGlobalSearchResultDetails()`, `ReviewResource::table()`.
- [ReviewResource.php](./ReviewResource.php#L253) also exposes `ReviewResource::infolist()`, `ReviewResource::canCreate()`, `ReviewResource::getRelations()`, `ReviewResource::getPages()`.
- [StaticPageResource.php](./StaticPageResource.php#L35) declares `StaticPageResource`, `StaticPageResource::canGloballySearch()`, `StaticPageResource::getGloballySearchableAttributes()`, `StaticPageResource::getGlobalSearchResultTitle()`, `StaticPageResource::getGlobalSearchResultDetails()`, `StaticPageResource::form()`, `StaticPageResource::localizedSchema()`, `StaticPageResource::table()`.
- [StaticPageResource.php](./StaticPageResource.php#L199) also exposes `StaticPageResource::infolist()`, `StaticPageResource::getPages()`.
- [TourResource.php](./TourResource.php#L25) declares `TourResource`, `TourResource::getNavigationBadge()`, `TourResource::getNavigationBadgeColor()`, `TourResource::canGloballySearch()`, `TourResource::getGloballySearchableAttributes()`, `TourResource::getGlobalSearchResultTitle()`, `TourResource::getGlobalSearchResultDetails()`, `TourResource::form()`.
- [TourResource.php](./TourResource.php#L162) also exposes `TourResource::table()`, `TourResource::infolist()`, `TourResource::getRelations()`, `TourResource::getPages()`.
- [TravelerResource.php](./TravelerResource.php#L29) declares `TravelerResource`, `TravelerResource::form()`, `TravelerResource::table()`, `TravelerResource::infolist()`, `TravelerResource::getRelations()`, `TravelerResource::canViewAny()`, `TravelerResource::canGloballySearch()`, `TravelerResource::getGloballySearchableAttributes()`.
- [TravelerResource.php](./TravelerResource.php#L273) also exposes `TravelerResource::getGlobalSearchResultTitle()`, `TravelerResource::getGlobalSearchResultDetails()`, `TravelerResource::canView()`, `TravelerResource::getEloquentQuery()`, `TravelerResource::getPages()`.
- [AdminRoleResource](./AdminRoleResource/README.md) — Page group for managing custom admin roles and inspecting system roles.
- [AdminUserResource](./AdminUserResource/README.md) — Page group for creating and maintaining administrator accounts, including role and direct permission assignments.
- [AuditLogResource](./AuditLogResource/README.md) — Page group for inspecting authentication audit records.
- [AuthorProfileResource](./AuthorProfileResource/README.md) — Page group for creating and editing blog author records.
- [AvailabilityResource](./AvailabilityResource/README.md) — Page group for inspecting tour availability.
- [BlogCategoryResource](./BlogCategoryResource/README.md) — Page group for managing blog categories.
- [BlogPostResource](./BlogPostResource/README.md) — Page group for authoring and previewing blog posts and dispatching translation work.
- [BlogTagResource](./BlogTagResource/README.md) — Page group for editing blog taxonomy tags.
- [BookingResource](./BookingResource/README.md) — Page group for inspecting bookings and downloading booking data.
- [CategoryResource](./CategoryResource/README.md) — Page group for maintaining tour categories.
- [ContentRevisionResource](./ContentRevisionResource/README.md) — Page group for inspecting content history and restoring revisions.
- [DestinationResource](./DestinationResource/README.md) — Page group for maintaining tour destinations.
- [FinancialLedgerResource](./FinancialLedgerResource/README.md) — Page group for viewing financial ledger entries and downloading filtered CSV data.
- [GovernanceAuditResource](./GovernanceAuditResource/README.md) — Page group for inspecting governance audit events.
- [MediaAssetResource](./MediaAssetResource/README.md) — Page group for browsing media assets, uploading files, and editing metadata.
- [PartnerResource](./PartnerResource/README.md) — Page group for reviewing partner records and onboarding states.
- [PaymentResource](./PaymentResource/README.md) — Page group for inspecting payment transactions.
- [ReviewResource](./ReviewResource/README.md) — Page group for inspecting reviews and accessing moderation actions.
- [StaticPageResource](./StaticPageResource/README.md) — Page group for authoring, previewing, and translating static CMS pages.
- [TourResource](./TourResource/README.md) — Page group for creating, inspecting, and editing tours and their translations.
- [TravelerResource](./TravelerResource/README.md) — Page group for inspecting and moderating traveler accounts.

## Invariants

- `BookingResource::table` gates transition actions on both actor policy and the booking's state machine. `ReviewResource::table` checks review moderation permission and target-state eligibility for hide and restore. [BookingResource.php](./BookingResource.php#L134) [ReviewResource.php](./ReviewResource.php#L171)
- `FinancialLedgerResource` and `PaymentResource` disallow create, edit, and delete. [FinancialLedgerResource.php](./FinancialLedgerResource.php#L71) [PaymentResource.php](./PaymentResource.php#L92)

## Side Effects

- Resource actions invoke domain operations for tour publication, booking transitions, partner onboarding decisions, and review moderation. [TourResource.php](./TourResource.php#L242) [BookingResource.php](./BookingResource.php#L134) [PartnerResource.php](./PartnerResource.php#L147) [ReviewResource.php](./ReviewResource.php#L155)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
