<?php

namespace App\Domains\Reviews\Controllers\Partner;

use App\Domains\Reviews\Models\Review;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PartnerReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->user()->id;

        $query = Review::query()
            ->whereHas('tour', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            })
            ->with(['tour', 'traveler'])
            ->orderBy('created_at', 'desc');

        if ($request->has('tour_id')) {
            $query->where('tour_id', $request->input('tour_id'));
        }

        $reviews = $query->paginate(10);

        $tourSummaries = Review::query()
            ->selectRaw('tour_id, AVG(rating)::numeric(3,2) as average_rating, COUNT(*) as review_count')
            ->whereIn('tour_id', function ($q) use ($partnerId) {
                $q->select('id')->from('tours')->where('partner_id', $partnerId);
            })
            ->whereIn('status', ['visible', 'flagged'])
            ->groupBy('tour_id')
            ->get();

        return response()->json([
            'data' => ReviewResource::collection($reviews),
            'meta' => [
                'tour_summaries' => $tourSummaries,
                'current_page' => $reviews->currentPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }
}
