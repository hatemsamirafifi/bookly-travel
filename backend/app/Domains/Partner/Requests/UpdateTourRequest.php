<?php

namespace App\Domains\Partner\Requests;

use App\Domains\Partner\Services\TourService;
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
     * Get the validation rules that apply to the request.
     *
     * `status` is prohibited: updates never change lifecycle state;
     * publication flows through the guarded submit endpoint only.
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
                'location' => 'nullable|string|max:255',
                'group_size_min' => 'nullable|integer|min:1',
                'group_size_max' => 'nullable|integer|min:1',
                'duration_value' => 'sometimes|integer|min:1',
                'duration_unit' => 'sometimes|string|in:hour,day',
                'difficulty_level' => 'sometimes|string|in:easy,moderate,challenging',
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
