<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Search\Actions\GetHomepageDataAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageController
{
    public function __construct(
        protected GetHomepageDataAction $getHomepageDataAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|in:en,es,it',
        ]);

        $data = $this->getHomepageDataAction->execute($validated['locale']);

        return response()->json($data);
    }
}
