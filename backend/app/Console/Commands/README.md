# Commands

<!-- EGM contract -->

## Overview

Operator commands that update scheduled content, permissions, invitation state, and tour translation data.

## Interface

- [AdoptLegacyTourItineraries.php](./AdoptLegacyTourItineraries.php#L12) declares `AdoptLegacyTourItineraries`, `AdoptLegacyTourItineraries::handle()`.
- [ExpirePartnerInvitations.php](./ExpirePartnerInvitations.php#L8) declares `ExpirePartnerInvitations`, `ExpirePartnerInvitations::handle()`.
- [GrantAdminBlogPermission.php](./GrantAdminBlogPermission.php#L10) declares `GrantAdminBlogPermission`, `GrantAdminBlogPermission::handle()`.
- [PublishScheduledContentCommand.php](./PublishScheduledContentCommand.php#L21) declares `PublishScheduledContentCommand`, `PublishScheduledContentCommand::handle()`.
- [QueueTourTranslations.php](./QueueTourTranslations.php#L11) declares `QueueTourTranslations`, `QueueTourTranslations::handle()`.

## Invariants

- `ExpirePartnerInvitations::handle` changes only pending invitations whose `expires_at` is earlier than now. [ExpirePartnerInvitations.php](./ExpirePartnerInvitations.php#L27)
- `QueueTourTranslations` and `AdoptLegacyTourItineraries` take `--after-id` and `--limit` for bounded resumption. Adoption has `--dry-run`. [QueueTourTranslations.php](./QueueTourTranslations.php#L13) [AdoptLegacyTourItineraries.php](./AdoptLegacyTourItineraries.php#L14)

## Side Effects

- `PublishScheduledContentCommand::handle` saves due pages and posts. If either set is nonempty, it logs a governance audit event and dispatches blog cache invalidation and sitemap regeneration. [PublishScheduledContentCommand.php](./PublishScheduledContentCommand.php#L27) [PublishScheduledContentCommand.php](./PublishScheduledContentCommand.php#L56)
- `AdoptLegacyTourItineraries::handle` performs adoption under the tour content lock and dispatches planned translations. `QueueTourTranslations::handle` dispatches translation jobs and optional index repair after commit. [AdoptLegacyTourItineraries.php](./AdoptLegacyTourItineraries.php#L55) [AdoptLegacyTourItineraries.php](./AdoptLegacyTourItineraries.php#L127) [QueueTourTranslations.php](./QueueTourTranslations.php#L55)
- `GrantAdminBlogPermission::handle` updates permission flags for matched admins. [GrantAdminBlogPermission.php](./GrantAdminBlogPermission.php#L94)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
