<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Services\TourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AvailabilityController extends Controller
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
            'data' => [
                'rules' => $tour->availabilityRules,
                'exceptions' => $tour->availabilityExceptions,
            ],
        ]);
    }

    public function storeRule(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $validated = $request->validate([
            'rule_type' => 'required|string|in:recurring,specific_date',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|between:0,6',
            'start_time' => 'required|date_format:H:i',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'capacity' => 'required|integer|min:1',
        ]);

        $rule = AvailabilityRule::create([
            'tour_id' => $tour->id,
            'rule_type' => $validated['rule_type'],
            'days_of_week' => $validated['days_of_week'] ?? null,
            'start_time' => $validated['start_time'] . ':00',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'capacity' => $validated['capacity'],
        ]);

        return response()->json([
            'data' => $rule,
            'message' => 'Availability rule created successfully.',
        ], 201);
    }

    public function updateRule(Request $request, string $tourId, string $ruleId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $rule = AvailabilityRule::where('tour_id', $tour->id)
            ->where('id', $ruleId)
            ->firstOrFail();

        $validated = $request->validate([
            'rule_type' => 'sometimes|required|string|in:recurring,specific_date',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|between:0,6',
            'start_time' => 'sometimes|required|date_format:H:i',
            'start_date' => 'sometimes|required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'capacity' => 'sometimes|required|integer|min:1',
        ]);

        if (isset($validated['start_time']) && strlen($validated['start_time']) === 5) {
            $validated['start_time'] = $validated['start_time'] . ':00';
        }

        $rule->update($validated);

        return response()->json([
            'data' => $rule,
            'message' => 'Availability rule updated successfully.',
        ]);
    }

    public function destroyRule(Request $request, string $tourId, string $ruleId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $rule = AvailabilityRule::where('tour_id', $tour->id)
            ->where('id', $ruleId)
            ->firstOrFail();

        // Check if there are active bookings for this rule in the future?
        // Since we don't store rule_id on booking (bookings are linked by tour_date/time),
        // we could check if bookings exist on that day/time, but deleting slot just stops new bookings.
        $rule->delete();

        return response()->json([
            'message' => 'Availability rule deleted successfully.',
        ]);
    }

    public function storeException(Request $request, string $tourId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $validated = $request->validate([
            'exception_type' => 'required|string|in:block,capacity_override,price_multiplier',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i',
            'capacity' => 'nullable|required_if:exception_type,capacity_override|integer|min:0',
            'price_multiplier' => 'nullable|required_if:exception_type,price_multiplier|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        if (isset($validated['start_time']) && strlen($validated['start_time']) === 5) {
            $validated['start_time'] = $validated['start_time'] . ':00';
        }

        $exception = AvailabilityException::create([
            'tour_id' => $tour->id,
            'exception_type' => $validated['exception_type'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'price_multiplier' => $validated['price_multiplier'] ?? '1.00',
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'data' => $exception,
            'message' => 'Availability exception created successfully.',
        ], 201);
    }

    public function updateException(Request $request, string $tourId, string $exceptionId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $exception = AvailabilityException::where('tour_id', $tour->id)
            ->where('id', $exceptionId)
            ->firstOrFail();

        $validated = $request->validate([
            'exception_type' => 'sometimes|required|string|in:block,capacity_override,price_multiplier',
            'date' => 'sometimes|required|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i',
            'capacity' => 'nullable|integer|min:0',
            'price_multiplier' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        if (isset($validated['start_time']) && strlen($validated['start_time']) === 5) {
            $validated['start_time'] = $validated['start_time'] . ':00';
        }

        $exception->update($validated);

        return response()->json([
            'data' => $exception,
            'message' => 'Availability exception updated successfully.',
        ]);
    }

    public function destroyException(Request $request, string $tourId, string $exceptionId): JsonResponse
    {
        $tour = $this->getTour($request, (int) $tourId);

        $exception = AvailabilityException::where('tour_id', $tour->id)
            ->where('id', $exceptionId)
            ->firstOrFail();

        $exception->delete();

        return response()->json([
            'message' => 'Availability exception deleted successfully.',
        ]);
    }
}
