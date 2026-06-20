<?php

namespace App\Domains\Partner\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * Title uniqueness is scoped to the authenticated partner so that
     * two different partners may share a tour title, but one partner
     * cannot have two tours with the same title.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $partnerId = $this->attributes->get('partner_id');

        return [
            'title' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('tours')->where(function ($query) use ($partnerId) {
                    return $query->where('partner_id', $partnerId);
                })->ignore($this->route('id')),
            ],
            'description' => 'sometimes|string|min:100|max:5000',
            'category' => 'sometimes|string|max:50',
            'destination' => 'sometimes|string|max:255',
            'duration_value' => 'sometimes|integer|min:1',
            'duration_unit' => 'sometimes|string|in:hour,day',
            'difficulty_level' => 'sometimes|string|in:easy,moderate,challenging',
            'itinerary' => 'nullable|array',
            'inclusions' => 'nullable|array',
            'meeting_point' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|url|max:2048',
            'price_from' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'pricing_tiers' => 'nullable|array',
            'availability_rules' => 'nullable|array',
            'availability_exceptions' => 'nullable|array',
        ];
    }
}
