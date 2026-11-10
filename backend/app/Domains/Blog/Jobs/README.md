# Jobs

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Blog/Jobs`.

## Interface

- [InvalidateBlogCacheJob.php](./InvalidateBlogCacheJob.php#L12) declares `InvalidateBlogCacheJob`, `InvalidateBlogCacheJob::handle()`.
- [PublishScheduledBlogPostJob.php](./PublishScheduledBlogPostJob.php#L12) declares `PublishScheduledBlogPostJob`, `PublishScheduledBlogPostJob::handle()`.
- [RegenerateSitemapJob.php](./RegenerateSitemapJob.php#L12) declares `RegenerateSitemapJob`, `RegenerateSitemapJob::handle()`.

## Invariants

- `PublishScheduledBlogPostJob::handle` reloads the article and checks that it is still published with a schedule in the past before applying the publication timestamp. [Scheduling guard](./PublishScheduledBlogPostJob.php#L26).

## Side Effects

- `PublishScheduledBlogPostJob::handle` sets `published_at` when missing and dispatches cache and sitemap jobs. `InvalidateBlogCacheJob::handle` flushes blog tags on taggable stores and forgets targeted keys on other stores. [Publication effects](./PublishScheduledBlogPostJob.php#L35) and [cache invalidation](./InvalidateBlogCacheJob.php#L28).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
