<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuditIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_reference' => 'sometimes|string|max:12',
            'actor_type' => 'sometimes|string|in:traveler,partner,admin,system',
            'action' => 'sometimes|string|in:created,confirmed,completed,cancelled,no_show,anonymized',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}
