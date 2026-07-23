<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_url',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function tours()
    {
        return $this->hasMany(Tour::class);
    }

    public function publishedTourCount(): int
    {
        return $this->tours()->published()->count();
    }

    /**
     * Active categories ordered for display, each annotated with a
     * `tours_count` of its bookable (published + valid pricing + upcoming
     * availability) tours. Encapsulates the popular-categories query shared
     * by the homepage, the category listing, and the search facets so the
     * count semantics cannot drift across callers.
     */
    public function scopePopularWithCounts($query, bool $bookableOnly = true)
    {
        return $query
            ->where('is_active', true)
            ->orderBy('display_order')
            ->withCount(['tours' => function ($q) use ($bookableOnly) {
                $bookableOnly ? $q->bookable() : $q->published();
            }]);
    }
}
