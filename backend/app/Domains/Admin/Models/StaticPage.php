<?php

namespace App\Domains\Admin\Models;

use App\Domains\CMS\Models\ContentRevision;
use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        'meta_title',
        'canonical_url',
        'og_image_media_id',
        'updated_by',
        'scheduled_at',
        'published_at',
    ];

    protected $casts = [
        'title' => 'array',
        'body' => 'array',
        'meta_description' => 'array',
        'meta_title' => 'array',
        'og_image_media_id' => 'integer',
        'updated_by' => 'integer',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    /**
     * Localized locales supported by the platform (Spec 013 i18n).
     */
    public const LOCALES = ['en', 'es', 'it'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_media_id');
    }

    /**
     * Append-only version history (newest first).
     *
     * @return MorphMany<ContentRevision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisionable')
            ->orderByDesc('version_number');
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

    /**
     * Return the exact stored value for a locale, without falling back.
     * Whitespace-only or empty HTML values are treated as missing.
     */
    public function rawContentFor(string $field, string $locale): ?string
    {
        $values = $this->{$field} ?? [];
        if (! is_array($values)) {
            return null;
        }

        $value = $values[$locale] ?? null;

        return is_string($value) && trim(strip_tags($value)) !== '' ? $value : null;
    }

    /**
     * Whether a locale has both non-empty title and body for public rendering.
     */
    public function hasCompleteLocale(string $locale): bool
    {
        if ($locale === 'en') {
            return true;
        }

        return $this->rawContentFor('title', $locale) !== null
            && $this->rawContentFor('body', $locale) !== null;
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
     * Return the value for a locale, falling back to English first and then the
     * first available locale's non-empty value.
     */
    private function localized(?array $values, string $locale): ?string
    {
        if (! is_array($values)) {
            return null;
        }
        if (Arr::has($values, $locale) && is_string($values[$locale]) && trim(strip_tags($values[$locale])) !== '') {
            return $values[$locale];
        }
        if (Arr::has($values, 'en') && is_string($values['en']) && trim(strip_tags($values['en'])) !== '') {
            return $values['en'];
        }

        return collect($values)->first(fn ($value) => is_string($value) && trim(strip_tags($value)) !== '');
    }
}
