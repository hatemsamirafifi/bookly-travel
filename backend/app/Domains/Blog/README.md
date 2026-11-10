# Blog

<!-- EGM contract -->

## Overview

Localized article publishing, scheduled visibility, signed previews, categories and author metadata, and blog cache invalidation.

## Subdirectories & Boundaries

- [Actions](./Actions/README.md) — Public declarations include `GeneratePreviewTokenAction`, `GetBlogCategoryAction`, `GetBlogPostAction`.
- [Controllers](./Controllers/README.md) — HTTP delivery of article lists, articles, category pages, and signed previews.
- [Jobs](./Jobs/README.md) — Public declarations include `InvalidateBlogCacheJob`, `PublishScheduledBlogPostJob`, `RegenerateSitemapJob`.
- [Models](./Models/README.md) — Public declarations include `AuthorProfile`, `BlogCategory`, `BlogPost`.
- [Policies](./Policies/README.md) — Public declarations include `BlogCategoryPolicy`, `BlogPostPolicy`.
- [Requests](./Requests/README.md) — Request validators for the public blog endpoints.
- [Services](./Services/README.md) — Public declarations include `BlogCache`, `PreviewTokenService`.
- [Transformers](./Transformers/README.md) — List and detail projections for blog articles in the requested locale.

## Interface

The linked child contracts describe the public interfaces.

## Invariants

See the linked child contracts.

## Side Effects

See the linked child contracts.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
