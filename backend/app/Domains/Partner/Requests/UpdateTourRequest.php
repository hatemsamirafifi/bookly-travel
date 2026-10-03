<?php

namespace App\Domains\Partner\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTourRequest extends FormRequest
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
     * Title is a non-null string whenever supplied and description follows
     * the shared nullable draft rule; ownership scoping stays in the
     * controller/service layer and the obsolete tours.title uniqueness
     * check (titles live in tour_translations, not tours) is removed.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return array_merge(
            TourContentRules::sourceRules(false),
            [
                'category' => 'sometimes|string|max:50',
                'destination' => 'sometimes|string|max:255',
                'duration_value' => 'sometimes|integer|min:1',
                'duration_unit' => 'sometimes|string|in:hour,day',
                'difficulty_level' => 'sometimes|string|in:easy,moderate,challenging',
                'cover_image_url' => 'nullable|url|max:2048',
                'price_from' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'pricing_tiers' => 'nullable|array',
                'availability_rules' => 'nullable|array',
                'availability_exceptions' => 'nullable|array',
            ],
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
