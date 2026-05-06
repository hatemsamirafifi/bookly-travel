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
        return $this->tours()->where('status', 'published')->count();
    }
}
