<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Services\TourService;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TourController
{
    public function __construct(
        private readonly TourService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $filters = $request->validate([
            'status' => 'sometimes|string|in:draft,pending_review,published,rejected,archived',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $tours = $this->service->listForPartner($partnerId, $filters);

        return response()->json($tours);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $tour = $this->service->getForPartner((int) $id, $partnerId);

        if (! $tour) {
            abort(404);
        }

        return response()->json($tour->load(['media', 'pricingTiers', 'availabilityRules', 'availabilityExceptions']));
    }

    public function store(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|min:100|max:5000',
            'category' => 'required|string|max:50',
            'destination' => 'required|string|max:255',
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:hour,day',
            'difficulty_level' => 'required|string|in:easy,moderate,challenging',
            'itinerary' => 'nullable|array',
            'inclusions' => 'nullable|array',
            'meeting_point' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|string|url|max:2048',
            'price_from' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'pricing_tiers' => 'nullable|array',
            'availability_rules' => 'nullable|array',
            'availability_exceptions' => 'nullable|array',
        ]);

        $tour = $this->service->createTour($partnerId, $data);

        return response()->json($tour, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $tour = $this->service->getForPartner((int) $id, $partnerId);

        if (! $tour) {
            abort(404);
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:120',
            'description' => 'sometimes|string|min:100|max:5000',
            'category' => 'sometimes|string|max:50',
            'destination' => 'sometimes|string|max:255',
            'duration_value' => 'sometimes|integer|min:1',
            'duration_unit' => 'sometimes|string|in:hour,day',
            'difficulty_level' => 'sometimes|string|in:easy,moderate,challenging',
            'itinerary' => 'nullable|array',
            'inclusions' => 'nullable|array',
            'meeting_point' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|string|url|max:2048',
            'price_from' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'pricing_tiers' => 'nullable|array',
            'availability_rules' => 'nullable|array',
            'availability_exceptions' => 'nullable|array',
        ]);

        $tour = $this->service->updateTour($tour, $data);

        return response()->json($tour);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $tour = $this->service->getForPartner((int) $id, $partnerId);

        if (! $tour) {
            abort(404);
        }

        $this->service->archiveTour($tour);

        return response()->json(['message' => 'Tour archived.']);
    }

    public function saveDraft(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $tour = $this->service->getForPartner((int) $id, $partnerId);

        if (! $tour) {
            abort(404);
        }

        $payload = $request->validate([
            'payload' => 'required|array',
        ])['payload'];

        $draft = $this->service->saveDraft($partnerId, $tour->id, $payload);

        return response()->json($draft);
    }

    public function latestDraft(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $tour = $this->service->getForPartner((int) $id, $partnerId);

        if (! $tour) {
            abort(404);
        }

        $draft = $this->service->getLatestDraft($partnerId, $tour->id);

        if (! $draft) {
            return response()->json(['message' => 'No draft found.'], 404);
        }

        return response()->json($draft);
    }
}