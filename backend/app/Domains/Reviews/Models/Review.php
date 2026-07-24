<?php

namespace App\Domains\Reviews\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\ReviewResponse;
use App\Enums\ReviewStatus;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function reviewResponse(): HasOne
    {
        return $this->hasOne(ReviewResponse::class);
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * Guard admin review-moderation transitions (data-model.md §5).
     *
     * Allowed: visible → hidden; hidden → visible; flagged → visible.
     * Reviews are flagged at submission time by the profanity filter
     * (SubmitReviewAction), not via a lifecycle transition from visible.
     * Hide/reinstate recomputes the tour aggregate rating (handled by the
     * moderation actions).
     */
    public function canTransitionTo(ReviewStatus|string $to): bool
    {
        $to = $to instanceof ReviewStatus ? $to->value : $to;

        $allowed = [
            'visible' => ['hidden'],
            'hidden' => ['visible'],
            'flagged' => ['visible'],
        ];

        return in_array($to, $allowed[$this->status] ?? [], true);
    }

    public function canEdit(): bool
    {
        if ($this->edited_at === null) {
            return now()->lessThan($this->created_at->addHours(48));
        }

        return now()->lessThan($this->edited_at->addHours(48));
    }
}
