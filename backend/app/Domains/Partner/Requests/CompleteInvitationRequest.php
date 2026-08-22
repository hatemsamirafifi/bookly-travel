<?php

namespace App\Domains\Partner\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'business_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'business_address' => ['sometimes', 'nullable', 'array'],
            'business_address.street' => ['required_with:business_address', 'string', 'max:255'],
            'business_address.city' => ['required_with:business_address', 'string', 'max:100'],
            'business_address.postal_code' => ['required_with:business_address', 'string', 'max:20'],
            'business_address.country' => ['required_with:business_address', 'string', 'size:2'],
            'payout_country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }
}
