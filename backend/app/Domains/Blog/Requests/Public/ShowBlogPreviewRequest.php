<?php

namespace App\Domains\Blog\Requests\Public;

use App\Http\Requests\Public\Search\LocaleRequest;

class ShowBlogPreviewRequest extends LocaleRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'token' => ['required', 'string'],
        ]);
    }
}
