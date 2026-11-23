<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetTourDetailAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourDetailController
{
    public function __construct(
        protected GetTourDetailAction $getTourDetailAction
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|in:en,es,it',
        ]);

        // The projection service owns the versioned cache key and the single
        // Tour-shared-lock read boundary; the controller stays thin.
        $result = $this->getTourDetailAction->execute($slug, $validated['locale']);

        return response()->json($result);
    }
}
