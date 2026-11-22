<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Services\TourDepartureService;
use App\Domains\Partner\Services\TourService;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TourDepartureController extends Controller
{
    public function __construct(
        private readonly TourService $tourService,
        private readonly TourDepartureService $departureService,
    ) {}

    private function getOwnedTour(Request $request, string $tourId): Tour
    {
        $partnerId = (int) $request->attributes->get('partner_id');
        $tour = $this->tourService->getForPartner((int) $tourId, $partnerId);

        if (! $tour) {
            abort(404, 'Tour not found or access denied.');
        }

        return $tour;
    }

    public function index(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getOwnedTour($request, $tourId);

        $filters = $request->validate([
            'tour_option_id' => 'sometimes|nullable|integer|min:1',
            'date_from' => 'sometimes|nullable|date_format:Y-m-d',
            'date_to' => 'sometimes|nullable|date_format:Y-m-d|after_or_equal:date_from',
            'status' => 'sometimes|nullable|string|in:active,cancelled,completed',
        ]);

        $departures = $this->departureService->listForTour($tour, $filters);
        $options = $tour->activeOptions()
            ->values()
            ->map(fn (TourOption $option): array => [
                'id' => (int) $option->id,
                'title' => (string) $option->title,
                'start_time' => $option->startTimeString(),
                'group_size_max' => $option->group_size_max,
                'effective_capacity' => $option->effectiveCapacity($tour),
            ])
            ->all();

        return response()->json([
            'data' => $departures,
            'meta' => [
                'total' => count($departures),
                'max_horizon_days' => TourDepartureService::MAX_HORIZON_DAYS,
                'options' => $options,
            ],
        ]);
    }

    public function store(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getOwnedTour($request, $tourId);

        $validated = $request->validate([
            'tour_option_id' => 'required|integer|min:1',
            'local_date' => 'required|date_format:Y-m-d',
            'local_time' => ['required', 'string', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'timezone' => 'required|string|timezone:all',
            'capacity' => 'required|integer|min:1|max:1000',
            'booking_cutoff_hours' => 'sometimes|nullable|integer|min:0|max:8760',
        ]);

        $option = TourOption::where('tour_id', $tour->id)
            ->where('id', (int) $validated['tour_option_id'])
            ->first();

        if (! $option) {
            abort(404, 'Tour option not found for this tour.');
        }

        $departure = $this->departureService->createDeparture($tour, $option, $validated);

        return response()->json([
            'data' => $this->departureService->serializeDeparture($departure, 0),
            'message' => 'Departure created successfully.',
        ], 201);
    }

    public function generate(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getOwnedTour($request, $tourId);

        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'sometimes|nullable|date_format:Y-m-d|after_or_equal:start_date',
            'timezone' => 'required|string|timezone:all',
            'tour_option_id' => 'sometimes|nullable|integer|min:1',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = ! empty($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : $startDate->copy()->addDays(TourDepartureService::MAX_HORIZON_DAYS);

        if ($startDate->diffInDays($endDate) > TourDepartureService::MAX_HORIZON_DAYS) {
            throw ValidationException::withMessages([
                'end_date' => ['Departure generation horizon cannot exceed 180 days.'],
            ]);
        }

        $optionId = isset($validated['tour_option_id']) ? (int) $validated['tour_option_id'] : null;

        $generated = $this->departureService->generateForTour(
            $tour,
            $startDate,
            $endDate,
            $validated['timezone'],
            $optionId
        );

        $ids = $generated->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $reservedMap = $this->departureService->batchedReservedSeatsMap($ids);
        $serialized = $generated
            ->map(fn (TourDeparture $dep): array => $this->departureService->serializeDeparture(
                $dep,
                $reservedMap[(int) $dep->id] ?? 0
            ))
            ->values()
            ->all();

        return response()->json([
            'data' => $serialized,
            'meta' => [
                'generated_count' => count($serialized),
            ],
            'message' => 'Departures generated successfully.',
        ]);
    }

    public function update(Request $request, string $tourId, string $departureId): JsonResponse
    {
        $tour = $this->getOwnedTour($request, $tourId);

        $departure = TourDeparture::where('tour_id', $tour->id)
            ->where('id', (int) $departureId)
            ->firstOrFail();

        $validated = $request->validate([
            'capacity' => 'sometimes|required|integer|min:1|max:1000',
            'booking_cutoff_hours' => 'sometimes|required|integer|min:0|max:8760',
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'capacity' => ['Provide capacity or booking cutoff hours to update.'],
            ]);
        }

        $updated = $this->departureService->updateDeparture($departure, $validated);

        return response()->json([
            'data' => $this->departureService->serializeDeparture($updated),
            'message' => 'Departure updated successfully.',
        ]);
    }

    public function cancel(Request $request, string $tourId, string $departureId): JsonResponse
    {
        $tour = $this->getOwnedTour($request, $tourId);

        $departure = TourDeparture::where('tour_id', $tour->id)
            ->where('id', (int) $departureId)
            ->firstOrFail();

        $validated = $request->validate([
            'confirm_booked_cancellation' => 'sometimes|boolean',
        ]);

        $cancelled = $this->departureService->cancelDeparture(
            $departure,
            (bool) ($validated['confirm_booked_cancellation'] ?? false)
        );

        return response()->json([
            'data' => $this->departureService->serializeDeparture($cancelled),
            'message' => 'Departure cancelled successfully.',
        ]);
    }
}
