# Actions

<!-- EGM contract -->

## Overview

Local source and public declarations for `backend/app/Domains/Partner/Actions`.

## Interface

- [CompletePartnerInvitationAction.php](./CompletePartnerInvitationAction.php#L15) declares `CompletePartnerInvitationAction`, `CompletePartnerInvitationAction::execute()`.
- [ResubmitPartnerApplicationAction.php](./ResubmitPartnerApplicationAction.php#L11) declares `ResubmitPartnerApplicationAction`, `ResubmitPartnerApplicationAction::execute()`.

## Invariants

- `CompletePartnerInvitationAction::execute` locks the invitation, requires it to be valid, and rejects registration when an account already uses the invited email. [Invitation transaction](./CompletePartnerInvitationAction.php#L19).
- `ResubmitPartnerApplicationAction::execute` requires rejected status and checks the transition against the locked record. [Resubmission guards](./ResubmitPartnerApplicationAction.php#L15).

## Side Effects

- `CompletePartnerInvitationAction::execute` creates the user, approved and active partner, profile and settings, consumes the invitation, creates an auth token, and writes an audit entry. [Account creation](./CompletePartnerInvitationAction.php#L38) and [consumption and audit](./CompletePartnerInvitationAction.php#L68).
- `ResubmitPartnerApplicationAction::execute` updates profile data, returns onboarding to pending, and records a `partner.resubmit` audit entry. [Resubmission effects](./ResubmitPartnerApplicationAction.php#L39).

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
