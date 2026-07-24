<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetHomepageDataAction;
use App\Http\Requests\Public\Search\LocaleRequest;
use Illuminate\Http\JsonResponse;

class HomepageController
{
    public function __construct(
        protected GetHomepageDataAction $getHomepageDataAction
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $data = $this->getHomepageDataAction->execute($request->validated()['locale']);

        return response()->json($data);
    }
}
