<?php

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Models\ReviewResponse;
use App\Domains\Reviews\Models\Review;
use App\Models\Tour;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReviewService
{
    /**
     * List reviews for a partner's tours with optional filters.
     *
     * @param  int  $partnerId  The authenticated partner's ID
     * @param  array{tour_id?: int, rating?: int, has_response?: bool, date_from?: string, date_to?: string, sort?: string, per_page?: int, page?: int}  $filters
     */
    public function listForPartner(int $partnerId, array $filters = []): LengthAwarePaginator
    {
        $query = Review::whereHas('tour', function ($q) use ($partnerId) {
            $q->where('partner_id', $partnerId);
        })
            ->with(['tour', 'traveler']);

        if (! empty($filters['tour_id'])) {
            $query->where('tour_id', $filters['tour_id']);
        }

        if (! empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (isset($filters['has_response'])) {
            if ($filters['has_response']) {
                $query->whereHas('reviewResponse');
            } else {
                $query->whereDoesntHave('reviewResponse');
            }
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'rating_asc' => $query->orderBy('rating', 'asc'),
            'rating_desc' => $query->orderBy('rating', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        return $query->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
    }

    /**
     * Get a review scoped to a partner's tours.
     *
     * Returns null if the review does not belong to one of the partner's tours.
     *
     * @param  int  $reviewId  The review ID
     * @param  int  $partnerId  The authenticated partner's ID
     */
    public function getReviewForPartner(int $reviewId, int $partnerId): ?Review
    {
        return Review::where('id', $reviewId)
            ->whereHas('tour', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            })
            ->first();
    }

    /**
     * Create a response to a review.
     *
     * Only one response is allowed per review. Throws a ConflictException if a
     * response already exists for the given review.
     *
     * @param  int  $reviewId  The review ID to respond to
     * @param  int  $partnerId  The authenticated partner's ID
     * @param  string  $responseText  The response text (max 1000 chars)
     * @return ReviewResponse The created review response
     *
     * @throws NotFoundHttpException If review not found or not owned by partner
     * @throws ConflictHttpException If a response already exists for this review
     */
    public function createResponse(int $reviewId, int $partnerId, string $responseText): ReviewResponse
    {
        $review = $this->getReviewForPartner($reviewId, $partnerId);

        if (! $review) {
            throw new NotFoundHttpException(
                'Review not found.'
            );
        }

        $existingResponse = ReviewResponse::where('review_id', $reviewId)->first();
        if ($existingResponse) {
            throw new ConflictHttpException(
                'A response already exists for this review. Use PUT to update it.'
            );
        }

        return ReviewResponse::create([
            'review_id' => $reviewId,
            'partner_id' => $partnerId,
            'response_text' => $responseText,
        ]);
    }

    /**
     * Update an existing review response.
     *
     * @param  int  $reviewId  The review ID whose response to update
     * @param  int  $partnerId  The authenticated partner's ID
     * @param  string  $responseText  The updated response text
     * @return ReviewResponse The updated review response
     *
     * @throws NotFoundHttpException If review not found, response not found, or not owned by partner
     */
    public function updateResponse(int $reviewId, int $partnerId, string $responseText): ReviewResponse
    {
        $review = $this->getReviewForPartner($reviewId, $partnerId);

        if (! $review) {
            throw new NotFoundHttpException(
                'Review not found.'
            );
        }

        $response = ReviewResponse::where('review_id', $reviewId)
            ->where('partner_id', $partnerId)
            ->first();

        if (! $response) {
            throw new NotFoundHttpException(
                'Review response not found.'
            );
        }

        $response->update([
            'response_text' => $responseText,
            'edited_at' => now(),
        ]);

        return $response->fresh();
    }

    /**
     * Get review summary statistics for a partner's tours.
     *
     * @param  int  $partnerId  The authenticated partner's ID
     * @param  array{tour_id?: int}  $filters
     * @return array<int, mixed>
     */
    public function getTourReviewSummaries(int $partnerId, array $filters = []): array
    {
        $tourIds = Tour::where('partner_id', $partnerId);

        if (! empty($filters['tour_id'])) {
            $tourIds->where('id', $filters['tour_id']);
        }

        $tourIdList = $tourIds->pluck('id');

        $summaries = Review::selectRaw('tour_id, AVG(rating) as average_rating, COUNT(*) as review_count')
            ->whereIn('tour_id', $tourIdList)
            ->whereIn('status', ['visible', 'flagged'])
            ->groupBy('tour_id')
            ->get();

        $tourNames = Tour::whereIn('id', $tourIdList)->pluck('title', 'id');

        return $summaries->map(function ($summary) use ($tourNames) {
            return [
                'tour_id' => $summary->tour_id,
                'tour_name' => $tourNames[$summary->tour_id] ?? null,
                'average_rating' => round((float) $summary->average_rating, 2),
                'review_count' => (int) $summary->review_count,
            ];
        })->values()->all();
    }
}
