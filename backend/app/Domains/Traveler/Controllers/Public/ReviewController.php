<?php

namespace App\Domains\Traveler\Controllers\Public;

use App\Domains\Reviews\Actions\GetTravelerReviewsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController
{
    public function index(Request $request, GetTravelerReviewsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
        ]);

        $result = $action->execute(
            travelerId: (int) $request->user()->id,
            page: (int) ($validated['page'] ?? 1),
        );

        return response()->json($result);
    }
}
