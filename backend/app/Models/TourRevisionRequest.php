<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One review/correction conversation on a tour (ADR 0024, design v3 §5).
 *
 * `context` is initial_review (never-public tours), published_review (the
 * approved base is captured) or correction (an administrator's exceptional
 * declared-field correction request). Old requests keep their history and are
 * never silently reused for another revision.
 *
 * @property int $id
 * @property int $tour_id
 * @property string $context
 * @property int|null $base_revision_id
 * @property int|null $revision_id
 * @property string $state
 * @property string $reason
 * @property array<int, array<string, string>> $field_refs
 * @property string|null $response_note
 * @property int|null $response_generation_epoch
 * @property int|null $requested_by
 * @property int|null $responded_by
 * @property Carbon|null $responded_at
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_reason
 * @property string $requested_content_hash
 */
class TourRevisionRequest extends Model
{
    public const CONTEXT_INITIAL_REVIEW = 'initial_review';

    public const CONTEXT_PUBLISHED_REVIEW = 'published_review';

    public const CONTEXT_CORRECTION = 'correction';

    public const CONTEXTS = [
        self::CONTEXT_INITIAL_REVIEW,
        self::CONTEXT_PUBLISHED_REVIEW,
        self::CONTEXT_CORRECTION,
    ];

    public const STATE_OPEN = 'open';

    public const STATE_ANSWERED = 'answered';

    public const STATE_APPROVED = 'approved';

    public const STATE_REJECTED = 'rejected';

    public const STATE_CLOSED = 'closed';

    public const STATE_SUPERSEDED = 'superseded';

    public const STATES = [
        self::STATE_OPEN,
        self::STATE_ANSWERED,
        self::STATE_APPROVED,
        self::STATE_REJECTED,
        self::STATE_CLOSED,
        self::STATE_SUPERSEDED,
    ];

    protected $fillable = [
        'tour_id',
        'context',
        'base_revision_id',
        'revision_id',
        'state',
        'reason',
        'field_refs',
        'response_note',
        'response_generation_epoch',
        'requested_by',
        'responded_by',
        'responded_at',
        'decided_by',
        'decided_at',
        'decision_reason',
        'requested_content_hash',
    ];

    protected $casts = [
        'field_refs' => 'array',
        'responded_at' => 'datetime',
        'decided_at' => 'datetime',
        'response_generation_epoch' => 'integer',
        'tour_id' => 'integer',
        'base_revision_id' => 'integer',
        'revision_id' => 'integer',
    ];

    protected static function booted(): void
    {
        // History rows are append-only except for their own workflow fields,
        // which change only through the locked domain service.
        static::updating(function (TourRevisionRequest $request): void {
            foreach (['tour_id', 'context', 'requested_content_hash'] as $attribute) {
                if ($request->isDirty($attribute)) {
                    throw new LogicException(
                        "tour_revision_requests.{$attribute} is immutable; new work uses a new request."
                    );
                }
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Tour revision requests are never deleted individually.');
        });
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<TourContentRevision, $this> */
    public function baseRevision(): BelongsTo
    {
        return $this->belongsTo(TourContentRevision::class, 'base_revision_id');
    }

    /** @return BelongsTo<TourContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(TourContentRevision::class, 'revision_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('state', self::STATE_OPEN);
    }

    public function scopeForTour(Builder $query, int $tourId): Builder
    {
        return $query->where('tour_id', $tourId);
    }

    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }
}
