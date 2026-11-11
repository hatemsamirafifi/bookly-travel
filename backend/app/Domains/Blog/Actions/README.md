# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Blog/Actions`.

## Interface

- [GeneratePreviewTokenAction.php](./GeneratePreviewTokenAction.php#L7) declares `GeneratePreviewTokenAction`, `GeneratePreviewTokenAction::execute()`.
- [GetBlogCategoryAction.php](./GetBlogCategoryAction.php#L15) declares `GetBlogCategoryAction`, `GetBlogCategoryAction::execute()`.
- [GetBlogPostAction.php](./GetBlogPostAction.php#L11) declares `GetBlogPostAction`, `GetBlogPostAction::execute()`.
- [GetBlogPostPreviewAction.php](./GetBlogPostPreviewAction.php#L12) declares `GetBlogPostPreviewAction`, `GetBlogPostPreviewAction::execute()`.
- [ListBlogPostsAction.php](./ListBlogPostsAction.php#L14) declares `ListBlogPostsAction`, `ListBlogPostsAction::execute()`.
- [UpdateBlogPostAction.php](./UpdateBlogPostAction.php#L19) declares `UpdateBlogPostAction`, `UpdateBlogPostAction::execute()`.

## Invariants

- `UpdateBlogPostAction::execute` enforces slug uniqueness, accepts only the draft, published, and archived status values, and requires an English title and body for publishing. [Publishing guards](./UpdateBlogPostAction.php#L35).
- `GetBlogPostPreviewAction::execute` checks the requested locale and verifies the preview token before looking up the post by slug. [Preview authorization](./GetBlogPostPreviewAction.php#L24).

## Side Effects

- `UpdateBlogPostAction::execute` saves the post in a transaction and dispatches cache and sitemap invalidation. For a future schedule, it also dispatches a delayed publication job. [Post write](./UpdateBlogPostAction.php#L134) and [job dispatch](./UpdateBlogPostAction.php#L194).
- `ListBlogPostsAction::execute` supplies a five-minute TTL for localized list results. `BlogCache::remember` stores tagged entries when supported and otherwise computes the result directly. [List cache](./ListBlogPostsAction.php#L47) and [cache fallback](../Services/BlogCache.php#L26).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
