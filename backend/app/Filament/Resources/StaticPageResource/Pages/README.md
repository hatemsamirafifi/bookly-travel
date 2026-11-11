# Pages

<!-- EGM contract -->

## Overview

Lists, creates, views, and edits static pages with localized content and metadata.

## Interface

- [CreateStaticPage.php](./CreateStaticPage.php#L19) declares `CreateStaticPage`.
- [EditStaticPage.php](./EditStaticPage.php#L24) declares `EditStaticPage`, `EditStaticPage::clearLocaleTranslation()`.
- [ListStaticPages.php](./ListStaticPages.php#L8) declares `ListStaticPages`.
- [ViewStaticPage.php](./ViewStaticPage.php#L8) declares `ViewStaticPage`.

## Invariants

- `EditStaticPage::handleRecordUpdate` rejects a supplied stale `loaded_lock_version`. `clearLocaleTranslation` only accepts `es` and `it`. [EditStaticPage.php](./EditStaticPage.php#L113) [EditStaticPage.php](./EditStaticPage.php#L145)

## Side Effects

- Creation, editing, and locale clearing invoke `UpdateStaticPageAction`. Edit actions can generate a preview token and dispatch translation work. [CreateStaticPage.php](./CreateStaticPage.php#L26) [EditStaticPage.php](./EditStaticPage.php#L30) [EditStaticPage.php](./EditStaticPage.php#L139) [EditStaticPage.php](./EditStaticPage.php#L145)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
