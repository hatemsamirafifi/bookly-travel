<?php

namespace App\Domains\Partner\Requests;

use App\Domains\Partner\Services\TourService;
use App\Models\Tour;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTourRequest extends FormRequest
{
    /**
     * Authorize via the scoped partner ownership lookup, aborting 404 for
     * cross-partner or unknown tours BEFORE input validation runs.
     *
     * This preserves the controller's historical scoped-404-first ordering:
     * resolving a FormRequest validates after authorization, so a naive
     * `return false` (403) or validation-first flow would turn scoped or
     * malformed cross-owner requests into 422/403. The partner middleware
     * still owns auth 401, missing capability/record 404 and restricted 403.
     *
     * Title is a non-null string whenever supplied and description follows
     * the shared nullable draft rule; ownership scoping stays in the
     * controller/service layer and the obsolete tours.title uniqueness
     * check (titles live in tour_translations, not tours) is removed.
     */
    public function authorize(): bool
    {
        // Scoped ownership lives in the domain service; aborting 404 here
        // (instead of returning false/403) keeps cross-partner and unknown
        // ids at 404 before input validation runs.
        app(TourService::class)->requireOwnedTour(
            (int) $this->route('id'),
            (int) $this->attributes->get('partner_id')
        );

        return true;
    }

    /**
     * The tour's immutable authored source locale, read from storage.
     *
     * Authorize() has already resolved the scoped, owned tour, so this only
     * needs the stored value to root the nested content rules and the
     * forbidden-path scan. A missing row falls back to English and is still
     * rejected as 404 by authorize() before validation runs.
     */
    private function storedSourceLocale(): string
    {
        $tour = Tour::find((int) $this->route('id'));

        return $tour?->sourceLocale() ?? Tour::DEFAULT_SOURCE_LOCALE;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `status` is prohibited: updates never change lifecycle state;
     * publication flows through the guarded submit endpoint only.
     * `source_locale` is immutable and rejected by the source-locale scan.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        $sourceLocale = $this->storedSourceLocale();

        return array_merge(
            TourContentRules::sourceRules(false, $sourceLocale),
            [
                'category' => 'sometimes|string|max:50',
                'destination' => 'sometimes|string|max:255',
                'location' => 'nullable|string|max:255',
                'group_size_min' => 'nullable|integer|min:1',
                'group_size_max' => 'nullable|integer|min:1',
                'duration_value' => 'sometimes|integer|min:1',
                'duration_unit' => 'sometimes|string|in:hour,day',
                'difficulty_level' => 'sometimes|string|in:easy,moderate,challenging',
                'cover_image_url' => 'nullable|string|url|max:2048',
                'price_from' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'pricing_tiers' => 'nullable|array',
                'availability_rules' => 'nullable|array',
                'availability_exceptions' => 'nullable|array',
                'status' => 'prohibited',
            ],
            TourContentRules::guideLanguageRules(),
            TourContentRules::mediaRules(),
            TourContentRules::itineraryRules('itinerary'),
            TourContentRules::itineraryRules("translations.{$sourceLocale}.itinerary"),
            TourContentRules::optionRules()
        );
    }

    /**
     * Reject every authored locale that is not this tour's source locale,
     * any source-language change, and every platform-owned key with its
     * precise offending path, including present-but-null/empty values that
     * `prohibited` would let through. Authored option ids are validated for
     * duplicates and scoped ownership (an id from another tour or partner is
     * rejected at its exact path) — ownership was already proven in
     * authorize(), so a scoped storage lookup is safe here.
     */
    public function withValidator(Validator $validator): void
    {
        $sourceLocale = $this->storedSourceLocale();
        $tour = Tour::find((int) $this->route('id'));

        $validator->after(function (Validator $validator) use ($sourceLocale, $tour): void {
            foreach (TourContentRules::forbiddenPresentPaths($this->all(), $sourceLocale, false) as $path => $message) {
                $validator->errors()->add($path, $message);
            }

            $options = $this->input('options');
            if (! is_array($options) || $tour === null) {
                return;
            }

            $scopedExists = fn (int $optionId): bool => $tour->options()
                ->whereKey($optionId)
                ->exists();

            foreach (TourContentRules::optionIdPaths($options, isCreate: false, scopedExists: $scopedExists) as $path => $message) {
                $validator->errors()->add($path, $message);
            }
        });
    }
}
