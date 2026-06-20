<?php

namespace App\Domains\Reviews\Controllers\Public;

use App\Domains\Reviews\Actions\EditReviewAction;
use App\Domains\Reviews\Actions\SubmitReviewAction;
use App\Domains\Reviews\Models\Review;
use App\Http\Resources\ReviewResource;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;

class ReviewController extends Controller
{
    public function store(Request $request, SubmitReviewAction $action): JsonResponse
    {
        $traveler = $request->user();

        $executed = RateLimiter::attempt(
            'review-submit:' . $traveler->id,
            10,
            function () {},
            3600
        );

        if (! $executed) {
            return response()->json([
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'You have exceeded the review submission limit. Please try again later.',
                ],
            ], 429);
        }

        $validated = $request->validate([
            'booking_reference' => 'required|string|exists:bookings,reference',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'locale' => 'required|string|in:en,es,it',
        ]);

        $review = $action->execute(
            $validated['booking_reference'],
            $validated['rating'],
            $validated['comment'] ?? null,
            $validated['locale'],
            $traveler
        );

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Review $review, EditReviewAction $action): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review = $action->execute(
            $review,
            $validated['rating'],
            $validated['comment'] ?? null,
            $request->user()
        );

        return (new ReviewResource($review))->response();
    }

    public function index(Request $request, $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        $reviews = Review::where('tour_id', $tour->id)
            ->whereIn('status', ['visible', 'flagged'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 5));

        return response()->json([
            'data' => ReviewResource::collection($reviews),
            'meta' => [
                'average_rating' => $tour->averageRating(),
                'review_count' => $tour->reviewCount(),
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }
}
