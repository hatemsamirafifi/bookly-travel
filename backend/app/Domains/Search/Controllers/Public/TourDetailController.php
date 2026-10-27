<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetTourDetailAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        $key = 'tour.' . $slug . '.' . $validated['locale'];

        $result = Cache::remember($key, 900, function () use ($slug, $validated) {
            return $this->getTourDetailAction->execute($slug, $validated['locale']);
        });

        return response()->json($result);
    }
}
