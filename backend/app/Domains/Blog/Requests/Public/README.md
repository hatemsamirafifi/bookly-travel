# Public

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Blog/Requests/Public`.

## Interface

- [ListBlogPostsRequest.php](./ListBlogPostsRequest.php#L7) declares `ListBlogPostsRequest`, `ListBlogPostsRequest::rules()`.
- [ShowBlogPostRequest.php](./ShowBlogPostRequest.php#L7) declares `ShowBlogPostRequest`.
- [ShowBlogPreviewRequest.php](./ShowBlogPreviewRequest.php#L7) declares `ShowBlogPreviewRequest`, `ShowBlogPreviewRequest::rules()`.

## Invariants

- `ListBlogPostsRequest::rules` extends locale validation with an optional category slug, a positive page, and a per-page limit from 1 through 50. [List query constraints](./ListBlogPostsRequest.php#L9).

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
