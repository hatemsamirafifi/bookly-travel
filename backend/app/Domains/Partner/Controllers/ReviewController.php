<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Requests\StoreReviewResponseRequest;
use App\Domains\Partner\Services\ReviewService;
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
            'has_response' => 'sometimes|boolean',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d',
            'sort' => 'sometimes|string|in:newest,rating_asc,rating_desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $result = $this->service->listForPartner($partnerId, $filters);

        return response()->json($result);
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

        return response()->json($response, 201);
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

        return response()->json($response);
    }
}
