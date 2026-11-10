# Pages

<!-- EGM contract -->

## Overview

Lists, creates, views, and edits blog posts. The edit page provides preview and translation actions.

## Interface

- [CreateBlogPost.php](./CreateBlogPost.php#L10) declares `CreateBlogPost`.
- [EditBlogPost.php](./EditBlogPost.php#L16) declares `EditBlogPost`.
- [ListBlogPosts.php](./ListBlogPosts.php#L9) declares `ListBlogPosts`.
- [ViewBlogPost.php](./ViewBlogPost.php#L9) declares `ViewBlogPost`.

## Invariants

- `EditBlogPost::handleRecordUpdate` compares a supplied `loaded_lock_version` with the record's fresh version before passing the update to the domain action. [EditBlogPost.php](./EditBlogPost.php#L113)

## Side Effects

- `CreateBlogPost::handleRecordCreation` and `EditBlogPost::handleRecordUpdate` delegate writes to `UpdateBlogPostAction`. `EditBlogPost::translationHeaderActions` delegates synchronization and dispatch to `AutoTranslationService`. [CreateBlogPost.php](./CreateBlogPost.php#L14) [EditBlogPost.php](./EditBlogPost.php#L113) [EditBlogPost.php](./EditBlogPost.php#L51)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
