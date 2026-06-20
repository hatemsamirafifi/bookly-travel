<?php

namespace App\Domains\Reviews\Actions;

use App\Domains\Reviews\DTOs\TravelerReviewDTO;
use App\Domains\Reviews\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

class GetTravelerReviewsAction
{
    public function execute(int $travelerId, int $page = 1): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = Review::with(['tour.translations'])
            ->where('traveler_id', $travelerId)
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'page', $page);

        $data = collect($paginator->items())
            ->map(fn (Review $review) => TravelerReviewDTO::fromReview($review))
            ->all();

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
