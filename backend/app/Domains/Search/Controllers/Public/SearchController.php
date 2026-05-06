<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\SearchToursAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController
{
    public function __construct(
        protected SearchToursAction $searchToursAction
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|in:en,es,it',
            'q' => 'nullable|string|max:255',
            'category' => 'nullable|string',
            'location' => 'nullable|string',
            'price_min' => 'nullable|integer|min:0',
            'price_max' => 'nullable|integer|min:0',
            'duration' => 'nullable|in:half-day,full-day,multi-day',
            'date' => 'nullable|date|date_format:Y-m-d',
            'sort' => 'nullable|in:relevance,price_asc,price_desc,rating,newest',
            'page' => 'nullable|integer|min:1',
        ]);

        $results = $this->searchToursAction->execute($validated);

        return response()->json($results);
    }
}
