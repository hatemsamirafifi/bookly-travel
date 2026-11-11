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
     * The authored source locale requested for this new tour.
     *
     * Resolved before rules are built so the nested content rules and the
     * forbidden-path scan share one language. Invalid or absent values fall
     * back to English here and are still rejected by the `in:` rule below.
     */
    private function requestedSourceLocale(): string
    {
        $locale = $this->input('source_locale');

        return is_string($locale) && in_array($locale, ['en', 'es', 'it'], true)
            ? $locale
            : 'en';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Combines the existing top-level create requirements (title,
     * description min 100, category/destination/duration/difficulty) with
     * the shared Spec 019 source/itinerary boundary, rooted at the supplier's
     * chosen authored language. `status` is prohibited: creation always
     * materializes a draft and publication flows through the guarded submit
     * endpoint only.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        $sourceLocale = $this->requestedSourceLocale();

        return array_merge(
            [
                'category' => 'required|string|max:50',
                'destination' => 'required|string|max:255',
                'duration_value' => 'required|integer|min:1',
                'duration_unit' => 'required|string|in:hour,day',
                'difficulty_level' => 'required|string|in:easy,moderate,challenging',
                'source_locale' => 'sometimes|string|in:en,es,it',
                'group_size_min' => 'nullable|integer|min:1',
                'group_size_max' => 'nullable|integer|min:1',
                'cover_image_url' => 'nullable|string|url|max:2048',
                'price_from' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'pricing_tiers' => 'nullable|array',
                'availability_rules' => 'nullable|array',
                'availability_exceptions' => 'nullable|array',
                'status' => 'prohibited',
            ],
            TourContentRules::sourceRules(true, $sourceLocale),
            TourContentRules::guideLanguageRules(),
            TourContentRules::mediaRules(),
            TourContentRules::itineraryRules('itinerary'),
            TourContentRules::itineraryRules("translations.{$sourceLocale}.itinerary"),
            TourContentRules::optionRules()
        );
    }

    /**
     * Reject every authored locale that is not this tour's source locale and
     * every platform-owned key with its precise offending path, including
     * present-but-null/empty values that `prohibited` would let through.
     * Create options are always new rows: any authored option id is reported.
     */
    public function withValidator(Validator $validator): void
    {
        $sourceLocale = $this->requestedSourceLocale();

        $validator->after(function (Validator $validator) use ($sourceLocale): void {
            foreach (TourContentRules::forbiddenPresentPaths($this->all(), $sourceLocale) as $path => $message) {
                $validator->errors()->add($path, $message);
            }

            $options = $this->input('options');
            if (! is_array($options)) {
                return;
            }

            foreach (TourContentRules::optionIdPaths($options, isCreate: true) as $path => $message) {
                $validator->errors()->add($path, $message);
            }
        });
    }
}
