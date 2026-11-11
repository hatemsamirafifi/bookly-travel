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
        // The requested booking locale's stored derivative wins, then the
        // tour's actual authored source (which may be Spanish or Italian),
        // then the legacy English row — never a hardcoded English-first view.
        $title = $this->tour->translations->firstWhere('locale', $this->locale)->title
            ?? $this->tour->sourceTranslation()->title
            ?? $this->tour->translations->firstWhere('locale', 'en')->title
            ?? '';
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
            // Purchased departure snapshot: title/pickup exactly as served to
            // the traveler when the booking was created (with the text's
            // actual locale), immune to later renames/retirement.
            'option' => $this->bookingOption(),
            'special_requests' => null,
            'payment_status' => optional($this->payment)->status ?? 'pending',
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
