<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Travel destination (Admin 2.0, T-B03).
 *
 * Simple CRUD catalog of locational destination cards managed from the
 * Filament `DestinationResource`. Tours are linked to destinations by the
 * tour's `location_slug` matching the destination slug.
 */
class Destination extends Model
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

    /**
     * Tours whose `location_slug` points at this destination.
     *
     * @return HasMany<Tour, $this>
     */
    public function tours()
    {
        return $this->hasMany(Tour::class, 'location_slug', 'slug');
    }

    /**
     * Published tours attached to this destination.
     *
     * @return HasMany<Tour, $this>
     */
    public function publishedTours()
    {
        return $this->tours()->published();
    }

    /**
     * Keep one destination slug per unique name, mirroring Category's
     * de-duplication behavior for seeder-driven CRUD.
     */
    public static function firstOrCreateBySlug(string $name, array $attributes = []): self
    {
        $slug = $attributes['slug'] ?? Str::slug($name);

        return static::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, ...$attributes],
        );
    }

    /**
     * Active destinations in admin-managed display order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('display_order')->orderBy('name');
    }
}
