<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/'
            ],
            'locale' => ['sometimes', 'filled', 'in:en,es,it'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }

    /**
     * Get customized translation messages for form errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'auth.errors.nameRequired',
            'name.max' => 'auth.errors.nameTooLong',
            'email.required' => 'auth.errors.emailRequired',
            'email.email' => 'auth.errors.emailInvalid',
            'email.unique' => 'auth.errors.emailTaken',
            'password.required' => 'auth.errors.passwordRequired',
            'password.min' => 'auth.errors.passwordTooShort',
            'password.regex' => 'auth.errors.passwordWeak',
            'locale.in' => 'auth.errors.localeInvalid',
        ];
    }
}
