<?php

namespace App\Domains\Reviews\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReviewAuditTrail extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'review_id',
        'actor_type',
        'actor_id',
        'action',
        'old_rating',
        'new_rating',
        'old_comment',
        'new_comment',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_rating' => 'integer',
            'new_rating' => 'integer',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
