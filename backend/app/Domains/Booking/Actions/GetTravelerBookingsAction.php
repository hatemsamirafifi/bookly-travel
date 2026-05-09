<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\DTOs\BookingResponseDTO;
use App\Domains\Booking\Models\Booking;
use Illuminate\Pagination\LengthAwarePaginator;

class GetTravelerBookingsAction
{
    public function execute(int $travelerId, int $page = 1, ?string $status = null): array
    {
        $query = Booking::with('tour')
            ->where('traveler_id', $travelerId)
            ->orderBy('tour_date', 'desc');

        if ($status && in_array($status, ['confirmed', 'completed', 'cancelled', 'no_show'], true)) {
            $query->where('status', $status);
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate(10, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(fn (Booking $booking) => BookingResponseDTO::fromBooking($booking))->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
