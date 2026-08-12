<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsReviewerName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for the partner reviews endpoint (spec-009, contracts/api.md).
 *
 * Emits the contract shape and the two fields the live dashboard additionally
 * needs (`tour_title`, `response`). Critically, it does NOT expose the raw
 * `Review` columns (`traveler_id`, `booking_id`, `tour_id`, `locale`,
 * `edited_at`) — closing the PII leak from returning raw models.
 */
class PartnerReviewResource extends JsonResource
{
    use FormatsReviewerName;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tour_slug' => $this->whenLoaded('tour', fn () => $this->tour?->slug),
            'tour_title' => $this->whenLoaded('tour', fn () => $this->tour?->displayTitle()),
            'reviewer_name' => $this->reviewerName(),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'response' => $this->whenLoaded('reviewResponse', function () {
                if (! $this->reviewResponse) {
                    return;
                }

                return [
                    'response_text' => $this->reviewResponse->response_text,
                    'created_at' => $this->reviewResponse->created_at?->toIso8601String(),
                    'updated_at' => $this->reviewResponse->updated_at?->toIso8601String(),
                ];
            }),
        ];
    }
}
