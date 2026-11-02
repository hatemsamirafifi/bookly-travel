<?php

namespace App\Domains\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Blog tag (Admin 2.0 Phase G, T-G01): JSONB-localized tag name kept in sync
 * with posts through the `blog_post_tags` pivot.
 */
class BlogTag extends Model
{
    public const LOCALES = ['en', 'es', 'it'];

    protected $table = 'blog_tags';

    protected $fillable = [
        'slug',
        'name',
    ];

    protected $casts = [
        'name' => 'array',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tags');
    }

    /**
     * Exact stored locale value, without falling back.
     */
    public function rawNameFor(string $locale): ?string
    {
        $values = is_array($this->name) ? $this->name : [];
        $value = $values[$locale] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Localized tag name: exact locale first, English fallback, then any value.
     */
    public function contentFor(string $locale): ?string
    {
        $values = is_array($this->name) ? $this->name : [];

        if (isset($values[$locale]) && filled($values[$locale])) {
            return (string) $values[$locale];
        }

        if (isset($values['en']) && filled($values['en'])) {
            return (string) $values['en'];
        }

        return collect($values)->filter(fn ($value) => filled($value))->first();
    }
}
