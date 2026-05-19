<?php

namespace App\Domains\Reviews\Controllers\Admin;

use App\Domains\Reviews\Actions\HideReviewAction;
use App\Domains\Reviews\Actions\ReinstateReviewAction;
use App\Domains\Reviews\Models\Review;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Review::query()
            ->with(['tour', 'traveler', 'auditTrails'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('tour_id')) {
            $query->where('tour_id', $request->input('tour_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $reviews = $query->paginate(15);

        return response()->json([
            'data' => ReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function hide(Review $review, Request $request, HideReviewAction $action): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);

        $action->execute($review, $request->user()->id, $request->input('reason'));

        return response()->json([
            'data' => new ReviewResource($review->fresh()),
        ]);
    }

    public function reinstate(Review $review, Request $request, ReinstateReviewAction $action): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);

        $action->execute($review, $request->user()->id, $request->input('reason'));

        return response()->json([
            'data' => new ReviewResource($review->fresh()),
        ]);
    }
}
