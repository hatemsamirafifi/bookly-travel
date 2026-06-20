<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Services\TourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PricingController extends Controller
{
    public function __construct(
        private readonly TourService $tourService
    ) {}

    private function getTour(Request $request, int $tourId)
    {
        $partnerId = $request->attributes->get('partner_id');
        $tour = $this->tourService->getForPartner($tourId, $partnerId);

        if (! $tour) {
            abort(404, 'Tour not found or access denied.');
        }

        return $tour;
    }

    public function index(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        return response()->json([
            'data' => $tour->pricingTiers,
        ]);
    }

    public function store(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0.01',
            'min_participants' => 'required|integer|min:1',
            'max_participants' => 'nullable|integer|gt:min_participants',
        ]);

        $tier = PricingTier::create([
            'tour_id' => $tour->id,
            'name' => $validated['name'],
            'price' => $validated['price'],
            'min_participants' => $validated['min_participants'],
            'max_participants' => $validated['max_participants'],
        ]);

        return response()->json([
            'data' => $tier,
            'message' => 'Pricing tier created successfully.',
        ], 201);
    }

    public function update(Request $request, string $tourId, string $tierId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $tier = PricingTier::where('tour_id', $tour->id)
            ->where('id', $tierId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'price' => 'sometimes|required|numeric|min:0.01',
            'min_participants' => 'sometimes|required|integer|min:1',
            'max_participants' => 'nullable|integer|gt:min_participants',
        ]);

        $tier->update($validated);

        return response()->json([
            'data' => $tier,
            'message' => 'Pricing tier updated successfully.',
        ]);
    }

    public function destroy(Request $request, string $tourId, string $tierId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $tier = PricingTier::where('tour_id', $tour->id)
            ->where('id', $tierId)
            ->firstOrFail();

        $tier->delete();

        return response()->json([
            'message' => 'Pricing tier deleted successfully.',
        ]);
    }
}
