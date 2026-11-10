# Models

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Blog/Models`.

## Interface

- [AuthorProfile.php](./AuthorProfile.php#L9) declares `AuthorProfile`, `AuthorProfile::user()`, `AuthorProfile::contentFor()`.
- [BlogCategory.php](./BlogCategory.php#L9) declares `BlogCategory`, `BlogCategory::posts()`, `BlogCategory::publishedPosts()`, `BlogCategory::scopeActive()`.
- [BlogPost.php](./BlogPost.php#L17) declares `BlogPost`, `BlogPost::author()`, `BlogPost::category()`, `BlogPost::relatedTours()`, `BlogPost::authorProfile()`, `BlogPost::tags()`, `BlogPost::revisions()`, `BlogPost::translationFieldStates()`.
- [BlogPost.php](./BlogPost.php#L137) also exposes `BlogPost::coverMedia()`, `BlogPost::ogImage()`, `BlogPost::scopeFeatured()`, `BlogPost::isPublished()`, `BlogPost::isArchived()`, `BlogPost::isDraft()`, `BlogPost::rawContentFor()`, `BlogPost::isLocaleEligible()`, `BlogPost::contentFor()`, `BlogPost::readingTime()`.
- [BlogTag.php](./BlogTag.php#L12) declares `BlogTag`, `BlogTag::posts()`, `BlogTag::rawNameFor()`, `BlogTag::contentFor()`.

## Invariants

- `BlogPost::scopePublished` requires `published` status and an absent or elapsed schedule. `BlogCategory::scopeActive` limits categories to active rows sorted by display order. [Post visibility](./BlogPost.php#L150) and [category visibility](./BlogCategory.php#L37).

- Lifecycle constants in [BlogPost.php](./BlogPost.php#L21) include `STATUS_DRAFT` → `draft`, `STATUS_PUBLISHED` → `published`, `STATUS_ARCHIVED` → `archived`.

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
