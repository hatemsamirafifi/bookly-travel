<?php

namespace App\Domains\Reviews\Models;

use App\Domains\Booking\Models\Booking;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $fillable = [
        'booking_id',
        'tour_id',
        'traveler_id',
        'rating',
        'comment',
        'status',
        'locale',
        'edited_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            return false;
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traveler_id');
    }

    public function auditTrails(): HasMany
    {
        return $this->hasMany(ReviewAuditTrail::class);
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function canEdit(): bool
    {
        if ($this->edited_at === null) {
            return now()->lessThan($this->created_at->addHours(48));
        }

        return now()->lessThan($this->edited_at->addHours(48));
    }
}
