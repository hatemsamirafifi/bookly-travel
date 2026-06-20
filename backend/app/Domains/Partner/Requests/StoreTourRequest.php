<?php

namespace App\Domains\Partner\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'title' => 'required|string|max:120',
            'description' => 'required|string|min:100|max:5000',
            'category' => 'required|string|max:50',
            'destination' => 'required|string|max:255',
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:hour,day',
            'difficulty_level' => 'required|string|in:easy,moderate,challenging',
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
