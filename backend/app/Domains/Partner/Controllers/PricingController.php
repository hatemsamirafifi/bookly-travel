<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Services\PricingTierService;
use App\Domains\Partner\Services\TourService;
use App\Models\Tour;
use App\Models\TourOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PricingController extends Controller
{
    public function __construct(
        private readonly TourService $tourService,
        private readonly PricingTierService $pricingService
    ) {}

    private function getTour(Request $request, int $tourId): Tour
    {
        $partnerId = (int) $request->attributes->get('partner_id');
        $tour = $this->tourService->getForPartner($tourId, $partnerId);

        if (! $tour) {
            abort(404, 'Tour not found or access denied.');
        }

        return $tour;
    }

    private function assertOptionForTour(Tour $tour, int $optionId): TourOption
    {
        $option = TourOption::where('tour_id', $tour->id)
            ->where('id', $optionId)
            ->first();

        if (! $option) {
            throw ValidationException::withMessages([
                'tour_option_id' => ['The selected tour option does not belong to this tour.'],
            ]);
        }

        if ($option->status !== TourOption::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'tour_option_id' => ['Cannot configure pricing tiers for a retired tour option.'],
            ]);
        }

        return $option;
    }

    private function assertNoOverlappingSibling(
        int $tourId,
        ?int $optionId,
        int $minParticipants,
        ?int $maxParticipants,
        ?int $ignoreTierId = null
    ): void {
        $query = PricingTier::where('tour_id', $tourId);
        if ($optionId === null) {
            $query->whereNull('tour_option_id');
        } else {
            $query->where('tour_option_id', $optionId);
        }
        if ($ignoreTierId !== null) {
            $query->where('id', '!=', $ignoreTierId);
        }

        $newMax = $maxParticipants ?? PHP_INT_MAX;

        /** @var PricingTier $sibling */
        foreach ($query->lockForUpdate()->get() as $sibling) {
            $siblingMax = $sibling->max_participants !== null ? (int) $sibling->max_participants : PHP_INT_MAX;
            if ($minParticipants <= $siblingMax && (int) $sibling->min_participants <= $newMax) {
                throw ValidationException::withMessages([
                    'min_participants' => ['Pricing tier participant range overlaps an existing tier.'],
                ]);
            }
        }
    }

    public function index(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $query = PricingTier::where('tour_id', $tour->id);

        if ($request->has('tour_option_id')) {
            $optionId = $request->input('tour_option_id');
            if ($optionId === null || $optionId === 'null' || $optionId === '') {
                $query->whereNull('tour_option_id');
            } else {
                $query->where('tour_option_id', (int) $optionId);
            }
        }

        $tiers = $query->orderBy('sort_order')->orderBy('min_participants')->orderBy('id')->get();
        $options = $tour->activeOptions()
            ->values()
            ->map(fn (TourOption $option): array => [
                'id' => (int) $option->id,
                'title' => (string) $option->title,
                'effective_capacity' => $option->effectiveCapacity($tour),
                'price_amount' => (int) ($option->price_amount ?? $tour->lowestPriceAmount()),
                'currency' => $tour->currency(),
            ])
            ->all();

        return response()->json([
            'data' => $tiers,
            'meta' => [
                'mode' => 'per_person',
                'options' => $options,
            ],
        ]);
    }

    public function store(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'sometimes|numeric|min:0.01',
            'price_amount' => 'sometimes|integer|min:1',
            'min_participants' => 'required|integer|min:1',
            'max_participants' => 'nullable|integer|min:1|gte:min_participants',
            'tour_option_id' => 'sometimes|nullable|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'sort_order' => 'sometimes|integer|min:0',
            'pricing_mode' => 'sometimes|string|in:per_person',
        ]);

        if (! isset($validated['price']) && ! isset($validated['price_amount'])) {
            throw ValidationException::withMessages([
                'price' => ['Either price or price_amount is required.'],
            ]);
        }

        $optionId = isset($validated['tour_option_id']) ? (int) $validated['tour_option_id'] : null;

        $priceAmount = isset($validated['price_amount'])
            ? (int) $validated['price_amount']
            : (int) round(((float) $validated['price']) * 100);

        $priceDecimal = isset($validated['price'])
            ? number_format((float) $validated['price'], 2, '.', '')
            : number_format($priceAmount / 100, 2, '.', '');

        $minParticipants = (int) $validated['min_participants'];
        $maxParticipants = array_key_exists('max_participants', $validated) && $validated['max_participants'] !== null
            ? (int) $validated['max_participants']
            : null;

        $tier = DB::transaction(function () use (
            $tour,
            $optionId,
            $validated,
            $priceDecimal,
            $priceAmount,
            $minParticipants,
            $maxParticipants
        ): PricingTier {
            $lockedTour = Tour::whereKey($tour->id)->lockForUpdate()->firstOrFail();

            if ($optionId !== null) {
                $this->assertOptionForTour($lockedTour, $optionId);
            }

            $this->assertNoOverlappingSibling(
                $lockedTour->id,
                $optionId,
                $minParticipants,
                $maxParticipants,
            );

            return PricingTier::create([
                'tour_id' => $lockedTour->id,
                'tour_option_id' => $optionId,
                'name' => $validated['name'],
                'price' => $priceDecimal,
                'price_amount' => $priceAmount,
                'currency' => $validated['currency'] ?? $lockedTour->currency(),
                'min_participants' => $minParticipants,
                'max_participants' => $maxParticipants,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
        });

        return response()->json([
            'data' => $tier,
            'message' => 'Pricing tier created successfully.',
        ], 201);
    }

    public function update(Request $request, string $tourId, string $tierId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);
        $resolvedTierId = (int) $tierId;

        $tier = PricingTier::where('tour_id', $tour->id)
            ->where('id', $resolvedTierId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'price' => 'sometimes|numeric|min:0.01',
            'price_amount' => 'sometimes|integer|min:1',
            'min_participants' => 'sometimes|required|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
            'tour_option_id' => 'sometimes|nullable|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'sort_order' => 'sometimes|integer|min:0',
            'pricing_mode' => 'sometimes|string|in:per_person',
        ]);
        unset($validated['pricing_mode']);

        if (isset($validated['price_amount'])) {
            $validated['price'] = number_format(((int) $validated['price_amount']) / 100, 2, '.', '');
        } elseif (isset($validated['price'])) {
            $validated['price_amount'] = (int) round(((float) $validated['price']) * 100);
            $validated['price'] = number_format((float) $validated['price'], 2, '.', '');
        }

        $updated = DB::transaction(function () use ($tour, $resolvedTierId, $validated): PricingTier {
            $lockedTour = Tour::whereKey($tour->id)->lockForUpdate()->firstOrFail();
            /** @var PricingTier $lockedTier */
            $lockedTier = PricingTier::where('tour_id', $lockedTour->id)
                ->whereKey($resolvedTierId)
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('tour_option_id', $validated) && $validated['tour_option_id'] !== null) {
                $this->assertOptionForTour($lockedTour, (int) $validated['tour_option_id']);
            }

            $targetOptionId = array_key_exists('tour_option_id', $validated)
                ? ($validated['tour_option_id'] !== null ? (int) $validated['tour_option_id'] : null)
                : ($lockedTier->tour_option_id !== null ? (int) $lockedTier->tour_option_id : null);

            $minParticipants = array_key_exists('min_participants', $validated)
                ? (int) $validated['min_participants']
                : (int) $lockedTier->min_participants;
            $maxParticipants = array_key_exists('max_participants', $validated)
                ? ($validated['max_participants'] !== null ? (int) $validated['max_participants'] : null)
                : ($lockedTier->max_participants !== null ? (int) $lockedTier->max_participants : null);

            if ($maxParticipants !== null && $maxParticipants < $minParticipants) {
                throw ValidationException::withMessages([
                    'max_participants' => ['max_participants must be greater than or equal to min_participants.'],
                ]);
            }

            $this->assertNoOverlappingSibling(
                $lockedTour->id,
                $targetOptionId,
                $minParticipants,
                $maxParticipants,
                (int) $lockedTier->id,
            );

            $lockedTier->update($validated);

            return $lockedTier->fresh() ?? $lockedTier;
        });

        return response()->json([
            'data' => $updated,
            'message' => 'Pricing tier updated successfully.',
        ]);
    }

    public function destroy(Request $request, string $tourId, string $tierId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);
        $resolvedTierId = (int) $tierId;

        DB::transaction(function () use ($tour, $resolvedTierId): void {
            Tour::whereKey($tour->id)->lockForUpdate()->firstOrFail();
            $tier = PricingTier::where('tour_id', $tour->id)
                ->where('id', $resolvedTierId)
                ->lockForUpdate()
                ->firstOrFail();

            $tier->delete();
        });

        return response()->json([
            'message' => 'Pricing tier deleted successfully.',
        ]);
    }

    /**
     * Atomically sync all pricing tiers for an option (EGM-005 D5).
     */
    public function sync(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $validated = $request->validate([
            'tour_option_id' => 'required|integer|min:1',
            'pricing_mode' => 'sometimes|string|in:per_person',
            'tiers' => 'required|array|min:1',
            'tiers.*.min_participants' => 'required|integer|min:1',
            'tiers.*.max_participants' => 'nullable|integer|min:1',
            'tiers.*.price_amount' => 'sometimes|nullable|integer|min:1',
            'tiers.*.price' => 'sometimes|nullable|numeric|min:0.01',
            'tiers.*.name' => 'sometimes|nullable|string|max:100',
            'tiers.*.currency' => 'sometimes|string|size:3',
            'tiers.*.pricing_mode' => 'sometimes|string|in:per_person',
        ]);

        $option = $this->assertOptionForTour($tour, (int) $validated['tour_option_id']);

        $synced = $this->pricingService->syncOptionTiers($option, $validated['tiers']);

        return response()->json([
            'data' => $synced,
            'message' => 'Pricing tiers synchronized successfully.',
        ]);
    }
}
