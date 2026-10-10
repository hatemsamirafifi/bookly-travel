<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Requests\StoreTourRequest;
use App\Domains\Partner\Requests\UpdateTourRequest;
use App\Domains\Partner\Services\TourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController
{
    public function __construct(
        private readonly TourService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $filters = $request->validate([
            'status' => 'sometimes|string|in:draft,pending_review,published,rejected,archived',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $tours = $this->service->listForPartner($partnerId, $filters);

        return response()->json([
            'data' => $tours->items(),
            'meta' => [
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');

        return response()->json(['data' => $this->service->getOwnedTourDetail((int) $id, $partnerId)]);
    }

    public function store(StoreTourRequest $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validated();

        $data = $this->service->resolveCategoryId($data, required: true);

        $tour = $this->service->createTour($partnerId, $data);

        return response()->json(['data' => $tour], 201);
    }

    public function update(UpdateTourRequest $request, string $id): JsonResponse
    {
        $tour = $this->service->requireOwnedTour(
            (int) $id,
            (int) $request->attributes->get('partner_id')
        );

        $data = $request->validated();

        if (array_key_exists('category', $data)) {
            $data = $this->service->resolveCategoryId($data, required: false);
        }

        $tour = $this->service->updateTour($tour, $data);

        return response()->json(['data' => $tour]);
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

    public function submitForReview(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $tour = $this->service->getForPartner((int) $id, $partnerId);

        if (! $tour) {
            abort(404);
        }

        $enTranslation = $tour->translations()->where('locale', 'en')->first();
        if (! $enTranslation || trim((string) $enTranslation->title) === '' || trim((string) ($enTranslation->description ?? '')) === '') {
            return response()->json([
                'message' => 'Validation failed: Tour must have at least an English title and description.',
            ], 422);
        }

        if ($tour->pricingTiers()->count() === 0) {
            return response()->json([
                'message' => 'Validation failed: Tour must have at least one pricing tier defined.',
            ], 422);
        }

        if (empty($tour->cover_image_url)) {
            return response()->json([
                'message' => 'Validation failed: Tour must have a cover image URL.',
            ], 422);
        }

        $tour = $this->service->submitForReview($tour);

        return response()->json([
            'data' => $tour,
            'message' => 'Tour submitted for review successfully.',
        ]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');
        $tour = $this->service->getForPartner((int) $id, $partnerId);

        if (! $tour) {
            abort(404);
        }

        $tour = $this->service->archiveTour($tour);

        return response()->json([
            'data' => $tour,
            'message' => 'Tour archived successfully.',
        ]);
    }
}
