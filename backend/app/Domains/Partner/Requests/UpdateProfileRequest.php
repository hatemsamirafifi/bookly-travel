<?php

namespace App\Domains\Partner\Requests;

use App\Domains\Partner\Rules\ValidIban;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => 'required|string|max:255',
            'business_description' => 'nullable|string|max:5000',
            'logo_url' => 'nullable|url|max:2048',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:2048',
            'business_address' => 'nullable|array',
            'tax_id' => 'nullable|string|max:100',
            'payout_holder_name' => 'nullable|string|max:255',
            'payout_bank_name' => 'nullable|string|max:255',
            'payout_account_number' => 'nullable|string|max:100',
            'payout_iban' => ['nullable', 'string', 'max:34', new ValidIban],
            'payout_swift_bic' => 'nullable|string|max:11',
            'payout_country' => 'nullable|string|size:2',
        ];
    }
}