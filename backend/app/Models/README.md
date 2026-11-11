# Models

<!-- EGM contract -->

## Overview

Shared Eloquent identity and catalog models, including tours, options, translations, destinations, categories, and auth audits.

## Interface

- [AuthAuditLog.php](./AuthAuditLog.php#L10) declares `AuthAuditLog`, `AuthAuditLog::user()`, `AuthAuditLog::scopeOfType()`.
- [Category.php](./Category.php#L8) declares `Category`, `Category::tours()`, `Category::publishedTourCount()`, `Category::scopePopularWithCounts()`.
- [Destination.php](./Destination.php#L17) declares `Destination`, `Destination::tours()`, `Destination::publishedTours()`, `Destination::firstOrCreateBySlug()`, `Destination::scopeOrdered()`.
- [GuestIdentity.php](./GuestIdentity.php#L11) declares `GuestIdentity`, `GuestIdentity::convertedUser()`, `GuestIdentity::scopeActive()`, `GuestIdentity::scopeAnonymizable()`, `GuestIdentity::convertToUser()`, `GuestIdentity::anonymize()`.
- [Tour.php](./Tour.php#L26) declares `Tour`, `Tour::queueMakeSearchable()`, `Tour::queueRemoveFromSearch()`, `Tour::clearDetailCache()`, `Tour::toSearchableArray()`, `Tour::shouldBeSearchable()`, `Tour::isPubliclyBookable()`, `Tour::scopePublished()`.
- [Tour.php](./Tour.php#L255) also exposes `Tour::scopeBookable()`, `Tour::hasValidPricing()`, `Tour::hasOptionsConfigured()`, `Tour::supportsFieldTranslation()`, `Tour::activeOptions()`, `Tour::hasActiveOptions()`, `Tour::hasUpcomingAvailability()`, `Tour::lowestPriceAmount()`, `Tour::currency()`, `Tour::averageRating()`, `Tour::reviewCount()`, `Tour::allImageUrls()`, `Tour::upcomingAvailableDates()`, `Tour::operatesOnDate()`, `Tour::startTimeForDate()`, `Tour::guideLanguages()`, `Tour::translations()`, `Tour::translationStates()`, `Tour::displayTitle()`, `Tour::sourceLocale()`, `Tour::sourceTranslation()`, `Tour::derivativeLocalesFor()`, `Tour::derivativeLocales()`, `Tour::category()`, `Tour::partner()`, `Tour::formatPrice()`, `Tour::media()`, `Tour::pricingTiers()`, `Tour::options()`, `Tour::availabilityRules()`, `Tour::availabilityExceptions()`, `Tour::drafts()`, `Tour::bookings()`, `Tour::governanceAuditLogs()`, `Tour::reviewDistribution()`, `Tour::partnerRecord()`.
- [TourOption.php](./TourOption.php#L27) declares `TourOption`, `TourOption::tour()`, `TourOption::translations()`, `TourOption::scopeActive()`, `TourOption::isActive()`, `TourOption::coversWeekday()`, `TourOption::effectiveCapacity()`, `TourOption::startTimeString()`.
- [TourOption.php](./TourOption.php#L146) also exposes `TourOption::guideLanguages()`.
- [TourOptionTranslation.php](./TourOptionTranslation.php#L16) declares `TourOptionTranslation`, `TourOptionTranslation::option()`.
- [TourTranslation.php](./TourTranslation.php#L22) declares `TourTranslation`, `TourTranslation::tour()`.
- [TourTranslationState.php](./TourTranslationState.php#L16) declares `TourTranslationState`, `TourTranslationState::tour()`.
- [User.php](./User.php#L24) declares `User`, `User::guestIdentities()`, `User::wishlists()`, `User::getFullNameAttribute()`, `User::auditLogs()`, `User::bookings()`, `User::reviews()`, `User::partner()`.
- [User.php](./User.php#L144) also exposes `User::adminPermission()`, `User::adminRoles()`, `User::authorProfile()`.

## Invariants

- `User::canAccessPanel` requires the `admin` role and an active status, treating a missing status as active. [User.php](./User.php#L171)
- `Tour::shouldBeSearchable` delegates to `isPubliclyBookable`, which requires publication, valid pricing, and upcoming availability. The `scopeBookable` SQL filter uses the presence of availability rules as its availability condition. [Tour.php](./Tour.php#L213) [Tour.php](./Tour.php#L255)
- `Tour::canTransitionTo` allows only configured status transitions and requires an approved owning partner to publish. [Tour.php](./Tour.php#L798)

- Lifecycle constants in [TourOption.php](./TourOption.php#L29) include `STATUS_ACTIVE` → `active`, `STATUS_RETIRED` → `retired`.

## Side Effects

- `Tour::booted` clears per-locale detail cache keys on save or delete, including the previous slug after a slug change. [Tour.php](./Tour.php#L83)
- `GuestIdentity::convertToUser` quietly persists the converted user reference. `anonymize` overwrites personal fields and stamps `anonymized_at`. [GuestIdentity.php](./GuestIdentity.php#L61) [GuestIdentity.php](./GuestIdentity.php#L73)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
