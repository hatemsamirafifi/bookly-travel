<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetHomepageDataAction;
use App\Http\Requests\Public\Search\LocaleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomepageController
{
    public function __construct(
        protected GetHomepageDataAction $getHomepageDataAction
    ) {}

    public function index(LocaleRequest $request): JsonResponse
    {
        $locale = $request->validated()['locale'];
        $key = 'homepage.' . $locale;

        $data = Cache::remember($key, 600, function () use ($locale) {
            return $this->getHomepageDataAction->execute($locale);
        });

        return response()->json($data);
    }
}
