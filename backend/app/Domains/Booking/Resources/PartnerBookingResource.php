<?php

namespace App\Domains\Booking\Resources;

use App\Domains\Booking\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class PartnerBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $title = $this->tour->translations->where('locale', $this->locale)->value('title')
            ?? $this->tour->translations->where('locale', 'en')->value('title') ?? '';
        $traveler = $this->traveler ?? $this->guestIdentity;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'tour' => [
                'id' => $this->tour_id,
                'slug' => $this->tour->slug,
                'title' => $title,
                'cover_image_url' => $this->tour->cover_image_url,
            ],
            'traveler' => [
                'id' => $this->traveler_id,
                'name' => optional($traveler)->name ?? '',
                'email' => optional($traveler)->email ?? '',
                'phone' => optional($traveler)->phone,
            ],
            'booking_date' => $this->created_at->toDateString(),
            'tour_date' => $this->tour_date->toDateString(),
            'tour_time' => $this->start_time?->format('H:i'),
            'participants' => [[
                'tier_id' => 'standard',
                'tier_name' => 'Standard',
                'count' => $this->participant_count,
                'price_per_person' => $this->price_per_person / 100,
            ]],
            'total_participants' => $this->participant_count,
            'total_amount' => $this->total_price / 100,
            'currency' => $this->currency,
            'special_requests' => null,
            'payment_status' => optional($this->payment)->status ?? 'pending',
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
