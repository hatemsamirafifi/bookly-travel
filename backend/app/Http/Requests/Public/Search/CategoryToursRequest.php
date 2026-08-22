<?php

namespace App\Http\Requests\Public\Search;

/**
 * Listing request for tours scoped to a category. The category is the path
 * slug, so the `category` query param is rejected here (a client cannot
 * override the scope). All other search rules are inherited from
 * SearchToursRequest so the shared validation stays in one place.
 */
class CategoryToursRequest extends SearchToursRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'category' => ['prohibited'],
        ]);
    }
}
