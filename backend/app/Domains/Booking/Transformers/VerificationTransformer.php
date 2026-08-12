<?php

namespace App\Domains\Booking\Transformers;

use App\Domains\Booking\DTOs\VerificationResult;

/**
 * Spec 014 (FR-022, FR-026, SC-010): serializes a VerificationResult DTO to
 * the public verification JSON envelope. Field-by-field construction — it
 * only ever emits the fields on the DTO, so it cannot leak PII even if the
 * underlying Booking model gains sensitive columns later.
 */
final class VerificationTransformer
{
    public function transform(VerificationResult $result): array
    {
        $data = [
            'reference' => $result->reference,
            'status' => $result->status,
            'tour_title' => $result->tourTitle,
            'tour_date' => $result->tourDate,
            'participant_count' => $result->participantCount,
        ];

        // created_at / voucher_generated_at are optional (MAY be omitted).
        if ($result->createdAt !== null) {
            $data['created_at'] = $result->createdAt;
        }
        if ($result->voucherGeneratedAt !== null) {
            $data['voucher_generated_at'] = $result->voucherGeneratedAt;
        }

        return ['data' => $data];
    }
}
