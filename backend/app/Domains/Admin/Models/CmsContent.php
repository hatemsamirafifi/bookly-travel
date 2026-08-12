<?php

namespace App\Domains\Admin\Models;

/**
 * Immutable localized content value object (Spec 013, US9).
 *
 * Backs a single locale's rendering of a StaticPage (title/body/meta). It is
 * not persisted — `StaticPage::contentFor()` derives it from the page's
 * localized JSONB columns so the public site can render one locale at a time.
 */
final class CmsContent
{
    public function __construct(
        public readonly string $locale,
        public readonly ?string $title,
        public readonly ?string $body,
        public readonly ?string $metaDescription = null,
    ) {}

    public function hasBody(): bool
    {
        return filled($this->body);
    }
}
