<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Services\TourService;
use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TourController
{
    public function __construct(
        private readonly TourService $service,
        private readonly TourTranslationService $translationService,
    ) {}

    private function requireEnglishSourceOnly(Request $request): void
    {
        $translations = $request->input('translations');
        if (! is_array($translations)) {
            return;
        }

        foreach (array_keys($translations) as $locale) {
            if ($locale !== 'en') {
                throw ValidationException::withMessages([
                    "translations.{$locale}" => 'Partners may edit English source content only.',
                ]);
            }
        }
    }

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

        $data = $tour->load(['translations', 'media', 'pricingTiers', 'availabilityRules', 'availabilityExceptions'])->toArray();
        $data['translation_statuses'] = [
            'es' => $this->translationService->publicStatus($tour, 'es'),
            'it' => $this->translationService->publicStatus($tour, 'it'),
        ];

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireEnglishSourceOnly($request);
        $partnerId = $request->attributes->get('partner_id');
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|min:100|max:5000',
            'category' => 'required|string|max:50',
            'destination' => 'required|string|max:255',
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:hour,day',
            'difficulty_level' => 'required|string|in:easy,moderate,challenging',
            'guide_languages' => 'sometimes|array|max:20',
            'guide_languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
            'languages' => 'sometimes|array|max:20',
            'languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
            'translations' => 'sometimes|array',
            'translations.*.title' => 'nullable|string|max:120',
            'translations.*.description' => 'nullable|string|max:5000',
            'translations.*.itinerary' => 'nullable|array|max:30',
            'translations.*.itinerary.*.day' => 'required|integer|min:1|max:30',
            'translations.*.itinerary.*.title' => 'required|string|max:160',
            'translations.*.itinerary.*.description' => 'nullable|string|max:2000',
            'translations.*.itinerary.*.stops' => 'nullable|array|max:20',
            'translations.*.itinerary.*.stops.*.title' => 'required|string|max:160',
            'translations.*.itinerary.*.stops.*.description' => 'nullable|string|max:2000',
            'translations.*.itinerary.*.stops.*.duration_minutes' => 'nullable|integer|min:1|max:1440',
            'itinerary' => 'nullable|array|max:30',
            'itinerary.*.day' => 'required|integer|min:1|max:30',
            'itinerary.*.title' => 'required|string|max:160',
            'itinerary.*.description' => 'nullable|string|max:2000',
            'itinerary.*.stops' => 'nullable|array|max:20',
            'itinerary.*.stops.*.title' => 'required|string|max:160',
            'itinerary.*.stops.*.description' => 'nullable|string|max:2000',
            'itinerary.*.stops.*.duration_minutes' => 'nullable|integer|min:1|max:1440',
            'important_information' => 'nullable|array|max:30',
            'important_information.*' => 'string|max:500',
            'inclusions' => 'nullable|array',
            'meeting_point' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|string|url|max:2048',
            'media' => 'sometimes|array|max:20',
            'media.*.url' => 'required|url|max:2048|distinct',
            'media.*.is_cover' => 'sometimes|boolean',
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

        $this->requireEnglishSourceOnly($request);

        $data = $request->validate([
            'title' => 'sometimes|string|max:120',
            'description' => 'sometimes|string|min:100|max:5000',
            'translations' => 'nullable|array',
            'translations.*.title' => 'nullable|string|max:120',
            'translations.*.description' => 'nullable|string|max:5000',
            'translations.*.highlights' => 'nullable|array',
            'translations.*.inclusions' => 'nullable|array',
            'translations.*.exclusions' => 'nullable|array',
            'translations.*.meeting_point' => 'nullable|string|max:500',
            'translations.*.cancellation_policy' => 'nullable|string|max:2000',
            'translations.*.itinerary' => 'nullable|array|max:30',
            'translations.*.itinerary.*.day' => 'required|integer|min:1|max:30',
            'translations.*.itinerary.*.title' => 'required|string|max:160',
            'translations.*.itinerary.*.description' => 'nullable|string|max:2000',
            'translations.*.itinerary.*.stops' => 'nullable|array|max:20',
            'translations.*.itinerary.*.stops.*.title' => 'required|string|max:160',
            'translations.*.itinerary.*.stops.*.description' => 'nullable|string|max:2000',
            'translations.*.itinerary.*.stops.*.duration_minutes' => 'nullable|integer|min:1|max:1440',
            'translations.*.important_information' => 'nullable|array|max:30',
            'translations.*.important_information.*' => 'string|max:500',
            'guide_languages' => 'sometimes|array|max:20',
            'guide_languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
            'languages' => 'sometimes|array|max:20',
            'languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
            'category' => 'sometimes|string|max:50',
            'destination' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
            'group_size_min' => 'nullable|integer|min:1',
            'group_size_max' => 'nullable|integer|min:1',
            'duration_value' => 'sometimes|integer|min:1',
            'duration_unit' => 'sometimes|string|in:hour,day',
            'difficulty_level' => 'sometimes|string|in:easy,moderate,challenging',
            'itinerary' => 'nullable|array|max:30',
            'itinerary.*.day' => 'required|integer|min:1|max:30',
            'itinerary.*.title' => 'required|string|max:160',
            'itinerary.*.description' => 'nullable|string|max:2000',
            'itinerary.*.stops' => 'nullable|array|max:20',
            'itinerary.*.stops.*.title' => 'required|string|max:160',
            'itinerary.*.stops.*.description' => 'nullable|string|max:2000',
            'itinerary.*.stops.*.duration_minutes' => 'nullable|integer|min:1|max:1440',
            'important_information' => 'nullable|array|max:30',
            'important_information.*' => 'string|max:500',
            'inclusions' => 'nullable|array',
            'meeting_point' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|string|url|max:2048',
            'media' => 'sometimes|array|max:20',
            'media.*.url' => 'required|url|max:2048|distinct',
            'media.*.is_cover' => 'sometimes|boolean',
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
