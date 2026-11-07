<?php

namespace App\Domains\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaUsage extends Model
{
    protected $table = 'media_usages';

    protected $fillable = [
        'media_asset_id',
        'usable_type',
        'usable_id',
        'field_name',
    ];

    protected $casts = [
        'media_asset_id' => 'integer',
        'usable_id' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function usable(): MorphTo
    {
        return $this->morphTo();
    }
}
