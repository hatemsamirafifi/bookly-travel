<?php

namespace App\Domains\CMS\Models;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Blog\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable content version history (Admin 2.0 Phase G, T-G02).
 *
 * One row per version of a `static_page` or `blog_post` snapshot. Entries are
 * append-only: model events reject every `update`/`delete` so history can never
 * be rewritten or pruned from the CMS.
 */
class ContentRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'version_number',
        'snapshot',
        'diff',
        'change_summary',
        'actor_id',
    ];

    protected $casts = [
        'revisionable_id' => 'integer',
        'version_number' => 'integer',
        'snapshot' => 'array',
        'diff' => 'array',
        'actor_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (): bool {
            return false;
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function staticPages(): MorphMany
    {
        return $this->morphMany(StaticPage::class, 'revisionable');
    }

    public function blogPosts(): MorphMany
    {
        return $this->morphMany(BlogPost::class, 'revisionable');
    }
}
