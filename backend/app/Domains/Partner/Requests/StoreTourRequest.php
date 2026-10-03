<?php

namespace App\Domains\Partner\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTourRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return array_merge(
            [
                'title' => 'required|string|max:120',
                'description' => 'required|string|min:100|max:5000',
                'category' => 'required|string|max:50',
                'destination' => 'required|string|max:255',
                'duration_value' => 'required|integer|min:1',
                'duration_unit' => 'required|string|in:hour,day',
                'difficulty_level' => 'required|string|in:easy,moderate,challenging',
                'inclusions' => 'nullable|array',
                'meeting_point' => 'nullable|string|max:500',
                'cover_image_url' => 'nullable|url|max:2048',
                'price_from' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'pricing_tiers' => 'nullable|array',
                'availability_rules' => 'nullable|array',
                'availability_exceptions' => 'nullable|array',
            ],
            TourContentRules::sourceRules(true),
            TourContentRules::itineraryRules('itinerary'),
            TourContentRules::itineraryRules('translations.en.itinerary')
        );
    }

    /**
     * Reject every authored non-English locale and platform-owned key with
     * its precise offending path, including present-but-null/empty values
     * that `prohibited` would let through.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (TourContentRules::forbiddenPresentPaths($this->all()) as $path => $message) {
                $validator->errors()->add($path, $message);
            }
        });
    }
}
