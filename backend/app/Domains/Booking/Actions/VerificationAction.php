<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\VerificationResult;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\VoucherService;

/**
 * Spec 014 (FR-023..FR-026, SC-010/011): the single reusable action behind
 * the public verification surface. Resolves an opaque booking reference to a
 * VerificationResult, mapping booking lifecycle → public verification status
 * and stripping all PII by constructing the DTO field-by-field.
 *
 * Side-effect free (FR-025, SC-011): no writes, no counters, no logging keyed
 * to the visitor, no jobs. Malformed references short-circuit before any DB
 * hit; malformed and unknown references both yield null (the controller maps
 * null → 404 with an identical body, so there is no enumeration side-channel).
 *
 * The QR format and URL scheme are stable (FR-023); a future `USED` (redeemed)
 * booking status is supported by adding one `match` arm — no QR/format change.
 */
final class VerificationAction
{
    public function __construct(
        private readonly VoucherService $voucherService,
    ) {}

    public function execute(string $reference): ?VerificationResult
    {
        if (! preg_match($this->referencePattern(), $reference)) {
            return null;
        }

        $booking = Booking::with('tour.translations')
            ->where('reference', $reference)
            ->first();

        if (! $booking) {
            return null;
        }

        return VerificationResult::fromBooking(
            $booking,
            $this->mapStatus($booking->status),
            $this->voucherService->resolveTourTitle($booking),
        );
    }

    /**
     * Opaque booking reference shape (R3): prefix BKO- + 6 chars from
     * Booking::REFERENCE_ALPHABET (the unambiguous alphabet — no I/L/O/0/1).
     * Built dynamically from the model's single source of truth so the
     * verification guard and the reference generator can never diverge (the
     * regex guard is the only timing difference between malformed and unknown:
     * malformed short-circuits before the DB hit; both then return null → 404).
     */
    private function referencePattern(): string
    {
        return '/^' . preg_quote(Booking::REFERENCE_PREFIX, '/')
            . '[' . preg_quote(Booking::REFERENCE_ALPHABET, '/') . ']{'
            . Booking::REFERENCE_LENGTH . '}$/';
    }

    /**
     * Booking lifecycle → public verification status (FR-023, contract
     * verification-api.md §Responses). Forward-compat: any not-yet-mapped
     * lifecycle state (e.g. a future `used`) is logged and returned as a
     * non-valid `UNKNOWN` status — a genuine booking is NEVER silently
     * admitted as VALID; the mapping must be updated explicitly.
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_CANCELLATION_REQUESTED,
            Booking::STATUS_COMPLETED => 'VALID',
            Booking::STATUS_CANCELLED => 'CANCELLED',
            Booking::STATUS_PENDING_PAYMENT => 'PENDING',
            Booking::STATUS_EXPIRED,
            Booking::STATUS_NO_SHOW => 'EXPIRED',
            default => $this->unknownStatus($status),
        };
    }

    private function unknownStatus(string $status): string
    {
        logger()->warning('Verification: unmapped booking status returned as UNKNOWN — add an explicit mapStatus arm', [
            'status' => $status,
        ]);

        return 'UNKNOWN';
    }
}
