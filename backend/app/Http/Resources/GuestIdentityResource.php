<?php

namespace App\Http\Resources;

use App\Models\GuestIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GuestIdentity */
class GuestIdentityResource extends JsonResource
{
    /**
     * Transform the guest identity into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'phone' => $this->phone,
            'created_at' => $this->created_at,
        ];
    }
}
