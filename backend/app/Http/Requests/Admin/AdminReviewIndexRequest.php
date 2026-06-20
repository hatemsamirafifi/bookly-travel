<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminReviewIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by middleware or gates
    }

    public function rules(): array
    {
        return [
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string',
            'tour_id' => 'nullable|integer',
            'flagged' => 'nullable|boolean',
        ];
    }
}
