<?php

namespace App\Http\Requests\Public\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the public search/tours query parameters at the API boundary.
 *
 * Rules mirror the Search API contract (search-api.md:38-45) and the CLAUDE.md
 * "validate input at system boundaries" rule: slug patterns, bounded price
 * integers, today-or-future dates within one year, and a capped page to
 * prevent deep-offset abuse. Shared by the search, category, and destination
 * controllers via the scoped subclasses so the three cannot drift.
 */
class SearchToursRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'regex:/^[a-z0-9-]+$/', 'max:100'],
            'location' => ['nullable', 'string', 'regex:/^[a-z0-9-]+$/', 'max:100'],
            'price_min' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'price_max' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'duration' => ['nullable', 'in:half-day,full-day,multi-day'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:+1 year'],
            'sort' => ['nullable', 'in:relevance,price_asc,price_desc,rating,newest'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * The `price_max >= price_min` constraint only applies when both bounds
     * are provided (search-api.md:41). Apply it conditionally so a lone
     * `price_max=0` (free-tour filter) is not rejected for a missing price_min.
     */
    public function withValidator($validator): void
    {
        $validator->sometimes('price_max', 'gte:price_min', function ($input) {
            return isset($input->price_min) && $input->price_min !== ''
                && isset($input->price_max) && $input->price_max !== '';
        });
    }

    /**
     * Strip Meilisearch filter-syntax operators from the free-text `q` so a
     * search query can never be interpreted as a filter expression
     * (search-api.md:36). The `q` is treated as literal text by Meilisearch
     * regardless, but the contract requires these tokens be stripped.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('q') && is_string($this->q)) {
            $this->merge([
                'q' => $this->sanitizeQuery($this->q),
            ]);
        }
    }

    protected function sanitizeQuery(string $query): string
    {
        // Remove standalone Meilisearch filter operators/tokens. Word-boundary
        // matches so legitimate search words containing these substrings
        // (e.g. "into", "andorra") are not affected.
        $pattern = '/\b(?:=|!=|>=|<=|>|<|TO|AND|OR|NOT|IN\s|EXISTS|IS\s+NULL|IS\s+EMPTY)\b/i';

        return trim(preg_replace($pattern, ' ', $query));
    }
}