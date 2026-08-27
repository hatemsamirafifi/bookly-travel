<?php

namespace App\Domains\Blog\Models;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class BlogPost extends Model
{
    public const LOCALES = ['en', 'es', 'it'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    private const READING_WPM = 200;

    protected $table = 'blog_posts';

    protected $fillable = [
        'slug',
        'status',
        'title',
        'body',
        'excerpt',
        'meta_description',
        'meta_title',
        'cover_image_url',
        'is_featured',
        'scheduled_at',
        'published_at',
        'author_id',
        'blog_category_id',
    ];

    protected $casts = [
        'title' => 'array',
        'body' => 'array',
        'excerpt' => 'array',
        'meta_description' => 'array',
        'meta_title' => 'array',
        'is_featured' => 'boolean',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'author_id' => 'integer',
        'blog_category_id' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function relatedTours(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class, 'blog_post_tours')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function authorProfile(): HasOneThrough
    {
        return $this->hasOneThrough(
            AuthorProfile::class,
            User::class,
            'id', // Foreign key on users table (User::id)
            'user_id', // Foreign key on author_profiles table (AuthorProfile::user_id)
            'author_id', // Local key on blog_posts table (BlogPost::author_id)
            'id' // Local key on users table (User::id)
        );
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function contentFor(string $field, string $locale): ?string
    {
        $values = $this->{$field} ?? [];
        if (! is_array($values)) {
            return null;
        }

        if (isset($values[$locale]) && filled($values[$locale])) {
            return $values[$locale];
        }

        if (isset($values['en']) && filled($values['en'])) {
            return $values['en'];
        }

        return collect($values)->first(fn ($v) => filled($v));
    }

    public function readingTime(string $locale = 'en'): int
    {
        $body = $this->contentFor('body', $locale) ?? '';
        $words = str_word_count(strip_tags($body));

        return max(1, (int) ceil($words / self::READING_WPM));
    }
}
