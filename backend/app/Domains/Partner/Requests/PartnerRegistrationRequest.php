<?php

namespace App\Domains\Partner\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartnerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // User account fields
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',

            // Partner Profile fields
            'company_name' => 'required|string|max:255',
            'contact_email' => 'required|string|email|max:255',
            'contact_phone' => 'required|string|max:50',
            'business_description' => 'required|string|max:1000',
            'business_address' => 'required|array',
            'business_address.street' => 'required|string|max:255',
            'business_address.city' => 'required|string|max:255',
            'business_address.state' => 'nullable|string|max:255',
            'business_address.postal_code' => 'required|string|max:20',
            'business_address.country' => 'required|string|size:2',
            'tax_id' => 'nullable|string|max:50',
            'payout_country' => 'required|string|size:2',
        ];
    }
}
