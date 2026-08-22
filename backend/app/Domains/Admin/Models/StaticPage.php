<?php

namespace App\Domains\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Localized static site page (Spec 013, US9, FR-015, ST-013-012/013).
 *
 * Backs the `static_pages` table. Localized fields (`title`, `body`,
 * `meta_description`) are JSONB keyed by platform locale (`en`/`es`/`it`).
 * Updates/publishes are audited via GovernanceAuditService as `cms.update` /
 * `cms.publish` (actor = `updated_by`). Append-only governance audit rows are
 * linked through the `static_page` morph map.
 */
class StaticPage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $table = 'static_pages';

    protected $fillable = [
        'slug',
        'status',
        'title',
        'body',
        'meta_description',
        'updated_by',
        'published_at',
    ];

    protected $casts = [
        'title' => 'array',
        'body' => 'array',
        'meta_description' => 'array',
        'updated_by' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Localized locales supported by the platform (Spec 013 i18n).
     */
    public const LOCALES = ['en', 'es', 'it'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function governanceAuditLogs(): HasMany
    {
        return $this->hasMany(GovernanceAuditLog::class, 'target_id')
            ->where('target_type', 'static_page');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Resolve the localized content for a single locale, falling back to the
     * first available locale when the requested one is missing.
     */
    public function contentFor(string $locale): CmsContent
    {
        $title = $this->localized($this->title, $locale);
        $body = $this->localized($this->body, $locale);
        $meta = $this->meta_description !== null ? $this->localized($this->meta_description, $locale) : null;

        return new CmsContent($locale, $title, $body, $meta);
    }

    /**
     * Return the value for a locale, falling back to the first available
     * locale's value so a page never renders empty for a supported language.
     */
    private function localized(?array $values, string $locale): ?string
    {
        if (! is_array($values)) {
            return null;
        }
        if (Arr::has($values, $locale) && filled($values[$locale])) {
            return $values[$locale];
        }

        return collect($values)->first(fn ($value) => filled($value));
    }
}
