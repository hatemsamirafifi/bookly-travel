<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\SearchToursAction;
use App\Http\Requests\Public\Search\SearchToursRequest;
use Illuminate\Http\JsonResponse;

class SearchController
{
    public function __construct(
        protected SearchToursAction $searchToursAction
    ) {}

    public function search(SearchToursRequest $request): JsonResponse
    {
        $results = $this->searchToursAction->execute($request->validated());

        return response()->json($results);
    }
}
