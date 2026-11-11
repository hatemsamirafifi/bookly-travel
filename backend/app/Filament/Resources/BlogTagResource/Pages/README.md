# Pages

<!-- EGM contract -->

## Overview

Lists, creates, and edits blog tags with localized values.

## Interface

- [CreateBlogTag.php](./CreateBlogTag.php#L9) declares `CreateBlogTag`.
- [EditBlogTag.php](./EditBlogTag.php#L10) declares `EditBlogTag`.
- [ListBlogTags.php](./ListBlogTags.php#L8) declares `ListBlogTags`.

## Invariants

None recorded in this contract.

## Side Effects

- `CreateBlogTag::handleRecordCreation` persists the converted form data. `EditBlogTag::handleRecordUpdate` updates the existing record. [CreateBlogTag.php](./CreateBlogTag.php#L16) [EditBlogTag.php](./EditBlogTag.php#L32)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
