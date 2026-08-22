<?php

namespace App\Domains\Blog\Requests\Public;

use App\Http\Requests\Public\Search\LocaleRequest;

class ShowBlogPostRequest extends LocaleRequest
{
    // Inherits locale validation rule ('required', in:supported_locales) from LocaleRequest
}
