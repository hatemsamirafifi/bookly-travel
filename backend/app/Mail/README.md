# Mail

<!-- EGM contract -->

## Overview

Mailables for authentication, traveler bookings and vouchers, partner bookings, and partner onboarding and status changes.

## Interface

- [AccountLockedOutMail.php](./AccountLockedOutMail.php#L14) declares `AccountLockedOutMail`, `AccountLockedOutMail::envelope()`, `AccountLockedOutMail::content()`, `AccountLockedOutMail::attachments()`.
- [BookingCancelledMail.php](./BookingCancelledMail.php#L12) declares `BookingCancelledMail`, `BookingCancelledMail::envelope()`, `BookingCancelledMail::content()`.
- [BookingConfirmedMail.php](./BookingConfirmedMail.php#L12) declares `BookingConfirmedMail`, `BookingConfirmedMail::envelope()`, `BookingConfirmedMail::content()`.
- [BookingVoucherMail.php](./BookingVoucherMail.php#L13) declares `BookingVoucherMail`, `BookingVoucherMail::envelope()`, `BookingVoucherMail::content()`, `BookingVoucherMail::attachments()`.
- [PartnerApplicationReceivedMail.php](./PartnerApplicationReceivedMail.php#L18) declares `PartnerApplicationReceivedMail`, `PartnerApplicationReceivedMail::envelope()`, `PartnerApplicationReceivedMail::content()`.
- [PartnerApprovedMail.php](./PartnerApprovedMail.php#L19) declares `PartnerApprovedMail`, `PartnerApprovedMail::envelope()`, `PartnerApprovedMail::content()`.
- [PartnerBookingCancelledMail.php](./PartnerBookingCancelledMail.php#L25) declares `PartnerBookingCancelledMail`, `PartnerBookingCancelledMail::envelope()`, `PartnerBookingCancelledMail::content()`.
- [PartnerInvitationMail.php](./PartnerInvitationMail.php#L13) declares `PartnerInvitationMail`, `PartnerInvitationMail::envelope()`, `PartnerInvitationMail::content()`.
- [PartnerNewBookingMail.php](./PartnerNewBookingMail.php#L22) declares `PartnerNewBookingMail`, `PartnerNewBookingMail::envelope()`, `PartnerNewBookingMail::content()`.
- [PartnerReinstatedMail.php](./PartnerReinstatedMail.php#L18) declares `PartnerReinstatedMail`, `PartnerReinstatedMail::envelope()`, `PartnerReinstatedMail::content()`.
- [PartnerRejectedMail.php](./PartnerRejectedMail.php#L21) declares `PartnerRejectedMail`, `PartnerRejectedMail::envelope()`, `PartnerRejectedMail::content()`.
- [PartnerSuspendedMail.php](./PartnerSuspendedMail.php#L19) declares `PartnerSuspendedMail`, `PartnerSuspendedMail::envelope()`, `PartnerSuspendedMail::content()`.
- [PasswordResetMail.php](./PasswordResetMail.php#L13) declares `PasswordResetMail`, `PasswordResetMail::envelope()`, `PasswordResetMail::content()`, `PasswordResetMail::attachments()`.
- [VerificationMail.php](./VerificationMail.php#L14) declares `VerificationMail`, `VerificationMail::envelope()`, `VerificationMail::content()`, `VerificationMail::attachments()`.

## Invariants

- `BookingVoucherMail::content` falls back to the English voucher view when the selected locale view is missing. [BookingVoucherMail.php](./BookingVoucherMail.php#L33)
- `PartnerBookingCancelledMail` implements `ShouldQueueAfterCommit`. [PartnerBookingCancelledMail.php](./PartnerBookingCancelledMail.php#L25)

## Side Effects

- `BookingVoucherMail::attachments` reads the supplied path as an `application/pdf` attachment and names it with the booking reference. [BookingVoucherMail.php](./BookingVoucherMail.php#L54)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
