<?php

namespace App\Http\Requests\Public\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Minimal request validating only the required `locale` query param, shared by
 * the category and destination index endpoints so the supported-locale list is
 * derived from config and cannot drift.
 */
class LocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locales = implode(',', config('app.supported_locales', ['en', 'es', 'it']));

        return [
            'locale' => ['required', "in:{$locales}"],
        ];
    }
}
