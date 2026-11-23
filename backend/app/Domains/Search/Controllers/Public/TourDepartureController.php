<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Partner\Services\PricingTierService;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Public Tour Departure & Availability Controller (EGM-005 D3, D4, D5).
 *
 * Exposes concrete bookable departures for a published tour within a 60-day window,
 * with independent capacity availability and calculated tiered pricing.
 * Enforces EGM-003 public owner eligibility safeguards (404 for ineligible owners).
 */
class TourDepartureController extends Controller
{
    public const MAX_QUERY_WINDOW_DAYS = 60;

    public function __construct(
        private readonly PricingTierService $pricingService
    ) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'option_id' => 'sometimes|nullable|integer|min:1',
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'participants' => 'sometimes|nullable|integer|min:1',
            'participant_count' => 'sometimes|nullable|integer|min:1',
        ]);

        $dateFrom = Carbon::parse($validated['date_from']);
        $dateTo = Carbon::parse($validated['date_to']);

        if ($dateFrom->diffInDays($dateTo) > self::MAX_QUERY_WINDOW_DAYS) {
            throw ValidationException::withMessages([
                'date_to' => ['Date range query cannot exceed 60 days.'],
            ]);
        }

        // 1. Resolve Tour and enforce publication & public owner eligibility
        $tour = Tour::query()
            ->with(['partnerRecord'])
            ->where('slug', $slug)
            ->first();

        if (! $tour) {
            return response()->json(['message' => 'Tour not found.'], 404);
        }

        if ($tour->status === 'archived' && $tour->published_at !== null) {
            return response()->json(['message' => 'This tour is no longer available.'], 410);
        }

        if ($tour->status !== 'published') {
            return response()->json(['message' => 'Tour not found.'], 404);
        }

        if (! $tour->hasEligiblePublicOwner()) {
            return response()->json(['message' => 'Tour not found.'], 404);
        }

        // 2. Query active departures in date window
        $participantCount = (int) ($request->input('participants') ?? $request->input('participant_count') ?? 1);

        $query = TourDeparture::query()
            ->where('tour_id', $tour->id)
            ->where('status', TourDeparture::STATUS_ACTIVE)
            ->whereBetween('local_date', [$validated['date_from'], $validated['date_to']])
            ->whereHas('tourOption', fn ($q) => $q->where('status', 'active'))
            ->with(['tourOption.pricingTiers'])
            ->orderBy('starts_at', 'asc');

        if (! empty($validated['option_id'])) {
            $query->where('tour_option_id', (int) $validated['option_id']);
        }

        $departures = $query->get();

        $data = $departures->map(function (TourDeparture $departure) use ($tour, $participantCount): array {
            /** @var TourOption $option */
            $option = $departure->tourOption;
            $reserved = $departure->reservedCapacity();
            $available = max(0, $departure->capacity - $reserved);
            $isBookable = $departure->isBookable($participantCount);

            $pricing = $this->pricingService->calculatePrice($option, $participantCount, $tour);

            return [
                'id' => $departure->id,
                'tour_id' => $departure->tour_id,
                'tour_option_id' => $departure->tour_option_id,
                'starts_at' => $departure->starts_at->toIso8601String(),
                'timezone' => $departure->timezone,
                'local_date' => $departure->local_date,
                'local_time' => $departure->local_time,
                'capacity' => $departure->capacity,
                'reserved_spots' => $reserved,
                'available_spots' => $available,
                'booking_cutoff_hours' => $departure->booking_cutoff_hours,
                'is_bookable' => $isBookable,
                'pricing' => $pricing,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
