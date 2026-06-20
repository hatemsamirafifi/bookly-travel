<?php

namespace App\Domains\Reviews\Controllers\Admin;

use App\Domains\Reviews\Actions\HideReviewAction;
use App\Domains\Reviews\Actions\ReinstateReviewAction;
use App\Domains\Reviews\Models\Review;
use App\Http\Requests\Admin\AdminReviewActionRequest;
use App\Http\Requests\Admin\AdminReviewIndexRequest;
use App\Http\Resources\AdminReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class AdminReviewController extends Controller
{
    public function index(AdminReviewIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Review::query()
            ->with(['tour', 'traveler', 'auditTrails.actor'])
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

        if ($request->boolean('flagged')) {
            $query->where('status', 'flagged');
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $reviews = $query->paginate($perPage);

        return response()->json([
            'data' => AdminReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function hide(Review $review, AdminReviewActionRequest $request, HideReviewAction $action): JsonResponse
    {
        Gate::authorize('manage', $review);

        $action->execute($review, $request->user()->id, $request->input('reason'));

        return response()->json([
            'data' => new AdminReviewResource($review->fresh()),
        ]);
    }

    public function reinstate(Review $review, AdminReviewActionRequest $request, ReinstateReviewAction $action): JsonResponse
    {
        Gate::authorize('manage', $review);

        $action->execute($review, $request->user()->id, $request->input('reason'));

        return response()->json([
            'data' => new AdminReviewResource($review->fresh()),
        ]);
    }
}
