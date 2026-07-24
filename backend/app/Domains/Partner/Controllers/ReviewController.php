<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Requests\StoreReviewResponseRequest;
use App\Domains\Partner\Services\ReviewService;
use App\Http\Resources\PartnerReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReviewController
{
    public function __construct(
        private readonly ReviewService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');

        $filters = $request->validate([
            'tour_id' => 'sometimes|integer',
            'rating' => 'sometimes|integer|min:1|max:5',
            'has_response' => 'sometimes|in:true,false,1,0,True,False',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d',
            'sort' => 'sometimes|string|in:newest,rating_asc,rating_desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if (isset($filters['has_response'])) {
            $filters['has_response'] = filter_var($filters['has_response'], FILTER_VALIDATE_BOOLEAN);
        }

        $result = $this->service->listForPartner($partnerId, $filters);
        $summaries = $this->service->getTourReviewSummaries($partnerId, $filters);

        // $result->items() (models) instead of the paginator keeps JsonResource
        // from injecting its own pagination `meta` — we emit the contract meta
        // shape (incl. tour_summaries) ourselves.
        return response()->json([
            'data' => PartnerReviewResource::collection($result->items()),
            'meta' => [
                'tour_summaries' => $summaries,
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function storeResponse(StoreReviewResponseRequest $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validated();

        try {
            $response = $this->service->createResponse((int) $id, $partnerId, $data['response_text']);
        } catch (NotFoundHttpException $e) {
            abort(404, 'Review not found.');
        }

        return response()->json(['data' => $response], 201);
    }

    public function updateResponse(StoreReviewResponseRequest $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validated();

        try {
            $response = $this->service->updateResponse((int) $id, $partnerId, $data['response_text']);
        } catch (NotFoundHttpException $e) {
            abort(404, 'Review not found or no existing response.');
        }

        return response()->json(['data' => $response]);
    }
}
