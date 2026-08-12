<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Services\TourService;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TourController
{
    public function __construct(
        private readonly TourService $service,
    ) {}

    /**
     * Resolve the category slug from the request payload to a category_id.
     * Adds the resolved id to the data array under the 'category_id' key.
     */
    private function resolveCategoryId(array $data, bool $required): array
    {
        if (! array_key_exists('category', $data)) {
            if ($required && empty($data['category_id'])) {
                throw new UnprocessableEntityHttpException('The category field is required.');
            }

            return $data;
        }

        $category = Category::where('slug', $data['category'])->first();
        if (! $category) {
            throw new UnprocessableEntityHttpException('Unknown category: ' . $data['category']);
        }

        $data['category_id'] = $category->id;

        return $data;
    }

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
        $tour = $this->service->getForPartner((int) $id, $partnerId);

        if (! $tour) {
            abort(404);
        }

        return response()->json([
            'data' => $tour->load(['media', 'pricingTiers', 'availabilityRules', 'availabilityExceptions']),
        ]);
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

        $data = $this->resolveCategoryId($data, required: true);

        $tour = $this->service->createTour($partnerId, $data);

        return response()->json(['data' => $tour], 201);
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

        if (array_key_exists('category', $data)) {
            $data = $this->resolveCategoryId($data, required: false);
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
        if (! $enTranslation || empty($enTranslation->title) || empty($enTranslation->description)) {
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
