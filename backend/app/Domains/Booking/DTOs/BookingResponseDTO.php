<?php

namespace App\Domains\Booking\DTOs;

use App\Domains\Booking\Models\Booking;

class BookingResponseDTO
{
    public static function fromBooking(Booking $booking): array
    {
        $tour = $booking->tour;

        $data = [
            'reference' => $booking->reference,
            'tour' => [
                'slug' => $tour->slug,
                'title' => '',
                'location' => $tour->location,
            ],
            'tour_date' => $booking->tour_date->toDateString(),
            'participant_count' => $booking->participant_count,
            'pricing' => [
                'price_per_person' => [
                    'amount' => $booking->price_per_person,
                    'currency' => $booking->currency,
                    'formatted' => Booking::formatPrice($booking->price_per_person, $booking->currency),
                ],
                'total' => [
                    'amount' => $booking->total_price,
                    'currency' => $booking->currency,
                    'formatted' => Booking::formatPrice($booking->total_price, $booking->currency),
                ],
            ],
            'total_price' => [
                'amount' => $booking->total_price,
                'currency' => $booking->currency,
                'formatted' => Booking::formatPrice($booking->total_price, $booking->currency),
            ],
            'status' => $booking->status,
            'cancellation_policy' => $booking->cancellation_policy,
            'cancellation_window_hours' => $booking->cancellation_window_hours,
            'can_cancel' => $booking->canCancel(),
            'created_at' => $booking->created_at->toIso8601String(),
        ];

        // Add traveler name when loaded
        if ($booking->relationLoaded('traveler')) {
            $data['traveler_name'] = $booking->traveler->name;
        }

        // Add richer tour detail when available
        if ($tour->relationLoaded('translations')) {
            $translation = $tour->translations->firstWhere('locale', $booking->locale)
                ?? $tour->translations->firstWhere('locale', 'en');
            if ($translation) {
                $data['tour']['title'] = $translation->title;
            }
        }

        $data['tour'] = array_merge($data['tour'], [
            'cover_image_url' => $tour->cover_image_url,
        ]);

        return $data;
    }
}
