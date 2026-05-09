<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\Booking;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPartnerBookingsAction
{
    public function execute(
        User $partner,
        ?string $tourSlug = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $page = 1,
    ): array {
        $query = Booking::with(['tour', 'traveler'])
            ->whereHas('tour', function ($q) use ($partner) {
                $q->where('partner_id', $partner->id);
            })
            ->orderBy('tour_date', 'desc');

        if ($tourSlug) {
            $query->whereHas('tour', function ($q) use ($tourSlug) {
                $q->where('slug', $tourSlug);
            });
        }

        if ($status && in_array($status, ['confirmed', 'completed', 'cancelled', 'no_show'], true)) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->where('tour_date', '>=', $dateFrom);
        } else {
            $query->where('tour_date', '>=', now()->toDateString());
        }

        if ($dateTo) {
            $query->where('tour_date', '<=', $dateTo);
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate(25, ['*'], 'page', $page);

        $data = $paginator->map(function (Booking $booking) {
            return [
                'reference' => $booking->reference,
                'traveler_name' => $booking->traveler?->name ?? '',
                'tour' => [
                    'slug' => $booking->tour->slug,
                    'title' => '',
                ],
                'tour_date' => $booking->tour_date->toDateString(),
                'participant_count' => $booking->participant_count,
                'total_price' => [
                    'amount' => $booking->total_price,
                    'currency' => $booking->currency,
                    'formatted' => Booking::formatPrice($booking->total_price, $booking->currency),
                ],
                'status' => $booking->status,
                'created_at' => $booking->created_at->toIso8601String(),
            ];
        })->values()->all();

        // Compute aggregates
        $baseQuery = Booking::whereHas('tour', function ($q) use ($partner) {
            $q->where('partner_id', $partner->id);
        })->where('tour_date', '>=', now()->toDateString());

        $aggregates = [
            'total_bookings' => $baseQuery->count(),
            'by_status' => [
                'confirmed' => (clone $baseQuery)->where('status', 'confirmed')->count(),
                'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
                'cancelled' => (clone $baseQuery)->where('status', 'cancelled')->count(),
                'no_show' => (clone $baseQuery)->where('status', 'no_show')->count(),
            ],
            'by_tour' => [],
        ];

        // Per-tour aggregates — single query approach
        $tourCounts = (clone $baseQuery)
            ->selectRaw('tour_id, status, COUNT(*) as count')
            ->groupBy('tour_id', 'status')
            ->get();
        $tours = \App\Models\Tour::whereIn('id', $tourCounts->pluck('tour_id')->unique())->pluck('slug', 'id');
        foreach ($tourCounts->groupBy('tour_id') as $tourId => $rows) {
            $slug = $tours[$tourId] ?? null;
            if ($slug) {
                $aggregates['by_tour'][$slug] = [
                    'confirmed' => (int) $rows->firstWhere('status', 'confirmed')?->count,
                    'total' => $rows->sum('count'),
                ];
            }
        }

        return [
            'data' => $data,
            'aggregates' => $aggregates,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
