<?php

namespace App\Domains\Partner\Controllers;

use App\Domains\Partner\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController
{
    public function __construct(
        private readonly AnalyticsService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_id');

        $filters = $request->validate([
            'from' => 'sometimes|date_format:Y-m-d',
            'to' => 'sometimes|date_format:Y-m-d',
            'tour_id' => 'sometimes|integer',
        ]);

        // Validate that tour_id belongs to this partner
        if (isset($filters['tour_id'])) {
            $tour = \App\Models\Tour::where('id', $filters['tour_id'])
                ->where('partner_id', $partnerId)
                ->exists();

            if (! $tour) {
                abort(404, 'Tour not found.');
            }
        }

        $analytics = $this->service->getAnalytics($partnerId, $filters);

        return response()->json($analytics);
    }
}