<?php

namespace App\Domains\Partner\Requests;

use App\Domains\Partner\Services\TourService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Partner response to an administrator change request (design v3 §6
 * `POST tours/{id}/revision-requests/{requestId}/respond`).
 *
 * A published-context response links ONLY the current owned submitted candidate
 * with the captured base; an initial-review response instead carries the
 * partner's observed generation epoch. The note is bounded and optional.
 */
class RespondToTourChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(TourService::class)->requireOwnedTour(
            (int) $this->route('id'),
            (int) $this->attributes->get('partner_id')
        );

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'revision_id' => 'sometimes|nullable|integer|min:1',
            'generation_epoch' => 'sometimes|nullable|integer|min:0',
            'observed_generation_epoch' => 'sometimes|nullable|integer|min:0',
            'note' => 'sometimes|nullable|string|max:2000',
            'response_note' => 'sometimes|nullable|string|max:2000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $input = $this->all();
            unset($input['revision_id'], $input['generation_epoch'], $input['observed_generation_epoch'], $input['note'], $input['response_note']);
            foreach (TourContentRules::forbiddenPresentPaths($input, 'en', allowSourceLocale: false) as $path => $message) {
                $validator->errors()->add($path, $message);
            }
        });
    }

    public function observedRevisionId(): ?int
    {
        $value = $this->input('revision_id');

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    public function observedGenerationEpoch(): ?int
    {
        $value = $this->input('observed_generation_epoch') ?? $this->input('generation_epoch');

        if ($value === null) {
            return null;
        }

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    public function responseNote(): ?string
    {
        $note = $this->input('response_note') ?? $this->input('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
