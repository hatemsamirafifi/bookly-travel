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

        $result = $this->getTourDetailAction->execute($slug, $validated['locale']);

        return response()->json($result);
    }
}
