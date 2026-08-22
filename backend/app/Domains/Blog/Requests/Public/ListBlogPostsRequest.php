<?php

namespace App\Domains\Blog\Requests\Public;

use App\Http\Requests\Public\Search\LocaleRequest;

class ListBlogPostsRequest extends LocaleRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'category' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
