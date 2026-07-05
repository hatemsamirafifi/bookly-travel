<?php

namespace App\Domains\Admin\Requests;

use App\Domains\Admin\Actions\UpdateSettingsAction;
use App\Domains\Admin\Services\AdminAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a platform settings update submission (Spec 013, US9, FR-015).
 *
 * The `group` selects which spatie settings class is updated; the remaining
 * fields are validated against the property types declared in data-model.md §4.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(AdminAuthorizationService $authz): bool
    {
        return $this->user() !== null && $authz->can($this->user(), 'manage_settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'group' => ['required', Rule::in(array_keys(UpdateSettingsAction::GROUPS))],
        ];

        return array_merge($rules, $this->groupRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function groupRules(): array
    {
        return match ($this->input('group')) {
            'general' => [
                'site_name' => ['sometimes', 'required', 'string', 'max:120'],
                'site_tagline' => ['nullable', 'string', 'max:160'],
                'support_email' => ['sometimes', 'required', 'email'],
                'default_currency' => ['sometimes', 'required', 'string', 'size:3'],
                'timezone' => ['sometimes', 'required', 'string', 'max:64'],
                'maintenance_mode' => ['sometimes', 'boolean'],
            ],
            'seo' => [
                'default_meta_title' => ['sometimes', 'required', 'string', 'max:120'],
                'default_meta_description' => ['nullable', 'string', 'max:255'],
                'default_og_image' => ['nullable', 'string', 'max:255'],
                'twitter_handle' => ['nullable', 'string', 'max:64'],
                'default_canonical_base' => ['nullable', 'string', 'max:255'],
                'sitemap_enabled' => ['sometimes', 'boolean'],
            ],
            'contact' => [
                'contact_email' => ['sometimes', 'required', 'email'],
                'contact_phone' => ['nullable', 'string', 'max:64'],
                'contact_address' => ['nullable', 'string', 'max:255'],
                'business_hours' => ['nullable', 'string', 'max:255'],
                'social_links' => ['nullable', 'array'],
            ],
            'booking' => [
                'allow_guest_checkout' => ['sometimes', 'boolean'],
                'min_advance_booking_hours' => ['sometimes', 'required', 'integer', 'min:0'],
                'default_booking_window_days' => ['sometimes', 'required', 'integer', 'min:1'],
                'max_guests_per_booking' => ['sometimes', 'required', 'integer', 'min:1'],
                'cancellation_cutoff_hours' => ['sometimes', 'required', 'integer', 'min:0'],
                'auto_complete_after_days' => ['sometimes', 'required', 'integer', 'min:0'],
            ],
            default => [],
        };
    }
}