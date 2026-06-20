<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FinancialLedgerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_reference' => 'sometimes|string',
            'entry_type' => 'sometimes|string|in:debit,credit',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}
