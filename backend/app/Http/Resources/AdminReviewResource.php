<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewer_name' => $this->reviewerName(),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'tour_id' => $this->tour_id,
            'tour_title' => $this->tour?->title,
            'flagged' => $this->status === 'flagged',
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'audit_trail' => $this->whenLoaded('auditTrails', function () {
                return $this->auditTrails->map(fn ($entry) => [
                    'action' => $entry->action,
                    'reason' => $entry->reason,
                    'created_at' => $entry->created_at?->toIso8601String(),
                    'actor_name' => $entry->actor?->name,
                ]);
            }),
        ];
    }

    private function reviewerName(): string
    {
        if (! $this->traveler) {
            return 'Anonymous Traveler';
        }

        $name = $this->traveler->name ?? '';

        return strtok($name, ' ') ?: $name ?: 'Anonymous Traveler';
    }
}
