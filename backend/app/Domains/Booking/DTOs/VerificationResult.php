<?php

namespace App\Domains\Booking\DTOs;

use App\Domains\Booking\Models\Booking;

/**
 * Spec 014 (FR-022, FR-026): read-only payload returned by the public
 * verification endpoint. Constructed field-by-field from a Booking — it
 * NEVER serializes the whole model, so traveler/payment/identity fields
 * cannot leak (SC-010). The DTO is the single source of truth for what the
 * public surface may expose; the transformer only re-shapes it to JSON.
 */
final class VerificationResult
{
    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly string $tourTitle,
        public readonly string $tourDate,
        public readonly int $participantCount,
        public readonly ?string $createdAt,
        public readonly ?string $voucherGeneratedAt,
    ) {}

    public static function fromBooking(Booking $booking, string $status, string $tourTitle): self
    {
        return new self(
            reference: $booking->reference,
            status: $status,
            tourTitle: $tourTitle,
            tourDate: $booking->tour_date->toDateString(),
            participantCount: (int) $booking->participant_count,
            createdAt: $booking->created_at?->toIso8601String(),
            voucherGeneratedAt: $booking->voucher_generated_at?->toIso8601String(),
        );
    }
}
