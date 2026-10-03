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
     * Combines the existing top-level create requirements (title,
     * description min 100, category/destination/duration/difficulty) with
     * the shared Spec 019 English source/itinerary boundary. `status` is
     * prohibited: creation always materializes a draft and publication
     * flows through the guarded submit endpoint only.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return array_merge(
            [
                'category' => 'required|string|max:50',
                'destination' => 'required|string|max:255',
                'duration_value' => 'required|integer|min:1',
                'duration_unit' => 'required|string|in:hour,day',
                'difficulty_level' => 'required|string|in:easy,moderate,challenging',
                'group_size_min' => 'nullable|integer|min:1',
                'group_size_max' => 'nullable|integer|min:1',
                'guide_languages' => 'sometimes|array|max:20',
                'guide_languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
                'languages' => 'sometimes|array|max:20',
                'languages.*' => 'string|distinct|regex:/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i',
                'cover_image_url' => 'nullable|string|url|max:2048',
                'media' => 'sometimes|array|max:20',
                'media.*.url' => 'required|url|max:2048|distinct',
                'media.*.is_cover' => 'sometimes|boolean',
                'price_from' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'pricing_tiers' => 'nullable|array',
                'availability_rules' => 'nullable|array',
                'availability_exceptions' => 'nullable|array',
                'status' => 'prohibited',
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
