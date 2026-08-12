<?php

namespace App\Domains\Admin\Requests;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Services\AdminAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a static-page update submission (Spec 013, US9, FR-015).
 *
 * Localized `title`/`body` are arrays keyed by locale; the `en` locale is
 * always required so a page never renders blank. The slug must be unique
 * across static pages (ignoring the current page on update).
 */
class UpdateStaticPageRequest extends FormRequest
{
    public function authorize(AdminAuthorizationService $authz): bool
    {
        return $this->user() !== null && $authz->can($this->user(), 'manage_cms');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pageId = $this->route('static_page') instanceof StaticPage
            ? $this->route('static_page')->id
            : (int) $this->route('static_page');

        return [
            'slug' => ['sometimes', 'required', 'string', 'max:120',
                'unique:static_pages,slug' . ($pageId ? ",{$pageId}" : '')],
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'body' => ['required', 'array'],
            'body.en' => ['required', 'string'],
            'meta_description' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,published'],
        ];
    }
}
