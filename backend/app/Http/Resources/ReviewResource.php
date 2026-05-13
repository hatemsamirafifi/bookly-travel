<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewer_name' => $this->reviewerName(),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'edited' => $this->isEdited(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
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
